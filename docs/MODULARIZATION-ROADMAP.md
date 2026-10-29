# Modularization roadmap — 29 October 2026

The LMS counts as fully modular when each feature owns its UI, state, API access, styles and
tests, with explicit imports and documented interfaces. The largest remaining job is removing
dependence on the recovered application runtime. Feature folders exist, but their components
are still created with `createComponent(readRuntime)` and receive stores, controls, helpers
and other components from recovered factory 1841.

Current numbers live in the generated [runtime dependency inventory](RUNTIME-DEPENDENCY-INVENTORY.md).
To regenerate it:

```bash
node tools/runtime-inventory-report.mjs --write
```

`tests/js/runtime-inventory.test.mjs` runs as part of `npm test`. It fails when a binding has no
owner, when a component reads a binding its `components.json` does not declare, or when the
report is stale.

## Invariants for every step

- Course IDs, enrollments, progress, quiz attempts, extension fields and saved email content
  stay unchanged. No step rewrites stored data unless a compatible migration ships with it.
- Before a feature's old implementation is retired, it must pass a real WordPress save →
  reopen check and a permission check (allowed and denied user).
- Public hooks, REST fields, PSR-4 class/file mappings and versioned assessment contracts
  stay compatible (see `AGENTS.md`).
- Modularization and performance need separate evidence. A migration claims no speed-up
  without a benchmark.

## Steps

| # | Work | Done when | Status |
| --- | --- | --- | --- |
| 1 | Dependency inventory | Every recovered library and LMS-owned module has an owner and plan | **Done**: generated report, zero unassigned bindings, drift test |
| 2 | Easy Email and remaining libraries | Email UI, editor core, source editor, html2canvas and embedded vendor copies use maintained packages or documented replacements; saved templates still open | Partly done (MJML, Arco shared factory, Chart.js, Emotion, Tiptap, Lowlight) |
| 3 | Shared application infrastructure | Routing, controls, notifications, permissions, configuration and utilities have named modules and public exports | In progress: notices, skeleton, avatar, spinner, tooltip, copy button, table, icons and 41 of 43 used controls are authored modules under `assets/src/shared`. Routing, permissions, configuration, `AdvancedSelectWP`, `SearchSelectWP` and the admin-menu, HTML-text and media helpers remain |
| 4 | State and API access | Features import their stores, hooks and API clients directly | Not started |
| 5 | Feature independence | Features use normal components and explicit imports, not `createComponent(readRuntime)` | Components extracted; they still read the runtime |
| 6 | Authored entry point | A small bootstrap registers routes, initializes services and loads features. Production startup needs no numeric Webpack IDs or adapters | Not started |
| 7 | Stylesheet ownership | Tokens and controls are centralized; feature styles load with their features; recovered CSS snapshots are retired | In progress: page-specific frontend modules, orphan and snapshot cleanup done; see [stylesheet ownership](STYLESHEET-OWNERSHIP.md) |
| 8 | Backend boundaries | PHP modules separate REST controllers, business rules and persistence; cross-feature access goes through services | Not started |
| 9 | Asset and dependency isolation | Each page type loads only its modules; one deliberate React; no accidental duplicate runtimes | Partly done (lazy email/editor chunks) |
| 10 | Enforcement and release checks | Automated checks catch circular imports, forbidden cross-feature imports, compatibility regressions, security findings and build drift | Inventory drift check added |

Sequence: remaining email dependencies → shared infrastructure → state/API modules →
independent features → authored bootstrap → retire recovery adapters. Backend, styles,
security and testing progress alongside.

## Owners and plans from the inventory

### Step 2: libraries (owner: `features/vendor-packages`)

- **Easy Email** (see [the audit](EASY-EMAIL-AUDIT.md): a locally patched copy of an unidentified release; swap core first, behind a golden-template corpus)
  (about 2.2 MB across chunk 355 and the shared bundle) is the largest remaining
  family. It also embeds private copies of MJML, Arco, CodeMirror, html2canvas and
  `@wordpress/icons`. Move the whole package rather than individual internals. Removing pieces
  one at a time risks the editor's form and selection contracts. Before switching, capture
  every saved template's JSON and MJML, then compare it after reopen and re-save.
- **Moment** (138 factories) and **Lodash** (33): features read them directly as `aN` and `M`.
  Replace each with `@wordpress/date` or Day.js, or with native code, one feature at a time.
  Check every date format string, including the localized strings.
- **React Router** (`f`, `v`, read by 12 features): becomes a pinned npm dependency owned by
  the step 6 bootstrap. Features receive navigation through a shared routing module, not
  through the router directly.
- **Bundled copies of `@wordpress/icons` and the JSX runtime** go back to WordPress externals.
- **231 unidentified vendor factories** (about 530 KB) must be assigned to packages before
  step 9 can claim no duplicate runtimes.

### Step 3: shared infrastructure (owner: `assets/src/application/shared`, public exports)

The most-read shared bindings, in suggested extraction order:

| Module to create | Recovered source | Read by |
| --- | --- | --- |
| `controls` | factory 38093 (`I`, `*WP` wrappers) | 18 features |
| `notices` | factory 71046 (`z`) plus the toast library | 13 |
| `admin-menu` / routing helpers | `HG` (admin menu highlight), router bindings | 14 |
| `html` utilities | `Ge` (safe text from HTML) | 12 |
| `table`, `skeleton`, `tooltip`, `switcher`, `avatar` | factories 65, 68119, 6425, 25946, 53725 | 2–10 |
| `media` controls | `df`, `uf`, `nf` (course-thumbnail-controls fragment) | 4–13 |
| `config` | `ohmylms_params` readers | several |

`assets/src/ui` already holds authored controls. Prefer extending it over adding a second kit.

### Step 4: state and API (owner: each feature, with a thin shared REST client)

Factory 86169 (`T`) is one store-plus-client read by 123 components in 17 of the 18 wired features. Split it by REST
namespace into per-feature `api.mjs` modules and stores. `courses/api.mjs` and
`courses/structureClient.js` already show the pattern. The plugin-installer client (`He`,
18 components) belongs in shared infrastructure. Each split needs the permission check from
the invariants: the client must not widen which capability can reach a route.

### Step 5: feature independence (owner: the named feature)

The report lists, for each feature, the recovered locals only that feature uses. They move
into the feature as authored modules. The 480 inlined Babel helper bindings need no work of
their own; they disappear as each component is rewritten as normal JSX.

Cross-feature reads (for example, settings reading `GamificationPage`, `EmailSettingsPage` and
`WebhooksPage`) must become documented public exports before step 10 can forbid the rest.

`assets/src/features/commerce-reference` is a 22-component copy of `commerce` that no loader
imports. Retire it once nothing else refers to it.

### Step 6: authored entry point (owner: bootstrap)

The recovered router mounts 31 routes. Twenty-seven already render authored feature components.
`/quiz-edit/:id` and `/students` still render recovered screens. `/license` and
`/free-vs-pro` point at bindings that are no longer in factory 1841 and need a decision:
retire them or reimplement them.

## Separate follow-ups

- Benchmark startup, navigation, editors, API requests and database queries before and after
  each step.
- Fix the local DNS delays found earlier on their own; they are unrelated to modularization.
