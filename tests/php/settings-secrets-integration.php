<?php
/**
 * Settings tabs with credentials: payment gateways, xAPI, AI tutor, MCP and the design system.
 *
 * Proves that secrets are never returned, an empty secret keeps the stored value, an explicit
 * clear removes it, denied users get 401/403 and rejected requests write nothing. Runs against a
 * WordPress install (the Local site by default); every option it touches is snapshotted straight
 * from the options table and restored byte for byte in `finally`.
 *
 * Usage (set OHMYLMS_SETTINGS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/settings-secrets-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment, WordPress.DB.PreparedSQL

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SETTINGS_TEST' ) ) {
	exit( "Set OHMYLMS_SETTINGS_TEST=1 to run the settings secrets tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Settings\PaymentGatewaySettings;

global $wpdb;

$checks   = 0;
$user_ids = array();
$tag      = 'sectest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * @param string $path   Path below /ohmylms/v1.
 * @param mixed  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path, $data = null ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( is_array( $data ) ? $data : array() );
	} elseif ( null !== $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Names of the options the tests can touch.
 *
 * @return string[]
 */
function touched_options() {
	$names = array( 'ohmylms_xapi_settings', 'ohmylms_ai', 'ohmylms_practice_style', 'ohmylms_mcp_tokens', 'ohmylms_design_system', 'ohmylms_integrations' );
	foreach ( array( 'offline', 'stripe', 'paypal', 'qpay', 'authorize_net' ) as $id ) {
		$names[] = PaymentGatewaySettings::option_name( $id );
	}
	return $names;
}

/**
 * Read the raw rows of the touched options.
 *
 * @return array<string, array|null>
 */
function snapshot_options() {
	global $wpdb;
	$rows = array();
	foreach ( touched_options() as $name ) {
		$row           = $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $name ), ARRAY_A );
		$rows[ $name ] = $row ? $row : null;
	}
	return $rows;
}

/**
 * Put the options back exactly.
 *
 * @param array $snapshot Result of snapshot_options().
 */
function restore_options( array $snapshot ) {
	global $wpdb;
	foreach ( $snapshot as $name => $row ) {
		if ( null === $row ) {
			$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		} elseif ( null === $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $name ) ) ) {
			$wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => $name,
					'option_value' => $row['option_value'],
					'autoload'     => $row['autoload'],
				)
			);
		} else {
			$wpdb->update(
				$wpdb->options,
				array(
					'option_value' => $row['option_value'],
					'autoload'     => $row['autoload'],
				),
				array( 'option_name' => $name )
			);
		}
	}
	wp_cache_flush();
}

/**
 * Assert that a response does not contain any of the given strings.
 *
 * @param WP_REST_Response $response Response.
 * @param string[]         $secrets  Secret values.
 * @param string           $label    Label.
 */
function ok_no_secret( $response, array $secrets, $label ) {
	$json = wp_json_encode( $response->get_data() );
	foreach ( $secrets as $secret ) {
		ok( false === strpos( $json, $secret ), $label . ' does not contain a secret' );
	}
}

/**
 * Assert an error status and code.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $label    Label.
 */
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	ok( $status === $response->get_status() && isset( $data['code'] ) && $code === $data['code'], $label . ": expected {$status} {$code}, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
}

