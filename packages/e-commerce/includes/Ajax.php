<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

use GatewayPaypal;

/**
 * Plugin ajax implementation.
 */
class Ajax {
	/**
	 * Init for Ajax.
	 */
	public static function init(): void {
		self::add_ajax_actions();
	}

	/**
	 * Add ajax actions for Ajax.
	 */
	public static function add_ajax_actions(): void {
		$ajax_events_nopriv = array(
			'capture_paypal_payment',
			'get_states_by_country',
		);

		foreach ( $ajax_events_nopriv as $ajax_event ) {
			add_action( 'wp_ajax_ohmylms_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			add_action( 'wp_ajax_nopriv_ohmylms_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}

		$ajax_events = array(
			'search_pages',
		);
		foreach ( $ajax_events as $ajax_event ) {
			add_action( 'wp_ajax_ohmylms_' . $ajax_event, array( __CLASS__, $ajax_event ) );
		}
	}

	/**
	 * AJAX handler to retrieve states based on a given country code.
	 *
	 * This function expects a POST request containing a 'country_code' parameter.
	 * It sanitizes the input and returns a JSON response with the list of states
	 * for the specified country. If the country code is missing, it returns an error.
	 *
	 * @since 1.0.0
	 *
	 * @return void Outputs JSON response via wp_send_json_success() or wp_send_json_error().
	 */
	public static function get_states_by_country() {
		$country_code = isset( $_POST['country_code'] ) ? sanitize_text_field( wp_unslash( $_POST['country_code'] ) ) : '';
		if ( empty( $country_code ) ) {
			wp_send_json_error( 'No country code provided.' );
		}

		$states = ohmylms_get_states( $country_code );
		wp_send_json_success( $states );
	}
}
