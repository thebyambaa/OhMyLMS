<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Admin\Pages;

/**
 * Plugin admin settings implementation.
 */
class AdminSettings {

	/**
	 * Settings.
	 *
	 * @var mixed
	 */
	private static $settings = array();


	/**
	 * Init settings pages and tabs
	 *
	 * @return array|mixed|void
	 * @since 1.0.0
	 */
	public function init_settings_pages() {
		$settings = array();

		$settings[]     = new GeneralSettings();
		$settings[]     = new PaymentGatewaySettings();
		self::$settings = apply_filters( 'ohmylms_get_settings_pages', $settings );

		return self::$settings;
	}



	/**
	 * Output admin settings fields
	 *
	 * @param mixed $options Operation options.
	 */
	public static function output_fields( $options ) {
		foreach ( $options as $value ) {
			if ( ! isset( $value['type'] ) ) {
				continue;
			}

			if ( ! isset( $value['id'] ) ) {
				$value['id'] = '';
			}

			if ( ! isset( $value['field_name'] ) ) {
				$value['field_name'] = $value['id'];
			}
			if ( ! isset( $value['title'] ) ) {
				$value['title'] = isset( $value['name'] ) ? $value['name'] : '';
			}
			if ( ! isset( $value['class'] ) ) {
				$value['class'] = '';
			}
			if ( ! isset( $value['css'] ) ) {
				$value['css'] = '';
			}
			if ( ! isset( $value['default'] ) ) {
				$value['default'] = '';
			}
			if ( ! isset( $value['desc'] ) ) {
				$value['desc'] = '';
			}
			if ( ! isset( $value['desc_tip'] ) ) {
				$value['desc_tip'] = false;
			}
			if ( ! isset( $value['placeholder'] ) ) {
				$value['placeholder'] = '';
			}
			if ( ! isset( $value['row_class'] ) ) {
				$value['row_class'] = '';
			}
			if ( ! empty( $value['row_class'] ) && substr( $value['row_class'], 0, 16 ) !== 'wc-settings-row-' ) {
				$value['row_class'] = 'wc-settings-row-' . $value['row_class'];
			}
			if ( ! isset( $value['suffix'] ) ) {
				$value['suffix'] = '';
			}
			if ( ! isset( $value['value'] ) ) {
				$value['value'] = self::get_option( $value['id'], $value['default'] );
			}

			// Custom attribute handling.
			$custom_attributes = array();

			if ( ! empty( $value['custom_attributes'] ) && is_array( $value['custom_attributes'] ) ) {
				foreach ( $value['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
				}
			}

			// Description handling.
			$field_description = self::get_field_description( $value );
			$description       = $field_description['description'];
			$tooltip_html      = $field_description['tooltip_html'];

			// Switch based on type.
			switch ( $value['type'] ) {

				// Section Titles.
				case 'title':
					if ( ! empty( $value['title'] ) ) {
						echo '<h2>' . esc_html( $value['title'] ) . '</h2>';
					}
					if ( ! empty( $value['desc'] ) ) {
						echo '<div id="' . esc_attr( sanitize_title( $value['id'] ) ) . '-description">';
						echo wp_kses_post( wpautop( wptexturize( $value['desc'] ) ) );
						echo '</div>';
					}
					echo '<table class="form-table">' . "\n\n";
					if ( ! empty( $value['id'] ) ) {
						do_action( 'ohmylms_settings_' . sanitize_title( $value['id'] ) );
					}
					break;

				case 'info':
					?><tr class="<?php echo esc_attr( $value['row_class'] ); ?>">
						<th scope="row" class="titledesc"><?php echo esc_html( $value['title'] ); ?></th>
						<td style="<?php echo esc_attr( $value['css'] ); ?>">
					<?php
					echo wp_kses_post( wpautop( wptexturize( $value['text'] ) ) );
					echo '</td></tr>';
					break;

				// Section Ends.
				case 'sectionend':
					if ( ! empty( $value['id'] ) ) {
						do_action( 'ohmylms_settings_' . sanitize_title( $value['id'] ) . '_end' );
					}
					echo '</table>';
					if ( ! empty( $value['id'] ) ) {
						do_action( 'ohmylms_settings_' . sanitize_title( $value['id'] ) . '_after' );
					}
					break;

				// Standard text inputs and subtypes like 'number'.
				case 'text':
				case 'password':
				case 'datetime':
				case 'datetime-local':
				case 'date':
				case 'month':
				case 'time':
				case 'week':
				case 'number':
				case 'email':
				case 'url':
				case 'tel':
					$option_value = $value['value'];

					?>
					<tr class="<?php echo esc_attr( $value['row_class'] ); ?>">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); // WPCS: XSS ok. ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<input
								name="<?php echo esc_attr( $value['field_name'] ); ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								type="<?php echo esc_attr( $value['type'] ); ?>"
								style="<?php echo esc_attr( $value['css'] ); ?>"
								value="<?php echo esc_attr( $option_value ); ?>"
								class="<?php echo esc_attr( $value['class'] ); ?>"
								placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
								<?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
								/><?php echo esc_html( $value['suffix'] ); ?> <?php echo wp_kses_post( $description ); // WPCS: XSS ok. ?>
						</td>
					</tr>
					<?php
					break;

				// Textarea.
				case 'textarea':
					$option_value = $value['value'];

					?>
					<tr class="<?php echo esc_attr( $value['row_class'] ); ?>">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); // WPCS: XSS ok. ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<?php echo wp_kses_post( $description ); // WPCS: XSS ok. ?>

							<textarea
								name="<?php echo esc_attr( $value['field_name'] ); ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								style="<?php echo esc_attr( $value['css'] ); ?>"
								class="<?php echo esc_attr( $value['class'] ); ?>"
								placeholder="<?php echo esc_attr( $value['placeholder'] ); ?>"
								<?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
								><?php echo esc_textarea( $option_value ); // WPCS: XSS ok. ?></textarea>
						</td>
					</tr>
					<?php
					break;

				// Select boxes.
				case 'select':
				case 'multiselect':
					$option_value = $value['value'];

					?>
					<tr class="<?php echo esc_attr( $value['row_class'] ); ?>">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); // WPCS: XSS ok. ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<select
								name="<?php echo esc_attr( $value['field_name'] ); ?><?php echo ( 'multiselect' === $value['type'] ) ? '[]' : ''; ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								style="<?php echo esc_attr( $value['css'] ); ?>"
								class="<?php echo esc_attr( $value['class'] ); ?>"
								<?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
								<?php echo 'multiselect' === $value['type'] ? 'multiple="multiple"' : ''; ?>
								>
								<?php
								foreach ( $value['options'] as $key => $val ) {
									?>
									<option value="<?php echo esc_attr( $key ); ?>"
										<?php

										if ( is_array( $option_value ) ) {
											selected( in_array( (string) $key, $option_value, true ), true );
										} else {
											selected( $option_value, (string) $key );
										}

										?>
									><?php echo esc_html( $val ); ?></option>
									<?php
								}
								?>
							</select> <?php echo wp_kses_post( $description ); // WPCS: XSS ok. ?>
						</td>
					</tr>
					<?php
					break;

				// Radio inputs.
				case 'radio':
					$option_value     = $value['value'];
					$disabled_values  = $value['disabled'] ?? array();
					$show_desc_at_end = $value['desc_at_end'] ?? false;

					?>
					<tr class="<?php echo esc_attr( $value['row_class'] ); ?>">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); // WPCS: XSS ok. ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<fieldset>
								<?php
								if ( ! $show_desc_at_end ) {
									echo wp_kses_post( $description );
								}
								?>
								<ul>
								<?php
								foreach ( $value['options'] as $key => $val ) {
									?>
									<li>
										<label><input
											name="<?php echo esc_attr( $value['field_name'] ); ?>"
											value="<?php echo esc_attr( $key ); ?>"
											type="radio"
											<?php
											if ( in_array( $key, $disabled_values, true ) ) {
												echo 'disabled'; }
											?>
											style="<?php echo esc_attr( $value['css'] ); ?>"
											class="<?php echo esc_attr( $value['class'] ); ?>"
											<?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
											<?php checked( $key, $option_value ); ?>
											/> <?php echo esc_html( $val ); ?></label>
									</li>
									<?php
								}
								if ( $show_desc_at_end ) {
									echo wp_kses_post( "<p class='description description-thin'>{$description}</p>" );
								}
								?>
								</ul>
							</fieldset>
						</td>
					</tr>
					<?php
					break;

