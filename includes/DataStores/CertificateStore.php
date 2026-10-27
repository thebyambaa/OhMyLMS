<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class CertificateStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Certificate;
use OhMyLMS\Data\Student;

defined( 'ABSPATH' ) || exit;

/**
 * Class CertificateStore
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
			flush_rewrite_rules( true );

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

		$this->read_certificate_data( $certificate );
	}

	/**
	 * Helper function that reads certificate data
	 *
	 * @param Certificate $certificate Certificate.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function read_certificate_data( &$certificate ) {
		$id               = $certificate->get_id();
		$post_meta_values = get_post_meta( $id );

		$meta_key_to_props = array(
			'_thumbnail_id' => 'thumbnail_id',
		);
		$set_props         = array();
		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;

			$set_props[ $prop ] = maybe_unserialize( $meta_value );

		}
		$certificate->set_props( $set_props );
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

		$this->update_post_meta( $certificate );

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
	 * Update post meta for the certificate.
	 *
	 * @param Certificate $certificate The certificate object.
	 * @param bool        $force Whether to force the update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$certificate, $force = false ) {
		$meta_key_to_props = array(
			'_contents' => 'contents',
		);

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $certificate->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			update_post_meta( $certificate->get_id(), $meta_key, $value );
		}
	}


	/**
	 * Delete the certificate
	 *
	 * @param mixed $certificate Certificate.
	 * @param array $args Operation arguments.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function delete( &$certificate, $args = array() ) {
		if ( $certificate ) {
			$certificate_id = $certificate->get_id();
			if ( $certificate_id ) {
				wp_delete_post( $certificate_id, true );
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
	 * Set the contents of the certificate.
	 *
	 * @param Certificate $certificate The certificate object.
	 * @param array       $content The content to set for the certificate.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function set_contents( $certificate, $content ) {
		update_post_meta( $certificate->get_id(), '_contents', $content );
		return true;
	}


	/**
	 * Set the contents of the certificate.
	 *
	 * @param Certificate $certificate The certificate object.
	 * @param array       $content The content to set for the certificate.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function set_html_contents( $certificate, $content ) {
		update_post_meta( $certificate->get_id(), '_html_contents', $content );
		return true;
	}

	/**
	 * Set thumbnail image for Certificate store.
	 *
	 * @param mixed $certificate Certificate.
	 * @param mixed $thumbnail_id Thumbnail identifier.
	 */
	public function set_thumbnail_image( $certificate, $thumbnail_id ) {
		$result = set_post_thumbnail( $certificate->get_id(), $thumbnail_id );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( 'failed_to_set_thumbnail', __( 'Failed to set preview image.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return true;
	}


	/**
	 * Get the contents of the certificate.
	 *
	 * @param Certificate $certificate The certificate object.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function get_contents( $certificate ) {
		return get_post_meta( $certificate->get_id(), '_contents', true );
	}

	/**
	 * Get the contents of the certificate.
	 *
	 * @param Certificate $certificate The certificate object.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function get_html_contents( $certificate ) {
		return get_post_meta( $certificate->get_id(), '_html_contents', true );
	}


	/**
	 * Get courses
	 *
	 * @param mixed $certificate Certificate.
	 */
	public function get_courses( $certificate ) {
		global $wpdb;

		// Get the certificate ID.
		$certificate_id = $certificate->get_id();

		// Query to get all course IDs associated with the certificate.
		$query = $wpdb->prepare( 'SELECT course_id FROM %i WHERE certificate_id = %d', $wpdb->prefix . 'ohmylms_certificate_relationship', $certificate_id );

		// Execute the query and get the results.
		$results = $wpdb->get_col( $query );

		if ( ! empty( $results ) ) {
			$courses    = array();
			$course_ids = $results;
			foreach ( $course_ids as $course_id ) {
				$course = ohmylms_get_course( $course_id );
				if ( $course ) {
					$courses[] = array(
						'id'           => $course_id,
						'name'         => $course->get_name(),
						'date_created' => $course->get_date_created(),
						'image_src'    => $course->get_thumbnail_url(),
					);
				}
			}
			return $courses;
		}

		// Return the list of course IDs.
		return array();
	}

	/**
	 * Set courses for Certificate store.
	 *
	 * @param mixed $certificate Certificate.
	 * @param mixed $courses Courses.
	 */
	public function set_courses( $certificate, $courses ) {
		global $wpdb;

		// Get the certificate ID.
		$certificate_id = $certificate->get_id();

		// Prepare the table name.
		$table_name = $wpdb->prefix . 'ohmylms_certificate_relationship';

		// Delete all existing relationships.
		$wpdb->delete(
			$table_name,
			array( 'certificate_id' => $certificate_id ),
			array( '%d' )
		);

		// Insert new relationships.
		foreach ( $courses as $course ) {
			if ( isset( $course['id'] ) ) {
				// Delete all existing relationships.
				$wpdb->delete(
					$table_name,
					array( 'course_id' => $course['id'] ),
					array( '%d' )
				);

				$wpdb->insert(
					$table_name,
					array(
						'certificate_id' => $certificate_id,
						'course_id'      => $course['id'],
					),
					array(
						'%d',
						'%d',
					)
				);
			}
		}
	}
}
