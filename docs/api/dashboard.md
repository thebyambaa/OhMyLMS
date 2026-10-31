# Scoped dashboard (`ohmylms/v1/dashboard/scopes`, `ohmylms/v1/dashboard/widgets/*`)

Phase B of the [admin redesign](../ADMIN-REDESIGN.md): the Dashboard page for site administrators, school
administrators and teachers, each limited to their own schools and classes. This document is the contract
for the next widget engineers: the PHP API (scope, period, cache, widget base class), the REST routes and
the React registry. The older site-wide overview (`GET /dashboard`, earnings and course lists) is unchanged
and documented in [analytics.md](analytics.md); on the new page it is the `overview` widget.

Server code: `includes/Dashboard/` (domain), `includes/Rest/V1/DashboardScopesController.php` and
`DashboardWidgetsController.php` (routes, registered by `Dashboard\Bootstrap`). Client:
`assets/src/app/features/dashboard/` (`DashboardPage.tsx`, `shell/`, `widgets/`).

## Who sees what

| User | Scopes | Commerce and site-wide figures |
| --- | --- | --- |
| Site administrator (`manage_options`) | `all`, every active school, every active class (school and independent) | only in `all` |
| School administrator (active `school_admin` membership of an active school) | their schools and those schools' active classes | never |
| Teacher (active `teacher` member of an active class; for a school class also an active teacher of that school; for an independent class the teacher role or a school teacher membership) | the classes they teach | never |
| Anyone else (students, parents, subscribers, a teacher without classes) | none: no dashboard | never |

Rules come from current Account Hub rows (`Schools\Schema` tables), as in `Schools\Access`; roles alone
grant nothing. The meta capability **`ohmylms_view_dashboard`** (`Dashboard\Access::CAPABILITY`) maps to
`manage_options` for site administrators, to `read` for users with at least one scope and to
`do_not_allow` otherwise. It is exposed to the app through `ohmylms_app_capabilities`. School staff are
otherwise kept out of wp-admin (`Student\LoginExperience`); `Dashboard\Access::keep_dashboard_open()`
(filter `ohmylms_hide_admin_for_user`) opens exactly `admin.php?page=ohmylms` to them.

"Never show commerce to scoped staff" is enforced twice: commerce is computed only for the `all` scope,
which only site administrators can resolve, and per-item links are dropped for users who lack the
target screen's capability.

## PHP API

### `OhMyLMS\Dashboard\Scope`

A value: `all`, `school:<id>` or `class:<id>`. It says what is counted, never who may see it.

