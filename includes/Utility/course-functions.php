<?php
/**
 * Course functions.
 *
 * @package OhMyLMS
 */


/**
 * Get course object
 *
 * @param mixed $course_id Course identifier.
 * @return bool|\OhMyLMS\Data\Course
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_course( $course_id ) {
	// Ensure the plugin is loaded and course_factory exists.
	if ( function_exists( 'OhMyLMS' ) && isset( ohmylms()->course_factory ) && ohmylms()->course_factory ) {
		return ohmylms()->course_factory->get_course( $course_id );
	}

	// Fallback: return null if course_factory is not ready.
	return null;
}

/**
 * Ohmylms get course id by content id for Course-functions.
 *
 * @param mixed $content_id Content identifier.
 */
function ohmylms_get_course_id_by_content_id( $content_id ) {
	global $wpdb;

	$table_content = $wpdb->prefix . 'ohmylms_content_relationship';
	$table_chapter = $wpdb->prefix . 'ohmylms_chapter_relationship';

	$query     = $wpdb->prepare(
		'SELECT cr.course_id
		FROM %i AS c
		JOIN %i AS cr
		ON c.chapter_id = cr.chapter_id
		WHERE c.content_id = %d',
		$table_content,
		$table_chapter,
		$content_id
	);
	$course_id = $wpdb->get_var( $query );

	// A course whose published learning program places this content counts too (see Placements).
	$course_id = \OhMyLMS\Learning\Placements::resolve( $content_id, $course_id, 0 );
	if ( null === $course_id || ! $course_id ) {
		return false;
	}

	return $course_id;
}

/**
 * Get the first lesson of a course.
 *
 * @param int $course_id The ID of the course.
 * @return string The permalink of the first lesson.
 * @since 1.0.0
 */
function ohmylms_get_course_first_lesson_url( $course_id ) {
	$course   = ohmylms_get_course( $course_id );
	$chapters = $course->get_chapters();
	if ( is_array( $chapters ) ) {
		foreach ( $chapters as $chapter_array ) {
			if ( ! isset( $chapter_array['id'] ) ) {
				continue;
			}
			$chapter = ohmylms_get_chapter( $chapter_array['id'] );

			if ( ! $chapter ) {
				continue;
			}

			$lessons = $chapter->get_lessons();
			if ( is_array( $lessons ) ) {
				foreach ( $lessons as $lesson_array ) {
					if ( ! isset( $lesson_array['id'] ) ) {
						continue;
					}
					if ( isset( $lesson_array['type'] ) && 'quiz' === $lesson_array['type'] ) {
						$quiz = ohmylms_get_quiz( $lesson_array['id'] );
						if ( $quiz ) {
							return ohmylms_get_pretty_content_permalink( $quiz->get_id() ); // Return the URL of the first incomplete quiz.
						}
					}

					if ( isset( $lesson_array['type'] ) && 'assignment' === $lesson_array['type'] ) {
						$assignment = ohmylms_get_assignment( $lesson_array['id'] );
						if ( $assignment ) {
							return ohmylms_get_pretty_content_permalink( $assignment->get_id() ); // Return the URL of the first incomplete assignment.
						}
					}

					$lesson = ohmylms_get_lesson( $lesson_array['id'] );
					if ( ! $lesson ) {
						continue;
					}
					return ohmylms_get_pretty_content_permalink( $lesson->get_id() ); // Return the URL of the first incomplete lesson.

				}
			}
		}
	}

	global $wpdb;
	$sql       = $wpdb->prepare(
		'SELECT content_rel.content_id
        FROM %i AS wp_posts
        INNER JOIN %i AS chapter_rel
            ON wp_posts.ID = chapter_rel.course_id
        INNER JOIN %i AS content_rel
            ON chapter_rel.chapter_id = content_rel.chapter_id
        INNER JOIN %i AS lesson_post
            ON content_rel.content_id = lesson_post.ID
        WHERE wp_posts.ID = %d
        AND lesson_post.post_status = \'publish\'
        ORDER BY content_rel.order_number ASC
        LIMIT 1',
		$wpdb->posts,
		$wpdb->prefix . 'ohmylms_chapter_relationship',
		$wpdb->prefix . 'ohmylms_content_relationship',
		$wpdb->posts,
		$course_id
	);
	$lesson_id = $wpdb->get_var( $sql );
	return $lesson_id ? ohmylms_get_pretty_content_permalink( $lesson_id ) : '';
}

/**
 * Get course ID by chapter ID.
 *
 * @param int $chapter_id The ID of the chapter.
 * @return int|null The course ID or null if not found.
 *
 * @since 1.0.0
 */
