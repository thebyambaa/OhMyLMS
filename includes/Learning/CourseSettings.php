<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Bounded metadata editing using the established course data store.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Data\Course;

defined( 'ABSPATH' ) || exit;

/** Keep publication separate from routine settings saves. */
final class CourseSettings {
	/** Editable metadata fields. */
	const FIELDS = array( 'title', 'description', 'author_id', 'image_id', 'status', 'price_type', 'regular_price', 'sale_price', 'access_type', 'has_capacity', 'capacity' );

	/**
	 * Summarize the saved learner program before explicit publication.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function preview( $course_id ) {
		$settings = self::get( $course_id );
		$program  = CourseProgram::editor( $course_id )['draft'];
		$units    = CourseStructure::get( $course_id )['units'];
		foreach ( $units as &$unit ) {
			$unit['items'] = array_map(
				static function ( $item ) {
					$term = 'practice' === $item['type'] ? get_term( $item['content_id'], \OhMyLMS\Skills\Taxonomy::NAME ) : null;
					return array(
						'id'    => $item['id'],
						'type'  => $item['type'],
						'title' => $term && ! is_wp_error( $term ) ? $term->name : (string) get_the_title( $item['content_id'] ),
					);
				},
				$unit['items']
			);
		}
		unset( $unit );
		return array(
			'course_id'            => $course_id,
			'revision'             => $settings['revision'],
			'title'                => $settings['title'],
			'status'               => $settings['status'],
			'mode'                 => $program['mode'],
			'outcomes'             => count( $program['outcomes'] ),
			'units'                => array_values( $units ),
			'readiness'            => CourseProgram::readiness( $program, $course_id ),
			'published_program_id' => (int) get_post_meta( $course_id, CourseProgram::CURRENT, true ),
		);
	}

	/**
	 * Return plain authoring values, never the stored password.
	 *
	 * @param int $course_id Course ID.
	 * @return array
	 */
	public static function get( $course_id ) {
		$course           = new Course( $course_id );
		$data             = array(
			'course_id'     => $course_id,
			'title'         => (string) get_post_field( 'post_title', $course_id ),
			'description'   => (string) get_post_field( 'post_content', $course_id ),
			'author_id'     => (int) get_post_field( 'post_author', $course_id ),
			'image_id'      => (int) get_post_thumbnail_id( $course_id ),
			'status'        => (string) get_post_status( $course_id ),
			'price_type'    => (string) ( $course->get_price_type() ?: 'free' ),
			'regular_price' => (string) ( $course->get_regular_price() ?: '0' ),
			'sale_price'    => (string) $course->get_sale_price(),
			'access_type'   => (string) ( $course->get_access_type() ?: 'public' ),
			'has_capacity'  => filter_var( $course->get_has_capacity(), FILTER_VALIDATE_BOOLEAN ),
			'capacity'      => (int) $course->get_capacity(),
		);
		$data['revision'] = hash( 'sha256', wp_json_encode( array( $data, Catalog::revision( $course_id ), get_post_field( 'post_password', $course_id ), get_post_meta( $course_id, '_sale_price_dates_from', true ), get_post_meta( $course_id, '_sale_price_dates_to', true ) ) ) );
		return $data;
	}