| Method | Returns |
| --- | --- |
| `Scope::from_request( WP_REST_Request $request, int $user_id = 0 )` | `Scope` or `WP_Error`: reads `scope` (default: the user's first scope) and checks it against the user's scopes |
| `type()`, `id()`, `label()`, `school_id()` | `all|school|class`, numeric id (0 for all), display name, the school (itself, a class's school, or 0) |
| `is_all()`, `includes_commerce()` | true only for `all` |
| `key()` / `cache_key()` | `all`, `school:3`, `class:9` (stable; the cache key does not depend on the user) |
| `learner_ids()` | `int[]` (sorted): a school's active student members; a class roster (`Schools\Gradebook::roster()`). **`null` for `all`** (unrestricted) |
| `course_ids()` | `int[]`: courses the learners are enrolled in plus courses linked to the class (or the school's classes, `course_classes`), trashed courses excluded. **`null` for `all`** |
| `learner_condition( $column, &$args )`, `course_condition( $column, &$args )` | SQL fragment for `WHERE … AND <fragment>`: `col IN (%d,…)` with values appended to `$args`, `1 = 1` for `all`, `1 = 0` for an empty scope |
| `Scope::in_condition( $column, ?int[] $ids, &$args )` | the same for any id list |
| `to_array()` | `{ id, type, label, school_id, school_name, commerce }` |
| `Scope::arg()`, `Scope::PATTERN` | REST argument (`^(all|school:N|class:N)$`) |

Errors of `from_request()` / `ScopeResolver::resolve()`: `401`/`403 ohmylms_dashboard_cannot_view` (no
dashboard access), `400 rest_invalid_param` (malformed), `403 ohmylms_dashboard_forbidden_scope` (not one of
the user's scopes), `404 ohmylms_dashboard_scope_not_found` (site administrators only: no such active
school or class). School staff always get 403 for a scope outside theirs, so they never learn whether a
school or class exists.

### `OhMyLMS\Dashboard\ScopeResolver`

`available( $user_id )` (scopes in picker order: all, each school followed by its classes, other classes,
independent classes; memoised per request, `reset()` clears), `resolve( ?string $value, $user_id )`.
Filter `ohmylms_dashboard_scopes( Scope[] $scopes, int $user_id )` may only remove scopes.

### `OhMyLMS\Dashboard\Period`

`new Period( 7|30|90 )` (other values: 30) or `Period::from_request( $request )` (`period`, default 30):
the last N site-local days ending today.

| Method | Returns |
| --- | --- |
| `days()` | 7, 30 or 90 |
| `range()`, `previous()` | `Analytics\DateRange` of the period and of the N days before (`start_sql()`, `end_sql()`, `start`, `end`) |
| `Period::last_week( DateRange $window )` | `[ start, end ]` datetimes of the window's last 7 days |
| `day_keys()` | `Y-m-d` of every day of the period (one sparkline point each) |
| `cache_key()`, `to_array()` | `30d:2026-10-31`; `{ days, start, end, previous_start, previous_end }` |

### `OhMyLMS\Dashboard\Cache`

Transients per widget + scope + period (+ optional extra inputs) + a version number.

| Method | Effect |
| --- | --- |
| `Cache::remember( $widget, Scope $scope, Period $period, callable $compute, array $extra = array() )` | cached array, or computes and stores it; `last_was_hit()` tells which |
| `Cache::ttl( $widget )` | filter `ohmylms_dashboard_cache_ttl( 600, $widget )`; 0 disables caching |
| `Cache::flush()` | drops every entry (raises `ohmylms_dashboard_cache_version`) |
| `Cache::invalidate()` | `flush()` at most once per request; hooked to `ohmylms_after_assignment_review`, `ohmylms_order_status_changed`, `ohmylms_manual_student_enrollment`, `ohmylms_student_unenrolled` |

Cached values must be the same for everyone who may see the scope. Per-user state (snoozes, links the user
may open) is applied after reading. Other writes (attempts, completions, roster changes) are bounded by the
10-minute lifetime.

### Adding a widget endpoint: `OhMyLMS\Dashboard\Widget`

```php
final class AtRisk extends \OhMyLMS\Dashboard\Widget {
	public function id() { return 'at-risk'; }
	public function compute( Scope $scope, Period $period ) {
		// Aggregate SQL limited with $scope->learner_condition() / course_condition(); no names here
		// unless the widget's purpose needs them, then only display names.
		return array( 'learners' => array() );
	}
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		return $data; // Per-user, uncached adjustments.
	}
	public function available( Scope $scope, $user_id ) {
		return true; // false answers 403 ohmylms_dashboard_widget_unavailable.
	}
}
add_filter( 'ohmylms_dashboard_widgets', fn( $w ) => $w + array( 'at-risk' => AtRisk::class ) );
```

The route `GET /dashboard/widgets/at-risk` then exists with access checks, scope and period resolution,
caching, `Cache-Control: private, no-store`, `X-OhMyLMS-Cache: hit|miss` and `500
ohmylms_dashboard_query_failed` for a `RuntimeException`. Put SQL in a read model (see
`Dashboard\Repository`), read enrolments only through `Dashboard\Enrolments` (see Known gaps), and add a
case to `tests/php/dashboard-rest-integration.php` (fixtures: `tests/php/support/dashboard-fixture.php`).

### At-risk provider

Return an `OhMyLMS\Dashboard\AtRiskProvider` (`high_risk_count( Scope, Period ): int`,
`route( Scope ): ?string`) from the filter `ohmylms_dashboard_at_risk_provider` to add the "learners at
high risk" item to Needs attention. Without a provider the item is not shown.

## REST routes

All below `/wp-json/ohmylms/v1`. Anonymous `401`, no dashboard access `403 ohmylms_dashboard_cannot_view`.

| Method | Path | Arguments | Success |
| --- | --- | --- | --- |
| GET | `/dashboard/scopes` | — | `{ scopes: Scope[], default: string|null, site_admin: bool }` |
| GET | `/dashboard/widgets/<id>` | `scope`, `period` (7, 30, 90; default 30) | `{ widget, scope: Scope, period, generated_at, data }` |
| POST | `/dashboard/widgets/needs-attention/snoozes` | `scope`, `key` (both required) | `{ scope, key, until }` (UTC) |
| DELETE | `/dashboard/widgets/needs-attention/snoozes` | `scope`, `key` | `{ scope, key, removed }` |

Other errors: `404 ohmylms_dashboard_widget_not_found`, the scope errors above, `400 rest_invalid_param`.
`generated_at` is when the cached figures were computed (UTC).

### Needs attention (`needs-attention`)

`data: { items[], snoozed, grading_days }`, items most urgent first:

| Key | When | Scope | Action (route, capability) |
| --- | --- | --- | --- |
| `grading` | assignment submissions `submitted` more than N days ago (`ohmylms_dashboard_grading_wait_days`, default 3) | learners and courses | `/assignments/<oldest>/submissions`, `edit_posts` |
| `failed_payments` | orders `ohmylms-failed` dated in the period | `all` only | `/orders`, `manage_options` |
| `manual_approval` | orders `ohmylms-on-hold` (any date) | `all` only | `/orders`, `manage_options` |
| `at_risk` | provider count > 0 | provider's | provider route, `ohmylms_view_dashboard` |
| `stalling_course:<id>` | ≥ 5 enrolled learners, completion < 30 %, with the content item where the most learners stop (largest drop in completions against the item before) | learners and courses | `/reports/courses/<id>`, `manage_options` |
| `missed_question:<id>` | in finished attempts of the period, ≥ 5 learners answered and ≥ 60 % of answers wrong | learners and courses | `/content-hub/question-bank/<id>`, `edit_posts` |

Thresholds: filter `ohmylms_dashboard_attention_rules` (`stalling`: `min_learners`, `max_rate`, `limit` 3;
`missed`: `min_learners`, `min_rate`, `limit` 3). Item: `{ key, urgency, tone: error|warning|info, title,
detail, count, action: { label, route } | null }`. Titles and details are server-translated sentences with
counts and content titles only; no learner names, emails or logins. `action` is null when the user lacks the
capability. Filter `ohmylms_dashboard_needs_attention_items( $items, Scope, Period )` adds items before
caching (shared data only). Snoozes: user meta `ohmylms_dashboard_snoozes` (`"<scope>|<key>" => expiry`),
7 days, per user and per scope, at most 200 kept.

### Pulse (`pulse`)

`data: { dates[], currency|null, metrics[] }`, each metric `{ id, format: money|number, value, previous,
series[] }` with one value per date:

| Id | Value / previous | Series | Scope |
| --- | --- | --- | --- |
| `revenue` | net earnings of the period / previous period (Analytics definitions) | net per day | `all` only |
| `active_learners` | distinct learners active in the period's last 7 days / the previous period's last 7 days | distinct active learners per day | learners and courses |
| `completions` | enrolments completed (`progress = completed`, by `end_date`) | per day | learners and courses |
| `renewals_at_risk` | failed renewal orders (orders with `_subscription_renewal_id`) + `on_hold` subscriptions now | per day | `all` only |

Activity = an enrolment, a progress row, a quiz attempt or an assignment submission starting.

### Course health (`course-health`)

`data: { courses[], total, limit }`: the scope's courses with enrolled learners, most enrolled first, at
most `limit` (filter `ohmylms_dashboard_course_health_limit`, default 12, 1–50); `total` = courses of the
scope with learners. Each course `{ id, title, enrolled, completed, completion, rating, items, lessons,
median_progress, drop_off, report }`, figures for the scope's learners **now** (not the period):

| Field | Definition |
| --- | --- |
| `enrolled`, `completed`, `completion` | distinct learners with an `enrolled` enrolment; of them `progress = completed`; whole % |
| `rating` | `{ average (1 decimal), count }` of approved top-level course reviews (comment meta `rating` 1–5) **by the scope's learners** (any author in `all`); `null` without reviews |
| `median_progress` | per enrolment, completed content items ÷ the course's published items (100 when the enrolment is completed, capped at 100), the median; `null` without enrolments |
| `drop_off` | `{ content_id, title, type: lesson|quiz|assignment|content, position, lost, percent }`: the published item (course order) with the largest loss of learners against the item before (everyone enrolled for the first item; counts capped by the item before; ties keep the earlier item); `percent` = lost ÷ enrolled. `null` when nobody is lost. Same rule as `stalling_course` |
| `report` | `/reports/courses/<id>` for users with `manage_options`, else `null` (added per user) |

Read model `Dashboard\CourseHealthReport` (memoised per request, shared with Insights): 5 aggregate
queries plus one structure read per course; enrolment SQL in `Dashboard\Enrolments`
(`courses_by_enrolment`, `completed_items_by_course`, `progress_histogram`). The client shows a table
(band label Healthy / Watch / At risk from completion and rating) and, for rated courses, a completion ×
rating SVG scatter with a text alternative.

### Insights (`insights`)

`data: { insights[] }`, at most 5 (`limit`), sorted by severity (`error`, `warning`, `success`, `info`)
then rule order below. Each `{ key, rule, severity, text, action: { label, route } | null }`; `text` is a
server-translated sentence with the numbers. Rules are pure functions in `Dashboard\InsightRules` over
figures the widget reads for the scope and period; **no AI and no external calls**. Thresholds: filter
`ohmylms_dashboard_insight_rules` (numbers only, clamped; `limit` 1–5).

| Rule (`key`) | Figures | Fires when (defaults) | Severity | Action (capability) |
| --- | --- | --- | --- | --- |
| `revenue` | net revenue of the period vs the previous period (`Analytics\Repository::summary`), top course by net | **`all` scope only** (site administrators; removed again in `present()` for anyone else); any revenue in either period | `success` up ≥ 5 %; `warning` down 5–19 %; `error` down ≥ 20 % (`bad_drop`); `info` "steady" under 5 % (`min_change`) | `/earnings` (`manage_options`) |
| `drop_off:<course>` | Course health `drop_off` | course with ≥ 5 enrolled (`min_learners`) and an item losing ≥ 30 % (`min_percent`); the largest | `error` from 50 % (`bad_percent`), else `warning` | `/reports/courses/<id>` (`manage_options`) |
| `class_gap:<class>` | levels from `Performance\Snapshot::students()` for the most started skills (≤ 15) of the scope's classes (≤ 25 classes, ≤ 2000 learners) | **school and `all` scopes only**, ≥ 2 classes; a class whose average is ≥ 15 pts (`min_points`) below the other classes' learners on a skill, both sides ≥ 3 assessed learners (`min_learners`); the largest gap. Points: attempted 25, familiar 50, proficient 80, mastered 100; not started is not counted; a learner in both never counts as "other" | `error` from 30 pts (`bad_points`), else `warning` | none |
| `missed_question:<id>` | `Repository::missed_questions` (finished attempts in the period) | ≥ 5 learners answered (`min_learners`) and ≥ 60 % of answers wrong (`min_rate`); the worst | `warning` from 75 % (`bad_rate`), else `info` | `/content-hub/question-bank/<id>` (`edit_posts`) |
| `quiz_lessons` | `Enrolments::lessons_by_quiz_taking` over the Course health courses with lessons: share of their lessons completed by enrolments with a finished quiz attempt in the course vs without | both groups ≥ 5 enrolments (`min_enrolments`), the difference ≥ 15 points (`min_points`) and the ratio ≥ 1.5 (`min_ratio`, skipped when the other group is at 0 %) | `info` (suggests reminder e-mails) | `/emails` (`manage_options`) |
| `completions` | course completions of the period vs the previous period | ≥ 5 in both periods together (`min_total`) and a change ≥ 20 % (`min_change`), or any after none | `success` up, `warning` down | none |

Fewer than three rules may fire; the widget then shows fewer sentences (never filler). Actions are dropped
per user without the capability; capabilities are never sent.

### Happening now (`activity`)

`data: { events[], limit }`, newest first, at most `limit` (filter `ohmylms_dashboard_activity_limit`,
default 10, 1–25), events of the period only. Each `{ id: "<type>:<row id>", type, at (ISO 8601 with the
site offset), text }`. Types: `enrolled`, `course_completed`, `lesson_completed` (progress row), `quiz_passed`,
`quiz_failed`, `quiz_completed` (finished attempts, by `end_date`), `assignment_submitted`, and `order`
(paid orders, **`all` scope only**, "New order #N · amount", no buyer). Each source is one bounded query
(`ORDER BY … DESC LIMIT n`) limited to the scope's learners and courses. Learners appear by display name
only; a display name containing `@` falls back to the first name, else "A learner"; no e-mail, login or
user id is sent. Cached 60 seconds (`Widgets\Activity::ttl` on `ohmylms_dashboard_cache_ttl`).

### Site health (`site-health`)

Site administrators in the `all` scope only (otherwise 403 `ohmylms_dashboard_widget_unavailable`; the
client registers it with `isAvailable: siteAdmin && scope.commerce`). Not cached (ttl 0): it reads a few
options. `data: { checks[], passed, total }`, each `{ id, status: ok|warning|error, label, detail,
action: { label, route|null, href|null } | null }` (`route` = app route, `href` = wp-admin URL):

| Id | Passes when | Fix link |
| --- | --- | --- |
| `payment_gateway` | a registered gateway is enabled with every credential of its current mode (`Settings\PaymentGatewaySettings`) | `/settings?tab=payment-gateways` |
| `email_sender` | sender name and a valid sender address are set (`Emails::get_email_settings()`) | `/emails` |
| `certificate_template` | at least one published certificate (detail: courses that award one) | `/certificates` |
| `cron` | the oldest queued cron event is at most 15 minutes overdue (`error` beyond; `warning` with an empty queue; mentions `DISABLE_WP_CRON`) | `site-health.php` |
| `privacy_policy` | `wp_page_for_privacy_policy` is a published page | `options-privacy.php` |

### At-risk learners (`at-risk`)

`data: { learners[], counts: { high, watch }, total, rules: { inactive, mastery, overdue }, nudge: { cap,
remaining, interval_hours } }`; learner `{ id, name, initials, level: high|watch, reasons: [{ rule, value,
severe, text }], summary, report|null, nudged_at|null, nudged_label, can_nudge }`, most urgent first (50 shown,
500 cached). Rules (`Dashboard\Widgets\AtRiskRules`, settings group `at-risk`): inactive ≥ 7 days (activity
as in Pulse plus skill evidence; only learners with an open enrolment), mastery down ≥ 10 points in 14 days
(the 0-100 `ScoreModel` score replayed from evidence at the window start and now, largest drop over a skill),
overdue ≥ 1 Account Hub class assignment due in the period and not completed (class/school of the scope).
High = 2+ rules or one at its severe threshold (14 days, 20 points, 3 items); watch = 1 rule. `report` only
for `manage_options`. `AtRiskCount` is the built-in `ohmylms_dashboard_at_risk_provider` (high count, route
`/dashboard?scope=<key>`).

| Method | Path | Arguments | Success / errors |
| --- | --- | --- | --- |
| POST | `/dashboard/widgets/at-risk/nudges` | `scope`, `learner` (required), `period` | `201 { learner, scope, nudged_at, nudged_label, next_at, remaining, guardians }`; 403 `ohmylms_at_risk_out_of_scope`, 409 `ohmylms_at_risk_not_flagged` / `_nudge_disabled`, 422 `_no_address`, 429 `_nudge_too_soon` / `_nudge_daily_cap` (`Retry-After`), 500 `_nudge_failed` |
| GET | `/dashboard/widgets/at-risk/nudges` | `learner` | the log, site administrators only (403 `ohmylms_at_risk_log_forbidden`) |
| GET, PATCH | `/settings/at-risk` | the group's fields | settings contract; 400 types/unknown, 422 `ohmylms_settings_invalid_value` per field |

Nudges send the `student_at_risk_nudge` email (Emails), optionally one copy per active guardian
(`nudge_guardians`, off by default); one per learner per sender per 72 h and `nudge_daily_cap` (20) per sender
in 24 h, under a per-sender DB lock; every attempt is logged in `{prefix}ohmylms_at_risk_nudges` (sender,
learner, scope, reasons, guardian copies, status, UTC time; rows removed when either user is deleted). Tests:
`tests/php/at-risk-integration.php` (`OHMYLMS_AT_RISK_TEST=1`), live fixtures `tests/php/at-risk-browser-fixture.php`.

### Mastery heatmap (`mastery-heatmap`)

Read model `Dashboard\MasteryRepository` over `student_skill_state` (derived from the skill evidence). A
learner's percentage on a skill is the stored 0–100 `mastery` score; they are *assessed* when the row has
evidence. Levels (`Widgets\MasteryLevels`, filter `ohmylms_dashboard_mastery_thresholds`): rounded percentage
≥ 95 Mastered, ≥ 80 Proficient, ≥ 50 Familiar, otherwise Attempted; no evidence is Not started (never
"struggling"). Rows: the scope's classes (a class scope is its class; at most 20, `rows_total` tells how many
exist); a scope without classes uses its courses with the most enrolled learners (`Enrolments::learners_by_course`).
At most 1000 learners read per row. Columns: the 40 skills most of those learners have evidence on. A cell is
the mean percentage over **all** the row's learners (unassessed count as 0) and that mean's level.

`data: { row_type: class|course, rows_total, rows[{ key: class:N|course:N, id, label, learners }], skills[{ id,
name, code, subject, assessed }], subjects[{ name, skills }], cells[{ row, skill, percent, level, assessed,
below }], levels[{ id, label, min|null }], limits{ rows, skills }, can_open_reports }`; only cells with evidence
are listed. Counts only, no names. The client pages 8 skills at a time and filters by subject (skill meta
`_ohmylms_shared_subject`).

`GET /dashboard/widgets/mastery-heatmap/learners?scope=&row=class:N|course:N&skill=N` (`Widgets\MasteryController`):
learners of that row below Familiar, lowest first, at most 50: `{ scope, row, skill{ id, name, code }, below,
assessed, learners[{ id, name (display name), percent, level, route }], limit }`. `route` is
`/students/<id>/report` for `manage_options` users, else null. Cached like a widget (extra key: row, skill).
Errors: `404 ohmylms_dashboard_row_not_found` (row not shown by the scope, so teachers reach only their
class), `404 ohmylms_dashboard_skill_not_found`, `400 rest_invalid_param`, and the scope errors.

### Recommended next assignments (`review-sets`)

A group is ≥ 3 learners of one class row with evidence on a skill and a percentage below Familiar (10 skills
per class considered, largest 8 groups). `data: { row_type, min_group, total, groups[{ key: class:N|skill:N,
class{ id, key, label, learners }, skill{ id, name, code }, learners, questions{ available, needed, draw, enough },
review_set|null }], can_assign }`. `questions` comes from `Assessment\Pools::available()` (different approved
families; 3 needed, 5 drawn, filter `ohmylms_dashboard_review_set_questions`), so the widget says "not enough
questions" instead of failing. `can_assign` (quiz `edit_posts`, or school staff with a dashboard scope) and
`review_set` (`{ id, title, status, route }`, route `/quizzes/<id>` only with `edit_post` and the Content page
capability) are per user, uncached.

`POST /dashboard/widgets/review-sets/assign` (`scope`, `class_id`, `skill_id`; `Widgets\ReviewSetsService`) creates
a **draft** quiz through `Assessment\SkillAssessment::create()` (one random pool by skill, `publish: false`,
created as the current user; never published) titled "<skill> review set (<class>)", with post meta
`_ohmylms_review_set_key` (`class:N|skill:N`) and `_ohmylms_review_set` (class, skill, learner ids, creator).
201 `{ scope, created: true, review_set{ …, questions } }`; an existing non-trashed set of the group answers
200 `created: false` (one per group; a MySQL advisory lock serialises double submits, waiting 5 s, then 409
`ohmylms_dashboard_review_set_busy`). Teachers and school administrators who cannot create quizzes may still
create the draft for a class among their own dashboard class scopes (a class they teach, or a class of a school
they administer): the quiz routes run with the quiz type's `edit_posts` granted to them for that call only, so
the draft is authored by them but they cannot edit or publish it afterwards (no editor route); a quiz author
publishes it from Content › Quizzes. Errors: 403 `ohmylms_dashboard_review_set_forbidden` (cannot create
quizzes), 403 `ohmylms_dashboard_forbidden_scope` (anyone but a site administrator naming a class that is not
theirs), 404 row/skill, 409 `ohmylms_dashboard_review_set_no_group` (fewer than 3 now), 409
`ohmylms_dashboard_review_set_not_enough_questions` (`data.available`, `data.needed`; nothing created).
Action `ohmylms_dashboard_review_set_created( $quiz_id, $class_id, $skill_id, $learner_ids )`.

## React shell and widget registry

`DashboardPage.tsx` loads `/dashboard/scopes`, keeps `?scope=` and `?period=` in the URL (an unknown or
forbidden scope falls back to the default), renders the scope picker (`SelectControl` with one `optgroup`
per school; hidden for a single scope), the 7/30/90 switcher (pressed buttons) and every widget of
`widgets/index.ts` that applies, each in its own grid cell, `ErrorBoundary` and `Suspense`.

To add a widget: create `widgets/MyWidget.tsx` (default export, props `{ context }` with `scope`,
`period`, `siteAdmin`), render it inside `shell/WidgetCard` (postbox markup with loading, error, retry and
empty states), load data with `shell/useWidget( id, context, parse )`, and add one entry to
`builtInWidgets` (`id`, `title`, `order`, `size: full|wide|narrow`, optional `isAvailable( context )`,
`component: lazy(…)`). The page needs no change. `createWidgetRegistry()` ignores duplicate or malformed
ids. Sparklines use `widgets/Sparkline.tsx` (plain SVG, `currentColor`).

## Tests

- `tests/php/dashboard-rest-integration.php` (`OHMYLMS_DASHBOARD_TEST=1`): 183 checks — capability per
  role, scopes per user, resolution errors, learners and courses of a scope, archived classes, every
  needs-attention item per scope, no commerce or names for staff, links per capability, caching (hit/miss,
  shared key, invalidation, ttl 0), snoozes (per user and scope, expiry, undo, validation), the at-risk hook,
  pulse per scope and period, and the wp-admin opening. Fixtures are tagged and removed.
- `tests/php/dashboard-insights-integration.php` (`OHMYLMS_DASHBOARD_TEST=1`): 136 checks — drop-off and
  median progress on fixed figures, every insight rule at and around its thresholds, ordering and the limit,
  threshold clamping, cron and money formatting; then course health, insights, happening now and site health
  per scope and user (school administrator, teacher, administrator), ratings by scope, no names or commerce
  for staff, links per capability, bounds (course and event limits), caching and the admin-only site checks.
  Extra fixtures (reviews, a skill with states, paid orders): `tests/php/support/dashboard-insights-fixture.php`.
- `tests/php/dashboard-mastery-integration.php` (`OHMYLMS_DASHBOARD_TEST=1`): 128 checks — level thresholds
  and rounding, filtered and invalid thresholds, heatmap cells on fixtures per scope (school, class, other
  school, all, course rows), bounds (20 of 21 classes, 40 of 41 skills), no names in widget data, the learner
  list (order, links per capability, rows outside the scope), recommendations (group size, bank counts), and
  review sets: permissions, shortages and small groups leave nothing behind, draft only with one pool and no
  revision, idempotent repeat and cross-scope repeat, the advisory lock, trashed sets, caching and timings.
  Fixtures: `tests/php/support/mastery-fixture.php`; live: `tests/php/dashboard-mastery-browser-fixture.php
  create|cleanup mastlive<digits>`.
- `tests/php/dashboard-browser-fixture.php create|cleanup dashlive<digits>` for live checks.
- `npx vitest run --configLoader runner features/dashboard`: shell (`shell/DashboardShell.test.tsx`),
  registry, both widgets and the overview widget (`DashboardPage.test.tsx`).

## Known gaps

- **Page access needs the coordinator's change** to `includes/Admin/AdminNavigation.php` and
  `includes/Assets/AdminAppAssets.php`: the Dashboard page (top-level menu, its submenu, `can_open( 'dashboard' )`)
  and its assets must require `ohmylms_view_dashboard` instead of `manage_options`. Until then school staff
  can use the endpoints but not open the page.
- `Student\Enrollments` has no bulk reads yet; the dashboard's enrolment and progress SQL is isolated in
  `Dashboard\Enrolments` (legacy `ohmylms_user_enrollment`, still authoritative) and moves there once the
  service offers course ids per learner set, completions per day, low-completion courses and completions
  per content item.
- Actions for school staff are hidden (the targets are site-administrator screens); hub links for staff are
  not offered yet.
- On very large sites the `all` scope reads every activity row of the period (cached for 10 minutes).
