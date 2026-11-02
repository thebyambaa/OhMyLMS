<?php
/**
 * Mastery heatmap and recommended review sets: aggregation on fixtures (level thresholds, scope filtering,
 * bounds, course rows), the learner list (scope, PII, links), the recommendations, and creating a review
 * set (draft only, permissions, staff limited to their own classes, idempotency, bank shortages), plus caching.
 *
 * Runs against a WordPress install (the Local site by default) with real tables. Fixtures are tagged
 * (tests/php/support/mastery-fixture.php) and removed in `finally`. It never resets the database.
 *
 * Usage (set OHMYLMS_DASHBOARD_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/dashboard-mastery-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_DASHBOARD_TEST' ) ) {
	exit( "Set OHMYLMS_DASHBOARD_TEST=1 to run the mastery dashboard tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/mastery-fixture.php';

use OhMyLMS\Assessment\Pools;
use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\MasteryRepository;
use OhMyLMS\Dashboard\ScopeResolver;
use OhMyLMS\Dashboard\Widgets\MasteryLevels;
use OhMyLMS\Dashboard\Widgets\ReviewSetsService;

global $wpdb;

$checks  = 0;
$started = microtime( true );
$tag     = 'mast' . strtolower( wp_generate_password( 6, false, false ) );
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
 * Dispatch a REST request as a user.
 *
 * @param int    $user   User (0 = anonymous).
 * @param string $method Method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $params Parameters.
 * @return WP_REST_Response
 */
