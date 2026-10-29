# Webhooks REST API (`ohmylms/v1/webhooks`)

Contract version 2. The plugin is pre-release, so this replaces the old API outright; there is no
compatibility layer. The machine-readable form is
`assets/src/app/features/webhooks/contract.json`. Server code:
`includes/Rest/V1/WebhookController.php` (routes, schema), `includes/Webhooks/WebhookDelivery.php`
(outbound request), `includes/Webhooks/DeliveryLog.php` (delivery log),
`includes/DataStores/WebhookStore.php` (SQL).

## Audit before

State of the code before this rewrite (`WebhookController` extended the plugin base class but
barely used `WP_REST_Controller`).

| Severity | Finding |
| --- | --- |
| High | **SSRF.** `WebhookSender::send_webhook()` and `Webhook::execute()` used `wp_remote_request()` with redirects on and no URL validation, so an admin could aim webhooks (and any redirect) at `127.0.0.1`, `169.254.169.254` or the LAN. The webhook URL was never validated on save either. |
| High | **No test route.** The UI called `POST /webhooks/test`; nothing was registered, so "Send test" always 404ed. |
| High | **Partial updates reset fields.** `get_webhook_args()` carried `default` values (`status: active`, `http_method: POST`, `data_type: json`) and was used for `PUT`, so any `PUT /webhooks/{id}` silently reset those three fields. |
| High | **404 never returned.** `new Webhook( $id )` throws on a missing row; the catch blocks turned it into a 500 `webhook_error`, so `get`/`update`/`delete` of an unknown id returned 500 with the raw exception message. |
| High | **Bulk action keys.** The server accepted `active`/`inactive`; the UI sent `activate`/`deactivate` (and `bulkWebhookAction` mapped text on the same wrong keys), so bulk status changes failed validation. Unknown ids were skipped silently and exceptions were returned as strings (`"error: ..."`) with HTTP 200. |
| Medium | **No signing.** `WebhookSender` sent no signature. `Webhook::execute()` signed with `wp_hash('ohmylms_webhook_'.$id)`, a value the receiver could not know. There was no per-webhook secret. |
| Medium | **Delivery logged as "success" for any HTTP status** (4xx/5xx included) and never stored; only the `ohmylms_webhook_sent` action existed. No delivery list route. |
| Medium | **GET deliveries did not encode the payload**: `add_query_arg( $payload, $url )` does not URL-encode, so values containing `&`, `#` or spaces corrupted or injected query parameters. |
| Medium | **30 second blocking timeout** in the request that triggered the event (checkout, lesson completion). Now 5 s (`ohmylms_webhook_timeout`). Deliveries are still synchronous (see Security notes). |
| Medium | **Validation.** `trigger_event`, `webhook_url` and `name` were not required or validated (any string, even empty); `data_mapping` was a free string; `per_page` had no maximum (`per_page=100000` allowed) or minimum (`0` produced a division by zero in `X-WP-TotalPages`); `orderby`/`order` were not parameters, and the store passed user text through `sanitize_sql_orderby()` only. |
| Medium | **Permission callbacks** were real (`manage_options` on every route, 401/403 via `rest_authorization_required_code()`), but copy-pasted five times with ad hoc codes (`ohmylms_rest_cannot_view`). `triggers` reused the list check. `bulk` reused `create_item_permissions_check`. |
| Medium | **`data_mapping` format mismatch.** `WebhookSender` read a list of `{key,value}` rows, `Webhook::prepare_webhook_data()` read a `key => source` map; the same stored value behaved differently per code path. |
| Low | `WebhookStore::create()`/`update()`/`delete()` swallowed database errors, so the API answered 201 with `id: 0`. |
| Low | No schema (`get_item_schema()`), no `OPTIONS` support, no `Location` header on 201, no `Link` headers, delete returned `{deleted:true}` only. |
| Low | `WebhooksMigration` built `SHOW TABLES LIKE '$table'` unprepared (`_` is a wildcard) and `Install.php` duplicated the table definition. `Webhooks::define_constants()` called `define()` unguarded. |
| Low | `$wpdb->prepare( '... %i' )` in `WebhookStore::read()` needs WordPress 6.2+. |
| OK | Search used `$wpdb->esc_like()` and prepared statements; list headers `X-WP-Total`/`X-WP-TotalPages` existed. Cookie authentication: WordPress core verifies `X-WP-Nonce` in `rest_cookie_check_errors()` before any permission callback; no custom nonce code is needed. |

