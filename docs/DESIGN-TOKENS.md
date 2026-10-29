# Design tokens

Shared components read one vocabulary: the `--oml-*` variables. Admin and learner pages use the same
saved Design system palette, spacing, roundness and font. Settings > Design system is the palette editor.

| Context | Values come from | Emitted by | Selector |
| --- | --- | --- | --- |
| Plugin admin screens | Settings > Design system (`ohmylms_design_system`) | `DesignSystem::css()` | `body.oml-ds` |
| Learner pages | The same `ohmylms_design_system` settings | `Tokens::frontend_css()` | `:root` |

Both call `DesignSystem::variables( $colors, $button_radius, $density )`, so derived shades (`-hover`,
`-active`, `-soft`, `-rgb`, `on-*`), radii, button heights and the 4px spacing scale are computed
identically. The default is Khan on both sides. A saved Classic or custom preset applies to both sides,
including density and radius. `ohmylms_font_family` supplies the shared font; the historical `inherit`
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

`assets/css/oml-legacy-buttons.css` restyles the existing `.ohmylms-button` markup with the same tokens, so the
learner buttons match `oml-btn` without touching templates. `Ui::attach_legacy_buttons()` adds it as inline
CSS after `ohmylms-frontend`, which wins ties with the older rules in `style.css` (recovered, left as is).
The class name stays because Elementor/Bricks controls, quiz scripts and custom CSS target it. Delete
the older `.ohmylms-button` rules in `style.css` once nothing depends on them.

Behavior change from the older look: radius and height follow the Classic preset (4px, 36px), text is
14px/600, and hover darkens the fill instead of switching to an outline. `ohmylms-button-outline` is the
secondary look.

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
  names its keyboard-scrollable region. Use native header `scope` and `aria-sort` for sortable columns.
  PHP `Ui::table( $headers, $rows, $args )` escapes all text cells and accepts the same caption/modifiers.
- `EmptyState` accepts a title, description and optional action children. PHP `Ui::empty_state( $title,
  $args )` escapes the title/description and accepts caller-escaped `actions_html`. It does not announce
  automatically or move focus; a caller can place changing search results in a status region.

`assets/js/ui.js` enhances PHP tabs/dialogs and is queued only by those helpers. React owns its own
state and events. Route navigation retains real links and its existing drag/reorder behavior, using
the shared tab classes with `aria-current` rather than pretending to be local ARIA panels.

The authored course/lesson SCSS compiles to `learning-pages.css` after the immutable recovery baseline.
The build also regenerates the ordered admin UI modules; edit those sources rather than their hashed
outputs. Video controls retain a documented fixed media palette over arbitrary footage. Status text
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
