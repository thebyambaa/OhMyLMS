<?php
/**
 * Gamification rewards settings REST integration test (`#/gamification?tab=rewards`).
 *
 * Runs against a WordPress install (the Local site by default). It snapshots the reward option and
 * restores it in `finally`, and creates only one tagged subscriber, removed afterwards.
 *
 * Usage (set OHMYLMS_GAMIFICATION_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/gamification-rewards-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

use OhMyLMS\Engagement\Reward;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_GAMIFICATION_TEST' ) ) {
	exit( "Set OHMYLMS_GAMIFICATION_TEST=1 to run the gamification rewards tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

$checks   = 0;
$user_ids = array();
$tag      = 'gmrewards' . strtolower( wp_generate_password( 6, false, false ) );
$snapshot = array(
	'ohmylms_reward_settings' => get_option( 'ohmylms_reward_settings', null ),
	'ohmylms_integrations'    => get_option( 'ohmylms_integrations', null ),
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
 * Dispatch a request to the rewards section.
 *
 * @param string $method HTTP method.
 * @param array  $data   JSON body.
 * @return WP_REST_Response
 */
function rewards_api( $method, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/gamification/settings/rewards' );
	if ( 'GET' !== $method ) {
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
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got . ' ' . wp_json_encode( $data ) );
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
	$subscriber = wp_insert_user(
		array(
			'user_login' => $tag . '_sub',
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_sub@example.invalid',
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $subscriber ), 'subscriber created' );
	$user_ids[] = $subscriber;
	delete_option( 'ohmylms_reward_settings' );
	$integrations                               = (array) get_option( 'ohmylms_integrations', array() );
	$integrations['gamification']['is_enable'] = true;
	update_option( 'ohmylms_integrations', $integrations );

	// Denied callers.
	foreach ( array( 0 => 401, $subscriber => 403 ) as $who => $status ) {
		wp_set_current_user( $who );
		ok( $status === rewards_api( 'GET' )->get_status(), "GET denied with $status" );
		ok( $status === rewards_api( 'PATCH', array( 'enabled' => true ) )->get_status(), "PATCH denied with $status" );
	}
	ok( null === get_option( 'ohmylms_reward_settings', null ), 'denied writes stored nothing' );

	wp_set_current_user( (int) $admins[0] );
	$first = rewards_api( 'GET' );
	$data  = $first->get_data();
	ok( 200 === $first->get_status() && false === $data['enabled'] && false === $data['purchase_with_points'], 'defaults off' );
	ok( preg_match( '/^[a-f0-9]{16}$/', $data['revision'] ), 'GET carries a revision' );
	$keys = array_keys( $data );
	sort( $keys );
	ok( array( 'enabled', 'purchase_with_points', 'revision' ) === $keys, 'only the reward fields and the revision' );

	// Schema validation.
	ok_error( rewards_api( 'PATCH', array( 'purchase_with_points' => 'maybe' ) ), 400, 'rest_invalid_param', 'flag must be boolean' );
	ok_error( rewards_api( 'PATCH', array( 'revision' => 'nothex' ) ), 400, 'rest_invalid_param', 'revision format' );
	ok_error( rewards_api( 'PATCH', array( 'rules' => array() ) ), 400, 'rest_invalid_param', 'legacy rules shape refused' );
	ok( null === get_option( 'ohmylms_reward_settings', null ), 'invalid writes stored nothing' );

	// Save with the loaded revision.
	$saved = rewards_api(
		'PATCH',
		array(
			'enabled'              => true,
			'purchase_with_points' => true,
			'revision'             => $data['revision'],
		)
	);
	ok( 200 === $saved->get_status() && true === $saved->get_data()['enabled'] && true === $saved->get_data()['purchase_with_points'], 'saved: ' . wp_json_encode( $saved->get_data() ) );
	ok( $saved->get_data()['revision'] !== $data['revision'], 'revision changes after a save' );
	ok( $saved->get_data()['revision'] === rewards_api( 'GET' )->get_data()['revision'], 'GET answers the saved revision' );
	ok( array( 'enabled' => true, 'purchase_with_points' => true ) === get_option( 'ohmylms_reward_settings' ), 'only known keys stored' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	ok( Reward::maybe_enable() && Reward::maybe_met_rules( 'purchase_course' ), 'front-end readers see purchases with points' );

	// A stale editor is refused without writing.
	$stale = rewards_api(
		'PATCH',
		array(
			'purchase_with_points' => false,
			'revision'             => $data['revision'],
		)
	);
	ok_error( $stale, 409, 'ohmylms_gamification_conflict', 'stale revision' );
	ok( $saved->get_data()['revision'] === $stale->get_data()['data']['revision'], 'conflict names the current revision' );
	ok( Reward::maybe_met_rules( 'purchase_course' ), 'stale save wrote nothing' );

	// Partial update; omitting the revision saves unconditionally (scripts, MCP).
	$partial = rewards_api( 'PATCH', array( 'purchase_with_points' => false ) );
	ok( 200 === $partial->get_status() && true === $partial->get_data()['enabled'] && false === $partial->get_data()['purchase_with_points'], 'partial update keeps enabled' );
	ok( Reward::maybe_enable() && ! Reward::maybe_met_rules( 'purchase_course' ), 'purchases off while rewards stay on' );
	$same = rewards_api(
		'PATCH',
		array(
			'purchase_with_points' => false,
			'revision'             => $partial->get_data()['revision'],
		)
	);
	ok( 200 === $same->get_status() && $partial->get_data()['revision'] === $same->get_data()['revision'], 'an unchanged save keeps the revision' );

	// Other sections share the revision contract.
	$board = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/gamification/settings/leaderboard' ) );
	ok( preg_match( '/^[a-f0-9]{16}$/', $board->get_data()['revision'] ), 'every section carries a revision' );

	// The add-on switch still governs the front end.
	$integrations['gamification']['is_enable'] = false;
	update_option( 'ohmylms_integrations', $integrations );
	rewards_api( 'PATCH', array( 'purchase_with_points' => true ) );
	ok( ! Reward::maybe_met_rules( 'purchase_course' ), 'purchases off while the add-on is off' );

	fwrite( STDOUT, "OK gamification-rewards-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
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
