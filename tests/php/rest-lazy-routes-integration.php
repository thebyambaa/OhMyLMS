<?php
/**
 * On-demand REST controller registration integration test.
 *
 * Runs against a WordPress install (the Local site by default). It only reads through GET routes and
 * rebuilds the `ohmylms_rest_route_map` option, which the plugin regenerates by itself.
 *
 * Usage (set OHMYLMS_REST_LAZY_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/rest-lazy-routes-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_REST_LAZY_TEST' ) ) {
	exit( "Set OHMYLMS_REST_LAZY_TEST=1 to run the lazy REST route tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

$checks = 0;
$option = \OhMyLMS\Rest\Api::MAP_OPTION;
$seen   = array();
$probes = array( '/app/data', '/dashboard', '/courses', '/lessons', '/students', '/questions', '/chapters', '/settings', '/nothing-registered-here' );

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
 * Start a new REST server, firing `rest_api_init` again.
 *
 * @return WP_REST_Server
 */
function fresh_server() {
	$GLOBALS['wp_rest_server'] = null;
	return rest_get_server();
}

/**
 * List the plugin's route patterns with their methods, in registration order.
 *
 * @param WP_REST_Server $server Server.
 * @return array<string,string[]>
 */
function plugin_routes( $server ) {
	$routes = array();
	foreach ( $server->get_routes() as $pattern => $handlers ) {
		if ( 0 !== strpos( $pattern, '/ohmylms/v1/' ) && 0 !== strpos( $pattern, '/ohmylms-commerce/v1/' ) ) {
			continue;
		}
		$methods = array();
		foreach ( $handlers as $handler ) {
			$methods[] = implode( '|', array_keys( $handler['methods'] ) );
		}
		$routes[ $pattern ] = $methods;
	}
	return $routes;
}

/**
 * Group route patterns by `/namespace/first-segment`, keeping their order.
 *
 * @param array<string,string[]> $routes Routes from plugin_routes().
 * @return array<string,string[]>
 */
function by_prefix( array $routes ) {
	$groups = array();
	foreach ( array_keys( $routes ) as $pattern ) {
		$parts                                                 = explode( '/', $pattern );
		$groups[ strtolower( implode( '/', array_slice( $parts, 0, 4 ) ) ) ][] = $pattern;
	}
	return $groups;
}

/**
 * Describe a GET response: status and the shape of its data.
 *
 * @param string $path Path below /ohmylms/v1.
 * @return string
 */
function probe( $path ) {
	$response = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1' . $path ) );
	$data     = $response->get_data();
	$shape    = is_array( $data ) ? array_keys( $data ) : gettype( $data );
	if ( is_array( $data ) && isset( $data['code'] ) ) {
		$shape = $data['code'];
	}
	return $response->get_status() . ' ' . wp_json_encode( $shape );
}

