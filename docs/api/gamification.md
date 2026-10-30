# Gamification REST API (`ohmylms/v1/gamification`)

Contract version 1. The plugin is pre-release, so this replaces the old `ohmylms/v1/engagement/badges`,
`.../levels` and `.../settings/{point,badge,level,reward,leaderboard}` routes outright; there is no
compatibility layer. Machine-readable form: `assets/src/app/features/gamification/contract.json`.
Server code: `includes/Rest/V1/EngagementController.php` (settings, options, point adjustments, legacy groups),
`includes/Engagement/Rest/LevelsController.php` and `BadgesController.php`, `GamificationController.php` (base),
`includes/Engagement/GamificationRegistry.php` (events, allow-lists, limits, defaults, schema) and
`GamificationStore.php` (storage, normalising, validation). Runtime readers: `Point`, `Level`, `Badge`,
`Reward`, `Leaderboard`, `Rules`, `Achievements`.

## Audit before

| Severity | Finding |
| --- | --- |
| High | **Badge and level criteria were barely validated.** `save_badges` stored the whole request body (any keys, any `image` string, including remote URLs); `delete_badges` and `delete_levels` accepted *any array* and overwrote the whole option with it, so one request could replace every badge with arbitrary data. |
| High | **Points were not bounded as integers.** Rules accepted numeric strings, floats and values up to 1,000,000; `Point::add_points()` accepted `5.5`, and a settings request could carry unsanitised email `body` HTML that was later sent with `wp_mail()`. |
| High | **Settings groups saved unvalidated for extension groups.** The `ohmylms_engagement_setting_groups` filter let any string become `ohmylms_<group>_settings` written with `update_option()` without validation. Removed. |
| High | **Level ladder was not validated.** Levels could share thresholds, depend on arbitrary rule combinations, be sorted with a subtraction comparator (`usort` returning `a - b`), and were stored twice (`ohmylms_levels` and inside `ohmylms_level_settings`). |
| Medium | **Slug identity was fragile.** The slug came from the name on create, but an update posted a slug from the client and replaced the first match; two badges with the same name overwrote each other's identity. Slugs are now unique and immutable. |
| Medium | **Leaderboard leaked student records.** `get_students_by_*` returned whole student arrays to the `ohmylms_leaderboard_students` filter. They now return only id, position, name and score. The completion-rate board cut the list to `limit` *before* applying the threshold. |
| Medium | **No award or revoke operation**, and no log of who changed a learner's points. Added idempotent badge award/revoke and point adjustments, each recording the administrator. |
| Medium | **Permission** was a custom `has_permission()` returning `false` for anonymous callers (always 403, never 401). Now one constant, `GamificationController::CAPABILITY` (`manage_options`), 401 anonymous / 403 others. |
| Medium | **Error handling:** 400 for every failure, `404` never; `save_levels` read `$new_level['rules']` before checking it was an array; the level save message said "Badges saved". Consistent codes and statuses below. |
| Low | `Rules::valid()` accepted `=` and `==`, float strings and `INF`-like values; `get_option()` was called per rule; `Badge::maybe_met_rules()` ran the lesson-count SQL once per badge (now once per learner per call). `Reward` rules had an unused threshold comparison. |
| OK | Cookie authentication: core verifies `X-WP-Nonce` before any permission callback. SQL uses `$wpdb->prepare()` and `%i`. Award rows are inserted under a per-learner `GET_LOCK`. |

Not part of this API (unchanged): streak, XP, score and mastery settings, still at
`engagement/settings/<streak|xp|score|mastery>` (same validation and capability; they have no authored screen yet), and
the learner-facing templates. `student-dashboard.php` builds the learner leaderboard from
`Course::get_students()`, which loads every student of a course: that is an unbounded query outside this API.

## Storage

One option per section (`ohmylms_point_settings`, `ohmylms_level_settings`, `ohmylms_badge_settings`,
`ohmylms_reward_settings`, `ohmylms_leaderboard_settings`) plus the collections `ohmylms_levels` and `ohmylms_badges`.
Every reader goes through `GamificationStore`, which merges defaults and drops anything outside the registry
(unknown events, malformed items, out-of-range numbers). Sections default to **off**. A section is only usable when the
Gamification add-on is on in Add-ons *and* the section's `enabled` flag is on (`Rules::enabled()`).
The filters `ohmylms_point_settings`, `_level_`, `_badge_`, `_reward_` and `_leaderboard_settings` still apply on read.

## Final routes

All routes require `manage_options`. Anonymous callers get `401`, logged-in users without it `403`. Argument validation runs
before the permission callback (core behaviour).

