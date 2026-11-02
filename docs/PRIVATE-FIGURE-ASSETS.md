# PDF diagrams and native figures

Status: Cambridge diagram workflow implemented, October 25, 2026. This extends the existing PDF importer; imports still require teacher review and never publish automatically. See [the implementation plan](PDF-FIGURES-IMPLEMENTATION.md) for the broader release sequence.

## Workflow

1. Explicit PDF extraction requests bounded figure candidates: source page, normalized crop bounds, stable figure/part IDs, description, and a not-to-scale flag. Only question-paper figures are eligible. Mark schemes remain separate and optional.
2. Existing Action Scheduler checkpoints queue private original crops. Identical requests reuse assets; corrected bounds create new immutable assets. Failed or uncertain crops stay visible for correction. Invalid AI crop bounds retain the question text with a required manual-crop warning; invalid teacher edits are rejected.
3. Review provides a full-page drag selection with keyboard-accessible percentage fields, crop replacement, ordering, part assignment, reassignment between questions, and original/reconstruction comparison. New detections from re-extraction are offered separately from saved teacher edits.
4. Teachers may request a JSXGraph proposal using the configured exam converter, or write its declarative configuration. They can edit labels, regenerate structural changes, accept it instead of or beside the original, or reject it, and explicitly approve each selected figure. Every question also requires figure-completeness confirmation. In the new admin these are the proposal routes of [the exams API](api/exams.md#figure-proposals-jsxgraph); row edits cannot select a reconstruction.
5. Commitment seals the exact reviewed content and creates native structured questions. Question approval and quiz revision publication reject incomplete, changed, missing, or unattested figure content.
6. Frozen question settings retain placements, selections and specs. Existing continuous papers retain their frozen questions and figures even after a later live placement is removed. Student delivery builds protected URLs for the exact attempt/version. Shared-stem and part figures render in players and previews. Printing uses a static SVG of the selected reconstruction or the original image on failure.

Not-to-scale figures are supported reconstructions and retain a visible note. Points, segments, polygons, circles, angle markings, axes, and bounded function-expression trees form spec version 1. Unsupported or uncertain figures use the approved original. Generated JavaScript, HTML, URLs, invalid references, nonfinite values, and oversized specs are rejected. Boards are fixed, with navigation, dragging, zoom controls and coordinate infoboxes disabled. Native crop resolution metadata preserves original physical dimensions in print; exact measurement tasks should use the original crop and actual-size printing.

## Deployment

`OHMYLMS_IMPORT_PRIVATE_DIR` must be writable by PHP and outside all served directories and aliases. Configure an absolute path to an installed Poppler executable:

```php
define( 'OHMYLMS_PDFTOPPM_BINARY', '/usr/bin/pdftoppm' );
```

Windows paths are supported. The local site was configured to use its existing MiKTeX Poppler installation; the web PHP capability probe succeeded. No development-runtime path is embedded in the plugin. Other deployments need their own configured binary and worker capability check.

`proc_open`, `proc_get_status`, `proc_terminate`, and `proc_close` must be enabled. GD and Imagick are not renderer requirements. Application watchdogs bound rendering to 20 seconds, a 2400-pixel maximum side, 10 MiB stdout and 64 KiB stderr. These do not replace host-level process resource controls. A killed PHP process can leave a private temporary workspace; normal completion and handled failures remove it.

Back up private sources/crops and WordPress together, including `_ohmylms_figure_review_key`. This private, nonautoloaded key binds approvals to exact content independently of WordPress login-salt rotation. It is never included in learner projections.

## APIs and contracts

Core WordPress REST authentication and cookie nonces remain in force.

| Request | Behavior |
| --- | --- |
| `GET /wp-json/ohmylms/v1/exam-import-renderer` | Exercise the configured renderer and private temporary storage; return safe capability metadata. |
| `POST /wp-json/ohmylms/v1/exam-imports/{id}/figures` | Queue a crop from an owned, validated question-paper source. |
| `GET /wp-json/ohmylms/v1/exam-imports/{id}/figures` | List safe crop status and owner-preview URLs. |
| `POST /wp-json/ohmylms/v1/exam-imports/{id}/reconstruct` | Explicitly analyze a ready owned crop and question context; return an unapproved validated spec. |

Crop body:

```json
{"page":2,"bounds":[0.1,0.2,0.9,0.65]}
```

Bounds are `[left, top, right, bottom]`, normalized to the top-left of the rotated visible PDF CropBox. Metadata retains actual page raster dimensions, pixel bounds, source checksum, renderer contract version, and physical dimensions when supplied by Poppler. Each import permits 100 retained assets and each question 20 placements. Rendering permits three attempts per asset. Interrupted work can be resumed by repeating the same request, within that budget.

Reconstruction body includes `asset_id` and `question` text. Native PNG adapters support the existing OpenAI, Anthropic and Gemini provider selection. The existing import request budget is reserved before an outbound call; tokens and failures are recorded. Reconstruction never reads mark-scheme assets or marking rubrics. Raw responses, private paths and provider credentials are not returned or retained in student content.

Editable settings use `figures` and `figure_review`; the existing immutable version settings column includes them in its content hash. Figure-spec versioning is independent of grader/version contracts. Learner projections contain rendered selected figures and protected original URLs, not approval attestations, source handles or rubrics.

## Access and retention

Originals are nonpublic `ohmylms-figure` records with private `figure-{uuid}.png` files. They are never public Media Library attachments and never enter the public media freezer. Serving checks file integrity as well as current authorization.

Source owners can preview their own assets. Authorized question users can preview assets belonging to reviewed question content. Students need their own actual attempt containing that exact version and current quiz/course access. Guessed attempt IDs, unrelated versions, guests, and direct access to another import owner's asset are denied. Image responses are private and no-store.

Crops survive source-job deletion and later question edits. Retention is conservative: unreferenced assets are also retained; automatic garbage collection is intentionally not enabled. Existing source-only imports remain readable with their existing PDF fallback. Imports still under review can adopt new detections or manually add crops without replacing teacher edits.

## Verification and limits

Verification includes real Poppler/WordPress integration, actual native review/commit, immutable version delivery, ownership and attempt checks, duplicate delivery, bounded retries, renderer failures, scanned raster-only PDFs, rotation, CropBox handling, original physical dimensions, provider PNG adapters, and invalid-spec rejection. Reconstruction endpoint tests intercept transport, exercise successful and invalid proposals, and confirm the original survives failure.

Browser tests exercise fixed JSXGraph geometry/functions, original fallback, unknown spec versions, mobile width, and selected print SVGs. A separate built WordPress UI rehearsal verified protected original/reconstruction comparison, label edits, per-figure approval, saved completeness, the web renderer probe and guest denial using a temporary QA import. The QA import is not an approved or published real exam.

Real 0580/43 October/November 2025 pages were rendered and manually inspected for stem-and-leaf data/key, a scale map, a decagon, and a labelled geometry figure. A crop omitting a not-to-scale note was corrected and rechecked. These checks establish crop behavior, not automatic detection or model accuracy across the full paper. Every proposed reconstruction still needs teacher approval; no live paid-provider accuracy claim is made.

Verification on October 26, 2026: 89 figure integration checks, 28 extraction checks, 22 existing import checks, and 16 standalone grading/registry checks passed. JavaScript tests passed 649 with one skipped; all four figure browser tests passed. Scoped PHP standards/syntax and full JavaScript/CSS lint passed; the asset build succeeded with existing webpack performance warnings. The full repository check stopped at existing PHP standards failures. Local PHP retains its existing missing Imagick DLL startup warning; the renderer does not need Imagick.

The fresh-request native player preview was also exercised in WordPress, including its runtime assets, protected original and physical print dimensions. Temporary QA users, preview sessions, imports, source copies and render files were removed afterward; original papers and teacher drafts were not published or changed.

Run `tests/php/figure-assets-integration.php --local` with the Local PHP configuration and `OHMYLMS_TEST_PDFTOPPM` set to a configured binary. Fixture writes roll back and recorded private fixture files are removed. Run the extraction unit tests, existing import rehearsal, browser figure tests, source tests, standards checks and asset build as well. Repository-wide pre-existing PHP standards findings remain visible.

Additional chart/3D types, interactive answering, the full adaptive Digital SAT workflow, and IELTS Academic-specific delivery/marking are separate later releases. They are not silently approximated by this figure renderer.
