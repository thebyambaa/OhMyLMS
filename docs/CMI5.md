# cmi5 support (phase 2): design

Status: **design only, nothing built.** Written 2026-10-13 after the xAPI emitter (phase 1, `docs/XAPI.md`) merged. Open decisions are at the end; the spikes behind the choices are in "What was verified".

## Goal and scope

Let OhMyLMS import a cmi5 course package (Storyline, Rise, Captivate or hand-written), launch its assignable units (AUs) for a learner, record what the AU reports, and mark the matching lesson complete when the AU's `moveOn` rule is met.

In scope: import `cmi5.xml` packages, host their files, launch, an LRS endpoint for AUs, `moveOn` evaluation, `launched`/`abandoned`/`satisfied` statements, forwarding stored statements to the external LRS from phase 1.

Out of scope for the first release: authoring cmi5, `waived`, launch modes other than Normal, `entitlementKey`, non-HTTP launch URLs (`app://`), full xAPI LRS conformance (see decision 4).

## What was verified (and what was not)

Spec facts below come from the cmi5 spec text (`AICC/CMI-5_Spec_Current`, commit 818b03a). The last ~2.7k characters, including parts of section 14 (ZIP rules), were not read.

| Requirement | Section |
|---|---|
| Launch URL gets `endpoint`, `fetch`, `actor` (JSON Agent, SHOULD NOT carry sensitive PII), `registration`, `activityId` | 8.1 |
| AU POSTs once to `fetch`; the URL MUST NOT return a token twice; token is used as HTTP Basic on every xAPI request; token MUST be limited to the session | 8.1.2, 8.2 |
| Fetch **errors use HTTP 200** with `error-code` 1 (already used or expired), 2 (security), 3 (other) | 8.2.2, 8.2.3 |
| LMS MUST record `launched` before launching, with sessionid, launchMode, launchURL, moveOn (and masteryScore, launchParameters when set) | 9.3.1, 9.6 |
| AU sends `initialized` first and `terminated` last; at most one of passed/failed; completed and passed once per registration | 9.3 |
| No `terminated`: LMS detects it when the same registration starts a different AU, then records `abandoned` with a duration and accepts nothing more for that session | 9.3.6 |
| `satisfied` is about a Block or the Course, not an AU; LMS SHOULD NOT send it twice | 9.3.9 |
| `moveOn`: Passed, Completed, CompletedAndPassed, CompletedOrPassed, NotApplicable (default). Block satisfied when its AUs are; course when all AUs and blocks are | 13.1.4 |
| LMS MUST write the `LMS.LaunchData` state document before launch (contextTemplate, launchMode, moveOn, plus masteryScore, launchParameters, returnURL when set); AU must not change it. `launchMethod` is **not** in it | 10 |
| LMS must serve State and Agent Profile (`cmi5LearnerPreferences`); LMS MUST NOT give the AU credentials that can void; LMS MUST void non-rejected statements that break section 9 | 6, 11 |
| The LMS **MUST implement an LRS**; the spec treats LMS and LRS as one entity | 6, 3 |
| Package: `cmi5.xml` (ZIP, 32/64-bit, or standalone); URLs relative or absolute; query strings are merged with the launch parameters; `masteryScore` is 0..1 | 13, 14 |

**Spike on the isolated test site (WordPress 7.1.2, PHP built-in server):**

- A correct application password on a REST route authenticates; a **wrong one is ignored** (request continues anonymously, no 401), whether or not the username is real and whether or not that user has application passwords. An earlier concern that core would reject a cmi5 token was **wrong** on this version.
- A Basic token with no colon never reaches `PHP_AUTH_USER`, so core does not look at it.
- The REST **CORS preflight does not allow `X-Experience-API-Version`** (nor `If-Match`/`If-None-Match`), which every xAPI request carries, so a browser would block the AU. A custom endpoint answered the same preflight correctly.
- A custom endpoint outside REST never runs WordPress authentication.

**Not verified:** how `Authorization` reaches PHP on the production web server (nginx/FPM or Apache; only the PHP built-in server was tested), behaviour with real Storyline/Rise packages, the unread part of the spec, the CATAPULT LMS test suite (needs an LMS integration written for OhMyLMS).

**Existing code worth knowing:**

- `ohmylms_register_lesson_type( $id, [ 'label', 'render' ] )` exists; `Extensions\Bootstrap::lesson()` calls `render( $lesson )` for `$lesson->get_type()`. A cmi5 AU can be a registered lesson type, no core change.
- `ScormImporter::extract_package()` calls `ZipArchive::extractTo()` with no size, count or file-type limits. That was safe because the extracted files are deleted afterwards. cmi5 must keep them web-accessible, so it needs its own extractor (see Security).
- Phase 1 already provides the pieces cmi5 needs: `Identity::actor()` (pseudonymous Agent, matches the "no sensitive PII" rule), `Identity::registration()` (one UUID per learner and course, which is what cmi5 means by a registration), the outbox and processor for forwarding, and `Vocabulary`.

## Design

**Mapping.** An imported package becomes one OhMyLMS course. `<block>` becomes a chapter, `<au>` becomes a lesson of type `cmi5`. The cmi5 registration is `Identity::registration( student, course )`. Course and AU ids from the package are stored; `activityId` is generated per AU and kept stable per registration (8.1.5).

**Tables** (`{prefix}ohmylms_cmi5_*`, versioned like `XApi\Schema`):

