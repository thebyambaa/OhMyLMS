<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Settings group `grade-scale`: the site-wide letter scale and the missing-work rule.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Stored in one option (`ohmylms_grade_scale`), served by `GET|PATCH /ohmylms/v1/settings/grade-scale` with
 * the contract of the registry groups (docs/api/settings.md): partial, all-or-nothing writes; `400
 * ohmylms_settings_unknown_field` / `rest_invalid_param` for unknown keys and wrong types; `422
 * ohmylms_settings_invalid_value` with one message per field for values out of range or boundaries that
 * do not descend (A > B > C > D; F is everything below D).
 */
final class ScaleSettings {

	const GROUP  = 'grade-scale';
	const OPTION = 'ohmylms_grade_scale';

	/**
	 * Boundary fields from the highest letter down, with the letter each starts.
	 */
	const BOUNDARIES = array(
		'a_min' => 'A',
		'b_min' => 'B',
		'c_min' => 'C',
		'd_min' => 'D',
	);

	/**
	 * Field definitions.
	 *
	 * @return array<string,array{type:string,default:bool|int,min?:int,max?:int}>
	 */
	public static function fields() {
		$boundary = static function ( $fallback ) {
			return array(
				'type'    => 'integer',
				'default' => $fallback,
				'min'     => 1,
				'max'     => 100,
			);
		};
		return array(
			'a_min'           => $boundary( 90 ),
			'b_min'           => $boundary( 80 ),
			'c_min'           => $boundary( 70 ),
			'd_min'           => $boundary( 60 ),
			'missing_as_zero' => array(
				'type'    => 'boolean',
				'default' => true,
			),
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
	 * Current values; a malformed stored scale reads as the defaults.
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
		if ( self::descending_problems( $values ) ) {
			foreach ( self::BOUNDARIES as $name => $letter ) {
				$values[ $name ] = self::fields()[ $name ]['default'];
			}
		}
		return $values;
	}

	/**
	 * The scale as letters with their lower bounds, highest first; F starts at 0.
	 *
	 * @param array|null $values Values (default: stored).
	 * @return array<int,array{letter:string,min:int}>
	 */
	public static function scale( $values = null ) {
		$values = null === $values ? self::read() : $values;
		$scale  = array();
		foreach ( self::BOUNDARIES as $name => $letter ) {
			$scale[] = array(
				'letter' => $letter,
				'min'    => (int) $values[ $name ],
			);
		}
		$scale[] = array(
			'letter' => 'F',
			'min'    => 0,
		);
		return $scale;
	}

	/**
	 * Whether missing work counts as zero.
	 *
	 * @return bool
	 */
	public static function missing_as_zero() {
		return (bool) self::read()['missing_as_zero'];
	}

	/**
	 * Fields whose boundary is not below the one above it.
	 *
	 * @param array $values Values.
	 * @return array<string,string>
	 */
	private static function descending_problems( array $values ) {
		$problems = array();
		$previous = null;
		foreach ( self::BOUNDARIES as $name => $letter ) {
			if ( null !== $previous && (int) $values[ $name ] >= (int) $values[ $previous ] ) {
				/* translators: 1: a letter such as B, 2: the letter above it such as A. */
				$problems[ $name ] = sprintf( __( '%1$s must start below %2$s.', 'ohmylms' ), $letter, self::BOUNDARIES[ $previous ] );
			}
			$previous = $name;
		}
		return $problems;
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
				$types[ $name ] = 'boolean' === $fields[ $name ]['type'] ? __( 'Must be true or false.', 'ohmylms' ) : __( 'Must be a whole number.', 'ohmylms' );
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
		if ( ! $problems ) {
			foreach ( self::descending_problems( array_merge( self::read(), $body ) ) as $name => $message ) {
				$problems[ $name ] = $message;
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
	 * Store validated values; raises the gradebook data version when anything changed.
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
		Version::bump();

		/**
		 * Fires once after a save changed the letter scale or the missing-work rule.
		 *
		 * @param string[] $changed Names of the changed fields.
		 */
		do_action( 'ohmylms_grade_scale_updated', $changed );
		return $changed;
	}
}
