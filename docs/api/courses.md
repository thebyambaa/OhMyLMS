# Courses REST API (`ohmylms/v1/courses`)

Contract version 1. The plugin is pre-release, so this replaces the old course CRUD outright; there is
no compatibility layer. Machine-readable form: `assets/src/app/features/courses/contract.json`. Server
code: `includes/Rest/V1/CourseController.php`. No meta key was renamed (see "Storage"), so templates,
shortcodes, blocks and the learner experience read the same data as before.

## Audit before

| Severity | Finding |
| --- | --- |
| High | **Flat `edit_posts` on every route.** Any author or contributor could update, trash, delete, duplicate, enrol students in, and tag any course by id, including other authors' drafts. `get_item_permissions_check` ran `read_post` only when the post existed and returned `true` for unknown ids. |
| High | **`GET /courses/{id}` and the list leaked** drafts, content, `password_protected` (the post password in clear text) and author data to every `edit_posts` user. The list skipped unreadable rows after paging, so counts and pages were wrong. |
| High | **Raw query forwarding.** `get_items` merged a client `filter` array into `WP_Query` args and passed `meta_query`, `tax_query`, `post_parent*`, `offset` and a `LIKE` match on `_price_type` through a "valid vars" list; `per_page` had no maximum; `orderby` accepted anything. |
| High | **Enrolment, import, export and term routes** used `edit_posts`: a contributor could create user accounts (`enroll-by-email`), enrol any user, import SCORM/JSON, export any course, and `assign_terms_to_course` set any taxonomy on any post id. |
| High | **No validation.** Prices, dates, level, capacity, access type and status were stored as sent (status through `get_post_status_object`, so `trash`, `inherit` or any registered status). `status` could publish without `publish_posts`. A sale above the regular price was accepted. |
| High | **Not atomic, wrong errors.** `create_item` returned `new WP_Error( 400, ... )` (numeric code); unknown ids answered 400/404 inconsistently; post, meta, status/password and cohort writes ran as separate saves, so a failure left a half-updated course. |
| High | **Store defects hit by every save.** `CourseStore::update()` wipes `post_excerpt` (the prop is never read), re-slugs any slug that merely contains "untitled", and rewrites the post date. Sale dates never influenced `_price`, so a scheduled sale was charged immediately. |
| Medium | **N+1 queries and an unbounded count.** Each row built a full `Course` object with chapters, per-chapter lessons, curriculum, bundles and certificate; every list request also ran an unbounded `posts_per_page = -1` query only to report `X-WP-TotalCourses`. Enrolment counts were one query per row. `enrollment` ordering sorted one page in PHP. |
| Medium | **Trash bulk** trashed ids without a per-id check and fired `ohmylms_rest_delete_course` (which unlinks chapters) on a mere trash. `DELETE` deleted permanently with no student check. `duplicate` was blocked by a "pro" check that is always true. Passwords were dropped on copy while `_access_type` was copied. |
| Medium | **Thumbnails**: `image_id`/`thumbnail_id` accepted any image id of any owner; `featured_image` routes set any attachment on any post id. |
| Low | `date()` instead of site-timezone helpers in the date filters; no `Location` header; duplicated action firing; partial updates reused `get_collection_params()` as arguments, so there was no item schema. |
| OK | Cookie authentication: core verifies `X-WP-Nonce` before any permission callback. Remaining SQL is prepared (`%i` for tables). |

## Final routes

Capabilities are defined in one place, `CourseController::can()`. `edit_posts` is needed to reach any
route (anonymous callers get `401`, other users `403`); then the object-level meta capability decides:

