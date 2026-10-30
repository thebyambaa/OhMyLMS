<?php
/**
 * Gamification REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It
 * snapshots every gamification option first and restores it in `finally`, creates only tagged
 * temporary users and attachments, and removes the achievement rows of those users.
 *
 * Usage (set OHMYLMS_GAMIFICATION_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/gamification-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.DirectDatabaseQuery

use OhMyLMS\Engagement\Badge;
use OhMyLMS\Engagement\Leaderboard;
use OhMyLMS\Engagement\Level;
use OhMyLMS\Engagement\Point;
use OhMyLMS\Engagement\Reward;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_GAMIFICATION_TEST' ) ) {
	exit( "Set OHMYLMS_GAMIFICATION_TEST=1 to run the gamification REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks   = 0;
$user_ids = array();
$posts    = array();
$tag      = 'gmtest' . strtolower( wp_generate_password( 6, false, false ) );
$options  = array(
	'ohmylms_integrations',
	'ohmylms_point_settings',
	'ohmylms_level_settings',
	'ohmylms_badge_settings',
	'ohmylms_reward_settings',
	'ohmylms_leaderboard_settings',
	'ohmylms_levels',
	'ohmylms_badges',
);
$snapshot = array();
foreach ( $options as $name ) {
	$snapshot[ $name ] = get_option( $name, null );
}
$fired = array( 'points_saved' => 0 );
add_action(
	'ohmylms_point_settings_updated',
	static function () use ( &$fired ) {
		++$fired['points_saved'];
	}
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
 * Dispatch a REST request under /ohmylms/v1.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected error code.
 * @param string           $label    Description.
 */
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	$got  = isset( $data['code'] ) ? $data['code'] : '';
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got );
}

/**
 * Count the achievement rows of a user.
 *
 * @param int    $user_id User.
 * @param string $type    Achievement type.
 * @param string $status  Row status.
 * @return int
 */
function rows_of( $user_id, $type, $status = 'active' ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND type = %s AND status = %s', $wpdb->prefix . 'ohmylms_user_achievement', $user_id, $type, $status ) );
}

