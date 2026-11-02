# Skill workspace API

The skill workspace rebuilds the Content Hub workflows that only the deleted legacy admin had: combined
mappings, AI runs, shared content links, domain placement, skill-owned quizzes and provenance review.
The screens are sections of Content Hub › Skills (`features/curriculum/SkillWorkspace`, lazy chunk
`admin-app-skill-workspace`), shown to site administrators only (the Skills tab itself needs
`edit_posts`):

| Section | App route | Address |
| --- | --- | --- |
| Combined mappings | `/content-hub/skills/mappings?skill=ID` | `page=ohmylms-content&tab=skills&section=mappings&skill=ID` |
| AI runs | `/content-hub/skills/ai-runs[?syllabus=ID][&run=ID]` | `…&section=ai-runs` |
| Content links | `/content-hub/skills/content-links?kind=lesson\|quiz\|assignment\|course\|question&content=ID` | `…&section=content-links` |
| Domain placement | `/content-hub/skills/placement?subject=…[&domain=][&unplaced=0][&s=]` | `…&section=placement` |
| Skill quizzes | `/content-hub/skills/skill-quizzes?skill=ID` | `…&section=skill-quizzes` |
| Provenance review | `/content-hub/skills/provenance[?review=pending\|rejected\|approved\|all][&source=][&s=][&skill=ID]` | `…&section=provenance` |

The former addresses on the comparison screen (`/content-hub/curriculum/compare?tab=mappings|ai|content|
placement|quizzes|provenance`, `page=ohmylms-content&tab=curriculum&view=compare&compare=…`) still work:
the comparison screen replaces them with the section above and keeps the rest of the query.

Every route is under `ohmylms/v1`, requires the curriculum administrator permission
(`Curriculum\Access::admin`: 401 anonymous, 403 others) and has typed arguments, so malformed input
answers 400 (`rest_invalid_param` / `rest_missing_callback_param`) before domain code runs.

## Routes

| Route | Contract |
| --- | --- |
| `GET /skill-workspace/skills/:id/mappings` | `Curriculum\CombinedMappings`: the skill (shared or objective, subject, provenance), where it is placed, every mapping in both directions with relation, note, `source` (`manual`, `ai`, …), `confidence`, `group_uuid`, pair `revision` and the syllabuses of its objective; combined groups with their members; courses (draft outline, published program, linked), lessons, quizzes (linked, connected or pooled), assignments and questions (primary/supporting), each at most 20 with an exact `total`. 404 `ohmylms_skill_missing`. |
| `GET /skill-workspace/content` | `kind` (`lesson`, `quiz`, `assignment`, `course`, extension kinds from `ohmylms_shared_skill_content_types`, `question`), `search` ≤ 200, `page`. 20 per page with `editable`. |
| `GET /skill-workspace/content/:kind/:id` | `Curriculum\ContentLinks::read`: linked skills with `role` (`linked`; for questions `primary`/`supporting`), `archived`, syllabus names and a 64-character `revision`. 403 without `edit_post`. |
| `PUT /skill-workspace/content/:kind/:id` | `add`, `remove` (unique IDs, 1–50 together, not both), required `expected_revision`. Under a per-content lock: a changed link set answers 409 `ohmylms_content_links_stale` (`data.revision`) and nothing is written; archived skills cannot be added (409). Taxonomy links are appended/removed without touching unseen links. A question gains or loses **supporting** skills in its draft skill map; removing a primary skill answers 409 `ohmylms_content_links_primary` (the question editor owns it). |
| `GET /skill-workspace/placement` | `subject` ≤ 100, `search`, `domain` ≤ 40, `unplaced` (no domain), `page`. Shared skills with `domain`, `grade_from`, `grade_to`, `revision`, plus the subject profile's `domains` and `levels`. |
| `PUT /skill-workspace/placement` | `items` (1–50 objects: `id`, `domain`, `grade_from`, `grade_to`, `expected_revision`). Every placement is checked against its skill's subject profile (400 `ohmylms_shared_invalid`), then every revision under the mapping lock; one stale skill refuses the batch (409 `ohmylms_shared_stale`, `data.skill_ids`) and nothing is stored. |
| `GET /skill-workspace/skills/:id/quizzes` | `Skills\SkillQuizzes`: quizzes connected through `quiz_skills` or with a pool drawing from the skill (`drawn` questions), with style, status, skill count and `editable`; `available` approved question families (`Pools::available`). |
| `PUT /skill-workspace/skills/:id/quizzes/:quiz_id` | Required boolean `linked`. Adds or removes only this skill in the quiz's `quiz_skills`, keeping the other skills in order, under a per-quiz lock. 404 for a non-quiz, 403 without `edit_post`, 409 for an archived skill. |
| `POST /skill-workspace/skills/:id/quizzes` | `title` ≤ 190, `per_skill` 1–10 (default 5), `time_limit` 0–600. Approved, active skills only (409 `ohmylms_skill_not_ready`); fewer approved families than `per_skill` answers 409 `ohmylms_skill_quiz_short`. Builds a **draft** through `Assessment\SkillAssessment::create` (the existing mixed-skill builder) and connects it. 201 with `created`. |
| `GET /skill-workspace/provenance` | `review` (`pending` default, `rejected`, `approved`, `all`), `source` ≤ 40, `search`, `page`. 30 per page, newest first, with provenance (source, ref, model, confidence, creator, reviewer), the last audit entry, `counts` of pending/rejected and the known `sources`. |
| `GET /skill-workspace/provenance/:id` | One skill's provenance, its audit trail (newest first) and the provenance of its mappings. |
| `POST /skill-workspace/provenance/review` | `items` (1–100 objects: `id`, `expected_review`), `review` (`approved`/`rejected`), `note` ≤ 500. A skill whose state is not the one the reviewer saw is left alone and reported (`code: ohmylms_skill_review_stale`). |

