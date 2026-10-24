# Unified course outline editor

Independent courses now use **Course → Units → Lessons → Activities** in the Content screen. Lessons are the existing program groups, retaining their UUIDs and `group_id` fields. Syllabus-owned courses continue to use their syllabus workspace; this does not convert them.

The selected lesson now also supports [creating activities, linking skills and checking lesson coverage](LESSON-ACTIVITIES.md).

Learners can open [lesson pages with grouped activity navigation, resume and separate progress measures](LEARNER-LESSON-JOURNEY.md).

The left outline selects a unit, lesson or activity. The right panel edits that selection. Authors can reorder units, lessons and activities, move lessons with their activities between units, and place reusable resources. Removing a lesson ungroups its activities; removing an activity removes its placement. Shared content posts remain intact. Only empty units can be explicitly removed.

## Saving and publishing

**Save outline draft** explicitly saves units, lessons and activity placements together. The outline does not autosave. Navigation warns about unsaved edits. Course information/media retains its existing metadata save behavior, without submitting a stale chapter list. Syllabus objectives remain separate mappings.

Preview saves pending edits before showing the saved draft. Publishing uses the reviewed revision and existing readiness/enrollment rules. Published unit snapshots protect learners from draft unit changes. Resource bodies still use their existing shared content editors and are not versioned by an outline publication. See [publishing behavior](COURSE-OUTLINE-PUBLISHING.md).

Concurrent edits return a conflict and retain local edits. Reloading requires explicit discard when there are local changes. A successful publication updates the editor revision so subsequent saves can proceed.

## API and storage

- `GET /ohmylms/v1/courses/{id}/structure` includes the complete program draft, ordered units, editable resource links, creation links and an unpublished-change indicator.
- `PUT /ohmylms/v1/courses/{id}/structure` accepts the complete `units` array and `program` object. Send the revision header or `revision` field. Existing units use their integer IDs; new units use UUID strings. Program chapter references may use those UUIDs in the same save. The response includes the saved structure and `unit_ids` mapping to native integer IDs.
- `GET /ohmylms/v1/courses/{id}/structure/resources` searches editable lesson, quiz and assignment resources using `type`, `search` and `page`. Practice choices come from the course's selected skill outcomes. Create or edit resource bodies through their existing editors.

The existing shared authorization, course lock and transaction wrap the complete save. Unknown unit references, invalid program rows and failed unit writes roll back all changes. Existing units cannot disappear through omission. No schema migration is required.

Activity placements can include an optional `title` of up to 200 characters. This overrides the displayed name only within this course. An empty title restores the shared resource title. Learner outlines, next-action labels and author previews use the override. Legacy placement round trips preserve it when omitted by older clients; existing resource IDs and assessment contracts remain unchanged.

## Ordering and assessments

Program activity order follows native unit order, then lesson order and its activities, followed by activities directly in the unit. The server canonicalizes this order so learner progression matches the displayed outline. Legacy ungrouped activities remain available.

Assessment placement uses the quiz's stored assessment style, not a client-provided style:

- One unit test at most per unit, after its other activities, outside lessons.
- One course challenge at most, outside units and lessons, after the course's other activities.

The shared program validator enforces these rules for other program writers too. The learner course view displays the challenge after the units and includes it in completion totals.

## Verification

The browser test covers adding a unit/lesson/resource, course-specific naming, reordering, unsaved navigation, explicit saving, metadata isolation, preview, publication, saving after publication and a conflicting edit in another tab. PHP integration covers temporary unit mapping, rollback, stale revisions, resource preservation, checkpoint placement and published learner output. Pure tests cover ordering and lesson removal.

Run the PHP integration with `OHMYLMS_STRUCTURE_INTEGRATION=1 php tests/php/course-outline-integration.php` against local WordPress, and the browser workflow with the fixture and `tests/course-structure.config.cjs` described in [the structure contract](COURSE-STRUCTURE-CONTRACT.md).

## Editor layout (October 24, 2026)

The Content step follows the Khan Academy course shape:

- **Header**: one status line (unsaved changes, draft not yet published, or published), a link to any publishing blockers, and **Discard changes**/**Reload saved outline**, **Save outline draft** and **Preview draft outline**. On the Content step the header replaces the separate course save-status bar; course information still saves through its existing metadata save.
- **Tabs**: Outline, Readiness (with the blocker count), Syllabus alignment (the course objective mappings) and Course info. Readiness links switch back to the Outline tab and select the item to repair.
- **Outline**: each unit is a card that can be collapsed, with its lesson, skill and activity counts. Lessons list their activities with type icons and draft or missing badges, plus linked skills that have no practice yet. Skills and activities outside a lesson appear under "Not in a lesson". Every unit ends with an **Add unit test** slot and the course ends with an **Add course challenge** slot; these pickers list only quizzes whose assessment style matches.
- **Practice is the skill**: Add practice lists the skills already linked to the lesson and, when you search, the whole skill library. Choosing a library skill links it to the lesson and places its practice in one step. A skill already linked to another lesson stays there and cannot be chosen.
- **Skills**: selecting a skill opens a panel to change whether it is required, move it to a lesson in its unit, add its practice or remove it from the course. Skills whose shared term no longer exists show as "Missing skill #ID". The server rejects saves while they remain, so the editor offers **Remove missing skills**, which also removes their practice placements and activity coverage links.

- **Drag and drop**: drag a unit onto another unit's header to put it before that unit, or onto the course end to move it last. Drag a lesson onto another lesson, or onto a unit header to append it to that unit; its activities and skills move with it. Drag an activity onto another activity, onto a lesson, onto "Not in a lesson" or onto a unit header. Unit tests and the course challenge keep their fixed places. The inspector's Move buttons remain the keyboard alternative.
- **Skill codes**: the structure response adds `skill_codes`, the shared skill's `_ohmylms_skill_code` keyed by term ID. Practice and skill rows and the skill picker show the code next to the name.
- **Group skills into lessons**: a selected unit with skills outside lessons offers **Make a lesson for each skill** and **Put them in one lesson**. Both link the skills to the new lessons and place their practice; missing skills are left for removal.
- **Unit test and course challenge slots** can also create a new draft quiz with that assessment style.

At 960px and below the toolbar wraps the steps under Back and the actions. At 782px and below it scrolls with the page and stays pinned to the top. No REST contract, stored field or assessment contract changed.
