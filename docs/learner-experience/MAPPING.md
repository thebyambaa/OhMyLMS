# Khan Academy to OhMyLMS: data-model mapping and restructuring notes

Status (updated October 19, 2026): **mapping analysis behind the learner hub; its open decisions are
now resolved** (see section 5). The comparison tables record the position on October 15, 2026,
corrected below where later work changed a row; the code is the authority. No stored name was
renamed: only learner-facing labels changed.

Written October 15, 2026 from the repository schemas and the Khan Academy Classroom
observations in [RESEARCH.md](RESEARCH.md). Schema versions quoted: curriculum 6, learning 1,
assessment 4. Re-read the schema files before acting on any column name.

## 1. The core idea

Khan's learner experience is one repeating shape: **Subject → Course → Unit → Lesson group
→ Activity**, with a mastery level on each skill and one progress strip per course.
OhMyLMS already stores all of those ingredients, but in five different places that each
have their own screens (courses, syllabuses, roadmaps, bundles, practice).

So the way to get the Khan experience "exactly" across every element is **not** to
restructure the tables first. It is to add one **learner read-model layer** that turns any
of those sources into the same normalized shape, and render everything with one set of
components (hero, progress strip, unit accordion, activity row). Renaming then happens
mostly in that layer's vocabulary, and storage can change later behind it.

```
 curriculum_items / links ─┐
 course + chapters + program ┼─► LearnerCatalog read-model ─► shell / library / course view
 syllabus groups + skills ──┤        (Subject, Course, Unit, LessonGroup,
 roadmap sources ───────────┤         Activity, Skill, Path, Collection)
 bundles ───────────────────┘
 student_skill_state / evidence / practice ─► MasteryAdapter ─► levels and strip colors
```

## 2. Entity-by-entity comparison

