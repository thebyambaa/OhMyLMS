# Plan: subject- and source-agnostic skills

Status: all four phases are built: subject profiles, prompts from profiles, skill sources with provenance and review, and the course editor panel. See [subject profiles](SUBJECT-PROFILES.md) and [skill sources](SKILL-SOURCES.md). Future courses will get their skills from an AI API, so the skill layer must not assume mathematics, school grades or one way of creating skills.

## Where the code assumes too much today

| Assumption | Where |
| --- | --- |
| Domains are a fixed maths list (number … calculus, mechanics, discrete) | `Curriculum/Comparison::domains()`, used by AI analysis, the skill map, guidance, placement and the comparison API |
| Levels are school grades 1–12 plus 13 ("Beyond grade 12") | `Comparison::MAX_GRADE`, `grade_label()`, the `_ohmylms_shared_grade_from/to` meta, guidance and placement ordering |
| The AI is "an expert mathematics curriculum analyst" and maps onto "shared mathematics skills"; exam level hints (IGCSE 9–10, A level 12–13, Digital SAT) are in the prompt | `Curriculum/AiAnalysisModel::classify_prompt`, `match_prompt`, `links_prompt` |
| AI can only start from a syllabus's objectives, not from a course | `Curriculum/AiAnalysis::KINDS` (`syllabus`, `placement`, `links`) |
| Skills are created in four places with different rules and no record of origin | `Comparison` (shared skills), `Syllabus` (objectives), `Importers/CourseImporter`, `Rest/V1/SkillController` |
| A deleted skill silently breaks courses that use it (course 1233 could not be saved) | `CourseProgram::normalize` rejects unknown terms; nothing prevents the deletion |

Already in place and kept: shared skills with stable IDs and UUIDs, a free-text subject on each shared skill (`_ohmylms_shared_subject`), and an AI connection with interchangeable providers (`AI/Client`: OpenAI, Anthropic, Gemini). There is also a review queue (`ai_suggestions`) in which AI output waits until a person accepts it, and accepted mappings record `source = ai`, the confidence and the AI's reason.

## Principles

- **Additive and compatible.** Existing term IDs, meta keys, REST fields, hooks and mastery evidence keep working. Today's maths behaviour becomes the seeded "Mathematics" profile and produces the same results.
- **Nothing generated is trusted silently.** Every skill records where it came from. AI output becomes a reviewable suggestion, never a direct change to the curriculum or a published course.
- **Identity is stable.** Skills are never re-created to change them. Edits, merges and retirements go through suggestions and mappings, so learner evidence survives.
- **Only curriculum text leaves the site.** Learner data is never sent to any provider.

## Phase 1: subject profiles (subject-agnostic)

- A new `Skills\SubjectProfiles` store (an option with a revision), keyed by the existing subject string. Each profile has:
  - its label and language;
  - its **domains** (id and label);
  - its **level scheme**: ordered levels such as "Grade 1 … Grade 12", "A1 … C2" or "Beginner / Intermediate / Advanced", stored as ordinals so ordering still works;
  - its **AI context**: a subject description, analyst role, level hints and examples.
- Seed **Mathematics** from today's domains and grade scale, including the exam hints that move out of the prompt. Subjects without a profile get a generic one with no domains and a numeric level scheme.
- `Comparison::domains( $subject )` and `grade_label( $grade, $subject )` read the profile. The `ohmylms_skill_domains` filter still runs, now also receiving the subject. Validation is per subject. The stored `grade_from/to` meta keeps its keys and holds level ordinals.
- Callers move to the subject-aware versions: AI analysis, the skill map, guidance, placement, the comparison REST response (additive `levels` and per-subject `domains`) and the React skill screens.
- A **Subjects** settings screen edits profiles, with matching REST and MCP tools (`get_subject_profile`, `save_subject_profile`).

## Phase 2: subject-neutral AI prompts

- The three prompts are built from versioned templates filled from the profile: the analyst role, subject, domain list, level scale, hints and examples. A filter (`ohmylms_skill_ai_prompt`) allows overrides.
- Runs store the `prompt_version` and the subject profile revision, so results can be traced and re-run.
- Tests: the Mathematics profile reproduces today's instructions. A non-maths profile (for example English language with A1–C2 levels) produces valid prompts and parses replies with its own domains.

