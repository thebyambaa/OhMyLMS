# Enforcement checks

Roadmap step 10 (see [the modularization roadmap](MODULARIZATION-ROADMAP.md)). These checks keep
the typed admin app (`assets/src/app`) independent of the recovered runtime, and keep its published
bundles (`assets/dist/admin`) small and free of duplicate React or WordPress code. CI also runs the
Node tests, ESLint, the PHP unit and integration suites and the WordPress coding standards on every
push and pull request to `main`.

## Run locally

| Command | What it runs |
| --- | --- |
| `npm run check:ci` | `typecheck`, `typecheck:js`, `test:app`, `check:architecture`, then `node --test tests/js/check-architecture.test.mjs` |
| `npm run typecheck:js` | `tsc` on the JavaScript of `assets/src/features` and `assets/interactivity` through JSDoc (see [Type checking the native and learner JavaScript](#type-checking-the-native-and-learner-javascript)) |
| `npm run check:architecture` | `node tools/check-architecture.mjs`: the architecture rules below and the budget table |
| `node --test tests/js/check-architecture.test.mjs` | Tests for the checker, using fixture trees under `tests/fixtures/architecture/` |
| `npm test` | `node --test` on `tests/js/*.test.mjs`. The PHP/React parity tests need `php` on `PATH` (or `OHMYLMS_PHP`) |
| `npm run lint:js` | ESLint with the WordPress config and Prettier on the authored JavaScript and TypeScript |
| `npm run test:php` | `node tools/run-php-tests.mjs unit`: the PHP suites that stub WordPress |
| `npm run test:php:integration` | `node tools/run-php-tests.mjs integration`: the PHP suites that load a real WordPress |
| `npm run lint:php:changed` | `node tools/phpcs-changed.mjs [base]`: phpcs errors on lines changed since `origin/main` |

The architecture check reads source files and the bundles already published in `assets/dist/admin`.
It never builds anything. After you rebuild the admin app, run it again to check the new bundles.

## CI jobs

`.github/workflows/ci.yml` runs six jobs in parallel. npm downloads are cached by
`actions/setup-node`, Composer downloads by `ramsey/composer-install`.

| Job | Runs |
| --- | --- |
| **checks** | Node from `.nvmrc` and PHP 8.2; `npm ci`, `npm run build`, `npm run check:ci`, `npm test` |
| **lint-js** | `npm ci`, `npm run lint:js`, `npm run lint:css` |
| **php** | PHP 8.2: `php -l` on every file in `includes/` and `tests/php`, then `node tools/run-php-tests.mjs unit` |
| **phpcs** | `composer install` in `tools/coding-standards`, then `node tools/wp-standards.mjs -n` (errors in every file) and `node tools/phpcs-changed.mjs <base>` (errors and listed warnings on lines changed since the pull request's base commit, or the previous `main` commit on a push) |
| **browser** | The plugin checked out to `wordpress/wp-content/plugins/ohmylms` with WordPress's `wp-includes` beside it (the interactivity fixture serves the Interactivity API from there), PHP 8.2 with mbstring, `npm ci`, `npx playwright install --with-deps chromium`; then `npx playwright test -c tests/<name>.config.cjs` for `interactivity`, `dropdown` and `mathlive` with `OHMYLMS_BROWSER_CHANNEL=''` (Playwright's Chromium instead of Edge). Each config starts its PHP fixture server (`tests/fixtures/<name>.php`; no WordPress, no database). Locally, a PHP without `php.ini` needs `OHMYLMS_PHP_ARGS`, for example `["-d","extension_dir=C:/php8.5.4/ext","-d","extension=mbstring"]`, and `OHMYLMS_PHP_BIN` |
| **php-integration** | MySQL 8.0 service container on port 10005; `poppler-utils`; `npm ci` and `npm run build:sdk` (the webpack entries in `build/sdk` for `extension-host-assets-integration`); `tools/install-test-wordpress.sh` installs WordPress at `xyz/app/public` (the Local layout `figure-assets-integration` checks for) with wp-cli, copies the plugin (and `build/sdk`, which git ignores) to `wp-content/plugins/ohmylms` and activates it; `tools/install-test-sites.sh` installs the disposable sites (below); `wp server` on `ohmylms.test:8080`; then `node tools/run-php-tests.mjs integration` (with `OHMYLMS_TEST_CREDENTIALS` and `OHMYLMS_TEST_PDFTOPPM=/usr/bin/pdftoppm`) and `tests/js/randomized-preview.test.mjs` against that site |

`tools/install-test-sites.sh <dir>` installs three disposable sites, each with the database its suites
check for: `source` (`ohmylms_source_test`), `migration` (`ohmylms_gutenberg_migration_20261020`) and
`streak` (`ohmylms_streak_test`). Each one has `OHMYLMS_TEST_SITE`, the isolation mu-plugin
(`tests/fixtures/isolation.php`: no outbound HTTP or mail), the administrator `ohmylms-test-admin` and
Mathematics as the default subject profile; `source` also enables the `examples` module. The script writes
`<dir>/test-credentials.json` (`username`, `password`, `site`, and `sites`: database to WordPress root).
MySQL defaults to `127.0.0.1:10005`, the port of `tools/create-test-site.py`. The script refuses a database
that already exists before its site folder does, so it never takes over a site that `create-test-site.py`
made.

### PHP suites: `tools/run-php-tests.mjs`

The runner runs each PHP file directly in `tests/` and `tests/php/` in its own process (subfolders hold
shared fixtures; `*-fixture.php` files seed data for the browser specs and are not run). A file without
`wp-load.php` is a **unit** suite. An **integration** suite is classified by how it finds WordPress:

| Kind | Bootstrap | What the runner sets |
| --- | --- | --- |
| local-site | `dirname( __DIR__, 5 ) . '/wp-load.php'` | `1` for every `'1' !== getenv( 'X' )` gate in the file (for example `OHMYLMS_COURSES_TEST`); `OHMYLMS_TEST_HOST` is passed on (the suites default to `800.local`) |
| argv-site | `$argv[1] . '/wp-load.php'` | The WordPress root as the first argument |
| site-path | `OHMYLMS_TEST_SITE_PATH` | The WordPress root, unless the variable is already set |
| credentials | `OHMYLMS_TEST_CREDENTIALS` | The `site` of the credentials file as the first argument, for suites that read `$argv[1]` |

A suite that checks `DB_NAME` against a dedicated database (`ohmylms_source_test`, the migration or the
streak database) and every credentials suite run against the disposable site with that database, from the
`site` and `sites` of `OHMYLMS_TEST_CREDENTIALS`: as `OHMYLMS_TEST_SITE_PATH` or as the first argument. A suite
that refuses to run without `--local` (`figure-assets-integration`) gets it. Without the site, or without
`OHMYLMS_TEST_PDFTOPPM` for a suite that reads it, the suite is not run; when `CI` is set it fails instead.

`node tools/run-php-tests.mjs integration --list` shows the kind, gates and required database of every
suite. Name files to run only those. Environment:

- `PHP_BINARY`: the PHP executable (otherwise `php` on `PATH`, then Local's PHP).
- `OHMYLMS_TEST_DB_PORT`: sets `mysqli.default_port`. Local's `wp-config.php` has `DB_HOST` `localhost`
  without a port, so use `10006` there. CI puts the port in `DB_HOST` instead.
- `OHMYLMS_TEST_WP_ROOT`: the WordPress root for argv-site and site-path suites (default: three folders
  above the plugin).
- `OHMYLMS_TEST_HOST`: the host header for local-site suites.
- `OHMYLMS_TEST_CREDENTIALS`: the credentials file of `tools/install-test-sites.sh` or
  `tools/create-test-site.py`.
- `OHMYLMS_TEST_PDFTOPPM`: a `pdftoppm` binary (poppler-utils; MiKTeX ships one on Windows).

When PHP has no `php.ini` (a bare Windows PHP), the runner writes a temporary one that loads mbstring,
mysqli, openssl, curl, sodium, zip, intl, gd, fileinfo and exif from the PHP `ext` folder.

Against the Local site:

```powershell
$env:PHP_BINARY = 'C:\php8.5.4\php.exe'; $env:OHMYLMS_TEST_DB_PORT = '10006'
npm run test:php
npm run test:php:integration
```

### Skipped and expected-to-fail suites: `tests/php-suites.json`

Each entry is a group of files with a `reason`. Listed files are not run, except:

- `"skipIn": "ci"`: skipped only when the `CI` variable is set (GitHub Actions sets it), for a suite that
  passes on the Local site but needs something a clean install lacks. No group uses it now.
- `"expect": "fail"`: suites that fail at HEAD. They still run; the job fails if one of them passes, so
  remove its entry when it is fixed. No group uses it now.

`--all`, or naming a listed file, runs it anyway. The runner fails when an entry names a missing file, a
file of the wrong kind, or has no reason. Not run in CI: suites that need the sibling
`ohmylms-custom-question` or `ohmylms-qpay` plugins or CreatorLMS, plus the non-suite helpers and the
`php -S` routers (`exam-import-http`, `pdf-exam-http`). Every other suite runs in CI, including the
disposable-site, migration and streak suites.

Suites create what they read; none depends on content that happens to be on the Local site. The six that
did until 2 November 2026:

| Suite | What it needs and where it comes from |
| --- | --- |
| `course-carousel-integration` | A tagged published course, created by the suite and deleted when it exits. |
| `extension-host-assets-integration` | Defines `OHMYLMS_SOURCE_ASSETS` for its own process (first definition wins over `wp-config.php`): source mode against `build/sdk`, then shipped mode in a child process. CI builds `build/sdk`; without it only shipped mode runs, and with `CI` set the suite fails. |
| `gradebook-rest-integration` | Its tagged roster, purchase and self enrolments; it reads them from the migrated `ohmylms_enrolments` table (`ohmylms_enrolments_read_new`), because a new install has not run the enrolment migration. |
| `lesson-settings-integration` | A tagged teaching syllabus with a unit, a group and three objectives, removed in `finally`. |
| `questions-rest-integration` | Add-on types are checked with a question type registered for the run; the `graded-reader` check runs only when `ohmylms-graded-reader` is active and otherwise prints that it did not run. |
| `skill-workspace-rest-integration` | Its fixture subject gets the built-in Mathematics profile (domains such as algebra) through an option filter for the request, whatever the site's default profile. |

`assessment-integration` (disposable `source` site) runs the `authoring` phase and every phase in
`tests/php/assessment/` except `xapi-live`, which needs a real LRS (`OHMYLMS_LRS_CREDENTIALS`); name phases
to run only those. Its question fixtures use the typed routes (`POST quizzes/{id}/questions/new`,
`POST|PATCH questions`, `quizzes/{id}/questions/*`).

### WordPress coding standards: `npm run lint:php` and `tools/phpcs-changed.mjs`

`npm run lint:php` checks every file in `phpcs.xml.dist`. Every file has had 0 errors since 2 November
2026 (2,124 cleared: 1,011 by phpcbf, the rest by hand; false positives carry a narrow
`phpcs:ignore Sniff -- reason`), and CI fails on any error with `node tools/wp-standards.mjs -n`. About
2,400 legacy warnings remain and are not enforced. `tools/phpcs-changed.mjs` diffs the
working tree against the merge base with the given revision, runs phpcs on the changed PHP files and
fails on errors in added or changed lines. Warnings on those lines are printed; findings on untouched
lines are counted but not reported. New files must be committed (or staged) to be diffed.

### Line endings

`.gitattributes` stores JavaScript, TypeScript, PHP, CSS, JSON, Markdown, YAML and shell files with LF
and checks them out with LF even when `core.autocrlf` is `true`. Files checked out before that rule may
still have CRLF on Windows; `.prettierrc.cjs` sets `endOfLine: 'auto'` so Prettier and ESLint accept
them, and Git stores LF when they are committed. `git add --renormalize .` is not needed: the index
already holds LF.

## Type checking the native and learner JavaScript

`npm run typecheck:js` (part of `check:ci`) runs `tsc --noEmit` on two projects. Their files stay
JavaScript: TypeScript reads them with `allowJs` and `checkJs` and the types come from JSDoc
(`@param`, `@return`, `@typedef`, `@type`). Nothing is compiled by `tsc`; webpack and Babel are
unchanged.

| Project | Covers | Notes |
| --- | --- | --- |
| `assets/src/features/tsconfig.json` | The native editor and block modules (111 files) and what they import from `assets/src/ui`, `assets/src/app/extensions` and `assets/interactivity/puzzles.mjs` | `jsx: react-jsx` for typing (the program also reads the app's `ExtensionBoundary.tsx`); files that keep a `/** @jsx createElement */` pragma are checked with it. Rule o below checks the `createElement` binding webpack needs |
| `assets/interactivity/tsconfig.json` | The learner script modules and `features/math/loader.mjs` | `paths` mirrors the import map (`ohmylms/interactivity` is `sdk.js`, `ohmylms/checkout`, `ohmylms/questions`, `ohmylms/video-<provider>`) |

Types of packages:

- `@wordpress/element`, `components`, `data`, `i18n`, `api-fetch`, `compose`, `hooks` and the other
  installed packages use their own `build-types`. `@types/react` 18 supplies React.
- Editor packages WordPress provides but npm does not install here (`@wordpress/blocks`,
  `block-editor`, `editor`, `edit-post`, `plugins`, `interactivity`, `interactivity-router`) are declared
  in `assets/src/features/types/wordpress-modules.d.ts` and `assets/interactivity/wordpress-interactivity.d.ts`,
  only as far as these modules use them. Add a declaration there when you use more of their API.
- `@wordpress/data` maps to `assets/src/features/types/wordpress-data.d.ts`: the package's own types,
  plus overloads for the stores these modules name with a string (`core`, `core/editor`,
  `core/block-editor`, `core/edit-post`, `ohmylms/paper`) with the selectors and actions in use.
- Page data printed by PHP (`ohmylmsNativeLesson`, `ohmylmsMath`, …) and third-party globals are typed in
  `assets/src/features/types/globals.d.ts` and `assets/interactivity/globals.d.ts`.
- `question-editor/extendedModel.d.mts` and `questionBlocks.d.mts` describe their `.mjs` for the app and
  for these modules alike; a new export needs a declaration there.

Writing JSDoc that TypeScript reads:

- Components return `{React.ReactElement}` (or `|null`) and take `{React.ReactNode}` children. `{Element}`
  is the DOM element to TypeScript.
- Mark props and options with a default or that callers may leave out as optional: `[props.help]`.
- DOM queries return `Element`; cast once where the element type is known:
  `/** @type {HTMLInputElement|null} */ ( root.querySelector( 'input' ) )`.
- Event handlers that read `key`, `detail` or a typed `target` annotate the parameter:
  `( /** @type {KeyboardEvent} */ event ) => …`.

Both projects are `strict` since 1 November 2026, with no folder excluded. The table shows the
non-strict errors fixed on 31 October 2026 and the strict errors fixed on 1 November 2026 (all now 0).
Untyped spots are a few documented `Record<string, any>` typedefs for data whose shape depends on the
question type, block or server response, plus two narrow `any` casts in `question-editor` (a
`FormTokenField` token and the `wp.media` frame); ESLint's `jsdoc/reject-any-type` lists them.

| Folder | Non-strict before | After | Strict errors fixed |
| --- | ---: | ---: | ---: |
| `features/question-editor` | 265 | 0 | 735 |
| `features/learning` | 175 | 0 | 292 |
| `features/paper` | 141 | 0 | 482 |
| `features/math` | 50 (+9 in the learner project) | 0 | 101 (+5) |
| `features/content-hub` | 45 | 0 | 387 |
| `features/question-bank` | 10 | 0 | 126 |
| `features/students` | 6 | 0 | 20 |
| `features/navigation` | 5 | 0 | 61 |
| `features/assessment` | 3 | 0 | 115 |
| `features/curriculum` | 0 | 0 | 83 |
| `features` root files | 3 | 0 | 3 |
| `ui` and `app/extensions` (imported) | 12 | 0 | 7 |
| `interactivity` root files | 195 (+5 in `puzzles.mjs` via features) | 0 | 738 |
| `interactivity/catalog` | 26 | 0 | 48 |
| `interactivity/quiz-player` | 23 | 0 | 111 |
| `interactivity/video` | 13 | 0 | 190 |
| `interactivity/ui` | 11 | 0 | 99 |
| `interactivity/paper` | 9 | 0 | 46 |
| `interactivity/forms` | 4 | 0 | 36 |
| `interactivity/account` | 3 | 0 | 39 |
| `interactivity/reviews`, `assignment`, `lesson` | 0 | 0 | 19 |

A new file must pass under `strict`; prefer a typedef that follows the PHP contract over `any`.

## What `tools/check-architecture.mjs` enforces

Imports are found with `@babel/parser`, which ignores comments. They are resolved the way the bundler
resolves them: relative paths, extensions, `index.*` files, and `.js` specifiers that point to `.ts`
files. The checker reads static imports, `export … from`, dynamic `import()` and `require()`.
Package imports are not followed. Tests (`*.test.*`, `*.spec.*`, `test/` folders) and `.d.ts` files
are not shipped, so they are skipped.

### a. The app does not reach recovered runtime code

The checker starts at every app file and follows runtime imports through `assets/src`. Type-only
imports are skipped because the build erases them. It fails when one of these files can be reached:

- any file under `assets/src/application/`, `modules/`, `runtimes/` or `static/`;
- the recovered SDK: `assets/src/extensions/index.jsx`, `lazyFeatures.js` or `LazyFeature.jsx`;
- any file listed in a `components.json`;
- any module that reads `readRuntime` (including `createComponent(readRuntime)`), refers to
  `webpackChunkohmylms`, or loads numeric webpack module IDs (`__webpack_require__(N)`, or `n(N)`
  next to the `n.d`/`n.r` helpers).

Most of these files were deleted at the cutover ([CUTOVER.md](CUTOVER.md)); the rule keeps them, or code like them,
from coming back. `assets/src/ui/**` is never flagged for its own
content, but the checker still follows its imports. The extension registry lives in the app
(`assets/src/app/extensions/registry.mjs`). Authored modules elsewhere in `assets/src`
(for example `features/question-editor/coreEditorComponents.js`) are allowed as long as nothing
they import reaches the list above. Nothing is allowed by name.

Each failure names the app file, the import that leaves the app, and the shortest chain to the
recovered module:

```text
assets/src/app/x.ts imports assets/src/features/legacy/index.js, which reaches recovered runtime code:
assets/src/features/legacy/index.js -> assets/src/features/legacy/Widget.jsx (listed in assets/src/features/legacy/components.json)
```

### b. No import cycles in `assets/src/app`

The checker groups files into strongly connected components using static and dynamic runtime
imports. It reports each cycle once, with an example path. Cycles made only of type imports are
allowed.

### c. Features use each other only through public entry points

A file in `assets/src/app/features/<a>/` may import from `features/<b>/` only through
`features/<b>/index.ts` (or `index.tsx`) or a file named `public.ts` (or `public.tsx`). Type
imports are included, because they couple features too.

### d. No duplicate runtimes in the admin bundles

The rule checks `assets/dist/admin/admin-app*.js` and `assets/dist/admin/chunks/admin-app-*.js`.
A bundle fails when it carries its own copy of React, ReactDOM, the scheduler, or an `@wordpress/*`
package that WordPress provides. WordPress-provided means any `@wordpress/*` package that
`@wordpress/dependency-extraction-webpack-plugin` does not list in `BUNDLED_PACKAGES`. Bundling
`@wordpress/icons` is therefore expected. The rule looks for these signs:

- React build markers (`react.production`, `scheduler.production`);
- definitions of `__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED` or of `ReactCurrentOwner`
  (reading them from the WordPress copy is fine);
- `node_modules/<package>/` module IDs in the bundle;
- the package in the source map's `sources`;
- React in the bundle's `.LICENSE.txt`.

The dependencies in `admin-app.asset.php` must also include `react`, `react-dom` and `wp-element`.

### e. Per-screen size budgets

The rule measures the raw size and the gzip size (level 9) of `admin-app.js`,
`admin-app-extensions.js`, `admin-app.css`, and every `chunks/admin-app-*` JS and CSS file. CRLF is
folded to LF first, so Windows and Linux checkouts give the same numbers. The content hash is
removed from chunk names (`chunks/admin-app-lessons.1567f15f9da9.js` becomes
`chunks/admin-app-lessons.js`). The sizes are compared with `tools/asset-budgets.json` and printed
as a table.

The check fails when:

- an asset is over its raw or gzip budget;
- an asset has no budget;
- a budget has no published asset;
- the same chunk is published twice under different hashes.

### g. Features use the shared hooks instead of their own copies

`useUnsavedChangesGuard` and `usePagedList` live once, in `assets/src/app/hooks`. A file under
`assets/src/app/features/` fails when it declares a function, class or variable with one of those
names at its top level, or is itself named after the hook (`useUnsavedChangesGuard.ts`, including
re-export shims). When a screen needs different behaviour, add a backwards-compatible option to the
shared hook (for example `ignoreSamePath`, `inAddress`, `keepPrevious`) instead of copying it.

`tools/architecture-rules/shared-helpers.mjs` applies the same idea to the request hooks, the response
validators and the media parser, in every source file of `assets/src/app` except their home module and
tests. A file fails when it:

- declares `useRequest` or `useOperation` at its top level or is named after one of them
  (`assets/src/app/hooks/useRequest.ts` offers `key`, `keepData`, `errorShape: 'message' | 'object'` and
  `replace`; `hooks/useOperation.ts` offers `errorShape` and an optional success callback);
- declares a one-argument `record`, `text` or `positiveId` (function or arrow). Bind the shared ones in
  `assets/src/app/lib/validation.ts` to the feature's own error instead, so its message stays:
  `const { record, text } = createValidators( invalidResponse );`. A message string becomes an
  `invalid_response` `ApiError` with status 502; a factory's error is thrown as it is;
- reads `media_details` (a member access or destructuring) instead of calling `parseMediaImage` from
  `assets/src/app/lib/media.ts` with its `accept` (mime types) and `fallback` (`'unusable'` or
  `'missing'`) options. `_fields` strings that name `media_details` do not count.

Tests: `tests/js/architecture-shared-helpers.test.mjs`, run by `npm test`.

### h. Spacing and radius use the tokens

Stylesheets in `assets/src/app` set `padding`, `margin`, `gap`, `row-gap` and `column-gap`
(including physical and logical longhands, and the legacy `grid-gap` aliases) and `border-radius`
(including corner longhands) from `--oml-space-*` or `--oml-radius-*`. A raw px length may stay
when it is `0`, `1px` or `2px`, including the same sizes with a minus sign (`-1px` overlaps a
segmented control). Those are hairlines, borders and focus rings. A px length written only as the
fallback inside `var(--oml-…)` belongs to that token. Comments are ignored. The check reads px
lengths; percentages, rem and em stay as written. `999px` is a fully round pill and needs an
allow-list reason, because no radius token stays fully round on the WordPress preset.

The check reads `*.css` under `assets/src/app`. `assets/src/ui` has no stylesheets.

### i. REST routes are registered by WP_REST_Controller subclasses

`tools/architecture-rules/rest-controllers.mjs` reads every PHP file under `includes/` and `packages/`
(`vendor/` and `node_modules/` are skipped). It blanks comments, strings, heredocs and inline HTML, then
finds each `register_rest_route(` call (a plain or `\`-prefixed function call; method calls, test stubs that
declare the function, and mentions in comments or strings do not count) and the class whose body contains it.
The call passes when that class reaches `WP_REST_Controller` through its `extends` chain: directly, through
classes of the plugin (`Abstracts\RestController`, `Engagement\Rest\GamificationController`,
`Commerce\Abstracts\RestController`, …) or through a core `WP_REST_*_Controller`. Parent names are resolved
the way PHP resolves them, from the file's namespace and `use` imports (aliases and group imports included),
so `extends WP_REST_Controller` in a namespaced file without `use WP_REST_Controller;` fails, as it would at
run time.

A file fails when a call sits in a class that does not reach `WP_REST_Controller` (an admin page, block,
hook class, gateway or service) or outside any class. The message names the file, the lines, the class and
its parent chain:

```text
includes/Blocks/PlayerBlock.php:5 calls register_rest_route() in OhMyLMS\Blocks\PlayerBlock (no parent); register routes in a WP_REST_Controller subclass (includes/Rest/V1 or the feature's Rest/ folder) listed in includes/Rest/Api.php
```

To fix one, move the `register_rest_route()` calls into a controller in `includes/Rest/V1/` or the feature's
own `Rest/` folder, keep the logic in the domain class and call it from the controller, and register the
controller in `includes/Rest/Api.php` (or from the feature bootstrap on `rest_api_init`). Keep paths,
methods, arguments, permission rules, status and error codes and bodies identical; dump
`rest_get_server()->get_routes()` before and after to show it ([BACKEND-BOUNDARIES.md](BACKEND-BOUNDARIES.md)).
The rule does not check that a controller is registered, or that its routes are well formed.

Tests: `tests/js/architecture-rest-controllers.test.mjs` (fixture trees in a temporary folder), run by
`npm test`.

### k. PHP admin links use app addresses, not `LegacyRoutes::url()`

`tools/architecture-rules/legacy-routes.mjs` reads the PHP files under `includes/`, `templates/`,
`packages/`, `modules/` and `ohmylms.php` (`vendor/`, `node_modules/` and `tests/` are not read). With
comments and inline HTML blanked, it fails on each `LegacyRoutes::url(` call (also `\OhMyLMS\Admin\…` and
`array( LegacyRoutes::class, 'url' )`) outside `includes/Admin/`, which owns `LegacyRoutes` and the menu,
commands and redirects that map legacy addresses. New links call `LegacyRoutes::app_url( '/<app path>' )`
so the legacy route table can shrink. The one exception, in `legacyRouteUrls` of the allow-list, is
`NativeLessonEditor::return_url()`: its input (`ohmylms_return`, assessment profiles' `return_route`) is a
legacy route name by design.

Tests: `tests/js/architecture-shared-helpers.test.mjs`, run by `npm test`.

### j. REST controllers stay within their `$wpdb` budgets

`tools/architecture-rules/controller-persistence.mjs` reads every REST controller file: each PHP file in a
`Rest` folder under `includes` at any depth (`includes/Rest/V1`, `includes/Engagement/Rest`,
`includes/Integrations/Zoom/Includes/Rest`, …), and each other file under `includes` or `packages` that
declares a `WP_REST_Controller` subclass, found with the class index of rule i (dashboard widget
controllers, `XApi\SettingsController`, the native editor controllers, the e-commerce package). `vendor`
and `node_modules` folders are skipped. It blanks comments (strings stay, so `"{$wpdb->options}"` counts)
and counts the lines that use `$wpdb`. This is the per-controller measure of
[BACKEND-BOUNDARIES.md](BACKEND-BOUNDARIES.md);
options and post or user meta are not counted. Each file is compared with its budget in
`tools/controller-persistence-budgets.json`. A file that is not listed, such as a new controller, has a
budget of 0.

The check fails when:

- a file uses `$wpdb` on more lines than its budget: move the query into the feature's domain service or
  read model (`Schools\Directory`, `Quiz\AttemptReports`, `Assignment\Submissions`, …) and call it from the
  controller;
- a file uses fewer lines than its budget: lower the budget in the same change, so it cannot grow back;
- a budget names a file that no longer exists or is no longer a controller.

```text
includes/Rest/V1/NewController.php uses $wpdb on 2 lines (budget 0); move the queries into the feature's domain service or repository
```

Budgets only go down. Do not raise one to make a change pass; the budgets list only files above 0. No file
has one any more: the last two (`Gradebook\Rest\GradebookController` 7, `ZoomWebhookController` 4) went to 0
on 1 November 2026, so every controller now has a budget of 0.
Tests: `tests/js/architecture-controller-persistence.test.mjs` (fixture trees in a temporary folder), run
by `npm test`.

### Front-end rules (l to o): native editors and learner modules

`tools/architecture-rules/frontend.mjs` applies four rules to the JavaScript outside the typed app:
`assets/src/features` (native editors and blocks) and `assets/interactivity` (learner script modules).
It parses every `.js`, `.jsx`, `.mjs` and `.cjs` file with `@babel/parser` (comments are ignored) and
resolves static imports, `export … from`, dynamic `import()` and `require()`: relative paths as webpack
resolves them, and the learner import-map names as `OhMyLMS\Extensions\Interactivity` and `Video`
register them (`ohmylms/interactivity` is `sdk.js`, `ohmylms/video-<provider>` is `video/<provider>.js`,
`ohmylms/<id>` is `<id>.js`). Exceptions are in `tools/architecture-rules/frontend-allowlist.json`
(below). Tests: `tests/js/architecture-frontend.test.mjs`, run by `npm test`.

#### l. No import cycles in `assets/src/features` and `assets/interactivity`

Strongly connected components over both trees, reported once each with an example path, as rule b does
for the app. On 1 November 2026 there are none at file level, `public.mjs` entry points included.
Folder level is not a rule: `learning`, `paper`, `question-editor`, `question-bank` and `assessment`
import each other in both directions through different files. That is why three components are imported
directly instead of through `question-editor/public.mjs` (rule n).

#### m. Learner modules use the SDK by name and no jQuery

- A module in `assets/interactivity` imports the SDK only as `ohmylms/interactivity`. Importing
  `./sdk.js` (or `../sdk`) by path would load a second SDK instance next to the import-map one, with its
  own configuration and registries.
- `sdk.js` imports nothing: it is the import-map entry, and node tests and the Playwright fixture load it
  as a single file.
- No module refers to `jQuery` or `$` (identifiers, `window[ 'jQuery' ]`, or an import of `jquery`).
  Add-on events go through `emit()`. There are no exceptions: the five jQuery event bridges that were
  allow-listed (`sdk.js`, `commerce-logic.mjs`, `cart.js`, `checkout.js`, `lesson/store.js`) were removed in 1.2.22.

```text
assets/interactivity/tabs.js:3 imports the SDK as './sdk.js'; import 'ohmylms/interactivity' (the import-map name), so the page loads one SDK instance
```

#### n. Feature folders use each other only through `public.mjs`

A top-level folder of `assets/src/features` (`learning`, `paper`, `question-editor`, …) imports another
folder only through that folder's `public.mjs`, which re-exports what other folders use from it. Root
modules (`rest.mjs`, `requestFeedback.mjs`) are shared and are imported directly. Static imports,
`export … from`, dynamic `import()` and `require()` all count, so a `public.mjs` that re-exports another
folder's file fails too. Every other cross-folder import fails:

```text
assets/src/features/paper/PaperInserter.jsx imports assets/src/features/learning/NativeLessonSettings.jsx; features/paper may use features/learning only through assets/src/features/learning/public.mjs (re-export what it needs there)
```

Eight folders have a `public.mjs`: `assessment`, `content-hub`, `curriculum`, `learning`, `math`,
`paper`, `question-bank` and `question-editor`; `navigation` and `students` are used by no other folder.
They use explicit file extensions, so the `.mjs` ones load in Node tests. `question-editor/public.mjs`
holds only the question type models (`.mjs`). `question-bank/model.mjs`, `paper/paperTypes.mjs` and
`learning/choiceAutosave.mjs` import it and run in Node tests, which cannot load JSX. Three
`featureImports` entries allow the direct imports that remain (four files, five import statements):

- `learning` to `question-editor/MultipleChoiceBlock.jsx` and `QuestionLivePreview.jsx`: they reach
  `question-bank/public.mjs`, whose `model.mjs` imports `question-editor/public.mjs`, so re-exporting
  them there makes an import cycle (rule l).
- `learning` to `paper/paperBlocks.jsx`: `NativeLessonSettings.jsx` imports it only for its side effect
  (it registers the paper blocks before the question blocks). webpack drops a `public.mjs` whose exports
  are unused, so a bare `import '../paper/public.mjs'` would not load the registration.
- `math` to `question-editor/QuestionMediaUpload.jsx`: a JSX component cannot be in the Node-loaded
  `question-editor/public.mjs`.

The entry files cost nothing in the bundles: webpack binds each import to the module that defines it
and leaves the `public.mjs` files out. On 1 November 2026, a temp-dir build put the same modules in
every bundle as before, and no bundle changed by more than 39 bytes.
Imports that leave the two trees are not checked by this rule; on 31 October 2026 they were
`assets/src/ui` (`Modal`, `Alert`; 3 imports), `assets/src/app/extensions` (`registry.mjs`, `api.ts`,
`ExtensionBoundary.tsx`; 4 imports), `features/question-editor/PuzzleEditor.jsx` to
`interactivity/puzzles.mjs`, and two learner modules to `features/math/loader.mjs`.

#### o. JSX modules in `assets/src/features` bind `createElement`

webpack compiles their JSX with the classic `createElement` pragma (`webpack.config.cjs`), so a module
with JSX must import `createElement` from `@wordpress/element` (or declare it). Without it the editor
throws a `ReferenceError` when the component renders. `npm run typecheck:js` types JSX with `react-jsx`
and cannot see this; `<>` fragments compile to WordPress' global `React.Fragment` and need no import.

### p. Advisory locks go through `OhMyLMS\Utility\Lock`

`tools/architecture-rules/advisory-locks.mjs` reads the PHP files under `includes/`, `templates/`,
`packages/`, `modules/` and `ohmylms.php` (`vendor/`, `node_modules/` and `tests/` are not read; the suites
hold locks from a second connection on purpose). With comments and inline HTML blanked (strings stay, since
the SQL is a string), it fails on each `GET_LOCK(` (any case) outside `includes/Utility/Lock.php`:

```text
includes/Example/Locking.php:9 takes a MySQL advisory lock itself; use OhMyLMS\Utility\Lock::with( $name, $timeout, $work, $busy )
```

To fix one, call `Lock::with( $name, $timeout, $work, $busy )`: it takes the lock, runs `$work`, releases in
`finally` and returns `$busy` (a Closure is called, so it can build the caller's own `WP_Error` or throw)
when the lock is not free in time; the default is `ohmylms_lock_busy` (409). Long bodies that already have
a `try`/`finally` can call `Lock::acquire()` and `Lock::release()` instead. `Lock::name()` keeps names that
start with `ohmylms` or `oml`, prefixes others with `ohmylms-`, and hashes names over MySQL's 64 characters.
Option-row locks (`ohmylms_lock_<name>`) use `Lock::acquire_option()`, `release_option()` or `with_option()`.
Exceptions go in `ADVISORY_LOCK_ALLOWLIST` in the rule file, each `{ file, reason }`; it is empty, and an
entry whose file no longer calls `GET_LOCK` fails as stale.

Tests: `tests/js/architecture-advisory-locks.test.mjs` (fixture trees in a temporary folder), run by
`npm test`; the helper itself: `tests/php/lock-integration.php` (`OHMYLMS_LOCK_TEST=1`).

## Changing the rules on purpose

### Allow-list: `tools/architecture-allowlist.json`

Use the allow-list only for violations that already exist and cannot be fixed in the same change.
Every entry needs a `reason`. Entries without one fail the check. An entry that no longer matches a
violation also fails, so remove entries once their violation is fixed. Allowed violations are
still printed (`~ allowed: …`) with their reason.

```json
{
	"recoveredRuntime": [ { "from": "assets/src/app/…", "to": "assets/src/…", "reason": "…" } ],
	"cycles": [ { "files": [ "assets/src/app/a.ts", "assets/src/app/b.ts" ], "reason": "…" } ],
	"crossFeature": [ { "from": "assets/src/app/features/a/…", "to": "assets/src/app/features/b/…", "reason": "…" } ],
	"sharedHooks": [ { "file": "assets/src/app/features/a/…", "hook": "usePagedList", "reason": "…" } ],
	"sharedHelpers": [ { "file": "assets/src/app/features/a/…", "helper": "useRequest", "reason": "…" } ],
	"legacyRouteUrls": [ { "file": "includes/Feature/File.php", "reason": "…" } ],
	"rawSpacing": [ { "file": "assets/src/app/feature.css", "value": "999px", "reason": "…" } ],
	"restControllers": [ { "file": "includes/Feature/Hooks.php", "reason": "…" } ]
}
```

For `recoveredRuntime`, `from` is the app file and `to` is the first file it imports on the chain;
both appear in the failure message. For `cycles`, `files` lists every file in the cycle.

`value` on a `rawSpacing` entry is the px length (`6px`, `999px`), compared case-insensitively, and it covers every use of that length in the file. `rawColours` allows one help-text example. `rawSpacing` allows the gradebook enrollment-source pill (`999px`).

`restControllers` entries are `{ "file": "includes/…/File.php", "reason": "…" }`, matched by the exact path
from the plugin root; one entry covers every call in that file. It lists one file: the packaged QPay gateway
(`packages/e-commerce/includes/Gateways/QPay/GatewayQPay.php`), which registers its public `/qpay/callback`
from the gateway constructor.

`sharedHelpers` entries name the file and the helper (`useRequest`, `useOperation`, `record`, `text`,
`positiveId`, or `parseMediaImage` for a file that parses media items); there are none.
`legacyRouteUrls` entries are `{ "file": "includes/…/File.php", "reason": "…" }`, one per file; it lists
`includes/Learning/NativeLessonEditor.php`.

### Front-end allow-list: `tools/architecture-rules/frontend-allowlist.json`

Rules l to o keep their exceptions next to the rule, with the same policy: every entry needs a `reason`,
and an entry that no longer matches fails as stale.

```json
{
	"cycles": [ { "files": [ "assets/interactivity/a.js", "assets/interactivity/b.js" ], "reason": "…" } ],
	"jquery": [ { "file": "assets/interactivity/cart.js", "reason": "…" } ],
	"featureImports": [ { "from": "learning", "to": "paper", "files": [ "paperBlocks.jsx" ], "reason": "…" } ]
}
```

`featureImports` names folders of `assets/src/features`; `files` are paths inside the `to` folder that
the `from` folder may import without going through `public.mjs`. A listed file that the `from` folder no
longer imports is reported as stale on its own. There are no `cycles` or `jquery` entries;
`featureImports` lists the three direct imports of rule n (an import cycle, a side-effect
import and a JSX component kept out of a Node-loaded entry).

### Budgets: `tools/asset-budgets.json`

The initial budgets are the published sizes plus 10% headroom, rounded up to 100 bytes. When a
screen legitimately grows, or when you add or remove a chunk:

1. Rebuild the admin app and publish the new bundles.
2. Either edit the entry in `tools/asset-budgets.json` by hand, or run
   `node tools/check-architecture.mjs --update-budgets`. That rewrites every budget to the current
   size plus `headroom`, adds new chunks and removes chunks that are gone.
3. Review the diff and explain the growth in the pull request. Do not raise budgets just to make CI
   pass.

### Runtime dependency inventory

Retired on 31 October 2026 with the recovered runtime it inventoried; see
[MODULARIZATION-ROADMAP.md](MODULARIZATION-ROADMAP.md).
