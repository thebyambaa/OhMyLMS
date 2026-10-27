<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Tax Service class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Includes\Tax;

use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit();

/**
 * Tax Service class
 *
 * Handles retrieval of tax settings from WordPress options
 *
 * @since 1.0.0
 */
class TaxService {

	/**
	 * Instance of this class
	 */
	private static $instance = null;

	/**
	 * Get instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Private constructor for singleton.
	}

	/**
	 * Check if tax is enabled
	 *
	 * @return bool
	 */
	public function is_tax_enabled() {
		return get_option( 'ohmylms_tax_enabled', 'no' ) === 'yes';
	}

	/**
	 * Get tax label
	 *
	 * @return string
	 */
	public function get_tax_label() {
		return get_option( 'ohmylms_tax_label', 'Tax' );
	}

	/**
	 * Check if prices include tax
	 *
	 * @return bool
	 */
	public function prices_include_tax() {
		return get_option( 'ohmylms_prices_include_tax', 'no' ) === 'yes';
	}

	/**
	 * Get tax based on setting (billing or shipping)
	 *
	 * @return string
	 */
	public function get_tax_based_on() {
		return get_option( 'ohmylms_tax_based_on', 'billing' );
	}

	/**
	 * Check if EU VAT is enabled
	 *
	 * @return bool
	 */
	public function is_eu_vat_enabled() {
		return get_option( 'ohmylms_eu_vat_enabled', 'no' ) === 'yes';
	}

	/**
	 * Check if VAT validation is disabled
	 *
	 * @return bool
	 */
	public function is_vat_validation_disabled() {
		return get_option( 'ohmylms_disable_vat_validation', 'no' ) === 'yes';
	}

	/**
	 * Get same country rule
	 *
	 * @return string
	 */
	public function get_same_country_rule() {
		return get_option( 'ohmylms_same_country_rule', 'charge_tax_unless_validated' );
	}

	/**
	 * Get VAT number label
	 *
	 * @return string
	 */
	public function get_vat_number_label() {
		return get_option( 'ohmylms_vat_number_label', 'VAT Number' );
	}

	/**
	 * Get fallback tax rate
	 *
	 * @return float
	 */
	public function get_fallback_tax_rate() {
		return floatval( get_option( 'ohmylms_fallback_tax_rate', '0.00' ) );
	}

	/**
	 * Get display prices setting for inclusive tax
	 *
	 * @return string
	 */
	public function get_display_prices_inclusive_tax() {
		return get_option( 'ohmylms_display_prices_inclusive_tax', 'including' );
	}

	/**
	 * Get existing tax rates
	 *
	 * @return array
	 */
	public function get_existing_tax_rates() {
		$rates = get_option( 'ohmylms_existing_tax_rates', array() );
		return is_array( $rates ) ? $rates : array();
	}

	/**
	 * Get new tax rates
	 *
	 * @return array
	 */
	public function get_new_tax_rates() {
		$rates = get_option( 'ohmylms_new_tax_rates', array() );
		return is_array( $rates ) ? $rates : array();
	}

	/**
	 * Get all tax rates
	 *
	 * @return array
	 */
	public function get_tax_rates() {
		$rates = get_option( 'ohmylms_tax_rates', array() );
		return is_array( $rates ) ? $rates : array();
	}

	/**
	 * Get tax rate for specific country and state
	 *
	 * @param string $country Country code.
	 * @param string $state State code.
	 * @return float Tax rate percentage
	 */
	public function get_tax_rate_for_location( $country, $state = '' ) {
		$tax_rates = $this->get_tax_rates();

		// Look for exact match (country + state).
		if ( ! empty( $state ) ) {
			foreach ( $tax_rates as $rate ) {
				if ( isset( $rate['country'] ) && isset( $rate['state'] ) &&
					$rate['country'] === $country && $rate['state'] === $state ) {
					return isset( $rate['rate'] ) ? floatval( $rate['rate'] ) : 0;
				}
			}
		}

		// Look for country match only.
		foreach ( $tax_rates as $rate ) {
			if ( isset( $rate['country'] ) && $rate['country'] === $country &&
				( empty( $rate['state'] ) || '' === $rate['state'] ) ) {
				return isset( $rate['rate'] ) ? floatval( $rate['rate'] ) : 0;
			}
		}

		// Return fallback rate if no match found.
		return $this->get_fallback_tax_rate();
	}

	/**
	 * Get all countries
	 *
	 * @return array
	 */
	public function get_countries() {
		$countries = get_option( 'ohmylms_countries', array() );
		return is_array( $countries ) ? $countries : array();
	}

	/**
	 * Get all states
	 *
	 * @return array
	 */
	public function get_states() {
		$states = get_option( 'ohmylms_states', array() );
		return is_array( $states ) ? $states : array();
	}

	/**
	 * Get states for specific country
	 *
	 * @param string $country Country code.
	 * @return array
	 */
	public function get_states_for_country( $country ) {
		$all_states = $this->get_states();
		return isset( $all_states[ $country ] ) && is_array( $all_states[ $country ] )
			? $all_states[ $country ]
			: array();
	}

