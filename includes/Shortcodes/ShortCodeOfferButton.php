<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class ShortCodeOfferButton
 *
 * @package OhMyLMS\Shortcodes
 * @since 1.0.0
 */

namespace OhMyLMS\Shortcodes;

use OhMyLMS\Integrations\Funnel\Includes\FunnelManager;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin short code offer button implementation.
 */
class ShortCodeOfferButton {

	/**
	 * Get the shortcode content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return void
	 * @since 1.0.0
	 */
	public static function output( $atts ) {
		$atts = shortcode_atts(
			array(
				'action'        => 'accept',        // accept or decline.
				'text'          => 'Accept Offer',              // Button text.
				'class'         => '',              // CSS classes.
				'id'            => '',              // CSS ID.
				'style'         => '',              // Inline CSS styles.
				'background'    => '#0073aa',              // Background color.
				'color'         => '#fff',              // Text color.
				'border'        => '',              // Border style.
				'padding'       => '12px 24px',              // Padding.
				'margin'        => '',              // Margin.
				'font_size'     => '16px',              // Font size.
				'font_weight'   => '',              // Font weight.
				'border_radius' => '4px',              // Border radius.
				'width'         => '',              // Button width.
				'height'        => '',              // Button height.
			),
			$atts,
			'ohmylms_offer_button'
		);

		// Get funnel session data to determine if we're in a funnel context.
		$funnel_data = FunnelManager::get_funnel_session();

		// Extract funnel parameters - get current step from URL instead of session.
		$order_id = $funnel_data['order_id'] ?? '';

		// Also check URL parameters for order_id (for offer pages).
		if ( empty( $order_id ) && isset( $_GET['order_id'] ) ) {
			$order_id = absint( $_GET['order_id'] );
		}

		// Get current step from URL (same way as FunnelEndpoint does).
		$current_step = get_query_var( 'step' );

		// If not available from query vars, check URL parameters (for offer pages).
		if ( empty( $current_step ) && isset( $_GET['step'] ) ) {
			$current_step = sanitize_text_field( wp_unslash( $_GET['step'] ));
		}

		// If still not available, fall back to session (for backward compatibility).
		if ( empty( $current_step ) ) {
			$current_step = $funnel_data['current_step'] ?? '';
		}

		// Ensure step has 'step_' prefix for consistency.
		if ( ! empty( $current_step ) && strpos( $current_step, 'step_' ) !== 0 ) {
			$current_step = 'step_' . $current_step;
		}

		// Set default button text based on action.
		if ( empty( $atts['text'] ) ) {
			$atts['text'] = ( 'accept' === $atts['action'] )
				? __( 'Yes, I want this offer!', 'ohmylms' )
				: __( 'No thanks, continue', 'ohmylms' );
		}

		// Build CSS classes.
		$button_classes = array( 'ohmylms-offer-btn' );

		// Add action class for default styling hook.
		$button_classes[] = 'ohmylms-offer-btn--' . sanitize_html_class( $atts['action'] );

		// Add custom classes.
		if ( ! empty( $atts['class'] ) ) {
			$custom_classes = explode( ' ', $atts['class'] );
			$button_classes = array_merge( $button_classes, array_map( 'sanitize_html_class', $custom_classes ) );
		}

		// Build inline styles.
		$inline_styles = array();

		if ( ! empty( $atts['background'] ) ) {
			$inline_styles[] = 'background-color: ' . esc_attr( $atts['background'] );
		}

		if ( ! empty( $atts['color'] ) ) {
			$inline_styles[] = 'color: ' . esc_attr( $atts['color'] );
		}

		if ( ! empty( $atts['border'] ) ) {
			$inline_styles[] = 'border: ' . esc_attr( $atts['border'] );
		}

		if ( ! empty( $atts['padding'] ) ) {
			$inline_styles[] = 'padding: ' . esc_attr( $atts['padding'] );
		}

		if ( ! empty( $atts['margin'] ) ) {
			$inline_styles[] = 'margin: ' . esc_attr( $atts['margin'] );
		}

		if ( ! empty( $atts['font_size'] ) ) {
			$inline_styles[] = 'font-size: ' . esc_attr( $atts['font_size'] );
		}

		if ( ! empty( $atts['font_weight'] ) ) {
			$inline_styles[] = 'font-weight: ' . esc_attr( $atts['font_weight'] );
		}

		if ( ! empty( $atts['border_radius'] ) ) {
			$inline_styles[] = 'border-radius: ' . esc_attr( $atts['border_radius'] );
		}

		if ( ! empty( $atts['width'] ) ) {
			$inline_styles[] = 'width: ' . esc_attr( $atts['width'] );
		}

		if ( ! empty( $atts['height'] ) ) {
			$inline_styles[] = 'height: ' . esc_attr( $atts['height'] );
		}

		// Add custom inline styles.
		if ( ! empty( $atts['style'] ) ) {
			$inline_styles[] = $atts['style'];
		}

		$style_attr = ! empty( $inline_styles ) ? implode( '; ', $inline_styles ) : '';

		// Generate action URL.
		$action_url = self::get_offer_action_url( $order_id, $current_step, $atts['action'], isset( $atts['redirect'] ) ? $atts['redirect'] : '' );

		// Use the funnel nonce passed from the funnel endpoint, or create one if not available.
		$nonce      = $_GET['funnel_nonce'] ?? wp_create_nonce( 'funnel_action_' . $order_id . '_' . $current_step );
		$action_url = $order_id ? add_query_arg( '_wpnonce', $nonce, $action_url ) : '';
		// Output the button.
		self::render_offer_button( $action_url, $atts['text'], $button_classes, $style_attr, $atts );
	}

