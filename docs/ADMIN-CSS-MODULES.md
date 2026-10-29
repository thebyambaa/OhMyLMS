# Admin CSS modules

The admin stylesheet is authored in `assets/src/admin-styles/`. Its manifest is
ordered: later rules retain their original cascade position. Edit these sources,
then run `npm run build`. Do not edit the generated files in `assets/dist`.

The initial common layer from the former 1,648,842-byte stylesheet is 104,322
bytes (uncompressed). Course, quiz, commerce, student, community, settings,
integration and setup styles load separately. WordPress editor, email shell,
Arco/email builder and automation styles have their own modules. Small common
fragments are printed inline in their original positions to avoid tiny requests;
larger common fragments are cached stylesheet files. Tailwind utilities, the admin
shell and design tokens remain shared. The 78,824-byte `admin-ui.css` is also
split, with 32,561 bytes common and the rest assigned to curriculum, quizzes,
skills, gamification, settings and commerce. The small global navigation stylesheet is
intentionally available throughout wp-admin, where the LMS menu is visible.

`AdminAssets` registers the module handles and retains `ohmylms-main` and
`ohmylms-rtl` as compatibility dependency handles. Deferred links have no `href`
until activated. Their permanent slots precede the design system overrides.
`assets/js/admin-styles.js` fills those slots without moving them. The existing
feature loader waits for the matching CSS and JavaScript together before
rendering. CSS requests are reused and failures can be retried. DOM observation
covers synchronous extensions, native screens and portals. The separate authored
quiz, course, curriculum and skill styles use the same loading mechanism.

Add a feature group to the loader when adding a new lazy screen. Place new style
rules in that feature's source modules; only genuinely common rules belong in the
shared group. Files stay at the original directory depth so relative font and
image URLs resolve as before. Content hashes invalidate changed modules.

The original recovered static CSS is retained as a regression fixture. The build
also assembles legacy CSS entrypoints from the modular sources for compatibility.
The recovered vendor declarations retain their original formatting and licenses;
this migration changes organization and loading, not their contents. New styling
must follow the repository's scoped WordPress CSS conventions.
The CSS standards command checks the authored `ui-*` modules directly. For an
intentional change to recovered styles, update the parity fixture as part of the
same reviewed change.

Validation:

- `node --test tests/js/admin-styles.test.mjs` checks lossless reconstruction,
  reproducibility and waiting for styles before feature rendering.
- `php tests/php/admin-styles.php` checks PHP registration and RTL selection.
- `npx playwright test tests/browser/admin-styles.spec.cjs` checks on-demand
  requests, request reuse and cascade order for LTR and RTL in a browser.
- `npm test` runs the source regression suite; `npm run build` ships the modules.
