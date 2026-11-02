# Gamification repair and daily learning streaks

Implemented on the `codex/gamification-streaks` branch (on `origin`; the feature is part of `main`). Existing Performance removal and student-directory changes were preserved. No live learner records or settings were changed by the regression fixtures; WordPress integration and browser checks used the separate `ohmylms_streak_test` database with outbound HTTP and email blocked.

## Audit and repairs

- Badge lesson conditions incorrectly used the point balance. Level lesson conditions and next-level progress used zero. Shared `Engagement\Rules` now counts distinct completed lesson posts through the target learner's enrollment/progress records. Points and completed courses remain separate conditions.
- Badge evaluation and current-level lookup used the current login rather than the award target. Optional learner parameters retain compatibility with existing calls and support teacher/background awards. Verified lesson/course completion also evaluates conditions when earning points is disabled.
- Empty, malformed and unsupported conditions could qualify accidentally. Evaluation now fails closed; the rule editor supports points, completed lessons and completed courses, and REST writes validate conditions.
- Badge/level methods returned success after failed/duplicate persistence. They now return the actual database result, and success hooks/transients only follow a new successful write.
- The integration switch was the original outer gate. The old group `enable` fields were unused placeholders without UI controls. They are preserved; new explicit `feature_enabled` switches default on for legacy configurations and actually control earning, badges, levels, rewards and leaderboard behavior. Bundled features remain free; no payment entitlement was added.
- Achievement insertion now serializes a learner's existence check and write with a MySQL named lock. Existing awards stay one-time. Point spends have a distinct order identity, an atomic balance check, and an idempotent refund for exceptions before point payment completion. Point payment is checked before completion/enrollment; mixed cash/point carts, memberships and per-course-disabled rewards cannot bypass payment.
- Settings groups are allowlisted with an extension filter. Invalid numeric values, flags, milestone definitions and badge choices are rejected. Existing leaderboard modes (`completion_rate`, `highest_quiz`, `fastest_time`) remain supported. Existing normalized quiz ranking and fastest-completion logic were retained.
- Student profile level progress uses all actual conditions instead of a point-only bar. Streak milestone badges reuse the badge display and celebration hooks. Refreshes do not re-award milestones; reduced-motion preferences disable celebration animation/confetti.

## Streak behavior

Streaks default **off**. They track one overall learner streak across courses, subjects and Course Bundles independently of spendable points, levels and mastery.

Qualifying sources are a newly persisted completed lesson, a finalized quiz/assessment with meaningful saved answers, and a completed owned skill-practice session with at least three meaningfully answered problems by default. Wrong answers qualify. A quiz exit, empty answers, active/inline/guest practice, individual answers, mastery recomputations, login, purchases and social events do not qualify. Quiz ownership and responses are read from persisted attempts; lesson ownership is checked through progress/enrollment rows; practice uses the persisted session owner and items. There is no client activity-write endpoint. Server processing time determines the qualifying date, including automatic finalization of a saved quiz attempt.

Each source has a permanent learner/type/source identity and each learner/date has one daily row. Concurrent requests lock the learner state inside a transaction. Distinct activities on the same date remain in the ledger but add at most one streak day.

The saved streak timezone defaults to the learner's saved valid timezone, then the site timezone. Calendar arithmetic handles daylight-saving dates without assuming a day lasts 24 hours. Site timezone changes do not move existing streaks. To prevent date manipulation, a learner may change their streak timezone only after the streak has ended, at least 24 hours after their last qualifying date, and when the new timezone has reached a date later than the reconciled cursor. Dates already recorded retain their original timezone.

Past dates are reconciled on the next activity or read; cron is unnecessary. Today remains pending until completed or until its date ends. Each protected missed date consumes one previously available freeze and preserves, but does not increase, the count. Further missed dates break the current streak. Longest streak and other learning progress are retained.

Freeze defaults: two initial, maximum two, one replacement per seven qualifying learning dates. Protected/missed dates earn no refill credit. Full inventory banks no extra credit. Initial inventory applies only to new participants; lowering the cap trims inventory. Disabling streak recording does not create practice dates; when re-enabled, elapsed dates are reconciled. There is no retroactive protection from later earnings.

