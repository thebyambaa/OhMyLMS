<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class SettingsApi.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Abstracts;

defined( 'ABSPATH' ) || exit;

/**
 * Class SettingsApi
 *
 * Here we have followed the WooCommerce architecture and use the WooCommerce Settings API class. This class is currently used only by the payment gateways.
 * In the future, we plan to restructure it so that all settings pages (SettingsPage) can extend this class.
 * This will help in limiting the duplication of code between SettingsApi and SettingsPage.
 *
 * @package OhMyLMS\Abstracts
 * @since 1.0.0
 */
abstract class SettingsApi {

	/**
	 * Plugin id
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $plugin_id = 'ohmylms_';

	/**
	 * ID of the class extending the settings API. Used in option names.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public string $id = '';


	/**
	 * Form option fields
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public array $form_fields = array();


	/**
	 * Setting values.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public array $settings = array();


	/**
	 * The posted settings data. When empty, $_POST data will be used.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	protected array $data = array();


	/**
	 * Validation errors.
	 *
	 * @var array of strings
	 * @since 1.0.0
	 */
	public array $errors = array();


	/**
	 * Initialise settings form fields.
	 *
	 * @since  1.0.0
	 */
	public function init_form_fields() {}


	/**
	 * Initialise Settings.
	 *
	 * @since 1.0.0
	 */
	public function init_settings() {
		$this->settings = get_option( $this->get_option_key(), array() );

		// If there are no settings defined, use defaults.
		if ( ! is_array( $this->settings ) ) {
			$_form_fields   = $this->get_form_fields();
			$this->settings = array_merge( array_fill_keys( array_keys( $_form_fields ), '' ), wp_list_pluck( $_form_fields, 'default' ) );
		}
	}


	/**
	 * Get option key
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_option_key() {
		return $this->plugin_id . $this->id . '_settings';
	}


	/**
	 * Output the admin options table.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>'; // WPCS: XSS ok.
	}


	/**
	 * Generate settings HTML
	 *
	 * @param array $form_fields Form fields.
	 * @param bool  $echo Echo.
	 * @return string
	 */
	public function generate_settings_html( $form_fields = array(), $echo = true ) {
		if ( empty( $form_fields ) ) {
			$form_fields = $this->get_form_fields();
		}

		$html = '';
		foreach ( $form_fields as $k => $v ) {
			$type = $this->get_field_type( $v );

			if ( method_exists( $this, 'generate_' . $type . '_html' ) ) {
				$html .= $this->{'generate_' . $type . '_html'}( $k, $v );
			} elseif ( has_filter( 'ohmylms_generate_' . $type . '_html' ) ) {
				$html .= apply_filters( 'ohmylms_generate_' . $type . '_html', '', $k, $v, $this );
			} else {
				$html .= $this->generate_text_html( $k, $v );
			}
		}

		if ( $echo ) {
			echo $html; // WPCS: XSS ok.
		} else {
			return $html;
		}
	}


	/**
	 * Get the form fields
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_form_fields() {
		return apply_filters( 'ohmylms_settings_api_form_fields_' . $this->id, array_map( array( $this, 'set_defaults' ), $this->form_fields ) );
	}


	/**
	 * Set default required properties for each field.
	 *
	 * @param array $field Setting field array.
	 * @return array
	 * @since 1.0.0
	 */
	protected function set_defaults( $field ) {
		if ( ! isset( $field['default'] ) ) {
			$field['default'] = '';
		}
		return $field;
	}


	/**
	 * Get a fields type. Defaults to "text" if not set.
	 *
	 * @param array $field Field key.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_field_type( $field ) {
		return empty( $field['type'] ) ? 'text' : $field['type'];
	}


	/**
	 * Get default value of the field
	 *
	 * @param mixed $field Field.
	 * @return mixed|string
	 * @since 1.0.0
	 */
	public function get_field_default( $field ) {
		return empty( $field['default'] ) ? '' : $field['default'];
	}

