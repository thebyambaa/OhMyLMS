# Design tokens

Shared components read one vocabulary: the `--oml-*` variables. Admin and learner pages use the same
saved Design system palette, spacing, roundness and font. Settings > Design system is the palette editor.

| Context | Values come from | Emitted by | Selector |
| --- | --- | --- | --- |
| Plugin admin screens | The user's WordPress admin colour scheme under the default WordPress preset (`DesignSystem::admin_colors()`); otherwise Settings > Design system (`ohmylms_design_system`) | `DesignSystem::css()` | `body.oml-ds` |
| Learner pages | The saved `ohmylms_design_system` palette | `Tokens::frontend_css()` | `:root` |

Both call `DesignSystem::variables( $colors, $button_radius, $density )`, so derived shades (`-hover`,
`-active`, `-soft`, `-rgb`, `on-*`), radii, button heights and the 4px spacing scale are computed
identically. The default preset is WordPress: admin screens take `--oml-*` from the admin colour scheme
(theme colour on WordPress's neutrals, 2px radius) and leave `--wp-admin-theme-color` to WordPress; learner
pages keep the Khan Academy palette that preset stores. A saved Khan Academy, Classic or custom preset
applies to both sides, including density and radius. `ohmylms_font_family` supplies the shared font; the historical `inherit`
value now means the default system stack so the two contexts do not choose different fonts.

Compatibility: legacy color option names remain accepted by `Tokens::color()` as aliases of the
shared palette. The older `--ohmylms-*` names also remain aliases, reapplied in dark scopes. Their
independent saved color values no longer override the Design system. Quiz canvas colors, subject/artwork
palettes and explicitly customized school/hub accents retain their existing contracts.

The variable list is in `docs/DESIGN-SYSTEM.md`. The older `--ohmylms-*`, `--wp-*` and Arco names
still exist for current stylesheets; new code uses `--oml-*` only.

## Rules for shared components

- React components live in `assets/src/ui/` (`Button.jsx` + `button.mjs`); styles are one section each in
  `assets/css/oml-ui.css`; PHP markup uses `OhMyLMS\Design\Ui` (`Ui::button()`), which emits identical
  class names. Styles take every color from `var(--oml-*)`; stylelint rejects hex,
  named colors and `rgb()/hsl()` in that directory (`npm run lint:css`).
- Spacing, radius and heights also use the tokens (`--oml-space-*`, `--oml-radius-*`,
  `--oml-button-height-*`).
- Add a token to `DesignSystem::variables()` rather than hardcoding a value in a component, and add a
  check to `tests/php/design-system-unit.php`.

## Learner `.ohmylms-button`

The existing `.ohmylms-button` markup is an alias in the shared declaration blocks in
`assets/css/oml-ui.css`. There is no separate learner button stylesheet or override layer.
The public class remains because Elementor/Bricks controls, quiz scripts and custom CSS target it.
`ohmylms-button-outline` selects the secondary appearance. Radius, density, typography and states
come from the same shared tokens as `oml-btn`; page modules supply layout only.

`Ui::attach_legacy_buttons()` and `Ui::legacy_button_css()` remain compatibility entry points;
the former enqueues the shared stylesheet and the latter returns an empty string.

## Button

`oml-btn` plus `oml-btn-primary|tertiary|danger` (secondary is the default), `oml-btn-sm|lg` (md is the
default) and `oml-btn-block`.

```jsx
import { Button } from '../../ui/Button';
<Button variant="primary" onClick={ save }>Save</Button>
<Button href={ url } size="lg">Open</Button>
```

```php
echo \OhMyLMS\Design\Ui::button( __( 'Enroll', 'ohmylms' ), array( 'variant' => 'primary', 'href' => $url ) );
```

`Ui::button()` enqueues `oml-ui.css` on learner pages; plugin admin screens load it as a dependency of the
design-system stylesheet. Migrate old buttons feature by feature and delete their own `*-button` classes.

The full guide to using these tokens and components, with the planned component list, is `docs/DESIGN-LANGUAGE.md`.
The visual gallery and its screenshot tests are `tests/browser/ui-gallery.spec.cjs`.

## Tabs, Modal, Table and EmptyState

- `Tabs` accepts `items` (`id`, `label`, `content`, optional `disabled`), an accessible `label`, optional
  controlled `value` / `onChange`, `defaultValue`, and horizontal/vertical `orientation`. It supports
  Home/End and wrapping arrow keys, skips disabled items, supports RTL, and keeps panel state mounted.
  PHP `Ui::tabs( $items, $args )` uses `content_html` for caller-escaped panel markup and `value` for
  initial selection. Its content remains readable before JavaScript enhances it.
- `Modal` uses a native dialog, a visible `title`, controlled `open` (default true), and `onRequestClose`.
  The browser makes the rest of the page inert; Tab/Shift+Tab stay inside, Escape requests closure, and
  focus returns to the opener. PHP `Ui::modal( $content_html, array( 'id' => 'settings', 'title' => 'Settings' ) )`
  starts closed; open it from a button with `data-oml-dialog-open="settings"`. Content HTML must already
  be escaped. React dialogs remain in their original theme scope; no portal loses inherited tokens.
- `Table` accepts a visible `caption`, native table children, `striped` and `compact`; the caption also
  names its keyboard-scrollable region and the table carries WordPress's `widefat` (and `striped`) classes.
  Use it for detail and matrix tables; lists use `ListTable`. Use native header `scope` and `aria-sort` for
  sortable columns.
- `ListTable` (`assets/src/ui/ListTable.jsx`, typed in `ListTable.d.ts`) prints `WP_List_Table` markup over
  REST data, so wp-admin's own `list-tables.css` styles it: `.subsubsub` views (`views`, with counts where
  known), `p.search-box` (`search`), `.tablenav.top/.bottom` with the bulk-actions select and Apply
  (`bulkActions`), filters and a Filter button (`filters`, `onFilter`; `filterLabel={ null }` when filters
  apply on change), `table.wp-list-table.widefat.fixed.striped.table-view-list` with `thead`/`tfoot`,
  sortable headers (`.sortable`/`.sorted asc|desc`, the sorting indicators, `aria-sort` and "Sort
  ascending." link text, a "Table ordered by …" description), the check column (`isSelectable`, controlled
  `selected`), `.row-actions` on the primary column, the `.toggle-row` responsive button (with
  `aria-expanded`), `.no-items` and `.tablenav-pages` (first/previous/current-of-total/next/last and
  `.displaying-num`). Row actions are buttons, links or a ready `render` element (a router `Link`); they
  are visually hidden until the row is hovered or a control in the primary cell has focus (`.visible`),
  and stay in the tab order, as in WordPress. Search and Filter submit one form, like `posts-filter`.
  `ListTableSelect` / `ListTableInput` are the filter controls (native control, screen-reader label).

