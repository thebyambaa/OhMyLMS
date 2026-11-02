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

// Register before the REST server builds the lesson settings schema, whose type enum lists registered types.
$lesson_custom_type = 'example-reading';
if ( ! \OhMyLMS\Extensions\Registry::get( 'lesson', $lesson_custom_type ) ) {
	$lesson_custom_type = 'fixture-reading';
	ohmylms_register_lesson_type(
		$lesson_custom_type,
		array(
			'label'  => 'Fixture reading',
			'render' => '__return_empty_string',
		)
	);
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
	$lesson_other   = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_title'  => 'Prerequisite fixture',
			'post_author' => get_current_user_id(),
		),
		true
	);
	$lesson_posts[] = $lesson_other;
	$lesson_image   = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'Lesson cover fixture',
			'post_status'    => 'inherit',
		)
	);
	$lesson_posts[] = $lesson_image;
	update_post_meta( $lesson_fixture, '_drip_settings', array_merge( get_post_meta( $lesson_fixture, '_drip_settings', true ), array( 'legacy' => 'kept' ) ) );
	$lesson_advanced = lesson_request( 'GET', $lesson_path )->get_data();
	lesson_check( true === $lesson_advanced['drip']['enable'] && 5 === $lesson_advanced['drip']['days'] && 'enrollment-from-x-days' === $lesson_advanced['drip']['type'], 'Stored drip settings are readable.' );
	lesson_check(
		array(
			'enable' => false,
			'items'  => array(),
		) === $lesson_advanced['prerequisites'] && 0 === $lesson_advanced['cover_image_id'] && false === $lesson_advanced['custom_type'],
		'Prerequisites, media and type flags are readable.'
	);
	$lesson_advanced_data = lesson_payload( $lesson_advanced ) + array(
		'drip'           => array(
			'enable' => true,
			'type'   => 'specific-date',
			'days'   => 0,
			'date'   => '2026-12-01',
			'time'   => '08:30',
		),
		'prerequisites'  => array(
			'enable' => true,
			'ids'    => array( $lesson_other ),
		),
		'cover_image_id' => $lesson_image,
		'video_id'       => 0,
		'audio_id'       => 0,
	);
	foreach ( array(
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
				'type'   => 'enrollment-from-x-days',
				'days'   => -1,
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
			'prerequisites' => array(
				'enable' => true,
				'ids'    => array( $lesson_fixture ),
			),
		),
		array(
			'prerequisites' => array(
				'enable' => true,
				'ids'    => array( $lesson_course ),
			),
		),
		array( 'cover_image_id' => $lesson_other ),
		array( 'video_id' => $lesson_image ),
	) as $lesson_invalid ) {
		lesson_check( 400 === lesson_request( 'PUT', $lesson_path, array_merge( $lesson_advanced_data, $lesson_invalid ) )->get_status(), 'Invalid drip, prerequisite or media input rejected.' );
		lesson_check( lesson_request( 'GET', $lesson_path )->get_data() === $lesson_advanced, 'Rejected advanced input changes nothing.' );
	}
	$lesson_advanced_saved = lesson_request( 'PUT', $lesson_path, $lesson_advanced_data );
	lesson_check( 200 === $lesson_advanced_saved->get_status(), 'Drip, prerequisites and media save: ' . wp_json_encode( $lesson_advanced_saved->get_data() ) );
	$lesson_advanced_reopen = lesson_request( 'GET', $lesson_path )->get_data();
	lesson_check( $lesson_advanced_data['drip'] === $lesson_advanced_reopen['drip'], 'Drip settings survive reopen.' );
	lesson_check(
		array(
			'enable' => true,
			'items'  => array(
				array(
					'id'    => $lesson_other,
					'title' => 'Prerequisite fixture',
				),
			),
		) === $lesson_advanced_reopen['prerequisites'],
		'Prerequisites survive reopen.'
	);
	lesson_check( $lesson_image === $lesson_advanced_reopen['cover_image_id'], 'Cover image survives reopen.' );
	$lesson_drip_meta = get_post_meta( $lesson_fixture, '_drip_settings', true );
	$lesson_prereq    = get_post_meta( $lesson_fixture, '_prerequisites', true );
	lesson_check( '08:30:00' === $lesson_drip_meta['time'] && '2026-12-01' === $lesson_drip_meta['date'] && 'kept' === $lesson_drip_meta['legacy'], 'Drip storage keeps the learner gate format and unrelated keys.' );
	lesson_check( $lesson_other === $lesson_prereq['data'][0]['value'] && 'Prerequisite fixture' === $lesson_prereq['data'][0]['label'], 'Prerequisites keep the learner gate value/label format.' );
	lesson_check( 409 === lesson_request( 'PUT', $lesson_path, $lesson_advanced_data )->get_status(), 'Stale drip save rejected.' );
	$lesson_rollback_data        = lesson_payload( $lesson_advanced_reopen ) + array(
		'drip' => array(
			'enable' => false,
			'type'   => 'enrollment-from-x-days',
			'days'   => 9,
			'date'   => '',
			'time'   => '',
		),
	);
	$lesson_after_insert_failure = static function () {
		throw new RuntimeException( 'Fixture failure after the settings write.' );
	};
	add_action( 'rest_after_insert_' . OHMYLMS_LESSON_CPT, $lesson_after_insert_failure, 1 );
	try {
		lesson_check( 500 === lesson_request( 'PUT', $lesson_path, $lesson_rollback_data )->get_status(), 'Failure after the drip write is surfaced.' );
	} finally {
		remove_action( 'rest_after_insert_' . OHMYLMS_LESSON_CPT, $lesson_after_insert_failure, 1 );
	}
	wp_cache_delete( $lesson_fixture, 'post_meta' );
	lesson_check( get_post_meta( $lesson_fixture, '_drip_settings', true ) === $lesson_drip_meta, 'Failed save rolls back drip settings.' );
	$lesson_custom       = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_title'  => 'Custom lesson fixture',
			'post_author' => get_current_user_id(),
			'meta_input'  => array( '_type' => $lesson_custom_type ),
		),
		true
	);
	$lesson_posts[]      = $lesson_custom;
	$lesson_custom_read  = lesson_request( 'GET', $lesson_base . '/' . $lesson_custom )->get_data();
	$lesson_custom_data  = lesson_payload( $lesson_custom_read );
	$lesson_custom_wrong = array_merge( $lesson_custom_data, array( 'type' => 'text' ) );
	lesson_check( true === $lesson_custom_read['custom_type'] && $lesson_custom_read['type'] === $lesson_custom_type, 'Registered extension lessons are flagged for their lesson editor.' );
	lesson_check( 400 === lesson_request( 'PUT', $lesson_base . '/' . $lesson_custom, $lesson_custom_wrong )->get_status(), 'Extension lessons keep their type.' );
	lesson_check( 400 === lesson_request( 'PUT', $lesson_path, array_merge( lesson_payload( $lesson_advanced_reopen ), array( 'type' => $lesson_custom_type ) ) )->get_status(), 'Built-in lessons cannot switch to an extension type.' );
	$lesson_custom_data['title']   = 'Custom lesson renamed';
	$lesson_custom_data['content'] = '<p>Extension description</p>';
	lesson_check( 200 === lesson_request( 'PUT', $lesson_base . '/' . $lesson_custom, $lesson_custom_data )->get_status(), 'Extension lesson name and description save.' );
	lesson_check( 'Custom lesson renamed' === get_the_title( $lesson_custom ) && get_post_meta( $lesson_custom, '_type', true ) === $lesson_custom_type, 'Extension lesson keeps its type after saving.' );
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