## Final routes

All routes require `manage_options` (`WebhookController::CAPABILITY`). Anonymous callers get `401`,
logged-in users without the capability get `403`. Argument validation runs before the permission
callback (core behaviour), so a malformed request can answer 400 before 401/403.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/webhooks` | List (paginated) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/webhooks` | Create | 201 + `Location`; includes `secret` once | 400, 401, 403, 422, 500 |
| GET | `/webhooks/{id}` | Read | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/webhooks/{id}` | Partial update | 200 | 400, 401, 403, 404, 422, 500 |
| DELETE | `/webhooks/{id}` | Delete (and its delivery log) | 200 `{deleted, previous}` | 401, 403, 404, 500 |
| POST | `/webhooks/bulk` | `delete`, `activate`, `deactivate` many | 200 `{action, succeeded, failed}` | 400, 401, 403 |
| GET | `/webhooks/triggers` | Triggers, methods, data types, statuses | 200 | 401, 403 |
| POST | `/webhooks/test` | Send one sample delivery | 200 `{success, ...}` | 400, 401, 403, 404, 422, 429 |
| POST | `/webhooks/{id}/rotate-secret` | New signing secret, shown once | 200 | 401, 403, 404, 500 |
| GET | `/webhooks/{id}/deliveries` | Delivery log, newest first | 200 + `X-WP-Total`, `X-WP-TotalPages` | 400, 401, 403, 404 |

Status toggle is `PATCH /webhooks/{id}` with `{"status": "inactive"}` or the bulk route.

Collection arguments: `page` (>= 1), `per_page` (1-100, default 10), `search` (name, URL or trigger),
`status` (`all|active|inactive`), `trigger_event`, `orderby`
(`id|name|status|trigger_event|created_at|updated_at`, default `created_at`), `order`
(`asc|desc`, default `desc`), `_fields`. A page beyond the last page answers 400
`ohmylms_webhook_invalid_page`. Deliveries accept `page`, `per_page`, `status` (`all|success|failed`).

## Examples

Create:

```http
POST /wp-json/ohmylms/v1/webhooks
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{
  "name": "Enrolment to CRM",
  "trigger_event": "course_enrollment",
  "webhook_url": "https://crm.example.org/hooks/ohmylms",
  "data_mapping": [
    { "key": "email", "value": "user_email" },
    { "key": "course", "value": "course_title" }
  ]
}
```

```http
HTTP/1.1 201 Created
Location: https://example.test/wp-json/ohmylms/v1/webhooks/7

{
  "id": 7,
  "name": "Enrolment to CRM",
  "trigger_event": "course_enrollment",
  "webhook_url": "https://crm.example.org/hooks/ohmylms",
  "http_method": "POST",
  "data_type": "json",
  "data_mapping": [ { "key": "email", "value": "user_email" }, { "key": "course", "value": "course_title" } ],
  "status": "active",
  "secret_hint": "whsec_****k3Zp",
  "last_delivery": null,
  "created_at": "2026-10-30T09:15:00",
  "updated_at": "2026-10-30T09:15:00",
  "secret": "whsec_Qm4V...k3Zp",
  "_links": { "self": [ { "href": ".../webhooks/7" } ], "collection": [ { "href": ".../webhooks" } ], "deliveries": [ { "href": ".../webhooks/7/deliveries" } ] }
}
```

`secret` appears only in the create and rotate-secret responses. Store it then; every later response
has `secret_hint` only.

Partial update (everything not sent is unchanged):

```json
PATCH /webhooks/7
{ "status": "inactive" }
```

Bulk:

```json
POST /webhooks/bulk
{ "action": "deactivate", "ids": [ 7, 8, 999 ] }

