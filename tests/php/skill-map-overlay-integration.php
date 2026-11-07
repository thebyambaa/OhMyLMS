<?php
/**
 * Learner overlays on the skill map (`GET /skill-map/overlay` and `/skill-map/overlay/targets`): who may
 * call them (administrators, curriculum administrators, school administrators and teachers; not learners
 * or visitors), staff limited to the learners, classes and schools of their scopes, the learner and group
 * payloads (heatmap levels, distributions, counts, links) and the target search (names only, bounds).
 *
 * Runs against a WordPress install (the Local site by default) with real tables. Fixtures are tagged
 * (tests/php/support/mastery-fixture.php plus a tagged subject library) and removed in `finally`.
 *
 * Usage (set OHMYLMS_SKILL_OVERLAY_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/skill-map-overlay-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SKILL_OVERLAY_TEST' ) ) {
	exit( "Set OHMYLMS_SKILL_OVERLAY_TEST=1 to run the skill map overlay tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/mastery-fixture.php';

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Dashboard\ScopeResolver;

global $wpdb;

$checks  = 0;
$started = microtime( true );
$tag     = 'smov' . strtolower( wp_generate_password( 6, false, false ) );
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
 * GET a route below /ohmylms/v1 as a user; the data as a client decodes it.
 *
 * @param int    $user   User (0 = anonymous).
 * @param string $path   Path.
 * @param array  $params Query parameters.
 * @return array{0:int,1:array,2:array} Status, data, headers.
 */
function get_as( $user, $path, array $params = array() ) {
	wp_set_current_user( $user );
	ScopeResolver::reset();
	$request = new WP_REST_Request( 'GET', '/ohmylms/v1' . $path );
	$request->set_query_params( $params );
	$response = rest_do_request( $request );
	return array( $response->get_status(), json_decode( wp_json_encode( $response->get_data() ), true ), $response->get_headers() );
}

/**
 * Assert an error answer.
 *
 * @param array  $answer  From get_as().
 * @param int    $status  Expected status.
 * @param string $code    Expected code.
 * @param string $message Failure message.
 */
function is_error( array $answer, $status, $code, $message ) {
	ok( $status === $answer[0], $message . ' answers ' . $status . ' (got ' . $answer[0] . ': ' . wp_json_encode( $answer[1] ) . ')' );
	ok( ( $answer[1]['code'] ?? '' ) === $code, $message . ' code ' . $code . ' (got ' . ( $answer[1]['code'] ?? '?' ) . ')' );
}

/**
 * Overlay row of a skill, or null when the skill is not started.
 *
 * @param array $data  Overlay.
 * @param int   $skill Skill.
 * @return array|null
 */
function skill_row( array $data, $skill ) {
	foreach ( $data['skills'] as $row ) {
		if ( $row['id'] === $skill ) {
			return $row;
		}
	}
	return null;
}

