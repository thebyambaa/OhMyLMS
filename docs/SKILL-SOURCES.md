# Skill sources, provenance and review

Phase 3 of the [skill-agnostic plan](SKILL-AGNOSTIC-PLAN.md). Skills can come from anywhere: a person, a syllabus, an import, AI analysis of a syllabus, or AI reading a course's own content. Every skill now records where it came from. Anything a source proposes waits for review, and a skill that something still uses is never deleted.

## Provenance

`Skills\Provenance` records an origin on every new skill term through the `created_ohmylms_skill` hook. It is stored as term meta:

| Meta | Meaning |
| --- | --- |
| `_ohmylms_skill_source` | `manual`, `syllabus`, `import`, `ai`, or a registered source id such as `course-content` |
| `_ohmylms_skill_source_ref` | What produced it, for example `syllabus:6`, `root:438` or `run:3/suggestion:71` |
| `_ohmylms_skill_review` | `approved`, `pending` or `rejected` |
| `_ohmylms_skill_origin` | Provider, model, prompt version, confidence, created by/at, reviewed by/at |
| `_ohmylms_skill_status` | `archived`, or absent when active |
| `_ohmylms_skill_audit` | Audit trail of reviews, archiving and restoring: action, previous state, note, user, time (newest 50) |

Creators declare their origin with `Provenance::with( $origin, $callback )`. The syllabus editor (`syllabus`), the course importer (`import`) and accepted AI suggestions (`ai`, or the source's id) do this. A skill created without a declared origin, including by other plugins through `wp_insert_term()`, is recorded as `manual` and approved.

A one-time backfill (option `ohmylms_skill_provenance_backfill`) marks skills created before provenance existed as approved, with an inferred source:

- **ai:** the skill was created by an accepted AI suggestion;
- **syllabus:** a syllabus objective or root;
- **manual:** everything else.

Read provenance with `Provenance::read( $term_id )`. Shared skills include it as `provenance`, and the course structure response includes `skill_meta` (source, review, status, model and confidence) for each outline skill.

## Creating shared skills: `Skills\SkillWriter`

- **`SkillWriter::shared( $fields, $origin )`** creates a shared skill unless one already exists in the same subject whose name means the same. Names are compared by their normalised keyword key, so "Identify the main idea of a text" and "identifying the main ideas of texts" match. A match is reused and returned with `reused: true`. It fires `ohmylms_skill_created`.
- **`SkillWriter::archive( $id )`** and **`restore( $id )`** fire `ohmylms_skill_archived` and `ohmylms_skill_restored`.
- **Archived skills** stay readable everywhere they are used, but are no longer offered as candidates for AI matching. Rejected skills are not offered either.

`DELETE /ohmylms/v1/skills/{id}` now archives instead of deleting when a course outline uses the skill, whether a draft or a published program version. A skill with learner evidence or frozen question mappings is also archived, unless `force` is sent. Only unused skills are deleted. `Skills\SkillUsage::of( $id )` reports what uses a skill.

## Review and publishing

- `POST /ohmylms/v1/skills/{id}/review` with `review` (`approved` or `rejected`). Administrators only.
- The provenance review queue of the [skill workspace](api/skill-workspace.md) lists skills by review
  state and source and decides several at once, each against the state the reviewer saw, with a note
  kept in the audit trail.
- `POST /ohmylms/v1/skills/{id}/archive` and `…/restore`. Administrators only.

Course readiness (`CourseProgram::readiness()` and the readiness report's `completion` list) now blocks publishing while the outline uses a skill that is pending ("Approve the proposed skill … first") or rejected ("Replace the rejected skill …").

## Skill sources

A source proposes skills; it never creates them. It implements `OhMyLMS\Skills\SkillSource`:

```php
interface SkillSource {
	public function id();        // 'my-source': lower-case letters, digits, hyphens.
	public function label();     // Shown to authors.
	public function available(); // Can it run on this site now?
	public function propose( array $request );
}
```

`propose()` receives `course_id`, `unit_id`, `group_id` (a lesson UUID, or empty for the unit's activities outside lessons) and `subject`. It returns:

- **`proposals`:** a list. Each item either reuses a library skill (`existing`, a term ID, with `confidence` and `reason`) or proposes a new one (`existing: 0`, `name`, `description`, `topic`, `domain`, `grade_from`, `grade_to`, `confidence`, `reason`).
- **`meta`:** `provider`, `model` and `prompt`, recorded on any skill created from a proposal.

Register a source with a filter:

```php
add_filter( 'ohmylms_skill_sources', function ( $sources ) {
	$sources[] = new My_Skill_Source();
	return $sources;
} );
```

The built-in **course-content** source (`Skills\CourseContentSource`) reads a lesson's title, its activities' names, the unit description and the text of its readings. It ranks the subject's library skills as candidates and asks the AI connection which skills the lesson teaches. The prompt (`Skills\CourseSkillModel`, version `course-1`) is built from the subject profile, like the analysis prompts. It allows at most eight skills and prefers reusing library skills. Only curriculum text is sent.

## Course skill suggestions

- `POST /ohmylms/v1/courses/{id}/structure/skill-suggestions` with `unit_id`, `group_id`, `subject` and `source` (default `course-content`). It runs a `course` analysis at once and returns the run and its `course_skill` suggestions. It requires permission to edit the course and a configured AI connection.
- `GET …/skill-suggestions?status=pending|accepted|rejected|all` lists the course's suggestions with their `target` (unit and lesson) and the available `sources`.
- `POST …/skill-suggestions/review` with `ids`, `action` (`accept` or `reject`) and `overrides`. Suggestions of other courses are ignored.
  - Accepting a reuse returns the library skill as `result_id`.
  - Accepting a new skill creates it through the skill writer with the source as its origin and review `pending`. It can be linked into the course draft straight away, but publishing waits for approval. Linking it into the outline is the editor's next step (phase 4).

Runs record the prompt version and profile like other analyses. `ai_suggestions` gains the additive columns `source`, `subject`, `course_id` and `target` (curriculum schema 11).

The MCP tools are `list_skill_sources`, `suggest_course_skills`, `list_course_skill_suggestions`, `review_course_skill_suggestions`, `review_skill`, `archive_skill` and `restore_skill`.

## In the course editor

- **Suggest skills with AI:** a selected lesson shows this panel.
  - **Subject:** choose one. The panel defaults to the subject most of the course's skills use.
  - **Saving first:** a lesson the outline hasn't saved yet asks you to save first, because the source reads the saved lesson.
  - **Suggestions:** each shows whether it is a library skill or a new skill, its confidence, its reason and, for a new skill, an editable name.
  - **Add to lesson:** accepts the suggestion and links the skill to the lesson with its practice, in the unsaved outline. **Dismiss** rejects it. **Add all** accepts every suggestion shown.
  - **Pending suggestions** stay with their lesson until reviewed.
- **Badges:** practice and skill rows show "AI · pending review", "Pending review", "Rejected" or "Archived".
- **Origin and review:** the skill panel, and the panel of a practice activity, shows where the skill came from (source, model and confidence) and its review state. Administrators see **Approve skill**, **Reject skill** and **Archive / Restore skill**. These change the skill library at once and do not mark the outline as edited.
- **Readiness:** the Readiness tab lists pending and rejected skills as publishing blockers.

The structure response adds `subject` (the most common subject of the outline's skills) and `can_review_skills`. The suggestions list adds `subjects` for the panel's subject choice.

## Verification

- `php tests/php/course-skill-unit.php`: 12 checks of the course prompt and its reply parsing.
- With `OHMYLMS_STRUCTURE_INTEGRATION=1`, `tests/php/skill-provenance-integration.php`: 27 checks. It uses a stub source, so no AI call is made. It covers:
  - provenance of manual, declared and REST-created skills, and reuse of near copies;
  - archiving and restoring a skill a course draft uses;
  - pending and rejected readiness, administrator review, and deleting an unused skill;
  - the course suggestion run, listing, accepting reuse and new skills, and course isolation;
  - backfill, MCP definitions and anonymous denial.
- Live check on 800.local:
  - A course-content run on a fractions lesson proposed three new skills.
  - Adding one linked it with an "AI · pending review" badge.
  - Saving kept it, and Readiness listed it as a blocker.
  - Archive and restore round-tripped without editing the outline.
  - The panel fits phone width.
