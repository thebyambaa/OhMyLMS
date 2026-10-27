<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to validate course rest requests.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Course;

use WP_Error;

/**
 * Responsible to validate course rest requests
 *
 * @since 1.0.0
 */
class CourseValidator {

	/**
	 * Validates add course params
	 *
	 * @return array[]
	 * @since 1.0.0
	 */
	public static function validate_add_course_params(): array {
		return array(
			'title'       => array(
				'required'          => true,
				'validate_callback' => function ( $param, $request, $key ) {
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
				'validate_callback' => function ( $param, $request, $key ) {
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

	/**
	 * Validate course general settings fields
	 *
	 * @param mixed $general_settings_items General settings items.
	 * @return array
	 * @since 1.0.0
	 */
	public static function validate_course_general_settings_fields( $general_settings_items ): array {
		foreach ( $general_settings_items as $key => $general_settings_item ) {
			switch ( $key ) {
				case 'course_duration':
					$general_settings_items[ $key ] = sanitize_text_field( $general_settings_item );
					break;

				case 'course_price':
					$general_settings_items[ $key ] = sanitize_text_field( $general_settings_item );
					if ( ! empty( $general_settings_item ) && ! is_numeric( $general_settings_item ) ) {
						return array(
							'status'  => 'error',
							'message' => __( 'Price must be a number.', 'ohmylms' ),
						);
					}
					break;

				case 'course_sale_price':
					$general_settings_items[ $key ] = sanitize_text_field( $general_settings_item );
					if ( ! empty( $general_settings_item ) && ! is_numeric( $general_settings_item ) ) {
						return array(
							'status'  => 'error',
							'message' => __( 'Sale Price must be a number.', 'ohmylms' ),
						);
					}
					break;

				case 'course_max_student_allowed':
					$general_settings_items[ $key ] = sanitize_text_field( $general_settings_item );
					if ( ! empty( $general_settings_item ) && ! is_numeric( $general_settings_item ) ) {
						return array(
							'status'  => 'error',
							'message' => __( 'Max student allowed field must be a number.', 'ohmylms' ),
						);
					}
					break;

				case 'course_max_retake_allowed':
					$general_settings_items[ $key ] = sanitize_text_field( $general_settings_item );
					if ( ! empty( $general_settings_item ) && ! is_numeric( $general_settings_item ) ) {
						return array(
							'status'  => 'error',
							'message' => __( 'Max retake allowed field must be a number.', 'ohmylms' ),
						);
					}
					break;

				case 'course_passing_grade':
					$general_settings_items[ $key ] = sanitize_text_field( $general_settings_item );
					if ( ! empty( $general_settings_item ) && ! is_numeric( $general_settings_item ) ) {
						return array(
							'status'  => 'error',
							'message' => __( 'Passing grade must be a number.', 'ohmylms' ),
						);
					}
					break;
			}
		}

		return $general_settings_items;
	}
}
