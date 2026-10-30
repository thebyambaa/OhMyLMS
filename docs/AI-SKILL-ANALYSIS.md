# AI skill analysis (phases 1 and 2 of the skill graph)

You enter a syllabus's objectives, then ask AI to analyse them. The AI places every objective in a domain and
grade band, matches it to the shared skills it covers, and proposes a new shared skill where none fits. Every
suggestion waits in a review queue: nothing in the curriculum changes until a person accepts it.

This builds the backbone the rest of the plan relies on. Each syllabus (Grade 1–12, IGCSE, A level, Digital SAT…)
maps onto one library of shared skills. Comparisons between syllabuses, grade progressions, student progress and
recommendations are then read from that one library instead of being authored pair by pair.

## Domain and grade band on shared skills

Every shared skill can carry:

- **domain:** number, algebra, functions, geometry, measurement, trigonometry, vectors, statistics, probability,
  calculus, mechanics or discrete. Extend with the `ohmylms_skill_domains` filter.
- **grade band:** `grade_from`–`grade_to` on the scale 1–12, plus 13 for work beyond grade 12 (for example the
  second year of A level).

Set them in a shared skill's settings (**Domain and grades**), through `shared-skills/save` (`domain`, `grade_from`,
`grade_to`; omitted fields keep their value), or by accepting AI placement suggestions. `shared-skills` filters by
`domain` and by `grade` (skills whose band includes it). `GET /syllabus-comparison/skill-placement` lists the
domains and grades.

## Running an analysis

On a syllabus home, **AI analysis** → **Analyze with AI**. The panel shows the run's progress and, as slices finish,
the suggestions. By default only objectives without a mapping are analysed; **Include objectives already mapped**
analyses all. **Place shared skills in domains and grades** suggests a placement for shared skills that have none.

Each slice of eight objectives takes two AI calls:

1. **Placement.** The domain, grade band and a short English restatement of each objective. The syllabus path and
   its profile's grade or level ("Grade 7", "IGCSE") are context. The estimate is kept on the objective as a hint
   (`_ohmylms_ai_domain`, `_ohmylms_ai_grade_from`, `_ohmylms_ai_grade_to`, `_ohmylms_ai_gloss`).
2. **Matching.** For each objective, a short list of candidates found by keyword ranking (BM25) of the English
   restatement, topped up with shared skills of the same domain at nearby grades. A Mongolian objective therefore
   still finds an English skill. The AI may pick at most two of the objective's own candidates, as `equivalent`,
   `broader`, `narrower` or `related`, with a confidence and a reason. When nothing fits, it proposes a new shared
   skill. Skills proposed earlier in the same run are offered to later objectives, and duplicate proposals are
   merged, so a library can grow from the first syllabus.

Replies that name anything outside an objective's candidates, an unknown relation or domain are discarded. A slice
that fails is retried up to three times, then skipped (counted as failures) so the run can finish. The panel
advances the run while it is open; WP-Cron finishes it otherwise. A run can be stopped.

Prompts are worded from the subject's profile (role, context, level scale and hints, domains); see [subject profiles](SUBJECT-PROFILES.md#ai-prompts). The analysis uses the shared AI connection (Settings → AI tools) with a longer reply budget than learner help.
Only curriculum text is sent: no learner data.

## Review

Suggestions list new skills first, then by confidence. For each one you can edit before accepting: a new skill's
name, domain and grades; a mapping's relationship; a placement's domain and grades.

- **Accept a new skill:** creates the shared skill (or reuses an existing skill of the same name) and maps the
  objective to it as equivalent.
- **Accept a mapping:** saved through the normal guarded mapping save. The mapping records `source = ai`, the
  confidence, and a note with the AI's reason. A mapping to a proposed skill waits until that skill is accepted.
- **Accept a placement:** stores the domain and grades on the shared skill.
- **Accept all at 85% or more:** accepts a run's confident suggestions, new skills first.
- **Reject:** marks the suggestion rejected. Nothing changes.

Only `equivalent` mappings pool assessment evidence, as before.