	/**
	 * Get country name by code
	 *
	 * @param string $country_code Country code.
	 * @return string Country name
	 */
	public function get_country_name( $country_code ) {
		$countries = $this->get_countries();

		foreach ( $countries as $country ) {
			if ( isset( $country['value'] ) && $country['value'] === $country_code ) {
				return isset( $country['label'] ) ? $country['label'] : $country_code;
			}
		}

		return $country_code;
	}

	/**
	 * Get state name by code
	 *
	 * @param string $country_code Country code.
	 * @param string $state_code State code.
	 * @return string State name
	 */
	public function get_state_name( $country_code, $state_code ) {
		$states = $this->get_states_for_country( $country_code );

		foreach ( $states as $state ) {
			if ( isset( $state['value'] ) && $state['value'] === $state_code ) {
				return isset( $state['label'] ) ? $state['label'] : $state_code;
			}
		}

		return $state_code;
	}

	/**
	 * Check if location has tax
	 *
	 * @param string $country Country code.
	 * @param string $state State code.
	 * @return bool
	 */
	public function location_has_tax( $country, $state = '' ) {
		if ( ! $this->is_tax_enabled() ) {
			return false;
		}

		$tax_rate = $this->get_tax_rate_for_location( $country, $state );
		return $tax_rate > 0;
	}

	/**
	 * Calculate tax amount for given total
	 *
	 * @param float $total Total amount.
	 * @param float $tax_rate Tax rate percentage.
	 */
	public function calculate_tax( $total, $tax_rate ) {
		if ( $this->prices_include_tax() ) {
			// If prices include tax, extract the tax amount.
			$tax_amount = $total - ( $total / ( 1 + ( $tax_rate / 100 ) ) );
			$subtotal   = $total - $tax_amount;
		} else {
			// If prices exclude tax, add the tax amount.
			$tax_amount = ( $total * $tax_rate ) / 100;
			$subtotal   = $total;
		}
		return array(
			'subtotal'   => $subtotal,
			'tax_rate'   => $tax_rate,
			'tax_amount' => $tax_amount,
			'total'      => $subtotal + $tax_amount,
			'tax_label'  => $this->get_tax_label(),
		);
	}

	/**
	 * Get tax rate for a specific country and state
	 *
	 * This function retrieves the tax rate for a given country and state.
	 *
	 * @param string $country Country code.
	 * @param string $state State code (optional)
	 * .
	 * @since 1.0.0
	 * @return float Tax rate percentage
	 */
	public function get_country_tax_rate( $country, $state = '' ) {
		$rate = self::get_fallback_tax_rate();
		// get eu rate if found.
		if ( self::is_eu_vat_enabled() && in_array( $country, self::get_eu_countries(), true ) ) {
			$eu_rates = self::get_eu_vat_rates();
			$rate     = isset( $eu_rates[ $country ] ) ? $eu_rates[ $country ] : 0;
		}
		// get global country rate to override eu rate where possible.
		$result = wp_list_filter(
			self::get_tax_rates(),
			array(
				'country'     => $country,
				'countryWide' => '1',
			)
		);
		// if state is specified, find state specific rate and override global country rate where possible.
		if ( ! empty( $state ) ) {
			$result2 = wp_list_filter(
				self::get_tax_rates(),
				array(
					'country'     => $country,
					'state'       => $state,
					'countryWide' => '0',
				)
			);
			if ( ! empty( $result2 ) ) {
				$result = $result2;
			}
		}

		if ( ! empty( $result ) ) {
			$result = reset( $result );
			$rate   = isset( $result['rate'] ) ? $result['rate'] : 0;
		}

		if ( ! is_numeric( $rate ) ) {
			$rate = 0;
		}

		return $rate;
	}

	/**
	 * Get EU countries
	 *
	 * @return array List of EU country codes
	 * @since 1.0.0
	 */
	public function get_eu_countries() {
		return array_keys( self::get_eu_vat_rates() );
	}

	/**
	 * Get current EU VAT rates
	 *
	 * This function retrieves the current VAT rates for EU countries.
	 *
	 * @return array Associative array of country codes and their VAT rates
	 * @since 1.0.0
	 */
	private function get_eu_vat_rates() {
		return apply_filters(
			'ohmylms_vat_current_eu_vat_rates',
			array(
				'AT' => 20.0,
				'BE' => 21.0,
				'BG' => 20.0,
				'CY' => 19.0,
				'CZ' => 21.0,
				'DE' => 19.0,
				'DK' => 25.0,
				'EE' => 22.0,
				'ES' => 21.0,
				'FI' => 25.5,
				'FR' => 20.0,
				'GR' => 24.0,
				'HR' => 25.0,
				'HU' => 27.0,
				'IE' => 23.0,
				'IT' => 22.0,
				'LT' => 21.0,
				'LU' => 17.0,
				'LV' => 21.0,
				'MT' => 18.0,
				'NL' => 21.0,
				'PL' => 23.0,
				'PT' => 23.0,
				'RO' => 19.0,
				'SI' => 22.0,
				'SK' => 20.0,
				'SE' => 25.0,
			)
		);
	}

	/**
	 * Check if a country is an EU country
	 *
	 * This function checks if the given country code is part of the EU countries.
	 *
	 * @param string $country Country code to check.
	 * @return bool True if the country is an EU country, false otherwise
	 * @since 1.0.0
	 */
	public function is_eu_countries( $country ) {
		return in_array( $country, $this->get_eu_countries(), true );
	}
}
