<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Checkout Shortcode.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Shortcodes;

use OhMyLMS\Data\Student;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout Shortcode
 *
 * Class ShortCodeCheckout
 *
 * @package OhMyLMS\Shortcodes
 * @since 1.0.0
 */
class ShortCodeCheckout {

	/**
	 * Render the checkout form
	 *
	 * @param array $atts Shortcode attributes for styling options.
	 * @since 1.0.0
	 */
	public static function output( $atts = array() ) {
		global $wp;

		// Check cart class is loaded or abort.
		if ( is_null( ecommerce()->cart ) ) {
			return;
		}

		// Parse shortcode attributes.
		$attributes = shortcode_atts(
			array(
				// Title styling.
				'title_color'                    => 'var(--ohmylms-heading-color)',
				'title_font_size'                => '22px',
				'title_font_weight'              => '600',
				'title_font_family'              => '',
				'title_text_transform'           => 'none',
				'title_text_decoration'          => 'none',
				'title_line_height'              => '1.3',
				'title_letter_spacing'           => '0',

				// Input styling.
				'input_label_color'              => 'var(--ohmylms-heading-color)',
				'input_label_font_size'          => '14px',
				'input_label_font_weight'        => '500',
				'input_label_font_family'        => '',
				'input_font_size'                => '14px',
				'input_font_weight'              => '400',
				'input_color'                    => 'var(--ohmylms-heading-color)',
				'input_font_family'              => '',
				'input_background_color'         => '#FFF',
				'input_border_color'             => '#EBEBEF',
				'input_border_width'             => '1px',
				'input_border_style'             => 'solid',
				'input_border_radius'            => '10px',

				// Checkout Button styling.
				'button_background_color'        => '#6e42d3',
				'button_color'                   => '#FFF',
				'button_font_size'               => '18px',
				'button_font_weight'             => '700',
				'button_font_family'             => '',
				'button_text_transform'          => 'none',
				'button_text_decoration'         => 'none',
				'button_line_height'             => '1.2',
				'button_letter_spacing'          => '0',
				'button_padding_top'             => '16px',
				'button_padding_right'           => '24px',
				'button_padding_bottom'          => '16px',
				'button_padding_left'            => '24px',
				'button_margin_top'              => '0',
				'button_margin_right'            => '0',
				'button_margin_bottom'           => '0',
				'button_margin_left'             => '0',
				'button_border_radius'           => '8px',
				'button_border_color'            => '#6e42d3',
				'button_border_width'            => '1px',
				'button_border_style'            => 'solid',
				'button_box_shadow'              => 'none',

				// Button hover states.
				'button_hover_background_color'  => 'transparent',
				'button_hover_color'             => '#6e42d3',
				'button_hover_border_color'      => '#6e42d3',
				'button_hover_box_shadow'        => 'none',

				// Privacy Text styling.
				'privacy_text_color'             => 'var(--ohmylms-heading-color)',
				'privacy_text_font_size'         => '14px',
				'privacy_text_font_weight'       => '400',
				'privacy_text_font_family'       => '',
				'privacy_text_transform'         => 'none',
				'privacy_text_decoration'        => 'none',
				'privacy_text_line_height'       => '1.3',
				'privacy_text_letter_spacing'    => '0',

				// Checkout Box styling.
				'checkout_box_background_color'  => '',
				'checkout_box_padding_top'       => '30px',
				'checkout_box_padding_right'     => '50px',
				'checkout_box_padding_bottom'    => '30px',
				'checkout_box_padding_left'      => '0',
				'checkout_box_margin_top'        => '0',
				'checkout_box_margin_right'      => '0',
				'checkout_box_margin_bottom'     => '0',
				'checkout_box_margin_left'       => '0',
				'checkout_box_border_color'      => 'transparent',
				'checkout_box_border_width'      => '0',
				'checkout_box_border_style'      => 'solid',
				'checkout_box_border_radius'     => '0',

				// Order Summary styling.
				'order_summary_background_color' => '',
				'order_summary_padding_top'      => '30px',
				'order_summary_padding_right'    => '0',
				'order_summary_padding_bottom'   => '30px',
				'order_summary_padding_left'     => '30px',
				'order_summary_margin_top'       => '0',
				'order_summary_margin_right'     => '0',
				'order_summary_margin_bottom'    => '0',
				'order_summary_margin_left'      => '0',
				'order_summary_border_color'     => '',
				'order_summary_border_width'     => '',
				'order_summary_border_style'     => '',
				'order_summary_border_radius'    => '',

				// Layout type.
				'layout_type'                    => '',
			),
			$atts
		);

		// Store attributes globally for template access.
		global $ohmylms_checkout_attributes;
		$ohmylms_checkout_attributes = $attributes;

		// Note: layout_type is now parsed directly from post content in CommonHook::template_include_callback
		// No need to set global variable here.

		if ( isset( $wp->query_vars['ohmylms-order-received'] ) ) {
			self::order_received( $wp->query_vars['ohmylms-order-received'] );
		} else {
			self::checkout();
		}
	}