				// Checkbox input.
				case 'checkbox':
					$option_value     = $value['value'];
					$visibility_class = array();

					if ( ! isset( $value['hide_if_checked'] ) ) {
						$value['hide_if_checked'] = false;
					}
					if ( ! isset( $value['show_if_checked'] ) ) {
						$value['show_if_checked'] = false;
					}
					if ( 'yes' === $value['hide_if_checked'] || 'yes' === $value['show_if_checked'] ) {
						$visibility_class[] = 'hidden_option';
					}
					if ( 'option' === $value['hide_if_checked'] ) {
						$visibility_class[] = 'hide_options_if_checked';
					}
					if ( 'option' === $value['show_if_checked'] ) {
						$visibility_class[] = 'show_options_if_checked';
					}
					if ( $value['row_class'] ) {
						$visibility_class[] = $value['row_class'];
					}

					$container_class = implode( ' ', $visibility_class );
					$must_disable    = $value['disabled'] ?? false;

					if ( ! isset( $value['checkboxgroup'] ) || 'start' === $value['checkboxgroup'] ) {
						$has_tooltip             = isset( $value['tooltip'] ) && '' !== $value['tooltip'];
						$tooltip_container_class = $has_tooltip ? 'with-tooltip' : '';
						?>
							<tr class="<?php echo esc_attr( $container_class ); ?>">
								<th scope="row" class="titledesc"><?php echo esc_html( $value['title'] ); ?></th>
								<td class="forminp forminp-checkbox <?php echo esc_html( $tooltip_container_class ); ?>">

									<fieldset>
						<?php
					} else {
						?>
							<fieldset class="<?php echo esc_attr( $container_class ); ?>">
						<?php
					}

