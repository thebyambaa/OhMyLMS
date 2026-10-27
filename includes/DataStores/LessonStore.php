<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class LessonStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Assignment;
use OhMyLMS\Data\Lesson;

defined( 'ABSPATH' ) || exit;

/**
 * Class LessonStore
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class LessonStore extends DataStore {

	/**
	 * Create lesson
	 *
	 * @param Lesson $lesson Lesson.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function create( &$lesson ) {

		if ( ! $lesson->get_date_created( 'edit' ) ) {
			$lesson->set_date_created( time() );
		}

		$slug = $lesson->get_slug( 'edit' );
		if ( ( ! $slug || 'untitled' === $slug ) && $lesson->get_name( 'edit' ) ) {
			$slug = $lesson->get_name( 'edit' );
		}

		$original_slug = $slug;
		$slug          = $this->generate_unique_slug( $slug, OHMYLMS_LESSON_CPT );

		$id = wp_insert_post(
			apply_filters(
				'ohmylms_new_lesson_data',
				array(
					'post_type'     => OHMYLMS_LESSON_CPT,
					'post_author'   => get_current_user_id(),
					'post_status'   => $lesson->get_status() ? $lesson->get_status() : 'draft',
					'post_title'    => $lesson->get_name() ? $lesson->get_name() : __( 'Untitled', 'ohmylms' ),
					'post_content'  => $lesson->get_description(),
					'post_name'     => $slug,
					'post_date'     => gmdate( 'Y-m-d H:i:s', $lesson->get_date_created( 'edit' )->getOffsetTimestamp() ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $lesson->get_date_created( 'edit' )->getTimestamp() ),
				)
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$lesson->set_id( $id );
			flush_rewrite_rules( true );
			$this->update_lesson_meta( $lesson );

			/**
			 * Fires after a new lesson is created.
			 *
			 * This action hook allows developers to perform additional actions after a lesson is created.
			 *
			 * @param int   $id     The ID of the newly created lesson.
			 * @param array $lesson The lesson data array, containing information about the created lesson.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_creating_new_lesson', $id, $lesson );
			if ( ! get_option( 'ohmylms_first_content_created', false ) ) {
				do_action( 'ohmylms_after_creating_first_content', $id, $lesson );
			}
		}
	}


	/**
	 * Read data
	 *
	 * @param Lesson $lesson Lesson.
	 * @return mixed|void
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function read( &$lesson ) {
		$post_object = get_post( $lesson->get_id() );
		if ( ! $lesson->get_id() || ! $post_object || OHMYLMS_LESSON_CPT !== $post_object->post_type ) {
			return ( __( 'Invalid lesson.', 'ohmylms' ) );
		}

		$lesson->set_props(
			array(
				'name'          => $post_object->post_title,
				'slug'          => $post_object->post_name,
				'status'        => $post_object->post_status,
				'date_created'  => $post_object->post_date_gmt,
				'date_modified' => $post_object->post_modified_gmt,
				'description'   => $post_object->post_content,
				'thumbnail_id'  => get_post_thumbnail_id( $lesson->get_id() ),
			)
		);

		$this->read_lesson_data( $lesson );
	}


	/**
	 * Update lesson data
	 *
	 * @param Lesson $lesson The lesson object to update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update( &$lesson ) {
		$slug      = $lesson->get_slug( 'edit' );
		$slug      = $this->generate_unique_slug( $slug, OHMYLMS_LESSON_CPT );
		$post_data = array(
			'post_content' => $lesson->get_description( 'edit' ),
			'post_excerpt' => $lesson->get_short_description( 'edit' ),
			'post_title'   => $lesson->get_name( 'edit' ),
			'post_status'  => $lesson->get_status( 'edit' ) ? $lesson->get_status( 'edit' ) : 'publish',
			'post_name'    => sanitize_title( $lesson->get_name() ),
			'post_type'    => OHMYLMS_LESSON_CPT,
		);
		if ( $lesson->get_date_created( 'edit' ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $lesson->get_date_created( 'edit' )->getOffsetTimestamp() );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $lesson->get_date_created( 'edit' )->getTimestamp() );
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );

		wp_update_post( array_merge( array( 'ID' => $lesson->get_id() ), $post_data ) );

		$this->update_lesson_meta( $lesson );

		/**
		 * Action hook to perform additional actions after a lesson is updated.
		 *
		 * @param int    $lesson_id The ID of the updated lesson.
		 * @param Lesson $lesson    The lesson object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_updating_lesson', $lesson->get_id(), $lesson );
	}


	/**
	 * Update post meta for the lesson.
	 *
	 * @param Lesson $lesson The lesson object.
	 * @param bool   $force Whether to force the update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$lesson, $force = false ) {
		$meta_key_to_props = array(
			'_type'              => 'type',
			'_thumbnail_id'      => 'thumbnail_id',
			'_content'           => 'content',
			'_drip_settings'     => 'drip_settings',
			'_enable_comments'   => 'enable_comments',
			'_download_resource' => 'download_resource',
			'_prerequisites'     => 'prerequisites',
			'_cover_image_id'    => 'cover_image_id',
			'_video_id'          => 'video_id',
			'_audio_id'          => 'audio_id',
			'_external_url'      => 'external_url',
			'_video_settings'    => 'video_settings',
		);

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $lesson->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;

			$this->update_or_delete_post_meta( $lesson, $meta_key, $value );
		}
	}

	/**
	 * Delete a lesson.
	 *
	 * This function deletes a lesson by its ID and triggers the 'ohmylms_after_deleting_a_lesson' action hook.
	 *
	 * @param Lesson $lesson The lesson object to be deleted.
	 * @param array  $args Optional. Additional arguments for the delete operation. Default empty array.
	 *
	 * @since 1.0.0
	 */
	public function delete( &$lesson, $args = array() ) {
		if ( $lesson ) {
			$lesson_id = $lesson->get_id();
			if ( $lesson_id ) {
				wp_delete_post( $lesson_id, true );
				/**
				 * Triggered after deleting a lesson.
				 *
				 * This action hook allows developers to perform additional actions after a lesson is deleted.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_after_deleting_a_lesson' );
			}
		}
	}


	/**
	 * Helper function that reads lesson data
	 *
	 * @param Lesson $lesson Lesson.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function read_lesson_data( &$lesson ) {
		$id               = $lesson->get_id();
		$post_meta_values = get_post_meta( $id );

		$meta_key_to_props = array(
			'_type'              => 'type',
			'_thumbnail_id'      => 'thumbnail_id',
			'_content'           => 'content',
			'_drip_settings'     => 'drip_settings',
			'_enable_comments'   => 'enable_comments',
			'_download_resource' => 'download_resource',
			'_prerequisites'     => 'prerequisites',
			'_cover_image_id'    => 'cover_image_id',
			'_video_id'          => 'video_id',
			'_audio_id'          => 'audio_id',
			'_external_url'      => 'external_url',
			'_preview_enable'    => 'preview_enable',
			'_video_settings'    => 'video_settings',
		);

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;

			$set_props[ $prop ] = maybe_unserialize( $meta_value );
		}
		$lesson->set_props( $set_props );
	}


	/**
	 * Helper function that updates lesson meta
	 *
	 * @param Lesson $lesson Lesson.
	 * @param bool   $force Force.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_lesson_meta( &$lesson, $force = false ) {
		$meta_key_to_props = array(
			'_type'              => 'type',
			'_thumbnail_id'      => 'thumbnail_id',
			'_content'           => 'content',
			'_drip_settings'     => 'drip_settings',
			'_enable_comments'   => 'enable_comments',
			'_download_resource' => 'download_resource',
			'_prerequisites'     => 'prerequisites',
			'_cover_image_id'    => 'cover_image_id',
			'_video_id'          => 'video_id',
			'_audio_id'          => 'audio_id',
			'_external_url'      => 'external_url',
			'_preview_enable'    => 'preview_enable',
			'_video_settings'    => 'video_settings',

		);
		$meta_key_to_props = apply_filters( 'ohmylms_lesson_meta_key_to_props', $meta_key_to_props );
		$props_to_update   = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = method_exists( $lesson, "get_$prop" ) ? $lesson->{"get_$prop"}( 'edit' ) : '';
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			$this->update_or_delete_post_meta( $lesson, $meta_key, $value );
		}

		/**
		 * Fires after the meta data for a lesson is updated.
		 *
		 * @param WP_Post $lesson The updated lesson object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_lesson_meta_updated', $lesson );
	}


	/**
	 * Get the order number of a lesson within its chapter.
	 *
	 * This function retrieves the order number of a lesson based on its ID and the chapter it belongs to.
	 *
	 * @param Lesson $lesson The lesson object.
	 * @return int|null The order number of the lesson, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function get_order_number( &$lesson ) {
		global $wpdb;
		$lesson_id    = $lesson->get_id();
		$chapter_id   = ohmylms_get_chapter_id_by_content_id( $lesson_id );
		$table_name   = $wpdb->prefix . 'ohmylms_content_relationship';
		$order_number = $wpdb->get_var(
			$wpdb->prepare( 'SELECT order_number FROM %i WHERE chapter_id = %d AND content_id = %d', $table_name, $chapter_id, $lesson_id )
		);
		return $order_number;
	}
}
