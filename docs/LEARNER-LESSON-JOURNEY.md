# Learner course and lesson journey

A lesson is a block lesson item inside its unit; lesson groups were flattened into their unit in `b0e21ca5`, and there are no separate lesson pages. The course overview lists each unit's published activities in order (`#hub-unit-N` anchors, by unit position). The focused activity player groups its sidebar by unit, highlights the current activity, links back to the current unit on the overview, and offers previous/next available activity links. Taking an active quiz retains the existing isolated quiz navigation and exit behavior.

Skill gaps and post-practice recommendations are described in [lesson practice guidance](LESSON-PRACTICE-GUIDANCE.md). Once a skill is met, guidance continues at the first open activity of the skill's unit (**Continue unit**), else the first open activity of a later unit (**Next unit**), else the course challenge, using the same flattened unit order as the rest of the journey (`LessonJourney::continue_from()`).

Course **Continue learning** and **Up next** use the existing learning-path decision, including required activity ordering, skill targets and locks. Navigation does not mark work complete. Reading/video completion uses the existing nonce-protected completion endpoint; quiz and practice results continue through their assessment/evidence writers.

## Separate progress measures

- **Activity completion** counts completed visible resources, including optional resources. Practice is excluded from this denominator. Course completion still follows required activities and skill targets.
- **Skill proficiency** counts selected skills at Proficient or above. Completing a reading/video does not grant mastery.
- Practice navigation uses the learner's actual skill target, so a higher target remains available even when the skill is already Proficient.

The existing `summary.percent`, `done` and `total` fields retain their compatibility behavior. The outline adds `activity_done`, `activity_total`, `activity_percent`, `mastery_percent`, `completed`, current skill labels, stable placement IDs and activity availability. Units also include activity counts; their `groups` key is always empty and `ungrouped` equals `items`, kept for template overrides.

## Access and publication

Old lesson page addresses (`hub_lesson={lesson UUID}` on the course overview or the course permalink) redirect with a 301 to the overview at the group's unit (`Student\LegacyLessonLinks`). The unit is read from the learner's assigned publication or the current one, which still name their groups when published before `b0e21ca5`; an unknown or malformed UUID lands on the overview without an anchor. Visitors land on the public course page. Nothing generates these addresses any more. Logged-in course pages use private/no-cache responses, like the student workspace.

Activity navigation separates content IDs from skill IDs and omits unavailable destinations. Author changes to native unit metadata remain drafts until publication; enrollment version selection and upgrades are unchanged. Shared resource bodies retain their existing live-content behavior.

## Verification

`tests/php/learner-journey-integration.php` requires `OHMYLMS_STRUCTURE_INTEGRATION=1` and local WordPress. It creates a separate subscriber learner, publishes a course, rewrites the stored publication to name a lesson group as one published before `b0e21ca5` would, and exercises the existing administrative enrollment and learner completion controllers. It checks denied access before enrollment, unit ordering, separate progress, stable resume after completion, completion retry safety, frozen unit metadata, lock filters, content/skill ID separation, the old lesson URL redirect (target and 301), and that the overview and guidance generate no lesson-group links. By default (and in `practice`/`practice-fixture`) it also runs a real practice session and checks the guidance continue link within the unit and into the next unit. Practice cannot be marked complete through the reading endpoint.

Its `fixture` and `practice-fixture` modes create an ignored expiring browser session. Run Playwright with `tests/learner-journey.config.cjs` or `tests/practice-guidance.config.cjs` (set `OHMYLMS_CHROMIUM_PATH` to use an installed Chrome), then run the PHP fixture with `cleanup`. The browsers follow an old lesson URL to its unit, enroll through the existing administrative controller, complete a reading, return to the unit, resume at the next activity, practise to the target and follow **Continue unit**. Commerce checkout is outside this test. Cleanup removes the learner, session, fixture content, program/outcome indexes, enrollment and progress, and restores the original workspace settings.
