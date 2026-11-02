<?php
/**
 * At-risk learners integration test: the rules engine, the `at-risk` settings group, the widget per scope
 * and period, the Needs attention count, and nudges (scope checks, rate limits, log, email content).
 *
 * Runs against a WordPress install (the Local site by default) with real tables. Fixtures are tagged
 * (tests/php/support/at-risk-fixture.php) and removed in `finally`; mail is captured, never sent.
 *
 * Usage (set OHMYLMS_AT_RISK_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/at-risk-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_AT_RISK_TEST' ) ) {
	exit( "Set OHMYLMS_AT_RISK_TEST=1 to run the at-risk tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/at-risk-fixture.php';

use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\ScopeResolver;
use OhMyLMS\Dashboard\Widgets\AtRiskNudges;
use OhMyLMS\Dashboard\Widgets\AtRiskRules;
use OhMyLMS\Dashboard\Widgets\AtRiskSettings;
use OhMyLMS\Emails\EmailManager;
use OhMyLMS\Emails\Emails;
use OhMyLMS\Skills\Score;
use OhMyLMS\Skills\ScoreModel;

global $wpdb;

$checks = 0;
$tag    = 'risk' . strtolower( wp_generate_password( 6, false, false ) );
$mails  = array();
$fail   = false;
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mails, &$fail ) {
		if ( $fail ) {
			return false;
		}
		$mails[] = $atts;
		return true;
	},
	10,
	2
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
 * Dispatch a REST request as a user.
 *
 * @param int    $user   User (0 = anonymous).
 * @param string $method Method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $params Parameters (JSON body for PATCH).
 * @return WP_REST_Response
 */
function call( $user, $method, $path, array $params = array() ) {
	wp_set_current_user( $user );
	ScopeResolver::reset();
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} elseif ( 'PATCH' === $method ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( (object) $params ) );
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
	ok( $status === $response->get_status(), $message . ' answers ' . $status . ' (got ' . $response->get_status() . ' ' . ( $data['code'] ?? '' ) . ')' );
	ok( ( $data['code'] ?? '' ) === $code, $message . ' code ' . $code . ' (got ' . ( $data['code'] ?? '?' ) . ')' );
}

/**
 * Learners of an at-risk response by id.
 *
 * @param WP_REST_Response $response Response.
 * @return array<int,array>
 */
function learners( $response ) {
	$list = array();
	foreach ( (array) ( $response->get_data()['data']['learners'] ?? array() ) as $learner ) {
		$list[ (int) $learner['id'] ] = $learner;
	}
	return $list;
}

$saved_rules = get_option( AtRiskSettings::OPTION, null );
$saved_email = get_option( Emails::OPTION_PREFIX . 'student_at_risk_nudge', null );
delete_option( AtRiskSettings::OPTION );
delete_option( Emails::OPTION_PREFIX . 'student_at_risk_nudge' );

