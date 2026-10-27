# P5 unit and practice presentation

Implemented October 16, 2026, using the existing OhMyLMS hub style. This checkpoint does not claim
a fresh pixel-for-pixel reference comparison.

## Supported presentation

- Course units group existing published activities into Learn, Practice and Assessments. No new
  assessment type, unit test placement, ordering policy or completion requirement is created.
- Skill mastery uses `MasteryDisplay` and its existing labels. The legend is display only.
- Program lessons and authorized standalone course practice share `activity-page.php`, the same
  course outline and an exact unit-anchor return. Closing navigation leaves the activity visible.
  Without JavaScript the outline remains available. Mobile starts collapsed when JavaScript works.
- Current-item matching distinguishes practice term IDs from content post IDs. Course access and
  the learner's frozen program are checked before rendering the focused shell.
- Practice entry counts the authorized selector pool without exposing question IDs, snapshots or
  answer keys. Finite questions are counted once; templates may fill the configured 3–30 limit.
  Counts are an upper bound at page load. Session limits, pool exhaustion and lesson-style review
  remain engine decisions. The standard runner shows one progress dot per problem (see KHAN-ACTIVITIES.md); the
  lesson style keeps its own segmented bar.
- Check, hints, server feedback, Next, summary and Practice again retain the real runner behavior.
  Controls stay in reach and focus follows question progress, Next and the summary. Submitting twice
  while a check is pending sends one answer request; an error permits retry.
- Existing per-user/course/skill session storage enables resume on the same browser session. Leaving
  does not submit an answer or save partially typed inputs. The unit return reopens the proper unit.

## Preserved boundaries

Question extensions, frozen question/program versions, server grading, adaptive selection, lesson-style
replay, hint assistance, rewards and completion policies are unchanged. Formal quizzes keep their own
exit confirmation and renderer contracts. Generic public/guest practice retains its existing page and
permissions. No new schema or generated bundle is required; authored assets use file modification versions.

Drawing is not supported by this practice runner and is not added as a decorative control. Skip was added on
October 16, 2026 for the standard runner only (see KHAN-ACTIVITIES.md, section 7).
Related lesson links appear only when the existing hint/recommendation response supplies them. No global
mastery is substituted for home-only report evidence. Program lesson pages join the private context-header
scope; provenance selection and source ownership checks are unchanged.

## Verification

Protected suites: learning integration 76, hub 88, player templates 81, family 56; count unit checks six.
Asset parity and JS: 417 pass, zero fail, one skip. Three runner tests exercise passive entry, duplicate
checking, errors, server-driven continuation/summary focus and resume without a new session.

Built-in browser checks used a disposable learner and published course: unit groups/legend, focused
lesson, current practice, correct/incorrect answers, hint, Enter/Next, return to the unit, resume across
a lesson visit, 390px dark layout, keyboard outline dismissal, and the completed 4/5 summary. Fixtures
were removed and the administrator's original teacher page was restored. Proof is ignored in test-results.

Changed PHP syntax, authored JS ESLint and changed CSS Stylelint pass. New presentation/template/player
PHP passes PHPCS; Practice/Frontend retains six read-only query warnings. Legacy Learning/Frontend has
34 pre-existing standards errors, and the older integration fixture retains its standards debt. Full
reference visual comparison, localization review and release/migration rehearsal remain acceptance work.
