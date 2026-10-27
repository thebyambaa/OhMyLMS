# Learner hub release verification — October 16, 2026

Status (October 19, 2026): this is a dated verification record, not a living checklist. Test counts,
lint totals and translation coverage below are the figures of October 16 and change with every build;
rerun the commands for current numbers. The "Remaining release gates" at the end are still open
unless the ledger in [CONTINUE-LEARNER-HUB.md](../../CONTINUE-LEARNER-HUB.md) says otherwise.

This checkpoint covers the requested code checks, integration verification, browser acceptance,
report additions, translations and local build. Work was done in the live OhMyLMS plugin checkout.
Fixture writes used only the guarded `ohmylms_source_test` installation outside the web root;
its MU plugin blocks outgoing HTTP and email. Nothing was pushed or deployed.

## Changes

- Khan bulk skill reads prepare table names with `%i`. The variable-length `IN` list contains
  literal `%d` placeholders only, with one integer argument per term. Two query-local PHPCS
  annotations explain the static analyzer's inability to count generated placeholders. Table
  interpolation and the old ineffective suppression were removed. Empty, duplicate and missing
  skill IDs are covered against WordPress and MySQL.
- The six flagged authored JavaScript files pass Prettier. Their regenerated shipped source maps
  come from the normal build. Older behavioral/style lint findings remain visible.
- The standalone grading fixture now implements `get_settings()` and checks the duplicate
  registration exception: 16 checks pass. The numerical practice fixture explicitly opts its
  synthetic skills into public practice instead of relying on an obsolete access assumption.
- Parent reports add activity-type totals, UTC daily counts and the ten most active courses.
  Every aggregate uses the existing exact current guardian/link/scope query. Global mastery,
  school activity and unclassified history remain excluded; the activity list still pages by 20.
- Teacher Reports adds class skill levels for 7/30/90 days, with 20 learners per page. It replays
  recorded class quiz outcomes using the Khan up/down rules, starting at Not started for the
  selected window. The global level is never read. Sources must have immutable school/class
  provenance, a matching active distribution and a current recipient; roster membership is
  rechecked. The feature requires the Khan model and provenance storage. Independent classes
  without attributable school activity show an empty state. More than 1,000 skill events for
  one learner/window produces a request for a shorter period, without presenting partial levels.
- The canonical hub's main landmark now encloses content and excludes the navigation rail.
  Sign-in/unavailable pages keep an outer landmark, and embedded shortcodes retain their theme's
  landmark. Unavailable workspace and family report pages have an h1 without revealing a child.
- Mobile navigation moves focus to Close, confines Tab/Shift+Tab to the open rail, makes the
  background inert, restores focus on Escape/Close and clears inert state on desktop resize.
  Closed mobile navigation is inert and hidden from assistive technology. Dark selected-link
  text was lightened after the learner Course overview link measured 4.48:1.

## Verification results

| Check | Result |
| --- | --- |
| Assessment phases: authoring, versioning, bank, deadlines, evidence, math, completion, templates, review, lesson, xp, skip, khan, skillcheck, levelup | 1,049 passed |
| New report authorization/aggregation/large-roster checks | 28 passed; 202 learners, bounded first and last pages |
| Student Home | 92 passed, including canonical/embedded/sign-in landmark checks |
| Teacher classes / family provenance and management | 47 / 56 passed |
| School reports / lifecycle / matrix | 32 / 31 / 19 passed |
| Schools / gradebook / student directory | Passed |
| Learning / player templates / account blocks | 76 / 81 / 44 passed |
| Standalone registry/grading / mastery display | Passed |
| JavaScript and asset parity | 425 passed, 0 failed, 1 skipped; includes three new navigation behavior tests |
| Production build | Passed; two existing webpack bundle-size warnings |
| Reproducibility | 215 generated artifacts byte-identical across two builds |
| Modified PHP syntax / diff whitespace | Passed |

The protected suites use `OHMYLMS_TEST_CREDENTIALS`; no disposable-site guard was bypassed.
Enable mysqli, mbstring, openssl and GD in the CLI runtime. The completion phase needs GD.
Gradebook and student directory take the disposable site path as their argument and need
`WP_ENVIRONMENT_TYPE=local`. The player-template runner now accepts the guarded credential
configuration directly and retains its older read-only local-site invocation.

Full lint is **not clean**: the repository-wide pass reported 14,100 PHP errors and 2,986 warnings,
6,947 JavaScript errors and 27 warnings, and 1,647 CSS errors. `npm run check` passes formatting
and source contracts, then stops at PHP standards. Tests and the build were run separately.
The scoped assessment/report PHP pass has only 14 filename errors across seven PSR-4 class files;
their namespace-to-file mappings are a narrow compatibility exception and remain unsuppressed.
The same scope reports 26 warnings, including the existing Hub reads, 200-course authoring limit
and deliberate fixture database/file operations; the player runner has one additional local-file
read warning. Older integration phase files and reconstructed feature code retain existing debt.
New navigation JavaScript, its tests, report templates and the hub stylesheet pass scoped lint.

