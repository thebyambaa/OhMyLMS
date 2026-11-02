# Backend design system

One look for every OhMyLMS admin screen: the React app (Dashboard, Content Hub, course and quiz editors, Account Hub, Settings), the PHP pages (Skill assessments, Import questions, Schools, xAPI) and the plugin's post screens. Learner pages read the same saved palette through `--oml-*` (`docs/DESIGN-TOKENS.md`); this document is the admin contract and the stored preset values.

## Current status — 2 November 2026

**The shared foundation is implemented and broadly adopted. Complete screen-by-screen consistency and
accessibility have not been established by this review.** See [Design language](DESIGN-LANGUAGE.md) for
the migration inventory and [Design tokens](DESIGN-TOKENS.md) for the token contract.

| Area | Status |
| --- | --- |
| Tokens | Implemented: colors, spacing, typography, radii, shadows, motion and layers |
| Shared components | Implemented in React and PHP: Button, Card, Badge, Progress, Input, Select, Alert, Tabs, Modal, Table and EmptyState |
| Site customization | Implemented: WordPress, Khan Academy, Classic and custom presets; density, roundness and font settings |
| Theme support | Learner light/dark/system token output implemented; admin dark mode is not available |
| Adoption | Broad migration documented across admin, learner and assessment screens; compatibility styling and intentional palette exceptions remain |
| Enforcement | Architecture rules, token lint rules, component tests and browser screenshot suites exist; their scope is narrower than a complete visual/accessibility audit |

### Evidence from this review

- `node tools/check-architecture.mjs`: passed locally on 2 November 2026, including the current admin
  color/spacing rules and asset budgets. This does not check every screen's rendered appearance.
- `node --test tests/js/ui-components.test.mjs tests/js/ui-button.test.mjs`: 9 checks, 7 passed,
  0 failed, 2 skipped. PHP/React class-name parity checks skipped because PHP was not on PATH; set
  `OHMYLMS_PHP` to a suitable PHP executable to run them.
- The code and documentation were inspected. Browser screenshot suites, the PHP design-system suite
  and a full accessibility audit were **not rerun** for this status review. Existing screenshots and
  historical verification notes are coverage references, not fresh passing results.

### Remaining work and acceptance

1. **Verify adoption by screen.** Audit the admin, native editors, learner pages and assessment chrome
   against the shared component gallery. Record screen, viewport, preset/theme and remaining one-off
   styles; migrate accidental duplication while preserving public selectors and extension behavior.
2. **Review exceptions.** Classify the spacing/radius literals and palette exceptions below as intentional
   or migration work. Keep media, artwork, assessment and print palettes where they serve a documented
   purpose. Introduce semantic tokens only where a repeated UI role needs them; extend lint coverage
   for each migrated source and remove obsolete exceptions.
3. **Complete verification.** Rerun the PHP design-system suite, React/PHP parity checks, component gallery
   and affected browser specs. Check keyboard operation, focus, labels, contrast, RTL, narrow layouts,
   long Mongolian/English text and learner light/dark/system behavior. Record commit and results;
   update screenshot baselines only for intentional changes.
4. **Decide admin dark-mode scope separately.** Learner dark tokens do not establish support for
   wp-admin or WordPress component portals. If admin dark mode is requested, plan its scope and tests
   before enabling it; it is not required to preserve the current WordPress-admin design contract.

Completion means the supported screen inventory is reviewed, remaining exceptions have reasons and
owners, and required component, PHP and browser checks pass with accessibility findings recorded and
resolved or explicitly tracked. A passing architecture check alone does not establish completion.

## Where it lives

| Piece | File |
| --- | --- |
| Data, sanitizing, presets, contrast, CSS variables | `includes/Design/DesignSystem.php` |
| REST (`GET/POST/DELETE /ohmylms/v1/design-system`, `manage_options`, REST nonce) | `includes/Rest/V1/DesignSystemController.php` |
| Shared stylesheet (reads the variables) | `assets/css/admin-design-system.css` |
| Settings tab "Design system" (`#/settings?tab=design-system`) | `assets/src/app/features/settings/DesignSystemForm.tsx`, model in `designSystem.ts` (see `docs/api/settings.md`) |
| Tests | `tests/php/design-system-unit.php`, `assets/src/app/features/settings/DesignTabs.test.tsx` |

Stored in one option, `ohmylms_design_system`: `preset`, `colors` (13 keys), `button_radius`, `density`. A site that never saved it gets the **WordPress** preset. The preset name is recomputed on every save, so editing any value gives `custom`; when two presets have the same values (WordPress and Khan Academy share a palette) the requested name wins.

