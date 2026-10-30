# Syllabuses: topics, chapters, skills, and the course a syllabus is

This page describes the established workspace, including its mind-map/spreadsheet surfaces.
The independent TypeScript preview's current scope and validation are documented in
[syllabus authoring](api/syllabus-authoring.md); full workspace parity is still in progress.

Any item in the **Curriculum** tab of the [Content Hub](CONTENT-HUB.md) can be a **syllabus**: Cambridge → IGCSE → Mathematics 0580, or a national grade 11 programme. A syllabus has its **topics**, its **chapters** (skill groups) and its **skills**, it is edited in its own full-page editor, the whole thing can be loaded from a CSV file, and **a syllabus is also a course**. Syllabus names, codes and examples in this page and in the tests are made up to show shapes; they are not syllabus definitions.

## How a syllabus is laid out

```
Cambridge                       framework   ┐
└─ IGCSE                        qualification│ ordinary curriculum items
   └─ Mathematics 0580          subject      ┘  ← switched on as a syllabus (and also a course)
      ├─ 1 Number               topic        ┐ topics: ordinary items beneath the syllabus (optional)
      │  ├─ C1.1 Types of number              ┐ chapters: skill groups (named, coded, ordered)
      │  │   ├─ C1.1.1 Identify natural numbers   ┐ skills (library skills, ordered)
      │  │   └─ C1.1.2 Identify prime numbers     ┘
      │  └─ C1.2 Sets
      └─ 2 Algebra and graphs
```

- **A syllabus is any item.** Open an item with **Edit** and tick **This item is a syllabus**. It saves at once and keeps the item's own type (a "subject" stays a "subject"); it gets a **Syllabus** badge, **its name becomes a link to the syllabus editor**, and the panel offers **Open the syllabus editor**. Items typed "syllabus" before this existed were switched on automatically. A syllabus row in the tree has no **Add child** button: its topics are added in the editor.
- **Topics** are the items beneath the syllabus (a unit, a strand, a paper). A topic is an ordinary curriculum item, so it can be moved and reordered, linked to courses and put in Course Bundles. They are optional: chapters can sit directly under the syllabus. A CSV import creates them too.
- **Chapters** are the skill groups. A chapter has a name, an optional code and optional notes, and is ordered among the chapters beside it. It sits under the syllabus or under a topic. A chapter given only a code is named by its code. **A chapter is also a chapter of the syllabus's course** (see below).
- **Skills** are placed in chapters, in order. A skill has a name (the learning objective), an optional **code** and optional notes or examples.

The two references this was designed from fit this shape: a Cambridge IGCSE syllabus is topic → coded section (C1.1) → objectives, and the Mongolian grade 11 skill list is chapter (11.1) → skill group (М11.1А) → atomic skill (М11.1А1).

## The syllabus editor

### Spreadsheet view and row actions

The header offers **Outline**, **Mind map**, and **Spreadsheet** views of the same
syllabus. Spreadsheet fills the workspace with the existing №, Unit, Chapter and
Topic columns, retaining the original folder, group and skill placement IDs.
It opens in the page, without a modal or a second syllabus.

Edit cells, add draft rows or paste rows, then **Save changes**. **Discard changes**
restores the last saved values. Save or discard before switching views or running
structural row actions. A rejected save keeps the draft available for correction.

Each saved row has an **Actions** menu: open its details and resources beside the
sheet, add a skill in its chapter, add a folder in its unit, move up/down, or open
its existing folder-move controls. A skill row also offers **Remove skill from this
chapter**, which removes only that placement and preserves the library skill and
other placements. **Close details** returns the spreadsheet to full width. Structural
actions use the workspace's existing save operations and refresh the sheet from the
returned outline. Unsaved new rows have a local remove action instead.

`tests/browser/syllabus-spreadsheet.spec.cjs` covers the actual view switcher, sheet
and row-action components with fixture controls and persistence. It checks shared
skill edits, failed-save recovery, draft guards, branch-aware additions, removal of
one placement, details beside the sheet, and mobile scrolling.

### Mind map

Open a syllabus and choose **Mind map** in its header. The map lays out the existing
folders, skill groups and skill placements from left to right. Select a card to edit
its details, learning resources and assessment connections in the existing right pane.
**Outline view** returns to the standard outline with the same selection.

- Use **+ Folder** or **+ Skill** on a card to add inside that branch. Changes use the
  existing authenticated syllabus actions; failed saves retain the entered text.