200
{ "action": "deactivate", "succeeded": [ 7, 8 ],
  "failed": [ { "id": 999, "code": "ohmylms_webhook_not_found", "message": "Webhook not found." } ] }
```

Test (`id` optional; without it `webhook_url` is required; other fields override for this delivery only):

```json
POST /webhooks/test
{ "id": 7 }

200
{
  "success": true,
  "delivery_id": "0b0a3a2e-5d0a-4b57-9c77-3c4c6a7e1f10",
  "request": { "url": "https://crm.example.org/hooks/ohmylms", "method": "POST", "data_type": "json",
               "event": "course_enrollment", "body": "{\"email\":\"student@example.com\",\"course\":\"Sample Course\"}" },
  "response": { "status_code": 200, "duration_ms": 184, "body": "{\"received\":true}", "truncated": false },
  "error": null
}
```

A remote failure is still `200`: `success: false`, `response.status_code` (0 if none) and
`error: { code: "ohmylms_webhook_http_error" | "ohmylms_webhook_request_failed", message }`.

Delivery row (`GET /webhooks/7/deliveries`):

```json
{ "id": 41, "webhook_id": 7, "delivery_id": "0b0a3a2e-...", "event": "course_enrollment",
  "url": "https://crm.example.org/hooks/ohmylms", "http_method": "POST", "status": "success",
  "response_code": 200, "duration_ms": 184, "error_code": "", "error_message": "",
  "request_body": "{...}", "response_body": "{\"received\":true}", "is_test": true,
  "created_at": "2026-10-30T09:16:02" }
```

## Error codes

Errors use the WordPress shape `{ "code", "message", "data": { "status", ... } }`.

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation (types, enums, lengths, `per_page` > 100, unknown `orderby`, bulk `ids`). `data.params` names the fields. |
| 400 | `ohmylms_webhook_invalid_page` | `page` is past the last page. |
| 400 | `ohmylms_webhook_test_target_required` | Test without `id` and `webhook_url`. |
| 401 / 403 | `ohmylms_webhook_cannot_view` / `_create` / `_edit` / `_delete` | Not logged in (401) or missing `manage_options` (403). |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_webhook_not_found` | Unknown id (also test and deliveries). |
| 422 | `ohmylms_webhook_invalid_url` | Not http(s), credentials in the URL, invalid characters, too long. |
| 422 | `ohmylms_webhook_unsafe_url` | Private, loopback, link-local, reserved or non-standard-port target, or an unresolvable host. |
| 422 | `ohmylms_webhook_invalid_name`, `ohmylms_webhook_invalid_mapping` | Blank name; duplicate mapping keys. |
| 429 | `ohmylms_webhook_test_rate_limited` | More than 10 tests per user per minute. `Retry-After` header and `data.retry_after`. |
| 500 | `ohmylms_webhook_create_failed`, `_update_failed`, `_delete_failed` | Database failure. |

## Schema

Served by `OPTIONS /webhooks` (JSON Schema draft 4, `title: webhook`).

| Property | Type | Notes |
| --- | --- | --- |
| `id` | integer | read only |
| `name` | string 1-255 | required on create |
| `trigger_event` | string | enum from `GET /webhooks/triggers`; required on create |
| `webhook_url` | string uri, max 2048 | required on create; http(s) only |
| `http_method` | `GET\|POST\|PUT\|PATCH\|DELETE` | default `POST` (create) |
| `data_type` | `json\|xml\|form` | default `json` (create) |
| `data_mapping` | array (max 50) of `{ key, value }` | default `[]`; `key` `^[A-Za-z_][A-Za-z0-9_.-]*$`, `value` `^[A-Za-z0-9_.-]+$`, keys unique |
| `status` | `active\|inactive` | default `active` (create) |
| `secret` | string 16-128 | write on create only; echoed on create and rotate-secret only |
| `secret_hint` | string | read only, `whsec_****` + last 4 |
| `last_delivery` | object or null | read only (status, response_code, duration_ms, error, created_at) |
| `created_at`, `updated_at` | string date-time | read only, site timezone |