	/**
	 * Prefix key for settings.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	public function get_field_key( $key ) {
		return $this->plugin_id . $this->id . '_' . $key;
	}


	/**
	 * Get the validate value
	 *
	 * @param mixed $key Lookup key.
	 * @param mixed $field Field.
	 * @param array $post_data Post data.
	 * @return mixed|string
	 * @since 1.0.0
	 */
	public function get_field_value( $key, $field, $post_data = array() ) {
		$type      = $this->get_field_type( $field );
		$field_key = $this->get_field_key( $key );
		$post_data = empty( $post_data ) ? $_POST : $post_data; // WPCS: CSRF ok, input var ok.
		$value     = isset( $post_data[ $field_key ] ) ? $post_data[ $field_key ] : null;

		if ( isset( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
			return call_user_func( $field['sanitize_callback'], $value );
		}

		// Look for a validate_FIELDID_field method for special handling.
		if ( is_callable( array( $this, 'validate_' . $key . '_field' ) ) ) {
			return $this->{'validate_' . $key . '_field'}( $key, $value );
		}

		// Look for a validate_FIELDTYPE_field method.
		if ( is_callable( array( $this, 'validate_' . $type . '_field' ) ) ) {
			return $this->{'validate_' . $type . '_field'}( $key, $value );
		}

		// Fallback to text.
		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Sets the posted data
	 *
	 * @param array $data Input data.
	 * @since 1.0.0
	 */
	public function set_post_data( $data = array() ) {
		$this->data = $data;
	}

	/**
	 * Returns the $_POST data, to be used to save the settings.
	 *
	 * @return array
	 */
	public function get_post_data() {
		if ( ! empty( $this->data ) && is_array( $this->data ) ) {
			return $this->data;
		}
		return $_POST; // WPCS: CSRF ok, input var ok.
	}


	/**
	 * Update single option
	 *
	 * @param mixed  $key Lookup key.
	 * @param string $value Value to process.
	 * @return bool
	 * @since 1.0.0
	 */
	public function update_option( $key, $value = '' ) {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}
		$this->settings[ $key ] = $value;
		return update_option( $this->get_option_key(), apply_filters( 'ohmylms_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$this->init_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				try {
					$this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
				} catch ( \Exception $e ) {
					$this->add_error( $e->getMessage() );
				}
			}
		}

		$option_key = $this->get_option_key();
		do_action( 'ohmylms_update_option', array( 'id' => $option_key ) );
		return update_option( $option_key, apply_filters( 'ohmylms_settings_api_sanitized_fields_' . $this->id, $this->settings ), 'yes' );
	}


