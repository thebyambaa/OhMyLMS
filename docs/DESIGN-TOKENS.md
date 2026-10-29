# Design tokens

Shared components read one vocabulary: the `--oml-*` variables. Admin and learner pages define the
same names, each from its own settings, so a component looks consistent without the two sides sharing
values.

| Context | Values come from | Emitted by | Selector |
| --- | --- | --- | --- |
| Plugin admin screens | Settings > Design system (`ohmylms_design_system`) | `DesignSystem::css()` | `body.oml-ds` |
| Learner pages | Learner branding options (primary, heading, body text, progress colors) | `Tokens::frontend_css()` | `:root` |

Both call `DesignSystem::variables( $colors, $button_radius, $density )`, so derived shades (`-hover`,
`-active`, `-soft`, `-rgb`, `on-*`), radii, button heights and the 4px spacing scale are computed
identically. On learner pages the status colors, neutrals and shape come from the Classic preset;
only primary, heading, text and success follow the learner branding.

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
