# Enforcement checks

Roadmap step 10 (see [the modularization roadmap](MODULARIZATION-ROADMAP.md)). These checks keep
the typed admin app (`assets/src/app`) independent of the recovered runtime, and keep its published
bundles (`assets/dist/admin`) small and free of duplicate React or WordPress code. CI also runs the
Node tests, ESLint, the PHP unit and integration suites and the WordPress coding standards on every
push and pull request to `main`.

## Run locally

| Command | What it runs |
| --- | --- |
| `npm run check:ci` | `typecheck`, `test:app`, `check:architecture`, then `node --test tests/js/check-architecture.test.mjs` |
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

`.github/workflows/ci.yml` runs five jobs in parallel. npm downloads are cached by
`actions/setup-node`, Composer downloads by `ramsey/composer-install`.

| Job | Runs |
| --- | --- |
| **checks** | Node from `.nvmrc` and PHP 8.2; `npm ci`, `npm run build`, `npm run check:ci`, `npm test` |
| **lint-js** | `npm ci`, `npm run lint:js` |
| **php** | PHP 8.2: `php -l` on every file in `includes/` and `tests/php`, then `node tools/run-php-tests.mjs unit` |
| **phpcs** | `composer install` in `tools/coding-standards`, then `node tools/phpcs-changed.mjs <base>`: the pull request's base commit, or the previous `main` commit on a push |
| **php-integration** | MySQL 8.0 service container; `tools/install-test-wordpress.sh` installs WordPress with wp-cli, copies the plugin to `wp-content/plugins/ohmylms` and activates it; `wp server` on `ohmylms.test:8080`; then `node tools/run-php-tests.mjs integration` and `tests/js/randomized-preview.test.mjs` against that site |

### PHP suites: `tools/run-php-tests.mjs`

The runner runs each PHP file directly in `tests/` and `tests/php/` in its own process (subfolders hold
shared fixtures; `*-fixture.php` files seed data for the browser specs and are not run). A file without
`wp-load.php` is a **unit** suite. An **integration** suite is classified by how it finds WordPress:

| Kind | Bootstrap | What the runner sets |
| --- | --- | --- |
| local-site | `dirname( __DIR__, 5 ) . '/wp-load.php'` | `1` for every `'1' !== getenv( 'X' )` gate in the file (for example `OHMYLMS_COURSES_TEST`); `OHMYLMS_TEST_HOST` is passed on (the suites default to `800.local`) |
| argv-site | `$argv[1] . '/wp-load.php'` | The WordPress root as the first argument |
| site-path | `OHMYLMS_TEST_SITE_PATH` | The WordPress root, unless the variable is already set |
| credentials | `OHMYLMS_TEST_CREDENTIALS` | Nothing: these need the disposable site from `tools/create-test-site.py` |

`node tools/run-php-tests.mjs integration --list` shows the kind, gates and required database of every
suite. Name files to run only those. Environment:

- `PHP_BINARY`: the PHP executable (otherwise `php` on `PATH`, then Local's PHP).
- `OHMYLMS_TEST_DB_PORT`: sets `mysqli.default_port`. Local's `wp-config.php` has `DB_HOST` `localhost`
  without a port, so use `10006` there. CI puts the port in `DB_HOST` instead.
- `OHMYLMS_TEST_WP_ROOT`: the WordPress root for argv-site and site-path suites (default: three folders
  above the plugin).
- `OHMYLMS_TEST_HOST`: the host header for local-site suites.

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

- `"skipIn": "ci"`: skipped only when the `CI` variable is set (GitHub Actions sets it). These pass on the
  Local site but need content a clean install lacks.
- `"expect": "fail"`: suites that fail at HEAD. They still run; the job fails if one of them passes, so
  remove its entry when it is fixed.

`--all`, or naming a listed file, runs it anyway. The runner fails when an entry names a missing file, a
file of the wrong kind, or has no reason. Not run in CI: suites that need the disposable
`ohmylms_source_test` site or its credentials, the migration databases
(`ohmylms_gutenberg_migration_20261020`, `ohmylms_streak_test`), a `pdftoppm` binary, the sibling
`ohmylms-custom-question` or `ohmylms-graded-reader` plugins, or content on the Local site, plus the
non-suite helpers.

### WordPress coding standards: `tools/phpcs-changed.mjs`

`npm run lint:php` checks every file in `phpcs.xml.dist` and still reports about 2,200 errors and 2,600
warnings in legacy code (31 October 2026), so CI does not run it. `tools/phpcs-changed.mjs` diffs the
working tree against the merge base with the given revision, runs phpcs on the changed PHP files and
fails on errors in added or changed lines. Warnings on those lines are printed; findings on untouched
lines are counted but not reported. New files must be committed (or staged) to be diffed.

### Line endings

`.gitattributes` stores JavaScript, TypeScript, PHP, CSS, JSON, Markdown, YAML and shell files with LF
and checks them out with LF even when `core.autocrlf` is `true`. Files checked out before that rule may
still have CRLF on Windows; `.prettierrc.cjs` sets `endOfLine: 'auto'` so Prettier and ESLint accept
them, and Git stores LF when they are committed. `git add --renormalize .` is not needed: the index
already holds LF.

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
	"rawSpacing": [ { "file": "assets/src/app/feature.css", "value": "999px", "reason": "…" } ]
}
```

For `recoveredRuntime`, `from` is the app file and `to` is the first file it imports on the chain;
both appear in the failure message. For `cycles`, `files` lists every file in the cycle.

`value` on a `rawSpacing` entry is the px length (`6px`, `999px`), compared case-insensitively, and it covers every use of that length in the file. `rawColours` allows one help-text example. `rawSpacing` allows the gradebook enrollment-source pill (`999px`).

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
