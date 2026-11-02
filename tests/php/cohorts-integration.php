<?php
/**
 * Cohorts (`OhMyLMS\Cohorts`, `ohmylms/v1/cohorts`) against the Local site.
 *
 * Creates tagged courses, a plan, a lesson, learners and cohorts; checks permissions, validation,
 * automatic assignment on enrolment, capacity, the enrolment window at checkout and for points,
 * the window sync, members and the access rules on lessons. Removes everything in `finally`.
 *
 * Usage (set OHMYLMS_COHORTS_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/cohorts-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_COHORTS_TEST' ) ) {
	exit( "Set OHMYLMS_COHORTS_TEST=1 to run the cohort tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Cohorts\CohortHooks;
use OhMyLMS\Cohorts\Cohorts;
use OhMyLMS\Cohorts\CohortSchema;
use OhMyLMS\Student\Enrollments;

global $wpdb;

$checks  = 0;
$tag     = 'cotest' . strtolower( wp_generate_password( 6, false, false ) );
$posts   = array();
$users   = array();
$cohorts = array();
$exit    = 0;

add_filter( 'pre_wp_mail', '__return_true' );

/**
 * Assert.
 *
 * @param mixed  $value   Truthy value.
 * @param string $message Failure message.
 * @throws RuntimeException On failure.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * REST call below /ohmylms/v1/cohorts.
 *
 * @param string $method Method.
 * @param string $path   Path.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function co_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/cohorts' . $path );
	if ( 'GET' === $method || 'DELETE' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Status.
 * @param string           $code     Code.
 * @param string           $label    Label.
 */
function co_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	ok( $status === $response->get_status() && $code === ( $data['code'] ?? '' ), "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
}

/**
 * Site-time date offset by days.
 *
 * @param int $days Days from now.
 * @return string ISO date-time.
 */
function co_day( $days ) {
	return str_replace( ' ', 'T', gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + $days * DAY_IN_SECONDS ) );
}

/**
 * Create a cohort through the API.
 *
 * @param array $body Body.
 * @return array
 */
function co_create( array $body ) {
	global $cohorts;
	$response = co_api( 'POST', '', $body );
	ok( 201 === $response->get_status(), 'cohort created: ' . wp_json_encode( $response->get_data() ) );
	$cohorts[] = (int) $response->get_data()['id'];
	return $response->get_data();
}

