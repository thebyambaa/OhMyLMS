# PDF figures: implementation plan

Status: implementation started; October 25, 2026. The milestones below include outstanding work.

Implementation update: the first private rendering/asset slice is now implemented; see
[PRIVATE-FIGURE-ASSETS.md](PRIVATE-FIGURE-ASSETS.md) for its API, setup, verification,
and explicit remaining work. This does not complete milestone 1 or enable student figures.

This plan supersedes the figure release sequence and renderer proposals in
[EXAM-PAPERS-AND-FIGURES.md](EXAM-PAPERS-AND-FIGURES.md). Existing import and assessment contracts remain compatible.

## Agreed scope

- Start with Cambridge IGCSE Mathematics 0580/43, October/November 2025, question paper and mark scheme; add contrasting fixtures afterward.
- Accept question papers without mark schemes. Proposed answers remain unverified drafts.
- Deliver figures in exams, quizzes, question previews, and printing.
- Teachers can create missing crops, resize, reassign, and reorder them. Every question requires explicit confirmation that figures are complete or none are required.
- Missing or unresolved required figures block publication. A teacher-approved original crop resolves a reconstruction failure.
- Reconstruct basic geometry and coordinate/function graphs first, including figures marked “Not to scale.” Keep that label visible, retain approximate layout and stated relationships, and infer no measurements from appearance.
- The importing teacher explicitly approves each reconstruction. Initial correction controls cover labels and basic properties; structural changes use regeneration.
- Figures are fixed by default. Interactive answering is a later assessment feature.
- Retain private assets while any question version or attempt references them, independently of import or exam deletion.
- Sequence: protected original figures, reviewed JSXGraph reconstructions, Digital SAT, IELTS Academic. The SAT scope includes a full adaptive two-module experience, requiring a separate delivery and scoring specification.

## Evidence and boundaries

The targeted repository trace covered `ExamImport/{Documents,Extraction,Jobs,Workflow}.php`,
`QuestionBank/{BulkImport,VersionPublisher,MediaFreezer}.php`, assessment snapshots and delivery,
exam source authorization, teacher review, quiz templates, and continuous paper delivery.
This is not a complete runtime audit or a real-paper accuracy result.

Extraction currently retains source-page references rather than figure assets; commitment forwards no figure data.
Versions already freeze question content, while the existing media freezer writes to uploads.
Private figures therefore require protected storage and version references rather than public media URLs.
Existing source access is scoped to import ownership or exam delivery; reusable questions also need authorization through quiz and preview contexts.

The local shell has Poppler tools installed with desktop tooling. This does not establish production availability
or availability to WordPress's web PHP service. Do not depend on Codex runtime paths in plugin code.
Existing working-tree changes include player and paper work; reconcile with those changes before implementation.

## Milestone 1: contracts, rendering capability, and private asset lifecycle

1. Add a renderer interface with an explicitly configured executable or provider adapter. Initial proposal: local Poppler, subject to a capability check under the actual WordPress worker identity.
2. Define configurable bounds for pages, raster dimensions, total pixels, duration, disk use, and retries. Fail visibly on unsupported, encrypted, malformed, or oversized inputs.
3. Define page coordinates against the rotated visible page, with a top-left normalized coordinate system. Persist page dimensions and transformation metadata so crop correction maps consistently to pixels.
4. Add immutable crop assets: opaque ID, checksum, MIME, dimensions, document role, source checksum/page/bounds, renderer version, and creation provenance. Restrict initial output to a bounded raster format.
5. Separate assets from placements. A placement has its own ID, stable question/part associations, order, accessible description, review state, selected representation, and optional reconstruction reference.
6. Keep source IDs and paths server-side. Treat AI-proposed page/bounds as untrusted candidates; resolve source documents from the job.
7. Define retained references from drafts and immutable versions. Job cleanup removes unreferenced temporary files; referenced committed assets survive. Design orphan cleanup around crashes and rollback because filesystem writes are not database transactions.
8. Define authenticated streaming for author review and authorized question delivery. Check the exact asset's membership in the delivered version and current access on each request. Apply private cache controls and preserve separate mark-scheme release rules.

Likely additions: figure schema, private asset store, renderer adapter, delivery service, and retention service.
Use existing `Documents.php` and `Exams/Player.php` trust boundaries as references, without routing reusable figures solely through exam IDs.

Gate: render and correct a private fixture crop; unauthorized requests fail; import deletion cannot break a referenced historical asset.

## Milestone 2: preserve and deliver originals

