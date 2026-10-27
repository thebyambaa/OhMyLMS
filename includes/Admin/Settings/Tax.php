<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Tax settings class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * Tax settings class.
 *
 * Handles tax settings for the OhMyLMS.
 *
 * @since 1.0.0
 */
class Tax extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'tax';

	/**
	 * Constructor.
	 *
	 * Initializes the tax settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'tax';
		$this->label = __( 'Tax', 'ohmylms' );
	}

	/**
	 * Get the tax settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {
		$settings = array(
			// Main tax settings.
			array(
				'id'       => 'ohmylms_tax_enabled',
				'default'  => 'no',
				'type'     => 'checkbox',
				'desc_tip' => true,
			),

			array(
				'id'       => 'ohmylms_tax_label',
				'default'  => 'Tax',
				'type'     => 'text',
				'desc_tip' => true,
			),

			array(
				'id'       => 'ohmylms_prices_include_tax',
				'default'  => 'no',
				'type'     => 'select',
				'options'  => array(
					'yes' => __( 'Yes, I will enter prices inclusive of tax', 'ohmylms' ),
					'no'  => __( 'No, I will enter prices exclusive of tax', 'ohmylms' ),
				),
				'desc_tip' => true,
			),

			// EU VAT settings.
			array(
				'id'       => 'ohmylms_eu_vat_enabled',
				'default'  => 'no',
				'type'     => 'checkbox',
				'desc_tip' => true,
			),

			array(
				'id'       => 'ohmylms_disable_vat_validation',
				'default'  => 'no',
				'type'     => 'checkbox',
				'desc_tip' => true,
			),

			array(
				'id'       => 'ohmylms_vat_number_label',
				'default'  => 'VAT Number',
				'type'     => 'text',
				'desc_tip' => true,
			),

			// Tax rates and fallback.
			array(
				'id'       => 'ohmylms_existing_tax_rates',
				'default'  => array(),
				'type'     => 'array',
				'desc_tip' => true,
			),

			array(
				'id'       => 'ohmylms_new_tax_rates',
				'default'  => array(),
				'type'     => 'array',
				'desc_tip' => true,
			),

			array(
				'id'       => 'ohmylms_tax_rates',
				'default'  => array(),
				'type'     => 'array',
				'desc_tip' => true,
			),

			array(
				'id'                => 'ohmylms_fallback_tax_rate',
				'default'           => '0.00',
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 100,
					'step' => 0.01,
				),
				'desc_tip'          => true,
			),

			// Countries and States data.
			array(
				'id'       => 'ohmylms_countries',
				'default'  => $this->get_countries(),
				'type'     => 'array',
				'desc_tip' => false,
			),

			array(
				'id'       => 'ohmylms_states',
				'default'  => $this->get_states(),
				'type'     => 'array',
				'desc_tip' => false,
			),
		);

		return $settings;
	}

	/**
	 * Get the list of countries.
	 *
	 * @return array The countries array.
	 *
	 * @since 1.0.0
	 */
	private function get_countries() {
		$countries_file = __DIR__ . '/Country.php';
		if ( file_exists( $countries_file ) ) {
			$countries = include $countries_file;
			// Convert to format expected by frontend.
			$formatted_countries = array();
			foreach ( $countries as $code => $name ) {
				$formatted_countries[] = array(
					'value' => $code,
					'label' => $name,
				);
			}
			return $formatted_countries;
		}
		return array();
	}

	/**
	 * Get the list of states for all countries.
	 *
	 * @return array The states array.
	 *
	 * @since 1.0.0
	 */
	private function get_states() {
		$states_file = __DIR__ . '/State.php';
		if ( file_exists( $states_file ) ) {
			$states = include $states_file;
			// Convert to format expected by frontend.
			$formatted_states = array();
			foreach ( $states as $country_code => $country_states ) {
				$formatted_states[ $country_code ] = array();
				foreach ( $country_states as $state_code => $state_name ) {
					$formatted_states[ $country_code ][] = array(
						'value' => $state_code,
						'label' => $state_name,
					);
				}
			}
			return $formatted_states;
		}
		return array();
	}
}
