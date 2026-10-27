# xAPI emitter

OhMyLMS can send learning activity to a Learning Record Store (LRS) as xAPI 1.0.3 statements. This is phase 1: OhMyLMS as an activity provider. It does not launch third-party content or act as an LRS (see "Not built yet").

Code: `includes/XApi/`. Configure the LRS under **OhMyLMS > Settings > xAPI**. The bundled **xAPI** add-on under **OhMyLMS > Add-ons > Connections** opens this same Settings tab through **Manage**. Off by default; no additional plugin is required.

The Add-ons switch and the settings screen's **Send statements** checkbox control the same setting. Existing enabled installations retain their configuration when the add-on card is introduced. Enabling the add-on does not send anything until a valid LRS endpoint is configured. Disabling it stops collection and delivery and removes the delivery cron job; queued statements and credentials are retained for re-enabling. Daily retention housekeeping continues while OhMyLMS is active. Deactivating OhMyLMS removes both xAPI cron jobs.

There is no standalone xAPI submenu. The Settings tab and the add-on's **Manage** button remain available while disabled for connection setup. Settings save and delivery actions run in place, without reloading the admin screen. The legacy hidden `admin.php?page=ohmylms-xapi` page and admin-post handlers remain registered for compatibility; their actions return to the Settings tab.

## What is sent

| OhMyLMS event | Hook | Statement |
|---|---|---|
| Enrolled in a course | `ohmylms_after_enrolled_student` | `registered` course |
| Lesson completed | `ohmylms_lesson_completed` | `completed` lesson (parent: course) |
| Course completed | `ohmylms_course_completed` | `completed` course |
| Quiz attempt submitted | `ohmylms_attempt_submitted` | `completed` assessment, with score and duration |
| Quiz attempt graded | `ohmylms_attempt_graded` | `passed` or `failed` assessment, with score |
| Question graded or regraded | `ohmylms_grade_event_recorded` | `answered` interaction, with score, success and response |
| Skill reaches "mastered" | `ohmylms_skill_state_updated` | `mastered` objective |

Choices worth knowing:

- Quizzes are reported through their attempts, not as lessons, so a quiz is never counted twice.
- `completed` carries the score but not `success`; `passed`/`failed` carries `success`. An LRS that counts successes counts each attempt once.
- A regrade is a new `answered` statement. Its `context.extensions` name the grade event it supersedes (`.../extensions/regrade`) and the grader (`.../extensions/grader`: `auto` or `manual`). Nothing is voided.
- Unanswered questions and abandoned attempts (`reason = exit`) are not reported.
- Responses are the text the learner saw, not internal option IDs. `correctResponsesPattern` is never sent, so the LRS cannot be used to read answers.
- One `context.registration` UUID per learner and course ties a learner's statements together.
- Activity IRIs are `{home_url}/xapi/activities/{type}/{id}` (questions use their permanent UUID). They do not change when a slug does.

## Identity and privacy

Learners are sent as an `account` whose name is an HMAC of the user ID, with a key created on first use. No name or email leaves the site by default. Other modes: WordPress user ID, or email (`mbox`; learners without an email are skipped). The key is not a WordPress salt, so rotating salts does not change anyone's identity.

Statement IDs are UUIDv5 of the event, scoped by a per-site random ID. Replaying an event produces the same statement, and two sites sharing an LRS cannot collide.

## Delivery

1. A listener inserts one small row into `{prefix}ohmylms_xapi_outbox` (a kind and a few IDs). Queuing is idempotent and never throws, because several hooks run inside grading code.
2. `Processor` builds the statement when it sends, from append-only data (grade events, question versions, attempts), then POSTs batches of up to 50. It runs every minute from WP-Cron and, on PHP-FPM, at the end of the request after the response is closed. It runs under a database lock.
3. Responses are classified:

| LRS answer | Meaning | What happens |
|---|---|---|
| 2xx | accepted | each statement is sent only if the LRS lists its ID in the answer (see below) |
| 409 | LRS already has it | marked sent |
| network error, 408, 429, 5xx | try again | backoff 1, 2, 4 ... minutes (max 1 hour); failed after 10 tries |
| 401, 403, 404, 405, 3xx | connection is wrong | whole queue pauses 15 minutes, no attempts used |
| other 4xx | LRS refused this content | batch is split to find the bad statement; it fails, the rest are sent |

**A success status is not proof of delivery.** A real LRS (Veracity, lrs.io) rejects a single invalid statement with 400, but for a *batch* it stores the valid statements, drops the invalid one, and answers 200 listing only the IDs it stored. So after a 2xx, a statement whose ID is not in the list is looked up (`GET /statements?statementId=`): stored means delivered (this is what a replay looks like), not stored means it is sent on its own so the LRS's real error is kept and the row fails with it, and an unclear answer means retry later. If the LRS lists nothing at all (for example an empty 204), the success is trusted.

