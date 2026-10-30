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

The streak, XP, score and mastery settings stay at `engagement/settings/<streak|xp|score|mastery>` and were hardened in
place; see [Streak, XP, score and mastery settings](#streak-xp-score-and-mastery-settings). Not part of this API: the
learner-facing templates. `student-dashboard.php` builds the learner leaderboard from
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
| PUT / PATCH / POST | `/gamification/settings/{section}` | Partial update; only fields sent change; optional `revision` | 200 (section) | 400, 401, 403, 409, 422, 500 |
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
| GET | `/engagement/settings/{streak|xp|score|mastery}` | One group (mastery adds its choices) | 200 | 401, 403 |
| POST / PUT / PATCH | `/engagement/settings/{streak|xp|score|mastery}` | Partial update; only fields sent change | 200 `{success, message, settings}` | 400, 401, 403, 422, 500 |

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

Every single-section GET and save answer also carries `revision` (16 hex characters of a hash of the stored option;
not part of `GET /gamification/settings`). Send the loaded `revision` with a save: the option row is locked
(`SELECT … FOR UPDATE` inside `OhMyLMS\Utility\Transaction`) and a different stored revision answers
`409 ohmylms_gamification_conflict` with `data.revision`, writing nothing. Without `revision` the save is
unconditional (scripts, MCP). A save that changes nothing keeps the revision.

## Rewards (`#/gamification?tab=rewards`)

Screen: `RewardsTab.tsx` in `assets/src/app/features/gamification`, its own tab (it used to share the leaderboard
tab). It replaces the legacy `#/gamification/reward-settings` and `#/settings/gamification-settings/reward-settings`
screen (`assets/src/features/gamification/RewardSettings.jsx`). The tab saves with the loaded `revision`, offers a
reload after a 409, and warns when the Points section is off (learners could not earn anything to spend).

What the front end reads: a course is sold for points only when the Gamification add-on is on, `enabled` and
`purchase_with_points` are both true (`Reward::maybe_met_rules( 'purchase_course' )`, used by
`template-functions.php`, `Point` and `Reward::valid_point_cart()`), and the course has a point price above 0 and is
not opted out (`_purchase_point`, `_reward_disabled`, edited in the course settings, not here). Both flags are
booleans in the schema; unknown fields (including the legacy `enable`/`rules` shape) answer 400.

| Severity | Finding (legacy reward screen) |
| --- | --- |
| Medium | **The legacy screen no longer worked.** It read and posted `engagement/settings/reward` with `{ enable, rules: [ { slug: "purchase_course", value } ] }`; contract v1 removed that group, so it showed the defaults and every save failed with a generic "Something went wrong". |
| Medium | **Concurrent saves overwrote each other** silently (no revision); now 409 for any section when `revision` is sent. |
| Low | The screen merged a client-side default rule list into the server answer and showed a threshold input the server never used; the rule tooltip described course completion points instead of purchases. |
| OK | `manage_options` and the core REST nonce; the per-course price and opt-out stay on the course. |

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

## Streak, XP, score and mastery settings

Screens: `#/gamification?tab=streaks` (streak group) and `#/gamification?tab=xp` (XP, mastery score and mastery model, each
saved on its own) in `assets/src/app/features/gamification` (`StreaksTab.tsx`, `XpTab.tsx`, `engagementApi.ts`,
`engagementModel.ts`, `useEngagement.ts`). The recovered `streak-settings` and `xp-settings` tabs and the MCP
`get/set_performance_settings` tools keep working: they post whole groups and read `success`/`message`.

### Audit before

| Severity | Finding |
| --- | --- |
| Medium | **No schema or argument validation.** One regex route served the four groups with no `args` and no `schema`; the body went straight to each group's `validate()`. Types were checked there, but every failure was one generic 400 (`streak_settings`, `xp_settings`, `score_settings`) without the field. |
| Medium | **A partial request reset the rest to the defaults.** `StreakSettings`, `XpSettings` and `Score::validate()` merge the body over the *defaults*, so `POST {"refill_days": 9}` silently reset every other streak setting (including milestones). |
| Medium | **Unknown keys were dropped silently** and the mastery group's read-only GET keys (`policies`, `surface_options`, `ai_ready`) were accepted without notice. |
| Low | Cross-field rules (initial vs maximum freezes, duplicate milestone days, the default goal outside the goals, unknown badges) answered the generic 400 or `streak_badge` 400. Only POST/PUT/PATCH (`EDITABLE`) existed; the save answer did not carry the stored values, so clients re-read every group. |
| OK | `manage_options` on both methods (401 anonymous, 403 others), core REST nonce; values are bounded integers, flags and allow-listed policies; storage tables install only when a group is enabled. The learner routes `GET/PUT engagement/xp` and `engagement/streak` are per-user (`is_user_logged_in`), bound `history_days` (7-90 / 7-366) and validate `goal` and `timezone`; they are unchanged. |

### Contract

- One route per group (`/engagement/settings/(?P<group_id>streak)` and so on) with a draft-04 schema (`OPTIONS`, title
  `engagement-<group>-settings`) whose writable properties are the route `args`. Types and ranges fail with core
  `rest_invalid_param` (400, `data.params` per field); unknown keys with `rest_invalid_param` (400). Numeric and flag strings
  (`"10"`, `"0"`) from the recovered screens are still accepted and normalised.
- **Partial updates:** only the fields sent change; the rest keep their *stored* values. The group's own `validate()` runs
  last, so nothing weaker than before is stored. Nothing is written unless the whole request is valid.
- **422 with `data.field`:** `ohmylms_streak_freezes` (`initial_freezes` above `maximum_freezes`), `ohmylms_streak_milestone_days`
  (two milestones on the same day), `ohmylms_streak_badge` (a milestone badge that is neither a custom nor a built-in badge;
  empty means no badge), `ohmylms_xp_default_goal` (default goal not among the goals). These replace the old 400 `streak_badge`.
- **Answer:** `{ "success": true, "message": "Settings saved successfully.", "settings": { ...same shape as GET } }`.
- **Mastery:** read-only `policies`, `surface_options` and `ai_ready` may be posted back and are ignored; `policy` is an enum of
  the registered policies; unknown `surfaces` keys are dropped as before. Changing the policy or the surfaces still schedules
  the background rebuild.

| Group | Fields (ranges) |
| --- | --- |
| streak | `enable, lesson, quiz, practice, freezes` (booleans); `practice_minimum` 1-30; `initial_freezes` 0-10 (<= maximum); `maximum_freezes` 0-10; `refill_days` 1-365; `milestones` <= 20 of `{days 1-10000 (unique), badge (slug or ""), points 0-100000}` |
| xp | `enable`; `lesson`, `perfect` 0-1000; `challenge` 0-100; `min_answered` 1-30; `goals` 1-6 integers 1-10000 (de-duplicated, sorted); `default_goal` one of the goals; `goal_bonus` 0-1000; `daily_cap` 10-100000 |
| score | `enable`; `bands` exactly 4 of `{gain, loss}` 0-50; `hint_gain` 0-1; `unlock` 50-100; `decay_grace_days` 0-365; `decay_per_week` 0-20 |
| mastery | `policy` (registered policy); `surfaces` {activity: boolean}; `show_score`, `ai_insights` (booleans) |

```http
PATCH /wp-json/ohmylms/v1/engagement/settings/xp
{ "goals": [ 30 ] }

HTTP/1.1 422 Unprocessable Entity
{ "code": "ohmylms_xp_default_goal", "message": "The default goal must be one of the goal choices.",
  "data": { "status": 422, "field": "default_goal" } }
```

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
| 409 | `ohmylms_gamification_conflict` (`data.revision`) | A section save with a stale `revision`. |
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
restores every option it touches (including `ohmylms_streak_settings`, `ohmylms_xp_settings`, `ohmylms_score_settings` and
`ohmylms_performance`) and removes its temporary users and attachments. For the four engagement groups it checks denied
callers, the legacy full POST, partial updates, string normalisation, every schema and 422 rule with the option unchanged
afterwards, the mastery round trip and the group schemas. It never enables streaks or XP, so no table is installed.

`tests/php/gamification-rewards-rest-integration.php` (same flag) covers the rewards section: denied callers, the
revision on GET and saves, a stale revision refused with nothing written, partial and unconditional saves, the
legacy shape refused, and the front-end readers following the flags and the add-on switch. It restores the reward
and integration options.
