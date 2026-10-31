# Emails REST API (`ohmylms/v1/emails`)

Contract version 2. The plugin is pre-release, so this replaces the old API outright (`GET
/all-emails`, `GET|PUT /email-settings/<id>`); there is no compatibility layer. The machine-readable
form is `assets/src/app/features/emails/contract.json`. Server code:
`includes/Rest/V1/EmailController.php` (routes, schema), `includes/Emails/EmailManager.php` (rules,
storage, preview, test sending), `includes/Emails/Emails.php` (registry, merge tags, global settings
read), `includes/Emails/SampleRecord.php` (sample data for previews) and the sending classes under
`includes/Emails/CreatorsEmail` and `includes/Emails/StudentsEmail`.

The configurable emails are the eleven in `Emails::catalog()`. `EmailVerification` has fixed content
and is not configurable.

## Audit before

| Severity | Finding                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| High     | **The enable toggle did nothing for seven emails.** `creator_new_order`, `creator_cancelled_order`, `student_new_order`, `student_confirm_enrollment`, `student_manual_enrollment`, `student_course_completed` and `student_cancel_enrollment` never read `enable`; switching them off in the UI still sent them. Only the four assignment/quiz emails honoured it.                                                                                             |
| High     | **Permission far too low.** Every route used `current_user_can( 'edit_posts' )`, so any contributor or author could read and rewrite the content of all system emails (including the button link and the recipient list). `update_items_permissions_check()` existed but was never wired.                                                                                                                                                                       |
| High     | **Stored XSS / mail HTML injection.** `PUT /email-settings/<id>` stored the raw JSON body with `update_option()`: any keys, any types, no `wp_kses`. Templates later printed heading, body and footer with `wp_kses_post()`, which allows far more than mail clients render, and `style`/`href` values were unchecked. The generic `PUT /settings/...` route stores `ohmylms_email_*` options with `wp_kses_post()` and no field rules.                         |
| High     | **Mail header injection.** The sender name and address came straight from options into `From: name <address>` in eleven places; a newline in `ohmylms_email_sender_name` (writable through the generic settings route) injected headers such as `Bcc:`. The email subject was stored unsanitised.                                                                                                                                                               |
| High     | **Merge-tag values were injected as raw HTML.** `replace_merge_tags()` and the private `replace_tags()` helpers substituted the student's display name into the rendered HTML unescaped. A student named `<img src=x onerror=...>` put markup in staff emails. The function also replaced the bare words `Student Name` anywhere in the text, plus 30 case/space variants of five tags.                                                                         |
| High     | **Unconfigured sites sent unstyled mail.** `get_email_settings()` returned `''` for unset colors, so templates (`?? 'default'` does not catch `''`) printed `background: ;`, and a missing logo printed `<img src="">`.                                                                                                                                                                                                                                         |
| High     | **`creator_cancelled_order` was sent to the student.** The class used the student's address as recipient and ignored `recipient_email`. `student_cancel_enrollment` read the _creator's_ option (`create_lms_email_creator_cancelled_order`), so editing one email changed the other.                                                                                                                                                                           |
| Medium   | **Tags that never worked.** Default texts used `[store_name]`, `[expected_processing_time]`, `[Your Platform Name]` and `[email@example.com]`; only some spellings were replaced, so the literal text reached students. Subjects of `confirm_enrollment`, `cancel_enrollment`, `creator_cancelled_order` and `course_completed` were never tag-processed. `course_completed` replaced tags with an order that was often missing, so `[student_name]` was blank. |
| Medium   | **Incomplete list.** `/all-emails` listed ten emails; `student_manual_enrollment` could be edited nowhere.                                                                                                                                                                                                                                                                                                                                                      |
| Medium   | **404 returned 500.** `get_item()` for an unknown id did `new $email()` on `false` (fatal). `update_items()` accepted any id and wrote an option for it. Invalid input answered `404 rest_setting_setting_invalid`.                                                                                                                                                                                                                                             |
| Medium   | **No validation or schema.** No argument schema, no limits, `recipient_email` was an untyped array, `button_link` was not validated, `get_public_item_schema()` returned an empty schema. `update_items()` used `get_json_params()` so form-encoded requests silently did nothing. `search` was undocumented and case-sensitive on the title only.                                                                                                              |
| Medium   | **Merge tags were undocumented** and nothing told the admin which tag works in which email.                                                                                                                                                                                                                                                                                                                                                                     |
| Low      | Constructors register hooks: instantiating a class to read its defaults (`new $email()`) registered its trigger a second time, which would send duplicate mail from a request that later fired the event.                                                                                                                                                                                                                                                       |
| Low      | No preview and no test send; the old UI built its preview from a separate hand-written HTML copy of each template.                                                                                                                                                                                                                                                                                                                                              |
| Low      | `get_items()` did `is_wp_error( $settings )` on an array; `update_setting()` returned a `WP_Error` that callers ignored.                                                                                                                                                                                                                                                                                                                                        |
| OK       | Cookie authentication: WordPress core's `rest_cookie_check_errors()` verifies `X-WP-Nonce` before any permission callback; no custom nonce code is needed.                                                                                                                                                                                                                                                                                                      |

