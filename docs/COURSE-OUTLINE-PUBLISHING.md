# Course outline publication contract

See [Learning Architecture](LEARNING-ARCHITECTURE.md) for current authoring and retained activation behavior. The structure preview/publish endpoints below remain compatibility APIs. The typed app uses its own [reviewed publication panel](api/course-authoring.md), which also publishes the course post; ordinary typed outline saves remain drafts.

Published programs snapshot unit metadata and requirements; shared resource bodies remain live. Structure publication alone does not change the course post visibility. Existing enrollments remain pinned unless explicitly upgraded; completed history is retained.

## API

- `GET /ohmylms/v1/courses/{id}/structure/preview` returns the saved draft, grouped activity names, readiness errors, current publication ID and revision. Requires permission to edit that course. Preview never publishes.
- `POST /ohmylms/v1/courses/{id}/structure/publish` publishes the saved draft. Send the reviewed `revision` (or revision header) and optional boolean `apply_existing`. Missing/stale revisions, readiness failures and authorization failures leave the publication unchanged.
- Existing learning-program and Content Hub publish endpoints also capture unit snapshots. Their successful response contracts remain available with the additive `units` field on programs.

Clients must send the revision from their displayed preview explicitly. A newer request in the same tab cannot silently substitute its token for the reviewed snapshot. Authors must reload the preview and review again after a conflict.

## Storage and readers

No schema migration is needed. Program JSON holds each publication's `units`. Before the first structure mutation, `_ohmylms_unit_baseline` captures the legacy native units and fallback program within the same transaction and course lock. Older program rows without a unit snapshot use that baseline; they are not rewritten. Enrollments pinned to the original native curriculum retain that fallback.

The learner hub reads metadata and order from its selected program. Legacy course chapter lists, chapter object getters, unit-number labels, and public title rendering use the same assigned snapshot. Authoring callbacks explicitly read native draft records, including course metadata-save responses and course cloning. Program copies remap snapshot unit IDs to the copied chapter IDs.

This versions outline metadata and the existing program. Shared lesson bodies, quiz definitions and other reusable resource content keep their existing live behavior; this is not content-revision publishing. Native chapter posts are authoring records, so integrations that read raw post fields directly must use the published program snapshot when presenting learner-facing unit metadata.

## Verification

Structure publication is covered by `tests/php/course-structure-integration.php`; typed publication by the course-authoring/settings integration suites and `PublicationPanel.test.tsx`. Use the environment guards documented in those suites.