1. Extend `Extraction.php` with bounded figure candidates and stable associations, retaining legacy `requires_source` and page fields.
2. Extend `Jobs.php` and `Workflow.php` with bounded rendering/detection checkpoints. Use stage identity plus source checksum, page, bounds, and renderer settings for idempotence. Keep teacher edits separate from regenerated proposals.
3. Add manual crop creation, correction, assignment, ordering, and completeness confirmation in `ExamsPage.jsx`; invalidate affected approvals after edits.
4. Carry figures through `BulkImport.php`, editable question storage, `VersionPublisher.php`, `QuestionSnapshot.php`, and learner-safe projections. Select the canonical storage field after checking all settings sanitizers and round trips. Include immutable selections and spec references in the content hash; add no expiring URLs to frozen content.
5. Enforce publication gates on the server for every publication path, including reusable questions and exam revisions. Legacy records remain readable and editable; define explicit review behavior before republishing an unresolved legacy import.
6. Add shared figure presentation to structured questions, previews, quiz templates, continuous paper, and print. Audit `PrintPaper.php`, `PaperPresentation.php`, `paperDocument.jsx`, `PaperQuestion.jsx`, and preview delivery for frozen-version consistency.
7. Keep mark-scheme crops, rubrics, and answers out of student question projections, including accessible descriptions and asset metadata.

Gate: original figures survive review, save/reopen, commitment, version approval, exam/quiz creation, attempts, and printing. Old attempts retain the exact selected crop after later edits.

## Milestone 3: declarative JSXGraph reconstruction

1. Define an independent figure-spec version and supported renderer versions. Bound points, segments, polygons, circles, markings, axes, and supported function expressions.
2. Reject executable JavaScript, HTML, external URLs, unsupported properties, invalid references, dependency cycles, nonfinite values, and excessive complexity. Use a restricted expression grammar for plots.
3. Separate visual layout from stated mathematical constraints. Keep visible labels, units, markings, and the “Not to scale” note; do not expose measuring controls that introduce answers.
4. Reuse appropriate JSXGraph lifecycle and expression parsing from `assets/src/blocks/interactive-activity/`; keep exam figures fixed and avoid automatically inheriting activity controls.
5. Analyze the retained crop with question context to generate an unapproved proposal. Unknown or unsupported content remains on the original-image path.

Gate: validated fixture specifications render correctly; invalid specifications are rejected; unsupported figures retain their originals.

## Milestone 4: review and resilient reconstruction delivery

1. Add original/reconstruction comparison, label/basic-property editing, regeneration, and explicit per-figure approval.
2. Freeze teacher selection, spec version, and immutable original reference in question versions. Never mutate published content or historical attempts in place.
3. On unavailable libraries, unsupported versions, mount errors, or runtime failure, display the authorized original crop and record a diagnosable failure without exposing private content in logs.
4. Reuse approved rendering across preview/player surfaces; clean up boards on navigation. Provide static print output, with the original crop available when reconstruction export fails.

Gate: approved originals and reconstructions render across all agreed surfaces; injected renderer failures show the original; no unreleased solution reaches the student.

## Milestone 5: acceptance, release, and later programs

Create a private fixture manifest with expected figure counts, page/bounds, associations, labels, scales, and teacher selections.
Start with the agreed 0580 paper, then cover scanned/rotated pages, vector diagrams, multiple/shared figures,
multipage questions, unsupported content, and imports without mark schemes. Do not commit exam PDFs or crops into the repository.

Test duplicate and stale scheduler deliveries, interrupted writes, concurrent reviews, retained references,
revoked access, cross-user requests, figure swaps, publication gates, mobile presentation, printing, and renderer failures.
Extend existing exam import PHP/HTTP rehearsals, relevant JS tests, and browser coverage with meaningful behavior checks.

Run PHP syntax checks, relevant tests, `composer test`, and repository checks through `npm run check`.
Run applicable browser and real WordPress rehearsals. Report actual failures and existing standards debt;
do not suppress them or edit generated/dependency code. Rebuild authored assets through the repository build.

Release originals only after milestone 2 acceptance. Release reconstruction after milestones 3–4 acceptance.
Additional figure types and interactive response/grading contracts follow separately.
Before Digital SAT implementation, specify module routing, item pools, timing, accommodations, scoring,
attempt snapshots, and what fidelity is claimed. Before IELTS Academic implementation, specify sections,
prompt-figure requirements, answer collection, and marking behavior.

## Immediate implementation slice

Implement milestone 1 with a synthetic private PDF and one manually selected crop first.
Verify the renderer under WordPress's worker identity, immutable asset retention, and authorized delivery
before adding automated diagram detection or JSXGraph generation. Production renderer capability remains
unverified; the initial real-paper files must also be located or supplied before their acceptance rehearsal.
