<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Payment gateway settings class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * Payment gateway settings class.
 *
 * Handles Payment gateway settings for the OhMyLMS.
 *
 * @since 1.0.0
 */
class PaymentGateway extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'payment-gateway';

	/**
	 * Constructor.
	 *
	 * Initializes the Payment gateway settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'payment-gateway';
		$this->label = __( 'Payment Gateway', 'ohmylms' );
	}

	/**
	 * Get the Payment gateway settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {
		$gateways = \OhMyLMS\Commerce\ecommerce()->gateways()->get_payment_gateway_settings();

		foreach ( $gateways as $gateway ) {
			$gateway_id   = $gateway['id'];
			$settings_key = "ohmylms_{$gateway_id}_settings";

			$gateway_settings = $gateway['settings_fields'] ?? array();
			$default_settings = array(
				'enabled' => 'no',
			);

			foreach ( $gateway_settings as $field ) {
				$option_name   = $field['option_name'] ?? '';
				$default_value = $field['default_value'] ?? '';

				if ( ! empty( $option_name ) ) {
					$default_settings[ $option_name ] = $default_value;
				}
			}

			$settings[] = array(
				'id'                   => $settings_key,
				'subscription_support' => isset( $gateway['subscription_support'] ) ? $gateway['subscription_support'] : false,
				'type'                 => 'object',
				'default'              => $default_settings,
				'value'                => '',
			);
		}
		return $settings;
	}
}