$fx = null;
try {
	$fx = masteryfx_create( $tag, false );
	$l  = $fx['learners'];

	// A subject library of four shared skills, with skill states on three of them.
	wp_set_current_user( $fx['admin'] );
	$subject = 'Mathematics ' . $tag;
	$shared  = static function ( $name, $domain, $from ) use ( $subject, $tag ) {
		$skill = Comparison::save_shared(
			0,
			array(
				'name'       => $tag . ' ' . $name,
				'subject'    => $subject,
				'topic'      => 'Overlay',
				'domain'     => $domain,
				'grade_from' => $from,
				'grade_to'   => $from,
			)
		);
		ok( ! is_wp_error( $skill ), 'shared skill created: ' . $name . ( is_wp_error( $skill ) ? ' ' . $skill->get_error_message() : '' ) );
		return (int) $skill['id'];
	};
	$s1      = $shared( 'Count to ten', 'number', 1 );
	$s2      = $shared( 'Add within twenty', 'number', 2 );
	$s3      = $shared( 'Spot patterns', 'algebra', 1 );
	$s4      = $shared( 'Simple equations', 'algebra', 2 );
	foreach ( array(
		array( $s1, array( 1 => 100, 2 => 96, 3 => 85, 4 => 60, 5 => 40, 7 => 95, 11 => 10 ) ),
		array( $s2, array( 1 => 30, 2 => 49.4, 3 => null ) ),
		array( $s3, array( 1 => 79.6 ) ),
	) as $states ) {
		foreach ( $states[1] as $n => $mastery ) {
			masteryfx_state( $l[ $n ], $states[0], $mastery );
		}
	}
	$a1 = 'class:' . $fx['class_a1'];
	$a2 = 'class:' . $fx['class_a2'];
	$sa = 'school:' . $fx['school_a'];

	// ---- Registration and arguments ----
	$routes = rest_get_server()->get_routes();
	ok( isset( $routes['/ohmylms/v1/skill-map/overlay'], $routes['/ohmylms/v1/skill-map/overlay/targets'] ), 'both routes are registered' );
	is_error( get_as( $fx['admin'], '/skill-map/overlay', array( 'target' => 'learner:' . $l[1] ) ), 400, 'rest_missing_callback_param', 'a request without subject' );
	foreach ( array( 'all', 'learner:0', 'course:5', 'learner:abc', 'school:' ) as $bad ) {
		is_error(
			get_as(
				$fx['admin'],
				'/skill-map/overlay',
				array(
					'subject' => $subject,
					'target'  => $bad,
				)
			),
			400,
			'rest_invalid_param',
			'target "' . $bad . '"'
		);
	}
	is_error( get_as( $fx['admin'], '/skill-map/overlay/targets', array( 'search' => str_repeat( 'x', 101 ) ) ), 400, 'rest_invalid_param', 'a search over 100 characters' );

	// ---- Who may call ----
	$learner_one = array(
		'subject' => $subject,
		'target'  => 'learner:' . $l[1],
	);
	is_error( get_as( 0, '/skill-map/overlay', $learner_one ), 401, 'ohmylms_skill_overlay_cannot_view', 'anonymous overlay' );
	is_error( get_as( 0, '/skill-map/overlay/targets' ), 401, 'ohmylms_skill_overlay_cannot_view', 'anonymous targets' );
	is_error( get_as( $l[1], '/skill-map/overlay', $learner_one ), 403, 'ohmylms_skill_overlay_cannot_view', 'a learner reading their own overlay here' );
	is_error( get_as( $l[2], '/skill-map/overlay/targets' ), 403, 'ohmylms_skill_overlay_cannot_view', 'a learner searching targets' );

	// ---- Learner payload (administrator) ----
	$answer = get_as( $fx['admin'], '/skill-map/overlay', $learner_one );
	ok( 200 === $answer[0], 'administrator overlay answers 200: ' . wp_json_encode( $answer[1] ) );
	ok( 'private, no-store' === $answer[2]['Cache-Control'], 'overlays are not stored by proxies' );
	$o = $answer[1];
	ok( $subject === $o['subject'] && 4 === $o['total'], 'subject and its four skills' );
	ok(
		array(
			'key'      => 'learner:' . $l[1],
			'type'     => 'learner',
			'id'       => $l[1],
			'label'    => 'L1 ' . $tag,
			'school'   => '',
			'learners' => 1,
			'counted'  => 1,
		) === $o['target'],
		'learner target: ' . wp_json_encode( $o['target'] )
	);
	$r1 = skill_row( $o, $s1 );
	ok( $r1 && 100 === $r1['percent'] && 'mastered' === $r1['level'] && 1 === $r1['assessed'] && 1 === $r1['distribution']['mastered'], 'S1 100 % Mastered: ' . wp_json_encode( $r1 ) );
	ok( 'attempted' === skill_row( $o, $s2 )['level'] && 30 === skill_row( $o, $s2 )['percent'], 'S2 30 % Attempted' );
	ok( 'proficient' === skill_row( $o, $s3 )['level'] && 80 === skill_row( $o, $s3 )['percent'], 'S3 79.6 rounds to 80 % Proficient' );
	ok( null === skill_row( $o, $s4 ) && 3 === count( $o['skills'] ), 'S4 has no evidence and is not listed' );
	ok(
		array(
			'mastered'    => 1,
			'proficient'  => 1,
			'familiar'    => 0,
			'attempted'   => 1,
			'not-started' => 1,
		) === $o['counts'],
		'counts per level over the map, highest first: ' . wp_json_encode( $o['counts'] )
	);
	ok( array( 'mastered', 'proficient', 'familiar', 'attempted', 'not-started' ) === array_column( $o['levels'], 'id' ) && 95 === $o['levels'][0]['min'], 'the heatmap legend' );
	ok( '/students/' . $l[1] . '/report' === $o['links']['report'] && null === $o['links']['gradebook'], 'administrators get the student report link' );
	ok( '/content-hub/gradebook/student?scope=all&learner=' . $l[1] === $o['links']['transcript'], 'and the transcript in the all scope: ' . $o['links']['transcript'] );
	$json = wp_json_encode( $o );
	ok( false === strpos( $json, '@example.test' ) && false === strpos( $json, $tag . '_l1' ), 'no e-mails or logins' );
	$l3 = get_as(
		$fx['admin'],
		'/skill-map/overlay',
		array(
			'subject' => $subject,
			'target'  => 'learner:' . $l[3],
		)
	)[1];
	ok( null === skill_row( $l3, $s2 ) && 'proficient' === skill_row( $l3, $s1 )['level'], 'a state row without evidence counts as not started' );
	$other = get_as(
		$fx['admin'],
		'/skill-map/overlay',
		array(
			'subject' => 'No such subject ' . $tag,
			'target'  => 'learner:' . $l[1],
		)
	);
	ok( 200 === $other[0] && 0 === $other[1]['total'] && array() === $other[1]['skills'] && 0 === $other[1]['counts']['not-started'], 'an unknown subject has no skills' );
	is_error(
		get_as(
			$fx['admin'],
			'/skill-map/overlay',
			array(
				'subject' => $subject,
				'target'  => 'learner:999999999',
			)
		),
		404,
		'ohmylms_skill_overlay_not_found',
		'an administrator asking for a missing learner'
	);

	// ---- Group payloads ----
	$class = get_as(
		$fx['admin'],
		'/skill-map/overlay',
		array(
			'subject' => $subject,
			'target'  => $a1,
		)
	);
	ok( 200 === $class[0], 'class overlay answers 200: ' . wp_json_encode( $class[1] ) );
	$c = $class[1];
	ok( 'class' === $c['target']['type'] && $fx['class_a1'] === $c['target']['id'] && $tag . ' Class A1' === $c['target']['label'] && $tag . ' Alpha School' === $c['target']['school'], 'class target with its school' );
	ok( 6 === $c['target']['learners'] && 6 === $c['target']['counted'], 'six learners, all counted' );
	$g1 = skill_row( $c, $s1 );
	ok( $g1 && 64 === $g1['percent'] && 'familiar' === $g1['level'] && 5 === $g1['assessed'], 'S1: (100+96+85+60+40)/6 = 64 % Familiar, as on the heatmap: ' . wp_json_encode( $g1 ) );
	ok(
		array(
			'mastered'    => 2,
			'proficient'  => 1,
			'familiar'    => 1,
			'attempted'   => 1,
			'not-started' => 1,
		) === $g1['distribution'],
		'S1 learners per level: ' . wp_json_encode( $g1['distribution'] )
	);
	$g2 = skill_row( $c, $s2 );
	ok( $g2 && 13 === $g2['percent'] && 'attempted' === $g2['level'] && 2 === $g2['assessed'] && 2 === $g2['distribution']['attempted'] && 4 === $g2['distribution']['not-started'], 'S2: 49.4 rounds below Familiar; the row without evidence is not started' );
	ok( 1 === skill_row( $c, $s3 )['distribution']['proficient'], 'S3: one learner Proficient' );
	ok(
		array(
			'mastered'    => 0,
			'proficient'  => 0,
			'familiar'    => 1,
			'attempted'   => 2,
			'not-started' => 1,
		) === $c['counts'],
		'class counts over the map'
	);
	ok( '/content-hub/gradebook/mastery?scope=class%3A' . $fx['class_a1'] === $c['links']['gradebook'] && null === $c['links']['report'] && null === $c['links']['transcript'], 'class link to the gradebook mastery view: ' . wp_json_encode( $c['links'] ) );
	$school = get_as(
		$fx['admin'],
		'/skill-map/overlay',
		array(
			'subject' => $subject,
			'target'  => $sa,
		)
	)[1];
	$h1     = skill_row( $school, $s1 );
	ok( 'school' === $school['target']['type'] && 10 === $school['target']['learners'] && '' === $school['target']['school'], 'school target: ten learners' );
	ok( 48 === $h1['percent'] && 'attempted' === $h1['level'] && 3 === $h1['distribution']['mastered'] && 4 === $h1['distribution']['not-started'], 'school S1 excludes school B (L11): 476/10 = 48 %' );
	is_error(
		get_as(
			$fx['admin'],
			'/skill-map/overlay',
			array(
				'subject' => $subject,
				'target'  => 'class:999999999',
			)
		),
		404,
		'ohmylms_dashboard_scope_not_found',
		'an administrator asking for a missing class'
	);

	// ---- School administrator: their school only ----
	$sa_one = get_as( $fx['sa'], '/skill-map/overlay', $learner_one );
	ok( 200 === $sa_one[0] && 'mastered' === skill_row( $sa_one[1], $s1 )['level'], 'a school administrator sees a learner of their school' );
	ok( null === $sa_one[1]['links']['report'] && '/content-hub/gradebook/student?scope=' . rawurlencode( $sa ) . '&learner=' . $l[1] === $sa_one[1]['links']['transcript'], 'no report link; transcript in their school scope: ' . wp_json_encode( $sa_one[1]['links'] ) );
	ok(
		200 === get_as(
			$fx['sa'],
			'/skill-map/overlay',
			array(
				'subject' => $subject,
				'target'  => $a2,
			)
		)[0],
		'a school administrator sees another class of their school'
	);
	foreach ( array( $l[11], 999999999 ) as $outside ) {
		is_error(
			get_as(
				$fx['sa'],
				'/skill-map/overlay',
				array(
					'subject' => $subject,
					'target'  => 'learner:' . $outside,
				)
			),
			403,
			'ohmylms_skill_overlay_forbidden',
			'a school administrator asking for learner ' . $outside . ' (another school, or missing: the same answer)'
		);
	}
	is_error(
		get_as(
			$fx['sa'],
			'/skill-map/overlay',
			array(
				'subject' => $subject,
				'target'  => 'school:' . $fx['school_b'],
			)
		),
		403,
		'ohmylms_dashboard_forbidden_scope',
		'a school administrator asking for another school'
	);

	// ---- Teacher: their class only ----
	$t1 = get_as( $fx['teacher'], '/skill-map/overlay', $learner_one );
	ok( 200 === $t1[0] && '/content-hub/gradebook/student?scope=' . rawurlencode( $a1 ) . '&learner=' . $l[1] === $t1[1]['links']['transcript'], 'a teacher sees a learner of their class, with the transcript in that class' );
	$tc = get_as(
		$fx['teacher'],
		'/skill-map/overlay',
		array(
			'subject' => $subject,
			'target'  => $a1,
		)
	);
	ok( 200 === $tc[0] && $tc[1]['skills'] === $c['skills'], 'a teacher gets the same class figures' );
	is_error(
		get_as(
			$fx['teacher'],
			'/skill-map/overlay',
			array(
				'subject' => $subject,
				'target'  => 'learner:' . $l[7],
			)
		),
		403,
		'ohmylms_skill_overlay_forbidden',
		'a teacher asking for a learner of another class of their school'
	);
	foreach ( array( $a2, $sa ) as $scope ) {
		is_error(
			get_as(
				$fx['teacher'],
				'/skill-map/overlay',
				array(
					'subject' => $subject,
					'target'  => $scope,
				)
			),
			403,
			'ohmylms_dashboard_forbidden_scope',
			'a teacher asking for ' . $scope
		);
	}

	// ---- Curriculum administrator without a scope ----
	$curriculum = static function () {
		return true;
	};
	add_filter( 'ohmylms_can_manage_curriculum', $curriculum );
	$cur = get_as(
		$l[16],
		'/skill-map/overlay',
		array(
			'subject' => $subject,
			'target'  => 'learner:' . $l[11],
		)
	);
	ok( 200 === $cur[0] && 'attempted' === skill_row( $cur[1], $s1 )['level'], 'a curriculum administrator sees any learner: ' . wp_json_encode( $cur[1] ) );
	ok( null === $cur[1]['links']['report'] && null === $cur[1]['links']['transcript'], 'without report or gradebook links they cannot open' );
	$cur_targets = get_as( $l[16], '/skill-map/overlay/targets', array( 'search' => 'L11 ' . $tag ) );
	ok( 200 === $cur_targets[0] && array() === $cur_targets[1]['groups'] && array( $l[11] ) === array_column( $cur_targets[1]['learners'], 'id' ), 'and finds any learner, with no groups' );
	remove_filter( 'ohmylms_can_manage_curriculum', $curriculum );
	is_error( get_as( $l[16], '/skill-map/overlay', $learner_one ), 403, 'ohmylms_skill_overlay_cannot_view', 'the same user without the filter' );

	// ---- Targets ----
	$t = get_as( $fx['teacher'], '/skill-map/overlay/targets' );
	ok( 200 === $t[0] && 'private, no-store' === $t[2]['Cache-Control'], 'targets answer 200, not stored' );
	ok( array( $a1 ) === array_column( $t[1]['groups'], 'key' ) && $tag . ' Alpha School' === $t[1]['groups'][0]['school'] && array() === $t[1]['learners'], 'a teacher: their class, no learners without a search' );
	ok( 20 === $t[1]['limit'] && 2 === $t[1]['min_search'] && 1 === $t[1]['groups_total'], 'limits are stated' );
	ok( array() === get_as( $fx['teacher'], '/skill-map/overlay/targets', array( 'search' => 'L' ) )[1]['learners'], 'one character does not search learners' );
	$found = get_as( $fx['teacher'], '/skill-map/overlay/targets', array( 'search' => $tag ) )[1];
	ok( array( $l[1], $l[2], $l[3], $l[4], $l[5], $l[6] ) === array_column( $found['learners'], 'id' ), 'a teacher finds only the learners of their class, by name: ' . wp_json_encode( $found['learners'] ) );
	ok( array( 'id', 'name' ) === array_keys( $found['learners'][0] ) && 'L1 ' . $tag === $found['learners'][0]['name'], 'learners carry an id and display name only' );
	ok( false === strpos( wp_json_encode( $found ), '@example.test' ), 'no e-mails in the search' );
	$sa_found = get_as( $fx['sa'], '/skill-map/overlay/targets', array( 'search' => $tag ) )[1];
	ok( 10 === count( $sa_found['learners'] ) && ! in_array( $l[11], array_column( $sa_found['learners'], 'id' ), true ), 'a school administrator finds the ten learners of their school' );
	ok( array( $sa, $a1, $a2 ) === array_column( $sa_found['groups'], 'key' ), 'and their school and its classes' );
	ok( array( $a1, $a2 ) === array_column( get_as( $fx['sa'], '/skill-map/overlay/targets', array( 'search' => 'class a' ) )[1]['groups'], 'key' ), 'groups match case-insensitively' );
	$admin_found = get_as( $fx['admin'], '/skill-map/overlay/targets', array( 'search' => 'L11 ' . $tag ) )[1];
	ok( array( $l[11] ) === array_column( $admin_found['learners'], 'id' ), 'an administrator finds a learner of any school' );
	$admin_all = get_as( $fx['admin'], '/skill-map/overlay/targets', array( 'search' => $tag ) )[1];
	ok( 20 === count( $admin_all['learners'] ) && ! in_array( 'all', array_column( $admin_all['groups'], 'key' ), true ), 'an administrator search is bounded to 20 learners and never offers "all"' );
	ok( in_array( 'school:' . $fx['school_b'], array_column( $admin_all['groups'], 'key' ), true ), 'administrators see every school' );

	// ---- The read-only map page for school staff ----
	foreach ( array(
		'teacher'              => $fx['teacher'],
		'school administrator' => $fx['sa'],
		'administrator'        => $fx['admin'],
	) as $who => $user ) {
		$subjects = get_as( $user, '/syllabus-comparison/skill-map/subjects' );
		ok( 200 === $subjects[0] && in_array( $subject, array_column( $subjects[1], 'subject' ), true ), "a $who lists the map subjects" );
		$map = get_as( $user, '/syllabus-comparison/skill-map', array( 'subject' => $subject ) );
		ok( 200 === $map[0] && 4 === count( $map[1]['skills'] ), "a $who reads the map: " . wp_json_encode( $map[0] ) );
	}
	is_error( get_as( 0, '/syllabus-comparison/skill-map', array( 'subject' => $subject ) ), 401, 'ohmylms_skill_map_cannot_view', 'an anonymous map' );
	is_error( get_as( $l[1], '/syllabus-comparison/skill-map/subjects' ), 403, 'ohmylms_skill_map_cannot_view', 'a learner listing map subjects' );
	is_error( get_as( $l[2], '/syllabus-comparison/skill-map', array( 'subject' => $subject ) ), 403, 'ohmylms_skill_map_cannot_view', 'a learner reading the map' );
	$own = get_as(
		$fx['teacher'],
		'/syllabus-comparison/skill-map',
		array(
			'subject'    => $subject,
			'student_id' => $l[1],
		)
	);
	ok( 200 === $own[0] && isset( $own[1]['student'] ), 'a teacher lays a learner of their class over the map' );
	foreach ( array( $l[7], 999999999 ) as $outside ) {
		is_error(
			get_as(
				$fx['teacher'],
				'/syllabus-comparison/skill-map',
				array(
					'subject'    => $subject,
					'student_id' => $outside,
				)
			),
			403,
			'ohmylms_skill_overlay_forbidden',
			'a teacher laying learner ' . $outside . ' over the map (another class, or missing: the same answer)'
		);
	}
	is_error(
		get_as(
			$fx['admin'],
			'/syllabus-comparison/skill-map',
			array(
				'subject'    => $subject,
				'student_id' => 999999999,
			)
		),
		404,
		'ohmylms_skill_map_student',
		'an administrator laying a missing learner over the map'
	);
	wp_set_current_user( $fx['teacher'] );
	ScopeResolver::reset();
	$link = new WP_REST_Request( 'POST', '/ohmylms/v1/syllabus-comparison/skill-links' );
	$link->set_body_params(
		array(
			'skill_id' => $s2,
			'other_id' => $s1,
			'type'     => 'prerequisite',
		)
	);
	ok( 403 === rest_do_request( $link )->get_status(), 'a teacher cannot edit the links of the map' );
	ok( 403 === get_as( $fx['teacher'], '/syllabus-comparison/subject-profiles' )[0], 'and cannot open the other curriculum screens' );
	$tabs = \OhMyLMS\Admin\AdminNavigation::visible_tabs( 'curriculum' );
	ok( isset( $tabs['tools'] ) && array( 'skill-map' ) === array_keys( $tabs['tools']['sections'] ) && 'skill-map' === $tabs['tools']['default_section'], 'a teacher sees only the skill map in the Tools tab: ' . wp_json_encode( array_keys( $tabs ) ) );
	ok( \OhMyLMS\Dashboard\Access::CAPABILITY === $tabs['tools']['capability'] && ! isset( $tabs['workspace'] ), 'through the staff capability, with no other site tab' );
	$place = \OhMyLMS\Admin\AdminNavigation::current(
		array(
			'page' => 'ohmylms-curriculum',
			'tab'  => 'tools',
		)
	);
	ok( 'tools' === $place['tab'] && 'skill-map' === $place['section'], 'the Tools tab opens the skill map for a teacher' );
	ok( 'skill-map' === \OhMyLMS\Admin\AdminNavigation::current( array( 'page' => 'ohmylms-curriculum', 'tab' => 'tools', 'section' => 'bundles' ) )['section'], 'other tool sections fall back to the skill map' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	wp_set_current_user( $l[1] );
	ScopeResolver::reset();
	ok( array() === \OhMyLMS\Admin\AdminNavigation::visible_tabs( 'curriculum' ), 'a learner gets no Curriculum page tabs' );
	wp_set_current_user( $fx['admin'] );
	ScopeResolver::reset();
	$admin_tabs = \OhMyLMS\Admin\AdminNavigation::visible_tabs( 'curriculum' );
	ok( array( 'workspace', 'tools' ) === array_keys( $admin_tabs ) && 'manage_options' === $admin_tabs['workspace']['capability'] && in_array( 'skill-map', array_keys( $admin_tabs['tools']['sections'] ), true ), 'administrators keep the workspace and every tool section' );
} catch ( Throwable $error ) {
	echo $error->getMessage() . "\n" . $error->getTraceAsString() . "\n";
	masteryfx_delete( $tag );
	exit( 1 );
}

masteryfx_delete( $tag );
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_login LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->terms} WHERE name LIKE %s", $wpdb->esc_like( $tag ) . '%' ) );
if ( $left ) {
	echo "FAILED: {$left} fixture rows were left behind\n";
	exit( 1 );
}
printf( "OK %d checks in %.1f s\n", $checks, microtime( true ) - $started );
