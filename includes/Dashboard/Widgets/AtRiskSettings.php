<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Settings group `at-risk`: the thresholds of the at-risk rules and the nudge limits.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Cache;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in one option (`ohmylms_at_risk_rules`), served by `GET|PATCH /ohmylms/v1/settings/at-risk` with the
 * contract of the registry groups (docs/api/settings.md): partial and all-or-nothing writes, `400
 * ohmylms_settings_unknown_field` / `rest_invalid_param` for unknown keys and wrong types, and `422
 * ohmylms_settings_invalid_value` with one message per field in `data.params` for values out of range and
 * for a severe threshold below its rule's threshold.
 */
final class AtRiskSettings {

	const GROUP  = 'at-risk';
	const OPTION = 'ohmylms_at_risk_rules';

	/**
	 * Severe thresholds and the threshold each must not be below.
	 */
	const SEVERE_OF = array(
		'inactive_severe_days' => 'inactive_days',
		'mastery_severe_drop'  => 'mastery_drop',
		'overdue_severe_items' => 'overdue_items',
	);

	/**
	 * Field definitions: type, default and (for integers) the allowed range.
	 *
	 * @return array<string,array{type:string,default:bool|int,min?:int,max?:int}>
	 */
	public static function fields() {
		$int = static function ( $fallback, $min, $max ) {
			return array(
				'type'    => 'integer',
				'default' => $fallback,
				'min'     => $min,
				'max'     => $max,
			);
		};
		$on  = static function ( $fallback ) {
			return array(
				'type'    => 'boolean',
				'default' => $fallback,
			);
		};
		return array(
			'inactive_enabled'     => $on( true ),
			'inactive_days'        => $int( 7, 1, 365 ),
			'inactive_severe_days' => $int( 14, 1, 365 ),
			'mastery_enabled'      => $on( true ),
			'mastery_drop'         => $int( 10, 1, 100 ),
			'mastery_window_days'  => $int( 14, 1, 90 ),
			'mastery_severe_drop'  => $int( 20, 1, 100 ),
			'overdue_enabled'      => $on( true ),
			'overdue_items'        => $int( 1, 1, 100 ),
			'overdue_severe_items' => $int( 3, 1, 100 ),
			'nudge_guardians'      => $on( false ),
			'nudge_daily_cap'      => $int( 20, 1, 200 ),
		);
	}

