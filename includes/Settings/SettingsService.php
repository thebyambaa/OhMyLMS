<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Reads, validates and writes settings groups.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Settings;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Settings storage layer used by the REST controller.
 *
 * Values are validated and sanitised against {@see SettingsRegistry}, persisted to the options the
 * rest of the plugin already reads, and never partially written: every field is checked before the
 * first write, and a failed write restores the previous values.
 *
 * @since 1.0.0
 */
final class SettingsService {

	/**
	 * First URL segments WordPress or the web server already use.
	 */
	const RESERVED_SLUGS = array( 'wp-admin', 'wp-json', 'wp-content', 'wp-includes', 'wp-login', 'feed', 'embed', 'trackback', 'comments', 'search', 'author', 'page', 'attachment', 'admin' );

	/**
	 * Read the current values of a group (REST shape, defaults applied).
	 *
	 * @param string $group Group id.
	 * @return array<string, mixed>
	 */
	public static function read( $group ) {
		$definition = SettingsRegistry::group( $group );
		$values     = array();
		if ( ! $definition ) {
			return $values;
		}
		foreach ( $definition['fields'] as $name => $field ) {
			$values[ $name ] = self::read_field( $name, $field );
		}

		return $values;
	}

	/**
	 * Read one field.
	 *
	 * @param string $name  Field name.
	 * @param array  $field Field definition.
	 * @return mixed
	 */
	private static function read_field( $name, array $field ) {
		$option = SettingsRegistry::option_name( $name, $field );
		$stored = get_option( $option, null );
		if ( isset( $field['member'] ) ) {
			$stored = is_array( $stored ) && isset( $stored[ $field['member'] ] ) ? $stored[ $field['member'] ] : null;
		}
		if ( null === $stored || ( '' === $stored && 'plain' !== $field['store'] ) ) {
			return $field['default'];
		}

		switch ( $field['store'] ) {
			case 'int':
				return absint( $stored );
			case 'yes_no':
				return 'yes' === $stored;
			case 'decimal':
				return is_numeric( $stored ) ? round( (float) $stored, 2 ) : $field['default'];
			case 'tax_rates':
				return self::rates_from_storage( $stored );
			default:
				if ( ! is_string( $stored ) ) {
					return $field['default'];
				}
				if ( 'color' === $field['kind'] ) {
					$color = self::normalize_color( $stored );
					return null === $color ? $field['default'] : $color;
				}
				return $stored;
		}
	}

	/**
	 * Convert stored tax rows to the REST shape.
	 *
	 * @param mixed $stored Option value.
	 * @return array
	 */
	private static function rates_from_storage( $stored ) {
		$rates = array();
		if ( ! is_array( $stored ) ) {
			return $rates;
		}
		foreach ( $stored as $row ) {
			if ( ! is_array( $row ) || empty( $row['country'] ) || ! isset( $row['rate'] ) || ! is_numeric( $row['rate'] ) ) {
				continue;
			}
			$rates[] = array(
				'country'      => (string) $row['country'],
				'state'        => isset( $row['state'] ) ? (string) $row['state'] : '',
				'rate'         => (float) $row['rate'],
				'country_wide' => ! isset( $row['countryWide'] ) || '0' !== (string) $row['countryWide'],
			);
		}

		return $rates;
	}

	/**
	 * Convert REST tax rows to the stored shape read by TaxService.
	 *
	 * @param array $rates REST rows.
	 * @return array
	 */
	private static function rates_to_storage( array $rates ) {
		$rows = array();
		foreach ( $rates as $rate ) {
			$rows[] = array(
				'country'     => $rate['country'],
				'state'       => $rate['state'],
				'rate'        => $rate['rate'],
				'countryWide' => $rate['country_wide'] ? '1' : '0',
			);
		}

		return $rows;
	}

