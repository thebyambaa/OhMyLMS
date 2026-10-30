# Full Gutenberg quiz and question authoring migration

Status: implementation delivered in this workspace, 20 October 2026. Gutenberg is the default authoring path. The former quiz editor and its rollback route were removed on 21 October 2026; the standalone question-form rollback remains. See [current behavior and compatibility](GUTENBERG-AUTHORING.md). The original phased plan below records the migration scope and its release gates.

## Outcome

Make the native WordPress block editor the complete authoring environment for quizzes and standalone bank questions. Authors must be able to create, edit, configure, preview, approve and publish supported content without visiting the existing form builders. Keep the question bank's search, filters, sharing and bulk management screens; their authoring actions open Gutenberg. Keep course management, reports and learner delivery in their current applications.

This is an authoring migration. Preserve question IDs, UUIDs, bank memberships, placement order, option identities, settings, grading contracts, permissions, immutable published versions, frozen attempts and skill evidence. Continue using the existing question writer and assessment publishing services.

## Evidence and remaining gaps

- `includes/Learning/NativeQuizEditor.php` already exposes native editor URLs, synthesizes blocks for existing quiz placements, validates references and synchronizes placement order. Its editable-type allowlist is narrower than the full question-type inventory; other questions become reference blocks.
- `assets/src/features/question-editor/MultipleChoiceBlock.jsx` already provides shared question fields, several answer types, media, equations and randomized templates. Preserve the existing serialized block name for compatibility even if its visible name becomes Question.
- `assets/src/features/learning/NativeQuizSettings.jsx` currently sends authors back to the existing quiz editor for settings. It is not complete settings parity.
- The recovered Question Bank screens (`QuestionBankPage.jsx`, `NewQuestionModal.jsx`) that used `QuestionForm` were deleted at the cutover; bank authoring is the typed `#/content-hub/question-bank` screen.
- `includes/PostTypes/QuestionPostType.php` currently hides the question post UI and does not opt into the core REST editor. Standalone native question editing needs a deliberately secured integration.
- Course quiz links, reports, bank usage links, content actions and extension panels still depend on `/quiz-edit/:id`.
- Existing native integration tests and documented browser checks provide a starting point. Re-run them against the current site; historical verification does not establish full migration readiness.

## Phase 1 — Inventory and define parity

1. Create a capability matrix from the server question registry, authored controls, bank workflows, quiz settings and registered extension panels. Track each function as supported, partial or missing in both quiz and standalone-question contexts.
2. Cover every built-in type, including true/false, statement, categorize, passage, hot text, audio/video responses, polls, word clouds, discussions and interactive video, alongside the existing choice, written, structured, mathematical and visual types. Verify current capabilities rather than relying solely on documentation or dropdown entries.
3. Record every quiz setting and its persistence endpoint: timing, attempts, pass/score rules, feedback/review, assessment presets, pools, version pins, skills, practice behavior, and any course-specific or extension-provided settings found in the audit.
4. Record bank ownership, sharing, approval, draft/version history, archive/restore, duplication, usage and skill workflows. Define which actions belong in editor panels and which stay in bank management.
5. Capture representative existing quizzes/questions and their stored data for migration comparisons. Identify installed custom question types and editor panels.

Exit: an agreed field-by-field and workflow-by-workflow matrix with explicit implementation and test coverage. Any unsupported active type or required panel blocks full retirement.

## Phase 2 — Consolidate the native question editor

1. Extract a shared question authoring model and controls from the current native block, form controls and answer editors. Reuse validation and answer widgets; remove dependence on the recovered form application's runtime for native authoring.
2. Drive available types from the server manifest and editor registry. Replace divergent hardcoded dropdowns and PHP allowlists with a consistent supported-type contract. Preserve existing type IDs and serialized block names.
3. Complete native editing for the parity matrix: prompt, media, answer controls, keys, marks, required status, hints, solutions, shuffle, partial credit, manual grading and type-specific settings.
4. Keep MathLive, randomized variables, constraints, computed settings and server-generated example previews working inside the native iframe. Load heavy math and specialized answer controls on demand.
5. Provide a native adapter for the existing `registerQuestionEditor` contract and editor panels. An installed type must remain editable through its registered controls; an unavailable extension must preserve data and show a clear recovery state.
6. Define copy/paste, duplication, type-change and removal behavior. Separate copying a reference from creating an independent bank question; prevent invalid duplicate placements and accidental answer loss.

Exit: every active supported type can be created, edited, previewed, saved and reopened in the native quiz context, including custom extensions selected for compatibility testing.

## Phase 3 — Standalone Gutenberg question builder

1. Integrate the existing `ohmylms-question` post type with the native editor using explicit capability mapping, restricted REST access and no public question page. Audit existing public query settings before enabling the editor.
2. Use a locked single-question document shell with the same shared authoring controls as quiz questions. Define how the existing question body maps to the editor shell without overwriting its stored prompt or answer data. Do not introduce a second authoritative copy of the question.
3. Route private question saves through `DraftWriter` and the existing version services. Core post-save requests must not bypass bank authorization, validation, conflict checks or approval rules.
4. Add bank membership, skills, ownership/sharing information, version history, approval/publish controls and usage context. Preserve existing distinctions between draft saving, question approval and quiz publication.
5. Change bank New, Edit and Duplicate actions to open this editor. Return authors to their bank filters or originating quiz after editing. Keep browsing, grants, archiving and bulk operations in bank management.