## Phase 3: one way to create skills, with provenance (source-agnostic)

- **`Skills\SkillWriter::create( $fields, $provenance )`** becomes the single write path for the four existing creators and for accepted AI suggestions. It sets the UUID, code, subject, domain and levels. Before creating, it looks for an existing skill with the same normalized name in the same subject and reuses it if found.
- **Provenance** term meta:
  - `_ohmylms_skill_source`: `manual`, `syllabus`, `import`, `ai` or `provider:<id>`;
  - `_ohmylms_skill_source_ref`: run, suggestion or external ID;
  - `_ohmylms_skill_review`: `approved`, `pending` or `rejected`;
  - `_ohmylms_skill_origin`: provider, model, prompt version, confidence and created time.
- A backfill marks existing skills as approved, with a source inferred from known signals: syllabus objective, created for a roadmap, imported, or an accepted AI suggestion (`ai_suggestions.result_term_id`).
- **Skill sources** behind one interface: a `Skills\SkillSource` contract with `id`, `label`, `available` and `propose( SkillRequest )`, registered through `ohmylms_skill_sources`. Built-in sources:
  - **Syllabus objectives:** the current analysis, unchanged in behaviour.
  - **Course content:** proposes skills for a course, unit or lesson from its titles, descriptions and reading text.
  - **Manual:** a person creating skills directly.
- The review queue is generalized with additive columns (`source`, `subject`, `course_id`, `target`) and new suggestion kinds:
  - `course_skill`: accepting it creates or reuses the skill and links it to the course draft's unit or lesson, with practice.
  - `skill_update`: accepting it applies a proposed rename or rescope to an existing skill, which keeps its identity.
- **Retiring instead of deleting.** A skill used by a course program or by learner evidence is archived (`_ohmylms_skill_status = archived`), not deleted. Editors show "Archived skill" and offer a replacement.

## Phase 4: AI skills in the course editor

- **Suggest skills with AI** on a unit or lesson starts a course-content run. A suggestions panel in the outline shows each proposal with its reason, confidence, matches to existing skills and edit, accept and reject controls.
- Skill rows show a provenance badge ("AI · pending review", "Syllabus", "Manual"). The skill panel shows source, model, confidence and reason.
- Readiness adds a blocker: a course cannot be published while its outline uses skills that are pending review.

## Phase 5: built in throughout for future use

- Versioned prompts and profile revisions recorded with every run and skill.
- Re-running never edits skills in place: changes arrive as `skill_update` suggestions.
- Duplicates are merged through equivalent mappings, so evidence pools and nothing is lost.
- Provider-neutral calls through `AI/Client`. Any future source is added behind `SkillSource`.
- Per-phase tests: pure tests for profile validation, prompt building and reply parsing; integration tests for the writer, provenance, backfill, review and archiving; a browser test for course suggestions.

## Build order

Each phase is its own commit and can be released alone: 1 → 2 → 3 → 4. Phase 5 rules apply throughout. A schema change is needed only in phase 3: additive columns on `ai_suggestions`, applied with the existing schema installer.

## Decisions (October 24, 2026)

1. **AI source:** skills come through the existing AI connection (OpenAI, Anthropic or Gemini). No External skill API source is built. A third party can still register a source through `ohmylms_skill_sources`.
2. **Pending skills:** AI-proposed skills that are pending review may be used in course drafts. Readiness blocks publishing until every skill the outline uses is approved.
3. **Seeded subject profiles.** Each profile's domains and levels are drafted for review and editable in Settings → Subjects:
   - **Generic:** no domains, numeric levels.
   - **Mathematics:** today's domains and grades 1–13.
   - **English and other languages:** reading, writing, listening, speaking, grammar and vocabulary on CEFR levels A1–C2.
   - **Sciences:** physics, chemistry and biology, each with its own domains, on school grades.
   - **Mongolian national curriculum:** its subjects on grades 1–12. Domain lists will be drafted from the official standards and need your confirmation.
   - **International standardized exams** (for example IELTS, TOEFL, Digital SAT, IGCSE, IB, A level): the exam's sections as domains and its score bands or levels as the level scheme. Same rule as Mongolian: lists drafted from the official specifications, for your confirmation.
