# Course outline publishing

The existing course Content screen now offers **Preview draft outline**. It saves any unsaved course edits first, then displays the saved unit titles, descriptions, numbers, lesson groups and activities in draft order. **Publish changes** publishes that reviewed revision through the existing learning-program readiness checks.

Saving unit edits does not change the learner outline. Publishing creates a new program version containing a `units` snapshot: existing chapter IDs, names, descriptions and author labels, in native relationship order. Client-supplied unit snapshots are ignored; the server captures the authored records. Group IDs, placement IDs, reusable content IDs and assessment contracts are unchanged.

Existing learners keep their assigned program by default. The explicit **Apply to existing learners who have not completed the course** option uses the existing enrollment upgrade policy; completed learners stay on their previous version. New enrollments receive the current publication. Publishing an outline does not change the WordPress course's draft/published visibility status.

## API

- `GET /ohmylms/v1/courses/{id}/structure/preview` returns the saved draft, grouped activity names, readiness errors, current publication ID and revision. Requires permission to edit that course. Preview never publishes.
- `POST /ohmylms/v1/courses/{id}/structure/publish` publishes the saved draft. Send the reviewed `revision` (or revision header) and optional boolean `apply_existing`. Missing/stale revisions, readiness failures and authorization failures leave the publication unchanged.
- Existing learning-program and Content Hub publish endpoints also capture unit snapshots. Their successful response contracts remain available with the additive `units` field on programs.

The dashboard sends the revision from the displayed preview explicitly. A newer request in the same tab cannot silently substitute its token for the reviewed snapshot. Authors must reload the preview and review again after a conflict.

## Storage and readers

No schema migration is needed. Program JSON holds each publication's `units`. Before the first structure mutation, `_ohmylms_unit_baseline` captures the legacy native units and fallback program within the same transaction and course lock. Older program rows without a unit snapshot use that baseline; they are not rewritten. Enrollments pinned to the original native curriculum retain that fallback.

The learner hub reads metadata and order from its selected program. Legacy course chapter lists, chapter object getters, unit-number labels, and public title rendering use the same assigned snapshot. Authoring callbacks explicitly read native draft records, including course metadata-save responses and course cloning. Program copies remap snapshot unit IDs to the copied chapter IDs.

This versions outline metadata and the existing program. Shared lesson bodies, quiz definitions and other reusable resource content keep their existing live behavior; this is not content-revision publishing. Native chapter posts are authoring records, so integrations that read raw post fields directly must use the published program snapshot when presenting learner-facing unit metadata.

## Verification

- Shared structure integration: 61 checks, including draft isolation across the learner hub and legacy getters/lists, preview, stale publication, immutable prior versions, enrollment pinning/upgrades, completed learner protection, readiness failures and anonymous access denial.
- Existing learning-model tests: 37 checks; structure tools: 295; syllabus: 56; unit-number rules: 17.
- Browser: existing dashboard saves, two-tab conflict, draft preview and explicit publish.
- JavaScript suite: 584 passed, one skipped. Build succeeded with the existing two webpack size warnings.
- New PHP services/controller/tests and the new React component pass targeted WordPress standards checks. Repository-wide PHP/JavaScript/CSS/formatting findings remain visible in the normal checks; no blanket baseline was introduced.

The next architecture step is subjects and course browsing, followed by a more coherent unit/lesson authoring presentation.
