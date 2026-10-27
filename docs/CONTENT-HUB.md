# Content Hub

One place to build learning content: **OhMyLMS → Content Hub** (`#/content-hub`) replaces the separate Courses, Assessments, Skills, Curriculum and Course Bundles submenus. Skills are a core part of OhMyLMS, not an add-on.

A **grade, exam or subject** (for example Grade 2 Math, MATH0580 or Digital SAT) is an ordinary course and is listed in the **Courses** tab. It can have chapters, skills inside those chapters, and lessons, quizzes and assessments attached to a chapter or to chosen skills. There is no fixed chapter-then-lesson order: a skill-based course is finished when its required skills reach their targets. A course built from a syllabus is edited in the syllabus editor (see [SYLLABUS.md](SYLLABUS.md)); every other course is opened in its editor from the Courses tab.

## Tabs

| Tab | What it is |
| --- | --- |
| **Courses** | The existing course list, unchanged (create, filter, edit, reports, bulk actions). It is the first tab, so the hub address `#/content-hub` opens it, and it lists every course, including the one each syllabus is. |
| **Lessons** | The lesson library: search, status filter, skills, where each lesson is used, edit, duplicate, trash. |
| **Quizzes** | The existing quiz list, unchanged (create, filter, edit, reports, bulk actions). |
| **Question Bank** | The question bank: search, approve, archive, duplicate, metadata, banks and sharing. Unchanged. |
| **Assignments** | The existing assignment list, unchanged. |
| **Skills** | The shared skill library: hierarchy, codes, prerequisites, linked lessons and courses. |
| **Curriculum** | The curriculum structure (exam boards, national curricula, grades, subjects, syllabuses) that courses, skills, question banks and exams are placed in. Any item can be a syllabus with its own chapters (skill groups) and skills, loaded from CSV. A syllabus's name opens its own full-page editor (`#/content-hub/curriculum/syllabus/ID`), and **a syllabus is also a course**: its chapters and skills are the course's, so it appears in the Courses tab too; see [SYLLABUS.md](SYLLABUS.md) and [CURRICULUM-BUNDLES.md](CURRICULUM-BUNDLES.md). |
| **Course Bundles** | Course Bundles: curated groupings of courses and syllabuses that learners can follow. Unchanged; see [CURRICULUM-BUNDLES.md](CURRICULUM-BUNDLES.md). |

The hub header shows the title followed by the section tabs. Content is added from the individual tabs.

The old addresses keep working: `#/courses` opens the Courses tab; `#/quizzes` and `#/assessments` the Quizzes tab; `#/assessments/question-bank` and `#/extensions/question-bank` the Question Bank tab; `#/assignments` and `#/assessments/assignments` the Assignments tab; `#/extensions/skills` the Skills tab; `#/extensions/curriculum` and `#/categories` the Curriculum tab; and `#/extensions/bundles` and `#/tags` the Course Bundles tab. The Content Hub menu entry stays highlighted on every tab and while you edit or report on a course, lesson, quiz or assignment from the hub.

There is no Catalog tab any more: the Courses tab does its job. `#/content-hub` opens the Courses tab, `#/content-hub/catalog` does too, and `#/content-hub/catalog/ID` opens that course's editor (`#/course-edit/ID`), so links and bookmarks made while the Catalog tab existed still land somewhere useful. Where a lesson or quiz lists the courses it is used in, each course links to its editor.

The Quizzes and Assignments tabs are the application's own screens, so they keep their own "All Quizzes" and "All Assignments" headings below the hub title, as the Courses tab does. The Question Bank, Curriculum and Course Bundles pages lower their own title one level so the hub title is the only page heading. The tabs wrap onto a second line on narrow screens. The Curriculum and Course Bundles tabs are the same pages that used to have their own menu entries, with their own title shown one level down so the hub title is the only page heading.

## Chapters, skills and attachments of a course

