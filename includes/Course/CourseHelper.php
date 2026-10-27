<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Responsible to handle all course related calculations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Course;

/**
 * Responsible to handle all course related calculations
 *
 * @since 1.0.0
 */
class CourseHelper {

	/**
	 * Course general settings meta key constants
	 *
	 * @return string[]
	 * @since 1.0.0
	 */
	public static function course_meta_key_constants(): array {
		return array(
			'course_duration',
			'course_price',
			'course_sale_price',
			'course_level',
			'course_max_student_allowed',
			'course_max_retake_allowed',
			'course_evaluation_type',
			'course_passing_grade',
			'course_requirements',
			'course_target_audiences',
			'course_faqs',
			'course_map',
		);
	}

	/**
	 * Add course
	 *
	 * @param mixed $map_data Map data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function add_course( $map_data ): array {

		if ( empty( $map_data['title'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Please enter a title.', 'ohmylms' ),
			);
		}

		if ( empty( $map_data['description'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Please provide a description.', 'ohmylms' ),
			);
		}

		/**
		 * Fires before course add
		 *
		 * @param array $map_data course data
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_add_course', $map_data );

		$post_id = wp_insert_post(
			array(
				'post_title'   => $map_data['title'],
				'post_excerpt' => $map_data['description'],
				'post_type'    => 'ohmylms-course',
				'post_status'  => 'publish',
			)
		);

		/**
		 * log the error when debug is enabled.
		 * $post_id->get_error_message()
		 */
		if ( is_wp_error( $post_id ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Failed to add course.', 'ohmylms' ),
			);
		}

		/**
		 * Fires after course add
		 *
		 * @param number $post_id new inserted course id
		 * @param array $map_data course data
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_add_course', $post_id, $map_data );

		set_post_thumbnail( $post_id, $map_data['feature_image']['src'] );

		unset( $map_data['feature_image'] );
		unset( $map_data['feature_video'] );
		update_post_meta( $post_id, 'ohmylms_course_map', $map_data );

		return array(
			'status'    => 'success',
			'message'   => __( 'Course has been created successfully.', 'ohmylms' ),
			'course_id' => $post_id,
		);
	}

	/**
	 * Update course
	 *
	 * @param mixed $post_id Post identifier.
	 * @param array $map_data Map data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function update_course( $post_id, array $map_data = array() ): array {

		/**
		 * Fires before course update
		 *
		 * @param number $post_id new inserted course id
		 * @param array $map_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_course_update', $post_id, $map_data );

		if ( empty( $map_data['name'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Title is required to save course.', 'ohmylms' ),
			);
		}

		if ( empty( $map_data['description'] ) ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Description is required to save course.', 'ohmylms' ),
			);
		}

		$post = get_post( $post_id );

		if ( $post && 'ohmylms-course' === $post->post_type ) {
			wp_update_post(
				array(
					'ID'         => $post_id,
					'post_title' => sanitize_text_field( $map_data['title'] ),
				)
			);
			update_post_meta( $post_id, 'ohmylms_course_map', $map_data );
		} else {
			$post_id = wp_insert_post(
				array(
					'post_title'  => sanitize_text_field( $map_data['title'] ),
					'post_type'   => 'ohmylms-course',
					'post_status' => 'publish',
				)
			);

			update_post_meta( $post_id, 'ohmylms_course_map', $map_data );
		}

		/**
		 * Fires after course update
		 *
		 * @param number $post_id new inserted course id
		 * @param array $map_data key value pair based items to update meta fields
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_course_update', $post_id, $map_data );

		return array(
			'status'  => 'success',
			'message' => __( 'Successfully saved course.', 'ohmylms' ),
		);
	}

	/**
	 * Delete course
	 *
	 * @param mixed $post_id Post identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function delete_course( $post_id ): array {

		$post = get_post( $post_id );
		if ( ! $post || 'ohmylms-course' !== $post->post_type ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Course not found.', 'ohmylms' ),
			);
		}

		/**
		 * Fires before course delete
		 *
		 * @param string $post_id course id to delete
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_before_course_delete', $post_id );

		$result = wp_delete_post( $post_id, true );

		if ( ! $result ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Failed to delete course.', 'ohmylms' ),
			);
		}

		/**
		 * Fires after course delete
		 *
		 * @param string $post_id Deleted course id
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_after_course_delete', $post_id );

		return array(
			'status'  => 'success',
			'message' => __( 'Course has been deleted.', 'ohmylms' ),
		);
	}

	/**
	 * Insert course section relation
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $section_id Section identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function insert_course_section_relation( $course_id, $section_id ): array {

		global $wpdb;

		$table  = $wpdb->prefix . 'ohmylms_courses_with_sections';
		$result = $wpdb->insert(
			$table,
			array(
				'course_id'      => $course_id,
				'section_id'     => $section_id,
				'created_at'     => current_time( 'mysql' ),
				'created_at_gmt' => current_time( 'mysql', 1 ),
				'updated_at'     => current_time( 'mysql' ),
				'updated_at_gmt' => current_time( 'mysql', 1 ),
				'created_by'     => get_current_user_id(),
				'updated_by'     => get_current_user_id(),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
			)
		);

		if ( false === $result ) {
			return array(
				'status'  => 'error',
				'message' => __( 'Failed to insert course section relation.', 'ohmylms' ),
			);
		}

		return array(
			'status'  => 'success',
			'message' => __( 'Course section relation inserted.', 'ohmylms' ),
		);
	}

	/**
	 * Update course map
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $map Map.
	 * @return array
	 * @since 1.0.0
	 */
	public static function update_course_map( $course_id, $map ): array {

		$course = get_post( $course_id );
		if ( ! $course || 'ohmylms-course' !== $course->post_type ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Course not found.', 'ohmylms' ),
			);
		}

