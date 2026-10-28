# Unified course editor

Empty courses, syllabus courses and courses imported from quiz files open the same course editor. The starting-content choice only determines what is prefilled.

Units contain block lessons, practice and quizzes. Lessons are reusable WordPress lesson posts, edited through their block editor. The outline inspector edits the placement title, unit, required completion and skill connections. It does not edit the shared resource body.

## Course header

The course's editable name and rich description appear at the top of the outline, alongside its readiness indicator. The separate Course info tab is removed. Image and video controls are available in the collapsed **Course image and video** section in the header. Existing metadata autosave behavior is preserved for drafts; live courses retain explicit saving, and metadata edits do not submit a stale chapter list.

## Subject and syllabus alignment

Syllabus alignment includes the existing Subject profile connections above the single syllabus selector. Both this view and Settings → Organize use the same course organization endpoint. Subject changes save immediately, preserve other curriculum placements, and control the Library and Explore grouping of publicly available courses. The syllabus independently controls skill suggestions.

## Skill connections

Use **Course skills** in the Outline to add skills from the selected syllabus. Units are optional at this stage. Course skills are searchable, with completion requirements and target controls on each skill card. **Connect lessons and quizzes** edits the skill's content links in the same panel.

Every lesson and quiz uses one **Connected skills** tag field and one **Add skills** action. Suggestions include skills from the selected syllabus, even when the course has no selected skills yet. Picking a source skill creates its optional course outcome and connects it to the selected activity in one local edit. Loading, request failure and missing-source states are shown explicitly; deleting an activity tag retains the course outcome and its completion requirements. The Add skills action opens the same selected syllabus picker and connects the selection directly to the selected activity and course. The older Manage lesson skills shortcut is removed; stored legacy group associations are preserved. Outline rows show linked skill chips that reopen the same controls.

Adding syllabus skills through this picker also saves the corresponding alignment references using their existing revision check. The dialog explains that alignment references save immediately; the author must choose Save course to keep the course and activity connections. Failure to save references retains the picker selection and does not add course outcomes. Completion always counts each distinct skill once.

**Skill connections** shows each course skill's teaching activities, practice and planned assessments, computed from the current draft. Selecting a listed resource opens its outline inspector. Quiz inspectors compare intended skill links against saved questions: primary question mappings count as assessment coverage; supporting mappings and quiz tags do not. A planned link alone does not establish learner mastery.

## Readiness indicator

A button beside **Course outline** replaces the Readiness tab. It shows a red publishing blocker count, amber **Ready with suggestions**, or green **Ready to publish**. Unsaved changes show **Save to check readiness**; loading and failed checks show their own status rather than a stale ready result.

Click the indicator to expand readiness details above the outline. It exposes its expanded state and controlled panel to assistive technology. Repair links close the details and focus the corresponding outline inspector. Content checks and the explicit Save course action remain authoritative.

## Saving for learners

The Add skills picker shows only the course's selected syllabus, using its saved topic and skill-group hierarchy. Syllabus alignment selects a single syllabus and saves it immediately in `_ohmylms_course_syllabus`, independently of selected objective references. Roadmaps are excluded; their Curriculum relationships remain available for broader skill measurement and comparison. Existing courses infer their source from a syllabus owner or one unambiguous alignment source. Changing the syllabus replaces alignment references from the old source but preserves course outcomes, activity links and completion requirements. Inline suggestions refresh without reloading or discarding outline edits. Unrelated syllabuses and the unrestricted skill library are excluded. The picker uses the linked source automatically and starts directly at its topics or groups, without a source dropdown or repeated syllabus root. Courses without a linked source are directed to Syllabus alignment. Search preserves ancestor paths, branches show skill counts, and filtering or collapsing retains selections. Existing course connections remain unchanged until the author saves.

Select a native lesson or quiz and choose **Edit blocks here** to open Gutenberg inside the course page. The embedded editor retains the native block inserter, media library, custom OhMyLMS blocks, resource permissions and save behavior. Save resource content in that panel, then choose **Back to outline**. Closing warns about unsaved block edits and offers an explicit discard action. Outline edits remain mounted while the panel is open; resource saves affect the shared lesson or quiz. Extension resource editors retain their separate editor links.

**Save course** saves units and placements and activates the validated program in one authorized, revision-guarded transaction. Saved draft block lessons and quizzes used by the course are made available in that save, subject to edit and publish capabilities. Active enrolled learners receive the saved version immediately; completed-course history stays pinned. Completion, skill review and approved-question checks remain enforced; failed saves roll back the outline, resource statuses and learner bindings. Course versions remain internal history. The course UI has no draft preview, publication stage or Save as Draft action. Legacy REST calls without `activate: true` retain their existing draft-save behavior. Navigation still protects unsaved outline edits. The top toolbar's **View course** link opens the saved learner-facing course in a separate tab from Content or Settings; it replaces the old Next action and does not save or activate pending edits.

Use the site's canonical admin address. On this Local installation, `http://800.local` is the configured WordPress origin; opening the admin through `:10004` can cause cross-origin REST writes to fail.

## Compatibility and verification

Older stored drafts may retain skill or legacy group associations after their native unit was removed. The unified save detaches an unchanged stored orphan skill association into a course-level outcome and drops its obsolete legacy group. Skill identity, target, required status and current activity links are retained. New or changed unknown references and activity references remain invalid; the authorized revision-locked transaction rolls back failed saves. This server-side repair accepts an already-open editor's retry without discarding its unsaved new units or lessons.


REST paths, resource identifiers and assessment contracts remain compatible. The existing legacy group helper remains available for older integrations; the current editor creates block lesson placements.

The local browser fixture exercises outline editing, saving, preview, publishing, conflicts and shared skill connections. The syllabus integration checks draft block lesson creation, skill links, save/reload, validation, authorization and rollback. Fixture records and session tokens are removed after verification.
