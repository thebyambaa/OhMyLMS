# Question types implementation plan

Prepared: 2026-10-09. Status (checked 2026-10-19): **partly overtaken.** This was the plan; it was written before the work it describes. The
editor and learner player now offer 38 native formats, including passage, graphing (points and straight lines), hot text, match
table grid, labeling, hotspot, draw, audio and video response, poll, word cloud, discussion board (individual responses), slide and
interactive video (see [question-block-editor.md](question-block-editor.md) for the list and
[question-editor-verification.md](question-editor-verification.md) for the parity limits), implemented in `Assessment\ExtendedQuestions`,
`ExtendedEditor.jsx` and `extendedModel.mjs` (check section 4's descriptor and codec proposals against that code before relying on them). The proposed identifiers `table-fill-in`, `drag-and-drop`
and `math-response` were not adopted (blank tables and tile building exist under other type names; `numerical` and the math editors
cover math response). Sections 4, 6 (milestones G to I) and 9 remain the design reference for what is still open: shape-aware
response codecs, historical grader dispatch, live Discussion Board, graphing curves and inequalities, and optional AI evaluation.
Repository findings in section 2 describe the code as it was on 2026-10-09.

## 1. Objective and scope

Extend the existing OhMyLMS question editor with the assessment interactions studied in Wayground. Every released type must work through authoring, saving, immutable publication, learner delivery, autosave/resume, validation, grading, teacher review, reports, and applicable practice/inline surfaces. A dropdown entry alone is not completion.

Use Wayground as a behavioral reference, with OhMyLMS styling, permissions, accessibility, and assessment contracts. Implement original components and avoid copying proprietary assets. Preserve existing type IDs, extension APIs, REST fields, hooks, class mappings, historical attempts, and skill-evidence rules.

The scope includes all 25 catalog options observed: multiple choice, multi-select, true/false, fill blanks, open ended, table fill, passage, graphing, math response, drag/drop, dropdown, categorize, reorder, match, hot text, match table grid, labeling, hotspot, draw, video response, audio response, poll, word cloud, discussion board, slide, and multi-part. Retain existing short answer, statement, and numerical types as additional OhMyLMS capabilities.

Question types are distinct from live session orchestration, interactive video resources, AI generation, and gamification. Discussion Board needs a live-session service and is included as its own later milestone. AI grading is optional and follows a complete manual-review path. Leaderboards, multiplayer games, full Wayground mode parity, and video resource authoring are separate projects.

## 2. Repository findings

| Existing boundary | Verified behavior | Implementation implication |
| --- | --- | --- |
| `includes/Extensions/QuestionTypes.php` | Nine default types plus `numerical` and `structured`; callbacks for rendering, validation, grading, and public settings | Extend this registry; use focused classes rather than growing one large conditional |
| `includes/Extensions/Registry.php` | Server manifest exposes label, contexts, editor; registration requires render/validate/grade | Add optional metadata compatibly; preserve third-party definitions |
| `assets/src/features/question-editor/` | Shared form, answer fields, type switching, local preview, numerical/structured controls | Canonical authoring location; preserve separate editor and bank interfaces |
| `assets/src/features/question-editor/questionBlocks.mjs` | Static type list and public preview projection; type switching resets answers | Make the catalog descriptor-driven; add safe conversion and undo |
| `assets/src/features/question-bank/` | Separate draft/payload conversion and validation; skill creation initially derives parts from settings | Update this path alongside quiz authoring; avoid two schema implementations |
| `includes/QuestionBank/DraftWriter.php` | Authorized transactional writes, option ownership checks, version capture, conflict handling | Keep writes here; add type-specific document validation before persistence |
| `includes/QuestionBank/VersionPublisher.php` | Immutable settings/options/media/parts, hashes, schema version, grader version | Reuse frozen storage; generalize parts and media collection |
| `includes/Assessment/QuestionSnapshot.php` | Learner-safe settings whitelist, option tokens, special structured projection | Add recursive type-owned public projections; never expose private answer maps |
| `includes/Assessment/AttemptItems.php` | Seeded option order; matching-specific token keys; flat token conversion | Add scoped codecs for nested blanks, grids, targets, and composite children |
| `includes/Assessment/Grader.php` | Shared frozen grading; generic string sanitization; presence sees scalar array members | Add shape-aware validation, normalization, and presence without breaking old answers |
| `includes/Assessment/Responses.php` | Server-received JSON drafts/events and sequence ordering | Reuse storage for bounded structured JSON; define equal-sequence idempotency and conflicting payload behavior |
| `includes/Rest/V1/AttemptController.php` | Autosave checks ownership/deadlines and a 65,535-byte JSON ceiling; does not run type-specific answer validation at this boundary | Validate draft answers by type before writing; use references for large artifacts |
| `assets/js/quiz-autosave.js` | Collects conventional form fields into flat lists/maps | Add registered serialize/restore adapters; retain legacy field collector |
| `includes/Assessment/Structured.php` | Parts limited to numerical, text, written | Preserve v1 and add mixed-type composition under a versioned contract |
| Submission, review, reports, practice | Several explicit `structured` branches for part weights, review, and eligibility | Generalize through descriptors; updating the editor alone misses these consumers |
| Grader versions | Publisher saves `grader_version`; inspected Grader selects the current registry callback | Introduce historical grader dispatch before changing old grading semantics |

The existing engine already provides decimal scoring, frozen attempts, grade events, skill mappings, teacher review, practice, and author-only player preview. These are implementation assets, not proof that every new shape is supported. No test suite was run for this planning task.

Primary internal references: `docs/ASSESSMENT-MODULES.md`, `docs/ASSESSMENT-ENGINE.md`, `docs/EXTENSIONS.md`, `docs/INTERACTIVITY.md`, `docs/question-block-editor.md`, `docs/inline-blanks.md`, `docs/REACT-DEVELOPMENT.md`, and `docs/CODING-STANDARDS.md`.

## 3. Type catalog and compatibility mapping

The proposed identifiers below are design choices, not existing public identifiers. Preserve every existing ID. In particular, OhMyLMS `multiple-choice` means multiple selections, while Wayground's Multiple choice normally means a single selection.

| Reference option | OhMyLMS implementation | Authoring and learner behavior | Scoring / milestone |
| --- | --- | --- | --- |
| Multiple choice | Existing `single-choice` | Options, exactly one correct; radio/card selection | Existing exact grading; B |
| Multi-select | Existing `multiple-choice` | Multiple correct options; checkbox/card selection | Keep historical exact scoring; opt-in new partial policy; B |
| True or false | Existing `true-false` | Two localized options, one correct | Exact; B |
| Fill in the blanks | Existing `fill-in-the-blank`, new document format | Stable inline blanks; text, dropdown, or math responses per blank; alternatives | Per-blank or exact policy; C |
| Open ended | Existing `long-text` | Prompt, length limit, rubric, optional supporting artifact | Manual first, optional AI later; B/F/I |
| Table Fill In | New `table-fill-in`, shared blank engine | Structured accessible table with fixed and response cells | Same blank policies; C |
| Passage | New `passage`, composite engine | Shared reference text/media beside child questions | Aggregate scored children; E |
| Graphing | New `graphing` | Bounds, axes, solution family, reference plots; points/curve controls/inequality regions | Server mathematical comparison with explicit tolerances; G |
| Math response | New `math-response`; retain `numerical` | Math keyboard, normalized expression, domains and accepted forms | Exact expression or validated equivalence; G |
| Drag and drop | New `drag-and-drop`, shared blank engine | Blank targets, tile bank, distractors, repeat-use rules; drag or tap/keyboard | Per-target or exact; C |
| Dropdown | New `dropdown`, shared blank engine | One or more blank menus with correct/distractor choices | Per-blank or exact; C |
| Categorize | New `categorize` | Categories and items; sort by drag, tap, or keyboard | Correct placement fraction or exact; D |
| Reorder | Existing `reorder` | Author correct order; learner arranges shuffled items | Keep exact v1; opt-in position scoring v2; B |
| Match | Existing `matching` | Author pairs; learner pairs shuffled independent sides | Keep exact v1; opt-in pair fraction v2; B |
| Hot text | New `hot-text` | Author selectable text spans and answer key; learner selects spans | Exact or net-correct selection policy; D |
| Match Table Grid | New `match-table-grid` | Row/column editor, correct cells, single/multiple per row | Exact or net-correct cell policy; D |
| Labeling | New `labeling` | Image, positioned targets, label bank/distractors; learner places labels | Per-target or exact; D |
| Hotspot | New `hotspot` | Image regions: circle, rectangle, polygon; learner selects regions | Exact or net-correct region policy; D |
| Draw | New `draw` | Blank/background canvas; strokes, colors, undo/redo | Ungraded or manual; F |
| Video response | New `video-response` | Duration/size limits; record, replay, retake, optionally upload | Ungraded or manual; F |
| Audio response | New `audio-response` | Duration/size limits; record, replay, retake, optionally upload | Ungraded/manual; optional AI; F/I |
| Poll | New `poll` | Options, single/multiple selections | Ungraded; exclude from academic denominator; B |
| Word cloud | New `word-cloud` | Bounded short text, response aggregation | Ungraded; B |
| Discussion Board | New `discussion-board` | Live shared posts, comments, reactions, moderation | Ungraded by default; H |
| Slide | New `slide`, content descriptor | Text/media content inserted between questions | No response or marks; E |
| Multi-part | Existing `structured` v1; mixed children v2 | Shared prompt, stable Part A/B/etc., typed children and weights | Auto/manual child aggregation; E |

Short answer (`short-text`), statement (`statement`), and numerical (`numerical`) retain their current contracts. Do not reinterpret statement as slide.

Proposed initial limits, configurable within server caps: 20 options; 20 blanks; 20 rows/columns with at most 200 grid cells; 4 categories/20 items; 10 labels/hotspots; 20 composite children; two composite levels. Recording duration: 5 seconds to 5 minutes. Word-cloud length: 20 grapheme clusters. These are OhMyLMS product defaults, not claims of exact Wayground limits. Set math and geometry limits after the dependency spike.

## 4. Foundation design

### 4.1 Descriptor and registry contracts

Add optional descriptors for category, interaction family, content schema version, supported response format, grading policies, available surfaces, manual-review capability, media needs, child eligibility, and bundle requirements. Authoring, bank creation, publish validation, learner rendering, reports, and practice should consult the same manifest.

Keep `ohmylms_register_question_type`, `registerQuestionEditor`, current callback signatures, and the frontend validator/mount APIs usable. Introduce optional adapters with legacy fallback: normalize/validate response, determine presence, build public view, encode/decode delivery tokens, expose part weights, render report response, and select historical grader. Existing extensions need no new methods to continue functioning.

Use new authored modules under `includes/Assessment/QuestionTypes/` and `assets/src/features/question-editor/types/` as proposed locations. Keep runtime factories/manifests consistent with the repository adapters. Third-party types missing enhanced metadata keep their existing editor and unsupported preview notice rather than being silently removed or rewritten.

### 4.2 Private document and public projection

Store new typed content in validated question settings JSON through the existing writer, with a per-document format version. Reuse the version table; do not create a parallel question store. Option-based legacy types continue using their current option rows.

Separate private author data from public delivery data:

| Private frozen data | Public delivery data |
| --- | --- |
| Correct options, pair/category maps, blank alternatives, correct grid cells | Visible choices, categories, blank IDs, grid headers, scoped opaque tokens |
| Hotspot correctness, labeling assignments | Visible region geometry/targets without correctness or pairing |
| Graph solution, math expected expression, tolerance, rubric | Axes/bounds, allowed tools, response mode, learner instructions |
| Child snapshots and answer keys | Recursively projected child prompts and response controls |

Visible choices can include the correct choice, as with normal multiple choice; do not expose which choice is correct or its target mapping. Use explicit type-specific allowlists, not removal of a few known secret keys. Test every learner REST payload, HTML attribute, preview projection, practice response, and feedback-release boundary.

Assign stable IDs to blanks, rows, columns, regions, labels, items, and parts. IDs persist across reorder; index positions are presentation only. Duplication remaps IDs and internal references consistently. Keep display tokens separate from authored IDs.

Prompt structure must persist independently of answer solutions. For new blanks use semantic prompt nodes (text/media/blank references), not answers embedded in brace strings. Keep the legacy brace parser and case-sensitive behavior for existing versions. Conversion is explicit, previewable, and creates a new version; old versions remain readable.

### 4.3 Response codecs

Do not globally reinterpret arbitrary JSON. A typed response adapter validates only the shape belonging to the frozen question. Conceptual response shapes:

| Family | Shape before transport-specific token encoding |
| --- | --- |
| Choice / hot text / hotspot | Selected token list |
| Blanks / table | Map of blank ID to typed text, selected token, or math value |
| Match / categorize / labeling | Map of scoped target/item token to selected assignment token |
| Grid | Map of row token to selected column tokens |
| Graph | Bounded point/equation/region descriptors; finite numeric values only |
| Composite | Map of child/part ID to child response |
| Recording / drawing | Owned artifact ID plus bounded metadata, never base64 media |
| Slide | No response |
| Discussion | Posts live in session storage; do not serialize classmates' posts into an attempt answer |

Specify serialize, restore, normalize, draft validation, final validation, meaningful presence, and required-completion rules per type. Draft validation accepts incomplete but well-formed responses; final required checks enforce the authored completion rule. Both client and server enforce it; server remains authoritative.

Extend autosave and submission through the same codec. Add registered collection/restore hooks with the current form-field fallback. Dispatch answer-change events for canvas and graph controls. Handle navigation away, retry, offline status, pending uploads, resumed state, stale sequences, and deadline expiry. Preserve the existing JSON ceiling until measurements justify a bounded change.

Token scopes include delivery, question/child, entity kind, and version. Validate every referenced ID/token against that frozen item, including nested keys. Do not permit raw IDs or cross-attempt tokens. Preserve existing HMAC token formats for old types; add a new scoped format for typed documents.

### 4.4 Grading and version selection

Add explicit historical schema readers and grader dispatch. Freeze grading policy/configuration with each question version. A code upgrade must not silently change an old snapshot's result. Retain legacy callbacks as grader v1; retain required historical implementations while attempts or reports reference them. Unknown versions fail with a useful unsupported-version error.

Proposed policies:

- `exact`: full credit only for the complete expected response.
- `per-part`: weighted correct blanks, pairs, targets, or categories; normalize by eligible authored weight.
- `net-correct`: `max(0, (correct selections - incorrect selections) / total correct selections)` for multi-select, hot text, hotspot, and grid when selected by the author.
- `ordered-position`: proportion of items in their correct position; opt-in for new reorder versions only.
- `manual`: pending until reviewed against the frozen rubric.
- `ungraded`: response recorded but excluded from grade totals, passing thresholds, and skill attainment.

Deduplicate selected tokens before scoring, reject unauthorized references, cap fractions to [0,1], and reject nonfinite values. Empty answer keys are publish errors for scored types. Required is independent of scored: a mandatory poll still has zero academic marks.

Keep existing exact multi-select/matching/reorder results. New partial grading requires an explicit authored policy and new version. Existing decimal/legacy-int attempt policies remain unchanged.

Introduce an additive evaluation status such as unanswered, ungraded, pending, partial, correct, incorrect while keeping existing result fields for consumers. Preserve automatically earned child credit while manual children are pending. Generalize existing structured part reporting, review, and skill weights through the descriptor; child marks must sum to the parent budget exactly under the attempt rounding policy.

## 5. Authoring experience

Replace the long static selector with a searchable categorized picker: Basic, Text and blanks, Relationships, Visual, Math, Responses, and Content/groups. Each tile shows an example, grading mode, and supported delivery surfaces. Use existing WordPress components and OhMyLMS design tokens.

Keep the shared `QuestionForm` for quiz cards and bank/skill creation. Each type supplies its answer editor and contextual settings. Shared settings cover marks, required, grading policy, feedback, media, accessibility, and skill mapping. Show only meaningful settings: slides have no required-response switch; polls cannot have positive academic marks.

Provide Edit, Learner preview, and Grading test. Local preview uses unsaved public data; the existing player preview remains the saved full-delivery check. Grading test submits a sample response to an author-only endpoint against a temporary snapshot and returns a breakdown without attempts, grade events, evidence, or publication.

Type changes retain the prompt and compatible shared settings. Show what answer data would be removed, offer cancel, and support undo. Safe conversions: choice to poll; choice to multi-select with explicit key review; typed blanks to dropdown only after options are supplied. Never automatically map an image geometry question to an unrelated text type.

Validation points to the exact blank, row, region, or child. Drafts can remain incomplete; publishing must reject incomplete answer keys, invalid geometry, empty scored parts, unsupported graders, inaccessible essential media, and unavailable runtime capabilities.

## 6. Implementation milestones

### A — Contracts and baseline

1. Capture fixtures for every existing type, legacy/versioned attempt, custom extension, manual part, quiz placement, and bank-created question.
2. Add descriptor contracts, type catalog, version readers/dispatch, response codecs, and generalized part/presence hooks with legacy defaults.
3. Extend public projection, scoped token handling, autosave adapters, submission validation, and report dispatch.
4. Trace submission, practice, inline checks, preview, deadlines, review, skill evidence, streak meaningfulness, and exports for assumptions about flat answers and positive marks.

Exit: all old fixtures grade and resume identically; a nested demonstration fixture survives save/resume without leaking keys; custom extension fixtures pass. Nothing new is enabled for authors yet.

### B — Existing types, engagement responses, and picker

1. Ship the categorized picker across quiz and bank creation.
2. Improve choice, true/false, reorder, matching, and written editors; add explicit partial policies under new grader versions.
3. Implement poll and word cloud, including aggregation and teacher report views.
4. Separate participation from academic accuracy; define zero-total assessments and completion behavior explicitly.

Exit: all existing types plus poll/word cloud save/reopen, preview, deliver, resume, and report. Old scored attempts remain unchanged; poll-only assessments do not divide by zero or create mastery evidence.

### C — Blank engine and tables

1. Implement semantic inline blank nodes with stable IDs, typed responses, alternatives, case/accent normalization, and distractor settings.
2. Add drag/drop and dropdown editors/renderers using the shared engine.
3. Add table builder with headers, cell roles, blank insertion, row/column editing, and explicit deletion warnings.
4. Retain legacy braces and provide an optional conversion preview.

Exit: mixed text/dropdown blanks, duplicate answer labels, shuffled banks, partial responses, all-blank required checks, and table resume work. Math blanks are capability-gated until G; no fake equivalence support is shown.

### D — Relationships and visual interactions

1. Categorize: category/item editing, distractors, assignment validation, tap and keyboard alternatives.
2. Match Table Grid: single/multiple per row, bounded dimensions, accessible row/column headers, exact/net-correct scoring.
3. Hot text: selectable semantic spans with stable IDs; invalidate/review affected spans when passage text changes. Do not anchor spans only by fragile character offsets.
4. Labeling/hotspot: freeze background media; normalized coordinates in [0,1]; author region editing; visible targets/region tokens; touch and keyboard controls.

Exit: resize and mobile layouts preserve geometry; polygons validate; all controls work without dragging; partial grading and unknown-token rejection pass; correct target mappings are absent from learner payloads.

### E — Passage, multi-part, and content slides

1. Generalize the composite engine while retaining structured v1's numerical/text/written parts.
2. Mixed children use embedded immutable child content in the parent's version. If imported from the bank, record provenance and explicit copy semantics; do not follow mutable child drafts during an attempt.
3. Disallow cycles, duplicate IDs, excessive depth, discussion children, and unsupported child types. Hide new child types until their end-to-end milestone passes.
4. Passage uses shared reference content and child navigation; multi-part uses a shared question stem. Keep groups together during shuffling and pools. Define pagination and required behavior per child.
5. Add slides as non-response items; update navigation, progress counts, totals, and report exports.

Exit: editing an imported child leaves active attempts unchanged; auto/manual children retain their individual marks and frozen skill mappings; group resume works; slides never produce correctness/evidence.

### F — Drawings and recordings

1. Implement private response-artifact storage and authorized streaming before exposing recording controls. Ordinary publicly addressable Media Library URLs are unsuitable for learner responses.
2. Bind uploads to learner, attempt/item, and question version; enforce MIME signature, size, duration, ownership, and completion state. Store outside the public web root or use private object storage; serve through a checked endpoint or short-lived authorized URL.
3. Draw: background/blank canvas, bounded stroke vectors and rendered preview, undo/redo, accessible alternative submission.
4. Audio/video: capability detection, permission-denied states, supported codecs, recording timer, retake/replay, interruption recovery, mobile browser tests, and configurable upload fallback.
5. Extend teacher rubric review, private report playback, retention/export/erasure integration, and orphan cleanup. Do not delete artifacts referenced by retained attempts.

Exit: another learner cannot retrieve media; upload references cannot be forged; pending upload prevents false Saved status; manual grades flow through existing grade events; no media enters mastery before eligible review. Maximum-duration files load on supported browsers.

### G — Math response and graphing

Start with a dependency/algorithm spike: inventory existing math renderers, compare maintained licenses and bundle sizes, and prototype server evaluation under PHP 7.4 compatibility. An external service is optional, not assumed.

1. Math response: math keyboard and accessible input, expression AST, numeric/fraction modes, variable/domain declarations, exact/equivalence policies.
2. Use bounded parsing and supported algebraic transformations. Never execute expressions with PHP `eval` or JavaScript evaluation. Random numeric sampling may reject candidates but is not proof of equivalence. Unsupported expressions must produce an explicit authoring limitation or manual-review route.
3. Graphing: points, linear/quadratic/exponential families, then inequalities. Grade canonical coefficients/points and boundary/region semantics, not screenshots or dragged pixel positions.
4. Freeze axis bounds, tools, precision, units, domain, tolerances, and solution family. Cap term count, nesting, exponent size, execution time, and geometry complexity.
5. Enable math blanks only after the same math engine works in standalone and composite contexts.

Exit: equivalent and nonequivalent expressions, domain exceptions, undefined values, boundary inclusivity, duplicate points, tolerances, and resource-exhaustion cases pass. A learner's valid answer is graded identically across render sizes.

### H — Discussion Board and minimum live delivery

This milestone requires server/session work beyond the editor. Reuse existing class/course identity and access policies; do not assume the asynchronous quiz player already supports synchronized moderation.

1. Define live session ownership, membership, lifecycle, current item, monotonic event cursor, instructor progression, disconnect/reconnect, and closed-session behavior.
2. Add bounded session/post/comment/reaction storage with prepared SQL, pagination, rate limits, idempotent posting, and revisioned moderation states. Polling is an acceptable first transport; measure load before adding another transport.
3. Author settings: one/multiple posts, comments, post approval, comment approval, name visibility. Proposed defaults: moderation on; max five posts; max 400 grapheme clusters per post.
4. Teacher moderation: approve/reject/hide, pending queue, student participation, disable comments. Students see approved peer content only; hiding names must remove identities from the learner API, not just CSS.
5. Lock this type to supported live sessions and reject unsupported publication/delivery combinations. Approval is publication of a response, not academic grading.

Exit: pending posts are never exposed to peers; unauthorized joining/moderation fails; reconnect preserves board state; duplicate reactions/posts do not inflate results; session close blocks further writes. Run a concurrent classroom simulation.

### I — Optional AI evaluation

After manual written/audio review is complete, add a provider adapter disabled until configured. This is separate from AI question generation.

Freeze rubric, model/provider identifier, evaluation configuration, and any transcript used for grading. Use asynchronous jobs with deduplication, timeout/retry, explicit pending/failure states, usage limits, and teacher override. Treat student text as response data, never evaluator instructions. Store reproducible evaluation metadata with grade history without exposing credentials.

Audio evaluation requires a separate transcription step. Configure institutional data-sharing and retention before any provider receives learner submissions. Keep manual review available when evaluation is unavailable or uncertain. AI marks must not silently establish skill mastery; default to teacher-confirmed grades and explicitly decide evidence eligibility.

Exit: rubric samples at strong/partial/weak levels distinguish performance; failed jobs remain reviewable; retries do not duplicate grades; teacher overrides supersede prior contributions once.

## 7. Storage, API, reports, and security work

Use existing settings/version/response JSON for bounded content. Add schema tables only for artifacts and live discussion/session data. Apply additive, idempotent migrations with inventory and rollback rehearsal. Preserve existing answer-row/report anchors and spelling in public fields such as `achive_mark`.

Extend current REST routes for typed authoring and responses while preserving old payloads. Proposed additions: author-only sample evaluation; attempt-bound artifact upload/finalize/retrieve; live-session board/moderation routes. Each requires an explicit permission matrix and resource-level checks, not just a generic logged-in capability.

Report renderers must display each actual submitted response and frozen question version: selections, per-blank results, pairings, categories, grid cells, image placements, graphs, media, and child rubrics. Add breakdowns without breaking current consumers. Distinguish pending and ungraded from incorrect. Feedback release must control solutions/explanations independently from access to one's own response.

For mixed questions, review accepts child/part marks with validation against frozen budgets and writes the existing grade event/evidence chain. Practice eligibility becomes descriptor- and configuration-based: exclude ungraded, manually graded, live-only, secure, archived, or unsupported nested types. Preview creates no learner attempt, evidence, streak, upload artifact persistence, or discussion posts; recordings can remain local temporary objects there.

Security requirements include capabilities/nonces where applicable; object ownership for all question/attempt/media/session operations; schema and payload limits; context-aware output escaping; sanitized authored HTML; prepared SQL; no executable uploaded content; no trusted external SVG/HTML; and no answer-key access through exports, errors, or hidden fields. Preserve mathematical backslashes and intentional rich HTML through type-aware normalization rather than blanket sanitization.

## 8. Accessibility and performance

- Target WCAG 2.2 AA interactions: visible focus, labels, instructions, announcements, contrast, touch targets, reduced motion, and understandable validation.
- Every drag operation also supports select-then-place and keyboard controls. Canvas/graph/image questions need a meaningful keyboard and screen-reader path appropriate to the assessed skill; a static alt string alone does not replace the interaction.
- Use normalized image geometry, semantic tables, stable text segments, and accessible equations. Test Mongolian and English, Unicode case/accent behavior, RTL, zoom, touch, and long translated labels.
- Lazy-load drawing, math, graphing, recording, and live-board modules only when used. Reuse WordPress React; do not add a second runtime. Set explicit bundle/payload and concurrent-session budgets during A and measure them at each heavy milestone.
- Recordings upload independently with progress; responses store references. Large reports paginate. Aggregate word-cloud and discussion data on the server with authorization and bounded queries.

## 9. Verification and acceptance matrix

Every type's release checklist:

1. Create from quiz, bank, and skill entry points where supported; edit, duplicate, reorder, save, reopen, publish, and pin versions.
2. Local unsaved preview and saved player preview agree with learner interaction; sample grading has no durable learner side effects.
3. Submit correct, incorrect, incomplete, duplicate, malformed, and unauthorized references; verify exact and partial policies.
4. Autosave, resume, out-of-order requests, equal-sequence retries, concurrent tabs, offline retry, deadline finalization, and required navigation work.
5. Edit the question after attempt start; frozen rendering, solutions, and historical grading remain unchanged.
6. Manual/regrade reports, part totals, feedback release, exports, evidence supersession, practice eligibility, and ungraded denominators behave correctly.
7. Learner/public payloads contain no private keys; authorization attacks and XSS fixtures fail safely.
8. Keyboard/touch/screen-reader and mobile layouts work; custom extension fixtures retain their public APIs.

Tests to add: focused JS model/codec/conversion tests; PHP schema/token/grading/version tests; WordPress integration tests for authorization, nested persistence, revisions, grades, media, and evidence; browser tests for representative type families and all catalog authoring paths. Use shared JSON fixtures to verify JS/PHP normalization consistency. Property tests should cover scoring bounds, shuffled-ID invariance, invalid coordinates, and composite mark conservation.

Run repository checks after implementation changes:

```text
npm run format:check
npm run lint
npm test
npm run build
npm run test:reproducible
php tests/unit.php
php tests/assessment-unit.php
php tests/php/custom-question-registry.php
php tests/php/assessment-integration.php
npm run test:browser -- tests/browser/assessment.spec.cjs
```

Run PHP syntax checks on changed PHP files and the relevant additional permission/integration suites. `npm run check` bundles formatting, lint, tests, and build. Integration/browser tests use the documented disposable database (`ohmylms_source_test`) and external `OHMYLMS_TEST_CREDENTIALS`; no test resets against the working site. New media/live tests must respect fixture isolation and cleanup.

Follow official WordPress PHP/JS/CSS standards. Modify authored source and adapters/manifests, then rebuild; never patch dependencies, recovered third-party runtime bundles, or generated assets directly. Report actual failures, including pre-existing findings, without blanket baselines or rule suppression. Existing working-tree changes must be preserved; establish ownership before any broad formatting pass.

## 10. Rollout, effort, and decisions

Deliver A through G in dependency order, with H and I separate releases. Each milestone includes reports, tests, migrations, and documentation; no unfinished types exposed in the author picker. Enable new types individually. Disabling authoring must not disable readers/graders needed by already-published versions or active attempts.

Initial release: A/B/C. Second: D/E. Third: F/G. Separate live-board and AI releases: H/I. Classroom pilot each release on the isolated/staging site, then enable for selected authors. Keep legacy formats readable indefinitely or until an audited retention/migration policy permits retirement. Rollback freezes new authoring, retains historical type modules, and restores only compatible code/schema backups; simply turning off the new engine cannot make legacy code read new question documents.

Indicative effort for one experienced developer with part-time design/QA support, not a delivery promise:

| Milestone | Planning range |
| --- | --- |
| A: contracts and regression foundation | 2–3 weeks |
| B: picker, existing types, poll/cloud | 1–2 weeks |
| C: blanks and tables | 2–3 weeks |
| D: relationships and image types | 3–5 weeks |
| E: composites and slides | 2–3 weeks |
| F: private media, drawing, recording | 3–5 weeks |
| G: math and graphing | 4–7 weeks |
| H: live sessions and moderated board | 3–6 weeks |
| I: optional AI evaluation | 2–4 weeks |

A–G: roughly 17–28 development weeks, plus pilot/release time. H/I add 5–10 weeks. Reestimate after A and the math spike; private storage availability, browser coverage, symbolic equivalence scope, and existing standards debt materially affect effort.

Defaults to proceed with: preserve old scoring; partial grading opt-in for changed existing types; embedded frozen composite children; ungraded poll/cloud/slide/board; private learner recordings; manual evaluation before AI; new live service only for the board milestone; no new third-party service assumed.

Decisions to resolve at the relevant milestone: target browsers/mobile devices; recording upload fallback and retention; required symbolic math subset and units; screen-reader alternatives for visual skills; classroom concurrency targets; live transport; AI provider/budget/data policy; whether reviewed media can count as independent skill evidence. These do not block preparing or implementing the foundation.

## 11. External behavioral references

Research uses the editor inspected on 2026-10-09 plus official help. The current catalog includes newer options absent from older overview counts. Table Fill In was observed opening a Fill in the Blank editor with a table; its saved student behavior was not tested. Multi-part authoring was inspected, but cross-part grading was not verified. The corresponding OhMyLMS behavior above is therefore an explicit product design.

- [Question types overview](https://help.wayground.com/support/solutions/articles/158000411419-question-types-explained)
- [Assessment creation](https://help.wayground.com/support/solutions/articles/158000462332-create-an-assessment-quiz)
- [Fill in the blanks](https://help.wayground.com/support/solutions/articles/158000405102-question-type-fill-in-the-blanks)
- [Drag and drop / dropdown](https://help.wayground.com/support/solutions/articles/158000405103-question-types-drop-down-drag-and-drop)
- [Match Table Grid and scoring](https://help.wayground.com/support/solutions/articles/158000458657-question-type-match-table-grid)
- [Hot text](https://help.wayground.com/support/solutions/articles/158000427805-question-type-hot-text)
- [Labeling](https://help.wayground.com/support/solutions/articles/158000405109-question-type-labeling)
- [Hotspot](https://help.wayground.com/support/solutions/articles/158000405108-question-type-hotspot)
- [Graphing](https://help.wayground.com/support/solutions/articles/158000405111-question-type-graphing)
- [Open-ended rubric evaluation](https://help.wayground.com/support/solutions/articles/158000437795-question-type-open-ended)
- [Audio and video response](https://help.wayground.com/support/solutions/articles/158000405104-question-types-audio-response-video-response)
- [Discussion Board](https://help.wayground.com/support/solutions/articles/158000471143-question-type-discussion-board)
- [Flexible grading](https://help.wayground.com/support/solutions/articles/158000404052-flexible-grading-on-wayground)
- [AI evaluation](https://help.wayground.com/support/solutions/articles/158000404055-auto-evaluate-open-ended-audio-responses-with-ai)

Wayground's grid net-correct policy is intentionally adopted as an optional policy; categorical/blank/ordering defaults and limits in this plan are OhMyLMS choices. Optional AI work requires fresh official provider documentation when its integration begins.