		update_post_meta( $course_id, 'ohmylms_course_map', $map );

		return array(
			'status'  => 'success',
			'message' => __( 'Course content saved', 'ohmylms' ),
		);
	}

	/**
	 * Get course map
	 *
	 * @param mixed $course_id Course identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_course_map( $course_id ): array {
		$course = get_post( $course_id );
		if ( ! $course || 'ohmylms-course' !== $course->post_type ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Course not found.', 'ohmylms' ),
			);
		}
		$map = get_post_meta( $course_id, 'ohmylms_course_map', true );
		if ( empty( $map ) ) {
			$map = self::get_default_map( $course_id );
		}
		$map['feature_image'] = get_the_post_thumbnail_url( $course_id, 'large' );
		$map['feature_video'] = get_post_meta( $course_id, 'featured_video', true );
		$map['id']            = $course_id;
		return array(
			'status'  => 'success',
			'message' => __( 'Fetched course map.', 'ohmylms' ),
			'data'    => $map,
		);
	}

	/**
	 * Fetch default course data map
	 *
	 * @param mixed $course_id Course identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_default_map( $course_id = null ): array {

		if ( ! empty( $course_id ) ) {
			$course      = get_post( $course_id );
			$course_data = array(
				'id'          => $course_id,
				'title'       => get_the_title( $course_id ),
				'description' => $course->post_excerpt,
				'chapters'    => array(),
			);
		} else {
			$course_data = array(
				'id'          => null,
				'title'       => '',
				'description' => '',
				'chapters'    => array(),
			);
		}
		return $course_data;
	}

	/**
	 * Ohmylms save course general settings for Course helper.
	 */
	public static function ohmylms_save_course_general_settings() {

		check_ajax_referer( 'ohmylms-course', 'nonce' );

		if ( ! current_user_can( 'edit_post', $_POST['post_id'] ) ) {
			$response = array(
				'status'  => 'error',
				'message' => __( 'You do not have permission to edit this post.', 'ohmylms' ),
			);

			wp_send_json( $response );
		}

		$general_settings_items = array();

		$course_id                                   = $_POST['post_id'];
		$general_settings_items['course_duration']   = $_POST['course_duration'];
		$general_settings_items['course_price']      = $_POST['course_price'];
		$general_settings_items['course_sale_price'] = $_POST['course_sale_price'];
		$general_settings_items['course_max_student_allowed'] = $_POST['course_max_student_allowed'];
		$general_settings_items['course_max_retake_allowed']  = $_POST['course_max_retake_allowed'];
		$general_settings_items['course_passing_grade']       = $_POST['course_passing_grade'];

		$validated_response = CourseValidator::validate_course_general_settings_fields( $general_settings_items );

		if ( isset( $validated_response['status'] ) && 'error' === $validated_response['status'] ) {
			wp_send_json( $validated_response );
		}

		$response = self::update_course( $course_id, '', '', $general_settings_items );
		wp_send_json( $response );
	}

	/**
	 * Save course settings
	 *
	 * @param int   $course_id Course identifier.
	 * @param mixed $settings_data Settings data.
	 * @return array
	 * @since 1.0.0
	 */
	public static function save_course_settings( int $course_id, $settings_data ): array {

		$course = get_post( $course_id );
		if ( ! $course || 'ohmylms-course' !== $course->post_type ) {

			return array(
				'status'  => 'error',
				'message' => __( 'Course not found.', 'ohmylms' ),
			);
		}

		update_post_meta( $course_id, 'ohmylms_course_settings', $settings_data );

		return array(
			'status'  => 'success',
			'message' => __( 'Course settings saved', 'ohmylms' ),
		);
	}
}
