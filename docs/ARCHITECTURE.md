# Authored admin architecture

Current authoring batches are documented in [course authoring](api/course-authoring.md),
[syllabus authoring](api/syllabus-authoring.md), [course settings](api/course-settings.md),
and [lesson authoring](api/lesson-authoring.md).
The historical first-batch notes below describe
their original scope; use the [current roadmap](MODULARIZATION-ROADMAP.md) for present coverage.

The admin app lives in `assets/src/app`. Six WordPress pages under the OhMyLMS menu mount it (one bundle):
Dashboard (`admin.php?page=ohmylms`), Content Hub (`ohmylms-content`), Account Hub (`ohmylms-accounts`),
Commerce (`ohmylms-commerce`), Engagement (`ohmylms-engagement`) and Settings (`ohmylms-settings`). It is the
only admin: the recovered runtime and its extension SDK were deleted at the cutover ([CUTOVER.md](CUTOVER.md)).
Old addresses (`page=ohmylms#/…`, `page=ohmylms-app#/…`) keep working ([LEGACY-ROUTES.md](LEGACY-ROUTES.md)).

## Routing

- **Pages, tabs and sections** come from one table, `includes/Admin/AdminNavigation.php`: groups, tabs
  (`&tab=`, capability, optional flag filter such as `ohmylms_show_sessions_menu`) and sections (`&section=`,
  `.subsubsub` links; a section may be rendered by PHP, like Skills › Assessments). PHP registers a submenu
  only when the user can see one of its tabs and prints each page: `.wrap`, `h1.wp-heading-inline`, the
  `nav-tab-wrapper` tabs (`#ohmylms-admin-tabs`, real links: a tab change is a page load), the sections
  (`#ohmylms-admin-sections`) and the mount point `#ohmylms-app-root` with `data-page`, `data-tab` and
  `data-section`. An unknown or hidden tab falls back to the group's first tab, a missing section to the tab's
  default.
- **Addresses.** Each `FeatureDefinition` names its `group`, `tab` and optionally `section`, `view` and
  `params` (route parameters carried under another address name). `routing/address.ts` is the only place in
  the app that knows the address format: `toAddress()` turns an internal route into
  `page=…&tab=…[&section=…][&id=…][&view=…]` plus the route's own query (`page` becomes `paged`), and
  `fromAddress()` reads it back; for example `/courses/12/settings` ⇄
  `page=ohmylms-content&tab=courses&id=12&view=settings`, `/settings?tab=tax` ⇄
  `page=ohmylms-settings&tab=settings&section=tax`. `AdminNavigation::address()` builds the same in PHP for
  links (`LegacyRoutes::app_url()`). `routing/navigation-contract.test.ts` fails when the registry and the PHP
  table (`tests/fixtures/admin-navigation.json`) disagree.
- **Router.** `routing/QueryRouter.tsx` replaces the hash router: feature code keeps `useParams`,
  `useSearchParams` and `Link to="/courses/12"`. Moving inside the current page and tab uses `pushState` /
  `replaceState` (back and forward work); any other address is a full page load. It also takes over same-tab
  links outside React (the PHP section links), marks the current section, and opens an old `#/route` hash.
  `routing/links.ts` lets unsaved-changes guards recognise same-tab links; `routing/place.ts` gives screens
  the server's page/tab/section (`useAdminPlace()`), so in-page tab rows that the PHP chrome already shows
  are not repeated.

## Boundaries

- `main.tsx` and `bootstrap/` validate PHP bootstrap data, configure WordPress API-fetch middleware
  and mount the app with the place printed on the mount element. PHP `Admin/AdminNavigation.php` and
  `Assets/AdminAppAssets.php` restrict the pages and assets to administrators. Client capability checks only
  control presentation.
- `features/registry.ts` explicitly registers lazy routes. Each feature owns its API client, model,
  state/hooks and tests. Business features import their own code and shared app services, never
  recovered factories, numeric Webpack IDs or `createComponent(readRuntime)`.
- `lib/api/` owns REST requests, typed errors, query serialization, cancellation and collection
  pagination. Generic type parameters do not validate JSON: each feature validates responses at
  its boundary. WordPress supplies the REST nonce and authentication middleware.
- `layout/` owns the main landmark, route focus, notices, extension page tabs, loading, errors and not-found
  states; the page chrome is PHP's. Use shared WordPress controls and the existing design tokens. This
  rebuild adds no second design system.
