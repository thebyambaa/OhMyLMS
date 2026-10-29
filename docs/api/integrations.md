# Integrations REST API (`ohmylms/v1/integrations`)

Contract version 2. The plugin is pre-release, so this replaces the old API outright; there is no
compatibility layer. The machine-readable form is
`assets/src/app/features/integrations/contract.json`. Server code:
`includes/Rest/V1/IntegrationsController.php` (routes, schema, switches),
`includes/Integrations/IntegrationSettings.php` (credential fields, validation, storage) and
`includes/Integrations/Zoom/Includes/Rest/ZoomSettingsController.php` (the older Zoom route, now
secret-free).

## Audit before

State of the code before this rewrite (`IntegrationsController` extended the plugin base class but
used little of `WP_REST_Controller`).

| Severity | Finding |
| --- | --- |
| High | **Secrets returned.** `GET /zoom/settings/credentials` returned `client_secret` and `webhook_secret_token` in clear text to any administrator session, so every UI load put them in the DOM, browser memory and logs. Google/Microsoft sign-in already returned only `has_client_secret`; Zoom did not. |
| High | **Blind overwrite of the whole switch map.** `PUT /integrations` replaced the entire `ohmylms_integrations` option with `$request->get_json_params()`: any key was stored (unknown ids, arbitrary nested data, attacker-chosen structure), values were never type-checked, and a stale client silently reset other add-ons. The saved option is read by many modules (`Addons`, `Rules`, `MCP`, `XApi`). |
| High | **Undefined variable / lost hooks.** After the loop, `ohmylms_integrations_{$sanitized_key}_after_update` used `$sanitized_key` from the last iteration (undefined for an empty body, so the hook name was `ohmylms_integrations__after_update`). Hooks fired for skipped integrations and for no-op writes. |
| High | **Enabling unavailable integrations.** `get_integration_validity()` always answered valid, so `is_valid`/`required_plan` were decorative, and the WP Fusion dependency was enforced only in the browser (`is_wpfusion_active`). A direct API call enabled WP Fusion without the plugin. |
| Medium | **Zoom credentials saved without validation**: no length limits or character checks, an empty `client_secret` overwrote the stored secret, and the three required fields could be blanked through `sanitize_text_field( null )`. |
| Medium | **`DELETE /integrations/{id}`** deleted a switch entry (and any extra keys) outright, which no screen used, with no hook and no `404` for ids that exist only in the manifest. |
| Medium | **Permission callbacks** were real (`manage_options`, 401/403 through `rest_authorization_required_code()`) but copy-pasted with three different codes, and Zoom/Google/Microsoft settings each had their own callback (`admin_permission`, `check_admin_permission`). |
| Medium | **Silent coercion.** Microsoft's `tenant` was replaced by `common` when invalid and the response said success; `is_enable` accepted any value via `absint`. `community` was forced on/off from a constant inside the list response. |
| Low | The list response mixed manifest and option data (`class`, `is_valid`, `required_plan`, stored `is_enable`), had no schema entry for it, no `_fields`/filters and no pagination headers. `get_item_schema()` did not exist (the schema callback pointed at the parent's empty one). |
| Low | `class` (a PHP class name) was stored in the option and returned to the client. |
| Low | `question_bank`, `ai_model` and `skills` were special-cased with `unset()` in three places. They are not in the catalogue, so they are now simply unknown ids. |
| OK | Cookie authentication: WordPress core's `rest_cookie_check_errors()` rejects cookie requests without a valid `X-WP-Nonce` before any permission callback; no custom nonce code is needed. No route made an outbound HTTP request (Google Meet's own OAuth routes do, with `wp_remote_post()` to fixed Google hosts; they are outside this contract). |

## Final routes

