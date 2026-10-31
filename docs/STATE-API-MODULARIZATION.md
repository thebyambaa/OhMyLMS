# State and API access (roadmap step 4)

Step 4 of the [modularization roadmap](MODULARIZATION-ROADMAP.md): every feature imports its own
stores, hooks and API client directly, and reaches another feature's data only through that
feature's `public.ts`. This page is the audit of 31 October 2026 and the rules that came out of it.

The earlier version of this page analysed the recovered `ohmylms/store` (webpack factory 86169) and a
plan to split it into slices. That store, its adapters and the SDK were deleted at the cutover
([CUTOVER.md](CUTOVER.md)); the analysis is in the history of this file. Nothing in `assets/src`
refers to `ohmylms/store` or `ohmylms_params` any more.

## Rules

Typed admin app (`assets/src/app`):

- `features/<name>/api.ts` is the only place a feature builds REST paths. It calls `lib/api`
  (`get`, `post`, `put`, `patch`, `del`, `request`, and `getCollection` for paged lists) and validates
  every response with a parser from its own `model.ts`. A generic type parameter is not validation.
- `lib/api` is the only module that imports `@wordpress/api-fetch` (and `bootstrap/` configures it).
  File uploads use `request( { method: 'POST', body: formData } )`. The exception is
  `extensions/api.ts`, the public extension helpers of [EXTENSIONS.md](EXTENSIONS.md), which return
  api-fetch results unchanged on purpose.
- State is page-owned (`useState` plus the feature's hooks, or the shared `hooks/useRequest` and
  `hooks/useOperation`). The only `@wordpress/data` store the app reads is WordPress's own
  `core/notices`, through `layout/NoticesRegion.tsx`. There is no app-wide store.
- A feature that needs another feature's data or operation imports it from that feature's
  `public.ts` (type imports included). `tools/check-architecture.mjs` rule c enforces the import
  side; this audit covers the REST side, which no tool checks: a feature must not call a route
  another feature owns.

Native editors and blocks (`assets/src/features/**`, built into `native-lesson-editor` and the other
SDK entries):

- Requests go through `features/rest.mjs` (`request`, `queryString`), which wraps the imported
  `@wordpress/api-fetch`. Webpack maps that import to the `wp-api-fetch` script, so the runtime
  function is the same one `window.wp.apiFetch` exposed. Nothing reads `window.wp.apiFetch` directly.
- Each area keeps its requests in its own `api.mjs`: `assessment`, `content-hub`, `curriculum`,
  `question-bank`, `learning`. Every helper takes an injectable `fetch` as its last argument so
  Node tests can record requests; components do not pass a transport.
- Since 31 October 2026 they call only typed routes: `question-bank/api.mjs` uses `ohmylms/v1/questions`
  (adapting the editor view to the flat fields the editors read: bank attributes, `skill_map`, `can_edit`,
  option `thumbnail_id`) and `quizzes/{id}/questions/*/pin`; `assessment/api.mjs` reads the quiz's questions
  and pools from `quizzes/{id}/questions` and saves pools with its revision; `content-hub/api.mjs` exports
  through `POST questions/export`. The legacy `/question`, `/question-bank`, `/quiz/{id}/content|questions|pools`
  routes they used were removed.
- REST failures reach callers as the parsed REST error (`code`, `message`, `data`), unchanged.
- `rest.mjs` and `learning/api.mjs` are `// @ts-check` with JSDoc types. They pass
  `tsc --allowJs --checkJs --strict`; `npm run typecheck` does not include them yet.
- Page bootstrap data printed by PHP (`window.ohmylmsNativeLesson`, `ohmylmsMath`, `ohmylmsSchools`,
  `ohmylmsTabPreferences`, `ohmylmsPlayerTemplates`) is current contract data, not legacy state.
- Feature-owned `@wordpress/data` stores are allowed when only that area reads them:
  `paper/store.js` (`ohmylms/paper`) is the only one.

## Audit

Static scan of `assets/src` (imports, REST path literals, `apiFetch`/`fetch` calls, module-level
mutable bindings), checked against the importers of each export. "Fixed" means changed in this step.

