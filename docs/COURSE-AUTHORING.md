# Course authoring and skill connections

The course outline remains the authoritative draft for unit order, activity placement and mastery
targets. A navigator, unit editors and contextual skill connections share one React workspace.
Lessons and quizzes continue to use their existing WordPress block editors.

## Author workflow

1. Open Content Hub > Courses, choose a course and open Outline.
2. Add units, rename them and reorder units or activities with drag handles or keyboard-accessible
   move controls. Add existing content or create a draft lesson, quiz or assignment from a unit.
3. Choose Skills beside an activity to search and connect skills or syllabus objectives. Create a
   reusable skill in the same panel when necessary. Save connections explicitly. A newly created
   skill exists independently even before its content connection is saved.
4. Use Learning settings for course/unit mastery targets and completion requirements. Course skill
   connections describe coverage; they do not replace these targets.
5. Use Block editor beside a lesson or quiz to edit its content in a new tab, keeping the outline open. The native lesson, quiz and question
   editors also expose Skills and curriculum, opening the connection workspace in a separate tab.
6. Save the outline draft, then use the existing Preview and publish flow to release changes.

Content Hub > Curriculum displays a nested, expandable listing. Expand all and Collapse all control
the hierarchy. Search reveals matching descendants with their ancestors. Add child prefills the
parent for a new item. The existing revision checks, move validation and deletion rules remain.

## Learning semantics

- Lesson links identify taught skills; finishing a lesson does not establish mastery.
- Quiz links identify intended coverage. Individual question mappings supply assessment evidence.
- A question's primary skill remains editable in the question editor. Supporting connections can
  be edited in the shared workspace; the primary connection cannot be removed there.
- The existing evidence ledger and performance evaluator remain responsible for mastery.
- Connection writes use the existing read revision. Conflicts retain staged edits and offer a
  reload/rebase. Switching selected content or leaving with unsaved connections requires a decision.
- Outline and connection saves have separate persistence contracts. Neither publishes the course.

## Module boundaries

- `course-structure`: navigation, placement editing and connection selection.
- `curriculum`: expandable hierarchy and reusable `ContentSkillConnections`, exposed via `public.ts`.
- `content-hub`: reusable skill creation through its existing API, exposed via `public.ts`.
- `learning/NativeSkillConnections`: block-editor document sidebar navigation, with no parallel
  skill storage or grading rules.
- `app/styles/authoring.css`: scoped responsive layout using shared design tokens. Existing shared
  controls and Badge components supply the visual language.

No public identifiers, assessment schemas or question extension contracts were renamed.

## Research basis

The reference is Khan Academy's public learning organization, not its private authoring software:
instruction is combined with exercises, quizzes, unit tests and course challenges. OhMyLMS adapts
that organization to its existing reusable WordPress content, syllabus mapping and mastery services.

- [Khan Academy: instruction and practice content](https://support.khanacademy.org/hc/en-us/articles/7762947147277-Content-Update-2022-Middle-School-Science-from-Khan-Academy)
- [Khan Academy: progress and mastery movement](https://support.khanacademy.org/hc/en-us/articles/360032987591-How-can-I-understand-my-progress-in-Khan-Academy)
- [WordPress coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)

OhMyLMS retains its existing prerequisite/evidence rules; this UI change does not copy Khan Academy's
prerequisite mastery propagation or introduce a second mastery evaluator.

## Asset budget changes

The authoring layout adds three feature-owned CSS chunks (course structure, curriculum and skill
workspace), each limited to 3,500 raw / 1,100 gzip bytes. The course-structure JS budget changes
from 32,900 / 9,500 to 39,500 / 11,400 bytes to cover navigation and contextual connection selection;
the curriculum listing changes from 12,600 / 4,300 to 14,700 / 4,900 for accessible expandable
branches and contextual child creation. Other budgets remain unchanged. These limits cover the new
UI modules only; architecture rules and lint rules have not been disabled.

## Membership-based course settings

Course settings direct authors to Memberships for plan pricing and included courses. The course editor
no longer displays individual price/sale, point-purchase, public/password access, available-date or
duration controls. Reviews, capacity and certificates remain editable. The course list omits price
columns and filters, and ignores obsolete price-filter bookmarks. Stored course fields and existing
access checks remain intact for compatibility; removing controls is not a data migration.
