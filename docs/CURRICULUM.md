# Curriculum

**OhMyLMS > Curriculum** (`admin.php?page=ohmylms-curriculum`) is one place for the curriculum and its skills. It has two tabs.

## Curriculum (the workspace)

The first tab (`tab=workspace`, app route `/curriculum`) is a single screen:

- **Left: the tree.** Curricula (syllabuses, subjects, roadmaps), their topics and groups, with what each holds ("24 skills · 2 courses"). Search keeps the matches and their parents on screen. The top level starts open, deeper levels start closed, and the open item's parents open by themselves. **Add** starts a new top-level item.
- **Right: the open item.** Where it sits (a breadcrumb), its kind, code and version, and **Add inside**, **Edit** and **Delete**. Three panes show what it holds:
  - **Skills**: the skills of the item and everything under it. **Add skill** works on any item: the first one quietly makes the item a skill holder and gives it a plain "Skills" group, so there is nothing to set up. **Add group** is optional, for sorting skills into named sets. A skill can be taken out of its group (the skill itself is kept). **Import and more** opens the syllabus editor (CSV import, requirements, mapping, comparison, course generation).
  - **Courses**: the courses placed on the item. A published course placed on a subject, syllabus or unit appears in the student library (`/dashboard/?hub_section=library`). Search for a course and **Add** it, or **Remove** it (the course is kept).
  - **Question banks and quizzes**: the banks, quizzes and exams linked to the item, added and removed the same way.

The open item and pane are in the address (`&item=ID&pane=skills|courses|content`), so a link opens the same place.

The workspace needs `manage_options`, because its endpoints are the curriculum administrator's. It uses the existing routes: `GET /curriculum/tree`, `POST|PUT|DELETE /curriculum/items`, `GET /curriculum/items/{id}` (the links), `POST|DELETE /curriculum/items/{id}/links`, `GET /curriculum/link-targets`, and `GET|POST /authoring/syllabuses/{id}[/commands]` for groups and skills.

## Tools

The second tab (`tab=tools`, `edit_posts`) holds the specialist screens, each a section:

Skill library, Skill map, Assessments, AI runs (the four everyday sections, listed first), then Combined mappings, Domain placement, Provenance review, and Subject profiles. Course bundles and Categories and tags moved to Content Hub › Courses. Skill map is also open to school staff, read only. Sections that need the curriculum administrator are hidden from other users.

## Moved from the Content Hub

The Content Hub's Skills and Curriculum tabs moved here. Their old addresses redirect, keeping every other parameter (`AdminNavigation::moved_query()`):

| Old | New |
| --- | --- |
| `page=ohmylms-content&tab=skills[&section=…]` | `page=ohmylms-curriculum&tab=tools[&section=…]` |
| `page=ohmylms-content&tab=curriculum` | `page=ohmylms-curriculum&tab=workspace` |
| `…&tab=curriculum&view=syllabus&id=N` | `…ohmylms-curriculum&tab=workspace&view=syllabus&id=N` |
| `…&tab=curriculum&section=skill-map\|subjects`, `…&view=compare` | `…ohmylms-curriculum&tab=tools&section=…` |
| `…&tab=curriculum&section=bundles\|taxonomies` | `…ohmylms-content&tab=courses&section=…` |

App routes keep their paths (`/content-hub/skills`, `/content-hub/curriculum/…`); only the page and tab they open changed. The legacy hash routes `#/categories`, `#/tags` and `#/extensions/curriculum` open the workspace.
