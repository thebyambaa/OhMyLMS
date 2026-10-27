<?php
/**
 * Isolated database acceptance and browser fixtures for course objective mapping.
 *
 * @package OhMyLMS
 */

if ( PHP_SAPI !== 'cli' ) {
	exit; }
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$config               = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
$_SERVER['HTTP_HOST'] = '127.0.0.1:8099';
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || DB_NAME !== 'ohmylms_source_test' ) {
	throw new RuntimeException( 'Requires isolated test site' ); }
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;

$admin = get_user_by( 'login', $config['username'] )->ID;
wp_set_current_user( $admin );
$fixture_mode          = $argv[1] ?? 'test';
$state                 = array(
	'posts' => array(),
	'items' => array(),
	'terms' => array(),
	'users' => array(),
);
$checks                = 0;
$previous_integrations = null;
$previous_tokens       = null;
$previous_https        = isset( $_SERVER['HTTPS'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTPS'] ) ) : null;
$mcp_session           = '';
/**
 * Count an acceptance assertion.
 *
 * @param bool   $condition Condition.
 * @param string $message Failure explanation.
 * @throws RuntimeException When an assertion fails.
 */
function objectives_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) ); }
	++$checks;
}
/**
 * Dispatch an authenticated REST request in the isolated installation.
 *
 * @param string $method HTTP method.
 * @param int    $fixture_course Course ID.
 * @param array  $data JSON body.
 * @param array  $query Query arguments.
 * @return WP_REST_Response
 */
function objectives_api( $method, $fixture_course, $data = null, $query = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/courses/' . $fixture_course . '/syllabus-objectives' );
	$request->set_query_params( $query );
	if ( null !== $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) ); }
	return rest_do_request( $request );
}
/**
 * Remove only records made by this fixture.
 *
 * @param array $state Fixture IDs.
 */
function objectives_cleanup( $state ) {
	foreach ( $state['posts'] as $id ) {
		wp_delete_post( $id, true ); }
	foreach ( array_reverse( $state['items'] ) as $id ) {
		if ( Items::get( $id ) ) {
			Items::delete( $id, 'delete', true ); }
	}
	foreach ( array_reverse( $state['terms'] ) as $id ) {
		wp_delete_term( $id, Taxonomy::NAME ); }
	foreach ( $state['users'] as $id ) {
		wp_delete_user( $id ); }
}
if ( 'cleanup' === $fixture_mode ) {
	objectives_cleanup( json_decode( $argv[2], true ) );
	exit; }

