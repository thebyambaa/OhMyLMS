<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The certificate template a course awards, edited from the course.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** A course uses at most one template; the relationship table is shared with the certificate editor. */
final class CourseCertificate {
	/** Template choices returned with a read. */
	const MAX_CHOICES = 100;

	/**
	 * Assigned template ID, or 0.
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function current( $course_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh assignment read under the course lock; never cached.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT certificate_id FROM %i WHERE course_id = %d ORDER BY id ASC LIMIT 1', $wpdb->prefix . 'ohmylms_certificate_relationship', $course_id ) );
	}

	/**
	 * Current assignment and the templates the user may assign, by title.
	 *
	 * @param int $course_id Course ID.
	 * @return array{course_id:int,certificate_id:int,choices:array}
	 */
	public static function get( $course_id ) {
		$posts   = get_posts(
			array(
				'post_type'      => OHMYLMS_CERTIFICATE_CPT,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => self::MAX_CHOICES,
				'orderby'        => array(
					'title' => 'ASC',
					'ID'    => 'ASC',
				),
			)
		);
		$current = self::current( $course_id );
		$choices = array();
		foreach ( $posts as $post ) {
			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$choices[] = array(
					'id'     => (int) $post->ID,
					'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
					'status' => $post->post_status,
				);
			}
		}
		if ( $current && ! in_array( $current, array_column( $choices, 'id' ), true ) && get_post( $current ) ) {
			$choices[] = array(
				'id'     => $current,
				'title'  => html_entity_decode( get_the_title( $current ), ENT_QUOTES, 'UTF-8' ),
				'status' => get_post_status( $current ),
			);
		}
		return array(
			'course_id'      => (int) $course_id,
			'certificate_id' => $current,
			'choices'        => $choices,
		);
	}

	/**
	 * Replace the course's template. Called inside the course lock and transaction.
	 *
	 * @param int   $course_id Course ID.
	 * @param mixed $data `{certificate_id, expected_certificate_id}`; 0 removes the certificate.
	 * @return array|\WP_Error Fresh read.
	 */
	public static function assign( $course_id, $data ) {
		global $wpdb;
		$keys = array( 'certificate_id', 'expected_certificate_id' );
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), $keys ) || array_diff( $keys, array_keys( $data ) ) || ! is_int( $data['certificate_id'] ) || ! is_int( $data['expected_certificate_id'] ) || $data['certificate_id'] < 0 ) {
			return Catalog::error( __( 'Send exactly the certificate ID (0 for none) and the ID you last read.', 'ohmylms' ) );
		}
		if ( self::current( $course_id ) !== $data['expected_certificate_id'] ) {
			return Catalog::error( __( 'The course certificate changed in another session. Reload it before trying again.', 'ohmylms' ), 409, 'ohmylms_course_certificate_stale' );
		}
		$certificate = $data['certificate_id'];
		if ( $certificate && ( OHMYLMS_CERTIFICATE_CPT !== get_post_type( $certificate ) || 'trash' === get_post_status( $certificate ) || ! current_user_can( 'edit_post', $certificate ) ) ) {
			return Catalog::error( __( 'Choose a certificate template you can edit.', 'ohmylms' ), 403 );
		}
		$table = $wpdb->prefix . 'ohmylms_certificate_relationship';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Relationship write inside the course transaction.
		if ( false === $wpdb->delete( $table, array( 'course_id' => (int) $course_id ), array( '%d' ) ) ) {
			return Catalog::error( __( 'The course certificate could not be saved.', 'ohmylms' ), 500 );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Relationship write inside the course transaction.
		if ( $certificate && ! $wpdb->insert(
			$table,
			array(
				'certificate_id' => $certificate,
				'course_id'      => (int) $course_id,
			),
			array( '%d', '%d' )
		) ) {
			return Catalog::error( __( 'The course certificate could not be saved.', 'ohmylms' ), 500 );
		}
		return self::get( $course_id );
	}
}
