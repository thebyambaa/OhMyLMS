# Third-party libraries — 31 October 2026

Roadmap step 2. This is the current inventory of every third-party library the plugin bundles or vendors,
checked against the code after the legacy admin was deleted ([CUTOVER](CUTOVER.md)). WordPress packages
(`@wordpress/*`, React, ReactDOM, jQuery) are script externals supplied by WordPress; the npm copies in
`devDependencies` are only for types, tests and the build.

## Bundled from npm (`package.json` dependencies)

| Package | Shipped as | Used by |
| --- | --- | --- |
| `react-router-dom` 6.30.6 (with `@remix-run/router` 1.23.4) | inside `assets/dist/admin/admin-app.js` | the admin app router; 124 importers |
| `mathlive` 0.111.1 | `math-runtime.js` (836 KB, loaded by URL only on math pages) and `assets/dist/mathlive/fonts` | `features/math/runtime.mjs`, handle `ohmylms-mathlive` |
| `@cortex-js/compute-engine` 0.151.0 | inside `math-runtime.js` | `features/math/teacherCompute.mjs` |
| `jsxgraph` 1.14.1 | `assets/dist/jsxgraph/jsxgraphcore.js` (995 KB), copied from `node_modules/jsxgraph/distrib`; Webpack external `JXG` | extended questions, import figures, interactive-activity block, exams (handle `ohmylms-jsxgraph`) |

All four are maintained, pinned and still imported. Every `devDependency` also has a live consumer
(build, lint, tests or types). `moment` in the lockfile is transitive (`@wordpress/components` →
`@wordpress/date`) and is not bundled. No `package.json` change was needed in this pass.

## Vendored copies (not from npm)

| Library | Location | Status | Plan |
| --- | --- | --- | --- |
| Slick 1.8.1, locally patched (its UMD wrapper is commented out so it always binds the global jQuery) | was `assets/src/runtimes/dist/frontend/slick.js` → `assets/dist/frontend/slick.js` (43 KB) | **Removed** (31 October 2026). Course carousels (archive/shortcode/Elementor/Bricks/WPBakery layouts 3 and 4, the hub catalog) render through `OhMyLMS\Design\Carousel` (`templates/loop/loop-start.php` / `loop-end.php`): a CSS scroll-snap track that works without JavaScript, plus the `ohmylms/carousel` Interactivity module (`assets/interactivity/carousel.js`) for arrows, optional dots and autoplay. The `ohmylms-slick` handle, `ohmylms-slick-editor`, the Bricks inline initializer, the unreferenced `assets/js/wpbakery-frontend.js` and `scss/archive/_slick.min.scss` are gone; both JS outputs are in `retiredAssets` | Element settings map to `slidesToShow` (columns) with the old breakpoints (≤1199px: 3, ≤767px: 2, ≤575px: 1) as CSS custom properties; `ohmylms_course_carousel_settings` can enable `autoplay`, `loop` or `dots`. Fetched or editor-preview HTML is not hydrated and simply scrolls |
| html2pdf.js bundle, registered as 0.10.1; it embeds html2canvas, jsPDF, canvg, DOMPurify, core-js 3.45.1, pako and fflate | was `includes/Assets/packages/html2pdf.bundle.min.js` (942,854 bytes) | **Removed** (31 October 2026). Nothing enqueued it: learner downloads are rendered on the server (`Certificates\Download`, PDF through `dompdf/dompdf` or a printable page), and `?ohmylms-certificate-data` redirects on `template_redirect` before any template or script loads. The `CommonHook::load_custom_template()` branch for that query, its empty `templates/page-template/ohmylms-certificate.php` and the unused "Certificate template" page template were removed with it | None. `includes/Assets/packages/` is gone |

## PHP (Composer)

