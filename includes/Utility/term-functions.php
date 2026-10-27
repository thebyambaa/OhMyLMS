<?php
/**
 * Term functions.
 *
 * @package OhMyLMS
 */


/**
 * Retrieve product terms for a given course.
 *
 * @param int    $course_id The ID of the course.
 * @param string $taxonomy The taxonomy to retrieve terms from.
 * @param array  $args Optional. Array of arguments to retrieve terms.
 *
 * @return array The terms associated with the course.
 *
 * @since 1.0.0
 */
function ohmylms_get_course_terms( $course_id, $taxonomy, $args = array() ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$cache_key   = 'ohmylms_' . $taxonomy . md5( wp_json_encode( $args ) );
	$cache_group = 'course-single-' . $course_id;
	$terms       = wp_cache_get( $cache_key, $cache_group );

	if ( false !== $terms ) {
		return $terms;
	}

	$terms = wp_get_post_terms( $course_id, $taxonomy, $args );

	wp_cache_add( $cache_key, $terms, $cache_group );

	return $terms;
}


/**
 * Retrieve terms for a given object.
 *
 * @param int    $object_id The ID of the object.
 * @param string $taxonomy The taxonomy to retrieve terms from.
 * @param string $field Optional. The field to retrieve. Default null.
 * @param string $index_key Optional. The key to index the terms by. Default null.
 *
 * @return array The terms associated with the object.
 *
 * @since 1.0.0
 */
function ohmylms_get_object_terms( $object_id, $taxonomy, $field = null, $index_key = null ) {
	$terms = get_the_terms( $object_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array();
	}
	return is_null( $field ) ? $terms : wp_list_pluck( $terms, $field, $index_key );
}