| Khan element | What it does | OhMyLMS source today | Fit | Action |
|---|---|---|---|---|
| Subject (library tile, icon, colour) | Top-level grouping of courses | `ohmylms_curriculum_items` (any depth, `parent_id`, `position`, `item_type`, `icon`, `description`); suggested types framework, qualification, level, grade, section, subject, syllabus, strand, topic, unit, custom | Strong. `icon` already exists. | Treat items of the chosen "subject" types as library tiles. Add a learner-visible colour only if wanted (see section 5). |
| Course | Titled page with unit and skill counts | Course CPT (`ohmylms-course`), linked to items through `curriculum_links` (`object_type`, `object_id`) | Strong | Count units and skills from real data only. |
| Unit | Large block with description, "Continue this unit" | Chapter CPT (`ohmylms-chapter`) via `ohmylms_chapter_relationship`; also curriculum item type `unit` and syllabus chapters | Strong | Label change only. Keep IDs. |
| **Lesson group** (the numbered "1. What is the SAT?" headings) | Groups activities inside a unit | Was a gap. Program drafts now carry real groups (`Learning\ProgramGroups`; items and skills have `group_id`; MCP `set_unit_lesson_groups`). `syllabus_groups` remain skill groups, not content groups. | Resolved | Decision D1 below. |
| Article / video | Reading and watching, mark complete | Lesson CPT (`ohmylms-lesson`) with text, video, audio | Strong | Activity kind `lesson`. |
| Exercise (skill practice, level up) | Adaptive practice that raises mastery | Practice sessions per skill (`practice_sessions`, `practice_items`), Selector, SmartScore | Strong, richer than Khan | Activity kind `practice`, one per skill term. |
| Quiz / Unit test | Scored check | Quiz CPT. The assessment setting `style` (`quiz`, `unit-test`, `course-challenge`) is presentation only; the kind (quiz, exam, practice) is unchanged | Resolved | Mixed-skill unit tests are built by `Assessment\SkillAssessment` ([KHAN-ACTIVITIES.md](KHAN-ACTIVITIES.md) sections 9 to 11). |
| Course challenge | Whole-course mixed test | The `course-challenge` style of a skill assessment | Resolved | Shown as a card on the hub course overview only when a published one exists. |
| Skill | A thing to master | Hierarchical taxonomy `ohmylms_skill` with stable UUID, optional code, prerequisites (acyclic); `question_skills`, `quiz_skills`, `syllabus_group_skills` | Stronger than Khan | Use as the exercise identity. |
| Mastery levels: Attempted, Familiar, Proficient, Mastered | Four coloured levels | `student_skill_state.level`: not-assessed, developing, proficient, mastered. SmartScore bands: learning, building, proficient, challenge (starting at 0, 70, 80, 90) | Partial | Decision D2. |
| Course progress strip (one icon per activity, coloured) | Shows state at a glance | `student_skill_state` per skill, lesson completion from existing progress | Possible | Strip needs one query joining unit activities to level. Bound it per course. |
| Skills to Proficient (count) | Headline metric | Count of skill terms in the course whose level is proficient or mastered | Possible | Derive, never store. Matches the plan's "no invented aggregate." |
| Continue learning | Next activity | `Student::get_course_resume_url`, `CompletionPolicy`, `LearningPath::next` | Strong | Reuse. |
| Missions (daily, weekly, unit) | Goal-based engagement | Nothing equivalent. Streaks, XP, badges, leaderboard exist in `Engagement/` | **Gap** | Do not fabricate. Show Streak and XP now. Missions need a separate, scoped feature. |
| Gems and Gem challenge | Reward currency and class goal | XP (`XpSchema`) is the closest. No class or community goal. | Gap | Rename XP in the learner UI only if the user wants. No group challenge yet. |
| Classes, assignments | Class selector, assigned work | `schools`, `classes`, `class_memberships`, `learning_assignments`, `assignment_recipients`, `school_invitations`, `guardian_links` | Strong | Already used by the hub. |
| Teacher: Dashboard, Assignments, Roster, Reports, Class Settings | Teacher shell | Schools Controller and Views, `gradebook_overrides`, quiz reports, assignment tables | Partial | Teacher slices come after learner slices (user decision). |
| Browse Subjects by search | Search box and expandable tiles | `Curriculum\Directory`, `Catalog` | Possible | Bound the query. |
| History and Scores | Activity log, scores | `grade_events`, `attempt_context`, `practice_sessions` | Strong | Read from `grade_events`. Do not convert scores. |
| Achievements | Badges and rewards | `Engagement\Achievements`, `Badge`, certificates | Strong | Rebuild presentation only. |
| Settings tabs | Account, personalization, appearance, notifications, classes | Hub Account views, profile, notification prefs, school connections | Partial | No Khanmigo tab (no learner tutor). |
| Khanmigo / Tutor Me | AI tutor | Admin assistant only | **Skipped by user decision** | Omit the menu item. |
| Teacher Tools (teacher rail) | Extra teacher utilities | n/a | **Skipped by user decision** | Omit from the teacher shell. |

## 3. Where OhMyLMS has no Khan counterpart

These need presentation decisions, because Khan has nothing to copy:

