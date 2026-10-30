<?php
/**
 * Frontend extension surfaces load the extension host, not the recovered SDK (includes/Extensions/SourceAssets.php).
 *
 * Checks, in the mode the site runs (source assets on 800.local), that slots, checkout fields and the signup
 * form enqueue `ohmylms-app-extensions` / `ohmylms-app-registration` with their data and fire
 * `ohmylms_enqueue_extension_scripts` with the host handle; that the recovered SDK loads only on
 * `admin.php?page=ohmylms` and carries `window.ohmylmsMath` there; that `MathLive::configure()` no longer
 * names the SDK; and that editor links go through LegacyRoutes. It then re-runs itself with
 * OHMYLMS_SOURCE_ASSETS defined false (shipped mode: published assets, no client slots or signup fields).
 * Nothing is written to the database.
 *
 * Usage (set OHMYLMS_EXTENSION_HOST_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/extension-host-assets-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_EXTENSION_HOST_TEST' ) ) {
	exit( "Set OHMYLMS_EXTENSION_HOST_TEST=1 to run the extension host asset tests against the local site.\n" );
}

$shipped = '1' === getenv( 'OHMYLMS_EXTENSION_HOST_SHIPPED' );
if ( $shipped ) {
	// The site's wp-config.php defines the constant itself; the first definition wins.
	define( 'OHMYLMS_SOURCE_ASSETS', false );
	set_error_handler(
		static function ( $number, $message ) {
			return false !== strpos( $message, 'OHMYLMS_SOURCE_ASSETS' );
		},
		E_WARNING
	);
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
if ( $shipped ) {
	restore_error_handler();
}

use OhMyLMS\Admin\LegacyRoutes;
use OhMyLMS\Assessment\MathLive;
use OhMyLMS\Assessment\PreviewPlayer;
use OhMyLMS\Extensions\Slots;
use OhMyLMS\Extensions\SourceAssets;
use OhMyLMS\Learning\NativeLessonEditor;
use OhMyLMS\Learning\NativeQuizEditor;

$checks = 0;

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

/** Start from WordPress' default scripts, as on a fresh request. */
function reset_scripts() {
	$GLOBALS['wp_scripts'] = new WP_Scripts();
}

/**
 * Source URL of a registered script, or an empty string.
 *
 * @param string $handle Script handle.
 * @return string
 */
function script_src( $handle ) {
	$script = wp_scripts()->query( $handle, 'registered' );
	return $script ? (string) $script->src : '';
}

/**
 * Inline data (`wp_localize_script`) of a script.
 *
 * @param string $handle Script handle.
 * @return string
 */
function script_data( $handle ) {
	return (string) wp_scripts()->get_data( $handle, 'data' );
}

$administrators = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
);
wp_set_current_user( $administrators ? $administrators[0]->ID : 0 );

$source    = defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS;
$directory = $source ? 'build/sdk' : 'assets/dist/admin';
$host      = SourceAssets::HOST_HANDLE;
$legacy    = SourceAssets::HANDLE;
$fired     = array();
$rendered  = array();
add_action(
	'ohmylms_enqueue_extension_scripts',
	static function ( $handle ) use ( &$fired ) {
		$fired[] = $handle;
	},
	1
);
add_action(
	'ohmylms_render_slot',
	static function ( $name ) use ( &$rendered ) {
		$rendered[] = $name;
	}
);

ok( $shipped === ! $source, 'the shipped run did not switch OHMYLMS_SOURCE_ASSETS off' );
ok( 'ohmylms-app-extensions' === $host, 'the host handle is not the admin app extension handle' );
ok( false !== has_action( 'wp_enqueue_scripts', array( SourceAssets::class, 'register_host' ) ), 'the host is not registered on the front end' );
ok( false === has_action( 'wp_enqueue_scripts', array( SourceAssets::class, 'register' ) ), 'the recovered SDK is still registered on every front-end page' );
ok( is_file( OHMYLMS_DIR . '/' . $directory . '/admin-app-extensions.js' ), $directory . '/admin-app-extensions.js is not built' );

// MathLive no longer configures the recovered SDK.
reset_scripts();
SourceAssets::register();
MathLive::configure();
ok( false === strpos( script_data( $legacy ), 'ohmylmsMath' ), 'MathLive::configure() still localizes the recovered SDK' );
ok( false !== strpos( script_data( 'ohmylms-mathlive-loader' ), 'ohmylmsMath' ), 'MathLive::configure() no longer configures its loader' );

// Front end: slots and checkout fields.
reset_scripts();
SourceAssets::register_host();
ok( false !== strpos( script_src( $host ), $directory . '/admin-app-extensions.js' ), 'the host is not registered from ' . $directory . ': ' . script_src( $host ) );
ob_start();
Slots::render( 'student.course.after', array( 'contentId' => 7 ) );
Slots::render( 'checkout.fields.after', array( 'contentId' => 7 ) );
$html = ob_get_clean();
ok( array( 'student.course.after', 'checkout.fields.after' ) === $rendered, 'ohmylms_render_slot did not fire for each slot' );
ok( ! wp_script_is( $legacy, 'enqueued' ) && ! wp_script_is( $legacy, 'registered' ), 'a frontend slot loaded the recovered SDK' );
if ( $source ) {
	ok( false !== strpos( $html, 'data-ohmylms-slot="student.course.after"' ), 'the course slot was not marked up: ' . $html );
	ok( false !== strpos( $html, 'data-ohmylms-slot="checkout.fields.after" data-ohmylms-kind="checkout-field"' ), 'the checkout field slot was not marked up: ' . $html );
	ok( wp_script_is( $host, 'enqueued' ), 'a frontend slot did not load the host' );
	ok( array( $host ) === $fired, 'ohmylms_enqueue_extension_scripts did not fire once with the host handle: ' . wp_json_encode( $fired ) );
	ok( false !== strpos( script_data( $host ), 'var ohmylmsMath' ), 'the host does not carry window.ohmylmsMath' );
	ok( false !== strpos( script_data( $host ), 'var ohmylmsExtensionManifest' ), 'the host does not carry the extension manifest' );
	ok( false === strpos( script_data( $host ), 'ohmylmsAssessment' ), 'the host carries recovered-admin data' );
	$example = wp_scripts()->query( 'ohmylms-example-module', 'registered' );
	ok( ! $example || in_array( $host, $example->deps, true ), 'an extension script does not depend on the handle it was given' );
} else {
	ok( '' === $html, 'shipped mode marked up client slots: ' . $html );
	ok( ! wp_script_is( $host, 'enqueued' ) && array() === $fired, 'shipped mode loaded the host for a slot' );
}

