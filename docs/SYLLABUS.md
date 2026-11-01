# Syllabus storage and compatibility

The authoritative entity model, independent-course intent and current UI discrepancies are in [OhMyLMS Learning Architecture](LEARNING-ARCHITECTURE.md). Use [syllabus authoring](api/syllabus-authoring.md) for commands, permissions, reviewed imports and tests, and [skill workspace](api/skill-workspace.md) for mappings.

Syllabuses use curriculum items, ordered `ohmylms_syllabus_groups` and `ohmylms_syllabus_group_skills` placements. Skills are `ohmylms_skill` terms. Curriculum metadata includes `is_syllabus`, `skill_root_id` and `course_id`; source codes remain term metadata. A library skill can occur once per syllabus. Detaching a skill or deleting its group preserves the skill, its resources and evidence.

Full objective text uses `_ohmylms_skill_objective`, falling back to description and name. It is separate from the short title and supports up to 2000 characters, including additive translation records. Existing CSV title/description columns do not export this separate objective field.

## Mirrored-course compatibility

`SyllabusCourse` maintains the existing syllabus-owned course mode. `_ohmylms_syllabus_item` marks the course and `_ohmylms_syllabus_group` identifies mirrored chapters. The course remains an ordinary course post; its program carries syllabus skill outcomes. Synchronization can update titles, chapters and outcomes, while preserving manually added resources. This is distinct from [independent generation](COURSE-FROM-SYLLABUS.md).

Typed syllabus mutations can synchronize the associated course draft. A separate `course_error` reports a failed sync without pretending the syllabus mutation was undone. Explicit synchronization retries; it does not publish. Deleting a syllabus retains its course and removes the ownership link. Existing mirrored courses require a deliberate migration to become independent.

## Import and validation

CSV review/apply uses bounded rows, source revision checks and the transactional importer. Import matches codes, or names where codes are absent, and never deletes omitted records. Duplicate codes or invalid placements fail validation. Review the full [API contract](api/syllabus-authoring.md) before integration.

Domain checks live in `tests/php/syllabus-unit.php` and `tests/php/syllabus-integration.php`; typed command checks live in `tests/php/syllabus-editor-rest-integration.php`. Follow their explicit database/environment guards. Historical recovered-editor browser tests are not current acceptance evidence.