function call( $user, $method, $path, array $params = array() ) {
	wp_set_current_user( $user );
	ScopeResolver::reset();
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $message  Failure message.
 */
function is_error( $response, $status, $code, $message ) {
	$data = $response->get_data();
	ok( $status === $response->get_status(), $message . ' answers ' . $status . ' (got ' . $response->get_status() . ': ' . wp_json_encode( $data ) . ')' );
	ok( ( $data['code'] ?? '' ) === $code, $message . ' code ' . $code . ' (got ' . ( $data['code'] ?? '?' ) . ')' );
}

/**
 * Heatmap cell of a response by row key and skill id, or null.
 *
 * @param array  $data  Widget data.
 * @param string $row   Row key.
 * @param int    $skill Skill.
 * @return array|null
 */
function cell( array $data, $row, $skill ) {
	$rows   = array_flip( array_column( $data['rows'], 'key' ) );
	$skills = array_flip( array_column( $data['skills'], 'id' ) );
	foreach ( $data['cells'] as $cell ) {
		if ( isset( $rows[ $row ], $skills[ $skill ] ) && $cell['row'] === $rows[ $row ] && $cell['skill'] === $skills[ $skill ] ) {
			return $cell;
		}
	}
	return null;
}

/**
 * Number of review-set quizzes of a group.
 *
 * @param int $class_id Class.
 * @param int $skill_id Skill.
 * @return int
 */
function review_sets( $class_id, $skill_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_status <> 'trash'", ReviewSetsService::KEY_META, ReviewSetsService::key( $class_id, $skill_id ) ) );
}

try {
	// ---- Level thresholds (pure) ----
	$th = MasteryLevels::thresholds();
	ok( array( 95, 80, 50 ) === array( $th['mastered'], $th['proficient'], $th['familiar'] ), 'default thresholds 95/80/50' );
	foreach ( array(
		array( 100, true, 'mastered' ),
		array( 95, true, 'mastered' ),
		array( 94.5, true, 'mastered' ),
		array( 94.4, true, 'proficient' ),
		array( 80, true, 'proficient' ),
		array( 79.5, true, 'proficient' ),
		array( 79.4, true, 'familiar' ),
		array( 50, true, 'familiar' ),
		array( 49.5, true, 'familiar' ),
		array( 49.4, true, 'attempted' ),
		array( 0, true, 'attempted' ),
		array( 0, false, 'not-started' ),
		array( 100, false, 'not-started' ),
	) as $case ) {
		ok( MasteryLevels::level( $case[0], $case[1], $th ) === $case[2], 'level of ' . $case[0] . ( $case[1] ? '' : ' (no evidence)' ) . ' is ' . $case[2] );
	}
	ok( MasteryLevels::below_familiar( 49.4, $th ) && ! MasteryLevels::below_familiar( 49.6, $th ), 'below Familiar uses the rounded percentage' );
	$legend = MasteryLevels::legend( $th );
	ok( array( 'mastered', 'proficient', 'familiar', 'attempted', 'not-started' ) === array_column( $legend, 'id' ), 'legend lists the five levels highest first' );
	ok( 'Not started' === $legend[4]['label'] && null === $legend[4]['min'] && 95 === $legend[0]['min'], 'legend has names and lower bounds' );
	$custom = static function () {
		return array(
			'mastered'   => 90,
			'proficient' => 70,
			'familiar'   => 40,
		);
	};
	add_filter( 'ohmylms_dashboard_mastery_thresholds', $custom );
	ok( 'familiar' === MasteryLevels::level( 45, true, MasteryLevels::thresholds() ), 'filtered thresholds apply' );
	remove_filter( 'ohmylms_dashboard_mastery_thresholds', $custom );
	$broken = static function () {
		return array(
			'mastered'   => 50,
			'proficient' => 80,
			'familiar'   => 10,
		);
	};
	add_filter( 'ohmylms_dashboard_mastery_thresholds', $broken );
	ok( MasteryLevels::DEFAULTS === MasteryLevels::thresholds(), 'thresholds out of order fall back to the defaults' );
	remove_filter( 'ohmylms_dashboard_mastery_thresholds', $broken );

	$fx = masteryfx_create( $tag );
	$l  = $fx['learners'];
	$sa = 'school:' . $fx['school_a'];
	$a1 = 'class:' . $fx['class_a1'];
	$a2 = 'class:' . $fx['class_a2'];

	// ---- Heatmap: school A (two classes) ----
	$r = call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => $sa ) );
	ok( 200 === $r->get_status(), 'heatmap answers 200: ' . wp_json_encode( $r->get_data() ) );
	ok( 'miss' === $r->get_headers()['X-OhMyLMS-Cache'] && 'private, no-store' === $r->get_headers()['Cache-Control'], 'first heatmap read is a miss, not stored by proxies' );
	$h = $r->get_data()['data'];
	ok( 'class' === $h['row_type'] && array( $a1, $a2 ) === array_column( $h['rows'], 'key' ), 'school rows are its classes in order' );
	ok( array( 6, 4 ) === array_column( $h['rows'], 'learners' ), 'rows carry roster sizes' );
	ok( array( $fx['k1'], $fx['k2'] ) === array_slice( array_column( $h['skills'], 'id' ), 0, 2 ) && 4 === count( $h['skills'] ), 'skills ordered by learners with evidence (K1 9, K2 5), K3 and K4 after' );
	ok( 9 === $h['skills'][0]['assessed'] && 'Mathematics' === $h['skills'][0]['subject'] && '' === $h['skills'][2]['subject'], 'skills carry evidence counts and subjects' );
	ok( array( 'Mathematics', 'Science' ) === array_column( $h['subjects'], 'name' ), 'subjects of the skills, sorted' );
	$c = cell( $h, $a1, $fx['k1'] );
	ok( $c && 64 === $c['percent'] && 'familiar' === $c['level'] && 5 === $c['assessed'] && 1 === $c['below'], 'A1 x K1: (100+96+85+60+40)/6 = 64 % Familiar, 5 assessed, 1 below: ' . wp_json_encode( $c ) );
	$c = cell( $h, $a2, $fx['k1'] );
	ok( $c && 95 === $c['percent'] && 'mastered' === $c['level'] && 0 === $c['below'], 'A2 x K1: 95 % Mastered' );
	$c = cell( $h, $a1, $fx['k2'] );
	ok( $c && 26 === $c['percent'] && 'attempted' === $c['level'] && 5 === $c['below'], 'A1 x K2: 26 % Attempted, 5 below (49.4 rounds to 49)' );
	ok( null === cell( $h, $a2, $fx['k2'] ), 'A2 x K2 (a row without evidence) is not started and not listed' );
	$c = cell( $h, $a1, $fx['k3'] );
	ok( $c && 2 === $c['below'] && 13 === $c['percent'], 'A1 x K3: 49.6 rounds to Familiar, 2 below, (49.6+10+20)/6 = 13 %' );
	ok( 5 === count( $h['levels'] ) && 'mastered' === $h['levels'][0]['id'], 'heatmap carries the legend' );
	ok(
		array(
			'rows'   => 20,
			'skills' => 40,
		) === $h['limits'],
		'heatmap states its bounds'
	);
	ok( true === $h['can_open_reports'], 'site administrators may open student reports' );
	$json = wp_json_encode( $r->get_data() );
	ok( false === strpos( $json, '@example.test' ) && false === strpos( $json, $tag . '_l1' ) && false === strpos( $json, 'L1 ' . $tag ), 'heatmap carries no names, logins or e-mails' );

	// ---- Caching: shared key, hit for another user of the scope ----
	$r = call( $fx['sa'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => $sa ) );
	ok( 200 === $r->get_status() && 'hit' === $r->get_headers()['X-OhMyLMS-Cache'], 'the school administrator reads the cached figures' );
	ok( false === $r->get_data()['data']['can_open_reports'], 'school administrators get no student report links' );
	ok( $r->get_data()['data']['cells'] === $h['cells'], 'cached cells are the same' );

	// ---- Scope filtering ----
	$r = call( $fx['teacher'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => $a1 ) );
	$t = $r->get_data()['data'];
	ok( 200 === $r->get_status() && array( $a1 ) === array_column( $t['rows'], 'key' ), 'a teacher sees their class only' );
	ok( 64 === cell( $t, $a1, $fx['k1'] )['percent'], 'class figures equal the school figures for that class' );
	is_error( call( $fx['teacher'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => $sa ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'a teacher asking for the school' );
	is_error( call( $fx['teacher'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => $a2 ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'a teacher asking for another class' );
	is_error( call( $fx['sa'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => 'school:' . $fx['school_b'] ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'a school administrator asking for another school' );
	is_error( call( 0, 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => $sa ) ), 401, 'ohmylms_dashboard_cannot_view', 'anonymous' );
	is_error( call( $l[1], 'GET', '/dashboard/widgets/mastery-heatmap', array() ), 403, 'ohmylms_dashboard_cannot_view', 'a learner' );
	$b = call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => 'school:' . $fx['school_b'] ) )->get_data()['data'];
	ok( array( 'class:' . $fx['class_b1'] ) === array_column( $b['rows'], 'key' ) && array( $fx['k2'] ) === array_column( $b['skills'], 'id' ), 'school B: its class and only the skill its learners have evidence on' );
	ok( 10 === cell( $b, 'class:' . $fx['class_b1'], $fx['k2'] )['percent'], 'school B figures exclude school A learners' );
	$all = call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => 'all' ) )->get_data()['data'];
	ok( 'class' === $all['row_type'] && $all['rows_total'] >= 24 && 20 === count( $all['rows'] ), 'all: every active class counted, at most 20 shown' );

	// ---- Course rows when the scope has no classes ----
	$g = call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => 'school:' . $fx['school_c'] ) )->get_data()['data'];
	ok( 'course' === $g['row_type'] && array( 'course:' . $fx['course_c'] ) === array_column( $g['rows'], 'key' ), 'a school without classes uses its courses: ' . wp_json_encode( $g['rows'] ) );
	ok( 3 === $g['rows'][0]['learners'] && 70 === cell( $g, 'course:' . $fx['course_c'], $fx['k1'] )['percent'] && 'familiar' === cell( $g, 'course:' . $fx['course_c'], $fx['k1'] )['level'], 'course row: 3 learners at 70 % Familiar' );

	// ---- Bounds ----
	$d = call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => 'school:' . $fx['school_d'] ) )->get_data()['data'];
	ok( 20 === count( $d['rows'] ) && 21 === $d['rows_total'], 'rows are bounded to 20 of 21' );
	ok( 40 === count( $d['skills'] ) && 40 === count( $d['cells'] ), 'skills are bounded to 40 of 41' );
	ok( MasteryRepository::MAX_ROWS === count( MasteryRepository::rows( ScopeResolver::resolve( 'school:' . $fx['school_d'], $fx['admin'] ) )['rows'] ), 'the read model applies the row bound' );

	// ---- Learners of a cell ----
	$r = call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $sa, 'row' => $a1, 'skill' => $fx['k2'] ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( 200 === $r->get_status(), 'learners answers 200: ' . wp_json_encode( $r->get_data() ) );
	$list = $r->get_data();
	ok( array( $l[5], $l[2], $l[1], $l[3], $l[4] ) === array_column( $list['learners'], 'id' ), 'learners below Familiar, lowest first' );
	ok( array( 10, 20, 30, 45, 49 ) === array_column( $list['learners'], 'percent' ) && 'attempted' === $list['learners'][0]['level'], 'with rounded percentages and levels' );
	ok( 5 === $list['below'] && 5 === $list['assessed'] && 6 === $list['row']['learners'] && $fx['k2'] === $list['skill']['id'], 'with counts, row and skill' );
	ok( 'L5 ' . $tag === $list['learners'][0]['name'] && '/students/' . $l[5] . '/report' === $list['learners'][0]['route'], 'display names and report links for administrators' );
	ok( array( 'id', 'name', 'percent', 'level', 'route' ) === array_keys( $list['learners'][0] ), 'learner rows carry no other personal data' );
	ok( false === strpos( wp_json_encode( $list ), '@example.test' ), 'no e-mails in the learner list' );
	$r = call( $fx['sa'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $sa, 'row' => $a1, 'skill' => $fx['k2'] ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( 'hit' === $r->get_headers()['X-OhMyLMS-Cache'] && null === $r->get_data()['learners'][0]['route'], 'cached list, no report links for a school administrator' );
	$k1 = call( $fx['teacher'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $a1, 'row' => $a1, 'skill' => $fx['k1'] ) )->get_data(); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( array( $l[5] ) === array_column( $k1['learners'], 'id' ) && 1 === $k1['below'], 'K1: only L5 (40 %) is below Familiar; not-started L6 is not listed' );
	is_error( call( $fx['teacher'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $a1, 'row' => $a2, 'skill' => $fx['k1'] ) ), 404, 'ohmylms_dashboard_row_not_found', 'a teacher asking for a row outside their class' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $sa, 'row' => 'class:' . $fx['class_b1'], 'skill' => $fx['k2'] ) ), 404, 'ohmylms_dashboard_row_not_found', 'a class of another school' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $sa, 'row' => $a1, 'skill' => 999999999 ) ), 404, 'ohmylms_dashboard_skill_not_found', 'an unknown skill' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $sa, 'row' => 'school:1', 'skill' => $fx['k2'] ) ), 400, 'rest_invalid_param', 'a malformed row' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	is_error( call( $fx['teacher'], 'GET', '/dashboard/widgets/mastery-heatmap/learners', array( 'scope' => $a2, 'row' => $a2, 'skill' => $fx['k1'] ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'a teacher asking for another class scope' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	// ---- Recommendations ----
	$r = call( $fx['admin'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $sa ) );
	ok( 200 === $r->get_status(), 'review sets answers 200: ' . wp_json_encode( $r->get_data() ) );
	$rec = $r->get_data()['data'];
	ok( array( ReviewSetsService::key( $fx['class_a1'], $fx['k2'] ), ReviewSetsService::key( $fx['class_a1'], $fx['k4'] ) ) === array_column( $rec['groups'], 'key' ), 'groups of 3+ below Familiar, largest first (K2 5, K4 3; K3 has 2; B1 is another school): ' . wp_json_encode( array_column( $rec['groups'], 'key' ) ) );
	ok( 5 === $rec['groups'][0]['learners'] && 6 === $rec['groups'][0]['class']['learners'] && 3 === $rec['min_group'], 'group sizes and class size' );
	ok(
		array(
			'available' => 5,
			'needed'    => 3,
			'draw'      => 5,
			'enough'    => true,
		) === $rec['groups'][0]['questions'],
		'K2 has enough questions (5 of 3 needed, draws 5)'
	);
	ok( 0 === $rec['groups'][1]['questions']['available'] && false === $rec['groups'][1]['questions']['enough'], 'K4 has not enough questions' );
	ok( true === $rec['can_assign'] && null === $rec['groups'][0]['review_set'], 'administrators may assign; nothing assigned yet' );
	ok( false === strpos( wp_json_encode( $rec ), $tag . ' L' ), 'recommendations carry no learner names' );
	ok( true === call( $fx['sa'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $sa ) )->get_data()['data']['can_assign'], 'a school administrator without authoring rights may assign drafts for their classes' );
	ok( true === call( $fx['teacher'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $a1 ) )->get_data()['data']['can_assign'], 'a teacher without authoring rights may assign drafts for their class' );
	$course_rec = call( $fx['admin'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => 'school:' . $fx['school_c'] ) )->get_data()['data'];
	ok( 'course' === $course_rec['row_type'] && array() === $course_rec['groups'], 'no class groups in a scope without classes' );

	// ---- Assign review set: permissions ----
	$assign = array(
		'scope'    => $a1,
		'class_id' => $fx['class_a1'],
		'skill_id' => $fx['k2'],
	);
	is_error( call( $fx['author'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'scope' => $a2 ) + $assign ), 403, 'ohmylms_dashboard_forbidden_scope', 'an authoring teacher on a class they do not teach' );
	is_error( call( $fx['author'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'class_id' => $fx['class_a2'] ) + $assign ), 403, 'ohmylms_dashboard_forbidden_scope', 'an authoring teacher naming a class they do not teach' );
	is_error( call( $fx['teacher'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'scope' => $a2 ) + $assign ), 403, 'ohmylms_dashboard_forbidden_scope', 'a teacher on a class scope they do not teach' );
	is_error( call( $fx['teacher'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'class_id' => $fx['class_a2'] ) + $assign ), 403, 'ohmylms_dashboard_forbidden_scope', 'a teacher naming a class of their school they do not teach' );
	is_error( call( $fx['teacher'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'class_id' => $fx['class_b1'] ) + $assign ), 403, 'ohmylms_dashboard_forbidden_scope', 'a teacher naming a class of another school' );
	is_error( call( $fx['sa'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'scope' => $sa, 'class_id' => $fx['class_b1'] ) + $assign ), 403, 'ohmylms_dashboard_forbidden_scope', 'a school administrator naming a class of another school' );
	is_error( call( $fx['sa'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'scope' => 'school:' . $fx['school_b'] ) + $assign ), 403, 'ohmylms_dashboard_forbidden_scope', 'a school administrator on another school' );
	is_error( call( $fx['admin'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'scope' => $sa, 'class_id' => $fx['class_b1'] ) + $assign ), 404, 'ohmylms_dashboard_row_not_found', 'an administrator naming a class outside the scope they look at' );
	is_error( call( 0, 'POST', '/dashboard/widgets/review-sets/assign', $assign ), 401, 'ohmylms_dashboard_cannot_view', 'anonymous assign' );
	is_error( call( $l[1], 'POST', '/dashboard/widgets/review-sets/assign', $assign ), 403, 'ohmylms_dashboard_cannot_view', 'a learner assigning' );
	ok( 0 === review_sets( $fx['class_a1'], $fx['k2'] ) && 0 === review_sets( $fx['class_b1'], $fx['k2'] ) && 0 === review_sets( $fx['class_a2'], $fx['k2'] ), 'refused requests created nothing' );

	// ---- Bank shortages and small groups ----
	$r = call( $fx['teacher'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'skill_id' => $fx['k4'] ) + $assign );
	is_error( $r, 409, 'ohmylms_dashboard_review_set_not_enough_questions', 'a skill without questions' );
	ok( 0 === $r->get_data()['data']['available'] && 3 === $r->get_data()['data']['needed'], 'the shortage says how many questions exist and are needed' );
	ok( 0 === review_sets( $fx['class_a1'], $fx['k4'] ) && 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUIZ_CPT, $wpdb->esc_like( $tag . ' Orbits' ) . '%' ) ), 'a shortage leaves no half-made quiz' );
	is_error( call( $fx['author'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'skill_id' => $fx['k3'] ) + $assign ), 409, 'ohmylms_dashboard_review_set_no_group', 'a group smaller than 3' );

	ok( ! user_can( $fx['teacher'], 'edit_posts' ), 'a refused teacher request leaves no authoring rights behind' );

	// ---- Assign: draft only, linked, idempotent ----
	$r = call( $fx['teacher'], 'POST', '/dashboard/widgets/review-sets/assign', $assign );
	ok( 201 === $r->get_status() && true === $r->get_data()['created'], 'a teacher without authoring rights creates the review set for their class: ' . wp_json_encode( $r->get_data() ) );
	$set  = $r->get_data()['review_set'];
	$quiz = (int) $set['id'];
	ok( 'draft' === $set['status'] && 'draft' === get_post_status( $quiz ) && OHMYLMS_QUIZ_CPT === get_post_type( $quiz ), 'the review set is a draft quiz' );
	ok( null === $set['route'] && 5 === $set['questions'], 'it draws 5 questions; no editor link for a teacher' );
	ok( $tag . ' Cells review set (' . $tag . ' Class A1)' === $set['title'], 'titled after the skill and class' );
	$pools = Pools::rules( $quiz );
	ok( 1 === count( $pools ) && $fx['k2'] === $pools[0]['term_id'] && 5 === $pools[0]['count'], 'one random pool by skill: ' . wp_json_encode( $pools ) );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE quiz_id = %d', \OhMyLMS\Assessment\Schema::table( 'quiz_revisions' ), $quiz ) ), 'no revision was published' );
	$meta = get_post_meta( $quiz, ReviewSetsService::META, true );
	ok( $fx['class_a1'] === $meta['class_id'] && $fx['k2'] === $meta['skill_id'] && array( $l[5], $l[2], $l[1], $l[3], $l[4] ) === $meta['learners'] && $fx['teacher'] === $meta['created_by'], 'the quiz remembers the class, skill and group' );
	ok( (int) get_post_field( 'post_author', $quiz ) === $fx['teacher'], 'created as the teacher' );
	ok( ! user_can( $fx['teacher'], 'edit_posts' ) && ! user_can( $fx['teacher'], 'publish_posts' ) && ! user_can( $fx['teacher'], 'edit_post', $quiz ) && ! user_can( $fx['teacher'], 'publish_post', $quiz ), 'the teacher keeps no authoring or publishing rights, not even on their draft' );
	is_error( call( $fx['teacher'], 'PUT', '/quiz/' . $quiz, array( 'status' => 'publish' ) ), 403, 'ohmylms_forbidden', 'the teacher publishing the draft through the quiz routes' );
	ok( 'draft' === get_post_status( $quiz ), 'the draft stays a draft' );
	$listed = call( $fx['admin'], 'GET', '/quizzes', array( 'status' => 'draft', 'include' => array( $quiz ) ) );
	ok( 200 === $listed->get_status() && in_array( $quiz, array_map( 'intval', array_column( $listed->get_data(), 'id' ) ), true ), 'administrators find the draft in the Content Hub quiz list: ' . wp_json_encode( $listed->get_data() ) );
	$again = call( $fx['teacher'], 'POST', '/dashboard/widgets/review-sets/assign', $assign );
	ok( 200 === $again->get_status() && false === $again->get_data()['created'] && $quiz === $again->get_data()['review_set']['id'], 'a second click returns the same draft' );
	$author_again = call( $fx['author'], 'POST', '/dashboard/widgets/review-sets/assign', $assign );
	ok( 200 === $author_again->get_status() && $quiz === $author_again->get_data()['review_set']['id'] && null === $author_again->get_data()['review_set']['route'], 'an authoring teacher gets the same draft, without a link to a page they cannot open' );
	$admin_again = call( $fx['admin'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'scope' => $sa ) + $assign );
	ok( 200 === $admin_again->get_status() && $quiz === $admin_again->get_data()['review_set']['id'], 'another user in another scope gets the same draft' );
	ok( '/quizzes/' . $quiz === $admin_again->get_data()['review_set']['route'], 'administrators get the quiz editor link' );
	ok( 1 === review_sets( $fx['class_a1'], $fx['k2'] ), 'exactly one review set exists' );

	// A request that arrives while another holds the group's lock waits, then reports it busy.
	$other = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, (int) ini_get( 'mysqli.default_port' ) ); // phpcs:ignore WordPress.DB.RestrictedClasses.mysql__mysqli -- A second connection is the only way to hold the advisory lock as another request would.
	$name  = 'ohmylms-review-set-' . md5( $wpdb->prefix . ReviewSetsService::key( $fx['class_a1'], $fx['k2'] ) );
	$other->query( "SELECT GET_LOCK('" . $other->real_escape_string( $name ) . "', 0)" );
	$wait = microtime( true );
	is_error( call( $fx['author'], 'POST', '/dashboard/widgets/review-sets/assign', $assign ), 409, 'ohmylms_dashboard_review_set_busy', 'a concurrent request' );
	ok( microtime( true ) - $wait >= ReviewSetsService::LOCK_WAIT - 0.5, 'the concurrent request waited for the lock' );
	$other->query( "SELECT RELEASE_LOCK('" . $other->real_escape_string( $name ) . "')" );
	$other->close();
	ok( 1 === review_sets( $fx['class_a1'], $fx['k2'] ), 'still exactly one review set' );

	// ---- The widget shows the review set per user ----
	$rec = call( $fx['author'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $a1 ) )->get_data()['data'];
	ok( $quiz === $rec['groups'][0]['review_set']['id'] && null === $rec['groups'][0]['review_set']['route'] && true === $rec['can_assign'], 'the authoring teacher sees the draft, without a link to a page they cannot open' );
	$rec = call( $fx['admin'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $sa ) )->get_data()['data'];
	ok( '/quizzes/' . $quiz === $rec['groups'][0]['review_set']['route'], 'an administrator sees the draft with a link' );
	$rec = call( $fx['sa'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $sa ) )->get_data()['data'];
	ok( $quiz === $rec['groups'][0]['review_set']['id'] && null === $rec['groups'][0]['review_set']['route'], 'a school administrator sees it without a link they cannot open' );
	$rec = call( $fx['teacher'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $a1 ) )->get_data()['data'];
	ok( $quiz === $rec['groups'][0]['review_set']['id'] && null === $rec['groups'][0]['review_set']['route'], 'the teacher who created it sees it without an editor link' );
	wp_trash_post( $quiz );
	ok( null === call( $fx['admin'], 'GET', '/dashboard/widgets/review-sets', array( 'scope' => $sa ) )->get_data()['data']['groups'][0]['review_set'], 'a trashed review set no longer counts' );

	// ---- A school administrator creates a draft for a class of their school ----
	$r = call( $fx['sa'], 'POST', '/dashboard/widgets/review-sets/assign', array( 'scope' => $sa ) + $assign );
	ok( 201 === $r->get_status() && true === $r->get_data()['created'] && null === $r->get_data()['review_set']['route'], 'a school administrator creates the draft, without an editor link: ' . wp_json_encode( $r->get_data() ) );
	$sa_quiz = (int) $r->get_data()['review_set']['id'];
	ok( 'draft' === get_post_status( $sa_quiz ) && (int) get_post_field( 'post_author', $sa_quiz ) === $fx['sa'] && ! user_can( $fx['sa'], 'edit_post', $sa_quiz ), 'a draft by the school administrator, who cannot edit or publish it' );
	wp_trash_post( $sa_quiz );

	// ---- Cache invalidation and ttl 0 ----
	Cache::flush();
	$zero = static function () {
		return 0;
	};
	add_filter( 'ohmylms_dashboard_cache_ttl', $zero );
	ok( 'miss' === call( $fx['admin'], 'GET', '/dashboard/widgets/mastery-heatmap', array( 'scope' => $sa ) )->get_headers()['X-OhMyLMS-Cache'], 'ttl 0 never hits' );
	remove_filter( 'ohmylms_dashboard_cache_ttl', $zero );

	// ---- Timings ----
	Cache::flush();
	$times = array();
	foreach ( array( 'mastery-heatmap', 'review-sets' ) as $widget ) {
		foreach ( array( 'cold', 'warm' ) as $kind ) {
			$at       = microtime( true );
			$response = call( $fx['admin'], 'GET', '/dashboard/widgets/' . $widget, array( 'scope' => $sa ) );

			$times[ $widget . ' ' . $kind ] = round( ( microtime( true ) - $at ) * 1000, 1 );
			ok( 200 === $response->get_status(), $widget . ' ' . $kind . ' read' );
		}
	}
	echo 'Timings (ms, in-process): ' . wp_json_encode( $times ) . "\n";
} catch ( Throwable $error ) {
	echo $error->getMessage() . "\n" . $error->getTraceAsString() . "\n";
	masteryfx_delete( $tag );
	exit( 1 );
}

masteryfx_delete( $tag );
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE name LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title LIKE %s", $wpdb->esc_like( $tag ) . '%' ) );
if ( $left ) {
	echo "FAILED: {$left} fixture rows were left behind\n";
	exit( 1 );
}
printf( "OK %d checks in %.1f s\n", $checks, microtime( true ) - $started );