- **Roadmaps** (ordered shared skills across several syllabuses, stored as post meta
  `_ohmylms_roadmap_sources` on a roadmap's own course, resolved by `Roadmaps::preview`).
  **User decision (October 15, 2026): a roadmap is not a learner-facing course.** It is the
  shared-syllabus mapping layer: doing certain skills in one syllabus or in a course linked
  to it also updates the same shared skills in other courses. So roadmaps are **never listed
  in Browse subjects, My learning or any course picker**, and there is no learner roadmap
  or "learning path" screen. The learner only sees the effect: skill progress shown in one
  course reflects evidence earned in another through `skill_mappings`. The catalog excludes
  any course carrying `_ohmylms_roadmap_sources` (`LearnerCatalog::roadmap_course_ids`).
- **Course bundles / collections** (`bundles`, `bundle_items`, `bundle_follows`; following
  grants no access). Closest Khan idea is a subject tile containing several courses.
  Render as a collection page with its member courses as rows.
- **Skill-based and blended program modes** (`programs.mode`: traditional, skill-based,
  blended; versioned JSON; `outcomes`; enrollment bound to a program version). Khan is
  always content-first. Keep the enrollment's program version as the contract. In
  skill-based mode the unit list is skill-driven, so the strip is the main structure.
- **Syllabus is also a course** (`curriculum_items.is_syllabus`, `course_id`). A syllabus
  course can supply units (its chapters) and skill groups, so for these the *lesson group*
  level already exists as `syllabus_groups`.
- **Mapped skills** (`skill_mappings`, relation equivalent and others). Shared skills let
  one mastery record show in many courses. The Khan strip must read the shared identity.

## 4. Rename and restructure plan

Learner-facing words can change freely. Stored identifiers must not, until a compatible
migration is written. There is precedent in this repository: curriculum schema version 6
renamed "tracks" to "bundles" in tables and columns, keeping every row, behind
`migrate_tracks_to_bundles()`. Use that pattern for any future storage rename.

### Vocabulary proposal (learner label → stored name)

| Learner label | Stored today | Rename storage? |
|---|---|---|
| Subject | curriculum item (type in a chosen set) | No |
| Course | `ohmylms-course` | No |
| Unit | `ohmylms-chapter` | Stored name stays. The displayed word is "Unit" site-wide through `Utility\Terminology` (October 15, 2026). |
| Lesson group | none yet | New, additive (D1) |
| Lesson / Practice / Quiz / Unit test | lesson, skill practice, quiz, quiz revision kind | No |
| Skill | `ohmylms_skill` term | No |
| Collection | bundle | No |
| Class | `classes` | No |

### Must keep stable

Table names under `ohmylms_`, post type slugs, the `ohmylms_skill` taxonomy and its term
meta, REST routes and fields, program version and enrollment binding, assessment
contracts, the evidence pipeline (`grade_events`, `evidence_outbox`, `skill_evidence`,
`student_skill_state`), and the `[ohmylms_student_hub]` shortcode.

### Structural changes worth considering (each needs its own approval)

1. **Lesson groups.** Add an optional group label or ID to program items, or a small
   `ohmylms_learning_item_groups` table keyed by `program_id` and `chapter_id`. Because
   programs are immutable versions, groups appear only on new versions; older enrollments
   keep their contract.
2. **Display mastery names.** Add a view-only mapping, not a new stored level.
3. **Subject colour.** Add an optional `color` to curriculum items if wanted.
4. **Normalized read-model.** A PHP service (no schema change) that returns one shape for
   course, syllabus-course, roadmap and collection. This is the main build item.
5. **Mission and group-goal features.** Separate product scope. Not part of the redesign.

## 5. Decisions and their outcome

- **D1. Lesson groups: resolved.** Real groups were added to course programs
  (`Learning\ProgramGroups`) rather than derived from syllabus groups. Programs are immutable
  versions, so groups appear on new versions only.
- **D2. Mastery names: resolved (October 15, 2026).** Khan's four names are shown in the learner UI
  as a view-only mapping from the SmartScore bands, falling back to the stored level; see
  `Skills\MasteryDisplay`. The data contract is untouched. The optional Khan level model (five names
  stored in an append-only log) was added later; see [KHAN-ACTIVITIES.md](KHAN-ACTIVITIES.md) section 8.
- **D3. Subject set: resolved in code.** `LearnerCatalog::subjects()` takes the curriculum item types
  that count as a subject from the hub setting; the real item types depend on the site's curriculum.
- **D4. Unit test and course challenge: resolved.** They are a presentation `style` on a normal
  quiz built from several skills, not a new `quiz_revisions.kind`.
- **D5. Missions and gems: left out**, as recommended.
- **D6. Teacher shell timing: done.** The teacher, parent and school workspaces were built after the
  learner slices.

## 6. Recommended implementation order

1. **Read-model service** (`LearnerCatalog`): subject tree, course outline, path, collection,
   plus `MasteryAdapter`. PHP, tested against the disposable database. No schema change.
2. **Shell and tokens** (Slice A), in parallel, since it depends on nothing above.
3. **Library** (Slice C) on top of the subject tree.
4. **Course view** (Slice D) on top of the course outline, with the progress strip.
5. **Collection view** (Slice F) as a thin variant of the same view. Slice E (roadmap path
   view) is **dropped**: roadmaps stay invisible infrastructure.
6. Classes, My learning, Membership, History and Achievements, Settings.
7. **Teacher shell**, reusing the same tokens and rail.

## 7. Risks to watch

- The strip and "skills to proficient" count can become one query per course. Batch by
  `term_id` list and cache per request only, never across revocation-sensitive data.
- Shared skills must not be merged across unrelated mappings. Use `skill_mappings` only.
- Draft, private and protected content must stay hidden in every adapter.
- Khan content, brand, icons and artwork are not copied. Only layout patterns are.