Scope: a `oml-ds` class is added to `<body>` on screens whose id contains `ohmylms` or whose post type is a plugin type, and the variables are under `body.oml-ds`, so other wp-admin pages are untouched. A branded preset (Khan Academy, Classic, custom) also adds `oml-ds-branded`; the restyle in `admin-design-system.css` (buttons, inputs, notices, cards, tables, headings, links, page background) is scoped to it, and its variables override `--wp-admin-theme-color`, `--wp-components-color-accent`, Arco `--primary-1..9` and the `--ohmylms-*` names so existing feature CSS follows the choice. (The older `ohmylms_admin_*` colour options no longer change plugin screens; `ohmylms_admin_font_family`, set under Settings > Fonts, still sets `--oml-font-family` here under every preset; see "Fonts" in `docs/DESIGN-TOKENS.md`.)

## WordPress preset (default)

Admin screens follow each user's WordPress admin colour scheme (Users › Profile › Admin Color Scheme), like the rest of wp-admin. `DesignSystem::admin_scheme()` reads the user's `admin_color` (`modern` when unset, as WordPress prints `admin-color-modern`); `scheme_theme_color()` maps the core schemes to their `--wp-admin-theme-color` (Default/modern `#3858e9`, Fresh `#2271b1`, Light `#007cba`, Blue `#437aa8`, Coffee `#916745`, Ectoplasm `#646c3e`, Midnight `#cf4339`, Ocean `#567958`, Sunrise `#ad631e`, from `wp-includes/css/dist/base-styles/admin-schemes.css`) and uses a plugin-registered scheme's highlight colour (`$_wp_admin_css_colors[ $scheme ]->colors[2]`). `admin_colors()` puts that theme colour (primary, secondary, info) on WordPress's neutrals and notice colours (text `#3c434a`, headings `#1d2327`, muted `#646970`, borders `#c3c4c7`, page `#f0f0f1`, links `#2271b1`, success `#00a32a`, warning `#dba617`, danger `#d63638`), with the 2px WordPress radius, so `--oml-*` in the app matches the scheme. `--wp-admin-theme-color` and the `@wordpress/components` variables are not printed, so WordPress's scheme CSS applies, and `oml-ds-branded` is absent, so WordPress's own button, form, notice and list-table styles apply.

