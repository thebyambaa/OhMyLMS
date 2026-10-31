<?php
/**
 * Assignment delivery integration test: resources, prerequisites, drip release, comments.
 *
 * Runs against the Local site with real tables. It creates tagged courses, chapters, lessons, an
 * assignment, a media attachment, learner submissions and temporary users, and removes everything in
 * `finally`. Outgoing mail is intercepted. The learner gate is exercised through the real AJAX handler.
 *
 * Usage (set OHMYLMS_ASSIGNMENT_DELIVERY_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/assignment-delivery-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_ASSIGNMENT_DELIVERY_TEST' ) ) {
	exit( "Set OHMYLMS_ASSIGNMENT_DELIVERY_TEST=1 to run the assignment delivery tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Assignment\Comments;
use OhMyLMS\Assignment\Delivery;
use OhMyLMS\Data\Assignment;
use OhMyLMS\Emails\EmailManager;
use OhMyLMS\Learning\DripSchedule;

global $wpdb;

$checks   = 0;
$post_ids = array();
$user_ids = array();
$files    = array();
$tag      = 'adtest' . strtolower( wp_generate_password( 6, false, false ) );
$mail     = array();


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
 * Dispatch a REST request below /ohmylms/v1.
 *
 * @param string $method Method.
 * @param string $path   Path.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function rest( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
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
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	$got  = isset( $data['code'] ) ? $data['code'] : '';
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got );
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
 * Temporary user.
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
 * Place content in a chapter of a course.
 *
 * @param int    $chapter Chapter ID.
 * @param int    $content Content ID.
 * @param string $type    Content type.
 * @param int    $order   Order.
 */
function place( $chapter, $content, $type, $order ) {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_content_relationship',
		array(
			'chapter_id'   => $chapter,
			'content_id'   => $content,
			'content_type' => $type,
			'order_number' => $order,
		)
	);
}

/**
 * Run an AJAX handler as the current user and decode its JSON.
 *
 * @param callable $handler Handler.
 * @param array    $post    POST fields.
 * @return array|null
 * @throws RuntimeException When the handler fails other than by finishing its response.
 */
function ajax( $handler, array $post ) {
	$_POST    = $post;
	$_REQUEST = $post;
	$doing    = static function () {
		return true;
	};
	$die      = static function () {
		return static function () {
			throw new RuntimeException( 'ohmylms-ajax-done' );
		};
	};
	add_filter( 'wp_doing_ajax', $doing );
	add_filter( 'wp_die_ajax_handler', $die );
	ob_start();
	try {
		call_user_func( $handler );
	} catch ( RuntimeException $done ) {
		if ( 'ohmylms-ajax-done' !== $done->getMessage() ) {
			throw $done;
		}
	} finally {
		$output = ob_get_clean();
		remove_filter( 'wp_doing_ajax', $doing );
		remove_filter( 'wp_die_ajax_handler', $die );
		$_POST    = array();
		$_REQUEST = array();
	}
	return json_decode( (string) $output, true );
}

/**
 * Count the submissions of a learner.
 *
 * @param int $assignment Assignment.
 * @param int $learner    Learner.
 * @return int
 */
function submissions( $assignment, $learner ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE assignment_id = %d AND user_id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', $assignment, $learner ) );
}

