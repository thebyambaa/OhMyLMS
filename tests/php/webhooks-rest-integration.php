<?php
/**
 * Webhooks REST API integration test.
 *
 * Runs against a WordPress install (the Local site by default) with real database tables. It only
 * creates tagged test webhooks and a temporary subscriber, and removes everything in `finally`.
 * No network request is made: deliveries are intercepted with the `pre_http_request` filter.
 *
 * Usage (set OHMYLMS_WEBHOOKS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/webhooks-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_WEBHOOKS_TEST' ) ) {
	exit( "Set OHMYLMS_WEBHOOKS_TEST=1 to run the webhooks REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Data\Webhook;
use OhMyLMS\DataStores\WebhookStore;
use OhMyLMS\Integrations\Webhooks\Includes\WebhooksMigration;
use OhMyLMS\Webhooks\DeliveryLog;
use OhMyLMS\Webhooks\WebhookDelivery;

global $wpdb;

$checks   = 0;
$created  = array();
$user_ids = array();
$tag      = 'whtest' . strtolower( wp_generate_password( 6, false, false ) );
$mock     = array(
	'calls'    => array(),
	'response' => null,
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
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/webhooks.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function call_api( $method, $path = '', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/webhooks' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * A valid create payload (a public IP literal so no DNS lookup is needed).
 *
 * @param string $name  Webhook name.
 * @param array  $extra Overrides.
 * @return array
 */
