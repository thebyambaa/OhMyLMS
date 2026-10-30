<?php
/**
 * Lesson authoring against the named Local site using disposable fixtures only.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_LESSON_TEST' ) ) {
	exit( "Set OHMYLMS_LESSON_TEST=1 to run lesson authoring tests.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Learning\NativeLessonEditor;
use OhMyLMS\Learning\CourseProgram;

$lesson_checks = 0;
$lesson_users  = array();
$lesson_posts  = array();

/**
 * Assert a REST/database invariant.
 *
 * @param bool   $condition Expected truth.
 * @param string $message Failure explanation.
 * @throws RuntimeException When a check fails.
 */
function lesson_check( $condition, $message ) {
	global $lesson_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion.
	}
	++$lesson_checks;
}

/**
 * Dispatch a real registered route.
 *
 * @param string $method Method.
 * @param string $route Route.
 * @param array  $data JSON or query.
 * @return WP_REST_Response
 */
function lesson_request( $method, $route, $data = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Extract only editable fields.
 *
 * @param array $data Lesson.
 * @return array
 */
function lesson_payload( $data ) {
	return array_intersect_key( $data, array_flip( array( 'revision', 'title', 'content', 'type', 'external_url', 'preview_enable', 'enable_comments', 'resources' ) ) );
}

try {
	foreach ( array( 'administrator', 'author', 'subscriber' ) as $lesson_role ) {
		$lesson_user = wp_insert_user(
			array(
				'user_login' => 'lesson' . wp_generate_password( 12, false, false ),
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => $lesson_role,
			)
		);
		lesson_check( ! is_wp_error( $lesson_user ), 'Fixture user created.' );
		$lesson_users[ $lesson_role ] = $lesson_user;
	}
	wp_set_current_user( $lesson_users['administrator'] );
	$lesson_base     = '/ohmylms/v1/authoring/lessons';
	$lesson_creation = array(
		'title'      => 'Lesson fixture ' . wp_generate_uuid4(),
		'request_id' => wp_generate_uuid4(),
	);
	$lesson_created  = lesson_request( 'POST', $lesson_base, $lesson_creation );
	lesson_check( 200 === $lesson_created->get_status(), 'Named lesson created: ' . wp_json_encode( $lesson_created->get_data() ) );
	$lesson_fixture = $lesson_created->get_data()['id'];
	$lesson_posts[] = $lesson_fixture;
	$lesson_path    = $lesson_base . '/' . $lesson_fixture;
	$lesson_listing = lesson_request( 'GET', $lesson_base, array( 'search' => $lesson_creation['title'] ) )->get_data();
	lesson_check( 1 === count( $lesson_listing['items'] ) && $lesson_fixture === $lesson_listing['items'][0]['id'] && ! isset( $lesson_listing['items'][0]['content'] ), 'Library returns bounded summaries without bodies.' );
	lesson_check( 400 === lesson_request( 'GET', $lesson_base, array( 'page' => 0 ) )->get_status(), 'Invalid collection page rejected.' );
	lesson_check( 'draft' === get_post_status( $lesson_fixture ), 'Creation is draft only.' );
	lesson_check( lesson_request( 'POST', $lesson_base, $lesson_creation )->get_data()['id'] === $lesson_fixture, 'Creation retry is idempotent.' );
	lesson_check( 400 === lesson_request( 'POST', $lesson_base, $lesson_creation + array( 'status' => 'publish' ) )->get_status(), 'Creation cannot set status.' );
	update_post_meta(
		$lesson_fixture,
		'_drip_settings',
		array(
			'enable' => true,
			'days'   => 5,
			'type'   => 'enrollment-from-x-days',
		)
	);
	update_post_meta( $lesson_fixture, '_video_settings', array( 'custom' => 'preserve' ) );
	update_post_meta(
		$lesson_fixture,
		'_download_resource',
		array(
			'extension' => 'retained',
			'file'      => array(
				array(
					'name' => 'Old resource',
					'url'  => 'https://example.com/lesson.pdf',
					'size' => '1 MB',
					'id'   => 99,
				),
			),
		)
	);
	$lesson_read              = lesson_request( 'GET', $lesson_path )->get_data();
	$lesson_data              = lesson_payload( $lesson_read );
	$lesson_data['resources'] = array(
		array(
			'name' => 'Updated resource',
			'url'  => 'https://example.com/lesson.pdf',
			'size' => '2 MB',
		),
	);
	$lesson_data['content']   = '<!-- wp:paragraph --><p>Use \\frac{1}{2} and \\alpha.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:ohmylms/shared-multiple-choice {"questionId":123,"uuid":"preserved-reference"} /-->';
	$lesson_data['title']     = 'Lesson \\alpha';
	$lesson_saved             = lesson_request( 'PUT', $lesson_path, $lesson_data );
	lesson_check( 200 === $lesson_saved->get_status(), 'Complete lesson save succeeds: ' . wp_json_encode( $lesson_saved->get_data() ) );
	$lesson_reopen = lesson_request( 'GET', $lesson_path )->get_data();
	lesson_check( $lesson_data['content'] === $lesson_reopen['content'] && $lesson_data['title'] === $lesson_reopen['title'], 'Block markup, custom references and math backslashes survive reopen.' );
	lesson_check( 'draft' === $lesson_reopen['status'], 'Content save never implicitly publishes.' );
	lesson_check( 'preserve' === get_post_meta( $lesson_fixture, '_video_settings', true )['custom'] && 5 === get_post_meta( $lesson_fixture, '_drip_settings', true )['days'], 'Advanced settings survive.' );
	$lesson_resources = get_post_meta( $lesson_fixture, '_download_resource', true );
	lesson_check( 'retained' === $lesson_resources['extension'] && 99 === $lesson_resources['file'][0]['id'] && 'Updated resource' === $lesson_resources['file'][0]['name'], 'Resource extension fields and attachment identity survive.' );
	lesson_check( 409 === lesson_request( 'PUT', $lesson_path, $lesson_data )->get_status(), 'Stale content save rejected.' );
	$lesson_data = lesson_payload( $lesson_reopen );
	foreach ( array(
		array( 'title' => '' ),
		array( 'type' => 'unknown' ),
		array( 'external_url' => 'javascript:alert(1)' ),
		array( 'preview_enable' => 'yes' ),
		array( 'status' => 'publish' ),
		array(
			'resources' => array(
				array(
					'name' => 'Unsafe',
					'url'  => 'data:text/html,test',
					'size' => '',
				),
			),
		),
	) as $lesson_invalid ) {
		lesson_check( 400 === lesson_request( 'PUT', $lesson_path, array_merge( $lesson_data, $lesson_invalid ) )->get_status(), 'Invalid lesson input rejected.' );
		lesson_check( lesson_request( 'GET', $lesson_path )->get_data() === $lesson_reopen, 'Rejected input changes nothing.' );
	}
	$lesson_native = lesson_request( 'POST', '/wp/v2/ohmylms-lessons/' . $lesson_fixture, array( 'title' => 'Native writer change' ) );
	lesson_check( 200 === $lesson_native->get_status(), 'Native REST writer remains compatible.' );
	lesson_check( 409 === lesson_request( 'PUT', $lesson_path, $lesson_data )->get_status(), 'Native changes invalidate typed editor read tokens.' );
	$lesson_data           = lesson_payload( lesson_request( 'GET', $lesson_path )->get_data() );
	$lesson_before_failure = get_post_field( 'post_content', $lesson_fixture );
	register_rest_field(
		OHMYLMS_LESSON_CPT,
		'ohmylms_lesson_settings',
		array(
			'schema'          => NativeLessonEditor::settings_schema(),
			'update_callback' => static function () {
				return new WP_Error( 'fixture_failure', 'Fixture settings failure', array( 'status' => 500 ) );
			},
		)
	);
	try {
		$lesson_failure_data            = $lesson_data;
		$lesson_failure_data['content'] = '<p>Must roll back.</p>';
		$lesson_failed_response         = lesson_request( 'PUT', $lesson_path, $lesson_failure_data );
		lesson_check( 500 === $lesson_failed_response->get_status(), 'Failure after the post write is surfaced: ' . wp_json_encode( $lesson_failed_response->get_data() ) );
	} finally {
		NativeLessonEditor::register_settings();
	}
	lesson_check( get_post_field( 'post_content', $lesson_fixture ) === $lesson_before_failure, 'Failed settings write rolls back content.' );
	lesson_check(
		403 === lesson_request(
			'POST',
			$lesson_path . '/publish',
			array(
				'revision'  => $lesson_data['revision'],
				'confirmed' => false,
			)
		)->get_status(),
		'Publication needs explicit confirmation.'
	);
	$lesson_publisher = wp_get_current_user();
	$lesson_publisher->add_cap( 'publish_posts', false );
	try {
		lesson_check(
			403 === lesson_request(
				'POST',
				$lesson_path . '/publish',
				array(
					'revision'  => $lesson_data['revision'],
					'confirmed' => true,
				)
			)->get_status(),
			'Publication also requires the registered publishing capability.'
		);
	} finally {
		$lesson_publisher->remove_cap( 'publish_posts' );
	}
	$lesson_published = lesson_request(
		'POST',
		$lesson_path . '/publish',
		array(
			'revision'  => $lesson_data['revision'],
			'confirmed' => true,
		)
	);
	lesson_check( 200 === $lesson_published->get_status() && 'publish' === get_post_status( $lesson_fixture ), 'Saved lesson publishes explicitly.' );
	lesson_check(
		409 === lesson_request(
			'POST',
			$lesson_path . '/publish',
			array(
				'revision'  => $lesson_data['revision'],
				'confirmed' => true,
			)
		)->get_status(),
		'Old publication confirmation cannot be replayed.'
	);
	$lesson_course                        = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'draft',
			'post_title'  => 'Lesson attachment fixture',
			'post_author' => get_current_user_id(),
		),
		true
	);
	$lesson_posts[]                       = $lesson_course;
	$lesson_structure_path                = '/ohmylms/v1/courses/' . $lesson_course . '/structure';
	$lesson_outline                       = lesson_request( 'GET', $lesson_structure_path )->get_data();
	$lesson_unit_uuid                     = wp_generate_uuid4();
	$lesson_outline['program']['items'][] = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'lesson',
		'content_id' => $lesson_fixture,
		'chapter_id' => $lesson_unit_uuid,
		'required'   => true,
	);
	$lesson_placement                     = lesson_request(
		'PUT',
		$lesson_structure_path,
		array(
			'revision' => $lesson_outline['structure_revision'],
			'units'    => array(
				array(
					'id'          => $lesson_unit_uuid,
					'title'       => 'Lesson fixture unit',
					'number'      => '',
					'description' => '',
				),
			),
			'program'  => $lesson_outline['program'],
		)
	);
	lesson_check( 200 === $lesson_placement->get_status(), 'Authored lesson attaches through existing outline save.' );
	foreach ( $lesson_placement->get_data()['units'] ?? array() as $lesson_unit ) {
		if ( $lesson_unit['id'] > 0 ) {
			$lesson_posts[] = $lesson_unit['id']; }
	}
	lesson_check( $lesson_fixture === $lesson_placement->get_data()['program']['items'][0]['content_id'] && 'draft' === get_post_status( $lesson_course ) && ! get_post_meta( $lesson_course, CourseProgram::CURRENT, true ), 'Lesson placement persists without publishing the course.' );
	update_post_meta( $lesson_fixture, '_type', 'fixture-extension' );
	$lesson_extension = lesson_request( 'GET', $lesson_path )->get_data();
	lesson_check( 'fixture-extension' === $lesson_extension['type'] && '' === $lesson_extension['native_editor_url'], 'Extension lessons remain discoverable without a forced native editing link.' );
	lesson_check( 400 === lesson_request( 'PUT', $lesson_path, lesson_payload( $lesson_extension ) )->get_status(), 'Typed saves cannot overwrite unsupported extension lessons.' );
	update_post_meta( $lesson_fixture, '_type', 'text' );
	foreach ( array( 'author', 'subscriber' ) as $lesson_role ) {
		wp_set_current_user( $lesson_users[ $lesson_role ] );
		foreach ( array( array( 'GET', $lesson_base ), array( 'GET', $lesson_path ), array( 'PUT', $lesson_path ), array( 'POST', $lesson_path . '/publish' ), array( 'POST', $lesson_base ) ) as $lesson_denied ) {
			lesson_check( 403 === lesson_request( $lesson_denied[0], $lesson_denied[1], $lesson_data )->get_status(), 'Unprivileged authoring denied.' );
		}
	}
	echo "Lesson authoring REST integration: $lesson_checks checks passed.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI count.
} finally {
	NativeLessonEditor::register_settings();
	wp_set_current_user( $lesson_users['administrator'] ?? 0 );
	foreach ( array_reverse( $lesson_posts ) as $lesson_post ) {
		wp_delete_post( $lesson_post, true ); }
	foreach ( $lesson_users as $lesson_user ) {
		wp_delete_user( $lesson_user ); }
}
