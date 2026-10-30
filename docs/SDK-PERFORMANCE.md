# On-demand extension SDK

> **Historical.** The legacy extension SDK described here was deleted on 31 October 2026
> ([CUTOVER.md](CUTOVER.md)). Extensions now use the app's host (`assets/src/app/extensions`, under 5 KB);
> see [EXTENSIONS.md](EXTENSIONS.md) and [ARCHITECTURE.md](ARCHITECTURE.md).

The SDK now registers synchronous component factories backed by React lazy
components. A feature loads only when one of its components renders. Each feature
shares one import promise, so opening sibling components does not download it again.
Runtime accessors still run at rendering time, preserving the recovered app's
initialization contract. The public extension registry and ready event are available
immediately, including Question Bank page and editor registrations.

The SDK startup bundle changed from about 997 KiB to 49 KiB (about 95% smaller).
Feature bundles are below Webpack's 244 KiB recommendation; the largest is Courses
at about 125 KiB. These numbers describe uncompressed JavaScript, not measured page
load times. The core admin app, vendor assets and WordPress dependencies are separate
and unchanged by this optimization.

`assets/src/extensions/lazyFeatures.js` defines the feature boundaries using existing
component manifests. Question Bank registration lives in `registerPages.js` so it
does not import the full bank at startup. Registration form code loads only on pages
containing registration fields. A failed feature load offers a reload action.

Webpack emits hashed files under `build/sdk/chunks`. `npm run build` copies those
chunks beside the shipped SDK under `assets/dist/admin/chunks`. Both shipped and
source SDKs discover their chunk URL from their own script URL (`publicPath: auto`).
Keep that directory alongside `extensions.js` when packaging or deploying the plugin.
The SDK uses its own Webpack namespace to avoid module/chunk ID collisions with the
recovered admin app. Existing hashed chunks are retained for already-open pages.

Validation:

- `npm run lint` checks source and factory contracts.
- `npm test` checks behavior and parity, including shared import caching.
- `npm run build` generates and syncs the SDK and its feature chunks.
- `npx playwright test tests/browser/sdk-lazy.spec.cjs` verifies production SDK
  loading from both asset locations, deferred runtime reads, interaction state,
  Question Bank loading, isolated chunk namespaces and failed-download recovery.
  These SDK tests use WordPress's installed React with mocked platform globals and
  do not require a WordPress database or credentials. They are not full authenticated
  admin workflow coverage. Set `OHMYLMS_CHROMIUM_PATH` if needed for an installed browser.
