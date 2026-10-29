<?php
/**
 * Integrations REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with the real options table. It adds
 * two fake integrations through the `ohmylms_integrations` filter and a temporary subscriber, and
 * restores every option and user meta it touches in `finally` (the integration switches, the
 * credential options, the administrator's Zoom credentials). No network request is made.
 *
 * Usage (set OHMYLMS_INTEGRATIONS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/integrations-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_INTEGRATIONS_TEST' ) ) {
	exit( "Set OHMYLMS_INTEGRATIONS_TEST=1 to run the integrations REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Services\GoogleAuthService;
use OhMyLMS\Services\MicrosoftAuthService;

$checks   = 0;
$user_ids = array();
$missing  = new stdClass();

/**
 * Options this test may change, restored afterwards.
 *
 * @var string[]
 */
$watched = array(
	'ohmylms_integrations',
	'ohmylms_content_protection',
	'ohmylms_zoom_webhook_secret_token',
	GoogleAuthService::OPTION_KEY,
	MicrosoftAuthService::OPTION_KEY,
);
$backup  = array();
foreach ( $watched as $option_name ) {
	$backup[ $option_name ] = get_option( $option_name, $missing );
}

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
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/integrations.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/integrations' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Whether the response carries the given error code.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected HTTP status.
 * @param string           $code     Expected error code.
 * @return bool
 */
function is_error( $response, $status, $code ) {
	$data = $response->get_data();
	return $status === $response->get_status() && is_array( $data ) && isset( $data['code'] ) && $code === $data['code'];
}