`composer.json` pins `dompdf/dompdf` ^3.1 (with php-font-lib, php-svg-lib, masterminds/html5,
sabberworm/php-css-parser), `woocommerce/action-scheduler` 3.9.3 and `wordpress/mcp-adapter` 0.6.1 (with
php-mcp-schema), plus the Jetpack autoloader. `packages/e-commerce/vendor` holds a second Action Scheduler
3.9.3 (113 files, 622 KB) that `packages/e-commerce/Package.php` still loads through its own autoloader.
Action Scheduler tolerates duplicate copies. Plan: drop that copy once the package uses the root
autoloader. Risk: low.

## Removed on 31 October 2026 (this pass)

The audit grepped every candidate first. `build:sdk`, typecheck and the architecture check pass afterwards.

| Removed | Why dead |
| --- | --- |
| Select2 4.1.0-rc.0: `assets/lib/admin/js/select2.js`, `assets/lib/admin/css/select2.css`, `assets/src/admin/lib/select2.js`, `assets/src/static/lib/admin/css/select2.css` and its manifest entry | No script or style registers it since the cutover. The `ohmylms-select2` classes left in two Admin settings definitions are inert. Both outputs are listed in `retiredAssets` |
| Six Mail Mint "automation-canvas" images (Easy Email social and logo defaults) in `assets/src/static/dist/packages/…` and their manifest entries | Easy Email is gone; the asset build already skipped `assets/dist/packages/` |
| `assets/src/frontend/js/slick.js` (readable Slick copy) | Nothing builds or serves it; the shipped copy comes from `runtimes/dist` |
| `.mjml-versions.json`, `.vendor-split-audit.json` | Outputs of deleted recovery tools |
| `tools/licenses/mjml-MIT.txt`, `tools/licenses/tiptap-MIT.txt` | MJML and Tiptap no longer ship |

That is 15 files and 310,454 bytes; `assets/src/manifest.json` went from 79 to 72 assets.

A later pass the same day (learner front-end batch L1, [LEARNER-FRONTEND](LEARNER-FRONTEND.md)) removed the
html2pdf bundle and the certificate template override above, the second copies of `ohmylms.js`,
`video-player.js` and `video-progress-tracker.js`, and the unloaded `blocks`, `dashboard` and `profile`
`.min.js` block scripts.

Already gone with the legacy admin: the recovered Easy Email editor and extensions with their private
CodeMirror and html2canvas copies, and 50 npm packages including `easy-email-core`, Arco, MJML, Chart.js,
Emotion, Tiptap, Lowlight, Lodash and `react-select` ([CUTOVER](CUTOVER.md)).
The typed email editor (`assets/src/app/features/emails`) uses only WordPress components and the server
preview, so no email library needs replacing.

## Loaded from the provider, not bundled (L7)

The lesson video module does not vendor a player. The first time a page has that provider, the adapter
fetches the API: YouTube IFrame API (`https://www.youtube.com/iframe_api`), Vimeo Player
(`https://player.vimeo.com/api/player.js`) and Wistia `E-v1.js`
(`https://fast.wistia.com/assets/external/E-v1.js`). Bunny Stream is spoken with the player.js `postMessage`
protocol; the player.js script itself is not loaded, because the Bunny iframe is the receiver. HTML5 uses
the browser's `<video>`. None of these is an npm dependency.

## Left for other owners

- The two inert `ohmylms-select2` field classes in `includes/Admin/Pages/GeneralSettings.php`. (The Mail Mint
  image `includes/Assets/your-logo.png`, `Abstracts\Assets::localize_script_MRM()` and the unused
  `Admin\Settings\Currency` class with the third were removed on 31 October 2026.)
- Empty `node_modules/@arco-design` and `node_modules/@tiptap` folders disappear with the next `npm ci`.
  The stale `build/assets/lib/admin/css/select2.css` is removed by the next asset build (`retiredAssets`).

## History

The 29 October migrations of Chart.js, Emotion, MJML, Arco, Easy Email core and Lowlight to npm
packages, with their adapters and parity tests, applied to the recovered admin and were deleted with it on
31 October 2026. See git history for this file and the retired audit documents removed in the October 2026
documentation cleanup.
