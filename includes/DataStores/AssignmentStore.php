<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class AssignmentStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Assignment;

defined( 'ABSPATH' ) || exit;

/**
 * Class AssignmentStore
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class AssignmentStore extends DataStore {

	/**
	 * Create Assignment
	 *
	 * @param Assignment $assignment Assignment.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function create( &$assignment ) {

		if ( ! $assignment->get_date_created( 'edit' ) ) {
			$assignment->set_date_created( time() );
		}

		$id = wp_insert_post(
			apply_filters(
				'ohmylms_new_assignment_data',
				array(
					'post_type'     => OHMYLMS_ASSIGNMENT_CPT,
					'post_author'   => get_current_user_id(),
					'post_status'   => 'publish',
					'post_title'    => $assignment->get_name() ? $assignment->get_name() : __( 'Untitled', 'ohmylms' ),
					'post_content'  => $assignment->get_description(),
					'post_name'     => $assignment->get_slug( 'edit' ),
					'post_date'     => gmdate( 'Y-m-d H:i:s', $assignment->get_date_created( 'edit' )->getOffsetTimestamp() ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $assignment->get_date_created( 'edit' )->getTimestamp() ),
				)
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$assignment->set_id( $id );

			$this->update_assignment_meta( $assignment );

			/**
			 * Fires after a new Assignment is created.
			 *
			 * This action hook allows developers to perform additional actions after a Assignment is created.
			 *
			 * @param int   $id     The ID of the newly created Assignment.
			 * @param array $assignment The Assignment data array, containing information about the created Assignment.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_creating_new_assignment', $id, $assignment );
		}
	}


	/**
	 * Read data
	 *
	 * @param Assignment $assignment Assignment.
	 * @return mixed|void
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function read( &$assignment ) {
		$post_object = get_post( $assignment->get_id() );
		if ( ! $assignment->get_id() || ! $post_object || OHMYLMS_ASSIGNMENT_CPT !== $post_object->post_type ) {
			return ( __( 'Invalid Assignment.', 'ohmylms' ) );
		}

		$assignment->set_props(
			array(
				'name'          => $post_object->post_title,
				'slug'          => $post_object->post_name,
				'status'        => $post_object->post_status,
				'date_created'  => $post_object->post_date_gmt,
				'date_modified' => $post_object->post_modified_gmt,
				'description'   => $post_object->post_content,
				'content'       => $post_object->post_content,
				'thumbnail_id'  => get_post_thumbnail_id( $assignment->get_id() ),
			)
		);

		$this->read_assignment_data( $assignment );
	}


	/**
	 * Update Assignment data
	 *
	 * @param Assignment $assignment The Assignment object to update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update( &$assignment ) {

		$post_data = array(
			'post_content' => $assignment->get_description( 'edit' ),
			'post_excerpt' => $assignment->get_short_description( 'edit' ),
			'post_title'   => $assignment->get_name( 'edit' ),
			'post_status'  => $assignment->get_status( 'edit' ) ? $assignment->get_status( 'edit' ) : 'publish',
			'post_name'    => sanitize_title( $assignment->get_name() ),
			'post_type'    => OHMYLMS_ASSIGNMENT_CPT,
		);
		if ( $assignment->get_date_created( 'edit' ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $assignment->get_date_created( 'edit' )->getOffsetTimestamp() );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $assignment->get_date_created( 'edit' )->getTimestamp() );
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );

		wp_update_post( array_merge( array( 'ID' => $assignment->get_id() ), $post_data ) );

		$this->update_assignment_meta( $assignment );

		/**
		 * Action hook to perform additional actions after a Assignment is updated.
		 *
		 * @param int    $assignment_id The ID of the updated Assignment.
		 * @param Assignment $assignment    The Assignment object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_updating_assignment', $assignment->get_id(), $assignment );
	}


	/**
	 * Update post meta for the Assignment.
	 *
	 * @param Assignment $assignment The Assignment object.
	 * @param bool       $force Whether to force the update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$assignment, $force = false ) {
		$meta_key_to_props = array(
			'_type'                   => 'type',
			'_content'                => 'content',
			'_enable_comments'        => 'enable_comments',
			'_download_resource'      => 'download_resource',
			'_prerequisites'          => 'prerequisites',
			'_enable_time_limit'      => 'enable_time_limit',
			'_time_limit'             => 'time_limit',
			'_time_limit_type'        => 'time_limit_type',
			'_total_points'           => 'total_points',
			'_maximum_pass_points'    => 'maximum_pass_points',
			'_allow_upload_files'     => 'allow_upload_files',
			'_number_of_files'        => 'number_of_files',
			'_enable_file_size_limit' => 'enable_file_size_limit',
			'_max_file_size_limit'    => 'max_file_size_limit',
			'_drip_settings'          => 'drip_settings',
		);

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $assignment->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;

			$this->update_or_delete_post_meta( $assignment, $meta_key, $value );
		}
	}

	/**
	 * Delete a Assignment.
	 *
	 * This function deletes a Assignment by its ID and triggers the 'ohmylms_after_deleting_a_assignment' action hook.
	 *
	 * @param Assignment $assignment The Assignment object to be deleted.
	 * @param array      $args Optional. Additional arguments for the delete operation. Default empty array.
	 *
	 * @since 1.0.0
	 */
	public function delete( &$assignment, $args = array() ) {
		if ( $assignment ) {
			$assignment_id = $assignment->get_id();
			if ( $assignment_id ) {
				wp_delete_post( $assignment_id, true );
				/**
				 * Triggered after deleting a Assignment.
				 *
				 * This action hook allows developers to perform additional actions after a Assignment is deleted.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_after_deleting_a_assignment' );
			}
		}
	}


	/**
	 * Helper function that reads Assignment data
	 *
	 * @param Assignment $assignment Assignment.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function read_assignment_data( &$assignment ) {
		$id               = $assignment->get_id();
		$post_meta_values = get_post_meta( $id );

		$meta_key_to_props = array(
			'_type'                   => 'type',
			'_content'                => 'content',
			'_enable_comments'        => 'enable_comments',
			'_download_resource'      => 'download_resource',
			'_prerequisites'          => 'prerequisites',
			'_enable_time_limit'      => 'enable_time_limit',
			'_time_limit'             => 'time_limit',
			'_time_limit_type'        => 'time_limit_type',
			'_total_points'           => 'total_points',
			'_maximum_pass_points'    => 'maximum_pass_points',
			'_allow_upload_files'     => 'allow_upload_files',
			'_number_of_files'        => 'number_of_files',
			'_enable_file_size_limit' => 'enable_file_size_limit',
			'_max_file_size_limit'    => 'max_file_size_limit',
			'_drip_settings'          => 'drip_settings',
		);

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value         = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;
			$set_props[ $prop ] = maybe_unserialize( $meta_value );
		}

		$assignment->set_props( $set_props );
	}


	/**
	 * Helper function that updates Assignment meta
	 *
	 * @param Assignment $assignment Assignment.
	 * @param bool       $force Force.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_assignment_meta( &$assignment, $force = false ) {
		$meta_key_to_props = array(
			'_type'                   => 'type',
			'_content'                => 'content',
			'_enable_comments'        => 'enable_comments',
			'_download_resource'      => 'download_resource',
			'_prerequisites'          => 'prerequisites',
			'_enable_time_limit'      => 'enable_time_limit',
			'_time_limit'             => 'time_limit',
			'_time_limit_type'        => 'time_limit_type',
			'_total_points'           => 'total_points',
			'_maximum_pass_points'    => 'maximum_pass_points',
			'_allow_upload_files'     => 'allow_upload_files',
			'_number_of_files'        => 'number_of_files',
			'_enable_file_size_limit' => 'enable_file_size_limit',
			'_max_file_size_limit'    => 'max_file_size_limit',
			'_drip_settings'          => 'drip_settings',
		);
		$meta_key_to_props = apply_filters( 'ohmylms_assignment_meta_key_to_props', $meta_key_to_props );
		$props_to_update   = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $assignment->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			$this->update_or_delete_post_meta( $assignment, $meta_key, $value );
		}

		/**
		 * Fires after the meta data for a Assignment is updated.
		 *
		 * @param WP_Post $assignment The updated Assignment object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_assignment_meta_updated', $assignment );
	}

	/**
	 * Get submission for Assignment store.
	 *
	 * @param mixed $assignment Assignment.
	 * @param mixed $user_id User identifier.
	 */
	public function get_submission( $assignment, $user_id ) {
		// Query to get the submission.
		global $wpdb;

		$table_name = $wpdb->prefix . 'ohmylms_assignment_attempts'; // Replace with your actual table name.

		$submission = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND assignment_id = %d', $table_name, $user_id, $assignment->get_id() ), ARRAY_A );

		return $submission;
	}

	/**
	 * Submit file submission for Assignment store.
	 *
	 * @param mixed $assignment Assignment.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $data Input data.
	 */
	public function submit_file_submission( $assignment, $student_id, $course_id, $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_assignment_attempts';

		$data = array(
			'user_id'       => $student_id,
			'course_id'     => $course_id,
			'assignment_id' => $assignment->get_id(),
			'files'         => maybe_serialize( $data['files'] ),
			'content'       => $data['content'],
			'status'        => 'submitted',
			'start_date'    => current_time( 'mysql' ),
			'end_date'      => current_time( 'mysql', 1 ),
		);

		if ( $wpdb->insert( $table_name, $data ) ) {
			do_action( 'ohmylms_assignment_submitted', (int) $wpdb->insert_id, $assignment->get_id(), $course_id, $student_id );
		}
	}


	/**
	 * Get report for Assignment store.
	 *
	 * @param mixed $assignment Assignment.
	 */
	public function get_report( $assignment ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_assignment_attempts';

		$query = $wpdb->prepare(
			'SELECT attempts.*, u.user_email, u.display_name, attempts.start_date AS submitted_date
		FROM %i attempts
		LEFT JOIN %i u ON attempts.user_id = u.ID
		WHERE assignment_id = %d',
			$table_name,
			$wpdb->prefix . 'users',
			$assignment->get_id()
		);

		$results = $wpdb->get_results( $query, ARRAY_A );
		$report  = array();

		foreach ( $results as $submission ) {
			$submission['files'] = maybe_unserialize( $submission['files'] );
			$key                 = $submission['user_id'] . '_' . $submission['course_id'] . '_' . $submission['assignment_id'];

			if ( ! isset( $report[ $key ] ) ) {
				$report[ $key ] = array(
					'user_id'       => $submission['user_id'],
					'course_id'     => $submission['course_id'],
					'assignment_id' => $submission['assignment_id'],
					'user_email'    => $submission['user_email'],
					'display_name'  => $submission['display_name'],
					'submissions'   => array(),
				);
			}

			$report[ $key ]['submissions'][] = $submission;
		}

		return array_values( $report );
	}

	/**
	 * Get assignment attempts for Assignment store.
	 *
	 * @param mixed $assignment Assignment.
	 * @param mixed $student_id Student identifier.
	 */
	public function get_assignment_attempts( $assignment, $student_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_assignment_attempts';

		$query = $wpdb->prepare(
			'SELECT attempts.*, u.user_email, u.display_name, attempts.start_date AS submitted_date
		FROM %i attempts
		LEFT JOIN %i u ON attempts.user_id = u.ID
		WHERE assignment_id = %d AND user_id = %d',
			$table_name,
			$wpdb->prefix . 'users',
			$assignment->get_id(),
			$student_id
		);

		$results = $wpdb->get_results( $query, ARRAY_A );
		$report  = array();

		foreach ( $results as $submission ) {
			$submission['files']              = maybe_unserialize( $submission['files'] );
			$submission_file_name             = isset( $submission['files']['file'] ) ? basename( $submission['files']['file'] ) : '';
			$submission_file_size             = isset( $submission['files']['file'] ) ? filesize( $submission['files']['file'] ) : 0;
			$submission_file_size             = ohmylms_format_file_size( $submission_file_size );
			$submission['files']['file_name'] = $submission_file_name;
			$submission['files']['file_size'] = $submission_file_size;
			$key                              = $submission['user_id'] . '_' . $submission['course_id'] . '_' . $submission['assignment_id'];

			if ( ! isset( $report[ $key ] ) ) {
				$report[ $key ] = array(
					'user_id'       => $submission['user_id'],
					'course_id'     => $submission['course_id'],
					'assignment_id' => $submission['assignment_id'],
					'user_email'    => $submission['user_email'],
					'display_name'  => $submission['display_name'],
					'submissions'   => array(),
				);
			}

			$report[ $key ]['submissions'][] = $submission;
		}

		return array_values( $report );
	}

	/**
	 * Update assignment attempts for Assignment store.
	 *
	 * @param mixed $assignment Assignment.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $attempt_data Attempt data.
	 */
	public function update_assignment_attempts( $assignment, $student_id, $attempt_data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_assignment_attempts';
		foreach ( $attempt_data as $key => $value ) {
			$data = array(
				'note'     => ! empty( $value['note'] ) ? $value['note'] : '',
				'status'   => $value['status'],
				'score'    => $value['score'],
				'end_date' => current_time( 'mysql', 1 ),
			);
			$wpdb->update(
				$table_name,
				$data,
				array(
					'id'            => $value['id'],
					'user_id'       => $student_id,
					'assignment_id' => $assignment->get_id(),
				)
			);
		}
	}
}