try {
	$admins = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	ok( ! empty( $admins ), 'an administrator exists' );
	$admin = (int) $admins[0];

	$subscriber = wp_insert_user(
		array(
			'user_login' => $tag . '_sub',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '@example.invalid',
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $subscriber ), 'temporary subscriber created' );
	$user_ids[] = $subscriber;
	$learner    = wp_insert_user(
		array(
			'user_login'   => $tag . '_learner',
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_learner@example.invalid',
			'display_name' => $tag . ' Learner',
			'role'         => 'subscriber',
		)
	);
	ok( ! is_wp_error( $learner ), 'temporary learner created' );
	$user_ids[] = $learner;

	$image = wp_insert_attachment(
		array(
			'post_title'     => $tag . ' image',
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
		)
	);
	$pdf   = wp_insert_attachment(
		array(
			'post_title'     => $tag . ' document',
			'post_mime_type' => 'application/pdf',
			'post_status'    => 'inherit',
		)
	);
	ok( $image && $pdf, 'attachments created' );
	$posts[] = $image;
	$posts[] = $pdf;

	// Start from a known state: add-on on, everything else default.
	$integrations = is_array( $snapshot['ohmylms_integrations'] ) ? $snapshot['ohmylms_integrations'] : array();
	$integrations['gamification']['is_enable'] = 1;
	update_option( 'ohmylms_integrations', $integrations );
	foreach ( array( 'ohmylms_point_settings', 'ohmylms_level_settings', 'ohmylms_badge_settings', 'ohmylms_reward_settings', 'ohmylms_leaderboard_settings', 'ohmylms_levels', 'ohmylms_badges' ) as $name ) {
		delete_option( $name );
	}

	// Routes and permission callbacks.
	wp_set_current_user( $admin );
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	$new    = array(
		'/gamification/settings',
		'/gamification/settings/points',
		'/gamification/settings/levels',
		'/gamification/settings/badges',
		'/gamification/settings/leaderboard',
		'/gamification/settings/rewards',
		'/gamification/options',
		'/gamification/points/adjustments',
		'/gamification/levels',
		'/gamification/levels/(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)',
		'/gamification/badges',
		'/gamification/badges/(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)',
		'/gamification/badges/(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)/awards',
		'/gamification/badges/(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)/awards/(?P<user_id>[\d]+)',
		'/engagement/settings/(?P<group_id>streak|xp|score|mastery)',
	);
	foreach ( $new as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], "real permission_callback on $route" );
		}
	}
	foreach ( array( '/engagement/badges', '/engagement/levels', '/engagement/settings/(?P<group_id>[\w-]+)' ) as $gone ) {
		ok( ! isset( $routes[ '/ohmylms/v1' . $gone ] ), "old route is gone: $gone" );
	}

	// Anonymous callers get 401, learners 403, on every route and method.
	$denied = array(
		array( 'GET', 'gamification/settings' ),
		array( 'GET', 'gamification/settings/points' ),
		array( 'PUT', 'gamification/settings/points' ),
		array( 'PUT', 'gamification/settings/rewards' ),
		array( 'GET', 'gamification/options' ),
		array( 'POST', 'gamification/points/adjustments' ),
		array( 'GET', 'gamification/levels' ),
		array( 'POST', 'gamification/levels' ),
		array( 'DELETE', 'gamification/levels/x' ),
		array( 'GET', 'gamification/badges' ),
		array( 'POST', 'gamification/badges' ),
		array( 'PUT', 'gamification/badges/x' ),
		array( 'POST', 'gamification/badges/x/awards' ),
		array( 'DELETE', 'gamification/badges/x/awards/1' ),
		array( 'GET', 'engagement/settings/streak' ),
		array( 'POST', 'engagement/settings/streak' ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
	) as $who => $status ) {
		wp_set_current_user( $who );
		foreach ( $denied as $case ) {
			$body = in_array( $case[0], array( 'POST', 'PUT' ), true ) ? array(
				'name'      => 'x',
				'points'    => 5,
				'user_id'   => $learner,
				'reference' => 'denied',
			) : array();
			$got  = call_api( $case[0], $case[1], $body )->get_status();
			ok( $status === $got, "{$case[0]} {$case[1]} as user $who: expected $status, got $got" );
		}
	}
	wp_set_current_user( $admin );

	// Defaults.
	$all = call_api( 'GET', 'gamification/settings' )->get_data();
	ok( true === $all['integration_enabled'], 'integration flag reflects Add-ons' );
	ok( false === $all['points']['enabled'] && 13 === count( $all['points']['rules'] ), 'points default: off, one rule per event' );
	ok( false === $all['levels']['enabled'] && false === $all['badges']['enabled'], 'levels and badges default off' );
	ok( 'completion_rate' === $all['leaderboard']['mode'] && 10 === $all['leaderboard']['limit'] && 0 === $all['leaderboard']['threshold'], 'leaderboard defaults' );
	ok( false === $all['rewards']['purchase_with_points'], 'rewards default off' );
	$lesson_rule = null;
	foreach ( $all['points']['rules'] as $rule ) {
		if ( 'lesson_complete' === $rule['event'] ) {
			$lesson_rule = $rule;
		}
	}
	ok( 5 === $lesson_rule['points'] && null === $lesson_rule['threshold'] && false === $lesson_rule['supports_threshold'] && '' !== $lesson_rule['label'], 'rule defaults include display fields' );
	$options_data = call_api( 'GET', 'gamification/options' )->get_data();
	ok( 3 === count( $options_data['metrics'] ) && 6 === count( $options_data['operators'] ) && 100000 === $options_data['limits']['max_points'], 'options expose allow-lists and limits' );

	// Points: validation.
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event'  => 'lesson_complete',
						'points' => 100001,
					),
				),
			)
		),
		400,
		'rest_invalid_param',
		'points above the maximum'
	);
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event'  => 'lesson_complete',
						'points' => -1,
					),
				),
			)
		),
		400,
		'rest_invalid_param',
		'negative points'
	);
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event'  => 'lesson_complete',
						'points' => 2.5,
					),
				),
			)
		),
		400,
		'rest_invalid_param',
		'fractional points'
	);
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event'  => 'made_up',
						'points' => 1,
					),
				),
			)
		),
		400,
		'rest_invalid_param',
		'unknown event'
	);
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event' => 'lesson_complete',
						'bogus' => 1,
					),
				),
			)
		),
		400,
		'rest_invalid_param',
		'unknown rule field'
	);
	ok_error( call_api( 'PUT', 'gamification/settings/points', array( 'bogus' => 1 ) ), 400, 'rest_invalid_param', 'unknown section field' );
	ok_error(
		call_api( 'PUT', 'gamification/settings/points', array( 'rules' => array( array( 'event' => 'lesson_complete' ), array( 'event' => 'lesson_complete' ) ) ) ),
		422,
		'ohmylms_gamification_duplicate_event',
		'duplicate event'
	);
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event'     => 'lesson_complete',
						'threshold' => 50,
					),
				),
			)
		),
		422,
		'ohmylms_gamification_threshold_unsupported',
		'threshold on an event without one'
	);
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event'     => 'quiz_passing_mark',
						'threshold' => 101,
					),
				),
			)
		),
		400,
		'rest_invalid_param',
		'threshold above 100'
	);
	ok_error(
		call_api(
			'PUT',
			'gamification/settings/points',
			array(
				'rules' => array(
					array(
						'event' => 'lesson_complete',
						'email' => array(
							'enabled' => true,
							'subject' => '',
							'body'    => 'Hi',
						),
					),
				),
			)
		),
		422,
		'ohmylms_gamification_email_incomplete',
		'email on without a subject'
	);
	ok( 0 === $fired['points_saved'] && false === get_option( 'ohmylms_point_settings', false ), 'rejected requests changed nothing' );

	// Points: save, partial update, sanitising.
	$saved = call_api(
		'PUT',
		'gamification/settings/points',
		array(
			'enabled' => true,
			'rules'   => array(
				array(
					'event'   => 'lesson_complete',
					'enabled' => true,
					'points'  => 7,
					'email'   => array(
						'enabled' => true,
						'subject' => "<b>Well</b> done\n",
						'body'    => '<p>Nice</p><script>alert(1)</script>',
					),
				),
				array(
					'event'     => 'quiz_passing_mark',
					'enabled'   => true,
					'threshold' => 80,
				),
			),
		)
	);
	ok( 200 === $saved->get_status() && true === $saved->get_data()['enabled'], 'points saved' );
	ok( 1 === $fired['points_saved'], 'ohmylms_point_settings_updated fired once' );
	$data = $saved->get_data();
	$map  = array();
	foreach ( $data['rules'] as $rule ) {
		$map[ $rule['event'] ] = $rule;
	}
	ok( 7 === $map['lesson_complete']['points'] && true === $map['lesson_complete']['enabled'], 'lesson rule saved' );
	ok( 'Well done' === $map['lesson_complete']['email']['subject'], 'email subject is plain text' );
	ok( false === strpos( $map['lesson_complete']['email']['body'], '<script' ) && false !== strpos( $map['lesson_complete']['email']['body'], '<p>Nice</p>' ), 'email body is sanitised with kses' );
	ok( 80 === $map['quiz_passing_mark']['threshold'] && 5 === $map['quiz_passing_mark']['points'], 'untouched fields keep their value' );
	ok( false === $map['submit_quiz']['enabled'] && 5 === $map['submit_quiz']['points'], 'events not listed are unchanged' );
	$stored = get_option( 'ohmylms_point_settings' );
	ok( ! isset( $stored['rules'][0]['label'] ) && ! isset( $stored['rules'][0]['supports_threshold'] ), 'display fields are not stored' );
	$partial = call_api( 'PATCH', 'gamification/settings/points', array( 'enabled' => false ) );
	ok( false === $partial->get_data()['enabled'] && 7 === Point::get_points_for_slug( 'lesson_complete' ), 'a partial update changes only what was sent' );
	call_api( 'PUT', 'gamification/settings/points', array( 'enabled' => true ) );

	// Point readers.
	ok( Point::maybe_enable(), 'points are usable' );
	ok( Point::maybe_met_rules( 'lesson_complete' ) && ! Point::maybe_met_rules( 'submit_quiz' ), 'only enabled events qualify' );
	ok( Point::maybe_met_rules( 'quiz_passing_mark', 80 ) && ! Point::maybe_met_rules( 'quiz_passing_mark', 79 ), 'threshold is a minimum' );
	ok( Point::maybe_enable_email_for_a_slug( 'lesson_complete' ) && ! Point::maybe_enable_email_for_a_slug( 'submit_quiz' ), 'email flag per event' );
	ok( ! Point::maybe_met_rules( 'made_up' ) && 0 === Point::get_points_for_slug( 'made_up' ), 'unknown events never award' );
	ok( false === Point::add_points( $learner, 'point', \OhMyLMS\Engagement\GamificationRegistry::MAX_POINTS + 1, 'bounds' ), 'amount above the maximum is refused' );

	// Leaderboard and rewards.
	foreach ( array(
		array( 'mode', 'rank_by_vibes' ),
		array( 'limit', 0 ),
		array( 'limit', 101 ),
		array( 'threshold', 101 ),
		array( 'threshold', -1 ),
	) as $bad ) {
		ok_error( call_api( 'PUT', 'gamification/settings/leaderboard', array( $bad[0] => $bad[1] ) ), 400, 'rest_invalid_param', "leaderboard {$bad[0]}={$bad[1]}" );
	}
	$board = call_api(
		'PUT',
		'gamification/settings/leaderboard',
		array(
			'enabled'   => true,
			'mode'      => 'fastest_time',
			'limit'     => 3,
			'threshold' => 40,
		)
	)->get_data();
	ok( true === $board['enabled'] && 'fastest_time' === $board['mode'] && 3 === $board['limit'] && 40 === $board['threshold'], 'leaderboard saved' );
	ok( 'fastest_time' === Leaderboard::get_leaderboard_rule() && 3 === Leaderboard::get_students_number() && 40 === Leaderboard::get_threshold() && Leaderboard::maybe_enable(), 'leaderboard readers follow the settings' );
	$rows = Leaderboard::public_rows(
		array(
			array(
				'student_id'       => 9,
				'name'             => 'Ann',
				'email'            => 'ann@example.org',
				'position'         => 1,
				'position_in_text' => '1st',
				'user_login'       => 'ann',
			),
		)
	);
	$keys = array_keys( $rows[0] );
	sort( $keys );
	ok( array( 'name', 'position', 'position_in_text', 'student_id' ) === $keys, 'learner leaderboard rows carry no private data' );
	ok_error( call_api( 'PUT', 'gamification/settings/rewards', array( 'purchase_with_points' => 'maybe' ) ), 400, 'rest_invalid_param', 'rewards flag must be boolean' );
	ok( ! Reward::maybe_met_rules( 'purchase_course' ), 'rewards off by default' );
	call_api(
		'PUT',
		'gamification/settings/rewards',
		array(
			'enabled'              => true,
			'purchase_with_points' => true,
		)
	);
	ok( Reward::maybe_met_rules( 'purchase_course' ) && ! Reward::maybe_met_rules( 'other' ), 'reward event follows the settings' );

	// Levels.
	call_api( 'PUT', 'gamification/settings/levels', array( 'enabled' => true ) );
	ok( Level::maybe_enable(), 'levels usable' );
	ok_error( call_api( 'POST', 'gamification/levels', array( 'points' => 10 ) ), 400, 'rest_missing_callback_param', 'level needs a name' );
	ok_error( call_api( 'POST', 'gamification/levels', array( 'name' => 'A' ) ), 400, 'rest_missing_callback_param', 'level needs points' );
	ok_error(
		call_api(
			'POST',
			'gamification/levels',
			array(
				'name'   => 'A',
				'points' => -1,
			)
		),
		400,
		'rest_invalid_param',
		'negative threshold'
	);
	ok_error(
		call_api(
			'POST',
			'gamification/levels',
			array(
				'name'   => 'A',
				'points' => 1.5,
			)
		),
		400,
		'rest_invalid_param',
		'fractional threshold'
	);
	ok_error(
		call_api(
			'POST',
			'gamification/levels',
			array(
				'name'   => 'A',
				'points' => 1,
				'color'  => 'red',
			)
		),
		400,
		'rest_invalid_param',
		'colour must be hex'
	);
	ok_error(
		call_api(
			'POST',
			'gamification/levels',
			array(
				'name'   => '   ',
				'points' => 1,
			)
		),
		422,
		'ohmylms_level_invalid_name',
		'blank name'
	);
	$gold = call_api(
		'POST',
		'gamification/levels',
		array(
			'name'   => $tag . ' Gold',
			'points' => 20,
			'color'  => '#aa8800',
		)
	);
	ok( 201 === $gold->get_status() && 0 === strpos( $gold->get_data()['slug'], $tag ) && false !== strpos( $gold->get_headers()['Location'], '/gamification/levels/' . $gold->get_data()['slug'] ), 'level created with a Location' );
	ok( '#ffffff' === $gold->get_data()['text_color'] && 0 === $gold->get_data()['earned_count'], 'level defaults' );
	$gold_slug = $gold->get_data()['slug'];
	$bronze    = call_api(
		'POST',
		'gamification/levels',
		array(
			'name'   => $tag . ' Bronze',
			'points' => 0,
		)
	);
	ok( 201 === $bronze->get_status(), 'a level may start at 0 points' );
	$silver = call_api(
		'POST',
		'gamification/levels',
		array(
			'name'   => $tag . ' Silver',
			'points' => 10,
		)
	)->get_data();
	$err    = call_api(
		'POST',
		'gamification/levels',
		array(
			'name'   => 'Dup',
			'points' => 10,
		)
	);
	ok_error( $err, 422, 'ohmylms_level_points_taken', 'two levels cannot share a threshold' );
	ok( $silver['slug'] === $err->get_data()['data']['conflicting_slug'], 'the conflicting level is named' );
	$same = call_api(
		'POST',
		'gamification/levels',
		array(
			'name'   => $tag . ' Silver',
			'points' => 15,
		)
	);
	ok( 201 === $same->get_status() && $silver['slug'] . '-2' === $same->get_data()['slug'], 'duplicate names get distinct slugs' );
	$list = call_api( 'GET', 'gamification/levels' );
	$pts  = wp_list_pluck( $list->get_data(), 'points' );
	ok( array( 0, 10, 15, 20 ) === $pts && '4' === $list->get_headers()['X-WP-Total'] && '1' === $list->get_headers()['X-WP-TotalPages'], 'levels are listed lowest first with totals' );
	ok_error( call_api( 'PUT', 'gamification/levels/' . $gold_slug, array( 'points' => 10 ) ), 422, 'ohmylms_level_points_taken', 'update cannot collide' );
	$moved = call_api(
		'PATCH',
		'gamification/levels/' . $gold_slug,
		array(
			'points'      => 30,
			'description' => '<b>Top</b>',
		)
	);
	ok( 200 === $moved->get_status() && $gold_slug === $moved->get_data()['slug'] && 30 === $moved->get_data()['points'] && 'Top' === $moved->get_data()['description'], 'update keeps the slug and sanitises' );
	$ladder = Level::get_levels();
	ok( $ladder[3]['slug'] === $gold_slug, 'order follows the new threshold' );
	ok( 200 === call_api( 'PUT', 'gamification/levels/' . $gold_slug, array( 'points' => 30 ) )->get_status(), 'saving the same threshold is allowed' );
	ok_error( call_api( 'GET', 'gamification/levels/nope' ), 404, 'ohmylms_level_not_found', 'unknown level' );
	ok_error( call_api( 'PUT', 'gamification/levels/nope', array( 'name' => 'x' ) ), 404, 'ohmylms_level_not_found', 'update unknown level' );
	ok_error( call_api( 'DELETE', 'gamification/levels/nope' ), 404, 'ohmylms_level_not_found', 'delete unknown level' );
	ok_error( call_api( 'GET', 'gamification/levels', array( 'page' => 2 ) ), 400, 'ohmylms_gamification_invalid_page', 'page past the end' );
	ok_error( call_api( 'GET', 'gamification/levels', array( 'per_page' => 101 ) ), 400, 'rest_invalid_param', 'per_page above 100' );
	$paged = call_api( 'GET', 'gamification/levels', array( 'per_page' => 3 ) );
	ok( 3 === count( $paged->get_data() ) && '2' === $paged->get_headers()['X-WP-TotalPages'] && ! empty( $paged->get_headers()['Link'] ), 'levels are paged' );
	$levels_before = get_option( 'ohmylms_levels' );
	$crowded       = array();
	for ( $i = 0; $i < 50; $i++ ) {
		$crowded[] = array(
			'slug'   => 'crowd-' . $i,
			'name'   => 'C',
			'points' => 1000 + $i,
		);
	}
	update_option( 'ohmylms_levels', $crowded );
	ok_error(
		call_api(
			'POST',
			'gamification/levels',
			array(
				'name'   => 'Too many',
				'points' => 5,
			)
		),
		422,
		'ohmylms_level_limit',
		'level limit'
	);
	update_option( 'ohmylms_levels', $levels_before );

	// Badges.
	call_api( 'PUT', 'gamification/settings/badges', array( 'enabled' => true ) );
	ok( Badge::maybe_enable(), 'badges usable' );
	$criterion = array(
		'metric'   => 'points',
		'operator' => '>=',
		'value'    => 10,
	);
	$new_badge = array(
		'name'     => $tag . ' Starter',
		'criteria' => array( $criterion ),
	);
	ok_error( call_api( 'POST', 'gamification/badges', array( 'criteria' => array( $criterion ) ) ), 400, 'rest_missing_callback_param', 'badge needs a name' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'criteria' => array( array_merge( $criterion, array( 'metric' => 'passwords' ) ) ) ) ) ), 400, 'rest_invalid_param', 'metric must be allow-listed' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'criteria' => array( array_merge( $criterion, array( 'operator' => '; DROP' ) ) ) ) ) ), 400, 'rest_invalid_param', 'operator must be allow-listed' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'criteria' => array( array_merge( $criterion, array( 'value' => 1000001 ) ) ) ) ) ), 400, 'rest_invalid_param', 'criterion value above the maximum' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'criteria' => array( array_merge( $criterion, array( 'value' => 1.5 ) ) ) ) ) ), 400, 'rest_invalid_param', 'criterion value must be an integer' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'criteria' => array_fill( 0, 11, $criterion ) ) ) ), 400, 'rest_invalid_param', 'too many criteria' );
	ok_error(
		call_api(
			'POST',
			'gamification/badges',
			array(
				'name'     => 'x',
				'criteria' => array(),
			)
		),
		422,
		'ohmylms_badge_invalid_criteria',
		'a criteria badge needs a criterion'
	);
	ok_error(
		call_api(
			'POST',
			'gamification/badges',
			array(
				'name'         => 'x',
				'award_source' => 'streak',
				'criteria'     => array( $criterion ),
			)
		),
		422,
		'ohmylms_badge_criteria_not_allowed',
		'streak badges take no criteria'
	);
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'image_id' => 'https://evil.example/x.png' ) ) ), 400, 'rest_invalid_param', 'remote image URLs are not accepted' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'image_id' => $pdf ) ) ), 422, 'ohmylms_badge_invalid_image', 'an attachment that is not an image' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'image_id' => 99999999 ) ) ), 422, 'ohmylms_badge_invalid_image', 'a missing attachment' );
	ok_error( call_api( 'POST', 'gamification/badges', array_merge( $new_badge, array( 'image_id' => $subscriber ) ) ), 422, 'ohmylms_badge_invalid_image', 'a post that is not an attachment' );
	ok( array() === get_option( 'ohmylms_badges', array() ), 'rejected badges were not stored' );
	$badge = call_api(
		'POST',
		'gamification/badges',
		array_merge(
			$new_badge,
			array(
				'image_id'    => $image,
				'description' => '<i>First</i> steps',
			)
		)
	);
	ok( 201 === $badge->get_status() && 'custom' === $badge->get_data()['source'] && 'criteria' === $badge->get_data()['award_source'] && $image === $badge->get_data()['image_id'], 'badge created' );
	ok( 'First steps' === $badge->get_data()['description'] && array( $criterion ) === $badge->get_data()['criteria'], 'badge fields sanitised and kept' );
	$badge_slug = $badge->get_data()['slug'];
	$twin       = call_api( 'POST', 'gamification/badges', $new_badge );
	ok( $badge_slug . '-2' === $twin->get_data()['slug'] && 0 === $twin->get_data()['image_id'] && null === $twin->get_data()['image_url'], 'duplicate names get distinct slugs; no image is allowed' );
	$twin_slug = $twin->get_data()['slug'];
	$edited    = call_api(
		'PATCH',
		'gamification/badges/' . $badge_slug,
		array(
			'color'    => '#112233',
			'image_id' => null,
		)
	);
	ok( 200 === $edited->get_status() && 0 === $edited->get_data()['image_id'] && '#112233' === $edited->get_data()['color'] && $badge_slug === $edited->get_data()['slug'], 'badge update, image removed' );
	call_api( 'PATCH', 'gamification/badges/' . $badge_slug, array( 'image_id' => $image ) );
	$streak_list = call_api( 'GET', 'gamification/badges', array( 'source' => 'streak' ) )->get_data();
	ok( 3 === count( $streak_list ) && 'streak' === $streak_list[0]['source'], 'system streak badges are listed on request' );
	$custom_list = call_api( 'GET', 'gamification/badges' )->get_data();
	ok( 2 === count( $custom_list ) && 'custom' === $custom_list[0]['source'], 'custom badges are the default list' );
	ok( 5 === count( call_api( 'GET', 'gamification/badges', array( 'source' => 'all' ) )->get_data() ), 'source=all lists both' );
	ok_error( call_api( 'GET', 'gamification/badges', array( 'source' => 'other' ) ), 400, 'rest_invalid_param', 'source enum' );
	ok_error( call_api( 'PUT', 'gamification/badges/streak-7', array( 'name' => 'Hack' ) ), 409, 'ohmylms_badge_system', 'system badges are read-only' );
	ok_error( call_api( 'DELETE', 'gamification/badges/streak-7' ), 409, 'ohmylms_badge_system', 'system badges cannot be deleted' );
	ok_error( call_api( 'GET', 'gamification/badges/nope' ), 404, 'ohmylms_badge_not_found', 'unknown badge' );
	ok( false !== strpos( Badge::get_badges( false )[0]['image'], '' ) && array_key_exists( 'image', Badge::get_badges( false )[0] ), 'templates still receive an image key' );

	// Awards: idempotent and logged.
	$award = call_api( 'POST', 'gamification/badges/' . $badge_slug . '/awards', array( 'user_id' => $learner ) );
	ok( 201 === $award->get_status() && true === $award->get_data()['awarded'], 'badge awarded' );
	$again = call_api( 'POST', 'gamification/badges/' . $badge_slug . '/awards', array( 'user_id' => $learner ) );
	ok( 200 === $again->get_status() && false === $again->get_data()['awarded'] && 1 === rows_of( $learner, 'badge' ), 'awarding twice is a no-op' );
	$reason = $wpdb->get_var( $wpdb->prepare( 'SELECT reason FROM %i WHERE user_id = %d AND type = %s', $wpdb->prefix . 'ohmylms_user_achievement', $learner, 'badge' ) );
	ok( 'manual:' . $admin === $reason, 'the award log names the administrator' );
	ok( 1 === call_api( 'GET', 'gamification/badges/' . $badge_slug )->get_data()['earned_count'], 'earned_count counts learners' );
	ok_error( call_api( 'POST', 'gamification/badges/' . $badge_slug . '/awards', array( 'user_id' => 99999999 ) ), 404, 'ohmylms_gamification_user_not_found', 'award to an unknown user' );
	ok_error( call_api( 'POST', 'gamification/badges/nope/awards', array( 'user_id' => $learner ) ), 404, 'ohmylms_badge_not_found', 'award an unknown badge' );
	ok_error( call_api( 'POST', 'gamification/badges/' . $badge_slug . '/awards', array() ), 400, 'rest_missing_callback_param', 'user_id is required' );
	ok( in_array( $badge_slug, wp_list_pluck( Badge::get_all_badges_of_a_user( $learner ), 'slug' ), true ), 'the learner sees the badge' );
	$revoked = call_api( 'DELETE', 'gamification/badges/' . $badge_slug . '/awards/' . $learner );
	ok( 200 === $revoked->get_status() && 1 === $revoked->get_data()['revoked'] && 0 === rows_of( $learner, 'badge' ) && 1 === rows_of( $learner, 'badge', 'revoked' ), 'badge revoked, row kept' );
	$reason = $wpdb->get_var( $wpdb->prepare( 'SELECT reason FROM %i WHERE user_id = %d AND type = %s', $wpdb->prefix . 'ohmylms_user_achievement', $learner, 'badge' ) );
	ok( 0 === strpos( $reason, 'manual:' . $admin . ' | revoked:' . $admin . ':' ), 'the revoke is logged on the row' );
	ok( 0 === call_api( 'DELETE', 'gamification/badges/' . $badge_slug . '/awards/' . $learner )->get_data()['revoked'], 'revoking twice is a no-op' );
	ok( 201 === call_api( 'POST', 'gamification/badges/' . $badge_slug . '/awards', array( 'user_id' => $learner ) )->get_status(), 'a revoked badge can be awarded again' );

	// Point adjustments: idempotent, bounded, logged.
	$adjust = static function ( array $data ) {
		return call_api( 'POST', 'gamification/points/adjustments', $data );
	};
	$first = $adjust(
		array(
			'user_id'   => $learner,
			'points'    => 12,
			'reference' => $tag . '-a',
			'note'      => '<b>Bonus</b>',
		)
	);
	ok( 201 === $first->get_status() && true === $first->get_data()['created'] && 12 === $first->get_data()['balance'] && 12 === Point::get_total_points( $learner ), 'points added' );
	$replay = $adjust(
		array(
			'user_id'   => $learner,
			'points'    => 12,
			'reference' => $tag . '-a',
		)
	);
	ok( 200 === $replay->get_status() && false === $replay->get_data()['created'] && 12 === $replay->get_data()['balance'] && 1 === rows_of( $learner, 'point' ), 'the same reference is applied once' );
	$reason = $wpdb->get_var( $wpdb->prepare( 'SELECT reason FROM %i WHERE user_id = %d AND type = %s', $wpdb->prefix . 'ohmylms_user_achievement', $learner, 'point' ) );
	ok( 'manual:' . $tag . '-a:' . $admin . ':Bonus' === $reason, 'the adjustment log has reference, administrator and note' );
	ok_error(
		$adjust(
			array(
				'user_id'   => $learner,
				'points'    => -13,
				'reference' => $tag . '-b',
			)
		),
		422,
		'ohmylms_points_insufficient',
		'balance cannot go negative'
	);
	ok( 12 === Point::get_total_points( $learner ), 'a refused deduction changes nothing' );
	$minus = $adjust(
		array(
			'user_id'   => $learner,
			'points'    => -2,
			'reference' => $tag . '-c',
		)
	);
	ok( 201 === $minus->get_status() && 10 === $minus->get_data()['balance'], 'points removed' );
	ok_error(
		$adjust(
			array(
				'user_id'   => $learner,
				'points'    => 0,
				'reference' => $tag . '-d',
			)
		),
		422,
		'ohmylms_points_zero',
		'zero points'
	);
	ok_error(
		$adjust(
			array(
				'user_id'   => $learner,
				'points'    => 100001,
				'reference' => $tag . '-e',
			)
		),
		400,
		'rest_invalid_param',
		'points above the maximum'
	);
	ok_error(
		$adjust(
			array(
				'user_id'   => $learner,
				'points'    => 1.5,
				'reference' => $tag . '-f',
			)
		),
		400,
		'rest_invalid_param',
		'fractional points'
	);
	ok_error(
		$adjust(
			array(
				'user_id'   => $learner,
				'points'    => 1,
				'reference' => 'has space',
			)
		),
		400,
		'rest_invalid_param',
		'reference pattern'
	);
	ok_error(
		$adjust(
			array(
				'user_id' => $learner,
				'points'  => 1,
			)
		),
		400,
		'rest_missing_callback_param',
		'reference is required'
	);
	ok_error(
		$adjust(
			array(
				'user_id'   => 99999999,
				'points'    => 1,
				'reference' => $tag . '-g',
			)
		),
		404,
		'ohmylms_gamification_user_not_found',
		'unknown user'
	);

	// The hook chain awards levels and criteria badges after points are added.
	ok( 10 === Point::get_total_points( $learner ), 'balance is 10' );
	ok( in_array( $badge_slug, Badge::maybe_met_rules( $learner ), true ), 'a badge with points >= 10 qualifies at 10 points' );
	$level_slugs = Level::maybe_met_rules( $learner );
	ok( false === $level_slugs || ! in_array( $gold_slug, $level_slugs, true ), 'a higher level is not awarded early' );
	$current = Level::get_current_level_of_a_user( $learner );
	ok( is_array( $current ) && $silver['slug'] === $current['level_id'], 'the adjustment awarded the level reached (' . wp_json_encode( $current ) . ')' );
	$next = Level::get_next_level_of_a_user( $learner );
	ok( $silver['slug'] . '-2' === $next['level_id'] && 5 === $next['points_needed'] && 66 === $next['progress_percentage'] && 'points' === $next['rules'][0]['type'], 'next level progress' );

	// Delete.
	$deleted = call_api( 'DELETE', 'gamification/levels/' . $gold_slug );
	ok( 200 === $deleted->get_status() && true === $deleted->get_data()['deleted'] && 30 === $deleted->get_data()['previous']['points'], 'level deleted' );
	ok_error( call_api( 'GET', 'gamification/levels/' . $gold_slug ), 404, 'ohmylms_level_not_found', 'deleted level is gone' );
	ok( 200 === call_api( 'DELETE', 'gamification/badges/' . $twin_slug )->get_status(), 'badge deleted' );
	ok_error( call_api( 'DELETE', 'gamification/badges/' . $twin_slug ), 404, 'ohmylms_badge_not_found', 'badge deleted twice' );

	// Streak milestones still validate against the badge list; the legacy group routes still work.
	$streak = call_api( 'GET', 'engagement/settings/streak' );
	ok( 200 === $streak->get_status() && isset( $streak->get_data()['milestones'] ), 'streak settings route kept' );
	ok( 404 === call_api( 'GET', 'engagement/settings/point' )->get_status(), 'the point group is no longer served by the engagement route' );

	// Schema.
	$schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/gamification/badges' ) )->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'gamification-badge' === $schema['title'], 'badge schema header' );
	foreach ( array( 'slug', 'name', 'description', 'image_id', 'image_url', 'color', 'award_source', 'criteria', 'source', 'earned_count' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "badge schema property $property" );
	}
	ok( ! empty( $schema['properties']['slug']['readonly'] ) && ! empty( $schema['properties']['image_url']['readonly'] ), 'schema marks derived fields readonly' );
	$level_schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/gamification/levels' ) )->get_data()['schema'];
	ok( isset( $level_schema['properties']['points']['maximum'] ) && 'gamification-level' === $level_schema['title'], 'level schema' );
	$section_schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/gamification/settings/leaderboard' ) )->get_data()['schema'];
	ok( 'gamification-leaderboard' === $section_schema['title'] && isset( $section_schema['properties']['mode']['enum'] ), 'section schema' );

	fwrite( STDOUT, "OK gamification-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created, then put every option back.
	wp_set_current_user( 0 );
	foreach ( $user_ids as $user_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_achievement', array( 'user_id' => $user_id ), array( '%d' ) );
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	foreach ( $snapshot as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
		}
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