Exit: an author can create and maintain a reusable bank question entirely in Gutenberg, then place it in a quiz without creating another identity or exposing private answers.

## Phase 4 — Complete the native quiz builder

1. Port all quiz settings from the parity matrix into native sidebar panels or native editor dialogs. Replace runtime-bound form settings with shared, authored controls using the existing API contracts.
2. Add the bank picker, existing-question placement, independent duplication, version selection/pinning, revision history, random pools, quiz/question skills and import/export actions.
3. Preserve course/chapter context and return navigation. Quiz preview must use the existing instructor player without creating learner attempts; grading and reports stay on their existing routes.
4. Integrate settings, question saves and placement saves with the native document's dirty state and save/publish controls. Publication must wait for successful validation and persistence of all dependent writes.
5. Explain shared edits and version consequences in the UI. Editing a shared bank draft must not silently change frozen published quizzes or historical learner attempts.

Exit: no normal quiz authoring action requires the old quiz editor or its Advanced settings link.

## Phase 5 — Save integrity and existing-content compatibility

1. Handle failed requests, slow connections, rapid edits, navigation during saves, reloads and competing browser tabs. Serialize dependent writes and retain newer local edits when earlier responses arrive.
2. Distinguish WordPress undo/post revisions from bank draft history and immutable assessment versions. Define and test undo/restore for question fields, placements and quiz settings; do not present post restoration as restoring bank data when it does not.
3. Preserve reference-only serialized question blocks. Keep answer keys, private templates and grading configuration out of public content, previews and learner REST payloads.
4. Open existing quizzes through a deterministic compatibility conversion. Preserve rich introductions, supported nested layouts, question order, identities and pins. Inspect the current content synthesis path for layout loss. Opening an editor alone must not rewrite or publish content.
5. Make conversion repeatable and test no-op save/reload round trips. Preserve unresolved custom references without silently dropping them. If a batch conversion becomes necessary, provide a dry run, backup and per-item failure report.
6. Validate capability checks and bank grants for each save/publish operation; keep server-side validation authoritative. Verify transaction boundaries and recoverable error handling where multiple APIs participate in one editor action.

Exit: representative existing content round-trips without semantic changes; published versions, attempts, grading results and evidence remain intact.

## Phase 6 — Default switch and retirement

1. First ship complete native authoring behind a temporary internal rollback setting. Exercise both fresh and existing content on a disposable database or test site before switching the normal flow.
2. Point all Create/Edit actions to native URLs: Content Hub, question bank, syllabus/course builder, skills resources, reports, imports/duplication results and preview return links. Redirect old bookmarked quiz routes while preserving access checks and course context.
3. Make Gutenberg the default only after the parity and integrity exit criteria pass. Remove the visible editor-choice actions and the native sidebar's link to the form builder.
4. Keep the previous editor reachable only through the temporary rollback mechanism for a defined stabilization release. Explicitly test that content written by the new editor remains usable after rollback.
5. Once release acceptance passes, remove obsolete form canvases, authoring modals, route implementations, styles and runtime adapters. Retain shared validators, answer controls, APIs and public extension compatibility shims still used elsewhere.
6. Document deprecated extension hooks and provide native equivalents before removing contracts. Update author guidance and regenerate assets from source; do not manually edit generated or recovered third-party bundles.

Exit: Gutenberg is the only normal quiz/question authoring path, all required settings are available there, old links resolve correctly, and remaining compatibility code has a documented purpose.

## Verification and release gates

- JavaScript: type registry parity, serialization, draft validation, autosave races, save locks, reference versus independent duplication, route resolution and extension adapters.
- PHP integration: native quiz and standalone question REST saves, grants/capabilities, private-data boundaries, invalid/duplicate references, ordering, publication, pinned versions and immutable historical attempts.
- Browser: create/save/reload/publish an existing and a new quiz; standalone bank creation and editing; each supported type's save/reload and learner preview; templates, equations/media, skills, pools, imports/exports, copy/paste, undo, revision restore, failed saves and concurrent edits. Cover administrator and actual instructor/shared-bank roles.
- Migration: compare IDs, settings, placements, versions and grading fixtures before and after conversion, including nested content and unavailable extensions. Verify opening without saving makes no database changes.
- Build and standards: run the repository's relevant JavaScript/PHP tests, browser coverage, format and WordPress standards checks, production build and reproducibility check where applicable. Report unrelated existing failures explicitly.
- Performance: measure initial editor assets and on-demand math/type chunks. Avoid importing the retired form application just to display settings. Track bundle warnings and interactive readiness; do not treat a higher warning limit as an optimization.

## Delivery order

Implement in reviewable increments: parity matrix; shared native question controls; standalone question integration; complete quiz settings and bank workflows; save/migration hardening; navigation/default switch; then legacy retirement. Keep the old implementation available during implementation, but full completion requires removing dependence on it for authoring.

The implementation covers shared native question controls, standalone question authoring, quiz configuration, bank/version workflows, compatibility conversion and default navigation. Final removal of the rollback form builders remains a stabilization-release action; extensions that use the retired form store require a compatibility review before that removal.