	/**
	 * Add error message for display
	 *
	 * @param mixed $error Error.
	 * @since 1.0.0
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
	}


	/**
	 * Get option from DB
	 *
	 * @param mixed $key Lookup key.
	 * @param null  $empty_value Empty value.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get_option( $key, $empty_value = null ) {
		if ( empty( $this->settings ) ) {
			$this->init_settings();
		}

		// Get option default if unset.
		if ( ! isset( $this->settings[ $key ] ) ) {
			$_form_fields           = $this->get_form_fields();
			$this->settings[ $key ] = isset( $_form_fields[ $key ] ) ? $this->get_field_default( $_form_fields[ $key ] ) : '';
		}

		if ( ! is_null( $empty_value ) && '' === $this->settings[ $key ] ) {
			$this->settings[ $key ] = $empty_value;
		}

		return $this->settings[ $key ];
	}


	/**
	 * Get HTML for tooltips.
	 *
	 * @param mixed $data Input data.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_tooltip_html( $data ) {
		if ( true === $data['desc_tip'] ) {
			$tip = $data['description'];
		} elseif ( ! empty( $data['desc_tip'] ) ) {
			$tip = $data['desc_tip'];
		} else {
			$tip = '';
		}

		return $tip ? ohmylms_help_tip( $tip ) : '';
	}


	/**
	 * Get HTML for description
	 *
	 * @param mixed $data Input data.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_description_html( $data ) {
		if ( true === $data['desc_tip'] ) {
			$description = '';
		} elseif ( ! empty( $data['desc_tip'] ) ) {
			$description = $data['description'];
		} elseif ( ! empty( $data['description'] ) ) {
			$description = $data['description'];
		} else {
			$description = '';
		}

		return $description ? '<p class="description">' . wp_kses_post( $description ) . '</p>' . "\n" : '';
	}


	/**
	 * Get custom attributes.
	 *
	 * @param mixed $data Input data.
	 * @return string
	 * @since 1.0.0
	 */
	public function get_custom_attribute_html( $data ) {
		$custom_attributes = array();

		if ( ! empty( $data['custom_attributes'] ) && is_array( $data['custom_attributes'] ) ) {
			foreach ( $data['custom_attributes'] as $attribute => $attribute_value ) {
				$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		return implode( ' ', $custom_attributes );
	}


	/**
	 * Generate Text Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_text_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generates HTML for the 'safe_text' input type (mostly used for gateway-related settings).
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @return string
	 *
	 * @since 7.6.0
	 */
	public function generate_safe_text_html( $key, $data ) {
		$data['type'] = 'text';
		return $this->generate_text_html( $key, $data );
	}

	/**
	 * Generate Price Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_price_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="ohmylms_input_price input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( ohmylms_format_localized_price( $this->get_option( $key ) ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Decimal Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_decimal_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="ohmylms_input_decimal input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( ohmylms_format_localized_price( $this->get_option( $key ) ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Password Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_password_html( $key, $data ) {
		$data['type'] = 'password';
		return $this->generate_text_html( $key, $data );
	}

	/**
	 * Generate Color Picker Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_color_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<span class="colorpickpreview" style="background:<?php echo esc_attr( $this->get_option( $key ) ); ?>;">&nbsp;</span>
					<input class="colorpick <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
					<div id="colorPickerDiv_<?php echo esc_attr( $field_key ); ?>" class="colorpickdiv ohmylms-color-picker-popup"></div>
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Textarea HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_textarea_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<textarea rows="3" cols="20" class="input-text wide-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>><?php echo esc_textarea( $this->get_option( $key ) ); ?></textarea>
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Checkbox HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_checkbox_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'label'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( ! $data['label'] ) {
			$data['label'] = $data['title'];
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<label for="<?php echo esc_attr( $field_key ); ?>">
						<input <?php disabled( $data['disabled'], true ); ?> class="<?php echo esc_attr( $data['class'] ); ?>" type="checkbox" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="1" <?php checked( $this->get_option( $key ), 'yes' ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> /> <?php echo wp_kses_post( $data['label'] ); ?></label><br/>
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Select HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_select_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = $this->get_option( $key );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<select class="select <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>>
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<?php if ( is_array( $option_value ) ) : ?>
								<optgroup label="<?php echo esc_attr( $option_key ); ?>">
									<?php foreach ( $option_value as $option_key_inner => $option_value_inner ) : ?>
										<option value="<?php echo esc_attr( $option_key_inner ); ?>" <?php selected( (string) $option_key_inner, esc_attr( $value ) ); ?>><?php echo esc_html( $option_value_inner ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php else : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, esc_attr( $value ) ); ?>><?php echo esc_html( $option_value ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Multiselect HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_multiselect_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'select_buttons'    => false,
			'options'           => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = (array) $this->get_option( $key, array() );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<select multiple="multiple" class="multiselect <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>[]" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>>
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<?php if ( is_array( $option_value ) ) : ?>
								<optgroup label="<?php echo esc_attr( $option_key ); ?>">
									<?php foreach ( $option_value as $option_key_inner => $option_value_inner ) : ?>
										<option value="<?php echo esc_attr( $option_key_inner ); ?>" <?php selected( in_array( (string) $option_key_inner, $value, true ), true ); ?>><?php echo esc_html( $option_value_inner ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php else : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( in_array( (string) $option_key, $value, true ), true ); ?>><?php echo esc_html( $option_value ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
					<?php if ( $data['select_buttons'] ) : ?>
						<br/><a class="select_all button" href="#"><?php esc_html_e( 'Select all', 'ohmylms' ); ?></a> <a class="select_none button" href="#"><?php esc_html_e( 'Select none', 'ohmylms' ); ?></a>
					<?php endif; ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generate Title HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_title_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title' => '',
			'class' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		</table>
		<h3 class="wc-settings-sub-title <?php echo esc_attr( $data['class'] ); ?>" id="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></h3>
		<?php if ( ! empty( $data['description'] ) ) : ?>
			<p><?php echo wp_kses_post( $data['description'] ); ?></p>
		<?php endif; ?>
		<table class="form-table">
		<?php

		return ob_get_clean();
	}

	/**
	 * Validate Text Field.
	 *
	 * Make sure the data is escaped correctly, etc.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string
	 */
	public function validate_text_field( $key, $value ) {
		$value = is_null( $value ) ? '' : $value;
		return wp_kses_post( trim( stripslashes( $value ) ) );
	}

	/**
	 * Validate Price Field.
	 *
	 * Make sure the data is escaped correctly, etc.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string
	 */
	public function validate_price_field( $key, $value ) {
		$value = is_null( $value ) ? '' : $value;
		return ( '' === $value ) ? '' : ohmylms_format_decimal( trim( stripslashes( $value ) ) );
	}

	/**
	 * Validate Decimal Field.
	 *
	 * Make sure the data is escaped correctly, etc.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string
	 */
	public function validate_decimal_field( $key, $value ) {
		$value = is_null( $value ) ? '' : $value;
		return ( '' === $value ) ? '' : ohmylms_format_decimal( trim( stripslashes( $value ) ) );
	}

	/**
	 * Validate Password Field. No input sanitization is used to avoid corrupting passwords.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string
	 */
	public function validate_password_field( $key, $value ) {
		$value = is_null( $value ) ? '' : $value;
		return trim( stripslashes( $value ) );
	}

	/**
	 * Validate Textarea Field.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string
	 */
	public function validate_textarea_field( $key, $value ) {
		$value = is_null( $value ) ? '' : $value;
		return wp_kses(
			trim( stripslashes( $value ) ),
			array_merge(
				array(
					'iframe' => array(
						'src'   => true,
						'style' => true,
						'id'    => true,
						'class' => true,
					),
				),
				wp_kses_allowed_html( 'post' )
			)
		);
	}

	/**
	 * Validate Checkbox Field.
	 *
	 * If not set, return "no", otherwise return "yes".
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string
	 */
	public function validate_checkbox_field( $key, $value ) {
		return ! is_null( $value ) ? 'yes' : 'no';
	}


	/**
	 * Validate Select Field.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string
	 */
	public function validate_select_field( $key, $value ) {
		$value = is_null( $value ) ? '' : $value;
		return ohmylms_clean( stripslashes( $value ) );
	}


	/**
	 * Validate Multiselect Field.
	 *
	 * @param string $key Field key.
	 * @param string $value Posted Value.
	 * @return string|array
	 */
	public function validate_multiselect_field( $key, $value ) {
		return is_array( $value ) ? array_map( 'ohmylms_clean', array_map( 'stripslashes', $value ) ) : '';
	}
}