A course's skill structure is its chapters, the skills inside them (each with its own **code**, a target and a required flag), and the lessons, quizzes and assignments attached to a chapter or to chosen skills. The Content Hub has no page of its own for it any more; the syllabus editor is where it is edited, and the REST routes below are what it uses. Content attached to several skills belongs to each of them.

- **Skills are shared.** Adding a skill to a chapter references the library skill (pick existing skills or create one on the spot). The same skill can be in many courses, its evidence is shared as before, and removing it from a chapter or course only removes the placement.
- **Codes are the skill's own `code` field** (for example `A.1`); nothing renumbers when you reorder. A skill with no code shows a dash.
- **Attach** works on a chapter (nothing selected) or on chosen skills of that course. A lesson, quiz or assignment is attached to a course once. New lessons, quizzes and assignments can be created from the attach dialog and are attached at once as drafts.
- **Required / optional**, and a pass percentage for quizzes, are per attachment (the existing checkpoint rules). Skills have a target (Proficient or Mastered) and a required flag, as in the Learning tab.
- **Chapters** are the course's existing chapters (units). Adding, renaming and reordering are saved immediately. A chapter that still holds lessons or quizzes in the course editor, or that the published program refers to, cannot be deleted.
- **Learning mode** (skill-based, blended, traditional) is a setting of the course's learning program. A syllabus's course starts skill-based.

### Draft and Publish

Skills, attachments and the learning mode are saved to the course's **learning-program draft**. Learners see nothing until **Publish**, which is the Learning tab's own publish: readiness checks (for example required skills need enough approved questions), a new immutable version, and the choice to move enrolled learners who have not completed the course to the new version. Everything after a publish stays a draft until you publish again. The course itself must also be published for learners to reach it.

## Lessons

Lessons were already reusable inside learning programs. The Lessons tab makes them a library: a lesson created from the hub belongs to no course until you attach it to a chapter, for instance from a syllabus editor. The **Used in** column lists every course that uses the lesson (chapters, published programs and drafts). Editing a lesson changes it everywhere it is used; trashing one that is in use asks for confirmation because it becomes unavailable to learners there.

## Quizzes and assignments in several courses

A quiz or assignment can now be attached to several grades and exams.