function ohmylms_get_course_id_by_chapter_id( $chapter_id ) {
	static $cache = array();

	if ( array_key_exists( $chapter_id, $cache ) ) {
		return $cache[ $chapter_id ];
	}

	global $wpdb;
	$sql    = $wpdb->prepare( 'SELECT course_id FROM %i WHERE chapter_id = %d LIMIT 1', $wpdb->prefix . 'ohmylms_chapter_relationship', $chapter_id );
	$result = $wpdb->get_var( $sql );

	if ( $result ) {
		$cache[ $chapter_id ] = $result;
	}
	return $result;
}

/**
 * Course IDs that a course-list group slug refers to.
 *
 * Course categories and tags were replaced by the curriculum and Course Bundles, so the group slug is
 * `c<id>` for a curriculum item (including everything below it) or `t<id>` for a Course Bundle.
 * Course lists pass the first selected filter value here.
 *
 * @param string|null $slug Group slug, or 'all'/empty for no grouping.
 *
 * @return int[]|null Null when no group applies; an empty array when the group has no courses.
 */
function ohmylms_course_ids_for_group( $slug ) {
	if ( ! is_string( $slug ) || '' === $slug || 'all' === $slug ) {
		return null;
	}

	return array_values(
		array_unique(
			array_merge(
				\OhMyLMS\Curriculum\Placement::course_ids_for_slugs( array( $slug ), 'item' ),
				\OhMyLMS\Curriculum\Placement::course_ids_for_slugs( array( $slug ), 'bundle' )
			)
		)
	);
}

/**
 * Retrieves the best selling courses.
 *
 * This function fetches the courses that have the highest number of sales.
 *
 * @return array An array of best selling courses.
 * @param mixed $category Category.
 */
function ohmylms_get_best_selling_course_ids( $category = null ) {
	global $wpdb;
	$group = ohmylms_course_ids_for_group( $category );
	if ( array() === $group ) {
		return array();
	}
	// Base query to fetch best-selling courses.
	$query = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->prefix}ohmylms_order_itemmeta m ON p.ID = m.meta_value
    ";

	// Add conditions for post type, status, and course ID meta key.
	$query .= '
        WHERE p.post_type = %s 
          AND p.post_status = %s
          AND m.meta_key = %s
    ';

	// Limit to the courses of the selected curriculum item or Course Bundle.
	if ( null !== $group ) {
		$query .= ' AND p.ID IN (' . implode( ',', array_map( 'intval', $group ) ) . ') ';
	}

	// Group by course ID and order by sales count in descending order.
	$query .= '
        GROUP BY p.ID
        ORDER BY COUNT(m.meta_value) DESC
    ';

	$prepared_query = $wpdb->prepare( $query, 'ohmylms-course', 'publish', '_course_id' );

	// Execute the query and return the results.
	$results = $wpdb->get_col( $prepared_query ); // Fetch only course IDs.

	return $results;
}

/**
 * Retrieves the top rated courses.
 *
 * This function fetches the courses that have the highest ratings.
 *
 * @return array An array of top rated courses.
 * @param mixed $category Category.
 */
function ohmylms_get_top_rated_course_ids( $category = null ) {
	global $wpdb;
	$group = ohmylms_course_ids_for_group( $category );
	if ( array() === $group ) {
		return array();
	}
	// Base query to fetch courses with average rating.
	$query = "
        SELECT p.ID AS course_id
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
    ";

	// Adding conditions for post type, status, and average rating meta key.
	$query .= '
        WHERE p.post_type = %s
          AND p.post_status = %s
          AND pm.meta_key = %s
    ';

	// Limit to the courses of the selected curriculum item or Course Bundle.
	if ( null !== $group ) {
		$query .= ' AND p.ID IN (' . implode( ',', array_map( 'intval', $group ) ) . ') ';
	}

	// Order by average rating in descending order.
	$query .= '
        ORDER BY CAST(pm.meta_value AS DECIMAL(10,2)) DESC
    ';

	$prepared_query = $wpdb->prepare( $query, 'ohmylms-course', 'publish', '_average_rating' );

	// Execute the query and return the results.
	$results = $wpdb->get_col( $prepared_query ); // Fetch only course IDs.

	return $results;
}

/**
 * Retrieves the top reviewed courses.
 *
 * This function retrieves the courses that have received the highest number of reviews.
 *
 * @return array An array of top reviewed courses.
 * @param mixed $category Category.
 */
