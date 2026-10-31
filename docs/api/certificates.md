# Certificates REST API (`ohmylms/v1/certificates`)

Contract version 1. The plugin is pre-release, so this replaces the recovered API outright; there is no
compatibility layer and old stored templates are not migrated (an unrecognised `_contents` value reads
as the default template). The machine-readable form is
`assets/src/app/features/certificates/contract.json`. Server code:
`includes/Rest/V1/CertificateController.php` (routes, schema), `includes/Certificates/Template.php`
(model, defaults, validation), `includes/Certificates/TemplateRenderer.php` (server-side HTML),
`includes/DataStores/CertificateStore.php` (storage) and `includes/Data/Certificate.php`.

Scope: certificate **templates** (list, metadata editor, text blocks, images, course assignment, preview),
and since 31 October 2026 **issued certificates** (automatic and manual issuing, revoke, re-issue, the
admin list, public verification and the learner download); see [Issued certificates](#issued-certificates).

## Audit before

State of `CertificateController` (757 lines, extending the plugin base class) before this rewrite.

| Severity | Finding |
| --- | --- |
| High | **Wrong capability.** Every route used `edit_posts` (contributors and authors), and `get_item_permissions_check()` was never wired to the route, so any contributor could create, overwrite, bulk-trash or permanently delete any template and read every template's contents. Now `manage_options` through one constant. |
| High | **Stored XSS.** `html_contents` (arbitrary HTML from the client) was saved with no `kses` and no escaping, and `Ajax::download_certificate()` sends it to the learner browser, where `ohmylms.js` injects it with `innerHTML`. Any `edit_posts` user could run script in every student's browser. `contents` was likewise stored as sent. Now clients cannot send HTML at all: the server renders `_html_contents` itself from the validated template with escaped output. |
| High | **SSRF and arbitrary file write.** `template_thumbnail` made the server `download_url()` any URL (including `http://127.0.0.1/...` and cloud metadata addresses) and sideload the response into the media library. Removed. Images are now only existing media-library attachments chosen by id. |
| High | **No attachment checks.** `thumbnail_id` went straight to `set_post_thumbnail()`, so any post or attachment id could be attached. Now the id must be an image attachment (JPEG, PNG, GIF, WebP; not SVG or PDF) that the user can `edit_post`. |
| High | **Partial bulk delete.** `DELETE /certificates/trash-bulk` trashed ids one by one and returned 400 on the first id that was not a certificate *after* the earlier ids were already trashed, returned 200 for an empty list and 500 for a non-array. Removed; the UI uses single, confirmed deletes. |
| High | **Orphaned and stolen assignments.** `delete_item()` never removed `ohmylms_certificate_relationship` rows, leaving dangling course assignments. Course ids in `courses` were not validated at all (any integer, any post type, strings with SQL-ish content were only protected by `$wpdb->insert` formats). `set_courses()` ran several statements without a transaction. Now ids must be existing courses the user can edit, the whole replacement is atomic, and delete removes the rows. |
| Medium | **`flush_rewrite_rules( true )` on every create** (a hard flush rewriting `.htaccess`). Removed. |
| Medium | **Unvalidated everything else.** `status` accepted any registered post status (`trash`, `private`, ...), `name` had no length limit, `per_page` had no maximum (`per_page=-1` dumped everything) and `per_page=0` produced a division by zero, `orderby` and `meta_key`/`meta_value` (LIKE on any meta key) were user-controlled `WP_Query` arguments, `post_status`/`before`/`after`/`parent` filters were passed through. The `PUT` route reused the collection arguments as its argument list. Now a typed schema, an `orderby` allow-list and a closed set of filters. |
| Medium | **404 and error shapes.** Unknown id answered 404 for read, 400 for update and delete; create returned `new WP_Error( 400, ...)` (a numeric code); update returned `$e->getErrorCode()` of unrelated exceptions. All routes now answer `ohmylms_certificate_not_found` (404) and use consistent codes. A post of another type with the same id is a 404 too. |
| Medium | **Course filter after pagination.** `course_id` filtered the already-paged page in PHP, so `X-WP-Total`, `X-WP-TotalPages` and the rows disagreed. The `course` filter is now part of the query. `X-WP-Total` is correct, an out-of-range page answers 400. |
| Medium | **N+1 queries.** Each listed certificate ran a course query and a `ohmylms_get_course()` per course, each with image lookups. Courses are now loaded for the whole page in one query. |
| Low | `Certificate::get_thumbnail_id()`, `get_slug()` and the `date_created` prop did not match what the store read; `set_contents`/`set_html_contents` returned the store result unvalidated; `update_post_meta()` rewrote `_contents` with its own old value on every save. Cleaned up. |
| Low | No schema (`get_item_schema()`), no `OPTIONS` support, no `Location` header, delete returned only a message, `X-WP-TotalCertificates` was a non-standard header. |
| OK | Cookie authentication: core verifies `X-WP-Nonce` in `rest_cookie_check_errors()` before any permission callback, so the controller needs no nonce code of its own. Database access used `$wpdb->prepare()` / `$wpdb->insert()` formats. |

## Final routes

All routes require `manage_options` (`CertificateController::CAPABILITY`). Anonymous callers get `401`,
logged-in users without the capability get `403`. Argument validation runs before the permission callback
(core behaviour), so a malformed request can answer 400 before 401/403.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/certificates` | List (paged) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/certificates` | Create (`name` required; default layout) | 201 + `Location` | 400, 401, 403, 422, 500 |
| GET | `/certificates/{id}` | Read | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/certificates/{id}` | Partial update, all-or-nothing | 200 | 400, 401, 403, 404, 422, 500 |
| DELETE | `/certificates/{id}` | Delete permanently, with its course assignments | 200 `{deleted, previous}` | 401, 403, 404, 500 |
| POST | `/certificates/{id}/duplicate` | Copy as a draft without courses | 201 + `Location` | 401, 403, 404, 500 |
| GET | `/certificates/options` | Choices, placeholders, limits | 200 | 401, 403 |
| GET | `/certificates/courses` | Assignable courses (search, paged, max 50) | 200 + `X-WP-Total`, `X-WP-TotalPages` | 400, 401, 403 |
| POST | `/certificates/preview` | Server-rendered preview of unsaved template fields; stores nothing | 200 | 400, 401, 403, 422 |

Collection arguments: `page` (>= 1), `per_page` (1-100, default 10), `search` (name and text), `status`
(`all|draft|publish`), `course` (course id), `orderby` (`id|name|status|created_at|updated_at`, default
`created_at`), `order` (`asc|desc`, default `desc`), `_fields`. A page beyond the last page answers 400
`ohmylms_certificate_invalid_page`; an empty result is 200 with `X-WP-Total: 0`.

## Template model

Positions are **validated numeric fields**, not a free-form canvas: each text block and the signature has
`x` (left edge), `y` (top edge) and `width`, all percentages of the page, with `x + width <= 100`. Font
sizes are points (8-120). The same rules run in PHP (`Template::normalize()`) and in the admin app
(`model.ts`).

Placeholders usable in text: `{student_name}`, `{course_name}`, `{date}` (the completion date, else the
issue date), `{site_name}`, `{certificate_code}` (the verification code) (extendable with the
`ohmylms_certificate_placeholders` filter, see Hooks). Any other `{token}` is rejected with
`ohmylms_certificate_unknown_placeholder`; braces are reserved. Text is plain: tags are stripped,
newlines kept, 1-500 characters.

## Examples

Create (only `name` is required; everything else defaults):

```http
POST /wp-json/ohmylms/v1/certificates
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "name": "Course completion" }
```

```http
HTTP/1.1 201 Created
Location: https://example.test/wp-json/ohmylms/v1/certificates/21

