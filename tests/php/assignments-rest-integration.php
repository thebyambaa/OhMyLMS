<?php
/**
 * Assignments REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It creates
 * tagged assignments, courses, chapters, learner submissions (through the learner submission service),
 * an upload and temporary users, and removes everything in `finally`. Outgoing mail is intercepted.
 *
 * Usage (set OHMYLMS_ASSIGNMENTS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/assignments-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ASSIGNMENTS_TEST' ) ) {
	exit( "Set OHMYLMS_ASSIGNMENTS_TEST=1 to run the assignments REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Data\Assignment;

global $wpdb;

$checks   = 0;
$post_ids = array();
$user_ids = array();
$files    = array();
$tag      = 'astest' . strtolower( wp_generate_password( 6, false, false ) );
$fired    = array(
	'insert'    => 0,
	'deleted'   => 0,
	'submitted' => 0,
	'reviews'   => array(),
	'mail'      => array(),
);

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
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/assignments.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/assignments' . $path );
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
 * @param int              $status   Expected status.
 * @param string           $code     Expected error code.
 * @param string           $label    Description.
 */
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	$got  = isset( $data['code'] ) ? $data['code'] : '';
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got );
}

/**
 * Create an assignment through the API and remember it for cleanup.
 *
 * @param array $data Payload.
 * @return array Response data.
 */
function make_assignment( array $data ) {
	global $post_ids;
	$response = call_api( 'POST', '', $data );
	ok( 201 === $response->get_status(), 'assignment created: ' . wp_json_encode( $response->get_data() ) );
	$post_ids[] = $response->get_data()['id'];
	return $response->get_data();
}

/**
 * Insert a tagged post.
 *
 * @param array $args Post fields.
 * @return int
 */
function make_post( array $args ) {
	global $post_ids;
	$id = wp_insert_post( $args, true );
	ok( $id && ! is_wp_error( $id ), 'post created: ' . $args['post_title'] );
	$post_ids[] = (int) $id;
	return (int) $id;
}

/**
 * Create a temporary user.
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role ) {
	global $user_ids, $tag;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $suffix . '@example.invalid',
			'display_name' => $tag . ' ' . ucfirst( $suffix ),
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role created" );
	$user_ids[] = (int) $id;
	return (int) $id;
}

/**
 * Place content in a course through a new chapter.
 *
 * @param int $course  Course ID.
 * @param int $content Content ID.
 * @return int Chapter ID.
 */
function place( $course, $content ) {
	global $wpdb, $tag;
	$chapter = make_post(
		array(
			'post_type'   => 'ohmylms-chapter',
			'post_title'  => "$tag chapter $course",
			'post_status' => 'publish',
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_chapter_relationship',
		array(
			'course_id'    => $course,
			'chapter_id'   => $chapter,
			'order_number' => 1,
		)
	);
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_content_relationship',
		array(
			'chapter_id'   => $chapter,
			'content_id'   => $content,
			'content_type' => 'assignment',
			'order_number' => 1,
		)
	);
	return $chapter;
}

/**
 * Submit as a learner through the service the learner front end uses after `wp_handle_upload()`.
 *
 * @param int    $assignment Assignment ID.
 * @param int    $learner    Learner ID.
 * @param int    $course     Course ID.
 * @param string $text       Submission text.
 * @param string $name       Upload file name.
 * @return int Submission ID.
 */
function submit( $assignment, $learner, $course, $text, $name ) {
	global $wpdb, $files;
	$uploads = wp_upload_dir();
	wp_mkdir_p( $uploads['path'] );
	$path = trailingslashit( $uploads['path'] ) . $name;
	file_put_contents( $path, "Learner work: $text\n" );
	$files[] = $path;
	wp_set_current_user( $learner );
	( new Assignment( $assignment ) )->submit_file_submission(
		$learner,
		$course,
		array(
			'content' => $text,
			'files'   => array(
				'file' => $path,
				'url'  => trailingslashit( $uploads['url'] ) . $name,
				'type' => 'text/plain',
			),
		)
	);
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i WHERE assignment_id = %d AND user_id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', $assignment, $learner ) );
}