try {
	$fixture_tag      = wp_generate_password( 6, false, false );
	$state['course']  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Objective mapping ' . $fixture_tag,
			'post_author' => $admin,
			'post_status' => 'draft',
		)
	);
	$state['posts'][] = $state['course'];
	$syllabus         = Items::create(
		array(
			'name'      => 'Objective test syllabus ' . $fixture_tag,
			'item_type' => 'syllabus',
			'version'   => '2026',
		)
	);
	objectives_check( ! is_wp_error( $syllabus ), 'Syllabus fixture created' );
	$state['syllabus'] = (int) $syllabus['id'];
	$state['items'][]  = $state['syllabus'];
	$group             = Syllabus::add_group(
		$state['syllabus'],
		array(
			'name' => 'Algebra',
			'code' => 'ALG',
		)
	);
	objectives_check( ! is_wp_error( $group ), 'Group fixture created' );
	foreach ( array( 'Solve linear equations', 'Simplify expressions' ) as $index => $name ) {
		$skill = Syllabus::create_skill(
			$state['syllabus'],
			$group,
			array(
				'name'      => $name,
				'code'      => 'ALG-0' . ( $index + 1 ),
				'objective' => $name . ' with one variable.',
			)
		);
		objectives_check( ! is_wp_error( $skill ), 'Skill fixture created' );
		$state['skills'][] = (int) $skill['term_id'];
	}
	$root             = (int) Items::get( $state['syllabus'] )['skill_root_id'];
	$state['terms']   = array_merge( array( $root ), $state['skills'] );
	$lesson           = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_title'  => 'Equation examples',
			'post_status' => 'publish',
		)
	);
	$state['posts'][] = $lesson;
	wp_set_object_terms( $lesson, array( $state['skills'][0] ), Taxonomy::NAME );
	$quiz             = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_title'  => 'Equation assessment',
			'post_status' => 'draft',
		)
	);
	$state['posts'][] = $quiz;
	$draft            = CourseProgram::defaults( $state['course'] );
	$draft['items']   = array(
		array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'lesson',
			'content_id' => $lesson,
			'skill_ids'  => array(),
			'chapter_id' => 0,
		),
		array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'quiz',
			'content_id' => $quiz,
			'skill_ids'  => array( $state['skills'][0] ),
			'chapter_id' => 0,
		),
	);
	update_post_meta( $state['course'], CourseProgram::DRAFT, $draft );
	if ( 'setup' === $fixture_mode ) {
		echo wp_json_encode( $state );
		exit; }

	if ( 'mcp' === $fixture_mode ) {
		$previous_integrations = get_option( 'ohmylms_integrations', array() );
		$previous_tokens       = get_option( \OhMyLMS\MCP\Server::OPTION, array() );
		$enabled               = $previous_integrations;
		$enabled['mcp']        = array( 'is_enable' => 1 );
		$was_enabled           = \OhMyLMS\MCP\Server::enabled();
		update_option( 'ohmylms_integrations', $enabled );
		if ( ! $was_enabled ) {
			\OhMyLMS\MCP\Abilities::init();
			\OhMyLMS\MCP\Server::boot_adapter();
		}
	}
	$fixture_course = $state['course'];
	$initial        = objectives_api( 'GET', $fixture_course );
	objectives_check( 200 === $initial->get_status() && array() === $initial->get_data()['mappings'], 'Initially empty' );
	$before = get_post_meta( $fixture_course, CourseProgram::DRAFT, true );
	$browse = objectives_api( 'GET', $fixture_course, null, array( 'syllabus_id' => $state['syllabus'] ) );
	objectives_check( 200 === $browse->get_status() && 2 === count( $browse->get_data()['objectives'] ), 'Browse official objectives' );
	objectives_check( ! Items::get( $state['syllabus'] )['course_id'], 'Browsing does not create a syllabus course' );
	$mapping = array(
		'syllabus_id' => $state['syllabus'],
		'term_id'     => $state['skills'][0],
	);
	$body    = array(
		'mappings' => array( $mapping, $mapping ),
		'revision' => $initial->get_data()['revision'],
	);
	$saved   = objectives_api( 'PUT', $fixture_course, $body );
	objectives_check( 200 === $saved->get_status(), 'Mapping saved: ' . wp_json_encode( $saved->get_data() ) );
	$payload = $saved->get_data();
	if ( 'mcp' === $fixture_mode ) {
		wp_set_current_user( $admin );
		$read    = wp_get_ability( 'ohmylms/get-course-objectives' );
		$options = wp_get_ability( 'ohmylms/list-course-objective-options' );
		$write   = wp_get_ability( 'ohmylms/set-course-objectives' );
		objectives_check( $read instanceof WP_Ability && $options instanceof WP_Ability && $write instanceof WP_Ability, 'Native MCP abilities registered' );
		$mcp_targets = $read->execute(
			array(
				'id'       => $fixture_course,
				'per_page' => 1,
			)
		);
		objectives_check( ! is_wp_error( $mcp_targets ) && 1 === $mcp_targets['total'] && $payload['revision'] === $mcp_targets['data']['revision'], 'MCP reads mappings and revision: ' . wp_json_encode( $mcp_targets ) );
		$choices = $options->execute(
			array(
				'id'          => $fixture_course,
				'syllabus_id' => $state['syllabus'],
				'per_page'    => 1,
				'page'        => 2,
			)
		);
		objectives_check( ! is_wp_error( $choices ) && 2 === $choices['total'] && $state['skills'][1] === $choices['data']['objectives'][0]['term_id'], 'MCP options forward syllabus and pagination' );
		$choices = $options->execute(
			array(
				'id'          => $fixture_course,
				'syllabus_id' => $state['syllabus'],
				'search'      => 'one variable expressions',
			)
		);
		objectives_check( ! is_wp_error( $choices ) && 1 === $choices['total'], 'MCP searches full objective wording' );
		objectives_check(
			is_wp_error(
				$write->execute(
					array(
						'id'       => $fixture_course,
						'revision' => $payload['revision'],
						'mappings' => array(
							array(
								'syllabus_id' => $state['syllabus'],
								'term_id'     => 'bad',
							),
						),
					)
				)
			),
			'Nested MCP input schema rejects malformed IDs'
		);
		$both    = array(
			$mapping,
			array(
				'syllabus_id' => $state['syllabus'],
				'term_id'     => $state['skills'][1],
			),
		);
		$changed = $write->execute(
			array(
				'id'       => $fixture_course,
				'revision' => $payload['revision'],
				'mappings' => $both,
			)
		);
		objectives_check( ! is_wp_error( $changed ) && 2 === $changed['total'], 'MCP writes mappings through course REST API' );
		$stale = $write->execute(
			array(
				'id'       => $fixture_course,
				'revision' => $payload['revision'],
				'mappings' => array(),
			)
		);
		objectives_check( is_wp_error( $stale ) && 409 === $stale->get_error_data()['status'], 'MCP rejects stale revision' );
		$mcp_page = $read->execute(
			array(
				'id'       => $fixture_course,
				'page'     => 2,
				'per_page' => 1,
			)
		);
		objectives_check( 2 === $mcp_page['total'] && 1 === count( $mcp_page['data']['mappings'] ) && $changed['data']['revision'] === $mcp_page['data']['revision'], 'Mapping pages share full-set revision' );
		$removed = $write->execute(
			array(
				'id'       => $fixture_course,
				'revision' => $changed['data']['revision'],
				'mappings' => array( $mapping ),
			)
		);
		objectives_check( ! is_wp_error( $removed ) && 1 === $removed['total'], 'MCP removes omitted references' );
		$cleared = $write->execute(
			array(
				'id'       => $fixture_course,
				'revision' => $removed['data']['revision'],
				'mappings' => array(),
			)
		);
		objectives_check( ! is_wp_error( $cleared ) && 0 === $cleared['total'], 'MCP can clear targets explicitly' );
		$restored = $write->execute(
			array(
				'id'       => $fixture_course,
				'revision' => $cleared['data']['revision'],
				'mappings' => array( $mapping ),
			)
		);
		objectives_check( ! is_wp_error( $restored ) && get_post_meta( $fixture_course, CourseProgram::DRAFT, true ) === $before, 'MCP leaves completion draft untouched' );
		wp_set_current_user( 0 );
		objectives_check(
			is_wp_error( $read->execute( array( 'id' => $fixture_course ) ) ) && is_wp_error(
				$write->execute(
					array(
						'id'       => $fixture_course,
						'revision' => $restored['data']['revision'],
						'mappings' => array(),
					)
				)
			),
			'Anonymous native MCP reads and writes denied'
		);
		wp_set_current_user( $admin );
		// Exercise the actual adapter transport without issuing a network request.
		$token = 'oml_mcp_' . bin2hex( random_bytes( 32 ) );
		update_option(
			\OhMyLMS\MCP\Server::OPTION,
			array(
				'openai' => array(
					'hash'    => hash( 'sha256', $token ),
					'user_id' => $admin,
				),
			)
		);
		$_SERVER['HTTPS'] = 'on';
		$transport        = static function ( $method, array $params ) use ( $token, &$mcp_session ) {
			wp_set_current_user( 0 );
			$request = new WP_REST_Request( 'POST', \OhMyLMS\MCP\Server::ROUTE );
			$request->set_header( 'authorization', 'Bearer ' . $token );
			$request->set_header( 'content-type', 'application/json' );
			$request->set_header( 'accept', 'application/json, text/event-stream' );
			if ( $mcp_session ) {
				$request->set_header( 'mcp-session-id', $mcp_session );
				$request->set_header( 'mcp-protocol-version', '2025-11-25' );
			}
			$request->set_body(
				wp_json_encode(
					array(
						'jsonrpc' => '2.0',
						'id'      => 1,
						'method'  => $method,
						'params'  => $params,
					)
				)
			);
			$reply       = rest_do_request( $request );
			$reply       = apply_filters( 'rest_post_dispatch', $reply, rest_get_server(), $request );
			$headers     = array_change_key_case( $reply->get_headers(), CASE_LOWER );
			$mcp_session = $headers['mcp-session-id'] ?? $mcp_session;
			return json_decode( wp_json_encode( $reply->get_data() ), true );
		};
		$hello            = $transport(
			'initialize',
			array(
				'protocolVersion' => '2025-11-25',
				'capabilities'    => (object) array(),
				'clientInfo'      => array(
					'name'    => 'course-objectives-test',
					'version' => '1',
				),
			)
		);
		objectives_check( isset( $hello['result'] ), 'MCP transport initializes: ' . wp_json_encode( $hello ) );
		$discovery        = $transport( 'tools/list', array() );
		$discovered_names = array_column( $discovery['result']['tools'] ?? array(), 'name' );
		objectives_check( 3 === count( array_intersect( array( 'get_course_objectives', 'list_course_objective_options', 'set_course_objectives' ), $discovered_names ) ), 'MCP discovery exposes all objective tools: ' . wp_json_encode( $discovery ) );
		$called = $transport(
			'tools/call',
			array(
				'name'      => 'get_course_objectives',
				'arguments' => array( 'id' => $fixture_course ),
			)
		);
		objectives_check( isset( $called['result'] ) && empty( $called['result']['isError'] ), 'MCP transport invokes course objective read' );
		$called = $transport(
			'tools/call',
			array(
				'name'      => 'set_course_objectives',
				'arguments' => array(
					'id'       => $fixture_course,
					'revision' => $restored['data']['revision'],
					'mappings' => $both,
				),
			)
		);
		objectives_check( isset( $called['result'] ) && empty( $called['result']['isError'] ) && 2 === count( CourseObjectives::references( $fixture_course ) ), 'MCP transport saves course targets' );
		require __DIR__ . '/fixtures/authoring-checks.php';
		authoring_checks( $transport, $state, (int) $admin );
		wp_set_current_user( $admin );
		// Restore the one-target state expected by the REST acceptance checks below.
		$write->execute(
			array(
				'id'       => $fixture_course,
				'revision' => CourseObjectives::payload( $fixture_course )['revision'],
				'mappings' => array( $mapping ),
			)
		);
	}
	objectives_check( 1 === count( $payload['mappings'] ), 'Duplicates collapsed' );
	objectives_check( 2 === count( $payload['mappings'][0]['content'] ), 'Course scoped lesson and assessment connections shown' );
	objectives_check( get_post_meta( $fixture_course, CourseProgram::DRAFT, true ) === $before, 'Learning draft untouched' );
	objectives_check( 409 === objectives_api( 'PUT', $fixture_course, $body )->get_status(), 'Concurrent stale save rejected' );
	$body['revision'] = $payload['revision'];
	$body['mappings'] = array(
		array(
			'syllabus_id' => $state['syllabus'],
			'term_id'     => 999999999,
		),
	);
	objectives_check( 409 === objectives_api( 'PUT', $fixture_course, $body )->get_status(), 'Foreign objective rejected' );
	$body['mappings'] = array(
		array(
			'syllabus_id' => -1,
			'term_id'     => $state['skills'][0],
		),
	);
	objectives_check( 400 === objectives_api( 'PUT', $fixture_course, $body )->get_status(), 'Invalid IDs rejected' );
	$body['mappings'] = array_fill( 0, 201, $mapping );
	objectives_check( 400 === objectives_api( 'PUT', $fixture_course, $body )->get_status(), 'Oversized list rejected' );
	wp_delete_term( $state['skills'][0], Taxonomy::NAME );
	$missing = objectives_api( 'GET', $fixture_course )->get_data();
	objectives_check( $missing['mappings'][0]['missing'], 'Deleted objective remains visible as unavailable' );
	objectives_check(
		200 === objectives_api(
			'PUT',
			$fixture_course,
			array(
				'revision' => $missing['revision'],
				'mappings' => array(),
			)
		)->get_status(),
		'Unavailable mapping removable'
	);
	wp_set_current_user( 0 );
	objectives_check( 401 === objectives_api( 'GET', $fixture_course )->get_status(), 'Guest read rejected' );
	objectives_check( 401 === objectives_api( 'PUT', $fixture_course, $body )->get_status(), 'Guest write rejected' );
	$author           = wp_create_user( 'obj-' . $fixture_tag, wp_generate_password( 24 ), 'obj-' . $fixture_tag . '@example.invalid' );
	$state['users'][] = $author;
	( new WP_User( $author ) )->set_role( 'author' );
	wp_set_current_user( $author );
	objectives_check( 403 === objectives_api( 'GET', $fixture_course )->get_status(), 'Other author cannot read mappings' );
	wp_set_current_user( $admin );
	wp_update_post(
		array(
			'ID'          => $fixture_course,
			'post_author' => $author,
		)
	);
	wp_set_current_user( $author );
	objectives_check( 200 === objectives_api( 'GET', $fixture_course )->get_status(), 'Course author can read mappings' );
	$own = objectives_api( 'GET', $fixture_course )->get_data();
	objectives_check(
		200 === objectives_api(
			'PUT',
			$fixture_course,
			array(
				'revision' => $own['revision'],
				'mappings' => array(
					array(
						'syllabus_id' => $state['syllabus'],
						'term_id'     => $state['skills'][1],
					),
				),
			)
		)->get_status(),
		'Course author can reference existing curriculum'
	);
} finally {
	wp_set_current_user( $admin );
	if ( $mcp_session ) {
		\WP\MCP\Transport\Infrastructure\SessionManager::delete_session( (int) $admin, $mcp_session );
	}
	if ( null !== $previous_integrations ) {
		update_option( 'ohmylms_integrations', $previous_integrations );
		update_option( \OhMyLMS\MCP\Server::OPTION, $previous_tokens );
	}
	if ( null === $previous_https ) {
		unset( $_SERVER['HTTPS'] );
	} else {
		$_SERVER['HTTPS'] = $previous_https;
	}
	objectives_cleanup( $state );
}
fwrite( STDOUT, $checks . " course objective integration checks passed.\n" );
