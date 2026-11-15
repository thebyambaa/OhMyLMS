<?php
/**
 * On-demand MCP adapter integration test.
 *
 * Runs against a WordPress install (the Local site by default) with the MCP add-on enabled. Read-only.
 *
 * Usage (set OHMYLMS_MCP_LAZY_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/mcp-lazy-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_MCP_LAZY_TEST' ) ) {
	exit( "Set OHMYLMS_MCP_LAZY_TEST=1 to run the lazy MCP adapter tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

if ( ! \OhMyLMS\MCP\Server::enabled() || ! function_exists( 'wp_register_ability' ) ) {
	exit( "Usage: enable the MCP add-on on a WordPress with the Abilities API to run these checks.\n" );
}

$checks = 0;
$seen   = array();

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

// What the server matches a request against.
add_filter(
	'rest_endpoints',
	static function ( $endpoints ) {
		global $seen;
		$seen = $endpoints;
		return $endpoints;
	},
	PHP_INT_MAX
);

$exit_code = 0;
try {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $admins[0] );

	// An ordinary request does not start the adapter.
	$response = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/app/data' ) );
	ok( 200 === $response->get_status(), 'an ordinary route answers' );
	ok( ! class_exists( '\WP\MCP\Core\McpServer', false ), 'an ordinary request does not load the MCP server classes' );
	ok( ! isset( $seen['/ohmylms/v1/mcp'] ), 'an ordinary request does not register the MCP route' );

	// The add-on's own settings route reports readiness, which needs the adapter.
	$response = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/mcp/settings' ) );
	ok( 200 === $response->get_status(), 'the MCP settings route answers' );
	ok( isset( $seen['/ohmylms/v1/mcp'] ), 'an MCP request registers the MCP route' );
	$servers = \WP\MCP\Core\McpAdapter::instance()->get_servers();
	ok( isset( $servers['ohmylms'] ) && count( $servers['ohmylms']->get_tools() ) > 10, 'the OhMyLMS MCP server is created with its tools' );
	ok( \OhMyLMS\MCP\Server::readiness()['ready'], 'the add-on reports ready' );

	// The MCP endpoint exists: without credentials it refuses, it is not missing.
	$request = new WP_REST_Request( 'POST', '/ohmylms/v1/mcp' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{}}' );
	$response = rest_do_request( $request );
	ok( 404 !== $response->get_status(), 'the MCP endpoint is registered: ' . $response->get_status() );

	// A new server that only lists routes sees the MCP routes once each.
	$GLOBALS['wp_rest_server'] = null;
	$routes                    = rest_get_server()->get_routes();
	ok( isset( $routes['/ohmylms/v1/mcp'], $routes['/ohmylms/v1/mcp/settings'] ), 'listing routes includes the MCP routes' );

	echo "OK: $checks checks\n";
} catch ( Throwable $error ) {
	echo $error->getMessage(), "\n";
	$exit_code = 1;
}

exit( $exit_code );
