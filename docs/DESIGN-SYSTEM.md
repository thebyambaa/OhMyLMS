# Backend design system

One look for every OhMyLMS admin screen: the React app (Dashboard, Content Hub, course and quiz editors, Account Hub, Settings), the PHP pages (Skill assessments, Import questions, Schools, xAPI) and the plugin's post screens. Learner pages are **not** affected; they keep their own branding options.

## Where it lives

| Piece | File |
| --- | --- |
| Data, sanitizing, presets, contrast, CSS variables | `includes/Design/DesignSystem.php` |
| REST (`GET/POST/DELETE /ohmylms/v1/design-system`, `manage_options`, REST nonce) | `includes/Rest/V1/DesignSystemController.php` |
| Shared stylesheet (reads the variables) | `assets/css/admin-design-system.css` |
| Settings tab "Design system" | `assets/src/features/settings/DesignSystemSettings.jsx`, model helpers in `designSystem.mjs` |
| Tests | `tests/php/design-system-unit.php`, `tests/js/design-system.test.mjs` |

Stored in one option, `ohmylms_design_system`: `preset`, `colors` (13 keys), `button_radius`, `density`. A site that never saved it gets the **Khan Academy** preset. The preset name is recomputed on every save, so editing any value gives `custom`.

Scope: a `oml-ds` class is added to `<body>` on screens whose id contains `ohmylms` or whose post type is a plugin type. Every variable and rule is under `body.oml-ds`, so other wp-admin pages are untouched. The variables also override the older `--wp-admin-theme-color`, `--wp-components-color-accent`, Arco `--primary-1..9` and `--ohmylms-*` names on those screens, so existing feature CSS follows the choice. (The older `ohmylms_admin_*` colour options therefore no longer change plugin screens; the font option still does.)

## Variables

Colors: `--oml-primary` (+ `-hover` 15% darker, `-active` 30%, `-soft` 90% tint, `-rgb`), `--oml-on-primary` (white or black, whichever has the higher WCAG contrast), `--oml-secondary`, `--oml-success`, `--oml-warning`, `--oml-danger` (+ hover/active/soft/on), `--oml-info`, `--oml-link`, `--oml-heading`, `--oml-text`, `--oml-muted`, `--oml-border`, `--oml-surface`, `--oml-subtle`, `--oml-background`, `--oml-focus` (primary, or heading when the primary is too light for a 3:1 ring).
Shape: `--oml-radius-button` (0–24px or 999px for pill), `--oml-radius-control` (min(button, 8px)), `--oml-radius-card` (control + 4px, 0 when square).
Spacing (4px grid): `--oml-space-1..8` = 4, 8, 12, 16, 24, 32, 48, 64px at Comfortable; Compact multiplies by 0.75, Spacious by 1.25.
Buttons: heights 28/36/44 (Compact 24/32/40, Spacious 32/40/48), horizontal padding `space-2/4/5`.
Native controls: `--oml-color-scheme` is `light` in the normal token scope and `dark` in dark scopes,
so select menus and date/time pickers follow the chosen theme independently of the device preference.

Roundness presets: Square 0, Slightly rounded 4, Rounded 8, Pill 999 (the slider covers 0–24; pill is a preset only).

Sanitizing: colors through a strict `#rrggbb` check (3-digit expands), radius clamped, density and preset whitelisted. The generated CSS contains only validated `--name:value;` declarations: colors, numeric dimensions/type/motion, generated shadows, and the fixed `light`/`dark` native color-scheme keywords.

## Khan Academy preset (default)

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
- In `admin-ui.css`, `question-editor`, `skill-studio`, `course-objectives`, `skill-matrix`, `quiz-overview`, `quiz-design`, `question-stage`, `admin-assistant`, `syllabus-comparison` and `learning-program`, hard-coded purple hover shades, red, borders, and 4/6/7/8px radii now read the variables (with the old value as fallback, so the files still work off-screen).

## Known gaps

- The recovered application bundle (`assets/src/application/**`, generated) still hard-codes `#6e42d3`/`#6E42D3` in some inline icon fills and borders (earnings icons, a few selected-state borders, badge editors). These are not edited by hand; they stay purple until the recovered source is rebuilt from authored source.
- Remaining one-off values in feature CSS (10px/12px radii, 6/10/14px gaps, assorted greys and tints such as `#efe9fb`, `#e3e6eb`) are not yet on the tokens.
- Small inline `style=""` attributes on PHP pages (e.g. the xAPI delivery table `max-width`) are layout only and were left.
- Cart/checkout-style learner screens and learner pages are out of scope.
