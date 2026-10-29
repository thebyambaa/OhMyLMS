# Course levels

Course Settings → Basics shows read-only level information instead of the manual
All levels / Beginner / Experience / Expert selector.

The organization REST response includes `level_summary`:

- `curriculum`: names of directly assigned curriculum grades/levels and their ancestors.
- `skills`: level ranges for saved course outcomes, grouped by subject and profile scale.
- `unplaced_skills`: number of skills without a valid band on their subject's scale.

Skill ranges use the lowest starting level and highest ending level among that subject's
skills, with labels from its subject profile. Different subjects' ordinals are never
compared. A single starting level without an end means that same level. Missing,
reversed or out-of-range bands are not guessed. No available data displays “Not yet
determined.” Assigning a course to a syllabus does not imply every level beneath it.

This describes course content, not a learner's mastery, enrollment requirements or
an access restriction. The editor refreshes the summary when Basics is opened again.

For compatibility, the established `level` REST field and stored legacy values remain
available to existing consumers. Basics does not write that field. The new derived
summary is additive and does not replace those values with incompatible grade labels.

Verification: `php tests/php/course-levels-unit.php`, course/organization JS tests,
WordPress PHP/JS standards, source contracts, build and browser verification.