All routes require `manage_options` (`IntegrationsController::CAPABILITY`). Anonymous callers get
`401`, logged-in users without the capability get `403`. Argument validation runs before the
permission callback (core behaviour), so a malformed request can answer 400 before 401/403.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/integrations` | Catalogue with switch state | 200 + `X-WP-Total`, `X-WP-TotalPages` (always `1`) | 400, 401, 403 |
| GET | `/integrations/{id}` | One integration | 200 | 401, 403, 404 |
| PATCH / PUT / POST | `/integrations/{id}` | Switch on or off: `{ "enabled": bool }` | 200 integration (`requires_reload`) | 400, 401, 403, 404, 422, 500 |
| GET | `/integrations/{id}/settings` | Credential fields (secrets masked) | 200 | 401, 403, 404 |
| PATCH / PUT / POST | `/integrations/{id}/settings` | Change credential fields | 200 settings | 400, 401, 403, 404, 422 |

Removed: `PUT /integrations` (bulk replace of the option) and `DELETE /integrations/{id}`.

The catalogue is the `ohmylms_integrations` filter (so extensions keep registering cards). The
catalogue is small and fixed, so it is not paginated; the screen filters locally. Collection
arguments: `search` (name or description, max 100), `category` (slug), `status`
(`all|enabled|disabled`), `_fields`. Only ids matching `^[a-z0-9_-]+$` are routed.

Credential settings exist for `zoom`, `google_signin` and `microsoft_signin`
(`configurable: true`). Others answer `404 ohmylms_integration_no_settings`.

## Examples

List item (`GET /integrations`):

```json
{
  "id": "zoom",
  "label": "Zoom",
  "description": "Integrate Zoom to host live, interactive classes.",
  "icon": "https://example.test/wp-content/plugins/OhMyLMS/includes/Integrations/Zoom/Assets/Images/zoom-icon.svg",
  "categories": [ "live-classes" ],
  "dependency": "Requires Cohorts",
  "required_plan": null,
  "enabled": false,
  "available": true,
  "unavailable_reason": null,
  "configurable": true,
  "requires_reload": false,
  "_links": { "self": [ { "href": ".../integrations/zoom" } ], "collection": [ { "href": ".../integrations" } ], "settings": [ { "href": ".../integrations/zoom/settings" } ] }
}
```

Switch:

```json
PATCH /integrations/mcp
{ "enabled": false }

200  { "id": "mcp", "enabled": false, "requires_reload": true, "...": "..." }
```

Enabling something that cannot be enabled:

```json
PATCH /integrations/wpfusion
{ "enabled": true }

422  { "code": "ohmylms_integration_unavailable",
       "message": "Install and activate the WP Fusion Lite plugin to use this integration.",
       "data": { "status": 422 } }
```

Settings (`GET /integrations/zoom/settings`). Secret fields never carry the secret:

```json
{
  "integration": "zoom",
  "configured": true,
  "fields": [
    { "kind": "text", "name": "account_id", "label": "Account ID", "help": "...", "required": true, "max_length": 128, "value": "acct-1" },
    { "kind": "secret", "name": "client_secret", "label": "Client secret", "help": "", "required": true, "max_length": 256, "configured": true, "hint": "****7890" },
    { "kind": "secret", "name": "webhook_secret_token", "label": "Webhook secret token", "help": "...", "required": false, "max_length": 256, "configured": false, "hint": "" }
  ],
  "readonly": [
    { "name": "webhook_url", "label": "Event notification endpoint URL", "help": "...", "value": "https://example.test/wp-json/ohmylms/v1/zoom/webhook" }
  ]
}
```

Saving (everything not sent is kept):

```json
PATCH /integrations/zoom/settings
{ "client_id": "cid-2", "client_secret": "new-secret", "webhook_secret_token": null }
```

`null` clears an optional secret. An empty string is rejected, not treated as "keep".
Validation failure:

```json
422  { "code": "ohmylms_integration_invalid_settings",
       "message": "Some settings are not valid.",
       "data": { "status": 422, "params": { "client_id": "Use at most 128 characters." } } }