try {
	// ---- Rules engine (pure).
	$rules = AtRiskSettings::rules();
	ok( 7 === $rules['inactive']['min'] && 10 === $rules['mastery']['min'] && 14 === $rules['mastery']['days'] && 1 === $rules['overdue']['min'], 'defaults: inactive 7 days, mastery 10 points in 14 days, 1 overdue item' );
	ok(
		null === AtRiskRules::evaluate(
			array(
				'inactive' => 6,
				'mastery'  => 9.9,
				'overdue'  => 0,
			),
			$rules
		),
		'below every threshold: not flagged'
	);
	$one = AtRiskRules::evaluate(
		array(
			'inactive' => 7,
			'mastery'  => null,
			'overdue'  => 0,
		),
		$rules
	);
	ok( AtRiskRules::WATCH === $one['level'] && 'inactive' === $one['reasons'][0]['rule'] && 7 === $one['reasons'][0]['value'], 'one rule (inactive 7 days): watch' );
	ok( AtRiskRules::WATCH === AtRiskRules::evaluate( array( 'mastery' => 10.4 ), $rules )['level'], 'mastery down 10 points alone: watch' );
	ok( AtRiskRules::WATCH === AtRiskRules::evaluate( array( 'overdue' => 1 ), $rules )['level'], 'one overdue item alone: watch' );
	ok(
		AtRiskRules::HIGH === AtRiskRules::evaluate(
			array(
				'inactive' => 8,
				'overdue'  => 1,
			),
			$rules
		)['level'],
		'two rules: high'
	);
	ok( AtRiskRules::HIGH === AtRiskRules::evaluate( array( 'inactive' => 14 ), $rules )['level'], 'inactive at the severe threshold (14 days): high' );
	ok( AtRiskRules::HIGH === AtRiskRules::evaluate( array( 'mastery' => 20 ), $rules )['level'], 'mastery down 20 points: high' );
	ok( AtRiskRules::HIGH === AtRiskRules::evaluate( array( 'overdue' => 3 ), $rules )['level'], 'three overdue items: high' );
	$off                        = $rules;
	$off['inactive']['enabled'] = false;
	ok( null === AtRiskRules::evaluate( array( 'inactive' => 30 ), $off ), 'a switched-off rule never flags' );
	$tuned = AtRiskSettings::rules(
		array_merge(
			AtRiskSettings::read(),
			array(
				'inactive_days'       => 3,
				'mastery_window_days' => 10,
			)
		)
	);
	ok( AtRiskRules::WATCH === AtRiskRules::evaluate( array( 'inactive' => 3 ), $tuned )['level'], 'thresholds are adjustable' );
	ok(
		'Inactive 7 days' === AtRiskRules::text(
			array(
				'rule'  => 'inactive',
				'value' => 7,
			),
			$rules
		),
		'inactive text'
	);
	ok(
		'Mastery down 15 pts in 2 wks' === AtRiskRules::text(
			array(
				'rule'  => 'mastery',
				'value' => 15,
			),
			$rules
		),
		'mastery text in weeks'
	);
	ok(
		'Mastery down 12 pts in 10 days' === AtRiskRules::text(
			array(
				'rule'  => 'mastery',
				'value' => 12,
			),
			$tuned
		),
		'mastery text in days'
	);
	ok(
		'1 overdue item' === AtRiskRules::text(
			array(
				'rule'  => 'overdue',
				'value' => 1,
			),
			$rules
		),
		'overdue text (singular)'
	);
	ok(
		'Inactive 7 days · Mastery down 15 pts in 2 wks' === AtRiskRules::summary(
			array(
				array(
					'rule'  => 'inactive',
					'value' => 7,
				),
				array(
					'rule'  => 'mastery',
					'value' => 15,
				),
			),
			$rules
		),
		'reasons are joined with a middle dot'
	);

	$fx = riskfx_create( $tag );
	$l  = $fx['l'];
	$a  = 'school:' . $fx['school_a'];
	$b  = 'school:' . $fx['school_b'];
	$a1 = 'class:' . $fx['class_a1'];
	$a2 = 'class:' . $fx['class_a2'];
	Cache::flush();

	// ---- Settings group.
	$get = call( $fx['admin'], 'GET', '/settings/at-risk' );
	ok( 200 === $get->get_status() && 'at-risk' === $get->get_data()['group'] && 7 === $get->get_data()['values']['inactive_days'] && false === $get->get_data()['values']['nudge_guardians'], 'administrators read the defaults (guardian copies off)' );
	is_error( call( 0, 'GET', '/settings/at-risk' ), 401, 'ohmylms_settings_cannot_view', 'anonymous settings read' );
	is_error( call( $fx['teacher'], 'GET', '/settings/at-risk' ), 403, 'ohmylms_settings_cannot_view', 'teacher settings read' );
	is_error( call( $fx['teacher'], 'PATCH', '/settings/at-risk', array( 'inactive_days' => 5 ) ), 403, 'ohmylms_settings_cannot_edit', 'teacher settings write' );
	is_error( call( $fx['admin'], 'PATCH', '/settings/at-risk', array( 'bogus' => 1 ) ), 400, 'ohmylms_settings_unknown_field', 'unknown field' );
	$typed = call(
		$fx['admin'],
		'PATCH',
		'/settings/at-risk',
		array(
			'inactive_days'   => '7',
			'mastery_enabled' => 1,
		)
	);
	is_error( $typed, 400, 'rest_invalid_param', 'wrong types' );
	ok( isset( $typed->get_data()['data']['params']['inactive_days'], $typed->get_data()['data']['params']['mastery_enabled'] ), 'type errors name each field' );
	$range = call(
		$fx['admin'],
		'PATCH',
		'/settings/at-risk',
		array(
			'inactive_days'       => 0,
			'mastery_window_days' => 91,
			'nudge_daily_cap'     => 5,
		)
	);
	is_error( $range, 422, 'ohmylms_settings_invalid_value', 'values out of range' );
	$params = $range->get_data()['data']['params'];
	ok( isset( $params['inactive_days'], $params['mastery_window_days'] ) && ! isset( $params['nudge_daily_cap'] ) && false !== strpos( $params['inactive_days'], '1 to 365' ), '422 has one message per invalid field' );
	ok( 20 === AtRiskSettings::read()['nudge_daily_cap'], 'a rejected request changes nothing' );
	$severe = call( $fx['admin'], 'PATCH', '/settings/at-risk', array( 'inactive_severe_days' => 5 ) );
	is_error( $severe, 422, 'ohmylms_settings_invalid_value', 'severe below the threshold' );
	ok( isset( $severe->get_data()['data']['params']['inactive_severe_days'] ), 'the severe field is named' );
	is_error( call( $fx['admin'], 'PATCH', '/settings/at-risk', array( 'overdue_items' => 5 ) ), 422, 'ohmylms_settings_invalid_value', 'raising a threshold above its severe one' );
	$version = (int) get_option( Cache::VERSION_OPTION, 1 );
	$saved   = call(
		$fx['admin'],
		'PATCH',
		'/settings/at-risk',
		array(
			'mastery_window_days' => 21,
			'nudge_daily_cap'     => 20,
		)
	);
	ok( 200 === $saved->get_status() && array( 'mastery_window_days' ) === $saved->get_data()['changed'] && 21 === $saved->get_data()['values']['mastery_window_days'], 'a valid partial update saves only what changed' );
	ok( (int) get_option( Cache::VERSION_OPTION, 1 ) === $version + 1, 'saving rules drops cached dashboard data' );
	ok( array() === call( $fx['admin'], 'PATCH', '/settings/at-risk', array( 'mastery_window_days' => 21 ) )->get_data()['changed'], 'saving the same value changes nothing' );
	call( $fx['admin'], 'PATCH', '/settings/at-risk', array( 'mastery_window_days' => 14 ) );
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/settings/at-risk' ) )->get_data()['schema'];
	ok( 'settings-at-risk' === $schema['title'] && 365 === $schema['properties']['inactive_days']['maximum'] && false === $schema['additionalProperties'], 'OPTIONS describes the group' );
	update_option(
		AtRiskSettings::OPTION,
		array(
			'inactive_days'        => 'x',
			'inactive_severe_days' => 2,
		)
	);
	ok( 7 === AtRiskSettings::read()['inactive_days'] && 7 === AtRiskSettings::read()['inactive_severe_days'], 'malformed stored values read as defaults, severe never below its threshold' );
	delete_option( AtRiskSettings::OPTION );
	Cache::flush();

	// ---- Widget per scope.
	$all = call( $fx['admin'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => 'all' ) );
	ok( 200 === $all->get_status() && 'miss' === $all->get_headers()['X-OhMyLMS-Cache'] && 'private, no-store' === $all->get_headers()['Cache-Control'], 'the widget answers 200, computed, private' );
	$in_all = learners( $all );
	ok( ! isset( $in_all[ $l[1] ] ), 'L1 (active today) is not flagged' );
	ok( ! isset( $in_all[ $l[8] ] ), 'L8 (course completed) is not judged on inactivity' );
	ok( isset( $in_all[ $l[2] ] ) && 'watch' === $in_all[ $l[2] ]['level'] && 'Inactive 9 days' === $in_all[ $l[2] ]['summary'], 'L2: inactive 9 days (quiz activity counts), watch' );
	ok( isset( $in_all[ $l[3] ] ) && 'high' === $in_all[ $l[3] ]['level'] && 'Inactive 20 days' === $in_all[ $l[3] ]['summary'] && true === $in_all[ $l[3] ]['reasons'][0]['severe'], 'L3: inactive 20 days (severe), high' );
	ok( isset( $in_all[ $l[5] ] ) && 'high' === $in_all[ $l[5] ]['level'] && 'Inactive 8 days · 3 overdue items' === $in_all[ $l[5] ]['summary'], 'L5: inactive 8 days and 3 overdue items (completed and future work excluded), high' );
	ok( isset( $in_all[ $l[6] ] ) && 'watch' === $in_all[ $l[6] ]['level'] && '1 overdue item' === $in_all[ $l[6] ]['summary'], 'L6: one overdue item, watch' );
	ok( isset( $in_all[ $l[7] ] ) && 'Inactive 10 days' === $in_all[ $l[7] ]['summary'], 'L7 (school B) is flagged in all schools' );
	if ( $fx['evidence'] ) {
		$rows     = $wpdb->get_results( $wpdb->prepare( 'SELECT awarded, available, independent, first_try, difficulty, evidence_at FROM %i WHERE student_id = %d ORDER BY evidence_at, id', \OhMyLMS\Assessment\Schema::table( 'skill_evidence' ), $l[4] ), ARRAY_A );
		$config   = Score::config();
		$expected = (int) floor( ScoreModel::run( array_slice( $rows, 0, 5 ), $config )['score'] - ScoreModel::run( $rows, $config )['score'] );
		ok( $expected >= 10 && isset( $in_all[ $l[4] ] ) && 'mastery' === $in_all[ $l[4] ]['reasons'][0]['rule'] && $expected === $in_all[ $l[4] ]['reasons'][0]['value'], 'L4: mastery down ' . $expected . ' points (replayed from evidence), not inactive' );
		ok( 1 === count( $in_all[ $l[4] ]['reasons'] ) && 'Mastery down ' . $expected . ' pts in 2 wks' === $in_all[ $l[4] ]['summary'], 'L4 reason text' );
	} else {
		echo "note: assessment tables not ready; mastery checks skipped\n";
	}
	$order = array_keys( $in_all );
	ok( array_search( $l[3], $order, true ) < array_search( $l[2], $order, true ) && array_search( $l[5], $order, true ) < array_search( $l[6], $order, true ), 'high before watch' );
	ok( '/students/' . $l[3] . '/report' === $in_all[ $l[3] ]['report'], 'administrators get the student report link' );
	ok( 'Risk' !== $in_all[ $l[3] ]['initials'] && 'LR' === substr( $in_all[ $l[3] ]['initials'], 0, 2 ), 'initials of the display name (' . $in_all[ $l[3] ]['initials'] . ')' );
	$json = wp_json_encode( $all->get_data() );
	ok( false === strpos( $json, '@example.test' ) && false === strpos( $json, $tag . '_l' ), 'no email addresses or logins in the response' );
	ok( 'hit' === call( $fx['admin'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => 'all' ) )->get_headers()['X-OhMyLMS-Cache'], 'the second request is cached' );

	$school_a = learners( call( $fx['sa'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a ) ) );
	ok( isset( $school_a[ $l[2] ], $school_a[ $l[3] ], $school_a[ $l[5] ], $school_a[ $l[6] ] ) && ! isset( $school_a[ $l[7] ] ), 'school A lists its learners only' );
	ok( null === $school_a[ $l[3] ]['report'], 'no report link for a school administrator' );
	$class_a1 = call( $fx['teacher'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a1 ) );
	$in_a1    = learners( $class_a1 );
	ok( isset( $in_a1[ $l[2] ], $in_a1[ $l[3] ], $in_a1[ $l[5] ] ) && ! isset( $in_a1[ $l[6] ] ) && ! isset( $in_a1[ $l[7] ] ), 'class A1 lists its roster only' );
	ok( ( $fx['evidence'] ? 2 : 3 ) === $class_a1->get_data()['data']['counts']['high'], 'class A1 high count' );
	$in_a2 = learners( call( $fx['teacher2'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a2 ) ) );
	ok( array( $l[6] ) === array_keys( $in_a2 ) && '1 overdue item' === $in_a2[ $l[6] ]['summary'], 'class A2 lists L6 with its class work' );
	ok( ! isset( learners( call( $fx['sa'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a1 ) ) )[ $l[6] ] ), 'overdue work of another class does not count in a class scope' );
	is_error( call( $fx['teacher'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a2 ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'teacher outside their classes' );
	is_error( call( $l[1], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a1 ) ), 403, 'ohmylms_dashboard_cannot_view', 'learners have no dashboard' );
	$week = learners(
		call(
			$fx['teacher'],
			'GET',
			'/dashboard/widgets/at-risk',
			array(
				'scope'  => $a1,
				'period' => 7,
			)
		)
	);
	ok( 'Inactive 8 days · 2 overdue items' === $week[ $l[5] ]['summary'], 'the period bounds the due dates (7 days: 2 overdue items)' );

	$na       = call( $fx['teacher'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a1 ) );
	$na_items = array();
	foreach ( $na->get_data()['data']['items'] as $item ) {
		$na_items[ $item['key'] ] = $item;
	}
	ok( isset( $na_items['at_risk'] ) && ( $fx['evidence'] ? 2 : 3 ) === $na_items['at_risk']['count'], 'Needs attention counts the high-risk learners of the scope' );
	ok( '/dashboard?scope=' . $a1 === $na_items['at_risk']['action']['route'], 'and links to the dashboard in that scope' );

	// ---- Nudges.
	$nudge = static function ( $user, $scope, $learner, array $extra = array() ) {
		return call(
			$user,
			'POST',
			'/dashboard/widgets/at-risk/nudges',
			array_merge(
				array(
					'scope'   => $scope,
					'learner' => $learner,
				),
				$extra
			)
		);
	};
	is_error( $nudge( 0, $a1, $l[3] ), 401, 'ohmylms_dashboard_cannot_view', 'anonymous nudge' );
	is_error( $nudge( $l[1], $a1, $l[3] ), 403, 'ohmylms_dashboard_cannot_view', 'learner nudge' );
	is_error( $nudge( $fx['teacher'], $a1, $l[6] ), 403, 'ohmylms_at_risk_out_of_scope', 'teacher nudging a learner of another class' );
	is_error( $nudge( $fx['teacher'], $a2, $l[6] ), 403, 'ohmylms_dashboard_forbidden_scope', 'teacher nudging through a class they do not teach' );
	is_error( $nudge( $fx['sa'], $b, $l[7] ), 403, 'ohmylms_dashboard_forbidden_scope', 'school administrator nudging in another school' );
	is_error( $nudge( $fx['teacher'], $a1, $l[1] ), 409, 'ohmylms_at_risk_not_flagged', 'nudging a learner who is not flagged' );
	is_error( call( $fx['teacher'], 'POST', '/dashboard/widgets/at-risk/nudges', array( 'learner' => $l[3] ) ), 400, 'rest_missing_callback_param', 'nudging needs a scope' );
	ok( array() === $mails, 'refused nudges send nothing' );

	$sent = $nudge( $fx['teacher'], $a1, $l[3] );
	ok( 201 === $sent->get_status() && $l[3] === $sent->get_data()['learner'] && 0 === $sent->get_data()['guardians'], 'the teacher nudges L3: 201' );
	ok( 1 === count( $mails ) && array( $tag . '_l3@example.test' ) === (array) $mails[0]['to'], 'one email, to the learner' );
	$teacher_name = get_userdata( $fx['teacher'] )->display_name;
	ok( $teacher_name . ' checked in on your learning' === $mails[0]['subject'], 'subject names the sender: ' . $mails[0]['subject'] );
	ok( false !== strpos( $mails[0]['message'], 'Hi L3 ' . $tag . ',' ) && false !== strpos( $mails[0]['message'], 'Here is what we noticed: Inactive 20 days.' ) && false !== strpos( $mails[0]['message'], 'Continue learning' ), 'body greets the learner and states the reasons' );
	ok( false === strpos( $mails[0]['message'], '{' ), 'no merge tag is left in the body' );
	ok( in_array( 'Content-Type: text/html; charset=UTF-8', (array) $mails[0]['headers'], true ), 'sent as HTML' );
	$log = AtRiskNudges::log( $l[3] );
	ok( 1 === count( $log ) && $fx['teacher'] === $log[0]['sender'] && $a1 === $log[0]['scope'] && 'sent' === $log[0]['status'] && 'inactive' === $log[0]['reasons'][0]['rule'] && 20 === $log[0]['reasons'][0]['value'], 'the nudge is logged: who, whom, scope, reasons' );
	ok( abs( strtotime( $log[0]['sent_at'] ) - time() ) < 60, 'and when' );

	$again = $nudge( $fx['teacher'], $a1, $l[3] );
	is_error( $again, 429, 'ohmylms_at_risk_nudge_too_soon', 'the same learner again within 72 hours' );
	ok( (int) $again->get_headers()['Retry-After'] > 71 * HOUR_IN_SECONDS && '' !== $again->get_data()['data']['nudged_label'], 'with Retry-After and the date of the nudge' );
	ok( 1 === count( $mails ), 'nothing sent' );
	$view = learners( call( $fx['teacher'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a1 ) ) );
	ok( false === $view[ $l[3] ]['can_nudge'] && null !== $view[ $l[3] ]['nudged_at'] && AtRiskNudges::date_label( time() ) === $view[ $l[3] ]['nudged_label'], 'the widget shows "Nudged <date>" to the sender' );
	ok( true === $view[ $l[2] ]['can_nudge'] && null === $view[ $l[2] ]['nudged_at'], 'other learners stay nudgeable' );
	$admin_view = learners( call( $fx['admin'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a1 ) ) );
	ok( true === $admin_view[ $l[3] ]['can_nudge'], 'the limit is per sender' );

	update_option( AtRiskSettings::OPTION, array_merge( AtRiskSettings::read(), array( 'nudge_daily_cap' => 2 ) ) );
	ok( 1 === call( $fx['teacher'], 'GET', '/dashboard/widgets/at-risk', array( 'scope' => $a1 ) )->get_data()['data']['nudge']['remaining'], 'remaining nudges today' );
	ok( 201 === $nudge( $fx['teacher'], $a1, $l[2] )->get_status(), 'second nudge of the day' );
	$capped = $nudge( $fx['teacher'], $a1, $l[5] );
	is_error( $capped, 429, 'ohmylms_at_risk_nudge_daily_cap', 'the daily cap' );
	ok( (int) $capped->get_headers()['Retry-After'] > 0, 'with Retry-After' );
	ok( 2 === count( $mails ), 'nothing sent past the cap' );

	$fail = true;
	is_error( $nudge( $fx['admin'], $a1, $l[5] ), 500, 'ohmylms_at_risk_nudge_failed', 'a failed send' );
	$fail = false;
	ok( 'failed' === AtRiskNudges::log( $l[5] )[0]['status'], 'is logged as failed' );
	ok( 201 === $nudge( $fx['admin'], $a1, $l[5] )->get_status(), 'and does not start the 72-hour limit' );

	update_option( AtRiskSettings::OPTION, array_merge( AtRiskSettings::read(), array( 'nudge_guardians' => true ) ) );
	$mails  = array();
	$copied = $nudge( $fx['sa'], $a, $l[6] );
	ok( 201 === $copied->get_status() && 1 === $copied->get_data()['guardians'], 'with guardian copies on, the guardian of L6 gets one' );
	ok( 2 === count( $mails ) && array( $tag . '_parent@example.test' ) === (array) $mails[1]['to'], 'as a separate email' );
	ok( 1 === AtRiskNudges::log( $l[6] )[0]['guardians'], 'the copy is logged' );

	update_option( Emails::OPTION_PREFIX . 'student_at_risk_nudge', array( 'enable' => false ) );
	is_error( $nudge( $fx['admin'], $a, $l[2] ), 409, 'ohmylms_at_risk_nudge_disabled', 'the email switched off under Emails' );
	delete_option( Emails::OPTION_PREFIX . 'student_at_risk_nudge' );

	$log_admin = call( $fx['admin'], 'GET', '/dashboard/widgets/at-risk/nudges', array( 'learner' => $l[5] ) );
	ok( 200 === $log_admin->get_status() && 2 === count( $log_admin->get_data() ), 'administrators read the log' );
	is_error( call( $fx['teacher'], 'GET', '/dashboard/widgets/at-risk/nudges' ), 403, 'ohmylms_at_risk_log_forbidden', 'teachers cannot read the log' );

	// ---- The email in the email system.
	ok( in_array( 'student_at_risk_nudge', EmailManager::ids(), true ), 'the nudge is listed under Emails' );
	$item = EmailManager::get( 'student_at_risk_nudge' );
	ok( 'student' === $item['recipient_type'] && true === $item['enabled'] && in_array( 'reasons', wp_list_pluck( $item['tags'], 'tag' ), true ), 'it is a student email with the sender and reasons tags' );
	$preview = EmailManager::preview( 'student_at_risk_nudge' );
	ok( ! is_wp_error( $preview ) && array() === $preview['unknown_tags'] && false !== strpos( $preview['html'], 'Inactive 9 days' ), 'its preview renders with sample data' );
	ok( 'Ms. Rivera checked in on your learning' === $preview['subject'], 'preview subject' );
} finally {
	riskfx_delete( $tag );
	if ( null === $saved_rules ) {
		delete_option( AtRiskSettings::OPTION );
	} else {
		update_option( AtRiskSettings::OPTION, $saved_rules );
	}
	if ( null === $saved_email ) {
		delete_option( Emails::OPTION_PREFIX . 'student_at_risk_nudge' );
	} else {
		update_option( Emails::OPTION_PREFIX . 'student_at_risk_nudge', $saved_email );
	}
	Cache::flush();
}

ok( array() === AtRiskNudges::log( $fx['l'][3] ), 'deleting a user removes their log rows' );
echo "OK: $checks at-risk checks passed.\n";
