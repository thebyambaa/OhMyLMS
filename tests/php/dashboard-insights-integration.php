<?php
/**
 * Dashboard widgets test: course health, insights, happening now and site health.
 *
 * Part one checks the pure rules on fixed figures (drop-off, median progress, every insight rule and its
 * thresholds, ordering, cron, money). Part two runs the REST endpoints against the Local site with the
 * tagged fixtures of tests/php/support/dashboard-fixture.php plus dashboard-insights-fixture.php (reviews,
 * a skill, paid orders), as administrator, school administrator and teacher, and removes everything in
 * `finally`. It never resets the database.
 *
 * Usage (set OHMYLMS_DASHBOARD_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/dashboard-insights-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_DASHBOARD_TEST' ) ) {
	exit( "Set OHMYLMS_DASHBOARD_TEST=1 to run the dashboard widget tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/dashboard-fixture.php';
require __DIR__ . '/support/dashboard-insights-fixture.php';

use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\ClassSkillGaps;
use OhMyLMS\Dashboard\CourseHealthReport;
use OhMyLMS\Dashboard\InsightRules;
use OhMyLMS\Dashboard\ScopeResolver;
use OhMyLMS\Dashboard\SiteHealthChecks;
use OhMyLMS\Dashboard\Widgets;
use OhMyLMS\Schools\Schema;

global $wpdb;

$checks = 0;
$tag    = 'dash' . strtolower( wp_generate_password( 6, false, false ) );
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
 * Whether a text contains every fragment.
 *
 * @param string   $text      Text.
 * @param string[] $fragments Fragments.
 * @return bool
 */
