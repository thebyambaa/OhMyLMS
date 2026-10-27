<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Woostify.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\ThemeSupport;

defined( 'ABSPATH' ) || exit;

/**
 * Class Woostify
 *
 * Handles the theme support for Woostify theme.
 */
class Woostify {
	/**
	 * Init for Woostify.
	 */
	public function init() {
		add_filter( 'ohmylms_enqueue_styles', array( $this, 'enqueue_styles' ) );
		// Hook very early to intercept the AJAX call before Woostify processes it.
		add_action( 'wp_ajax_get_curr_percent_shipping_threshold_product', array( $this, 'prevent_woostify_ajax_error' ), 1 );
		add_action( 'wp_ajax_nopriv_get_curr_percent_shipping_threshold_product', array( $this, 'prevent_woostify_ajax_error' ), 1 );
	}

	/**
	 * Enqueue styles for Woostify.
	 *
	 * @param mixed $styles Styles.
	 */
	public function enqueue_styles( $styles ) {
		$styles['ohmylms-general'] = array(
			'src'     => OHMYLMS_ASSETS_URL . ( '/theme-support/theme-woostify.css' ),
			'deps'    => array(),
			'version' => OHMYLMS_VERSION,
			'media'   => 'all',
			'has_rtl' => true,
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}

	/**
	 * Prevent Woostify AJAX error when product_id is invalid
	 *
	 * This intercepts the AJAX call before Woostify's handler and validates
	 * that we have a valid WooCommerce product. If not, we send an empty
	 * response to prevent the fatal error.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function prevent_woostify_ajax_error() {
		// Validate nonce first.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['ajax_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ajax_nonce'] ) ), 'woostify_woocommerce_general_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'ohmylms' ) ) );
		}

		// Check if product_id is set and valid.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		// If product_id is invalid or not a WooCommerce product, send error response.
		if ( ! $product_id || ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product', 'ohmylms' ) ) );
		}

		$product = wc_get_product( $product_id );

		// If product doesn't exist, send error response to prevent fatal error.
		if ( ! $product || is_bool( $product ) ) {
			wp_send_json_error( array( 'message' => __( 'Product not found', 'ohmylms' ) ) );
		}

		// Product is valid, let Woostify's handler process it normally
		// We return here without sending JSON to allow the next hook to run.
	}
}
