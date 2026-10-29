<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared UI components for PHP-rendered markup.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Design;

defined( 'ABSPATH' ) || exit;

/**
 * Shared UI components.
 *
 * Each component has one stylesheet section in assets/css/oml-ui.css (tokens only), a React version in
 * assets/src/ui and a PHP helper here that emits identical class names, so server-rendered and React
 * screens look the same. See docs/DESIGN-TOKENS.md.
 */
final class Ui {
	const STYLE_HANDLE    = 'ohmylms-ui';
	const BUTTON_VARIANTS = array( 'primary', 'secondary', 'tertiary', 'danger' );
	const BUTTON_SIZES    = array( 'sm', 'md', 'lg' );
	const BADGE_TONES     = array( 'primary', 'success', 'warning', 'danger', 'info' );
	const ALERT_TONES     = array( 'info', 'success', 'warning', 'danger' );
	const CONTROL_SIZES   = array( 'sm', 'md', 'lg' );
	const INPUT_TYPES     = array( 'text', 'email', 'number', 'password', 'search', 'tel', 'url', 'date', 'datetime-local', 'month', 'time', 'week' );

	/** Register the stylesheet on admin and learner pages. */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'register' ), 5 );
	}

	/** Deprecated compatibility entry point; button aliases are part of the shared component stylesheet. */
	public static function attach_legacy_buttons() {
		self::enqueue();
	}

	/**
	 * Deprecated compatibility entry point. No separate legacy button rules are loaded.
	 *
	 * @return string
	 */
	public static function legacy_button_css() {
		return '';
	}

	/** Register the shared stylesheet; learner pages enqueue it through the helpers below. */
	public static function register() {
		$path = OHMYLMS_DIR . '/assets/css/oml-ui.css';
		wp_register_style( self::STYLE_HANDLE, plugins_url( 'assets/css/oml-ui.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION . '.' . substr( (string) hash_file( 'sha256', $path ), 0, 12 ) );
	}

	/** Enqueue the shared stylesheet, registering it first when a helper runs early. */
	public static function enqueue() {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			self::register();
		}
		wp_enqueue_style( self::STYLE_HANDLE );
	}

	/** Enqueue progressive enhancement for PHP tabs and native dialogs. */
	private static function enqueue_behavior() {
		wp_enqueue_script( 'ohmylms-ui', plugins_url( 'assets/js/ui.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION . '.' . substr( (string) hash_file( 'sha256', OHMYLMS_DIR . '/assets/js/ui.js' ), 0, 12 ), true );
	}

	/**
	 * Render local tabs. Panels remain readable without JavaScript.
	 *
	 * @param array $items Items containing id, label, content_html (already escaped) and disabled.
	 * @param array $args label, value, orientation, class.
	 * @return string HTML containing caller-escaped panel markup.
	 */
	public static function tabs( $items, $args = array() ) {
		self::enqueue();
		self::enqueue_behavior();
		$id       = wp_unique_id( 'oml-tabs-' );
		$vertical = 'vertical' === ( $args['orientation'] ?? '' );
		$enabled  = array_values( array_filter( $items, static fn( $item ) => empty( $item['disabled'] ) ) );
		$active   = $args['value'] ?? ( $enabled[0]['id'] ?? '' );
		if ( ! in_array( $active, array_column( $enabled, 'id' ), true ) ) {
			$active = $enabled[0]['id'] ?? '';
		}
		$html   = '<div class="' . esc_attr( trim( 'oml-tabs ' . ( $args['class'] ?? '' ) ) ) . '" data-oml-tabs><div class="oml-tab-list" role="tablist" aria-label="' . esc_attr( $args['label'] ?? '' ) . '" aria-orientation="' . ( $vertical ? 'vertical' : 'horizontal' ) . '">';
		$panels = '';
		foreach ( array_values( $items ) as $index => $item ) {
			$selected = $active === $item['id'];
			$tab_id   = $id . '-tab-' . $index;
			$panel_id = $id . '-panel-' . $index;
			$html    .= '<button type="button" role="tab" class="oml-tab" id="' . esc_attr( $tab_id ) . '" aria-controls="' . esc_attr( $panel_id ) . '" aria-selected="' . ( $selected ? 'true' : 'false' ) . '" tabindex="' . ( $selected ? '0' : '-1' ) . '"' . ( ! empty( $item['disabled'] ) ? ' disabled' : '' ) . '>' . esc_html( $item['label'] ?? '' ) . '</button>';
			$panels  .= '<div class="oml-tab-panel" role="tabpanel" id="' . esc_attr( $panel_id ) . '" aria-labelledby="' . esc_attr( $tab_id ) . '" tabindex="0">' . ( $item['content_html'] ?? '' ) . '</div>';
		}
		return $html . '</div>' . $panels . '</div>';
	}

	/**
	 * Render a closed native dialog. Open with a button bearing data-oml-dialog-open="ID".
	 *
	 * @param string $content_html Caller-escaped dialog markup.
	 * @param array  $args Required id and title; optional class and close_label.
	 * @return string HTML containing caller-escaped content.
	 */
	public static function modal( $content_html, $args ) {
		self::enqueue();
		self::enqueue_behavior();
		$id          = $args['id'];
		$close_label = $args['close_label'] ?? __( 'Close', 'ohmylms' );
		return '<dialog data-oml-dialog id="' . esc_attr( $id ) . '" class="' . esc_attr( trim( 'oml-modal ' . ( $args['class'] ?? '' ) ) ) . '" aria-labelledby="' . esc_attr( $id . '-title' ) . '"><header class="oml-modal-header"><h2 class="oml-modal-title" id="' . esc_attr( $id . '-title' ) . '">' . esc_html( $args['title'] ?? '' ) . '</h2>' . self::button(
			$close_label,
			array(
				'variant' => 'tertiary',
				'attrs'   => array(
					'data-oml-dialog-close' => '',
					'aria-label'            => $close_label,
				),
			)
		) . '</header><div class="oml-modal-content">' . $content_html . '</div></dialog>';
	}

	/**
	 * Render a native, scrollable table with escaped text cells.
	 *
	 * @param array $headers Column labels.
	 * @param array $rows Arrays of plain-text cell values.
	 * @param array $args Required caption; optional striped, compact, class.
	 * @return string Escaped table HTML.
	 */
	public static function table( $headers, $rows, $args ) {
		self::enqueue();
		$classes = 'oml-table' . ( ! empty( $args['striped'] ) ? ' oml-table-striped' : '' ) . ( ! empty( $args['compact'] ) ? ' oml-table-compact' : '' ) . ' ' . ( $args['class'] ?? '' );
		$html    = '<div class="oml-table-scroll" role="region" aria-label="' . esc_attr( $args['caption'] ) . '" tabindex="0"><table class="' . esc_attr( trim( $classes ) ) . '"><caption>' . esc_html( $args['caption'] ) . '</caption><thead><tr>';
		foreach ( $headers as $label ) {
			$html .= '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $headers as $index => $label ) {
				$html .= '<td>' . esc_html( $row[ $index ] ?? '' ) . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table></div>';
	}

	/**
	 * Render empty-content guidance and optional caller-escaped actions.
	 *
	 * @param string $title Plain-text title.
	 * @param array  $args description, actions_html (already escaped), class.
	 * @return string HTML containing caller-escaped action markup.
	 */
	public static function empty_state( $title, $args = array() ) {
		self::enqueue();
		$html = '<div class="' . esc_attr( trim( 'oml-empty-state ' . ( $args['class'] ?? '' ) ) ) . '"><p class="oml-empty-state-title">' . esc_html( $title ) . '</p>';
		if ( ! empty( $args['description'] ) ) {
			$html .= '<p class="oml-empty-state-description">' . esc_html( $args['description'] ) . '</p>';
		}
		if ( ! empty( $args['actions_html'] ) ) {
			$html .= '<div class="oml-empty-state-actions">' . $args['actions_html'] . '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * Class names for a button; the same strings as assets/src/ui/button.mjs.
	 *
	 * @param array $args variant, size, block, class.
	 * @return string
	 */
	public static function button_class( $args = array() ) {
		$variant = $args['variant'] ?? 'secondary';
		$size    = $args['size'] ?? 'md';
		$classes = array( 'oml-btn' );
		if ( 'secondary' !== $variant && in_array( $variant, self::BUTTON_VARIANTS, true ) ) {
			$classes[] = 'oml-btn-' . $variant;
		}
		if ( 'md' !== $size && in_array( $size, self::BUTTON_SIZES, true ) ) {
			$classes[] = 'oml-btn-' . $size;
		}
		if ( ! empty( $args['block'] ) ) {
			$classes[] = 'oml-btn-block';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * Render a button, or a link when href is given.
	 *
	 * @param string $label Button text (escaped here).
	 * @param array  $args  variant, size, block, class, href, type, disabled, attrs (name => value).
	 * @return string Escaped HTML.
	 */
	public static function button( $label, $args = array() ) {
		self::enqueue();
		$attrs    = array( 'class' => self::button_class( $args ) );
		$disabled = ! empty( $args['disabled'] );
		if ( ! empty( $args['href'] ) ) {
			$tag = 'a';
			if ( $disabled ) {
				$attrs['aria-disabled'] = 'true';
			} else {
				$attrs['href'] = $args['href'];
			}
		} else {
			$tag           = 'button';
			$attrs['type'] = $args['type'] ?? 'button';
			if ( $disabled ) {
				$attrs['disabled'] = 'disabled';
			}
		}
		foreach ( (array) ( $args['attrs'] ?? array() ) as $name => $value ) {
			$attrs[ sanitize_key( $name ) ] = $value;
		}
		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . ( 'href' === $name ? esc_url( $value ) : esc_attr( $value ) ) . '"';
		}
		return '<' . $tag . $html . '>' . esc_html( $label ) . '</' . $tag . '>';
	}

	/**
	 * Class names for a card; the same strings as assets/src/ui/components.mjs.
	 *
	 * @param array $args flat, interactive, class.
	 * @return string
	 */
	public static function card_class( $args = array() ) {
		$classes = array( 'oml-card' );
		if ( ! empty( $args['flat'] ) ) {
			$classes[] = 'oml-card-flat';
		}
		if ( ! empty( $args['interactive'] ) ) {
			$classes[] = 'oml-card-interactive';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * Class names for a badge.
	 *
	 * @param array $args tone, class.
	 * @return string
	 */
	public static function badge_class( $args = array() ) {
		$tone    = $args['tone'] ?? 'neutral';
		$classes = array( 'oml-badge' );
		if ( in_array( $tone, self::BADGE_TONES, true ) ) {
			$classes[] = 'oml-badge-' . $tone;
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * Alert classes; matches assets/src/ui/components.mjs.
	 *
	 * @param array $args tone, class.
	 * @return string
	 */
	public static function alert_class( $args = array() ) {
		$tone = $args['tone'] ?? 'info';
		$tone = in_array( $tone, self::ALERT_TONES, true ) ? $tone : 'info';
		return trim( 'oml-alert oml-alert-' . $tone . ' ' . ( $args['class'] ?? '' ) );
	}

	/**
	 * Render feedback with an optional plain-text title and message.
	 *
	 * @param string $message Plain text, escaped here.
	 * @param array  $args    tone, title, class, role (status, alert or none), attrs (id, title, aria-/data-*).
	 * @return string Escaped HTML. Danger announces assertively; other tones announce politely.
	 */
	public static function alert( $message, $args = array() ) {
		self::enqueue();
		$role       = $args['role'] ?? '';
		$role       = in_array( $role, array( 'alert', 'status', 'none' ), true ) ? $role : ( 'danger' === ( $args['tone'] ?? 'info' ) ? 'alert' : 'status' );
		$attributes = array_intersect_key(
			(array) ( $args['attrs'] ?? array() ),
			array(
				'id'    => true,
				'title' => true,
			)
		);
		foreach ( (array) ( $args['attrs'] ?? array() ) as $name => $value ) {
			if ( preg_match( '/^(aria|data)-[a-z0-9_-]+$/', $name ) ) {
				$attributes[ $name ] = $value;
			}
		}
		$html = '<div class="' . esc_attr( self::alert_class( $args ) ) . '" role="' . esc_attr( $role ) . '"' . self::control_attributes( $attributes ) . '>';
		if ( isset( $args['title'] ) && '' !== (string) $args['title'] ) {
			$html .= '<strong class="oml-alert-title">' . esc_html( $args['title'] ) . '</strong>';
		}
		return $html . '<div class="oml-alert-content">' . esc_html( $message ) . '</div></div>';
	}

	/**
	 * Class names for a progress bar.
	 *
	 * @param array $args tone, class.
	 * @return string
	 */
	public static function progress_class( $args = array() ) {
		$classes = array( 'oml-progress' );
		if ( 'success' === ( $args['tone'] ?? 'primary' ) ) {
			$classes[] = 'oml-progress-success';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * A progress value as a whole number from 0 to 100.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function clamp_percent( $value ) {
		return is_numeric( $value ) ? (int) min( 100, max( 0, round( (float) $value ) ) ) : 0;
	}

	/**
	 * Render a card around markup the caller has already escaped.
	 *
	 * @param string $content_html Escaped inner HTML.
	 * @param array  $args         flat, interactive, class, tag.
	 * @return string
	 */
	public static function card( $content_html, $args = array() ) {
		self::enqueue();
		$tag = $args['tag'] ?? 'div';
		$tag = in_array( $tag, array( 'div', 'section', 'article', 'li', 'aside' ), true ) ? $tag : 'div';
		return '<' . $tag . ' class="' . esc_attr( self::card_class( $args ) ) . '">' . $content_html . '</' . $tag . '>';
	}

	/**
	 * Render a badge.
	 *
	 * @param string $label Text (escaped here).
	 * @param array  $args  tone, class.
	 * @return string
	 */
	public static function badge( $label, $args = array() ) {
		self::enqueue();
		return '<span class="' . esc_attr( self::badge_class( $args ) ) . '">' . esc_html( $label ) . '</span>';
	}

	/**
	 * Render a progress bar.
	 *
	 * @param mixed  $value 0 to 100.
	 * @param string $label Accessible name (escaped here).
	 * @param array  $args  tone, class.
	 * @return string
	 */
	public static function progress( $value, $label, $args = array() ) {
		self::enqueue();
		$percent = self::clamp_percent( $value );
		return '<div class="' . esc_attr( self::progress_class( $args ) ) . '" role="progressbar" aria-label="' . esc_attr( $label ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $percent . '"><span class="oml-progress-bar" style="width:' . $percent . '%"></span></div>';
	}

	/**
	 * Input classes; matches assets/src/ui/controls.mjs.
	 *
	 * @param array $args size, invalid, error, class.
	 * @return string
	 */
	public static function input_class( $args = array() ) {
		return self::control_class( 'input', $args );
	}

	/**
	 * Select classes; matches assets/src/ui/controls.mjs.
	 *
	 * @param array $args size, invalid, error, class.
	 * @return string
	 */
	public static function select_class( $args = array() ) {
		return self::control_class( 'select', $args );
	}

	/**
	 * Shared control class names, keeping unknown sizes at the default.
	 *
	 * @param string $kind input or select.
	 * @param array  $args Presentation arguments.
	 * @return string
	 */
	private static function control_class( $kind, $args ) {
		$classes = array( 'oml-control', 'oml-' . $kind );
		$size    = $args['size'] ?? 'md';
		if ( 'md' !== $size && in_array( $size, self::CONTROL_SIZES, true ) ) {
			$classes[] = 'oml-control-' . $size;
		}
		if ( ! empty( $args['invalid'] ) || ( isset( $args['error'] ) && '' !== (string) $args['error'] ) ) {
			$classes[] = 'oml-control-invalid';
		}
		if ( ! empty( $args['class'] ) ) {
			$classes[] = $args['class'];
		}
		return implode( ' ', $classes );
	}

	/**
	 * Render a native text-like input, including its label and descriptions.
	 *
	 * @param array $args id, label, help, error, size, invalid, type, name, value, placeholder,
	 *                    required, disabled, readonly, class, field_class, attrs.
	 * @return string Escaped HTML. Without a visible label, provide attrs aria-label/aria-labelledby.
	 */
	public static function input( $args = array() ) {
		return self::control( 'input', $args );
	}

	/**
	 * Render a native select, including its label and descriptions.
	 *
	 * @param array $args Same field arguments as input(), plus options (value, label, disabled),
	 *                    multiple and value (a scalar, or an array for a multiple select).
	 * @return string Escaped HTML.
	 */
	public static function select( $args = array() ) {
		return self::control( 'select', $args );
	}

	/**
	 * Render only known native attributes and aria-/data- extensions, never event handlers.
	 *
	 * @param array $attributes Native control attributes.
	 * @return string Escaped attribute markup.
	 */
	private static function control_attributes( $attributes ) {
		$allowed = array( 'id', 'class', 'type', 'name', 'value', 'placeholder', 'disabled', 'readonly', 'required', 'multiple', 'autofocus', 'autocomplete', 'min', 'max', 'step', 'minlength', 'maxlength', 'pattern', 'inputmode', 'form', 'title', 'spellcheck', 'size' );
		$boolean = array( 'disabled', 'readonly', 'required', 'multiple', 'autofocus' );
		$html    = '';
		foreach ( $attributes as $name => $value ) {
			if ( ! in_array( $name, $allowed, true ) && ! preg_match( '/^(aria|data)-[a-z0-9_-]+$/', $name ) ) {
				continue;
			}
			if ( in_array( $name, $boolean, true ) ) {
				if ( $value ) {
					$html .= ' ' . $name;
				}
				continue;
			}
			if ( null !== $value && is_scalar( $value ) ) {
				$html .= ' ' . $name . '="' . esc_attr( is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value ) . '"';
			}
		}
		return $html;
	}

	/**
	 * Build a control and one associated field wrapper. Descriptions are plain text.
	 *
	 * @param string $kind input or select.
	 * @param array  $args Field and native control arguments.
	 * @return string Escaped field markup.
	 */
	private static function control( $kind, $args ) {
		self::enqueue();
		$attributes = (array) ( $args['attrs'] ?? array() );
		foreach ( array( 'id', 'name', 'placeholder', 'disabled', 'readonly', 'required', 'multiple', 'autofocus', 'autocomplete', 'min', 'max', 'step', 'minlength', 'maxlength', 'pattern', 'inputmode' ) as $name ) {
			if ( array_key_exists( $name, $args ) ) {
				$attributes[ $name ] = $args[ $name ];
			}
		}
		$id                  = ! empty( $attributes['id'] ) ? (string) $attributes['id'] : wp_unique_id( 'oml-' . $kind . '-' );
		$help                = (string) ( $args['help'] ?? '' );
		$error               = (string) ( $args['error'] ?? '' );
		$args['invalid']     = ! empty( $args['invalid'] ) || '' !== $error || in_array( $attributes['aria-invalid'] ?? false, array( true, 'true' ), true );
		$attributes['id']    = $id;
		$attributes['class'] = self::control_class( $kind, $args );
		if ( $args['invalid'] ) {
			$attributes['aria-invalid'] = 'true';
		}
		$descriptions = preg_split( '/\s+/', trim( (string) ( $attributes['aria-describedby'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
		if ( '' !== $help ) {
			$descriptions[] = $id . '-help';
		}
		if ( '' !== $error ) {
			$descriptions[] = $id . '-error';
		}
		if ( $descriptions ) {
			$attributes['aria-describedby'] = implode( ' ', array_unique( $descriptions ) );
		}
		if ( 'input' === $kind ) {
			$attributes['type']  = in_array( $args['type'] ?? 'text', self::INPUT_TYPES, true ) ? ( $args['type'] ?? 'text' ) : 'text';
			$attributes['value'] = $args['value'] ?? '';
			$control             = '<input' . self::control_attributes( $attributes ) . '>';
		} else {
			unset( $attributes['placeholder'], $attributes['value'], $attributes['type'], $attributes['readonly'] );
			$options = (array) ( $args['options'] ?? array() );
			if ( isset( $args['placeholder'] ) && '' !== (string) $args['placeholder'] && empty( $attributes['multiple'] ) ) {
				array_unshift(
					$options,
					array(
						'value' => '',
						'label' => $args['placeholder'],
					)
				);
			}
			$selected = array_map( 'strval', (array) ( $args['value'] ?? array() ) );
			$control  = '<select' . self::control_attributes( $attributes ) . '>';
			foreach ( $options as $option ) {
				$value    = (string) ( $option['value'] ?? '' );
				$control .= '<option value="' . esc_attr( $value ) . '"' . ( in_array( $value, $selected, true ) ? ' selected' : '' ) . ( ! empty( $option['disabled'] ) ? ' disabled' : '' ) . '>' . esc_html( $option['label'] ?? '' ) . '</option>';
			}
			$control .= '</select>';
		}
		$html = '<div class="' . esc_attr( trim( 'oml-field ' . ( $args['field_class'] ?? '' ) ) ) . '">';
		if ( isset( $args['label'] ) && '' !== (string) $args['label'] ) {
			$html .= '<label class="oml-field-label" for="' . esc_attr( $id ) . '">' . esc_html( $args['label'] ) . ( ! empty( $attributes['required'] ) ? '<span class="oml-field-required" aria-hidden="true"> *</span>' : '' ) . '</label>';
		}
		$html .= $control;
		if ( '' !== $help ) {
			$html .= '<p class="oml-field-help" id="' . esc_attr( $id . '-help' ) . '">' . esc_html( $help ) . '</p>';
		}
		if ( '' !== $error ) {
			$html .= '<p class="oml-field-error" id="' . esc_attr( $id . '-error' ) . '" role="alert">' . esc_html( $error ) . '</p>';
		}
		return $html . '</div>';
	}
}
