# Unified Learning: First Usable Release

Status (October 19, 2026): implemented and merged; this is the reference for course learning modes, completion
rules and learning-program publication. The original planning proposal (UNIFIED-LEARNING-PLAN, October 5, 2026) was removed
because this release shipped it. Later work adds lesson groups to program drafts, a learner-hub course overview
and mixed-skill unit tests (see [CONTENT-HUB.md](CONTENT-HUB.md) and
[learner-experience/KHAN-ACTIVITIES.md](learner-experience/KHAN-ACTIVITIES.md)).

## Set Up a Course

1. Skills are always available. Build a grade, exam or subject from chapters and skills in a syllabus's editor in the [Content Hub](CONTENT-HUB.md), or use the course's Learning tab as described here; both edit the same program draft.
2. Create skills and approve practice questions in the Question Bank. Assign each question its primary skill and a question family. Secure exam questions and questions needing manual marking are not practice questions.
3. Open a course > Settings > **Learning**.
4. Choose Traditional, Skill-based, or Blended. Select required skill outcomes and their Proficient or Mastered targets. Add practice steps, reusable lessons, and checkpoints to the path.
5. Check required/optional flags, checkpoint pass percentages, units, and order. The mode changes the presentation and defaults for new items; it does not silently remove existing requirements.
6. Preview completion rules, then publish the learning program. This is a separate action from publishing the course itself. Both must be published for learners to access the path.
7. Use **Open learner view** while signed in as an enrolled learner. Use **Learner progress** for the teacher report.

The learner path is also available at `/?ohmylms_learning=COURSE_ID`, through the course's Learning tab, or with `[ohmylms_learning course="COURSE_ID"]`.

## Completion Rules

Completion requires all selected required activities, skill outcomes, and checkpoints. Optional content is excluded from the denominators. An empty requirement set cannot complete a course.

- **Traditional:** normally requires lessons and checkpoints. Skills are optional unless explicitly made required.
- **Skill-based:** normally requires skill targets; supporting lessons can be optional.
- **Blended:** normally requires lessons, skill targets, and selected checkpoints together.

Practice session length is never a course-completion requirement. A skill must reach its selected target using independent evidence. Hinted answers do not count as independent evidence. Checkpoints use graded scores and their published pass percentages, not a learner's self-reported completion. Manual marking and pending evidence can delay completion.

The learner sees separate activity, skill, and checkpoint counts. Legacy percentage widgets use the least-complete required dimension, capped below 100 until completion is awarded. Awards are durable: a later review-due flag or skill decline does not revoke a previously earned course completion.

Default skill rules come from the existing mastery engine: Proficient requires at least three independent correct answers, two question families, and sufficient recent accuracy. Mastered also requires more independent evidence and a later review. Publishing checks the allowed pool against the configured question/family minimums.

## Reuse and Evidence

Lessons remain WordPress custom post types. Skills remain shared taxonomy terms, not duplicate lesson or course posts. A learning program references existing lesson IDs, so one lesson can appear in multiple courses. Editing that lesson changes its content everywhere; course completion remains separate for each enrollment. The learning program freezes requirements, not lesson content.

By default, a course recognizes existing skill evidence from other courses. Turn this off to require evidence produced in this course after the learner was assigned its program version. An optional evidence-age limit restricts how old qualifying evidence can be.

Select allowed question banks to narrow course practice. With no explicit selection, the publishing author's personal questions and banks they can use form the pool. Learners cannot choose an arbitrary course ID to unlock questions. Catalog practice exposes only explicitly public pools or questions the learner is entitled to use. Only an administrator can enable a skill's public catalog practice setting. Practice resume is available in the same browser tab and revalidates access on the server.

Quizzes and assignments can be placed in several courses; a learner's attempts and a checkpoint's pass status are shared across them (see [CONTENT-HUB.md](CONTENT-HUB.md)). You can still reuse their questions through the Question Bank. Create assessments in the existing Content editor or from the Content Hub, then select/configure them in Learning.

## Publication and Existing Learners

Each publication creates an immutable program version. New enrollments use the current version. Existing enrollments retain their prior version, including the original curriculum when no program existed.

The explicit **Apply this publication to currently enrolled learners who have not completed the course** checkbox migrates unfinished enrollments. Completed enrollments and awards are preserved. With course-only evidence enabled, migration resets the evidence start boundary to the new assignment time. A stale publication is rejected when another teacher has published a newer version since the editor loaded.

No existing course is automatically converted. Existing curriculum and completion behavior remain in use until a learning program is published and, where appropriate, existing learners are explicitly migrated. Skills are a core feature with no switch (see [ADDONS.md](ADDONS.md)), so published skill requirements can never become unreachable because an add-on was turned off.

## Storage and Rollout

Four additive, indexed InnoDB tables hold programs, outcomes, enrollment-version bindings, and completion awards. Existing lesson, quiz, enrollment, and progress records are retained. The schema installs on WordPress init. The Learning editor is included in the lazy-loaded courses feature chunk.

Back up the site and database before deployment. Pilot one course, preview its policy, verify approved question availability, and test it with an enrolled learner before migrating other courses. Shared lesson edits affect every course using that lesson. Do not remove required resources from active versions without planning a replacement publication.

This release provides the authoring workflow, course-scoped learning path, existing practice/mastery engine integration, separate completion dimensions, reusable lesson rendering, and a paginated teacher report with per-skill details. It is not a complete IXL clone. Advanced adaptive prerequisite sequencing, intervention dashboards, curriculum import, diagnostic placement, and bulk migration remain later phases of the comprehensive plan. The next-action selector follows the authored path and unmet outcomes; it does not claim a statistical personalized curriculum.

## Verification

- `npm test`: asset parity and JavaScript unit tests.
- `npm run build`: application assets, lazy SDK chunks, and admin synchronization.
- `php tests/php/learning-integration.php`: disposable WordPress database only; configured through `OHMYLMS_TEST_CREDENTIALS`.
- `npx playwright test tests/browser/learning-program.spec.cjs --workers=1`: teacher publication, reused lesson, learner completion, practice resume, desktop and narrow mobile layouts. Use `OHMYLMS_CHROMIUM_PATH` for an installed Chromium browser if Playwright's bundled browser is unavailable.

Do not run database integration or browser fixture suites concurrently against the same disposable site: fixtures temporarily change add-on settings. Never point these tests at a production database.

The existing rich-text editor still has the ProseMirror duplication issue documented in `DEVELOPMENT.md`. The wider course-authoring browser checks detect that error. This release does not replace or migrate that editor; the new Learning settings and learner-path checks are separate from it.

## Design rules kept from the original plan

- A course-to-skill taxonomy link means **association**. A skill becomes a completion requirement only through an explicit course
  outcome (skill, target level, required or optional).
- Reuse a skill across grades and courses when the measurable ability is the same; create a new skill when the expected ability
  changes, not merely because the grade or course does. Grade level, standard and subject are classifications, not skills.
- Topic parents organise the catalogue; attainment is measured on precise leaf skills. A topic's progress is a transparent rollup of
  its selected outcomes. Prerequisites form a directed acyclic graph separate from the catalogue hierarchy.
- Show three separate dimensions (activities, skill outcomes, checkpoint results) and never average them into an unexplained
  percentage. Completion is an explicit AND of the published required conditions, evaluated by one `CompletionPolicy` service,
  awarded once, and not revoked by a later review-due flag.
- Mastering a skill in one course never grants access to another product. Practice access is validated against the real enrollment
  or an explicitly public pool, never against a client-supplied course ID.
