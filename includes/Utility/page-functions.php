<?php
/**
 * Page functions.
 *
 * @package OhMyLMS
 */


use OhMyLMS\Data\Lesson;
use OhMyLMS\Data\Quiz;

/**
 * Get page ID by page name.
 *
 * @param string $page Page name.
 * @return int
 * @since 1.0.0
 */
function ohmylms_get_page_id( $page ) {

	$page_id = apply_filters( 'ohmylms_get_' . $page . '_page_id', get_option( 'ohmylms_' . $page . '_page_id' ) );
	if ( 'course' === $page ) {
		$page_slug = get_post_field( 'post_name', $page_id );
		if ( ! in_array( $page_slug, array( 'ohmylms-all-courses', 'ohmylms-all-courses' ), true ) ) {
			return -1;
		}
	}

	return $page_id ? absint( $page_id ) : -1;
}

/**
 * Get page url by name
 *
 * @param mixed $page Page.
 * @return false|string|null
 * @since 1.0.0
 */
function ohmylms_get_page_url( $page ) {
	$id = ohmylms_get_page_id( $page );

	if ( $id ) {
		return get_permalink( $id );
	}

	return site_url();
}


/**
 * Get endpoint URL.
 *
 * Gets the URL for an endpoint, which varies depending on permalink settings.
 *
 * @param string $endpoint Endpoint slug.
 * @param string $value Query param value.
 * @param string $permalink Permalink.
 *
 * @return string
 */
function ohmylms_get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
	if ( ! $permalink ) {
		$permalink = get_permalink();
	}

	// Map endpoint to options.
	$query_vars = \OhMyLMS\Commerce\ecommerce()->query->get_query_vars();
	$endpoint   = ! empty( $query_vars[ $endpoint ] ) ? $query_vars[ $endpoint ] : $endpoint;
	$value      = ( get_option( 'ohmylms_myprofile_edit_address_endpoint', 'edit-address' ) === $endpoint ) ? ohmylms_edit_address_i18n( $value ) : $value;

	if ( get_option( 'permalink_structure' ) ) {
		if ( strstr( $permalink, '?' ) ) {
			$query_string = '?' . wp_parse_url( $permalink, PHP_URL_QUERY );
			$permalink    = current( explode( '?', $permalink ) );
		} else {
			$query_string = '';
		}
		$url = trailingslashit( $permalink );

		if ( $value ) {
			$url .= trailingslashit( $endpoint ) . user_trailingslashit( $value );
		} else {
			$url .= user_trailingslashit( $endpoint );
		}

		$url .= $query_string;
	} else {
		$url = add_query_arg( $endpoint, $value, $permalink );
	}

	return apply_filters( 'ohmylms_get_endpoint_url', $url, $endpoint, $value, $permalink );
}


/**
 * Retrieve page permalink.
 *
 * @param string      $page page slug.
 * @param string|bool $fallback Fallback URL if page is not set. Defaults to home URL. @since 3.4.0.
 * @return string
 */
function ohmylms_get_page_permalink( $page, $fallback = null ) {
	$page_id   = ohmylms_get_page_id( $page );
	$permalink = 0 < $page_id ? get_permalink( $page_id ) : '';
	if ( ! $permalink ) {
		$permalink = is_null( $fallback ) ? get_home_url() : $fallback;
	}

	return apply_filters( 'ohmylms_get_' . $page . '_page_permalink', $permalink );
}



/**
 * Ohmylms get next content permalink for Page-functions.
 *
 * @param mixed $current_lesson Current lesson.
 */
function ohmylms_get_next_content_permalink( $current_lesson ) {
	$next_lesson = ohmylms_get_next_content( $current_lesson );
	if ( $next_lesson ) {
		return get_permalink( $next_lesson['id'] );
	}
	return null;
}



/**
 * Ohmylms get next content for Page-functions.
 *
 * @param mixed $current_lesson_id Current lesson identifier.
 */
function ohmylms_get_next_content( $current_lesson_id ) {
	$course_id = ohmylms_get_course_by_content_id( $current_lesson_id );
	$course    = ohmylms_get_course( $course_id );
	if ( $course ) {
		$lessons = $course->get_lessons();
		foreach ( $lessons as $index => $l ) {
			if ( 'quiz' === $l['type'] ) {
				$lesson = new Quiz( $l['id'] );
			} elseif ( 'assignment' === $l['type'] ) {
				$lesson = ohmylms_is_pro() ? new OhMyLMS\Data\Assignment( $l['id'] ) : null;
			} else {
				$lesson = new Lesson( $l['id'] );
			}

			if ( $lesson && $lesson->get_id() == $current_lesson_id && isset( $lessons[ $index + 1 ] ) ) {
				return $lessons[ $index + 1 ];
			}
		}
	}
	return null;
}


/**
 * Ohmylms get prev content for Page-functions.
 *
 * @param mixed $current_lesson_id Current lesson identifier.
 */
function ohmylms_get_prev_content( $current_lesson_id ) {
	$course_id = ohmylms_get_course_by_content_id( $current_lesson_id );
	$course    = ohmylms_get_course( $course_id );
	if ( $course ) {
		$lessons = $course->get_lessons();
		foreach ( $lessons as $index => $l ) {
			if ( 'quiz' === $l['type'] ) {
				$lesson = new Quiz( $l['id'] );
			} elseif ( 'assignment' === $l['type'] ) {
				$lesson = ohmylms_is_pro() ? new OhMyLMS\Data\Assignment( $l['id'] ) : null;
			} else {
				$lesson = new Lesson( $l['id'] );
			}

			if ( $lesson && $lesson->get_id() == $current_lesson_id && isset( $lessons[ $index - 1 ] ) ) {
				return $lessons[ $index - 1 ];
			}
		}
	}
	return null;
}

/**
 * Check if current page is student dashboard page.
 *
 * @return bool
 * @since 1.0.0
 */
function is_ohmylms_student_dashboard() {
	return is_page( ohmylms_get_page_id( 'student_dashboard' ) );
}

/**
 * Check if current page is student profile page.
 *
 * @return bool
 * @since 1.0.0
 */
function is_ohmylms_student_profile() {
	return is_page( ohmylms_get_page_id( 'student_profile' ) );
}

/**
 * Check if current page is student courses page.
 *
 * @return bool
 * @since 1.0.0
 */
function is_ohmylms_student_courses() {
	return is_page( ohmylms_get_page_id( 'student_courses' ) );
}
