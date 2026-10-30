# Setup wizard REST API (`ohmylms/v1/setup`)

Contract version 1. The plugin is pre-release, so this replaces the old `setup-wizard` API outright; there
is no compatibility layer for the recovered admin. Machine-readable form:
`assets/src/app/features/setup/contract.json`. Server code: `includes/Rest/V1/SetupWizardController.php`
(routes, permissions, schema), `includes/SetupWizard/` (`SetupService` logic, `SetupSteps` step
definitions and schemas, `SetupProgress` storage, `StarterPages`). Also covers
`includes/Rest/V1/SiteSetupController.php` (`ohmylms/v1/site-setup`).

## Audit before

| Severity | Finding |
| --- | --- |
| High | **Any editor could activate any plugin file.** `POST /setup-wizard/activate-addon` took a free-form `slug`, was gated by `edit_posts` and called `activate_plugin()` (the `activate_plugins` check ran inside the callback, after the string was accepted). Removed. |
| High | **Arbitrary option writes.** `PUT /setup-wizard` ran `update_option( $key, $value )` over client-supplied maps (`currency`, `optin`, `payment`, `permalink`, `design`), so an author-level user (`edit_posts`) could write **any option name** with any value, including `siteurl`, `users_can_register` and `default_role`. Replaced by per-step schemas and the settings registry. |
| High | **Personal data sent to third parties.** `CreateContact` posted the administrator's name and email, and the whole wizard answers, to a hard-coded Mailmint webhook and an Appsero endpoint (with a hard-coded API key) and forced `ohmylms_allow_tracking` to `yes`. Deleted; the wizard makes no outbound HTTP request and takes no consent it does not use. |
| High | **Sample course import** (`POST /import-course`) accepted any JSON from `edit_posts` users, read undefined keys (`$course_data['content']`), inserted rows with raw `$wpdb->insert` and could not be repeated safely (duplicates). Removed; course import is a "later" item. |
| Medium | **Weak permissions and a custom nonce.** `edit_posts` for every route; a custom `nonce` header checked with `wp_verify_nonce()` returned a 200 response holding a `WP_Error` (`rest_ensure_response( $error )`) for failures. Now `manage_options` through one constant and core's cookie nonce. |
| Medium | **Re-running changed unrelated things.** Every save called `flush_rewrite_rules( true )` and `save_layout_settings()` re-saved every Design option with its default (resetting archive layout choices). Gone. |
| Medium | **No progress model.** The client posted the entire wizard data blob (`ohmylms_setup_wizard_data`, unvalidated, autoloaded) and a certificate id. Replaced by one small versioned option. |
| Low | `ohmylms_onboarding_*` events did nothing on the server except fire hooks. Kept as hooks, fired once per real transition. |
| Low | `SiteSetupController` validated lengths with `strlen()` in callbacks (bytes, not characters), had no `rest_base`, no schema descriptions and returned bare arrays. Now uses schema `maxLength`, `rest_validate_request_arg` and `rest_ensure_response`. Its permission callbacks (`manage_options` + `edit_theme_options`, `publish_pages` to write) and its preview-first `confirm` behaviour were already sound. |
| OK | `SiteSetup::subjects_page()` and `explore_menu()` are idempotent and never replace content. |

## Storage

One option, `ohmylms_setup_progress` (not autoloaded, under 1 KB), format version 1:

```json
{ "version": 1, "status": "in_progress",
  "steps": { "welcome": "done", "import": "skipped" },
  "answers": { "experience": "beginner", "niche": "tech-skills", "topics": ["Algebra"] },
  "started_at": "2026-10-30T05:33:03Z", "completed_at": null }
```

Unreadable data or another `version` reads as not started. Settings the wizard changes live in their own
options, written **through the settings registry** (`SettingsService::validate()` and `::write()`):
currency fields of the `currency` group and `<key>_page_id` fields of the `general` group. Nothing is
duplicated in the progress option. Obsolete and no longer written: `ohmylms_setup_wizard_data`,
`ohmylms_setup_wizard_certificate_id` (its only reader, `AdminAssets`, defaults to `0`), `ohmylms_allow_tracking`.
The options can be deleted.

