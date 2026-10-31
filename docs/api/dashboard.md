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