### Hardened comparison routes

The legacy-shaped routes the workspace uses now carry typed arguments (MCP tools send the same shapes):

- `GET/POST /syllabus-comparison/ai/runs`, `GET …/ai/runs/:id`, `POST …/step`, `…/cancel`,
  `…/accept-confident` (`min_confidence` 0.5–1, required), `GET …/ai/suggestions` (`run_id`, `id`,
  `course_id`, `status`, `kind`, `min_confidence`, `page`) and `POST …/ai/suggestions/review` (`ids`
  1–200 unique, `action`, `overrides` keyed by suggestion ID with only `relation`, `shared_id`, `name`,
  `description`, `topic`, `domain`, `grade_from`, `grade_to`, `weight`). This route starts `syllabus`,
  `placement` and `links` runs; course runs start from the course structure route.
- Reviews of suggestions run one at a time under a named lock, and rows are read inside it, so two
  reviewers cannot apply the same suggestion twice; a decided suggestion is returned unchanged.
- `POST /syllabus-comparison/mapping-groups` (`shared_id`, `relation`, `note` ≤ 1000, `groups` 1–10 with
  `source_id` and 2–200 `pairs`) and `…/mapping-groups/remove` (`uuid`, `pairs`).
- `GET/PUT /syllabus-comparison/shared-skills/:id/content` (`kind`, `page`, `search`, `search_targets`;
  `content_id`, `linked`).

The AI runs screen uses only these routes: runs call the configured provider through
`Curriculum\AiAnalysis` exactly as before, and the screen makes no other request.

## Provenance audit trail

`Skills\Provenance::review()` now appends to the term meta `_ohmylms_skill_audit` (newest 50 entries:
`action`, `previous`, `note`, `by`, `at`); archiving and restoring a skill are recorded too. Every caller
(`POST /skills/:id/review`, the course editor, MCP) is audited.

## Screens

- **Combined mappings**: library search, then one table of mappings with their source and confidence,
  combined groups (removed only as a whole, with their pair revisions), placements and usage lists.
  Removing asks for confirmation; a stale refusal says so and reloads on close.
- **AI runs**: start a syllabus, placement or links analysis; list runs with progress, failures and
  suggestion counts; process the next slice or stop an active run; review suggestions with status views,
  an editable relation for mappings, accept/reject per row or in bulk and "Accept all at 85% or more"
  behind a confirmation. Per-suggestion failures are listed.
- **Content links**: pick a content type and item, stage added skills (library search covers objectives
  and shared skills) and removals, and save them together; a stale save offers "Reload links and keep my
  changes".
- **Domain placement**: per-row domain and level selects, bulk "Apply to selected", one batch save, a
  disabled save for an invalid band, and "Reload skills and keep my placements" after a stale refusal.
- **Skill quizzes**: owned quizzes with how they belong, connect/disconnect, quiz search, and a draft
  generator that is disabled while the bank cannot supply the draws.
- **Provenance review**: views with counts, source filter, search, bulk approve/reject with an optional
  note, details with the audit trail and mapping provenance.

## Verification

`OHMYLMS_SKILL_WORKSPACE_TEST=1` runs `tests/php/skill-workspace-rest-integration.php` on the Local site
(122 checks): route arguments and permissions, editor/subscriber/anonymous refusal, schema rejections,
combined view, content links (stale, primary, archived), placement batches (stale and invalid refuse the
whole batch), quiz ownership and generation limits, provenance decisions and audit, AI run review over
fixture rows with outbound HTTP blocked (none attempted), and the syllabus spreadsheet command. Fixtures
are tagged and removed in `finally`. App tests: `features/curriculum/workspace.test.ts` and
`SkillWorkspace.test.tsx`.