## Browser acceptance

Used the built-in browser and the existing View as UI on the disposable installation, without
typing passwords. Verified a teacher's report, a three-assessment Mastered result and pagination
across a 23-student roster. A parent opened the report, removed the disposable link through the
normal confirmed form, and the old bookmark immediately showed the unavailable page without
the learner's name. Learner Connections displayed its active class and assigned teacher.

At 375px, all seven learner sections, seven teacher sections and eight school sections (including
account settings) had one h1, one main landmark and no horizontal overflow. Parent report and
revoked-link views also fit. Light and dark report checks covered rendered text contrast, unique
IDs and landmark structure. The corrected learner link passes the 4.5:1 text threshold. Contrast
observations were taken after theme transitions settled. Keyboard checks covered skip-link focus,
native disclosure activation, menu opening, wraparound focus and Escape returning to Open menu.
The accessibility tree was inspected; a spoken screen-reader session was not run.

Initial static previews supported layout checks; authenticated report, pagination, Connections
and revocation checks subsequently used real HTTP routes. Navigation observations taken before
a response rendered were repeated after the workspace became visible. The ignored browser
record retains both observations. Proof is in `test-results/release-teacher-live.png`,
`release-parent-live.png` and `release-parent-mobile-dark.png`.

Returned to the administrator before cleanup and reset the viewport. Fixture user, activity and
Khan-event count queries returned zero. Six temporary HTML previews were removed; temporary
fixture scripts and the task-owned server were cleaned up. No live 800.local identities, roles,
classes, enrollments, learning records or passwords were changed.

## Khan reference comparison

Observed the existing signed-in [Khan classroom](https://classroom.khanacademy.org/teacher/classes)
dashboard, Activity report and Skills report. No reference account data was changed or copied
into fixtures or documentation.

| Reference pattern | OhMyLMS adaptation and limit |
| --- | --- |
| Dashboard and class navigation: Assignments, Roster, Reports, Class Settings, Browse Subjects | Shared teacher rail and selected-class tabs; a class remains a roster separate from courses. Teacher Tools stays excluded by the user's product decision. |
| Activity report with date range and student rows | Class outcomes are period-filtered and roster-paged. No time-on-task estimate is invented from submission timestamps. |
| Skills report with course/unit/level filters and unit groups | The new class-only report uses expandable learner rows and the same Khan level names/rules. Its period-specific level is separate from overall mastery. A course/unit skill matrix and these additional filters remain parity differences. |
| Broad activity/overall mastery visibility | Existing OhMyLMS provenance and role boundaries are preserved. Independent/home activity is not silently added to a teacher report. |
| Parent dashboard | The richer home report uses the established hub components. A reference parent-dashboard session was unavailable; exact visual parity is unverified. |

This comparison records the remaining differences; it does not certify complete Khan feature parity.

## Translation artifacts

Mongolian (`mn`) is the only bundled translation: `languages/ohmylms-mn.po`, `.mo` and the admin script
catalog `ohmylms-mn-ohmylms.json`, plus the template `ohmylms.pot`. The Spanish, Dutch and Bengali files
that shipped earlier were deleted (commit `0d04837`). The figures in this section are the October 16
snapshot; the catalog has been refreshed since.

The POT then had 9,641 entries. PHP extraction and extraction from changed authored JavaScript
were merged into the existing catalog with [WP-CLI make-pot](https://developer.wordpress.org/cli/commands/i18n/make-pot/).
Unchanged JavaScript/JSON catalog entries were retained. An initial full scan failed when the
bundled tool tried to fetch WordPress block metadata without a working HTTP transport; its
PHP 8.5 parser also emitted extensive deprecations. The targeted refresh succeeded with curl/
openssl enabled and block/theme JSON extraction skipped, since those sources did not change.

`tools/compile-translations.py` requires polib and appends missing PO entries without deleting or
rewriting existing translations. It compiles `ohmylms-mn.mo` and the registered `ohmylms` admin
handle's Jed JSON. All 9,603 original PO entries retained their translation values; 38 untranslated
entries were appended. Coverage remains **3,300 translated of 9,641**, including 2,071 JavaScript
messages. The existing machine-translation/native-review notice is preserved. WordPress loaded
both compiled files and translated My classes in a disposable CLI probe. Recompilation produced
identical hashes.

```sh
python -m pip install polib==1.2.0
python tools/compile-translations.py --locale mn --merge-pot
```

## Remaining release gates

- Triage and repair the older repository-wide lint debt without a blanket baseline or disabled rules.
- Complete untranslated Mongolian strings and native-language review; the catalog is refreshed,
  not fully translated. No other locale is bundled.
- Run a spoken screen-reader acceptance session. Text-contrast/DOM checks do not cover every
  image, icon, focus indicator, browser or assistive-technology combination.
- Email/password card mutations still need the user's password entry under the standing local
  working rule. Outbound email and production/provider integrations were not exercised.
- Capacity beyond the 202-learner test and the 1,000-event report guard, real production migration
  rehearsals and the parity differences above remain separate acceptance work.
