# Skills authoring API

The typed Skills screen opens at `admin.php?page=ohmylms-app#/content-hub/skills` (extension route
`/content-hub/skills`). It lists the library, adds skills, archives/restores them, manages lesson
and course links, and edits each skill's details, full learning objective, English/Mongolian
translations, public practice flag and resource counts. Skills remain a core feature.

## Routes the screen uses

All routes are under `ohmylms/v1` and return 503 before the skills schema is installed.

| Route | Permission | Contract |
| --- | --- | --- |
| `GET /skills/catalog` | `edit_posts` | `page` ≥ 1, `per_page` 1–50, `search` ≤ 200, `include` ≤ 50 IDs. Summaries only (no linked posts); `X-WP-Total`/`X-WP-TotalPages`. |
| `POST /skills` | `edit_posts` | `name` 1–200 (required after trimming), `code` ≤ 40, `description` ≤ 10000, `parent` existing skill, `prerequisites` ≤ 100 acyclic IDs, optional `objective` ≤ 2000. 201 with the skill. |
| `GET /authoring/skills/:id` | `edit_posts` | Editor state, `revision`, resource counts and permissions (below). 404 for a missing skill. |
| `PUT /authoring/skills/:id` | `edit_posts`; `manage_options` to change `public_practice` | Revision-checked patch (below). |
| `POST /skills/:id/translate` | `edit_posts` | `locale` `en`/`mn` (required); `source` object with only `name` ≤ 190, `description` ≤ 2000, `objective` ≤ 2000 (required). Returns a provider draft; never saves. 404 for a missing skill, 503 when no AI provider is configured. |
| `GET /skills/:id/relationships/(lessons\|courses)` | `edit_posts` | `page`, `search` ≤ 200, `linked_only`. Only posts the user can edit; authors without `edit_others_posts` see their own. |
| `PUT /skills/:id/relationships/(lessons\|courses)/:post` | `edit_posts` + `edit_post` on the target | Required boolean `linked`. Changes one link without replacing unseen links. |
| `POST /skills/:id/archive`, `/restore` | `manage_options` | 404 for a missing skill. Archiving keeps every course, question and learner record. |

The legacy `PUT /skills/:id` keeps its contract; `objective` (string ≤ 2000) and `translations`
(`en`/`mn` objects with only `name`, `description`, `objective`) are now typed in its schema.

## Revision-checked editor

`GET /authoring/skills/:id` returns:

```json
{
  "skill": { "id": 7, "name": "", "code": "", "description": "", "parent": 0, "prerequisites": [],
             "objective": "", "translations": { "mn": { "name": "", "objective": "" } },
             "public_practice": false, "archived": false, "effective_objective": "" },
  "revision": "<sha256 of the editable state>",
  "resources": { "lessons": 0, "courses": 0, "questions": 0 },
  "permissions": { "public_practice": false }
}
```

`objective` is the stored full objective (empty when none); `effective_objective` is what learners
see (objective, else description, else name). Counts come from direct term relationships and do not
load posts.

`PUT` takes a JSON object of at most 64 KB with the required 64-character `expected_revision` and
any of `name`, `code`, `description`, `parent`, `prerequisites` (unique), `objective`,
`translations`, `public_practice`. Unknown fields, wrong types and oversized text return 400 before
domain validation. Domain validation (blank name, parent cycle or unknown parent, prerequisite
cycle or unknown prerequisite, translation rules) runs on every field before anything is written.
An empty `objective` removes the stored objective so the fallback applies. Translations merge per
language; a translated name or objective cannot be cleared (the service rejects empty values).
An author who changes `public_practice` gets 403 and nothing is written.

Writes run under a per-skill advisory lock. If the stored state no longer matches
`expected_revision`, the response is 409 `ohmylms_skill_stale` and nothing changes. The editor keeps
the author's edits, offers "Reload latest and keep my edits" (fields the author did not touch take
the server value) and requires another explicit save. A busy lock returns 409 `ohmylms_skill_busy`.
The response to a successful write is the new editor state. Only changed fields are sent.

## Deep link

`#/content-hub/skills?add=1` (the legacy "Add skill" address) opens the create dialog on load; the
parameter is then removed from the address so a reload or Back does not reopen it.

## Verification and remaining scope

`OHMYLMS_SKILL_AUTHORING_TEST=1` runs `tests/php/skill-authoring-rest-integration.php` (denied
subscriber/author cases, schema and domain rejections without writes, stale revision, objective
fallback, translation preview validation and legacy schema checks). `OHMYLMS_SKILLS_TEST=1` runs
`tests/php/skills-catalog-rest-integration.php`. App tests cover parsing, patches, stale reload with
kept edits, discard confirmation and the administrator-only practice control.

Skill-owned quizzes, provenance review, domain placement and combined mappings are in the
[skill workspace](skill-workspace.md). Not covered: question-bank question creation from a skill and
the `syllabus.skill.resources` extension slot.