- Search by displayed name or code. Matching paths open temporarily, including branches
  that were collapsed. Clear search to restore the collapsed state.
- Expand or collapse individual branches, use **Expand all** or **Collapse all**, and
  adjust zoom. Selecting a node or searching brings it into the scrollable viewport.
- Drag blank canvas with a mouse or a finger to pan; a middle-button drag also pans.
  Normal wheel/two-finger scrolling moves the canvas. Ctrl/Cmd + wheel and trackpad
  pinch zoom around the pointer; toolbar zoom preserves the viewport center. With
  the canvas focused, `+`, `-` and `0` zoom in, zoom out and reset the scale.
  **Fit map** shows the visible branches; **Find selected** clears search, opens the
  path to the selection and brings it back into view. Zooming no longer recenters
  the map on the selected node.
- Drop a folder onto the middle of another folder to nest it; drop near the top or
  bottom to reorder compatible siblings. Skills can be reordered against other skills
  within the same topic. Drag from the labeled **Move** handle, using either a mouse
  or touch; highlights indicate whether a drop goes inside, before or after a node.
  The viewport scrolls when the dragged pointer approaches an edge. Click the handle
  or use **Move selected** to choose a placement and destination without dragging.
  Failed moves keep the picker open. Existing editor move controls provide additional
  destinations. Every move uses the existing validator, which rejects cycles.
- On narrow screens, the details editor stacks below the map. Canvas scrolling stays
  inside its own region. Map position, zoom, search and collapsed branches are local UI
  state; the map does not add another syllabus structure or change learner evidence.

Verification: `node --test tests/js/syllabus-map.test.mjs tests/js/workspace.test.mjs`
and `playwright test tests/browser/syllabus-mindmap.spec.cjs`. The browser suite runs
the authored map and QuickAdd components with local WordPress React scripts and a
workspace fixture recording writes; database persistence still requires the existing
isolated WordPress syllabus integration suite.

### Outline and detail editor

Click a syllabus's **name** in the Curriculum tab (or **Open the syllabus editor** in its panel) to open it at `#/content-hub/curriculum/syllabus/{id}`. It is a full page like the course builder, with **Back** and **Done** on top, and it keeps the Content Hub menu entry highlighted.

- **Left panel**: **Add Chapter**, the number of chapters, and the whole syllabus as a nested outline: the syllabus, its topics, the chapters in them, and the skills in each chapter. Choosing a node opens it. **Open all** and **Close all** help with a long syllabus, and the panel can be folded away. **Add Chapter** puts the new chapter into the topic the selection is in (or into the syllabus) and opens it.
- **Right pane**: whatever is selected.

| You select | The pane shows |
| --- | --- |
| The syllabus | Its name, code, version and description; **the course card**; and its content: chapters and topics, with **Add Content** (Chapter, Topic) |
| A topic | Its name, code and description; the chapters and sub-topics in it; **Add Content** |
| A chapter | Its name, code, notes and which topic it sits under; its skills with **Add Content** (**Skill**, **Skill from the library**, **Lesson, quiz or assignment**); and the lessons, quizzes and assignments attached to the whole chapter |
| A skill | Its learning objective, code, notes and chapter; **In the course** (required or not, and the target); and **what the skill owns**: its lessons and its questions |

| You want to | Do this |
| --- | --- |
| Add chapters or skills quickly | **Add Content → Skill** (or **Chapter**) opens a row that stays open: type the code, Tab, type the name, **Enter**. It adds, empties itself and returns to the code field for the next one |
| Rename or add notes | Type in the title, code or notes and leave the field. It **saves as you leave it** (Enter on a single line; Escape puts the saved text back) and says "Saved" |
| Reorder, move or remove | The **⋯** menu on a row or on the pane's title (Move up, Move down, Delete…, Take out of this chapter), and the **Sits under** / **Chapter** selects to move something elsewhere |
| Attach a lesson, quiz or assignment to a chapter | **Add Content → Lesson, quiz or assignment** on the chapter. Existing content is a reference; new content can be made right in the dialog as a draft |
| Back up or edit in a spreadsheet | The **⋯** menu in the header: **Export CSV** (the layout the importer reads) and **Download CSV template** |
| Import | **Import CSV** in the header |

