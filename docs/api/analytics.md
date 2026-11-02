# Dashboard and analytics REST API (`ohmylms/v1/dashboard`, `ohmylms/v1/analytics`)

Contract version 2. The plugin is pre-release, so this replaces the recovered API outright; there is
no compatibility layer. The machine-readable form is
`assets/src/app/features/dashboard/contract.json`. Server code:

- `includes/Rest/V1/DashboardController.php` (`/dashboard`)
- `includes/Rest/V1/AnalyticsController.php` (`/analytics/earnings|transactions|courses`, the
  `CAPABILITY` constant)
- `includes/Rest/V1/DashboardProController.php` (`/analytics/courses/{id}` and `.../students`)
- `includes/Analytics/` (`DateRange`, `Repository` (all SQL), `Cache`, `RestSupport`)

## Audit before

| Severity | Finding |
| --- | --- |
| High | **Financial data for contributors.** `/dashboard`, `/analytics/course/{id}` and `/dashboard/courses/{id}` answered to `edit_posts`, so any author or contributor could read revenue, refunds, order totals and (for the course report) student names and emails. Everything now needs `manage_options`. |
| High | **Fatal errors.** `AnalyticsController` used `WP_Error` without importing it into its namespace; every error path (`invalid course`, `invalid date`, the non-Pro guard) raised "Class not found" and answered 500. |
| High | **Wrong money.** The course earnings query joined one row per order *item* but summed the order's `_order_total`, so a multi-item order was counted once per item. Refunds were looked up per order with `ohmylms_ecommerce_get_order()` inside the loop. Partial refunds were counted in one report and ignored in another (`DashboardPro` ignored them completely; `earning_overview` growth ignored them in `previous`). A refunded order was added to `earning` and `refund`, so gross and refund no longer matched across screens. |
| High | **Not scoped to the range.** `date` filters used `MONTH(post_date) = MONTH(CURRENT_DATE())` and `WEEK()` (non-sargable, server timezone, ISO week mismatch); `get_all_courses...` and `total_students` ignored the filter or used it inconsistently; `get_top_performed_course_last_30_days` used UTC-based `strtotime`. |
| High | **Unvalidated dates and enums.** `filter`, `start_date`, `end_date`, `sort_by`, `completion_type` had no REST `args`. Custom dates reached `new DateTime()` unvalidated (exception = 500 with a stack, or arbitrary strings), start after end and multi-year spans were accepted, `data_type` and `filter` were free text. |
| Medium | **N+1 queries.** Per course: `ohmylms_get_course`, three COUNT queries, thumbnails, ratings; per order: an order object and a refunds `WP_Query`; per student: `new Student()`, `get_userdata`, `get_over_all_completion_rate`, per-chapter objects and a loop over every content item to find "skipped" quizzes (roughly O(students x content)). `get_all_courses_with_sales_count_and_enrollment` ran the same query twice and loaded every course unpaged. |
| Medium | **Unbounded result sets.** The dashboard loaded every course, every order of the filter and every student of a course in one response; `count_unchecked_orders()` and the transactions list used `posts_per_page => -1` or `limit 10` with a full `WP_Query` and post-filtering (so the `type` filter shrank pages and `X-WP-Total` was never sent). `GET /dashboard/courses` accepted any `per_page`. |
| Medium | **Unsafe SQL patterns.** `$wpdb->prepare( $query, $course_id )` on a string that already contained `{$course_id}`, queries run unprepared when no filter applied, `$query .= $wpdb->prepare(...)` fragments (placeholders in already-escaped text), `ORDER BY` built from `sort_by` through `WP_Query` only by accident. No injectable value was found, but several paths only avoided injection through `intval`. |
| Medium | **Personal data.** The course student report returned name, email, avatar, reminder history and skipped content per student with no pagination. `order_by_country` and the transaction list are aggregate-only (kept). |
| Medium | **Currency.** Amounts were floats from `floatval( meta )` and the symbol was an HTML-entity-decoded string; the client could not format by currency code, decimals or position. Orders store `_order_currency`; mixed-currency sites would be summed together (not solved, see below). |
| Medium | **Pro gate.** `ohmylms_is_pro()` returns `true` unconditionally, yet two routes kept an unreachable guard that returned an "Invalid ID" error. Removed. |
| Low | `DATE(p.post_date) BETWEEN` prevents index use; fixed by comparing the column to datetime bounds. |
| Low | No `get_item_schema()` for any of the routes, no `args`, no pagination headers, no shared date helper (the preset switch was copied four times with different end-of-range rules, e.g. `today->setTime(23,59,59)` vs no time), response keys differed between routes (`total_earning`, `total_revenue`, `earning`, `net_amount`, `net_income`). |
| Low | Missing indexes (reported, tables not altered): `ohmylms_order_itemmeta (meta_key(32))` exists but not `(order_item_id, meta_key)`; `ohmylms_user_enrollment` has no `(course_id, status, start_date)` or `(status, start_date)` index (the report filters on all three); `ohmylms_user_progress` has no `(enrollment_id, status)`. |
| OK | `$wpdb->esc_like()` on searches, prepared date bounds in most queries. |