function ohmylms_get_top_reviewed_course_ids( $category = null ) {
	global $wpdb;
	$group = ohmylms_course_ids_for_group( $category );
	if ( array() === $group ) {
		return array();
	}
	// Base query to fetch courses with average rating.
	$query = "
        SELECT p.ID AS course_id
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
    ";

	// Adding conditions for post type, status, and review count.
	$query .= '
        WHERE p.post_type = %s
          AND p.post_status = %s
          AND pm.meta_key = %s
    ';

	// Limit to the courses of the selected curriculum item or Course Bundle.
	if ( null !== $group ) {
		$query .= ' AND p.ID IN (' . implode( ',', array_map( 'intval', $group ) ) . ') ';
	}

	// Order by review count (assuming _review_count stores numeric values).
	$query .= '
        ORDER BY CAST(pm.meta_value AS DECIMAL(10,2)) DESC
    ';

	$prepared_query = $wpdb->prepare( $query, 'ohmylms-course', 'publish', '_review_count' );

	// Execute the query and return the results.
	$results = $wpdb->get_col( $prepared_query ); // Fetch only course IDs.
	return $results;
}


/**
 * Retrieves the list of free courses.
 *
 * @return array The array of free courses.
 * @param mixed $category Category.
 */
function ohmylms_get_free_course_ids( $category = null ) {
	global $wpdb;
	$group = ohmylms_course_ids_for_group( $category );
	if ( array() === $group ) {
		return array();
	}
	// Base query to fetch free courses.
	$query = "
        SELECT p.ID AS course_id
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
    ";

	// Adding conditions for post type, status, and free price type.
	$query .= '
        WHERE p.post_type = %s
          AND p.post_status = %s
          AND pm.meta_key = %s
          AND pm.meta_value = %s
    ';

	// Limit to the courses of the selected curriculum item or Course Bundle.
	if ( null !== $group ) {
		$query .= ' AND p.ID IN (' . implode( ',', array_map( 'intval', $group ) ) . ') ';
	}

	$prepared_query = $wpdb->prepare( $query, 'ohmylms-course', 'publish', '_price_type', 'free' );

	// Execute the query and return the results.
	$results = $wpdb->get_col( $prepared_query ); // Fetch only course IDs.
	return $results;
}


/**
 * Retrieves the list of paid courses.
 *
 * @return array The list of paid courses.
 * @param mixed $category Category.
 */
function ohmylms_get_paid_course_ids( $category = null ) {
	global $wpdb;
	$group = ohmylms_course_ids_for_group( $category );
	if ( array() === $group ) {
		return array();
	}
	// Base query to fetch paid courses.
	$query = "
        SELECT p.ID AS course_id
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id
    ";

	// Adding conditions for post type, status, and paid price type.
	$query .= '
        WHERE p.post_type = %s
          AND p.post_status = %s
          AND pm.meta_key = %s
          AND pm.meta_value = %s
    ';

	// Limit to the courses of the selected curriculum item or Course Bundle.
	if ( null !== $group ) {
		$query .= ' AND p.ID IN (' . implode( ',', array_map( 'intval', $group ) ) . ') ';
	}

	$prepared_query = $wpdb->prepare( $query, 'ohmylms-course', 'publish', '_price_type', 'paid' );

	// Execute the query and return the results.
	$results = $wpdb->get_col( $prepared_query ); // Fetch only course IDs.
	return $results;
}



/**
 * Retrieves the list of recent courses.
 *
 * @return array The list of recent course IDs.
 * @param mixed $category Category.
 */
function ohmylms_get_recent_course_ids( $category = null ) {
	global $wpdb;

	$group = ohmylms_course_ids_for_group( $category );
	if ( array() === $group ) {
		return array();
	}
	// Base query to fetch recent courses.
	$query = "
        SELECT p.ID AS course_id
        FROM {$wpdb->posts} p
    ";

	// Adding conditions for post type and post status.
	$query .= '
        WHERE p.post_type = %s
          AND p.post_status = %s
    ';

	// Limit to the courses of the selected curriculum item or Course Bundle.
	if ( null !== $group ) {
		$query .= ' AND p.ID IN (' . implode( ',', array_map( 'intval', $group ) ) . ') ';
	}

	// Order by post_date to get the most recent courses.
	$query .= ' ORDER BY p.post_date DESC ';

	$prepared_query = $wpdb->prepare( $query, 'ohmylms-course', 'publish' );

	// Execute the query and return the results.
	$results = $wpdb->get_col( $prepared_query ); // Fetch only course IDs.
	return $results;
}
