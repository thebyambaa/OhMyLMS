# Learner course and lesson journey

The course overview now links each lesson to its own page. That page lists its published activities in order, offers **Continue lesson**, and shows the learner's skill progress. The focused activity player groups its sidebar by unit and lesson, highlights the current activity, and offers lesson overview and previous/next available activity links. Taking an active quiz retains the existing isolated quiz navigation and exit behavior.

Lesson skill gaps and post-practice recommendations are described in [lesson practice guidance](LESSON-PRACTICE-GUIDANCE.md).

Course **Continue learning** and **Up next** use the existing learning-path decision, including required activity ordering, skill targets and locks. A lesson's own continue action selects its first available unfinished activity, including optional work. Navigation does not mark work complete. Reading/video completion uses the existing nonce-protected completion endpoint; quiz and practice results continue through their assessment/evidence writers.

## Separate progress measures

- **Activity completion** counts completed visible resources, including optional resources. Practice is excluded from this denominator. Course completion still follows required activities and skill targets.
- **Skill proficiency** counts selected skills at Proficient or above. Each lesson also displays the linked skills' current labels. Completing a reading/video does not grant mastery.
- Practice navigation uses the learner's actual skill target, so a higher target remains available even when the skill is already Proficient.

The existing `summary.percent`, `done` and `total` fields retain their compatibility behavior. The outline adds `activity_done`, `activity_total`, `activity_percent`, `mastery_percent`, `completed`, current skill labels, stable placement IDs and activity availability. Units and lesson groups also include activity counts.

## Access and publication

`hub_lesson={lesson UUID}` is an additive read-only parameter on the existing course overview URL. Lesson lookup uses only the enrolled learner's assigned published program. An unknown lesson, missing enrollment or protected course yields an unavailable lesson message. It never loads the author's draft outline or embeds activity bodies before their existing access gates. Native course redirects retain valid lesson parameters. Logged-in course pages use private/no-cache responses, like the student workspace.

Lesson pages preserve group UUIDs, resource IDs, shared skills and existing completion records. Activity navigation separates content IDs from skill IDs and omits unavailable destinations. Author changes to native unit metadata remain drafts until publication; enrollment version selection and upgrades are unchanged. Shared resource bodies retain their existing live-content behavior.

## Verification

`tests/php/learner-journey-integration.php` requires `OHMYLMS_STRUCTURE_INTEGRATION=1` and local WordPress. It creates a separate subscriber learner, publishes a grouped course and exercises the existing administrative enrollment and learner completion controllers. It checks denied access before enrollment, lesson ordering, separate progress, stable resume after completion, completion retry safety, frozen unit metadata, lock filters and content/skill ID separation. Practice cannot be marked complete through the reading endpoint.

Its `fixture` mode creates an ignored expiring browser session. Run Playwright with `tests/learner-journey.config.cjs`, then run the PHP fixture with `cleanup`. The browser verifies the enrollment card and access gate, uses the existing administrative enrollment controller, completes a reading, reopens the course, resumes at the next activity, and checks desktop/mobile lesson views. Commerce checkout is outside this test. Cleanup removes the learner, session, fixture content, program/outcome indexes, enrollment and progress, and restores the original workspace settings.

Local verification on October 24, 2026: 18 learner journey checks, 22 outline checks, 61 shared structure checks, 37 model checks, 295 structure tool checks and 33 subject checks passed. The learner browser flow passed. JavaScript tests: 591 passed, one existing skip. PHP syntax, the new helper/templates' WordPressCS, source contracts and scoped CSS passed. Existing standards findings remain in the legacy learning renderer/workspace and unrelated CSS. No generated assets needed rebuilding for these PHP/CSS changes.