{
  "id": 21,
  "name": "Course completion",
  "status": "draft",
  "orientation": "landscape",
  "size": "a4",
  "font_family": "serif",
  "background_color": "#ffffff",
  "background_id": 0,
  "background_url": "",
  "signature": { "image_id": 0, "x": 65, "y": 80, "width": 25 },
  "signature_url": "",
  "blocks": [
    { "id": "title", "text": "Certificate of Completion", "x": 10, "y": 18, "width": 80, "font_size": 40,
      "align": "center", "color": "#1d2327", "bold": true },
    { "id": "student", "text": "{student_name}", "x": 10, "y": 44, "width": 80, "font_size": 34,
      "align": "center", "color": "#1d2327", "bold": true }
  ],
  "course_ids": [],
  "courses": [],
  "course_count": 0,
  "created_at": "2026-10-30T09:15:00Z",
  "updated_at": "2026-10-30T09:15:00Z",
  "_links": { "self": [ { "href": ".../certificates/21" } ], "collection": [ { "href": ".../certificates" } ] }
}
```

(The default template has five blocks; two are shown.)

Partial update (everything not sent is unchanged; `signature` merges field by field, `blocks` and
`course_ids` replace):

```json
PATCH /certificates/21
{
  "status": "publish",
  "background_id": 148,
  "signature": { "image_id": 149, "x": 60 },
  "course_ids": [ 12, 15 ]
}
```

Preview:

```json
POST /certificates/preview
{ "orientation": "portrait", "blocks": [ { "id": "a", "text": "Dear {student_name}", "x": 10, "y": 20, "width": 80, "font_size": 24 } ] }

