<?php
/**
 * Studio course layout and school people search storage integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real tables. The two classes hold the
 * queries the studio and front-dashboard REST controllers used to run themselves. The test inserts its own
 * rows under ids that nothing else uses and removes them in `finally`.
 *
 * Usage (set OHMYLMS_STUDIO_STORAGE_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/studio-storage-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STUDIO_STORAGE_TEST' ) ) {
	exit( "Set OHMYLMS_STUDIO_STORAGE_TEST=1 to run the studio storage tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Schools\PeopleSearch;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Studio\CourseLayout;

global $wpdb;

$checks = 0;
$base   = 900000000 + wp_rand( 1000, 99999 ) * 10;
$tag    = 'stg' . strtolower( wp_generate_password( 6, false, false ) );

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

$course    = $base;
$chapters  = array( $base + 1, $base + 2 );
$contents  = array( $base + 3, $base + 4, $base + 5 );
$school    = $base + 6;
$other     = $base + 7;
$class_id  = 0;
$user_id   = 0;
$failed    = false;
$chapter_t = $wpdb->prefix . 'ohmylms_chapter_relationship';
$content_t = $wpdb->prefix . 'ohmylms_content_relationship';

try {
	// Units: appended in order, then reordered.
	CourseLayout::append_chapter( $course, $chapters[0] );
	CourseLayout::append_chapter( $course, $chapters[1] );
	$listed = CourseLayout::chapters( $course );
	ok( array( $chapters[0], $chapters[1] ) === array_map( 'intval', wp_list_pluck( $listed, 'chapter_id' ) ), 'units come back in the order they were added' );
	ok( array( 1, 2 ) === array_map( 'intval', wp_list_pluck( $listed, 'order_number' ) ), 'appended units are numbered from 1' );
	ok( CourseLayout::chapter_in_course( $chapters[0], $course ) && ! CourseLayout::chapter_in_course( $chapters[0], $course + 1 ), 'a unit belongs only to its own course' );
	ok( CourseLayout::course_of( $chapters[1] ) === $course && 0 === CourseLayout::course_of( $base + 99 ), 'the course of a unit is found, and 0 for an unplaced one' );
	CourseLayout::set_chapter_position( $course, $chapters[0], 2 );
	CourseLayout::set_chapter_position( $course, $chapters[1], 1 );
	ok( array( $chapters[1], $chapters[0] ) === array_map( 'intval', wp_list_pluck( CourseLayout::chapters( $course ), 'chapter_id' ) ), 'moving units changes their order' );
	CourseLayout::set_chapter_position( $course + 1, $chapters[0], 9 );
	ok( 2 === (int) wp_list_pluck( CourseLayout::chapters( $course ), 'order_number' )[1], 'moving a unit in another course leaves this course alone' );

	// Content: appended with a type, then reordered.
	CourseLayout::append_content( $chapters[0], $contents[0], 'lesson' );
	CourseLayout::append_content( $chapters[0], $contents[1], 'quiz' );
	CourseLayout::append_content( $chapters[0], $contents[2], 'video' );
	$items = CourseLayout::contents( $chapters[0] );
	ok( array( $contents[0], $contents[1], $contents[2] ) === array_map( 'intval', wp_list_pluck( $items, 'content_id' ) ), 'content comes back in the order it was added' );
	ok( array( 'lesson', 'quiz', 'video' ) === wp_list_pluck( $items, 'content_type' ), 'content keeps its type' );
	ok( array( 1, 2, 3 ) === array_map( 'intval', wp_list_pluck( $items, 'order_number' ) ), 'appended content is numbered from 1' );
	CourseLayout::set_content_position( $chapters[0], $contents[2], 1 );
	CourseLayout::set_content_position( $chapters[0], $contents[0], 3 );
	ok( array( $contents[2], $contents[1], $contents[0] ) === array_map( 'intval', wp_list_pluck( CourseLayout::contents( $chapters[0] ), 'content_id' ) ), 'moving content changes its order' );
	ok( array() === CourseLayout::contents( $chapters[1] ), 'a unit with no content lists none' );

	// Reads for things that do not exist return empty values, not errors.
	ok( array() === CourseLayout::quiz_questions( $base + 98 ) && array() === CourseLayout::answers( $base + 98 ), 'a quiz or question that does not exist has no rows' );
	ok( 0 === CourseLayout::quiz_skill_uses( $base + 98 ), 'an unused skill has no quiz uses' );

	// People of a school.
	foreach ( array( 'schools', 'school_memberships', 'classes', 'school_invitations' ) as $table ) {
		ok( Schema::table( $table ) === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Schema::table( $table ) ) ), "$table table is installed" );
	}
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	);
	ok( $admins, 'an administrator exists' );
	$user_id = (int) $admins[0]->ID;
	$now     = gmdate( 'Y-m-d H:i:s' );
	$wpdb->insert(
		Schema::table( 'schools' ),
		array(
			'id'         => $school,
			'name'       => "School $tag",
			'slug'       => "school-$tag",
			'created_by' => $user_id,
			'created_at' => $now,
		)
	);
	$wpdb->insert(
		Schema::table( 'school_memberships' ),
		array(
			'school_id' => $school,
			'user_id'   => $user_id,
			'role'      => 'teacher',
			'status'    => 'active',
			'joined_at' => $now,
		)
	);
	$wpdb->insert(
		Schema::table( 'classes' ),
		array(
			'school_id'        => $school,
			'academic_year_id' => 0,
			'name'             => "Class $tag",
		)
	);
	$class_id = (int) $wpdb->insert_id;
	$wpdb->insert(
		Schema::table( 'school_invitations' ),
		array(
			'token_hash' => hash( 'sha256', $tag ),
			'school_id'  => $school,
			'role'       => 'guardian',
			'email'      => "parent-$tag@example.test",
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'invited_by' => $user_id,
			'created_at' => $now,
		)
	);

	$term = substr( $admins[0]->display_name, 0, 3 );
	$rows = PeopleSearch::find( array( $school ), 'teacher', $term );
	ok( 1 === count( $rows ) && $user_id === (int) $rows[0]['id'] && "School $tag" === $rows[0]['school'], 'a school member is found by name, with the school name' );
	ok( array() === PeopleSearch::find( array( $school ), 'teacher', 'zz' . $tag ), 'a search that matches nobody returns none' );
	ok( array() === PeopleSearch::find( array( $other ), 'teacher', $term ), 'members of another school are not found' );
	ok( array() === PeopleSearch::find( array( $school ), 'student', $term ), 'only the asked role is searched' );
	$guardians = PeopleSearch::find( array( $school ), 'guardian', "parent-$tag" );
	ok( 1 === count( $guardians ) && "parent-$tag@example.test" === $guardians[0]['email'] && '' === $guardians[0]['school'], 'a pending guardian invitation is found by email' );
	$wpdb->update( Schema::table( 'school_invitations' ), array( 'revoked_at' => $now ), array( 'token_hash' => hash( 'sha256', $tag ) ) );
	ok( array() === PeopleSearch::find( array( $school ), 'guardian', "parent-$tag" ), 'a revoked invitation is not found' );
	ok( PeopleSearch::class_id_by_name( $school, "  Class $tag  " ) === $class_id, 'a class is found by its name, ignoring surrounding spaces' );
	ok( 0 === PeopleSearch::class_id_by_name( $school, '' ) && 0 === PeopleSearch::class_id_by_name( $other, "Class $tag" ), 'a blank name or another school finds no class' );

	echo "Studio storage: $checks checks passed." . PHP_EOL;
} catch ( Throwable $error ) {
	echo $error->getMessage() . PHP_EOL;
	$failed = true;
} finally {
	$wpdb->delete( $chapter_t, array( 'course_id' => $course ) );
	$wpdb->delete( $content_t, array( 'chapter_id' => $chapters[0] ) );
	$wpdb->delete( $content_t, array( 'chapter_id' => $chapters[1] ) );
	$wpdb->delete( Schema::table( 'school_invitations' ), array( 'school_id' => $school ) );
	$wpdb->delete( Schema::table( 'classes' ), array( 'school_id' => $school ) );
	$wpdb->delete( Schema::table( 'school_memberships' ), array( 'school_id' => $school ) );
	$wpdb->delete( Schema::table( 'schools' ), array( 'id' => $school ) );
}
if ( $failed ) {
	exit( 1 );
}