// Front end: the signup form.
$registration_hook = has_action( 'ohmylms_signup_form_start', array( SourceAssets::class, 'enqueue_registration' ) );
ob_start();
ohmylms_signup_form( array() );
$form = ob_get_clean();
if ( $source ) {
	ok( false !== $registration_hook, 'the signup form does not load the registration fields' );
	ok( false !== strpos( $form, 'data-ohmylms-registration-fields' ), 'the signup form does not mark its fields' );
	ok( wp_script_is( SourceAssets::REGISTRATION_HANDLE, 'enqueued' ), 'the signup form did not enqueue the registration fields' );
	ok( false !== strpos( script_src( SourceAssets::REGISTRATION_HANDLE ), $directory . '/admin-app-registration.js' ), 'the registration fields are not loaded from ' . $directory );
	ok( array( $host ) === $fired, 'the signup form fired ohmylms_enqueue_extension_scripts again' );
} else {
	ok( false === $registration_hook && false === strpos( $form, 'data-ohmylms-registration-fields' ), 'shipped mode replaced the signup fields' );
	ok( ! wp_script_is( SourceAssets::REGISTRATION_HANDLE, 'enqueued' ), 'shipped mode loaded the registration fields' );
}
ok( ! wp_script_is( $legacy, 'registered' ), 'the signup form loaded the recovered SDK' );

// wp-admin: the recovered SDK on its own page only.
reset_scripts();
$fired = array();
SourceAssets::admin( 'edit.php' );
ok( ! wp_script_is( $legacy, 'registered' ) && ! wp_script_is( $host, 'enqueued' ) && array() === $fired, 'another admin screen loaded extension scripts' );
SourceAssets::admin( 'toplevel_page_' . OHMYLMS_SLUG );
ok( wp_script_is( $legacy, 'enqueued' ), 'the recovered admin lost its SDK' );
ok( false !== strpos( script_src( $legacy ), $directory . '/extensions.js' ), 'the recovered SDK is not loaded from ' . $directory );
ok( false !== strpos( script_data( $legacy ), 'var ohmylmsMath' ), 'the recovered SDK lost window.ohmylmsMath' );
ok( array( $legacy ) === $fired, 'the recovered admin did not pass its SDK handle to extensions: ' . wp_json_encode( $fired ) );
ok( ! wp_script_is( $host, 'enqueued' ), 'the recovered admin loaded the host' );

// Editor links go through the route map.
ok( LegacyRoutes::url( '/quiz-edit/5' ) === PreviewPlayer::editor_url( 5 ), 'preview return link bypasses LegacyRoutes' );
ok( LegacyRoutes::url( '/quiz-edit/5' ) === NativeQuizEditor::form_url( 5 ), 'quiz form link bypasses LegacyRoutes' );
$_GET['ohmylms_return'] = '/question-bank';
ok( LegacyRoutes::url( '/content-hub/question-bank' ) === NativeLessonEditor::return_url(), 'the question bank return link is not the Content Hub tab: ' . NativeLessonEditor::return_url() );
$_GET['ohmylms_return'] = '/course-edit/12/curriculum';
ok( LegacyRoutes::url( '/course-edit/12/curriculum' ) === NativeLessonEditor::return_url(), 'the course return link bypasses LegacyRoutes' );
if ( LegacyRoutes::enabled() ) {
	ok( LegacyRoutes::app_url( '/courses/12' ) === NativeLessonEditor::return_url(), 'the course return link does not open the app: ' . NativeLessonEditor::return_url() );
	ok( LegacyRoutes::app_url( '/quizzes/5' ) === PreviewPlayer::editor_url( 5 ), 'the preview return link does not open the app' );
}
unset( $_GET['ohmylms_return'] );

echo ( $source ? 'source' : 'shipped' ) . " mode: {$checks} checks passed\n";

if ( $source && ! $shipped ) {
	$arguments = array( PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get( 'extension_dir' ) );
	foreach ( array( 'mysqli', 'mbstring', 'openssl', 'curl' ) as $extension ) {
		if ( extension_loaded( $extension ) ) {
			array_push( $arguments, '-d', 'extension=' . $extension );
		}
	}
	array_push( $arguments, '-d', 'mysqli.default_port=' . ini_get( 'mysqli.default_port' ), __FILE__ );
	$process = proc_open( $arguments, array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, array_merge( getenv(), array( 'OHMYLMS_EXTENSION_HOST_SHIPPED' => '1' ) ) );
	$output  = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
	$status  = proc_close( $process );
	echo $output;
	if ( 0 !== $status ) {
		exit( 1 );
	}
}