// Two fake integrations: one that can be switched on, one that is locked.
$fired = array();
add_filter(
	'ohmylms_integrations',
	static function ( $catalogue ) {
		$catalogue['zz_test']   = array(
			'label'       => 'ZZ Test <b>Integration</b>',
			'description' => 'A fake integration used by the REST test.',
			'icon'        => 'https://example.invalid/icon.svg',
			'categories'  => array( 'testing', 'Course-Enhancements' ),
			'dependency'  => 'Requires nothing',
		);
		$catalogue['zz_locked'] = array(
			'label'       => 'ZZ Locked',
			'description' => 'Not available.',
			'categories'  => array( 'testing' ),
			'is_valid'    => false,
		);
		return $catalogue;
	},
	99
);
foreach ( array(
	'ohmylms_integrations_before_update',
	'ohmylms_integration_zz_test_updated',
	'ohmylms_integration_zz_test_before_enable',
	'ohmylms_integration_zz_test_after_enable',
	'ohmylms_addon_activated',
	'ohmylms_integrations_after_update',
	'ohmylms_integrations_zz_test_after_update',
) as $hook_name ) {
	add_action(
		$hook_name,
		static function () use ( &$fired, $hook_name ) {
			$fired[] = $hook_name;
		},
		10,
		2
	);
}
$settings_events = array();
add_action(
	'ohmylms_integration_settings_updated',
	static function ( $id, $changed ) use ( &$settings_events ) {
		$settings_events[] = array( $id, $changed );
	},
	10,
	2
);

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

	$zoom_meta_backup = get_user_meta( $admin, 'ohmylms_zoom_api_credentials', true );
	$had_zoom_meta    = metadata_exists( 'user', $admin, 'ohmylms_zoom_api_credentials' );

	$tag        = 'inttest' . strtolower( wp_generate_password( 6, false, false ) );
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

	// Routes.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/integrations', '/integrations/(?P<id>[a-z0-9_-]+)', '/integrations/(?P<id>[a-z0-9_-]+)/settings' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
		foreach ( $routes[ '/ohmylms/v1' . $route ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'permission_callback is a real check: ' . $route );
		}
	}

	// Anonymous and under-privileged callers.
	$guarded = array(
		array( 'GET', '', 'ohmylms_integration_cannot_view' ),
		array( 'GET', '/zoom', 'ohmylms_integration_cannot_view' ),
		array( 'PATCH', '/zoom', 'ohmylms_integration_cannot_edit' ),
		array( 'PUT', '/zoom', 'ohmylms_integration_cannot_edit' ),
		array( 'GET', '/zoom/settings', 'ohmylms_integration_cannot_view' ),
		array( 'PUT', '/zoom/settings', 'ohmylms_integration_cannot_edit' ),
		array( 'PATCH', '/zoom/settings', 'ohmylms_integration_cannot_edit' ),
	);
	$before = get_option( 'ohmylms_integrations', array() );
	foreach ( array(
		array( 0, 401 ),
		array( $subscriber, 403 ),
	) as $actor ) {
		wp_set_current_user( $actor[0] );
		foreach ( $guarded as $case ) {
			$body     = 'GET' === $case[0] ? array() : array( 'enabled' => true );
			$response = call_api( $case[0], $case[1], $body );
			ok( is_error( $response, $actor[1], $case[2] ), "{$case[0]} {$case[1]} answers {$actor[1]} for user {$actor[0]}" );
		}
	}
	ok( get_option( 'ohmylms_integrations', array() ) === $before, 'denied requests change nothing' );

	wp_set_current_user( $admin );

	// Removed legacy routes: the bulk PUT and DELETE no longer exist.
	ok( 404 === call_api( 'PUT', '', array( 'zoom' => array( 'is_enable' => 1 ) ) )->get_status(), 'bulk PUT /integrations is gone' );
	ok( 404 === call_api( 'DELETE', '/zoom' )->get_status(), 'DELETE /integrations/{id} is gone' );
	ok( get_option( 'ohmylms_integrations', array() ) === $before, 'removed routes change nothing' );

	// Collection.
	$response = call_api( 'GET' );
	ok( 200 === $response->get_status(), 'list is 200' );
	$items = $response->get_data();
	$by_id = array();
	foreach ( $items as $item ) {
		$by_id[ $item['id'] ] = $item;
	}
	foreach ( array( 'zoom', 'google_signin', 'microsoft_signin', 'webhooks', 'wpfusion', 'zz_test', 'zz_locked' ) as $id ) {
		ok( isset( $by_id[ $id ] ), "list contains $id" );
	}
	ok( (string) count( $items ) === $response->get_headers()['X-WP-Total'] && '1' === $response->get_headers()['X-WP-TotalPages'], 'list sends total headers' );
	$expected_keys = array( 'available', 'categories', 'configurable', 'dependency', 'description', 'enabled', 'icon', 'id', 'label', 'required_plan', 'requires_reload', 'unavailable_reason' );
	foreach ( $items as $item ) {
		$keys = array_values( array_diff( array_keys( $item ), array( '_links' ) ) );
		sort( $keys );
		ok( $expected_keys === $keys, 'item shape for ' . $item['id'] . ': ' . implode( ',', $keys ) );
		ok( is_bool( $item['enabled'] ) && is_bool( $item['available'] ) && is_bool( $item['configurable'] ) && is_bool( $item['requires_reload'] ), 'booleans are booleans for ' . $item['id'] );
		ok( ! array_key_exists( 'class', $item ) && ! array_key_exists( 'is_valid', $item ), 'internal manifest fields are not exposed' );
	}
	ok( 'ZZ Test Integration' === $by_id['zz_test']['label'], 'label is stripped of markup' );
	ok( array( 'testing', 'course-enhancements' ) === $by_id['zz_test']['categories'], 'categories are slugs' );
	ok( 'Requires nothing' === $by_id['zz_test']['dependency'], 'dependency passes through' );
	ok( false === $by_id['zz_test']['enabled'] && true === $by_id['zz_test']['available'] && false === $by_id['zz_test']['configurable'], 'fake integration starts off' );
	ok( false === $by_id['zz_locked']['available'] && is_string( $by_id['zz_locked']['unavailable_reason'] ), 'is_valid false makes the integration unavailable' );
	ok( true === $by_id['zoom']['configurable'] && true === $by_id['google_signin']['configurable'] && true === $by_id['microsoft_signin']['configurable'], 'credential integrations are configurable' );
	ok( false === $by_id['webhooks']['configurable'] && false === $by_id['xapi']['configurable'], 'integrations without managed credentials are not configurable' );
	if ( ! defined( 'WP_FUSION_VERSION' ) ) {
		ok( false === $by_id['wpfusion']['available'] && is_string( $by_id['wpfusion']['unavailable_reason'] ), 'WP Fusion is unavailable without the plugin' );
	}

	// Filters.
	$found = wp_list_pluck( call_api( 'GET', '', array( 'search' => 'zz test' ) )->get_data(), 'id' );
	ok( array( 'zz_test' ) === $found, 'search matches label case-insensitively' );
	$found = wp_list_pluck( call_api( 'GET', '', array( 'search' => 'fake integration used' ) )->get_data(), 'id' );
	ok( array( 'zz_test' ) === $found, 'search matches the description' );
	$found = wp_list_pluck( call_api( 'GET', '', array( 'category' => 'testing' ) )->get_data(), 'id' );
	ok( array( 'zz_test', 'zz_locked' ) === $found, 'category filter' );
	$found = wp_list_pluck( call_api( 'GET', '', array( 'status' => 'enabled' ) )->get_data(), 'id' );
	ok( ! in_array( 'zz_test', $found, true ) && in_array( 'mcp', $found, true ), 'status=enabled filter' );
	$found = wp_list_pluck( call_api( 'GET', '', array( 'status' => 'disabled' ) )->get_data(), 'id' );
	ok( in_array( 'zz_test', $found, true ) && ! in_array( 'mcp', $found, true ), 'status=disabled filter' );
	ok( 400 === call_api( 'GET', '', array( 'status' => 'bogus' ) )->get_status(), 'unknown status is rejected' );
	ok( 400 === call_api( 'GET', '', array( 'search' => str_repeat( 'a', 101 ) ) )->get_status(), 'overlong search is rejected' );
	$fields = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/integrations' ) );
	$fields = rest_get_server()->response_to_data( $fields, false );
	ok( is_array( $fields ) && isset( $fields[0]['id'] ), 'response_to_data keeps the list' );

	// Single item.
	$response = call_api( 'GET', '/zz_test' );
	ok( 200 === $response->get_status() && 'zz_test' === $response->get_data()['id'], 'get item' );
	$links = $response->get_links();
	ok( isset( $links['self'], $links['collection'] ) && ! isset( $links['settings'] ), 'links: self and collection, no settings link for a plain integration' );
	ok( isset( call_api( 'GET', '/zoom' )->get_links()['settings'] ), 'configurable integrations link to their settings' );
	ok( is_error( call_api( 'GET', '/nope' ), 404, 'ohmylms_integration_not_found' ), 'unknown integration is 404' );
	ok( 404 === call_api( 'GET', '/Bad%20Id' )->get_status(), 'malformed id is not routed' );

	// Toggle.
	$stored_extra         = get_option( 'ohmylms_integrations', array() );
	$stored_extra         = is_array( $stored_extra ) ? $stored_extra : array();
	$stored_extra['keep'] = array(
		'is_enable' => 1,
		'note'      => 'preserved',
	);
	update_option( 'ohmylms_integrations', $stored_extra );

	ok( 400 === call_api( 'PATCH', '/zz_test', array() )->get_status(), 'enabled is required' );
	ok( 400 === call_api( 'PATCH', '/zz_test', array( 'enabled' => 'maybe' ) )->get_status(), 'enabled must be boolean' );
	ok( is_error( call_api( 'PATCH', '/nope', array( 'enabled' => true ) ), 404, 'ohmylms_integration_not_found' ), 'toggle unknown integration is 404' );

	$fired    = array();
	$response = call_api( 'PATCH', '/zz_test', array( 'enabled' => true ) );
	ok( 200 === $response->get_status() && true === $response->get_data()['enabled'], 'enable returns the item' );
	$option = get_option( 'ohmylms_integrations' );
	ok( 1 === $option['zz_test']['is_enable'], 'switch stored as is_enable 1' );
	ok( 1 === $option['keep']['is_enable'] && 'preserved' === $option['keep']['note'], 'other entries are preserved' );
	ok(
		array(
			'ohmylms_integrations_before_update',
			'ohmylms_integration_zz_test_updated',
			'ohmylms_integration_zz_test_before_enable',
			'ohmylms_integration_zz_test_after_enable',
			'ohmylms_addon_activated',
			'ohmylms_integrations_after_update',
			'ohmylms_integrations_zz_test_after_update',
		) === $fired,
		'enable hooks fire in order: ' . implode( ',', $fired )
	);
	ok( false === $response->get_data()['requires_reload'], 'no reload for a plain integration' );
	ok( true === call_api( 'GET', '/zz_test' )->get_data()['enabled'], 'enabled state is read back' );

	$fired    = array();
	$response = call_api( 'PATCH', '/zz_test', array( 'enabled' => true ) );
	ok( 200 === $response->get_status() && array() === $fired, 'enabling an enabled integration is a no-op without hooks' );

	$fired    = array();
	$response = call_api( 'PUT', '/zz_test', array( 'enabled' => false ) );
	ok( 200 === $response->get_status() && false === $response->get_data()['enabled'], 'disable returns the item' );
	ok( 0 === get_option( 'ohmylms_integrations' )['zz_test']['is_enable'], 'switch stored as is_enable 0' );
	ok(
		array(
			'ohmylms_integrations_before_update',
			'ohmylms_integration_zz_test_updated',
			'ohmylms_integrations_after_update',
			'ohmylms_integrations_zz_test_after_update',
		) === $fired,
		'disable hooks fire without activation hooks: ' . implode( ',', $fired )
	);

	// Unavailable integrations cannot be enabled but can always be disabled.
	$fired    = array();
	$response = call_api( 'PATCH', '/zz_locked', array( 'enabled' => true ) );
	ok( is_error( $response, 422, 'ohmylms_integration_unavailable' ) && array() === $fired, 'locked integration cannot be enabled' );
	ok( ! isset( get_option( 'ohmylms_integrations' )['zz_locked'] ), 'nothing stored for the refused integration' );
	if ( ! defined( 'WP_FUSION_VERSION' ) ) {
		ok( is_error( call_api( 'PATCH', '/wpfusion', array( 'enabled' => true ) ), 422, 'ohmylms_integration_unavailable' ), 'WP Fusion cannot be enabled without the plugin' );
	}
	add_filter(
		'ohmylms_integration_unavailable_reason',
		static function ( $reason, $id ) {
			return 'zz_test' === $id ? 'Blocked by a filter.' : $reason;
		},
		10,
		2
	);
	$response = call_api( 'PATCH', '/zz_test', array( 'enabled' => true ) );
	ok( is_error( $response, 422, 'ohmylms_integration_unavailable' ) && 'Blocked by a filter.' === $response->get_data()['message'], 'the unavailable reason is filterable' );
	remove_all_filters( 'ohmylms_integration_unavailable_reason' );

	// Menu-affecting switches ask for a reload; content protection mirrors its own option.
	$mcp_before = isset( get_option( 'ohmylms_integrations' )['mcp']['is_enable'] ) ? (int) get_option( 'ohmylms_integrations' )['mcp']['is_enable'] : 0;
	$response   = call_api( 'PATCH', '/mcp', array( 'enabled' => 0 === $mcp_before ) );
	ok( 200 === $response->get_status() && true === $response->get_data()['requires_reload'], 'mcp change requires a reload' );
	call_api( 'PATCH', '/mcp', array( 'enabled' => 1 === $mcp_before ) );
	$response = call_api( 'PATCH', '/content_protection', array( 'enabled' => true ) );
	ok( 200 === $response->get_status() && 'yes' === get_option( 'ohmylms_content_protection' ), 'content protection switch writes its option' );
	call_api( 'PATCH', '/content_protection', array( 'enabled' => false ) );
	ok( 'no' === get_option( 'ohmylms_content_protection' ), 'content protection switch off' );

	// Settings: authorisation targets.
	ok( is_error( call_api( 'GET', '/nope/settings' ), 404, 'ohmylms_integration_not_found' ), 'settings of an unknown integration are 404' );
	ok( is_error( call_api( 'GET', '/zz_test/settings' ), 404, 'ohmylms_integration_no_settings' ), 'integrations without credentials have no settings route' );
	ok( is_error( call_api( 'PUT', '/webhooks/settings', array( 'x' => 'y' ) ), 404, 'ohmylms_integration_no_settings' ), 'settings write is refused for them too' );

	// Zoom settings.
	delete_user_meta( $admin, 'ohmylms_zoom_api_credentials' );
	delete_option( 'ohmylms_zoom_webhook_secret_token' );
	$response = call_api( 'GET', '/zoom/settings' );
	$data     = $response->get_data();
	ok( 200 === $response->get_status() && 'zoom' === $data['integration'] && false === $data['configured'], 'fresh zoom settings are not configured' );
	$names = wp_list_pluck( $data['fields'], 'name' );
	ok( array( 'account_id', 'client_id', 'client_secret', 'webhook_secret_token' ) === $names, 'zoom fields' );
	ok( 'webhook_url' === $data['readonly'][0]['name'] && rest_url( 'ohmylms/v1/zoom/webhook' ) === $data['readonly'][0]['value'], 'zoom exposes the webhook URL read only' );

	$response = call_api( 'PUT', '/zoom/settings', array( 'account_id' => 'acct-1' ) );
	$data     = $response->get_data();
	ok( is_error( $response, 422, 'ohmylms_integration_invalid_settings' ), 'required zoom fields are enforced' );
	ok( isset( $data['data']['params']['client_id'], $data['data']['params']['client_secret'] ) && ! isset( $data['data']['params']['account_id'] ), 'params name only the invalid fields' );
	ok( ! metadata_exists( 'user', $admin, 'ohmylms_zoom_api_credentials' ), 'a rejected save writes nothing' );

	$secret   = 'zs_Secret-Value_1234567890';
	$token    = 'whtoken-ABCDEF123456';
	$response = call_api(
		'PUT',
		'/zoom/settings',
		array(
			'account_id'           => '  acct-1  ',
			'client_id'            => 'cid-1',
			'client_secret'        => $secret,
			'webhook_secret_token' => $token,
		)
	);
	$data     = $response->get_data();
	ok( 200 === $response->get_status() && true === $data['configured'], 'zoom settings saved' );
	$by_name = array();
	foreach ( $data['fields'] as $field ) {
		$by_name[ $field['name'] ] = $field;
	}
	ok( 'acct-1' === $by_name['account_id']['value'] && 'cid-1' === $by_name['client_id']['value'], 'text fields are trimmed and returned' );
	ok( true === $by_name['client_secret']['configured'] && '****7890' === $by_name['client_secret']['hint'] && ! array_key_exists( 'value', $by_name['client_secret'] ), 'secret returns configured and a hint only' );
	$wire = wp_json_encode( $data ) . wp_json_encode( call_api( 'GET', '/zoom/settings' )->get_data() );
	ok( false === strpos( $wire, $secret ) && false === strpos( $wire, $token ), 'no response contains a stored secret' );
	$meta = get_user_meta( $admin, 'ohmylms_zoom_api_credentials', true );
	$stored_token = get_option( 'ohmylms_zoom_webhook_secret_token' );
	ok( 'acct-1' === $meta['account_id'] && $secret === $meta['client_secret'] && $token === $stored_token, 'zoom values are stored where the zoom client reads them' );
	ok( array( array( 'zoom', array( 'account_id', 'client_id', 'client_secret', 'webhook_secret_token' ) ) ) === $settings_events, 'settings hook receives field names only: ' . wp_json_encode( $settings_events ) );

	$settings_events = array();
	$response        = call_api( 'PATCH', '/zoom/settings', array( 'client_id' => 'cid-2' ) );
	ok( 200 === $response->get_status(), 'partial update' );
	$meta = get_user_meta( $admin, 'ohmylms_zoom_api_credentials', true );
	ok( $secret === $meta['client_secret'], 'omitted secrets are kept' );
	ok( array( array( 'zoom', array( 'client_id' ) ) ) === $settings_events, 'only changed fields are reported' );
	$settings_events = array();
	call_api( 'PATCH', '/zoom/settings', array( 'client_id' => 'cid-2' ) );
	ok( array() === $settings_events, 'an unchanged save fires no hook' );

	$response = call_api( 'PATCH', '/zoom/settings', array( 'webhook_secret_token' => null ) );
	ok( 200 === $response->get_status() && '' === get_option( 'ohmylms_zoom_webhook_secret_token' ), 'an optional secret can be cleared with null' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'client_secret' => null ) ), 422, 'ohmylms_integration_invalid_settings' ), 'a required secret cannot be cleared' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'client_secret' => '' ) ), 422, 'ohmylms_integration_invalid_settings' ), 'an empty secret is rejected, not treated as keep' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'client_secret' => 'has space' ) ), 422, 'ohmylms_integration_invalid_settings' ), 'secrets with spaces are rejected' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'client_secret' => str_repeat( 'a', 257 ) ) ), 422, 'ohmylms_integration_invalid_settings' ), 'overlong secret is rejected' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'client_id' => array( 'x' ) ) ), 422, 'ohmylms_integration_invalid_settings' ), 'non-string text is rejected' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'account_id' => '' ) ), 422, 'ohmylms_integration_invalid_settings' ), 'a required text field cannot be blanked' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'account_id' => str_repeat( 'a', 129 ) ) ), 422, 'ohmylms_integration_invalid_settings' ), 'overlong text is rejected' );
	ok( is_error( call_api( 'PATCH', '/zoom/settings', array( 'bogus' => 'x' ) ), 400, 'ohmylms_integration_unknown_setting' ), 'unknown settings are rejected' );
	$mixed = call_api(
		'PATCH',
		'/zoom/settings',
		array(
			'client_id'     => 'ok',
			'client_secret' => 'bad secret',
		)
	);
	$meta  = get_user_meta( $admin, 'ohmylms_zoom_api_credentials', true );
	ok( is_error( $mixed, 422, 'ohmylms_integration_invalid_settings' ) && 'cid-2' === $meta['client_id'], 'a partly invalid save changes nothing' );
	$list_request = new WP_REST_Request( 'PATCH', '/ohmylms/v1/integrations/zoom/settings' );
	$list_request->set_header( 'Content-Type', 'application/json' );
	$list_request->set_body( '["a","b"]' );
	ok( is_error( rest_do_request( $list_request ), 400, 'ohmylms_integration_invalid_body' ), 'a JSON list body is rejected' );
	$sanitised = call_api( 'PATCH', '/zoom/settings', array( 'client_id' => '<b>cid</b>-3' ) );
	ok( 200 === $sanitised->get_status() && 'cid-3' === get_user_meta( $admin, 'ohmylms_zoom_api_credentials', true )['client_id'], 'text values are sanitised' );

	// Legacy zoom route no longer leaks secrets and keeps blank secrets.
	$legacy_request = new WP_REST_Request( 'GET', '/ohmylms/v1/zoom/settings/credentials' );
	$legacy         = rest_do_request( $legacy_request );
	ok( 200 === $legacy->get_status() && false === strpos( wp_json_encode( $legacy->get_data() ), $secret ) && true === $legacy->get_data()['data']['has_client_secret'], 'legacy zoom credentials route returns no secrets' );
	$legacy_save = new WP_REST_Request( 'POST', '/ohmylms/v1/zoom/settings/credentials' );
	$legacy_save->set_header( 'Content-Type', 'application/json' );
	$legacy_save->set_body(
		wp_json_encode(
			array(
				'account_id'    => 'acct-1',
				'client_id'     => 'cid-4',
				'client_secret' => '',
			)
		)
	);
	$legacy_status = rest_do_request( $legacy_save )->get_status();
	$meta          = get_user_meta( $admin, 'ohmylms_zoom_api_credentials', true );
	ok( 200 === $legacy_status && $secret === $meta['client_secret'], 'legacy zoom save keeps the secret when it is blank' );

	// Google sign-in.
	delete_option( GoogleAuthService::OPTION_KEY );
	$data = call_api( 'GET', '/google_signin/settings' )->get_data();
	ok( false === $data['fields'][0]['required'] && true === $data['configured'] && GoogleAuthService::get_redirect_uri() === $data['readonly'][0]['value'], 'google settings expose the redirect URI' );
	$response = call_api(
		'PUT',
		'/google_signin/settings',
		array(
			'client_id'     => 'google-client.apps.example',
			'client_secret' => 'GOCSPX-abcdefghijklmno',
		)
	);
	ok( 200 === $response->get_status() && false === strpos( wp_json_encode( $response->get_data() ), 'GOCSPX-abcdefghijklmno' ), 'google settings saved without echoing the secret' );
	$stored = get_option( GoogleAuthService::OPTION_KEY );
	ok( array( 'client_id', 'client_secret' ) === array_keys( $stored ) && 'GOCSPX-abcdefghijklmno' === $stored['client_secret'], 'google option holds only the credentials the service reads' );
	ok( 'google-client.apps.example' === GoogleAuthService::get_settings()['client_id'], 'the google service reads the saved client id' );

	// Microsoft sign-in.
	delete_option( MicrosoftAuthService::OPTION_KEY );
	$data = call_api( 'GET', '/microsoft_signin/settings' )->get_data();
	ok( 'common' === $data['fields'][1]['value'] && 'tenant' === $data['fields'][1]['name'], 'tenant defaults to common' );
	ok( is_error( call_api( 'PUT', '/microsoft_signin/settings', array( 'tenant' => 'bad tenant!' ) ), 422, 'ohmylms_integration_invalid_settings' ), 'an invalid tenant is rejected instead of silently replaced' );
	$response = call_api(
		'PUT',
		'/microsoft_signin/settings',
		array(
			'client_id'     => 'ms-client-id',
			'tenant'        => 'contoso.onmicrosoft.com',
			'client_secret' => 'ms~secret.value-123',
		)
	);
	ok( 200 === $response->get_status() && 'contoso.onmicrosoft.com' === MicrosoftAuthService::get_settings()['tenant'], 'microsoft settings saved' );
	ok( false === strpos( wp_json_encode( $response->get_data() ), 'ms~secret.value-123' ), 'microsoft secret is not echoed' );
	call_api( 'PATCH', '/microsoft_signin/settings', array( 'tenant' => '' ) );
	ok( 'common' === MicrosoftAuthService::get_settings()['tenant'], 'an empty tenant falls back to common' );
	ok( 'ms~secret.value-123' === MicrosoftAuthService::get_settings()['client_secret'], 'the microsoft secret survives other edits' );

	// Subscribers can read neither the settings nor write them.
	wp_set_current_user( $subscriber );
	$response = call_api( 'GET', '/zoom/settings' );
	ok( 403 === $response->get_status() && false === strpos( wp_json_encode( $response->get_data() ), 'cid-' ), 'subscribers cannot read settings' );
	wp_set_current_user( $admin );

	// Schema.
	$options = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/integrations' ) );
	$schema  = $options->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'integration' === $schema['title'], 'schema header' );
	foreach ( $expected_keys as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}
	ok( empty( $schema['properties']['enabled']['readonly'] ) && ! empty( $schema['properties']['id']['readonly'] ), 'schema: only enabled is writable' );
	$settings_schema = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/integrations/zoom/settings' ) )->get_data()['schema'];
	ok( 'integration-settings' === $settings_schema['title'], 'settings schema' );

	fwrite( STDOUT, "OK integrations-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Restore exactly what this run could have changed.
	foreach ( $backup as $option_name => $value ) {
		if ( $value === $missing ) {
			delete_option( $option_name );
		} else {
			update_option( $option_name, $value );
		}
	}
	if ( isset( $admin ) ) {
		if ( $had_zoom_meta ) {
			update_user_meta( $admin, 'ohmylms_zoom_api_credentials', $zoom_meta_backup );
		} else {
			delete_user_meta( $admin, 'ohmylms_zoom_api_credentials' );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
