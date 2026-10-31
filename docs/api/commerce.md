# Commerce REST API: orders, coupons, refunds (`ohmylms-commerce/v1`)

Contract version 2. The plugin is pre-release, so this replaces the old API outright; there is no
compatibility layer for the recovered admin (its order and coupon screens will not work against
these shapes). The machine-readable form is `assets/src/app/features/commerce/contract.json`.
Server code: `packages/e-commerce/includes/Rest/V1/OrdersController.php`, `CouponController.php`,
`OrderRefundController.php` on `Abstracts/RestController.php` (capability, locks). Test:
`tests/php/commerce-rest-integration.php`. Subscriptions have their own hardened API under
`ohmylms/v1/subscriptions`; see [Subscriptions](#subscriptions) below. The namespace `cx-ecommerce/v1` is
still registered with the same controllers on sites without CreatorLMS (see `docs/CREATORLMS-COEXISTENCE.md`).

## Audit before

| Severity | Finding |
| --- | --- |
| High | **Wrong capability.** Every order, coupon and refund route allowed `edit_posts`, i.e. contributors and authors. They could list all orders (names, emails, addresses, phone numbers), change order status, create refunds and delete coupons. The refund `GET` listed nothing and its create route trusted `edit_posts` too. |
| High | **Money mover without guards.** `POST /orders/{id}/refunds` allowed an amount of `0`, answered HTTP 200 with `success:false` for every failure, checked no order status, had no lock (a double click created two refunds until the remaining amount ran out), and called the live payment gateway (Stripe) for non-offline orders. |
| High | **Arbitrary query injection.** `GET /orders` merged `filter[...]` into the `WP_Query` arguments (any `post_type`, `posts_per_page=-1`, `meta_query`), accepted any `meta_key`/`meta_value` pair (read any order meta) and an unbounded `per_page`. |
| High | **Order creation and arbitrary writes.** `POST /orders` and `PUT /orders/{id}` copied ~30 body fields onto the order (total, student, status, payment, line items) with no validation. `PUT` also trusted `set_paid`/`transaction_id`. Any status could follow any status. |
| High | **Bulk destructive route.** `DELETE /coupons` deleted every `ids[]` permanently without checking the first ones; `DELETE /orders/trash-bulk` trashed ids after only a type check and returned 200 for a non-array. |
| Medium | **Personal data in lists.** The order list carried email, phone, WhatsApp, timezone, avatar, address and country of every student, plus the student's lifetime totals (one student query and `get_related_orders` per row, so N+1). |
| Medium | **GET with side effects, N+1, whole-table scans.** `GET /orders/{id}` fetched the student, notes, related orders and gateway data; `GET /orders/stats` loaded every completed order into PHP; status counts used a raw query with a hard-coded status list; the list re-sorted the page in PHP for `total` and `student_name`. |
| Medium | **Coupon rules were never enforced.** The enum listed `fixed_cart`, `fixed_course`, `other`, but the cart only discounts `percent` and `flat-rate` (anything else silently gave no discount). A percentage above 100 or a negative amount was accepted. `individual_use` and `exclude_sale_items` were written as booleans and read back as `'yes' === ...`, so they never persisted. `minimum_amount`, `maximum_amount`, `email_restrictions` and `excluded_course_ids` were accepted but ignored by the cart. Expiry before start, per-user limit above the total limit and unknown course IDs were accepted. Duplicate codes were found only for published coupons. |
| Medium | **Status and error handling.** `PUT` coupon answered 201 with a `Location` built from an undefined `rest_base`; a missing coupon/order returned 200 with `message`; `update_item` called `get_status()` on a `WP_Error`; the status route registered the same method twice; the order status note was written twice (once by the data store, once by the controller). Pagination headers existed only for orders; coupon lists were unbounded (`per_page` default `-1`). |
| Medium | **Notes.** Notes were stored as sent (markup kept) and `note` was not required. |
| Low | The order list was wrapped as `{orders, status_counts, currency}` and ignored `include`/`exclude`/`slug`; the date filters were commented out; dates mixed MySQL strings and timestamps. |
| OK | Search and status counts used `prepare()`. Cookie authentication: core verifies `X-WP-Nonce` in `rest_cookie_check_errors()` before any permission callback; no custom nonce code is needed. |

## Final routes

All routes require `manage_options` (`Abstracts\RestController::CAPABILITY`). Anonymous callers get
`401`, logged-in users without the capability `403`. Argument validation runs before the permission
callback (core behaviour), so a malformed request can answer 400 before 401/403.

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/orders` | Paged order list (light rows) | 200 + `X-WP-Total`, `X-WP-TotalPages` | 400, 401, 403 |
| GET | `/orders/status-counts` | Count per status | 200 `{pending: n, ...}` | 401, 403 |
| GET | `/orders/{id}` | Full order | 200 | 401, 403, 404 |
| PATCH / PUT / POST | `/orders/{id}` | Change status (`status`) | 200 `Order` | 400, 401, 403, 404, 409, 500 |
| DELETE | `/orders/{id}` | Trash the order and remove its enrolments | 200 `{deleted, previous}` | 401, 403, 404, 500 |
| GET | `/orders/{id}/notes` | Notes, newest first (max 100) | 200 | 401, 403, 404 |
| POST | `/orders/{id}/notes` | Add a plain text note | 201 `Note` | 400, 401, 403, 404, 422, 500 |
| GET | `/orders/{id}/refunds` | Refunds of the order | 200 | 401, 403, 404 |
| POST | `/orders/{id}/refunds` | Create a refund (guarded, see below) | 201 `Refund` | 400, 401, 403, 404, 409, 422, 500 |
| GET | `/coupons` | Paged coupon list | 200 + totals | 400, 401, 403 |
| POST | `/coupons` | Create | 201 + `Location` | 400, 401, 403, 409, 422 |
| GET | `/coupons/{id}` | One coupon | 200 | 401, 403, 404 |
| PATCH / PUT / POST | `/coupons/{id}` | Partial update | 200 `Coupon` | 400, 401, 403, 404, 409, 422 |
| DELETE | `/coupons/{id}` | Delete permanently | 200 `{deleted, previous}` | 401, 403, 404, 500 |

Removed: `POST /orders` (orders come from checkout), `PUT /orders/{id}` field copying, `/orders/trash-bulk`,
`/orders/stats`, `DELETE /coupons` (bulk) and `/coupons/{id}/status` (use `status` on the coupon),
the `{orders, status_counts, currency}` list wrapper and `filter`/`meta_key`/`meta_value` arguments.

### Orders

`GET /orders` arguments: `page` (>= 1), `per_page` (1-100, default 20), `search` (order number,
billing name or email; at most 1000 matches considered), `status` (`any` or an order status),
`payment_method` (gateway id), `after`/`before` (`YYYY-MM-DD`, inclusive, site time, real calendar
days, `after <= before`), `orderby` (`date|id|total`, default `date`), `order` (`asc|desc`, default
`desc`). A page beyond the last answers 400 `ohmylms_order_invalid_page`.

A list row carries `id, number, status, currency, total, date_created, payment_method,
payment_method_title, student_id, student_name`: no email, phone or address. Post meta is primed by
`WP_Query`, so a page costs a constant number of queries. The detail adds `parent_id, subtotal,
discount_total, tax_amount, date_paid, date_completed, transaction_id, billing{...}, line_items[],
coupon_lines[], refunds[], total_refunded, remaining_refundable, related_orders[],
allowed_statuses[], points` (`{points, refunded, refunded_at, refundable}` for an order paid with bonus points,
else `null`; see [point purchases](gamification.md#point-purchases)). Notes are fetched separately. Opening an order sets the `_is_open` flag (the
"new orders" badge in the legacy menu reads it); that is the only side effect of a `GET`.

**Money** is a decimal string with the shop's number of decimals (`"49.50"`) in the order's
`currency` (ISO 4217). Clients must format with `Intl.NumberFormat` and never do float arithmetic on
it. **Dates** are site time, ISO 8601 without offset.

**Status changes** (`PATCH /orders/{id}` with `status`). Allowed (`OrdersController::TRANSITIONS`,
filter `ohmylms_order_allowed_status_transitions`, returned per order as `allowed_statuses`):

| From | To |
| --- | --- |
| pending | processing, on-hold, completed, cancelled, failed |
| processing | on-hold, completed, cancelled, failed |
| on-hold | pending, processing, completed, cancelled, failed |
| failed | pending, processing, cancelled |
| completed | cancelled |
| cancelled | pending |
| refunded | (none: reached only through a refund) |

A forbidden change answers 409 `ohmylms_order_invalid_transition` and changes nothing; setting the
current status again is a 200 no-op that fires no hook. A per-order lock (`ohmylms_lock_order-{id}`
option, 60 s) answers 409 `ohmylms_order_busy` to a parallel request. Documented hooks keep
firing: `ohmylms_order_status_*`, `ohmylms_order_status_changed` (data store),
`ohmylms_update_order_status_to_completed` and `ohmylms_update_order_status_to_cancelled` (enrol /
unenrol the student), `ohmylms_rest_before_delete_order`, `ohmylms_rest_delete_order`,
`ohmylms_rest_prepare_order` (filter). Cancelling deletes the student's enrolment of the order;
trashing deletes enrolments and the order items.

**Notes** are plain text: tags are stripped (`sanitize_textarea_field`), 1-2000 characters, internal
only (never flagged as customer notes, no email). Markup-only text answers 422
`ohmylms_order_note_empty`.

### Refunds (server only)

The new admin screens deliberately offer **no refund button**: the previous implementation could call
the live gateway. The route is kept, hardened, and covered by tests with the offline gateway only.
`POST /orders/{id}/refunds` `{amount, reason, cancel_enrollment?}` requires:
administrator capability; an order in `processing|on-hold|completed` (409
`ohmylms_refund_order_status`); a payment method listed by the filter
`ohmylms_commerce_refundable_gateways` (default `["offline"]`; otherwise 409
`ohmylms_refund_gateway_unsupported`: refund in the provider's dashboard); `amount > 0` (422
`ohmylms_refund_amount_invalid`) and `<= remaining_refundable` (422 `ohmylms_refund_exceeds_remaining`);
a `reason` (400); and a per-order lock against double submission (409
`ohmylms_refund_in_progress`). A refund that reaches the order total sets the order to `refunded` and
cancels the enrolment through the existing `ohmylms_order_refunded` hook. Totals come from
`remaining_refundable`, re-read after taking the lock.

### Coupons

Fields (`PATCH` accepts any subset): `code` (unique case-insensitively among published and draft
coupons, trimmed, 1-50 chars), `title`, `description` (plain text), `status` (`publish|draft`; draft is
disabled at checkout), `discount_type` (`percent|flat-rate`), `amount` (percent: `0 < n <= 100`;
flat-rate: `> 0`; a decimal string in responses), `date_start`, `date_expires` (ISO 8601, site time
when no offset; `null` clears; expiry must be after the start), `usage_limit`,
`usage_limit_per_user` (>= 0; `null`/`0` unlimited; the per-customer limit may not exceed the total),
`individual_use`, `course_id_type` (`all|selected_course`), `course_ids` (existing courses, at least
one when `selected_course`). Read-only: `id`, `usage_count`, `date_created`, `currency` (the shop currency, for flat-rate amounts). Fields the cart does not
enforce (minimum/maximum spend, excluded courses, email restrictions, sale-item exclusion) are not
part of the API. Collection arguments: `page`, `per_page` (1-100, default 20), `search` (code, title,
description), `status` (`any|publish|draft`), `orderby` (`date|code|usage_count`), `order`. Past orders
keep the coupon code they used, so deleting a coupon is safe.

## Examples

```http
GET /wp-json/ohmylms-commerce/v1/orders?status=completed&after=2026-10-01&per_page=2
X-WP-Nonce: <wp_rest nonce>

HTTP/1.1 200 OK
X-WP-Total: 37
X-WP-TotalPages: 19

[ { "id": 311, "number": "311", "status": "completed", "currency": "USD", "total": "49.50",
    "date_created": "2026-10-29T08:30:00", "payment_method": "offline",
    "payment_method_title": "Offline", "student_id": 12, "student_name": "Ada Lovelace" } ]
```

```http
PATCH /wp-json/ohmylms-commerce/v1/orders/311
{ "status": "pending" }

HTTP/1.1 409 Conflict
{ "code": "ohmylms_order_invalid_transition", "message": "An order cannot change from completed to pending.", "data": { "status": 409 } }
```

```http
POST /wp-json/ohmylms-commerce/v1/coupons
{ "code": "SPRING15", "discount_type": "percent", "amount": "15", "usage_limit": 100,
  "date_expires": "2026-12-31T23:59:59", "course_id_type": "all" }

HTTP/1.1 201 Created
Location: https://example.test/wp-json/ohmylms-commerce/v1/coupons/57
{ "id": 57, "code": "SPRING15", "status": "publish", "discount_type": "percent", "amount": "15.00",
  "usage_count": 0, "usage_limit": 100, "usage_limit_per_user": null, "individual_use": false,
  "course_id_type": "all", "course_ids": [], "date_start": null, "date_expires": "2026-12-31T23:59:59", ... }
```

## Error codes

`ohmylms_order_cannot_view|edit|delete`, `ohmylms_coupon_cannot_view|create|edit|delete`,
`ohmylms_refund_cannot_view|create` (401/403); `ohmylms_order_not_found`, `ohmylms_coupon_not_found`
(404); `ohmylms_order_invalid_page`, `ohmylms_coupon_invalid_page`, `ohmylms_order_invalid_date` (400);
`ohmylms_order_invalid_transition`, `ohmylms_order_busy`, `ohmylms_coupon_code_exists`,
`ohmylms_refund_*` conflicts (409); `ohmylms_coupon_amount_invalid`, `_code_invalid`, `_date_invalid`,
`_date_range`, `_usage_limit`, `_courses_required`, `_course_invalid`, `ohmylms_order_note_empty`,
`ohmylms_refund_amount_invalid`, `ohmylms_refund_exceeds_remaining` (422, with `data.field`);
`ohmylms_order_not_saved|not_deleted`, `ohmylms_coupon_not_saved|not_deleted`,
`ohmylms_order_note_failed`, `ohmylms_refund_failed` (500). Schema failures use core's `rest_invalid_param`
and `rest_missing_callback_param` (400).

## Security notes

- One capability constant, enforced in every `permission_callback`; no route uses `__return_true`.
- No client-supplied query arguments reach `WP_Query`; sort keys, statuses and payment filters are
  allow-listed or validated; search uses `$wpdb->prepare()` with `esc_like()`.
- Lists expose no email, address or phone. The detail does, to administrators only. Coupon `used_by`
  and email restrictions are not exposed.
- Money-moving and state-changing actions take a lock via `add_option()` (atomic on the unique
  `option_name`); a lock older than 60 s is treated as abandoned. This is not a database transaction.
- Notes and coupon text are stored as plain text; the React client renders them as text.
- Not covered: refunds through live gateways, creating orders by hand.

## Verification

`tests/php/commerce-rest-integration.php` (run with `OHMYLMS_COMMERCE_TEST=1` against the Local site)
creates tagged offline orders, coupons, a course and users, checks denied callers on every route,
validation, transitions, hooks, notes, the refund guards and cleans up everything it created.

## Subscriptions

Read-mostly API under **`ohmylms/v1/subscriptions`** (contract version 1). Server code:
`includes/Rest/V1/SubscriptionsController.php` (registered in `includes/Rest/Api.php`; nothing is registered
when the commerce package is not loaded). Status changes go through the existing
`OhMyLMS\Commerce\SubscriptionManager`; no new storage. Screens: `#/subscriptions` and `#/subscriptions/:id`
in `assets/src/app/features/commerce` (extension routes `/subscriptions` and `/subscription-edit/:id`).
Test: `tests/php/subscriptions-rest-integration.php` (`OHMYLMS_SUBSCRIPTIONS_TEST=1`).

### Retired legacy route

`ohmylms-commerce/v1/subscriptions` and its `cx-ecommerce/v1/subscriptions` twin
(`packages/e-commerce/includes/Rest/V1/SubscriptionController.php`) were **removed on 31 October 2026**; both answer
404 `rest_no_route`. Their only callers were the recovered `#/subscriptions`, `#/subscriptions/:page`,
`#/memberships/subscriptions` and `#/subscription-edit/:id` screens, deleted at the cutover (their addresses
redirect to `/subscriptions` and `/subscriptions/:id`, see LEGACY-ROUTES.md); no PHP, JavaScript, MCP tool or
template called them. The audit below describes that route.

| Removed | Replacement |
| --- | --- |
| `GET /ohmylms-commerce/v1/subscriptions` | `GET /ohmylms/v1/subscriptions` (paged light rows, no email) |
| `GET /ohmylms-commerce/v1/subscriptions/{id}` | `GET /ohmylms/v1/subscriptions/{id}` |
| `PUT /ohmylms-commerce/v1/subscriptions/{id}` (status) | `POST /ohmylms/v1/subscriptions/{id}/pause`, `/resume`, `/cancel` with `{confirm: true}` |
| `PUT` of other fields, `POST /…/subscriptions`, `DELETE` | None by design: subscriptions come from checkout and are not edited by hand |

`ohmylms_update_subscription_status_to_active` / `_to_cancelled` are now documented on the typed controller; the
legacy-only `_to_expired`, `cx_ecommerce_rest_insert_ohmylms-subscription` and
`cx_ecommerce_rest_ohmylms-subscription_query` hooks no longer fire (nothing listened to them).

### Audit before

| Severity | Finding |
| --- | --- |
| High | **Any field and any status could be written.** `PUT /subscriptions/{id}` copied ~15 body fields (student, membership, gateway ID, recurring amount, dates, status) onto the subscription without validation and allowed any status from any status, including `active` for an unpaid `pending` subscription. The data store wrote `post_status` with a raw `$wpdb->update()`, so renewals were neither scheduled nor cleared and `SubscriptionManager`'s hooks did not fire. |
| High | **Creation route.** `POST /subscriptions` cast a `Subscription` object to an array and passed it to `wp_insert_post()`, then copied every `_`-prefixed schema field into post meta (gateway customer ID and payment token included). Subscriptions come from checkout. |
| High | **Fatal errors.** The controller used `WP_Error` without importing it, so a missing ID (`GET /subscriptions/999`) and an unknown post type crashed with `Class "OhMyLMS\Commerce\Rest\V1\WP_Error" not found`. `get_subscription_data()` called `$order->get_tax_rate()` on a missing original order. |
| High | **`pending-cancel` cannot be stored.** `ohmylms-pending-cancel` has 22 characters; `post_status` is `varchar(20)`, so `wp_update_post()` fails, `mark_subscription_pending_cancellation()` returns false and adds a failure note. The status is registered and listed but never reached. |
| Medium | **Unbounded, injectable queries.** The list accepted `offset`, `include`/`exclude`, `before`/`after` and an `orderby` enum extended with meta keys (including `_gateway_payment_method_token`), passed straight to `WP_Query`, and ran the filter `cx_ecommerce_rest_{post_type}_query`. Search matched only the generated post title. |
| Medium | **Personal data and N+1.** Each list row loaded the student (`ohmylms_get_student()`), the original order, its items, every related order and every note, and returned email and profile URLs. `get_related_orders()` ran an unbounded query and returned display strings (`"Renewal Order"`, `ucfirst` statuses). |
| Medium | **Hooks.** `ohmylms_update_subscription_status_to_{active,cancelled,expired}` fired only from this controller; no plugin code listens to them or to `SubscriptionManager`'s `ohmylms_subscription_*` hooks, so cancelling does not by itself change membership rows (access follows the membership records). |
| Low | Permission callbacks returned `false` (generic `rest_forbidden`), not typed errors; `prepare_item_for_database()` set `student_name`, `student_email`, `student_profile`; statuses were exposed with and without the `ohmylms-` prefix; schema keys (`_student_id`) differed from response keys (`student_id`). |
| OK | Every route required `manage_options`; cookie requests need the core REST nonce. |

### Routes

All routes require `manage_options`. Anonymous callers get `401`, logged-in users without it `403`
(`ohmylms_subscription_cannot_view|edit`). Argument validation runs before the permission callback (core).

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/subscriptions` | Paged list (light rows) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| GET | `/subscriptions/{id}` | One subscription with orders and notes | 200 | 401, 403, 404 |
| POST | `/subscriptions/{id}/pause` | Put on hold (`mark_subscription_on_hold`) | 200 detail | 400, 401, 403, 404, 409, 500 |
| POST | `/subscriptions/{id}/resume` | Reactivate (`mark_subscription_active`) | 200 detail | 400, 401, 403, 404, 409, 500 |
| POST | `/subscriptions/{id}/cancel` | Cancel now (`mark_subscription_cancelled`) | 200 detail | 400, 401, 403, 404, 409, 500 |

There is no create, update or delete route.

**List arguments:** `page` (>= 1), `per_page` (1-100, default 20), `search` (at most 100 characters:
subscription or order number, customer display name or email, plan name; at most 1000 matches considered),
`status` (`any` or a status), `orderby` (`date|id`), `order` (`asc|desc`). A page past the end answers 400
`ohmylms_subscription_invalid_page`. Post meta, users and plans are primed, so a page costs a constant
number of queries.

**Row:** `id, status, plan_id, plan_name, customer_id, customer_name, start_date, next_payment_date,
end_date, billing_period, billing_interval, recurring_amount, currency, allowed_actions`. No email, no gateway
data. `customer_name` is first and last name, else the display name (as the legacy screen shows it).
`recurring_amount` includes the subscription's tax rate, as before. **Detail** adds `customer_email,
original_order_id, payment_method, payment_method_title, last_payment_date, cancelled_date, date_created,
related_orders[] {id, relationship: parent|renewal, status, total, currency, date_created}` (at most 50, newest
first) and `notes[] {id, content, added_by, date_created}` (plain text, at most 50). Dates are site time,
ISO 8601 without offset, or `null`; money is a decimal string.

### Status changes

| Action | Allowed from | Result |
| --- | --- | --- |
| `pause` | active | on-hold; scheduled renewals are cleared |
| `resume` | on-hold, pending-cancel | active; the next renewal is scheduled when its date is in the future |
| `cancel` | pending, active, on-hold, pending-cancel | cancelled; renewals cleared, `_end_date` recorded |

`pending` (unpaid) cannot be activated here and `cancelled`/`expired` are final. Cancelling at the end of the
term is not offered because `pending-cancel` cannot be stored (see the audit).

- **Confirmation:** the body must be `{"confirm": true}`. Without it the answer is 400
  `ohmylms_subscription_confirm_required` and nothing changes. The admin screen asks first in a dialog.
- **Invalid transition:** 409 `ohmylms_subscription_invalid_transition` with `data.current_status` and
  `data.allowed_actions`; nothing changes, no hook fires. Resuming past `_schedule_end_date` answers 409
  `ohmylms_subscription_ended`.
- **Concurrency:** a per-subscription lock (`ohmylms_lock_subscription-{id}` option, 60 s) answers 409
  `ohmylms_subscription_busy`; the status is re-read after taking it.
- **Hooks:** the manager's `ohmylms_subscription_status_{status}`, `ohmylms_subscription_status_changed`,
  `ohmylms_subscription_on_hold`, `ohmylms_subscription_cancelled`, plus the legacy
  `ohmylms_update_subscription_status_to_active` / `_to_cancelled` (resume / cancel) and the new
  `ohmylms_rest_subscription_action( $id, $action, $previous_status )`. Filter: `ohmylms_rest_prepare_subscription`.
  The manager adds a note for each change, attributed to the administrator.

```http
POST /wp-json/ohmylms/v1/subscriptions/501/pause
X-WP-Nonce: <wp_rest nonce>
{ "confirm": true }

HTTP/1.1 409 Conflict
{ "code": "ohmylms_subscription_invalid_transition", "message": "A subscription cannot pause while it is on-hold.",
  "data": { "status": 409, "current_status": "on-hold", "allowed_actions": [ "resume", "cancel" ] } }
```

### Security notes

- One capability constant; no `__return_true`. Client values never reach `WP_Query` except the allow-listed
  status, sort and an ID list produced by a prepared, bounded search.
- Lists carry no email; gateway customer IDs and payment tokens are never returned.
- Status changes run only through `SubscriptionManager` after the transition and confirmation checks, under a
  lock. Notes are returned as plain text and rendered as text.
- Not covered: renewals and payments (Action Scheduler job) and editing schedule dates or amounts. The legacy
  route is retired (above).

### Verification

`tests/php/subscriptions-rest-integration.php` creates a tagged plan, customer, offline orders and six
subscriptions, and checks: routes and permission callbacks; anonymous, subscriber, contributor and editor denied
on every route with nothing changed; list totals, paging, filters, search, sorting and the absence of email;
the detail; confirmation; every invalid transition (409, post, meta and notes unchanged); the lock; pause,
resume and cancel with their hooks; the schema. It removes everything it created.
