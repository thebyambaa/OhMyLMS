<?php
/**
 * REST checks for the typed comparison screen (`#/content-hub/curriculum/compare`): comparison groups,
 * shared skills, roadmaps and the learner audit, including schema rejections, capability checks and
 * stale writes. Run on the Local site with OHMYLMS_CURRICULUM_COMPARE_TEST=1; tagged fixtures are
 * removed and the comparison option restored in `finally`.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CURRICULUM_COMPARE_TEST' ) ) {
	exit( "Set OHMYLMS_CURRICULUM_COMPARE_TEST=1 to run the curriculum compare REST tests.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$compare_checks = 0;

/**
 * Dispatch a REST request below /ohmylms/v1/syllabus-comparison.
 *
 * @param string $method HTTP method.
 * @param string $path   Route below the comparison base.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function compare_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/syllabus-comparison' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert a condition.
 *
 * @param mixed  $value   Truthy when the check passes.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function compare_ok( $value, $message ) {
	global $compare_checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$compare_checks;
}

/**
 * Assert a status and error code.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $label    Description.
 */
function compare_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	compare_ok( $response->get_status() === $status && ( $data['code'] ?? '' ) === $code, "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
}

$tag       = 'cmp' . strtolower( wp_generate_password( 6, false, false ) );
$items     = array();
$terms     = array();
$maps      = array();
$users     = array();
$option    = get_option( Comparison::OPTION, null );
$admins    = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$exit_code = 0;
try {
	compare_ok( ! empty( $admins ), 'an administrator exists' );
	$admin = (int) $admins[0];
	foreach ( array( 'editor', 'subscriber' ) as $role ) {
		$user = wp_insert_user(
			array(
				'user_login' => $tag . $role,
				'user_pass'  => wp_generate_password( 24 ),
				'role'       => $role,
			)
		);
		compare_ok( ! is_wp_error( $user ), "$role fixture" );
		$users[ $role ] = $user;
	}
	wp_set_current_user( $admin );
	OhMyLMS\Curriculum\Schema::install();

	// Routes carry typed arguments and real permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/syllabuses', '/configuration', '/shared-skills', '/shared-skills/(?P<id>\d+)', '/shared-skills/save', '/roadmaps', '/roadmaps/preview', '/roadmaps/(?P<id>\d+)', '/audit/(?P<student_id>\d+)', '/audit' ) as $route ) {
		$key = '/ohmylms/v1/syllabus-comparison' . $route;
		compare_ok( isset( $routes[ $key ] ), "route $route" );
		foreach ( $routes[ $key ] as $endpoint ) {
			compare_ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "permission on $route" );
		}
	}

	// Fixtures: two syllabuses, each with one objective mapped to one shared skill.
	$shared = compare_api(
		'POST',
		'/shared-skills/save',
		array(
			'name'    => $tag . ' Solve linear equations',
			'subject' => $tag . ' Mathematics',
			'topic'   => 'Algebra',
		)
	);
	compare_ok( 200 === $shared->get_status(), 'shared skill created: ' . wp_json_encode( $shared->get_data() ) );
	$shared     = $shared->get_data();
	$terms[]    = (int) $shared['id'];
	$objectives = array();
	foreach ( array( 'Cambridge', 'National' ) as $label ) {
		$item = Items::create(
			array(
				'name'        => $tag . ' ' . $label,
				'is_syllabus' => true,
			)
		);
		compare_ok( ! is_wp_error( $item ), "$label syllabus" );
		$items[] = (int) $item['id'];
		$group   = Syllabus::add_group( (int) $item['id'], array( 'name' => 'Algebra' ) );
		$term    = Syllabus::create_skill( (int) $item['id'], $group, array( 'name' => $tag . ' ' . $label . ' equations' ) );
		compare_ok( ! is_wp_error( $term ), "$label objective" );
		$terms[]      = (int) $term['term_id'];
		$objectives[] = (int) $term['term_id'];
		$mapped       = rest_do_request(
			( static function () use ( $term, $shared ) {
				$request = new WP_REST_Request( 'PUT', '/ohmylms/v1/syllabus-comparison/mapping' );
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_body(
					wp_json_encode(
						array(
							'specific_id'       => (int) $term['term_id'],
							'shared_id'         => (int) $shared['id'],
							'relation'          => 'equivalent',
							'note'              => '',
							'expected_revision' => '',
						)
					)
				);
				return $request;
			} )()
		);
		compare_ok( 200 === $mapped->get_status(), "$label mapping" );
		$maps[] = array( (int) $term['term_id'], (int) $shared['id'] );
	}

	// Capability: editors and subscribers cannot reach administration; anyone signed in reads their own audit.
	foreach ( array( 'editor', 'subscriber' ) as $role ) {
		wp_set_current_user( $users[ $role ] );
		foreach ( array( array( 'GET', '/syllabuses' ), array( 'GET', '/shared-skills' ), array( 'GET', '/roadmaps/' . $items[0] ), array( 'POST', '/roadmaps/preview', array( 'syllabus_ids' => array( $items[0] ) ) ), array( 'GET', '/audit/' . $admin ) ) as $call ) {
			compare_ok( 403 === compare_api( $call[0], $call[1], $call[2] ?? array() )->get_status(), "$role refused on {$call[0]} {$call[1]}" );
		}
		compare_ok( 200 === compare_api( 'GET', '/audit', array( 'student_id' => $admin ) )->get_status(), "$role reads their own audit" );
	}
	wp_set_current_user( 0 );
	compare_ok( 401 === compare_api( 'GET', '/syllabuses' )->get_status(), 'anonymous refused' );
	wp_set_current_user( $admin );

	// Comparison groups.
	$list = compare_api( 'GET', '/syllabuses' )->get_data();
	compare_ok( preg_match( '/^[a-f0-9]{16}$/', $list['revision'] ), 'groups carry a revision' );
	$row = current( array_filter( $list['items'], static fn( $entry ) => $items[0] === (int) $entry['id'] ) );
	compare_ok( $row && 'syllabus' === $row['item_type'], 'selectors carry item_type' );
	$subject = $tag . ' Mathematics';
	compare_error(
		compare_api(
			'PUT',
			'/configuration',
			array(
				'subject'      => $subject,
				'syllabus_ids' => $items,
			)
		),
		400,
		'rest_missing_callback_param',
		'configuration needs expected_revision'
	);
	compare_error(
		compare_api(
			'PUT',
			'/configuration',
			array(
				'subject'           => $subject,
				'syllabus_ids'      => array( 'x' ),
				'expected_revision' => $list['revision'],
			)
		),
		400,
		'rest_invalid_param',
		'syllabus ids must be integers'
	);
	compare_error(
		compare_api(
			'PUT',
			'/configuration',
			array(
				'subject'           => $subject,
				'syllabus_ids'      => range( 1, 11 ),
				'expected_revision' => $list['revision'],
			)
		),
		400,
		'rest_invalid_param',
		'at most ten sources'
	);
	compare_error(
		compare_api(
			'PUT',
			'/configuration',
			array(
				'subject'           => str_repeat( 'x', 101 ),
				'syllabus_ids'      => $items,
				'expected_revision' => $list['revision'],
			)
		),
		400,
		'rest_invalid_param',
		'subject length'
	);
	$saved = compare_api(
		'PUT',
		'/configuration',
		array(
			'subject'           => $subject,
			'syllabus_ids'      => $items,
			'expected_revision' => $list['revision'],
		)
	);
	compare_ok( 200 === $saved->get_status() && $items === $saved->get_data()['selected'][ $subject ] && $saved->get_data()['revision'] !== $list['revision'], 'group saved with a new revision' );
	$stale = compare_api(
		'PUT',
		'/configuration',
		array(
			'subject'           => $subject,
			'syllabus_ids'      => array( $items[0] ),
			'expected_revision' => $list['revision'],
		)
	);
	compare_error( $stale, 409, 'ohmylms_comparison_stale', 'stale group save' );
	compare_ok( get_option( Comparison::OPTION )[ $subject ] === $items, 'stale save wrote nothing' );

	// Shared skills.
	compare_error( compare_api( 'GET', '/shared-skills', array( 'search' => str_repeat( 'x', 101 ) ) ), 400, 'rest_invalid_param', 'search length' );
	compare_error( compare_api( 'GET', '/shared-skills', array( 'page' => 0 ) ), 400, 'rest_invalid_param', 'page minimum' );
	$found = compare_api( 'GET', '/shared-skills', array( 'search' => $tag ) )->get_data();
	compare_ok( 1 === $found['total'] && (int) $shared['id'] === (int) $found['items'][0]['id'], 'shared skill search' );
	$detail = compare_api( 'GET', '/shared-skills/' . $shared['id'] )->get_data();
	compare_ok( 2 === count( $detail['mappings']['mapped_from'] ), 'both objectives are visible' );
	compare_error( compare_api( 'POST', '/shared-skills/save', array( 'id' => $shared['id'] ) ), 400, 'rest_missing_callback_param', 'name required' );
	compare_error(
		compare_api(
			'POST',
			'/shared-skills/save',
			array(
				'id'                => $shared['id'],
				'name'              => 'x',
				'expected_revision' => 'short',
			)
		),
		400,
		'rest_invalid_param',
		'revision format'
	);
	compare_error(
		compare_api(
			'POST',
			'/shared-skills/save',
			array(
				'id'                => $shared['id'],
				'name'              => $tag . ' renamed',
				'subject'           => $subject,
				'expected_revision' => str_repeat( '0', 64 ),
			)
		),
		409,
		'ohmylms_shared_stale',
		'stale shared skill save'
	);
	$renamed = compare_api(
		'POST',
		'/shared-skills/save',
		array(
			'id'                => $shared['id'],
			'name'              => $tag . ' Solve equations',
			'subject'           => $subject,
			'topic'             => 'Algebra',
			'expected_revision' => $detail['revision'],
		)
	);
	compare_ok( 200 === $renamed->get_status() && $tag . ' Solve equations' === $renamed->get_data()['name'], 'shared skill saved with the current revision' );

	// Roadmaps.
	compare_error( compare_api( 'POST', '/roadmaps/preview', array() ), 400, 'rest_missing_callback_param', 'preview needs sources' );
	compare_error( compare_api( 'POST', '/roadmaps/preview', array( 'syllabus_ids' => array( $items[0], $items[0] ) ) ), 400, 'rest_invalid_param', 'duplicate sources' );
	$preview = compare_api( 'POST', '/roadmaps/preview', array( 'syllabus_ids' => $items ) )->get_data();
	compare_ok( array( (int) $shared['id'] ) === array_map( 'intval', array_column( $preview['skills'], 'id' ) ) && 2 === count( $preview['sources'] ), 'preview merges shared identities' );
	compare_error( compare_api( 'POST', '/roadmaps', array( 'syllabus_ids' => $items ) ), 400, 'rest_missing_callback_param', 'roadmap needs a name' );
	compare_error(
		compare_api(
			'POST',
			'/roadmaps',
			array(
				'name'      => 'x',
				'skill_ids' => array( 0 ),
			)
		),
		400,
		'rest_invalid_param',
		'skill ids are positive'
	);
	$created = compare_api(
		'POST',
		'/roadmaps',
		array(
			'name'         => $tag . ' Roadmap',
			'syllabus_ids' => $items,
		)
	);
	compare_ok( 200 === $created->get_status(), 'roadmap created: ' . wp_json_encode( $created->get_data() ) );
	$roadmap = (int) $created->get_data()['syllabus']['id'];
	$items[] = $roadmap;
	$state   = compare_api( 'GET', '/roadmaps/' . $roadmap )->get_data();
	compare_ok( preg_match( '/^[a-f0-9]{64}$/', $state['revision'] ) && array( (int) $shared['id'] ) === array_column( $state['skills'], 'id' ), 'roadmap state with revision' );
	$write = array(
		'name'         => $tag . ' Roadmap 2',
		'syllabus_ids' => $items[0] === $state['syllabus_ids'][0] ? array( $items[0] ) : $state['syllabus_ids'],
		'skill_ids'    => array_column( $state['skills'], 'id' ),
	);
	compare_error( compare_api( 'PUT', '/roadmaps/' . $roadmap, $write ), 400, 'rest_missing_callback_param', 'roadmap save needs a revision' );
	compare_error( compare_api( 'PUT', '/roadmaps/' . $roadmap, $write + array( 'expected_revision' => str_repeat( 'a', 64 ) ) ), 409, 'ohmylms_roadmap_stale', 'stale roadmap save' );
	compare_ok( Items::get( $roadmap )['name'] === $tag . ' Roadmap', 'stale roadmap save wrote nothing' );
	compare_error(
		compare_api(
			'PUT',
			'/roadmaps/' . $roadmap,
			$write + array(
				'expected_revision' => $state['revision'],
				'folders'           => array( array( 'id' => 'x' ) ),
			)
		),
		400,
		'rest_invalid_param',
		'folder shape'
	);
	$updated = compare_api( 'PUT', '/roadmaps/' . $roadmap, $write + array( 'expected_revision' => $state['revision'] ) );
	compare_ok( 200 === $updated->get_status() && $tag . ' Roadmap 2' === $updated->get_data()['name'] && $updated->get_data()['revision'] !== $state['revision'], 'roadmap saved with the current revision' );
	$list = compare_api( 'GET', '/syllabuses' )->get_data();
	$row  = current( array_filter( $list['items'], static fn( $entry ) => $roadmap === (int) $entry['id'] ) );
	compare_ok( $row && 'roadmap' === $row['item_type'], 'roadmaps are marked' );

	// Learner audit.
	compare_error( compare_api( 'GET', '/audit/999999999' ), 404, 'ohmylms_student_missing', 'unknown learner' );
	$audit = compare_api( 'GET', '/audit/' . $users['subscriber'] );
	compare_ok( 200 === $audit->get_status() && isset( $audit->get_data()[ $subject ] ), 'audit lists configured subjects' );

	// Removing a group.
	$current = compare_api( 'GET', '/syllabuses' )->get_data()['revision'];
	$removed = compare_api(
		'PUT',
		'/configuration',
		array(
			'subject'           => $subject,
			'syllabus_ids'      => array(),
			'expected_revision' => $current,
		)
	);
	compare_ok( 200 === $removed->get_status() && ! isset( $removed->get_data()['selected'][ $subject ] ), 'an empty group is removed' );

	echo "OK curriculum-compare-rest-integration: {$compare_checks} checks\n";
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	$exit_code = 1;
} finally {
	if ( isset( $admin ) ) {
		wp_set_current_user( $admin );
	}
	foreach ( $maps as $pair ) {
		SkillMappings::remove( $pair[0], $pair[1] );
	}
	foreach ( array_reverse( $items ) as $item ) {
		if ( Items::get( $item ) ) {
			$course = SyllabusCourse::course_id( $item );
			Items::delete( $item, 'delete', true );
			if ( $course ) {
				wp_delete_post( $course, true );
			}
		}
	}
	foreach ( $terms as $term ) {
		wp_delete_term( $term, Taxonomy::NAME );
	}
	if ( null === $option ) {
		delete_option( Comparison::OPTION );
	} else {
		update_option( Comparison::OPTION, $option, false );
	}
	foreach ( $users as $user ) {
		wp_delete_user( $user );
	}
}
exit( $exit_code );
