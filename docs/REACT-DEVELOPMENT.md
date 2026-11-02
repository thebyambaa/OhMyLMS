# React development guide

The admin UI is one typed React app in `assets/src/app`, opened at `admin.php?page=ohmylms-app#/…`. It is the only
admin: the recovered runtime and its `createComponent(readRuntime)` factories, `components.json` contracts, adapters
and extension SDK were deleted at the cutover ([CUTOVER.md](CUTOVER.md)). Read [ARCHITECTURE.md](ARCHITECTURE.md) for
the boundaries, [EXTENSIONS.md](EXTENSIONS.md) for the public extension contract and [ENFORCEMENT.md](ENFORCEMENT.md)
for the checks CI runs.

## App layout

| Path | Owns |
| --- | --- |
| `main.tsx`, `bootstrap/` | Validates the PHP bootstrap data, configures `@wordpress/api-fetch`, registers built-in question editors, mounts the app |
| `routes.tsx`, `App.tsx` | Builds routes from the feature registry; each route sits in its own `ErrorBoundary` and `Suspense` |
| `layout/` | Navigation, the `main` landmark, route focus, `NoticesRegion` (the `core/notices` store), loading, error and "Page not found" states |
| `features/<name>/` | One feature: route pages, API client, model, hooks, tests |
| `features/registry.ts`, `features/types.ts` | The explicit feature list and `FeatureDefinition` |
| `lib/api/` | REST client (`get`, `post`, `put`, `patch`, `del`), `ApiError`/`normalizeApiError`, query strings, paged collections (`getCollection`) |
| `components/` | App-only shared components (`Pagination`) |
| `extensions/` | The extension host: registry, slots, `ExtensionBoundary`, DOM mounting |
| `styles/` | App stylesheets; they read the `--oml-*` design tokens |
| `test/` | Vitest setup and `render.tsx` helpers (`createBootstrap`, rendering with router, bootstrap and extensions) |

Shared React UI lives outside the app in `assets/src/ui/` (`Table`, `Button`, `Card`, `Alert`, `Modal`, `Tabs`,
`EmptyState`, form controls) with a PHP twin in `includes/Design/Ui.php`. Prefer `@wordpress/components` and these
shared components to new markup.

## Adding a feature

Copy the shape of an existing feature; `features/webhooks/` is a complete, small example.

```text
features/<name>/
  index.ts          FeatureDefinition(s): the only file other features may import (or public.ts)
  <Name>Page.tsx    route component, loaded lazily
  api.ts            REST calls through lib/api; validates every response
  model.ts          pure parsing, validation and payload building
  types.ts          TypeScript shapes
  use<Name>.ts      data loading, cancellation, stale-response handling
  contract.json     machine-readable REST contract (when the feature owns an API)
  *.test.ts(x)      Vitest tests next to the code
```

1. Export a `FeatureDefinition` from `index.ts`: a stable kebab-case `id`, a translated `title`, an absolute `path`, an
   optional `icon`, the `capability` the server routes require, `hidden` for routes that should not appear in the
   navigation (editors, detail pages), and `extensionRoute` when extensions know the screen by an older route such as
   `/quiz-edit/:id`. The `component` must be `lazy( () => import( /* webpackChunkName: "admin-app-<name>" */ './Page' ) )`
   so each feature is its own chunk.
2. Import it in `features/registry.ts` and add it to `features` in navigation order. Nothing is discovered
   automatically; `findRegistryProblems()` rejects duplicate ids or paths and relative or wildcard paths.
3. Read and write data only through `lib/api`. Generic type parameters do not validate JSON, so parse responses in
   `api.ts`/`model.ts`. Cancel requests on unmount or navigation, ignore stale responses and keep the user's edits when
   a save fails.
4. Raise notices through the `core/notices` store; `NoticesRegion` shows them. Do not render a second notice area.
5. Features import another feature only through its `index.ts` or `public.ts`, and never import deleted runtime code
   (`assets/src/application`, `modules`, `components.json` files, `readRuntime`, numeric Webpack IDs). The architecture
   check enforces both.

Capability checks in the client only decide what is shown. The REST permission callbacks are the real check.

## REST APIs

Every rebuilt feature ships, together with its screen:

- a hardened `WP_REST_Controller` in `includes/Rest/V1/` (or the package's `Rest/V1`): argument schemas with types,
  limits and enums, a permission callback on every route, `WP_Error` responses with correct status codes, an item
  schema and `X-WP-Total`/`X-WP-TotalPages` headers on lists;
- `docs/api/<feature>.md` describing routes, shapes, errors and security notes, with `contract.json` as its
  machine-readable form; contract tests (`contract.test.ts`) check that the client's field rules match it;
- a PHP integration test, `tests/php/<feature>-rest-integration.php`, that runs against a real WordPress, covers
  allowed and denied users and validation failures, and removes only the fixtures it tagged.

Change the PHP, TypeScript, docs and tests together when a shape changes. Stored data is test data before release, so
no compatibility layer is needed; keep documented hooks and extension points.

## Design tokens and styles

Colors, spacing, radius and control heights come from the `--oml-*` variables that `DesignSystem::variables()` emits
for admin and learner pages ([DESIGN-TOKENS.md](DESIGN-TOKENS.md), [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md)). Do not
hard-code colors; `npm run lint:css` rejects them in the shared UI styles. Add a missing token to the design system
instead of a one-off value.

## Translations

Use `__`, `_n`, `_x` and `sprintf` from `@wordpress/i18n` with the `ohmylms` domain and literal strings, so
`wp i18n make-pot` can extract them from the built bundles. See "Translations" in [DEVELOPMENT.md](DEVELOPMENT.md).

## Commands

```sh
npm ci
npm run dev          # webpack watch of the admin app and the other entries into build/sdk
npm run typecheck    # tsc for assets/src/app
npm run test:app     # Vitest: app unit and component tests
npm run lint:js
npm run check:ci     # CI's checks job: typecheck, test:app, architecture check and its tests (then npm test)
npm run build        # production build, synced into assets/dist/admin
```

A site loads `build/sdk` only when `OHMYLMS_SOURCE_ASSETS` is true; otherwise it uses the published
`assets/dist/admin`. After a build, run `npm run check:architecture` again: it checks the published bundles against
`tools/asset-budgets.json`.

Browser specs in `tests/browser/` run with `npm run test:browser` against a disposable site. They need
`OHMYLMS_TEST_URL`, an administrator in the JSON file named by `OHMYLMS_TEST_CREDENTIALS`
(`{"username": "…", "password": "…"}`) and, without Playwright's own browsers, `OHMYLMS_CHROMIUM_PATH`.
`admin-app-smoke.spec.cjs` opens the main app screens and checks the legacy order redirect.
