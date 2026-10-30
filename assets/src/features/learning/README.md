# Native lesson and quiz editors

The `native-lesson-editor` Webpack entry (`NativeLessonSettings.jsx`) adds the LMS panels to the WordPress post
editor for lessons, quizzes and questions (`post.php?post=<id>&action=edit`). Edit the files here, then run
`npm run build:sdk`; `npm run dev` rebuilds on change.

## Files

- `NativeLessonSettings.jsx`: the entry. It registers the settings sidebars with Gutenberg SlotFills; it does not
  replace the editor canvas, toolbar, inserter, list view or WordPress editor CSS.
- `NativeQuizSettings.jsx`, `NativeQuizConfiguration.jsx`, `NativeAssessmentSettings.jsx`, `NativeExamPanel.jsx`:
  quiz and exam settings.
- `NativeQuestionSettings.jsx`, `NativeQuestionDetails.jsx`, `LessonChoiceBlock.jsx`, `QuestionBlockPreview.jsx`,
  `AssessmentPreview.jsx`, `quizPreview.mjs`, `choiceAutosave.mjs`, `questionHistory.mjs`, `nativeCanvas.mjs`:
  question blocks, previews and autosave.
- `NativeExtensionPanels.jsx`: editor panels registered by extensions, each inside the app's
  `ExtensionBoundary`.
- `nativeAuthoringBootstrap.js`: creates `window.ohmylms.extensions` from the app's registry and REST helpers
  (`assets/src/app/extensions/`) when the extension host is not on the page.
- `returnLink.mjs`: the return route, stored per post in tab session storage because WordPress replaces the URL
  when saving.

## Backend

PHP registers the `ohmylms-lesson` post type with editor, REST and revision support. The
`ohmylms_lesson_settings` field on `/wp/v2/ohmylms-lessons` uses the existing metadata keys. Content remains
`post_content`; native saves do not move course placements. The native REST controller restricts lesson reads to
editors; learners go through the LMS player.

Compatibility exceptions: `LessonPostType.php`, `NativeLessonEditor.php` and `NativeLessonController.php` follow
the plugin's PSR-4 autoload mapping, so their CamelCase filenames are reported by WPCS's filename rules. The
native back-button SlotFill is exposed by WordPress only as `__experimentalMainDashboardButton`; the settings panel
keeps a normal return link if that export is unavailable.

## Validation

- `npm run lint:js`, `npm test`, `npm run build:sdk`
- `php tests/php/native-lesson-editor-integration.php` with `OHMYLMS_TEST_SITE_PATH` set to a local WordPress
  checkout (connection-local temporary post tables).
- `npm run test:browser -- tests/browser/lesson-block-editor.spec.cjs tests/browser/native-authoring.spec.cjs`,
  with `OHMYLMS_TEST_CREDENTIALS` or a short-lived `OHMYLMS_TEST_STORAGE_STATE`. Never commit either file.
