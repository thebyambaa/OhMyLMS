# Legacy design audit

Updated 30 October 2026 after frontend modularization and admin stylesheet extraction.

## Removed runtime layers

- The independent learner-palette helper and its obsolete tests.
- The separate learner button bridge and course/lesson override stylesheet.
- The recovered full frontend CSS inputs. Authored SCSS now builds the page modules and compatibility URLs.
- The plugin's duplicate WordPress block-editor stylesheet and unused vendor CSS bundle.
- Unreferenced fingerprinted outputs identified by build metadata.
- Eight earlier exact duplicate admin rules. Their later copies remain in the same feature group,
  direction, layer and conditional scope, so the final cascade is preserved.

## Shared implementation

Frontend requests load `oml-ui.css`, core and the detected page groups. Page modules supply layouts;
legacy button, form-control and progress selectors share component declarations in `oml-ui.css`.
The layout-3 progress bar retains its width allocation and uses the shared progress appearance.
Admin feature styles are authored in `assets/src/design-system/admin` and loaded in manifest order.
Automatic group detection scans application content and body-level portals, excluding WordPress
navigation and toolbar entries. Explicit feature-loading calls remain supported.

## Compatibility and dependencies to retain

| Item | Why it remains |
| --- | --- |
| Public `.ohmylms-*` selectors and legacy token aliases | Templates, scripts, builders and add-ons consume these contracts. They resolve to shared components/tokens rather than a second palette. |
| Generated `style.css`, `style-rtl.css` and admin compatibility entrypoints | Integrations may request the old URLs. Normal plugin requests use modules. |
| Recovered front-end, block and theme assets in `runtimes`/`static` | The 79 assets `manifest.json` still lists. The recovered admin application, its email editor, email shell, automation vendor styles and admin CSS were deleted at the cutover ([CUTOVER.md](CUTOVER.md)). |
| Assessment canvas, artwork and media palettes | Intentional visual contracts documented in the design language. |

See `STYLESHEET-OWNERSHIP.md` for ownership, measurements and remaining optimization boundaries.
Do not remove active dependencies solely because the shared-component gallery passes.