## Links between shared skills (phase 2)

Shared skills are linked in two directed, loop-free ways (`Curriculum\SkillEdges`, table `skill_edges`):

- **Needs first (prerequisite):** skill A needs skill B before it can be started. The link joins A's prerequisite
  list, which recommendations already read, so loops and unknown skills are refused as before. The edge table keeps
  who suggested it (`manual` or `ai`), the confidence and the reason.
- **Includes (encompasses):** practising skill A also practises skill B, by a **weight** from 0.05 to 1 (the share
  of B a typical problem on A uses). The guidance engine (phase 3, see [performance](PERFORMANCE.md#guidance))
  uses these weights to give partial review credit, so a learner who works advanced skills needs fewer separate
  reviews of the skills inside them.

**Suggest prerequisites and links** in the AI analysis panel starts a `links` run over the subject's placed shared
skills (those with grades), earliest grades first, skipping skills that already have prerequisites unless asked.
For each skill the AI sees a short list of candidates: skills learned no later than it, found by keyword ranking
and topped up with the nearest earlier skills of its domain. It may choose at most three direct prerequisites and
three included skills with weights; anything outside the skill's own candidates is discarded. Suggestions arrive
in the same review queue (kind `link`): **Needs first** or **Includes**, with the weight editable before accepting.
Place skills in domains and grades first: unplaced skills are not linked.

Each shared skill's settings show **Skill links**: what it needs first, what it includes (with the weight) and what
includes it, marked **AI** with the confidence when AI suggested them, each removable.

## API and MCP

Under `/ohmylms/v1/syllabus-comparison` (administrators):

| Route | Purpose |
| --- | --- |
| `GET skill-placement` | Domains, grades, whether AI is connected |
| `GET/POST ai/runs` | List runs (`syllabus_id`, and `subject` to add that subject's library-wide runs), or start one (`kind` syllabus with `syllabus_id`; placement or links with `subject`; `include_mapped`, `include_linked`) |
| `GET ai/runs/{id}`, `POST ai/runs/{id}/step`, `POST ai/runs/{id}/cancel` | Follow, advance, stop |
| `POST ai/runs/{id}/accept-confident` | Accept at or above `min_confidence` (0.5–1) |
| `GET ai/suggestions` | Review queue: `run_id`, `status` (pending, accepted, rejected, all), `kind`, `min_confidence`, `page` |
| `POST ai/suggestions/review` | `ids`, `action` (accept or reject), optional `overrides` per id (for links: `weight`, `shared_id`) |
| `GET skill-links/{id}` | A shared skill's prerequisites, included skills and the skills that include it |
| `POST skill-links` | Add a link: `skill_id`, `other_id`, `type` (prerequisite, or encompasses with `weight`) |
| `POST skill-links/remove` | Remove a link: `skill_id`, `other_id`, `type` |

MCP tools: `get_skill_placement_options`, `start_syllabus_analysis`, `advance_syllabus_analysis`,
`list_skill_suggestions`, `review_skill_suggestions`, `get_skill_links`, `save_skill_link`.

Storage: curriculum schema 8 adds `ai_analysis_runs`, `ai_suggestions`, and `source` and `confidence` on
`skill_mappings`; schema 9 adds `skill_edges`.

## Next phases

2. Prerequisite and "encompasses" links between shared skills, AI-proposed through the same review queue. Done.
3. Guidance on the performance engine: strength by area, next steps (help, reviews, skills ready to learn),
   reviews covered by work on skills that include them, and readiness per syllabus. Done; see
   [performance](PERFORMANCE.md#guidance).
4. Visual coherence map (domains × grades), syllabus comparison view, student skill map. Done; see
   [skill map](SKILL-MAP.md).
5. Adaptive placement test. Done; see [placement test](PLACEMENT-TEST.md).

## Tests

`php tests/php/ai-analysis-unit.php` and
`OHMYLMS_TEST_CREDENTIALS=... php tests/php/ai-analysis-integration.php` (a fake provider; the isolated site
blocks real network calls).
