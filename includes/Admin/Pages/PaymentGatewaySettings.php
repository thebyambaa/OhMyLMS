<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Admin\Pages;

use OhMyLMS\Abstracts\SettingsPage;
use Gateways\Gateways;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;


/**
 * Plugin payment gateway settings implementation.
 */
class PaymentGatewaySettings extends SettingsPage {

	/**
	 * Gateways.
	 *
	 * @var mixed
	 */
	public $gateways;

	/**
	 * Initialize Payment gateway settings.
	 */
	public function __construct() {
		$this->id    = 'payments';
		$this->label = __( 'Payment Gateways', 'ohmylms' );

		parent::__construct();
	}


	/**
	 * Get the sections for the settings page
	 *
	 * @return array|string[]
	 */
	public function get_own_sections(): array {
		return array(
			''                => __( 'General', 'ohmylms' ),
			'offline_payment' => __( 'Offline', 'ohmylms' ),
			'stripe'          => __( 'Stripe', 'ohmylms' ),
			'paypal'          => __( 'Paypal', 'ohmylms' ),
		);
	}


	/**
	 * Get default settings options
	 *
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function get_settings_for_default_section() {
		$settings = array(
			array(
				'title' => __( 'General Settings', 'ohmylms' ),
				'type'  => 'title',
				'desc'  => '',
				'id'    => 'payment_general_settings',
			),
			array(
				'title'         => __( 'Guest checkout', 'ohmylms' ),
				'desc'          => __( 'Enable guest checkout', 'ohmylms' ),
				'id'            => 'ohmylms_guest_checkout',
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
			),
			array(
				'title'         => __( 'Content Protection', 'ohmylms' ),
				'desc'          => __( 'Enable content protection', 'ohmylms' ),
				'id'            => 'ohmylms_content_protection',
				'default'       => 'no',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
			),
			array(
				'title'         => __( 'Account login', 'ohmylms' ),
				'desc'          => __( 'Enable login form for checkout', 'ohmylms' ),
				'id'            => 'ohmylms_enable_login',
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
			),
			array(
				'title'         => __( 'Allow purchase without login', 'ohmylms' ),
				'desc'          => __( 'Allow purchase without login', 'ohmylms' ),
				'id'            => 'ohmylms_allow_purchase_without_login',
				'default'       => 'yes',
				'type'          => 'checkbox',
				'checkboxgroup' => 'start',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'payment_general_settings',
			),
		);
		return apply_filters( 'ohmylms_payment_general_settings', $settings );
	}


	/**
	 * Output the settings fields
	 *
	 * @since 1.0.0
	 */
	public function output() {
		global $ohmylms_current_section;
		// Load gateways so we can show any global options they may have.
		$gateways = ecommerce()->gateways();

		if ( $ohmylms_current_section ) {
			foreach ( $gateways->payment_gateways as $gateway ) {
				if ( in_array( $ohmylms_current_section, array( $gateway->id, sanitize_title( get_class( $gateway ) ) ), true ) ) {
					if ( isset( $_GET['toggle_enabled'] ) ) {
						$enabled = $gateway->get_option( 'enabled' );
						if ( $enabled ) {
							$gateway->settings['enabled'] = ohmylms_string_to_bool( $enabled ) ? 'no' : 'yes';
						}
					}
					$gateway->admin_options();
					break;
				}
			}
		}
		parent::output();
	}


	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 */
	public function save() {
		global $ohmylms_current_section;

		$payment_gateways = ecommerce()->gateways();

		$this->save_settings_for_current_section();

		if ( ! $ohmylms_current_section ) {
			// If section is empty, we're on the main settings page. This makes sure 'gateway ordering' is saved.
			$payment_gateways->process_admin_options();
			$payment_gateways->init();
		} else {
			// There is a section - this may be a gateway or custom section.
			foreach ( $payment_gateways->payment_gateways() as $gateway ) {
				if ( in_array( $ohmylms_current_section, array( $gateway->id, sanitize_title( get_class( $gateway ) ) ), true ) ) {
					do_action( 'ohmylms_update_options_payment_gateways_' . $gateway->id );
					$payment_gateways->init();
				}
			}
		}

		$this->do_update_options_action();
	}
}