	/**
	 * Validate all input before touching posts or metadata. Caller holds the shared lock/transaction.
	 *
	 * @param int   $course_id Course ID.
	 * @param mixed $data Decoded JSON.
	 * @return array|\WP_Error
	 */
	public static function save( $course_id, $data ) {
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), array_merge( self::FIELDS, array( 'revision', 'password' ) ) ) || array_diff( array_merge( self::FIELDS, array( 'revision' ) ), array_keys( $data ) ) ) {
			return Catalog::error( __( 'Send the complete course settings and revision, without unknown fields.', 'ohmylms' ) );
		}
		$current = self::get( $course_id );
		if ( ! is_string( $data['revision'] ) || ! hash_equals( $current['revision'], $data['revision'] ) ) {
			return Catalog::error( __( 'This course changed in another session. Your edits are kept. Reload the saved settings before trying again.', 'ohmylms' ), 409 );
		}
		foreach ( array( 'title', 'description', 'status', 'price_type', 'regular_price', 'sale_price', 'access_type' ) as $field ) {
			if ( ! is_string( $data[ $field ] ) ) {
				return Catalog::error( __( 'Course text fields must be strings.', 'ohmylms' ) );
			}
		}
		$title = trim( sanitize_text_field( $data['title'] ) );
		if ( '' === $title || mb_strlen( $title ) > 190 || strlen( $data['description'] ) > 200000 ) {
			return Catalog::error( __( 'Give the course a title of up to 190 characters and a description under 200 KB.', 'ohmylms' ) );
		}
		foreach ( array( 'author_id', 'image_id', 'capacity' ) as $field ) {
			if ( ! is_int( $data[ $field ] ) || $data[ $field ] < 0 || $data[ $field ] > 2147483647 ) {
				return Catalog::error( __( 'Author, image and capacity must be valid whole numbers.', 'ohmylms' ) );
			}
		}
		$type = get_post_type_object( OHMYLMS_COURSE_CPT );
		if ( ! get_userdata( $data['author_id'] ) || ! user_can( $data['author_id'], $type->cap->edit_posts ) || ( $current['author_id'] !== $data['author_id'] && ! current_user_can( $type->cap->edit_others_posts ) ) ) {
			return Catalog::error( __( 'Choose a user who can author courses.', 'ohmylms' ) );
		}
		if ( $data['image_id'] && ( ! wp_attachment_is_image( $data['image_id'] ) || ! current_user_can( 'edit_post', $data['image_id'] ) ) ) {
			return Catalog::error( __( 'Choose an image attachment you can edit.', 'ohmylms' ) );
		}
		$statuses = array( 'draft', 'pending', 'private' );
		if ( 'publish' === $current['status'] ) {
			$statuses[] = 'publish';
		}
		if ( ! in_array( $data['status'], $statuses, true ) || ( in_array( $data['status'], array( 'private', 'publish' ), true ) && ! current_user_can( $type->cap->publish_posts ) ) ) {
			return Catalog::error( __( 'Use the reviewed publication workflow to publish a course.', 'ohmylms' ) );
		}
		if ( ! in_array( $data['price_type'], array( 'free', 'paid' ), true ) || ! in_array( $data['access_type'], array( 'public', 'password_protected' ), true ) || ! is_bool( $data['has_capacity'] ) || ( $data['has_capacity'] && $data['capacity'] < 1 ) ) {
			return Catalog::error( __( 'Choose valid pricing and access options, and a positive enrollment capacity.', 'ohmylms' ) );
		}
		foreach ( array( 'regular_price', 'sale_price' ) as $field ) {
			if ( ( 'sale_price' !== $field || '' !== $data[ $field ] ) && ! preg_match( '/^\d{1,10}(?:\.\d{1,6})?$/D', $data[ $field ] ) ) {
				return Catalog::error( __( 'Prices must be nonnegative decimal amounts.', 'ohmylms' ) );
			}
		}
		if ( '' !== $data['sale_price'] && (float) $data['sale_price'] > (float) $data['regular_price'] ) {
			return Catalog::error( __( 'The sale price cannot exceed the regular price.', 'ohmylms' ) );
		}
		if ( 'paid' === $data['price_type'] && (float) $data['regular_price'] <= 0 ) {
			return Catalog::error( __( 'A paid course needs a positive regular price.', 'ohmylms' ) );
		}
		if ( array_key_exists( 'password', $data ) && ( ! is_string( $data['password'] ) || strlen( $data['password'] ) > 200 ) ) {
			return Catalog::error( __( 'Use a password of up to 200 bytes.', 'ohmylms' ) );
		}
		$password = $data['password'] ?? '';
		if ( 'password_protected' === $data['access_type'] && '' === $password && '' === (string) get_post_field( 'post_password', $course_id ) ) {
			return Catalog::error( __( 'Set a password for password protected access.', 'ohmylms' ) );
		}
		$course = new Course( $course_id );
		$course->set_name( $title );
		$course->set_description( wp_kses_post( $data['description'] ) );
		foreach ( array( 'status', 'price_type', 'regular_price', 'sale_price', 'access_type', 'has_capacity', 'capacity' ) as $field ) {
			$course->{ 'set_' . $field }( $data[ $field ] );
		}
		$course->set_thumbnail_id( $data['image_id'] );
		if ( '' !== $password ) {
			$course->set_password_protected( $password );
		}
		$course->save();
		$result = wp_update_post(
			array(
				'ID'          => $course_id,
				'post_author' => $data['author_id'],
			),
			true
		);
		return is_wp_error( $result ) ? $result : self::get( $course_id );
	}
}