## Steps

| Id | Skippable | Input | Effect |
| --- | --- | --- | --- |
| `welcome` | no | none | Starts the wizard. |
| `experience` | no | `experience`: `beginner`, `intermediate`, `experienced` (required) | Stored in the progress. |
| `preferences` | yes | `currency` (`^[A-Z]{3}$`, required, must be a known code), `currency_pos`, `price_num_decimals` (0-8) | Writes the `currency` settings group; unsent fields untouched. |
| `niche` | yes | `niche` (required): `digital-marketing-growth`, `tech-skills`, `creator-economy`, `academic-test-prep`, `other`; `topics`: up to 10 names, 1-60 chars | Stores the answers; creates top-level curriculum items for topics that do not exist (names are reused, nothing is deleted). |
| `pages` | yes | none | Starter pages, see below. Needs `publish_pages` too. |
| `import` | yes, **unavailable** | none | Course migration and SCORM import are not rebuilt; saving answers 409, skipping works. |

Any step can be run again at any time, in any order, also after completion (the status stays
`completed`). The server never requires an order; the UI follows `current_step`.

### Starter pages

Pages come from the filter `ohmylms_default_pages` (defaults: course archive, checkout, dashboard,
profile, courses); only keys that are `<key>_page_id` fields of the `general` settings group are used
(so the `membership` page is ignored; use settings "restore pages" for it). For each page: a configured
**published** page is `kept`; otherwise a **published** page with the expected slug is `adopted` (only the
setting is written, content and title untouched); otherwise a new page is `created`. A draft with the
slug is never touched or published. The page ids are saved together, and if any creation or save fails
the pages created by that request are deleted (`500 ohmylms_setup_pages_failed`). `GET /setup` previews
each page as `ready`, `adoptable` or `missing` without writing.

## Routes

All routes require `manage_options` (`SetupWizardController::CAPABILITY`): 401 anonymous, 403 otherwise.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/setup` | State | 200 state | 401, 403 |
| OPTIONS | `/setup` | JSON schema of the state | 200 | - |
| POST | `/setup/steps/{step}` | Validate, apply, mark done | 200 `{state, result}` | 400, 401, 403, 409, 422, 500 |
| POST | `/setup/steps/{step}/skip` | Skip (a done step stays done) | 200 `{state, result}` | 401, 403, 409 |
| POST | `/setup/complete` | Explicit completion; every step done or skipped | 200 `{state}` | 401, 403, 409, 500 |
| POST | `/setup/dismiss` | Leave without completing; body `{step?}` | 200 `{state}` | 400, 401, 403, 409 |
| POST | `/setup/reset` | Start over (progress only) | 200 `{state}` | 401, 403 |
| GET | `/site-setup/navigation-menus` | Block menus | 200 | 401, 403 |
| POST | `/site-setup/subjects-page` | `{title?, display?, confirm?}` | 200 | 400, 401, 403, 409 |
| POST | `/site-setup/explore-menu` | `{menu_id, label?, confirm?}` | 200 | 400, 401, 403, 404, 409 |

`{step}` is one of the six ids; others answer core's 404 `rest_no_route`. Removed:
`PUT /setup-wizard`, `/setup-wizard/activate-addon`, `/onboarding-started`, `/onboarding-completed`,
`/onboarding-skipped`, `/import-course`.

### State

```json
{ "version": 1, "status": "in_progress", "current_step": "preferences",
  "steps": [ { "id": "welcome", "status": "done", "skippable": false, "available": true } ],
  "answers": { "experience": "beginner", "niche": null, "topics": [] },
  "preferences": { "currency": "USD", "currency_pos": "left", "price_num_decimals": 2 },
  "starter_pages": [ { "key": "checkout", "title": "OhMy Checkout", "slug": "ohmylms-checkout", "page_id": 0, "status": "missing" } ],
  "started_at": "2026-10-30T05:33:03Z", "completed_at": null }
