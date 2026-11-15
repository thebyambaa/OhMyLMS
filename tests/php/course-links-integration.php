<?php
/**
 * Learner course link integration test.
 *
 * Runs against a WordPress install (the Local site by default). Read-only: it uses an existing
 * published course and the first administrator.
 *
 * Usage (set OHMYLMS_COURSE_LINKS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/course-links-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_COURSE_LINKS_TEST' ) ) {
	exit( "Set OHMYLMS_COURSE_LINKS_TEST=1 to run the course link tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Student\Hub;
use OhMyLMS\Student\LearnerCatalog;

$checks = 0;

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

$exit_code = 0;
try {
	if ( ! Hub::base_url() || ! in_array( 'library', Hub::settings()['tabs'], true ) ) {
		exit( "Usage: the site needs a published learner hub with the library tab for these checks.\n" );
	}
	$courses   = get_posts(
		array(
			'post_type'    => OHMYLMS_COURSE_CPT,
			'post_status'  => 'publish',
			'has_password' => false,
			'fields'       => 'ids',
			'numberposts'  => 20,
			'exclude'      => LearnerCatalog::roadmap_course_ids(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- A handful of ids in a test.
		)
	);
	$course_id = (int) ( $courses[0] ?? 0 );
	ok( $course_id > 0, 'the site has a published course outside the roadmaps' );
	$permalink = get_permalink( $course_id );
	$overview  = Hub::course_url( $course_id );
	ok( $permalink !== $overview && false !== strpos( $overview, 'hub_course=' . $course_id ), 'the hub overview has its own address' );

	// Signed-out visitors keep the public course page.
	wp_set_current_user( 0 );
	ok( ! Hub::opens_in_hub( $course_id ), 'a signed-out visitor is not sent to the hub' );
	ok( Hub::learner_course_url( $course_id ) === $permalink, 'a signed-out visitor gets the course permalink' );

	// Signed-in learners get the address the course page would redirect to.
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $admins[0] );
	ok( Hub::opens_in_hub( $course_id ), 'a signed-in user opens the course in the hub' );
	ok( Hub::learner_course_url( $course_id ) === $overview, 'a signed-in user gets the hub overview address' );
	ok( ! Hub::opens_in_hub( 0 ), 'no course, no hub address' );

	// Catalogue cards use the same address; other post types keep their link.
	$GLOBALS['post'] = get_post( $course_id );
	setup_postdata( $GLOBALS['post'] );
	ok( apply_filters( 'ohmylms_loop_product_link', $permalink ) === $overview, 'a course card links straight to the hub overview' );
	wp_set_current_user( 0 );
	ok( apply_filters( 'ohmylms_loop_product_link', $permalink ) === $permalink, 'a signed-out course card keeps the permalink' );
	wp_set_current_user( (int) $admins[0] );
	$pages = get_posts(
		array(
			'post_type'   => 'page',
			'numberposts' => 1,
		)
	);
	if ( $pages ) {
		$GLOBALS['post'] = $pages[0];
		setup_postdata( $GLOBALS['post'] );
		ok( apply_filters( 'ohmylms_loop_product_link', 'https://example.test/x' ) === 'https://example.test/x', 'a card for another post type keeps its link' );
	}
	wp_reset_postdata();

	foreach ( LearnerCatalog::roadmap_course_ids() as $roadmap_id ) {
		ok( Hub::learner_course_url( $roadmap_id ) === (string) get_permalink( $roadmap_id ), 'a roadmap course keeps its permalink' );
		break;
	}

	echo "OK: $checks checks\n";
} catch ( Throwable $error ) {
	echo $error->getMessage(), "\n";
	$exit_code = 1;
}

exit( $exit_code );
