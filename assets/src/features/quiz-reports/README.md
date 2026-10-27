# Quiz reports and grading React source

Edit the JSX files here and run `npm run build`, or use `npm run dev`.

- `QuizReport`: submissions table, search, pagination, result badges and grading navigation.
- `QuizGrading`: attempt loading, grade submission, student details and result layout.
- `QuizQuestionHeader`: question title, status and manual marks input.
- `QuizQuestionResults`: selects the result view for each question type.
- `SingleChoiceResult`: single-choice and true/false answers.
- `MultipleChoiceResult`: multiple-choice answers.
- `TextAnswerResult`: short text, long text, statement and fill-in-the-blank answers.
- `ReorderResult` and `MatchingResult`: ordered and matched answers.
- `QuizResultSummary`: score, correct count, student/course details and final result.

`components.json` records the ten original bindings and their runtime dependencies.
`tools/quiz-report-adapters.mjs` replaces each implementation during the adapted build
and fails if any binding is missing. Existing memo wrappers, routes, REST endpoints,
question grading rules, styles and backend permissions remain in use. Manual marks
retain the backend's `achive_mark` field spelling and the full attempt POST payload.
Successful saves retain the existing reload behavior.

These are editable reconstructed JSX modules integrated with the recovered application.
Shared controls, icons, date utilities and some transpiler helpers still come from that
runtime. Recovered files remain the parity baseline. Do not rerun the extraction tool
over manual edits. The existing `OHMYLMS_SOURCE_ASSETS` setting controls activation;
building does not enable source assets on a site.

Validation: `npm run build`, `npm run lint`, and `npm test`. The quiz report tests compare
rendered element trees with the baseline for all nine question types and check report
filtering/pagination, navigation, API paths, immutable marks edits, save payloads and
success/failed-response reload behavior. They use mocked controls and API requests.
A one-time live WordPress check on a different local site (September 24, 2026; manual marks
saved through the real REST route, guest and subscriber requests refused) found and fixed
search pagination, missing attempt status, and mixed numeric/string answer IDs (`model.mjs`).
Do not regenerate over these fixes. The live fixture helper `tools/quiz-reports-live-fixture.php`
only runs on a Local site folder named `xyz`, so it does not work on the current site.