					if ( ! empty( $value['title'] ) ) {
						?>
							<legend class="screen-reader-text"><span><?php echo esc_html( $value['title'] ); ?></span></legend>
						<?php
					}

					?>
						<label for="<?php echo esc_attr( $value['id'] ); ?>">
							<input
								<?php echo $must_disable ? 'disabled' : ''; ?>
								name="<?php echo esc_attr( $value['field_name'] ); ?>"
								id="<?php echo esc_attr( $value['id'] ); ?>"
								type="checkbox"
								class="<?php echo esc_attr( isset( $value['class'] ) ? $value['class'] : '' ); ?>"
								value="1"
								<?php disabled( $value['disabled'] ?? false ); ?>
								<?php checked( $option_value, 'yes' ); ?>
								<?php echo implode( ' ', $custom_attributes ); // WPCS: XSS ok. ?>
							/> <?php echo wp_kses_post( $description ); // WPCS: XSS ok. ?>
						</label> <?php echo wp_kses_post( $tooltip_html ); // WPCS: XSS ok. ?>
					<?php

					if ( ! isset( $value['checkboxgroup'] ) || 'end' === $value['checkboxgroup'] ) {
						?>
									</fieldset>
								</td>
							</tr>
						<?php
					} else {
						?>
							</fieldset>
						<?php
					}
					break;

				case 'single_select_page':
					$args = array(
						'name'             => $value['field_name'],
						'id'               => $value['id'],
						'sort_column'      => 'menu_order',
						'sort_order'       => 'ASC',
						'show_option_none' => ' ',
						'class'            => $value['class'],
						'echo'             => false,
						'selected'         => absint( $value['value'] ),
						'post_status'      => 'publish,private,draft',
					);

					if ( isset( $value['args'] ) ) {
						$args = wp_parse_args( $value['args'], $args );
					}

