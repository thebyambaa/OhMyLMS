# Quiz and question source — 2026-09-23

Historical implementation record. As of 21 October 2026, the form-based QuizEditor is removed and its public factory redirects to Gutenberg. See [current authoring](GUTENBERG-AUTHORING.md).

The source build now uses 17 named React components from `assets/src/features/quizzes/`. It is enabled in the isolated WordPress test site. The math site still uses the shipped JavaScript.

## What is implemented

- QuizEditor: authored JSX layout, useQuizEditor hook, explicit REST API client, load/save/error states.
- ChoiceOptionsEditor: authored hooks and named answer/add/remove/correctness/drag handlers. Drag ordering avoids mutating store objects.
- QuestionList, QuestionCanvas, QuestionSettings, QuizSettings, QuizSettingsFields: reconstructed JSX components.
- MultipleChoiceEditor, SingleChoiceEditor, TrueFalseEditor, ShortTextEditor, LongTextEditor, StatementEditor, FillInTheBlankEditor, ReorderEditor, MatchingEditor: separate reconstructed JSX components.
- TextAnswerEditor: shared text-question explanation.
- Custom question editors now update both the selected question and the quiz question collection. Previously an extension field could appear changed but revert after saving the quiz.

`components.json` maps each component to its original factory-1841 binding and compatibility dependencies. `tools/quiz-adapters.mjs` replaces the original component definitions during production builds and requires all 17 integration points. Lazy runtime dependencies preserve initialization order. Webpack builds the native modules into the SDK with WordPress-provided React and embedded source maps. The old compiled application is not a build input.

These files are reconstructed source, not recovered original JSX. The authored editor and hook coexist with reconstructed components that still depend on shared controls, stores, helpers, and some minified local names. The question option-row control, rich-text/media controls and student quiz runner remain in recovered source. Quiz reports and grading now have ten editable JSX modules in `assets/src/features/quiz-reports/`, integrated by `tools/quiz-report-adapters.mjs`; see that directory's README for scope and validation. This is not a claim that the entire quiz subsystem is independently modularized.

## Verification

- Browser: render all nine built-in editors in a saved quiz; edit a choice answer; enforce two-option minimum; add/remove an option; edit a registered custom numeric question; save and reopen all ten questions and verify persisted answers/settings. No page errors in this scenario.
- Model tests: persisted IDs and extension settings survive payload normalization, temporary IDs are removed without mutation, invalid selected questions block navigation, and option reorder is immutable.
- Existing isolated PHP suite: custom question rendering/grading, content persistence, timers, duplicate submissions, enrollment and progress checks remain passing.
- The complete nine-scenario browser suite passed after integrating the quiz components and fixing custom-question saving. The expanded quiz scenario passed again after the choice-editor rewrite.

Creation fixtures and question selection in this browser test use REST/store APIs. It does not claim end-to-end mouse authoring coverage of every built-in type. Remaining quiz acceptance includes all answer-specific interactions, embedded course quiz editing, student submission/browser timers, reporting, existing custom-question add-on compatibility, responsive/actual RTL, and original-vs-new visual comparison.

## Development and rollout

Edit the native files, then run `npm run build`, `npm test`, `npm run lint`, and the browser suite using isolated credentials. See DEVELOPMENT.md for the source-asset switch and rollback. The one-time component extraction utility has been retired; ongoing development uses the checked-in source.

The quiz components currently ship through the existing whole-application `OHMYLMS_SOURCE_ASSETS` switch; there is no quiz-only production switch. Do not enable it on math until the remaining acceptance gates and site smoke test pass. The latest read-only math homepage request, including an elevated retry, obtained no HTTP response.