// What the server matches a request against: the endpoints after every `rest_endpoints` filter.
add_filter(
	'rest_endpoints',
	static function ( $endpoints ) {
		global $seen;
		$seen = array();
		foreach ( array_keys( $endpoints ) as $pattern ) {
			if ( 0 === strpos( $pattern, '/ohmylms/v1/' ) || 0 === strpos( $pattern, '/ohmylms-commerce/v1/' ) ) {
				$seen[ $pattern ] = true;
			}
		}
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
	ok( ! empty( $admins ), 'the site has an administrator' );
	wp_set_current_user( (int) $admins[0] );

	// Full registration is the reference.
	add_filter( 'ohmylms_rest_lazy_routes', '__return_false' );
	$eager_routes = plugin_routes( fresh_server() );
	$expected     = array();
	foreach ( $probes as $path ) {
		$expected[ $path ] = probe( $path );
	}
	remove_filter( 'ohmylms_rest_lazy_routes', '__return_false' );
	ok( count( $eager_routes ) > 300, 'full registration lists the plugin routes' );
	ok( 0 === strpos( $expected['/nothing-registered-here'], '404' ), 'an unknown route is a 404' );

	// Without a map everything is registered once and the map is stored.
	delete_option( $option );
	ok( plugin_routes( fresh_server() ) === $eager_routes, 'building the map registers every route in the same order' );
	$map = get_option( $option );
	ok( is_array( $map ) && ! empty( $map['lazy'] ) && count( $map['groups'] ) > 10, 'the map is stored and allows lazy registration' );
	$group = $map['groups'][ $map['prefixes']['/ohmylms/v1/chapters'] ];
	ok( in_array( \OhMyLMS\Rest\V1\CourseStructureController::class, $group, true ), 'the structure controller is grouped with the chapter routes its filters adapt' );

	// With a map a dispatch registers the requested group only.
	fresh_server();
	probe( '/app/data' );
	ok( isset( $seen['/ohmylms/v1/app/data'] ) && count( $seen ) < count( $eager_routes ) / 4, 'a request is matched against its own group, not every route' );
	foreach ( $probes as $path ) {
		ok( probe( $path ) === $expected[ $path ], "lazy response for $path matches full registration: " . $expected[ $path ] );
	}
	ok( is_array( get_option( $option ) ), 'a missing route does not discard the map' );

	// A group is registered whole and in the listed order.
	fresh_server();
	probe( '/chapters' );
	$partial = by_prefix( $seen );
	$full    = by_prefix( $eager_routes );
	ok( isset( $partial['/ohmylms/v1/courses'] ) && ! isset( $partial['/ohmylms/v1/students'] ), 'a chapter request registers its group only' );
	ok( $partial['/ohmylms/v1/courses'] === $full['/ohmylms/v1/courses'], 'course routes keep their registration order' );
	ok( $partial['/ohmylms/v1/chapters'] === $full['/ohmylms/v1/chapters'], 'chapter routes keep their registration order' );

	// Code that lists routes without dispatching a request sees all of them.
	$listed = plugin_routes( fresh_server() );
	ok( by_prefix( $listed ) == by_prefix( $eager_routes ), 'listing routes registers every route, in the same order within each segment' ); // phpcs:ignore Universal.Operators.StrictComparisons -- Segment order may differ; each segment's own order may not.
	ksort( $listed );
	$sorted = $eager_routes;
	ksort( $sorted );
	ok( $listed === $sorted, 'listed routes have the same methods as with full registration' );

	// The namespace index and the root index list everything.
	foreach ( array( '/ohmylms/v1', '/' ) as $index ) {
		$server = fresh_server();
		rest_do_request( new WP_REST_Request( 'GET', $index ) );
		$listed = plugin_routes( $server );
		ksort( $listed );
		$sorted = $eager_routes;
		ksort( $sorted );
		ok( $listed === $sorted, "the index $index lists every route and method" );
		ok( by_prefix( plugin_routes( $server ) ) == by_prefix( $eager_routes ), "routes keep their order within each segment after $index" ); // phpcs:ignore Universal.Operators.StrictComparisons -- Segment order may differ; each segment's own order may not.
	}

	// A map older than the code repairs itself.
	$stale = get_option( $option );
	unset( $stale['prefixes']['/ohmylms/v1/students'] );
	update_option( $option, $stale, true );
	fresh_server();
	ok( probe( '/students' ) === $expected['/students'], 'a route missing from the map is still served' );
	ok( false === get_option( $option ), 'a stale map is discarded' );
	fresh_server();
	$rebuilt = get_option( $option );
	ok( is_array( $rebuilt ) && isset( $rebuilt['prefixes']['/ohmylms/v1/students'] ), 'the map is rebuilt on the next request' );

	echo "OK: $checks checks\n";
} catch ( Throwable $error ) {
	echo $error->getMessage(), "\n";
	$exit_code = 1;
	delete_option( $option );
}

exit( $exit_code );