## Definitions

- An **order counts** when its status is `ohmylms-completed` or `ohmylms-refunded`. It belongs to
  the day of its `post_date` (site time). Pending, processing, cancelled and trashed orders are ignored.
- **Gross** is the sum of `_order_total`. **Refunds** of an order are its whole total when the status is
  `ohmylms-refunded`, otherwise the sum of its non-trashed `ohmylms_order_refund` children
  (`_refund_amount`), never more than the total. **Net** = gross - refunds.
- Per course, **gross** is the line item's `_line_total`; the order's refunds are allocated to its items
  **pro rata** to the line totals. A **sale** is one course line item of a counted order.
- **Enrolled students** are distinct users with an `enrolled` enrolment whose `start_date` is in the
  range. Blocked (`banned`) enrolments are excluded from counts but listed in the course roster.
- Amounts are JSON numbers in major units rounded to the currency decimals. Compute changes in the
  client; the server returns the previous period instead of a growth percentage.
- **Previous period**: the same number of days directly before the range.

## Date range arguments (every route except the students list)

| Argument | Values |
| --- | --- |
| `range` | `today`, `last_7_days`, `last_30_days` (default), `last_90_days`, `this_month` (to today), `last_month`, `this_year` (to today), `last_12_months` (from the first day of the month 11 months ago), `custom` |
| `start`, `end` | `YYYY-MM-DD` site time, inclusive; required for `custom`, ignored otherwise; at most 731 days |

Ranges up to 92 days are charted per `day`, longer ones per `month` (`range.granularity`). Errors:
`400 rest_invalid_param` (unknown preset, malformed date), `400 ohmylms_analytics_invalid_range`
(missing dates for `custom`, impossible date such as 2001-02-30, start after end, more than 731 days).

## Final routes

