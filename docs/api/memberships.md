# Memberships REST API (`ohmylms/v1/memberships`)

Contract version 1. The plugin is pre-release, so this replaces the old `ohmylms/v1/membership`
API outright; there is no compatibility layer. The machine-readable form is
`assets/src/app/features/memberships/contract.json`. Server code:
`includes/Rest/V1/MembershipController.php` (routes, schema), `includes/Data/Membership.php` and
`includes/DataStores/MembershipStore.php` (storage), `includes/Membership/CourseSelection.php`
(access resolution and enrolment sync) and `includes/Membership/FreeSale.php` (free promotions).

## Audit before

| Severity | Finding |
| --- | --- |
| High | **Permission was `edit_posts`**, so any author or contributor could reach the plan routes. Item checks relied on post-type map_meta_cap. Now `manage_options` on every route. |
| High | **Delete removed plans with live members**: `DELETE` force-deleted the post and a hook then wiped the members' `user_membership` and enrolment rows. Now 409 `ohmylms_membership_has_members` while members are enrolled or pending. |
| High | **Wrong statuses for failures**: unknown ids answered 400 (update, delete) or a mix of 404/400 (read); `create_item` returned `new WP_Error( 400, ... )` (a numeric code) on any `DataException`; `MembershipStore::update()` ignored `wp_update_post()` errors; `ohmylms_get_membership()` returns `false` for unknown ids and `delete_item` assumed an object. |
| High | **Slug rewritten on every update**: `MembershipStore::update()` set `post_name` from the title, so any save changed public permalinks. |
| High | **Prices unvalidated** on the server: `regular_price`, `sign_up_fee` and a non-zero `sale_price` were stored as given (strings, negatives, huge). A sale above the regular price was accepted. A sale with only one date was stored but never applied (`Membership::is_on_sale()` needs both dates or neither), so it silently never went on sale. |
| Medium | **`get_items` forwarded raw query vars** (`meta_key`/`meta_value` LIKE, `post_status`, `date_filter`, `parent*`, `offset`, `filter`) to `WP_Query` through a "valid vars" list copied from WooCommerce (hooks named `woocommerce_rest_query_var-*`). `per_page` had no maximum. |
| Medium | **N+1 queries**: per plan, one `COUNT(*)` for members and a resolve of the course set with `get_post_type`, `get_post_status` and `get_the_title` per course. Now one grouped member count per page, a primed post cache and a cheap count for "all learning content". |
| Medium | **Course validation** ran one `get_post_type()` per course, accepted any count, and the whole save was not atomic: the plan was saved first, then rules were validated and saved again, so a rejected course list left a half-updated plan. Now one query, 500 maximum, unique ids, `invalid_ids` in the error, and everything is validated before the first write. |
| Medium | **Partial updates** reused `get_collection_params()` as arguments; there was no item schema, so types were unchecked. |
| Medium | **Bulk trash** (`DELETE /membership/trash-bulk`) trashed unvalidated ids without a per-id result, and **`course-preview`** resolved arbitrary category/tag/bundle ids with its own query loops. Both removed. |
| Low | No `Location` header on 201; delete returned `{status, message}`; names allowed HTML via `wp_filter_post_kses`; money mixed strings and floats; the currency symbol was decoded for every row. |
| OK | Cookie authentication: core verifies `X-WP-Nonce` before any permission callback. Prepared SQL with `%i` for tables. |

Legacy category/tag rules and the retired bundle rules are no longer part of the API. Stored
`_course_categories`, `_course_tags` and `_course_bundles` are untouched, and `CourseSelection::resolve()`
still honours categories and tags if a plan has them.

## Final routes

All routes require `manage_options` (`MembershipController::CAPABILITY`). Anonymous callers get `401`,
logged-in users without the capability get `403`. Argument validation runs before the permission
callback (core behaviour), so a malformed request can answer 400 before 401/403.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/memberships` | List (paged) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/memberships` | Create (only `name` is required; starts as a draft) | 201 + `Location` | 400, 401, 403, 422, 500 |
| GET | `/memberships/{id}` | Read (`context=edit` adds courses, rules, description) | 200 | 401, 403, 404 |
| PUT / PATCH / POST | `/memberships/{id}` | Partial update | 200 (edit context) | 400, 401, 403, 404, 422, 500 |
| DELETE | `/memberships/{id}` | Permanent delete | 200 `{deleted, previous}` | 401, 403, 404, 409, 500 |
| POST | `/memberships/{id}/duplicate` | Draft copy with price, schedule, courses and rules; no members | 201 + `Location` | 401, 403, 404, 500 |
| GET | `/memberships/{id}/members` | Read-only members (paged) | 200 + totals | 400, 401, 403, 404 |
| GET | `/memberships/options` | Statuses, billing periods, site currency, limits | 200 | 401, 403 |
| GET | `/memberships/courses` | Course search for pickers (max 50 per page; `include` by id) | 200 + totals | 400, 401, 403 |

Collection arguments: `page` (>= 1), `per_page` (1-100, default 20), `search` (name or description),
`status` (`all|publish|draft`), `include` (ids), `orderby` (`id|name|status|created_at|updated_at`,
default `created_at`), `order`, `context`, `_fields`. A page past the end answers 400
`ohmylms_membership_invalid_page`.

## Pricing and sale rules

- Money is a decimal **string** (`"29.50"`), 0 to 99999999.99, with at most the site's
  `ohmylms_price_num_decimals` decimals (default 2). Responses always use that fixed number of
  decimals. The currency is the site setting (`currency` on each plan; symbol, position and decimals in
  `/options`); it is never stored on a plan.