	/**
	 * Normalise a #rgb or #rrggbb colour to lower-case #rrggbb.
	 *
	 * @param string $value Colour.
	 * @return string|null
	 */
	public static function normalize_color( $value ) {
		$value = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $short ) ) {
			return '#' . $short[1][0] . $short[1][0] . $short[1][1] . $short[1][1] . $short[1][2] . $short[1][2];
		}

		return preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : null;
	}

	/**
	 * Build a 400 error in the shape WordPress uses for invalid parameters.
	 *
	 * @param array $messages Field name => message.
	 * @return WP_Error
	 */
	private static function invalid_params( array $messages ) {
		return new WP_Error(
			'rest_invalid_param',
			/* translators: %s: comma-separated field names. */
			sprintf( __( 'Invalid parameter(s): %s', 'ohmylms' ), implode( ', ', array_keys( $messages ) ) ),
			array(
				'status' => 400,
				'params' => $messages,
			)
		);
	}

	/**
	 * Validate and sanitise a partial update.
	 *
	 * @param string $group Group id.
	 * @param mixed  $body  Decoded JSON body.
	 * @return array<string, mixed>|WP_Error Sanitised values for the submitted fields only.
	 */
	public static function validate( $group, $body ) {
		$definition = SettingsRegistry::group( $group );
		if ( ! $definition ) {
			return new WP_Error( 'ohmylms_settings_unknown_group', __( 'Unknown settings group.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! is_array( $body ) || ( $body && array_values( $body ) === $body ) ) {
			return new WP_Error( 'ohmylms_settings_invalid_body', __( 'Send a JSON object of settings.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$unknown = array();
		foreach ( array_keys( $body ) as $key ) {
			if ( ! isset( $definition['fields'][ $key ] ) ) {
				$unknown[ (string) $key ] = __( 'Unknown setting.', 'ohmylms' );
			}
		}
		if ( $unknown ) {
			return new WP_Error(
				'ohmylms_settings_unknown_field',
				__( 'Unknown setting(s) in the request.', 'ohmylms' ),
				array(
					'status' => 400,
					'params' => $unknown,
				)
			);
		}

		$schema_errors = array();
		$clean         = array();
		foreach ( $body as $name => $value ) {
			$field  = $definition['fields'][ $name ];
			$result = rest_validate_value_from_schema( $value, $field['schema'], $name );
			if ( is_wp_error( $result ) ) {
				$schema_errors[ $name ] = $result->get_error_message();
				continue;
			}
			$clean[ $name ] = rest_sanitize_value_from_schema( $value, $field['schema'], $name );
		}
		if ( $schema_errors ) {
			return self::invalid_params( $schema_errors );
		}

		$problems = array();
		foreach ( $clean as $name => $value ) {
			$error = self::sanitize_kind( $definition['fields'][ $name ], $value );
			if ( is_wp_error( $error ) ) {
				$problems[ $name ] = $error->get_error_message();
			} else {
				$clean[ $name ] = $error;
			}
		}
		if ( ! $problems ) {
			$problems = self::check_rules( $group, array_merge( self::read( $group ), $clean ), array_keys( $clean ) );
		}
		if ( $problems ) {
			return new WP_Error(
				'ohmylms_settings_invalid_value',
				__( 'Some settings are not valid.', 'ohmylms' ),
				array(
					'status' => 422,
					'params' => $problems,
				)
			);
		}

		return $clean;
	}

	/**
	 * Apply a field's semantic sanitiser.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Schema-valid value.
	 * @return mixed|WP_Error
	 */
	private static function sanitize_kind( array $field, $value ) {
		$kind = isset( $field['kind'] ) ? $field['kind'] : '';

		switch ( $kind ) {
			case 'page':
				if ( 0 === $value ) {
					return 0;
				}
				$page = get_post( $value );
				if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
					return new WP_Error( 'ohmylms_settings_invalid_page', __( 'Choose an existing published page.', 'ohmylms' ) );
				}
				return (int) $value;
			case 'color':
				$color = self::normalize_color( $value );
				return null === $color ? new WP_Error( 'ohmylms_settings_invalid_color', __( 'Enter a hex colour such as #6e42d3.', 'ohmylms' ) ) : $color;
			case 'url':
				if ( '' === $value ) {
					return '';
				}
				$url = esc_url_raw( $value, array( 'http', 'https' ) );
				return '' === $url || $url !== $value ? new WP_Error( 'ohmylms_settings_invalid_url', __( 'Enter a full http or https URL.', 'ohmylms' ) ) : $url;
			case 'html':
				return wp_kses_post( trim( $value ) );
			case 'currency':
				return array_key_exists( $value, get_ohmylms_currencies() ) ? $value : new WP_Error( 'ohmylms_settings_invalid_currency', __( 'Choose a supported currency.', 'ohmylms' ) );
			case 'text':
				if ( isset( $field['schema']['enum'] ) || isset( $field['schema']['pattern'] ) ) {
					return $value;
				}
				$text = sanitize_text_field( $value );
				return '' === $text ? new WP_Error( 'ohmylms_settings_empty', __( 'This field cannot be empty.', 'ohmylms' ) ) : $text;
			default:
				break;
		}

		if ( 'tax_rates' === $field['store'] ) {
			return self::sanitize_rates( $value );
		}

		return $value;
	}

	/**
	 * Check tax rate rows against the country and state lists.
	 *
	 * @param array $rates Schema-valid rows.
	 * @return array|WP_Error Normalised rows.
	 */
	private static function sanitize_rates( array $rates ) {
		$countries = self::countries();
		$states    = self::states();
		$seen      = array();
		$rows      = array();
		foreach ( $rates as $index => $rate ) {
			$position     = $index + 1;
			$country      = $rate['country'];
			$state        = isset( $rate['state'] ) ? $rate['state'] : '';
			$country_wide = ! isset( $rate['country_wide'] ) || $rate['country_wide'];
			if ( ! isset( $countries[ $country ] ) ) {
				/* translators: %d: row number. */
				return new WP_Error( 'ohmylms_settings_invalid_country', sprintf( __( 'Row %d: choose a known country.', 'ohmylms' ), $position ) );
			}
			if ( $country_wide && '' !== $state ) {
				/* translators: %d: row number. */
				return new WP_Error( 'ohmylms_settings_invalid_state', sprintf( __( 'Row %d: a country-wide rate has no state.', 'ohmylms' ), $position ) );
			}
			if ( ! $country_wide && ( '' === $state || ! isset( $states[ $country ][ $state ] ) ) ) {
				/* translators: %d: row number. */
				return new WP_Error( 'ohmylms_settings_invalid_state', sprintf( __( 'Row %d: choose a state of the selected country.', 'ohmylms' ), $position ) );
			}
			$key = $country . '|' . $state;
			if ( isset( $seen[ $key ] ) ) {
				/* translators: %d: row number. */
				return new WP_Error( 'ohmylms_settings_duplicate_rate', sprintf( __( 'Row %d repeats a country and state already listed.', 'ohmylms' ), $position ) );
			}
			$seen[ $key ] = true;
			$rows[]       = array(
				'country'      => $country,
				'state'        => $state,
				'rate'         => round( (float) $rate['rate'], 4 ),
				'country_wide' => $country_wide,
			);
		}

		return $rows;
	}

	/**
	 * Cross-field rules, evaluated on the merged result.
	 *
	 * @param string   $group     Group id.
	 * @param array    $merged    Current values with the submission applied.
	 * @param string[] $submitted Submitted field names.
	 * @return array<string, string> Field => message.
	 */
	private static function check_rules( $group, array $merged, array $submitted ) {
		$problems = array();

		if ( 'general' === $group ) {
			$exclusive = array( 'course_page_id', 'checkout_page_id', 'thank_you_page_id' );
			foreach ( $submitted as $name ) {
				if ( ! in_array( $name, $exclusive, true ) || ! $merged[ $name ] ) {
					continue;
				}
				foreach ( $exclusive as $other ) {
					if ( $other !== $name && $merged[ $other ] === $merged[ $name ] ) {
						$problems[ $name ] = __( 'The course archive, checkout and thank you pages must be different pages.', 'ohmylms' );
					}
				}
			}
		}

		if ( 'permalink' === $group ) {
			$counts = array_count_values( array_values( $merged ) );
			foreach ( $submitted as $name ) {
				$first = explode( '/', $merged[ $name ] )[0];
				if ( in_array( $first, self::RESERVED_SLUGS, true ) ) {
					$problems[ $name ] = __( 'This URL base is reserved by WordPress.', 'ohmylms' );
				} elseif ( $counts[ $merged[ $name ] ] > 1 ) {
					$problems[ $name ] = __( 'Each URL base must be different.', 'ohmylms' );
				}
			}
		}

		if ( 'currency' === $group && '' !== $merged['price_thousand_sep'] && $merged['price_thousand_sep'] === $merged['price_decimal_sep'] ) {
			$name              = in_array( 'price_decimal_sep', $submitted, true ) ? 'price_decimal_sep' : 'price_thousand_sep';
			$problems[ $name ] = __( 'The thousands and decimal separators must be different.', 'ohmylms' );
		}

		return $problems;
	}

	/**
	 * Persist validated values.
	 *
	 * Fires `ohmylms_{group}_settings_updated( $option, $stored_value, $group )` for each changed
	 * field and `ohmylms_settings_updated( $group, $changed_fields )` once. Permalink changes mark
	 * rewrite rules stale (see {@see self::mark_rewrite_rules_stale()}).
	 *
	 * @param string $group  Group id.
	 * @param array  $values Output of {@see self::validate()}.
	 * @return string[]|WP_Error Names of the fields that changed.
	 */
	public static function write( $group, array $values ) {
		$definition = SettingsRegistry::group( $group );
		$current    = self::read( $group );
		$changed    = array();
		foreach ( $values as $name => $value ) {
			if ( $value !== $current[ $name ] && ! ( is_float( $value ) && is_numeric( $current[ $name ] ) && abs( $value - $current[ $name ] ) < 0.00001 ) ) {
				$changed[] = $name;
			}
		}
		if ( ! $changed ) {
			return array();
		}

		// Group the changes by option so an array option is written once.
		$snapshot = array();
		$planned  = array();
		foreach ( $changed as $name ) {
			$field  = $definition['fields'][ $name ];
			$option = SettingsRegistry::option_name( $name, $field );
			if ( ! array_key_exists( $option, $snapshot ) ) {
				$snapshot[ $option ] = get_option( $option, null );
				$planned[ $option ]  = isset( $field['member'] ) && is_array( $snapshot[ $option ] ) ? $snapshot[ $option ] : ( isset( $field['member'] ) ? array() : null );
			}
			$stored = self::to_storage( $field, $values[ $name ] );
			if ( isset( $field['member'] ) ) {
				$planned[ $option ][ $field['member'] ] = $stored;
			} else {
				$planned[ $option ] = $stored;
			}
		}

		$written = array();
		foreach ( $planned as $option => $stored ) {
			update_option( $option, $stored );
			if ( get_option( $option, null ) != $stored ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Options round-trip scalars as strings.
				self::rollback( $snapshot, $written );
				return new WP_Error( 'ohmylms_settings_save_failed', __( 'The settings could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
			}
			$written[] = $option;
		}

		if ( 'permalink' === $group ) {
			self::mark_rewrite_rules_stale();
		}

		foreach ( $changed as $name ) {
			$field  = $definition['fields'][ $name ];
			$option = SettingsRegistry::option_name( $name, $field );
			/**
			 * Fires after one setting changed through the settings API.
			 *
			 * @param string $option Option name.
			 * @param mixed  $value  Stored value.
			 * @param string $group  Settings group id.
			 */
			do_action( "ohmylms_{$group}_settings_updated", $option, get_option( $option ), $group );
		}
		/**
		 * Fires once after a settings request changed at least one field.
		 *
		 * @param string   $group   Settings group id.
		 * @param string[] $changed Names of the changed fields (never values).
		 */
		do_action( 'ohmylms_settings_updated', $group, $changed );

		return $changed;
	}

	/**
	 * Convert a REST value to its stored form.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value REST value.
	 * @return mixed
	 */
	private static function to_storage( array $field, $value ) {
		switch ( $field['store'] ) {
			case 'yes_no':
				return $value ? 'yes' : 'no';
			case 'int':
				return (int) $value;
			case 'decimal':
				return number_format( (float) $value, 2, '.', '' );
			case 'tax_rates':
				return self::rates_to_storage( $value );
			default:
				return $value;
		}
	}

	/**
	 * Restore options after a failed write.
	 *
	 * @param array    $snapshot Option => previous value (null when missing).
	 * @param string[] $written  Options already written.
	 * @return void
	 */
	private static function rollback( array $snapshot, array $written ) {
		foreach ( $written as $option ) {
			if ( null === $snapshot[ $option ] ) {
				delete_option( $option );
			} else {
				update_option( $option, $snapshot[ $option ] );
			}
		}
	}

	/**
	 * Mark rewrite rules stale so WordPress rebuilds them once on the next request.
	 *
	 * Deleting the `rewrite_rules` option is core's supported deferred flush: the next request that
	 * needs rules regenerates them (including this plugin's `generate_rewrite_rules` rules) after all
	 * post types are registered. Nothing is flushed during the save request itself.
	 *
	 * @return void
	 */
	public static function mark_rewrite_rules_stale() {
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Country code => name.
	 *
	 * @return array<string, string>
	 */
	public static function countries() {
		$countries = include __DIR__ . '/../Admin/Settings/Country.php';

		return is_array( $countries ) ? $countries : array();
	}

	/**
	 * Country code => ( state code => name ).
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function states() {
		$states = include __DIR__ . '/../Admin/Settings/State.php';

		return is_array( $states ) ? $states : array();
	}
}
