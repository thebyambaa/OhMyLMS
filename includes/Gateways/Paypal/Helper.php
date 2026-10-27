<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Helper class for Paypal gateway related functions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gateways\Paypal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for Paypal gateway related functions.
 */
class Helper {
	/**
	 * Get supported currencies for PayPal.
	 *
	 * @return array
	 */
	public static function get_supported_currencies() {
		return array(
			'USD',
			'EUR',
			'GBP',
			'AUD',
			'CAD',
			'JPY',
			'NZD',
			'CHF',
			'HKD',
			'SGD',
			'SEK',
			'DKK',
			'PLN',
			'NOK',
			'HUF',
			'CZK',
			'ILS',
			'MXN',
			'BRL',
			'MYR',
			'PHP',
			'TWD',
			'THB',
			'TRY',
			'RUB',
			'INR',
			'ZAR',
			'SAR',
			'AED',
		);
	}

	/**
	 * Returns an array of currencies that do not use decimal places.
	 *
	 * @return array
	 */
	public static function no_decimal_currencies() {
		return array(
			'JPY',
			'HUF',
			'TWD',
		);
	}

	/**
	 * Format amount for PayPal (string, 2 decimals, no thousands separator).
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_amount( $amount ) {
		return number_format( (float) $amount, 2, '.', '' );
	}
}