```

## Error codes

Errors use the WordPress shape `{ "code", "message", "data": { "status", ... } }`.

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation (`enabled` not boolean/missing, bad `status`, over-long `search`). `data.params` names the fields. |
| 400 | `ohmylms_integration_invalid_body` | Settings body is not a JSON object. |
| 400 | `ohmylms_integration_unknown_setting` | A settings field name the integration does not define (`data.params` lists them). |
| 401 / 403 | `ohmylms_integration_cannot_view` / `ohmylms_integration_cannot_edit` | Not logged in (401) or missing `manage_options` (403). Settings reads use `cannot_view`. |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_integration_not_found` | Id not in the catalogue. |
| 404 | `ohmylms_integration_no_settings` | Integration has no credential settings here. |
| 422 | `ohmylms_integration_unavailable` | Enabling an integration that is not available (`is_valid` false, WP Fusion Lite missing, or the `ohmylms_integration_unavailable_reason` filter). Disabling is always allowed. |
| 422 | `ohmylms_integration_invalid_settings` | A field is blank/required, too long, has bad characters, or a tenant is malformed. Nothing is written unless every submitted field is valid. |
| 500 | `ohmylms_integration_update_failed` | The switch could not be stored. |

## Schema

Served by `OPTIONS /integrations` (JSON Schema draft 4, `title: integration`) and
`OPTIONS /integrations/{id}/settings` (`title: integration-settings`).

| Property | Type | Notes |
| --- | --- | --- |
| `id` | string | read only |
| `label`, `description` | string | read only, markup stripped |
| `icon` | string uri | read only, may be empty |
| `categories` | string[] | read only, slugs |
| `dependency` | string or null | informational ("Requires Cohorts"); not enforced |
| `required_plan` | string or null | read only |
| `enabled` | boolean | the only writable property |
| `available` / `unavailable_reason` | boolean / string or null | read only |
| `configurable` | boolean | read only |
| `requires_reload` | boolean | true only in the response to a switch that changes the admin menu (`gamification`, `zoom`, `community`, `webhooks`, `googlemeet`, `mcp`) |

Storage: the switch map stays in the `ohmylms_integrations` option as `{ id: { is_enable: 0|1 } }`
because other modules read it; extra keys stored for an id are preserved. When a module supplies its
own `is_enable` in the manifest (xAPI, MCP, add-ons) that wins when reading.

## Hooks

Preserved, now fired only when a switch really changes and with the real integration id:
`ohmylms_integrations_before_update( $changes, $current )`, `ohmylms_integration_{id}_updated( $is_enable )`,
`ohmylms_integration_{id}_before_enable( $id )`, the option write, `ohmylms_integration_{id}_after_enable( $id )`,
`ohmylms_addon_activated( $id )`, `ohmylms_integrations_after_update( $saved )` and
`ohmylms_integrations_{id}_after_update( $saved )`. A `content_protection` switch still mirrors
`ohmylms_content_protection` (`yes`/`no`). New: filter `ohmylms_integration_unavailable_reason( $reason, $id )`
and action `ohmylms_integration_settings_updated( $id, $changed_field_names )` (names only, never values).
The catalogue filter `ohmylms_integrations` is unchanged.

## Security notes

- **Authorisation.** `manage_options` on every route through one constant. CSRF: WordPress core's `rest_cookie_check_errors()` rejects cookie-authenticated requests without a valid `X-WP-Nonce`.
- **Secrets are write-only.** Responses carry `configured` and a `hint` (`****` + last 4 characters for secrets of 12 or more characters). The settings hook receives field names only. Secrets are stored byte for byte and therefore validated rather than sanitised: printable ASCII without spaces, 1 to `max_length` characters. They are stored where their consumers read them (Zoom credentials in the administrator's user meta `ohmylms_zoom_api_credentials`, the Zoom webhook token in `ohmylms_zoom_webhook_secret_token`, sign-in credentials in `GoogleAuthService::OPTION_KEY` / `MicrosoftAuthService::OPTION_KEY`), in plain text because the plugin must send them to the provider. A site-wide encryption layer is not part of this change.
- **Legacy Zoom route.** `GET /zoom/settings/credentials` now returns only `has_client_secret` and `has_webhook_secret_token`; `POST` delegates to the same validation, and a blank secret keeps the stored one. Google and Microsoft sign-in `.../settings` routes already masked secrets and are unchanged.
- **Outbound HTTP.** None in this contract.
- **Output.** Labels and descriptions from the catalogue are stripped of markup and icons pass through `esc_url_raw()`; the UI renders them as text.
- **Per-user Zoom credentials.** Zoom reads credentials from the *current user's* meta, so each administrator configures their own and cron or other users cannot use them. This is the existing behaviour and is kept; moving them to a site option is a decision for the owner.