	/**
	 * Show the checkout.
	 *
	 * @since 1.0.0
	 */
	private static function checkout() {
		// Check if we're in preview mode (Elementor, Gutenberg, Bricks, or WPBakery).
		$is_elementor_preview_mode = apply_filters( 'ohmylms_elementor_preview_mode', false );
		$is_gutenberg_preview_mode = apply_filters( 'ohmylms_gutenberg_preview_mode', false );
		$is_bricks_preview_mode    = apply_filters( 'ohmylms_bricks_preview_mode', false );
		$is_wpbakery_preview_mode  = apply_filters( 'ohmylms_wpbakery_preview_mode', false );

		$is_preview_mode = $is_elementor_preview_mode || $is_gutenberg_preview_mode || $is_bricks_preview_mode || $is_wpbakery_preview_mode;

		// Auto-add random course to cart if empty (for first-time checkout).
		if ( ! $is_preview_mode && ecommerce()->cart->is_empty() && ! \is_customize_preview() ) {
			// Check if user hasn't seen this checkout before (using transient instead of session).
			$user_id       = get_current_user_id();
			$transient_key = 'ohmylms_checkout_auto_added_' . ( $user_id ? $user_id : wp_get_session_token() );

			if ( ! get_transient( $transient_key ) ) {
				// Get a random published course.
				$random_course_args = array(
					'post_type'      => 'ohmylms-course',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'rand',
					'fields'         => 'ids',
				);

				$random_courses = get_posts( $random_course_args );

				if ( ! empty( $random_courses ) ) {
					$course_id = $random_courses[0];

					// Add course to cart.
					ecommerce()->cart->add_to_cart(
						array(
							'course_id' => $course_id,
							'type'      => 'ohmylms-course',
							'quantity'  => 1,
						)
					);

					// Mark that we've auto-added a course (expires in 1 hour).
					set_transient( $transient_key, true, HOUR_IN_SECONDS );

					// Recalculate totals.
					ecommerce()->cart->calculate_totals();
				}
			}
		}

		// Check cart has contents (skip check in preview mode).
		if ( ! $is_preview_mode && ecommerce()->cart->is_empty() && ! \is_customize_preview() ) {
			$archive_page_id  = \get_option( 'ohmylms_course_page_id', 0 );
			$archive_page_url = \home_url();
			if ( $archive_page_id ) {
				$archive_page_url = \get_permalink( $archive_page_id );
			}

			ohmylms_get_template( 'checkout/no-cart-data.php', array( 'archive_page_url' => $archive_page_url ) );
			return;
		}

		// Calc totals (skip in preview mode).
		if ( ! $is_preview_mode ) {
			ecommerce()->cart->calculate_totals();
		}

		// Get checkout object.
		$checkout                   = ecommerce()->checkout();
		$cart_data                  = ! $is_preview_mode ? ecommerce()->cart->get_cart_contents() : array();
		$is_course_already_enrolled = false;

		if ( ! $is_preview_mode && is_array( $cart_data ) ) {
			foreach ( $cart_data as $key => $data ) {
				if ( isset( $data['course_id'], $data['type'] ) ) {
					$id   = $data['course_id'];
					$type = $data['type'];
					if ( 'ohmylms-course' === $type ) {
						$course = ohmylms_get_course( $id );
						if ( $course ) {
							$is_course_already_enrolled = $course->has_access();
							$url                        = $course->get_permalink();
						}
					} else {
						if ( 'ohmylms-membership' !== $type ) {
							continue;
						}
						$membership = ohmylms_get_membership( $id );
						if ( $membership ) {
							$is_course_already_enrolled = $membership->is_already_purchased();
							$url                        = home_url( '/my-profile/' );
						}
					}
				}
			}
		}
		// Inject custom styles.
		self::inject_custom_styles();
		ohmylms_get_template( 'checkout/form-checkout.php', array( 'checkout' => $checkout ) );
	}