- Webhooks uses page-owned state because its list and editor do not need a global singleton. Requests are cancelled on navigation and obsolete
  responses are ignored even if a transport completes after cancellation.

## First feature batch: Webhooks

The lazy `#/webhooks` route requires `manage_options`, matching its server routes. It includes paged
list/search, create/edit, mapping validation, confirmed deletion, explicit test delivery, secret
rotation and paged delivery history. New webhooks start inactive. Lists never retain signing secrets;
create/rotate secrets are kept only in the temporary reveal dialog and cleared on close/unmount.
Edits survive failed saves. Mutation buttons prevent duplicate submissions and pending requests are
cancelled on unmount. The [REST contract](api/webhooks.md) defines the matching PHP boundary.

The current batch does not implement bulk operations. The server bulk endpoint remains available;
the preview offers individual editing and deletion.
Automatic event delivery still follows the site's existing Webhooks integration setting; the screen
links to Add-ons for that control until integration settings are rebuilt. Explicit test delivery
remains a separate, confirmed action.

## Content Hub batch: core Skills catalogue

The lazy `#/content-hub/skills` route requires `edit_posts`, matching the Skills author API.
The preview page itself remains restricted to administrators until production access and extension
coverage are ready. Skills stays core, without an Add-ons switch. This batch includes bounded
name search, pagination, name/code/description editing, searchable parent and prerequisite choices,
administrator-only archive/restore, and paginated lesson/course link management. It uses the shared
Table, WordPress controls and shared app pagination; it adds no feature stylesheet or recovered UI.

`GET /skills/catalog` returns summaries and WordPress total headers, at most 50 per request (20 in
the list). It avoids the linked-post queries in full skill descriptions. Selected relation labels
are resolved through bounded `include` queries. Existing detailed `/skills` consumers remain until
their corresponding editors are rebuilt; the new app never fetches that unbounded collection.
`GET /skills/<id>/relationships/<lessons|courses>` pages editable content; the matching
`PUT .../<post_id>` endpoint changes only one link, with an exact post type and `edit_post` check.
It never replaces an unseen page of relationships or another author's links.

Skill writes validate field types/limits, parent hierarchy, prerequisite existence/cycles, objective
and translation input, and public-practice permissions before writing fields. A rejected term update
does not write metadata first. This preflight prevents partial edits caused by validation failures;
it is not a database transaction or a guarantee against concurrent prerequisite edits.

This is the catalogue batch, not full Content Hub parity. Learning-objective/translation and public
practice controls, source review/comparison/mapping tools, syllabus/curriculum workflows, courses,
lessons, quizzes, Question Bank, assignments and bundles still needed authored screens at the time; typed
screens for them now exist (parity per feature is in the roadmap) and the old runtime was deleted at the cutover.

The app suite now passes 66 tests. `tests/php/skills-catalog-rest-integration.php` exercises real
WordPress catalogue paging, create/update/reopen, validation failures without data changes, denied
users, archive/restore and exact relationship changes. It creates and removes only its tagged
fixtures, with `OHMYLMS_SKILLS_TEST=1`; it never resets the database. App-only production compilation,
TypeScript, ESLint, source contracts and asset-publishing tests pass. Targeted PHP checks have zero
errors and five query-performance warnings: one new bounded relationship taxonomy query and four
existing Taxonomy lookup/detail-query warnings. Live preview checks cover catalogue load/search,
existing editor values, parent labels, keyboard prerequisite search and the links dialog.
The 23 existing skill-map unit checks also pass. A repository-wide `git diff --check` still reports
trailing whitespace in the separately modified generated admin CSS; stylesheet cleanup remains
excluded, and those files were not changed by this batch. The targeted diff check for this batch's
tracked PHP and roadmap changes passes.

## Public extension contract

[EXTENSIONS.md](EXTENSIONS.md) remains the public API v1 contract. The app's authored host,
`assets/src/app/extensions`, is its only implementation. The native lesson and quiz editors on `post.php`
import the same registry (`registry.mjs`), REST helpers (`api.ts`) and `ExtensionBoundary.tsx` from there
(`features/learning/nativeAuthoringBootstrap.js` creates the registry when the host is not on the page).

- `admin-app-extensions` (under 5 KB) publishes the frozen `window.ohmylms.extensions` (registry methods,
  `api`, `renderSlot`, `renderEditor`, `mount`) and fires `ohmylms:extensions-ready`.
  `AdminAppAssets` loads it first, then fires `ohmylms_enqueue_extension_scripts` with its handle and
  makes the app depend on every script that action queued, so extensions register before the app renders.