200
{ "html": "<!DOCTYPE html>...", "width_mm": 210, "height_mm": 297,
  "values": { "{student_name}": "Alex Morgan", "{course_name}": "Introduction to Learning", "{date}": "October 30, 2026", "{site_name}": "My Site" } }
```

Validation failure (422 carries the field or block at fault):

```json
{ "code": "ohmylms_certificate_unknown_placeholder",
  "message": "Unknown placeholder: {first_name}",
  "data": { "status": 422, "field": "a", "block_id": "a", "placeholders": [ "{first_name}" ] } }
```

## Error codes

Errors use the WordPress shape `{ "code", "message", "data": { "status", ... } }`.

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation: types, enums, lengths, ranges, hex colors, unknown keys in `signature` or a block, more than 20 blocks, `per_page` > 100, unknown `orderby`. `data.params` names the fields. |
| 400 | `ohmylms_certificate_invalid_page` | `page` is past the last page. |
| 401 / 403 | `ohmylms_certificate_cannot_view` / `_create` / `_edit` / `_delete` | Not logged in (401) or missing `manage_options` (403). |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_certificate_not_found` | Unknown id, trashed, or an id of another post type. |
| 422 | `ohmylms_certificate_invalid_name` | Blank after stripping tags, or over 200 characters. |
| 422 | `ohmylms_certificate_invalid_option`, `_invalid_color` | Unsupported orientation, size, font, alignment or color (defence in depth behind the schema). |
| 422 | `ohmylms_certificate_invalid_image` | `background_id` or `signature.image_id` is not an image attachment of a supported type that the user can edit. |
| 422 | `ohmylms_certificate_invalid_geometry`, `_block_out_of_bounds` | Number out of range, or `x + width` over 100. `data.block_id` or `data.field` is set. |
| 422 | `ohmylms_certificate_invalid_blocks`, `_duplicate_block`, `_invalid_text` | Malformed block, duplicate id, empty or over-long text after stripping tags. |
| 422 | `ohmylms_certificate_unknown_placeholder` | A `{token}` that is not registered. `data.placeholders` lists them. |
| 422 | `ohmylms_certificate_invalid_courses` | A course id does not exist, is not a course, or cannot be edited by the user; more than 200 courses. `data.course_ids` lists the bad ids. |
| 500 | `ohmylms_certificate_save_failed`, `_courses_failed`, `_delete_failed` | Database failure (the course replacement is rolled back). |

A rejected create or update changes nothing: all fields, images and courses are validated before the first
write.

## Schema

Served by `OPTIONS /certificates` (JSON Schema draft 4, `title: certificate`).

| Property | Type | Notes |
| --- | --- | --- |
| `id` | integer | read only |
| `name` | string 1-200 | required on create |
| `status` | `draft\|publish` | default `draft` (create) |
| `orientation` | `landscape\|portrait` | default `landscape` (create) |
| `size` | `a4\|letter` | default `a4` (create); dimensions from `GET /certificates/options` |
| `font_family` | `serif\|sans-serif` | default `serif` (create); generic families, nothing is downloaded |
| `background_color` | `#rrggbb` | default `#ffffff` (create) |
| `background_id` | integer >= 0 | attachment id, 0 for none |
| `background_url`, `signature_url` | string | read only, media URL or empty |
| `signature` | `{ image_id, x, y, width }` | merged field by field |
| `blocks` | array (max 20) of text blocks | replaces all blocks; default blocks on create |
| `course_ids` | integer[] (max 200) | replaces the assignments; a course moves from any other template |
| `courses`, `course_count` | read only | `[{id, title}]` and its length |
| `created_at`, `updated_at` | string date-time | read only, UTC with `Z` |

