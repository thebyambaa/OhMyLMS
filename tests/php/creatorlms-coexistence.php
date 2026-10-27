<?php
/**
 * Exercise independent LMS runtimes and real activation in an isolated site.
 *
 * Run each scenario in a fresh PHP process with OHMYLMS_TEST_CREDENTIALS set.
 * The isolated site's plugins directory must contain both plugin checkouts.
 *
 * @package OhMyLMS\Tests
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$coexistence_stream = fopen( getenv( 'OHMYLMS_TEST_CREDENTIALS' ), 'rb' );
$coexistence_config = json_decode( stream_get_contents( $coexistence_stream ), true );
fclose( $coexistence_stream );
$coexistence_mode  = $argv[1] ?? 'ohmylms-first';
$coexistence_cases = array(
	'ohmylms-first'       => array( 'OhMyLMS/ohmylms.php', 'creatorlms/creatorlms.php' ),
	'creatorlms-first'    => array( 'creatorlms/creatorlms.php', 'OhMyLMS/ohmylms.php' ),
	'activate-creatorlms' => array( 'OhMyLMS/ohmylms.php' ),
	'activate-ohmylms'    => array( 'creatorlms/creatorlms.php' ),
	'ohmylms-only'        => array( 'OhMyLMS/ohmylms.php' ),
	'ohmylms-legacy'      => array( 'OhMyLMS/ohmylms.php' ),
);
if ( ! isset( $coexistence_cases[ $coexistence_mode ] ) ) {
	throw new InvalidArgumentException( 'Unknown coexistence scenario.' );
}

define( 'WP_ADMIN', true );
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
if ( 'ohmylms-legacy' === $coexistence_mode ) {
	define( 'WP_PLUGIN_DIR', $coexistence_config['site'] . '/wp-content/standalone-plugins' );
}
$_SERVER['HTTP_HOST'] = '127.0.0.1:8780';
$coexistence_filter   = static function () use ( $coexistence_cases, $coexistence_mode ) {
	return $coexistence_cases[ $coexistence_mode ];
};
// Load the hook API early to override the fixture site's usual plugin list.
require_once $coexistence_config['site'] . '/wp-includes/plugin.php';
add_filter( 'pre_option_active_plugins', $coexistence_filter, PHP_INT_MAX );
require $coexistence_config['site'] . '/wp-load.php';

if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the isolated WordPress test database.' );
}

/**
 * Assert a coexistence behavior.
 *
 * @param bool   $condition Expected outcome.
 * @param string $message Failure description.
 * @return void
 * @throws RuntimeException When a coexistence assertion fails.
 */
function ohmylms_coexistence_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['ohmylms_coexistence_checks'];
}

$GLOBALS['ohmylms_coexistence_checks'] = 0;
wp_set_current_user( get_user_by( 'login', $coexistence_config['username'] )->ID );