- `sale_price` must be `<= regular_price`. `null` or omitted means no sale.
- `sale_start_date` / `sale_end_date` are calendar dates (`YYYY-MM-DD`, site timezone), real dates, start
  `<=` end. Dates without a sale price are rejected. A discounted sale needs both dates or neither. A sale
  price of `0` is a free promotion (see `FreeSale`: no billing) and needs an end date; its start is optional.
- Rules are checked against the merged result of stored and submitted values, so lowering
  `regular_price` below a stored sale price is rejected.
- `price` in responses is the price that applies today (free promotion, sale window or regular).

## Courses and access rules

`course_ids` replaces the explicit list as a whole (order kept). `excluded_course_ids` wins over every
other rule. `all_learning_content` grants every published course, now and later. `curriculum_item_ids`
grants everything beneath the items. Ids are validated with one query (courses must exist as
`ohmylms-course`; curriculum items must exist), at most 500 / 200, unique, and included and excluded sets
may not overlap. The whole request is validated before anything is written, so a rejected request changes
nothing. This is validation before one save, not a database transaction.

After a save `ohmylms_rest_after_adding_products_on_membership` runs (also for an empty list), which
synchronises members' course access: added courses are granted, removed courses revoked.
`course_count` is the number of published courses granted today; `member_count` the enrolled members.

## Examples

```http
POST /wp-json/ohmylms/v1/memberships
X-WP-Nonce: <wp_rest nonce>
Content-Type: application/json

{ "name": "All access", "status": "publish", "regular_price": "29.5", "sale_price": "19",
  "sale_start_date": "2026-11-01", "sale_end_date": "2026-11-30",
  "billing_period": "month", "course_ids": [ 12, 15 ] }
```

```http
HTTP/1.1 201 Created
Location: https://example.test/wp-json/ohmylms/v1/memberships/31

{ "id": 31, "name": "All access", "slug": "all-access", "status": "publish", "description": "",
  "permalink": "https://example.test/membership/all-access/",
  "regular_price": "29.50", "sale_price": "19.00", "sale_start_date": "2026-11-01", "sale_end_date": "2026-11-30",
  "price": "29.50", "sign_up_fee": null, "billing_period": "month", "billing_interval": 1, "currency": "USD",
  "all_learning_content": false, "course_ids": [ 12, 15 ],
  "courses": [ { "id": 12, "title": "Algebra", "status": "publish" }, { "id": 15, "title": "Geometry", "status": "publish" } ],
  "excluded_course_ids": [], "excluded_courses": [], "curriculum_item_ids": [],
  "course_count": 2, "member_count": 0, "created_at": "2026-10-30T09:15:00", "updated_at": "2026-10-30T09:15:00" }
```

Member row (`GET /memberships/31/members`):

```json
{ "id": 4, "user_id": 9, "display_name": "Alex", "email": "alex@example.org", "status": "enrolled",
  "order_id": 0, "start_date": "2026-10-30T09:20:00Z", "end_date": null }
```

Validation failure:

```json
{ "code": "ohmylms_membership_sale_price_too_high",
  "message": "The sale price cannot be higher than the regular price.", "data": { "status": 422 } }
```

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema validation (types, enums, price pattern, ranges, duplicate ids, list limits). |
| 400 | `ohmylms_membership_invalid_page` | `page` is past the last page (list, members, courses). |
| 401 / 403 | `ohmylms_membership_cannot_view` / `_create` / `_edit` / `_delete` | Not logged in (401) or missing `manage_options` (403). |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_membership_not_found` | Unknown id, or an id of another post type. |
| 409 | `ohmylms_membership_has_members` | Delete while enrolled or pending members exist (`data.members`). |
| 422 | `ohmylms_membership_invalid_name`, `_invalid_price`, `_sale_price_too_high`, `_invalid_sale_dates`, `_sale_dates_without_price`, `_sale_dates_incomplete`, `_free_needs_end` | Pricing and schedule rules above. |
| 422 | `ohmylms_membership_invalid_courses`, `_course_conflict`, `_invalid_curriculum` | Unknown ids (`data.invalid_ids`) or a course both included and excluded. |
| 500 | `ohmylms_membership_create_failed`, `_update_failed`, `_delete_failed` | Database failure. |

## Hooks kept

Actions `ohmylms_rest_insert_membership` ($post, $request), `ohmylms_membership_created` ($id, $request),
`ohmylms_rest_delete_membership` ($id) and `ohmylms_rest_after_adding_products_on_membership`
($products, $id). Filters `ohmylms_rest_prepare_membership` ($response, $post, $request) and
`ohmylms_rest_ohmylms_membership_query` ($args, $request; now applied to a fixed, validated query).
Data-store hooks `ohmylms_update_membership` and `ohmylms_after_creating_new_membership` still fire.

## Security notes

- One capability constant. Cookie requests need the core REST nonce. Only validated `search`, `status`,
  `include`, `orderby`, `order`, `page` and `per_page` reach `WP_Query`.
- Descriptions are sanitised with `wp_kses_post` on write; names are plain text. Responses are JSON.
- Member emails are returned only to `manage_options` users.
- Exists but is not part of this API: billing and renewals (`SubscriptionManager`), checkout and gateways
  (`packages/e-commerce`), free-promotion signup (`FreeSale`, an `admin-post` action with its own nonce),
  `subscription_length`, trials and stop-renew. This API only stores `billing_period`, `billing_interval`
  and `sign_up_fee`.

## Tests

`tests/php/memberships-rest-integration.php` (set `OHMYLMS_MEMBERSHIPS_TEST=1`; see the file header).