## Final routes

All routes require `manage_options` (`EmailController::CAPABILITY`). Anonymous callers get `401`,
logged-in users without the capability get `403`. Argument validation runs before the permission
callback (core behaviour), so a malformed request can answer 400 before 401/403.

| Method             | Path                   | Purpose                                            | Success                               | Errors                            |
| ------------------ | ---------------------- | -------------------------------------------------- | ------------------------------------- | --------------------------------- |
| GET                | `/emails`              | List all configurable emails                       | 200 + `X-WP-Total`, `X-WP-TotalPages` | 400, 401, 403                     |
| GET                | `/emails/settings`     | Global sender, branding and colors                 | 200                                   | 401, 403                          |
| PUT / PATCH / POST | `/emails/settings`     | Partial update of the global settings              | 200                                   | 400, 401, 403, 422                |
| GET                | `/emails/{id}`         | Read one email                                     | 200                                   | 401, 403, 404                     |
| PUT / PATCH / POST | `/emails/{id}`         | Partial update (toggle, subject, text, recipients) | 200                                   | 400, 401, 403, 404, 422           |
| POST               | `/emails/{id}/preview` | Render the real template with sample data          | 200 `{ subject, html, unknown_tags }` | 400, 401, 403, 404, 422           |
| POST               | `/emails/{id}/test`    | Send a sample email to the current administrator   | 200 `{ sent, to, subject }`           | 400, 401, 403, 404, 422, 429, 500 |

`{id}` is one of: `creator_new_order`, `creator_cancelled_order`, `instructor_assignment_submitted`,
`instructor_quiz_submitted`, `student_new_order`, `student_confirm_enrollment`,
`student_manual_enrollment`, `student_course_completed`, `student_cancel_enrollment`,
`student_assignment_graded`, `student_quiz_graded`, `student_at_risk_nudge` (sent by the dashboard's
At-risk learners nudge; tags `student_name`, `sender_name`, `reasons`; no trigger hook). `settings` is registered before the id pattern so the
literal path wins. An update changes only the fields sent; sending none is `400 ohmylms_email_empty_update`.

Collection arguments: `page` (>= 1), `per_page` (1-100, default 20), `search` (case-insensitive in
title, description and subject), `status` (`all|enabled|disabled`), `recipient_type`
(`all|student|creator`), `context`, `_fields`. A page beyond the last answers 400
`ohmylms_email_invalid_page`.

## Merge tags

Write a tag as `{tag_name}` or `[tag_name]`; the name is case-insensitive, letters, digits and
underscores only. Values are HTML-escaped when the text contains markup (bodies, footers) and left
as plain text in subjects. Unknown tags are left as written. Each email lists the tags it supports in
its `tags` field; saving an email that uses any other `{tag}` (or `[snake_case_tag]`) is
`422 ohmylms_email_unknown_tags`. `[Word]` without an underscore is treated as literal text. A preview
does not fail on them: it lists them in `unknown_tags`.

| Tag                        | Value                                                                                   |
| -------------------------- | --------------------------------------------------------------------------------------- |
| `student_name`             | Display name of the student the email is about                                          |
| `course_name`              | Course title                                                                            |
| `quiz_name`                | Quiz title                                                                              |
| `assignment_name`          | Assignment title                                                                        |
| `status`                   | `Passed` or `Failed` (assignment graded)                                                |
| `expected_processing_time` | Refund/payout time; filter `ohmylms_email_expected_processing_time`, default `24 hours` |
| `site_name`, `store_name`  | Site title                                                                              |
| `admin_email`              | `admin_email` option                                                                    |

Per email: `creator_new_order`, `student_new_order`: `student_name`; `creator_cancelled_order`,
`student_cancel_enrollment`: `student_name`, `course_name`, `expected_processing_time`;
`instructor_assignment_submitted`: `student_name`, `course_name`, `assignment_name`;
`instructor_quiz_submitted`, `student_quiz_graded`: `student_name`, `course_name`, `quiz_name`;
`student_confirm_enrollment`, `student_manual_enrollment`, `student_course_completed`:
`student_name`, `course_name`; `student_assignment_graded`: `student_name`, `course_name`,
`assignment_name`, `status`. Every email also has `site_name`, `store_name`, `admin_email`.

## Examples

Read:

