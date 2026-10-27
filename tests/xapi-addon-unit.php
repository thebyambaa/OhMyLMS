<?php
/** Add-on lifecycle checks using the real emitter settings and scheduler, without a database or LRS. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'OHMYLMS_FILE', dirname( __DIR__ ) . '/ohmylms.php' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
require dirname( __DIR__ ) . '/vendor/autoload.php';

use OhMyLMS\XApi\Bootstrap;
use OhMyLMS\XApi\AdminPage;
use OhMyLMS\XApi\Integration;
use OhMyLMS\XApi\Outbox;
use OhMyLMS\XApi\Processor;
use OhMyLMS\XApi\Schema;
use OhMyLMS\XApi\Settings;
use OhMyLMS\XApi\SettingsController;

$options = array( Schema::OPTION => Schema::VERSION );
$hooks = array();
$cron = array();
$checks = 0;
$writes = 0;
function check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}
function __( $text ) { return $text; }
function plugins_url( $path, $file ) { return 'https://school.test/plugins/ohmylms/' . $path; }
function is_admin() { return false; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
function sanitize_text_field( $value ) { return trim( strip_tags( $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function add_action( $name, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['hooks'][ $name ][ $priority ][] = array( $callback, $args );
}
function add_filter( ...$args ) { add_action( ...$args ); }
function do_action( $name, ...$args ) {
	$callbacks = $GLOBALS['hooks'][ $name ] ?? array();
	ksort( $callbacks );
	foreach ( $callbacks as $group ) {
		foreach ( $group as list( $callback, $accepted ) ) {
			$callback( ...array_slice( $args, 0, $accepted ) );
		}
	}
}
function update_option( $name, $value, $autoload = null ) {
	global $options, $writes;
	if ( ++$writes > 200 ) { throw new RuntimeException( 'Option synchronization recursed' ); }
	$exists = array_key_exists( $name, $options );
	$previous = $options[ $name ] ?? false;
	if ( $exists && $previous === $value ) { return false; }
	$options[ $name ] = $value;
	if ( $exists ) {
		do_action( 'update_option_' . $name, $previous, $value, $name );
	} else {
		do_action( 'add_option_' . $name, $name, $value );
	}
	return true;
}
function delete_option( $name ) {
	unset( $GLOBALS['options'][ $name ] );
	do_action( 'delete_option_' . $name, $name );
}
function wp_next_scheduled( $hook ) { return $GLOBALS['cron'][ $hook ] ?? false; }
function wp_schedule_event( $timestamp, $interval, $hook ) { $GLOBALS['cron'][ $hook ] = $timestamp; }
function wp_clear_scheduled_hook( $hook ) { unset( $GLOBALS['cron'][ $hook ] ); }
function add_submenu_page( $parent, $title, $label, $capability, $slug, $callback ) {
	$GLOBALS['submenu_pages'][$slug] = $callback;
	$GLOBALS['visible_submenus'][$slug] = $label;
}
function remove_submenu_page( $parent, $slug ) { unset( $GLOBALS['visible_submenus'][$slug] ); }

Bootstrap::init();
do_action( 'init' );
check( get_option( 'ohmylms_integrations' )['xapi']['is_enable'] === 0, 'A new installation must start disabled' );
check( ! Settings::enabled() && ! wp_next_scheduled( Processor::HOOK ), 'New installations must not deliver' );
AdminPage::menu();
check( ! isset( $GLOBALS['visible_submenus'][AdminPage::SLUG] ), 'Disabled add-on must not appear in the submenu' );
check( is_callable( $GLOBALS['submenu_pages'][AdminPage::SLUG] ), 'Manage must still open the registered settings page while disabled' );
$manifest = Integration::manifest( array( 'existing' => array( 'label' => 'Existing' ) ) );
check( isset( $manifest['existing'] ) && $manifest['xapi']['hasSettings'], 'Card retains other add-ons and offers settings' );
check( $manifest['xapi']['categories'] === array( 'connections' ), 'Card is listed in Connections' );
check( is_file( dirname( __DIR__ ) . '/assets/images/xapi-icon.svg' ), 'Card icon exists' );

// Upgrade from the emitter that existed before the Add-ons card.
unset( $options['ohmylms_integrations'] );
$legacy = array(
	'enabled' => 1, 'endpoint' => 'https://lrs.example.test/xapi', 'key' => 'example-key',
	'secret' => 'private-secret', 'actor' => 'pseudonym', 'responses' => 0,
	'groups' => array( 'answers' => 0, 'lessons' => 1 ), 'retention_days' => 21,
);
$options[Settings::OPTION] = $legacy;
$options[Settings::SITE_ID_OPTION] = 'existing-site-identity';
$options[Settings::ACTOR_KEY_OPTION] = 'existing-actor-key';
Integration::migrate();
check( get_option( Settings::OPTION ) === $legacy, 'Migration must preserve every existing setting' );
check( get_option( 'ohmylms_integrations' )['xapi']['is_enable'] === 1, 'Migration retains an enabled installation' );
check( Settings::enabled() && wp_next_scheduled( Processor::HOOK ), 'Migrated emitter continues delivery' );
AdminPage::menu();
check( ! isset( $GLOBALS['visible_submenus'][AdminPage::SLUG] ), 'Enabled add-on uses the Settings tab rather than a standalone submenu' );
$manifest = Integration::manifest( array() );
check( strpos( json_encode( $manifest ), 'private-secret' ) === false && strpos( json_encode( $manifest ), 'example-key' ) === false, 'The card must never expose credentials' );

// Exercise real WordPress option hook ordering in both directions.
$integrations = get_option( 'ohmylms_integrations' );
$integrations['other'] = array( 'is_enable' => 1 );
$integrations['xapi']['is_enable'] = 0;
update_option( 'ohmylms_integrations', $integrations );
check( ! Settings::enabled() && ! wp_next_scheduled( Processor::HOOK ), 'Disabling the card must stop delivery immediately' );
AdminPage::menu();
check( ! isset( $GLOBALS['visible_submenus'][AdminPage::SLUG] ), 'Disabling the card removes its submenu' );
check( Processor::process() === 0, 'An explicitly requested send must also stop while disabled' );
check( Outbox::enqueue( 'lesson_completed', 1, array( 'lesson_id' => 2 ), 'event' ) === false, 'Disabling the card stops collection' );
check( get_option( Settings::OPTION ) === array_merge( $legacy, array( 'enabled' => 0 ) ), 'Disabling only changes the enabled flag' );
check( get_option( Settings::SITE_ID_OPTION ) === 'existing-site-identity' && get_option( Settings::ACTOR_KEY_OPTION ) === 'existing-actor-key', 'Toggling must preserve statement and learner identities' );

$integrations['xapi']['is_enable'] = '1';
update_option( 'ohmylms_integrations', $integrations );
check( Settings::enabled() && wp_next_scheduled( Processor::HOOK ), 'Re-enabling resumes the existing emitter' );
$settings = Settings::all();
$settings['enabled'] = 0;
update_option( Settings::OPTION, $settings, false );
check( get_option( 'ohmylms_integrations' )['xapi']['is_enable'] === 0 && ! wp_next_scheduled( Processor::HOOK ), 'Settings-screen changes update the card and delivery schedule' );
check( get_option( 'ohmylms_integrations' )['other']['is_enable'] === 1, 'Settings changes retain other add-ons' );

// Settings::save must still retain the secret when its password field is blank.
Settings::save( array_merge( $legacy, array( 'secret' => '' ) ) );
check( get_option( Settings::OPTION )['secret'] === 'private-secret', 'A blank password retains the existing secret' );
check( get_option( 'ohmylms_integrations' )['xapi']['is_enable'] === 1 && wp_next_scheduled( Processor::HOOK ), 'Saving the form enables the same switch' );
$scheduled = wp_next_scheduled( Processor::HOOK );
Processor::schedule();
check( wp_next_scheduled( Processor::HOOK ) === $scheduled, 'Repeated scheduling retains one delivery job' );

unset( $integrations['xapi'] );
update_option( 'ohmylms_integrations', $integrations );
check( ! Settings::enabled() && ! wp_next_scheduled( Processor::HOOK ), 'Removing the switch disables delivery' );
Integration::migrate();
check( get_option( 'ohmylms_integrations' )['xapi']['is_enable'] === 0, 'A removed switch cannot restore legacy enabled state' );

update_option( Settings::OPTION, $legacy );
delete_option( 'ohmylms_integrations' );
check( ! Settings::enabled() && ! array_key_exists( 'ohmylms_integrations', $options ), 'Deleting integrations disables delivery without recreating the option' );
update_option( Settings::OPTION, $legacy );
delete_option( Settings::OPTION );
check( ! array_key_exists( Settings::OPTION, $options ) && get_option( 'ohmylms_integrations' )['xapi']['is_enable'] === 0, 'Deleting settings disables the card without recreating credentials' );

update_option( Settings::OPTION, $legacy );
check( wp_next_scheduled( Processor::HOOK ) && wp_next_scheduled( Processor::PRUNE_HOOK ), 'Enabled emitter has delivery and retention jobs' );
do_action( 'ohmylms_plugin_deactivated' );
check( ! wp_next_scheduled( Processor::HOOK ) && ! wp_next_scheduled( Processor::PRUNE_HOOK ), 'Deactivation clears both jobs' );
check( get_option( Settings::OPTION ) === $legacy, 'Deactivation retains settings for reactivation' );
do_action( 'init' );
check( Settings::enabled() && wp_next_scheduled( Processor::HOOK ), 'Reactivation resumes an enabled emitter' );

// The real integrations endpoint must save xAPI without asking the client to reload.
class WP_REST_Controller {}
class WP_REST_Response {
	public $data;
	public function __construct( $data, $status ) { $this->data = $data; }
}
$controller = new \OhMyLMS\Rest\V1\IntegrationsController();
foreach ( array( 0, 1, 0 ) as $enabled ) {
	$payload = get_option( 'ohmylms_integrations', array() );
	$payload['xapi'] = array( 'is_enable' => $enabled );
	$request = new class( $payload ) {
		private $payload;
		public function __construct( $payload ) { $this->payload = $payload; }
		public function get_json_params() { return $this->payload; }
	};
	$response = $controller->update_items( $request );
	check( $response->data['success'] && ! $response->data['need_reload'], 'An xAPI toggle saves without reloading the Add-ons screen' );
	check( Settings::enabled() === (bool) $enabled, 'The endpoint applies the requested xAPI state' );
}

// The Settings tab uses administrator-only routes and never reads back the password.
define( 'ARRAY_A', 'ARRAY_A' );
$wpdb = new class {
	public $prefix = 'wp_';
	public function prepare( $query, ...$values ) { return $query; }
	public function get_results( $query, $format ) { return array(); }
};
$can_manage = false;
function current_user_can( $capability ) { return $GLOBALS['can_manage'] && $capability === 'manage_options'; }
function register_rest_route( $namespace, $route, $definition ) { $GLOBALS['routes'][$route] = $definition; }
class WP_Error {
	public $code;
	public function __construct( $code, $message, $data ) { $this->code = $code; }
}
SettingsController::register_routes();
check( ! SettingsController::can_manage(), 'Non-administrators cannot access xAPI settings' );
$can_manage = true;
check( SettingsController::can_manage(), 'Administrators can manage xAPI settings' );
check( count( $GLOBALS['routes'] ) === 2, 'Settings and explicit action routes are registered' );
foreach ( $GLOBALS['routes']['/xapi/settings'] as $route ) {
	check( $route['permission_callback'] === array( SettingsController::class, 'can_manage' ), 'Reads and writes require administrator access' );
}
check( $GLOBALS['routes']['/xapi/settings/(?P<action>test|send|retry)']['permission_callback'] === array( SettingsController::class, 'can_manage' ), 'Delivery actions require administrator access' );
$snapshot = SettingsController::read();
check( ! array_key_exists( 'secret', $snapshot['settings'] ) && $snapshot['has_secret'], 'Snapshot reports that a secret exists without returning it' );
check( strpos( json_encode( $snapshot ), 'private-secret' ) === false, 'The Settings API does not leak the saved password' );
$request = new class( array_merge( $legacy, array( 'secret' => '', 'enabled' => false ) ) ) {
	private $payload;
	public function __construct( $payload ) { $this->payload = $payload; }
	public function get_json_params() { return $this->payload; }
};
$saved = SettingsController::save( $request );
check( $saved['settings']['enabled'] === 0 && get_option( 'ohmylms_integrations' )['xapi']['is_enable'] === 0, 'Saving in the Settings tab synchronizes the Add-ons switch' );
check( get_option( Settings::OPTION )['secret'] === 'private-secret' && ! isset( $saved['settings']['secret'] ), 'Saving a blank password preserves it without echoing it back' );
$before_invalid = get_option( Settings::OPTION );
$invalid = new class {
	public function get_json_params() { return array( 'endpoint' => 'ftp://invalid' ); }
};
check( SettingsController::save( $invalid ) instanceof WP_Error && get_option( Settings::OPTION ) === $before_invalid, 'An invalid endpoint is rejected without changing saved settings' );
$bad_body = new class {
	public function get_json_params() { return null; }
};
check( SettingsController::save( $bad_body ) instanceof WP_Error, 'Non-JSON settings are rejected' );
echo "xAPI add-on: $checks checks passed\n";
