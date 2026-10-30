<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course archive layout and quiz design settings.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Settings;

use OhMyLMS\Design\Tokens;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The two design groups of the legacy Design tab, over their existing options:
 *
 * - `course-archive`: archive layout, columns, filters, search/sort toggles and carousel rows, read by
 *   `templates/archive-course.php`, `templates/loop/*`, `templates/filters/*`, the carousel/popup
 *   templates and the Bricks/Elementor course list defaults.
 * - `quiz-design`: the twenty quiz palette colours of {@see Tokens::QUIZ_COLORS}.
 *
 * Every submitted field is validated before the first write, nothing else is written, and a failed
 * write restores the previous options. Error codes and shapes match {@see SettingsService}.
 *
 * @since 1.0.0
 */
final class DesignSettings {

	/**
	 * Group ids.
	 */
	const GROUPS = array( 'course-archive', 'quiz-design' );

	/**
	 * Archive filters the filter templates render.
	 */
	const FILTERS = array( 'keyword', 'category', 'tag', 'difficulty_level', 'price_type' );

	/**
	 * Carousel row sources: `all` or a `get_{criteria}_course_ids()` function.
	 */
	const ROW_CRITERIA = array( 'all', 'best_selling', 'top_rated', 'top_reviewed', 'free', 'paid', 'recent' );

	/**
	 * Most carousel rows.
	 */
	const MAX_ROWS = 10;