```http
GET /wp-json/ohmylms/v1/emails/student_quiz_graded
X-WP-Nonce: <wp_rest nonce>
```

```json
{
	"id": "student_quiz_graded",
	"title": "Notify Student When Quiz Is Graded",
	"description": "Sent to the student after an instructor grades their quiz submission.",
	"recipient_type": "student",
	"enabled": true,
	"subject": "Your quiz {quiz_name} has been graded",
	"heading": "Your Quiz Has Been Graded",
	"additional_content": "<p>Dear {student_name},</p>",
	"button_text": "View My Result",
	"button_link": "",
	"footer_text": "Click the button below to view your quiz result.",
	"course_suggestion_text": null,
	"recipient_email": [],
	"tags": [
		{
			"tag": "student_name",
			"label": "Student name",
			"description": "Display name of the student the email is about."
		}
	],
	"_links": {
		"self": [ { "href": ".../emails/student_quiz_graded" } ],
		"collection": [ { "href": ".../emails" } ]
	}
}
```

Partial update (everything not sent is unchanged; response is the saved email):

```json
PATCH /emails/student_quiz_graded
{ "enabled": false, "subject": "Result for {student_name}" }
```

Preview (the body fields are an unsaved draft applied over the stored email; nothing is stored):

```json
POST /emails/student_quiz_graded/preview
{ "heading": "Draft for {student_name}", "additional_content": "<p>Hi {student_nam}</p>" }

200
{ "subject": "Your quiz Chapter 1 Quiz has been graded",
  "html": "<div class=\"ohmylms-email-container\"><table ...>...</table></div>",
  "unknown_tags": [ "{student_nam}" ] }
```

`html` is a sanitized fragment (the `<body>` content, `wp_kses` with the allow-list below). Show it in
`<iframe sandbox="" srcdoc>`.

Test (same optional draft body; there is deliberately no recipient parameter):

```json
POST /emails/student_quiz_graded/test
{}

200
{ "sent": true, "to": "admin@example.org", "subject": "[Test] Your quiz Chapter 1 Quiz has been graded" }
```

Global settings:

```json
GET /emails/settings
{ "branding_image": "", "base_color": "#6E42D3", "background_color": "#F4F5F7",
  "body_background_color": "#FFFFFF", "body_text_color": "#1F2328", "button_position": "left",
  "sender_name": "", "sender_email": "", "footer_text": "" }

PUT /emails/settings
{ "sender_name": "Example Academy", "sender_email": "academy@example.org", "base_color": "#1a2b3c" }
```

## Error codes

Errors use the WordPress shape `{ "code", "message", "data": { "status", ... } }`.

| HTTP      | Code                                                      | When                                                                                                                                                 |
| --------- | --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| 400       | `rest_invalid_param`                                      | Schema validation: types, enums, `per_page` outside 1-100, empty `subject`/`heading`, a color that is not `#RRGGBB`, a button position outside `left | center | right`. `data.params` names the fields. |
| 400       | `ohmylms_email_invalid_page`                              | `page` is past the last page.                                                                                                                        |
| 400       | `ohmylms_email_empty_update`                              | An update with no editable field.                                                                                                                    |
| 401 / 403 | `ohmylms_email_cannot_view` / `ohmylms_email_cannot_edit` | Not logged in (401) or missing `manage_options` (403). Preview uses _view_; update, settings update and test use _edit_.                             |
| 403       | `rest_cookie_invalid_nonce`                               | Cookie auth without a valid `X-WP-Nonce` (core).                                                                                                     |
| 404       | `ohmylms_email_not_found`                                 | Unknown email id (also preview and test).                                                                                                            |
| 422       | `ohmylms_email_invalid_field`                             | A value fails the rules below. `data.field` names it.                                                                                                |
| 422       | `ohmylms_email_unsupported_field`                         | Recipients on a student email; a course suggestion on an email without one. `data.field`.                                                            |
| 422       | `ohmylms_email_unknown_tags`                              | Unsupported merge tags. `data.tags` lists them (save and test; preview reports them instead).                                                        |
| 422       | `ohmylms_email_no_recipient`                              | The administrator's account has no valid address (test).                                                                                             |
| 429       | `ohmylms_email_test_rate_limited`                         | A test email was sent in the last 30 seconds by this user. `Retry-After` header and `data.retry_after`.                                              |
| 500       | `ohmylms_email_send_failed`                               | `wp_mail()` returned false. The rate limit is not started by a failed send.                                                                          |
| 500       | `ohmylms_email_save_failed`                               | The email could not be read back after saving.                                                                                                       |

## Schema

Served by `OPTIONS /emails/{id}` (`title: ohmylms-email`) and `OPTIONS /emails/settings` (`title:
ohmylms-email-settings`), JSON Schema draft 4. Limits are in characters after sanitizing.

