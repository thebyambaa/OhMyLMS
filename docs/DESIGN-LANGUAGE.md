# Design language

How every OhMyLMS screen should look and be built, so a learner moves from a course page to a quiz to a
dashboard and it feels like one product. The values are the `--oml-*` tokens (`docs/DESIGN-TOKENS.md`);
this page says how to use them.

## Principles

1. **Tokens, never literals.** Colors, spacing, radius, type size, shadow and motion come from `--oml-*`.
   A hex color in a component is a bug: a site that changes its brand color would leave it behind.
2. **Components, never one-offs.** If a Button, Card, Badge or Progress exists, use it. Do not write a
   `*-button` class for a single feature. If a variant is missing, add it to the component.
3. **Same markup on both sides.** React (`assets/src/ui`) and PHP (`Ui::*`) emit identical class names.
4. **Players keep a playful palette, chrome stays shared.** Quiz and exam stage, prompt and answer colors
   (`--ohmylms-quiz-*`) are their own palette. The toolbar, buttons, cards, text, borders and focus ring
   around them use the shared tokens.
5. **Accessible by default.** Visible `:focus-visible` ring (`--oml-focus`), text on a brand color uses
   `--oml-on-primary`, progress bars carry `role="progressbar"` and a label, disabled controls use the
   muted tokens.

## Color roles

| Role | Tokens | Use for |
| --- | --- | --- |
| Brand | `--oml-primary`, `-hover`, `-active`, `-soft`, `--oml-on-primary` | Main actions, selected state, links to the brand |
| Neutral surfaces | `--oml-background`, `--oml-surface`, `--oml-subtle` | Page, cards and panels, quiet fills |
| Text | `--oml-heading`, `--oml-text`, `--oml-muted`, `--oml-link` | Titles, body, secondary text, links |
| Lines | `--oml-border`, `--oml-secondary` | Dividers, outlines, secondary button border |
| Status | `--oml-success`, `--oml-warning`, `--oml-danger`, `--oml-info` (+ `-soft`) | Feedback, badges, destructive actions |
| Focus | `--oml-focus` | Focus ring |
| Shade | `--oml-shade` | A dark color in both themes, for darkening an accent (for example a tactile button edge) |

## Type

Sizes `--oml-font-size-xs|sm|md|lg|xl|2xl` (12, 13, 14, 16, 20, 24 px), line heights `--oml-line-tight`
(1.2) and `--oml-line-normal` (1.5), weights `--oml-weight-regular|medium|strong` (400, 500, 600). Body
text is `md`; card titles `lg`; page titles `2xl`. Fonts come from the Design settings.

## Space and shape

Spacing is a 4px grid, `--oml-space-1` to `--oml-space-8` (4, 8, 12, 16, 24, 32, 48, 64 at the Comfortable
density). Radius: buttons `--oml-radius-button`, inputs `--oml-radius-control`, cards
`--oml-radius-card`. Pills (badges, progress) use a fully round radius.

## Elevation, motion, layers

Shadows `--oml-shadow-sm` (cards at rest) and `--oml-shadow-md` (raised, hover). Transitions use
`--oml-motion-fast` (120 ms, color changes) and `--oml-motion-base` (200 ms, movement). Layers:
`--oml-z-dropdown` 100, `--oml-z-modal` 1000, `--oml-z-toast` 1100.

## Dark mode

Dark mode is a second set of the same `--oml-*` tokens, not a second stylesheet. Put `data-theme="dark"` on
an element and everything inside it flips; `data-theme="system"` follows the device
(`prefers-color-scheme`). The player shell already carries this attribute; `data-theme="light"` is the default.

- **Neutrals are fixed:** page `#252531`, card `#151521`, text `#f3f3f7`, muted `#b7b8c5`, borders `#4a4c53`
  (`DesignSystem::DARK`).
- **Brand and status colors adapt.** Each is lightened just enough to reach 4.5:1 against the dark card
  (`DesignSystem::for_dark()`), so a dark brand color such as navy still reads. Soft tints are mixed into the
  dark card, and text on the primary is re-chosen for contrast.
- **Components need no dark rules.** If a component uses tokens, it supports dark mode. A hardcoded color is
  what breaks dark mode.
- **Exception: assessment canvases stay light.** The quiz and paper stage keep a light canvas in every theme
  (their literal colors are documented in `player-context.css`), because quiz designs have their own color contract.
- **Learner pages only for now.** Admin screens follow the Design system settings and have no dark theme.

## Components

| Component | Classes | React | PHP |
| --- | --- | --- | --- |
| Button | `oml-btn` + `-primary` `-tertiary` `-danger`, `-sm` `-lg`, `-block` | `Button` | `Ui::button()` |
| Card | `oml-card` + `-flat` `-interactive`, `oml-card-title` | `Card`, `CardTitle` | `Ui::card()` |
| Badge | `oml-badge` + `-primary` `-success` `-warning` `-danger` `-info` | `Badge` | `Ui::badge()` |
| Progress | `oml-progress` + `-success`, inner `oml-progress-bar` | `Progress` | `Ui::progress()` |

Planned next: Input/Select, Alert, Tabs, Modal, Table, EmptyState.

## Adopting it

- New screens use the components from the start.
- Existing screens migrate one at a time. Replace hardcoded colors and one-off classes with tokens and
  components, delete the old rules, and check the screen against the gallery screenshots.
- The stylelint rule that rejects hardcoded colors currently covers `assets/css/oml-ui*.css` and
  `assets/src/ui`. Add each migrated stylesheet to it (`stylelint.config.mjs`, the shared-components
  override) so it cannot regress.

## Checking changes

`tests/browser/ui-gallery.spec.cjs` renders every component and variant in learner mode and in the admin
preset, and compares screenshots. Run it after any change to `oml-ui.css` or the tokens, and update the
baselines only when the visual change is intended.

## Migration status

| Area | State |
| --- | --- |
| Shared components (`oml-ui.css`) | Tokens only, lint-enforced |
| Learner `.ohmylms-button` | Restyled with tokens (`oml-legacy-buttons.css`) |
| Exam player chrome (`exam-player-template.css`) | Migrated: neutrals, status chips and button text use tokens; the accent stays the quiz accent setting. Covered by `tests/browser/exam-player-tokens.spec.cjs` and lint-enforced |
| Quiz player templates (`quiz-player-templates.css`) | Migrated: the shared question widgets (answer cards, matching, reorder, paper form, result card) use tokens. Template palettes (`--player-*`, `--playful-*`) and the print sheet keep literal colors behind commented stylelint disables. Covered by `tests/browser/quiz-templates-tokens.spec.cjs` |
| Question player, game player, answer review | Migrated: chrome uses tokens; the answer review palette and game artwork (board, wall, hole, image mask) stay literal behind commented disables. Covered by `tests/browser/player-widgets-tokens.spec.cjs` |
| Player context shell (`player-context.css`) | Migrated: the shell uses tokens and gets dark mode from them; the quiz and paper canvases stay light behind commented disables. Covered by `tests/browser/dark-theme.spec.cjs` |
| Student hub (`student-hub.css`) | Migrated: the shell palette is tokens and the hub gets dark mode from them; the accent follows the site brand color unless an admin picks one (Student Home settings, "Use the site brand color"); a school can still set its own. Selected and hero tints, the button edge and the text on the accent derive from the accent, in light and dark. The category and status palette (subject tones, state dots, card tints) and embedded legacy pages stay literal behind commented disables. Covered by `tests/browser/hub-tokens.spec.cjs` |
| Course and lesson pages, dashboard, practice, student hub, admin screens | Not migrated |