if ( 0 === strpos( $coexistence_mode, 'activate-' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$coexistence_plugin = 'activate-ohmylms' === $coexistence_mode ? 'OhMyLMS/ohmylms.php' : 'creatorlms/creatorlms.php';
	remove_filter( 'pre_option_active_plugins', $coexistence_filter, PHP_INT_MAX );
	$coexistence_saved_plugins = get_option( 'active_plugins', array() );
	add_filter( 'pre_option_active_plugins', $coexistence_filter, PHP_INT_MAX );
	register_shutdown_function(
		static function () use ( $coexistence_saved_plugins ) {
			update_option( 'active_plugins', $coexistence_saved_plugins );
		}
	);
	$coexistence_result = activate_plugin( $coexistence_plugin );
	ohmylms_coexistence_check( ! is_wp_error( $coexistence_result ), 'Activating the second LMS failed.' );
	ohmylms_coexistence_check( function_exists( 'ohmylms' ) && function_exists( 'CRLMS' ), 'Both LMS entrypoints must be loaded.' );
	ohmylms_coexistence_check( class_exists( 'OhMyLMS_Commerce_Package', false ) && class_exists( 'Package', false ), 'Activation must load separate commerce packages.' );
	echo esc_html( $coexistence_mode ) . ': ' . (int) $GLOBALS['ohmylms_coexistence_checks'] . " checks passed.\n";
	exit;
}

$coexistence_ohmy = \OhMyLMS\Commerce\ecommerce();
ohmylms_coexistence_check( $coexistence_ohmy->cart instanceof \OhMyLMS\Commerce\Cart, 'OhMyLMS must initialize its own cart.' );
ohmylms_coexistence_check( 'ohmylms' === OHMYLMS_ECOMMERCE_MODULE_PREFIX, 'OhMyLMS hooks must retain their prefix.' );
ohmylms_coexistence_check( post_type_exists( 'ohmylms-course' ), 'OhMyLMS courses must remain registered.' );
ohmylms_coexistence_check( $coexistence_ohmy->gateways()->get_payment_gateways()['offline'] instanceof \OhMyLMS_Gateway_Offline, 'OhMyLMS must own its offline gateway.' );
ohmylms_coexistence_check( $coexistence_ohmy->gateways()->get_payment_gateways()['stripe'] instanceof \OhMyLMS_Gateway_Stripe_Intents, 'OhMyLMS must own its Stripe gateway.' );

$coexistence_routes = rest_get_server()->get_routes();
ohmylms_coexistence_check( isset( $coexistence_routes['/ohmylms-commerce/v1/orders'] ), 'OhMyLMS must expose its own order API.' );
ohmylms_coexistence_check( $coexistence_routes['/ohmylms-commerce/v1/orders'][0]['callback'][0] instanceof \OhMyLMS\Commerce\Rest\V1\OrdersController, 'OhMyLMS routes must use its own controllers.' );

if ( in_array( $coexistence_mode, array( 'ohmylms-only', 'ohmylms-legacy' ), true ) ) {
	ohmylms_coexistence_check( $coexistence_routes['/cx-ecommerce/v1/orders'][0]['callback'][0] instanceof \OhMyLMS\Commerce\Rest\V1\OrdersController, 'Standalone sites must retain the legacy commerce API.' );
	if ( 'ohmylms-legacy' === $coexistence_mode ) {
		ohmylms_coexistence_check( \CodeRex\Ecommerce\ecommerce() === $coexistence_ohmy, 'Legacy commerce helpers must resolve to the OhMyLMS singleton.' );
		ohmylms_coexistence_check( is_a( 'CodeRex\\Ecommerce\\Cart', 'OhMyLMS\\Commerce\\Cart', true ), 'Legacy class aliases must preserve extension inheritance.' );
		ohmylms_coexistence_check( \Package::get_path() === \OhMyLMS_Commerce_Package::get_path(), 'Legacy package helpers must remain available.' );
		ohmylms_coexistence_check( get_recent_course_ids() === ohmylms_get_recent_course_ids(), 'Legacy course helpers must retain their results.' );
		ohmylms_coexistence_check( 'ohmylms' === ECOMMERCE_MODULE_PREFIX, 'Standalone legacy constants must resolve to OhMyLMS.' );
	} else {
		ohmylms_coexistence_check( ! function_exists( 'ecommerce_get_order' ), 'Installing CreatorLMS must reserve its helper symbols before activation.' );
	}
	echo esc_html( $coexistence_mode ) . ': ' . (int) $GLOBALS['ohmylms_coexistence_checks'] . " checks passed.\n";
	exit;
}

$coexistence_creator = \CodeRex\Ecommerce\ecommerce();
ohmylms_coexistence_check( $coexistence_creator->cart instanceof \CodeRex\Ecommerce\Cart, 'CreatorLMS must initialize its own cart.' );
ohmylms_coexistence_check( $coexistence_ohmy !== $coexistence_creator && $coexistence_ohmy->session !== $coexistence_creator->session, 'Commerce instances and sessions must be separate.' );
ohmylms_coexistence_check( 'creator_lms' === ECOMMERCE_MODULE_PREFIX, 'CreatorLMS must retain its commerce prefix.' );
ohmylms_coexistence_check( post_type_exists( 'crlms-course' ), 'CreatorLMS courses must remain registered.' );
foreach ( array( 'course_category', 'course_tag' ) as $coexistence_taxonomy ) {
	ohmylms_coexistence_check( is_object_in_taxonomy( 'ohmylms-course', $coexistence_taxonomy ) && is_object_in_taxonomy( 'crlms-course', $coexistence_taxonomy ), 'Established course taxonomies must support both course types in either load order.' );
}
ohmylms_coexistence_check( $coexistence_creator->gateways()->get_payment_gateways()['offline'] instanceof \GatewayOffline, 'CreatorLMS must retain its gateway.' );
ohmylms_coexistence_check( $coexistence_routes['/cx-ecommerce/v1/orders'][0]['callback'][0] instanceof \CodeRex\Ecommerce\Rest\V1\OrdersController, 'The legacy commerce API must belong to CreatorLMS when both run.' );

$coexistence_cookie_property = new ReflectionProperty( $coexistence_ohmy->session, '_cookie' );
$coexistence_creator_cookie  = new ReflectionProperty( $coexistence_creator->session, '_cookie' );
ohmylms_coexistence_check( $coexistence_cookie_property->getValue( $coexistence_ohmy->session ) !== $coexistence_creator_cookie->getValue( $coexistence_creator->session ), 'Session cookie names must be separate.' );

$coexistence_posts = array();
$coexistence_code  = 'coexistence-' . strtolower( wp_generate_password( 10, false, false ) );
try {
	foreach ( array( 'ohmylms_coupon', 'crlms_coupon' ) as $coexistence_post_type ) {
		$coexistence_posts[] = wp_insert_post(
			array(
				'post_type'   => $coexistence_post_type,
				'post_status' => 'publish',
				'post_title'  => $coexistence_code,
			)
		);
	}
	ohmylms_coexistence_check( $coexistence_posts[0] > 0 && $coexistence_posts[1] > 0, 'Both coupon fixtures must be created.' );
	ohmylms_coexistence_check( ohmylms_ecommerce_get_coupon_id_by_code( $coexistence_code ) === $coexistence_posts[0], 'OhMyLMS coupon lookup must select its own record.' );
	ohmylms_coexistence_check( ecommerce_get_coupon_id_by_code( $coexistence_code ) === $coexistence_posts[1], 'CreatorLMS coupon lookup must select its own record.' );
	$coexistence_coupon = new \OhMyLMS\Commerce\Data\Coupon( $coexistence_code );
	ohmylms_coexistence_check( $coexistence_posts[0] === $coexistence_coupon->get_id(), 'OhMyLMS coupon objects must read their own store.' );
	$coexistence_coupon = new \CodeRex\Ecommerce\Data\Coupon( $coexistence_code );
	ohmylms_coexistence_check( $coexistence_posts[1] === $coexistence_coupon->get_id(), 'CreatorLMS coupon objects must read their own store.' );

	foreach ( array( '/ohmylms-commerce/v1/orders', '/cx-ecommerce/v1/orders' ) as $coexistence_route ) {
		$coexistence_response = rest_do_request( new WP_REST_Request( 'GET', $coexistence_route ) );
		ohmylms_coexistence_check( 200 === $coexistence_response->get_status(), 'An administrator must access each order API independently.' );
	}
	wp_set_current_user( 0 );
	foreach ( array( '/ohmylms-commerce/v1/orders', '/cx-ecommerce/v1/orders' ) as $coexistence_route ) {
		$coexistence_response = rest_do_request( new WP_REST_Request( 'GET', $coexistence_route ) );
		ohmylms_coexistence_check( 401 === $coexistence_response->get_status() || 403 === $coexistence_response->get_status(), 'Anonymous requests must remain denied by each order API.' );
	}
} finally {
	foreach ( $coexistence_posts as $coexistence_post_id ) {
		wp_delete_post( $coexistence_post_id, true );
	}
}

echo esc_html( $coexistence_mode ) . ': ' . (int) $GLOBALS['ohmylms_coexistence_checks'] . " checks passed.\n";