add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mail ) {
		$mail[] = $atts;
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
			'fields'  => 'ID',
			'orderby' => 'ID',
		)
	);
	$admin    = (int) $admins[0];
	$author   = make_user( 'author', 'author' );
	$other    = make_user( 'other', 'author' );
	$learner  = make_user( 'learner', 'subscriber' );
	$learner2 = make_user( 'learnertwo', 'subscriber' );

	// Routes.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/assignments/(?P<id>[\d]+)/prerequisite-options', '/assignments/(?P<id>[\d]+)/learner-state', '/assignments/(?P<id>[\d]+)/submissions/(?P<submission_id>[\d]+)/comments', '/assignments/(?P<id>[\d]+)/submissions/(?P<submission_id>[\d]+)/comments/(?P<comment_id>[\d]+)' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), "route $route" );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ), "permission callback on $route" );
		}
	}

	// Fixtures: a course of the author with one chapter: lesson L1, lesson L2, then the assignment.
	$course  = make_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_title'  => "$tag course",
			'post_status' => 'publish',
			'post_author' => $author,
		)
	);
	$chapter = make_post(
		array(
			'post_type'   => 'ohmylms-chapter',
			'post_title'  => "$tag chapter",
			'post_status' => 'publish',
			'post_author' => $author,
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
	$lesson_one = make_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_title'  => "$tag lesson one",
			'post_status' => 'publish',
			'post_author' => $author,
		)
	);
	update_post_meta( $lesson_one, '_type', 'text' );
	$lesson_two = make_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_title'  => "$tag lesson two",
			'post_status' => 'publish',
			'post_author' => $author,
		)
	);
	update_post_meta( $lesson_two, '_type', 'text' );
	$foreign = make_post(
		array(
			'post_type'   => 'ohmylms-lesson',
			'post_title'  => "$tag foreign lesson",
			'post_status' => 'publish',
			'post_author' => $other,
		)
	);
	wp_set_current_user( $author );
	$created = rest( 'POST', '/assignments', array( 'title' => "$tag essay" ) );
	ok( 201 === $created->get_status(), 'assignment created' );
	$assignment = (int) $created->get_data()['id'];
	$post_ids[] = $assignment;
	place( $chapter, $lesson_one, 'lesson', 1 );
	place( $chapter, $lesson_two, 'lesson', 2 );
	place( $chapter, $assignment, 'assignment', 3 );
	ok( 0 === count( rest( 'GET', "/assignments/$assignment", array( 'context' => 'view' ) )->get_data()['resources'] ?? array() ), 'view context has no delivery fields' );
	$fresh = rest( 'GET', "/assignments/$assignment", array( 'context' => 'edit' ) )->get_data();
	ok( array() === $fresh['resources'] && false === $fresh['prerequisites']['enable'] && false === $fresh['drip']['enable'] && false === $fresh['comments_enabled'], 'new assignment: empty delivery settings' );

	// A media attachment of the author, and one of another author.
	$uploads = wp_upload_dir();
	wp_mkdir_p( $uploads['path'] );
	$brief = trailingslashit( $uploads['path'] ) . "$tag-brief.txt";
	file_put_contents( $brief, str_repeat( 'Brief ', 400 ) );
	$files[]    = $brief;
	$attachment = wp_insert_attachment(
		array(
			'post_title'     => "$tag brief",
			'post_mime_type' => 'text/plain',
			'post_status'    => 'inherit',
			'post_author'    => $author,
		),
		$brief
	);
	$post_ids[] = (int) $attachment;
	$alien      = wp_insert_attachment(
		array(
			'post_title'     => "$tag alien",
			'post_mime_type' => 'text/plain',
			'post_status'    => 'inherit',
			'post_author'    => $other,
		),
		$brief
	);
	$post_ids[] = (int) $alien;

	// Save every delivery field and reopen.
	$base     = $fresh['updated_at'];
	$settings = array(
		'resources'        => array(
			array(
				'type'          => 'file',
				'attachment_id' => (int) $attachment,
				'name'          => 'Assignment brief',
				'url'           => 'https://evil.example/not-this.exe',
				'size'          => '1 TB',
			),
			array(
				'type'          => 'link',
				'attachment_id' => 0,
				'name'          => 'Reading list',
				'url'           => 'https://example.org/reading',
				'size'          => '',
			),
		),
		'prerequisites'    => array(
			'enable' => true,
			'ids'    => array( $lesson_one ),
		),
		'drip'             => array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 3,
			'date'   => '',
			'time'   => '',
		),
		'comments_enabled' => true,
		'base_modified'    => $base,
	);
	sleep( 1 );
	$saved = rest( 'PATCH', "/assignments/$assignment", $settings );
	ok( 200 === $saved->get_status(), 'delivery settings saved: ' . wp_json_encode( $saved->get_data() ) );
	$read = rest( 'GET', "/assignments/$assignment", array( 'context' => 'edit' ) )->get_data();
	ok( 2 === count( $read['resources'] ) && 'file' === $read['resources'][0]['type'] && (int) $attachment === $read['resources'][0]['attachment_id'], 'file resource reopened' );
	ok( wp_get_attachment_url( $attachment ) === $read['resources'][0]['url'] && '1 TB' !== $read['resources'][0]['size'] && '' !== $read['resources'][0]['size'], 'file URL and size come from the media library, not the request' );
	ok( 'link' === $read['resources'][1]['type'] && 'https://example.org/reading' === $read['resources'][1]['url'], 'link resource reopened' );
	ok( true === $read['prerequisites']['enable'] && array( $lesson_one ) === array_column( $read['prerequisites']['items'], 'id' ) && 'lesson' === $read['prerequisites']['items'][0]['type'], 'prerequisites reopened' );
	ok(
		array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 3,
			'date'   => '',
			'time'   => '',
		) === $read['drip'],
		'drip reopened'
	);
	ok( true === $read['comments_enabled'], 'comments switch reopened' );
	$stored_resources = get_post_meta( $assignment, '_download_resource', true );
	$stored_required  = get_post_meta( $assignment, '_prerequisites', true );
	ok( 'file' === $stored_resources['file'][0]['type'] && (int) $attachment === $stored_resources['file'][0]['id'] && isset( $stored_resources['file'][1]['url'] ), 'resources stored as the learner template reads them' );
	ok(
		array(
			array(
				'value' => $lesson_one,
				'label' => "$tag lesson one",
			),
		) === $stored_required['data'] && true === $stored_required['enable'],
		'prerequisites stored in the legacy shape'
	);
	ok( '1' === get_post_meta( $assignment, '_enable_comments', true ), 'comments flag stored as 1' );
	$assignment_object = new Assignment( $assignment );
	ok( ! empty( $assignment_object->get_download_resource()['file'] ) && ! empty( $assignment_object->get_prerequisites()['enable'] ), 'the data class reads the stored settings' );

	// Validation: nothing is written on a rejected save.
	$before = wp_json_encode( Delivery::read( $assignment ) );
	$bad    = array(
		array(
			'resources' => array(
				array(
					'type'          => 'link',
					'attachment_id' => 0,
					'name'          => 'Bad',
					'url'           => 'javascript:alert(1)',
					'size'          => '',
				),
			),
		),
		array(
			'resources' => array(
				array(
					'type'          => 'file',
					'attachment_id' => (int) $alien,
					'name'          => 'Someone else',
					'url'           => '',
					'size'          => '',
				),
			),
		),
		array(
			'resources' => array(
				array(
					'type'          => 'link',
					'attachment_id' => 0,
					'name'          => '   ',
					'url'           => 'https://example.org',
					'size'          => '',
				),
			),
		),
		array(
			'resources' => array(
				array(
					'type' => 'video',
					'name' => 'x',
					'url'  => 'https://example.org',
				),
			),
		),
		array(
			'prerequisites' => array(
				'enable' => true,
				'ids'    => array( $assignment ),
			),
		),
		array(
			'prerequisites' => array(
				'enable' => true,
				'ids'    => array( $course ),
			),
		),
		array(
			'prerequisites' => array(
				'enable' => true,
				'ids'    => array( $foreign ),
			),
		),
		array(
			'prerequisites' => array(
				'enable' => true,
				'ids'    => array( $lesson_one, $lesson_one ),
			),
		),
		array(
			'drip' => array(
				'enable' => true,
				'type'   => 'specific-date',
				'days'   => 0,
				'date'   => '',
				'time'   => '',
			),
		),
		array(
			'drip' => array(
				'enable' => true,
				'type'   => 'specific-date',
				'days'   => 0,
				'date'   => '2026-02-30',
				'time'   => '',
			),
		),
		array(
			'drip' => array(
				'enable' => true,
				'type'   => 'enrollment-from-x-days',
				'days'   => 4000,
				'date'   => '',
				'time'   => '',
			),
		),
		array(
			'drip' => array(
				'enable' => true,
				'type'   => 'weekly',
				'days'   => 1,
				'date'   => '',
				'time'   => '',
			),
		),
		array(
			'drip' => array(
				'enable' => true,
				'type'   => 'specific-date',
				'days'   => 0,
				'date'   => '2026-11-01',
				'time'   => '25:00',
			),
		),
	);
	foreach ( $bad as $index => $payload ) {
		$response = rest( 'PATCH', "/assignments/$assignment", $payload );
		ok( 422 === $response->get_status() && 0 === strpos( $response->get_data()['code'], 'ohmylms_assignment_invalid_' ), "invalid delivery payload $index rejected: " . $response->get_status() . ' ' . ( $response->get_data()['code'] ?? '' ) );
	}
	ok_error( rest( 'PATCH', "/assignments/$assignment", array( 'comments_enabled' => 'yes' ) ), 400, 'rest_invalid_param', 'comments flag type' );
	ok_error( rest( 'PATCH', "/assignments/$assignment", array( 'resources' => array_fill( 0, 21, array( 'type' => 'link' ) ) ) ), 400, 'rest_invalid_param', 'more than 20 resources' );
	ok( Delivery::read( $assignment ) === json_decode( $before, true ), 'rejected saves changed nothing' );

	// Permissions.
	wp_set_current_user( $other );
	ok_error( rest( 'PATCH', "/assignments/$assignment", array( 'comments_enabled' => false ) ), 403, 'ohmylms_assignment_cannot_edit', 'another author cannot change delivery settings' );
	ok_error( rest( 'GET', "/assignments/$assignment/prerequisite-options" ), 403, 'ohmylms_assignment_cannot_edit', 'another author cannot list prerequisite choices' );
	wp_set_current_user( 0 );
	ok_error( rest( 'GET', "/assignments/$assignment/prerequisite-options" ), 401, 'ohmylms_assignment_cannot_edit', 'guests cannot list prerequisite choices' );
	ok_error( rest( 'GET', "/assignments/$assignment/learner-state" ), 401, 'ohmylms_assignment_cannot_view', 'guests have no learner state' );

	// Prerequisite choices: the course's content, without the assignment itself.
	wp_set_current_user( $author );
	$options = rest( 'GET', "/assignments/$assignment/prerequisite-options" )->get_data();
	$ids     = array_column( $options['items'], 'id' );
	ok( 'course' === $options['scope'] && in_array( $lesson_one, $ids, true ) && in_array( $lesson_two, $ids, true ) && ! in_array( $assignment, $ids, true ) && ! in_array( $foreign, $ids, true ), 'prerequisite choices are the course content: ' . wp_json_encode( $ids ) );
	$searched = rest( 'GET', "/assignments/$assignment/prerequisite-options", array( 'search' => 'lesson two' ) )->get_data();
	ok( array( $lesson_two ) === array_column( $searched['items'], 'id' ), 'prerequisite choices are searchable' );

	// Publish and enrol the learner today.
	ok( 200 === rest( 'PATCH', "/assignments/$assignment", array( 'status' => 'publish' ) )->get_status(), 'assignment published' );
	ok( true === \OhMyLMS\Student\Enrollments::enroll_manually( $learner, $course ), 'learner enrolled' );

	// Learner state and the real submission handler.
	wp_set_current_user( $learner );
	$state = rest( 'GET', "/assignments/$assignment/learner-state" )->get_data();
	$opens = strtotime( $state['opens_at'] );
	ok( false === $state['can_submit'] && 'not_open' === $state['reason'] && $opens > time() + 2 * DAY_IN_SECONDS && $opens < time() + 4 * DAY_IN_SECONDS, 'drip: closed for three days after enrolment: ' . wp_json_encode( $state ) );
	ok( false !== strpos( $state['message'], DripSchedule::format( new DateTimeImmutable( $state['opens_at'] ) ) ), 'the learner is told when it opens' );
	$drip_message = apply_filters( 'ohmylms_drip_protection_message', '', $assignment, 'ohmylms-assignment', $learner );
	ok( 0 === strpos( $drip_message, 'This assignment will be available on ' ), 'the assignment page shows the release date: ' . $drip_message );
	$submit = array(
		'action'          => 'save_assignment_submission_file',
		'assignment_id'   => (string) $assignment,
		'course_id'       => (string) $course,
		'submission-body' => 'My essay',
		'nonce'           => wp_create_nonce( 'assignment_submission_nonce' ),
	);
	$result = ajax( array( \OhMyLMS\Ajax::class, 'save_assignment_submission_file' ), $submit );
	ok( false === $result['success'] && 'ohmylms_assignment_not_open' === $result['data']['code'] && 0 === submissions( $assignment, $learner ), 'the server refuses a submission before the release: ' . wp_json_encode( $result ) );

	// Open now: the prerequisite still blocks.
	update_post_meta(
		$assignment,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'enrollment-from-x-days',
			'days'   => 0,
			'date'   => '',
			'time'   => '',
		)
	);
	$state = Delivery::state( $assignment, $learner );
	ok( false === $state['can_submit'] && 'prerequisites' === $state['reason'] && false === $state['prerequisites'][0]['completed'] && '' !== $state['prerequisites'][0]['url'], 'prerequisite incomplete: listed with a link' );
	$result = ajax( array( \OhMyLMS\Ajax::class, 'save_assignment_submission_file' ), $submit );
	ok( false === $result['success'] && 'ohmylms_assignment_prerequisites' === $result['data']['code'] && 0 === submissions( $assignment, $learner ), 'the server refuses a submission with an incomplete prerequisite' );

	// Complete the prerequisite: the gate opens and the handler goes on to the upload.
	ok( ( new \OhMyLMS\Data\Student( $learner ) )->complete_lesson( $lesson_one, $course ), 'learner completes the prerequisite lesson' );
	$state = Delivery::state( $assignment, $learner );
	ok( true === $state['can_submit'] && null === $state['reason'] && true === $state['prerequisites'][0]['completed'], 'gate open after the prerequisite' );
	$result = ajax( array( \OhMyLMS\Ajax::class, 'save_assignment_submission_file' ), $submit );
	ok( false === $result['success'] && ! isset( $result['data']['code'] ), 'past the gate the handler checks the upload: ' . wp_json_encode( $result ) );

	// Other learners and specific dates.
	ok( 'not_enrolled' === Delivery::state( $assignment, $learner2 )['reason'], 'a learner who is not enrolled cannot submit' );
	ok( true === Delivery::state( $assignment, $author )['can_submit'] && true === Delivery::state( $assignment, $author )['preview'], 'the author previews without the gates' );
	$future = wp_date( 'Y-m-d', time() + 10 * DAY_IN_SECONDS );
	update_post_meta(
		$assignment,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'specific-date',
			'days'   => 0,
			'date'   => $future,
			'time'   => '09:30:00',
		)
	);
	$state = Delivery::state( $assignment, $learner );
	ok( 'not_open' === $state['reason'] && wp_date( 'Y-m-d H:i', strtotime( $state['opens_at'] ) ) === "$future 09:30", 'specific date and time, site time: ' . $state['opens_at'] );
	update_post_meta(
		$assignment,
		'_drip_settings',
		array(
			'enable' => true,
			'type'   => 'specific-date',
			'days'   => 0,
			'date'   => '2020-01-01T00:00:00.000',
			'time'   => '2020-01-01T08:15:00.000',
		)
	);
	ok( true === Delivery::state( $assignment, $learner )['can_submit'], 'a past date in the legacy editor format is open' );
	$legacy = DripSchedule::opens_at(
		array(
			'enable' => true,
			'type'   => 'specific-date',
			'date'   => '2020-01-01T00:00:00.000',
			'time'   => '2020-01-01T08:15:00.000',
		),
		0,
		0
	);
	ok( $legacy && '2020-01-01 08:15:00' === $legacy->format( 'Y-m-d H:i:s' ), 'legacy date and time fields are read together' );
	$midnight = DripSchedule::opens_at(
		array(
			'enable' => true,
			'type'   => 'specific-date',
			'date'   => '2031-05-06',
			'time'   => '',
		),
		0,
		0
	);
	ok( $midnight && '2031-05-06 00:00:00' === $midnight->format( 'Y-m-d H:i:s' ), 'a date without a time opens at midnight' );
	ok(
		null === DripSchedule::opens_at(
			array(
				'enable' => true,
				'type'   => 'cohort-start',
			),
			$course,
			$learner
		),
		'a cohort schedule without cohorts stays open'
	);
	delete_post_meta( $assignment, '_drip_settings' );

	// A learner submission for the comment thread.
	$path = trailingslashit( $uploads['path'] ) . "$tag-essay.txt";
	file_put_contents( $path, 'Essay' );
	$files[] = $path;
	( new Assignment( $assignment ) )->submit_file_submission(
		$learner,
		$course,
		array(
			'content' => 'Essay text',
			'files'   => array(
				'file' => $path,
				'url'  => trailingslashit( $uploads['url'] ) . "$tag-essay.txt",
				'type' => 'text/plain',
			),
		)
	);
	$submission = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i WHERE assignment_id = %d AND user_id = %d', $wpdb->prefix . 'ohmylms_assignment_attempts', $assignment, $learner ) );
	ok( $submission > 0, 'learner submission exists' );
	$thread = "/assignments/$assignment/submissions/$submission/comments";

	// Comments: learner, instructor, permissions, pace, deletion, switch.
	$mail = array();
	wp_set_current_user( $learner );
	$posted = rest( 'POST', $thread, array( 'body' => "Could you look at my intro?\n<script>x</script>" ) );
	ok( 201 === $posted->get_status() && 'learner' === $posted->get_data()['role'] && false === strpos( $posted->get_data()['body'], '<script>' ), 'learner comment posted as plain text' );
	$learner_comment = $posted->get_data()['id'];
	ok( 1 === count( $mail ) && "{$tag}_author@example.invalid" === $mail[0]['to'] && false !== strpos( $mail[0]['subject'], "$tag Learner" ) && false !== strpos( $mail[0]['message'], 'Could you look at my intro?' ), 'the instructor is emailed: ' . wp_json_encode( array_column( $mail, 'to' ) ) );
	ok( false !== strpos( $mail[0]['message'], '/assignments/' . $assignment . '/submissions/' . $submission ) || false !== strpos( $mail[0]['message'], 'id=' . $assignment ), 'the instructor email links the grading screen' );
	ok_error( rest( 'POST', $thread, array( 'body' => 'Again' ) ), 429, 'ohmylms_assignment_comment_rate_limited', 'a second comment at once is refused' );
	ok_error( rest( 'POST', $thread, array( 'body' => '   ' ) ), 422, 'ohmylms_assignment_invalid_comment', 'blank comment' );
	ok_error( rest( 'POST', $thread, array( 'body' => str_repeat( 'x', 5001 ) ) ), 400, 'rest_invalid_param', 'comment too long' );
	$mail = array();
	wp_set_current_user( $author );
	$reply = rest( 'POST', $thread, array( 'body' => 'Yes, see question 3.' ) );
	ok( 201 === $reply->get_status() && 'instructor' === $reply->get_data()['role'], 'instructor comment posted' );
	$instructor_comment = $reply->get_data()['id'];
	ok( 1 === count( $mail ) && "{$tag}_learner@example.invalid" === $mail[0]['to'] && false !== strpos( $mail[0]['message'], '#ohmylms-submission-' . $submission ), 'the learner is emailed with a link to the submission' );
	wp_set_current_user( $learner );
	$list = rest( 'GET', $thread )->get_data();
	ok( 2 === $list['total'] && array( $learner_comment, $instructor_comment ) === array_column( $list['items'], 'id' ) && 'learner' === $list['role'] && true === $list['comments_enabled'], 'the learner reads the thread oldest first' );
	ok( true === $list['items'][0]['can_delete'] && false === $list['items'][1]['can_delete'], 'the learner may delete only their own comment' );
	ok( ! isset( $list['items'][0]['author']['email'] ) && array( 'id', 'display_name' ) === array_keys( $list['items'][0]['author'] ), 'authors are an ID and a display name' );
	ok_error( rest( 'DELETE', "$thread/$instructor_comment" ), 403, 'ohmylms_assignment_cannot_delete_comment', 'the learner cannot delete the instructor comment' );
	wp_set_current_user( $learner2 );
	ok_error( rest( 'GET', $thread ), 403, 'ohmylms_assignment_cannot_comment', 'another learner cannot read the thread' );
	ok_error( rest( 'POST', $thread, array( 'body' => 'Hi' ) ), 403, 'ohmylms_assignment_cannot_comment', 'another learner cannot post' );
	wp_set_current_user( $other );
	ok_error( rest( 'GET', $thread ), 403, 'ohmylms_assignment_cannot_comment', 'another author cannot read the thread' );
	wp_set_current_user( 0 );
	ok_error( rest( 'GET', $thread ), 401, 'ohmylms_assignment_cannot_comment', 'guests cannot read the thread' );
	wp_set_current_user( $author );
	ok_error( rest( 'GET', "/assignments/$assignment/submissions/999999999/comments" ), 404, 'ohmylms_assignment_submission_not_found', 'unknown submission' );
	ok_error( rest( 'GET', "/assignments/$lesson_one/submissions/$submission/comments" ), 404, 'ohmylms_assignment_not_found', 'a lesson is not an assignment' );
	$deleted = rest( 'DELETE', "$thread/$learner_comment" );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'] && $learner_comment === $deleted->get_data()['previous']['id'], 'the instructor deletes a learner comment' );
	ok_error( rest( 'DELETE', "$thread/$learner_comment" ), 404, 'ohmylms_assignment_comment_not_found', 'a deleted comment is gone' );
	ok( 200 === rest( 'PATCH', "/assignments/$assignment", array( 'comments_enabled' => false ) )->get_status(), 'comments switched off' );
	$mail = array();
	wp_set_current_user( $learner );
	ok_error( rest( 'POST', $thread, array( 'body' => 'Late' ) ), 409, 'ohmylms_assignment_comments_disabled', 'no new comments when switched off' );
	ok( 1 === rest( 'GET', $thread )->get_data()['total'] && false === rest( 'GET', $thread )->get_data()['comments_enabled'], 'the thread stays readable when switched off' );
	ok( array() === $mail, 'nothing was emailed for refused comments' );
	ok( array( $submission => 1 ) === Comments::counts( array( $submission ) ), 'comment counts per submission' );

	// The comment emails are editable like the others.
	wp_set_current_user( $admin );
	foreach ( array(
		'student_assignment_comment'    => 'student',
		'instructor_assignment_comment' => 'creator',
	) as $email_id => $recipient ) {
		$email = EmailManager::get( $email_id );
		ok( $email && $recipient === $email['recipient_type'] && in_array( 'comment', array_column( $email['tags'], 'tag' ), true ) && in_array( 'commenter_name', array_column( $email['tags'], 'tag' ), true ), "$email_id is listed with its tags" );
		$preview = EmailManager::preview( $email_id, array() );
		ok( ! is_wp_error( $preview ) && array() === $preview['unknown_tags'] && false !== strpos( $preview['html'], 'question 3' ), "$email_id preview renders the sample comment" );
	}

	fwrite( STDOUT, "OK assignment-delivery-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	$post_ids = array_unique( array_map( 'intval', $post_ids ) );
	foreach ( $post_ids as $post_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_assignment_attempts', array( 'assignment_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'chapter_id' => $post_id ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post_id ) );
		Comments::delete_for_assignment( $post_id );
		if ( 'attachment' === get_post_type( $post_id ) ) {
			$wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $post_id ) );
		} else {
			wp_delete_post( $post_id, true );
		}
	}
	if ( $user_ids ) {
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