function has( $text, array $fragments ) {
	foreach ( $fragments as $fragment ) {
		if ( false === strpos( (string) $text, (string) $fragment ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Dispatch a GET request as a user.
 *
 * @param int    $user   User.
 * @param string $widget Widget id.
 * @param array  $params Query parameters.
 * @return WP_REST_Response
 */
function widget( $user, $widget, array $params = array() ) {
	wp_set_current_user( $user );
	ScopeResolver::reset();
	CourseHealthReport::reset();
	$request = new WP_REST_Request( 'GET', '/ohmylms/v1/dashboard/widgets/' . $widget );
	$request->set_query_params( $params );
	return rest_do_request( $request );
}

/**
 * Insights of a response by rule.
 *
 * @param WP_REST_Response $response Response.
 * @return array<string,array>
 */
function by_rule( $response ) {
	$list = array();
	foreach ( $response->get_data()['data']['insights'] as $insight ) {
		$list[ $insight['rule'] ] = $insight;
	}
	return $list;
}

/**
 * A course row of a course-health response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $id       Course.
 * @return array|null
 */
function course_row( $response, $id ) {
	foreach ( $response->get_data()['data']['courses'] as $course ) {
		if ( (int) $id === $course['id'] ) {
			return $course;
		}
	}
	return null;
}

$rules = InsightRules::DEFAULTS;
$usd   = array(
	'code'     => 'USD',
	'symbol'   => '$',
	'decimals' => 2,
	'position' => 'left',
);

// ---------------------------------------------------------------- Part one: pure rules.

// Drop-off: the item with the largest loss against the one before.
$stop = CourseHealthReport::drop_off(
	array( 11, 12, 13 ),
	array(
		11 => 5,
		12 => 2,
		13 => 1,
	),
	6
);
ok( 12 === $stop['content_id'] && 2 === $stop['position'] && 3 === $stop['lost'] && 50 === $stop['percent'], 'drop-off: 3 of 6 stop at the second item (50%)' );
$stop = CourseHealthReport::drop_off(
	array( 11, 12 ),
	array(
		11 => 2,
		12 => 0,
	),
	4
);
ok( 11 === $stop['content_id'] && 2 === $stop['lost'], 'drop-off: ties keep the earlier item' );
$stop = CourseHealthReport::drop_off(
	array( 11, 12 ),
	array(
		11 => 1,
		12 => 3,
	),
	3
);
ok( 11 === $stop['content_id'] && 2 === $stop['lost'], 'drop-off: later counts are capped by the item before' );
ok( null === CourseHealthReport::drop_off( array( 11 ), array( 11 => 4 ), 4 ), 'drop-off: none when nobody stops' );
ok( null === CourseHealthReport::drop_off( array(), array(), 4 ) && null === CourseHealthReport::drop_off( array( 1 ), array(), 0 ), 'drop-off: none without items or learners' );

// Median progress from a histogram.
ok(
	33 === CourseHealthReport::median_progress(
		array(
			-1 => 1,
			2  => 1,
			1  => 3,
			0  => 1,
		),
		3
	),
	'median of 0, 33, 33, 33, 67, 100 is 33'
);
ok(
	50 === CourseHealthReport::median_progress(
		array(
			0 => 1,
			2 => 1,
			4 => 1,
		),
		4
	),
	'median of three is the middle one'
);
ok(
	75 === CourseHealthReport::median_progress(
		array(
			2 => 1,
			4 => 1,
		),
		4
	),
	'median of two is their mean'
);
ok( 100 === CourseHealthReport::median_progress( array( 9 => 2 ), 4 ), 'progress is capped at the course size' );
ok( 0 === CourseHealthReport::median_progress( array( 3 => 2 ), 0 ), 'a course without items is at 0 for running enrolments' );
ok( null === CourseHealthReport::median_progress( array(), 5 ), 'no median without enrolments' );

// Revenue.
$r = InsightRules::revenue(
	4820,
	4463,
	30,
	$usd,
	array(
		'title' => 'Essay Workshop',
		'net'   => 1200,
	),
	$rules['revenue']
);
ok( 'success' === $r['severity'] && has( $r['text'], array( 'up 8%', '30 days', '$4,820.00', 'Essay Workshop brought in the most ($1,200.00)' ) ), 'revenue up 8% with the top course: ' . $r['text'] );
ok( '/earnings' === $r['action']['route'] && 'manage_options' === $r['action']['capability'], 'revenue links to earnings for administrators' );
$r = InsightRules::revenue( 96, 100, 30, $usd, null, $rules['revenue'] );
ok( 'info' === $r['severity'] && has( $r['text'], array( 'steady', '$96.00', '$100.00' ) ), 'a change under 5% is steady' );
$r = InsightRules::revenue( 85, 100, 7, $usd, null, $rules['revenue'] );
ok( 'warning' === $r['severity'] && has( $r['text'], array( 'down 15%', '7 days' ) ), 'down 15% is a warning' );
ok( 'error' === InsightRules::revenue( 80, 100, 7, $usd, null, $rules['revenue'] )['severity'], 'down 20% or more is an error' );
ok( 'success' === InsightRules::revenue( 105, 100, 7, $usd, null, $rules['revenue'] )['severity'], 'up exactly 5% counts as up' );
ok( null === InsightRules::revenue( 0, 0, 30, $usd, null, $rules['revenue'] ), 'no revenue, no insight' );
ok( has( InsightRules::revenue( 50, 0, 30, $usd, null, $rules['revenue'] )['text'], array( '$50.00', 'none' ) ), 'revenue after none before' );

// Completions.
$r = InsightRules::completions( 12, 10, 30, $rules['completions'] );
ok( 'success' === $r['severity'] && has( $r['text'], array( 'up 20%', '12', '10' ) ) && null === $r['action'], 'completions up 20%' );
ok( null === InsightRules::completions( 11, 10, 30, $rules['completions'] ), 'a 10% change is not reported' );
ok( null === InsightRules::completions( 2, 2, 30, $rules['completions'] ), 'fewer than 5 completions in both periods are not judged' );
ok( 'warning' === InsightRules::completions( 6, 10, 30, $rules['completions'] )['severity'], 'completions down 40% is a warning' );
ok( has( InsightRules::completions( 5, 0, 30, $rules['completions'] )['text'], array( '5 course completions', 'none' ) ), 'completions after none before' );

// Drop-off rule.
$course = static function ( $id, $enrolled, $percent ) {
	return array(
		'id'       => $id,
		'title'    => 'Course ' . $id,
		'enrolled' => $enrolled,
		'drop_off' => null === $percent ? null : array(
			'content_id' => $id * 10,
			'title'      => 'Lesson 6',
			'position'   => 6,
			'lost'       => (int) round( $enrolled * $percent / 100 ),
			'percent'    => $percent,
		),
	);
};
$r      = InsightRules::drop_off( array( $course( 1, 40, 35 ), $course( 2, 40, 58 ), $course( 3, 4, 90 ), $course( 4, 10, null ) ), $rules['drop_off'] );
ok( 'drop_off:2' === $r['key'] && 'error' === $r['severity'] && has( $r['text'], array( '“Lesson 6”', 'item 6', 'Course 2', '58%', '23 stop' ) ), 'the largest drop-off of a course with 5+ learners: ' . $r['text'] );
ok( '/reports/courses/2' === $r['action']['route'], 'it links to the course report' );
ok( 'warning' === InsightRules::drop_off( array( $course( 1, 40, 30 ) ), $rules['drop_off'] )['severity'], 'a 30% drop-off is a warning' );
ok( null === InsightRules::drop_off( array( $course( 1, 40, 29 ), $course( 3, 4, 90 ) ), $rules['drop_off'] ), 'below 30% or under 5 learners: none' );

// Class gap: pure comparison and rule.
$levels = array(
	7 => array(
		1 => array( 50 => 'attempted' ),
		2 => array( 50 => 'attempted' ),
		3 => array( 50 => 'familiar' ),
	),
	8 => array(
		4 => array( 50 => 'proficient' ),
		5 => array( 50 => 'proficient' ),
		6 => array( 50 => 'mastered' ),
		9 => array( 50 => 'not-started' ),
	),
);
$gap    = ClassSkillGaps::compare( $levels, $rules['class_gap'] );
ok( 7 === $gap['class_id'] && 50 === $gap['term_id'] && 33 === $gap['class_points'] && 87 === $gap['others_points'] && 54 === $gap['gap'] && 3 === $gap['class_learners'], 'class 7 is 54 pts behind on skill 50 (33 against 87; not started is not counted)' );
$few = $levels;
unset( $few[8][6] );
ok( null === ClassSkillGaps::compare( $few, $rules['class_gap'] ), 'fewer than 3 assessed learners on one side: no comparison' );
$close = array(
	7 => $levels[7],
	8 => array(
		4 => array( 50 => 'familiar' ),
		5 => array( 50 => 'familiar' ),
		6 => array( 50 => 'familiar' ),
	),
);
ok( 17 === ClassSkillGaps::compare( $close, $rules['class_gap'] )['gap'], 'a 17-point gap is reported (threshold 15)' );
ok( null === ClassSkillGaps::compare( $close, array_merge( $rules['class_gap'], array( 'min_points' => 18 ) ) ), 'below the threshold: none' );
$both       = $levels;
$both[8][1] = array( 50 => 'mastered' );
$shared     = ClassSkillGaps::compare( $both, $rules['class_gap'] );
ok( 3 === $shared['others_learners'], 'a learner in both classes never counts as "other"' );
$gap['class'] = 'Grade 8B';
$gap['skill'] = 'Linear equations';
$r            = InsightRules::class_gap( $gap, $rules['class_gap'] );
ok( 'class_gap:7' === $r['key'] && 'error' === $r['severity'] && has( $r['text'], array( 'Grade 8B', '54 pts behind', 'Linear equations', '33 against 87', '3 learners' ) ), 'class gap sentence: ' . $r['text'] );
ok( 'warning' === InsightRules::class_gap( array_merge( $gap, array( 'gap' => 18 ) ), $rules['class_gap'] )['severity'], 'an 18-point gap is a warning' );
ok( null === InsightRules::class_gap( null, $rules['class_gap'] ), 'no gap, no insight' );

// Missed question.
$q = array(
	'question_id' => 301,
	'title'       => 'Q7',
	'quiz_title'  => 'SAT Prep',
	'learners'    => 24,
	'answers'     => 24,
	'missed'      => 17,
);
$r = InsightRules::missed_question( $q, $rules['missed_question'] );
ok( 'info' === $r['severity'] && has( $r['text'], array( '“Q7”', 'SAT Prep', '71%', '24 learners' ) ) && 'edit_posts' === $r['action']['capability'], 'a question answered wrongly 71% of the time' );
ok( 'warning' === InsightRules::missed_question( array_merge( $q, array( 'missed' => 18 ) ), $rules['missed_question'] )['severity'], '75% or more is a warning' );
ok( null === InsightRules::missed_question( array_merge( $q, array( 'missed' => 14 ) ), $rules['missed_question'] ), 'below 60%: none' );
ok( null === InsightRules::missed_question( array_merge( $q, array( 'learners' => 4 ) ), $rules['missed_question'] ), 'under 5 learners: none' );

// Quiz takers and lessons.
$rows = array(
	array(
		'course_id'  => 1,
		'took'       => true,
		'enrolments' => 10,
		'lessons'    => 63,
	),
	array(
		'course_id'  => 1,
		'took'       => false,
		'enrolments' => 10,
		'lessons'    => 30,
	),
);
$r    = InsightRules::quiz_lessons( $rows, array( 1 => 10 ), $rules['quiz_lessons'] );
ok( 'quiz_lessons' === $r['key'] && has( $r['text'], array( '2.1×', '63%', '30%' ) ) && '/emails' === $r['action']['route'], 'quiz takers complete 2.1× as many lessons: ' . $r['text'] );
$rows[1]['lessons'] = 45;
ok( null === InsightRules::quiz_lessons( $rows, array( 1 => 10 ), $rules['quiz_lessons'] ), 'a 1.4× ratio is not reported' );
$rows[1]['lessons'] = 50;
ok( null === InsightRules::quiz_lessons( $rows, array( 1 => 10 ), $rules['quiz_lessons'] ), 'a 13-point difference is not reported' );
$rows[1]['enrolments'] = 4;
$rows[1]['lessons']    = 0;
ok( null === InsightRules::quiz_lessons( $rows, array( 1 => 10 ), $rules['quiz_lessons'] ), 'under 5 enrolments in a group: none' );
ok( null === InsightRules::quiz_lessons( $rows, array(), $rules['quiz_lessons'] ), 'courses without lessons are ignored' );

// Ordering, limit and thresholds.
$list = InsightRules::select(
	array(
		InsightRules::insight( 'completions', 'completions', 'success', 'c', '', null, '' ),
		null,
		InsightRules::insight( 'missed_question:1', 'missed_question', 'info', 'm', '', null, '' ),
		InsightRules::insight( 'drop_off:1', 'drop_off', 'warning', 'd', '', null, '' ),
		InsightRules::insight( 'revenue', 'revenue', 'success', 'r', '', null, '' ),
		InsightRules::insight( 'class_gap:1', 'class_gap', 'error', 'g', '', null, '' ),
		InsightRules::insight( 'quiz_lessons', 'quiz_lessons', 'info', 'q', '', null, '' ),
	),
	5
);
ok( array( 'class_gap', 'drop_off', 'revenue', 'completions', 'missed_question' ) === wp_list_pluck( $list, 'rule' ), 'insights sort by severity, then rule, at most 5' );
$cap = static function () {
	return array(
		'limit'    => 9,
		'drop_off' => array( 'min_percent' => -4 ),
	);
};
add_filter( 'ohmylms_dashboard_insight_rules', $cap );
$t = InsightRules::thresholds();
remove_filter( 'ohmylms_dashboard_insight_rules', $cap );
ok( 5 === $t['limit'] && 0 === $t['drop_off']['min_percent'] && 5 === $t['drop_off']['min_learners'], 'thresholds are clamped and keep defaults' );

// Site health: cron and money.
$now = 1800000000;
ok( 'ok' === SiteHealthChecks::cron( $now, array( $now - 300 => array( 'x' => array() ) ) )['status'], 'cron 5 minutes late is running' );
$late = SiteHealthChecks::cron(
	$now,
	array(
		$now - 1800 => array( 'x' => array() ),
		$now + 60   => array( 'y' => array() ),
	)
);
ok( 'error' === $late['status'] && has( $late['detail'], array( '30 minutes' ) ) && false !== strpos( $late['action']['href'], 'site-health.php' ), 'cron 30 minutes late is not running and links to Site Health' );
ok( 'warning' === SiteHealthChecks::cron( $now, array() )['status'], 'an empty cron queue is a warning' );
ok(
	'12.50 €' === InsightRules::money(
		12.5,
		array(
			'symbol'   => '€',
			'decimals' => 2,
			'position' => 'right_space',
		)
	),
	'money right with space'
);
ok(
	'¥1,200' === InsightRules::money(
		1200,
		array(
			'symbol'   => '¥',
			'decimals' => 0,
			'position' => 'left',
		)
	),
	'money left without decimals'
);

// ---------------------------------------------------------------- Part two: endpoints.

try {
	$fx = dashfx_create( $tag );
	$fx = dashinsfx_extend( $fx );
	Cache::flush();
	Widgets::reset();
	$s  = $fx['students'];
	$a  = 'school:' . $fx['school_a'];
	$b  = 'school:' . $fx['school_b'];
	$a1 = 'class:' . $fx['class_a1'];
	$c1 = $fx['course_1'];

	ok( null !== Widgets::get( 'course-health' ) && null !== Widgets::get( 'insights' ) && null !== Widgets::get( 'activity' ) && null !== Widgets::get( 'site-health' ), 'the four widgets are registered' );

	// ---- Course health.
	$ch_a = widget( $fx['sa'], 'course-health', array( 'scope' => $a ) );
	ok( 200 === $ch_a->get_status() && 'miss' === $ch_a->get_headers()['X-OhMyLMS-Cache'], 'course health answers 200 to the school administrator' );
	$row = course_row( $ch_a, $c1 );
	ok( null !== $row && $tag . ' Course One' === $row['title'], 'school A lists course one' );
	ok( null === course_row( $ch_a, $fx['course_2'] ), 'but not school B\'s course' );
	ok( 6 === $row['enrolled'] && 1 === $row['completed'] && 17 === $row['completion'], '6 enrolled, 1 completed (17%)' );
	ok( 3 === $row['items'] && 3 === $row['lessons'] && 33 === $row['median_progress'], 'median progress of 0, 33, 33, 33, 67 and 100 is 33%' );
	ok( $fx['lessons'][2] === $row['drop_off']['content_id'] && 2 === $row['drop_off']['position'] && 3 === $row['drop_off']['lost'] && 50 === $row['drop_off']['percent'], 'the drop-off is lesson two: 3 of 6 stop there' );
	ok( 'lesson' === $row['drop_off']['type'] && $tag . ' Lesson Two' === $row['drop_off']['title'], 'named as a lesson' );
	ok(
		array(
			'average' => 4.5,
			'count'   => 2,
		) === $row['rating'],
		'ratings of school A learners only, approved only: 4.5 from 2'
	);
	ok( null === $row['report'], 'no course report link for a school administrator' );
	ok( 1 === $ch_a->get_data()['data']['total'] && 12 === $ch_a->get_data()['data']['limit'], 'one course in scope, limit 12' );
	$text = wp_json_encode( $ch_a->get_data() );
	foreach ( array( 1, 2, 3, 7 ) as $i ) {
		ok( false === strpos( $text, 'S' . $i . ' ' . $tag ) && false === strpos( $text, $tag . '_s' . $i ), 'no learner name or login of S' . $i . ' in course health' );
	}
	ok( 'hit' === widget( $fx['admin'], 'course-health', array( 'scope' => $a ) )->get_headers()['X-OhMyLMS-Cache'], 'course health is cached per scope, shared by users' );
	$admin_row = course_row( widget( $fx['admin'], 'course-health', array( 'scope' => $a ) ), $c1 );
	ok( '/reports/courses/' . $c1 === $admin_row['report'], 'administrators get the course report route' );

	$row_a1 = course_row( widget( $fx['teacher'], 'course-health', array( 'scope' => $a1 ) ), $c1 );
	ok( 5 === $row_a1['enrolled'] && 20 === $row_a1['completion'] && 60 === $row_a1['drop_off']['percent'], 'class A1: 5 enrolled, 20% complete, 60% stop at lesson two' );
	ok( 4.5 === $row_a1['rating']['average'] && null === $row_a1['report'], 'class A1 ratings, no report link for the teacher' );
	$ch_b = widget( $fx['sb'], 'course-health', array( 'scope' => $b ) );
	$row  = course_row( $ch_b, $fx['course_2'] );
	ok( null !== $row && null === $row['rating'] && null === $row['drop_off'] && 0 === $row['items'], 'school B: course two without reviews or structure' );
	ok( null === course_row( $ch_b, $c1 ), 'school B does not see course one' );
	$all_row = course_row( widget( $fx['admin'], 'course-health', array( 'scope' => 'all' ) ), $c1 );
	ok( 3.3 === $all_row['rating']['average'] && 3 === $all_row['rating']['count'], 'all schools count every approved review: 3.3 from 3' );

	$one = static function () {
		return 1;
	};
	add_filter( 'ohmylms_dashboard_course_health_limit', $one );
	Cache::flush();
	$bounded = widget( $fx['admin'], 'course-health', array( 'scope' => 'all' ) )->get_data()['data'];
	ok( 1 === count( $bounded['courses'] ) && 1 === $bounded['limit'] && $bounded['total'] >= 2, 'the course list is bounded by the limit and reports the total' );
	remove_filter( 'ohmylms_dashboard_course_health_limit', $one );
	$many = static function () {
		return 500;
	};
	add_filter( 'ohmylms_dashboard_course_health_limit', $many );
	ok( 50 === CourseHealthReport::limit(), 'the limit is capped at 50' );
	remove_filter( 'ohmylms_dashboard_course_health_limit', $many );

	// ---- Insights, default thresholds.
	Cache::flush();
	$in_a = widget( $fx['sa'], 'insights', array( 'scope' => $a ) );
	ok( 200 === $in_a->get_status(), 'insights answer 200' );
	$ins = by_rule( $in_a );
	ok( isset( $ins['drop_off'] ) && 'drop_off:' . $c1 === $ins['drop_off']['key'] && 'error' === $ins['drop_off']['severity'], 'school A: course one loses 50% at lesson two (error)' );
	ok( has( $ins['drop_off']['text'], array( 'Lesson Two', 'item 2', 'Course One', '50%', '3 stop' ) ), 'with the numbers: ' . $ins['drop_off']['text'] );
	ok( null === $ins['drop_off']['action'], 'no report link for school staff' );
	ok( isset( $ins['missed_question'] ) && 'warning' === $ins['missed_question']['severity'] && has( $ins['missed_question']['text'], array( 'Tricky question', 'Checkpoint', '80%', '5 learners' ) ), 'the question 4 of 5 learners missed (80%)' );
	ok( ! isset( $ins['revenue'] ), 'no revenue for school staff' );
	ok( ! isset( $ins['class_gap'] ), 'class A2 has one assessed learner: under the 3-learner threshold' );
	ok( ! isset( $ins['quiz_lessons'] ) && ! isset( $ins['completions'] ), 'too few enrolments for the quiz rule, too few completions for the trend' );
	ok( array( 'drop_off', 'missed_question' ) === array_keys( $ins ), 'ordered by severity' );
	$text = wp_json_encode( $in_a->get_data() );
	ok( false === strpos( $text, 'capability' ), 'capabilities are not sent' );
	foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
		ok( false === strpos( $text, 'S' . $i . ' ' . $tag ), 'no learner name of S' . $i . ' in insights' );
	}
	$in_admin_a = by_rule( widget( $fx['admin'], 'insights', array( 'scope' => $a ) ) );
	ok( '/reports/courses/' . $c1 === $in_admin_a['drop_off']['action']['route'] && ! isset( $in_admin_a['revenue'] ), 'administrators in a school scope get links but no revenue' );

	$in_all = by_rule( widget( $fx['admin'], 'insights', array( 'scope' => 'all' ) ) );
	ok( isset( $in_all['revenue'] ) && '/earnings' === $in_all['revenue']['action']['route'], 'administrators in all schools get the revenue insight' );
	ok( has( $in_all['revenue']['text'], array( 'Revenue', '30 days' ) ), 'in plain words: ' . $in_all['revenue']['text'] );

	// ---- Insights, lowered thresholds.
	$low = static function ( $rules ) {
		$rules['class_gap']['min_learners']      = 1;
		$rules['quiz_lessons']['min_enrolments'] = 1;
		return $rules;
	};
	add_filter( 'ohmylms_dashboard_insight_rules', $low );
	Cache::flush();
	$ins = by_rule( widget( $fx['sa'], 'insights', array( 'scope' => $a ) ) );
	if ( $fx['skill'] ) {
		ok( isset( $ins['class_gap'] ) && 'class_gap:' . $fx['class_a1'] === $ins['class_gap']['key'] && 'error' === $ins['class_gap']['severity'], 'class A1 is behind class A2' );
		ok( has( $ins['class_gap']['text'], array( $tag . ' Class A1', '75 pts behind', $tag . ' Fractions', '25 against 100', '5 learners' ) ), 'with the numbers: ' . $ins['class_gap']['text'] );
	} else {
		echo "Skill tables are not installed: class-gap endpoint checks skipped.\n";
	}
	ok( isset( $ins['quiz_lessons'] ) && has( $ins['quiz_lessons']['text'], array( '53%', 'none' ) ), 'quiz takers completed 8 of 15 lessons, S6 none: ' . ( $ins['quiz_lessons']['text'] ?? '' ) );
	ok( null === $ins['quiz_lessons']['action'], 'the reminder link needs the e-mail settings capability' );
	ok( count( $ins ) <= 5, 'at most five insights' );
	$in_a1 = by_rule( widget( $fx['teacher'], 'insights', array( 'scope' => $a1 ) ) );
	ok( ! isset( $in_a1['class_gap'] ) && ! isset( $in_a1['revenue'] ), 'a class scope has no class comparison and no revenue' );
	ok( isset( $in_a1['drop_off'] ) && has( $in_a1['drop_off']['text'], array( '60%' ) ), 'class A1 sees its own drop-off (60%)' );
	$in_b = by_rule( widget( $fx['sb'], 'insights', array( 'scope' => $b ) ) );
	ok( ! isset( $in_b['drop_off'] ) && ! isset( $in_b['missed_question'] ) && ! isset( $in_b['class_gap'] ), 'school B sees nothing of school A' );
	remove_filter( 'ohmylms_dashboard_insight_rules', $low );

	// ---- Happening now.
	Cache::flush();
	ok( 60 === Cache::ttl( 'activity' ) && 0 === Cache::ttl( 'site-health' ) && 600 === Cache::ttl( 'pulse' ), 'activity is cached 60 s, site health not at all, others 10 minutes' );
	wp_update_user(
		array(
			'ID'           => $s[2],
			'display_name' => $tag . '_s2@example.test',
		)
	);
	$act    = widget( $fx['sa'], 'activity', array( 'scope' => $a ) );
	$events = $act->get_data()['data']['events'];
	$texts  = implode( "\n", wp_list_pluck( $events, 'text' ) );
	ok( 200 === $act->get_status() && 10 === count( $events ) && 10 === $act->get_data()['data']['limit'], 'school A lists its 10 newest events' );
	ok( has( $texts, array( 'S1 ' . $tag . ' completed ' . $tag . ' Course One', 'finished ' . $tag . ' Checkpoint', 'submitted ' . $tag . ' Essay' ) ), 'course completions, quizzes and submissions are listed' );
	$times = wp_list_pluck( $events, 'at' );
	$order = $times;
	rsort( $order );
	ok( $order === $times && preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d[+-]\d\d:\d\d$/', $times[0] ), 'newest first, ISO times with the site offset' );
	ok( false === strpos( $texts, '@' ) && false === strpos( $texts, $tag . '_s' ), 'no e-mail address or login, even when the display name is one' );
	ok( false === strpos( $texts, 'S7 ' . $tag ) && false === strpos( $texts, 'New order' ), 'nothing of school B and no orders for school staff' );
	$three = static function () {
		return 3;
	};
	add_filter( 'ohmylms_dashboard_activity_limit', $three );
	Cache::flush();
	$few = widget(
		$fx['sa'],
		'activity',
		array(
			'scope'  => $a,
			'period' => 7,
		)
	)->get_data()['data'];
	ok( 3 === count( $few['events'] ) && 3 === $few['limit'], 'the list is bounded by the limit' );
	remove_filter( 'ohmylms_dashboard_activity_limit', $three );
	$old = static function () {
		return 50;
	};
	add_filter( 'ohmylms_dashboard_activity_limit', $old );
	Cache::flush();
	$long = widget(
		$fx['admin'],
		'activity',
		array(
			'scope'  => 'all',
			'period' => 90,
		)
	)->get_data()['data'];
	ok( 25 === $long['limit'] && count( $long['events'] ) <= 25, 'the limit is capped at 25' );
	ok( false !== strpos( implode( "\n", wp_list_pluck( $long['events'], 'text' ) ), 'New order #' . number_format_i18n( $fx['orders_paid'][0] ) ) || count( $long['events'] ) === 25, 'all schools list paid orders without the buyer' );
	remove_filter( 'ohmylms_dashboard_activity_limit', $old );
	$a2_events = widget(
		$fx['sa'],
		'activity',
		array(
			'scope'  => 'class:' . $fx['class_a2'],
			'period' => 7,
		)
	)->get_data()['data']['events'];
	ok( array() === $a2_events, 'class A2 (S6, idle for 20 days) has no events this week' );

	// ---- Site health.
	$sh = widget( $fx['admin'], 'site-health', array( 'scope' => 'all' ) );
	ok( 200 === $sh->get_status() && 'miss' === $sh->get_headers()['X-OhMyLMS-Cache'], 'site health answers 200 to administrators, uncached' );
	$ids = wp_list_pluck( $sh->get_data()['data']['checks'], 'id' );
	ok( array( 'payment_gateway', 'email_sender', 'certificate_template', 'cron', 'privacy_policy' ) === $ids, 'five checks in order' );
	ok( 5 === $sh->get_data()['data']['total'] && $sh->get_data()['data']['passed'] <= 5, 'with a passed count' );
	foreach ( $sh->get_data()['data']['checks'] as $check ) {
		ok( in_array( $check['status'], array( 'ok', 'warning', 'error' ), true ) && '' !== $check['detail'], $check['id'] . ' is ' . $check['status'] . ': ' . $check['detail'] );
		ok( 'ok' === $check['status'] || ( is_array( $check['action'] ) && ( $check['action']['route'] || $check['action']['href'] ) ), $check['id'] . ' links to its fix when it fails' );
	}
	$privacy = (int) get_option( 'wp_page_for_privacy_policy', 0 );
	update_option( 'wp_page_for_privacy_policy', 0 );
	$checks_now = widget( $fx['admin'], 'site-health', array( 'scope' => 'all' ) )->get_data()['data']['checks'];
	update_option( 'wp_page_for_privacy_policy', $privacy );
	ok( 'warning' === $checks_now[4]['status'] && false !== strpos( $checks_now[4]['action']['href'], 'options-privacy.php' ), 'a missing privacy page is a warning linking to Settings › Privacy, shown at once' );
	foreach ( array( array( $fx['sa'], $a ), array( $fx['teacher'], $a1 ), array( $fx['admin'], $a ) ) as $case ) {
		$denied = widget( $case[0], 'site-health', array( 'scope' => $case[1] ) );
		ok( 403 === $denied->get_status() && 'ohmylms_dashboard_widget_unavailable' === $denied->get_data()['code'], 'site health is unavailable in ' . $case[1] . ' for user ' . $case[0] );
	}
	foreach ( array( 'course-health', 'insights', 'activity' ) as $id ) {
		ok( 403 === widget( $fx['sa'], $id, array( 'scope' => $b ) )->get_status(), $id . ' refuses a scope outside the user\'s' );
		ok( 403 === widget( $fx['outsider'], $id )->get_status(), $id . ' refuses users without dashboard access' );
	}

	echo "Dashboard widgets (course health, insights, activity, site health): {$checks} checks passed.\n";
} catch ( Throwable $error ) {
	echo $error->getMessage(), "\n", $error->getFile(), ':', $error->getLine(), "\n";
	$failed = true;
} finally {
	wp_set_current_user( 0 );
	dashinsfx_delete( $tag );
	dashfx_delete( $tag );
	$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_login LIKE %s', $wpdb->users, $wpdb->esc_like( $tag ) . '%' ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_title LIKE %s', $wpdb->posts, $wpdb->esc_like( $tag ) . '%' ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE comment_content LIKE %s', $wpdb->comments, $wpdb->esc_like( $tag ) . '%' ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE name LIKE %s', $wpdb->terms, $wpdb->esc_like( $tag ) . '%' ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE name LIKE %s', Schema::table( 'schools' ), $wpdb->esc_like( $tag ) . '%' ) );
	echo 0 === $left ? "Fixtures removed.\n" : "WARNING: {$left} fixture rows left.\n";
}
exit( empty( $failed ) ? 0 : 1 );
