# Subject profiles

Skills are no longer tied to mathematics. Each subject has a **profile** that defines how its skills are organised:

- **Domains:** the areas the subject is divided into, such as Algebra, or Reading and Writing.
- **Levels:** an ordered scale, lowest first, such as Grade 1 … Grade 12, CEFR A1 … C2, or an exam's bands.
- **AI context:** the analyst role, a subject description and level hints, used when AI analysis places and proposes skills.

## AI prompts

All three AI analysis prompts are built from the subject's profile: placing objectives, matching them to shared skills, and suggesting prerequisites and encompassed skills.

- **Analyst:** the profile's role and subject context replace the former fixed "mathematics curriculum analyst" wording.
- **Levels:** the level scale is spelled out with every number and its name, for example "1 = A1; … 6 = C2". Replies are clamped to that scale.
- **Hints:** level hints are added, for example how IGCSE or IELTS bands map onto levels. The Mathematics profile holds the exam hints that used to be in the prompt.
- **Domains:** the allowed domain ids are listed with their names where these differ. A profile without domains asks for an empty domain.
- **Fixed wording:** fences around the data, strictness and the JSON reply shapes are unchanged.

Each run records the prompt template version (`prompt`, now `2`), the profile key (`profile`) and a fingerprint of the profile fields used (`profile_rev`). These are the additive columns of `ohmylms_ai_analysis_runs` (curriculum schema 10), returned with every run, so a suggestion can be traced to the prompt and profile that produced it. A run reads the subject's current profile at each step; the fingerprint is the one it started with.

Open **Content Hub → Curriculum → Subject profiles** (`#/content-hub/curriculum/subjects`).

## How a skill finds its profile

A shared skill carries a subject (`_ohmylms_shared_subject`, for example "Mathematics"). The profile key is that subject in lower case with words joined by hyphens. Any script is kept, so "English language" becomes `english-language` and "Математик" becomes `математик`. A subject without its own profile uses the **default profile**.

Two profiles are built in:

- **Generic:** no domains, levels "Level 1" … "Level 10".
- **Mathematics:** the twelve domains and the grade scale (1–12, then 13 for beyond grade 12) the plugin used before profiles existed, so existing placements read exactly as before. Its AI context holds the exam level hints that used to be inside the prompt.

The default profile is the saved choice. Without a saved choice, it is Mathematics when the site's shared skills include Mathematics, otherwise Generic.

## Editing

- **Adding a profile** creates it from its subject name.
- **Editing a built-in profile** stores a copy. **Restore built-in values** removes the copy.
- **Deleting a profile** asks for confirmation. Skills keep their stored domain ids and level positions.
- **Export** copies a profile as JSON. **Import** pastes JSON into the form; nothing is saved until **Save profile**. Into a new profile the JSON brings its subject name. Into an existing profile it replaces the domains, levels and AI context but keeps that profile's name and key, so the skills using it are unaffected.
- **Domain ids** are stored on skills (`_ohmylms_shared_domain`). Keep an id once skills use it. For names without Latin letters, enter the id yourself.
- **Levels** are stored on skills by position (`_ohmylms_shared_grade_from/to` hold ordinals). Add new levels at the end, or keep positions when renaming.

Limits: 60 domains, 30 levels, 2,000 characters per AI field, 100 stored profiles. Saves are guarded by a revision; a stale save returns 409.

## Where profiles apply

Domain lists, level names and placement validation are per subject in:

- shared skill settings;
- AI analysis: the placement it suggests and the review of its suggestions;
- the skill map and syllabus comparison;
- learner guidance;
- the placement test, including its level picker ("Which level are you at?").

## API and tools

All routes require administrator access and send the current `expected_revision` when writing.

- `GET /ohmylms/v1/syllabus-comparison/subject-profiles`: `profiles` (with `key`, `builtin`, `customized`), `default`, `subjects` (subjects in use with skill counts and resolved profile), `limits` and `revision`.
- `POST …/subject-profiles/save`: `key` (optional, for an existing profile), `label`, `language`, `domains` (`id`, `label`), `levels` (labels in order) and `ai` (`role`, `context`, `level_hints`).
- `POST …/subject-profiles/remove` and `POST …/subject-profiles/default` both take `key`.
- `GET …/skill-placement?subject=…` now answers per subject and adds `levels` and `profile`. `grades` keeps its name and holds the same levels for existing clients.
- MCP tools: `list_subject_profiles`, `save_subject_profile`, `remove_subject_profile` and `set_default_subject_profile`. `get_skill_placement_options` accepts `subject`.

`Comparison::domains()`, `grade_label()` and `placement()` take an optional subject. The `ohmylms_skill_domains` filter receives the subject as a second argument. `Comparison::MAX_GRADE` remains, now documented as the built-in Mathematics scale; use `Comparison::max_level( $subject )` instead.

Profiles are site settings, not plugin code. Profiles for languages, sciences, the Mongolian curriculum and international exams are meant to be imported and reviewed per site.

## Verification

- `php tests/php/subject-profiles-unit.php`: 22 pure checks covering keys, validation, ordinals and labels.
- With `OHMYLMS_STRUCTURE_INTEGRATION=1`, `tests/php/subject-profiles-integration.php`: 22 checks. They cover the built-in defaults, unchanged Mathematics behaviour, per-subject domains, levels and placement, guarded saves, the default profile, customising and restoring a built-in profile, MCP tool definitions and anonymous denial. The stored option is restored afterwards.
- `node --test tests/js/subject-profiles.test.mjs` plus the level-aware cases in `ai-analysis.test.mjs` and `skill-map.test.mjs`.