Deleting a topic asks what happens to the topics and chapters under it. Deleting a chapter asks first and keeps its skills in the library. Taking a skill out of a chapter never deletes the skill. A syllabus that still has chapters cannot be switched back into an ordinary item; remove its chapters first.

Every change is saved on the server first and the screen follows what the server answers, so what you see is what is stored. A change that is refused (a skill code already used in the syllabus, say) shows the reason and changes nothing.

## A syllabus is also a course

Every syllabus has one real course (an ordinary course post), so it is listed in the Content Hub **Courses** tab and can be published, priced, enrolled in and put in a Course Bundle like any other course. The syllabus owns the structure and the course mirrors it, one way:

| Syllabus | Course |
| --- | --- |
| The syllabus's name | The course title |
| A chapter (skill group), in outline order | A chapter of the course, titled "C1.1 · Types of number" (code and name) |
| A skill in a chapter | A skill (outcome) of the course's learning-program **draft**, in that chapter, in the same order |

- **When the course is made.** When an item is switched on as a syllabus, when an item typed "syllabus" is created, and, for a syllabus that existed before this, the first time its editor is opened. **Update course from syllabus** (the course card, and the header menu) makes it match again. A course is also placed under its syllabus in the curriculum, like any course linked there.
- **Only the draft is written.** Learners see nothing until the course is **published** (the course card's **Publish course** is the course's own publish, with its readiness checks and the choice to move enrolled learners). The course itself must also be published for learners to reach it.
- **What the course keeps for itself.** A chapter mirrors a chapter of the syllabus when it carries that chapter's uuid, and only such chapters, and the skills in them, are managed. A chapter, a skill, an attachment, the learning mode, a skill's target and its "required" flag set on the course itself are left alone. A skill new to the course starts at **Proficient** and **not required**.
- **Choosing what the course requires.** A skill-based course will not publish until at least one skill is **required**, and a required skill needs approved questions (the course's own readiness checks, listed in the publish dialog). So the editor lets you decide as skills become ready: a skill's **In the course** block (**Learners must reach this skill to finish the course**, and its **Target**, Proficient or Mastered), and the **✓** menu above a chapter's skills (**Require every skill in this chapter** / **Make every skill in this chapter optional**). Required skills are tagged on the chapter's rows, counted ("3 of 5 required for the course") and totalled on the course card. Only a syllabus skill's requirement and target change; the skills and chapters stay as the syllabus has them.
- **Lessons, quizzes and assignments** are attached to a chapter from the chapter's pane. They are the course's own attachments, so the course shows the same thing.
- **A course may hold every skill of its syllabus** (up to 5,000). An ordinary course's learning program still lists at most 200 skills.
- **Deleting the course** (outside the editor) means the syllabus is given a new one the next time it asks. **Deleting the syllabus** keeps the course and drops the link.
- If the course cannot follow a change, the change to the syllabus still stands and the editor says so; **Update course from syllabus** tries again.

## Skills own their lessons and questions

Lessons open directly in the native WordPress block editor. Its inserter adds headings, paragraphs, lists, images and other lesson content. The **Lesson** sidebar holds LMS preview access, media, drip settings, resources and inline question checks; **Block** holds the selected block's settings. WordPress saves block content and lesson settings together. **Back to Content Hub** returns to the selected syllabus chapter or skill. The old embedded editor and its custom fullscreen layout have been removed.

A skill is a library skill, so what teaches and tests it belongs to the **skill**, not to one syllabus or course. In a skill's pane, **What this skill owns** shows:

- **Lessons**: the lessons tagged to the skill. **Add lesson** finds an existing lesson, or enter a **New lesson title** and choose **Create and add** to create a text draft linked directly to this skill and open its lesson editor. **Back** returns to the same syllabus skill. If linking fails, **Retry create and add** reuses the draft. **Remove** takes a lesson off the skill. Editing a lesson changes it everywhere it is used.
- **Questions**: the questions mapped to the skill in the question bank (written there, not in the editor), with their status.
- **Games, soon**: the pane renders the extension slot `syllabus.skill.resources` (context: `skill`, `syllabus`). A module that adds practice games registers a slot (`registerSlot`, see [EXTENSIONS.md](EXTENSIONS.md)) and its section appears under the questions, with nothing else to change.

## Skills are real library skills

Skills created for a syllabus are ordinary skills in the skill library (Content Hub → **Skills**), so questions, lessons, practice and learner evidence work on them like any other skill.

- Each syllabus has one **root skill** in the library, named after the syllabus, created the first time a skill is added. Its skills are the root's children, so the library tree reads Syllabus → skills, and the same objective text in two syllabuses is two separate skills (skills are never merged by name; use [skill mappings](CURRICULUM-BUNDLES.md) to relate them).
- **Names may repeat inside a syllabus** (the same objective in a Core and an Extended section); the **code** tells them apart. **Codes are unique within a syllabus**, ignoring case.
- A skill's name, code and notes are edited in the syllabus; the library shows the same values.
- **Removing a skill from a chapter, or deleting a chapter, never deletes the skill.** It stays in the library with its questions, lessons and evidence. Delete it there if you want it gone.
- An existing library skill can be placed in a chapter (**Add Content → Skill from the library**). It stays one skill; it can be in one chapter per syllabus.

## Importing from CSV

**Import CSV** (in the editor's header) opens a four-step dialog. Nothing is saved until the review is clean.

1. **Choose the file.** UTF-8, comma, semicolon or tab separated (Mongolian and other scripts are fine; save a spreadsheet as "CSV UTF-8"). Up to 5 MB and 5,000 rows per file; split a bigger syllabus into parts.
2. **Tell us what each column holds.** Columns are recognised from their titles ("Skill ID", "Ref", "Atomic Learning Objective", "Chapter", "Topic", "Section code", "Notes and examples" and similar) and you can change any of them. A **Skill** column is required; everything else is optional. Two options help with spreadsheets: *the first row holds column titles*, and *an empty content or group cell repeats the one above it* (for merged cells; only on rows that have a skill, and a group only continues within the same content).
3. **Review.** The file is checked on the server as a dry run: how many contents, skill groups and skills would be **new**, **updated**, **moved** or **unchanged**, plus every problem with its row number. If any row has a problem nothing is imported.
4. **Import.** Applied in one transaction: it either all happens or none of it. The course then follows: a chapter for every skill group and a skill for every skill.

### The columns

| Column | Meaning |
| --- | --- |
| Content code, Content | A topic. A path such as `Paper 1 > 1 Number` nests (the code belongs to the last level). Empty = directly under the syllabus. |
| Skill group code, Skill group name | A chapter. A code with no name names the group. Empty (on a row with a skill) = a chapter called "Skills" under that topic, with a warning. |
| Skill code, Skill | The learning objective and its code. A code with no skill name is an error. |
| Notes | Notes or examples for the skill. |

```csv
content_code,content,group_code,group,skill_code,skill,description
1,Number,C1.1,Types of number,C1.1.1,Identify and use natural numbers,e.g. convert between numbers and words
1,Number,C1.1,Types of number,C1.1.2,Identify and use prime numbers,
11.1,Квадрат тэгшитгэл ба тэнцэтгэл биш,М11.1А,,М11.1А1,Квадрат тэгшитгэлийн графикийг зөв зурах.,
```

### Importing again is safe

An import **never deletes anything**: what the file leaves out stays. Rows are matched to what the syllabus already has:

- a topic, chapter or skill matches by its **code** when both have one, otherwise by its **name** (ignoring case and spacing);
- a skill with a code is found **anywhere in the syllabus**, so a skill whose code now sits under another chapter is **moved**, not duplicated;
- a match whose text differs is **updated** to the file's text (empty notes in the file never blank existing notes);
- the same skill code under two different chapters in one file is an error, and an identical repeated row is ignored with a warning.

So importing the same file twice reports "nothing to import", and a corrected or extended file updates the syllabus in place.

## Where syllabus skills show up

- **Course Bundles.** A syllabus's skills (and those of its topics) count as the skills of that curriculum item on a learner's bundle dashboard, and a skill shows the topic it belongs to.
- **Skill library.** Under the syllabus's root skill, with their codes.
- **The course.** As its chapters and skills, in the course you open from the Content Hub Courses tab.
- The Curriculum tree shows each syllabus with its course and its number of skill groups (its chapters) and skills.

## REST API (`ohmylms/v1`, administrators only)

All routes are under `curriculum/items/{id}/syllabus` (`{id}` is the syllabus). Every write answers with the syllabus's fresh outline, the item tree and the course summary.

| Route | Purpose |
| --- | --- |
| `GET …/syllabus` | The outline: contents (the syllabus first, with `depth`), each with `groups` (each with the `chapter_id` of the course chapter it became), each with `skills`; totals; the root skill; the `course` summary (`null` until it exists) |
| `POST …/syllabus/course` | Give the syllabus its course if it has none and make the course match; answers like a write |
| `PUT …/syllabus/course/skills` | Which skills the course requires: `skills` is `[{term_id, required?, target?}]`. Only skills of this syllabus change, and only their requirement and target; answers like a write |
| `POST …/syllabus/groups` | Add a group (`name`, `code`, `description`, `item_id`, `position`) |
| `PUT/DELETE …/groups/{group}` | Edit a group; delete it (a group holding skills needs `confirm`) |
| `POST …/groups/{group}/move` | `item_id` and `position` |
| `POST …/groups/{group}/skills` | Create a skill (`name`, `code`, `description`) or place an existing one (`term_id`) |
| `PUT …/skills/{term}` | Edit a placed skill's name, code or notes |
| `DELETE …/groups/{group}/skills/{term}` | Take a skill out of the group (it stays in the library) |
| `POST …/groups/{group}/skills/{term}/move` | `group_id` and `position` |
| `POST …/syllabus/import` | `rows` and `dry_run`; answers a `report` (counts, `errors`, `warnings`, `applied`) |

Writes also carry `course` (id, title, status, mode, `published`, counts of chapters, skills and attachments, and the address of the course settings, `edit`) and, when the course could not follow, `course_error`. Items answer with `course_id`. `PUT curriculum/items/{id}` also accepts `is_syllabus`; turning it on, or creating an item typed "syllabus", makes the course, and renaming a syllabus renames its course. Moving or deleting an item that holds chapters brings the course up to date.

What a skill owns uses the skill API: `GET skills/{id}` (its `lessons`), `PUT skills/{id}/lessons` (`lesson_ids`, replaces the set), `GET skills/link-targets?type=lesson`, and `GET question-bank?skill={id}`. The course's attachments use the catalog API (`GET/PUT content-hub/catalog/{course}`); the editor saves only `attachments`, never the skills, which the syllabus owns. Skill names and notes from the skill API (`skills`) and the curriculum skill pickers are returned as plain text (`a > 0`), not as the HTML entities WordPress stores (`a &gt; 0`).

## Storage

Schema version 3 of `OhMyLMS\Curriculum\Schema` (applied on the next page load, safe to repeat): three columns on `ohmylms_curriculum_items` (`is_syllabus`, `skill_root_id`, and `course_id`, the course a syllabus is, 0 until it is made) and two tables, `ohmylms_syllabus_groups` and `ohmylms_syllabus_group_skills` (the ordered placements). Skills themselves are terms of the `ohmylms_skill` taxonomy with the code in `_ohmylms_skill_code`; the root skill is marked with `_ohmylms_syllabus_item`. Deleting a curriculum item removes its groups and placements (and the link to its course; the course stays); deleting a library skill removes its placements.

The course is an ordinary course post with the meta `_ohmylms_syllabus_item` (the syllabus), and each mirrored chapter carries `_ohmylms_syllabus_group` (the uuid of its skill group). The skills are outcomes in the course's learning-program draft.

Skills created here get a short slug of their own. WordPress derives one from the name, which for long names in Cyrillic and similar scripts is URL-encoded and cut at 200 characters; two objectives that begin the same then collide and the insert fails.

## Source

- PHP: `includes/Curriculum/Syllabus.php` (outline, groups, skills, import), `SyllabusCourse.php` (the course a syllabus is: creating it and mirroring chapters and skills, with the planning kept pure), `SyllabusRows.php` (cleans and checks rows), `SyllabusPlan.php` (matches rows against the syllabus and plans the operations; shared by the dry run and the import), controller `includes/Rest/V1/SyllabusController.php`. `Links::object_ids()` and `Links::memberships()` include syllabus skills. `CourseProgram::too_many_outcomes()` lifts the 200-skill limit for a syllabus's course.
- Admin UI: `assets/src/features/curriculum/`. The editor is `SyllabusWorkspace.jsx` (the page and its route component), `WorkspaceSidebar.jsx` (the outline), `TopicPane.jsx`, `ChapterPane.jsx`, `SkillPane.jsx` and `SkillResources.jsx` (the panes), `CoursePanel.jsx`, `WorkspaceParts.jsx` (autosaving fields, quick add, rows, menus), `WorkspaceDialogs.jsx`, `AddContentMenu.jsx`, `useSyllabusWorkspace.js` (loading and guarded changes), `useCourseCatalog.js` (the course's attachments), `workspaceActions.js` (every change), `workspace.mjs` (the pure model: nested outline, selection, ordering). `SyllabusSummary.jsx` is what the item panel shows, `ImportDialog.jsx`, `csv.mjs` (parser, column detection, export), `syllabus.mjs` (helpers) and `download.mjs` are shared. The route is `SYLLABUS_ROUTE` in `assets/src/features/content-hub/hubRoutes.mjs`, wired in `assets/src/extensions/index.jsx`; the page loads lazily from the `curriculum` chunk. Styles are `.ohmylms-ws-*` in `assets/css/admin-ui.css`.

## Validation

```sh
node --test tests/js/workspace.test.mjs tests/js/syllabus.test.mjs tests/js/curriculum.test.mjs tests/js/content-hub.test.mjs
php tests/php/syllabus-unit.php
OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/syllabus-integration.php
OHMYLMS_TEST_CREDENTIALS=... OHMYLMS_CHROMIUM_PATH=... npx playwright test tests/browser/syllabus.spec.cjs --workers=1
```

The integration suite refuses any database other than `ohmylms_source_test`. It covers the flag and its guard, group and skill editing, equal names and unique codes, maths and Cyrillic text, long names that begin alike, a dry run that changes nothing, an import applied, repeated and edited, a refused file, an import that fails part-way and rolls back (including the library skills it created), the Bundles and membership integration, deletion, permissions, and the course: it is made, named, linked and rebuilt, chapters and skills follow every change (rename, reorder, move, delete, import), what was added to the course by hand survives, more than 200 skills fit, and deleting a syllabus keeps its course. The browser spec opens the editor from the syllabus's name and builds chapters and skills from the keyboard, attaches a lesson to a chapter, tags lessons to a skill, and runs the CSV import and export.

## Known limitations

- PDF syllabuses are not read by the app; they are converted to CSV first (send one to a developer or assistant to convert and review).
- Chapters are not nested inside each other (topics hold chapters), and a skill is in one chapter per syllabus.
- Reordering uses the Move up and Move down menu items, not drag and drop.
- An import never deletes, so removing a skill from a syllabus is done in the editor.
- The course follows the syllabus one way. Chapters, skills and attachments added to the course by hand stay, but a skill removed from the syllabus leaves the course, and a skill added to the course by hand that is later placed in a chapter is taken over by it.
- A large syllabus takes a moment longer to save: the course is brought up to date after each change (about 70 ms for a skill added to a 500-skill syllabus, and a second or two for the first sync of a large import).
- A new syllabus course cannot be published until you require a skill and that skill has approved questions; there is no "require everything" shortcut beyond one chapter at a time.
- Lessons attach to a whole chapter in the editor. Attaching to chosen skills of the course is supported by the API (`skill_ids` on an attachment) but the app has no screen for it since the Content Hub Catalog tab was removed. Lessons tagged to a skill belong to the skill, and are not attached to the course automatically.
- Games are not built yet; only the slot for them exists.
- Strings added after the last catalog refresh are not yet in `languages/ohmylms.pot` or the Mongolian translation; see "Translations" in [DEVELOPMENT.md](DEVELOPMENT.md).


## Single skills workspace

Syllabuses and roadmaps use one outline and detail workspace. Compact skill names and connection counts appear in the outline; search matches names, codes and objective statements while preserving folder context. Connection filters include unconnected skills and each relationship type. Checkboxes select multiple skills for a folder move; successfully moved items are removed from the selection, and a failed move stops the batch. The skill detail pane edits names, bilingual objectives, codes, folders and resources.

Roadmap connections are edited in the selected skill pane with source names, directional types and notes together. Connect skill searches the configured source syllabuses with pagination. Individual edits and removals include the saved pair revision; stale writes are rejected. Required-together creation selects at least two objectives from one source; existing groups use the complete-group editor. Unsaved connection forms block skill selection and bulk-tool navigation until saved or discarded. The optional focused diagram opens connection editing directly.

Bulk edit opens the existing spreadsheet as a secondary full-screen tool, preserving paste/import and save/discard behavior. The former Outline/Mind map/Spreadsheet switch and separate visual-editor entry have been removed from the workspace. Existing map and studio modules remain available to other consumers. These changes do not alter skill IDs, source evidence or completion rules.
