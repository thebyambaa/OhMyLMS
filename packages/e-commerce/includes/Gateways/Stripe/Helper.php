<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Helper class for Stripe gateway related functions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Gateways\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class for Stripe gateway related functions.
 *
 * @since 1.0.0
 */
class Helper {

	/**
	 * Calculate the Stripe amount based on the total and currency.
	 *
	 * @param float  $total    The total amount.
	 * @param string $currency The currency code (default: '').
	 * @return int The amount in the smallest currency unit (e.g., cents).
	 *
	 * @since 1.0.0
	 */
	public static function get_stripe_amount( $total, $currency = '' ) {
		if ( in_array( $currency, self::no_decimal_currencies(), true ) ) {
			return absint( $total );
		} elseif ( in_array( $currency, self::three_decimal_currencies(), true ) ) {
			$price_decimals = ohmylms_get_price_decimals();
			$amount         = absint( ohmylms_format_decimal( ( (float) $total * 1000 ), $price_decimals ) ); // For tree decimal currencies.
			return $amount - ( $amount % 10 ); // Round the last digit down. See https://docs.stripe.com/currencies?presentment-currency=AE#three-decimal.
		} else {
			return absint( ohmylms_format_decimal( ( (float) $total * 100 ), ohmylms_get_price_decimals() ) ); // In cents.
		}
	}


	/**
	 * Get the Stripe currency based on the current currency.
	 *
	 * @return string The Stripe currency code.
	 *
	 * @since 1.0.0
	 */
	public static function get_stripe_currency() {
		$stripe_supported_currencies = array(
			'USD',
			'AED',
			'AFN',
			'ALL',
			'AMD',
			'ANG',
			'AOA',
			'ARS',
			'AUD',
			'AWG',
			'AZN',
			'BAM',
			'BBD',
			'BDT',
			'BGN',
			'BIF',
			'BMD',
			'BND',
			'BOB',
			'BRL',
			'BSD',
			'BWP',
			'BYN',
			'BZD',
			'CAD',
			'CDF',
			'CHF',
			'CLP',
			'CNY',
			'COP',
			'CRC',
			'CVE',
			'CZK',
			'DJF',
			'DKK',
			'DOP',
			'DZD',
			'EGP',
			'ETB',
			'EUR',
			'FJD',
			'FKP',
			'GBP',
			'GEL',
			'GIP',
			'GMD',
			'GNF',
			'GTQ',
			'GYD',
			'HKD',
			'HNL',
			'HTG',
			'HUF',
			'IDR',
			'ILS',
			'INR',
			'ISK',
			'JMD',
			'JPY',
			'KES',
			'KGS',
			'KHR',
			'KMF',
			'KRW',
			'KYD',
			'KZT',
			'LAK',
			'LBP',
			'LKR',
			'LRD',
			'LSL',
			'MAD',
			'MDL',
			'MGA',
			'MKD',
			'MMK',
			'MNT',
			'MOP',
			'MUR',
			'MVR',
			'MWK',
			'MXN',
			'MYR',
			'MZN',
			'NAD',
			'NGN',
			'NIO',
			'NOK',
			'NPR',
			'NZD',
			'PAB',
			'PEN',
			'PGK',
			'PHP',
			'PKR',
			'PLN',
			'PYG',
			'QAR',
			'RON',
			'RSD',
			'RUB',
			'RWF',
			'SAR',
			'SBD',
			'SCR',
			'SEK',
			'SGD',
			'SHP',
			'SLE',
			'SOS',
			'SRD',
			'STD',
			'SZL',
			'THB',
			'TJS',
			'TOP',
			'TRY',
			'TTD',
			'TWD',
			'TZS',
			'UAH',
			'UGX',
			'UYU',
			'UZS',
			'VND',
			'VUV',
			'WST',
			'XAF',
			'XCD',
			'XCG',
			'XOF',
			'XPF',
			'YER',
			'ZAR',
			'ZMW',
		);
		return $stripe_supported_currencies;
	}

	/**
	 * Returns an array of currencies that do not use decimal places.
	 *
	 * @see https://docs.stripe.com/currencies#zero-decimal
	 * @return array List of no-decimal currencies.
	 *
	 * @since 1.0.0
	 */
	public static function no_decimal_currencies() {
		return array(
			'bif', // Burundian Franc.
			'clp', // Chilean Peso.
			'djf', // Djiboutian Franc.
			'gnf', // Guinean Franc.
			'jpy', // Japanese Yen.
			'kmf', // Comorian Franc.
			'krw', // South Korean Won.
			'mga', // Malagasy Ariary.
			'pyg', // Paraguayan Guaraní.
			'rwf', // Rwandan Franc.
			'vnd', // Vietnamese Đồng.
			'vuv', // Vanuatu Vatu.
			'xaf', // Central African Cfa Franc.
			'xof', // West African Cfa Franc.
			'xpf', // Cfp Franc.
		);
	}

	/**
	 * Returns an array of currencies that use three decimal places.
	 *
	 * @see https://docs.stripe.com/currencies#three-decimal
	 * @return array List of three-decimal currencies.
	 *
	 * @since 1.0.0
	 */
	private static function three_decimal_currencies() {
		return array(
			'bhd', // Bahraini Dinar.
			'jod', // Jordanian Dinar.
			'kwd', // Kuwaiti Dinar.
			'omr', // Omani Rial.
			'tnd', // Tunisian Dinar.
		);
	}

	/**
	 * Get the URL for a Stripe transaction in the dashboard.
	 *
	 * @param bool $is_test_mode Whether to return the test mode URL.
	 * @return string The URL for the Stripe transaction.
	 *
	 * @since 1.0.0
	 */
	public static function get_transaction_url( $is_test_mode = false ) {
		if ( 'yes' === $is_test_mode ) {
			return 'https://dashboard.stripe.com/test/payments/%s';
		}
		return 'https://dashboard.stripe.com/payments/%s';
	}

	/**
	 * Format the balance transaction fee or net amount.
	 *
	 * @param object $balance_transaction The balance transaction object.
	 * @param string $type The type of amount to format ('fee' or 'net').
	 *
	 * @return string|null The formatted amount or null if the input is not valid.
	 * @since 1.0.0
	 */
	public static function format_balance_fee( $balance_transaction, $type = 'fee' ) {
		if ( ! is_object( $balance_transaction ) ) {
			return;
		}

		if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies() ) ) {
			if ( 'fee' === $type ) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ( 'fee' === $type ) {
			return number_format( $balance_transaction->fee / 100, 2, '.', '' );
		}

		return number_format( $balance_transaction->net / 100, 2, '.', '' );
	}
}