## No raw colours in the app

`npm run check:architecture` fails on hex, `rgb()`/`rgba()` and `hsl()`/`hsla()` literals in
`assets/src/app/**/*.css` and in the strings of `assets/src/app/**/*.tsx` (comments, tests, `href`/`to`
fragments and `.ts` model files are not checked). Use WordPress classes, `@wordpress/components`,
`var(--wp-admin-theme-color)` or the `--oml-*` tokens. Justified exceptions go in the `rawColours`
section of `tools/architecture-allowlist.json` as `{ "file", "value", "reason" }`; today the only entry is
the example colour in the Branding tab's help text.
  PHP `Ui::table( $headers, $rows, $args )` escapes all text cells and accepts the same caption/modifiers.
- `EmptyState` accepts a title, description and optional action children. PHP `Ui::empty_state( $title,
  $args )` escapes the title/description and accepts caller-escaped `actions_html`. It does not announce
  automatically or move focus; a caller can place changing search results in a status region.

`assets/js/ui.js` enhances PHP tabs/dialogs and is queued only by those helpers. React owns its own
state and events. Route navigation retains real links and its existing drag/reorder behavior, using
the shared tab classes with `aria-current` rather than pretending to be local ARIA panels.

Authored frontend SCSS compiles to nine page modules through `tools/build-frontend-styles.mjs`.
`FrontendStyles` registers shared components and the groups required by each request;
`ohmylms_frontend_style_groups` lets integrations request additional groups explicitly.
The generated `style.css` and `style-rtl.css` URLs remain compatibility outputs and are not loaded
alongside those modules. They are produced from the same SCSS; they are not a second colour source.
Edit `assets/src/frontend/scss` and regenerate with `tools/build-frontend-styles.mjs`. Do not hand-edit
`assets/css/style.css`, `assets/css/style-rtl.css`, or `assets/css/frontend/**`.

