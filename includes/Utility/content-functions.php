<?php
/**
 * Content functions.
 *
 * @package OhMyLMS
 */


/**
 * Modify the permalink structure for lessons.
 *
 * @param string  $permalink The original permalink.
 * @param WP_Post $post The post object.
 * @return string The modified permalink.
 *
 * @since 1.0.0
 */
function ohmylms_content_link( $permalink, $post ) {
	$post_types = array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-assignment' );
	if ( ! in_array( $post->post_type, $post_types ) ) {
		return $permalink;
	}
	$course_id = ohmylms_get_course_by_content_id( $post->ID );
	$course    = ohmylms_get_course( $course_id );

	if ( ! $course ) {
		return $permalink;
	}
	return $course->get_content_link( $post->ID );
}

add_filter( 'post_type_link', 'ohmylms_content_link', 10, 2 );


/**
 * Get pretty permalink for ohmylms-lesson, ohmylms-quiz, or ohmylms-assignment, even if parent course is draft.
 *
 * @param int $content_id The ID of the lesson, quiz, or assignment.
 * @return string The pretty permalink, or empty string if not found.
 *
 * @since 1.0.0
 */
function ohmylms_get_pretty_content_permalink( $content_id ) {

	$post = get_post( $content_id );
	if ( ! $post ) {
		return '';
	}
	$post_type   = $post->post_type;
	$valid_types = array( 'ohmylms-lesson', 'ohmylms-quiz', 'ohmylms-assignment' );
	if ( ! in_array( $post_type, $valid_types ) ) {
		return get_permalink( $content_id );
	}

	$course_id = ohmylms_get_course_by_content_id( $content_id );
	if ( ! $course_id ) {
		return get_permalink( $content_id );
	}
	$course = get_post( $course_id );
	if ( ! $course ) {
		return get_permalink( $content_id );
	}

	$content_slug = $post->post_name;

	// If permalinks are set to Plain, return a custom query URL.
	if ( ! get_option( 'permalink_structure' ) ) {
		if ( 'ohmylms-lesson' === $post_type ) {
			return home_url( '/?ohmylms-lesson=' . $content_slug );
		} elseif ( 'ohmylms-quiz' === $post_type ) {
			return home_url( '/?ohmylms-quiz=' . $content_slug );
		} elseif ( 'ohmylms-assignment' === $post_type ) {
			return home_url( '/?ohmylms-assignment=' . $content_slug );
		}
	}

	$permalink_structure = function_exists( 'ohmylms_get_permalink_structure' ) ? ohmylms_get_permalink_structure() : array();
	$course_base         = isset( $permalink_structure['course_base'] ) ? $permalink_structure['course_base'] : 'ohmylms-courses';
	$lesson_base         = isset( $permalink_structure['lesson_base'] ) ? $permalink_structure['lesson_base'] : 'lessons';
	$quiz_base           = isset( $permalink_structure['quiz_base'] ) ? $permalink_structure['quiz_base'] : 'quizzes';
	$assignment_base     = isset( $permalink_structure['assignment_base'] ) ? $permalink_structure['assignment_base'] : 'assignments';

	$course_slug  = $course->post_name;
	$content_slug = $post->post_name;

	if ( 'ohmylms-lesson' === $post_type ) {
		$url = home_url( "/$course_base/$course_slug/$lesson_base/$content_slug/" );
	} elseif ( 'ohmylms-quiz' === $post_type ) {
		$url = home_url( "/$course_base/$course_slug/$quiz_base/$content_slug/" );
	} elseif ( 'ohmylms-assignment' === $post_type ) {
		$url = home_url( "/$course_base/$course_slug/$assignment_base/$content_slug/" );
	} else {
		$url = get_permalink( $content_id );
	}
	return $url;
}


/**
 * Get chapter ID by content ID.
 *
 * @param int $content_id The ID of the content.
 * @return int|null The chapter ID or null if not found.
 *
 * @since 1.0.0
 */
function ohmylms_get_chapter_id_by_content_id( $content_id ) {
	static $cache = array();

	if ( isset( $cache[ $content_id ] ) ) {
		return $cache[ $content_id ];
	}

	global $wpdb;
	$chapter_id = $wpdb->get_var(
		$wpdb->prepare( 'SELECT chapter_id FROM %i WHERE content_id = %d LIMIT 1', $wpdb->prefix . 'ohmylms_content_relationship', $content_id )
	);
	$result     = $chapter_id ? (int) $chapter_id : 0;

	if ( $result ) {
		$cache[ $content_id ] = $result;
	}
	return $result;
}


/**
 * Get chapter ID by content ID.
 *
 * @param int $content_id The ID of the content.
 * @return int|null The chapter ID or null if not found.
 *
 * @since 1.0.0
 */
function ohmylms_get_content_type_id_by_content_id( $content_id ) {
	global $wpdb;
	$sql    = $wpdb->prepare( 'SELECT content_type FROM %i WHERE content_id = %d LIMIT 1', $wpdb->prefix . 'ohmylms_content_relationship', $content_id );
	$result = $wpdb->get_row( $sql );
	return isset( $result->content_type ) ? $result->content_type : null;
}


/**
 * Get course ID by content ID.
 *
 * @param int $content_id The ID of the content.
 * @return int|null The course ID or null if not found.
 *
 * @since 1.0.0
 * @param int $student_id Student identifier.
 */
function ohmylms_get_course_by_content_id( $content_id, $student_id = 0 ) {
	$home       = null;
	$chapter_id = ohmylms_get_chapter_id_by_content_id( $content_id );
	if ( $chapter_id ) {
		$course_id = ohmylms_get_course_id_by_chapter_id( $chapter_id );
		if ( $course_id ) {
			$home = $course_id;
		}
	}

	// Content placed in learning programs (quizzes and assignments included) can belong to several
	// courses. Content with no program placement keeps its chapter-based course exactly as before.
	$course_id = \OhMyLMS\Learning\Placements::resolve( $content_id, $home, $student_id );
	return $course_id ? $course_id : null;
}

/**
 * Ohmylms format file size for Content-functions.
 *
 * @param mixed $size Size.
 */
function ohmylms_format_file_size( $size ) {
	if ( $size >= 1073741824 ) {
		return number_format( $size / 1073741824, 2 ) . ' GB';
	} elseif ( $size >= 1048576 ) {
		return number_format( $size / 1048576, 2 ) . ' MB';
	} elseif ( $size >= 1024 ) {
		return number_format( $size / 1024, 2 ) . ' KB';
	} else {
		return $size . ' bytes';
	}
}


/**
 * Ohmylms get question ans by question id for Content-functions.
 *
 * @param mixed $question_id Question identifier.
 */
function ohmylms_get_question_ans_by_question_id( $question_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'ohmylms_question_answers'; // Replace 'your_table_name' with the actual table name.

	$results = $wpdb->get_results(
		$wpdb->prepare( 'SELECT * FROM %i WHERE question_id = %d AND is_correct = 1', $table_name, $question_id ),
		ARRAY_A
	);

	return $results;
}