| Method | Path | Purpose | Success | Errors |
| --- | --- | --- | --- | --- |
| GET | `/gamification/settings` | All sections plus `integration_enabled` | 200 | 401, 403 |
| GET | `/gamification/settings/{section}` | One section (`points`, `levels`, `badges`, `leaderboard`, `rewards`) | 200 | 401, 403 |
| PUT / PATCH / POST | `/gamification/settings/{section}` | Partial update; only fields sent change | 200 (section) | 400, 401, 403, 422, 500 |
| GET | `/gamification/options` | Metrics, operators, leaderboard modes, image types, limits | 200 | 401, 403 |
| GET | `/gamification/levels` | List, lowest threshold first (`page`, `per_page`) | 200 + `X-WP-Total`, `X-WP-TotalPages`, `Link` | 400, 401, 403 |
| POST | `/gamification/levels` | Create | 201 + `Location` | 400, 401, 403, 422 |
| GET / PUT / PATCH / DELETE | `/gamification/levels/{slug}` | Read, update, delete | 200 (delete: `{deleted, previous}`) | 400, 401, 403, 404, 422 |
| GET | `/gamification/badges` | List (`source=custom|streak|all`, default `custom`) | 200 + totals | 400, 401, 403 |
| POST | `/gamification/badges` | Create | 201 + `Location` | 400, 401, 403, 422 |
| GET / PUT / PATCH / DELETE | `/gamification/badges/{slug}` | Read, update, delete (system streak badges: 409) | 200 | 400, 401, 403, 404, 409, 422 |
| POST | `/gamification/badges/{slug}/awards` | Award by hand (`user_id`); idempotent | 201 new, 200 already held | 400, 401, 403, 404, 500 |
| DELETE | `/gamification/badges/{slug}/awards/{user_id}` | Revoke; idempotent | 200 `{revoked: 0|n}` | 401, 403, 404 |
| POST | `/gamification/points/adjustments` | Add or remove points by hand, once per `reference` | 201 new, 200 replay | 400, 401, 403, 404, 409, 422, 500 |
| GET / POST | `/engagement/settings/{streak|xp|score|mastery}` | Existing groups, unchanged | 200 | 400, 401, 403, 500 |

Collections accept `page` (>= 1), `per_page` (1-100, default 20) and `context`. A page past the end answers 400
`ohmylms_gamification_invalid_page`. Levels and badges are capped at 50 and 100.

## Sections

```json
{ "enabled": true, "rules": [
  { "event": "lesson_complete", "label": "Lesson completion", "description": "Award points when a learner finishes a lesson.",
    "supports_threshold": false, "enabled": true, "points": 5, "threshold": null,
    "email": { "enabled": false, "subject": "", "body": "" } } ] }
```

- **points**: one rule per allow-listed event (13: `course_completion_rate`, `lesson_complete`, `quiz_passing_mark`,
  `assignment_passing_mark`, `submit_quiz`, `submit_assignment`, `comment_on_course`, `user_registration`, `course_enrollment`,
  `purchase`, `community_post_create`, `community_post_comment`, `community_reaction`). `points` is an integer 0-100000.
  `threshold` (0-100, a percentage) exists only for `course_completion_rate`, `quiz_passing_mark` and `assignment_passing_mark`;
  `null` resets to the default. `label`, `description`, `supports_threshold` are read-only. Rules sent in a request change
  only those events; each event may appear once. An enabled email needs a subject (plain text, 150) and a message
  (`wp_kses_post`, 5000).
- **levels**, **badges**: `{ "enabled": bool }`.
- **leaderboard**: `{ "enabled", "mode": "completion_rate|highest_quiz|fastest_time", "threshold": 0-100, "limit": 1-100 }`.
- **rewards**: `{ "enabled", "purchase_with_points" }`.

## Levels and badges

```json
{ "slug": "silver", "name": "Silver", "description": "", "color": "#6e42d3", "text_color": "#ffffff", "points": 100, "earned_count": 12 }
```

Level thresholds (`points`, 0-1,000,000) are unique, so the ladder is strictly increasing; the list is always ordered by
threshold. A level may start at 0. Deleting a level keeps earlier awards in the log.

```json
{ "slug": "starter", "name": "Starter", "description": "", "image_id": 41, "image_url": "https://example.test/u/starter.png",
  "color": "#6e42d3", "award_source": "criteria", "source": "custom", "earned_count": 3,
  "criteria": [ { "metric": "points", "operator": ">=", "value": 10 } ] }
```

- `criteria`: 1-10 items, all must be met. `metric` is `points`, `completed_lesson` or `completed_courses`; `operator` is one of
  `>= > <= < == !=`; `value` an integer 0-1,000,000. These are fixed allow-lists (`GamificationRegistry`).