Defaults are applied on create only; an update never receives schema defaults.

## Storage

- Post type `ohmylms-certificate`; title = `name`; status `draft` or `publish`.
- `_contents` post meta: the canonical template (version 1: orientation, size, font family, background
  color and id, signature, blocks).
- `_html_contents` post meta: HTML rendered by the server from `_contents` on every save, with the
  placeholders still as tokens. Clients can never write it.
- Table `{prefix}ohmylms_certificate_relationship (course_id, certificate_id)`: a course uses one template.

## Security notes

- **Authorisation.** `manage_options` on every route through one constant. CSRF: core's
  `rest_cookie_check_errors()` rejects cookie-authenticated requests without a valid `X-WP-Nonce`.
- **Output.** `TemplateRenderer` escapes every text with `esc_html()` after placeholder replacement, every
  URL with `esc_url()`, and builds `style` attributes only from numbers formatted by `number_format()`,
  `#rrggbb` colors and enumerations re-checked against allow-lists. No script, external stylesheet, font or
  link is emitted. Background and signature are `<img>` elements, never CSS `url()`.
- **Preview.** The preview route returns a full document with
  `Content-Security-Policy: default-src 'none'; img-src http: https: data:; style-src 'unsafe-inline'`.
  The admin app shows it only in `<iframe sandbox="" srcdoc>`, so even a future renderer bug cannot run
  script or reach the admin session.
- **Images.** Only attachment ids; the server never fetches a URL or reads a path (no SSRF, no path
  traversal). SVG is excluded because it can carry script.
- **SQL.** All queries use `$wpdb->prepare()` (including the `IN (...)` list, built from `absint()` ids),
  `$wpdb->delete()`/`insert()` formats or `WP_Query`; `orderby` is an allow-list map.