Defaults are applied on create only. An update never receives schema defaults.

## Security notes

- **Authorisation.** `manage_options` on every route through one constant. CSRF: WordPress core's `rest_cookie_check_errors()` rejects cookie-authenticated requests without a valid `X-WP-Nonce`; the controller adds no weaker path.
- **SSRF.** URLs are validated on save and again before every delivery (`WebhookDelivery::validate_url()`): http/https only, no embedded credentials, `wp_http_validate_url()` (rejects loopback, RFC 1918, link-local/cloud metadata, CGNAT, reserved ranges, ports other than 80/443/8080). Requests use `wp_safe_remote_request()` with `redirection => 0`, so a redirect is reported (`success: false`, status 3xx) and never followed. A site that must target an internal host can opt in with the core `http_request_host_is_external` and `http_allowed_safe_ports` filters. Residual risk: DNS rebinding between the check and the connection (core has the same limitation).
- **Secrets.** Signing needs the secret in plain text, so it is stored unhashed in `{prefix}ohmylms_webhooks.secret`. It is never in list, read, update, delete or log responses (only `secret_hint`); only create and rotate-secret return it. Deliveries carry `X-OhMyLMS-Timestamp`, `X-OhMyLMS-Delivery`, `X-OhMyLMS-Event` and `X-OhMyLMS-Signature: sha256=<hex>` where the digest is `HMAC_SHA256(secret, "{timestamp}.{raw body}")`. Receivers should reject old timestamps and compare digests in constant time. Test deliveries add `X-OhMyLMS-Test: 1`.
- **Test endpoint.** It is a way for an admin to make the server send a request, so it has the SSRF checks above, a 5 s timeout (`ohmylms_webhook_test_timeout`), a 2000 character response excerpt, no response headers, and a rate limit of 10 per user per minute (`ohmylms_webhook_test_rate_limit`, transient based, so best effort on sites with an external object cache that evicts).
- **Stored data.** The delivery log keeps the request body (4000 chars) and response body (2000 chars) of the newest 100 attempts per webhook, deleted with the webhook. Bodies can contain student email addresses and remote content; the UI must render them as text.
- **SQL.** All queries use `$wpdb->prepare()`; `orderby` is an allow-list map, `order` is forced to `ASC`/`DESC`, search is `esc_like()`-escaped.
- **Synchronous delivery.** Event deliveries still run in the request that fires the event (bounded by the 5 s timeout). Move them to Action Scheduler / WP-Cron before enabling webhooks on busy sites.
- **Schema upgrade.** `WebhooksMigration::DB_VERSION` is `2` (adds `secret` and `{prefix}ohmylms_webhook_deliveries`). `ensure_schema()` runs `dbDelta()` the first time any webhook route is used by an administrator, and on `admin_init` when the integration is on. `Install::get_schema()` includes the same SQL.

## Hooks

Unchanged: `ohmylms_webhook_triggers` (filter), `ohmylms_webhook_created`, `ohmylms_webhook_loaded`,
`ohmylms_webhook_updated`, `ohmylms_before_delete_webhook`, `ohmylms_webhook_deleted`,
`ohmylms_webhooks_table_created`, `ohmylms_webhooks_table_dropped`, `ohmylms_should_enable_webhooks`,
`ohmylms_rest_v1_controllers`.

Changed: `ohmylms_webhook_sent` now also fires for test deliveries and its `$log_data` gains
`response_code`, `response_body`, `duration_ms`, `is_test`, `delivery_id`. `status` is `success`
(2xx) or `failed`; it used to be `success`/`error`, and `success` for any HTTP response.
`ohmylms_webhook_loaded` fires for list rows as well.

New: `ohmylms_webhook_request_args` (filter, outgoing `wp_safe_remote_request()` args),
`ohmylms_webhook_sample_payload` (filter), `ohmylms_webhook_timeout`, `ohmylms_webhook_test_timeout`,
`ohmylms_webhook_test_rate_limit` (filters). `Webhook::execute()` is kept and delegates to
`WebhookDelivery`.
