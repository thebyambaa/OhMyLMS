# Lesson skill gaps and practice guidance

Course practice shows the skill's current evaluated level, target, practice gap and due review. The suggested action prefers an available unmet target at a lower level, then a due review. When no practice recommendation remains, guidance continues along the flattened unit order (lesson groups were flattened into their unit in `b0e21ca5`): the first open activity of the skill's unit (**Continue unit**), else the first open activity of a later unit (**Next unit**), else the course challenge. **Back to unit progress** opens the skill's unit on the course overview.

Question availability uses the existing authorized practice pool: approved versions, non-secure questions, supported automatic grading, and the learner's assigned course banks. A skill with no eligible questions shows an unavailable explanation and is not recommended. Counts describe the maximum questions in a normal session, including generated template instances. The existing adaptive selector still controls difficulty, question families, staged lesson practice, hints and retries.

Course practice shows guidance below the runner. When a non-placement session summary renders, the runner emits `ohmylms:practice-complete` with only `courseId` and `skillId`. The small guidance client rereads current server state, replaces the escaped guidance fragment and refreshes matching practice labels in the player/lesson list. It does not calculate mastery or mark activities complete. Session grading, evaluated evidence and the configured performance policy remain authoritative. Pending evidence produces an evaluation message rather than a claim that the target was reached.

**Refresh skill progress** also updates the card after work in another tab. Failed refreshes retain the previous information, show an error and allow retry. If completion occurs during a refresh, another read follows so an older response cannot consume the completion update. Lesson and course progress links remain available.

## API and compatibility

`GET /ohmylms/v1/courses/{id}/learning/practice-guidance` needs a `skill` ID; without one it returns 400. The `lesson` argument is still accepted but ignored, and the response's `lesson_id` is always empty. The caller must be signed in and enrolled in the published course. Password protection and assigned program versions apply. Responses are private and not cached.

The response includes safe skill descriptions, evaluated target state, planned session limits, practice URLs, the recommendation, continuation and an escaped owned-template `html` fragment. It contains no questions, answer keys, frozen settings or another learner's state. The endpoint and event are additive. Question extensions, assessment contracts, grading writers and mastery thresholds are unchanged.

## Verification

The learner fixture runs these checks by default and in its `practice` and `practice-fixture` modes. They create three approved practice questions, one unapproved question and one approved exam-only question, then answer a real session through the existing grader. They verify pool filtering, progress updates, continuation within the unit and into the next unit, denied foreign skills and anonymous access. The fixture removes temporary question versions, practice sessions/items, grading/evidence/state, learner sidecars and the temporary learner during cleanup.

Run with `OHMYLMS_STRUCTURE_INTEGRATION=1`, PHP and the local WordPress database. `tests/practice-guidance.config.cjs` runs the browser flow after the `practice-fixture` setup. It verifies automatic refresh to Proficient/target reached, refreshed player labels, **Continue unit**, refresh failure/retry and persisted results after returning to the unit. Commerce and mastery thresholds are not changed for these checks.

Local verification: 38 learner/practice integration checks and four pure recommendation checks passed. The browser practice flow passed. The JavaScript suite passed 591 tests with one existing skip, including the additive completion event. New helper/template WordPressCS, authored guidance ESLint/Prettier, scoped CSS and source contracts passed. Existing PHP/CSS/JavaScript standards findings elsewhere remain; no blanket baseline or disabled rule was introduced. These authored PHP/JS/CSS assets are served directly and do not require a generated bundle rebuild.
