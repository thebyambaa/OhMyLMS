<?php
/**
 * Gradebook: cell states (missing vs not assigned vs none vs pending), weighting and override math, the
 * letter scale, permissions per role and scope, overrides with history and 409, item settings, saved
 * views, the five views, caching and timings.
 *
 * Runs against a WordPress install (the Local site by default) with real tables. Fixtures are tagged
 * (tests/php/support/gradebook-fixture.php) and removed in `finally`. It never resets the database.
 *
 * Usage (set OHMYLMS_GRADEBOOK_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/gradebook-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_GRADEBOOK_TEST' ) ) {
	exit( "Set OHMYLMS_GRADEBOOK_TEST=1 to run the gradebook tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/gradebook-fixture.php';

use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\ScopeResolver;
use OhMyLMS\Gradebook\GradeMath;
use OhMyLMS\Gradebook\ScaleSettings;
use OhMyLMS\Gradebook\Schema as GradebookSchema;
use OhMyLMS\Gradebook\Version;

global $wpdb;

$checks  = 0;
$started = microtime( true );
$tag     = 'gbk' . strtolower( wp_generate_password( 6, false, false ) );
add_filter( 'pre_wp_mail', '__return_true' );

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
 * Assert two values are equal (floats to 0.01).
 *
 * @param mixed  $expected Expected.
 * @param mixed  $actual   Actual.
 * @param string $message  Message.
 */
function same( $expected, $actual, $message ) {
	$equal = is_float( $expected ) || is_float( $actual ) ? null !== $actual && null !== $expected && abs( (float) $expected - (float) $actual ) < 0.01 : $expected === $actual;
	ok( $equal, $message . ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')' );
}

/**
 * Dispatch a REST request as a user.
 *
 * @param int    $user   User (0 = anonymous).
 * @param string $method Method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $params Parameters (query for GET, JSON body otherwise).
 * @return WP_REST_Response
 */
