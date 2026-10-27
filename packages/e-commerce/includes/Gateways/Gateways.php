<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Gateways;

use OhMyLMS\Abstracts\SettingsPage;

/**
 * Plugin gateways implementation.
 */
class Gateways extends SettingsPage {

	/**
	 * Payment gateway classes.
	 *
	 * @var array
	 * @since 1.0.0
	 */
	public $payment_gateways = array();


	/**
	 * The single instance of the class.
	 *
	 * @var Gateways
	 * @since 1.0.0
	 */
	protected static $_instance = null;


	/**
	 * Instance.
	 *
	 * @return Gateways
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	/**
	 * Initialize payment gateways.
	 */
	public function __construct() {
		$this->init();
	}


	/**
	 * Initialize payment gateways.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$load_gateways = array(
			'OhMyLMS_Gateway_Offline',
			'OhMyLMS_Gateway_Stripe_Intents',
			'GatewayPaypal',
			\OhMyLMS\Commerce\Gateways\QPay\GatewayQPay::class,
		);

		$load_gateways = apply_filters( 'ohmylms_payment_gateways', $load_gateways );
		// Get sort order option.
		$ordering  = (array) get_option( 'ohmylms_gateway_order' );
		$order_end = 999;

		foreach ( $load_gateways as $gateway ) {

			if ( is_string( $gateway ) && class_exists( $gateway ) ) {
				$gateway = new $gateway();
				if ( isset( $ordering[ $gateway->id ] ) && is_numeric( $ordering[ $gateway->id ] ) ) {
					$this->payment_gateways[ $ordering[ $gateway->id ] ] = $gateway;
				} else {
					// Add to end of the array.
					$this->payment_gateways[ $order_end ] = $gateway;
					++$order_end;
				}
			}
		}
		ksort( $this->payment_gateways );
	}

	/**
	 * Initialize paypal
	 *
	 * @return \PaypalGateway
	 * @since 1.0.0
	 */
	public function paypal(): \PaypalGateway {
		return new \PaypalGateway();
	}


	/**
	 * Get gateways.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_payment_gateways(): array {
		$_available_gateways = array();
		if ( count( $this->payment_gateways ) > 0 ) {
			foreach ( $this->payment_gateways as $gateway ) {
				$_available_gateways[ $gateway->id ] = $gateway;
			}
		}
		return $_available_gateways;
	}


	/**
	 * Get available gateways.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_available_payment_gateways(): array {
		$_available_gateways = array();
		foreach ( $this->payment_gateways as $gateway ) {
			if ( $gateway->is_available() ) {
				$_available_gateways[ $gateway->id ] = $gateway;
			}
		}
		return array_filter( (array) apply_filters( 'coderex_ecommerce_available_payment_gateways', $_available_gateways ) );
	}

	/**
	 * Get payment gateway settings.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function get_payment_gateway_settings() {
		$payment_gateways = $this->get_payment_gateways();
		$settings         = array();
		foreach ( $payment_gateways as $gateway ) {
			$settings[ $gateway->id ] = $gateway->get_settings();
		}
		return $settings;
	}


	/**
	 * Save options in admin.
	 */
	public function process_admin_options() {
		$gateway_order = isset( $_POST['gateway_order'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway_order'] ) ) : ''; // WPCS: input var ok, CSRF ok.
		$order         = array();

		if ( is_array( $gateway_order ) && count( $gateway_order ) > 0 ) {
			$loop = 0;
			foreach ( $gateway_order as $gateway_id ) {
				$order[ esc_attr( $gateway_id ) ] = $loop;
				++$loop;
			}
		}

		update_option( 'coderex_ecommerce_gateway_order', $order );
	}



	/**
	 * Set the current, active gateway.
	 *
	 * @param array $gateways Available payment gateways.
	 * @since 1.0.0
	 */
	public function set_current_gateway( $gateways ) {
		// Be on the defensive.
		if ( ! is_array( $gateways ) || empty( $gateways ) ) {
			return;
		}

		$current_gateway = false;

		if ( ohmylms()->session ) {
			$current = ohmylms()->session->get( 'chosen_payment_method' );
			if ( $current && isset( $gateways[ $current ] ) ) {
				$current_gateway = $gateways[ $current ];
			}
		}

		if ( ! $current_gateway ) {
			$current_gateway = current( $gateways );
		}

		// Ensure we can make a call to set_current() without triggering an error.
		if ( $current_gateway && is_callable( array( $current_gateway, 'set_current' ) ) ) {
			$current_gateway->set_current();
		}
	}


	/**
	 * Output for Gateways.
	 */
	public function output() {
		echo '<h1>' . esc_html__( 'Payment Gateways', 'ohmylms' ) . '</h1>';
	}
}