| Property                     | Type                                   | Notes                                                                                                                               |
| ---------------------------- | -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `id`, `title`, `description` | string                                 | read only                                                                                                                           |
| `recipient_type`             | `student\|creator`                     | read only                                                                                                                           |
| `enabled`                    | boolean                                | stored as `enable` in the option the sending class reads                                                                            |
| `subject`                    | string 1-255                           | plain text, one line (tags and newlines are removed)                                                                                |
| `heading`                    | string 1-200                           | plain text, one line                                                                                                                |
| `additional_content`         | string, HTML, max 20000                | must have visible text or an image                                                                                                  |
| `button_text`                | string, max 60                         | plain text; empty hides the button where the template allows it                                                                     |
| `button_link`                | string, max 2048                       | empty or a full `http(s)` URL. Emails that compute their own destination (new order, assignment and quiz notifications) override it |
| `footer_text`                | string, HTML, max 5000                 |                                                                                                                                     |
| `course_suggestion_text`     | string or null, max 5000               | `null` on emails without it (only `student_course_completed` has it)                                                                |
| `recipient_email`            | array of up to 10 addresses            | staff emails only; empty means the default (administrator or course author)                                                         |
| `tags`                       | array of `{ tag, label, description }` | read only                                                                                                                           |

Global settings: `branding_image` (empty or `http(s)` URL), `base_color`, `background_color`,
`body_background_color`, `body_text_color` (`#RRGGBB`, stored upper case), `button_position`
(`left|center|right`), `sender_name` (max 100, no control characters, `<`, `>` or `"`), `sender_email`
(valid address or empty), `footer_text` (HTML, max 1000). The `From` header is only set when both
sender name and address are present. These map to the existing `ohmylms_email_*` options.

## Security notes

- **Authorisation.** `manage_options` on every route through one constant. CSRF: WordPress core's
  `rest_cookie_check_errors()` rejects cookie-authenticated requests without a valid `X-WP-Nonce`.
- **HTML allow-list.** `additional_content`, `footer_text`, the global footer and the preview output pass
  `wp_kses()` with `EmailManager::allowed_html()` (filter `ohmylms_email_allowed_html`): `a`, `img`, `p`,
  `div`, `span`, `br`, `hr`, `strong`, `b`, `em`, `i`, `u`, `s`, `small`, `sub`, `sup`, `ul`, `ol`, `li`,
  `blockquote`, `pre`, `code`, `h1`-`h6`, `table`, `thead`, `tbody`, `tfoot`, `tr`, `td`, `th`,
  `caption` with `style`, `class`, `align`, `dir` plus the attributes a tag needs (`href`, `src`,
  `alt`, `width`, `colspan`, ...). Link protocols are `http`, `https`, `mailto`, `tel`. `script`, `style`,
  `iframe`, `object`, `embed`, `noscript`, `template` and `textarea` elements are removed together with
  their content (kses alone would keep the text). Event attributes, forms and `javascript:` links
  cannot survive. The preview adds only layout CSS properties (`border-radius`, `display`, ...) to
  the safe list while it sanitizes.
- **Plain text fields.** Subject, heading and button text go through `sanitize_text_field()`: tags and
  line breaks are removed, so a subject cannot add a header line.
- **Sender header.** Names and addresses are validated on save, and `Emails::get_email_settings()` cleans
  them again when read, so a value written around the API (for example by the generic settings
  route) still cannot add a header line or break the address.
- **Merge tag values** are escaped for HTML (see above). Tag names are matched with a fixed pattern;
  unknown tags are never evaluated.
- **Test endpoint.** Sends only to `wp_get_current_user()->user_email`; there is no recipient
  parameter and unknown parameters are ignored. Rate limit: one test per user per 30 seconds, kept in a
  transient (best effort on sites with an object cache that evicts). Sample data only; no real
  order, student or course is read. The subject is prefixed `[Test]`.
- **Preview.** Uses the real templates through `ohmylms_get_template()` with `SampleRecord` stand-ins, so
  it exercises the same code as a real send. Nothing is stored or sent. The result is sanitized again
  and meant for a sandboxed iframe.
- **Behaviour change.** `enable` is now honoured by every email. Emails whose default is off
  (`student_confirm_enrollment`, `student_course_completed`, `student_cancel_enrollment`,
  `creator_cancelled_order`) are no longer sent until switched on.

## Hooks

New: `ohmylms_email_updated` (action: `$email_id`, `$item`), `ohmylms_email_allowed_html` (filter),
`ohmylms_email_expected_processing_time` (filter). Unchanged: `ohmylms_email_header`,
`ohmylms_email_footer`, `ohmylms_email_order_details`, `ohmylms_email_order_items`, the trigger actions
the classes listen to, and `ohmylms_rest_v1_controllers`.