## Learner SCSS colours

`npm run lint:css` rejects new raw colours in `assets/src/frontend/scss`. `color-no-hex`, `color-named`
and the `rgb`/`rgba`/`hsl`/`hsla` ban apply, with one allowance: a previous hex may appear only as the
fallback of a design token, for example `color: var(--oml-primary, #1865f2)`. A page that does not emit
the token keeps that previous colour; a learner page resolves the token from the saved palette. Use
`--oml-primary`, `--oml-text`, `--oml-heading`, `--oml-muted`, `--oml-border`, `--oml-surface`,
`--oml-background`, `--oml-danger`, `--oml-success`, `--oml-warning`, `--oml-link` and `--oml-on-primary`.
Use an `--ohmylms-*` alias only in a file that already uses that family.

Single-colour icons use a white SVG mask (`--icon-mask`) plus `background-color: var(--oml-*, <previous>)`, so the
palette can restyle them. `#fff` / `#000` inside that mask are the silhouette, not a brand colour.
`ohmylms/no-raw-svg-colour` (`tools/stylelint-learner-colours.mjs`) rejects any other colour inside an
SVG data URI unless `tools/learner-colour-allowlist.json` lists that file and value. The list is for
values that cannot follow one token: star-rating artwork, select chevrons painted behind the control's
text, a notice glyph that shares its box with a surface disc, and two-paint lesson-type icons. This
SCSS has no chart series and no exam-print colours (`assets/css/paper.css` is separate). The video
player partial is outside the rule: its fixed media palette is documented on that file and owned with
the player. Generated `assets/css/style.css`, `assets/css/style-rtl.css` and `assets/css/frontend/**`
stay ignored. `assets/css/oml-ui.css` stays on the stricter rule, with no hex fallbacks.

The ordered admin feature modules are built from
`assets/src/design-system/admin`; edit authored sources rather than hashed outputs. Video controls retain a documented fixed media palette over arbitrary footage. Status text
has separate contrast-adjusted `--oml-{success,warning,danger,info}-text` tokens for soft backgrounds.

## Input and Select

`Input` (`assets/src/ui/Input.jsx`) and `Select` (`Select.jsx`) render native controls inside an
`oml-field` wrapper. PHP uses `Ui::input( $args )` and `Ui::select( $args )`; both enqueue `oml-ui.css`.
The pure helpers `inputClass()` / `selectClass()` in `controls.mjs` match PHP's `input_class()` /
`select_class()` and are covered by the component class-name parity test. When using a class helper
directly, keep the `oml-field` wrapper so styles outrank native WordPress form defaults.

```jsx
import { Input } from '../../ui/Input';
import { Select } from '../../ui/Select';

<Input label="Email" name="email" type="email" required
    help="Use your school email." error={ emailError }
    value={ email } onChange={ event => setEmail( event.target.value ) } />
<Select label="Subject" name="subject" placeholder="Choose a subject" required
    options={ [ { value: 'math', label: 'Mathematics' } ] }
    value={ subject } onChange={ event => setSubject( event.target.value ) } />
```

```php
echo \OhMyLMS\Design\Ui::input( array(
    'label' => __( 'Email', 'ohmylms' ), 'name' => 'email', 'type' => 'email',
    'required' => true, 'help' => __( 'Use your school email.', 'ohmylms' ),
    'error' => $email_error, 'value' => $email,
) );
```

