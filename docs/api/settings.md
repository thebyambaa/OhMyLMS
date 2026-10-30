# Settings REST API (`ohmylms/v1/settings`)

Contract version 1. The plugin is pre-release, so this replaces the old generic settings API
outright; there is no compatibility layer for the recovered admin. Machine-readable form:
`assets/src/app/features/settings/contract.json` (generated from the registry). Server code:
`includes/Settings/SettingsRegistry.php` (groups, fields, schema, defaults, one source),
`includes/Settings/SettingsService.php` (validation, storage, hooks),
`includes/Rest/V1/SettingsController.php` (routes, permissions).

Registry groups: General, Branding, Account and privacy, Permalinks, Advanced, Currency and Tax.
Payment gateways have their own routes in this namespace (`PaymentGatewaySettingsController`,
`includes/Settings/PaymentGatewaySettings.php`, see [Payment gateways](#payment-gateways)). The
Design system, Hub icons, xAPI, AI tutor and MCP tabs use their existing routes (see
[Other tabs](#other-tabs-existing-routes)). Emails, Integrations and Migration have their own screens.

## Audit before

State of `SettingsController` (660 lines) and the option usage before this rewrite.

| Severity | Finding |
| --- | --- |
| High | **Gateway secrets sent to the browser.** `GET /settings/payment-gateway` returned `ohmylms_stripe_settings`, `ohmylms_paypal_settings`, `ohmylms_authorize_net_settings`, `ohmylms_qpay_settings` and `ohmylms_offline_settings` (API keys, secrets) in clear text, and the generic writer accepted any value for them. That route is gone; `/settings/payment-gateways` replaces it with write-only credentials. **Still exposed elsewhere:** see [Remaining work](#remaining-work). |
| High | **Tax rates never took effect.** The UI saved `ohmylms_existing_tax_rates` and `ohmylms_new_tax_rates`; the only reader (`TaxService::get_tax_rates()`) reads `ohmylms_tax_rates`, which nothing wrote. Tax rows configured in the UI were ignored at checkout. |
| High | **Hard rewrite flush on every key.** `update_items()` called `flush_rewrite_rules( true )` for every saved option (a full rule rebuild and `.htaccess` write per key, including colours and page ids), inside the request that saves. |
| High | **No type validation.** Every scalar went through `wp_kses_post( trim( stripslashes() ) )`: `ohmylms_courses_per_page = "<b>x"` was stored, numbers became strings, page ids were never checked to exist or be pages, `ohmylms_permalink` skipped sanitising entirely (any array, any depth, slashes, `..`, reserved slugs). Arrays were stored unvalidated. |
| High | **Unknown keys were silently skipped** (`continue`), so typos looked like successful saves; success was `{"success":true}` whatever happened. A filter (`ohmylms_valid_option_keys`) let extensions whitelist arbitrary options for administrators to write. |
| Medium | **Not atomic.** Keys were written one by one with `update_option`, so a failure halfway left a half-saved group; nothing verified the write. |
| Medium | **Single-item route used by nothing safely**: `PUT /settings/{group}/{id}` rewrote `request['value']` through `wp_kses_post( stripslashes() )` (array values fatal) and skipped the key whitelist, so it could write **any option name** the group registered... and `update_option( $request['id'] )` used the URL value, not the registered id. |
| Medium | **`get_group_settings()` wrote on read**: a GET of the general group called `update_option()` to "repair" student page ids. |
| Medium | **Permission callbacks** were real (`manage_options`) but returned `false` (a bare 403 with `rest_forbidden`, no 401 for anonymous callers) and were copy-pasted. `get_args` used `get_endpoint_args_for_item_schema()` with a schema that did not exist. |
| Medium | **Page search** (`/page/search`) returned draft and private pages, an unbounded `limit` (default -1 = all pages) and a map keyed by id; titles of non-published pages were blank. |
| Medium | **Cache cleared more than its own data.** The AJAX cache button also deleted `_site_transient_update_plugins`, wiping WordPress' plugin update data for every plugin, using four-nine `LIKE` clauses with duplicated patterns. |
| Medium | **Branding colours are overridden.** `Tokens::color()` maps `ohmylms_primary_color_scheme`, `heading`, `body_text` and `progress` to the Design system palette (`DesignSystem::get()`), so these Branding options only reach the Bricks course list element. The settings are kept (and validated) but a change does not restyle learner pages until the Design system tab owns them. |
| Medium | **`ohmylms_get_page_id( 'course' )` returns -1** unless the page slug is exactly `ohmylms-all-courses`; selecting another page as course archive has no effect on those readers. Not changed (outside this scope). |
| Low | Defaults disagreed between registration and readers: permalink defaults were `course-lessons`/`course-categories` in `Permalink`, `lessons`/`course-category` in `ohmylms_get_permalink_structure()`; `ohmylms_price_thousand_sep` had no reader default. The registry is now the reference. |
| Low | `ohmylms_countries` and `ohmylms_states` (about 2,200 lines of static data) were stored as options when saved, and returned in every tax GET. They are now served by read-only endpoints from the bundled lists. |
| Low | `ohmylms_nav_my_profile_url`, `ohmylms_nav_my_courses_url`, `ohmylms_debug_mode` and `ohmylms_guest_checkout` have no readers in the plugin (or an inverted meaning for `guest_checkout`) and are not exposed. |
| OK | Storage is one option per setting (small autoloaded rows), not one large serialized blob; `ohmylms_permalink` is a small array. Cookie authentication: core's `rest_cookie_check_errors()` verifies `X-WP-Nonce` before any permission callback. |

## Storage and readers

Option names are unchanged, so no reader needed updating. REST names drop the `ohmylms_` prefix.

| REST field | Option | Stored as |
| --- | --- | --- |
| booleans | same name | `'yes'` / `'no'` |
| `*_page_id` | `ohmylms_*_page_id` | integer (0 = none) |
| `price_num_decimals` | `ohmylms_price_num_decimals` | integer |
| `fallback_tax_rate` | `ohmylms_fallback_tax_rate` | string with two decimals |
| permalink bases | one option `ohmylms_permalink` (array) | `{course_base, ..., membership_base}`; other members such as `assignment_base` are preserved |
| `tax_rates` | `ohmylms_tax_rates` | rows `{country, state, rate, countryWide: '1'\|'0'}` (the shape `TaxService` reads) |
| branding colours | `ohmylms_primary_color_scheme`, `_primary_hover_color_scheme`, `_heading_color_scheme`, `_body_text_color_scheme`, `_body_progress_color_scheme`, `ohmylms_video_player_logo_bg_color` | lower-case `#rrggbb` |

Obsolete and no longer written: `ohmylms_existing_tax_rates`, `ohmylms_new_tax_rates`,
`ohmylms_countries`, `ohmylms_states`. Existing rows can be deleted; nothing reads them but the
unused `TaxService` getters.

## Final routes

Every route requires `manage_options` (`SettingsController::CAPABILITY`): 401 anonymous, 403 for
logged-in users without it. Argument validation can run before the permission callback (core
behaviour), so a malformed query may answer 400 first.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/settings` | List groups (`id`, `label`, `description`, `fields`) | 200 | 401, 403 |
| GET | `/settings/{group}` | Current values | 200 `{group, values, changed: []}` | 401, 403 |
| PUT / PATCH / POST | `/settings/{group}` | Partial update, all-or-nothing | 200 `{group, values, changed}` | 400, 401, 403, 422, 500 |
| OPTIONS | `/settings/{group}` | JSON schema of the group | 200 | - |
| GET | `/settings/pages` | Published pages for pickers | 200 `[{id, title, slug}]` | 400, 401, 403 |
| GET | `/settings/currencies` | `[{code, name, symbol}]` | 200 | 401, 403 |
| GET | `/settings/countries` | `[{code, name}]` | 200 | 401, 403 |
| GET | `/settings/countries/{code}/states` | `[{code, name}]` | 200 | 401, 403, 404 |
| POST | `/settings/general/restore-pages` | Re-create missing default pages | 200 `{restored, values}` | 401, 403, 500 |
| POST | `/settings/advanced/clear-cache` | Delete OhMyLMS transients | 200 `{deleted}` | 401, 403 |
| GET | `/settings/payment-gateways` | Every registered gateway, credentials as `has_value` only | 200 `[gateway]` | 401, 403 |
| GET | `/settings/payment-gateways/{gateway}` | One gateway | 200 `gateway + changed: []` | 401, 403, 404 |
| PUT / PATCH / POST | `/settings/payment-gateways/{gateway}` | Partial update, all-or-nothing | 200 `gateway + changed` | 400, 401, 403, 404, 422, 500 |
| GET | `/integration-status/{names}` | Kept for other admin screens (unchanged) | 200 | 401, 403 |

Groups: `general`, `branding`, `account-and-privacy`, `permalink`, `advanced`, `currency`, `tax`
(other names answer core's 404 `rest_no_route`). PUT and PATCH are both partial: **fields that are
not sent are never touched**. `changed` lists the fields whose value actually changed.

`GET /settings/pages` parameters: `search` (max 100), `per_page` (1-20, default 20), `include`
(up to 20 ids, returns exactly those published pages; used to label saved choices).

## Per-group fields

Defaults apply while an option does not exist. All fields are readable and writable.

### general (pages; integer >= 0, 0 = none; must be an existing published `page`)

`course_page_id`, `checkout_page_id`, `thank_you_page_id`, `student_dashboard_page_id`,
`student_profile_page_id`, `student_courses_page_id`, `registration_page_id`, `terms_page_id`,
`privacy_policy_page_id`, `profile_page_id`. Rule: the course archive, checkout and thank you pages
must differ (422).

### branding

| Field | Type | Default |
| --- | --- | --- |
| `color_preset` | string `^[a-z0-9]+(-[a-z0-9]+)*$`, max 40 | `royal-purple` |
| `primary_color` | `#rgb` / `#rrggbb` (stored `#rrggbb`) | `#6e42d3` |
| `primary_hover_color` | colour | `#1447bc` |
| `heading_color` | colour | `#000d25` |
| `body_text_color` | colour | `#52525b` |
| `progress_color` | colour | `#35bd4c` |
| `video_player_logo` | string, max 2048, `''` or an http(s) URL (`esc_url_raw`) | `''` |
| `video_player_logo_bg_color` | colour | `#6e42d3` |

### account-and-privacy

| Field | Type | Default |
| --- | --- | --- |
| `allow_purchase_without_login` | boolean | `true` |
| `require_email_verification` | boolean | `false` |
| `checkout_phone_field` | `optional\|required\|hidden` | `optional` |
| `privacy_policy_message` | string max 2000, `wp_kses_post` | the stock sentence with `[privacy_policy]` |

### permalink (each: `^[a-z0-9]+([_-][a-z0-9]+)*(/[a-z0-9]+([_-][a-z0-9]+)*)*$`, 1-60)

`course_base` (`ohmylms-courses`), `lesson_base` (`course-lessons`), `quiz_base`
(`course-quizzes`), `category_base` (`course-categories`), `tag_base` (`course-tags`),
`membership_base` (`members`). Rules (422): the six bases are pairwise different; the first segment
must not be `wp-admin`, `wp-json`, `wp-content`, `wp-includes`, `wp-login`, `feed`, `embed`,
`trackback`, `comments`, `search`, `author`, `page`, `attachment` or `admin`.

### advanced

`use_custom_video_player` boolean, default `true`.

### currency

| Field | Type | Default |
| --- | --- | --- |
| `currency` | `^[A-Z]{3}$`, must be a code from `get_ohmylms_currencies()` (422) | `USD` |
| `currency_pos` | `left\|right\|left_space\|right_space` | `left` |
| `price_thousand_sep` | max 1 char, not a digit or `< > & " '`; `''` and `' '` allowed | `,` |
| `price_decimal_sep` | exactly 1 char, same limits | `.` |
| `price_num_decimals` | integer 0-8 | `2` |

Rule (422): the separators differ when the thousands separator is not empty.

### tax

| Field | Type | Default |
| --- | --- | --- |
| `tax_enabled`, `prices_include_tax`, `eu_vat_enabled`, `disable_vat_validation` | boolean | `false` |
| `tax_label` | string 1-50, `sanitize_text_field` | `Tax` |
| `vat_number_label` | string 1-50 | `VAT Number` |
| `fallback_tax_rate` | number 0-100 (two decimals stored) | `0` |
| `tax_rates` | array (max 500) of `{country, state?, rate, country_wide?}` | `[]` |

Tax row: `country` `^[A-Z]{2}$` and a known country (422), `state` code of that country from the
bundled list (422), `rate` 0-100, `country_wide` defaults to `true`. A country-wide row has an empty
state; a state row needs one. `(country, state)` must be unique (422). The whole list is replaced
when `tax_rates` is sent.

## Payment gateways

Gateways and their fields come from the gateway registry (`ecommerce()->gateways()`, filter
`ohmylms_payment_gateways`): each gateway's own `get_settings()['settings_fields']` is the
definition, so add-on gateways appear without changes here. Storage is unchanged: one array option
per gateway, `ohmylms_{gateway}_settings`, read by the gateway classes. Members the API does not know
(for example PayPal's `webhook_id`) are preserved on write.

Field mapping: `switch`/`checkbox` → `toggle` (stored `'yes'`/`'no'`); `text` → `text` (max 200,
`sanitize_text_field`), or `secret` when the definition says `'secret' => true` or the name matches
`secret|transaction_key|signature_key|api_login_id|password|private|token` (max 500, printable ASCII
without spaces, stored byte for byte); `textarea` → `textarea` (max 2000, `wp_kses_post`). The
`test_mode`/`testmode` switch becomes `mode` (`test|live`) and `enabled` is top-level. `title` is
required; `*_email` fields must be valid emails (422). Section headers and unknown input types are
not exposed. Publishable keys and client ids (`publishable_key`, `client_key`, `client_id`) are not
secret and are returned.

Gateway (`GET`):

```json
{ "id": "stripe", "title": "Stripe", "description": "Stripe", "subscription_support": true,
  "enabled": false, "mode": "test",
  "fields": [ { "name": "secret_key", "label": "Live Secret Key", "help": "Enter your Live Secret Key. ...",
                "type": "secret", "mode": "live", "max_length": 500, "required": false, "default": "" } ],
  "values": { "title": "Card", "publishable_key": "pk_live_..." },
  "secrets": { "secret_key": { "has_value": true }, "sandbox_secret_key": { "has_value": false } } }
```

`fields[].mode` says in which mode a field is used (`null`: both). Help texts are plain text.
Responses carry `Cache-Control: no-store`.

Write (`PATCH`); every member is optional and only what is sent changes:

```json
{ "enabled": true, "mode": "live", "values": { "title": "Pay by card" },
  "secrets": { "secret_key": "sk_live_..." },
  "clear_secrets": [ "sandbox_secret_key" ] }
```

- **Credentials are write-only.** No response, error or hook ever contains a stored credential.
- **An empty secret keeps the stored value** (so a form can always send the field).
- **`clear_secrets` removes** stored credentials (names must be secret fields). Sending a new value
  and clearing the same field is 422.
- Unknown top-level keys, unknown field names, a secret under `values` or a non-secret under
  `secrets`, and `mode` for a gateway without a test mode: 400 `ohmylms_settings_unknown_field`.
  Wrong types, lengths or characters: 400 `rest_invalid_param`. Rules (`title` empty, bad email,
  set and clear together): 422 `ohmylms_settings_invalid_value`. Field keys in `data.params` are
  `values.<name>`, `secrets.<name>`, `enabled`, `mode`, `clear_secrets.<name>`.
- Unknown gateway: 404 `ohmylms_settings_gateway_not_found`.
- Everything is validated before the single option write; the write is verified and rolled back on
  failure (500 `ohmylms_settings_save_failed`). `changed` lists `enabled`, `mode` and field names
  whose stored value changed (the same secret again is not a change).
- Hook: `ohmylms_payment_gateway_settings_updated( $gateway_id, $changed_names )` (names only).
  QPay's `update_option_ohmylms_qpay_settings` listener still fires through `update_option()`.

## Other tabs (existing routes)

These routes predate this API and keep their contracts (legacy callers keep working). All require
`manage_options` (MCP also `edit_posts`) and answer 401/403 through the route's permission callback.

| Tab | Routes | Credentials | Change in this round |
| --- | --- | --- | --- |
| Design system | `GET/POST/DELETE /design-system` (`DesignSystemController`) | none | none; invalid fields answer 400 `ohmylms_invalid_design` with `data.fields` (`colors.primary`, `button_radius`, `density`), nothing stored. The response's `on_primary`, `primary_passes`, `white_passes` are shown; the tab also previews the same WCAG formula for unsaved colours. |
| Hub icons | `GET/POST /hub-icons` (`HubIconsController`) | none | none; images are chosen from `/wp/v2/media`, the server keeps only image attachments. |
| xAPI | `GET/POST /xapi/settings`, `POST /xapi/settings/{test,send,retry}` | LRS secret: `has_secret` only; empty keeps | **Added `clear_secret` (boolean)** to `POST /xapi/settings` and `XApi\Settings::save()` so the secret can be removed; constants (`OHMYLMS_XAPI_*`) still win and lock the field. The LRS key (basic-auth user name) is returned as before. |
| AI tutor | `GET/POST /ai`, `POST /ai/models`, `POST /ai/test` | API key sealed (`AI\Secrets`); `has_key`, `key_hint` (last 4 chars), `key_from`; empty keeps, `clear_key` removes | none. `POST /ai` replaces every policy, so the tab always sends the whole form. |
| MCP | `GET /mcp/settings`, `POST /mcp/settings/{openai,anthropic,gemini}` `{action: generate|revoke}` | tokens stored as SHA-256 hashes; a new token is returned once by `generate` | none. |

`tests/php/settings-secrets-integration.php` (same `OHMYLMS_SETTINGS_TEST=1` flag) proves for
gateways, xAPI, AI, MCP and the design system: no stored secret appears in any response (reads,
writes, rejections, denials), an empty secret keeps the value, an explicit clear removes it,
anonymous 401 / subscriber 403 on every route, and rejected requests change no option.

## Errors

Shape `{ "code", "message", "data": { "status", "params": { field: message } } }`.

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `ohmylms_settings_invalid_body` | The body is not a JSON object. |
| 400 | `ohmylms_settings_unknown_field` | A key is not a field of the group (nothing is saved). `params` names them. |
| 400 | `rest_invalid_param` | Type, enum, range, length or pattern violation. `params` lists every offending field. |
| 400 | `rest_invalid_param` | Also for the pages query (`per_page` > 20). |
| 401 / 403 | `ohmylms_settings_cannot_view` / `ohmylms_settings_cannot_edit` | Not logged in (401) or missing `manage_options` (403). |
| 403 | `rest_cookie_invalid_nonce` | Cookie authentication without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_settings_country_not_found` | Unknown country for the states list. |
| 404 | `ohmylms_settings_gateway_not_found` | Unknown payment gateway id. |
| 422 | `ohmylms_settings_invalid_value` | Semantic rule failed: page missing, not a page or not published, colour/URL/currency invalid, rules above, tax row problems. `params` has the field messages. |
| 500 | `ohmylms_settings_save_failed` | An option did not store; earlier writes of the request are rolled back. |
| 500 | `ohmylms_settings_restore_failed` | A default page could not be created. |

Validation happens for every field before the first write, so a rejected request changes nothing.

## Example

```http
PATCH /wp-json/ohmylms/v1/settings/currency
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "price_num_decimals": 3 }

200
{ "group": "currency",
  "values": { "currency": "USD", "currency_pos": "left", "price_thousand_sep": ",",
              "price_decimal_sep": ".", "price_num_decimals": 3 },
  "changed": [ "price_num_decimals" ] }
```

## Security notes

- **Authorisation** is `manage_options` for every route, including the reference lists and actions.
  CSRF: core verifies the REST nonce for cookie authentication; there is no weaker path.
- **Allow-list.** Only registry fields can be written; the registry maps to fixed option names, so a
  request can never name an option. The old `ohmylms_valid_option_keys` filter is gone.
- **Sanitising by type.** Text with `sanitize_text_field`, rich text with `wp_kses_post`, URLs with
  `esc_url_raw` (http/https only), colours normalised by regex, page ids checked with `get_post()`,
  everything else by JSON schema. Output is data (JSON); consumers escape for their own context, for
  example `esc_url( get_option( 'ohmylms_video_player_logo' ) )` in the lesson template.
- **No secrets** are in the registry groups. Payment gateway credentials are write-only (see
  [Payment gateways](#payment-gateways)); they are stored in plain text in their options because the
  gateways send them to the providers (no site-wide encryption layer in this change).
- **Cache action** deletes only `ohmylms_`-prefixed transients (prepared `LIKE`, four patterns).
- **Restore pages** creates pages as the current user and only for missing or unpublished targets.

## Rewrite rules (deferred flush)

Saving permalink changes never flushes rules. When a permalink base changes, `SettingsService`
deletes the `rewrite_rules` option (core's supported lazy flush). The next request that needs rules
rebuilds them once, after every post type is registered, and this plugin's `generate_rewrite_rules`
rules use the new bases. Saves that change nothing do not touch the option. Hosts that write
`.htaccess` rules are unaffected because the rule structure does not change.

## Hooks

Kept: `ohmylms_{group}_settings_updated( $option, $stored_value, $group )` fires per changed field
(group ids are now `general`, `branding`, `account-and-privacy`, `permalink`, `advanced`, `currency`,
`tax`; the old `design` and `payment-gateway` groups are not served here).
New: `ohmylms_settings_updated( $group, $changed_field_names )` once per request with changes, and
`ohmylms_payment_gateway_settings_updated( $gateway_id, $changed_names )` for gateway saves.
`ohmylms_default_pages` (filter) still extends the restore-pages list.
Removed: `ohmylms_valid_option_keys`, `ohmylms_settings-{group}` and the classic `Settings` classes in
`includes/Admin/Settings` are no longer used by the REST API; they remain for the legacy
settings pages until those are retired.

## Remaining work

- **Legacy gateway secret exposure (High, not fixed here).** `includes/Assets/AdminAssets.php`
  localizes `payment_gateways` (`ecommerce()->gateways()->get_payment_gateway_settings()`) into
  `window.ohmylms_params` for the legacy `ohmylms` admin bundle. Each gateway's `get_settings()`
  puts the stored value of every field in `settings_fields[].value`, so Stripe secret keys, PayPal
  client secrets, QPay client secrets and Authorize.Net transaction/signature keys are printed into
  the HTML of every legacy admin page load. `ohmylms_get_payment_gateways_settings()` returns the same
  data to PHP callers. Fix (owner of `includes/Assets`): localize only `id`, `title`, `enabled` or
  build the list from `PaymentGatewaySettings::read()`. The legacy bundle's gateway screens
  (`payment-gateway-settings.js`, `PaymentGatewaysSettings.jsx`, `checkout-page-settings.js`,
  `monetization-settings-tabs.js`) still call the removed `/settings/payment-gateway` route and fail;
  they should be retired in favour of the new tab.
- **Gateway extras not exposed:** checkout order (`ohmylms_gateway_order`), `ohmylms_guest_checkout`,
  `ohmylms_content_protection`, `ohmylms_enable_login`, and Stripe's read-only webhook data.
- **Design settings (layout, archive, quiz design):** no route serves them since the old `design`
  group was removed from the settings API, and their options and readers need an audit like the
  one above before a registry group is added. Not implemented. (Hub icons, also in the legacy
  Design tab, is implemented over `/hub-icons`.)
- **AI tutor validation:** `POST /ai` clamps or replaces invalid values (`provider`, `model`, limits)
  instead of answering 400, and resets policies that are not sent. The tab validates first and sends
  the whole form; a stricter route would change the legacy contract.
- **Stored credentials are plain text** in gateway and xAPI options (AI keys are sealed).

## Admin preview

`admin.php?page=ohmylms-app#/settings` (one registry entry; the tab is the `tab` query parameter,
for example `#/settings?tab=tax`). Tabs: the seven groups, then `payment-gateways`,
`design-system`, `hub-icons`, `xapi`, `ai-tutor` and `mcp`. Each tab loads when first opened,
validates like the server, saves only changed fields (gateways) or the whole form (routes that
require it), shows per-field errors, keeps drafts across tabs and guards unsaved edits (page unload
and in-app links). Credential inputs are password fields that start empty, show only whether a value
is saved (AI: its last four characters) and offer an explicit "Remove the saved …" checkbox. The MCP
token is shown once after it is generated. Page pickers load at most 20 published pages per search.