$before  = snapshot_options();
$updated = array();
add_action(
	'ohmylms_payment_gateway_settings_updated',
	static function ( $id, $changed ) use ( &$updated ) {
		$updated[] = array( $id, $changed );
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
	$admin      = (int) $admins[0];
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

	// Seed credentials that must never come back.
	$live_secret = 'sk_live_' . $tag . 'LIVESECRET';
	$test_secret = 'sk_test_' . $tag . 'TESTSECRET';
	update_option(
		'ohmylms_stripe_settings',
		array(
			'enabled'                 => 'no',
			'test_mode'               => 'yes',
			'title'                   => 'Card',
			'publishable_key'         => 'pk_live_public',
			'secret_key'              => $live_secret,
			'sandbox_publishable_key' => 'pk_test_public',
			'sandbox_secret_key'      => $test_secret,
			'legacy_member'           => 'kept',
		)
	);
	$qpay_secret = 'qpay_' . $tag . 'SECRET';
	update_option(
		'ohmylms_qpay_settings',
		array(
			'enabled'            => 'no',
			'testmode'           => 'no',
			'live_client_id'     => 'merchant',
			'live_client_secret' => $qpay_secret,
		)
	);
	$xapi_secret = 'xapi_' . $tag . 'SECRET';
	update_option(
		'ohmylms_xapi_settings',
		array_merge(
			\OhMyLMS\XApi\Settings::defaults(),
			array(
				'endpoint' => 'https://lrs.example.com/xapi',
				'key'      => 'lrs-user',
				'secret'   => $xapi_secret,
			)
		)
	);
	$ai_key = 'sk-ant-' . $tag . 'AIKEY1234';
	\OhMyLMS\AI\Settings::update(
		array(
			'provider' => 'anthropic',
			'model'    => 'claude-test',
			'key'      => $ai_key,
		)
	);
	update_option(
		'ohmylms_mcp_tokens',
		array(
			'openai' => array(
				'hash'    => hash( 'sha256', 'oml_mcp_' . $tag ),
				'user_id' => $admin,
				'created' => gmdate( 'c' ),
			),
		),
		false
	);
	$secrets = array( $live_secret, $test_secret, $qpay_secret, $xapi_secret, $ai_key, hash( 'sha256', 'oml_mcp_' . $tag ) );
	$seeded  = snapshot_options();

	// The client contract matches the server.
	$contract = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/assets/src/app/features/settings/contract.json' ), true );
	ok( PaymentGatewaySettings::BODY_KEYS === $contract['paymentGateways']['writeKeys'], 'contract lists the gateway write keys' );
	ok( PaymentGatewaySettings::MAX_LENGTH === $contract['paymentGateways']['maxLength'], 'contract lists the gateway field limits' );

	// Redacted gateway definitions (PaymentGatewaySettings::redact(), which the retired legacy page data
	// used) carry no credentials. Gateway objects read their options once per request, so a gateway with
	// stored values is supplied through the gateway filter.
	wp_set_current_user( $admin );
	$legacy_secret = $tag . 'legacy_secret';
	$legacy_filter = static function ( $gateways ) use ( $legacy_secret ) {
		$gateways['ohmylms_legacy_probe'] = array(
			'title'           => 'Probe',
			'settings_fields' => array(
				array(
					'input_type'    => 'text',
					'option_name'   => 'title',
					'value'         => 'Probe title',
					'default_value' => 'Probe title',
				),
				array(
					'input_type'    => 'text',
					'option_name'   => 'secret_key',
					'value'         => $legacy_secret,
					'default_value' => $legacy_secret,
				),
				array(
					'input_type'    => 'text',
					'option_name'   => 'sandbox_secret_key',
					'value'         => '',
					'default_value' => '',
				),
			),
		);
		return $gateways;
	};
	add_filter( 'ohmylms_payment_gateways', $legacy_filter );
	$legacy = array( 'payment_gateways' => PaymentGatewaySettings::redact( (array) apply_filters( 'ohmylms_payment_gateways', \OhMyLMS\Commerce\ecommerce()->gateways()->get_payment_gateway_settings() ) ) );
	remove_filter( 'ohmylms_payment_gateways', $legacy_filter );
	$legacy_json = (string) wp_json_encode( $legacy['payment_gateways'] );
	$probe       = $legacy['payment_gateways']['ohmylms_legacy_probe']['settings_fields'];
	ok( false === strpos( $legacy_json, $legacy_secret ), 'redacted gateway data omits a gateway credential' );
	foreach ( array( $live_secret, $test_secret, $qpay_secret ) as $secret ) {
		ok( false === strpos( $legacy_json, $secret ), 'redacted gateway data omits a stored gateway credential' );
	}
	ok( 'Probe title' === $probe[0]['value'] && ! isset( $probe[0]['has_value'] ), 'redacted gateway data keeps non-secret values' );
	ok( true === $probe[1]['has_value'] && '' === $probe[1]['value'] && '' === $probe[1]['default_value'], 'redacted gateway data reports a stored credential as has_value only' );
	ok( false === $probe[2]['has_value'], 'redacted gateway data reports an empty credential as has_value false' );

	// Routes and permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/settings/payment-gateways', '/settings/payment-gateways/(?P<gateway>[a-z0-9_-]+)', '/xapi/settings', '/ai', '/ai/test', '/ai/models', '/mcp/settings', '/design-system' ) as $path ) {
		ok( isset( $routes[ '/ohmylms/v1' . $path ] ), 'route registered: ' . $path );
		foreach ( $routes[ '/ohmylms/v1' . $path ] as $endpoint ) {
			ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'real permission callback on ' . $path );
		}
	}

	// Anonymous and subscriber are refused and nothing is written.
	$cases = array(
		array( 'GET', '/settings/payment-gateways', null ),
		array( 'GET', '/settings/payment-gateways/stripe', null ),
		array( 'PATCH', '/settings/payment-gateways/stripe', array( 'enabled' => true ) ),
		array( 'GET', '/xapi/settings', null ),
		array( 'POST', '/xapi/settings/test', null ),
		array( 'GET', '/ai', null ),
		array( 'POST', '/ai', array( 'clear_key' => true ) ),
		array( 'POST', '/ai/test', null ),
		array( 'POST', '/ai/models', null ),
		array( 'GET', '/mcp/settings', null ),
		array( 'POST', '/mcp/settings/openai', array( 'action' => 'revoke' ) ),
		array( 'GET', '/design-system', null ),
		array( 'POST', '/design-system', array( 'density' => 'compact' ) ),
		array( 'DELETE', '/design-system', null ),
	);
	foreach ( array(
		0           => 401,
		$subscriber => 403,
	) as $user => $status ) {
		wp_set_current_user( $user );
		foreach ( $cases as $case ) {
			$response = call_api( $case[0], $case[1], $case[2] );
			ok( $status === $response->get_status(), "user {$user} {$case[0]} {$case[1]} is {$status}, got " . $response->get_status() );
			ok_no_secret( $response, $secrets, "denied {$case[0]} {$case[1]}" );
		}
	}
	// The xAPI save route validates its required body before the permission check (core order).
	wp_set_current_user( $subscriber );
	$response = call_api(
		'POST',
		'/xapi/settings',
		array(
			'enabled'        => true,
			'endpoint'       => 'https://evil.example.com',
			'key'            => 'x',
			'actor'          => 'pseudonym',
			'responses'      => true,
			'retention_days' => 14,
			'groups'         => (object) array(),
		)
	);
	ok( 403 === $response->get_status(), 'subscriber cannot save xAPI settings' );
	ok( snapshot_options() === $seeded, 'denied requests changed no option' );
	wp_set_current_user( $admin );

	// Gateways: list and read never contain credentials.
	$response = call_api( 'GET', '/settings/payment-gateways' );
	ok( 200 === $response->get_status(), 'gateway list answers' );
	ok_no_secret( $response, $secrets, 'gateway list' );
	$ids = wp_list_pluck( $response->get_data(), 'id' );
	ok( in_array( 'stripe', $ids, true ) && in_array( 'offline', $ids, true ) && in_array( 'qpay', $ids, true ), 'gateway list includes stripe, offline and qpay: ' . implode( ',', $ids ) );
	foreach ( $response->get_data() as $gateway ) {
		foreach ( $gateway['fields'] as $field ) {
			if ( 'secret' === $field['type'] ) {
				ok( ! property_exists( $gateway['values'], $field['name'] ), $gateway['id'] . ' ' . $field['name'] . ' has no value member' );
				ok( is_bool( $gateway['secrets']->{$field['name']}['has_value'] ), $gateway['id'] . ' ' . $field['name'] . ' reports has_value' );
			}
		}
	}

	$response = call_api( 'GET', '/settings/payment-gateways/stripe' );
	$stripe   = $response->get_data();
	ok( 200 === $response->get_status() && 'test' === $stripe['mode'] && false === $stripe['enabled'], 'stripe mode and enabled read from the option' );
	ok( true === $stripe['secrets']->secret_key['has_value'] && true === $stripe['secrets']->sandbox_secret_key['has_value'], 'stripe secrets report has_value' );
	ok( 'pk_live_public' === $stripe['values']->publishable_key, 'publishable key (public) is returned' );
	ok( 'no-store' === $response->get_headers()['Cache-Control'], 'gateway responses are not cached' );
	ok_no_secret( $response, $secrets, 'stripe read' );
	$fields = array_column( $stripe['fields'], null, 'name' );
	ok( 'live' === $fields['secret_key']['mode'] && 'test' === $fields['sandbox_secret_key']['mode'], 'credential fields carry their mode' );
	ok( false === strpos( $fields['secret_key']['help'], '<a' ), 'help text is plain' );

	ok_error( call_api( 'GET', '/settings/payment-gateways/nope' ), 404, 'ohmylms_settings_gateway_not_found', 'unknown gateway' );
	ok_error( call_api( 'PATCH', '/settings/payment-gateways/nope', array( 'enabled' => true ) ), 404, 'ohmylms_settings_gateway_not_found', 'unknown gateway write' );

	// Validation errors write nothing.
	$rejected = array(
		array( array( 1, 2 ), 400, 'ohmylms_settings_invalid_body' ),
		array( array( 'nope' => 1 ), 400, 'ohmylms_settings_unknown_field' ),
		array( array( 'values' => array( 'secret_key' => 'sk_x' ) ), 400, 'ohmylms_settings_unknown_field' ),
		array( array( 'secrets' => array( 'title' => 'x' ) ), 400, 'ohmylms_settings_unknown_field' ),
		array( array( 'values' => array( 'nope' => 'x' ) ), 400, 'ohmylms_settings_unknown_field' ),
		array( array( 'clear_secrets' => array( 'title' ) ), 400, 'ohmylms_settings_unknown_field' ),
		array( array( 'enabled' => 'yes' ), 400, 'rest_invalid_param' ),
		array( array( 'mode' => 'sandbox' ), 400, 'rest_invalid_param' ),
		array( array( 'values' => array( 'title' => str_repeat( 'a', 201 ) ) ), 400, 'rest_invalid_param' ),
		array( array( 'values' => array( 'title' => array( 'x' ) ) ), 400, 'rest_invalid_param' ),
		array( array( 'values' => array( 'title' => '   ' ) ), 422, 'ohmylms_settings_invalid_value' ),
		array( array( 'secrets' => array( 'secret_key' => 'has space' ) ), 400, 'rest_invalid_param' ),
		array( array( 'secrets' => array( 'secret_key' => str_repeat( 'k', 501 ) ) ), 400, 'rest_invalid_param' ),
		array( array( 'secrets' => array( 'secret_key' => 5 ) ), 400, 'rest_invalid_param' ),
		array( array( 'clear_secrets' => 'secret_key' ), 400, 'rest_invalid_param' ),
		array(
			array(
				'secrets'       => array( 'secret_key' => 'sk_live_other' ),
				'clear_secrets' => array( 'secret_key' ),
			),
			422,
			'ohmylms_settings_invalid_value',
		),
		array(
			array(
				'enabled' => true,
				'values'  => array( 'title' => '' ),
			),
			422,
			'ohmylms_settings_invalid_value',
		),
	);
	foreach ( $rejected as $case ) {
		$response = call_api( 'PATCH', '/settings/payment-gateways/stripe', $case[0] );
		ok_error( $response, $case[1], $case[2], 'stripe rejects ' . wp_json_encode( $case[0] ) );
		ok_no_secret( $response, $secrets, 'rejection' );
	}
	ok_error( call_api( 'PATCH', '/settings/payment-gateways/offline', array( 'secrets' => array( 'secret_key' => 'x' ) ) ), 400, 'ohmylms_settings_unknown_field', 'offline has no credentials' );
	ok( snapshot_options() === $seeded, 'rejected gateway requests wrote nothing' );

	// An empty secret keeps the stored value; other fields still save.
	$updated  = array();
	$response = call_api(
		'PATCH',
		'/settings/payment-gateways/stripe',
		array(
			'enabled' => true,
			'mode'    => 'live',
			'values'  => array( 'title' => ' <b>Pay by card</b> ' ),
			'secrets' => array(
				'secret_key'         => '',
				'sandbox_secret_key' => '',
			),
		)
	);
	$data   = $response->get_data();
	$stored = get_option( 'ohmylms_stripe_settings' );
	ok( 200 === $response->get_status(), 'stripe PATCH ok: ' . wp_json_encode( $data ) );
	ok( array( 'enabled', 'mode', 'title' ) === $data['changed'], 'changed lists enabled, mode and title only' );
	ok( $live_secret === $stored['secret_key'] && $test_secret === $stored['sandbox_secret_key'], 'empty secrets kept the stored values' );
	ok( 'yes' === $stored['enabled'] && 'no' === $stored['test_mode'] && 'Pay by card' === $stored['title'] && 'kept' === $stored['legacy_member'], 'stored in the shape the gateway reads; unknown members kept' );
	ok( true === $data['enabled'] && 'live' === $data['mode'] && true === $data['secrets']->secret_key['has_value'], 'response reflects the save' );
	ok_no_secret( $response, $secrets, 'stripe PATCH' );
	ok( array( array( 'stripe', array( 'enabled', 'mode', 'title' ) ) ) === $updated, 'hook fired once with names only' );
	$gateway = new OhMyLMS_Gateway_Stripe_Intents();
	ok( 'yes' === $gateway->enabled && 'no' === $gateway->testmode && $live_secret === $gateway->get_setting( 'secret_key' ), 'the gateway reads the saved settings' );

	// A new secret replaces the stored one and is not echoed.
	$new_secret = 'sk_live_' . $tag . 'ROTATED';
	$secrets[]  = $new_secret;
	$response   = call_api( 'PATCH', '/settings/payment-gateways/stripe', array( 'secrets' => array( 'secret_key' => $new_secret ) ) );
	ok( 200 === $response->get_status() && array( 'secret_key' ) === $response->get_data()['changed'], 'new secret saved' );
	ok( get_option( 'ohmylms_stripe_settings' )['secret_key'] === $new_secret, 'new secret stored' );
	ok_no_secret( $response, $secrets, 'secret rotation' );
	$response = call_api( 'PATCH', '/settings/payment-gateways/stripe', array( 'secrets' => array( 'secret_key' => $new_secret ) ) );
	ok( array() === $response->get_data()['changed'], 'the same secret again changes nothing' );

	// Clear removes it.
	$response = call_api( 'PATCH', '/settings/payment-gateways/stripe', array( 'clear_secrets' => array( 'sandbox_secret_key' ) ) );
	ok( 200 === $response->get_status() && false === $response->get_data()['secrets']->sandbox_secret_key['has_value'], 'clear reports has_value false' );
	ok( '' === get_option( 'ohmylms_stripe_settings' )['sandbox_secret_key'] && get_option( 'ohmylms_stripe_settings' )['secret_key'] === $new_secret, 'clear removed only that secret' );
	$response = call_api( 'PATCH', '/settings/payment-gateways/stripe', array( 'clear_secrets' => array( 'sandbox_secret_key' ) ) );
	ok( array() === $response->get_data()['changed'], 'clearing an empty secret changes nothing' );

	// Toggles (payment methods) and a gateway with a different mode field.
	$response = call_api( 'PATCH', '/settings/payment-gateways/qpay', array( 'mode' => 'test' ) );
	ok( 200 === $response->get_status() && 'yes' === get_option( 'ohmylms_qpay_settings' )['testmode'] && 'test' === $response->get_data()['mode'], 'qpay mode maps to its testmode field' );
	ok( get_option( 'ohmylms_qpay_settings' )['live_client_secret'] === $qpay_secret, 'qpay secret untouched' );
	$offline = call_api( 'GET', '/settings/payment-gateways/offline' )->get_data();
	ok( 'test' === $offline['mode'] || 'live' === $offline['mode'], 'offline has a mode' );
	$response = call_api( 'PATCH', '/settings/payment-gateways/offline', array( 'values' => array( 'instruction' => '<p>Bank transfer</p><script>x</script>' ) ) );
	ok( 200 === $response->get_status() && false === strpos( get_option( 'ohmylms_offline_settings' )['instruction'], '<script' ), 'textarea passes through wp_kses_post' );

	// xAPI: the secret is write-only; empty keeps; clear_secret removes.
	$response = call_api( 'GET', '/xapi/settings' );
	ok( 200 === $response->get_status() && true === $response->get_data()['has_secret'] && ! isset( $response->get_data()['settings']['secret'] ), 'xAPI read reports has_secret only' );
	ok_no_secret( $response, $secrets, 'xAPI read' );
	$xapi = array(
		'enabled'        => false,
		'endpoint'       => 'https://lrs.example.com/xapi',
		'key'            => 'lrs-user',
		'secret'         => '',
		'actor'          => 'pseudonym',
		'responses'      => true,
		'retention_days' => 30,
		'groups'         => array( 'lessons' => false ),
	);
	$response = call_api( 'POST', '/xapi/settings', $xapi );
	ok( 200 === $response->get_status() && $xapi_secret === get_option( 'ohmylms_xapi_settings' )['secret'] && 30 === get_option( 'ohmylms_xapi_settings' )['retention_days'], 'xAPI empty secret keeps the stored one' );
	ok_no_secret( $response, $secrets, 'xAPI save' );
	$response = call_api( 'POST', '/xapi/settings', array_merge( $xapi, array( 'endpoint' => 'ftp://nope' ) ) );
	ok( 400 === $response->get_status() && 30 === get_option( 'ohmylms_xapi_settings' )['retention_days'], 'xAPI invalid endpoint is 400' );
	$response = call_api( 'POST', '/xapi/settings', array_merge( $xapi, array( 'retention_days' => 999 ) ) );
	ok( 400 === $response->get_status(), 'xAPI retention is bounded' );
	$response = call_api( 'POST', '/xapi/settings', array_merge( $xapi, array( 'clear_secret' => true ) ) );
	ok( 200 === $response->get_status() && '' === get_option( 'ohmylms_xapi_settings' )['secret'] && false === $response->get_data()['has_secret'], 'xAPI clear_secret removes the secret' );

	// AI tutor: the key is write-only (hint only); empty keeps; clear_key removes.
	$response = call_api( 'GET', '/ai' );
	ok( 200 === $response->get_status() && true === $response->get_data()['settings']['has_key'] && ! isset( $response->get_data()['settings']['key'] ), 'AI read reports has_key only' );
	ok_no_secret( $response, $secrets, 'AI read' );
	$ai       = array(
		'enabled'      => false,
		'provider'     => 'anthropic',
		'model'        => 'claude-test',
		'key'          => '',
		'hints'        => true,
		'explanations' => true,
		'guests'       => false,
		'daily_limit'  => 12,
		'max_tokens'   => 400,
		'timeout'      => 20,
	);
	$response = call_api( 'POST', '/ai', $ai );
	ok( 200 === $response->get_status() && \OhMyLMS\AI\Settings::api_key() === $ai_key && 12 === \OhMyLMS\AI\Settings::get()['daily_limit'], 'AI empty key keeps the stored key' );
	ok_no_secret( $response, $secrets, 'AI save' );
	ok( false === strpos( wp_json_encode( get_option( 'ohmylms_ai' ) ), $ai_key ), 'AI key is sealed in the database' );
	$response = call_api( 'POST', '/ai', array_merge( $ai, array( 'clear_key' => true ) ) );
	ok( 200 === $response->get_status() && false === $response->get_data()['settings']['has_key'], 'AI clear_key removes the key' );

	// MCP: token records are never returned.
	$response = call_api( 'GET', '/mcp/settings' );
	ok( 200 === $response->get_status() && ! isset( $response->get_data()['token'] ), 'MCP read has no token' );
	ok_no_secret( $response, $secrets, 'MCP read' );
	$response = call_api( 'POST', '/mcp/settings/openai', array( 'action' => 'revoke' ) );
	ok( 200 === $response->get_status() && ! isset( get_option( 'ohmylms_mcp_tokens' )['openai'] ) && ! isset( $response->get_data()['token'] ), 'MCP revoke removes the token' );
	ok( 400 === call_api( 'POST', '/mcp/settings/openai', array( 'action' => 'steal' ) )->get_status(), 'MCP action is validated' );

	// Design system: invalid values are reported by name and nothing is stored.
	$design   = get_option( 'ohmylms_design_system', null );
	$response = call_api(
		'POST',
		'/design-system',
		array(
			'colors'        => array( 'primary' => 'red' ),
			'button_radius' => 'round',
		)
	);
	ok( 400 === $response->get_status() && isset( $response->get_data()['data']['fields']['colors.primary'], $response->get_data()['data']['fields']['button_radius'] ), 'design system names invalid fields' );
	ok( get_option( 'ohmylms_design_system', null ) === $design, 'invalid design system wrote nothing' );
	$response = call_api( 'POST', '/design-system', array_merge( \OhMyLMS\Design\DesignSystem::preset_values( 'classic' ), array( 'density' => 'compact' ) ) );
	ok( 200 === $response->get_status() && 'custom' === $response->get_data()['preset'] && 'compact' === $response->get_data()['density'] && is_bool( $response->get_data()['primary_passes'] ), 'design system saves and reports contrast' );
	$response = call_api( 'DELETE', '/design-system' );
	ok( 200 === $response->get_status() && 'khan' === $response->get_data()['preset'], 'design system resets to the default preset' );

	echo "OK: {$checks} checks passed.\n";
} catch ( Throwable $e ) {
	echo $e->getMessage() . "\n";
	$failed = true;
} finally {
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	restore_options( $before );
	$after_ok = ( snapshot_options() === $before );
	echo $after_ok ? "Options restored exactly.\n" : "WARNING: options differ from the snapshot.\n";
}
exit( ! empty( $failed ) || empty( $after_ok ) ? 1 : 0 );