function payload( $name, array $extra = array() ) {
	return array_merge(
		array(
			'name'          => $name,
			'trigger_event' => 'course_enrollment',
			'webhook_url'   => 'https://93.184.216.34/hooks/ohmylms',
		),
		$extra
	);
}

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$mock ) {
		$mock['calls'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( null !== $mock['response'] ) {
			return $mock['response'];
		}
		return array(
			'headers'  => array(),
			'body'     => '{"received":true}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

$fired = array(
	'created' => 0,
	'updated' => 0,
	'deleted' => 0,
	'sent'    => array(),
);
add_action(
	'ohmylms_webhook_created',
	static function () use ( &$fired ) {
		++$fired['created'];
	}
);
add_action(
	'ohmylms_webhook_updated',
	static function () use ( &$fired ) {
		++$fired['updated'];
	}
);
add_action(
	'ohmylms_webhook_deleted',
	static function () use ( &$fired ) {
		++$fired['deleted'];
	}
);
add_action(
	'ohmylms_webhook_sent',
	static function ( $log ) use ( &$fired ) {
		$fired['sent'][] = $log;
	}
);

try {
	WebhooksMigration::ensure_schema();
	ok( WebhooksMigration::table_exists(), 'webhooks table exists' );
	ok( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( DeliveryLog::table() ) ) ) === DeliveryLog::table(), 'deliveries table exists' );
	ok( null !== $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->prefix}ohmylms_webhooks LIKE 'secret'" ), 'secret column exists' );

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

	// Routes and schema.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/webhooks', '/webhooks/(?P<id>[\d]+)', '/webhooks/bulk', '/webhooks/triggers', '/webhooks/test', '/webhooks/(?P<id>[\d]+)/rotate-secret', '/webhooks/(?P<id>[\d]+)/deliveries' ) as $route ) {
		ok( isset( $routes[ '/ohmylms/v1' . $route ] ), 'route registered: ' . $route );
	}
	foreach ( $routes['/ohmylms/v1/webhooks'] as $endpoint ) {
		ok( is_callable( $endpoint['permission_callback'] ) && '__return_true' !== $endpoint['permission_callback'], 'collection permission_callback is a real check' );
	}

	// Anonymous.
	wp_set_current_user( 0 );
	foreach ( array(
		array( 'GET', '' ),
		array( 'POST', '' ),
		array( 'GET', '/triggers' ),
		array( 'POST', '/bulk' ),
		array( 'POST', '/test' ),
		array( 'GET', '/1' ),
		array( 'DELETE', '/1' ),
		array( 'GET', '/1/deliveries' ),
		array( 'POST', '/1/rotate-secret' ),
	) as $case ) {
		$response = call_api(
			$case[0],
			$case[1],
			payload(
				'x',
				array(
					'action' => 'delete',
					'ids'    => array( 1 ),
				)
			)
		);
		ok( 401 === $response->get_status(), 'anonymous ' . $case[0] . ' ' . $case[1] . ' is 401, got ' . $response->get_status() );
	}

	// Subscriber (logged in, no capability).
	wp_set_current_user( $subscriber );
	foreach ( array(
		array( 'GET', '' ),
		array( 'POST', '' ),
		array( 'GET', '/triggers' ),
		array( 'POST', '/bulk' ),
		array( 'POST', '/test' ),
		array( 'GET', '/1' ),
		array( 'PUT', '/1' ),
		array( 'DELETE', '/1' ),
		array( 'GET', '/1/deliveries' ),
		array( 'POST', '/1/rotate-secret' ),
	) as $case ) {
		$response = call_api(
			$case[0],
			$case[1],
			payload(
				'x',
				array(
					'action' => 'delete',
					'ids'    => array( 1 ),
				)
			)
		);
		ok( 403 === $response->get_status(), 'subscriber ' . $case[0] . ' ' . $case[1] . ' is 403, got ' . $response->get_status() );
	}
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_webhooks WHERE name = %s", 'x' ) ), 'forbidden create wrote nothing' );

	// Administrator.
	wp_set_current_user( $admin );

	$triggers = call_api( 'GET', '/triggers' );
	ok( 200 === $triggers->get_status(), 'triggers 200' );
	$trigger_data = $triggers->get_data();
	ok( ! empty( $trigger_data['triggers'][0]['value'] ) && ! empty( $trigger_data['methods'] ) && ! empty( $trigger_data['data_types'] ) && ! empty( $trigger_data['statuses'] ), 'triggers shape' );

	// Create.
	$response = call_api(
		'POST',
		'',
		payload(
			$tag . ' alpha',
			array(
				'data_mapping' => array(
					array(
						'key'   => 'email',
						'value' => 'user_email',
					),
					array(
						'key'   => 'course',
						'value' => 'course_title',
					),
				),
			)
		)
	);
	ok( 201 === $response->get_status(), 'create 201: ' . wp_json_encode( $response->get_data() ) );
	$alpha = $response->get_data();
	$created[] = $alpha['id'];
	ok( rest_url( 'ohmylms/v1/webhooks/' . $alpha['id'] ) === $response->get_headers()['Location'], 'create sets Location' );
	ok( 0 === strpos( $alpha['secret'], 'whsec_' ) && strlen( $alpha['secret'] ) >= 30, 'create returns the generated secret once' );
	ok( 'whsec_****' . substr( $alpha['secret'], -4 ) === $alpha['secret_hint'], 'create returns a masked hint' );
	ok( 'active' === $alpha['status'] && 'POST' === $alpha['http_method'] && 'json' === $alpha['data_type'], 'create defaults' );
	ok( 2 === count( $alpha['data_mapping'] ) && 'email' === $alpha['data_mapping'][0]['key'], 'mapping round trip' );
	ok( 1 === $fired['created'], 'ohmylms_webhook_created fired' );
	ok( preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $alpha['created_at'] ) === 1, 'created_at format' );

	$provided = str_repeat( 'k', 20 );
	$response = call_api(
		'POST',
		'',
		payload(
			$tag . ' bravo',
			array(
				'secret'        => $provided,
				'status'        => 'inactive',
				'trigger_event' => 'quiz_submission',
				'http_method'   => 'PUT',
				'data_type'     => 'form',
			)
		)
	);
	ok( 201 === $response->get_status() && $provided === $response->get_data()['secret'], 'caller supplied secret is honoured' );
	$bravo     = $response->get_data();
	$created[] = $bravo['id'];

	$response = call_api( 'POST', '', payload( $tag . ' Disc%' . $tag . ' pct' ) );
	$created[] = $response->get_data()['id'];
	$pct_id    = $response->get_data()['id'];

	// Read: the secret never leaks.
	$response = call_api( 'GET', '/' . $alpha['id'] );
	ok( 200 === $response->get_status(), 'read 200' );
	$read = $response->get_data();
	ok( ! array_key_exists( 'secret', $read ) && $alpha['secret_hint'] === $read['secret_hint'], 'read masks the secret' );
	ok( null === $read['last_delivery'], 'no delivery yet' );
	ok( false === strpos( wp_json_encode( $read ), $alpha['secret'] ), 'secret not in read body' );

	// Collection.
	$response = call_api( 'GET', '', array( 'search' => $tag ) );
	ok( 200 === $response->get_status(), 'list 200' );
	ok( '3' === $response->get_headers()['X-WP-Total'] && '1' === $response->get_headers()['X-WP-TotalPages'], 'list pagination headers' );
	ok( false === strpos( wp_json_encode( $response->get_data() ), 'whsec_' . substr( $alpha['secret'], 6 ) ) && false === strpos( wp_json_encode( $response->get_data() ), $provided ), 'list never contains secrets' );
	foreach ( $response->get_data() as $item ) {
		ok( ! array_key_exists( 'secret', $item ), 'list item has no secret key' );
	}

	$response = call_api(
		'GET',
		'',
		array(
			'search'   => $tag,
			'per_page' => 2,
			'page'     => 2,
			'orderby'  => 'name',
			'order'    => 'asc',
		)
	);
	ok( 1 === count( $response->get_data() ) && '2' === $response->get_headers()['X-WP-TotalPages'] && '3' === $response->get_headers()['X-WP-Total'], 'second page holds the remainder' );
	ok( isset( $response->get_headers()['Link'] ) && false !== strpos( $response->get_headers()['Link'], 'rel="prev"' ), 'Link prev header' );
	ok(
		400 === call_api(
			'GET',
			'',
			array(
				'search'   => $tag,
				'per_page' => 2,
				'page'     => 3,
			)
		)->get_status(),
		'page past the end is 400'
	);
	ok( 400 === call_api( 'GET', '', array( 'per_page' => 101 ) )->get_status(), 'per_page over 100 is 400' );
	ok( 400 === call_api( 'GET', '', array( 'per_page' => 0 ) )->get_status(), 'per_page 0 is 400' );
	ok( 400 === call_api( 'GET', '', array( 'orderby' => 'id; DROP TABLE wp_users' ) )->get_status(), 'orderby outside the allow-list is 400' );
	ok( 400 === call_api( 'GET', '', array( 'order' => 'sideways' ) )->get_status(), 'bad order is 400' );
	ok( 400 === call_api( 'GET', '', array( 'status' => 'bogus' ) )->get_status(), 'bad status filter is 400' );

	$names = wp_list_pluck(
		call_api(
			'GET',
			'',
			array(
				'search'  => $tag,
				'orderby' => 'name',
				'order'   => 'asc',
			)
		)->get_data(),
		'name'
	);
	$sorted = $names;
	usort( $sorted, 'strcasecmp' );
	ok( $sorted === $names, 'orderby name asc' );
	ok( 1 === count( call_api( 'GET', '', array( 'search' => 'Disc%' . $tag ) )->get_data() ), 'LIKE wildcard % in search is escaped' );
	ok( 0 === count( call_api( 'GET', '', array( 'search' => 'Disc_' . $tag ) )->get_data() ), 'LIKE wildcard _ in search is escaped' );
	ok(
		1 === count(
			call_api(
				'GET',
				'',
				array(
					'search' => $tag,
					'status' => 'inactive',
				)
			)->get_data()
		),
		'status filter'
	);
	ok(
		1 === count(
			call_api(
				'GET',
				'',
				array(
					'search'        => $tag,
					'trigger_event' => 'quiz_submission',
				)
			)->get_data()
		),
		'trigger_event filter'
	);
	$fields = call_api(
		'GET',
		'',
		array(
			'search'  => $tag,
			'_fields' => 'id,status',
		)
	)->get_data();
	ok( array( 'id', 'status' ) === array_values( array_diff( array_keys( $fields[0] ), array( '_links' ) ) ), '_fields is honoured: ' . wp_json_encode( array_keys( $fields[0] ) ) );
	ok(
		3 === count(
			WebhookStore::get_webhooks(
				array(
					'status'  => 'all',
					'search'  => $tag,
					'orderby' => '1; DROP TABLE x',
					'order'   => 'sideways',
				)
			)
		),
		'store ignores a hostile orderby'
	);

	// Validation.
	$bad = array(
		'missing name'        => array(
			'trigger_event' => 'course_enrollment',
			'webhook_url'   => 'https://93.184.216.34/',
		),
		'missing url'         => array(
			'name'          => $tag,
			'trigger_event' => 'course_enrollment',
		),
		'unknown trigger'     => payload( $tag, array( 'trigger_event' => 'nope' ) ),
		'bad method'          => payload( $tag, array( 'http_method' => 'TRACE' ) ),
		'bad data type'       => payload( $tag, array( 'data_type' => 'yaml' ) ),
		'bad status'          => payload( $tag, array( 'status' => 'paused' ) ),
		'mapping not array'   => payload( $tag, array( 'data_mapping' => 'a=b' ) ),
		'mapping bad key'     => payload(
			$tag,
			array(
				'data_mapping' => array(
					array(
						'key'   => 'bad key!',
						'value' => 'user_id',
					),
				),
			)
		),
		'mapping extra field' => payload(
			$tag,
			array(
				'data_mapping' => array(
					array(
						'key'   => 'a',
						'value' => 'b',
						'x'     => 1,
					),
				),
			)
		),
		'short secret'        => payload( $tag, array( 'secret' => 'short' ) ),
		'name too long'       => payload( str_repeat( 'n', 256 ) ),
	);
	foreach ( $bad as $label => $body ) {
		$response = call_api( 'POST', '', $body );
		ok( 400 === $response->get_status() && in_array( $response->get_data()['code'], array( 'rest_invalid_param', 'rest_missing_callback_param' ), true ), "validation 400 for $label (got " . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) . ')' );
	}
	foreach ( array(
		'loopback'        => array( 'http://127.0.0.1/hook', 'ohmylms_webhook_unsafe_url' ),
		'localhost'       => array( 'http://localhost/hook', 'ohmylms_webhook_unsafe_url' ),
		'private 10/8'    => array( 'http://10.0.0.5/hook', 'ohmylms_webhook_unsafe_url' ),
		'private 192.168' => array( 'http://192.168.1.10/hook', 'ohmylms_webhook_unsafe_url' ),
		'cloud metadata'  => array( 'http://169.254.169.254/latest/meta-data', 'ohmylms_webhook_unsafe_url' ),
		'decimal ip'      => array( 'http://2130706433/', 'ohmylms_webhook_unsafe_url' ),
		'ipv6 loopback'   => array( 'http://[::1]/', 'ohmylms_webhook_unsafe_url' ),
		'odd port'        => array( 'https://93.184.216.34:22/', 'ohmylms_webhook_unsafe_url' ),
		'ftp scheme'      => array( 'ftp://93.184.216.34/file', 'ohmylms_webhook_invalid_url' ),
		'credentials'     => array( 'https://user:pass@93.184.216.34/', 'ohmylms_webhook_invalid_url' ),
		'not a url'       => array( 'not a url', 'ohmylms_webhook_invalid_url' ),
		'space in url'    => array( 'https://93.184.216.34/a b', 'ohmylms_webhook_invalid_url' ),
	) as $label => $case ) {
		$response = call_api( 'POST', '', payload( $tag . ' ssrf', array( 'webhook_url' => $case[0] ) ) );
		ok( 422 === $response->get_status() && $case[1] === $response->get_data()['code'], "SSRF create rejected ($label): " . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );
	}
	$response = call_api( 'POST', '', payload( '   ' ) );
	ok( 422 === $response->get_status() && 'ohmylms_webhook_invalid_name' === $response->get_data()['code'], 'blank name is 422' );
	$response = call_api(
		'POST',
		'',
		payload(
			$tag . ' dup',
			array(
				'data_mapping' => array(
					array(
						'key'   => 'a',
						'value' => 'x',
					),
					array(
						'key'   => 'a',
						'value' => 'y',
					),
				),
			)
		)
	);
	ok( 422 === $response->get_status() && 'ohmylms_webhook_invalid_mapping' === $response->get_data()['code'], 'duplicate mapping key is 422' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ohmylms_webhooks WHERE name IN (%s, %s)", $tag . ' ssrf', $tag . ' dup' ) ), 'rejected creates wrote nothing' );

	// Update is a partial update (defaults must not overwrite stored values).
	$response = call_api( 'PUT', '/' . $bravo['id'], array( 'name' => $tag . ' bravo renamed' ) );
	ok( 200 === $response->get_status(), 'update 200' );
	$updated = $response->get_data();
	ok( $tag . ' bravo renamed' === $updated['name'] && 'inactive' === $updated['status'] && 'PUT' === $updated['http_method'] && 'form' === $updated['data_type'] && 'quiz_submission' === $updated['trigger_event'], 'partial update leaves other fields alone' );
	ok( ! array_key_exists( 'secret', $updated ), 'update does not return the secret' );
	$stored_secret = ( new Webhook( $bravo['id'] ) )->get_secret( 'edit' );
	ok( $provided === $stored_secret, 'update keeps the secret' );
	ok( 1 <= $fired['updated'], 'ohmylms_webhook_updated fired' );
	ok( 200 === call_api( 'PATCH', '/' . $bravo['id'], array( 'status' => 'active' ) )->get_status() && 'active' === ( new Webhook( $bravo['id'] ) )->get_status(), 'PATCH status toggles' );
	ok( 422 === call_api( 'PUT', '/' . $bravo['id'], array( 'webhook_url' => 'http://127.0.0.1/' ) )->get_status(), 'update rejects an unsafe URL' );
	ok( 400 === call_api( 'PUT', '/' . $bravo['id'], array( 'status' => 'nope' ) )->get_status(), 'update validates enum' );
	$response = call_api( 'PUT', '/999999999', array( 'name' => 'x' ) );
	ok( 404 === $response->get_status() && 'ohmylms_webhook_not_found' === $response->get_data()['code'], 'update unknown id is 404' );
	ok( 404 === call_api( 'GET', '/999999999' )->get_status(), 'read unknown id is 404' );
	ok( 404 === call_api( 'DELETE', '/999999999' )->get_status(), 'delete unknown id is 404' );
	ok( 404 === call_api( 'GET', '/999999999/deliveries' )->get_status(), 'deliveries unknown id is 404' );
	ok( 404 === call_api( 'POST', '/999999999/rotate-secret' )->get_status(), 'rotate unknown id is 404' );

	// Rotate secret.
	$response = call_api( 'POST', '/' . $alpha['id'] . '/rotate-secret' );
	ok( 200 === $response->get_status() && $response->get_data()['secret'] !== $alpha['secret'] && 0 === strpos( $response->get_data()['secret'], 'whsec_' ), 'rotate-secret returns a new secret' );
	$alpha_secret = $response->get_data()['secret'];

	// Bulk.
	$response = call_api(
		'POST',
		'/bulk',
		array(
			'action' => 'deactivate',
			'ids'    => array( $alpha['id'], $bravo['id'], 999999999 ),
		)
	);
	$bulk     = $response->get_data();
	ok( 200 === $response->get_status() && array( $alpha['id'], $bravo['id'] ) === $bulk['succeeded'], 'bulk deactivate succeeded ids' );
	ok( 1 === count( $bulk['failed'] ) && 999999999 === $bulk['failed'][0]['id'] && 'ohmylms_webhook_not_found' === $bulk['failed'][0]['code'], 'bulk reports missing ids' );
	ok( 'inactive' === ( new Webhook( $alpha['id'] ) )->get_status(), 'bulk deactivate applied' );
	call_api(
		'POST',
		'/bulk',
		array(
			'action' => 'activate',
			'ids'    => array( $alpha['id'] ),
		)
	);
	ok( 'active' === ( new Webhook( $alpha['id'] ) )->get_status(), 'bulk activate applied' );
	foreach ( array(
		'legacy action key' => array(
			'action' => 'active',
			'ids'    => array( 1 ),
		),
		'empty ids'         => array(
			'action' => 'delete',
			'ids'    => array(),
		),
		'bad id'            => array(
			'action' => 'delete',
			'ids'    => array( 'abc' ),
		),
		'duplicate ids'     => array(
			'action' => 'delete',
			'ids'    => array( 1, 1 ),
		),
		'too many ids'      => array(
			'action' => 'delete',
			'ids'    => range( 1, 101 ),
		),
		'missing action'    => array( 'ids' => array( 1 ) ),
	) as $label => $body ) {
		ok( 400 === call_api( 'POST', '/bulk', $body )->get_status(), "bulk validation 400: $label" );
	}

	// Test delivery.
	$mock['calls'] = array();
	$response      = call_api( 'POST', '/test', array( 'id' => $alpha['id'] ) );
	$result        = $response->get_data();
	ok( 200 === $response->get_status() && true === $result['success'], 'test delivery succeeded: ' . wp_json_encode( $result ) );
	ok( 200 === $result['response']['status_code'] && '{"received":true}' === $result['response']['body'] && is_int( $result['response']['duration_ms'] ), 'test response block' );
	ok( 'course_enrollment' === $result['request']['event'] && 'https://93.184.216.34/hooks/ohmylms' === $result['request']['url'], 'test request block' );
	ok( 1 === count( $mock['calls'] ), 'exactly one outbound request' );
	$call = $mock['calls'][0];
	ok( 0 === $call['args']['redirection'], 'redirects are disabled' );
	ok( ! empty( $call['args']['reject_unsafe_urls'] ), 'sent through the safe HTTP API' );
	ok( $call['args']['timeout'] <= 5, 'short timeout' );
	$sent_body = $call['args']['body'];
	$decoded   = json_decode( $sent_body, true );
	ok( array( 'email', 'course' ) === array_keys( $decoded ) && 'student@example.com' === $decoded['email'], 'sample payload honours the data mapping' );
	$headers = $call['args']['headers'];
	ok( '1' === $headers['X-OhMyLMS-Test'] && 'course_enrollment' === $headers['X-OhMyLMS-Event'] && ! empty( $headers['X-OhMyLMS-Delivery'] ), 'delivery headers' );
	ok( 'sha256=' . hash_hmac( 'sha256', $headers['X-OhMyLMS-Timestamp'] . '.' . $sent_body, $alpha_secret ) === $headers['X-OhMyLMS-Signature'], 'signature verifies with the stored secret' );
	ok( 'application/json' === $headers['Content-Type'], 'content type' );
	ok( 1 === count( $fired['sent'] ) && 'success' === $fired['sent'][0]['status'] && true === $fired['sent'][0]['is_test'], 'ohmylms_webhook_sent fired for the test' );

	$deliveries = call_api( 'GET', '/' . $alpha['id'] . '/deliveries' );
	ok( 200 === $deliveries->get_status() && '1' === $deliveries->get_headers()['X-WP-Total'], 'delivery logged' );
	$row = $deliveries->get_data()[0];
	ok( 'success' === $row['status'] && 200 === $row['response_code'] && true === $row['is_test'] && $result['delivery_id'] === $row['delivery_id'], 'delivery row' );
	ok( 'success' === call_api( 'GET', '/' . $alpha['id'] )->get_data()['last_delivery']['status'], 'read exposes last_delivery' );
	ok( 1 === count( call_api( 'GET', '/' . $alpha['id'] . '/deliveries', array( 'status' => 'success' ) )->get_data() ) && 0 === count( call_api( 'GET', '/' . $alpha['id'] . '/deliveries', array( 'status' => 'failed' ) )->get_data() ), 'deliveries status filter' );

	// Unsaved overrides: no id, nothing logged, secret optional.
	$mock['calls'] = array();
	$response      = call_api(
		'POST',
		'/test',
		array(
			'webhook_url'   => 'https://93.184.216.34/adhoc',
			'data_type'     => 'xml',
			'trigger_event' => 'quiz_submission',
		)
	);
	ok( 200 === $response->get_status() && true === $response->get_data()['success'], 'ad hoc test succeeded' );
	ok( 'https://93.184.216.34/adhoc' === $mock['calls'][0]['url'] && 'application/xml' === $mock['calls'][0]['args']['headers']['Content-Type'] && 0 === strpos( $mock['calls'][0]['args']['body'], '<?xml' ), 'xml body' );
	ok( ! isset( $mock['calls'][0]['args']['headers']['X-OhMyLMS-Signature'] ), 'unsigned without a secret' );
	ok( false !== simplexml_load_string( $mock['calls'][0]['args']['body'] ), 'xml is well formed' );

	// Failure shapes.
	$mock['response'] = array(
		'headers'  => array(),
		'body'     => str_repeat( 'é', 2500 ),
		'response' => array(
			'code'    => 500,
			'message' => 'Server Error',
		),
		'cookies'  => array(),
		'filename' => null,
	);
	$result = call_api( 'POST', '/test', array( 'id' => $alpha['id'] ) )->get_data();
	ok( false === $result['success'] && 500 === $result['response']['status_code'] && 'ohmylms_webhook_http_error' === $result['error']['code'], 'HTTP 500 is a failed delivery' );
	ok( true === $result['response']['truncated'] && 2000 === mb_strlen( $result['response']['body'], 'UTF-8' ), 'response body truncated to 2000 characters' );

	$mock['response'] = array(
		'headers'  => array( 'location' => 'http://127.0.0.1/internal' ),
		'body'     => '',
		'response' => array(
			'code'    => 302,
			'message' => 'Found',
		),
		'cookies'  => array(),
		'filename' => null,
	);
	$result = call_api( 'POST', '/test', array( 'id' => $alpha['id'] ) )->get_data();
	ok( false === $result['success'] && 302 === $result['response']['status_code'], 'a redirect is reported, not followed' );

	$mock['response'] = new WP_Error( 'http_request_failed', 'cURL error 28: Connection timed out' );
	$result           = call_api( 'POST', '/test', array( 'id' => $alpha['id'] ) )->get_data();
	ok( false === $result['success'] && 0 === $result['response']['status_code'] && 'ohmylms_webhook_request_failed' === $result['error']['code'], 'transport error' );
	$mock['response'] = null;

	$failed = call_api( 'GET', '/' . $alpha['id'] . '/deliveries', array( 'status' => 'failed' ) );
	ok( '3' === $failed->get_headers()['X-WP-Total'], 'failed deliveries logged' );

	// Test endpoint refusals.
	$mock['calls'] = array();
	$response      = call_api( 'POST', '/test', array( 'webhook_url' => 'http://127.0.0.1:8080/' ) );
	ok( 422 === $response->get_status() && 'ohmylms_webhook_unsafe_url' === $response->get_data()['code'], 'test refuses a loopback URL' );
	$response = call_api(
		'POST',
		'/test',
		array(
			'id'          => $alpha['id'],
			'webhook_url' => 'http://169.254.169.254/',
		)
	);
	ok( 422 === $response->get_status(), 'test refuses an unsafe URL override on a saved webhook' );
	ok( array() === $mock['calls'], 'no outbound request for refused targets' );
	ok( 400 === call_api( 'POST', '/test', array() )->get_status() && 'ohmylms_webhook_test_target_required' === call_api( 'POST', '/test', array() )->get_data()['code'], 'test needs a target' );
	ok( 404 === call_api( 'POST', '/test', array( 'id' => 999999999 ) )->get_status(), 'test unknown id is 404' );
	$delivery = WebhookDelivery::send( new Webhook(), array(), array( 'log' => false ) );
	ok( false === $delivery['success'] && 'ohmylms_webhook_invalid_url' === $delivery['error']['code'] && array() === $mock['calls'], 'WebhookDelivery refuses an empty URL without a request' );

	// Real delivery path (GET query string, mapping, inactive webhooks are never executed).
	$get_hook = new Webhook();
	$get_hook->set_props(
		array(
			'name'          => $tag . ' get',
			'trigger_event' => 'lesson_completion',
			'webhook_url'   => 'https://93.184.216.34/get?x=1',
			'http_method'   => 'GET',
			'data_type'     => 'json',
			'status'        => 'active',
		)
	);
	$mock['calls'] = array();
	$result        = WebhookDelivery::send( $get_hook, array( 'a' => 'b c' ), array( 'log' => false ) );
	ok( true === $result['success'] && 'https://93.184.216.34/get?x=1&a=b+c' === $mock['calls'][0]['url'] && '' === $mock['calls'][0]['args']['body'], 'GET puts the payload in the query string: ' . wp_json_encode( array( $result['success'], $result['error'], $mock['calls'][0]['url'], $mock['calls'][0]['args']['body'] ) ) );
	$get_hook->set_status( 'inactive' );
	ok( false === $get_hook->execute( array( 'a' => 'b' ) ), 'execute() skips inactive webhooks' );
	ok( 'sha256=' !== substr( WebhookDelivery::sign( 'secret', 1, 'body' ), 0, 7 ) && 64 === strlen( WebhookDelivery::sign( 'secret', 1, 'body' ) ), 'sign() returns a hex sha256' );

	// Rate limit.
	delete_transient( 'ohmylms_wh_test_' . $admin );
	add_filter(
		'ohmylms_webhook_test_rate_limit',
		static function () {
			return 3;
		}
	);
	for ( $i = 0; $i < 3; $i++ ) {
		ok( 200 === call_api( 'POST', '/test', array( 'id' => $alpha['id'] ) )->get_status(), 'test within the quota ' . $i );
	}
	$response = call_api( 'POST', '/test', array( 'id' => $alpha['id'] ) );
	ok( 429 === $response->get_status() && 'ohmylms_webhook_test_rate_limited' === $response->get_data()['code'] && ! empty( $response->get_headers()['Retry-After'] ), 'test endpoint is rate limited' );
	delete_transient( 'ohmylms_wh_test_' . $admin );

	// Delete.
	$delivery_rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . DeliveryLog::table() . ' WHERE webhook_id = %d', $alpha['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	ok( $delivery_rows > 0, 'deliveries exist before delete' );
	$response = call_api( 'DELETE', '/' . $alpha['id'] );
	ok( 200 === $response->get_status() && true === $response->get_data()['deleted'] && $alpha['id'] === $response->get_data()['previous']['id'], 'delete 200 with previous' );
	ok( ! array_key_exists( 'secret', $response->get_data()['previous'] ), 'delete response has no secret' );
	ok( 404 === call_api( 'GET', '/' . $alpha['id'] )->get_status(), 'deleted webhook is gone' );
	ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . DeliveryLog::table() . ' WHERE webhook_id = %d', $alpha['id'] ) ), 'deliveries deleted with the webhook' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	ok( $fired['deleted'] >= 1, 'ohmylms_webhook_deleted fired' );

	$response = call_api(
		'POST',
		'/bulk',
		array(
			'action' => 'delete',
			'ids'    => array( $bravo['id'], $pct_id ),
		)
	);
	ok( array( $bravo['id'], $pct_id ) === $response->get_data()['succeeded'], 'bulk delete' );
	ok( 0 === count( call_api( 'GET', '', array( 'search' => $tag ) )->get_data() ), 'all test webhooks gone' );

	// Schema.
	$options = rest_do_request( new WP_REST_Request( 'OPTIONS', '/ohmylms/v1/webhooks' ) );
	$schema  = $options->get_data()['schema'];
	ok( 'http://json-schema.org/draft-04/schema#' === $schema['$schema'] && 'webhook' === $schema['title'], 'schema header' );
	foreach ( array( 'id', 'name', 'trigger_event', 'webhook_url', 'http_method', 'data_type', 'data_mapping', 'status', 'secret', 'secret_hint', 'last_delivery', 'created_at', 'updated_at' ) as $property ) {
		ok( isset( $schema['properties'][ $property ] ), "schema property $property" );
	}
	ok( ! empty( $schema['properties']['id']['readonly'] ), 'schema marks id readonly' );

	fwrite( STDOUT, "OK webhooks-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	// Remove only what this run created.
	$ids = array_map( 'intval', $created );
	$ids = array_merge( $ids, array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_webhooks WHERE name LIKE %s", $wpdb->esc_like( $tag ) . '%' ) ) ) );
	foreach ( array_unique( $ids ) as $leftover ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_webhooks', array( 'id' => $leftover ), array( '%d' ) );
		DeliveryLog::delete_for_webhook( $leftover );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
	if ( isset( $admin ) ) {
		delete_transient( 'ohmylms_wh_test_' . $admin );
	}
}

exit( isset( $exit_code ) ? $exit_code : 0 );
