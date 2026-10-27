<?php // phpcs:ignore WordPress.Files.FileName -- Retains the established public include path and Composer class mapping.

use OhMyLMS\Commerce\Includes\Tax\TaxService;

/**
 * Plugin oh my lms_eu_vat_api implementation.
 */
class OhMyLMS_EU_VAT_API {

	const API_URL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

	/**
	 * Check a VAT number against the supplied country code.
	 *
	 * @param string $vat_number The VAT number to check.
	 * @param string $country_code The country code.
	 *
	 * @return  EUVATAPIResponse
	 */
	public static function check_vat( $vat_number, $country_code ) {
		$result = new OhMyLMS_EU_VAT_API_Response( $vat_number, $country_code );

		if ( ! $vat_number ) {
			$result->error = OhMyLMS_EU_VAT_API_Response::NO_VAT_NUMBER;

			return $result;
		}

		if ( ! $country_code ) {

			$result->error = OhMyLMS_EU_VAT_API_Response::NO_COUNTRY_CODE;

			return $result;
		}

		// Check country is in the EU.
		if ( ! in_array( $country_code, self::get_eu_countries(), true ) ) {
			$result->error = OhMyLMS_EU_VAT_API_Response::INVALID_COUNTRY_CODE;

			return $result;
		}

		// Sanitize VAT number (remove white space, etc).
		$vat_number = str_replace( array( ' ', '.', '-' ), '', strtoupper( $vat_number ) );

		// Check prefix.
		$vat_prefix_for_country = self::get_vat_number_prefix( $country_code );
		$vat_number_prefix      = substr( $vat_number, 0, 2 );

		// If prefix is a valid VAT prefix but doesn't match selected country, return an error.
		if ( $vat_prefix_for_country !== $vat_number_prefix ) {
			$result->error = OhMyLMS_EU_VAT_API_Response::VAT_NUMBER_INVALID_FOR_COUNTRY;

			return $result;
		}

		// Strip country code if VAT number starts with it.
		if ( $vat_prefix_for_country === $vat_number_prefix ) {
			$vat_number = substr( $vat_number, 2 );
		}

		$result = self::vies_request( $result, $vat_number, $country_code, $vat_prefix_for_country );

		// Catch all for invalid VAT number if no error set.
		if ( ! $result->is_valid() && empty( $result->error ) ) {
			$result->error = OhMyLMS_EU_VAT_API_Response::INVALID_VAT_NUMBER;
		}

		return $result;
	}

	/**
	 * Makes a request to the VIES API
	 *
	 * @param OhMyLMS_EU_VAT_API_Response $result Result.
	 * @param string                      $vat_number Vat number.
	 * @param string                      $country_code Country code.
	 * @param string                      $vat_prefix_for_country Vat prefix for country.
	 *
	 * @return OhMyLMS_EU_VAT_API_Response
	 */
	private static function vies_request( $result, $vat_number, $country_code, $vat_prefix_for_country ) {
		try {

			$cache_key = md5( 'ohmylms_eu_vat_vies_' . $country_code . '_' . $vat_number . '_' . $vat_prefix_for_country );

			$response = get_transient( $cache_key );

			if ( empty( $response ) || false === $response ) {

				// Create the SOAP client for VIES API call.
				$client = new \SoapClient( self::API_URL );

				// API parameters.
				$parameters = array(
					'countryCode' => $vat_prefix_for_country,
					'vatNumber'   => $vat_number,
				);

				// Fetch response.
				$response = $client->checkVat( $parameters );

				// Save response for a day.
				set_transient( $cache_key, $response, DAY_IN_SECONDS );
			}

			$result->valid = filter_var( $response->valid, FILTER_VALIDATE_BOOLEAN );

			// 3 dashes are returned in name and address field if not found
			if ( $response->name && '---' !== $response->name ) {
				$result->name = $response->name;
			}

			if ( $response->address && '---' !== $response->address ) {
				$address = explode( "\n", $response->address );

				// Add country code to address.
				$address[] = $country_code;

				$result->address = implode( ', ', array_filter( $address ) );
			}
		} catch ( \Exception $exception ) {
			// Handle error.
			$result->error = $exception->getMessage();
		}

		// Translate common errors.
		if ( 'MS_UNAVAILABLE' === $result->error ) {
			$result->error = OhMyLMS_EU_VAT_API_Response::API_ERROR;
		} elseif ( 'INVALID_INPUT' === $result->error ) {
			$result->error = OhMyLMS_EU_VAT_API_Response::INVALID_INPUT;
		}

		return $result;
	}

	/**
	 * Return the vat number prefix.
	 *
	 * @param string $country Country.
	 *
	 * @return string
	 */
	private static function get_vat_number_prefix( $country ) {
		switch ( $country ) {
			// Greek VAT numbers begin with EL, not GR.
			case 'GR':
				$vat_prefix = 'EL';
				break;
			// makes monaco use france's tax rate.
			case 'MC':
				$vat_prefix = 'FR';
				break;
			// makes UK use Northern ireland's tax rate.
			case 'GB':
				$vat_prefix = 'XI';
				break;
			default:
				$vat_prefix = $country;
				break;
		}

		return $vat_prefix;
	}

	/**
	 * Get eu countries for Eu vat api.
	 */
	private static function get_eu_countries() {
		return TaxService::get_instance()->get_eu_countries();
	}
}