	/**
	 * Field definitions of a group.
	 *
	 * @param string $group Group id.
	 * @return array<string, array{option:string,store:string,default:mixed,schema:array}>
	 */
	public static function fields( $group ) {
		if ( 'quiz-design' === $group ) {
			$fields = array();
			foreach ( Tokens::QUIZ_COLORS as $option => $default ) {
				$name            = substr( $option, strlen( 'ohmylms_quiz_' ), -strlen( '_color' ) );
				$fields[ $name ] = array(
					'option'  => $option,
					'store'   => 'color',
					'default' => strtolower( $default ),
					'schema'  => array(
						'type'      => 'string',
						'pattern'   => '^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$',
						'maxLength' => 7,
					),
				);
			}
			return $fields;
		}
		if ( 'course-archive' !== $group ) {
			return array();
		}
		$toggle = static function ( $option ) {
			return array(
				'option'  => $option,
				'store'   => 'yes_no',
				'default' => false,
				'schema'  => array( 'type' => 'boolean' ),
			);
		};
		return array(
			'courses_per_page' => array(
				'option'  => 'ohmylms_courses_per_page',
				'store'   => 'int',
				'default' => 10,
				'schema'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				),
			),
			'layout'           => array(
				'option'  => 'ohmylms_archive_page_layout',
				'store'   => 'enum',
				'default' => 'grid',
				'schema'  => array(
					'type' => 'string',
					'enum' => array( 'grid', 'list' ),
				),
			),
			'layout_style'     => array(
				'option'  => 'ohmylms_archive_page_layout_style',
				'store'   => 'enum',
				'default' => 'grid-style1',
				'schema'  => array(
					'type' => 'string',
					'enum' => array( 'grid-style1', 'grid-style2', 'grid-style3', 'grid-style4' ),
				),
			),
			'columns_per_row'  => array(
				'option'  => 'ohmylms_columns_per_row',
				'store'   => 'int',
				'default' => 4,
				'schema'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 6,
				),
			),
			'show_filters'     => $toggle( 'ohmylms_archive_page_filter_is_enabled' ),
			'filters'          => array(
				'option'  => 'ohmylms_archive_page_filters',
				'store'   => 'list',
				'default' => self::FILTERS,
				'schema'  => array(
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => self::FILTERS,
					),
					'maxItems'    => count( self::FILTERS ),
					'uniqueItems' => true,
				),
			),
			'show_sorting'     => $toggle( 'ohmylms_archive_page_sorting_is_enabled' ),
			'show_search'      => $toggle( 'ohmylms_archive_page_search_is_enabled' ),
			'show_category'    => $toggle( 'ohmylms_archive_page_category_is_enabled' ),
			'rows'             => array(
				'option'  => 'ohmylms_archive_page_row',
				'store'   => 'rows',
				'default' => array(
					array(
						'heading'  => 'Untitled',
						'criteria' => 'all',
					),
				),
				'schema'  => array(
					'type'     => 'array',
					'maxItems' => self::MAX_ROWS,
					'items'    => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'heading', 'criteria' ),
						'properties'           => array(
							'heading'  => array(
								'type'      => 'string',
								'minLength' => 1,
								'maxLength' => 100,
							),
							'criteria' => array(
								'type' => 'string',
								'enum' => self::ROW_CRITERIA,
							),
						),
					),
				),
			),
		);
	}

	/**
	 * JSON Schema of a group.
	 *
	 * @param string $group Group id.
	 * @return array
	 */
	public static function schema( $group ) {
		$properties = array();
		foreach ( self::fields( $group ) as $name => $field ) {
			$properties[ $name ] = $field['schema'] + array( 'default' => $field['default'] );
		}
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'settings-' . $group,
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => $properties,
		);
	}

	/**
	 * Current values (REST shape; malformed stored values read as the default).
	 *
	 * @param string $group Group id.
	 * @return array<string, mixed>
	 */
	public static function read( $group ) {
		$values = array();
		foreach ( self::fields( $group ) as $name => $field ) {
			$values[ $name ] = self::from_storage( $field, get_option( $field['option'], null ) );
		}
		return $values;
	}

	/**
	 * Convert a stored option to the REST shape.
	 *
	 * @param array $field  Field definition.
	 * @param mixed $stored Option value or null.
	 * @return mixed
	 */
	private static function from_storage( array $field, $stored ) {
		if ( null === $stored || '' === $stored ) {
			return $field['default'];
		}
		switch ( $field['store'] ) {
			case 'int':
				$number = is_numeric( $stored ) ? (int) $stored : 0;
				return $number >= $field['schema']['minimum'] && $number <= $field['schema']['maximum'] ? $number : $field['default'];
			case 'yes_no':
				return 'yes' === $stored;
			case 'enum':
				return in_array( $stored, $field['schema']['enum'], true ) ? $stored : $field['default'];
			case 'color':
				$color = is_string( $stored ) ? SettingsService::normalize_color( $stored ) : null;
				return null === $color ? $field['default'] : $color;
			case 'list':
				return is_array( $stored ) ? array_values( array_unique( array_intersect( array_map( 'strval', $stored ), self::FILTERS ) ) ) : $field['default'];
			case 'rows':
				$rows = array();
				foreach ( is_array( $stored ) ? $stored : array() as $row ) {
					if ( is_array( $row ) && isset( $row['row_heading'], $row['row_display_criteria'] ) && is_string( $row['row_heading'] ) && '' !== $row['row_heading'] && in_array( $row['row_display_criteria'], self::ROW_CRITERIA, true ) ) {
						$rows[] = array(
							'heading'  => $row['row_heading'],
							'criteria' => $row['row_display_criteria'],
						);
					}
				}
				return array_slice( $rows, 0, self::MAX_ROWS );
		}
		return $field['default'];
	}

	/**
	 * Convert a REST value to its stored form (the shape the templates read).
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
			case 'rows':
				$rows = array();
				foreach ( $value as $row ) {
					$rows[] = array(
						'row_display_criteria' => $row['criteria'],
						'row_heading'          => $row['heading'],
					);
				}
				return $rows;
			default:
				return $value;
		}
	}

	/**
	 * Validate and sanitise a partial update.
	 *
	 * @param string $group Group id.
	 * @param mixed  $body  Decoded JSON body.
	 * @return array<string, mixed>|WP_Error Clean values of the submitted fields.
	 */
	public static function validate( $group, $body ) {
		$fields = self::fields( $group );
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

		$errors = array();
		$clean  = array();
		foreach ( $body as $name => $value ) {
			$schema = $fields[ $name ]['schema'];
			$result = rest_validate_value_from_schema( $value, $schema, $name );
			if ( is_wp_error( $result ) ) {
				$errors[ $name ] = $result->get_error_message();
				continue;
			}
			$clean[ $name ] = rest_sanitize_value_from_schema( $value, $schema, $name );
		}
		if ( $errors ) {
			return new WP_Error(
				'rest_invalid_param',
				/* translators: %s: comma-separated field names. */
				sprintf( __( 'Invalid parameter(s): %s', 'ohmylms' ), implode( ', ', array_keys( $errors ) ) ),
				array(
					'status' => 400,
					'params' => $errors,
				)
			);
		}

		$problems = array();
		foreach ( $clean as $name => $value ) {
			$store = $fields[ $name ]['store'];
			if ( 'color' === $store ) {
				$clean[ $name ] = SettingsService::normalize_color( $value );
			} elseif ( 'rows' === $store ) {
				foreach ( $value as $index => $row ) {
					$heading = sanitize_text_field( $row['heading'] );
					if ( '' === $heading ) {
						/* translators: %d: row number. */
						$problems[ $name ] = sprintf( __( 'Row %d needs a heading.', 'ohmylms' ), $index + 1 );
						break;
					}
					$clean[ $name ][ $index ]['heading'] = $heading;
				}
			} elseif ( 'list' === $store ) {
				$clean[ $name ] = array_values( $value );
			}
		}
		$merged = array_merge( self::read( $group ), $clean );
		if ( 'course-archive' === $group && $merged['show_filters'] && ! $merged['filters'] && ( isset( $clean['filters'] ) || isset( $clean['show_filters'] ) ) ) {
			$problems[ isset( $clean['filters'] ) ? 'filters' : 'show_filters' ] = __( 'Choose at least one filter, or turn the filter panel off.', 'ohmylms' );
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
	 * Persist validated values; only changed options are written.
	 *
	 * @param string $group  Group id.
	 * @param array  $values Output of {@see self::validate()}.
	 * @return string[]|WP_Error Changed field names.
	 */
	public static function write( $group, array $values ) {
		$fields  = self::fields( $group );
		$current = self::read( $group );
		$changed = array();
		foreach ( $values as $name => $value ) {
			if ( $value !== $current[ $name ] ) {
				$changed[] = $name;
			}
		}
		if ( ! $changed ) {
			return array();
		}

		$snapshot = array();
		foreach ( $changed as $name ) {
			$option              = $fields[ $name ]['option'];
			$snapshot[ $option ] = get_option( $option, null );
			$stored              = self::to_storage( $fields[ $name ], $values[ $name ] );
			update_option( $option, $stored );
			if ( get_option( $option, null ) != $stored ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Options round-trip scalars as strings.
				foreach ( $snapshot as $written => $previous ) {
					if ( null === $previous ) {
						delete_option( $written );
					} else {
						update_option( $written, $previous );
					}
				}
				return new WP_Error( 'ohmylms_settings_save_failed', __( 'The settings could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		}

		/**
		 * Fires once after a design settings request changed at least one field.
		 *
		 * @param string   $group   `course-archive` or `quiz-design`.
		 * @param string[] $changed Names of the changed fields.
		 */
		do_action( 'ohmylms_design_settings_updated', $group, $changed );
		return $changed;
	}
}