- `size` is `sm`, `md` (default) or `lg`; unknown values use `md`. Input types are text, email, number,
  password, search, tel, url, date, datetime-local, month, time and week; unsupported types use text.
- `label`, `help` and `error` are text. A supplied error implies `invalid` and `aria-invalid="true"`.
  Native `required`, disabled, read-only, browser validation and form submission remain intact.
- IDs are optional: React uses `useId()`, PHP uses `wp_unique_id()`. Labels target the control ID;
  generated help/error IDs merge with the caller's `aria-describedby` without duplicates. Without a
  visible label, supply `aria-label` or `aria-labelledby`. The required marker is hidden from assistive
  technology because the native `required` attribute supplies that state.
- React forwards refs and native input/select props, including `value` / `defaultValue` and events.
  PHP accepts `attrs` for native attributes and `aria-*` / `data-*`; values are escaped and event
  handler attributes are rejected. Extra control classes use React `className` / PHP `class`; wrapper
  classes use `fieldClassName` / `field_class`. PHP's native read-only flag is `readonly`.
- Select options are `{value,label,disabled}` objects. PHP `value` selects the matching option;
  `multiple` accepts an array. React also accepts native option/optgroup children. A placeholder adds
  an empty option to a single select; multiple selects omit it. A locked select uses `disabled`, since
  HTML does not define a read-only select. Native list size can be set through PHP `attrs['size']`.
- Controls read `--oml-color-scheme` (`light` / `dark`) alongside the color tokens, so native picker
  chrome follows explicit themes even when the device preference differs. Focus uses `--oml-focus`;
  errors use `--oml-danger`; disabled/read-only controls use subtle/muted tokens. Reduced motion
  removes their transitions.

The gallery has learner/admin control sections; `tests/browser/ui-controls.spec.cjs` also exercises
the authored React components with WordPress's React runtime, native form data and light/dark/system.

## Alert

React `Alert` (`assets/src/ui/Alert.jsx`) and `Ui::alert( $message, $args )` share
`oml-alert`, a tone modifier (`oml-alert-info|success|warning|danger`), and optional
`oml-alert-title` followed by `oml-alert-content`. Unknown tones fall back to info.
`alertClass()` matches PHP's `alert_class()`.

```jsx
import { Alert } from '../../ui/Alert';
<Alert tone="success" title="Saved">Your changes are saved.</Alert>
```

```php
echo \OhMyLMS\Design\Ui::alert( __( 'Your changes are saved.', 'ohmylms' ), array(
    'tone' => 'success', 'title' => __( 'Saved', 'ohmylms' ),
) );
```

- The default role is `status` (polite); danger defaults to `alert` (assertive).
  Override with `role="status"`, `role="alert"` or `role="none"` for static information.
  Live-region roles announce changes, so mount the region before updating messages when reliable
  announcements are needed. Alerts do not move focus; the caller owns visibility and dismissal.
- PHP escapes the message and title as text. Its `attrs` allow `id`, `title`, `aria-*` and
  `data-*`; event handlers and inline styles are rejected. React accepts children (including links)
  and native div attributes. Extra classes use React `className` / PHP `class`.
- Status borders and soft backgrounds use the corresponding tokens; body/title text uses shared
  text/heading tokens for readable contrast. Links keep an underline and a token focus ring.
  Spacing, radius and type use tokens, logical borders support RTL, and long content wraps on phones.
  Light/dark/system require no component-specific theme rules.

Learner/admin gallery snapshots cover all tones, a title-free message and escaped PHP content.
`tests/browser/ui-alerts.spec.cjs` checks React roles, themes, focus and narrow layouts.

## Dark theme token output

`DesignSystem::dark_variables()` returns overrides for the same names, and `Tokens::frontend_dark_css()`
emits them for `[data-theme="dark"]` and, under `prefers-color-scheme: dark`, `[data-theme="system"]`.
See "Dark mode" in `docs/DESIGN-LANGUAGE.md`.
