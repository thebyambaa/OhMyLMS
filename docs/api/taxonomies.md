# Taxonomies REST API (`ohmylms/v1/categories`, `ohmylms/v1/tags`)

Contract version 2. The plugin is pre-release, so this replaces the old API outright. The
machine-readable form is `assets/src/app/features/taxonomies/contract.json`. Server code:
`includes/Rest/V1/CategoryController.php` (all behaviour) and `includes/Rest/V1/TagController.php`
(extends it; data source and messages only). Test: `tests/php/taxonomies-rest-integration.php`.

## What these endpoints are now

Both are **legacy, read-only compatibility views**. Course categories were replaced by the
curriculum and course tags by Course Bundles (`docs/MODULARIZATION-ROADMAP.md`;
`Curriculum\Placement`):

- `/categories` lists **curriculum items** (`ohmylms_curriculum_items`) with their parent and the
  number of linked courses.
- `/tags` lists **course bundles** (`ohmylms_bundles`) with the number of courses they reach.

Usage found in the repository: the only readers were the recovered admin's course-list filter
(`assets/src/application/routes.js`) and the recovered `Categories`/`Tags` pages
(`assets/src/features/taxonomies`), which the app already redirects to the curriculum
(`assets/src/extensions/index.jsx`) and which could only receive `410`; all of these were deleted at the cutover. No template or PHP class
calls these routes. Writes belong to `/curriculum/items` (administrators) and `/bundles`.

## Audit before

| Severity | Finding |
| --- | --- |
| Medium | **Unbounded, unpaginated output.** `GET` returned every item with a `courses` array of all linked course titles: `Links::object_ids()` plus `get_the_title()` per course per item (N+1, and the whole list in one response). No `per_page`, `page`, headers or maximum. |
| Medium | **Private course titles leaked.** Titles of draft/private/trashed-link courses were returned to every `edit_posts` user (contributors included). |
| Medium | **Write routes ran their permission check as a read check.** All four write routes used `edit_posts`, so a contributor learned the same `410` an administrator did and nothing distinguished the capabilities; there was no `manage_categories` or `manage_options` check anywhere. |
| Low | **No validation or schema.** `search` (tags) was cast to string without a limit; no `args`, no `get_item_schema()`, no `get_collection_params()`, no `OPTIONS`. |
| Low | **Legacy shape.** `term_id` duplicated `id`; the `slug` is synthetic (`c<ID>`, `b<ID>`). |
| Low | **No single-item route.** There was no `GET /{id}`. |
| OK | No SQL was written in the controllers; `Placement` uses prepared statements. Cookie authentication: core verifies `X-WP-Nonce` before any permission callback. |

## Final routes

Reading needs `edit_posts` (`CategoryController::READ_CAPABILITY`: course authors fill filters and
pickers with it). The `edit` context and the refused write routes need `manage_options`
(`WRITE_CAPABILITY`). Anonymous callers get 401, users without the capability 403.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/categories`, `/tags` | List (paginated, searchable) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| GET | `/categories/{id}`, `/tags/{id}` | One item; `context=edit` adds `courses` | 200 | 401, 403, 404 |
| POST | `/categories`, `/tags` | Refused | 410 | 401, 403 |
| PUT / PATCH / POST / DELETE | `/categories/{id}`, `/tags/{id}` | Refused | 410 | 401, 403 |
| DELETE | `/categories/bulk`, `/tags/bulk` | Refused | 410 | 401, 403 |

Collection arguments: `page` (>= 1), `per_page` (1-100, default 20), `search` (name, max 100
characters), `orderby` (`name|id`, default `name`), `order` (`asc|desc`), `parent` (categories only,
`0` = top level). A page beyond the last page answers 400 `ohmylms_category_invalid_page` or
`ohmylms_tag_invalid_page`.

## Examples

```http
GET /wp-json/ohmylms/v1/categories?search=alg&per_page=20
X-WP-Nonce: <wp_rest nonce>

HTTP/1.1 200 OK
X-WP-Total: 1
X-WP-TotalPages: 1

[ { "id": 4, "name": "Algebra", "slug": "c4", "description": "", "parent": 3, "count": 1 } ]
```

```http
GET /wp-json/ohmylms/v1/categories/4?context=edit      (manage_options only)

{ "id": 4, "name": "Algebra", "slug": "c4", "description": "", "parent": 3, "count": 1,
  "courses": [ { "id": 40, "title": "Intro to Algebra" } ] }
```

```json
POST /wp-json/ohmylms/v1/tags        (administrator)

410
{ "code": "ohmylms_tags_replaced",
  "message": "Course tags were replaced by Course Bundles. Manage them under OhMyLMS > Course Bundles.",
  "data": { "status": 410 } }
```

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param` | Schema validation (`per_page` > 100, bad `orderby`/`order`, negative `parent`, `search` > 100 chars). |
| 400 | `ohmylms_category_invalid_page`, `ohmylms_tag_invalid_page` | `page` is past the last page. |
| 401 / 403 | `ohmylms_category_cannot_view`, `ohmylms_tag_cannot_view` | Not logged in or missing `edit_posts`. |
| 401 / 403 | `ohmylms_category_cannot_edit`, `ohmylms_tag_cannot_edit` | Write route without `manage_options`. |
| 403 | `rest_forbidden_context` | `context=edit` without `manage_options`. |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_category_not_found`, `ohmylms_tag_not_found` | Unknown ID. |
| 410 | `ohmylms_categories_replaced`, `ohmylms_tags_replaced` | Any write, with `manage_options`. Nothing is changed. |

## Schema

Served by `OPTIONS /categories` (`title: category`) and `OPTIONS /tags` (`title: tag`), draft 4.
All properties are read only: `id`, `name`, `slug`, `description`, `parent` (always `0` for tags),
`count` (linked courses) and, in the `edit` context only, `courses` (`[{id, title}]`, at most 100).
`term_id` no longer exists.

## Security notes

- **Authorisation.** Two constants, no per-route variation. Course titles are only disclosed in the
  `edit` context, which requires `manage_options`; `count` may still reveal that unpublished
  courses are linked.
- **Cost.** The whole taxonomy is read once (`Items::all()` / `Bundles::all()`; both are
  administrator-curated and small) and filtered, sorted and sliced in PHP. Categories count courses
  for the page with one grouped query; tags run `Placement::course_ids_for_bundles()` once per
  returned bundle (at most `per_page`).
- **Writes.** Nothing can be written through these controllers. Curriculum items and bundles have
  their own administrator-only write APIs (`Curriculum\Access::admin()`), which also maintain
  syllabus courses, links and followers.

## Authored admin preview

`#/taxonomies` (`assets/src/app/features/taxonomies/`): one tab per taxonomy, with search, paging,
and for administrators a "Show courses" dialog and a link to the screen that owns the data. It is
read-only because the endpoints refuse writes by design; see the owner decisions in the rebuild
report.
