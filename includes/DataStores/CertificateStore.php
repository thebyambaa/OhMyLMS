<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class CertificateStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Certificates\Template;
use OhMyLMS\Data\Certificate;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class CertificateStore
 *
 * Certificates are posts of type `ohmylms-certificate`. The template lives in the `_contents` meta
 * (see {@see Template}), the server-rendered HTML in `_html_contents`, and the course assignments in
 * the `ohmylms_certificate_relationship` table.
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class CertificateStore extends DataStore {

	/**
	 * Create certificate
	 *
	 * @param Certificate $certificate Certificate.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function create( &$certificate ) {

		if ( ! $certificate->get_date_created( 'edit' ) ) {
			$certificate->set_date_created( time() );
		}
		$id = wp_insert_post(
			apply_filters(
				'ohmylms_new_certificate_data',
				array(
					'post_type'     => OHMYLMS_CERTIFICATE_CPT,
					'post_author'   => get_current_user_id(),
					'post_status'   => $certificate->get_status() ? $certificate->get_status() : 'draft',
					'post_title'    => $certificate->get_name() ? $certificate->get_name() : __( 'Untitled', 'ohmylms' ),
					'post_name'     => $certificate->get_slug( 'edit' ),
					'post_date'     => gmdate( 'Y-m-d H:i:s', $certificate->get_date_created( 'edit' )->getOffsetTimestamp() ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $certificate->get_date_created( 'edit' )->getTimestamp() ),
				)
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$certificate->set_id( $id );

			/**
			 * Fires after a new certificate is created.
			 *
			 * This action hook allows developers to perform additional actions after a certificate is created.
			 *
			 * @param int   $id     The ID of the newly created certificate.
			 * @param array $certificate The certificate data array, containing information about the created certificate.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_creating_new_certificate', $id, $certificate );
		}
	}


	/**
	 * Read data
	 *
	 * @param Certificate $certificate Certificate.
	 * @return mixed|void
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function read( &$certificate ) {
		$post_object = get_post( $certificate->get_id() );
		if ( ! $certificate->get_id() || ! $post_object || OHMYLMS_CERTIFICATE_CPT !== $post_object->post_type ) {
			return;
		}

		$certificate->set_props(
			array(
				'name'          => $post_object->post_title,
				'slug'          => $post_object->post_name,
				'status'        => $post_object->post_status,
				'thumbnail_id'  => get_post_thumbnail_id( $certificate->get_id() ),
				'date_created'  => $post_object->post_date_gmt,
				'date_modified' => $post_object->post_modified_gmt,
			)
		);
	}


	/**
	 * Update certificate data
	 *
	 * @param Certificate $certificate The certificate object to update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update( &$certificate ) {

		$post_data = array(
			'post_title'  => $certificate->get_name( 'edit' ),
			'post_status' => $certificate->get_status( 'edit' ) ? $certificate->get_status( 'edit' ) : 'publish',
			'post_name'   => sanitize_title( $certificate->get_name() ),
			'post_type'   => OHMYLMS_CERTIFICATE_CPT,
		);
		if ( $certificate->get_date_created( 'edit' ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $certificate->get_date_created( 'edit' )->getOffsetTimestamp() );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $certificate->get_date_created( 'edit' )->getTimestamp() );
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );

		wp_update_post( array_merge( array( 'ID' => $certificate->get_id() ), $post_data ) );

		/**
		 * Action hook to perform additional actions after a certificate is updated.
		 *
		 * @param int    $certificate_id The ID of the updated certificate.
		 * @param Certificate $certificate    The certificate object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_update_certificate', $certificate->get_id(), $certificate );
	}


	/**
	 * Delete the certificate and its course assignments.
	 *
	 * @param mixed $certificate Certificate.
	 * @param array $args Operation arguments.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function delete( &$certificate, $args = array() ) {
		global $wpdb;

		if ( $certificate ) {
			$certificate_id = $certificate->get_id();
			if ( $certificate_id ) {
				wp_delete_post( $certificate_id, true );
				$wpdb->delete( $wpdb->prefix . 'ohmylms_certificate_relationship', array( 'certificate_id' => $certificate_id ), array( '%d' ) );
				/**
				 * Triggered after deleting a certificate.
				 *
				 * This action hook allows developers to perform additional actions after a certificate is deleted.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_after_deleting_a_certificate' );
			}
		}
	}

	/**
	 * Store the canonical template (already validated by Template::normalize()).
	 *
	 * @param Certificate $certificate The certificate object.
	 * @param array       $template    Canonical template.
	 * @return bool True on success.
	 *
	 * @since 1.0.0
	 */
	public function set_contents( $certificate, $template ) {
		update_post_meta( $certificate->get_id(), '_contents', $template );
		return true;
	}

	/**
	 * Store the HTML rendered from the template (derived server-side; never accepted from clients).
	 *
	 * @param Certificate $certificate The certificate object.
	 * @param string      $html        HTML produced by TemplateRenderer.
	 * @return bool True on success.
	 *
	 * @since 1.0.0
	 */
	public function set_html_contents( $certificate, $html ) {
		update_post_meta( $certificate->get_id(), '_html_contents', wp_slash( (string) $html ) );
		return true;
	}

	/**
	 * Set the featured image.
	 *
	 * @param Certificate $certificate  Certificate.
	 * @param int         $thumbnail_id Attachment id (0 removes it).
	 * @return bool|WP_Error
	 */
	public function set_thumbnail_image( $certificate, $thumbnail_id ) {
		$thumbnail_id = absint( $thumbnail_id );
		if ( ! $thumbnail_id ) {
			delete_post_thumbnail( $certificate->get_id() );
			return true;
		}
		if ( ! set_post_thumbnail( $certificate->get_id(), $thumbnail_id ) ) {
			return new WP_Error( 'ohmylms_certificate_thumbnail_failed', __( 'Failed to set preview image.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return true;
	}

	/**
	 * Get the canonical template, with defaults for anything missing.
	 *
	 * @param Certificate $certificate The certificate object.
	 * @return array Canonical template.
	 *
	 * @since 1.0.0
	 */
	public function get_contents( $certificate ) {
		return Template::from_stored( get_post_meta( $certificate->get_id(), '_contents', true ) );
	}

	/**
	 * Get the stored rendered HTML (placeholders are still tokens such as {student_name}).
	 *
	 * @param Certificate $certificate The certificate object.
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function get_html_contents( $certificate ) {
		return (string) get_post_meta( $certificate->get_id(), '_html_contents', true );
	}

	/**
	 * Course ids assigned to certificates, one query for any number of certificates.
	 *
	 * @param int[] $certificate_ids Certificate ids.
	 * @return array<int,int[]> Certificate id => course ids.
	 */
	public function get_course_ids_for( array $certificate_ids ) {
		global $wpdb;

		$certificate_ids = array_values( array_unique( array_filter( array_map( 'absint', $certificate_ids ) ) ) );
		$map             = array_fill_keys( $certificate_ids, array() );
		if ( ! $certificate_ids ) {
			return $map;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $certificate_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT certificate_id, course_id FROM %i WHERE certificate_id IN ($placeholders) ORDER BY course_id ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The placeholder list is built from absint() ids.
				array_merge( array( $wpdb->prefix . 'ohmylms_certificate_relationship' ), $certificate_ids )
			),
			ARRAY_A
		);
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['certificate_id'] ][] = (int) $row['course_id'];
		}

		return $map;
	}

	/**
	 * Get the assigned courses as id/title pairs (courses that no longer exist are skipped).
	 *
	 * @param Certificate $certificate Certificate.
	 * @param int[]|null  $course_ids  Preloaded course ids, or null to query.
	 * @return array<int,array{id:int,title:string}>
	 */
	public function get_courses( $certificate, $course_ids = null ) {
		if ( null === $course_ids ) {
			$all        = $this->get_course_ids_for( array( $certificate->get_id() ) );
			$course_ids = isset( $all[ $certificate->get_id() ] ) ? $all[ $certificate->get_id() ] : array();
		}

		$courses = array();
		foreach ( $course_ids as $course_id ) {
			if ( OHMYLMS_COURSE_CPT === get_post_type( $course_id ) ) {
				$courses[] = array(
					'id'    => (int) $course_id,
					'title' => get_the_title( $course_id ),
				);
			}
		}

		return $courses;
	}

	/**
	 * Replace the courses assigned to a certificate.
	 *
	 * A course uses one certificate, so assigning a course here moves it away from any other template.
	 * The change is atomic: either every row is written or none.
	 *
	 * @param Certificate $certificate Certificate.
	 * @param int[]       $course_ids  Course ids (already validated by the caller).
	 * @return true|WP_Error
	 */
	public function set_courses( $certificate, $course_ids ) {
		global $wpdb;

		$table          = $wpdb->prefix . 'ohmylms_certificate_relationship';
		$certificate_id = (int) $certificate->get_id();
		$course_ids     = array_values( array_unique( array_filter( array_map( 'absint', (array) $course_ids ) ) ) );

		$wpdb->query( 'START TRANSACTION' );

		$ok = false !== $wpdb->delete( $table, array( 'certificate_id' => $certificate_id ), array( '%d' ) );
		foreach ( $course_ids as $course_id ) {
			if ( $ok ) {
				$ok = false !== $wpdb->delete( $table, array( 'course_id' => $course_id ), array( '%d' ) )
					&& false !== $wpdb->insert(
						$table,
						array(
							'certificate_id' => $certificate_id,
							'course_id'      => $course_id,
						),
						array( '%d', '%d' )
					);
			}
		}

		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'ohmylms_certificate_courses_failed', __( 'The assigned courses could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$wpdb->query( 'COMMIT' );

		return true;
	}
}
