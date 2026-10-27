# Shared syllabus structure

Every syllabus uses the same outline: syllabus → topic → skill group → skill. Nested topics remain supported. Existing group, topic and skill IDs, categories, placements, course chapters and learning-resource connections are preserved. Groups such as Core/Extended and SAT A1/A2 are visible and editable instead of flattened into the topic. Topic pages provide an Add skill group action; group pages add and organize their own skills.

The main skill editor shows the complete **Learning objective**. Its read order is the explicit `_ohmylms_skill_objective` term metadata, then the legacy definition (`description`), then the canonical short title (`name`). Existing definitions therefore appear in the new layout without rewriting the imported records. Editing the objective saves its own bounded, sanitized text of up to 2000 characters. The short title retains its existing 190-character contract, and old notes remain editable under “Notes and original definition.”

English and Mongolian translation records may additionally contain `objective` (2000 characters). Older name/description translations still work. The automatic-translation draft includes the short title, full objective and notes separately. No new tables, duplicate skills or assessment snapshot migrations are introduced.

The `Objectives.php` filename follows the existing PSR-4 class/file mapping. Its two WordPressCS filename findings are a narrow compatibility exception, reported without suppression.

The legacy CSV columns remain unchanged: they contain canonical short titles and descriptions. The explicit objective field is additive REST/term metadata; the current CSV exporter does not include it.

Verification: 46 relevant JavaScript tests and 35 PHP checks passed. Browser checks covered IGCSE Core/Extended groups, SAT A1/A2/A3 groups, group editing, keyboard navigation, and save/reopen of a full objective on existing skill ID 6 with its title and legacy definition unchanged. SDK assets were rebuilt and synchronized. The full repository check stops at six pre-existing formatting failures; older curriculum modules retain existing lint debt. The new PHP class reports only its documented filename exception. Local PHP retains its missing Imagick DLL startup warning, and webpack reports its existing two bundle-size warnings.
