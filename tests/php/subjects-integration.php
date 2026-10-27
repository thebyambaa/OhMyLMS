<?php
/**
 * Explicitly enabled local subject browsing fixtures and regression checks.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SUBJECTS_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_SUBJECTS_INTEGRATION=1 for local subject checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\Roadmaps;
use OhMyLMS\Curriculum\SubjectCourses;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\Schema;
use OhMyLMS\Student\Subjects;

/**
 * Request one route with JSON data.
 *
 * @param string $method HTTP method.
 * @param string $path Route suffix.
 * @param array  $data JSON body.
 * @return WP_REST_Response
 */
function subjects_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

/**
 * Assert a meaningful integration result.
 *
 * @param bool   $condition Result.
 * @param string $message Failure message.
 * @throws RuntimeException On failure.
 */
function subjects_check( $condition, $message ) {
	global $subjects_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Local CLI test diagnostics.
	}
	++$subjects_checks;
}

/**
 * Remove only explicitly tracked fixture records.
 *
 * @param array $state Fixture IDs.
 */
function subjects_cleanup( array $state ) {
	global $wpdb;
	wp_set_current_user( $state['actor'] );
	if ( ! empty( $state['token'] ) ) {
		WP_Session_Tokens::get_instance( $state['actor'] )->destroy( $state['token'] );
	}
	foreach ( $state['enrollments'] as $id ) {
		$wpdb->delete( Schema::table( 'enrollments' ), array( 'enrollment_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture program bindings only.
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture learner progress only.
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture enrollment only.
	}
	foreach ( $state['courses'] as $course ) {
		foreach ( Catalog::chapters( $course ) as $unit ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $unit['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture placements only.
			wp_delete_post( $unit['id'], true );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture unit relationships only.
		$wpdb->delete( Schema::table( 'programs' ), array( 'course_id' => $course ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture programs have no skill outcomes.
	}
	foreach ( array_reverse( $state['items'] ) as $id ) {
		$wpdb->delete( Links::table(), array( 'item_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture curriculum memberships only.
		$wpdb->delete( Items::table(), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit fixture item ID.
		delete_option( SubjectCourses::OPTION_PREFIX . $id );
	}
	foreach ( array_reverse( $state['posts'] ) as $id ) {
		wp_delete_post( $id, true );
	}
}

$subjects_path = dirname( __DIR__, 2 ) . '/test-results/subjects-browser-session.json';
if ( 'cleanup' === ( $argv[1] ?? '' ) ) {
	$subjects_state = json_decode( file_get_contents( $subjects_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local ignored fixture file.
	subjects_cleanup( $subjects_state );
	wp_delete_file( $subjects_path );
	exit;
}
$subjects_checks = 0;
$subjects_state  = array(
	'actor'       => get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	)[0]->ID,
	'items'       => array(),
	'posts'       => array(),
	'courses'     => array(),
	'enrollments' => array(),
	'base'        => home_url(),
);
$subjects_keep   = false;
try {
	wp_set_current_user( $subjects_state['actor'] );
	global $wpdb;
	foreach ( array( 'Subject browser fixture', 'Other subject fixture', 'Non-subject fixture' ) as $index => $name ) {
		$item = Items::create(
			array(
				'name'      => $name,
				'item_type' => 2 === $index ? 'custom' : 'subject',
			)
		);
		subjects_check( ! is_wp_error( $item ), 'Fixture curriculum item created.' );
		$subjects_state['items'][] = (int) $item['id'];
	}
	$subject_id                = $subjects_state['items'][0];
	$other_id                  = $subjects_state['items'][1];
	$subgroup                  = Items::create(
		array(
			'name'      => 'Subject subgroup fixture',
			'item_type' => 'topic',
			'parent_id' => $subject_id,
		)
	);
	$subjects_state['items'][] = (int) $subgroup['id'];
	$subjects_state['subject'] = $subject_id;
	foreach ( array(
		'Zulu course fixture'    => 'publish',
		'Alpha course fixture'   => 'publish',
		'Hidden draft fixture'   => 'draft',
		'Hidden private fixture' => 'private',
		'Hidden roadmap fixture' => 'publish',
	) as $name => $subjects_post_status ) {
		$course                      = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_COURSE_CPT,
				'post_title'  => $name,
				'post_status' => $subjects_post_status,
			)
		);
		$subjects_state['posts'][]   = $course;
		$subjects_state['courses'][] = $course;
		Links::add( $subject_id, 'course', $course );
	}
	$zulu    = $subjects_state['courses'][0];
	$alpha   = $subjects_state['courses'][1];
	$draft   = $subjects_state['courses'][2];
	$private = $subjects_state['courses'][3];
	$roadmap = $subjects_state['courses'][4];
	update_post_meta( $roadmap, Roadmaps::SOURCES_META, array( 1 ) );
	Links::add( (int) $subgroup['id'], 'course', $alpha );
	Links::remove( $subject_id, 'course', $alpha );
	Links::add( $other_id, 'course', $alpha );
	Links::add( $subjects_state['items'][2], 'course', $zulu );
	$unit                   = Catalog::add_chapter( $zulu, 'Published unit fixture' )['id'];
	$subjects_state['unit'] = $unit;
	for ( $index = 0; $index < 2; ++$index ) {
		$lesson                      = wp_insert_post(
			array(
				'post_type'    => OHMYLMS_LESSON_CPT,
				'post_title'   => 'Subject activity fixture ' . $index,
				'post_content' => 'Subject test activity.',
				'post_status'  => 'publish',
			)
		);
		$subjects_state['posts'][]   = $lesson;
		$subjects_state['lessons'][] = $lesson;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicitly tracked fixture relationship.
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $unit,
				'content_id'   => $lesson,
				'content_type' => 'text',
				'order_number' => $index + 1,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Native fixture activity placement.
	}
	$published = subjects_api( 'POST', 'courses/' . $zulu . '/structure/publish', array( 'revision' => Catalog::revision( $zulu ) ) );
	subjects_check( 200 === $published->get_status(), 'Fixture outline published.' );
	$edit = subjects_api(
		'PUT',
		'chapters/' . $unit,
		array(
			'name'     => 'Hidden draft unit fixture',
			'revision' => Catalog::revision( $zulu ),
		)
	);
	subjects_check( 200 === $edit->get_status(), 'Fixture unit draft saved.' );
	$view = subjects_api( 'GET', 'subjects/' . $subject_id . '/courses' );
	subjects_check( 200 === $view->get_status() && 5 === count( $view->get_data()['courses'] ), 'Author sees published, unpublished and subgroup placements exactly once.' );
	$original_revision = $view->get_data()['revision'];
	$wanted            = array( $zulu, $private, $draft, $alpha, $roadmap );
	$saved             = subjects_api(
		'PUT',
		'subjects/' . $subject_id . '/courses',
		array(
			'course_ids' => $wanted,
			'revision'   => $original_revision,
		)
	);
	subjects_check( 200 === $saved->get_status() && array_column( $saved->get_data()['courses'], 'id' ) === $wanted, 'Author saves a complete cross-subgroup course order.' );
	$revision = $saved->get_data()['revision'];
	subjects_check(
		409 === subjects_api(
			'PUT',
			'subjects/' . $subject_id . '/courses',
			array(
				'course_ids' => array_reverse( $wanted ),
				'revision'   => $original_revision,
			)
		)->get_status(),
		'Stale course reordering rejected.'
	);
	subjects_check( 409 === subjects_api( 'PUT', 'subjects/' . $subject_id . '/courses', array( 'course_ids' => $wanted ) )->get_status(), 'Missing order revision rejected.' );
	foreach ( array( array( $zulu ), array( $zulu, $zulu, $draft, $alpha, $roadmap ), array( -1, $private, $draft, $alpha, $roadmap ), array( (string) $zulu, $private, $draft, $alpha, $roadmap ) ) as $invalid ) {
		subjects_check(
			400 === subjects_api(
				'PUT',
				'subjects/' . $subject_id . '/courses',
				array(
					'course_ids' => $invalid,
					'revision'   => $revision,
				)
			)->get_status(),
			'Incomplete, duplicate or malformed order rejected.'
		);
	}
	subjects_check( array_column( SubjectCourses::view( $subject_id )['courses'], 'id' ) === $wanted, 'Invalid writes do not alter the saved order.' );
	subjects_check(
		400 === subjects_api(
			'PUT',
			'subjects/' . $subject_id . '/courses',
			array(
				'course_ids' => array( $zulu, $private, $draft, $alpha, $unit ),
				'revision'   => $revision,
			)
		)->get_status(),
		'A foreign record cannot be injected into course order.'
	);
	add_filter( 'ohmylms_can_manage_curriculum', '__return_false' );
	subjects_check( 403 === subjects_api( 'GET', 'subjects/' . $subject_id . '/courses' )->get_status(), 'A signed-in user without curriculum permission cannot read private courses.' );
	subjects_check(
		403 === subjects_api(
			'PUT',
			'subjects/' . $subject_id . '/courses',
			array(
				'course_ids' => $wanted,
				'revision'   => $revision,
			)
		)->get_status(),
		'A signed-in user without curriculum permission cannot reorder courses.'
	);
	remove_filter( 'ohmylms_can_manage_curriculum', '__return_false' );
	subjects_check( array( $alpha ) === Subjects::get( $other_id )['course_ids'], 'Ordering one subject leaves another subject independent.' );
	subjects_check( in_array( $subjects_state['items'][2], \OhMyLMS\Curriculum\Placement::item_ids( $zulu ), true ), 'Non-subject course links are preserved.' );
	$public = Subjects::get( $subject_id );
	subjects_check( array( $zulu, $alpha ) === $public['course_ids'], 'Public subject filters private/draft/roadmap courses after ordering.' );
	subjects_check( is_wp_error( Subjects::get( $subjects_state['items'][2] ) ), 'A custom curriculum item does not become a subject page.' );
	wp_set_current_user( 0 );
	$html = Subjects::render( $subject_id );
	subjects_check( false !== strpos( $html, 'Published unit fixture' ) && false === strpos( $html, 'Hidden draft unit fixture' ), 'Subject outline preview exposes published unit metadata only.' );
	subjects_check( false === strpos( $html, 'Hidden private fixture' ) && false === strpos( $html, 'Hidden draft fixture' ) && false === strpos( $html, 'Hidden roadmap fixture' ), 'Private and infrastructure course titles never leak into the subject page.' );
	subjects_check( strpos( $html, 'Zulu course fixture' ) < strpos( $html, 'Alpha course fixture' ), 'Public cards follow the saved subject order.' );
	subjects_check( 2 === substr_count( $html, '>Start course</a>' ), 'Visitors can start either course through its enrollment overview.' );
	subjects_check( 401 === subjects_api( 'GET', 'subjects/' . $subject_id . '/courses' )->get_status(), 'Anonymous authoring read denied.' );
	subjects_check(
		401 === subjects_api(
			'PUT',
			'subjects/' . $subject_id . '/courses',
			array(
				'course_ids' => $wanted,
				'revision'   => $revision,
			)
		)->get_status(),
		'Anonymous ordering denied.'
	);
	wp_set_current_user( $subjects_state['actor'] );
	$new_course                  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'New placement fixture',
			'post_status' => 'draft',
		)
	);
	$subjects_state['courses'][] = $new_course;
	$subjects_state['posts'][]   = $new_course;
	Links::add( $subject_id, 'course', $new_course );
	$new_view = SubjectCourses::view( $subject_id );
	subjects_check( $new_course === $new_view['courses'][5]['id'], 'A newly linked course appends after the authored order.' );
	subjects_check(
		409 === subjects_api(
			'PUT',
			'subjects/' . $subject_id . '/courses',
			array(
				'course_ids' => $wanted,
				'revision'   => $revision,
			)
		)->get_status(),
		'Placement changes invalidate an earlier ordering revision.'
	);
	Links::remove( $subject_id, 'course', $new_course );
	subjects_check( 'draft' === get_post_status( $new_course ) && array_column( SubjectCourses::view( $subject_id )['courses'], 'id' ) === $wanted, 'Removing a placement preserves the course and keeps remaining order.' );
	$empty_subject             = Items::create(
		array(
			'name'      => 'Empty subject deletion fixture',
			'item_type' => 'subject',
		)
	);
	$subjects_state['items'][] = (int) $empty_subject['id'];
	update_option( SubjectCourses::OPTION_PREFIX . $empty_subject['id'], array(), false );
	subjects_check( ! is_wp_error( Items::delete( (int) $empty_subject['id'] ) ) && false === get_option( SubjectCourses::OPTION_PREFIX . $empty_subject['id'] ), 'Deleting a subject removes only its ordering storage.' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicitly tracked fixture enrollment or progress.
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_enrollment',
		array(
			'user_id'    => $subjects_state['actor'],
			'course_id'  => $zulu,
			'status'     => 'enrolled',
			'progress'   => 'in-progress',
			'start_date' => current_time( 'mysql', true ),
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary fixture enrollment.
	$enrollment                      = $wpdb->insert_id;
	$subjects_state['enrollments'][] = $enrollment;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicitly tracked fixture enrollment or progress.
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_progress',
		array(
			'enrollment_id' => $enrollment,
			'content_id'    => $subjects_state['lessons'][0],
			'content_type'  => 'text',
			'status'        => 'completed',
			'start_date'    => current_time( 'mysql', true ),
		)
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary fixture completion.
	$html = Subjects::render( $subject_id );
	subjects_check( false !== strpos( $html, '>Continue learning</a>' ), 'Enrolled learners continue at their next available activity.' );
	subjects_check( false !== strpos( $html, '>Start course</a>' ), 'An unstarted course still offers its enrollment overview.' );
	if ( 'browser' === ( $argv[1] ?? '' ) ) {
		$expiry                    = time() + HOUR_IN_SECONDS;
		$token                     = WP_Session_Tokens::get_instance( $subjects_state['actor'] )->create( $expiry );
		$subjects_state['token']   = $token;
		$subjects_state['cookies'] = array();
		foreach ( array(
			LOGGED_IN_COOKIE => 'logged_in',
			AUTH_COOKIE      => 'auth',
		) as $cookie => $scheme ) {
			$subjects_state['cookies'][] = array(
				'name'     => $cookie,
				'value'    => wp_generate_auth_cookie( $subjects_state['actor'], $expiry, $scheme, $token ),
				'domain'   => wp_parse_url( home_url(), PHP_URL_HOST ),
				'path'     => '/',
				'expires'  => $expiry,
				'httpOnly' => true,
				'secure'   => false,
				'sameSite' => 'Lax',
			);
		}
		file_put_contents( $subjects_path, wp_json_encode( $subjects_state ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Ignored, temporary local browser fixture.
		$subjects_keep = true;
	}
	echo 'PASS: ' . (int) $subjects_checks . " subject integration checks.\n";
} finally {
	if ( ! $subjects_keep ) {
		subjects_cleanup( $subjects_state );
	}
}
