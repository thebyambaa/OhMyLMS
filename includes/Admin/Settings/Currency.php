<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Currency settings class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * Currency settings class.
 *
 * Handles currency settings for the OhMyLMS.
 *
 * @since 1.0.0
 */
class Currency extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'currency';

	/**
	 * Constructor.
	 *
	 * Initializes the currency settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'currency';
		$this->label = __( 'Currency', 'ohmylms' );
	}

	/**
	 * Get the currency settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {
		$settings = array(
			array(
				'id'       => 'ohmylms_currency',
				'default'  => 'USD',
				'type'     => 'select',
				'desc_tip' => true,
				'options'  => $this->get_currency_code_options(),
			),

			array(
				'id'       => 'ohmylms_currency_pos',
				'class'    => 'ohmylms-select2',
				'default'  => 'left',
				'type'     => 'select',
				'options'  => array(
					'left'        => __( 'Left', 'ohmylms' ),
					'right'       => __( 'Right', 'ohmylms' ),
					'left_space'  => __( 'Left with space', 'ohmylms' ),
					'right_space' => __( 'Right with space', 'ohmylms' ),
				),
				'desc_tip' => true,
			),

			array(
				'id'       => 'ohmylms_price_thousand_sep',
				'css'      => 'width:50px;',
				'default'  => ',',
				'type'     => 'text',
				'desc_tip' => true,
			),

			array(
				'id'      => 'ohmylms_price_decimal_sep',
				'css'     => 'width:50px;',
				'default' => '.',
				'type'    => 'text',
			),

			array(
				'id'                => 'ohmylms_price_num_decimals',
				'css'               => 'width:50px;',
				'default'           => '2',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 1,
				),
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
