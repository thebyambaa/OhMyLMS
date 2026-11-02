<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ShortCodeBuyNow
 *
 * @package OhMyLMS\Shortcodes
 */

namespace OhMyLMS\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin short code buy now implementation.
 */
class ShortCodeBuyNow {
	/**
	 * Output the Buy Now button shortcode
	 *
	 * @param array $atts Atts.
	 */
	public static function output( $atts ) {
		$atts = shortcode_atts(
			array(
				'btn_text'        => __( 'Buy Now', 'ohmylms' ),
				'course_id'       => '',
				'background'      => '#0073aa',
				'color'           => '#fff',
				'padding'         => '12px 24px',
				'border_radius'   => '4px',
				'font_size'       => '16px',
				'text_decoration' => 'none',
				'line_height'     => '1.5',
				'width'           => 'auto',
				'max_width'       => '100%',
				'min_width'       => '100px',
				'height'          => 'auto',
				'class'           => '',
				'quantity'        => 1,
				'attributes'      => array(),
			),
			$atts
		);

		if ( empty( $atts['course_id'] ) ) {
			self::error( __( 'Course ID is required.', 'ohmylms' ) );
			return;
		}

		$course = function_exists( 'ohmylms_get_course' ) ? ohmylms_get_course( $atts['course_id'] ) : null;
		if ( ! $course ) {
			self::error( __( 'Invalid Course ID.', 'ohmylms' ) );
			return;
		}

		$status = $course->get_status();
		if ( 'publish' !== $status ) {
			self::error( __( 'Course is not published.', 'ohmylms' ) );
			return;
		}

		// Set default class and attributes as in add-to-cart.php.
		$default_class = $course->is_purchasable() && $course->is_in_stock() ? 'add_to_cart_button enroll-button ohmylms-button' : 'ohmylms-button enroll-button';
		$defaults      = array(
			'quantity'   => 1,
			'class'      => trim( $default_class . ' ' . $atts['class'] ),
			'attributes' => array_merge(
				array(
					'data-course_id' => $course->get_id(),
					'rel'            => 'nofollow',
				),
				is_array( $atts['attributes'] ) ? $atts['attributes'] : array()
			),
		);

		$should_show_buy_now = true;
		if ( method_exists( $course, 'get_type' ) && $course->get_type() === 'cohort-based' ) {
			$cohorts               = $course->get_cohort();
			$has_active_enrollment = false;
			$all_expired           = true;
			$current_time          = current_time( 'timestamp' );
			foreach ( $cohorts as $cohort ) {
				if ( ! empty( $cohort['enrollment_deadline'] ) ) {
					$enrollment_end = strtotime( $cohort['enrollment_deadline'] );
					if ( $enrollment_end > $current_time ) {
						$has_active_enrollment = true;
					}
				} else {
					$has_active_enrollment = true;
				}
				if ( ! empty( $cohort['end_date'] ) ) {
					if ( strtotime( $cohort['end_date'] ) >= $current_time ) {
						$all_expired = false;
					}
				} else {
					$all_expired = false;
				}
			}
			$should_show_buy_now = $has_active_enrollment && ! $all_expired;
		}
		if ( $should_show_buy_now ) {
			$url      = $course->add_to_cart_url() ? $course->add_to_cart_url() : '';
			$btn_text = $atts['btn_text'] ? $atts['btn_text'] : ( method_exists( $course, 'add_to_cart_text' ) ? $course->add_to_cart_text() : __( 'Buy Now', 'ohmylms' ) );
			$style    = sprintf(
				'background:%s;color:%s;padding:%s;border-radius:%s;font-size:%s;border:none;cursor:pointer;text-decoration:%s;line-height:%s;width:%s;max-width:%s;min-width:%s;height:%s;',
				esc_attr( $atts['background'] ),
				esc_attr( $atts['color'] ),
				esc_attr( $atts['padding'] ),
				esc_attr( $atts['border_radius'] ),
				esc_attr( $atts['font_size'] ),
				esc_attr( $atts['text_decoration'] ),
				esc_attr( $atts['line_height'] ),
				esc_attr( $atts['width'] ),
				esc_attr( $atts['max_width'] ),
				esc_attr( $atts['min_width'] ),
				esc_attr( $atts['height'] )
			);
			// Build HTML attributes string.
			$html_attributes = '';
			foreach ( $defaults['attributes'] as $attr_key => $attr_val ) {
				$html_attributes .= sprintf( ' %s="%s"', esc_attr( $attr_key ), esc_attr( $attr_val ) );
			}
			$html_attributes .= sprintf( ' data-quantity="%s"', esc_attr( $defaults['quantity'] ) );

			$button = sprintf(
				'<a href="%s"%s class="%s" style="%s">%s</a>',
				esc_url( $url ),
				$html_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each key and value is escaped above.
				esc_attr( $default_class ),
				esc_attr( $style ),
				esc_html( $btn_text )
			);
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above; Commerce only adds directive attributes.
			echo \OhMyLMS\Extensions\Commerce::cart_buttons( $button );
		}
	}

	/**
	 * Plain-text error for a shortcode that cannot render a button.
	 *
	 * @param string $message Message.
	 */
	private static function error( $message ) {
		if ( class_exists( \OhMyLMS\Design\Ui::class ) ) {
			\OhMyLMS\Design\Ui::enqueue();
		}
		echo '<span class="ohmylms-buy-now-error">' . esc_html( $message ) . '</span>';
	}
}