```

`status`: `not_started`, `in_progress`, `completed`, `dismissed`. `current_step` is the first step neither
done nor skipped (`null` when all are resolved). Step `status`: `pending`, `done`, `skipped`.

### Step example

```http
POST /wp-json/ohmylms/v1/setup/steps/preferences
X-WP-Nonce: <wp_rest nonce>
{ "currency": "EUR", "price_num_decimals": 0 }

200 { "state": { ... }, "result": { "changed": ["currency", "price_num_decimals"], "pages": [] } }
```

`result.pages` (pages step) lists `{key, page_id, action: kept|adopted|created}`.

## Errors

Shape `{ "code", "message", "data": { "status", "params": { field: message } } }`.

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param` | Type, enum, range, length or pattern violation, a missing required field, a non-array `topics`. `params` lists every field. |
| 400 | `ohmylms_setup_unknown_field` | A key is not an input of the step (nothing is saved). |
| 400 | `ohmylms_setup_invalid_body` | The body is not a JSON object. |
| 400 | `rest_invalid_json` | Malformed JSON (core). |
| 401 / 403 | `ohmylms_setup_cannot_view` / `ohmylms_setup_cannot_manage` | Not logged in / missing `manage_options`. |
| 403 | `ohmylms_setup_cannot_publish_pages` | Pages step without `publish_pages`. |
| 403 | `rest_cookie_invalid_nonce` | Missing or invalid `X-WP-Nonce` (core). |
| 409 | `ohmylms_setup_step_unavailable` | Saving the `import` step. |
| 409 | `ohmylms_setup_step_required` | Skipping `welcome` or `experience`. |
| 409 | `ohmylms_setup_incomplete` | Completing early; `data.missing` lists the step ids. |
| 409 | `ohmylms_setup_already_completed` | Dismissing a completed wizard. |
| 409 | `ohmylms_block_missing` | Site setup: built block files are absent. |
| 404 | `ohmylms_navigation_missing` | Site setup: unknown menu. |
| 422 | `ohmylms_setup_invalid_value` | Semantic rule failed, for example an unknown currency code. |
| 500 | `ohmylms_setup_save_failed` | The progress option did not store. |
| 500 | `ohmylms_setup_pages_failed` | A starter page or its settings could not be saved; created pages were removed. |

## Hooks

Kept, fired once per real transition: `ohmylms_onboarding_started` (first step of a not-started wizard),
`ohmylms_onboarding_completed`, `ohmylms_onboarding_skipped( $step )` (dismiss). Removed:
`ohmylms_after_accept_consent` (no consent is collected). Settings hooks fire as for the settings API
(`ohmylms_currency_settings_updated`, `ohmylms_settings_updated`). Filter `ohmylms_default_pages` still extends
the starter pages (only general page settings).

## Security notes

- Authorisation: `manage_options` everywhere; CSRF by core's REST nonce. Site setup additionally
  needs `edit_theme_options` and, to write, `publish_pages`.
- Allow-listed inputs only: option names are never client input; unknown fields are rejected.
- No outbound HTTP, no credentials, no secrets in the progress option.
- Nothing is deleted or overwritten: existing pages and curriculum items are kept; the only deletion is
  rolling back pages created by the failing request itself.
- Safe to repeat: every step and `complete` is idempotent.

## Later (not in the wizard)

Course migration from other LMS plugins, SCORM import and sample course import (the `import` step is
skipped with a message), the Design settings (archive layout) and the "subject browsing" site setup UI
(routes exist and are tested; the wizard does not call them yet).

## Admin preview

`admin.php?page=ohmylms-app#/setup`, step in the `step` query parameter (`#/setup?step=preferences`).