Redirects are never followed, so credentials cannot travel to another host. Rows whose learner or content no longer exists are skipped with a reason. Sent and skipped rows are pruned after the retention period (default 14 days); failed rows after 90.

The screen shows counts, last success, last problem and recent failures, with **Test connection**, **Send queued now** and **Retry failed**.

**Test connection** reads the LRS `about` resource and then posts an empty list of statements. The second step matters: LRSs commonly answer `about` without checking the key, so `about` alone says "connected" even with a wrong secret. An empty POST needs the same write permission as sending and stores nothing. If an LRS refuses an empty request, the test reports the address as working and the key as not yet checked.

## Configuration

Set in the screen, or fix in `wp-config.php` (a constant always wins and keeps the secret out of the database):

```php
define( 'OHMYLMS_XAPI_ENDPOINT', 'https://lrs.example.com/xapi' );
define( 'OHMYLMS_XAPI_KEY', '...' );
define( 'OHMYLMS_XAPI_SECRET', '...' );
```

Filters and actions: `ohmylms_xapi_account_homepage` (account home page IRI), `ohmylms_xapi_request_args` (HTTP arguments), `ohmylms_grade_event_recorded` (fires in `GradeEvents::record`, inside the grading transaction; listeners must not throw), `ohmylms_xapi_loaded`.

## Tests

```
php tests/xapi-unit.php                                              # pure: UUIDs, scores, durations, identity, response codes
php tests/xapi-addon-unit.php                                        # add-on switches, upgrade, schedules and deactivation
php tests/php/assessment-integration.php xapi                        # isolated test site: hooks, real attempt and regrade, fake LRS, failures
```

The integration suite fakes the LRS through `pre_http_request`. The test site's isolation plugin blocks all HTTP at `PHP_INT_MAX`, so the fake registers at the same priority and answers only for `lrs.example.test`. The fake models real behaviour as well as the spec, including an LRS that drops an invalid statement from a batch and answers 200.

### Live smoke test against a real LRS

A fake only knows what its author assumed, so there is also a manual suite for a real LRS. Use a throwaway LRS (a free account on lrs.io or SCORM Cloud is enough) and never a production site:

```
OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json \
OHMYLMS_LRS_CREDENTIALS=/path/lrs.json \
php tests/php/assessment-integration.php xapi-live
```

`lrs.json` is `{ "endpoint": "https://…/xapi", "key": "…", "secret": "…" }`; the key needs write and read permission. The suite runs the whole flow with fake users on the isolated test site, opens a pass-through for the LRS host only, reads every statement back by ID and compares it with what was sent, then tries a replay, a batch with an invalid statement, a wrong secret and a wrong URL. Statements stay in the LRS afterwards; clear them from its admin.

First run against Veracity (lrs.io), 2026-10-13: all 11 statements of a quiz flow were stored exactly as sent, a replay did not duplicate, queries by learner worked, and the two defects described above (silently dropped statements, and a "Connected" test with a wrong secret) were found and fixed.

## Coding standards

`includes/XApi/` is clean under `phpcs.xml.dist` except for one documented compatibility exception:

- **File names.** WordPress prefers `class-*.php`; this plugin autoloads `OhMyLMS\` from `includes/` by PSR-4, which needs the class name as the file name. Every PSR-4 file in the plugin, including `Integration.php`, has the same two `WordPress.Files.FileName` findings, and `docs/CODING-STANDARDS.md` requires PSR-4 mappings to stay compatible, so these findings are reported, not suppressed.

Suppressions in this module are narrow and carry their reason in the comment:

- `WordPress.DB.DirectDatabaseQuery` (direct query, no caching) at the top of each class that reads or writes the plugin's own queue tables. There is no WordPress API for custom tables, and queue workers must read current rows.
- `WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode` on the one line that builds the HTTP Basic header, which is defined as base64.
- `WordPress.DB.PreparedSQL*` on the single `IN (...)` in `Outbox::mark_sent()`, whose placeholders are `%d` repeated once per validated integer ID.

Table names use the `%i` identifier placeholder (WordPress 6.2+; the plugin requires 6.8), so no table name is interpolated into SQL.

Check it with `tools/coding-standards/vendor/bin/phpcs --standard=phpcs.xml.dist includes/XApi` (install the tools first with `composer install --working-dir=tools/coding-standards --no-plugins --no-scripts`). The `tests/` directory is outside the ruleset.

## Not built yet

- Video progress statements (`VideoProgressTracker`) and assignment submissions.
- `abandoned` for attempts left unfinished, and `attempted` when an attempt starts.
- Backfill of history from before the emitter was enabled.
- Cleanup of tables and options on uninstall.
- xAPI 2.0 (the cmi5 specification is still tied to 1.0.x, so 1.0.3 is the compatible choice).
- Phase 2: cmi5 launch, an LRS endpoint inside OhMyLMS, and package import.
