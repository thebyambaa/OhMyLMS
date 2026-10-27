<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class CourseStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Course;

defined( 'ABSPATH' ) || exit;

/**
 * Class CourseStore
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class CourseStore extends DataStore {
	/**
	 * Invalidate request-local unit projections after an atomic structure change.
	 *
	 * @param int $course_id Course ID.
	 */
	public static function clear_structure_cache( $course_id ) {
		unset( self::$raw_chapters_cache[ $course_id ] );
		foreach ( array_keys( self::$chapters_cache ) as $key ) {
			if ( 0 === strpos( (string) $key, $course_id . '_' ) ) {
				unset( self::$chapters_cache[ $key ] );
			}
		}
	}

	/**
	 * Cache for enrollment counts to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $enrollment_count_cache = array();

	/**
	 * Cache for chapters to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $chapters_cache = array();

	/**
	 * Cache for raw chapter relationship data from database.
	 *
	 * @var array
	 */
	private static $raw_chapters_cache = array();

	/**
	 * Cache for lesson counts to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $lessons_count_cache = array();

	/**
	 * Cache for course access checks to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $course_access_cache = array();

	/**
	 * Cache for quiz counts to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $quiz_count_cache = array();

	/**
	 * Cache for assignment counts to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $assignment_count_cache = array();

	/**
	 * Cache for all content counts to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $all_content_count_cache = array();

	/**
	 * Create course
	 *
	 * @param Course $course Course.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function create( &$course ) {

		if ( ! $course->get_date_created( 'edit' ) ) {
			$course->set_date_created( time() );
		}

		$slug = $course->get_slug( 'edit' );
		if ( ( ! $slug || 'untitled' === $slug ) && $course->get_name( 'edit' ) ) {
			$slug = $course->get_name( 'edit' );
		}

		$slug = $this->generate_unique_slug( $slug, OHMYLMS_COURSE_CPT );

		$id = wp_insert_post(
			apply_filters(
				'ohmylms_new_course_data',
				array(
					'post_type'    => OHMYLMS_COURSE_CPT,
					'post_author'  => get_current_user_id(),
					'post_status'  => $course->get_status() ? $course->get_status() : 'draft',
					'post_title'   => $course->get_name() ? $course->get_name() : __( 'Untitled', 'ohmylms' ),
					'post_name'    => $slug,
					'post_content' => $course->get_description(),
					// 'post_date'     => gmdate( 'Y-m-d H:i:s', $course->get_date_created( 'edit' )->getOffsetTimestamp() ),
					// 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $course->get_date_created( 'edit' )->getTimestamp() ),
				)
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$course->set_id( $id );
			$this->update_post_meta( $course );

			/**
			 * Fires after a new course is created.
			 *
			 * This action hook allows developers to perform additional actions after a course is created.
			 *
			 * @param int   $id     The ID of the newly created course.
			 * @param array $course The course data array, containing information about the created course.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_new_course', $id, $course );

			if ( ! get_option( 'ohmylms_first_course_created', false ) ) {
				do_action( 'ohmylms_after_creating_first_course', $id, $course );
			}
		}
	}


	/**
	 * Read data
	 *
	 * @param Course $course Course.
	 * @return mixed|void
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function read( &$course ) {
		$post_object = get_post( $course->get_id() );
		if ( ! $course->get_id() || ! $post_object || OHMYLMS_COURSE_CPT !== $post_object->post_type ) {
			return;
			// throw new \Exception( __( 'Invalid course.', 'ohmylms' ) );.
		}
		$course->set_props(
			array(
				'name'               => $post_object->post_title,
				'slug'               => $post_object->post_name,
				'status'             => $post_object->post_status,
				'post_date'          => $post_object->post_date,
				'date_created'       => $post_object->post_date_gmt,
				'date_modified'      => $post_object->post_modified_gmt,
				'description'        => $post_object->post_content,
				'password_protected' => $post_object->post_password ? $post_object->post_password : '',
				'thumbnail_id'       => get_post_thumbnail_id( $course->get_id() ),
			)
		);
		$this->read_course_data( $course );
	}


	/**
	 * Update course data
	 *
	 * @param Course $course The course object to update.
	 * @return void
	 *
	 * @since 1.0.0
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public function update( &$course ) {
		$slug = $course->get_slug( 'edit' );
		if ( strpos( $slug, 'untitled' ) !== false && $course->get_name( 'edit' ) ) {
			$slug = $course->get_name( 'edit' );
		}

		$slug      = $this->generate_unique_slug( $slug, OHMYLMS_COURSE_CPT, $course->get_id() );
		$post_data = array(
			'post_content' => $course->get_description( 'edit' ),
			'post_excerpt' => $course->get_short_description( 'edit' ),
			'post_title'   => $course->get_name( 'edit' ),
			'post_status'  => $course->get_status( 'edit' ) ? $course->get_status( 'edit' ) : 'publish',
			'post_name'    => $slug,
			'post_type'    => OHMYLMS_COURSE_CPT,
		);
		if ( $course->get_date_created( 'edit' ) ) {
			$post_data['post_date_gmt'] = $course->get_post_date() ? gmdate( 'Y-m-d H:i:s', $course->get_post_date( 'edit' )->getTimestamp() ) : gmdate( 'Y-m-d H:i:s', $course->get_date_created( 'edit' )->getTimestamp() );
			$post_data['post_date']     = get_date_from_gmt( $post_data['post_date_gmt'] );
		}
		if ( 'password_protected' == $course->get_access_type() ) {
			$post_data['post_password'] = $course->get_password_protected();
		} else {
			$post_data['post_password'] = '';
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );

		$result = wp_update_post( wp_slash( array_merge( array( 'ID' => $course->get_id() ), $post_data ) ), true );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( $result->get_error_message() );
		}
		$course->set_slug( get_post_field( 'post_name', $result ) );
		$this->update_post_meta( $course );

		/**
		 * Action hook to perform additional actions after a course is updated.
		 *
		 * @param int    $course_id The ID of the updated course.
		 * @param Course $course    The course object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_update_course', $course->get_id(), $course );
	}


	/**
	 * Update post meta for the course.
	 *
	 * @param Course $course The course object.
	 * @param bool   $force Whether to force the update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$course, $force = false ) {
		$meta_key_to_props = array(
			'_price'                 => 'price',
			'_price_type'            => 'price_type',
			'_regular_price'         => 'regular_price',
			'_sale_price'            => 'sale_price',
			'_sale_price_dates_from' => 'sale_price_dates_from',
			'_download_resource'     => 'download_resource',
			'_point_disabled'        => 'point_disabled',
			'_reward_disabled'       => 'reward_disabled',
			'_purchase_point'        => 'purchase_point',
			'_sale_price_dates_to'   => 'sale_price_dates_to',
			'_thumbnail_id'          => 'thumbnail_id',
			'_video_id'              => 'video_id',
			'_level'                 => 'level',
			'_availability'          => 'availability',
			'_available_date'        => 'available_date',
			'_access_type'           => 'access_type',
			'_has_capacity'          => 'has_capacity',
			'_capacity'              => 'capacity',
			'_review_count'          => 'review_count',
			'_rating_counts'         => 'rating_counts',
			'_average_rating'        => 'average_rating',
			'_duration'              => 'duration',
			'_enable_reviews'        => 'enable_reviews',
			'_benefit_description'   => 'benefit_description',
			'_benefiter_description' => 'benefiter_description',
			'_requirement'           => 'requirement',
			'_type'                  => 'type',
			'_creation_method'       => 'creation_method',
			'_has_community'         => 'has_community',
			'_sequential_mode'       => 'sequential_mode',
			'_space_title'           => 'space_title',
			'_space_description'     => 'space_description',
			'_funnel_steps'          => 'funnel_steps',
		);

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = method_exists( $course, "get_$prop" ) ? $course->{"get_$prop"}( 'edit' ) : '';
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			switch ( $prop ) {
				case 'sale_price_dates_from':
				case 'sale_price_dates_to':
					$value = $value ? $value->getTimestamp() : '';
					break;
			}
			$course_price_props = array( '_regular_price', '_sale_price' );
			if ( in_array( $meta_key, $course_price_props ) ) {
				$value = ohmylms_format_decimal( $value );
				if ( $course->is_on_sale( 'edit' ) ) {
					update_post_meta( $course->get_id(), '_price', $course->get_sale_price( 'edit' ) );
					$course->set_price( $course->get_sale_price( 'edit' ) );
				} else {
					update_post_meta( $course->get_id(), '_price', $course->get_regular_price( 'edit' ) );
					$course->set_price( $course->get_regular_price( 'edit' ) );
				}
			}
			$this->update_or_delete_post_meta( $course, $meta_key, $value );
			do_action( 'ohmylms_update_or_delete_course_meta_' . ltrim( $meta_key, '_' ), $course, ltrim( $meta_key, '_' ), $value );
		}
	}


	/**
	 * Delete the course
	 *
	 * @param mixed $course Course.
	 * @param array $args Operation arguments.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function delete( &$course, $args = array() ) {
		if ( $course ) {
			$course_id = $course->get_id();
			$args      = wp_parse_args(
				$args,
				array(
					'force_delete' => true,
				)
			);
			if ( ! $course_id ) {
				return;
			}
			if ( $args['force_delete'] ) {
				wp_delete_post( $course_id );
				$course->set_id( 0 );
				do_action( 'ohmylms_delete_course', $course_id );
			} else {
				wp_trash_post( $course_id );
				do_action( 'ohmylms_trash_course', $course_id );
			}
		}
	}


	/**
	 * Helper function that reads course data
	 *
	 * @param Course $course Course.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function read_course_data( &$course ) {
		$id               = $course->get_id();
		$post_meta_values = get_post_meta( $id );

		$meta_key_to_props = array(
			'_price'                 => 'price',
			'_price_type'            => 'price_type',
			'_regular_price'         => 'regular_price',
			'_sale_price'            => 'sale_price',
			'_sale_price_dates_from' => 'sale_price_dates_from',
			'_sale_price_dates_to'   => 'sale_price_dates_to',
			'_thumbnail_id'          => 'thumbnail_id',
			'_download_resource'     => 'download_resource',
			'_video_id'              => 'video_id',
			'_level'                 => 'level',
			'_availability'          => 'availability',
			'_available_date'        => 'available_date',
			'_access_type'           => 'access_type',
			'_has_capacity'          => 'has_capacity',
			'_capacity'              => 'capacity',
			'_review_count'          => 'review_count',
			'_rating_counts'         => 'rating_counts',
			'_leaderboard_disabled'  => 'leaderboard_disabled',
			'_point_disabled'        => 'point_disabled',
			'_reward_disabled'       => 'reward_disabled',
			'_purchase_point'        => 'purchase_point',
			'_average_rating'        => 'average_rating',
			'_duration'              => 'duration',
			'_enable_reviews'        => 'enable_reviews',
			'_benefit_description'   => 'benefit_description',
			'_benefiter_description' => 'benefiter_description',
			'_requirement'           => 'requirement',
			'_type'                  => 'type',
			'_creation_method'       => 'creation_method',
			'_has_community'         => 'has_community',
			'_sequential_mode'       => 'sequential_mode',
			'_space_title'           => 'space_title',
			'_space_description'     => 'space_description',
			'_funnel_steps'          => 'funnel_steps',
		);

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value         = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;
			$set_props[ $prop ] = maybe_unserialize( $meta_value );
		}
		$course->set_props( $set_props );
	}


	/**
	 * Get the chapters of the course.
	 *
	 * @param Course $course The course object.
	 * @param string $return Return.
	 * @return array The list of chapters.
	 *
	 * @since 1.0.0
	 */
	public function get_chapters( $course, $return = 'array' ) {
		$course_id = $course->get_id();
		if ( ! \OhMyLMS\Learning\UnitSnapshots::is_authoring() ) {
			$published = array();
			foreach ( \OhMyLMS\Learning\UnitSnapshots::public_units( $course_id ) as $position => $unit ) {
				$chapter = ohmylms_get_chapter( $unit['id'] );
				if ( $chapter ) {
					$published[] = 'objects' === $return ? $chapter : $unit + array( 'order_number' => $position + 1 );
				}
			}
			return $published;
		}
		$cache_key = $course_id . '_' . $return;

		// Check if result is already cached.
		if ( isset( self::$chapters_cache[ $cache_key ] ) ) {
			return self::$chapters_cache[ $cache_key ];
		}

		// Check if raw database results are cached.
		if ( ! isset( self::$raw_chapters_cache[ $course_id ] ) ) {
			global $wpdb;
			$table_name                             = $wpdb->prefix . 'ohmylms_chapter_relationship';
			self::$raw_chapters_cache[ $course_id ] = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE course_id = %d ORDER BY order_number ASC', $table_name, $course_id ) );
		}

		$chapters          = self::$raw_chapters_cache[ $course_id ];
		$filtered_chapters = array();

		if ( $chapters ) {
			foreach ( $chapters as $chapter ) {
				$chapter_obj = ohmylms_get_chapter( $chapter->chapter_id );
				if ( 'objects' === $return ) {
					$filtered_chapters[] = $chapter_obj;
					continue;
				}
				if ( $chapter_obj ) {
					$filtered_chapters[] = array(
						'id'           => $chapter_obj->get_id(),
						'name'         => $chapter_obj->get_name(),
						'description'  => $chapter_obj->get_description(),
						'order_number' => (int) $chapter->order_number,
					);
				}
			}
		}

		// Cache the result.
		self::$chapters_cache[ $cache_key ] = $filtered_chapters;

		return $filtered_chapters;
	}


	/**
	 * Get the number of enrollments for the course.
	 *
	 * @param Course $course The course object.
	 * @return int The number of enrollments.
	 *
	 * @since 1.0.0
	 */
	public function get_total_enrolled_users( $course ) {
		$course_id = $course->get_id();

		// Check if result is already cached.
		if ( isset( self::$enrollment_count_cache[ $course_id ] ) ) {
			return self::$enrollment_count_cache[ $course_id ];
		}

		global $wpdb;
		$table_name       = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enrollment_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d AND status = %s', $table_name, $course_id, 'enrolled' ) );

		// Cache the result.
		self::$enrollment_count_cache[ $course_id ] = $enrollment_count;

		return $enrollment_count;
	}


	/**
	 * Get the number of in progress users for the course.
	 *
	 * @param Course $course The course object.
	 * @return int The number of in progress users.
	 *
	 * @since 1.0.0
	 */
	public function get_total_in_progress_users( $course ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$count      = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d AND progress = %s', $table_name, $course->get_id(), 'running' ) );
		return $count;
	}


	/**
	 * Get the number of completed users for the course.
	 *
	 * @param Course $course The course object.
	 * @return int The number of completed users.
	 *
	 * @since 1.0.0
	 */
	public function get_total_completed_users( $course ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$count      = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d AND progress = %s', $table_name, $course->get_id(), 'completed' ) );
		return $count;
	}


	/**
	 * Set the chapters for the course.
	 *
	 * @param Course $course The course object.
	 * @param array  $chapters The list of chapters to set, each containing 'id' and 'order_number'.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function set_chapters( $course, $chapters ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$course_id  = $course->get_id();
		foreach ( $chapters as $chapter ) {
			$chapter_id   = $chapter['id'];
			$order_number = $chapter['order_number'];

			// Check if the row exists.
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d AND chapter_id = %d', $table_name, $course_id, $chapter_id )
			);

			if ( $exists ) {
				// Update the existing row.
				$wpdb->update(
					$table_name,
					array( 'order_number' => $order_number ),
					array(
						'course_id'  => $course_id,
						'chapter_id' => $chapter_id,
					),
					array( '%d' ),
					array( '%d', '%d' )
				);
			} else {
				// Insert a new row.
				$wpdb->insert(
					$table_name,
					array(
						'course_id'    => $course_id,
						'chapter_id'   => $chapter_id,
						'order_number' => $order_number,
					),
					array( '%d', '%d', '%d' )
				);
			}
		}

		return true;
	}

	/**
	 * Get the number of lessons for the course.
	 *
	 * @param Course $course The course object.
	 * @return int The number of lessons.
	 *
	 * @since 1.0.0
	 */
	public function get_lessons_count( $course ) {
		$course_id = $course->get_id();

		// Check if result is already cached.
		if ( isset( self::$lessons_count_cache[ $course_id ] ) ) {
			return self::$lessons_count_cache[ $course_id ];
		}

		global $wpdb;
		$chapter_relationship_table = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$lessons_relationship_table = $wpdb->prefix . 'ohmylms_content_relationship';

		$lessons_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i cr
            INNER JOIN %i lr ON cr.chapter_id = lr.chapter_id
            INNER JOIN %i p ON lr.content_id = p.ID
            WHERE cr.course_id = %d
            AND lr.content_type IN ('text', 'video', 'audio', 'session')
            AND p.post_status = 'publish'",
				$chapter_relationship_table,
				$lessons_relationship_table,
				$wpdb->posts,
				$course_id
			)
		);

		// Cache the result.
		self::$lessons_count_cache[ $course_id ] = $lessons_count;

		return $lessons_count;
	}

	/**
	 * Get the number of lessons for the course.
	 *
	 * @param Course $course The course object.
	 * @return int The number of lessons.
	 *
	 * @since 1.0.0
	 */
	public function get_quiz_count( $course ) {
		$cache_key = $course->get_id();

		if ( isset( self::$quiz_count_cache[ $cache_key ] ) ) {
			return self::$quiz_count_cache[ $cache_key ];
		}

		global $wpdb;
		$chapter_relationship_table = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$lessons_relationship_table = $wpdb->prefix . 'ohmylms_content_relationship';

		$lessons_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i cr
				INNER JOIN %i lr ON cr.chapter_id = lr.chapter_id
				INNER JOIN %i p ON lr.content_id = p.ID
				WHERE cr.course_id = %d
				AND lr.content_type IN ('quiz')
				AND p.post_status = 'publish'",
				$chapter_relationship_table,
				$lessons_relationship_table,
				$wpdb->posts,
				$course->get_id()
			)
		);

		self::$quiz_count_cache[ $cache_key ] = $lessons_count;
		return $lessons_count;
	}

	/**
	 * Get the quiz IDs for the course.
	 *
	 * @param Course $course The course object.
	 * @return array The list of quiz IDs.
	 *
	 * @since 1.0.0
	 */
	public function get_quiz_ids( $course ) {
		global $wpdb;
		$chapter_relationship_table = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$lessons_relationship_table = $wpdb->prefix . 'ohmylms_content_relationship';

		$quiz_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM %i cr
				INNER JOIN %i lr ON cr.chapter_id = lr.chapter_id
				INNER JOIN %i p ON lr.content_id = p.ID
				WHERE cr.course_id = %d
				AND lr.content_type IN ('quiz')
				AND p.post_status = 'publish'",
				$chapter_relationship_table,
				$lessons_relationship_table,
				$wpdb->posts,
				$course->get_id()
			)
		);
		return $quiz_ids;
	}

	/**
	 * Get the number of lessons for the course.
	 *
	 * @param Course $course The course object.
	 * @return int The number of lessons.
	 *
	 * @since 1.0.0
	 */
	public function get_assignment_count( $course ) {
		$cache_key = $course->get_id();

		if ( isset( self::$assignment_count_cache[ $cache_key ] ) ) {
			return self::$assignment_count_cache[ $cache_key ];
		}

		global $wpdb;
		$chapter_relationship_table = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$lessons_relationship_table = $wpdb->prefix . 'ohmylms_content_relationship';

		$lessons_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i cr
				INNER JOIN %i lr ON cr.chapter_id = lr.chapter_id
				INNER JOIN %i p ON lr.content_id = p.ID
				WHERE cr.course_id = %d AND lr.content_type IN ('assignment')
				AND p.post_status = 'publish'",
				$chapter_relationship_table,
				$lessons_relationship_table,
				$wpdb->posts,
				$course->get_id()
			)
		);

		self::$assignment_count_cache[ $cache_key ] = $lessons_count;
		return $lessons_count;
	}

	/**
	 * Get all content count for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_all_content_count( $course ) {
		$cache_key = $course->get_id();

		if ( isset( self::$all_content_count_cache[ $cache_key ] ) ) {
			return self::$all_content_count_cache[ $cache_key ];
		}

		global $wpdb;
		$chapter_relationship_table = $wpdb->prefix . 'ohmylms_chapter_relationship';
		$lessons_relationship_table = $wpdb->prefix . 'ohmylms_content_relationship';

		$lessons_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i cr
				INNER JOIN %i lr ON cr.chapter_id = lr.chapter_id
				INNER JOIN %i p ON lr.content_id = p.ID
				WHERE cr.course_id = %d
				AND lr.content_type IN ('text', 'video', 'audio','session', 'quiz', 'assignment')
				AND p.post_status = 'publish'",
				$chapter_relationship_table,
				$lessons_relationship_table,
				$wpdb->posts,
				$course->get_id()
			)
		);

		self::$all_content_count_cache[ $cache_key ] = $lessons_count;
		return $lessons_count;
	}


	/**
	 * Check course access for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function check_course_access( $course ) {
		// Get the current user ID.
		$user_id   = get_current_user_id();
		$course_id = $course->get_id();
		$cache_key = $user_id . '_' . $course_id;

		// Check if result is already cached.
		if ( array_key_exists( $cache_key, self::$course_access_cache ) ) {
			return self::$course_access_cache[ $cache_key ];
		}

		global $wpdb;

		// Prepare the query.
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';
		$query      = $wpdb->prepare( 'SELECT course_id FROM %i WHERE user_id = %d AND course_id = %d AND status = %s', $table_name, $user_id, $course_id, 'enrolled' );

		// Execute the query and return the result.
		$result     = $wpdb->get_row( $query, ARRAY_A );
		$has_access = ! empty( $result ) ? true : false;

		// Cache the result.
		self::$course_access_cache[ $cache_key ] = $has_access;

		return $has_access;
	}


	/**
	 * Get certificate for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_certificate( &$course ) {
		global $wpdb;

		// Get the current user ID.
		$user_id = get_current_user_id();

		// Prepare the query.
		$table_name = $wpdb->prefix . 'ohmylms_certificate_relationship';
		$query      = $wpdb->prepare( 'SELECT certificate_id FROM %i WHERE course_id = %d', $table_name, $course->get_id() );
		// Execute the query and return the result.
		$result = $wpdb->get_row( $query, ARRAY_A );
		if ( ! empty( $result ) ) {
			$certificate = ohmylms_get_certificate( $result['certificate_id'] );
			return $certificate;
		}
	}


	/**
	 * Set certificate for Course store.
	 *
	 * @param mixed $course Course.
	 * @param mixed $certificate_id Certificate identifier.
	 */
	public function set_certificate( &$course, $certificate_id ) {
		global $wpdb;

		// Get the current course ID.
		$course_id = $course->get_id();

		// Prepare the table name.
		$table_name = $wpdb->prefix . 'ohmylms_certificate_relationship';

		// Check if a relationship already exists for the course.
		$existing_entry = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE course_id = %d', $table_name, $course_id ),
			ARRAY_A
		);

		if ( $existing_entry ) {
			// Update the existing relationship.
			$updated = $wpdb->update(
				$table_name,
				array( 'certificate_id' => $certificate_id ),
				array( 'course_id' => $course_id ),
				array( '%d' ),
				array( '%d' )
			);

			return false !== $updated; // Return true if updated successfully.
		} else {
			// Insert a new relationship.
			$inserted = $wpdb->insert(
				$table_name,
				array(
					'course_id'      => $course_id,
					'certificate_id' => $certificate_id,
				),
				array( '%d', '%d' )
			);

			return false !== $inserted; // Return true if inserted successfully.
		}
	}

	/**
	 * Search lessons within the course based on a search term.
	 *
	 * This function queries the database to find lessons that belong to the specified course
	 * and match the given search term. It returns an array of lessons with their IDs and names.
	 *
	 * @param Course $course The course object to search lessons within.
	 * @param string $term The search term to filter lessons by.
	 * @return array An array of lessons that match the search term, each containing 'value' (lesson ID) and 'label' (lesson name).
	 *
	 * @since 1.0.0
	 */
	public function search_lessons_in_course( &$course, $term ) {
		global $wpdb;

		// Table names.
		$content_table = $wpdb->prefix . 'ohmylms_content_relationship';
		$chapter_table = $wpdb->prefix . 'ohmylms_chapter_relationship';

		// Get the course ID.
		$course_id = $course->get_id();

		if ( ! empty( $term ) ) {
			$search  = '%' . $wpdb->esc_like( $term ) . '%';
			$lessons = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT content.* FROM %i AS content INNER JOIN %i AS chapter ON content.chapter_id = chapter.chapter_id WHERE chapter.course_id = %d AND content.content_id IN (SELECT ID FROM %i WHERE post_type = 'ohmylms-lesson' AND post_title LIKE %s) ORDER BY content.order_number ASC",
					$content_table,
					$chapter_table,
					$course_id,
					$wpdb->posts,
					$search
				)
			);
		} else {
			$lessons = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT content.* FROM %i AS content INNER JOIN %i AS chapter ON content.chapter_id = chapter.chapter_id WHERE chapter.course_id = %d ORDER BY content.order_number ASC',
					$content_table,
					$chapter_table,
					$course_id
				)
			);
		}

		// Filter and format the lessons.
		$filtered_lessons = array();
		if ( $lessons ) {
			foreach ( $lessons as $lesson ) {
				$post = get_post( $lesson->content_id );
				if ( ! $post || 'publish' !== $post->post_status ) {
					continue;
				}
				$lesson_obj = ohmylms_get_lesson( $lesson->content_id );
				if ( ! $lesson_obj ) {
					continue;
				}

				$filtered_lessons[] = array(
					'value' => $lesson_obj->get_id(),
					'label' => $lesson_obj->get_name(),
				);
			}
		}

		return $filtered_lessons;
	}


	/**
	 * Get students count for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_students_count( &$course ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_enrollment';

		$query = $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d AND status = %s', $table_name, $course->get_id(), 'enrolled' );

		$count = $wpdb->get_var( $query );

		return $count ? intval( $count ) : 0;
	}

	/**
	 * Get students for Course store.
	 *
	 * @param mixed  $course Course.
	 * @param string $filter Filter.
	 * @param mixed  $sort_by Sort by.
	 * @param mixed  $start_date Start date.
	 * @param mixed  $end_date End date.
	 * @param mixed  $search Search.
	 * @param mixed  $only_completed Only completed.
	 */
	public function get_students( &$course, $filter = 'all', $sort_by = null, $start_date = null, $end_date = null, $search = null, $only_completed = null ) {
		global $wpdb;

		$where_date_filter = '';
		// Apply date filters unless it's 'all'.
		if ( 'all' !== $filter ) {
			switch ( $filter ) {
				case 'last_30_days':
					$start_date = ( new \DateTime( '-30 days' ) )->format( 'Y-m-d' );
					$end_date   = ( new \DateTime() )->format( 'Y-m-d' );
					break;
				case 'current_month':
					$start_date = ( new \DateTime( 'first day of this month' ) )->format( 'Y-m-d' );
					$end_date   = ( new \DateTime( 'last day of this month' ) )->format( 'Y-m-d' );
					break;
				case 'previous_month':
					$start_date = ( new \DateTime( 'first day of last month' ) )->format( 'Y-m-d' );
					$end_date   = ( new \DateTime( 'last day of last month' ) )->format( 'Y-m-d' );
					break;
				case 'current_year':
					$start_date = ( new \DateTime( 'first day of January this year' ) )->format( 'Y-m-d' );
					$end_date   = ( new \DateTime( 'last day of December this year' ) )->format( 'Y-m-d' );
					break;
				case 'last_12_months':
					$start_date = ( new \DateTime( '-12 months' ) )->format( 'Y-m-d' );
					$end_date   = ( new \DateTime() )->format( 'Y-m-d' );
					break;
				case 'custom':
					if ( ! $start_date || ! $end_date ) {
						return new \WP_Error( 'invalid_date', 'Start and End date are required for custom filter' );
					}
					break;
				default:
					return new \WP_Error( 'invalid_filter', 'Invalid filter type' );
			}

			// Build SQL filter for dates.
			$where_date_filter = $wpdb->prepare(
				' AND DATE(ue.start_date) BETWEEN %s AND %s',
				$start_date,
				$end_date
			);
		}

		// Main query.
		$enrollment_query = $wpdb->prepare(
			"SELECT
				ue.user_id AS student_id,
				ue.start_date,
				ue.end_date,
				ue.progress,
				ue.status
			FROM {$wpdb->prefix}ohmylms_user_enrollment ue
			WHERE ue.course_id = %d AND ue.status = %s" . $where_date_filter,
			$course->get_id(),
			'enrolled'
		);

		$results = $wpdb->get_results( $enrollment_query, ARRAY_A );

		if ( empty( $results ) ) {
			return array();
		}

		$chapters = $course->get_chapters();

		// Calculate completion rate and duration for each student.
		$students = array_map(
			function ( $student ) use ( $course, $chapters ) {

				$user_data        = get_userdata( $student['student_id'] );
				$student['name']  = $user_data ? $user_data->display_name : 'Unknown';
				$student['email'] = $user_data ? $user_data->user_email : 'Unknown';

				// Fetch profile image (uses Gravatar or a custom user meta field if available).
				$student['profile_image'] = get_avatar_url( $student['student_id'], array( 'size' => 96 ) );

				$student_obj     = new \OhMyLMS\Data\Student( $student['student_id'] );
				$completion_rate = $student_obj->get_over_all_completion_rate( $course->get_id() );

				// Calculate duration only if the course is completed.
				$duration = PHP_INT_MAX; // Default for students not completed.
				if ( 'completed' === $student['progress'] && '0000-00-00 00:00:00' !== $student['start_date'] && '0000-00-00 00:00:00' !== $student['end_date'] ) {
					$start_time = strtotime( $student['start_date'] );
					$end_time   = strtotime( $student['end_date'] );
					$duration   = $end_time - $start_time;
				}

				$student['completion_rate']     = $completion_rate;
				$student['is_completed']        = 100 == (int) $completion_rate ? true : false;
				$student['completion_duration'] = $duration;

				$contents = array();
				foreach ( $chapters as $chapter ) {
					$chapter  = ohmylms_get_chapter( $chapter['id'] );
					$lessons  = $chapter->get_lessons();
					$contents = array_merge( $contents, $lessons );
					// array_push( $contents, $lessons[0] ).

				}
				$student['skipped_quizzes']     = array();
				$student['skipped_assignments'] = array();
				foreach ( $contents as $index => &$item ) {
					if ( in_array( $item['type'], array( 'assignment', 'quiz' ) ) && ! $student_obj->maybe_completed( $item['id'] ) ) {
						// Check next contents for completion.
						$skipped = false;
						for ( $j = $index + 1; $j < count( $contents ); $j++ ) {
							if ( $student_obj->maybe_completed( $contents[ $j ]['id'] ) ) {
								$skipped = true;
								break;
							}
						}

						if ( $skipped ) {
							if ( 'quiz' === $item['type'] ) {
								$student['skipped_quizzes'][] = $item;
							}

							if ( 'assignment' === $item['type'] ) {
								$student['skipped_assignments'][] = $item;
							}
						}
					}
				}

				$student['is_reminder_sent']   = $student_obj->maybe_reminder_sent( $course->get_id() );
				$student['last_reminder_sent'] = $student_obj->get_last_reminder( $course->get_id() );
				return $student;
			},
			$results
		);

		// Sort students by completion rate and then by duration.
		usort(
			$students,
			function ( $a, $b ) {
				if ( $a['completion_rate'] === $b['completion_rate'] ) {
					return $a['completion_duration'] <=> $b['completion_duration'];
				}
				return $b['completion_rate'] <=> $a['completion_rate'];
			}
		);

		// Add position to each student.
		$position = 1;
		foreach ( $students as &$student ) {
			$updated_positon             = $position++;
			$student['position']         = $updated_positon;
			$student['position_in_text'] = $this->get_position_in_text( $updated_positon );
		}

		// Sort students based on the $sort_by param.
		usort(
			$students,
			function ( $a, $b ) use ( $sort_by ) {
				switch ( $sort_by ) {
					case 'name':
						return strcasecmp( $a['name'], $b['name'] );

					case 'start_date':
						$timeA = strtotime( $a['start_date'] );
						$timeB = strtotime( $b['start_date'] );
						return $timeA <=> $timeB;

					default:
						// Default: by completion rate DESC, then duration ASC.
						if ( $a['completion_rate'] === $b['completion_rate'] ) {
							return $a['completion_duration'] <=> $b['completion_duration'];
						}
						return $b['completion_rate'] <=> $a['completion_rate'];
				}
			}
		);

		// If search is provided, filter by name or email.
		if ( $search ) {
			$search_lower = strtolower( $search );
			$students     = array_filter(
				$students,
				function ( $student ) use ( $search_lower ) {
					return strpos( strtolower( $student['name'] ), $search_lower ) !== false
					|| strpos( strtolower( $student['email'] ), $search_lower ) !== false;
				}
			);
			// Reindex array to avoid gaps in keys after filter.
			$students = array_values( $students );
		}

		if ( null !== $only_completed ) {
			// Filter only completed students if requested.
			if ( $only_completed ) {
				$students = array_filter(
					$students,
					function ( $student ) {
						return true === $student['is_completed'];
					}
				);
				$students = array_values( $students ); // Reindex array.
			}

			if ( ! $only_completed ) {
				$students = array_filter(
					$students,
					function ( $student ) {
						return false === $student['is_completed'];
					}
				);
				$students = array_values( $students ); // Reindex array.
			}
		}
		return $students;
	}


	/**
	 * Convert a numeric position into ordinal text (e.g., 1 => "1st", 2 => "2nd").
	 *
	 * @param int $position Position.
	 * @return string
	 */
	private function get_position_in_text( $position ) {
		$suffixes = array( 'th', 'st', 'nd', 'rd' );
		$mod100   = $position % 100;
		$suffix   = ( $mod100 >= 11 && $mod100 <= 13 ) ? 'th' : ( $suffixes[ $position % 10 ] ?? 'th' );
		return $position . $suffix;
	}

	/**
	 * Get cohort data
	 *
	 * This function retrieves all cohorts associated with a specific course.
	 *
	 * @param Course|int $course The course object or ID for which to retrieve cohorts.
	 * @return array An array of cohorts, each represented as an associative array with cohort details.
	 * @since 1.0.0
	 */
	public function get_cohort( &$course ) {
		if ( 'cohort-based' !== $course->get_type() ) {
			return array(); // Return empty array if the course is not cohort-based.
		}
		global $wpdb;
		$table     = $wpdb->prefix . 'ohmylms_cohorts';
		$course_id = is_object( $course ) && method_exists( $course, 'get_id' ) ? $course->get_id() : (int) $course;
		$results   = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE course_id = %d ORDER BY start_date ASC', $table, $course_id ),
			ARRAY_A
		);
		$cohorts   = array();
		foreach ( $results as $row ) {
			$capacity = null;
			if ( isset( $row['capacity'] ) && is_numeric( $row['capacity'] ) ) {
				$capacity = (int) $row['capacity'];
			}
			$cohorts[] = array(
				'id'                  => (int) $row['id'],
				'start_date'          => ! empty( $row['start_date'] ) ? date( 'Y-m-d\TH:i:s', strtotime( $row['start_date'] ) ) : '',
				'end_date'            => ! empty( $row['end_date'] ) ? date( 'Y-m-d\TH:i:s', strtotime( $row['end_date'] ) ) : '',
				'enrollment_deadline' => ! empty( $row['enrollment_end'] ) ? date( 'Y-m-d\TH:i:s', strtotime( $row['enrollment_end'] ) ) : '',
				'has_capacity'        => ! empty( $row['has_capacity'] ) ? true : false,
				'capacity'            => $capacity,
			);
		}
		return $cohorts;
	}

	/**
	 * Get the URL of the community space associated with the course.
	 *
	 * @param mixed $course Course.
	 * @return string|void|null
	 * @since 1.0.0
	 */
	public function get_space_url( &$course ) {
		if ( class_exists( '\OhMyLMS\Integrations\Community\Includes\Repository\SpaceRepository' ) ) {
			$space_repository = new \OhMyLMS\Integrations\Community\Includes\Repository\SpaceRepository();
			$space_id         = $space_repository->get_space_id_by_course_id( $course->get_id() );
			if ( $space_id ) {
				$space = $space_repository->get_by_id( $space_id );
				if ( $space && isset( $space->slug ) ) {
					return \OhMyLMS\Integrations\Community\Includes\Helper\CommunityHelper::get_default_channel_url( $space->slug, $space->id );
				}
			}
		}
	}

	/**
	 * Get resources for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_resources( &$course ) {
		$resources = array();
		// Get course-level resources.
		$course_resource = method_exists( $course, 'get_download_resource' ) ? $course->get_download_resource() : null;
		if ( $course_resource ) {
			$resources[] = $course_resource;
		}

		// Get chapters and return early if none exist.
		$chapters = $this->get_chapters( $course, 'objects' );
		if ( ! $chapters ) {
			return $resources;
		}

		// Iterate through chapters and their lessons.
		foreach ( $chapters as $chapter ) {
			$lessons = $chapter->get_lessons( 'objects' );
			if ( ! $lessons ) {
				continue;
			}

			// Collect valid lesson resources.
			foreach ( $lessons as $lesson ) {
				if ( ! isset( $lesson->object_type ) || ! in_array( $lesson->object_type, array( 'lesson', 'assignment' ), true ) ) {
					continue;
				}

				$resource = method_exists( $lesson, 'get_download_resource' ) ? $lesson->get_download_resource() : null;
				if ( $resource ) {
					$resources[] = $resource;
				}
			}
		}

		return $resources;
	}

	/**
	 * Get leaderboard disabled for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_leaderboard_disabled( &$course ) {
		$leaderboard_disabled = get_post_meta( $course->get_id(), '_leaderboard_disabled', 'no' );
		return $leaderboard_disabled;
	}

	/**
	 * Get funnel steps for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_funnel_steps( &$course ) {
		$funnel_steps = get_post_meta( $course->get_id(), '_funnel_steps', true );
		return $funnel_steps ? $funnel_steps : array();
	}

	/**
	 * Get point disabled for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_point_disabled( &$course ) {
		$point_disabled = get_post_meta( $course->get_id(), '_point_disabled', 'no' );
		return $point_disabled;
	}

	/**
	 * Get reward disabled for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_reward_disabled( &$course ) {
		$reward_disabled = get_post_meta( $course->get_id(), '_reward_disabled', 'no' );
		return $reward_disabled;
	}

	/**
	 * Get purchase point for Course store.
	 *
	 * @param mixed $course Course.
	 */
	public function get_purchase_point( &$course ) {
		return get_post_meta( $course->get_id(), '_purchase_point', true );
	}
}
