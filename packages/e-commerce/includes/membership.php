<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

use PaypalGateway;

/**
 * Plugin membership implementation.
 */
class Membership {


	/**
	 * The single instance of the class.
	 *
	 * @var Membership|null
	 */
	protected static $instance = null;

	/**
	 * Gets the main Checkout instance
	 *
	 * @return Membership|null
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Generate membership product
	 *
	 * @param mixed $membership_id Membership identifier.
	 * @param mixed $membership_plans Membership plans.
	 * @return array|void
	 * @since 1.0.0
	 */
	public function generate_membership_product( $membership_id, $membership_plans ) {
		$paypal_config = new PaypalGateway();
		$settings      = $paypal_config->get_saved_settings();

		if ( ! $settings ) {
			return array(
				'status'  => 'error',
				'message' => __( 'No valid configuration found.', 'ohmylms' ),
			);
		}

		$paypal  = ecommerce()->paypal( $settings['client_id'], $settings['client_secret'], false );
		$product = $paypal->create_product( $membership_id, 'Membership-' . $membership_id );
		die();
	}

	/**
	 * Request membership
	 *
	 * @param mixed $membership_id Membership identifier.
	 * @return void
	 * @since 1.0.0
	 */
	public function request_membership( $membership_id ) {
		$checkout_page = get_permalink( ohmylms_get_page_id( 'checkout' ) );

		$query_array = array(
			'membership_id' => $membership_id,
		);

		$redirect_url = $checkout_page . '?' . http_build_query( $query_array );

		ecommerce()->cart->empty_cart( true ); // empty cart for membership checkout.

		$response = array(
			'status'       => 'success',
			'message'      => __( 'Redirecting to checkout.', 'ohmylms' ),
			'redirect_url' => $redirect_url,
		);

		wp_send_json( $response );
	}
}