- **Resolution.** `ohmylms_get_course_by_content_id()` (and `ohmylms_get_course_id_by_content_id()`) still return the original chapter course when there is one. When a published program also places the content, the course the **current learner is enrolled in** is used (the original course first). Content with a single course behaves exactly as before. The placement index is `OhMyLMS\Learning\Placements`, stored in the `ohmylms_learning_placements` option and rebuilt on publish and when a course changes state.
- **Attempts are shared.** A learner has one attempt allowance and one history per quiz, whichever course it was started from. A checkpoint counts as passed in every course that places the quiz (`count_total_attempt()` ignores the course, and the program's pass lookup no longer filters on `course_id`).
- **Course-only evidence.** With "recognize prior skills" off, quiz evidence counts when the quiz is one of the program's own checkpoints and was taken after the learner was assigned the program.
- **Enrollment.** Opening or taking such a quiz needs enrollment in any course that places it.

## Skills are core

Skills no longer have an Add-ons card or a switch. `Addons::enabled('skills')` is always true, the skills module always loads, and the "Enable the Skills add-on" errors and the dependent-course guard on disabling are gone. A saved `skills` switch from an older version is ignored and not rewritten. Existing skills, links, mappings and evidence are untouched. Skills, like the Question Bank, appear in the manifest of neither.

## REST API (`ohmylms/v1`)

Every route needs `edit_posts`; routes with a course also need `edit_post` on that course.

| Route | Purpose |
| --- | --- |
| `GET content-hub/courses` | Grades, exams and subjects with their counts (`search`, `page`, `per_page`). No screen uses it now that the Catalog tab is gone |
| `GET content-hub/catalog/{id}` | The catalog: chapters, skills (with chapter and target), attachments (with scope), publish state |
| `PUT content-hub/catalog/{id}` | Replace `mode`, `outcomes` and/or `attachments` in the draft. Validated by the program's own rules. Practice steps and learning settings are kept |
| `POST content-hub/catalog/{id}/publish` | Publish the draft (`apply_existing`); errors list what blocks publishing (409) |
| `POST content-hub/catalog/{id}/chapters`, `PUT .../chapters/order`, `PUT/DELETE .../chapters/{chapter_id}` | Add, order, rename and delete chapters |
| `GET content-hub/targets` | Search lessons, quizzes, assignments or skills (`type`, `search`, `course` leaves out what the course has) |
| `GET content-hub/lessons`, `POST content-hub/lessons/trash`, `PUT content-hub/lessons/{id}/skills`, `POST content-hub/lessons/{id}/duplicate` | The lesson library |

Program JSON additions (additive, no table changes): an outcome may carry `chapter_id`; a lesson, quiz or assignment item may carry `skill_ids` (a subset of the program's outcomes). The `outcomes` table still stores only skill, target and required.

## Source

- PHP: `includes/Learning/Catalog.php` (catalog, chapters, draft, lists), `includes/Learning/Placements.php` (placement index and course resolution), `includes/Rest/V1/ContentHubController.php`; program rules in `CourseProgram::normalize()`.
- Admin UI: `assets/src/features/content-hub/` (`hubRoutes.mjs`, `ContentHub.jsx`, `AttachDialog.jsx`, `AttachmentItem.jsx`, `PublishDialog.jsx`, `SkillPicker.jsx`, `LessonsPage.jsx`, `catalogModel.mjs`); the syllabus editor reuses the attach and publish dialogs, `AttachmentItem` and the model. The hub frame and routes are wired in `assets/src/extensions/index.jsx`; pages load lazily from the `content-hub` chunk. The Skills, Question Bank, Curriculum and Course Bundles tabs are SDK admin pages (`HUB_EXTENSION_TABS` in `hubRoutes.mjs`) shown inside the hub frame; they read `HubContext` to lower their heading level. The Courses, Quizzes and Assignments tabs are the application's own list screens (`HUB_APP_ROUTES`), and `HUB_ALIASES` maps the retired `#/assessments*` addresses onto their tabs, and `legacyCatalogTarget` says where the retired `#/content-hub/catalog*` addresses lead. The quiz, assignment, course and lesson editors and reports are listed in `HUB_MENU_ROUTES` so the menu entry stays highlighted. Styles are in `assets/css/admin-ui.css`.

## Validation

```sh
node --test tests/js/content-hub.test.mjs tests/js/catalog-model.test.mjs tests/js/attachment-item.test.mjs
php tests/php/learning-model-unit.php
for mode in off skills bank both; do php tests/php/addons-unit.php $mode; done
```

These are unit and stubbed-render checks (routing and the retired Catalog addresses, menu source, catalog model, the attachment row, placement logic and program rules). They need no WordPress. The REST controllers, the SQL in `Catalog`, the shared-attempt behaviour and the browser flows need the disposable WordPress site described in [DEVELOPMENT.md](DEVELOPMENT.md) (`php tests/php/learning-integration.php`, `curriculum-integration.php`, `placement-integration.php` and the Playwright specs under `tests/browser/`). They were not run for this change; see [ACCEPTANCE.md](ACCEPTANCE.md).

## Known limitations

- Attaching content is add and remove; to change a lesson's scope, remove it and attach it again.
- Chapter names change for enrolled learners as soon as they are renamed (chapters are not versioned); skills and attachments change only on publish.
- A quiz or assignment page opened without a learner context (for example by an administrator) resolves to the original course, or the lowest-numbered course that places it.
- The lesson sidebar and the traditional curriculum view of a course show the course's own chapters, not the skill catalog.
- The learner-facing catalog (skill-practice style grade pages) is not part of this change.
