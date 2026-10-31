<?php
/**
 * Plugin Name: OhMyLMS
 * Description: OhMyLMS is an all-in-one WordPress LMS: sell courses, quizzes, certificates, memberships and coaching, with built-in checkout and an extension API.
 * Version: 1.2.21
 * Author: OhMyLMS contributors; original work by WPFunnels Team
 * License: GPL-2.0-or-later
 * Text Domain: ohmylms
 * Requires at least: 6.8
 * Requires PHP: 7.4
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
if ( defined( 'OHMYLMS_FILE' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'OhMyLMS is paused because another copy of OhMyLMS is active.', 'ohmylms' ) . '</p></div>';
		}
	);
	return;
}
define( 'OHMYLMS_FILE', __FILE__ );
define( 'OHMYLMS_DIR', __DIR__ );
define( 'OHMYLMS_VERSION', '1.2.21' );
define( 'OHMYLMS_NATIVE_QPAY', true );
define( 'OHMYLMS_API_VERSION', 'v1' );
define( 'OHMYLMS_API_URL', 'ohmylms/v1' );
define( 'OHMYLMS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'OHMYLMS_PRO_VERSION', OHMYLMS_VERSION );
define( 'OHMYLMS_PRO_FILE', __FILE__ );
define( 'OHMYLMS_PRO_DIR', __DIR__ );
define( 'OHMYLMS_PRO_PATH', __DIR__ );
define( 'OHMYLMS_PRO_URL', plugins_url( '', __FILE__ ) );
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/compatibility.php';
require_once __DIR__ . '/includes/extensions.php';
require_once __DIR__ . '/includes/mcp.php';
if ( ! class_exists( 'ActionScheduler', false ) ) {
	require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}
require_once __DIR__ . '/includes/Packages.php';
require_once __DIR__ . '/includes/OhMyLMS.php';
require_once __DIR__ . '/includes/cl-functions.php';
/**
 * Get the main OhMyLMS instance.
 *
 * @return OhMyLMS
 */
function ohmylms() {
	return OhMyLMS::instance();
}
$GLOBALS['ohmylms'] = ohmylms();
register_activation_hook( __FILE__, array( 'OhMyLMS\\Install', 'install' ) );
register_deactivation_hook(
	__FILE__,
	function () {
		do_action( 'ohmylms_plugin_deactivated' );
	}
);