- **Learner download.** The legacy `Ajax::download_certificate*()` handlers were removed (see
  [Issued certificates](#issued-certificates)); downloads render an issued certificate on the server.

## Hooks

Kept: `ohmylms_rest_insert_certificate` (action, `$post`, `$request`; after create and duplicate),
`ohmylms_rest_delete_certificate` (action, `$id`), `ohmylms_rest_prepare_certificate` (filter),
`ohmylms_rest_ohmylms_certificate_query` (filter, `WP_Query` args and request),
`ohmylms_new_certificate_data`, `ohmylms_after_creating_new_certificate`, `ohmylms_update_certificate`,
`ohmylms_after_deleting_a_certificate`, `ohmylms_before_certificate_object_save`,
`ohmylms_after_certificate_object_save`, and the `extension_settings` field of the extension API.

New: `ohmylms_certificate_placeholders` (filter): add or remove placeholders, shape
`'{token}' => array( 'label' => ..., 'sample' => ... )`; tokens must match `{[a-z][a-z0-9_]*}`.

Removed with the recovered API: the `creatolr_lms_rest_query_var-*` and `ohmylms_rest_query_vars` filters
(the query no longer takes arbitrary query vars), and the `trash-bulk` route.

## Issued certificates

Server code: `includes/Certificates/Issuer.php` (issue, revoke, re-issue, reads),
`includes/Certificates/IssuedSchema.php` (table), `includes/Certificates/Download.php` (learner download and
public verification pages), `includes/Certificates/PdfRenderer.php` (PDF through Dompdf, a Composer dependency),
`includes/Certificates/Bootstrap.php` (hooks) and `includes/Rest/V1/IssuedCertificatesController.php`.
Admin screen: `features/certificates/IssuedCertificatesList.tsx` at `/certificates?list=issued`
(`page=ohmylms-engagement&tab=gamification&section=certificates&list=issued`), reached from the template
list's **Issued certificates** title action. Learner: the student hub's Achievements section
(`templates/student-hub/achievements.php`), the profile course card and the completion e-mail.

### Audit before (legacy download)

| Severity | Finding |
| --- | --- |
| High | **Forged links leak any learner's name and completion date.** `wp_ajax_nopriv_ohmylms_download_certificate_from_email` decrypted `certificate_data` with the key `ohmylms-certificate-key`, hard-coded in the open-source e-mail template and handler, so anyone could encrypt `student_id=<any>&course_id=<any>&certificate_id=<any>` and receive that learner's name, the course name and the end date, without signing in (the nonce is printed on public pages). Removed; old links now redirect to the learner's certificates. |
| High | **No issued record, nothing to verify.** A "certificate" was the template HTML plus names filled in by browser JavaScript (html2pdf); there was no record of who received what, no code, no revocation and no verification. |
| Medium | **Any template for any course.** `wp_ajax_ohmylms_download_certificate` returned the HTML of whatever `certificate_id` was posted, with no check that the learner completed the course or that the template belonged to it; a fatal error for an unknown id. |
| Medium | **Placeholders never replaced.** The browser replaced the old sample strings (`Name Surname`, `[course_name]`), not the `{tokens}` the rebuilt templates use, so downloads showed raw tokens. |
| Low | The achievements template printed `esc_attr()` of a certificate object as `data-certificate-id`, so its download button could not work. |

### Model

Table `{prefix}ohmylms_issued_certificates`, installed on first use (option
`ohmylms_issued_certificates_schema`). One row per certificate: `code` (unique), `user_id`, `course_id`,
`certificate_id` (template), `status` (`issued` or `revoked`), `source` (`completion`, `manual`, `reissue`),
`student_name` and `course_title` as printed, `template` (the template JSON at issue time), `completed_at`,
`issued_at`, `issued_by` (0 for automatic), `revoked_at`, `revoked_by`, `replaces` (the re-issued row).
`active_key` (`<user>:<course>`) is set only while the row is issued and is unique, so the database allows at
most one valid certificate per learner and course; revoked rows are kept for verification.

- **Codes**: 16 characters from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` in four groups (`JZAF-PBCJ-NJU8-GJ89`),
  80 random bits from `random_int()`. Lookups ignore case, spaces and dashes.
- **Automatic issuing**: on `ohmylms_course_completed` (priority 5, before the completion e-mail at 10) when the
  enrolment is really completed (read fresh), the course's assigned template is **published**, and the learner
  has **no** certificate row for the course (a revoked certificate is not re-issued by completing again). A
  failure never breaks the completion. The Achievements page backfills completions from before this feature
  (`Issuer::sync_for_user()`, at most 50 courses, same rules).
- **Snapshots**: name, course title, template and completion date are frozen at issue time; renaming the course,
  editing or deleting the template later does not change an issued certificate. **Re-issue** creates a new row
  and code from the current name, title and course template (the old template if the course has none), keeps
  the completion date and revokes the old row if it was still valid.

### Routes

All administration routes need `manage_options` (`Issuer::CAPABILITY`): 401 anonymous, 403
`ohmylms_issued_certificate_forbidden` otherwise. Responses carry `Cache-Control: private, no-store`.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/issued-certificates` | List: `page`, `per_page` (1–100, default 20), `search` (≤ 100: exact code, learner name, e-mail or login, course title), `status` (`all\|issued\|revoked`), `source` (`''\|completion\|manual\|reissue`), `course`, `certificate`, `user`, `orderby` (`issued_at\|id\|student_name\|course_title`), `order` | 200 + `X-WP-Total`, `X-WP-TotalPages` | 400 (`ohmylms_issued_certificate_invalid_page` past the end), 401, 403 |
| POST | `/issued-certificates` | Issue by hand: `user_id`, `course_id`, `certificate_id` (0 = the course's template; draft templates allowed). Completion not required. | 201 + `Location` | 400 (unknown field), 401, 403, 409 `ohmylms_certificate_already_issued` (`data.id`), 422 `ohmylms_certificate_invalid_user\|_invalid_course\|_no_template\|_invalid_template` |
| GET | `/issued-certificates/{id}` | Read | 200 | 401, 403, 404 `ohmylms_issued_certificate_not_found` |
| POST | `/issued-certificates/{id}/revoke` | Revoke (no body) | 200 | 400, 401, 403, 404, 409 `ohmylms_certificate_already_revoked` |
| POST | `/issued-certificates/{id}/reissue` | Re-issue (no body) | 201 + `Location` | 400, 401, 403, 404, 409 `ohmylms_certificate_already_issued` (a newer one is valid), `_no_template`, 500 |
| GET | `/issued-certificates/learners` | Learner search for the issue form (`search`, ≤ 20 results: id, name, e-mail) | 200 | 401, 403 |
| GET | `/issued-certificates/verify/{code}` | **Public** verification | 200 | 404 `ohmylms_certificate_code_not_found`, 429 `ohmylms_certificate_verify_throttled` |

Admin item: `id, code, status, source, student_name, learner {id, exists, email}, course {id, title, exists},
template {id, name ('' when deleted)}, completed_at, issued_at, revoked_at (RFC 3339 UTC or null), issued_by,
revoked_by, replaces, links {pdf, print, verify}` (`pdf`/`print` empty when revoked). Filter
`ohmylms_rest_prepare_issued_certificate` ($data, $record).

Verification answers only `code, status, student_name, course_title, completed_on, issued_on, revoked_on,
valid, page_url` (dates formatted in the site's format): no user or course id, e-mail or template.

### Learner download and verification pages

Answered on `template_redirect` (priority 1) at the home URL, before the theme:

- `?ohmylms-download-certificate=<code>&format=pdf` — the PDF (`Content-Disposition: attachment`), rendered by
  `PdfRenderer` from the snapshot with real values; `format=print` — a page at paper size (`@page` size of the
  template, no margins) with **Print or save as PDF**, rendered by `TemplateRenderer::fragment()`. Only the
  learner and administrators; anonymous visitors are redirected to sign in, anyone else gets 404, a revoked
  certificate 410. `no-store`, `noindex`, `Referrer-Policy: no-referrer`; the print page's CSP allows only its
  own nonce script and images.
- `?ohmylms-verify-certificate=<code>` — public page with the status (valid, or revoked on a date), name,
  course, dates and code, plus a form to check another code. CSP `default-src 'none'`, `noindex`. Failed
  lookups are limited to 30 per address per 10 minutes (shared with the REST route).
- `?ohmylms-certificate-data=…` (links in completion e-mails sent before this change) redirects to the learner's
  certificates (or sign-in) without decrypting anything.

The PDF uses millimetres and points (Dompdf has no container query units), DejaVu Serif/Sans for Unicode,
`isRemoteEnabled` off, `chroot` limited to the uploads folder and Dompdf's own fonts; background and signature
images are read from their media-library files only when they are inside the uploads folder. Text is escaped
exactly as in `TemplateRenderer`.

### Hooks

`ohmylms_certificate_issued` (action, $record; automatic, manual and re-issue), `ohmylms_certificate_revoked`
(action, $record; also for the row a re-issue replaces), `ohmylms_certificate_values` (filter, token => text,
$record: values for placeholders added with `ohmylms_certificate_placeholders`),
`ohmylms_rest_prepare_issued_certificate` (filter). Removed: the AJAX actions `ohmylms_download_certificate` and
`ohmylms_download_certificate_from_email`.

### Tests

`tests/php/issued-certificates-integration.php` (`OHMYLMS_CERTIFICATES_TEST=1`, 107 checks): hook wiring, routes,
401/403 on every admin route, automatic issuing (not before completion, not for draft or missing templates, once
only, never after a revocation), code format, snapshots surviving course and template edits and template deletion,
placeholder values, the printable fragment and a one-page PDF, escaping in the PDF markup, download access
(learner, other learner, anonymous, administrator), verification (case and dashes ignored, only public fields,
unknown and malformed codes), list search and filters, validation, manual issue, 409 duplicates, revoke, re-issue
(new code, current title and template, completion date kept), the dashboard backfill, and over HTTP the
verification page headers, the sign-in redirect and the old e-mail link redirect. App tests:
`issuedApi.test.ts`, `IssuedCertificatesList.test.tsx`.

## Not covered

- **Bulk issuing** (for every learner who completed a course before a template was assigned) exists only as the
  per-learner backfill on the Achievements page; there is no admin bulk action.
- **Learner list in the app**: learners see their certificates in the PHP student hub; there is no learner REST
  route.
- **Free-form canvas.** Positions are numeric fields by design.
- **Media upload from the editor.** The editor picks existing library images through core's `/wp/v2/media`.
- **Bulk operations**, concurrent-edit detection (last write wins) and revision history.