All routes require `manage_options` (`AnalyticsController::CAPABILITY`). Anonymous callers get `401`,
logged-in users without the capability get `403 ohmylms_analytics_cannot_view`. Argument validation
runs before the permission callback (core behaviour).

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/dashboard` | Overview: summary with previous period, series, totals, 5 recent and 5 popular courses | 200 | 400, 401, 403, 500 |
| GET | `/analytics/earnings` | Summary, series, orders by country and by payment method | 200 | 400, 401, 403, 500 |
| GET | `/analytics/transactions` | Paged orders of the range | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403, 500 |
| GET | `/analytics/courses` | Paged course report (money and students per course) | 200 + pagination headers | 400, 401, 403, 500 |
| GET | `/analytics/courses/{id}` | One course: content, money, students, series | 200 | 400, 401, 403, 404, 500 |
| GET | `/analytics/courses/{id}/students` | Paged students of a course with progress | 200 + pagination headers | 400, 401, 403, 404, 500 |

The recovered routes `/dashboard/courses`, `/dashboard/courses/{id}`, `/analytics/course/{id}` are removed.

Collection arguments: `page` (>= 1), `per_page` (1-100, default 20).
`transactions`: `search` (order number), `payment_method`, `orderby` (`date|id|total|refunded|net`,
default `date`), `order`. `courses`: `search` (title), `orderby`
(`title|date|sales|gross|refunds|net|enrollments`, default `net`), `order`. `students`: `search` (name,
username, email), `progress` (`all|running|completed`), `orderby` (`name|enrolled_at|percent`),
`order`; students are not limited to a date range. A page beyond the last answers
`400 ohmylms_analytics_invalid_page`. A range with no data is a normal `200` with empty arrays/zeros.

## Examples

`GET /wp-json/ohmylms/v1/dashboard?range=custom&start=2001-03-01&end=2001-03-31`

```json
{
  "generated_at": "2026-10-30T09:15:00",
  "range": { "preset": "custom", "start": "2001-03-01", "end": "2001-03-31", "days": 31,
             "granularity": "day", "previous_start": "2001-01-29", "previous_end": "2001-02-28" },
  "currency": { "code": "USD", "symbol": "$", "decimals": 2, "position": "left" },
  "summary": {
    "current":  { "orders": 3, "gross": 330, "refunds": 110, "net": 220, "sales": 4, "enrollments": 2 },
    "previous": { "orders": 1, "gross": 25, "refunds": 0, "net": 25, "sales": 1, "enrollments": 0 }
  },
  "totals": { "courses": 3, "published_courses": 2, "students": 2 },
  "series": [ { "date": "2001-03-05", "orders": 2, "gross": 250, "refunds": 30, "net": 220 } ],
  "recent_courses": [ { "id": 41, "title": "Algebra", "status": "publish", "date": "2026-10-01T10:00:00",
      "sales": 2, "gross": 150, "refunds": 10, "net": 140, "enrollments": 2, "completed": 1,
      "permalink": "https://example.test/course/algebra/", "thumbnail_url": null } ],
  "popular_courses": []
}
```

Transaction row: `{ "order_id": 77, "date": "2001-03-05T18:00:00", "status": "completed",
"payment_method": "paypal", "payment_title": "PayPal", "total": 150, "refunded": 30, "net": 120,
"items": [ { "course_id": 41, "title": "Algebra" } ] }`. Order rows carry no customer name, email or address.

Course student row (personal data): `{ "user_id": 12, "name": "Ada", "email": "ada@example.com",
"avatar_url": "...", "status": "enrolled", "progress": "running", "percent_complete": 66,
"enrolled_at": "2001-03-10T09:00:00", "completed_at": null, "lessons": { "completed": 1, "total": 2 },
"quizzes": { "completed": 1, "total": 1 }, "assignments": { "completed": 0, "total": 0 } }`.
Percent: 100 for a completed enrolment, otherwise `floor(100 * done / total)` with each kind capped at
its total (same rule as `/students/{id}/enrollments`).

## Error codes

| Code | Status | Meaning |
| --- | --- | --- |
| `ohmylms_analytics_cannot_view` | 401 / 403 | Missing `manage_options` |
| `rest_invalid_param` | 400 | Argument outside its type, enum, range or date format |
| `ohmylms_analytics_invalid_range` | 400 | See "Date range arguments" |
| `ohmylms_analytics_invalid_page` | 400 | Page beyond the last |
| `ohmylms_analytics_course_not_found` | 404 | Not an existing, non-trashed course |
| `ohmylms_analytics_query_failed` | 500 | Database error (details are not exposed) |

## Hooks kept

- Filter `ohmylms_dashboard_data( $data, DateRange $range )`: the `/dashboard` payload (the payload shape is new).
- Action `ohmylms_rest_analytics_course_data( $data, $course_id )`: after `/analytics/courses/{id}` was built.
- New filter `ohmylms_analytics_cache_ttl( $seconds )`; `OhMyLMS\Analytics\Cache::flush()` drops all cached aggregates.

## Performance notes

- Every figure is one aggregate query; no order, course or student object is built per row. A page of
  transactions is two queries plus one batched query for its items; a page of students is a count and one query
  with its progress grouped in a derived table.
- `/dashboard` and `/analytics/earnings` aggregates are cached in transients for 60 seconds (key = route +
  range + a version number). They are not invalidated by single writes; the short lifetime bounds
  staleness. Tables (transactions, courses, students) are never cached.
- Result sets are capped (5 course cards, 10 countries, 20 payment methods, 100 rows per page); the range
  is capped at 731 days, so a chart never exceeds 92 daily or 25 monthly points.
- Reported, not changed: add `(order_item_id, meta_key)` on `ohmylms_order_itemmeta`,
  `(course_id, status, start_date)` and `(status, start_date)` on `ohmylms_user_enrollment`, and
  `(enrollment_id, status)` on `ohmylms_user_progress`. Order amounts live in `postmeta`; a large store
  should move to a dedicated orders table.

## Known limits

- Orders in different currencies are summed as if they used the site currency (`_order_currency` is not read).
- Refunds are attributed to the order's date, not the refund's date.
- The recovered "skipped quizzes" and reminder history of the course student report are not part of this
  API. Roster writes (enrol by e-mail, bulk unenrol, fixed-template reminders) live in
  `CourseRosterController` under the same `/analytics/courses/{id}` prefix and are documented in
  [students.md](students.md#course-roster-courserostercontroller); the old open-relay notification
  route now answers 410.