The stored palette of this preset is the Khan Academy one, so learner pages look as before. The REST snapshot adds `admin_scheme` and `admin_colors` (the requesting user's), which the Design system tab shows in the preset help; choosing Khan Academy, Classic or editing a colour brands the admin again.

## Variables

Colors: `--oml-primary` (+ `-hover` 15% darker, `-active` 30%, `-soft` 90% tint, `-rgb`), `--oml-on-primary` (white or black, whichever has the higher WCAG contrast), `--oml-secondary`, `--oml-success`, `--oml-warning`, `--oml-danger` (+ hover/active/soft/on), `--oml-info`, `--oml-link`, `--oml-heading`, `--oml-text`, `--oml-muted`, `--oml-border`, `--oml-surface`, `--oml-subtle`, `--oml-background`, `--oml-focus` (primary, or heading when the primary is too light for a 3:1 ring).
Shape: `--oml-radius-button` (0–24px or 999px for pill), `--oml-radius-control` (min(button, 8px)), `--oml-radius-card` (control + 4px, 0 when square).
Spacing (4px grid): `--oml-space-1..8` = 4, 8, 12, 16, 24, 32, 48, 64px at Comfortable; Compact multiplies by 0.75, Spacious by 1.25.
Buttons: heights 28/36/44 (Compact 24/32/40, Spacious 32/40/48), horizontal padding `space-2/4/5`.
Native controls: `--oml-color-scheme` is `light` in the normal token scope and `dark` in dark scopes,
so select menus and date/time pickers follow the chosen theme independently of the device preference.

Roundness presets: Square 0, Slightly rounded 4, Rounded 8, Pill 999 (the slider covers 0–24; pill is a preset only).

Sanitizing: colors through a strict `#rrggbb` check (3-digit expands), radius clamped, density and preset whitelisted. The generated CSS contains only validated `--name:value;` declarations: colors, numeric dimensions/type/motion, generated shadows, and the fixed `light`/`dark` native color-scheme keywords.

## Khan Academy preset

Source: Khan Academy Wonder Blocks tokens, `packages/wonder-blocks-tokens/src/tokens/color.ts` and `spacing.ts` (github.com/Khan/wonder-blocks, read 2026-10-17). khanacademy.org itself was not needed: the design system repository is the source of the brand colors.

| Token | Value | Wonder Blocks origin |
| --- | --- | --- |
| primary, link, info | `#1865f2` | `blue` |
| danger | `#d92916` | `red` |
| success | `#00a60e` | `green` |
| warning | `#ffb100` | `gold` |
| heading | `#0b2149` | `darkBlue` |
| text | `#21242c` | `offBlack` |
| muted | `#5f6167` | `fadedOffBlack72` (offBlack at 72% over white) |
| border | `#dcdcdd` | `fadedOffBlack16` |
| secondary (button outline) | `#909296` | `offBlack50` over white |
| background | `#f7f8fa` | `offWhite` |
| surface | `#ffffff` | `white` |
| spacing | 4, 8, 12, 16, 24, 32, 48, 64 | `spacing` (xxxSmall_4 … xxxLarge_64) |
| button radius | 4px | Khan buttons are slightly rounded; chosen, not read from a token |

Derived shades (hover, active, soft) are computed by mixing with black or white, so they approximate but are not Wonder Blocks' own `activeBlue` (`#1b50b3`). The brief's guessed `#0A2A66` heading and `#626569` muted were replaced by the real tokens above.

Classic preset: the previous look: primary `#6e42d3`, heading/text `#000d25`, muted `#7a8b9a`, success `#35bd4c`, danger `#cc1818`, warning `#b45309`, info `#2271b1`, borders `#dcdcde`, page `#f0f0f1`, 4px buttons, Comfortable.

## Audit (before this change)

Buttons were styled five ways: WordPress `.button/.button-primary` (PHP pages, 2–3px radius, WordPress blue), `@wordpress/components` Button (2px radius, color from `--wp-admin-theme-color`), Arco buttons in the recovered app bundle (own radius and a purple scale), feature classes in `admin-ui.css` (`.ohmylms-ws-add-content`, chips, icon buttons) and Tailwind utilities.

Radii used in the admin stylesheets (declarations): 8px ×33, 4px ×30, 6px ×16, 7px ×12 (`--ohmylms-admin-radius`), 10px ×7, 50% ×6, 999px ×5, 12px ×8, 0 ×4, others once or twice (2, 5, 9, 16, 20, 24). Gaps in `admin-ui.css`: 8px ×34, 12px ×22, 6px ×11, 10px ×9, 16px ×6, 24px ×5, 4px ×5; padding 12px ×12, 16px ×11, 24px ×7, 10px 12px ×6, 14px 16px ×4. Only 4/8/12/16/24 sit on the grid; 6, 10, 14 do not.

Colors hard-coded in `admin-ui.css`: `#dcdcde` ×30, `#6e42d3` ×16, `#000d25` ×13, plus ten purple hover/tint variants and several greys and reds (`#cc1818`, `#b45309`).

Deviating screens: PHP pages (Skill assessments, Import questions, Schools, xAPI) used stock WordPress blue buttons and WordPress greys, so they did not match the React screens at all; Content Hub and the editors used the purple with square-ish 2px buttons; Settings used 4px inputs at 40px height.

## What changed

- The shared stylesheet restyles `.button*`, `.page-title-action`, `.components-button` (primary, secondary, tertiary, link, destructive, small/large, disabled), Arco `.arco-btn*`, inputs and selects, cards, notices, tables, headings and page padding, with one `:focus-visible` ring.
- In the pre-cutover `admin-ui.css`, `question-editor`, `skill-studio`, `course-objectives`, `skill-matrix`, `quiz-overview`, `quiz-design`, `question-stage`, `admin-assistant`, `syllabus-comparison` and `learning-program`, hard-coded purple hover shades, red, borders, and 4/6/7/8px radii were pointed at the variables.
- Those legacy-screen stylesheets (`admin-ui.css`, `quiz-overview.css`, `question-stage.css`, and the others listed) and the Arco/Tailwind styles were deleted with the recovered admin at the cutover ([CUTOVER.md](CUTOVER.md)). The typed app styles itself through `@wordpress/components`, `assets/src/ui` and the `--oml-*` variables. Live question authoring chrome is `assets/css/question-editor.css` (tokens + lint; quiz stage/answer palettes remain documented exceptions).

## Known gaps

- App stylesheets under `assets/src/app` take padding, margin, gap and radius from `--oml-space-*` and `--oml-radius-*`. The architecture check rejects any other raw px on those properties except `0`, `1px` and `2px`. Still literal: `50%` circles (dashboard attention marker, at-risk avatar, site-health mark); the gradebook enrollment-source pill at `999px` (allow-listed, because `--oml-radius-button` is 2px on the WordPress preset and there is no separate pill token); hairline `1px` and `2px` where the next space step would open a tight group (period-control overlap `-1px`, pulse `gap: 2px`, heatmap header and cell padding `2px`, gradebook chip margins `1px`/`2px`, manual-mark offset `2px`, dialog-radio `margin: 2px 0`, level-chip vertical padding `2px`); and gradebook rollup depth 2–3 at `padding-inline-start: 3.5rem` (56px at a 16px root), halfway between `--oml-space-7` (48px) and `--oml-space-8` (64px). `assets/src/ui` has no stylesheets.
- Small inline `style=""` attributes on PHP pages (e.g. the xAPI delivery table `max-width`) are layout only and were left.
- Learner colour declarations in `assets/src/frontend/scss` read `--oml-*`, with the previous hex kept only as a `var()` fallback. The page modules in `assets/css/frontend` are generated from that SCSS, not a second palette to recolour by hand (the compatibility bundle `assets/css/style.css`, 483,294 bytes, and `style-rtl.css` was removed in 1.2.22). Still outside the tokens: the video player's fixed media palette, star-rating artwork, select chevrons, one notice glyph, and two-paint lesson-type icons (`tools/learner-colour-allowlist.json`). Exam-print colours stay in `paper.css`.