function call( $user, $method, $path, array $params = array() ) {
	wp_set_current_user( $user );
	ScopeResolver::reset();
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method || 'DELETE' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert a status (and optionally an error code).
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Status.
 * @param string           $code     Error code ('' to skip).
 * @param string           $message  Message.
 */
function status_is( $response, $status, $code, $message ) {
	$data = $response->get_data();
	ok( $response->get_status() === $status, $message . ': status ' . $response->get_status() . ' ' . wp_json_encode( $data ) );
	if ( '' !== $code ) {
		ok( is_array( $data ) && ( $data['code'] ?? '' ) === $code, $message . ': code ' . wp_json_encode( $data['code'] ?? null ) );
	}
}

/**
 * Rows of a course grid by user id.
 *
 * @param array $data Response data.
 * @return array<int,array>
 */
function by_user( array $data ) {
	$rows = array();
	foreach ( $data['rows'] as $row ) {
		$rows[ $row['id'] ] = $row;
	}
	return $rows;
}

/**
 * A cell of a row as an array.
 *
 * @param array $row  Row.
 * @param int   $item Item id.
 * @return array|null
 */
function cell_of( array $row, $item ) {
	$cells = (array) $row['cells'];
	return isset( $cells[ (string) $item ] ) ? (array) $cells[ (string) $item ] : null;
}

$scale_before = get_option( ScaleSettings::OPTION, null );
try {
	// ---------------------------------------------------------------- Pure math.
	$cell = GradeMath::cell(
		array(
			'assigned' => false,
			'graded'   => array(
				'score' => 5,
				'max'   => 10,
			),
			'now'      => '2026-01-02 00:00:00',
			'max'      => 10,
		)
	);
	same( 'not_assigned', $cell['state'], 'not assigned wins over attempts' );
	$cell = GradeMath::cell(
		array(
			'assigned' => true,
			'due'      => '2026-01-01 00:00:00',
			'now'      => '2026-01-02 00:00:00',
			'max'      => 10,
		)
	);
	same( 'missing', $cell['state'], 'assigned, due passed, nothing submitted: missing' );
	$cell = GradeMath::cell(
		array(
			'assigned' => true,
			'due'      => '2026-01-03 00:00:00',
			'now'      => '2026-01-02 00:00:00',
			'max'      => 10,
		)
	);
	same( 'none', $cell['state'], 'not due yet: none' );
	$cell = GradeMath::cell(
		array(
			'assigned' => true,
			'pending'  => true,
			'due'      => '2026-01-01 00:00:00',
			'now'      => '2026-01-02 00:00:00',
			'max'      => 10,
		)
	);
	same( 'pending', $cell['state'], 'a submission waiting for grading is pending, not missing' );
	$cell = GradeMath::cell(
		array(
			'assigned' => true,
			'pending'  => true,
			'now'      => '2026-01-02 00:00:00',
			'max'      => 10,
			'override' => array(
				'kind'  => 'score',
				'score' => 7,
				'max'   => 10,
			),
		)
	);
	same( 'score', $cell['state'], 'override gives a score' );
	ok( $cell['overridden'] && 'pending' === $cell['computed']['state'], 'the computed state stays visible under an override' );
	$cell = GradeMath::cell(
		array(
			'assigned' => false,
			'now'      => '2026-01-02 00:00:00',
			'max'      => 10,
			'override' => array(
				'kind'  => 'excused',
				'score' => null,
				'max'   => null,
			),
		)
	);
	same( 'not_assigned', $cell['state'], 'an override never applies to a not-assigned cell' );

	$cells = array(
		array(
			'category' => 'homework',
			'state'    => 'score',
			'score'    => 18,
			'max'      => 20,
		),
		array(
			'category' => 'quiz',
			'state'    => 'score',
			'score'    => 8,
			'max'      => 10,
		),
		array(
			'category' => 'test',
			'state'    => 'score',
			'score'    => 30,
			'max'      => 40,
		),
		array(
			'category' => 'homework',
			'state'    => 'none',
			'score'    => null,
			'max'      => 20,
		),
	);
	same( 79.5, GradeMath::total( $cells, array( 'homework' => 20, 'quiz' => 30, 'test' => 50 ), true )['percent'], 'weighted total' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 80.0, GradeMath::total( $cells, array(), true )['percent'], 'points total (56/70)' );
	same( 'points', GradeMath::total( $cells, null, true )['mode'], 'no weights: points mode' );
	$missing = array(
		array(
			'category' => 'quiz',
			'state'    => 'score',
			'score'    => 9,
			'max'      => 10,
		),
		array(
			'category' => 'homework',
			'state'    => 'missing',
			'score'    => null,
			'max'      => 20,
		),
	);
	same( 38.57, GradeMath::total( $missing, array( 'homework' => 40, 'quiz' => 30, 'test' => 30 ), true )['percent'], 'missing counts as zero; empty test category re-normalised' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 90.0, GradeMath::total( $missing, array( 'homework' => 40, 'quiz' => 30, 'test' => 30 ), false )['percent'], 'missing ignored when the setting is off' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 90.0, GradeMath::total( $missing, array( 'quiz' => 100 ), true )['percent'], 'items of a category without weight do not count' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( is_wp_error( GradeMath::validate_weights( array( 'quiz' => 50, 'test' => 40 ) ) ), 'weights must add to 100' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( is_wp_error( GradeMath::validate_weights( array( 'Quiz' => 100 ) ) ), 'category keys are slugs' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( is_wp_error( GradeMath::validate_weights( array( 'quiz' => 100.001 ) ) ), 'two decimals at most' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( is_wp_error( GradeMath::validate_weights( array( 'a' => -10, 'b' => 110 ) ) ), 'no negative weights' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( array( 'quiz' => 33.33, 'test' => 66.67 ), GradeMath::validate_weights( array( 'quiz' => '33.33', 'test' => 66.67 ) ), 'decimal weights accepted' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( array(), GradeMath::validate_weights( array() ), 'empty weights clear them' );
	$scale = ScaleSettings::scale(
		array(
			'a_min' => 90,
			'b_min' => 80,
			'c_min' => 70,
			'd_min' => 60,
		)
	);
	same( 'A', GradeMath::letter( 90.0, $scale ), 'boundary is inclusive' );
	same( 'B', GradeMath::letter( 89.99, $scale ), 'just below A' );
	same( 'F', GradeMath::letter( 0.0, $scale ), 'zero is F' );
	same( null, GradeMath::letter( null, $scale ), 'no total, no letter' );

	// ---------------------------------------------------------------- Fixtures.
	delete_option( ScaleSettings::OPTION );
	$fx      = gbfx_create( $tag, true );
	$l       = $fx['l'];
	$course  = $fx['course'];
	$scope_a = 'school:' . $fx['school_a'];
	$scope_1 = 'class:' . $fx['class_a1'];
	ok( GradebookSchema::ready(), 'schema installed' );

	// ---------------------------------------------------------------- Access per role.
	status_is( call( 0, 'GET', '/gradebook/context' ), 401, 'ohmylms_gradebook_cannot_view', 'anonymous' );
	status_is( call( $fx['outsider'], 'GET', '/gradebook/context' ), 403, 'ohmylms_gradebook_cannot_view', 'subscriber' );
	ok( user_can( $fx['teacher'], 'ohmylms_view_gradebook' ) && user_can( $fx['sa'], 'ohmylms_view_gradebook' ) && user_can( $fx['admin'], 'ohmylms_view_gradebook' ), 'staff hold the capability' );
	ok( ! user_can( $l[1], 'ohmylms_view_gradebook' ), 'learners do not' );
	$context = call( $fx['teacher'], 'GET', '/gradebook/context' )->get_data();
	same( $scope_1, $context['scope']['id'], 'teacher default scope is their class' );
	ok( in_array( $course, wp_list_pluck( $context['courses'], 'id' ), true ), 'teacher sees the linked course' );
	same( 1, count( $context['classes'] ), 'teacher context lists only their class' );
	status_is( call( $fx['teacher'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'class:' . $fx['class_a2'] ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'teacher cannot use another class' );
	status_is( call( $fx['sa'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all' ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'school admin cannot use all' );
	status_is( call( $fx['sb'], 'GET', '/gradebook/courses/' . $fx['perf']['course'], array( 'scope' => 'school:' . $fx['school_b'] ) ), 404, 'ohmylms_gradebook_course_not_found', 'course outside the scope' );

	// ---------------------------------------------------------------- Course × Students: states and totals.
	$admin = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all' ) );
	status_is( $admin, 200, '', 'admin grid' );
	ok( 'private, no-store' === ( $admin->get_headers()['Cache-Control'] ?? '' ), 'no-store' );
	$data = $admin->get_data();
	same( 10, $data['total'], 'admin sees every enrolled learner' );
	same( 5, count( $data['items'] ), 'five graded items' );
	$kinds = array_combine( wp_list_pluck( $data['items'], 'id' ), wp_list_pluck( $data['items'], 'kind' ) );
	same( 'exam', $kinds[ $fx['e1'] ], 'exam detected' );
	same( 'assignment', $kinds[ $fx['h1'] ], 'assignment kind' );
	$rows = by_user( $data );
	same( 'roster', $rows[ $l[1] ]['source'], 'roster source' );
	same( 'purchase', $rows[ $l[8] ]['source'], 'purchase source' );
	same( 'self', $rows[ $l[9] ]['source'], 'self source' );
	same( $fx['class_a1'], $rows[ $l[1] ]['via']['id'], 'via class A1' );
	same( 'score', cell_of( $rows[ $l[1] ], $fx['q1'] )['state'], 'L1 Q1 graded' );
	same( 80.0, cell_of( $rows[ $l[1] ], $fx['q1'] )['percent'], 'L1 Q1 80%' );
	same( 'pending', cell_of( $rows[ $l[3] ], $fx['q1'] )['state'], 'in review: pending' );
	same( 'none', cell_of( $rows[ $l[4] ], $fx['q1'] )['state'], 'in progress without due date: none' );
	same( 'missing', cell_of( $rows[ $l[3] ], $fx['h1'] )['state'], 'L3 H1 due yesterday: missing' );
	same( 'pending', cell_of( $rows[ $l[2] ], $fx['h1'] )['state'], 'submitted assignment past due: pending, not missing' );
	same( 'not_assigned', cell_of( $rows[ $l[5] ], $fx['h2'] )['state'], 'H2 targets class A1: not assigned to A2' );
	same( 'none', cell_of( $rows[ $l[1] ], $fx['h2'] )['state'], 'H2 assigned to A1, not due' );
	same( 'not_assigned', cell_of( $rows[ $l[1] ], $fx['q2'] )['state'], 'Q2 for individuals: not assigned to a roster learner' );
	same( 'score', cell_of( $rows[ $l[8] ], $fx['q2'] )['state'], 'Q2 graded for an individual' );
	same( 'missing', cell_of( $rows[ $l[8] ], $fx['h1'] )['state'], 'individual missing H1' );
	same( 79.5, $rows[ $l[1] ]['total']['percent'], 'L1 total with the class A1 override weights' );
	same( 'C', $rows[ $l[1] ]['total']['letter'], 'L1 letter' );
	same( $fx['class_a1'], $rows[ $l[1] ]['total']['weights_class'], 'L1 weights come from class A1' );
	same( 50.0, $rows[ $l[2] ]['total']['percent'], 'L2 total: only Q1 counts' );
	same( 0.0, $rows[ $l[3] ]['total']['percent'], 'L3 total: missing H1 counts as zero' );
	same( 50.0, $rows[ $l[5] ]['total']['percent'], 'L5 total with course weights' );
	same( 0, $rows[ $l[5] ]['total']['weights_class'], 'L5 uses the course weights' );
	same( 38.57, $rows[ $l[8] ]['total']['percent'], 'L8 total: missing zero, quiz 90, test empty' );
	same( 'F', $rows[ $l[8] ]['total']['letter'], 'L8 letter' );
	same( 0.0, $rows[ $l[10] ]['total']['percent'], 'L10 total: only the missing H1 counts' );
	same( 1, $rows[ $l[3] ]['counts']['missing'], 'missing count' );
	$summary = (array) $data['summary']['items'];
	ok( isset( $summary[ (string) $fx['q1'] ] ) && 65.0 === (float) $summary[ (string) $fx['q1'] ]['average'], 'column summary: Q1 average of 80 and 50' );

	// Filters.
	$individuals = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'audience' => 'individuals' ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	$ids         = wp_list_pluck( $individuals['rows'], 'id' );
	sort( $ids );
	$expected = array( $l[7], $l[8], $l[9], $l[10] );
	sort( $expected );
	same( $expected, $ids, 'individuals only: no class row in the course' );
	$roster = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'audience' => 'class:' . $fx['class_a1'] ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 4, $roster['total'], 'one roster: class A1' );
	$missing_rows = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'status' => 'missing' ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( ! in_array( $l[1], wp_list_pluck( $missing_rows['rows'], 'id' ), true ) && in_array( $l[3], wp_list_pluck( $missing_rows['rows'], 'id' ), true ), 'status filter: has missing work' );
	$typed = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'type' => 'homework' ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 2, count( $typed['items'] ), 'type filter keeps homework columns' );
	same( 79.5, by_user( $typed )[ $l[1] ]['total']['percent'], 'column filters do not change totals' );
	$moduled = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'module' => $fx['m2'] ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 3, count( $moduled['items'] ), 'module filter' );
	$searched = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'search' => 'L8 ' . $tag ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 1, $searched['total'], 'search by name' );
	$sorted = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'orderby' => 'total', 'order' => 'desc' ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( $l[1], $sorted['rows'][0]['id'], 'sorted by total, highest first' );
	$totals = array_map(
		static function ( $row ) {
			return $row['total']['percent'];
		},
		$sorted['rows']
	);
	$ordered = $totals;
	rsort( $ordered );
	same( $ordered, $totals, 'totals descend' );
	$paged = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'per_page' => 3, 'page' => 2 ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	same( 3, count( $paged['rows'] ), 'page size' );
	same( 4, $paged['total_pages'], 'pages' );
	status_is( call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all', 'audience' => 'class:x' ) ), 400, 'rest_invalid_param', 'bad audience' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	// Scoping.
	$school = call( $fx['sa'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => $scope_a ) )->get_data();
	$ids    = wp_list_pluck( $school['rows'], 'id' );
	sort( $ids );
	$expected = array_slice( array_values( $l ), 0, 6 );
	sort( $expected );
	same( $expected, $ids, 'school admin sees the school students only' );
	$teacher = call( $fx['teacher'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => $scope_1 ) )->get_data();
	same( 4, $teacher['total'], 'teacher sees their class' );
	same( array( $fx['class_a1'] ), wp_list_pluck( $teacher['classes'], 'id' ), 'teacher sees only their class name' );
	ok( ! $teacher['can']['manage_course'] && array( $fx['class_a1'] ) === $teacher['can']['weight_classes'], 'teacher may weight their class only' );
	$beta = call( $fx['sb'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'school:' . $fx['school_b'] ) )->get_data();
	same( array( $l[7] ), wp_list_pluck( $beta['rows'], 'id' ), 'school B admin sees only L7' );
	same( array(), $beta['classes'], 'school B sees no school A class names' );

	// ---------------------------------------------------------------- Overrides, history and 409.
	$path = '/gradebook/courses/' . $course . '/cells/' . $fx['h1'] . '/' . $l[2];
	$read = call( $fx['teacher'], 'GET', $path, array( 'scope' => $scope_1 ) );
	status_is( $read, 200, '', 'teacher reads a cell' );
	same( 0, $read->get_data()['cell']['version'], 'no manual change yet' );
	status_is( call( $fx['teacher'], 'PUT', $path, array( 'scope' => $scope_1, 'action' => 'override', 'score' => 15, 'reason' => '', 'expected_version' => 0 ) ), 422, 'ohmylms_gradebook_invalid_cell', 'reason required' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['teacher'], 'PUT', $path, array( 'scope' => $scope_1, 'action' => 'override', 'score' => 25, 'reason' => 'Too much', 'expected_version' => 0 ) ), 422, 'ohmylms_gradebook_invalid_cell', 'score above max' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['teacher'], 'PUT', $path, array( 'scope' => $scope_1, 'action' => 'override', 'score' => 15, 'reason' => 'Paper submission', 'expected_version' => 3 ) ), 409, 'ohmylms_gradebook_stale', 'wrong version on first write' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	$saved = call( $fx['teacher'], 'PUT', $path, array( 'scope' => $scope_1, 'action' => 'override', 'score' => 15, 'reason' => 'Paper submission', 'expected_version' => 0 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $saved, 200, '', 'override saved' );
	$cell = $saved->get_data()['cell'];
	ok( 'score' === $cell['state'] && $cell['overridden'] && 75.0 === (float) $cell['percent'], 'override applied' );
	same( 'pending', $cell['computed']['state'], 'computed state still visible' );
	same( 1, $cell['version'], 'version 1' );
	same( 'Paper submission', $cell['reason'], 'reason kept' );
	same( 60.0, $saved->get_data()['total']['percent'], 'total recomputed with the override (A1 weights)' );
	same( 50.0, $saved->get_data()['total']['computed_percent'], 'computed total without overrides' );
	$legacy = $wpdb->get_row( $wpdb->prepare( 'SELECT score, note FROM %i WHERE course_id = %d AND content_id = %d AND user_id = %d', \OhMyLMS\Schools\Schema::table( 'gradebook_overrides' ), $course, $fx['h1'], $l[2] ), ARRAY_A );
	ok( $legacy && 15.0 === (float) $legacy['score'], 'mirrored to the legacy override table' );
	$stale = call( $fx['admin'], 'PUT', $path, array( 'scope' => 'all', 'action' => 'override', 'score' => 12, 'reason' => 'Late fix', 'expected_version' => 0 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $stale, 409, 'ohmylms_gradebook_stale', 'stale write' );
	same( 15.0, (float) $stale->get_data()['data']['current']['cell']['score'], '409 carries the current cell' );
	$grid = by_user( call( $fx['teacher'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => $scope_1 ) )->get_data() );
	same( 60.0, $grid[ $l[2] ]['total']['percent'], 'cached grid shows the override at once (version key)' );
	ok( cell_of( $grid[ $l[2] ], $fx['h1'] )['overridden'], 'grid cell overridden' );
	status_is( call( $fx['admin'], 'PUT', $path, array( 'scope' => 'all', 'action' => 'override', 'score' => 12, 'reason' => 'Late fix', 'expected_version' => 1 ) ), 200, '', 'second override' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	$reverted = call( $fx['admin'], 'PUT', $path, array( 'scope' => 'all', 'action' => 'revert', 'expected_version' => 2 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $reverted, 200, '', 'revert' );
	ok( 'pending' === $reverted->get_data()['cell']['state'] && ! $reverted->get_data()['cell']['overridden'], 'reverted to computed' );
	same( 3, $reverted->get_data()['cell']['version'], 'revert raises the version' );
	$history = $reverted->get_data()['history'];
	same( 3, count( $history ), 'three history rows' );
	same( array( 'revert', 'override', 'override' ), wp_list_pluck( $history, 'action' ), 'history newest first' );
	ok( 12.0 === (float) $history[0]['old']['score'] && 15.0 === (float) $history[1]['old']['score'] && null === $history[2]['old']['score'], 'old values' );
	ok( $history[2]['by']['id'] === $fx['teacher'] && $history[1]['by']['id'] === $fx['admin'] && '' !== $history[0]['at'], 'who and when' );
	same( 'Paper submission', $history[2]['reason'], 'reason in history' );
	ok( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id = %d AND content_id = %d AND user_id = %d', \OhMyLMS\Schools\Schema::table( 'gradebook_overrides' ), $course, $fx['h1'], $l[2] ) ), 'legacy row removed on revert' );
	status_is( call( $fx['admin'], 'PUT', $path, array( 'scope' => 'all', 'action' => 'revert', 'expected_version' => 3 ) ), 409, 'ohmylms_gradebook_nothing_to_revert', 'nothing to revert' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	$excuse = call( $fx['sa'], 'PUT', '/gradebook/courses/' . $course . '/cells/' . $fx['h1'] . '/' . $l[3], array( 'scope' => $scope_a, 'action' => 'excuse', 'reason' => 'Medical note', 'expected_version' => 0 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $excuse, 200, '', 'school admin excuses' );
	same( 'excused', $excuse->get_data()['cell']['state'], 'excused' );
	same( 'missing', $excuse->get_data()['cell']['computed']['state'], 'computed missing stays visible' );
	same( null, $excuse->get_data()['total']['percent'], 'excused work leaves the total (nothing else counted)' );
	status_is( call( $fx['teacher'], 'PUT', '/gradebook/courses/' . $course . '/cells/' . $fx['h2'] . '/' . $l[1], array( 'scope' => $scope_1, 'action' => 'override', 'score' => 10, 'reason' => 'x', 'expected_version' => 0 ) ), 200, '', 'teacher overrides a none cell' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['admin'], 'PUT', '/gradebook/courses/' . $course . '/cells/' . $fx['h2'] . '/' . $l[5], array( 'scope' => 'all', 'action' => 'override', 'score' => 10, 'reason' => 'x', 'expected_version' => 0 ) ), 422, 'ohmylms_gradebook_not_assigned', 'not assigned cannot be graded' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['teacher'], 'PUT', '/gradebook/courses/' . $course . '/cells/' . $fx['h1'] . '/' . $l[5], array( 'scope' => $scope_1, 'action' => 'override', 'score' => 10, 'reason' => 'x', 'expected_version' => 0 ) ), 404, 'ohmylms_gradebook_learner_not_found', 'teacher cannot grade another class' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['sb'], 'GET', '/gradebook/courses/' . $course . '/cells/' . $fx['h1'] . '/' . $l[1], array( 'scope' => 'school:' . $fx['school_b'] ) ), 404, 'ohmylms_gradebook_learner_not_found', 'school B cannot read school A cells' );
	status_is( call( $fx['admin'], 'PUT', '/gradebook/courses/' . $course . '/cells/' . $fx['h1'] . '/' . $l[2], array( 'scope' => 'all', 'action' => 'boost', 'expected_version' => 3 ) ), 400, 'rest_invalid_param', 'unknown action' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	// Legacy route goes through the history.
	$legacy_request = new WP_REST_Request( 'POST', '/ohmylms/v1/courses/' . $course . '/gradebook/grades' );
	wp_set_current_user( $fx['admin'] );
	$legacy_request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$legacy_request->set_body_params(
		array(
			'user_id'    => $l[4],
			'content_id' => $fx['q1'],
			'score'      => 6,
			'note'       => '',
		)
	);
	status_is( rest_do_request( $legacy_request ), 200, '', 'legacy grade route' );
	$detail = call( $fx['admin'], 'GET', '/gradebook/courses/' . $course . '/cells/' . $fx['q1'] . '/' . $l[4], array( 'scope' => 'all' ) )->get_data();
	ok( 6.0 === (float) $detail['cell']['score'] && 1 === count( $detail['history'] ) && '' !== $detail['history'][0]['reason'], 'legacy grade recorded with a reason' );

	// ---------------------------------------------------------------- Weights.
	$weights_path = '/gradebook/courses/' . $course . '/weights';
	status_is( call( $fx['teacher'], 'PUT', $weights_path, array( 'scope' => $scope_1, 'class_id' => 0, 'weights' => array( 'quiz' => 100 ), 'expected_version' => 1 ) ), 403, 'ohmylms_gradebook_forbidden', 'teacher cannot change course weights' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['teacher'], 'PUT', $weights_path, array( 'scope' => $scope_1, 'class_id' => $fx['class_a2'], 'weights' => array( 'quiz' => 100 ), 'expected_version' => 0 ) ), 403, 'ohmylms_gradebook_forbidden', 'teacher cannot weight another class' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['teacher'], 'PUT', $weights_path, array( 'scope' => $scope_1, 'class_id' => $fx['class_a1'], 'weights' => array( 'quiz' => 60, 'test' => 30 ), 'expected_version' => 1 ) ), 422, 'ohmylms_gradebook_invalid_weights', 'sum must be 100' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['teacher'], 'PUT', $weights_path, array( 'scope' => $scope_1, 'class_id' => $fx['class_a1'], 'weights' => array( 'quiz' => 100 ), 'expected_version' => 0 ) ), 409, 'ohmylms_gradebook_stale', 'weights stale write' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	$w = call( $fx['teacher'], 'PUT', $weights_path, array( 'scope' => $scope_1, 'class_id' => $fx['class_a1'], 'weights' => array( 'homework' => 50, 'quiz' => 50 ), 'expected_version' => 1 ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $w, 200, '', 'teacher sets their class override' );
	same( 2, $w->get_data()['version'], 'override version 2' );
	$grid = by_user( call( $fx['teacher'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => $scope_1 ) )->get_data() );
	// L1: homework 18/20 (+ H2 override 10/20) = 28/40 = 70; quiz 80 => (50*70 + 50*80)/100 = 75.
	same( 75.0, $grid[ $l[1] ]['total']['percent'], 'L1 total with the new class weights and the H2 override' );
	status_is( call( $fx['admin'], 'PUT', $weights_path, array( 'scope' => 'all', 'class_id' => $fx['class_a1'], 'weights' => (object) array(), 'expected_version' => 2 ) ), 200, '', 'clear the class override' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	$grid = by_user( call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all' ) )->get_data() );
	same( 0, $grid[ $l[1] ]['total']['weights_class'], 'L1 back on course weights' );
	// L1 on course weights 40/30/30: homework 70, quiz 80, test 75 => 28 + 24 + 22.5 = 74.5.
	same( 74.5, $grid[ $l[1] ]['total']['percent'], 'L1 total with the course weights' );
	status_is( call( $fx['admin'], 'GET', $weights_path, array( 'scope' => 'all' ) ), 200, '', 'read weights' );

	// ---------------------------------------------------------------- Item settings.
	$item_path = '/gradebook/courses/' . $course . '/items/' . $fx['q1'];
	status_is( call( $fx['teacher'], 'PUT', $item_path, array( 'scope' => $scope_1, 'category' => 'quiz' ) ), 403, 'ohmylms_gradebook_forbidden', 'teacher cannot change item settings' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['admin'], 'PUT', $item_path, array( 'scope' => 'all', 'audience' => 'classes', 'class_ids' => array( $fx['class_b1'] ) ) ), 422, 'ohmylms_gradebook_invalid_item', 'audience class must be linked' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( call( $fx['admin'], 'PUT', $item_path, array( 'scope' => 'all', 'due_at' => 'tomorrow' ) ), 422, 'ohmylms_gradebook_invalid_item', 'bad due date' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	$item = call( $fx['admin'], 'PUT', $item_path, array( 'scope' => 'all', 'category' => 'quiz', 'max_marks' => 10, 'due_at' => wp_date( 'Y-m-d\TH:i', time() - HOUR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $item, 200, '', 'admin sets a due date on Q1' );
	$grid = by_user( call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all' ) )->get_data() );
	same( 'missing', cell_of( $grid[ $l[5] ], $fx['q1'] )['state'], 'Q1 now missing for L5 (due passed, nothing submitted)' );
	same( 'pending', cell_of( $grid[ $l[3] ], $fx['q1'] )['state'], 'in review stays pending' );

	// ---------------------------------------------------------------- Letter scale settings.
	status_is( call( $fx['teacher'], 'GET', '/settings/grade-scale' ), 403, '', 'teacher cannot read the scale settings' );
	$scale_read = call( $fx['admin'], 'GET', '/settings/grade-scale' );
	status_is( $scale_read, 200, '', 'admin reads the scale' );
	same( 90, $scale_read->get_data()['values']['a_min'], 'default A' );
	status_is( call( $fx['admin'], 'PATCH', '/settings/grade-scale', array( 'b_min' => 95 ) ), 422, 'ohmylms_settings_invalid_value', 'B above A refused' );
	status_is( call( $fx['admin'], 'PATCH', '/settings/grade-scale', array( 'a_min' => '93' ) ), 400, 'rest_invalid_param', 'string refused' );
	status_is( call( $fx['admin'], 'PATCH', '/settings/grade-scale', array( 'z_min' => 3 ) ), 400, 'ohmylms_settings_unknown_field', 'unknown field' );
	$patched = call( $fx['admin'], 'PATCH', '/settings/grade-scale', array( 'c_min' => 75, 'missing_as_zero' => false ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $patched, 200, '', 'scale saved' );
	same( array( 'c_min', 'missing_as_zero' ), $patched->get_data()['changed'], 'changed fields' );
	$grid = by_user( call( $fx['admin'], 'GET', '/gradebook/courses/' . $course, array( 'scope' => 'all' ) )->get_data() );
	same( 'D', $grid[ $l[1] ]['total']['letter'], '74.5 is D once C starts at 75' );
	same( 90.0, $grid[ $l[8] ]['total']['percent'], 'missing no longer counts as zero' );
	status_is( call( $fx['admin'], 'PATCH', '/settings/grade-scale', array( 'c_min' => 70, 'missing_as_zero' => true ) ), 200, '', 'scale restored' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	// ---------------------------------------------------------------- Other views.
	$roster_view = call( $fx['teacher'], 'GET', '/gradebook/classes/' . $fx['class_a1'], array( 'scope' => $scope_1 ) );
	status_is( $roster_view, 200, '', 'roster × assignments' );
	$rv = $roster_view->get_data();
	same( 4, count( $rv['learners'] ), 'class A1 learners' );
	same( 2, count( $rv['courses'] ), 'class A1 courses (Algebra and the performance course)' );
	$first = (array) $rv['learners'][0]['cells'];
	ok( isset( $first[ $course . ':' . $fx['q1'] ] ), 'cells keyed course:item' );
	status_is( call( $fx['teacher'], 'GET', '/gradebook/classes/' . $fx['class_a2'], array( 'scope' => $scope_1 ) ), 404, 'ohmylms_gradebook_class_not_found', 'teacher cannot open another roster' );

	$transcript = call( $fx['sa'], 'GET', '/gradebook/learners/' . $l[1], array( 'scope' => $scope_a ) );
	status_is( $transcript, 200, '', 'transcript' );
	$tr = $transcript->get_data();
	same( 1, count( $tr['enrolments'] ), 'L1 has one enrolment' );
	same( 74.5, $tr['enrolments'][0]['row']['total']['percent'], 'transcript total matches the grid' );
	same( 1, count( $tr['mastery']['skills'] ), 'L1 mastery' );
	same( 'proficient', $tr['mastery']['skills'][0]['level'], 'L1 skill level' );
	status_is( call( $fx['sa'], 'GET', '/gradebook/learners/' . $l[8], array( 'scope' => $scope_a ) ), 404, 'ohmylms_gradebook_learner_not_found', 'individual outside the school' );
	$picker = call( $fx['teacher'], 'GET', '/gradebook/learners', array( 'scope' => $scope_1, 'search' => 'L1' ) )->get_data();
	ok( in_array( $l[1], wp_list_pluck( $picker['learners'], 'id' ), true ) && ! in_array( $l[5], wp_list_pluck( $picker['learners'], 'id' ), true ), 'learner picker scoped' );

	$mastery = call( $fx['admin'], 'GET', '/gradebook/mastery', array( 'scope' => 'all', 'course' => $course ) );
	status_is( $mastery, 200, '', 'skill mastery' );
	$md = $mastery->get_data();
	same( 10, $md['total'], 'mastery learners' );
	same( array( $fx['skill'] ), wp_list_pluck( $md['skills'], 'id' ), 'mastery skills' );
	status_is( call( $fx['admin'], 'GET', '/gradebook/mastery', array( 'scope' => 'all' ) ), 400, 'ohmylms_gradebook_course_required', 'all needs a course' );
	same( 4, call( $fx['teacher'], 'GET', '/gradebook/mastery', array( 'scope' => $scope_1 ) )->get_data()['total'], 'class mastery without a course' );

	$rollup = call( $fx['admin'], 'GET', '/gradebook/rollup', array( 'scope' => 'all' ) );
	status_is( $rollup, 200, '', 'roll-up' );
	$nodes = array();
	foreach ( $rollup->get_data()['nodes'] as $node ) {
		$nodes[ $node['type'] . ':' . $node['name'] ] = $node;
	}
	$alpha = $nodes[ 'school:' . $tag . ' Alpha School' ] ?? null;
	ok( $alpha && 2 === count( $alpha['children'] ) && 6 === $alpha['learners'], 'school A with two classes and six learners' );
	same( 6, $alpha['enrolments'], 'school A enrolments: A1 four in Algebra (none in the performance course), A2 two' );
	ok( ! isset( $alpha['entries'] ), 'internal entries not sent' );
	$individual = $nodes['individuals:Individual learners (no class)'] ?? null;
	ok( $individual && $individual['learners'] >= 3, 'individual learners node' );
	$school_rollup = call( $fx['sa'], 'GET', '/gradebook/rollup', array( 'scope' => $scope_a ) )->get_data();
	same( 1, count( $school_rollup['nodes'] ), 'school admin roll-up: their school' );

	// ---------------------------------------------------------------- Saved views.
	status_is( call( $fx['teacher'], 'POST', '/gradebook/views', array( 'name' => '', 'section' => 'course', 'query' => '' ) ), 422, 'ohmylms_gradebook_invalid_view', 'view needs a name' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	$view = call( $fx['teacher'], 'POST', '/gradebook/views', array( 'name' => 'Missing work', 'section' => 'course', 'query' => 'scope=' . $scope_1 . '&course=' . $course . '&status=missing&evil=1' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	status_is( $view, 201, '', 'view saved' );
	ok( false === strpos( $view->get_data()['query'], 'evil' ), 'unknown parameters dropped' );
	same( 1, count( call( $fx['teacher'], 'GET', '/gradebook/views' )->get_data()['views'] ), 'one view' );
	same( 0, count( call( $fx['sa'], 'GET', '/gradebook/views' )->get_data()['views'] ), 'views are per user' );
	status_is( call( $fx['sa'], 'DELETE', '/gradebook/views/' . $view->get_data()['id'] ), 404, 'ohmylms_gradebook_view_not_found', 'cannot delete another user\'s view' );
	status_is( call( $fx['teacher'], 'DELETE', '/gradebook/views/' . $view->get_data()['id'] ), 200, '', 'view deleted' );

	// ---------------------------------------------------------------- Caching and timings (30 × 20).
	$perf = $fx['perf']['course'];
	Version::bump();
	$t0   = microtime( true );
	$cold = call( $fx['admin'], 'GET', '/gradebook/courses/' . $perf, array( 'scope' => 'all' ) );
	$cold_ms = ( microtime( true ) - $t0 ) * 1000;
	status_is( $cold, 200, '', 'perf grid' );
	same( 'miss', $cold->get_headers()['X-OhMyLMS-Cache'], 'first read is a miss' );
	same( 30, $cold->get_data()['total'], '30 learners' );
	same( 20, count( $cold->get_data()['items'] ), '20 items' );
	$times = array();
	for ( $n = 0; $n < 5; $n++ ) {
		$t0      = microtime( true );
		$warm    = call( $fx['admin'], 'GET', '/gradebook/courses/' . $perf, array( 'scope' => 'all' ) );
		$times[] = ( microtime( true ) - $t0 ) * 1000;
	}
	sort( $times );
	same( 'hit', $warm->get_headers()['X-OhMyLMS-Cache'], 'warm read is a hit' );
	ok( $times[2] < 400, 'warm 30 × 20 under 400 ms (median ' . round( $times[2], 1 ) . ' ms)' );
	$t0       = microtime( true );
	$teacher  = call( $fx['teacher'], 'GET', '/gradebook/courses/' . $perf, array( 'scope' => $scope_1 ) );
	$scope_ms = ( microtime( true ) - $t0 ) * 1000;
	same( 0, $teacher->get_data()['total'], 'perf learners are not in class A1 (scoped)' );
	echo sprintf( "timings: 30x20 cold %.1f ms, warm median %.1f ms (in-process), teacher scope cold %.1f ms\n", $cold_ms, $times[2], $scope_ms );
	add_filter( 'ohmylms_dashboard_cache_ttl', '__return_zero' );
	same( 'miss', call( $fx['admin'], 'GET', '/gradebook/courses/' . $perf, array( 'scope' => 'all' ) )->get_headers()['X-OhMyLMS-Cache'], 'ttl 0 disables caching' );
	remove_filter( 'ohmylms_dashboard_cache_ttl', '__return_zero' );
	$version = Version::current();
	do_action( 'ohmylms_attempt_graded', 1 );
	ok( Version::current() !== $version, 'grading replaces the gradebook version' );
} finally {
	try {
		gbfx_delete( $tag );
	} catch ( Throwable $cleanup ) {
		echo 'cleanup failed: ' . $cleanup->getMessage() . "\n";
	}
	if ( null === $scale_before ) {
		delete_option( ScaleSettings::OPTION );
	} else {
		update_option( ScaleSettings::OPTION, $scale_before, false );
	}
	Cache::flush();
}
$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_login LIKE %s', $wpdb->users, $wpdb->esc_like( $tag ) . '%' ) );
ok( 0 === $left, 'fixtures removed' );
echo sprintf( "gradebook-rest-integration: %d checks passed in %.1f s\n", $checks, microtime( true ) - $started );
