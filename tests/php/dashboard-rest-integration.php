<?php
/**
 * Scoped dashboard integration test: scope resolution and permissions, the scopes, needs-attention,
 * pulse and snooze endpoints, and their caching.
 *
 * Runs against a WordPress install (the Local site by default) with real tables. It creates tagged
 * schools, classes, users, courses, enrolments, attempts and orders (tests/php/support/dashboard-fixture.php)
 * and removes them in `finally`; mail is intercepted. It never resets the database.
 *
 * Usage (set OHMYLMS_DASHBOARD_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/dashboard-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_DASHBOARD_TEST' ) ) {
	exit( "Set OHMYLMS_DASHBOARD_TEST=1 to run the dashboard REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require __DIR__ . '/support/dashboard-fixture.php';

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\AtRiskProvider;
use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\ScopeResolver;
use OhMyLMS\Dashboard\Snoozes;
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
	ok( $status === $response->get_status(), $message . ' answers ' . $status . ' (got ' . $response->get_status() . ')' );
	ok( ( $data['code'] ?? '' ) === $code, $message . ' code ' . $code . ' (got ' . ( $data['code'] ?? '?' ) . ')' );
}

/**
 * Scope ids of a scopes response.
 *
 * @param WP_REST_Response $response Response.
 * @return string[]
 */
function scope_ids( $response ) {
	return wp_list_pluck( $response->get_data()['scopes'], 'id' );
}

/**
 * Items of a needs-attention response by key.
 *
 * @param WP_REST_Response $response Response.
 * @return array<string,array>
 */
function items( $response ) {
	$items = array();
	foreach ( $response->get_data()['data']['items'] as $item ) {
		$items[ $item['key'] ] = $item;
	}
	return $items;
}

/**
 * Metrics of a pulse response by id.
 *
 * @param WP_REST_Response $response Response.
 * @return array<string,array>
 */
function metrics( $response ) {
	$metrics = array();
	foreach ( $response->get_data()['data']['metrics'] as $metric ) {
		$metrics[ $metric['id'] ] = $metric;
	}
	return $metrics;
}