| Table | Holds |
|---|---|
| `packages` | course IRI, course_id, content hash, storage folder, file count, bytes, status |
| `blocks`, `aus` | structure from `cmi5.xml`: IRIs, launch URL, moveOn, masteryScore, launchMethod, launchParameters, lesson_id or chapter_id, position |
| `sessions` | session UUID, registration, student, AU, state (launched, initialized, terminated, abandoned, expired), **hashed** token, fetched_at, launched_at, last_seen_at |
| `progress` | per registration and AU: completed_at, passed_at, failed_at, satisfied_at |
| `lrs_statements`, `lrs_state`, `lrs_profile` | stored statements, State documents (with ETag) and Agent Profile documents |

**Launch.**
1. Learner opens the lesson; `render()` shows a launch button (not auto-launch).
2. Server creates a session, stores `LMS.LaunchData`, records `launched` (before anything else), and returns the launch URL with `endpoint`, `fetch`, `actor`, `registration`, `activityId`. `endpoint` is the custom LRS URL, `fetch` is a one-time URL for this session.
3. AU POSTs `fetch`. First call returns `{"auth-token": ...}` (random, stored hashed, bound to session, actor and registration); a second call returns the error object with code 1, still HTTP 200.
4. AU talks to the LRS endpoint with `Authorization: Basic <token>`.

**LRS endpoint.** A custom endpoint, not `/wp-json`, handled early on `init`: it needs exact xAPI headers (ETag concurrency, `X-Experience-API-Version`, raw non-JSON State bodies), and the REST CORS shim would have to fight core. Minimum resources: `statements` (POST, PUT, GET with registration/agent/verb filters), `activities/state`, `agents/profile`, `about`. Authorization is the session token only: the handler never reads a WordPress user, never reads cookies, and never sends `Allow-Credentials`, so a logged-in admin's browser cannot be used to ride cookies. The token may write only its own actor and registration, only while the session is open, and can never void.

**Rules on stored statements** (9.3, 9.6): require registration, actor and sessionid to match the session; `initialized` first, nothing after `terminated` (after a short grace period) or `abandoned`; no duplicate cmi5 verbs; passed and failed exclusive; score against `masteryScore`. Reject what breaks these, void what must be stored but conflicts.

**Progress.** On `completed`, `passed` or `failed`, evaluate the AU's `moveOn`. When met: complete the lesson the way `Quiz\Submission` does (`complete_lesson()` then `ohmylms_lesson_completed`), so xAPI emission, webhooks, XP and course completion keep working with no new wiring. When every AU in a block or the course is satisfied, record `satisfied` for it (once).

**Abandonment.** When a registration launches a different AU, or on a cron sweep for sessions idle past a limit, record `abandoned` with duration (launched to last statement) and close the session.

**Forwarding.** Each stored statement is also queued as a new outbox kind (`stored_statement`) and sent by the existing processor, so an external LRS gets the AU's own statements as well as OhMyLMS's.

## Security

- **Package files.** Extract with our own routine: reject absolute paths and `..`, cap total bytes, file count and per-file size, and **allow-list extensions** (html, htm, js, css, json, xml, images, audio, video, fonts, svg, wasm, txt). Never store `.php`, `.phtml`, `.phar`, `.htaccess`, `.user.ini`, `web.config`. Only users with a dedicated capability may upload.
- **Serving.** Through a gatekeeper endpoint, not the raw uploads folder, so PHP cannot execute whatever the server config says, with `X-Content-Type-Options: nosniff`, content type from the allow-list, and `Content-Security-Policy: sandbox allow-scripts allow-forms allow-popups` (no `allow-same-origin`). Package JavaScript then runs in an opaque origin and cannot read WordPress cookies or REST nonces. If a package needs `localStorage` or cookies it will fail under that sandbox, so the alternative is a separate cookieless content origin (constant `OHMYLMS_CMI5_CONTENT_ORIGIN`). Which one to default to needs a test with real packages.
- **Token.** 32+ random bytes, only a hash stored, single fetch, dies with the session, rate-limited, body-size capped.
- **Privacy.** `actor` is the phase 1 pseudonymous Agent; no name or email goes to third-party content by default.

## Phasing

1. **2a, import and launch:** extractor, `cmi5.xml` parser (schema-validated), tables, lesson type, gatekeeper, launch and fetch.
2. **2b, LRS and progress:** the endpoint, statement rules, `moveOn`, completion, `satisfied`, abandonment.
3. **2c, forwarding and admin:** outbox kind, import screen, session list, a self-test button that calls the endpoint with an `Authorization` header (catches web servers that strip it).
4. Later: `waived`, Browse/Review, `entitlementKey`.

**Testing:** unit tests for the parser, extractor limits and rule engine; an integration suite on the isolated test site driven by a small **reference AU** I would write (fetch, initialize, complete, terminate, plus misbehaving variants); then real packages exported from Rise or Storyline; then the ADL CATAPULT LMS test suite, which needs an OhMyLMS integration written first.

## Open decisions

1. **Go or no-go.** Who needs cmi5? If customers mostly bring SCORM, the existing importer may be enough. My recommendation: proceed only if at least one named user wants Storyline or Rise content.
2. **Content origin.** Default to the CSP sandbox, or require a separate content domain? Recommendation: sandbox by default, separate origin as a setting, decided after testing two real packages.
3. **Who may upload packages.** Recommendation: a new capability, granted to administrators only at first.
4. **LRS scope.** The spec requires a full LRS, and cmi5 conformance expects it to pass the ADL LRS test suite. Recommendation: build the subset above, label it "cmi5 AU compatible", and treat full conformance as a separate later project. Say so if certification is a requirement; that changes the estimate considerably.
5. **Lesson type or block.** Recommendation: lesson type, because completion and gating attach to lessons.
6. **Retention** of stored AU statements and State documents (they can be large). Recommendation: keep indefinitely for statements, prune State when a registration is deleted.
