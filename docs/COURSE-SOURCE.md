# Course source — 2026-09-24

> **Historical.** The converted components, `components.json` and adapters described here were deleted with
> the recovered admin on 31 October 2026 ([CUTOVER.md](CUTOVER.md)); see [REACT-DEVELOPMENT.md](REACT-DEVELOPMENT.md).

The source build now loads 25 named course React components from `assets/src/features/courses/`. They run in the isolated WordPress installation. The working math site still uses the shipped JavaScript.

## Source inventory

| Area | Components |
| --- | --- |
| Course library and creation | CourseList, CourseListItem, CourseCreateDialog |
| Builder and curriculum | CourseEditor, CourseToolbar, CourseCurriculum, CourseChapter, CourseInfoHeader, CourseMedia |
| Settings | CourseSettings, CourseBasics, CoursePricing, CourseCapacity, CourseAccess, CourseAvailability, CourseLevel, CourseSlug, CourseReviews |
| Additional tabs | CourseResources, CourseOrganization, CourseEngagement, CourseCohortSettings, CourseCommunity, CourseFunnel |
| Publishing review | CoursePreview |

CourseEditor, CourseCreateDialog, CourseInfoHeader and CourseSettings have authored JSX. `useCourseEditor.js` owns loading, persistence and completion state; `useCourseIntegrations.js` owns integration/automation dialogs and listener cleanup. `api.mjs` contains explicit course REST requests, and `model.mjs` contains immutable payload/order/completion helpers. CourseToolbar now waits for a successful save before showing publication completion.

Other component files contain reconstructed JSX with runtime dependencies recorded in `components.json`. They retain some original minified locals, shared controls, store actions, and transpiler helpers. They are editable source, but this is not a claim that every course dependency is independently modularized. Student course templates, reporting, import/export, and external integration behavior have not been rewritten in this increment.

`tools/course-adapters.mjs` checks all 25 original factory-1841 bindings and connects them to the native module factories. The one-time extraction utility has been retired; edit the checked-in components directly. Production builds consume checked-in source, not the previous compiled application. The native files compile with WordPress-provided React and source maps into the SDK.

## Behavior repaired

- Save waits for both course details and chapter writes. Previously the chapter request was not awaited and its failures were swallowed by the shared action.
- A chapter failure explicitly reports that course details were already saved, retains editor data, and permits retry. The two REST operations are not a database transaction.
- Failed course creation leaves the dialog open with an error and retry rather than closing after a console-only failure.
- Save does not mutate the original course object. A late response preserves newer field edits made during the request.
- Blank descriptions remain blank; completion checks no longer restore an older description or dispatch store changes as a side effect.
- The toolbar only marks a publication complete after successful persistence.

## Verification

- Browser creation through the course-list dialog, simulated creation failure and retry, adding a second chapter, draft saving and reopening.
- Browser editing of course title, rich-text description and chapter name; existing lesson relationships preserved after reopening.
- Browser paid pricing (120 regular / 90 sale), Basics/Resources/Organize/Engagement tab navigation, preview, publishing and persisted price/status checks.
- Simulated chapter-write failure followed by successful retry. No unexpected page errors in the course scenarios.
- Four focused model/API tests cover immutable payloads, relationship order, awaited chapter writes, partial failures, stopping after a failed course request, and keeping edits newer than a save request.
- Existing quiz, lesson, membership, extension and student smoke scenarios run in the same isolated suite. PHP integration checks continue to cover enrollment, grading, timers, progress and mocked checkout.

A pre-conversion course screenshot was saved outside Git. Course content and settings screenshots are emitted by the browser tests. The fixture was corrected to use the course's automatically created chapter; these screenshots are not a claim of exhaustive pixel-identical comparison.

Still required before full release: broader content insertion/reordering/deletion and embedded quiz workflows, cohort scheduling, media uploads, AI outlines, funnels/community and live integration compatibility, all student journeys, actual RTL and course responsive layouts, dependency/license verification, and the working-site smoke test. HTTP/email remain isolated in tests; no real external integrations were invoked.

## Development and rollout

Run `npm run build`, `npm test`, `npm run lint` and the isolated browser/PHP commands in DEVELOPMENT.md. The existing `OHMYLMS_SOURCE_ASSETS` switch controls the whole reconstructed application; there is no course-only release switch. No database migration was added by this increment. Do not enable the switch on math until the remaining acceptance gates pass; the latest read-only homepage check obtained no HTTP response, including the elevated retry.