	/**
	 * Locate the course archive template
	 *
	 * @since 1.0.0
	 * @return string|false
	 */
	private static function locate_course_template() {
		$template_paths = array(
			get_stylesheet_directory() . '/archive-course.php',
			get_template_directory() . '/archive-course.php',
			plugin_dir_path( __FILE__ ) . '../../templates/archive-course.php',
		);

		foreach ( $template_paths as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return false;
	}

	/**
	 * Generate and inject custom CSS styles from shortcode attributes
	 *
	 * @since 1.0.0
	 */
	private static function inject_custom_styles() {
		global $ohmylms_checkout_attributes;

		if ( empty( $ohmylms_checkout_attributes ) ) {
			return;
		}

		$css = self::generate_custom_css( $ohmylms_checkout_attributes );

		if ( ! empty( $css ) ) {
			echo '<style type="text/css" id="ohmylms-checkout-custom-styles">' . $css . '</style>';
		}
	}


	/**
	 * Generate CSS from shortcode attributes
	 *
	 * @param array $attrs Shortcode attributes.
	 * @return string Generated CSS
	 * @since 1.0.0
	 */
	private static function generate_custom_css( $attrs ) {

		$css = '';
		// Title styles.
		$title_styles = array();
		if ( ! empty( $attrs['title_color'] ) ) {
			$title_styles[] = 'color: ' . esc_attr( $attrs['title_color'] );
		}
		if ( ! empty( $attrs['title_font_size'] ) ) {
			$title_styles[] = 'font-size: ' . esc_attr( $attrs['title_font_size'] );
		}
		if ( ! empty( $attrs['title_font_weight'] ) ) {
			$title_styles[] = 'font-weight: ' . esc_attr( $attrs['title_font_weight'] );
		}
		if ( ! empty( $attrs['title_font_family'] ) ) {
			$title_styles[] = 'font-family: ' . esc_attr( $attrs['title_font_family'] );
		}
		if ( ! empty( $attrs['title_text_transform'] ) ) {
			$title_styles[] = 'text-transform: ' . esc_attr( $attrs['title_text_transform'] );
		}
		if ( ! empty( $attrs['title_text_decoration'] ) ) {
			$title_styles[] = 'text-decoration: ' . esc_attr( $attrs['title_text_decoration'] );
		}
		if ( ! empty( $attrs['title_line_height'] ) ) {
			$title_styles[] = 'line-height: ' . esc_attr( $attrs['title_line_height'] );
		}
		if ( ! empty( $attrs['title_letter_spacing'] ) ) {
			$title_styles[] = 'letter-spacing: ' . esc_attr( $attrs['title_letter_spacing'] );
		}

		if ( ! empty( $title_styles ) ) {
			$css .= '.ohmylms-page .ohmylms-checkout-form-wrapper .ohmylms-checkout-title { ' . implode( '; ', $title_styles ) . '; }' . "\n";
		}

		// Input label styles.
		$label_styles = array();
		if ( ! empty( $attrs['input_label_color'] ) ) {
			$label_styles[] = 'color: ' . esc_attr( $attrs['input_label_color'] );
		}
		if ( ! empty( $attrs['input_label_font_size'] ) ) {
			$label_styles[] = 'font-size: ' . esc_attr( $attrs['input_label_font_size'] );
		}
		if ( ! empty( $attrs['input_label_font_weight'] ) ) {
			$label_styles[] = 'font-weight: ' . esc_attr( $attrs['input_label_font_weight'] );
		}
		if ( ! empty( $attrs['input_label_font_family'] ) ) {
			$label_styles[] = 'font-family: ' . esc_attr( $attrs['input_label_font_family'] );
		}

		if ( ! empty( $attrs['input_background_color'] ) ) {
			$label_styles[] = 'background-color: ' . esc_attr( $attrs['input_background_color'] );
		}

		if ( ! empty( $label_styles ) ) {
			$css .= '.ohmylms-form-row label.ohmylms-input-label { ' . implode( '; ', $label_styles ) . '; }' . "\n";
		}

		// Input field styles.
		$input_styles = array();
		if ( ! empty( $attrs['input_font_size'] ) ) {
			$input_styles[] = 'font-size: ' . esc_attr( $attrs['input_font_size'] );
		}
		if ( ! empty( $attrs['input_font_weight'] ) ) {
			$input_styles[] = 'font-weight: ' . esc_attr( $attrs['input_font_weight'] );
		}
		if ( ! empty( $attrs['input_color'] ) ) {
			$input_styles[] = 'color: ' . esc_attr( $attrs['input_color'] );
		}
		if ( ! empty( $attrs['input_font_family'] ) ) {
			$input_styles[] = 'font-family: ' . esc_attr( $attrs['input_font_family'] );
		}
		if ( ! empty( $attrs['input_background_color'] ) ) {
			$input_styles[] = 'background-color: ' . esc_attr( $attrs['input_background_color'] );
		}
		if ( ! empty( $attrs['input_border_color'] ) ) {
			$input_styles[] = 'border-color: ' . esc_attr( $attrs['input_border_color'] );
		}
		if ( ! empty( $attrs['input_border_width'] ) ) {
			$input_styles[] = 'border-width: ' . esc_attr( $attrs['input_border_width'] );
		}
		if ( ! empty( $attrs['input_border_style'] ) ) {
			$input_styles[] = 'border-style: ' . esc_attr( $attrs['input_border_style'] );
		}
		if ( ! empty( $attrs['input_border_radius'] ) ) {
			$input_styles[] = 'border-radius: ' . esc_attr( $attrs['input_border_radius'] );
		}

		if ( ! empty( $input_styles ) ) {
			$css .= '.ohmylms-page input.ohmylms-input-text, .ohmylms-page select.ohmylms-input-select { ' . implode( '; ', $input_styles ) . '; }' . "\n";
		}

		// Checkout button styles.
		$button_styles = array();
		if ( ! empty( $attrs['button_background_color'] ) ) {
			$button_styles[] = 'background-color: ' . esc_attr( $attrs['button_background_color'] ) . '!important';
		}
		if ( ! empty( $attrs['button_color'] ) ) {
			$button_styles[] = 'color: ' . esc_attr( $attrs['button_color'] ) . '!important';
		}
		if ( ! empty( $attrs['button_font_size'] ) ) {
			$button_styles[] = 'font-size: ' . esc_attr( $attrs['button_font_size'] ) . '!important';
		}
		if ( ! empty( $attrs['button_font_weight'] ) ) {
			$button_styles[] = 'font-weight: ' . esc_attr( $attrs['button_font_weight'] ) . '!important';
		}
		if ( ! empty( $attrs['button_font_family'] ) ) {
			$button_styles[] = 'font-family: ' . esc_attr( $attrs['button_font_family'] ) . '!important';
		}
		if ( ! empty( $attrs['button_text_transform'] ) ) {
			$button_styles[] = 'text-transform: ' . esc_attr( $attrs['button_text_transform'] ) . '!important';
		}
		if ( ! empty( $attrs['button_text_decoration'] ) ) {
			$button_styles[] = 'text-decoration: ' . esc_attr( $attrs['button_text_decoration'] ) . '!important';
		}
		if ( ! empty( $attrs['button_line_height'] ) ) {
			$button_styles[] = 'line-height: ' . esc_attr( $attrs['button_line_height'] ) . '!important';
		}
		if ( ! empty( $attrs['button_letter_spacing'] ) ) {
			$button_styles[] = 'letter-spacing: ' . esc_attr( $attrs['button_letter_spacing'] ) . '!important';
		}

		// Button padding.
		$padding_parts = array();
		if ( ! empty( $attrs['button_padding_top'] ) || ! empty( $attrs['button_padding_right'] ) ||
			! empty( $attrs['button_padding_bottom'] ) || ! empty( $attrs['button_padding_left'] ) ) {
			$padding_parts[] = ! empty( $attrs['button_padding_top'] ) ? esc_attr( $attrs['button_padding_top'] ) : '0';
			$padding_parts[] = ! empty( $attrs['button_padding_right'] ) ? esc_attr( $attrs['button_padding_right'] ) : '0';
			$padding_parts[] = ! empty( $attrs['button_padding_bottom'] ) ? esc_attr( $attrs['button_padding_bottom'] ) : '0';
			$padding_parts[] = ! empty( $attrs['button_padding_left'] ) ? esc_attr( $attrs['button_padding_left'] ) : '0';
			$button_styles[] = 'padding: ' . implode( ' ', $padding_parts );
		}

		// Button margin.
		$margin_parts = array();
		if ( ! empty( $attrs['button_margin_top'] ) || ! empty( $attrs['button_margin_right'] ) ||
			! empty( $attrs['button_margin_bottom'] ) || ! empty( $attrs['button_margin_left'] ) ) {
			$margin_parts[]  = ! empty( $attrs['button_margin_top'] ) ? esc_attr( $attrs['button_margin_top'] ) : '0';
			$margin_parts[]  = ! empty( $attrs['button_margin_right'] ) ? esc_attr( $attrs['button_margin_right'] ) : '0';
			$margin_parts[]  = ! empty( $attrs['button_margin_bottom'] ) ? esc_attr( $attrs['button_margin_bottom'] ) : '0';
			$margin_parts[]  = ! empty( $attrs['button_margin_left'] ) ? esc_attr( $attrs['button_margin_left'] ) : '0';
			$button_styles[] = 'margin: ' . implode( ' ', $margin_parts );
		}

		if ( ! empty( $attrs['button_border_radius'] ) ) {
			$button_styles[] = 'border-radius: ' . esc_attr( $attrs['button_border_radius'] );
		}
		if ( ! empty( $attrs['button_border_color'] ) ) {
			$button_styles[] = 'border-color: ' . esc_attr( $attrs['button_border_color'] );
		}
		if ( ! empty( $attrs['button_border_width'] ) ) {
			$button_styles[] = 'border-width: ' . esc_attr( $attrs['button_border_width'] );
		}
		if ( ! empty( $attrs['button_border_style'] ) ) {
			$button_styles[] = 'border-style: ' . esc_attr( $attrs['button_border_style'] );
		}
		if ( ! empty( $attrs['button_box_shadow'] ) ) {
			$button_styles[] = 'box-shadow: ' . esc_attr( $attrs['button_box_shadow'] );
		}

		if ( ! empty( $button_styles ) ) {
			$css .= '.ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button { ' . implode( '; ', $button_styles ) . '; }' . "\n";
		}

		// Button hover styles.
		$button_hover_styles = array();
		if ( ! empty( $attrs['button_hover_background_color'] ) ) {
			$button_hover_styles[] = 'background-color: ' . esc_attr( $attrs['button_hover_background_color'] ) . '!important';
		}
		if ( ! empty( $attrs['button_hover_color'] ) ) {
			$button_hover_styles[] = 'color: ' . esc_attr( $attrs['button_hover_color'] ) . '!important';
		}
		if ( ! empty( $attrs['button_hover_border_color'] ) ) {
			$button_hover_styles[] = 'border-color: ' . esc_attr( $attrs['button_hover_border_color'] ) . '!important';
		}
		if ( ! empty( $attrs['button_hover_box_shadow'] ) ) {
			$button_hover_styles[] = 'box-shadow: ' . esc_attr( $attrs['button_hover_box_shadow'] );
		}

		if ( ! empty( $button_hover_styles ) ) {
			$css .= '.ohmylms-page .ohmylms-checkout-payment button.ohmylms-place-order-button:hover { ' . implode( '; ', $button_hover_styles ) . '; }' . "\n";
		}

		// Privacy text styles.
		$privacy_styles = array();
		if ( ! empty( $attrs['privacy_text_color'] ) ) {
			$privacy_styles[] = 'color: ' . esc_attr( $attrs['privacy_text_color'] );
		}
		if ( ! empty( $attrs['privacy_text_font_size'] ) ) {
			$privacy_styles[] = 'font-size: ' . esc_attr( $attrs['privacy_text_font_size'] );
		}
		if ( ! empty( $attrs['privacy_text_font_weight'] ) ) {
			$privacy_styles[] = 'font-weight: ' . esc_attr( $attrs['privacy_text_font_weight'] );
		}
		if ( ! empty( $attrs['privacy_text_font_family'] ) ) {
			$privacy_styles[] = 'font-family: ' . esc_attr( $attrs['privacy_text_font_family'] );
		}
		if ( ! empty( $attrs['privacy_text_transform'] ) ) {
			$privacy_styles[] = 'text-transform: ' . esc_attr( $attrs['privacy_text_transform'] );
		}
		if ( ! empty( $attrs['privacy_text_decoration'] ) ) {
			$privacy_styles[] = 'text-decoration: ' . esc_attr( $attrs['privacy_text_decoration'] );
		}
		if ( ! empty( $attrs['privacy_text_line_height'] ) ) {
			$privacy_styles[] = 'line-height: ' . esc_attr( $attrs['privacy_text_line_height'] );
		}
		if ( ! empty( $attrs['privacy_text_letter_spacing'] ) ) {
			$privacy_styles[] = 'letter-spacing: ' . esc_attr( $attrs['privacy_text_letter_spacing'] );
		}

		if ( ! empty( $privacy_styles ) ) {
			$css .= '.ohmylms-page .ohmylms-tnc-wrapper span { ' . implode( '; ', $privacy_styles ) . '; }' . "\n";
		}

		// Checkout box styles.
		$checkout_box_styles = array();
		if ( ! empty( $attrs['checkout_box_background_color'] ) ) {
			$checkout_box_styles[] = 'background-color: ' . esc_attr( $attrs['checkout_box_background_color'] );
			$css                  .= '.ohmylms-page div.ohmylms-checkout-form-outer::before { background-color: ' . esc_attr( $attrs['checkout_box_background_color'] ) . '; }' . "\n";
			$css                  .= '.ohmylms-page .ohmylms-checkout-payment .ohmylms-payment-method-wrapper { background-color: ' . esc_attr( $attrs['checkout_box_background_color'] ) . '; }' . "\n";
		}

		// Checkout box padding.
		$checkout_padding_parts = array();
		if ( ! empty( $attrs['checkout_box_padding_top'] ) || ! empty( $attrs['checkout_box_padding_right'] ) ||
			! empty( $attrs['checkout_box_padding_bottom'] ) || ! empty( $attrs['checkout_box_padding_left'] ) ) {
			$checkout_padding_parts[] = ! empty( $attrs['checkout_box_padding_top'] ) ? esc_attr( $attrs['checkout_box_padding_top'] ) . '!important' : '0';
			$checkout_padding_parts[] = ! empty( $attrs['checkout_box_padding_right'] ) ? esc_attr( $attrs['checkout_box_padding_right'] ) . '!important' : '0';
			$checkout_padding_parts[] = ! empty( $attrs['checkout_box_padding_bottom'] ) ? esc_attr( $attrs['checkout_box_padding_bottom'] ) . '!important' : '0';
			$checkout_padding_parts[] = ! empty( $attrs['checkout_box_padding_left'] ) ? esc_attr( $attrs['checkout_box_padding_left'] ) . '!important' : '0';
			$checkout_box_styles[]    = 'padding: ' . implode( ' ', $checkout_padding_parts );
		}

		// Checkout box margin.
		$checkout_margin_parts = array();
		if ( ! empty( $attrs['checkout_box_margin_top'] ) || ! empty( $attrs['checkout_box_margin_right'] ) ||
			! empty( $attrs['checkout_box_margin_bottom'] ) || ! empty( $attrs['checkout_box_margin_left'] ) ) {
			$checkout_margin_parts[] = ! empty( $attrs['checkout_box_margin_top'] ) ? esc_attr( $attrs['checkout_box_margin_top'] ) . '!important' : '0';
			$checkout_margin_parts[] = ! empty( $attrs['checkout_box_margin_right'] ) ? esc_attr( $attrs['checkout_box_margin_right'] ) . '!important' : '0';
			$checkout_margin_parts[] = ! empty( $attrs['checkout_box_margin_bottom'] ) ? esc_attr( $attrs['checkout_box_margin_bottom'] ) . '!important' : '0';
			$checkout_margin_parts[] = ! empty( $attrs['checkout_box_margin_left'] ) ? esc_attr( $attrs['checkout_box_margin_left'] ) . '!important' : '0';
			$checkout_box_styles[]   = 'margin: ' . implode( ' ', $checkout_margin_parts );
		}

		if ( ! empty( $attrs['checkout_box_border_color'] ) ) {
			$checkout_box_styles[] = 'border-color: ' . esc_attr( $attrs['checkout_box_border_color'] ) . '!important';
		}
		if ( ! empty( $attrs['checkout_box_border_width'] ) ) {
			$checkout_box_styles[] = 'border-width: ' . esc_attr( $attrs['checkout_box_border_width'] ) . '!important';
		}
		if ( ! empty( $attrs['checkout_box_border_style'] ) ) {
			$checkout_box_styles[] = 'border-style: ' . esc_attr( $attrs['checkout_box_border_style'] ) . '!important';
		}
		if ( ! empty( $attrs['checkout_box_border_radius'] ) ) {
			$checkout_box_styles[] = 'border-radius: ' . esc_attr( $attrs['checkout_box_border_radius'] ) . '!important';
		}

		if ( ! empty( $checkout_box_styles ) ) {
			$css .= '.ohmylms-page .ohmylms-checkout-form-left { ' . implode( '; ', $checkout_box_styles ) . '; }' . "\n";
		}

		// Order summary styles.
		$order_summary_styles = array();
		if ( ! empty( $attrs['order_summary_background_color'] ) ) {
			$order_summary_styles[] = 'background-color: ' . esc_attr( $attrs['order_summary_background_color'] ) . '!important';
			$css                   .= '.ohmylms { background-color: ' . esc_attr( $attrs['order_summary_background_color'] ) . '!important; }' . "\n";
		}

		// Order summary padding.
		$summary_padding_parts = array();
		if ( ! empty( $attrs['order_summary_padding_top'] ) || ! empty( $attrs['order_summary_padding_right'] ) ||
			! empty( $attrs['order_summary_padding_bottom'] ) || ! empty( $attrs['order_summary_padding_left'] ) ) {
			$summary_padding_parts[] = ! empty( $attrs['order_summary_padding_top'] ) ? esc_attr( $attrs['order_summary_padding_top'] ) . '!important' : '0';
			$summary_padding_parts[] = ! empty( $attrs['order_summary_padding_right'] ) ? esc_attr( $attrs['order_summary_padding_right'] ) . '!important' : '0';
			$summary_padding_parts[] = ! empty( $attrs['order_summary_padding_bottom'] ) ? esc_attr( $attrs['order_summary_padding_bottom'] ) . '!important' : '0';
			$summary_padding_parts[] = ! empty( $attrs['order_summary_padding_left'] ) ? esc_attr( $attrs['order_summary_padding_left'] ) . '!important' : '0';
			$order_summary_styles[]  = 'padding: ' . implode( ' ', $summary_padding_parts );
		}

		// Order summary margin.
		$summary_margin_parts = array();
		if ( ! empty( $attrs['order_summary_margin_top'] ) || ! empty( $attrs['order_summary_margin_right'] ) ||
			! empty( $attrs['order_summary_margin_bottom'] ) || ! empty( $attrs['order_summary_margin_left'] ) ) {
			$summary_margin_parts[] = ! empty( $attrs['order_summary_margin_top'] ) ? esc_attr( $attrs['order_summary_margin_top'] ) . '!important' : '0';
			$summary_margin_parts[] = ! empty( $attrs['order_summary_margin_right'] ) ? esc_attr( $attrs['order_summary_margin_right'] ) . '!important' : '0';
			$summary_margin_parts[] = ! empty( $attrs['order_summary_margin_bottom'] ) ? esc_attr( $attrs['order_summary_margin_bottom'] ) . '!important' : '0';
			$summary_margin_parts[] = ! empty( $attrs['order_summary_margin_left'] ) ? esc_attr( $attrs['order_summary_margin_left'] ) . '!important' : '0';
			$order_summary_styles[] = 'margin: ' . implode( ' ', $summary_margin_parts );
		}

		if ( ! empty( $attrs['order_summary_border_color'] ) ) {
			$order_summary_styles[] = 'border-color: ' . esc_attr( $attrs['order_summary_border_color'] ) . '!important';
		}
		if ( ! empty( $attrs['order_summary_border_width'] ) ) {
			$order_summary_styles[] = 'border-width: ' . esc_attr( $attrs['order_summary_border_width'] ) . '!important';
		}
		if ( ! empty( $attrs['order_summary_border_style'] ) ) {
			$order_summary_styles[] = 'border-style: ' . esc_attr( $attrs['order_summary_border_style'] ) . '!important';
		}
		if ( ! empty( $attrs['order_summary_border_radius'] ) ) {
			$order_summary_styles[] = 'border-radius: ' . esc_attr( $attrs['order_summary_border_radius'] ) . '!important';
		}

		if ( ! empty( $order_summary_styles ) ) {
			$css .= '.ohmylms-page div.ohmylms-checkout-form-right { ' . implode( '; ', $order_summary_styles ) . '; }' . "\n";
		}

		return $css;
	}

	/**
	 * Handles the order received page.
	 *
	 * @param int $order_id The ID of the received order.
	 * @since 1.0.0
	 */
	private static function order_received( $order_id ) {
		$order = false;

		// Get the order.
		$order_id  = absint( $order_id );
		$order_key = empty( $_GET['key'] ) ? '' : wp_unslash( $_GET['key'] ); // WPCS: input var ok, CSRF ok.

		if ( $order_id > 0 ) {
			$order = ohmylms_ecommerce_get_order( $order_id );
			if ( ( ! hash_equals( $order->get_order_key(), $order_key ) ) ) {
				$order = false;
			}
		}

		if ( ! $order->has_status( 'failed' ) ) {
			ohmylms_empty_cart();
		}

		if ( ! $order ) {
			ohmylms_get_template(
				'checkout/thankyou.php',
				array(
					'order' => false,
				)
			);
			return;
		}

		$order_student_id = $order->get_student_id();
		$student          = new Student( $order_student_id );
		if ( $order_student_id && get_current_user_id() != $order_student_id ) {
			ohmylms_get_template( 'checkout/order-received.php', array( 'order' => false ) );
			return;
		}
		ohmylms_get_template(
			'checkout/thankyou.php',
			array(
				'order'   => $order,
				'student' => $student,
			)
		);
	}
}