| Kind | Where | Count | Status |
| --- | --- | --- | --- |
| Shared `@wordpress/data` store across features | `ohmylms/store` (deleted at cutover) | 0 | none left |
| Feature-owned `@wordpress/data` store | `paper/store.js` (`ohmylms/paper`), used only by `paper/*` | 1 | allowed |
| Shared WordPress store | `core/notices` via `layout/NoticesRegion.tsx` | 1 | allowed |
| Module-level singletons shared across features | none. Feature-local only: `navigation/tabPreferences.js` cache and write queue, `math/loader.mjs`, `math/runtime.mjs`, `NativeSaveGuard` counter, `questions/model.ts` key counter, `routing/links.ts` resolver | 0 shared (6 local) | allowed |
| App feature calling another feature's REST route | `course-structure` POST `/quizzes` and `/assignments` with its own parser | 2 | fixed: `quizzes/public.ts`, `assignments/public.ts` |
| | `setup` GET `/settings/currencies` with a copied parser | 1 | fixed: `settings/public.ts` |
| | `commerce` GET `/students/courses` with a copied parser and limit | 1 | fixed: `students/public.ts` |
| | `syllabus` and `curriculum` both read `/syllabus-comparison/shared-skills`, each with its own parser; the rest of `/syllabus-comparison/*` is split between them | 1 | remaining (owner decision) |
| App `apiFetch` bypassing `lib/api` | `exams/api.ts` multipart upload | 1 | fixed: `lib/api` `body` option |
| | `extensions/api.ts` (public extension contract) | 1 | allowed |
| Duplicated parsers/types | `CurrencyOption` + `parseCurrencies` (settings, setup) | 1 | fixed |
| | `CourseOption` + `parseCourseOption` + `COURSE_OPTION_LIMIT` (students, commerce) | 1 | fixed |
| | `/wp/v2/media` parsers in courses, certificates, gamification, settings (`hubIcons`) with different mime and thumbnail rules; three `MediaImage` types and three `MediaPicker` components | 4 | remaining |
| | `parseCourseChoice` in assignments, certificates, quizzes, sessions, each for its own `/<feature>/courses` route | 4 | remaining (different routes) |
| | validation primitives (`record`, `positiveId`, `text`) redefined in feature models although `lib/authoring-validation.ts` exports them | 21 `record`, 13 `positiveId` | remaining |
| Duplicated hooks | feature `useRequest` (commerce = students, dashboard, taxonomies), `useOperation` (commerce = students), `usePagedList` (memberships = quizzes, account-hub); signatures differ from `hooks/` | 9 files | remaining |
| | feature `useUnsavedChangesGuard` copies (certificates, courses, gamification, quizzes, settings) of `hooks/useUnsavedChangesGuard` | 5 | remaining (being edited by navigation work) |
| Native: `window.wp.apiFetch` global | `assessment`, `content-hub`, `curriculum`, `question-bank` `api.mjs` | 4 | fixed: `rest.mjs` |
| | `content-hub` `ImportDialog`, `ImportFigures`, `ExamsPage`, `NewExamDialog` | 4 | fixed |
| | `question-editor/TemplatePanel.jsx`, `question-editor/QuestionLivePreview.jsx` | 2 | remaining (question-editor owner) |
| Native: copied query-string helper | `content-hub`, `curriculum`, `question-bank` | 3 | fixed: `rest.mjs` `queryString` |
| Native: inline `apiFetch` in components | `NativeExamPanel` (2), `NativeLessonSettings` (1), `QuestionBlockPreview` (1) | 4 | fixed: `learning/api.mjs` |
| | explicit `apiFetch` passed into question-bank helpers by `LessonChoiceBlock`, `NativeQuestionDetails`, `NativeQuizConfiguration`, `NativeQuestionSettings` | 22 calls | fixed (default transport) |
| | `navigation/tabPreferences.js` (schools screen) | 1 | allowed (single feature-local call) |
| Native: dead REST helpers inherited from the deleted app | `curriculum/api.mjs` 27, `question-bank/api.mjs` 14, `assessment/api.mjs` 8, `content-hub/api.mjs` 7 | 56 | fixed: removed |
| Native: dead UI and modules | `QuizActions.jsx` `useAddQuiz`, `CreateBlockQuizButton`, `useExportQuiz`, `ImportIcon`, `ExportIcon`; `assessment/skillMatrix.mjs` | 6 | fixed: removed |
| Native: legacy app route | `content-hub/api.mjs` `editPath()` (`/quiz-edit/<id>`), used by the `ImportDialog` fallback when no `onQuiz` handler is given | 1 | remaining (behaviour change) |
| Native: raw `fetch` with its own nonce | `schools/api.mjs` (`window.ohmylmsSchools.nonce`) | 1 | fixed: the schools screen was deleted (31 October 2026); its workflows are Account Hub screens |
| | `students/registration-api.mjs` (front-end admin-ajax) | 1 | allowed |

