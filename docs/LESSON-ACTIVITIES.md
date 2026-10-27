# Activities and skill coverage inside lessons

Select a lesson in an independent course's Content screen. **Add video**, **Add reading** and **Add quiz** open draft creation; **Add practice** offers that lesson's linked skills. **Add activity** continues to offer reusable resources. The activity dialog also lets authors switch between existing resources and new drafts.

Readings accept initial content; videos accept an HTTP/HTTPS URL and notes. Both use the native lesson writer/settings contract. New quizzes use the established quiz writer and start as drafts; add questions and configure them in their content editor. Question extensions and versioned assessment contracts are unchanged.

Creating a resource saves a reusable draft immediately. Its placement remains local until **Save outline draft**. Closing/reloading the outline can discard the placement while retaining the resource in the library. Resource creation does not autosave pending units, lessons or other placements. Every new resource returns an **Edit resource content** link. Publish the resource in its editor, then use **Reload saved outline** to refresh publication status; unsaved outline edits require explicit discard before reloading.

## Skills

Lessons generated from a syllabus retain their skill links. For another lesson, use **Link skills to lesson** to select existing library skills. A newly selected course skill is optional for completion, targeting Proficient; linking it does not automatically create an official syllabus objective mapping. Skills already linked to another lesson are excluded because the program places each course outcome once.

Each reading/video can select the lesson skills it teaches. A quiz/assignment can select the skills it assesses. Practice inherently references its selected skill. These are the existing per-placement `skill_ids` and practice `content_id`, not mutations of a reusable resource's global taxonomy tags. The server continues to reject references to skills outside the course's selected outcomes. Moving an activity preserves its declared scope and shows selected skills linked outside the destination lesson so the author can repair them explicitly.

New lesson skills are available for practice immediately in the unsaved outline. Saving persists both the outcome placement and its practice activity together.

## Lesson checklist

The checklist reflects the current local draft, including unsaved edits. For each lesson it shows missing activities, missing linked skills, skills without teaching activities, skills without assessment/practice activities, and resources awaiting publication. Coverage counts only explicit links on activities in the same lesson and unit. Resource tags, ungrouped activities and activities in other lessons do not imply coverage.

This describes intended coverage. It does not verify content quality, question alignment or learner mastery and does not replace publication readiness checks. Existing resource publication, question-pool, completion and assessment checks remain separate. Selecting a checklist lesson opens its editing panel.

## API

`POST /ohmylms/v1/courses/{id}/structure/resources` creates a reusable resource draft. Send the saved course `revision`, a fresh UUID `request_id`, `kind` (`reading`, `video` or `quiz`), an optional quiz `style` (`quiz`, `unit-test` or `course-challenge`; stored in the quiz's assessment settings), `title` (up to 200 characters), optional `content` (up to 100 KB), and a valid video `url` when applicable. URLs are stored without fetching them. The response follows the picker resource contract, adding `status`, `format` and the current `revision`.

Course authorization, the shared course lock and transaction wrap creation; the native resource writer additionally checks its own permissions. Stale revisions return 409. A retry with the same request UUID and input returns the same resource. Reusing that UUID with different input returns 409. An error after insertion rolls back the resource and its request metadata. Resource content and structure publication are separate actions.

The existing structure GET includes current resource `status`. `GET .../structure/resources?type=skill&search=...&page=...` searches existing skill choices for lesson linking. Existing placement and assessment REST fields are unchanged.

## Verification

The activity integration test covers reading/video/quiz draft creation, native content/settings, retry identity, invalid input, stale revisions, draft isolation, skill scope, unchanged resource tags, publication checks, post-insertion rollback and anonymous denial. Pure tests cover coverage isolation, practice, drafts and missing links. The browser flow creates all four activity kinds, chooses teaching/assessment skills, saves/reloads, inspects the checklist, and links a library skill to a new lesson before adding its practice activity.

Run the PHP tests only with `OHMYLMS_STRUCTURE_INTEGRATION=1`. The browser uses the syllabus-course fixture and cleanup described in [course generation](COURSE-FROM-SYLLABUS.md).

Local verification on October 24, 2026: 29 activity checks, 22 outline checks, 61 shared structure checks, 37 model checks, 295 structure tool checks and 30 syllabus generation checks passed. JavaScript tests: 591 passed, one existing skip. Both the activity-authoring and existing save/preview/publish/conflict browser workflows passed. The authoring browser test also verifies that syllabus objective coverage refreshes after saving activity links. Temporary records, created resources and authentication sessions were cleaned up.

Source contracts, changed PHP syntax/WordPressCS, new/modified activity components' ESLint/Prettier, and scoped CSS checks passed. The build succeeded with two existing webpack size warnings. Repository-wide lint retains existing findings (316 JavaScript errors/37 warnings and 125 CSS errors); legacy course orchestration also retains its existing hook warnings. No blanket baseline or disabled rule was introduced.
