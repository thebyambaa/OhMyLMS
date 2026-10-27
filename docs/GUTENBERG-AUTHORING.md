# Gutenberg quiz and question authoring

Quizzes and reusable questions now use the native WordPress block editor by default. The Content Hub and question bank remain the management screens. Existing quiz bookmarks and course-builder actions resolve to the WordPress editor; IDs and learner routes do not change.

## Authoring

- **Add Quiz** creates a draft and opens Gutenberg. Add core blocks for introductory content and Ohmylms Question blocks for questions. Drag blocks or use List view to order questions.
- **New question** and editable bank question links open the standalone question editor. Its single question block is locked in place. The question statement and answers continue to live in the bank; the Gutenberg document is a reference shell, never a second copy of the answer key.
- The question type selector uses the installed server manifest. Built-in response controls are shared with existing authoring components. Declarative `ohmylms-options` extensions and registered `registerQuestionEditor` components are supported. Missing editors retain their data and show an unavailable-editor message.
- Complete question edits save automatically. Unfinished or failed saves block document saving and publication. Retry errors explicitly; conflict errors preserve local work for recovery.
- Use **Undo question edit** and **Redo question edit** in the question toolbar for private draft changes. These retain up to 50 local edits during the current editor session. WordPress's document Undo/Redo handles block layout and placement changes. Removed answer options restored after an autosave receive new editable identities; historical frozen versions retain their original identities.
- In the standalone editor, **Preview question** displays the saved bank draft. Math fields, randomized variables and server-generated template examples retain their existing authoring controls.

## Quiz settings and reusable content

The document sidebar contains **Quiz settings** and **Assessment and pools**. Quiz settings include the existing timer, passing marks, attempts, feedback visibility, question layout, character limits, player design, course release rules and quiz skills. They save with the Gutenberg document. A zero timer means no time limit; the existing attempt engine treats an empty or zero attempt allowance as one attempt.

Assessment sections, availability, feedback release, accommodations and random pools use the existing services and their explicit Save actions. Unsaved assessment or pool edits block publication. Saving these settings makes the native quiz document dirty so the next document save can publish the new assessment configuration.

**Add from question bank** inserts references into the current native draft. It does not change saved placements before the document is saved. Duplicate question creates an independent bank copy; WordPress's generic block Duplicate still copies a reference, and the server rejects duplicate references within a quiz.

Select a question to open **Question bank and versions** in the block sidebar. It provides bank membership, difficulty, source, family, secure status, part-level skills, version previews, restore-as-draft and quiz version pins. These operations use existing bank permissions and version services. Save the quiz placement before pinning a newly inserted question.

**Approve for shared use** remains an explicit bank action in the question's settings menu. WordPress Save/Publish does not itself approve a bank question. Quiz publication continues to freeze assessment revisions through the existing engine. Editing a shared draft or removing a placement does not rewrite old attempts or frozen revisions.

Quiz export is available in the native sidebar after a successful save. Import remains in Content Hub and opens the resulting draft in Gutenberg. Reports, grading, bank sharing and bulk management stay in their existing screens.

## Storage, permissions and compatibility

Private question data is written through `QuestionBank\DraftWriter`; core native question saves cannot overwrite the statement with a reference document or bypass that writer for title/media changes. Native question REST reads require question-edit permission. Bank edit grants permit opening the native screen without granting deletion or approval. Questions have no public WordPress page or public core REST collection.

Native quiz settings are exposed through the edit-context `ohmylms_quiz` field. They use the existing `_quiz_settings`, `_drip_settings` and quiz-skill storage inside the quiz controller's transaction. Existing extension settings survive edits to known fields. Failed writes roll back the post, settings and placements together.

Existing quiz documents are synthesized on read. When placements differ from saved block references, references are reconciled in their existing nested layout slots; surrounding core content remains in place. Opening a quiz does not save or publish the synthesized document.

Native screens expose the registry and API helpers and fire the established `ohmylms:extensions-ready` event. The existing `ohmylms_enqueue_extension_scripts` hook receives the native bundle handle on those screens. Registered quiz editor panels receive `{ quizId, native: true, hash }`; extensions that directly access the retired form store must adapt their panels to the native document/API context.

The former quiz form editor has been removed. Quiz creation and former quiz routes always open Gutenberg, including when `OHMYLMS_FORM_AUTHORING` is enabled. The public `createQuizEditor` factory remains as a redirect adapter for existing integrations. Shared answer controls, API helpers and exported editor utilities remain for compatibility. The temporary constant still controls standalone question forms; it cannot restore the removed quiz editor.

## Verification

The migration has dedicated JavaScript checks for registry filtering, extension availability, type readiness and private undo identities, plus the existing editor/autosave suites. PHP integration checks cover native quiz ordering/publication, nested-layout reconciliation, transaction rollback, standalone shell isolation, blank documents, bank edit/use grants and denied anonymous access. Browser coverage exercises standalone create/save/reload/edit, private undo, legacy quiz navigation, settings persistence, draft-only bank insertion and assessment save locks.

Run the PHP migration suites only against their explicitly guarded disposable databases. Tests must not target a production database. See `tests/php/native-question-editor-integration.php`, `tests/php/native-quiz-editor-integration.php` and `tests/browser/native-authoring.spec.cjs`.