## Changes in this step

- `lib/api`: `request()` accepts a multipart `body`; tested in `client.test.ts`.
- `exams`: uploads go through `request()`; no direct `@wordpress/api-fetch` import.
- `quizzes/public.ts` (`createQuiz`), `assignments/public.ts` (`createAssignment`): the course outline
  picker creates drafts through them, so the response is checked by the owning feature's parser.
- `settings/public.ts` (`listCurrencies`, `CurrencyOption`): `setup` re-exports them; its copied
  parser is gone.
- `students/public.ts` (`listCourses`, `COURSE_OPTION_LIMIT`, `CourseOption`): `commerce` re-exports
  them; its copied parser and constant are gone.
- `features/rest.mjs`: one transport and query helper for the native code.
- `features/learning/api.mjs`: exam instructions and publication, chapter content search, question
  preview.
- Dead legacy helpers, UI and their route-shape tests removed (`curriculum-api`, `syllabus-settings`,
  `skill-matrix`, `native-quiz-creation` test files; parts of `curriculum`, `question-bank`,
  `assessment` tests). New `tests/js/native-api.test.mjs`.

REST paths, methods, payloads and error objects are unchanged.

## Remaining, with reasons

- **Media library parsers** (courses, certificates, gamification, settings): they differ in mime
  filtering and thumbnail fallback, and each has its own `MediaPicker` component. Merging needs one
  shared picker in `app/components` (WordPress look work) and an agreed parser.
- **`syllabus-comparison` ownership**: decide whether `syllabus` or `curriculum` owns
  `/syllabus-comparison/*`; the other then imports the shared-skills read from its `public.ts`.
- **Validation primitives**: moving 21 feature copies of `record`/`positiveId`/`text` to
  `lib/authoring-validation.ts` touches every model file; worth a separate mechanical change.
- **Hook variants**: the feature hooks have different signatures (keyed reload, `replace`, no success
  callback). Converging them changes page components (list page owners).
- **Question editor**: `TemplatePanel.jsx` and `QuestionLivePreview.jsx` still call `window.wp.apiFetch`;
  they should import `request` from `features/rest.mjs`.
- **`editPath()`**: after a quiz import without an `onQuiz` handler, `ImportDialog` sets the hash to
  `#/quiz-edit/<id>`, an old app route that does nothing on `post.php`. Every current caller passes
  `onQuiz` or only offers other targets, except `ExamsPage`, where choosing "quiz" reaches it. Fixing
  it means opening `quiz.native_editor_url` instead, a visible behaviour change.
- **Type checking of native modules**: `npm run typecheck` covers `assets/src/app` only; adding a
  `checkJs` project for `assets/src/features` belongs with the roadmap's tooling work.

## Verification (31 October 2026)

- `npm run typecheck`: no errors in files of this step. Three errors remain in `features/questions`
  (fixtures, model test, `QuestionWorkspace`), from question editor work in progress.
- `npx vitest run` on `lib`, `exams`, `course-structure`, `setup`, `settings`, `commerce`, `students`,
  `assignments`: 46 files, 449 tests pass. Full `npm run test:app`: 147 of 149 files and 1514 of
  1543 tests pass. The failures are in `questions/QuestionEditorPage` and `extensions/coreEditors`
  (question editor work in progress).
- `npm test`: 389 of 393 pass; the 3 failures are `core-question-editors.test.mjs`
  (`question-editor/registerCoreEditors.js`, work in progress).
- `node tools/check-architecture.mjs`: passed (cross-feature import rule included).
- `npm run build:sdk`: compiled (existing size warnings only). `native-lesson-editor.asset.php` lists
  `wp-api-fetch`; the bundle's only direct `wp.apiFetch(` calls are the two question-editor files.
- Live on 800.local (admin session): Setup Preferences loads 163 currencies through `settings`;
  the coupon editor lists courses through `students`; the course outline picker created a draft quiz
  (201) and assignment (201) through their features; the exam import row check (multipart, dry run)
  answered 200 with the row report. Native editors on `post.php`, run headless with this build's
  `native-lesson-editor.js` substituted for the published one (the published bundle in
  `assets/dist` was not rebuilt): quiz, exam and lesson editors loaded pools, content, revisions,
  skills, question-bank search and question loads (all 200) with no console errors. The smoke drafts
  and the temporary administrator were removed (the assignment is in the trash).
