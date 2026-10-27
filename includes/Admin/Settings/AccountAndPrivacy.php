<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * General settings class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * General settings class.
 *
 * Handles general settings for the OhMyLMS.
 *
 * @since 1.0.0
 */
class AccountAndPrivacy extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'account-and-privacy';

	/**
	 * Constructor.
	 *
	 * Initializes the general settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'account-and-privacy';
		$this->label = __( 'Account and Privacy', 'ohmylms' );
	}

	/**
	 * Get the general settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {
		$settings = array(
			array(
				'id'      => 'ohmylms_privacy_policy_message',
				'type'    => 'longtext',
				'default' => 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].',
				'value'   => '',
			),
			array(
				'id'      => 'ohmylms_guest_checkout',
				'default' => 'no',
				'type'    => 'checkbox',
				'value'   => '',
			),
			array(
				'id'      => 'ohmylms_checkout_phone_field',
				'default' => 'optional',
				'type'    => 'select',
				'options' => array(
					'optional' => __( 'Optional', 'ohmylms' ),
					'required' => __( 'Required', 'ohmylms' ),
					'hidden'   => __( 'Hidden', 'ohmylms' ),
				),
				'value'   => '',
			),
			array(
				'id'      => 'ohmylms_allow_purchase_without_login',
				'default' => 'yes',
				'type'    => 'checkbox',
				'value'   => 'yes',
			),
			array(
				'id'      => 'ohmylms_require_email_verification',
				'default' => 'no',
				'type'    => 'checkbox',
				'value'   => 'yes',
			),
		);

		return $settings;
	}
	/**
	 * Get currency code options with symbols.
	 *
	 * @return array The currency code options with symbols.
	 */
	public function get_currency_code_options() {
		$currency_code_options = get_ohmylms_currencies();

		foreach ( $currency_code_options as $code => $name ) {
			$currency_code_options[ $code ] = $name . ' (' . get_ohmylms_currency_symbol( $code ) . ')';
		}

		return $currency_code_options;
	}
}
