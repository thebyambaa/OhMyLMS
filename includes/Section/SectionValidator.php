<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to validate section rest requests.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Section;

use WP_Error;

/**
 * Responsible to validate section rest requests
 *
 * @since 1.0.0
 */
class SectionValidator {

	/**
	 * Validate add section params
	 *
	 * @return array[]
	 * @since 1.0.0
	 */
	public static function validate_add_section_params(): array {
		return array(
			'title'       => array(
				'required'          => true,
				'validate_callback' => function ( $param, $_request, $_key ) {
					if ( ! is_string( $param ) ) {
						return new WP_Error(
							'rest_invalid_param',
							__( 'Title must ba a string.', 'ohmylms' ),
							array( 'status' => 400 )
						);
					}
					if ( strlen( $param ) > 100 ) {
						return new WP_Error(
							'rest_invalid_param',
							__( 'Title must not be greater than 100 characters.', 'ohmylms' ),
							array( 'status' => 400 )
						);
					}
					return true;
				},
			),
			'description' => array(
				'required'          => true,
				'validate_callback' => function ( $param, $_request, $_key ) {
					if ( ! is_string( $param ) ) {
						return new WP_Error(
							'rest_invalid_param',
							__( 'Description must be a string.', 'ohmylms' ),
							array( 'status' => 400 )
						);
					}
					if ( strlen( $param ) > 250 ) {
						return new WP_Error(
							'rest_invalid_param',
							__( 'Description must not be greater than 250 characters.', 'ohmylms' ),
							array( 'status' => 400 )
						);
					}
					return true;
				},
			),
		);
	}
}
