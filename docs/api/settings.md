# Settings REST API (`ohmylms/v1/settings`)

Contract version 1. The plugin is pre-release, so this replaces the old generic settings API
outright; there is no compatibility layer for the recovered admin. Machine-readable form:
`assets/src/app/features/settings/contract.json` (generated from the registry). Server code:
`includes/Settings/SettingsRegistry.php` (groups, fields, schema, defaults, one source),
`includes/Settings/SettingsService.php` (validation, storage, hooks),
`includes/Rest/V1/SettingsController.php` (routes, permissions).

Scope of this round: General, Branding, Account and privacy, Permalinks, Advanced, Currency and Tax.
Payment gateways, Emails, Integrations, Design system, xAPI, AI tools and Migration are not covered.

## Audit before

State of `SettingsController` (660 lines) and the option usage before this rewrite.

| Severity | Finding |
| --- | --- |
| High | **Gateway secrets sent to the browser.** `GET /settings/payment-gateway` returned `ohmylms_stripe_settings`, `ohmylms_paypal_settings`, `ohmylms_authorize_net_settings`, `ohmylms_qpay_settings` and `ohmylms_offline_settings` (API keys, secrets) in clear text, and the generic writer accepted any value for them. Still true for the payment-gateway group, which this round does not touch: see Out of scope. |
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
- **No secrets** are in these groups; the registry has no credential fields. The payment-gateway
  group is not served by this controller (see below).
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
New: `ohmylms_settings_updated( $group, $changed_field_names )` once per request with changes.
`ohmylms_default_pages` (filter) still extends the restore-pages list.
Removed: `ohmylms_valid_option_keys`, `ohmylms_settings-{group}` and the classic `Settings` classes in
`includes/Admin/Settings` are no longer used by the REST API; they remain for the legacy
settings pages until those are retired.

## Out of scope this round

- **Payment gateways** (Stripe, PayPal, Authorize.Net, QPay, offline): `ohmylms_{gateway}_settings`
  options hold API keys. The previous API returned them to the browser. They need write-only secret
  fields (configured flag plus hint, as in `docs/api/integrations.md`) and separate routes before
  they are rebuilt. `ohmylms_guest_checkout`, `ohmylms_content_protection` and `ohmylms_enable_login`
  live with them.
- Design (layout, archive and quiz colours), Emails, Integrations, Design system, xAPI, AI tools and
  Migration tabs.

## Admin preview

`admin.php?page=ohmylms-app#/settings` (one registry entry; the tab is the `tab` query parameter,
for example `#/settings?tab=tax`). Each tab loads when first opened, validates like the server,
saves only changed fields, shows per-field errors, keeps drafts across tabs and guards unsaved edits
(page unload and in-app links). Page pickers load at most 20 published pages per search.
