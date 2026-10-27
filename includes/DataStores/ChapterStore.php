<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class ChapterStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Chapter;
use OhMyLMS\Data\Student;

defined( 'ABSPATH' ) || exit;

/**
 * Class ChapterStore
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class ChapterStore extends DataStore {

	/**
	 * Cache for lessons to prevent duplicate queries.
	 *
	 * @var array
	 */
	private static $lessons_cache = array();

	/**
	 * Cache for raw content relationship data from database.
	 *
	 * @var array
	 */
	private static $raw_contents_cache = array();

	/**
	 * Cache for all content counts.
	 *
	 * @var array
	 */
	private static $all_content_count_cache = array();

	/**
	 * Create chapter
	 *
	 * @param Chapter $chapter Chapter.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function create( &$chapter ) {

		if ( ! $chapter->get_date_created( 'edit' ) ) {
			$chapter->set_date_created( time() );
		}
		$slug = $chapter->get_slug( 'edit' );
		if ( ( ! $slug || 'untitled' === $slug ) && $chapter->get_name( 'edit' ) ) {
			$slug = $chapter->get_name( 'edit' );
		}

		$slug = $this->generate_unique_slug( $slug, OHMYLMS_CHAPTER_CPT );

		$id = wp_insert_post(
			apply_filters(
				'ohmylms_new_chapter_data',
				array(
					'post_type'     => OHMYLMS_CHAPTER_CPT,
					'post_author'   => get_current_user_id(),
					'post_status'   => $chapter->get_status() ? $chapter->get_status() : 'draft',
					'post_title'    => $chapter->get_name() ? $chapter->get_name() : __( 'Untitled', 'ohmylms' ),
					'post_content'  => $chapter->get_description(),
					'post_name'     => $slug,
					'post_parent'   => $chapter->get_parent_id( 'edit' ),
					'post_date'     => gmdate( 'Y-m-d H:i:s', $chapter->get_date_created( 'edit' )->getOffsetTimestamp() ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $chapter->get_date_created( 'edit' )->getTimestamp() ),
				)
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$chapter->set_id( $id );
			flush_rewrite_rules( true );
			$this->update_chapter_meta( $chapter );

			/**
			 * Fires after a new chapter is created.
			 *
			 * This action hook allows developers to perform additional actions after a chapter is created.
			 *
			 * @param int   $id     The ID of the newly created chapter.
			 * @param array $chapter The chapter data array, containing information about the created chapter.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_creating_new_chapter', $id, $chapter );
		}
	}


	/**
	 * Read data
	 *
	 * @param Chapter $chapter Chapter.
	 * @return mixed|void
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function read( &$chapter ) {
		$post_object = get_post( $chapter->get_id() );
		if ( ! $chapter->get_id() || ! $post_object || OHMYLMS_CHAPTER_CPT !== $post_object->post_type ) {
			return;
		}

		$chapter->set_props(
			array(
				'name'          => $post_object->post_title,
				'slug'          => $post_object->post_name,
				'status'        => $post_object->post_status,
				'date_created'  => $post_object->post_date_gmt,
				'date_modified' => $post_object->post_modified_gmt,
				'description'   => $post_object->post_content,
				'parent_id'     => $post_object->post_parent,
			)
		);

		$this->read_chapter_data( $chapter );
	}


	/**
	 * Update chapter data
	 *
	 * @param Chapter $chapter The chapter object to update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update( &$chapter ) {
		$slug = $chapter->get_slug( 'edit' );
		$slug = $this->generate_unique_slug( $slug, OHMYLMS_CHAPTER_CPT );

		$post_data = array(
			'post_content' => $chapter->get_description( 'edit' ),
			'post_title'   => $chapter->get_name( 'edit' ),
			'post_status'  => $chapter->get_status( 'edit' ) ? $chapter->get_status( 'edit' ) : 'publish',
			'post_name'    => sanitize_title( $chapter->get_name() ),
			'post_type'    => OHMYLMS_CHAPTER_CPT,
			'post_parent'  => $chapter->get_parent_id( 'edit' ),
		);
		if ( $chapter->get_date_created( 'edit' ) ) {
			$post_data['post_date']     = gmdate( 'Y-m-d H:i:s', $chapter->get_date_created( 'edit' )->getOffsetTimestamp() );
			$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $chapter->get_date_created( 'edit' )->getTimestamp() );
		}
		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );

		wp_update_post( array_merge( array( 'ID' => $chapter->get_id() ), $post_data ) );

		$this->update_post_meta( $chapter );

		/**
		 * Action hook to perform additional actions after a chapter is updated.
		 *
		 * @param int    $chapter_id The ID of the updated chapter.
		 * @param Chapter $chapter    The chapter object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_update_chapter', $chapter->get_id(), $chapter );
	}


	/**
	 * Update post meta for the chapter.
	 *
	 * @param Chapter $chapter The chapter object.
	 * @param bool    $force Whether to force the update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$chapter, $force = false ) {
		$meta_key_to_props = array();

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $chapter->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			$this->update_or_delete_post_meta( $chapter, $meta_key, $value );
		}
	}


	/**
	 * Delete the chapter
	 *
	 * @param mixed $chapter Chapter.
	 * @param array $args Operation arguments.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function delete( &$chapter, $args = array() ) {
		if ( $chapter ) {
			$chapter_id = $chapter->get_id();
			if ( $chapter_id ) {
				wp_delete_post( $chapter_id, true );
				/**
				 * Triggered after deleting a chapter.
				 *
				 * This action hook allows developers to perform additional actions after a chapter is deleted.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_after_deleting_a_chapter' );
			}
		}
	}

	/**
	 * Set the session for the chapter.
	 *
	 * @param Chapter $chapter The chapter object.
	 * @param array   $session The session to set for the chapter.
	 * @return void
	 */
	public function set_session( $chapter, $session ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_content_relationship';
		$chapter_id = $chapter->get_id();
		$wpdb->insert(
			$table_name,
			array(
				'chapter_id'   => $chapter_id,
				'content_id'   => $session['id'],
				'order_number' => $session['order_number'],
				'content_type' => 'session',
			),
			array( '%d', '%d', '%d', '%s' )
		);
		$this->invalidate_chapter_cache( $chapter_id );
	}

	/**
	 * Set the contents of the chapter.
	 *
	 * @param Chapter $chapter The chapter object.
	 * @param array   $lessons The lessons to set for the chapter.
	 * @return bool True on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function set_contents( $chapter, $lessons ) {
		if ( empty( $lessons ) ) {
			return;
		}
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_content_relationship';
		$chapter_id = $chapter->get_id();
		$values       = array( $table_name );
		$placeholders = array();
		foreach ( $lessons as $index => $lesson ) {
			$values[] = $chapter_id;
			$values[] = $lesson['id'];
			$values[] = $lesson['order_number'];
			$values[] = isset( $lesson['content_type'] ) ? $lesson['content_type'] : $lesson['type'];

			// Create placeholders for each set of values.
			$placeholders[] = '(%d, %d, %d, %s)';
		}

		// Construct the query with ON DUPLICATE KEY UPDATE.
		$insert_query = "
			INSERT INTO %i (chapter_id, content_id, order_number, content_type)
			VALUES " . implode( ', ', $placeholders ) . '
			ON DUPLICATE KEY UPDATE
			order_number = VALUES(order_number)
		';

		// Execute the query using prepared statements to prevent SQL injection.
		$wpdb->query( $wpdb->prepare( $insert_query, $values ) );
		$this->invalidate_chapter_cache( $chapter_id );

		return true;
	}

	/**
	 * Invalidate cached data for a chapter.
	 *
	 * @param int $chapter_id The chapter ID whose cache entries should be cleared.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	private function invalidate_chapter_cache( $chapter_id ) {
		self::clear_structure_cache( $chapter_id );
	}

	/**
	 * Invalidate request-local activity projections after commit or rollback.
	 *
	 * @param int $chapter_id Unit ID.
	 */
	public static function clear_structure_cache( $chapter_id ) {
		unset( self::$raw_contents_cache[ $chapter_id ] );

		// Cache keys for a chapter are formatted as "{chapter_id}_{type}", e.g. "123_array", "123_objects", "123_all_contents".
		foreach ( array_keys( self::$lessons_cache ) as $key ) {
			if ( strpos( $key, $chapter_id . '_' ) === 0 ) {
				unset( self::$lessons_cache[ $key ] );
			}
		}
	}

	/**
	 * Helper function that reads chapter data
	 *
	 * @param Chapter $chapter Chapter.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function read_chapter_data( &$chapter ) {
		$meta_key_to_props = array();
		$set_props         = array();
		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value         = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;
			$set_props[ $prop ] = maybe_unserialize( $meta_value );
		}

		$chapter->set_props( $set_props );
	}


	/**
	 * Helper function that updates chapter meta
	 *
	 * @param Chapter $chapter Chapter.
	 * @param bool    $force Force.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_chapter_meta( &$chapter, $force = false ) {
		$meta_key_to_props = array();
		$meta_key_to_props = apply_filters( 'ohmylms_chapter_meta_key_to_props', $meta_key_to_props );
		$props_to_update   = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $chapter->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			$this->update_or_delete_post_meta( $chapter, $meta_key, $value );
		}
	}


	/**
	 * Get the lessons of the chapter.
	 *
	 * @param Chapter $chapter The chapter object.
	 * @return array The list of lessons.
	 *
	 * @since 1.0.0
	 * @param string  $return_type Return type.
	 */
	public function get_lessons( &$chapter, $return_type = 'array' ) {
		$chapter_id = $chapter->get_id();
		$cache_key  = $chapter_id . '_' . $return_type;

		// Check if result is already cached.
		if ( isset( self::$lessons_cache[ $cache_key ] ) ) {
			return self::$lessons_cache[ $cache_key ];
		}

		// Check if raw database results are cached.
		if ( ! isset( self::$raw_contents_cache[ $chapter_id ] ) ) {
			global $wpdb;
			$table_name                              = $wpdb->prefix . 'ohmylms_content_relationship';
			self::$raw_contents_cache[ $chapter_id ] = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE chapter_id = %d ORDER BY order_number ASC', $table_name, $chapter_id )
			);
		}

		$lessons          = self::$raw_contents_cache[ $chapter_id ];
		$filtered_lessons = array();

		if ( $lessons ) {
			foreach ( $lessons as $lesson ) {
				$lesson_obj = null;
				if ( 'assignment' === $lesson->content_type ) {
					if ( function_exists( 'ohmylms_get_assignment' ) ) {
						$lesson_obj = ohmylms_get_assignment( $lesson->content_id );
					}
				} elseif ( 'quiz' === $lesson->content_type ) {
					if ( function_exists( 'ohmylms_get_quiz' ) ) {
						$lesson_obj = ohmylms_get_quiz( $lesson->content_id );
					}
				} elseif ( 'session' === $lesson->content_type ) {
					if ( function_exists( 'ohmylms_get_session' ) ) {
						$lesson_obj = ohmylms_get_session( $lesson->content_id );
					}
				} elseif ( in_array( $lesson->content_type, array( 'text', 'video', 'audio' ) ) ) {
						$lesson_obj = ohmylms_get_lesson( $lesson->content_id );
				}

				if ( $lesson_obj && 'publish' !== $lesson_obj->get_status() ) {
					$lesson_id = $lesson_obj->get_id();
					$post      = get_post( $lesson_id );

					// check post author.
					if ( $post && (int) $post->post_author !== (int) get_current_user_id() ) {
						continue;
					}
				}

				if ( 'objects' === $return_type ) {
					$filtered_lessons[] = $lesson_obj;
					continue;
				}

				if ( $lesson_obj ) {
					$lesson_data = array(
						'id'           => $lesson_obj->get_id(),
						'name'         => $lesson_obj->get_name(),
						'description'  => $lesson_obj->get_description(),
						'type'         => $lesson->content_type,
						'order_number' => $lesson->order_number,
					);

					if ( 'quiz' !== $lesson->content_type && 'session' !== $lesson->content_type ) {
						$lesson_data['prerequisites'] = $lesson_obj->get_prerequisites();
					}

					if ( 'session' === $lesson->content_type ) {
						$lesson_data['platform'] = get_post_meta( $lesson_obj->get_id(), '_platform', true );
					}

					$automation                    = get_post_meta( $lesson_obj->get_id(), '_mm_automation_id', true );
					$lesson_data['has_automation'] = is_array( $automation ) && count( $automation ) ? true : false;

					$filtered_lessons[] = $lesson_data;
				}
			}
		}

		// Cache the result.
		self::$lessons_cache[ $cache_key ] = $filtered_lessons;

		return $filtered_lessons;
	}

	/**
	 * Search lessons within the chapter based on a search term.
	 *
	 * This function queries the database to find lessons that belong to the specified chapter
	 * and match the given search term. It returns an array of lessons with their IDs and names.
	 *
	 * @param Chapter $chapter The chapter object to search lessons within.
	 * @param string  $term The search term to filter lessons by.
	 * @return array An array of lessons that match the search term, each containing 'value' (lesson ID) and 'label' (lesson name).
	 *
	 * @since 1.0.0
	 */
	public function search_lessons_in_chapter( &$chapter, $term ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_content_relationship';
		$chapter_id = $chapter->get_id();

		if ( ! empty( $term ) ) {
			$search  = '%' . $wpdb->esc_like( $term ) . '%';
			$lessons = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE chapter_id = %d AND content_id IN (SELECT ID FROM %i WHERE post_type = 'ohmylms-lesson' AND post_title LIKE %s) ORDER BY order_number ASC",
					$table_name,
					$chapter_id,
					$wpdb->posts,
					$search
				)
			);
		} else {
			$lessons = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE chapter_id = %d ORDER BY order_number ASC', $table_name, $chapter_id )
			);
		}

		$filtered_lessons = array();
		if ( $lessons ) {
			foreach ( $lessons as $lesson ) {
				$lesson_obj         = ohmylms_get_lesson( $lesson->content_id );
				$filtered_lessons[] = array(
					'value' => $lesson_obj->get_id(),
					'label' => $lesson_obj->get_name(),
				);
			}
		}
		return $filtered_lessons;
	}

	/**
	 * Get all contents for Chapter store.
	 *
	 * @param mixed $chapter Chapter.
	 */
	public function get_all_contents( &$chapter ) {
		$chapter_id = $chapter->get_id();
		$cache_key  = $chapter_id . '_all_contents';

		if ( isset( self::$lessons_cache[ $cache_key ] ) ) {
			return self::$lessons_cache[ $cache_key ];
		}

		// Check if raw database results are cached.
		if ( ! isset( self::$raw_contents_cache[ $chapter_id ] ) ) {
			global $wpdb;
			$table_name                              = $wpdb->prefix . 'ohmylms_content_relationship';
			self::$raw_contents_cache[ $chapter_id ] = $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE chapter_id = %d ORDER BY order_number ASC', $table_name, $chapter_id )
			);
		}

		$lessons          = self::$raw_contents_cache[ $chapter_id ];
		$filtered_lessons = array();
		if ( $lessons ) {
			foreach ( $lessons as $lesson ) {
				$filtered_lessons[] = $lesson;
			}
		}

		self::$lessons_cache[ $cache_key ] = $filtered_lessons;
		return $filtered_lessons;
	}


	/**
	 * Get lesson count for Chapter store.
	 *
	 * @param mixed $chapter Chapter.
	 */
	public function get_lesson_count( &$chapter ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_content_relationship';
		$chapter_id = $chapter->get_id();
		$lessons    = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE chapter_id = %d AND content_type IN ("text", "video", "audio", "session") ORDER BY order_number ASC', $table_name, $chapter_id ) );
		return count( $lessons );
	}

	/**
	 * Get all content count for Chapter store.
	 *
	 * @param mixed $chapter Chapter.
	 */
	public function get_all_content_count( &$chapter ) {
		$chapter_id = $chapter->get_id();

		if ( isset( self::$all_content_count_cache[ $chapter_id ] ) ) {
			return self::$all_content_count_cache[ $chapter_id ];
		}

		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_content_relationship';
		$posts_table = $wpdb->posts;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(r.content_id)
				FROM %i r
				INNER JOIN %i p ON r.content_id = p.ID
				WHERE r.chapter_id = %d AND p.post_status = %s',
				$table_name,
				$posts_table,
				$chapter_id,
				'publish'
			)
		);

		$result                                       = intval( $count );
		self::$all_content_count_cache[ $chapter_id ] = $result;
		return $result;
	}

	/**
	 * Get content by order for Chapter store.
	 *
	 * @param mixed $chapter Chapter.
	 * @param mixed $order Order.
	 */
	public function get_content_by_order( &$chapter, $order ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_content_relationship';
		$chapter_id = $chapter->get_id();
		$lesson     = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE chapter_id = %d AND order_number = %d', $table_name, $chapter_id, $order )
		);
		return $lesson;
	}

	/**
	 * Completed content count for Chapter store.
	 *
	 * @param mixed $chapter Chapter.
	 * @param mixed $student_id Student identifier.
	 */
	public function completed_content_count( &$chapter, $student_id ) {
		$get_all_content = $this->get_all_contents( $chapter );
		$completed_count = 0;
		foreach ( $get_all_content as $content ) {
			$content_id   = $content->content_id;
			$content_type = $content->content_type;
			$lesson_obj   = null;
			if ( 'assignment' === $content_type ) {
				if ( function_exists( 'ohmylms_get_assignment' ) ) {
					$lesson_obj = ohmylms_get_assignment( $content_id );
				}
			} elseif ( 'quiz' === $content_type ) {
				if ( function_exists( 'ohmylms_get_quiz' ) ) {
					$lesson_obj = ohmylms_get_quiz( $content_id );
				}
			} elseif ( 'session' === $content_type ) {
				if ( function_exists( 'ohmylms_get_session' ) ) {
					$lesson_obj = ohmylms_get_session( $content_id );
				}
			} elseif ( function_exists( 'ohmylms_get_lesson' ) ) {
				$lesson_obj = ohmylms_get_lesson( $content_id );
			}

			if ( $lesson_obj ) {
				$student = new Student( $student_id );
				if ( $student->maybe_completed( $lesson_obj->get_id() ) ) {
					++$completed_count;
				}
			}
		}
		return $completed_count;
	}


	/**
	 * Total completion rate for Chapter store.
	 *
	 * @param mixed $chapter Chapter.
	 * @param mixed $student_id Student identifier.
	 */
	public function total_completion_rate( $chapter, $student_id ) {
		$completed_count = $this->completed_content_count( $chapter, $student_id );

		$total_content   = $this->get_all_content_count( $chapter );
		$completion_rate = 0;
		if ( 0 < $total_content ) {
			$completion_rate = number_format( ( $completed_count / $total_content ) * 100, 0 );
		}
		return $completion_rate;
	}

	/**
	 * Maybe content all completed for Chapter store.
	 *
	 * @param mixed $chapter Chapter.
	 * @param mixed $student_id Student identifier.
	 */
	public function maybe_content_all_completed( &$chapter, $student_id ) {
		$completed_count = $this->completed_content_count( $chapter, $student_id );
		$total_content   = $this->get_all_content_count( $chapter );
		if ( $total_content === $completed_count ) {
			return true;
		}
		return false;
	}
}