- The front end uses the same host: `Slots::render()` calls `SourceAssets::enqueue()`, which loads
  `ohmylms-app-extensions` with `ohmylmsExtensionManifest` and `ohmylmsMath` and fires
  `ohmylms_enqueue_extension_scripts` with that handle; on `DOMContentLoaded` the host mounts every
  `[data-ohmylms-slot]` element (`extensions/dom.ts`, `checkout-field` kind included). The signup form's
  React fields are a separate entry, `admin-app-registration` (`ohmylms-app-registration`), enqueued on
  `ohmylms_signup_form_start`, so the host carries no chunk loader. Both are source-build only, as before;
  the recovered SDK no longer exists.
- `/extensions/<id>` renders registered admin pages, each a tab `ext-<id>` on the page of its `group`
  (Settings by default; `layout/ExtensionTabs.tsx` adds the tab to the PHP tab row). Every route renders the
  `admin.screen.before`/`after` slots and the editor panels whose slot is the feature's
  `extensionRoute`: the original admin route pattern (for example `/quiz-edit/:id` for `/quizzes/:id`),
  so existing extensions keep their keys. Context is the route params plus `route` and `hash` (the
  screen's route as the hash router had it, `#/quizzes/5`).
  Integrations renders `integration` panels.
- Each entry renders inside `ExtensionBoundary`: a failure shows an alert for that extension only and emits
  `ohmylms:extension-error`.

- Question editors render in the Question Bank editor (`#/content-hub/question-bank/<id>`) for types
  whose `editor.format` is not `ohmylms-options`, inside `ExtensionBoundary` and `Suspense`, with
  `{question, value, onChange}`. The app registers the 15 built-in settings editors (numerical,
  structured and the interactive types) into the host in `bootstrap/mount.tsx`, after extension
  scripts ran, through the same `registerCoreQuestionEditors()`
  (`assets/src/features/question-editor/registerCoreEditors.js`); an editor an extension registered first
  is kept. The components are authored modules in the lazy
  `admin-app-question-editors` chunk (`extensions/coreEditors.ts`). On the app screen
  `AdminAppAssets` enqueues what they use: the learners' interactive widgets
  (`Interactive::enqueue()`, `window.OhMyLMSInteractive`), the media library, the math field styles
  and `window.ohmylmsMath` (`MathLive::config()`, on the host handle) for the on-demand math runtime.

Lesson editors and membership settings are registered and listed but are not yet rendered: they need
the rebuilt lesson-type and membership extension-settings screens. Checkout fields and frontend slots
render through the host (see above).

## Rebuild policy and checks

Stored data does not require migration wrappers. Change PHP, TypeScript, validation and tests together
when redesigning a shape. Preserve documented hooks and extension points, authentication, capability
checks, server grading and extension privacy. Ask before destructive actions outside the repository.

Run `npm run typecheck`, `npm run test:app`, `npm run lint:js`, and the relevant PHP and browser checks.
`npm run build:sdk` builds the authored entry and lazy features using WordPress React externals.
`tools/sync-admin-app.mjs` publishes the entry, maps, licenses and named `chunks/admin-app-*` feature
assets; the normal full-build synchronizer calls it too. Feature chunks have content hashes so a
new entry cannot accidentally reuse a cached chunk from an older build.
The preview uses `build/sdk` with source assets enabled and `assets/dist/admin` otherwise. Save/reopen
and denied-user checks must pass before feature cutover. No startup speed-up is claimed without a
benchmark.

## Verification of the first batch (30 October 2026)

- TypeScript type checking and app ESLint passed. App tests cover routing/capabilities, runtime JSON
  validation, pagination, save errors, one-time secrets, confirmation and request cancellation.
- The existing Webhooks REST integration suite passed 181 checks against WordPress with outbound HTTP
  intercepted. It checks denied users as well as allowed requests and cleans up its tagged fixtures.
- Live preview creation and full-page reopen confirmed persistence of an inactive verification
  webhook. No test delivery was sent. This inactive example remains available for review.
- The preview excludes the recovered extension SDK and keeps one main landmark inside WordPress.
- The SDK build succeeds; existing large email/editor/math asset warnings remain. This is not a claim
  that the complete LMS rebuild, bulk UI, extension integration or production cutover is finished.