					?>
						<tr class="single_select_page <?php echo esc_attr( $value['row_class'] ); ?>">
							<th scope="row" class="titledesc">
								<label><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); // WPCS: XSS ok. ?></label>
							</th>
							<td class="forminp">
							<?php echo str_replace( ' id=', " data-placeholder='" . esc_attr__( 'Select a page&hellip;', 'ohmylms' ) . "' style='" . esc_attr( $value['css'] ) . "' class='" . esc_attr( $value['class'] ) . "' id=", wp_dropdown_pages( $args ) ); // WPCS: XSS ok. ?> <?php echo wp_kses_post( $description ); // WPCS: XSS ok. ?>
							</td>
						</tr>
						<?php
					break;

				case 'single_select_page_with_search':
					$option_value = $value['value'];
					$page         = get_post( $option_value );

					if ( ! is_null( $page ) ) {
						$page                = get_post( $option_value );
						$option_display_name = sprintf(
							/* translators: 1: page name 2: page ID */
							__( '%1$s (ID: %2$s)', 'ohmylms' ),
							$page->post_title,
							$option_value
						);
					}
					?>
						<tr class="single_select_page <?php echo esc_attr( $value['row_class'] ); ?>">
							<th scope="row" class="titledesc">
								<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
							</th>
							<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
								<select
									name="<?php echo esc_attr( $value['field_name'] ); ?>"
									id="<?php echo esc_attr( $value['id'] ); ?>"
									style="<?php echo esc_attr( $value['css'] ); ?>"
									class="<?php echo esc_attr( $value['class'] ); ?>"
								<?php echo implode( ' ', $custom_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									data-placeholder="<?php esc_attr_e( 'Search for a page&hellip;', 'ohmylms' ); ?>"
									data-allow_clear="true"
									data-exclude="<?php echo esc_attr( wp_json_encode( $value['args']['exclude'] ) ); ?>"
									>
									<option value=""></option>
								<?php if ( ! is_null( $page ) ) { ?>
										<option value="<?php echo esc_attr( $option_value ); ?>" selected="selected">
										<?php echo esc_html( wp_strip_all_tags( $option_display_name ) ); ?>
										</option>
									<?php } ?>
								</select> <?php echo wp_kses_post( $description ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
						</tr>
						<?php
					break;

				case 'permalink':
					$default_structures = array();

					break;

				case 'delete_cache_button':
					?>
					<tr class="<?php echo esc_attr( $value['row_class'] ); ?>">
						<th scope="row" class="titledesc">
							<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?> <?php echo wp_kses_post( $tooltip_html ); // WPCS: XSS ok. ?></label>
						</th>
						<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
							<?php echo wp_kses_post( $description ); // WPCS: XSS ok. ?>
							<button type="button" class="button button-secondary ohmylms-delete-cache-btn" id="<?php echo esc_attr( $value['id'] ); ?>">
								<?php esc_html_e( 'Delete Cache', 'ohmylms' ); ?>
							</button>
							<span class="ohmylms-delete-cache-status" style="margin-left: 10px; display: none;"></span>
						</td>
					</tr>
					<?php
					break;

				// Default: run an action.
				default:
					do_action( 'ohmylms_admin_field_' . $value['type'], $value );
					break;
			}
		}
	}


	/**
	 * Get a setting from the settings API.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_option( $option_name, $default = '' ) {
		if ( ! $option_name ) {
			return $default;
		}

		// Array value.
		if ( strstr( $option_name, '[' ) ) {

			parse_str( $option_name, $option_array );

			// Option name is first key.
			$option_name = current( array_keys( $option_array ) );

			// Get value.
			$option_values = get_option( $option_name, '' );

			$key = key( $option_array[ $option_name ] );

			if ( isset( $option_values[ $key ] ) ) {
				$option_value = $option_values[ $key ];
			} else {
				$option_value = null;
			}
		} else {
			// Single value.
			$option_value = get_option( $option_name, null );
		}

		if ( is_array( $option_value ) ) {
			$option_value = wp_unslash( $option_value );
		} elseif ( ! is_null( $option_value ) ) {
			$option_value = stripslashes( $option_value );
		}

		return ( null === $option_value ) ? $default : $option_value;
	}


	/**
	 * Helper function to get the formatted description and tip HTML for a
	 * given form field. Plugins can call this when implementing their own custom
	 * settings types.
	 *
	 * @param array $value The form field value array.
	 * @return array The description and tip as a 2 element array.
	 */
	public static function get_field_description( $value ) {
		$description  = '';
		$tooltip_html = '';

		if ( true === $value['desc_tip'] ) {
			$tooltip_html = $value['desc'];
		} elseif ( ! empty( $value['desc_tip'] ) ) {
			$description  = $value['desc'];
			$tooltip_html = $value['desc_tip'];
		} elseif ( ! empty( $value['desc'] ) ) {
			$description = $value['desc'];
		}

		$description_is_error    = $value['description_is_error'] ?? false;
		$extra_description_class = $description_is_error ? ' ohmylms-settings-error' : '';

		if ( $description && in_array( $value['type'], array( 'textarea', 'radio' ), true ) ) {
			$description = '<p class="ohmylms-settings-description">' . wp_kses_post( $description ) . '</p>';
		} elseif ( $description && in_array( $value['type'], array( 'checkbox' ), true ) ) {
			$description = wp_kses_post( $description );
		} elseif ( $description ) {
			$description = '<p class="description' . esc_attr( $extra_description_class ) . '">' . wp_kses_post( $description ) . '</p>';
		}

		$tooltip_html = '<p class="description' . esc_attr( $extra_description_class ) . '">' . $tooltip_html . '</p>';

		return array(
			'description'  => $description,
			'tooltip_html' => $tooltip_html,
		);
	}


	/**
	 * Save the settings.
	 */
	public static function save() {
		global $ohmylms_current_tab;
		check_admin_referer( 'ohmylms-settings' );

		// Trigger actions.
		do_action( 'ohmylms_settings_save_' . $ohmylms_current_tab );
		do_action( 'ohmylms_update_options_' . $ohmylms_current_tab );
		do_action( 'ohmylms_update_options' );

		do_action( 'ohmylms_settings_saved' );
	}


	/**
	 * Save admin fields.
	 *
	 * Loops through the OhMyLMS options array and outputs each field.
	 *
	 * @param array $options Options array to output.
	 * @param array $data Optional. Data to use for saving. Defaults to $_POST.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function save_fields( $options, $data = null ) {
		if ( is_null( $data ) ) {
			$data = $_POST; // WPCS: input var okay, CSRF ok.
		}
		if ( empty( $data ) ) {
			return false;
		}

		// Options to update will be stored here and saved later.
		$update_options   = array();
		$autoload_options = array();

		// Loop options and get values to save.
		foreach ( $options as $option ) {
			if ( ! isset( $option['id'] ) || ! isset( $option['type'] ) || ( isset( $option['is_option'] ) && false === $option['is_option'] ) ) {
				continue;
			}

			$option_name = $option['field_name'] ?? $option['id'];

			// Get posted value.
			if ( strstr( $option_name, '[' ) ) {
				parse_str( $option_name, $option_name_array );
				$option_name  = current( array_keys( $option_name_array ) );
				$setting_name = key( $option_name_array[ $option_name ] );
				$raw_value    = isset( $data[ $option_name ][ $setting_name ] ) ? wp_unslash( $data[ $option_name ][ $setting_name ] ) : null;
			} else {
				$setting_name = '';
				$raw_value    = isset( $data[ $option_name ] ) ? wp_unslash( $data[ $option_name ] ) : null;
			}

			// Format the value based on option type.
			switch ( $option['type'] ) {
				case 'checkbox':
					$value = '1' === $raw_value || 'yes' === $raw_value ? 'yes' : 'no';
					break;
				case 'textarea':
					$value = wp_kses_post( trim( $raw_value ) );
					break;
				case 'multiselect':
				case 'multi_select_countries':
					$value = array_filter( array_map( 'ohmylms_clean', (array) $raw_value ) );
					break;
				case 'select':
					$allowed_values = empty( $option['options'] ) ? array() : array_map( 'strval', array_keys( $option['options'] ) );
					if ( empty( $option['default'] ) && empty( $allowed_values ) ) {
						$value = null;
						break;
					}
					$default = ( empty( $option['default'] ) ? $allowed_values[0] : $option['default'] );
					$value   = in_array( $raw_value, $allowed_values, true ) ? $raw_value : $default;
					break;
				default:
					$value = $raw_value;
					break;
			}

			/**
			 * Sanitize the value of an option.
			 *
			 * @since 1.0.0
			 */
			$value = apply_filters( 'ohmylms_admin_settings_sanitize_option', $value, $option, $raw_value );

			/**
			 * Sanitize the value of an option by option name.
			 *
			 * @since 1.0.0
			 */
			$value = apply_filters( "ohmylms_admin_settings_sanitize_option_$option_name", $value, $option, $raw_value );

			if ( is_null( $value ) ) {
				continue;
			}

			// Check if option is an array and handle that differently to single values.
			if ( $option_name && $setting_name ) {
				if ( ! isset( $update_options[ $option_name ] ) ) {
					$update_options[ $option_name ] = get_option( $option_name, array() );
				}
				if ( ! is_array( $update_options[ $option_name ] ) ) {
					$update_options[ $option_name ] = array();
				}
				$update_options[ $option_name ][ $setting_name ] = $value;
			} else {
				$update_options[ $option_name ] = $value;
			}

			$autoload_options[ $option_name ] = isset( $option['autoload'] ) ? (bool) $option['autoload'] : true;

			do_action( 'ohmylms_update_option', $option );
		}

		foreach ( $update_options as $name => $value ) {
			update_option( $name, $value, $autoload_options[ $name ] ? 'yes' : 'no' );
		}

		return true;
	}
}