| Method | Path | Capability | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/courses` | `edit_posts`; users without `edit_others_posts` only ever see their own courses in every status | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/courses` | `edit_posts` (+ `publish_posts` for `publish`, `future`, `private`) | 201 + `Location` (edit context) | 400, 401, 403, 422, 500 |
| GET | `/courses/options` | `edit_posts` | 200 | 401, 403 |
| GET | `/courses/{id}` | `read_post`; `context=edit` needs `edit_post` | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/courses/{id}` | `edit_post` (+ publish capability for those statuses) | 200 (edit context) | 400, 401, 403, 404, 409, 422, 500 |
| DELETE | `/courses/{id}` | `delete_post`; moves to the trash | 200 (the trashed course) | 401, 403, 404, 409, 500 |
| DELETE | `/courses/{id}?force=true` | `delete_post`; refused while students are enrolled | 200 `{deleted, previous}` | 401, 403, 404, 409, 500 |
| POST | `/courses/{id}/duplicate` | `edit_posts` and `edit_post` on the source | 201 + `Location` | 401, 403, 404, 409, 500 |
| POST | `/courses/{id}/restore` | `delete_post` | 200 (as a draft) | 401, 403, 404, 409, 500 |

Collection arguments: `page` (>= 1), `per_page` (1-100, default 20), `search`, `status`
(`all|trash|publish|draft|pending|private|future`; `all` excludes the trash), `price_type`
(`all|free|paid`), `level`, `author` (only with `edit_others_posts`), `created_after` / `created_before`
(`YYYY-MM-DD`, inclusive, site timezone), `include`, `orderby`
(`id|title|status|created_at|updated_at|price`), `order`. Nothing else reaches `WP_Query`; unknown query
arguments are ignored. Sorting by `price` omits courses that have no stored `_price` (every API-created
course has one). A page past the end answers 400 `ohmylms_course_invalid_page`. The collection is view
context only.

### Removed routes

`PUT /courses/{id}/status`, `DELETE /courses/trash-bulk`, `POST|PUT /courses/{id}/clone` (now
`/duplicate`) and `/courses/featured_image/{id}` (now `thumbnail_id` on update).

### Auxiliary routes (not part of this contract)

Enrolment (`/{id}/enroll`, `/{id}/enroll/{student}`, `/{id}/enroll-by-email`), `/import`, `/import-scorm`,
`/export`, `/export-scorm`, `/{id}/chapters`, `/settings/{id}`, `/get-map/*`, `/update-map/{id}`,
`/filtered-courses` and `/{id}/terms` keep their old shapes for the old builder and add-ons but now share
one permission callback: `edit_post` on the course in the URL; import and export need `manage_options`;
enrolling needs `list_users`; `enroll-by-email` (creates accounts) needs `create_users`; `/{id}/terms`
only accepts taxonomies registered for courses. They are not otherwise audited (see the report).

## Fields

`title`, `slug`, `status`, `scheduled_at`, `description` (edit), `excerpt` (edit), `level`
(`beginner|experience|expert|all`), `price_type` (`free|paid`), `regular_price`, `sale_price`,
`sale_start_date`, `sale_end_date`, `price` (read only), `currency`, `access_type`
(`public|password_protected`), `password` (edit context only), `has_password`, `available_date`,
`duration` (`{hours, minutes}` or null), `has_capacity`, `capacity`, `enable_reviews`,
`learning_outcomes`, `target_audience`, `requirements` (edit), `thumbnail_id`, `thumbnail_url`,
`enrolled_count`, `author_id`, `author_name`, `permalink`, `created_at`, `updated_at`.
Other plugins may add fields through `ohmylms_rest_get_course_data`.

Curriculum, lessons, quizzes, assignments, certificates, community, gamification, funnel and cohort
settings are not part of this contract.

## Rules

- **Whole-request validation** happens before the first write (`plan_write()`); a rejected request changes
  nothing. Partial updates are validated against the merged stored and submitted values (lowering
  `regular_price` below a stored sale is rejected). This is validation before the writes, not a database
  transaction.
- **Money** is a decimal string (`"29.50"`), 0 to 99999999.99, with at most the site's decimals
  (`ohmylms_price_num_decimals`). A paid course needs a regular price above zero. `sale_price <=
  regular_price`; a sale needs both dates or neither; dates without a sale price are rejected; a free course
  cannot have a sale. `price` is the price that applies today and `_price` is saved with it.
- **Statuses**: write `draft|pending|publish|future|private`. `future` needs `scheduled_at`
  (`YYYY-MM-DDTHH:MM`, site time, in the future). Trash is reached only through `DELETE` and left through
  `/restore` (WordPress restores drafts). A trashed course cannot be edited or copied (409).
- **Slug**: made unique with `wp_unique_post_slug` against every course, drafts included. A title change
  never rewrites the slug.
- **Password**: stored as `post_password`; `access_type=password_protected` needs one, `public` clears it, a
  private course cannot have one. It is returned only in `context=edit` to users who can edit the course
  (never in lists or the view context). Unrelated updates keep it.
- **Thumbnail**: JPEG, PNG, GIF or WebP attachment that the user may edit (`edit_post`); `null` removes it.
- **Delete**: permanent delete is refused with 409 `ohmylms_course_has_students` (`data.students`) while
  students are enrolled. Trash is always allowed.
- **Duplicate** copies content, settings, curriculum placement, units and thumbnail as a draft owned by the
  caller, plus the password; no students.
- **Known limit**: `_price` is a snapshot taken at save time. A sale window that opens or closes later does
  not change it until the course is saved again; `Course::get_price()` should evaluate the window (owner
  decision, `includes/Data/Course.php` is outside this feature).

## Storage

No meta key changed. The API maps: `_price_type`, `_regular_price`, `_sale_price`, `_price`,
`_sale_price_dates_from|to` (timestamps of site-local midnight), `_level`, `_access_type`,
`_available_date`, `_duration` (`{hour, min}`), `_has_capacity`, `_capacity`, `_enable_reviews`,
`_benefit_description`, `_benefiter_description`, `_requirement`, `_thumbnail_id`, and the post fields.
`_availability` is no longer written (nothing read it; it duplicated "duration set").

## Examples

```http
POST /wp-json/ohmylms/v1/courses
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "title": "Algebra I", "status": "publish", "level": "beginner", "price_type": "paid",
  "regular_price": "49.5", "sale_price": "29", "sale_start_date": "2026-11-01", "sale_end_date": "2026-11-30",
  "has_capacity": true, "capacity": 30, "duration": { "hours": 3, "minutes": 30 } }
```

```http
HTTP/1.1 201 Created
Location: https://example.test/wp-json/ohmylms/v1/courses/31

{ "id": 31, "title": "Algebra I", "slug": "algebra-i", "status": "publish", "level": "beginner",
  "price_type": "paid", "regular_price": "49.50", "sale_price": "29.00", "sale_start_date": "2026-11-01",
  "sale_end_date": "2026-11-30", "price": "49.50", "currency": "USD", "access_type": "public",
  "has_password": false, "password": "", "has_capacity": true, "capacity": 30,
  "duration": { "hours": 3, "minutes": 30 }, "enrolled_count": 0, "thumbnail_id": null, "thumbnail_url": null,
  "author_id": 1, "author_name": "Admin", "created_at": "2026-10-30T09:15:00", "updated_at": "2026-10-30T09:15:00" }
```

Validation failure:

```json
{ "code": "ohmylms_course_sale_price_too_high",
  "message": "The sale price cannot be higher than the regular price.",
  "data": { "status": 422, "field": "sale_price" } }
```

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation (types, enums, price pattern, ranges). |
| 400 | `ohmylms_course_invalid_page` | `page` past the last page. |
| 401 / 403 | `ohmylms_course_cannot_view` / `_create` / `_edit` / `_delete` / `_publish`, `_thumbnail_forbidden` | Not logged in (401) or not allowed (403). |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_course_not_found` | Unknown id or another post type. |
| 409 | `ohmylms_course_trashed`, `_already_trashed`, `_not_trashed`, `_has_students` | State conflicts. |
| 422 | `ohmylms_course_invalid_title`, `_invalid_slug`, `_invalid_text`, `_invalid_price`, `_paid_needs_price`, `_sale_price_too_high`, `_invalid_sale_dates`, `_sale_dates_without_price`, `_sale_dates_incomplete`, `_sale_on_free`, `_invalid_date`, `_invalid_schedule`, `_password_required`, `_invalid_password`, `_private_password`, `_invalid_capacity`, `_invalid_thumbnail` | Rules above (`data.field` names the field). |
| 500 | `ohmylms_course_create_failed`, `_update_failed`, `_delete_failed`, `_duplicate_failed` | Database failure. |

## Hooks kept

Actions: `ohmylms_rest_before_create_course` ($request), `ohmylms_new_course` ($id, $course),
`ohmylms_after_creating_first_course`, `ohmylms_rest_insert_course` ($post, $id, $request; creates the default
chapter), `ohmylms_rest_after_create_course`, `ohmylms_rest_before_update_course` ($id, $request),
`ohmylms_update_course` ($id, $course), `ohmylms_rest_update_course`, `ohmylms_rest_after_update_course`,
`ohmylms_trash_course` ($id), `ohmylms_rest_before_delete_course`, `ohmylms_rest_delete_course` ($id, $request),
`ohmylms_delete_course`, `ohmylms_rest_after_delete_course`. Filters: `ohmylms_rest_get_course_data`,
`ohmylms_rest_prepare_course`, `ohmylms_rest_ohmylms_course_query`. Dropped because the REST layer no longer
calls `Course::save()`: `ohmylms_rest_before_save_course`, `ohmylms_rest_after_save_course`,
`ohmylms_rest_after_save_course_meta`, `ohmylms_before_course_object_save`,
`ohmylms_after_course_object_save` and the per-meta `ohmylms_update_or_delete_course_meta_*` actions.

## Security notes

- One capability map; object checks on every item route; cookie requests need the core REST nonce.
- Existence is revealed only after `edit_posts` is confirmed; other authors' drafts answer 403.
- Only validated `search`, `status`, `price_type`, `level`, `author`, dates, `include`, `orderby`, `order`,
  `page` and `per_page` reach `WP_Query`. Descriptions pass `wp_kses_post`; other text is plain text.
- Author display names are returned only to users who can edit others' courses; emails are never returned.
- Passwords are write-only outside the edit context.

## Old callers broken by the new shapes

The recovered admin calls `GET|POST|PUT|DELETE /courses`, `/courses/{id}/status`, `/courses/trash-bulk`,
`/courses/{id}/clone` and `/courses/featured_image/{id}` with the old field names (`name`, `image_id`,
`chapters`, `curriculum`, `cohort`, `funnel_steps`, ...). The file list is in the feature report.

## Tests

`tests/php/courses-rest-integration.php` (set `OHMYLMS_COURSES_TEST=1`; see the file header).