Milestones default to 7, 30 and 100 qualifying streak days, awarded once per learner across restarts. Administrators can configure days, an existing/built-in badge, and optional bonus points. Milestone badge rewards are controlled by streak settings; points require the explicit point feature to be enabled. Pending rewards retry on the next activity/dashboard read. Built-in badges do not appear as editable rule badges.

## Storage and API

Additive schema version `1`, recorded in `ohmylms_streak_schema`:

- `ohmylms_streak_state`: saved timezone, current/longest counts, freeze/refill inventory, reconciliation cursor, last activity timestamp.
- `ohmylms_streak_days`: practiced/protected/missed dates and their timezone.
- `ohmylms_streak_activities`: idempotent source identities and UTC processing timestamps.
- `ohmylms_streak_milestones`: durable one-time reward delivery queue.

All four use InnoDB and primary keys enforcing the identities. Installation is idempotent, verifies columns/storage engines, and runs when streaks are enabled. No existing achievement/progress table is rewritten and no historical streak is invented.

Authenticated `GET /ohmylms/v1/engagement/streak` returns the caller's state and daily history (7 dates by default, up to 366 with `history_days`). Authenticated `PUT` changes only the caller's timezone. The REST cookie nonce protections apply normally. Admin settings use the engagement settings endpoint with group `streak` (schema-validated partial updates; contract in [api/gamification.md](api/gamification.md#streak-xp-score-and-mastery-settings)); the new admin app edits them at `#/gamification?tab=streaks`.

## Enable and rollback

If the Gamification menu is hidden, enable the free Gamification add-on under Add-ons. Open **OhMyLMS → Gamification → Streaks** (also available under the existing gamification settings route). Enable learning streaks, choose activities/freezes/milestones, and save. The additive tables install at that step. The learner hub (Achievements) and the legacy student dashboard display the responsive card with current/longest counts, today's state, seven calendar dates, freeze balance, a learning action and timezone control. Optional point milestones require Bonus Point's feature switch to be enabled.

Disable streaks to stop new recording and hide the card. Existing streak tables, learning progress and awarded achievements remain stored. A code rollback should retain the additive tables rather than delete learner history. No automatic backfill is provided. PHP runtime integration was verified on PHP 8.2/MySQL; other supported PHP/database versions were not exercised. The new checkout eligibility/accounting services were integration-tested; external payment gateways were not transacted.

## Verification

Each milestone's **Add new badge** button opens the existing Achievement Badge editor. The badge is saved in the shared badge list and selected for that milestone; save streak settings to apply the assignment. Badges created here use `award_source: streak` with no ordinary achievement rules, so only milestone delivery awards them. They remain editable through Achievement Badges using the same editor.

Latest targeted results: 28 calendar checks and 76 isolated WordPress integration checks passed. Browser coverage includes six tests for admin tabs/rule editors/settings persistence, SDK loading and desktop/mobile dashboard states. The shared badge editor also opens correctly from the live Streaks tab.

- `php tests/streak-unit.php`: calendar/freezes/refill/restart, same-day idempotence, midnight, leap dates, DST and meaningful answers.
- `php tests/php/gamification-streak-integration.php /isolated/wordpress`: settings/permissions, verified sources, correct learner, lesson conditions/progress, disabled/failed/duplicate awards, freezes, one-time/pending milestones, point spending/refunds, cart eligibility and concurrent same/different-source requests. Requires an explicitly isolated test database and removes its fixture data via cleanup.
- `php tests/php/streak-card-fixture.php /isolated/wordpress` then `playwright test tests/browser/streak-card.spec.cjs`: generated dashboard states, desktop/mobile fit, accessible timezone control and mocked save feedback.
- Gamification browser test: all six tabs, direct navigation, settings load/save against isolated WordPress.
- Production SDK lazy-loading tests cover build and shipped bundles.
- Existing suite: 146 JavaScript tests, 16 registry/grading checks and 49 assessment checks passed. Source contracts, PHP syntax, formatting and production asset builds passed.

The normal production build remains `npm run build`. This workspace used its pinned Node/webpack tools directly because npm was not on the command path. Generated SDK assets were synchronized to the shipped admin directory.