try {
	$fx = dashfx_create( $tag );
	Cache::flush();
	$s  = $fx['students'];
	$a  = 'school:' . $fx['school_a'];
	$b  = 'school:' . $fx['school_b'];
	$a1 = 'class:' . $fx['class_a1'];
	$a2 = 'class:' . $fx['class_a2'];
	$b1 = 'class:' . $fx['class_b1'];
	$i1 = 'class:' . $fx['class_i1'];

	// ---- Who may use the dashboard.
	ok( user_can( $fx['admin'], Access::CAPABILITY ), 'administrators hold the dashboard capability' );
	ok( user_can( $fx['sa'], Access::CAPABILITY ), 'school administrators hold it' );
	ok( user_can( $fx['teacher'], Access::CAPABILITY ), 'teachers of a class hold it' );
	ok( ! user_can( $fx['lone'], Access::CAPABILITY ), 'a teacher without classes does not' );
	ok( ! user_can( $fx['outsider'], Access::CAPABILITY ), 'a subscriber does not' );
	ok( ! user_can( $s[1], Access::CAPABILITY ), 'a student does not' );
	ok( ! user_can( $fx['sa'], 'manage_options' ), 'the capability grants nothing else' );

	is_error( call( 0, 'GET', '/dashboard/scopes' ), 401, 'ohmylms_dashboard_cannot_view', 'anonymous scopes' );
	is_error( call( 0, 'GET', '/dashboard/widgets/pulse' ), 401, 'ohmylms_dashboard_cannot_view', 'anonymous widget' );
	foreach ( array( 'outsider', 'lone' ) as $name ) {
		is_error( call( $fx[ $name ], 'GET', '/dashboard/scopes' ), 403, 'ohmylms_dashboard_cannot_view', $name . ' scopes' );
		is_error( call( $fx[ $name ], 'GET', '/dashboard/widgets/needs-attention' ), 403, 'ohmylms_dashboard_cannot_view', $name . ' widget' );
	}
	is_error( call( $s[1], 'GET', '/dashboard/widgets/pulse', array( 'scope' => $a ) ), 403, 'ohmylms_dashboard_cannot_view', 'student widget' );
	is_error( call( $s[1], 'POST', '/dashboard/widgets/needs-attention/snoozes', array( 'scope' => $a, 'key' => 'grading' ) ), 403, 'ohmylms_dashboard_cannot_view', 'student snooze' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	// ---- Scopes per user.
	$admin_scopes = call( $fx['admin'], 'GET', '/dashboard/scopes' );
	ok( 200 === $admin_scopes->get_status(), 'admin scopes answer 200' );
	$ids = scope_ids( $admin_scopes );
	ok( 'all' === $ids[0] && 'all' === $admin_scopes->get_data()['default'], 'administrators start with all schools' );
	ok( true === $admin_scopes->get_data()['site_admin'], 'administrators are flagged as site administrators' );
	foreach ( array( $a, $a1, $a2, $b, $b1, $i1 ) as $id ) {
		ok( in_array( $id, $ids, true ), 'administrators may use ' . $id );
	}
	ok( array_search( $a1, $ids, true ) > array_search( $a, $ids, true ) && array_search( $a1, $ids, true ) < array_search( $b, $ids, true ), 'classes follow their school' );
	ok( array_search( $i1, $ids, true ) > array_search( $b1, $ids, true ), 'independent classes come last' );
	$all = $admin_scopes->get_data()['scopes'][0];
	ok( true === $all['commerce'] && 'all' === $all['type'], 'the all scope carries commerce' );

	$sa_scopes = call( $fx['sa'], 'GET', '/dashboard/scopes' );
	ok( array( $a, $a1, $a2 ) === scope_ids( $sa_scopes ), 'a school administrator sees exactly their school and its classes' );
	ok( $a === $sa_scopes->get_data()['default'] && false === $sa_scopes->get_data()['site_admin'], 'their default is their school' );
	foreach ( $sa_scopes->get_data()['scopes'] as $scope ) {
		ok( false === $scope['commerce'], 'no commerce in ' . $scope['id'] );
	}
	$a1_row = $sa_scopes->get_data()['scopes'][1];
	ok( (int) $fx['school_a'] === $a1_row['school_id'] && $tag . ' Alpha School' === $a1_row['school_name'] && 'class' === $a1_row['type'], 'a class names its school' );
	ok( array( $a1, $i1 ) === scope_ids( call( $fx['teacher'], 'GET', '/dashboard/scopes' ) ), 'a teacher sees exactly the classes they teach' );
	ok( array( $b, $b1 ) === scope_ids( call( $fx['sb'], 'GET', '/dashboard/scopes' ) ), 'the other school administrator sees only their school' );

	// ---- Resolution errors.
	foreach ( array( $b, $b1, 'all', $i1, 'school:999999999', 'class:999999999' ) as $id ) {
		is_error( call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $id ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'school administrator asking for ' . $id );
	}
	foreach ( array( $a, $a2, $b1 ) as $id ) {
		is_error( call( $fx['teacher'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => $id ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'teacher asking for ' . $id );
	}
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => 'school:999999999' ) ), 404, 'ohmylms_dashboard_scope_not_found', 'administrator asking for an unknown school' );
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => 'class:999999999' ) ), 404, 'ohmylms_dashboard_scope_not_found', 'administrator asking for an unknown class' );
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => 'school:abc' ) ), 400, 'rest_invalid_param', 'malformed scope' );
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/pulse', array( 'period' => 45 ) ), 400, 'rest_invalid_param', 'unsupported period' );
	is_error( call( $fx['admin'], 'GET', '/dashboard/widgets/nothing-here' ), 404, 'ohmylms_dashboard_widget_not_found', 'unknown widget' );

	// ---- Scope values.
	ScopeResolver::reset();
	$scope_a = ScopeResolver::resolve( $a, $fx['sa'] );
	ok( $scope_a instanceof Scope && $a === $scope_a->key() && $a === $scope_a->cache_key(), 'resolve() returns the scope with a stable key' );
	$expected = array_values( array_map( 'intval', array( $s[1], $s[2], $s[3], $s[4], $s[5], $s[6] ) ) );
	sort( $expected );
	ok( $expected === $scope_a->learner_ids(), 'school learners are its active students' );
	$scope_a1 = ScopeResolver::resolve( $a1, $fx['teacher'] );
	$expected = array_slice( $expected, 0, 5 );
	ok( $expected === $scope_a1->learner_ids(), 'class learners are its roster' );
	ok( array( $fx['course_1'] ) === $scope_a1->course_ids(), 'class courses are the linked and enrolled courses' );
	ok( array( $fx['course_2'] ) === ScopeResolver::resolve( $b, $fx['sb'] )->course_ids(), 'school B courses come from its learners' );
	ok( null === Scope::all()->learner_ids() && null === Scope::all()->course_ids(), 'all is unrestricted' );
	$args = array();
	ok( '1 = 1' === Scope::all()->learner_condition( 'x.user_id', $args ) && ! $args, 'all adds no SQL restriction' );
	ok( '1 = 0' === Scope::in_condition( 'x', array(), $args ), 'an empty scope matches nothing' );
	ok( ScopeResolver::resolve( null, $fx['teacher'] )->key() === $a1, 'without a scope the first one is used' );
	$period = new Period( 7 );
	ok( 7 === count( $period->day_keys() ) && 7 === $period->to_array()['days'], 'a 7-day period has 7 days' );
	ok( ( new Period( 90 ) )->previous()->end < ( new Period( 90 ) )->range()->start, 'the previous window ends before the current one' );
	ok( 30 === ( new Period( 12 ) )->days(), 'unknown lengths fall back to 30 days' );

	// An archived class disappears from every list.
	$wpdb->update( Schema::table( 'classes' ), array( 'status' => 'archived' ), array( 'id' => $fx['class_a2'] ) );
	ok( array( $a, $a1 ) === scope_ids( call( $fx['sa'], 'GET', '/dashboard/scopes' ) ), 'archived classes are not offered' );
	ok( ! in_array( $a2, scope_ids( call( $fx['admin'], 'GET', '/dashboard/scopes' ) ), true ), 'not even to administrators' );
	$wpdb->update( Schema::table( 'classes' ), array( 'status' => 'active' ), array( 'id' => $fx['class_a2'] ) );

	// ---- Needs attention.
	remove_filter( 'ohmylms_dashboard_at_risk_provider', array( \OhMyLMS\Dashboard\Widgets\AtRiskCount::class, 'provide' ) );
	$na_sa = call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) );
	ok( 200 === $na_sa->get_status(), 'needs attention answers 200 to the school administrator' );
	ok( 'miss' === $na_sa->get_headers()['X-OhMyLMS-Cache'], 'the first request computes' );
	ok( 'private, no-store' === $na_sa->get_headers()['Cache-Control'], 'responses are private' );
	ok( $a === $na_sa->get_data()['scope']['id'] && 30 === $na_sa->get_data()['period']['days'], 'the response names its scope and period' );
	$items = items( $na_sa );
	ok( isset( $items['grading'] ) && 1 === $items['grading']['count'], 'one submission waits more than 3 days in school A' );
	ok( false !== strpos( $items['grading']['detail'], $tag . ' Essay' ), 'the item names the assignment' );
	ok( null === $items['grading']['action'], 'no admin link for a school administrator' );
	ok( ! isset( $items['failed_payments'] ) && ! isset( $items['manual_approval'] ), 'no commerce items for school staff' );
	ok( ! isset( $items['at_risk'] ), 'no at-risk item without a provider' );
	$stall = 'stalling_course:' . $fx['course_1'];
	ok( isset( $items[ $stall ] ), 'course one is stalling in school A' );
	ok( false !== strpos( $items[ $stall ]['detail'], 'Lesson Two' ) && false !== strpos( $items[ $stall ]['detail'], '50%' ), 'most learners stop at lesson two (3 of 6)' );
	ok( false !== strpos( $items[ $stall ]['detail'], '17%' ), 'one of six finished' );
	$missed = 'missed_question:' . $fx['question'];
	ok( isset( $items[ $missed ] ) && false !== strpos( $items[ $missed ]['title'], '80%' ), 'the question four of five learners missed is listed' );
	ok( $items[ $missed ]['count'] === 4, 'its count is the wrong answers' );
	$keys = array_keys( $items );
	ok( 'grading' === $keys[0], 'items are ranked by urgency' );
	foreach ( $na_sa->get_data()['data']['items'] as $item ) {
		$text = wp_json_encode( $item );
		foreach ( array( 1, 2, 3, 4, 5, 6 ) as $i ) {
			ok( false === strpos( $text, 'S' . $i . ' ' . $tag ) && false === strpos( $text, $tag . '_s' . $i ), 'no learner names or logins in ' . $item['key'] );
		}
		ok( false === strpos( $text, 'capability' ), 'capabilities are not sent' );
	}

	$na_admin_a = items( call( $fx['admin'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) ) );
	ok( '/assignments/' . $fx['assignment'] . '/submissions' === $na_admin_a['grading']['action']['route'], 'administrators get the grading route' );
	ok( 'Grade now' === $na_admin_a['grading']['action']['label'], 'with its label' );
	ok( '/reports/courses/' . $fx['course_1'] === $na_admin_a[ $stall ]['action']['route'], 'and the course report route' );
	ok( '/content-hub/question-bank/' . $fx['question'] === $na_admin_a[ $missed ]['action']['route'], 'and the question editor route' );
	ok( ! isset( $na_admin_a['failed_payments'] ), 'commerce stays out of a school scope even for administrators' );

	$na_b = items( call( $fx['sb'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $b ) ) );
	ok( isset( $na_b['grading'] ) && 1 === $na_b['grading']['count'], 'school B counts only its own submission' );
	ok( ! isset( $na_b[ $stall ] ) && ! isset( $na_b[ $missed ] ), 'school B sees nothing of school A' );

	$na_a1 = items( call( $fx['teacher'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a1 ) ) );
	ok( isset( $na_a1[ $missed ] ) && isset( $na_a1['grading'] ), 'class A1 sees its question and submission' );
	ok( isset( $na_a1[ $stall ] ) && false !== strpos( $na_a1[ $stall ]['detail'], '60%' ), 'in class A1, 3 of its 5 learners stop at lesson two' );
	$na_a2 = items( call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a2 ) ) );
	ok( ! isset( $na_a2['grading'] ) && ! isset( $na_a2[ $missed ] ), 'class A2 (S6 only) has none of it' );

	$na_all = items( call( $fx['admin'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => 'all' ) ) );
	ok( isset( $na_all['failed_payments'] ) && $na_all['failed_payments']['count'] >= 1, 'all schools shows failed payments' );
	ok( isset( $na_all['manual_approval'] ) && $na_all['manual_approval']['count'] >= 1, 'and orders waiting for approval' );
	ok( '/orders' === $na_all['failed_payments']['action']['route'], 'which link to the orders' );
	ok( isset( $na_all['grading'] ) && $na_all['grading']['count'] >= 2, 'all schools counts both waiting submissions' );

	// ---- Caching.
	$again = call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) );
	ok( 'hit' === $again->get_headers()['X-OhMyLMS-Cache'], 'the second request is served from the cache' );
	ok( $again->get_data()['generated_at'] === $na_sa->get_data()['generated_at'], 'with the original time' );
	ok( 'hit' === call( $fx['admin'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) )->get_headers()['X-OhMyLMS-Cache'], 'the cache is shared by everyone who may see the scope' );
	ok( 'miss' === call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a, 'period' => 7 ) )->get_headers()['X-OhMyLMS-Cache'], 'periods are cached apart' );
	ok( 'miss' === call( $fx['sa'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => $a ) )->get_headers()['X-OhMyLMS-Cache'], 'widgets are cached apart' );
	$key_a = Cache::key( 'pulse', $scope_a, new Period( 30 ) );
	ok( $key_a === Cache::key( 'pulse', ScopeResolver::resolve( $a, $fx['admin'] ), new Period( 30 ) ), 'the cache key does not depend on the user' );
	ok( $key_a !== Cache::key( 'pulse', $scope_a1, new Period( 30 ) ), 'but on the scope' );
	$version = (int) get_option( Cache::VERSION_OPTION, 1 );
	do_action( 'ohmylms_after_assignment_review', $fx['assignment'], $fx['course_1'], 0, 'passed' );
	ok( (int) get_option( Cache::VERSION_OPTION, 1 ) === $version + 1, 'grading invalidates the cache' );
	ok( 'miss' === call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) )->get_headers()['X-OhMyLMS-Cache'], 'after which the data is computed again' );
	add_filter( 'ohmylms_dashboard_cache_ttl', '__return_zero' );
	ok( 'miss' === call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) )->get_headers()['X-OhMyLMS-Cache'], 'a zero lifetime disables the cache' );
	remove_filter( 'ohmylms_dashboard_cache_ttl', '__return_zero' );

	// ---- Snoozes.
	$snooze = call( $fx['sa'], 'POST', '/dashboard/widgets/needs-attention/snoozes', array( 'scope' => $a, 'key' => 'grading' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( 200 === $snooze->get_status() && 'grading' === $snooze->get_data()['key'], 'snoozing answers 200' );
	$until = strtotime( $snooze->get_data()['until'] );
	ok( abs( $until - ( time() + 7 * DAY_IN_SECONDS ) ) < 60, 'a snooze lasts seven days' );
	$after = call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) );
	ok( ! isset( items( $after )['grading'] ) && 1 === $after->get_data()['data']['snoozed'], 'the snoozed item is hidden and counted' );
	ok( 'hit' === $after->get_headers()['X-OhMyLMS-Cache'], 'snoozing does not touch the shared cache' );
	ok( isset( items( call( $fx['admin'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) ) )['grading'] ), 'snoozes are per user' );
	ok( isset( items( call( $fx['teacher'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a1 ) ) )['grading'] ), 'and per scope' );
	ok( 1 === count( Snoozes::active( $fx['sa'] ) ) && array() === Snoozes::active( $fx['sa'], time() + 8 * DAY_IN_SECONDS ), 'snoozes expire after seven days' );
	is_error( call( $fx['sa'], 'POST', '/dashboard/widgets/needs-attention/snoozes', array( 'scope' => $b, 'key' => 'grading' ) ), 403, 'ohmylms_dashboard_forbidden_scope', 'snoozing outside the scopes' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	is_error( call( $fx['sa'], 'POST', '/dashboard/widgets/needs-attention/snoozes', array( 'scope' => $a, 'key' => 'Bad Key!' ) ), 400, 'rest_invalid_param', 'malformed key' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	is_error( call( $fx['sa'], 'POST', '/dashboard/widgets/needs-attention/snoozes', array( 'key' => 'grading' ) ), 400, 'rest_missing_callback_param', 'snoozing needs a scope' );
	$wake = call( $fx['sa'], 'DELETE', '/dashboard/widgets/needs-attention/snoozes', array( 'scope' => $a, 'key' => 'grading' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( 200 === $wake->get_status() && true === $wake->get_data()['removed'], 'a snooze can be undone' );
	ok( isset( items( call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) ) )['grading'] ), 'the item is back' );
	ok( '' === get_user_meta( $fx['sa'], Snoozes::META_KEY, true ), 'no meta is left behind' );
	ok( false === call( $fx['sa'], 'DELETE', '/dashboard/widgets/needs-attention/snoozes', array( 'scope' => $a, 'key' => 'grading' ) )->get_data()['removed'], 'undoing twice removes nothing' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

	// ---- At-risk provider hook.
	$provider = new class() implements AtRiskProvider {
		/**
		 * Count.
		 *
		 * @param Scope  $scope  Scope.
		 * @param Period $period Period.
		 * @return int
		 */
		public function high_risk_count( Scope $scope, Period $period ) {
			return $scope->is_all() ? 9 : count( (array) $scope->learner_ids() ) - 4;
		}
		/**
		 * Route.
		 *
		 * @param Scope $scope Scope.
		 * @return string
		 */
		public function route( Scope $scope ) {
			return '/dashboard';
		}
	};
	$give = static function () use ( $provider ) {
		return $provider;
	};
	add_filter( 'ohmylms_dashboard_at_risk_provider', $give );
	Cache::flush();
	$risk = items( call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) ) );
	ok( isset( $risk['at_risk'] ) && 2 === $risk['at_risk']['count'], 'a registered provider adds the at-risk item for the scope' );
	ok( '/dashboard' === $risk['at_risk']['action']['route'], 'its route is offered to school staff with dashboard access' );
	remove_filter( 'ohmylms_dashboard_at_risk_provider', $give );
	Cache::flush();
	add_filter( 'ohmylms_dashboard_at_risk_provider', array( \OhMyLMS\Dashboard\Widgets\AtRiskCount::class, 'provide' ) );
	$built_in = items( call( $fx['sa'], 'GET', '/dashboard/widgets/needs-attention', array( 'scope' => $a ) ) );
	ok( isset( $built_in['at_risk'] ) && 1 === $built_in['at_risk']['count'] && '/dashboard?scope=' . $a === $built_in['at_risk']['action']['route'], 'the built-in at-risk widget counts S6 (inactive 20 days) in school A' );
	Cache::flush();

	// ---- Pulse.
	$pulse_a = call( $fx['sa'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => $a ) );
	ok( 200 === $pulse_a->get_status(), 'pulse answers 200' );
	$m = metrics( $pulse_a );
	ok( ! isset( $m['revenue'] ) && ! isset( $m['renewals_at_risk'] ), 'no revenue or renewals for school staff' );
	ok( null === $pulse_a->get_data()['data']['currency'], 'and no currency' );
	ok( 1 === $m['completions']['value'] && 0 === $m['completions']['previous'], 'school A completed one course in the period' );
	ok( 30 === count( $m['completions']['series'] ) && 30 === count( $pulse_a->get_data()['data']['dates'] ), 'one series value per day' );
	ok( 1 === end( $m['completions']['series'] ), 'the completion is today' );
	ok( 5 === $m['active_learners']['value'], 'five learners of school A were active this week' );
	ok( 0 === $m['active_learners']['previous'], 'none in the same week of the previous period' );
	ok( max( $m['active_learners']['series'] ) >= 1, 'active learners per day are charted' );
	$m_b = metrics( call( $fx['sb'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => $b ) ) );
	ok( 1 === $m_b['completions']['value'] && 1 === $m_b['active_learners']['value'], 'school B counts its own learners only' );
	$m_a2 = metrics( call( $fx['sa'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => $a2 ) ) );
	ok( 0 === $m_a2['completions']['value'] && 0 === $m_a2['active_learners']['value'], 'class A2 counts S6 only (enrolled 20 days ago, idle since)' );
	$pulse_all = call( $fx['admin'], 'GET', '/dashboard/widgets/pulse', array( 'scope' => 'all', 'period' => 7 ) );
	$m_all     = metrics( $pulse_all );
	ok( isset( $m_all['revenue'] ) && 'money' === $m_all['revenue']['format'] && 7 === count( $m_all['revenue']['series'] ), 'administrators see revenue per day' );
	ok( isset( $m_all['renewals_at_risk'] ) && isset( $m_all['renewals_at_risk']['on_hold'] ), 'and renewals at risk' );
	ok( is_array( $pulse_all->get_data()['data']['currency'] ) && '' !== $pulse_all->get_data()['data']['currency']['code'], 'with the currency' );
	ok( $m_all['completions']['value'] >= 2, 'all schools count every completion' );

	// ---- The dashboard page stays open to staff, and only it.
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	$GLOBALS['pagenow']        = 'admin.php';
	$GLOBALS['current_screen'] = WP_Screen::get( 'toplevel_page_' . OHMYLMS_SLUG );
	$_GET['page']              = OHMYLMS_SLUG;
	ok( false === Access::keep_dashboard_open( true, get_userdata( $fx['sa'] ) ), 'school administrators may open the Dashboard page' );
	ok( true === Access::keep_dashboard_open( true, get_userdata( $fx['outsider'] ) ), 'subscribers may not' );
	$_GET['page'] = 'ohmylms-content';
	ok( true === Access::keep_dashboard_open( true, get_userdata( $fx['sa'] ) ), 'other OhMyLMS pages stay closed to staff' );
	unset( $_GET['page'], $GLOBALS['current_screen'] );
	ok( in_array( Access::CAPABILITY, (array) apply_filters( 'ohmylms_app_capabilities', array( 'manage_options' ) ), true ), 'the app learns the capability' );

	echo "Dashboard REST integration: {$checks} checks passed.\n";
} catch ( Throwable $error ) {
	echo $error->getMessage(), "\n", $error->getFile(), ':', $error->getLine(), "\n";
	$failed = true;
} finally {
	wp_set_current_user( 0 );
	dashfx_delete( $tag );
	$left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_login LIKE %s', $wpdb->users, $wpdb->esc_like( $tag ) . '%' ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE post_title LIKE %s', $wpdb->posts, $wpdb->esc_like( $tag ) . '%' ) )
		+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE name LIKE %s', Schema::table( 'schools' ), $wpdb->esc_like( $tag ) . '%' ) );
	echo 0 === $left ? "Fixtures removed.\n" : "WARNING: {$left} fixture rows left.\n";
}
exit( empty( $failed ) ? 0 : 1 );