try {
	CohortSchema::ensure();
	$admin = (int) get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	)[0];
	foreach ( array( 'ann', 'ben', 'cat', 'dan', 'sub' ) as $who ) {
		$users[ $who ] = wp_insert_user(
			array(
				'user_login'   => $tag . '_' . $who,
				'user_pass'    => wp_generate_password( 24 ),
				'user_email'   => $tag . '_' . $who . '@example.invalid',
				'display_name' => ucfirst( $who ) . ' ' . $tag,
				'role'         => 'subscriber',
			)
		);
	}
	$course  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => $tag . ' course',
		)
	);
	$closed  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'publish',
			'post_title'  => $tag . ' closed',
		)
	);
	$plan    = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_MEMBERSHIP_CPT,
			'post_status' => 'publish',
			'post_title'  => $tag . ' plan',
		)
	);
	$lesson  = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_status' => 'publish',
			'post_title'  => $tag . ' lesson',
		)
	);
	$posts   = array( $course, $closed, $plan, $lesson );

	// Permissions.
	wp_set_current_user( 0 );
	co_error( co_api( 'GET' ), 401, 'ohmylms_cohort_forbidden', 'anonymous list' );
	wp_set_current_user( $users['sub'] );
	co_error(
		co_api(
			'POST',
			'',
			array(
				'scope'      => 'course',
				'course_id'  => $course,
				'title'      => 'x',
				'start_date' => co_day( 1 ),
			)
		),
		403,
		'ohmylms_cohort_forbidden',
		'subscriber create'
	);
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE course_id = %d', CohortSchema::cohorts(), $course ) ), 'denied create wrote nothing' );

	// Validation.
	wp_set_current_user( $admin );
	co_error( co_api( 'POST', '', array( 'title' => 'x' ) ), 422, 'ohmylms_cohort_invalid', 'scope required' );
	co_error( co_api( 'POST', '', array( 'scope' => 'course', 'course_id' => $plan, 'title' => 'x', 'start_date' => co_day( 1 ) ) ), 422, 'ohmylms_cohort_invalid', 'course id must be a course' );
	co_error( co_api( 'POST', '', array( 'scope' => 'course', 'course_id' => $course, 'title' => 'x', 'start_date' => 'tomorrow' ) ), 422, 'ohmylms_cohort_invalid', 'bad date' );
	co_error( co_api( 'POST', '', array( 'scope' => 'course', 'course_id' => $course, 'title' => 'x', 'start_date' => co_day( 5 ), 'end_date' => co_day( 2 ) ) ), 422, 'ohmylms_cohort_invalid', 'end before start' );
	co_error( co_api( 'POST', '', array( 'scope' => 'course', 'course_id' => $course, 'title' => 'x', 'start_date' => co_day( 1 ), 'end_date' => co_day( 5 ), 'enrollment_end' => co_day( 9 ) ) ), 422, 'ohmylms_cohort_invalid', 'enrolment closes after end' );
	co_error( co_api( 'POST', '', array( 'scope' => 'course', 'course_id' => $course, 'title' => 'x', 'start_date' => co_day( 1 ), 'capacity' => 0 ) ), 422, 'ohmylms_cohort_invalid', 'capacity 0' );
	co_error( co_api( 'POST', '', array( 'scope' => 'course', 'course_id' => $course, 'title' => 'x', 'start_date' => co_day( 1 ), 'sql' => 'DROP' ) ), 400, 'ohmylms_cohort_unknown_field', 'unknown field' );
	$invalid = co_api( 'POST', '', array( 'scope' => 'course', 'course_id' => $course, 'title' => '', 'start_date' => co_day( 1 ) ) );
	ok( 422 === $invalid->get_status() && isset( $invalid->get_data()['data']['params']['title'] ), 'field messages in params' );

	// Course cohort: starts in two days, one seat.
	$early = co_create(
		array(
			'scope'          => 'course',
			'course_id'      => $course,
			'title'          => 'Early <b>batch</b>',
			'start_date'     => co_day( 2 ),
			'end_date'       => co_day( 30 ),
			'enrollment_end' => co_day( 20 ),
			'capacity'       => 1,
		)
	);
	ok( 'Early batch' === $early['title'] && 'course' === $early['scope'] && 1 === $early['capacity'] && 1 === $early['seats_left'] && 'upcoming' === $early['state'] && $early['enrollment_open'], 'course cohort fields: ' . wp_json_encode( $early ) );
	ok( 'cohort-based' === get_post_meta( $course, '_type', true ), 'course became cohort-based' );
	$late = co_create(
		array(
			'scope'      => 'course',
			'course_id'  => $course,
			'title'      => 'Late batch',
			'start_date' => co_day( 10 ),
		)
	);
	$plan_cohort = co_create(
		array(
			'scope'               => 'membership',
			'membership_id'       => $plan,
			'title'               => 'Plan cohort',
			'start_date'          => co_day( -1 ),
			'end_date'            => co_day( -0.01 ),
			'close_access_at_end' => true,
		)
	);
	ok( 'membership' === $plan_cohort['scope'] && $plan === $plan_cohort['membership_id'] && $plan_cohort['close_access_at_end'], 'plan cohort fields' );

	co_error( co_api( 'PATCH', '/' . $early['id'], array( 'scope' => 'membership' ) ), 422, 'ohmylms_cohort_invalid', 'scope cannot change' );
	co_error( co_api( 'PATCH', '/' . $early['id'], array( 'course_id' => $closed ) ), 422, 'ohmylms_cohort_invalid', 'course cannot change' );

	// List and headers.
	$list = co_api( 'GET', '', array( 'course_id' => $course ) );
	ok( 200 === $list->get_status() && '2' === $list->get_headers()['X-WP-Total'] && 2 === count( $list->get_data() ), 'list filtered by course' );
	$search = co_api( 'GET', '', array( 'search' => 'Late' ) );
	ok( in_array( $late['id'], array_column( $search->get_data(), 'id' ), true ), 'search by title' );

	// Automatic assignment on manual enrolment (earliest open cohort with a seat).
	ok( true === Enrollments::enroll_manually( $users['ann'], $course ), 'ann enrolled' );
	ok( 1 === Cohorts::member_count( $early['id'] ), 'ann joined the early cohort' );
	ok( true === Enrollments::enroll_manually( $users['ben'], $course ), 'ben enrolled' );
	ok( 1 === Cohorts::member_count( $early['id'] ) && 1 === Cohorts::member_count( $late['id'] ), 'ben went to the late cohort because early is full' );

	// Manual membership.
	co_error( co_api( 'POST', '/' . $late['id'] . '/members', array( 'user_id' => $users['cat'] ) ), 409, 'ohmylms_cohort_not_enrolled', 'not enrolled learner refused' );
	co_error( co_api( 'POST', '/' . $early['id'] . '/members', array( 'user_id' => $users['ben'] ) ), 409, 'ohmylms_cohort_full', 'full cohort refused' );
	co_error( co_api( 'POST', '/' . $late['id'] . '/members', array( 'user_id' => $users['ben'] ) ), 409, 'ohmylms_cohort_member_exists', 'duplicate member refused' );
	$members = co_api( 'GET', '/' . $late['id'] . '/members' );
	ok( 200 === $members->get_status() && 1 === count( $members->get_data() ) && $users['ben'] === $members->get_data()[0]['user_id'] && 'enrolment' === $members->get_data()[0]['source'], 'member list: ' . wp_json_encode( $members->get_data() ) );
	ok( false === strpos( wp_json_encode( $members->get_data() ), '@example.invalid' ), 'member list carries no email' );
	$added = co_api( 'POST', '/' . $late['id'] . '/members', array( 'user_id' => $users['ann'] ) );
	ok( 201 === $added->get_status() && 2 === $added->get_data()['member_count'], 'enrolled learner added by hand' );
	co_error( co_api( 'PATCH', '/' . $late['id'], array( 'capacity' => 1 ) ), 422, 'ohmylms_cohort_invalid', 'capacity below the member count' );
	$candidates = co_api( 'GET', '/' . $late['id'] . '/candidates' );
	ok( 200 === $candidates->get_status() && array() === $candidates->get_data(), 'no candidates left (everyone enrolled is a member)' );
	$removed = co_api( 'DELETE', '/' . $late['id'] . '/members/' . $users['ann'] );
	ok( 200 === $removed->get_status() && 1 === $removed->get_data()['member_count'], 'member removed' );
	co_error( co_api( 'DELETE', '/' . $late['id'] . '/members/' . $users['ann'] ), 404, 'ohmylms_cohort_member_not_found', 'second removal' );

	// Closed enrolment: checkout and points refuse the course.
	$past = co_create(
		array(
			'scope'          => 'course',
			'course_id'      => $closed,
			'title'          => 'Past batch',
			'start_date'     => co_day( -20 ),
			'end_date'       => co_day( 20 ),
			'enrollment_end' => co_day( -10 ),
		)
	);
	ok( ! $past['enrollment_open'] && 'running' === $past['state'], 'past window is closed' );
	$error = CohortHooks::closed_error( $closed );
	ok( is_wp_error( $error ) && 'ohmylms_cohort_enrolment_closed' === $error->get_error_code(), 'closed course refused' );
	ok( null === CohortHooks::closed_error( $course ), 'open course accepted' );
	$point_error = apply_filters( 'ohmylms_point_purchase_course_error', null, $closed, $users['cat'] );
	ok( is_wp_error( $point_error ), 'points purchase refused for the closed course' );
	$cart = \OhMyLMS\Commerce\ecommerce()->cart;
	$cart->empty_cart();
	$cart->set_cart_contents( array( 'k' => array( 'course_id' => $closed ) ) );
	$bag = new WP_Error();
	do_action( 'ohmylms_after_checkout_validation', array(), $bag );
	ok( 'ohmylms_cohort_enrolment_closed' === $bag->get_error_code(), 'checkout validation adds the closed error' );
	$cart->set_cart_contents( array( 'k' => array( 'course_id' => $course ) ) );
	$bag = new WP_Error();
	do_action( 'ohmylms_after_checkout_validation', array(), $bag );
	ok( ! $bag->has_errors(), 'checkout accepts the open course' );
	$cart->empty_cart();
	ok( true === Enrollments::enroll_manually( $users['cat'], $closed ), 'manual enrolment still works on a closed course' );
	ok( 0 === Cohorts::member_count( $past['id'] ), 'closed window assigns nobody' );

	// Window sync: learners whose enrolment started inside the window.
	$wpdb->update( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'start_date' => str_replace( 'T', ' ', co_day( -15 ) ) ), array( 'user_id' => $users['cat'], 'course_id' => $closed ) );
	co_error( co_api( 'POST', '/' . $past['id'] . '/sync', array( 'confirm' => false ) ), 400, 'ohmylms_cohort_confirm_required', 'sync needs confirmation' );
	$sync = co_api( 'POST', '/' . $past['id'] . '/sync', array( 'confirm' => true ) );
	ok( 200 === $sync->get_status() && 1 === $sync->get_data()['added'] && 1 === $sync->get_data()['cohort']['member_count'], 'sync added the learner: ' . wp_json_encode( $sync->get_data() ) );
	ok( 0 === co_api( 'POST', '/' . $past['id'] . '/sync', array( 'confirm' => true ) )->get_data()['added'], 'sync is idempotent' );

	// Access: before the member's cohort starts, during it, after a closing end.
	ok( true === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $course, $users['ann'] ), 'locked before the cohort starts' );
	ok( false === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $closed, $users['cat'] ), 'open while the cohort runs' );
	ok( false === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $closed, $users['dan'] ), 'non-members are not affected' );
	co_api( 'PATCH', '/' . $past['id'], array( 'end_date' => co_day( -1 ), 'enrollment_end' => co_day( -12 ) ) );
	ok( false === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $closed, $users['cat'] ), 'ended cohort without closing access stays open' );
	co_api( 'PATCH', '/' . $past['id'], array( 'close_access_at_end' => true ) );
	ok( true === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $closed, $users['cat'] ), 'ended cohort closes access' );
	co_api( 'PATCH', '/' . $past['id'], array( 'close_access_at_end' => false, 'end_date' => co_day( 20 ) ) );

	// Drip from the member's own cohort start (the learner's cohort started 20 days ago).
	update_post_meta(
		$lesson,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'cohort-from-x-days',
			'days'   => 10,
		)
	);
	ok( false === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $closed, $users['cat'] ), 'drip 10 days after the member cohort start is open' );
	update_post_meta(
		$lesson,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'cohort-from-x-days',
			'days'   => 30,
		)
	);
	ok( true === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $closed, $users['cat'] ), 'drip 30 days after the member cohort start is locked' );
	ok( true === apply_filters( 'ohmylms_is_lesson_locked', true, $lesson, $closed, $users['cat'] ), 'an earlier lock is kept' );

	// Plan cohort: buying the plan joins it; ended plan cohort closes access to plan courses.
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_user_membership',
		array(
			'user_id'       => $users['dan'],
			'membership_id' => $plan,
			'status'        => 'enrolled',
			'progress'      => 'running',
			'start_date'    => current_time( 'mysql' ),
		)
	);
	ok( true === Cohorts::add_member( $plan_cohort['id'], $users['dan'], 'membership' ), 'plan holder joins the plan cohort' );
	Enrollments::create(
		array(
			'user_id'       => $users['dan'],
			'course_id'     => $course,
			'membership_id' => $plan,
			'status'        => 'enrolled',
			'progress'      => 'running',
			'start_date'    => current_time( 'mysql' ),
		),
		'detect'
	);
	ok( true === apply_filters( 'ohmylms_is_lesson_locked', false, $lesson, $course, $users['dan'] ), 'ended plan cohort closes the plan course' );

	// Archive and delete.
	co_error( co_api( 'DELETE', '/' . $past['id'] ), 409, 'ohmylms_cohort_has_members', 'cohort with members cannot be deleted' );
	$archived = co_api( 'PATCH', '/' . $past['id'], array( 'status' => 'archived' ) );
	ok( 200 === $archived->get_status() && 'archived' === $archived->get_data()['status'], 'cohort archived' );
	co_error( co_api( 'POST', '/' . $past['id'] . '/members', array( 'user_id' => $users['cat'] ) ), 409, 'ohmylms_cohort_archived', 'archived cohort takes no members' );
	ok( '1' === co_api( 'GET', '', array( 'course_id' => $closed, 'status' => 'archived' ) )->get_headers()['X-WP-Total'], 'archived filter' );
	$empty = co_create(
		array(
			'scope'      => 'course',
			'course_id'  => $closed,
			'title'      => 'Empty',
			'start_date' => co_day( 40 ),
		)
	);
	$deleted = co_api( 'DELETE', '/' . $empty['id'] );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'] && null === Cohorts::row( $empty['id'] ), 'empty cohort deleted' );
	co_error( co_api( 'GET', '/' . $empty['id'] ), 404, 'ohmylms_cohort_not_found', 'deleted cohort is gone' );
	$targets = co_api( 'GET', '/targets', array( 'scope' => 'membership', 'search' => $tag ) );
	ok( 200 === $targets->get_status() && $plan === $targets->get_data()[0]['id'], 'plan picker finds the plan' );

	echo "Cohorts integration: {$checks} checks passed.\n";
} catch ( Throwable $e ) {
	echo $e->getMessage(), "\n", $e->getTraceAsString(), "\n";
	$exit = 1;
} finally {
	foreach ( $cohorts as $id ) {
		$wpdb->delete( CohortSchema::members(), array( 'cohort_id' => $id ) );
		$wpdb->delete( CohortSchema::cohorts(), array( 'id' => $id ) );
	}
	foreach ( $users as $user_id ) {
		if ( is_int( $user_id ) ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user_id ) );
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_membership', array( 'user_id' => $user_id ) );
			if ( \OhMyLMS\Student\EnrolmentSchema::exists() ) {
				$wpdb->delete( $wpdb->prefix . 'ohmylms_enrolments', array( 'user_id' => $user_id ) );
			}
		}
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user_id ) {
		if ( is_int( $user_id ) ) {
			wp_delete_user( $user_id );
		}
	}
	exit( $exit );
}
