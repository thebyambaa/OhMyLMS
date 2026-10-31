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
| Slick 1.8.1, locally patched (its UMD wrapper is commented out so it always binds the global jQuery) | `assets/src/runtimes/dist/frontend/slick.js` → `assets/dist/frontend/slick.js` (43 KB) | **Used**: handle `ohmylms-slick`, enqueued by `FrontendAssets` (single course, archive grid styles 3/4, course-list shortcode), `BlocksManager` (block editor), Bricks `CourseListElement` (inline initializer), Elementor `CheckoutWidget` and `Student\Hub`; four `.slick()` calls in `frontend/js/ohmylms.js`, nine references in `wpbakery-frontend.js`; `scss/archive/_slick.min.scss` plus 53 SCSS lines | Upstream is unmaintained (last release 2017; npm `slick-carousel` is the same 1.8.1). Replace with CSS scroll-snap and a small authored controller during the learner front-end migration. Size: about 43 KB JS and 1.5 KB CSS removed. Risk: medium; five PHP consumers, RTL and the Bricks/WPBakery initializers must move together |
| html2pdf.js bundle, registered as 0.10.1; it embeds html2canvas, jsPDF, canvg, DOMPurify, core-js 3.45.1, pako and fflate | `includes/Assets/packages/html2pdf.bundle.min.js` (943 KB) | **Used**: enqueued only for `?ohmylms-certificate-data` (learner certificate download in `frontend/js/ohmylms.js`). Its header points to a `LICENSE.txt` and a source map that are not shipped | Replace in the certificate-issuing batch: render the PDF on the server with the already-installed `dompdf/dompdf`, or take the maintained npm `html2pdf.js` and copy its dist and notices in `sync-admin-assets`. Check the exact embedded versions first: core-js 3.45.1 suggests a newer build than the 0.10.1 handle version. Size: −943 KB on that page. Risk: low; one consumer. Ship the MIT notices until then |

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

Already gone with the legacy admin: the recovered Easy Email editor and extensions with their private
CodeMirror and html2canvas copies, and 50 npm packages including `easy-email-core`, Arco, MJML, Chart.js,
Emotion, Tiptap, Lowlight, Lodash and `react-select` ([CUTOVER](CUTOVER.md)).
The typed email editor (`assets/src/app/features/emails`) uses only WordPress components and the server
preview, so no email library needs replacing.

## Left for other owners

- The two inert `ohmylms-select2` field classes in `includes/Admin/Pages/GeneralSettings.php`. (The Mail Mint
  image `includes/Assets/your-logo.png`, `Abstracts\Assets::localize_script_MRM()` and the unused
  `Admin\Settings\Currency` class with the third were removed on 31 October 2026.)
- Empty `node_modules/@arco-design` and `node_modules/@tiptap` folders disappear with the next `npm ci`.
  The stale `build/assets/lib/admin/css/select2.css` is removed by the next asset build (`retiredAssets`).

## History

The 29 October migrations of Chart.js, Emotion, MJML, Arco, Easy Email core and Lowlight to npm
packages, with their adapters and parity tests, applied to the recovered admin and were deleted with it. See
git history for this file, [EMAIL-PACKAGE-MIGRATION](EMAIL-PACKAGE-MIGRATION.md),
[EASY-EMAIL-AUDIT](EASY-EMAIL-AUDIT.md) and [ADMIN-VENDOR-AUDIT](ADMIN-VENDOR-AUDIT.md).