- `award_source: streak` badges take no criteria and are awarded by streak milestones. The system streak badges
  (`streak-7`, `streak-30`, `streak-100`) are read-only (409).
- `image_id` is a media-library attachment id. It must be a JPEG, PNG, GIF or WebP attachment (422 `ohmylms_badge_invalid_image`);
  `image_url` is derived and read-only. Remote URLs are not accepted. `null` or `0` removes the image.
- Slugs come from the name, are unique among custom and system badges (`-2`, `-3`, ...) and never change.

## Awards and adjustments

- `POST .../awards {"user_id": 9}` inserts one `badge` row with `reason = manual:<administrator id>` unless the learner already
  holds the badge (then 200 `awarded: false`). Revoking sets the row's `status` to `revoked` and appends
  ` | revoked:<administrator id>:<UTC timestamp>` to `reason`; the row is kept. Both run under the per-learner lock.
  Awarding fires `ohmylms_after_badge_added`.
- `POST /points/adjustments {"user_id": 9, "points": -5, "reference": "refund-1042", "note": "Goodwill"}`: `points` is a
  non-zero integer within +-100000; `reference` (`[A-Za-z0-9_.-]`, 1-60) is the idempotency key. The row records
  `manual:<reference>:<administrator id>:<note>`. Repeating a reference answers 200 `created: false` with the current balance.
  A deduction cannot take the balance below zero (422 `ohmylms_points_insufficient`). Additions fire `ohmylms_after_point_added`,
  so levels and criteria badges are awarded as for earned points; deductions fire `ohmylms_after_point_deduct`.
  Levels are never taken away when points fall.

## Error codes

| HTTP | Code | When |
| --- | --- | --- |
| 400 | `rest_invalid_param`, `rest_missing_callback_param` | Schema violations: types, ranges, enums, hex colours, unknown fields, unknown events, image URLs, too many criteria. |
| 400 | `ohmylms_gamification_invalid_page` | Page past the last page. |
| 401 / 403 | `ohmylms_gamification_forbidden` | Not logged in / missing `manage_options`. |
| 403 | `rest_cookie_invalid_nonce` | Cookie auth without a valid `X-WP-Nonce` (core). |
| 404 | `ohmylms_level_not_found`, `ohmylms_badge_not_found`, `ohmylms_gamification_user_not_found` | Unknown slug or user. |
| 409 | `ohmylms_badge_system`, `ohmylms_points_busy` | System badge change; another award is running. |
| 422 | `ohmylms_gamification_duplicate_event`, `_threshold_unsupported`, `_email_incomplete` | Points section rules. |
| 422 | `ohmylms_level_invalid_name`, `_invalid_points`, `_points_taken` (`data.conflicting_slug`), `_limit` | Level rules. |
| 422 | `ohmylms_badge_invalid_name`, `_invalid_criteria`, `_criteria_not_allowed`, `_invalid_image`, `_limit` | Badge rules. |
| 422 | `ohmylms_points_zero`, `ohmylms_points_insufficient` | Adjustments. |
| 500 | `ohmylms_gamification_save_failed`, `ohmylms_badge_award_failed`, `ohmylms_points_adjust_failed` | Database failure. |

## Hooks kept

Actions `ohmylms_point_settings_updated` ($points_section), `ohmylms_after_point_added`, `ohmylms_after_point_deduct`,
`ohmylms_after_point_refunded`, `ohmylms_after_badge_added`, `ohmylms_after_level_added`, `ohmylms_gamification_trigger_used`.
Filters `ohmylms_point_settings`, `ohmylms_level_settings`, `ohmylms_badge_settings`, `ohmylms_reward_settings`,
`ohmylms_leaderboard_settings` (now receive the normalised section) and `ohmylms_leaderboard_students`. Removed:
`ohmylms_engagement_setting_groups`.

## Security notes

- One capability constant; cookie requests need the core REST nonce. No route is public.
- Only registry values reach storage: events, metrics, operators and leaderboard modes are enumerations, numbers are bounded
  integers, colours hex, text `sanitize_text_field`, email HTML `wp_kses_post`. Responses are JSON.
- `Leaderboard::public_rows()` limits learner-facing rows to id, position, display name and score.
- Award and adjustment writes happen under `GET_LOCK` per learner and are logged on the row with the administrator id.

## Tests

`tests/php/gamification-rest-integration.php` (set `OHMYLMS_GAMIFICATION_TEST=1`; see the file header). It snapshots and
restores every option it touches and removes its temporary users and attachments.