add_action(
	'ohmylms_rest_insert_assignment',
	static function () use ( &$fired ) {
		++$fired['insert'];
	}
);
add_action(
	'ohmylms_rest_delete_assignment',
	static function () use ( &$fired ) {
		++$fired['deleted'];
	}
);
add_action(
	'ohmylms_assignment_submitted',
	static function () use ( &$fired ) {
		++$fired['submitted'];
	}
);
add_action(
	'ohmylms_after_assignment_review',
	static function ( $assignment, $course, $student, $status ) use ( &$fired ) {
		$fired['reviews'][] = array( (int) $assignment, (int) $course, (int) $student, (string) $status );
	},
	5,
	4
);
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$fired ) {
		$fired['mail'][] = $atts;
		return true;
	},
	10,
	2
);

try {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin       = (int) $admins[0];
	$subscriber  = make_user( 'sub', 'subscriber' );
	$author      = make_user( 'author', 'author' );
	$other       = make_user( 'other', 'author' );
	$contributor = make_user( 'contrib', 'contributor' );
	$learner     = make_user( 'learner', 'subscriber' );
	$learner2    = make_user( 'learnertwo', 'subscriber' );

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/assignments', '/assignments/courses', '/assignments/(?P<id>[\d]+)', '/assignments/(?P<id>[\d]+)/submissions', '/assignments/(?P<id>[\d]+)/submissions/(?P<submission_id>[\d]+)', '/assignments/(?P<id>[\d]+)/submissions/(?P<submission_id>[\d]+)/file' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}
	$legacy_routes = array_filter(
		array_keys( $routes ),
		static function ( $route ) {
			return 0 === strpos( $route, '/ohmylms/v1/assignment/' ) || '/ohmylms/v1/assignment' === $route;
		}
	);
	ok( array() === array_values( $legacy_routes ), 'the legacy assignment routes are removed: ' . wp_json_encode( array_values( $legacy_routes ) ) );
	ok( method_exists( \OhMyLMS\Rest\V1\AssignmentController::class, 'update_attempt_report' ), 'the shared grading pipeline stays' );

	// Courses: one owned by the author, one by the administrator.
	$course_a = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => "$tag course A",
			'post_status' => 'publish',
			'post_author' => $author,
		)
	);
	$course_b = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => "$tag course B",
			'post_status' => 'publish',
			'post_author' => $admin,
		)
	);

	// Validation, as the author. None of these may write.
	wp_set_current_user( $author );
	$bad = array( 'title' => "$tag bad" );
	ok_error( call_api( 'POST', '', array() ), 400, 'rest_missing_callback_param', 'title required' );
	ok_error( call_api( 'POST', '', array( 'title' => '   ' ) ), 422, 'ohmylms_assignment_invalid_title', 'blank title' );
	ok_error( call_api( 'POST', '', array( 'title' => str_repeat( 'x', 201 ) ) ), 400, 'rest_invalid_param', 'title too long' );
	ok_error( call_api( 'POST', '', $bad + array( 'status' => 'trash' ) ), 400, 'rest_invalid_param', 'status enum' );
	ok_error( call_api( 'POST', '', $bad + array( 'max_score' => 0 ) ), 400, 'rest_invalid_param', 'max score minimum' );
	ok_error( call_api( 'POST', '', $bad + array( 'max_score' => 1001 ) ), 400, 'rest_invalid_param', 'max score maximum' );
	ok_error( call_api( 'POST', '', $bad + array( 'max_score' => 'many' ) ), 400, 'rest_invalid_param', 'max score type' );
	ok_error( call_api( 'POST', '', $bad + array( 'time_limit_unit' => 'year' ) ), 400, 'rest_invalid_param', 'time unit enum' );
	ok_error( call_api( 'POST', '', $bad + array( 'max_files' => 21 ) ), 400, 'rest_invalid_param', 'max files maximum' );
	ok_error( call_api( 'POST', '', $bad + array( 'max_file_size_mb' => 0 ) ), 400, 'rest_invalid_param', 'file size minimum' );
	ok_error(
		call_api(
			'POST',
			'',
			$bad + array(
				'max_score'  => 10,
				'pass_score' => 11,
			)
		),
		422,
		'ohmylms_assignment_invalid_pass_score',
		'pass above max'
	);
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", OHMYLMS_ASSIGNMENT_CPT, "$tag bad" ) ), 'rejected creates wrote nothing' );

	// Create with defaults.
	$created = call_api(
		'POST',
		'',
		array(
			'title'        => "$tag alpha",
			'instructions' => '<p>Write an essay.</p><script>alert(1)</script>',
		)
	);
	ok( 201 === $created->get_status(), 'alpha created' );
	$alpha      = $created->get_data();
	$post_ids[] = $alpha['id'];
	ok( rest_url( 'ohmylms/v1/assignments/' . $alpha['id'] ) === $created->get_headers()['Location'], 'Location header' );
	ok( 'draft' === $alpha['status'] && $author === $alpha['author_id'] && true === $alpha['can_edit'], 'starts as the author\'s draft' );
	ok( 50 === $alpha['max_score'] && 25 === $alpha['pass_score'] && false === $alpha['time_limit_enabled'] && 10 === $alpha['time_limit'] && 'week' === $alpha['time_limit_unit'], 'score and time defaults' );
	ok( true === $alpha['allow_file_uploads'] && 5 === $alpha['max_files'] && true === $alpha['file_size_limit_enabled'] && 5 === $alpha['max_file_size_mb'], 'upload defaults' );
	ok( false !== strpos( $alpha['instructions'], '<p>Write an essay.</p>' ) && false === strpos( $alpha['instructions'], '<script' ), 'instructions are sanitised' );
	ok( 0 === $alpha['submission_count'] && null === $alpha['course'] && array() === $alpha['placements'], 'no submissions or placement yet' );
	ok( '50' === get_post_meta( $alpha['id'], '_total_points', true ) && '1' === get_post_meta( $alpha['id'], '_allow_upload_files', true ) && 'assignment' === get_post_meta( $alpha['id'], '_type', true ), 'stored in the legacy meta format' );
	ok( $fired['insert'] >= 1, 'ohmylms_rest_insert_assignment fired on create' );
	$legacy = new Assignment( $alpha['id'] );
	ok( '50' === $legacy->get_total_points() && true === $legacy->get_allow_upload_files() && $tag . ' alpha' === $legacy->get_name(), 'the legacy data class reads what the API wrote' );

	// Partial update, stale save, rejected update.
	$patched = call_api(
		'PATCH',
		'/' . $alpha['id'],
		array(
			'max_score'          => 80,
			'time_limit_enabled' => true,
			'time_limit'         => 2,
			'time_limit_unit'    => 'month',
			'allow_file_uploads' => false,
		)
	);
	$p       = $patched->get_data();
	ok( 200 === $patched->get_status() && 80 === $p['max_score'] && 25 === $p['pass_score'] && true === $p['time_limit_enabled'] && 2 === $p['time_limit'] && 'month' === $p['time_limit_unit'], 'partial update' );
	ok( $tag . ' alpha' === $p['title'] && 'draft' === $p['status'] && false !== strpos( $p['instructions'], 'Write an essay' ), 'partial update keeps other fields' );
	ok( false === $p['allow_file_uploads'] && '0' === get_post_meta( $alpha['id'], '_allow_upload_files', true ) && false === ( new Assignment( $alpha['id'] ) )->get_allow_upload_files(), 'a false flag is stored as 0 and read as false by learners' );
	ok_error( call_api( 'PATCH', '/' . $alpha['id'], array( 'pass_score' => 81 ) ), 422, 'ohmylms_assignment_invalid_pass_score', 'pass above the stored max' );
	ok_error( call_api( 'PATCH', '/' . $alpha['id'], array( 'title' => '' ) ), 400, 'rest_invalid_param', 'empty title on update' );
	$stale = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $p['updated_at'] ) - 60 );
	ok_error(
		call_api(
			'PATCH',
			'/' . $alpha['id'],
			array(
				'base_modified' => $stale,
				'title'         => "$tag stale",
			)
		),
		409,
		'ohmylms_assignment_conflict',
		'stale save'
	);
	$check = call_api( 'GET', '/' . $alpha['id'], array( 'context' => 'edit' ) )->get_data();
	ok( $tag . ' alpha' === $check['title'] && 25 === $check['pass_score'], 'rejected and stale updates changed nothing' );
	$fresh = call_api(
		'PUT',
		'/' . $alpha['id'],
		array(
			'base_modified'      => $check['updated_at'],
			'title'              => "$tag alpha",
			'allow_file_uploads' => true,
		)
	);
	ok( 200 === $fresh->get_status() && true === $fresh->get_data()['allow_file_uploads'], 'save from the current copy succeeds' );
	$view = call_api( 'GET', '/' . $alpha['id'] )->get_data();
	ok( ! isset( $view['instructions'] ) && ! isset( $view['placements'] ) && isset( $view['max_score'] ), 'view context omits edit-only fields' );

	// Publishing needs publish_posts.
	wp_set_current_user( $contributor );
	ok_error(
		call_api(
			'POST',
			'',
			array(
				'title'  => "$tag contributor",
				'status' => 'publish',
			)
		),
		403,
		'ohmylms_assignment_cannot_publish',
		'contributor cannot publish'
	);

	// Placement in a course.
	wp_set_current_user( $author );
	$chapter_a = place( $course_a, $alpha['id'] );
	$placed    = call_api( 'GET', '/' . $alpha['id'], array( 'context' => 'edit' ) )->get_data();
	ok( $course_a === $placed['course']['id'] && $tag . ' course A' === $placed['course']['title'], 'course of the assignment' );
	ok( 1 === count( $placed['placements'] ) && $chapter_a === $placed['placements'][0]['chapter_id'] && 'chapter' === $placed['placements'][0]['source'], 'placements list the chapter' );
	ok( in_array( $alpha['id'], wp_list_pluck( call_api( 'GET', '', array( 'course' => $course_a ) )->get_data(), 'id' ), true ), 'course filter includes placed assignments' );
	ok( array() === call_api( 'GET', '', array( 'course' => $course_b ) )->get_data(), 'course filter excludes other courses' );

	// The administrator's assignment in course B.
	wp_set_current_user( $admin );
	$beta = make_assignment(
		array(
			'title'  => "$tag beta",
			'status' => 'publish',
		)
	);
	place( $course_b, $beta['id'] );
	$gamma = make_assignment( array( 'title' => "$tag gamma" ) );

	// Learner submissions through the learner service.
	$sub_a    = submit( $alpha['id'], $learner, $course_a, 'My essay', "$tag-essay.txt" );
	$sub_b    = submit( $beta['id'], $learner2, $course_b, 'Other course work', "$tag-other.txt" );
	$sub_g    = submit( $gamma['id'], $learner2, $course_b, 'Orphan work', "$tag-orphan.txt" );
	ok( $sub_a > 0 && $sub_b > 0 && $sub_g > 0 && 3 === $fired['submitted'], 'learner submissions stored and ohmylms_assignment_submitted fired' );
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_assignment_attempts',
		array(
			'user_id'       => $learner2,
			'course_id'     => $course_b,
			'assignment_id' => $beta['id'],
			'files'         => maybe_serialize( array( 'file' => __FILE__ ) ),
			'content'       => 'outside',
			'status'        => 'submitted',
			'start_date'    => current_time( 'mysql' ),
			'end_date'      => current_time( 'mysql', 1 ),
		)
	);
	$sub_outside = (int) $wpdb->insert_id;

	// Denied users: anonymous 401, subscriber 403, another author 403. Nothing is written.
	$denied = array(
		array( 'GET', '' ),
		array( 'POST', '' ),
		array( 'GET', '/courses' ),
		array( 'GET', '/' . $alpha['id'] ),
		array( 'PATCH', '/' . $alpha['id'] ),
		array( 'DELETE', '/' . $alpha['id'] ),
		array( 'GET', '/' . $alpha['id'] . '/submissions' ),
		array( 'GET', '/' . $alpha['id'] . '/submissions/' . $sub_a ),
		array( 'PUT', '/' . $alpha['id'] . '/submissions/' . $sub_a ),
		array( 'GET', '/' . $alpha['id'] . '/submissions/' . $sub_a . '/file' ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
		$learner    => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $denied as $case ) {
			$response = call_api(
				$case[0],
				$case[1],
				'GET' === $case[0] || 'DELETE' === $case[0] ? array() : array(
					'title' => "$tag denied",
					'score' => 1,
				)
			);
			ok( $status === $response->get_status(), "user $user {$case[0]} {$case[1]} is $status, got " . $response->get_status() );
		}
	}
	wp_set_current_user( $other );
	foreach ( array_slice( $denied, 3 ) as $case ) {
		$response = call_api(
			$case[0],
			$case[1],
			'GET' === $case[0] || 'DELETE' === $case[0] ? array() : array(
				'title' => "$tag denied",
				'score' => 1,
			)
		);
		ok( 403 === $response->get_status(), "another author {$case[0]} {$case[1]} is 403, got " . $response->get_status() );
	}
	ok( ! in_array( $alpha['id'], wp_list_pluck( call_api( 'GET', '', array( 'search' => $tag ) )->get_data(), 'id' ), true ), 'another author does not list it' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", OHMYLMS_ASSIGNMENT_CPT, "$tag denied" ) ), 'forbidden create wrote nothing' );
	ok( $tag . ' alpha' === get_post( $alpha['id'] )->post_title && 'submitted' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', $sub_a ) ), 'forbidden update and grade wrote nothing' );

	// Submission list: this assignment only, minimal student data.
	wp_set_current_user( $author );
	$list  = call_api( 'GET', '/' . $alpha['id'] . '/submissions' );
	$rows  = $list->get_data();
	ok( 200 === $list->get_status() && '1' === $list->get_headers()['X-WP-Total'] && 1 === count( $rows ) && $sub_a === $rows[0]['id'], 'only this assignment\'s submissions are listed' );
	ok( array( 'id', 'display_name' ) === array_keys( $rows[0]['student'] ) && $learner === $rows[0]['student']['id'] && $tag . ' Learner' === $rows[0]['student']['display_name'], 'student is id and display name only' );
	ok( false === strpos( wp_json_encode( $rows ), '@example.invalid' ) && ! isset( $rows[0]['content'] ) && ! isset( $rows[0]['files'] ), 'no email, text or file paths in the list' );
	ok( 'submitted' === $rows[0]['status'] && null === $rows[0]['score'] && null === $rows[0]['result'] && 80 === $rows[0]['max_score'] && $course_a === $rows[0]['course']['id'] && 1 === $rows[0]['attachment_count'], 'pending row shape' );
	ok( '' !== $rows[0]['submitted_at'] && null === $rows[0]['graded_at'], 'submitted time' );
	ok( array() === call_api( 'GET', '/' . $alpha['id'] . '/submissions', array( 'course' => $course_b ) )->get_data(), 'course filter on submissions' );
	ok_error( call_api( 'GET', '/' . $alpha['id'] . '/submissions/' . $sub_b ), 404, 'ohmylms_assignment_submission_not_found', 'another assignment\'s submission' );
	ok_error( call_api( 'PUT', '/' . $alpha['id'] . '/submissions/' . $sub_b, array( 'score' => 1 ) ), 404, 'ohmylms_assignment_submission_not_found', 'grading another assignment\'s submission' );
	ok_error( call_api( 'GET', '/' . $alpha['id'] . '/submissions', array( 'page' => 2 ) ), 400, 'ohmylms_assignment_invalid_page', 'submissions page past the end' );
	ok_error( call_api( 'GET', '/' . $alpha['id'] . '/submissions', array( 'status' => 'bogus' ) ), 400, 'rest_invalid_param', 'submission status enum' );
	ok_error( call_api( 'GET', '/' . $beta['id'] . '/submissions' ), 403, 'ohmylms_assignment_cannot_grade', 'author cannot read the admin\'s submissions' );
	$listed = call_api( 'GET', '', array( 'search' => "$tag alpha" ) )->get_data();
	ok( 1 === count( $listed ) && 1 === $listed[0]['submission_count'] && 1 === $listed[0]['pending_count'] && $course_a === $listed[0]['course']['id'], 'counts in the list' );

	// Submission detail and its file.
	$detail = call_api( 'GET', '/' . $alpha['id'] . '/submissions/' . $sub_a )->get_data();
	ok( 'My essay' === $detail['content'] && '' === $detail['feedback'] && 1 === count( $detail['attachments'] ), 'detail has text and one attachment' );
	$file = $detail['attachments'][0];
	ok( "$tag-essay.txt" === $file['name'] && 'text/plain' === $file['type'] && $file['size'] > 0, 'attachment name, type and size' );
	ok( false !== strpos( $file['url'], '/assignments/' . $alpha['id'] . '/submissions/' . $sub_a . '/file' ) && false !== strpos( $file['url'], '_wpnonce=' ), 'attachment URL is the authorised download route' );
	ok( false === strpos( wp_json_encode( $detail ), wp_normalize_path( WP_CONTENT_DIR ) ) && false === strpos( wp_json_encode( $detail ), '/uploads/' ), 'no server path or public upload URL leaks' );
	$download = call_api( 'GET', '/' . $alpha['id'] . '/submissions/' . $sub_a . '/file' );
	$headers  = $download->get_headers();
	ok( 200 === $download->get_status() && "$tag-essay.txt" === $download->get_data()['name'] && 0 === strpos( $headers['Content-Disposition'], 'attachment;' ) && 'nosniff' === $headers['X-Content-Type-Options'], 'download route answers with attachment headers' );
	wp_set_current_user( $admin );
	$outside = call_api( 'GET', '/' . $beta['id'] . '/submissions/' . $sub_outside )->get_data();
	ok( array() === $outside['attachments'] && 0 === $outside['attachment_count'], 'a stored path outside uploads is not offered' );
	ok_error( call_api( 'GET', '/' . $beta['id'] . '/submissions/' . $sub_outside . '/file' ), 404, 'ohmylms_assignment_file_not_found', 'a stored path outside uploads is not served' );

	// Delete is refused while submissions exist.
	ok_error( call_api( 'DELETE', '/' . $beta['id'] ), 409, 'ohmylms_assignment_has_submissions', 'delete blocked by submissions' );
	ok_error( call_api( 'DELETE', '/' . $beta['id'], array( 'force' => true ) ), 409, 'ohmylms_assignment_has_submissions', 'force delete blocked by submissions' );
	ok( 'publish' === get_post_status( $beta['id'] ), 'assignment still published' );

	// Grading: bounds, feedback, legacy pipeline, persistence.
	wp_set_current_user( $author );
	$path = '/' . $alpha['id'] . '/submissions/' . $sub_a;
	ok_error( call_api( 'PUT', $path, array( 'score' => 81 ) ), 422, 'ohmylms_assignment_invalid_score', 'score above max' );
	ok_error( call_api( 'PUT', $path, array( 'score' => -1 ) ), 400, 'rest_invalid_param', 'negative score' );
	ok_error( call_api( 'PUT', $path, array( 'score' => 2.5 ) ), 400, 'rest_invalid_param', 'fractional score' );
	ok_error( call_api( 'PUT', $path, array( 'feedback' => 'x' ) ), 400, 'rest_missing_callback_param', 'score required' );
	ok( 'submitted' === $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', $sub_a ) ) && array() === $fired['reviews'], 'rejected grades changed nothing' );
	$graded = call_api(
		'PUT',
		$path,
		array(
			'score'    => 70,
			'feedback' => '<p>Good work.</p><script>alert(1)</script>',
		)
	);
	$g      = $graded->get_data();
	ok( 200 === $graded->get_status() && 'graded' === $g['status'] && 'passed' === $g['result'] && 70 === $g['score'] && null !== $g['graded_at'], 'graded and passed: ' . wp_json_encode( $g ) );
	ok( false !== strpos( $g['feedback'], '<p>Good work.</p>' ) && false === strpos( $g['feedback'], '<script' ), 'feedback is sanitised' );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT status, score, note FROM %i WHERE id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', $sub_a ) );
	ok( 'passed' === $row->status && 70 === (int) $row->score && false !== strpos( $row->note, 'Good work.' ), 'the grade persisted in the legacy format' );
	ok( array( array( $alpha['id'], $course_a, $learner, 'passed' ) ) === $fired['reviews'], 'ohmylms_after_assignment_review fired as from the legacy screen' );
	$mailed = wp_list_pluck( $fired['mail'], 'to' );
	ok( in_array( $tag . '_learner@example.invalid', array_map( 'strval', $mailed ), true ), 'the learner was emailed: ' . wp_json_encode( $mailed ) );
	$again = call_api( 'GET', $path )->get_data();
	ok( 'graded' === $again['status'] && 70 === $again['score'] && 'passed' === $again['result'], 'a graded submission reads back graded' );
	$regraded = call_api( 'PATCH', $path, array( 'score' => 10 ) )->get_data();
	ok( 'failed' === $regraded['result'] && 10 === $regraded['score'] && false !== strpos( $regraded['feedback'], 'Good work.' ), 'regrade below the pass score fails and keeps feedback' );
	ok( 1 === count( call_api( 'GET', '/' . $alpha['id'] . '/submissions', array( 'status' => 'graded' ) )->get_data() ) && array() === call_api( 'GET', '/' . $alpha['id'] . '/submissions', array( 'status' => 'submitted' ) )->get_data(), 'status filter' );
	ok( 0 === call_api( 'GET', '/' . $alpha['id'] )->get_data()['pending_count'], 'pending count drops' );
	wp_set_current_user( $admin );
	ok_error( call_api( 'PUT', '/' . $gamma['id'] . '/submissions/' . $sub_g, array( 'score' => 5 ) ), 409, 'ohmylms_assignment_not_in_course', 'an unplaced assignment cannot be graded' );

	// Trash, restore and permanent delete.
	wp_set_current_user( $author );
	$delta   = make_assignment( array( 'title' => "$tag delta" ) );
	$before  = $fired['deleted'];
	$trashed = call_api( 'DELETE', '/' . $delta['id'] );
	ok( 200 === $trashed->get_status() && true === $trashed->get_data()['trashed'] && false === $trashed->get_data()['deleted'] && $delta['id'] === $trashed->get_data()['previous']['id'], 'delete moves to the trash' );
	ok( 'trash' === call_api( 'GET', '/' . $delta['id'] )->get_data()['status'] && $fired['deleted'] === $before + 1, 'trashed, ohmylms_rest_delete_assignment fired' );
	ok( in_array( $delta['id'], wp_list_pluck( call_api( 'GET', '', array( 'status' => 'trash' ) )->get_data(), 'id' ), true ) && ! in_array( $delta['id'], wp_list_pluck( call_api( 'GET', '' )->get_data(), 'id' ), true ), 'trash filter' );
	ok( 'draft' === call_api( 'PATCH', '/' . $delta['id'], array( 'status' => 'draft' ) )->get_data()['status'], 'restored by sending a status' );
	$gone = call_api( 'DELETE', '/' . $delta['id'], array( 'force' => true ) );
	ok( 200 === $gone->get_status() && true === $gone->get_data()['deleted'] && null === get_post( $delta['id'] ), 'force delete removes it' );
	ok_error( call_api( 'GET', '/' . $delta['id'] ), 404, 'ohmylms_assignment_not_found', 'deleted assignment is gone' );
	ok_error( call_api( 'GET', '/' . $course_a ), 404, 'ohmylms_assignment_not_found', 'a course id is not an assignment' );
	ok_error( call_api( 'PATCH', '/' . $course_a, array( 'title' => 'x' ) ), 404, 'ohmylms_assignment_not_found', 'update of a non-assignment post' );

	// List: paging, headers, filters, errors.
	for ( $i = 1; $i <= 3; $i++ ) {
		make_assignment( array( 'title' => "$tag page $i" ) );
	}
	$query = array(
		'search'   => "$tag page",
		'per_page' => 2,
		'orderby'  => 'title',
		'order'    => 'asc',
	);
	$page  = call_api( 'GET', '', $query );
	ok( 200 === $page->get_status() && 2 === count( $page->get_data() ) && '3' === $page->get_headers()['X-WP-Total'] && '2' === $page->get_headers()['X-WP-TotalPages'], 'paged list with totals' );
	ok( array( "$tag page 1", "$tag page 2" ) === wp_list_pluck( $page->get_data(), 'title' ), 'ordered by title' );
	ok( false !== strpos( $page->get_headers()['Link'], 'rel="next"' ), 'Link header' );
	ok_error( call_api( 'GET', '', $query + array( 'page' => 3 ) ), 400, 'ohmylms_assignment_invalid_page', 'page past the end' );
	ok_error( call_api( 'GET', '', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page above 100' );
	ok_error( call_api( 'GET', '', array( 'orderby' => 'meta_value' ) ), 400, 'rest_invalid_param', 'orderby enum' );
	ok( 1 === count( call_api( 'GET', '/courses', array( 'search' => "$tag course" ) )->get_data() ), 'course picker lists only the author\'s courses' );

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/assignments' ) )->get_data()['schema'];
	ok( 'ohmylms-assignment' === $schema['title'], 'schema title' );
	foreach ( array( 'id', 'title', 'status', 'instructions', 'placements', 'max_score', 'pass_score', 'time_limit_enabled', 'time_limit', 'time_limit_unit', 'allow_file_uploads', 'max_files', 'file_size_limit_enabled', 'max_file_size_mb', 'submission_count', 'pending_count', 'updated_at' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}

	fwrite( STDOUT, "OK assignments-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	wp_set_current_user( 0 );
	$post_ids = array_map( 'intval', $post_ids );
	$post_ids = array_merge( $post_ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('ohmylms-assignment','ohmylms-course','ohmylms-chapter') AND post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) ) );
	foreach ( array_unique( $post_ids ) as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_assignment_attempts', array( 'assignment_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post_id ) );
		wp_delete_post( $post_id, true );
	}
	if ( $user_ids && \OhMyLMS\Assessment\Schema::ready() ) {
		foreach ( $user_ids as $user_id ) {
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE student_id = %d', \OhMyLMS\Assessment\Schema::table( 'grade_events' ), $user_id ) ) as $event ) {
				$wpdb->delete( \OhMyLMS\Assessment\Schema::table( 'evidence_outbox' ), array( 'grade_event_id' => (int) $event ) );
			}
		}
	}
	if ( $user_ids ) {
		// Rows that grading, completion and provenance hooks wrote for the temporary users.
		$columns = $wpdb->get_results( $wpdb->prepare( "SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s AND COLUMN_NAME IN ('user_id','student_id','learner_id')", $wpdb->esc_like( $wpdb->prefix . 'ohmylms_' ) . '%' ) );
		$holders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
		foreach ( (array) $columns as $column ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE %i IN ({$holders})", array_merge( array( $column->t, $column->c ), $user_ids ) ) );
		}
	}
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
