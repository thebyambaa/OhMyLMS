<?php
/**
 * Free course access integration test.
 *
 * Runs against the Local site with real tables. It creates tagged courses and temporary users and removes
 * them, and their enrolment rows, in `finally`.
 *
 * Usage: OHMYLMS_FREE_ACCESS_TEST=1 node tools/run-php-tests.mjs integration tests/php/free-access-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_FREE_ACCESS_TEST' ) ) {
	exit( "Set OHMYLMS_FREE_ACCESS_TEST=1 to run the free access tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks = 0;
$posts  = array();
$users  = array();
$table  = $wpdb->prefix . 'ohmylms_user_enrollment';

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

/**
 * Make a published course with the given meta.
 *
 * @param array $meta Meta to store.
 * @return int Course ID.
 */
function make_course( array $meta = array() ) {
	global $posts;
	$id      = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => 'publish',
			'post_title'  => 'freeaccess test ' . wp_generate_password( 6, false ),
		)
	);
	$posts[] = $id;
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}
	return $id;
}

/**
 * Make a subscriber and sign them in.
 *
 * @return int User ID.
 */
function make_student() {
	global $users;
	$id      = wp_insert_user(
		array(
			'user_login' => 'fatest' . wp_generate_password( 8, false ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'subscriber',
		)
	);
	$users[] = $id;
	wp_set_current_user( $id );
	return $id;
}

/**
 * Open a course as the signed-in user, the way a page does.
 *
 * @param int $course_id Course ID.
 * @return bool
 */
function opens( $course_id ) {
	wp_cache_flush();
	$reflection = new ReflectionProperty( \OhMyLMS\DataStores\CourseStore::class, 'course_access_cache' );
	$reflection->setAccessible( true );
	$reflection->setValue( null, array() );
	return (bool) ohmylms_get_course( $course_id )->has_access();
}

/**
 * Enrolment statuses of a student in a course.
 *
 * @param int $user_id   User.
 * @param int $course_id Course.
 * @return array Statuses.
 */
function rows( $user_id, $course_id ) {
	global $wpdb, $table;
	return $wpdb->get_col( $wpdb->prepare( 'SELECT status FROM %i WHERE user_id = %d AND course_id = %d', $table, $user_id, $course_id ) );
}

try {
	// A course with nothing stored is free access, as every course made before the setting was.
	$open    = make_course();
	$student = make_student();
	ok( ohmylms_get_course( $open )->get_free_access(), 'no stored value counts as on' );
	ok( opens( $open ) && array( 'enrolled' ) === rows( $student, $open ), 'a signed-in student opens it and is enrolled' );
	ok( opens( $open ) && 1 === count( rows( $student, $open ) ), 'opening it again adds no second enrolment' );

	// Switched off: only enrolled students open it.
	$closed = make_course( array( '_free_access' => '0' ) );
	ok( ! opens( $closed ) && array() === rows( $student, $closed ), 'a course with free access off stays closed' );
	$wpdb->insert(
		$table,
		array(
			'user_id'    => $student,
			'course_id'  => $closed,
			'status'     => 'enrolled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		)
	);
	ok( opens( $closed ), 'an enrolled student still opens it' );

	// Visitors are never enrolled.
	wp_set_current_user( 0 );
	ok( ! opens( $open ), 'a visitor does not open it' );

	// An ended enrolment is respected.
	$other = make_student();
	$wpdb->insert(
		$table,
		array(
			'user_id'    => $other,
			'course_id'  => $open,
			'status'     => 'cancelled',
			'progress'   => 'running',
			'start_date' => current_time( 'mysql' ),
		)
	);
	ok( ! opens( $open ) && array( 'cancelled' ) === rows( $other, $open ), 'a cancelled student is not enrolled again' );

	// Courses with their own entry rules are not opened by the switch.
	$locked = make_course( array( '_access_type' => 'password_protected' ) );
	$seats  = make_course(
		array(
			'_has_capacity' => '1',
			'_capacity'     => 5,
		)
	);
	$third  = make_student();
	ok( ! opens( $locked ) && ! opens( $seats ) && array() === rows( $third, $locked ), 'a password or a seat limit keeps the course closed' );

	// A draft is not open.
	$draft = make_course();
	wp_update_post(
		array(
			'ID'          => $draft,
			'post_status' => 'draft',
		)
	);
	ok( ! opens( $draft ), 'a draft is not open' );

	// The library's course overview reads the enrolment row, so opening it must enrol too.
	$reader  = make_student();
	$outline = \OhMyLMS\Student\CourseOutline::get( $open, $reader );
	ok( is_array( $outline ) && ! empty( $outline['enrolled'] ) && array( 'enrolled' ) === rows( $reader, $open ), 'the library overview enrols a student in a free-access course' );
	$shut = \OhMyLMS\Student\CourseOutline::get( $closed, $reader );
	ok( is_array( $shut ) && empty( $shut['enrolled'] ), 'the library overview does not enrol for a course with free access off' );

	// A published course nobody placed in the curriculum is still listed in the library.
	$listed = array_column( \OhMyLMS\Student\LearnerCatalog::unplaced_courses( array() ), 'id' );
	ok( in_array( $open, $listed, true ), 'a published course that is not placed in the curriculum is listed' );
	ok( ! in_array( $draft, $listed, true ), 'a draft is not listed' );
	ok( ! in_array( $open, array_column( \OhMyLMS\Student\LearnerCatalog::unplaced_courses( array( $open ) ), 'id' ), true ), 'a course a subject already lists is not listed twice' );
	$exam = make_course( array( '_ohmylms_exam_wrapper' => '1' ) );
	ok( ! in_array( $exam, array_column( \OhMyLMS\Student\LearnerCatalog::unplaced_courses( array() ), 'id' ), true ), 'an exam wrapper course is not listed' );
	ok( array() === \OhMyLMS\Student\LearnerCatalog::unplaced_courses( array(), 'zzz-no-such-title' ), 'the search text filters them' );

	// The author is not enrolled by previewing their own course.
	$author = make_student();
	( new WP_User( $author ) )->set_role( 'administrator' );
	ok( ! opens( $open ) && array() === rows( $author, $open ), 'staff are not enrolled by opening a course' );

	echo "Free access: {$checks} checks passed.\n";
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . "\n" );
	$code = 1;
} finally {
	foreach ( $posts as $id ) {
		$wpdb->delete( $table, array( 'course_id' => $id ) );
		wp_delete_post( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
}
exit( isset( $code ) ? $code : 0 );
