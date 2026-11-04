# WordPress coding standards

OhMyLMS uses the official [WordPress coding standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) tooling:

- PHP: WordPressCS through PHP_CodeSniffer and PHP Code Beautifier, using `phpcs.xml.dist`.
- JavaScript/JSX: `@wordpress/eslint-plugin` and WordPress's `wp-prettier` formatter with `@wordpress/prettier-config`.
- CSS/SCSS: `@wordpress/stylelint-config` and its SCSS configuration.
- Editor and Git settings preserve tabs and LF line endings on Windows as well as other systems.

## Install and run

```sh
npm ci
composer install --working-dir=tools/coding-standards --no-plugins --no-scripts
npm run lint:php
npm run lint:js
npm run lint:css
npm run format:check
```

`npm run lint` runs the source-contract check and the standards checks. Existing findings are reported as failures; they are not hidden behind a baseline. `npm run lint:standards` runs just the standards checks. PHP tooling is isolated from the plugin's runtime Composer dependencies.

Automatic fix commands are `npm run lint:php:fix`, `npm run lint:js:fix`, `npm run lint:css:fix`, and `npm run format`. Review changes and run relevant tests before rebuilding and shipping assets. PHP Code Beautifier uses exit status 1 when it successfully fixes files; this is distinct from a PHP syntax failure.

The PHP launcher uses PHP on PATH, the `PHP_BINARY` environment variable, or an installed Local PHP runtime on Windows. It enables the Local runtime's mbstring extension for the checker. Standard PHP installations should have the extensions required by WordPressCS enabled.

## Compatibility boundaries

- Vendor dependencies, generated bundles, and recovered upstream runtime assets are not edited or reformatted as plugin source.
- WordPress imports are host-provided dependencies managed by the existing dependency-extraction build. ESLint marks the exact imported WordPress packages as external core modules rather than requiring the plugin to bundle another copy of WordPress.
- Reconstructed React factories use hoisted legacy aliases. Converting those aliases to `let`/`const` introduced a verified quiz-report regression. Only those marked factory files have exceptions for `no-var` and `prefer-const`. Other checks still run on them; their remaining findings remain visible.
- Existing public PHP names, hooks, REST fields, database schema, CSS selectors, and PSR-4 file mappings must remain compatible. Naming migrations require an explicit compatibility plan; a formatter must not rename these interfaces silently.

### Reviewed naming and generated-file exceptions (October 16, 2026)

PHP class files retain their established Composer paths. Files whose basename matches the
declared class, interface or trait now carry a file-local `WordPress.Files.FileName` annotation.
This applies only to filename spelling and the `class-` prefix: every other PHP rule still runs.
Renaming those files would break case-sensitive PSR-4 loading, the published package classmap,
or consumers that include the existing paths. Unmatched paths remain reported for review.

`Assessment\Interactive::shuffled()` retains its public `$list` parameter for PHP named-argument
compatibility. Its method-local annotation covers only the reserved-parameter-name warning;
other standards checks remain active. New parameters must avoid reserved keywords.

`stylelint.config.mjs` preserves an exact list of existing public BEM/checkout selectors.
WordPress component classes belong to the host; plugin classes and checkout field IDs are
already consumed by templates, scripts and extensions. Other selector names still follow the
WordPress hyphenated convention. This list must not grow without a compatibility reason.

Styles copied from `static/` by `assets/src/manifest.json` are compiled recovery artifacts,
including the Tailwind utility stylesheet. Stylelint now excludes those exact manifest outputs,
as it already excludes generated bundles. Authored frontend SCSS and authored standalone CSS
remain checked. This corrects the prior audit's inclusion of compiled CSS; it does not repair
the corresponding authored findings. The asset build continues to copy the recorded inputs.

ESLint's JSX pragma is `createElement`, matching `webpack.config.cjs`; JSX imports are therefore
counted as used by the same function that compiles them. Reconstructed component contracts,
public object keys and REST field names remain unchanged during private-binding renames.

These boundaries are not a claim of full compliance (see "Known standards debt" below). Follow `AGENTS.md` for every future change; use modern declarations and WordPress conventions in new authored code.

## Known standards debt

Status on `main`, 4 November 2026 (after the recovered admin was deleted): **PHP 0 errors and 624 warnings, JavaScript 0 errors
and 17 warnings, CSS clean.** The standards commands report every remaining finding; there is no baseline hiding them. Earlier,
much larger figures in git history described the recovered runtime and are superseded.

What remains in PHP, by cause:

- **Nonce warnings (79).** Reviewed one by one on 4 November 2026. They are read-only URL parameters (tabs, views, paging,
  layout), payment redirects that are only read, token-authenticated links and one in-memory toggle. The one real gap, the Google
  Meet OAuth callback, now checks a per-user nonce carried in `state`. `?ohmylms_add_to_cart=<id>` still adds a course to the
  cart of a logged-in user from a plain link (no purchase); a nonce would break existing links.
- **`current_time( 'timestamp' )` (21).** These compare against site-local stored dates, so the shifted timestamp is consistent.
  Values stored in GMT use `time()` instead (the subscription scheduler was changed for this).
- **Strictness.** Loose `==` and `in_array()` without `true` remain where the operands are IDs, counts or response codes, because
  `===` could change the result. They were made strict only for string-literal names and string-only lists.
- **Unused parameters, commented-out code, `error_log()`.** Hook signatures must keep their parameters; most "commented-out code"
  findings are ordinary prose.

### Direct database queries (scoped rule exception)

`phpcs.xml.dist` excludes `WordPress.DB.DirectDatabaseQuery.DirectQuery` and `.NoCaching` under `includes/`, `packages/` and
`modules/`. The plugin owns custom tables (enrolments, attempts, curriculum, gradebook) that WordPress has no API for, so `$wpdb`
is its data layer and per-query object caching does not apply. Prepared-SQL, escaping and nonce sniffs stay enabled. A new
direct query outside those folders is still reported.

Public PHP interfaces, PSR-4 mappings, database fields and CSS selectors need compatible migrations before renaming. Escaping and
query findings need inspection of the actual trust boundary. Full JSON reports are local audit artifacts under the ignored
`.wp-dev/` folder.

## Review findings manually

Escaping findings require identifying whether the output is plain text, an attribute, a URL, or deliberately rendered HTML. Sanitization and nonce findings require examining the request and permission boundary, including existing REST authentication. Database caching warnings require distinguishing reads from writes. Do not blanket-escape markup, add fake documentation, change loose comparison behavior, reorder CSS cascades, or disable rules simply to make the report green.

Remaining recovered JavaScript needs source-level cleanup with regression tests. CSS specificity and selector naming changes also need coordination with the templates and script consumers. Run the full audit again after those migrations.