	/**
	 * Generate the offer action URL.
	 *
	 * @param int    $order_id The order ID.
	 * @param string $current_step The current funnel step.
	 * @param string $action The action (accept/decline).
	 * @param string $custom_redirect Custom redirect URL.
	 * @return string The action URL
	 * @since 1.0.0
	 */
	private static function get_offer_action_url( $order_id, $current_step, $action, $custom_redirect = '' ) {
		// If custom redirect is provided and action is decline, use it.
		if ( ! empty( $custom_redirect ) && 'decline' === $action ) {
			return esc_url( $custom_redirect );
		}

		// Remove 'step_' prefix if it exists to get just the number.
		$step_number = $current_step;
		if ( strpos( $step_number, 'step_' ) === 0 ) {
			$step_number = substr( $step_number, 5 );
		}

		// Build funnel step URL (without action in the path).
		$base_url = $order_id ? home_url( sprintf( '/post-checkout-funnel/order/%d/step/%s', $order_id, $step_number ) ) : '';

		// Add action as query parameter.
		$action_url = $base_url ? add_query_arg( 'action', $action, $base_url ) : '#';

		return $action_url;
	}

	/**
	 * Render the offer button HTML.
	 *
	 * @param string $url The button URL.
	 * @param string $text The button text.
	 * @param array  $classes CSS classes array.
	 * @param string $style_attr Inline style attribute.
	 * @param array  $atts Shortcode attributes.
	 * @since 1.0.0
	 */
	private static function render_offer_button( $url, $text, $classes, $style_attr, $atts ) {
		?>
		<div class="ohmylms-offer-button-wrapper">
			<a href="<?php echo esc_url( $url ); ?>" 
				<?php
				if ( ! empty( $atts['id'] ) ) :
					?>
					id="<?php echo esc_attr( $atts['id'] ); ?>"<?php endif; ?>
				class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
				<?php
				if ( ! empty( $style_attr ) ) :
					?>
					style="<?php echo esc_attr( $style_attr ); ?>"<?php endif; ?>
				data-action="<?php echo esc_attr( $atts['action'] ); ?>">
				<?php echo esc_html( $text ); ?>
			</a>
		</div>
		<?php
	}
}