	/**
	 * JSON Schema of the group.
	 *
	 * @return array
	 */
	public static function schema() {
		$properties = array();
		foreach ( self::fields() as $name => $field ) {
			$property = array(
				'type'    => $field['type'],
				'default' => $field['default'],
			);
			if ( 'integer' === $field['type'] ) {
				$property['minimum'] = $field['min'];
				$property['maximum'] = $field['max'];
			}
			$properties[ $name ] = $property;
		}
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'settings-' . self::GROUP,
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $properties,
		);
	}

	/**
	 * Current values; a missing or malformed stored value reads as its default.
	 *
	 * @return array<string,bool|int>
	 */
	public static function read() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array();
		foreach ( self::fields() as $name => $field ) {
			$value = $stored[ $name ] ?? null;
			if ( 'boolean' === $field['type'] ) {
				$values[ $name ] = is_bool( $value ) ? $value : $field['default'];
				continue;
			}
			$values[ $name ] = is_int( $value ) && $value >= $field['min'] && $value <= $field['max'] ? $value : $field['default'];
		}
		foreach ( self::SEVERE_OF as $severe => $base ) {
			if ( $values[ $severe ] < $values[ $base ] ) {
				$values[ $severe ] = $values[ $base ];
			}
		}
		return $values;
	}

	/**
	 * The rules in the shape {@see AtRiskRules} evaluates.
	 *
	 * @param array|null $values Values (default: the stored ones).
	 * @return array{inactive:array,mastery:array,overdue:array}
	 */
	public static function rules( $values = null ) {
		$v = null === $values ? self::read() : $values;
		return array(
			'inactive' => array(
				'enabled' => (bool) $v['inactive_enabled'],
				'min'     => (int) $v['inactive_days'],
				'severe'  => (int) $v['inactive_severe_days'],
			),
			'mastery'  => array(
				'enabled' => (bool) $v['mastery_enabled'],
				'min'     => (int) $v['mastery_drop'],
				'severe'  => (int) $v['mastery_severe_drop'],
				'days'    => (int) $v['mastery_window_days'],
			),
			'overdue'  => array(
				'enabled' => (bool) $v['overdue_enabled'],
				'min'     => (int) $v['overdue_items'],
				'severe'  => (int) $v['overdue_severe_items'],
			),
		);
	}

	/**
	 * Validate a partial update.
	 *
	 * @param mixed $body Decoded JSON body.
	 * @return array<string,bool|int>|WP_Error Clean values of the submitted fields.
	 */
	public static function validate( $body ) {
		$fields = self::fields();
		if ( ! is_array( $body ) || ( $body && array_values( $body ) === $body ) ) {
			return new WP_Error( 'ohmylms_settings_invalid_body', __( 'Send a JSON object of settings.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$unknown = array_diff_key( $body, $fields );
		if ( $unknown ) {
			return new WP_Error(
				'ohmylms_settings_unknown_field',
				__( 'Unknown setting(s) in the request.', 'ohmylms' ),
				array(
					'status' => 400,
					'params' => array_fill_keys( array_map( 'strval', array_keys( $unknown ) ), __( 'Unknown setting.', 'ohmylms' ) ),
				)
			);
		}

		$types = array();
		foreach ( $body as $name => $value ) {
			$ok = 'boolean' === $fields[ $name ]['type'] ? is_bool( $value ) : is_int( $value );
			if ( ! $ok ) {
				$types[ $name ] = 'boolean' === $fields[ $name ]['type']
					? __( 'Must be true or false.', 'ohmylms' )
					: __( 'Must be a whole number.', 'ohmylms' );
			}
		}
		if ( $types ) {
			return new WP_Error(
				'rest_invalid_param',
				/* translators: %s: comma-separated field names. */
				sprintf( __( 'Invalid parameter(s): %s', 'ohmylms' ), implode( ', ', array_keys( $types ) ) ),
				array(
					'status' => 400,
					'params' => $types,
				)
			);
		}

		$problems = array();
		foreach ( $body as $name => $value ) {
			$field = $fields[ $name ];
			if ( 'integer' === $field['type'] && ( $value < $field['min'] || $value > $field['max'] ) ) {
				/* translators: 1: smallest value, 2: largest value. */
				$problems[ $name ] = sprintf( __( 'Enter a number from %1$d to %2$d.', 'ohmylms' ), $field['min'], $field['max'] );
			}
		}
		$merged = array_merge( self::read(), $body );
		foreach ( self::SEVERE_OF as $severe => $base ) {
			if ( isset( $problems[ $severe ] ) || isset( $problems[ $base ] ) || ( ! array_key_exists( $severe, $body ) && ! array_key_exists( $base, $body ) ) ) {
				continue;
			}
			if ( $merged[ $severe ] < $merged[ $base ] ) {
				$problems[ array_key_exists( $severe, $body ) ? $severe : $base ] = __( 'The high-risk threshold cannot be below the rule’s threshold.', 'ohmylms' );
			}
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
		return $body;
	}

	/**
	 * Store validated values. Cached dashboard data is dropped when anything changed.
	 *
	 * @param array $values Output of {@see self::validate()}.
	 * @return string[]|WP_Error Changed field names.
	 */
	public static function write( array $values ) {
		$current = self::read();
		$changed = array();
		foreach ( $values as $name => $value ) {
			if ( $value !== $current[ $name ] ) {
				$changed[] = $name;
			}
		}
		if ( ! $changed ) {
			return array();
		}
		$next = array_merge( $current, $values );
		update_option( self::OPTION, $next, false );
		if ( get_option( self::OPTION ) !== $next ) {
			return new WP_Error( 'ohmylms_settings_save_failed', __( 'The settings could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		Cache::flush();

		/**
		 * Fires once after a save changed at least one at-risk setting.
		 *
		 * @param string[] $changed Names of the changed fields.
		 */
		do_action( 'ohmylms_at_risk_settings_updated', $changed );
		return $changed;
	}
}
