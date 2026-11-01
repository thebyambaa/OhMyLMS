# Shared-skill API and compatibility

The authoritative relationships, roadmap intent and evidence semantics are in [Learning Architecture](LEARNING-ARCHITECTURE.md). The recovered canvas workflow is retired; use the typed [skill workspace contract](api/skill-workspace.md) for implementation details.

## REST and MCP

The additive administrator API lives under `/ohmylms/v1/syllabus-comparison`: `syllabuses`, `objectives/{id}`, `shared-skills`, `shared-skills/{id}`, `shared-skills/save`, `shared-skills/{id}/content`, `mapping`, `mapping/remove`, `roadmaps`, `configuration`, and `audit/{student_id}`. `/me/subject-skills` and `/syllabus-comparison/audit` return only the authenticated account's plans. WordPress REST cookie authentication requires its standard nonce. MCP bearer authentication retains the issuing administrator's capability checks and dispatches through the same REST callbacks.

The MCP add-on adds thirteen tools: `list_syllabuses`, `list_syllabus_objectives`, `list_shared_skills`, `get_shared_skill`, `save_shared_skill`, `save_skill_mapping`, `remove_skill_mapping`, `configure_subject_syllabuses`, `list_shared_skill_content`, `connect_shared_skill_content`, `create_shared_skill_roadmap`, `audit_my_study_plans`, and `audit_student_study_plans`. Native Abilities schemas reject unknown arguments. Tools have accurate read/write, destructive and idempotent annotations. The original nine content-read tools retain their names and behavior.

Shared-skill edits require `expected_revision`. Mapping creation requires an empty revision; edits/removal require the current revision from an objective page. Revisions cover content rather than second-resolution timestamps. Stale edits return HTTP 409. Mapping batches save one pair at a time and retain failed/unsaved selections; successful pairs remain saved. Reload proposal revisions and review before retrying a conflict.

Trusted extensions may add content types through `ohmylms_shared_skill_content_types`, a map of resource kind to registered post type. Content writes require edit permission on the specified post and append/remove only this skill's term relationship. Published learner resource links contain titles and URLs, never draft content or quiz answers; destination access rules remain in force. Resource lists are bounded to 20 per kind/page.

## Compatibility and limits

No existing public route, hook, assessment field, question extension or taxonomy identity was renamed. Existing mapping callers may omit the new expected revision; the new comparison and MCP flows always supply it. New class files retain namespace-matching case for the repository's existing PSR-4 Composer mapping; only the filename sniff is narrowly excepted at the opening line. All other coding checks remain active. MySQL advisory lock calls are necessarily uncached; bounded taxonomy/meta queries still receive performance warnings from WPCS.

Structural splitting/merging, automatic mapping, automatic exam-credit transfer, readiness scores and personalized per-student plan authoring remain outside this release. Profile labels currently define administrator-selected source sets; audits are read-only. Session drafts last for the browser session. Publishing remains an explicit syllabus/course action; configuring a profile does not publish or enroll it.

## Verification

Use the comparison and skill-workspace integration suites and current typed app tests. Historical canvas browser checks do not establish current UI availability.
