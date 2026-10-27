<?php
/**
 * Template and plugin compatibility helpers.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Preserve the previous PHP API when CreatorLMS is not installed.
 *
 * Checking installed plugins also covers activating CreatorLMS later in the
 * current request: legacy aliases must never occupy its symbols first.
 *
 * @return void
 */
function ohmylms_load_legacy_commerce_api() {
	if ( defined( 'CRLMS_FILE' ) || class_exists( 'CreatorLms', false ) ) {
		return;
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( get_plugins() as $plugin ) {
		if ( in_array( $plugin['TextDomain'], array( 'creatorlms', 'creatorlms-pro' ), true ) || preg_match( '/^Creator\s*LMS\b/i', $plugin['Name'] ) ) {
			return;
		}
	}
	require_once __DIR__ . '/legacy-commerce.php';
}

// Both LMS plugins use these established taxonomies. Restore our attachment
// after all registrations so plugin load order cannot detach OhMyLMS courses.
add_action(
	'init',
	static function () {
		foreach ( array( 'course_category', 'course_tag' ) as $taxonomy ) {
			if ( taxonomy_exists( $taxonomy ) ) {
				register_taxonomy_for_object_type( $taxonomy, 'ohmylms-course' );
				if ( post_type_exists( 'crlms-course' ) ) {
					register_taxonomy_for_object_type( $taxonomy, 'crlms-course' );
				}
			}
		}
	},
	20
);

// Let themes override templates from <theme>/ohmylms/.
add_filter(
	'ohmylms_locate_template',
	function ( $path, $name ) {
		$override = locate_template( 'ohmylms/' . $name );
		return $override ? $override : $path;
	},
	20,
	2
);

add_filter(
	'ohmylms_get_template_part',
	function ( $path, $slug, $name ) {
		$override = locate_template( 'ohmylms/' . $slug . ( $name ? '-' . $name : '' ) . '.php' );
		return $override ? $override : $path;
	},
	20,
	3
);
