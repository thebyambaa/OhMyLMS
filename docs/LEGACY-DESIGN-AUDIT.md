# Legacy design dependency audit

Audited on 29 October 2026 after the unified Design system implementation.

The shared settings, tokens and components are implemented. The replacement of every legacy runtime,
template selector and stylesheet is **not complete**, so a wholesale legacy-code deletion is unsafe.

## Removed

- `assets/src/features/settings/theme.mjs`: the separate learner-palette helper, formerly imported by
  `ThemeSettings.jsx`. The Colors & fonts screen now links to the shared Design system editor. A
  repository reference search found only the helper's own tests as consumers, with no remaining
  runtime, manifest or documented public API references.
- `tests/js/design.test.mjs`: tests exclusively for that removed helper and its obsolete four-color
  option-writing contract. The shared preset behavior remains covered by `design-system.test.mjs`
  and `tests/php/design-system-unit.php`; the browser gallery checks the shared admin/learner primary.

## Keep: active dependencies

| Layer | Evidence | Requirement before removal |
| --- | --- | --- |
| `assets/css/style.css` and its recovery input | Registered as `ohmylms-frontend` in `includes/Assets/FrontendAssets.php`; `learning-pages.css` loads after it | Replace all layout, widget, checkout, archive, account and other rules still supplied by this baseline, then switch enqueueing and verify those screens |
| `assets/css/oml-legacy-buttons.css` and `.ohmylms-button` | `Ui::attach_legacy_buttons()` loads the bridge; 61 template lines still reference legacy button classes. Scripts, builder integrations and custom CSS also use these selectors | Migrate templates and their JavaScript hooks, retaining documented compatibility for consumers of public selectors |
| Legacy `--ohmylms-*` aliases and `Tokens::color()` aliases | Checkout, archive, dashboard and shared base SCSS still consume them; published integrations can also depend on them | Migrate remaining authored consumers, rebuild their outputs, and provide a compatible public-token migration |
| Recovered `assets/src/runtimes`, `modules`, `application` and `static` inputs | `assets/src/manifest.json` and `tools/build-assets.mjs` still assemble shipped assets from them | Replace the corresponding runtime/factory or static input and its build-manifest contract, with application parity and relevant feature tests |
| Admin compatibility CSS entrypoints | The builder emits `admin-ui.css`, `ohmylms.css` and `ohmylms-rtl.css`; PHP retains fallback URLs for builds without modular metadata | Remove or migrate fallback consumers and packaging requirements before deleting the outputs |
| Historical fingerprinted CSS | Current module metadata selects the active files; older filenames may still be referenced by cached documents | Establish a cache-retention/invalidation policy before pruning deployed historical assets |

The runtime source-recovery tree is not a second design system: it still supplies application behavior
and base layouts. The compatibility aliases now resolve to the single shared Design system palette.
Assessment canvases, media controls and documented artwork palettes are intentional contracts rather
than obsolete design settings.

## Safe next sequence

1. Migrate remaining legacy markup/hooks and the checkout, archive and account/base styles.
2. Build those authored styles as complete replacements for the required baseline rules.
3. Verify the relevant routes and integrations with the old layers disabled in a test environment.
4. Remove each proven-unused layer, update its build/enqueue metadata, and rerun the checks.

Do not infer that an alias, file or stylesheet is unused solely because the component gallery passes.
