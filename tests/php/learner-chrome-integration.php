<?php
/**
 * Learner page chrome (batch L2): the toast region, account menu, profile drawer, scroll to top
 * and form helpers render `ohmylms/ui` and `ohmylms/forms` directives, theme overrides with the
 * pre-L2 markup are decorated the same way, and the SDK gets its server configuration.
 *
 * Renders templates only; nothing is written to the database.
 *
 * Usage (set OHMYLMS_CHROME_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/learner-chrome-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CHROME_TEST' ) ) {
	exit( "Set OHMYLMS_CHROME_TEST=1 to run the learner chrome tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Extensions\Chrome;
use OhMyLMS\Extensions\Interactivity;

$checks   = 0;
$warnings = array();
set_error_handler(
	static function ( $severity, $message, $file, $line ) use ( &$warnings ) {
		if ( false !== strpos( wp_normalize_path( $file ), wp_normalize_path( OHMYLMS_DIR ) ) ) {
			$warnings[] = "$message in $file:$line";
		}
		return false;
	}
);

/**
 * Record one check.
 *
 * @param bool   $condition Passes.
 * @param string $message   Failure message.
 * @throws RuntimeException When the check fails.
 */
function ok( $condition, $message ) {
	global $checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}

/**
 * Render a template to a string.
 *
 * @param string $name Template name.
 * @param array  $args Arguments.
 * @return string
 */
function render( $name, $args = array() ) {
	ob_start();
	ohmylms_get_template( $name, $args );
	return (string) ob_get_clean();
}

/**
 * The opening tag of the first element matching a class (or id), for attribute checks.
 *
 * @param string $html     HTML.
 * @param string $selector Class name, or `#id`.
 * @return string Opening tag, '' when missing.
 */
function opening_tag( $html, $selector ) {
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag() ) {
		$match = 0 === strpos( $selector, '#' ) ? substr( $selector, 1 ) === $tags->get_attribute( 'id' ) : $tags->has_class( $selector );
		if ( $match ) {
			$attributes = array();
			foreach ( (array) $tags->get_attribute_names_with_prefix( '' ) as $name ) {
				$value        = $tags->get_attribute( $name );
				$attributes[] = true === $value ? $name : $name . '="' . $value . '"';
			}
			return '<' . strtolower( $tags->get_tag() ) . ' ' . implode( ' ', $attributes ) . '>';
		}
	}
	return '';
}

/**
 * Assert that a tag carries every attribute.
 *
 * @param string $tag        Opening tag from opening_tag().
 * @param array  $attributes Name => value (true for boolean).
 * @param string $label      What is checked.
 */
function has_attributes( $tag, array $attributes, $label ) {
	foreach ( $attributes as $name => $value ) {
		$needle = true === $value ? ' ' . $name : ' ' . $name . '="' . $value . '"';
		$found  = true === $value ? preg_match( '/ ' . preg_quote( $name, '/' ) . '[ >]/', $tag ) : false !== strpos( $tag, $needle );
		ok( (bool) $found, "$label: missing $needle in $tag" );
	}
}

$exit = 0;
try {
	wp_set_current_user( 0 );

	// The SDK reads its configuration from WordPress's script module data.
	$sdk = apply_filters( 'script_module_data_ohmylms/interactivity', array() );
	ok( 0 === strpos( $sdk['restRoot'], home_url() ) && preg_match( '/^[a-f0-9]{10}$/', $sdk['restNonce'] ) && false !== strpos( $sdk['ajaxUrl'], 'admin-ajax.php' ), 'SDK data carries the REST root, nonce and admin-ajax URL: ' . wp_json_encode( $sdk ) );
	ok( '' !== $sdk['messages']['dismiss'] && '' !== $sdk['messages']['network'], 'SDK data carries translated fallback messages' );

	// Modules: ohmylms/forms is registered and depends on the runtime and the SDK.
	Interactivity::register();
	wp_enqueue_script_module( 'ohmylms/forms' );
	ob_start();
	wp_script_modules()->print_enqueued_script_modules();
	wp_script_modules()->print_import_map();
	$modules = (string) ob_get_clean();
	ok( false !== strpos( $modules, 'assets/interactivity/forms.js' ), 'ohmylms/forms is registered and enqueued' );
	ok( false !== strpos( $modules, '"ohmylms\/interactivity"' ) || false !== strpos( $modules, '"ohmylms/interactivity"' ), 'ohmylms/forms imports the SDK' );

	// Toast region: one per page.
	$toast = render( 'global/toast.php' );
	has_attributes(
		opening_tag( $toast, '#ohmylms-toast' ),
		array(
			'data-wp-interactive'         => 'ohmylms/ui',
			'data-wp-init'                => 'callbacks.toastRegion',
			'data-wp-class--active'       => 'state.toast.visible',
			'data-wp-class--toast-danger' => 'state.toastDanger',
			'data-wp-on--mouseenter'      => 'actions.pauseToast',
			'data-wp-on--focusout'        => 'actions.resumeToast',
			'role'                        => 'status',
			'aria-live'                   => 'polite',
		),
		'toast region'
	);
	has_attributes(
		opening_tag( $toast, 'ohmylms-toast-content' ),
		array(
			'data-wp-bind--hidden' => 'state.toastHidden',
			'hidden'               => true,
		),
		'toast content'
	);
	has_attributes( opening_tag( $toast, 'ohmylms-message' ), array( 'data-wp-text' => 'state.toast.message' ), 'toast message' );
	$close = opening_tag( $toast, '#ohmylms-close-toast' );
	ok( 0 === strpos( $close, '<button ' ), 'toast close is a button' );
	has_attributes(
		$close,
		array(
			'data-wp-on--click' => 'actions.dismissToast',
			'type'              => 'button',
		),
		'toast close'
	);
	ok( '' === trim( render( 'global/toast.php' ) ), 'a second toast region is not rendered' );
	$state = wp_interactivity_state( 'ohmylms/ui' );
	ok( isset( $state['toast']['visible'], $state['messages']['verifyExpired'] ) && false === $state['toast']['shown'], 'ohmylms/ui state starts with a hidden toast and the verification messages' );

	// Login and sign-up forms.
	ob_start();
	ohmylms_login_form(
		array(
			'message'     => '',
			'redirect_to' => home_url( '/' ),
			'hidden'      => false,
		)
	);
	$login = (string) ob_get_clean();
	has_attributes(
		opening_tag( $login, 'ohmylms-form-login' ),
		array(
			'data-wp-interactive'  => 'ohmylms/forms',
			'data-wp-init--fold'   => 'callbacks.foldFilled',
			'data-wp-on--focusin'  => 'actions.fieldFocus',
			'data-wp-on--focusout' => 'actions.fieldBlur',
		),
		'login form'
	);
	has_attributes( opening_tag( $login, 'ohmylms-password-show' ), array( 'data-wp-context' => '{"revealed":false}' ), 'login password wrapper' );
	has_attributes( opening_tag( $login, '#login-password' ), array( 'data-wp-bind--type' => 'state.passwordType' ), 'login password field' );
	has_attributes(
		opening_tag( $login, 'show-password-icon' ),
		array(
			'role'                       => 'button',
			'aria-pressed'               => 'false',
			'data-wp-bind--aria-pressed' => 'context.revealed',
			'data-wp-on--click'          => 'actions.togglePassword',
			'data-wp-on--keydown'        => 'actions.passwordKey',
		),
		'password toggle'
	);
	has_attributes( opening_tag( $login, 'eye-on' ), array( 'data-wp-style--display' => 'state.eyeOnDisplay' ), 'eye icon' );
	has_attributes(
		opening_tag( $login, 'ohmylms-form-login-rememberme' ),
		array(
			'data-wp-on--keydown' => 'actions.checkboxKey',
			'tabindex'            => '0',
		),
		'remember me'
	);
	ok( 1 === substr_count( $login, 'data-wp-interactive=' ), 'login fields share the form island' );

	ob_start();
	ohmylms_signup_form(
		array(
			'message'     => '',
			'redirect_to' => home_url( '/' ),
			'hidden'      => true,
		)
	);
	$signup = (string) ob_get_clean();
	has_attributes(
		opening_tag( $signup, 'ohmylms-form-signup' ),
		array(
			'data-wp-context'       => '{"consent":false}',
			'data-wp-init--consent' => 'callbacks.syncConsent',
		),
		'sign-up form'
	);
	has_attributes( opening_tag( $signup, '#tnc-accept' ), array( 'data-wp-on--change' => 'actions.consentChange' ), 'terms checkbox' );
	has_attributes(
		opening_tag( $signup, 'ohmylms-form-signup-submit' ),
		array(
			'data-wp-bind--disabled' => 'state.consentMissing',
			'disabled'               => true,
		),
		'sign-up submit'
	);
	has_attributes(
		opening_tag( $signup, '#signup-phone' ),
		array(
			'data-wp-on--input' => 'actions.filterPhone',
			'inputmode'         => 'tel',
		),
		'phone field'
	);
	has_attributes(
		opening_tag( $signup, 'ohmylms-form-login-rememberme' ),
		array(
			'for'                 => 'tnc-accept',
			'tabindex'            => '0',
			'data-wp-on--keydown' => 'actions.checkboxKey',
		),
		'terms label'
	);

	// Checkout terms: the label is outside a forms island, so it becomes one.
	$terms = render( 'checkout/terms.php' );
	has_attributes(
		opening_tag( $terms, 'ohmylms-tnc-label' ),
		array(
			'data-wp-interactive' => 'ohmylms/forms',
			'data-wp-on--keydown' => 'actions.checkboxKey',
		),
		'checkout terms label'
	);

	// Scroll to top.
	$scroll = render( 'global/scrollto-top.php' );
	has_attributes(
		opening_tag( $scroll, 'ohmylms-scroll-to-top' ),
		array(
			'data-wp-interactive'             => 'ohmylms/ui',
			'data-wp-class--visible'          => 'context.scrolled',
			'data-wp-on-async-window--scroll' => 'callbacks.trackScroll',
		),
		'scroll to top'
	);
	ok( false !== strpos( $scroll, 'data-wp-on--click="actions.scrollToTop"' ), 'scroll button is bound' );

	// Header account menu and profile drawer need a learner.
	$users = get_users(
		array(
			'number' => 1,
			'fields' => 'ID',
		)
	);
	ok( ! empty( $users ), 'the site has a user to render the header for' );
	wp_set_current_user( (int) $users[0] );
	$header = render( 'global/main-header.php', array( 'student' => new \OhMyLMS\Data\Student( (int) $users[0] ) ) );
	has_attributes(
		opening_tag( $header, 'ohmylms-user' ),
		array(
			'data-wp-interactive'          => 'ohmylms/ui',
			'data-wp-context'              => '{"open":false}',
			'data-wp-class--show-dropdown' => 'context.open',
			'data-wp-on-document--click'   => 'actions.dropdownOutside',
			'data-wp-on-document--keydown' => 'actions.dropdownEscape',
		),
		'account menu'
	);
	has_attributes(
		opening_tag( $header, 'ohmylms-user-avatar' ),
		array(
			'role'                        => 'button',
			'aria-controls'               => Chrome::USER_MENU_ID,
			'data-wp-bind--aria-expanded' => 'context.open',
			'data-wp-on--click'           => 'actions.dropdownToggle',
			'data-wp-on--keydown'         => 'actions.dropdownKeydown',
		),
		'account menu trigger'
	);
	has_attributes( opening_tag( $header, 'ohmylms-user-dropdown' ), array( 'id' => Chrome::USER_MENU_ID ), 'account menu list' );

	$layout = render( 'profile/profile-layout.php', array( 'user' => wp_get_current_user() ) );
	has_attributes(
		opening_tag( $layout, 'ohmylms-student-profile' ),
		array(
			'data-wp-interactive'         => 'ohmylms/ui',
			'data-wp-class--open-sidebar' => 'context.drawerOpen',
			'data-wp-on-document--click'  => 'actions.drawerOutside',
		),
		'profile drawer'
	);
	has_attributes(
		opening_tag( $layout, 'ohmylms-hamburger' ),
		array(
			'role'              => 'button',
			'aria-controls'     => Chrome::PROFILE_PANEL_ID,
			'data-wp-on--click' => 'actions.drawerToggle',
		),
		'drawer toggle'
	);
	ok( false !== strpos( $layout, 'id="' . Chrome::PROFILE_PANEL_ID . '"' ), 'the drawer panel has the id the toggle controls' );

	// Theme overrides with the pre-L2 markup get the same behaviour.
	$old_toast = '<div class="ohmylms-toast" id="ohmylms-toast"><div class="ohmylms-toast-content"><span class="ohmylms-message">Success!</span><span class="ohmylms-close" id="ohmylms-close-toast">x</span></div></div><div class="ohmylms-toast-content"><span class="ohmylms-message">other</span></div>';
	$decorated = Chrome::ui( $old_toast );
	has_attributes(
		opening_tag( $decorated, '#ohmylms-toast' ),
		array(
			'data-wp-interactive' => 'ohmylms/ui',
			'role'                => 'status',
		),
		'override toast'
	);
	has_attributes(
		opening_tag( $decorated, '#ohmylms-close-toast' ),
		array(
			'role'                => 'button',
			'tabindex'            => '0',
			'data-wp-on--keydown' => 'actions.dismissToastKey',
		),
		'override toast close'
	);
	ok( 1 === substr_count( $decorated, 'data-wp-text=' ) && 1 === substr_count( $decorated, 'data-wp-bind--hidden=' ), 'only children of the toast region are decorated' );

	$old_header = '<header class="ohmylms-header"><div class="ohmylms-user"><a href="#" class="ohmylms-user-avatar">AB</a><ul class="ohmylms-user-dropdown"><li><a href="/x">x</a></li></ul></div></header>';
	$decorated  = Chrome::ui( $old_header );
	has_attributes(
		opening_tag( $decorated, 'ohmylms-user-avatar' ),
		array(
			'data-wp-on--click' => 'actions.dropdownToggle',
			'aria-label'        => 'Account menu',
		),
		'override account trigger'
	);
	has_attributes( opening_tag( $decorated, 'ohmylms-user-dropdown' ), array( 'id' => Chrome::USER_MENU_ID ), 'override account list' );

	$old_profile = '<div class="ohmylms-student-profile"><span class="ohmylms-hamburger" aria-label="Menu">=</span><div class="ohmylms-student-profile-wrapper"><aside class="ohmylms-student-profile-sidebar"></aside></div></div>';
	$decorated   = Chrome::ui( $old_profile );
	has_attributes( opening_tag( $decorated, 'ohmylms-student-profile' ), array( 'data-wp-on-document--keydown' => 'actions.drawerEscape' ), 'override drawer' );
	has_attributes(
		opening_tag( $decorated, 'ohmylms-hamburger' ),
		array(
			'tabindex'            => '0',
			'data-wp-on--keydown' => 'actions.drawerKey',
		),
		'override drawer toggle'
	);
	ok( false === strpos( Chrome::ui( '<div class="ohmylms-student-profile oml-hub-legacy"><p>x</p></div>' ), 'data-wp-' ), 'a profile wrapper without a drawer toggle stays static' );

	$old_settings = '<form class="profile-settings"><span class="ohmylms-password-show"><input class="ohmylms-input-text password" type="password" name="password_current"><label class="show-password-icon" tabindex="0"><input type="checkbox" class="show-password-checkbox"><span class="eye-on"></span><span class="eye-off"></span></label></span><input type="text" name="whatsapp"></form>';
	$decorated    = Chrome::forms( $old_settings );
	has_attributes(
		opening_tag( $decorated, 'ohmylms-password-show' ),
		array(
			'data-wp-interactive' => 'ohmylms/forms',
			'data-wp-context'     => '{"revealed":false}',
		),
		'profile password wrapper island'
	);
	has_attributes(
		opening_tag( $decorated, 'show-password-checkbox' ),
		array(
			'data-wp-bind--checked' => 'context.revealed',
			'tabindex'              => '-1',
		),
		'profile password checkbox'
	);
	has_attributes( opening_tag( $decorated, 'eye-off' ), array( 'data-wp-style--display' => 'state.eyeOffDisplay' ), 'profile eye icon' );
	$whatsapp = opening_tag( str_replace( 'name="whatsapp"', 'name="whatsapp" class="wa"', $decorated ), 'wa' );
	has_attributes(
		$whatsapp,
		array(
			'data-wp-interactive' => 'ohmylms/forms',
			'data-wp-on--input'   => 'actions.filterPhone',
		),
		'whatsapp field island'
	);

	$plain = '<p class="ohmylms-message">Nothing to decorate</p>';
	ok( Chrome::ui( $plain ) === $plain && Chrome::forms( $plain ) === $plain, 'unrelated markup is returned unchanged' );
	ok( false === strpos( Chrome::template( '<input name="phone">', 'emails/receipt.php' ), 'data-wp-' ), 'email templates are not decorated' );

	// The footer prints a region for pages that need one and did not render it.
	$reset = static function () {
		foreach ( array( 'toast_rendered', 'needs_region' ) as $property ) {
			( new ReflectionProperty( Chrome::class, $property ) )->setValue( null, false );
		}
	};
	$reset();
	ob_start();
	Chrome::footer();
	ok( '' === trim( (string) ob_get_clean() ), 'pages without learner chrome get no toast region' );
	wp_register_script( 'ohmylms-frontend', plugins_url( 'assets/src/frontend/js/ohmylms.js', OHMYLMS_FILE ), array(), '1', true );
	wp_enqueue_script( 'ohmylms-frontend' );
	ob_start();
	Chrome::footer();
	$footer = (string) ob_get_clean();
	ok( 1 === substr_count( $footer, 'id="ohmylms-toast"' ) && false !== strpos( $footer, 'callbacks.toastRegion' ), 'a page with the legacy learner script gets a toast region in the footer' );
	ob_start();
	Chrome::footer();
	ok( '' === trim( (string) ob_get_clean() ), 'the footer region is printed once' );

	ok( array() === $warnings, "no PHP warnings from the plugin:\n" . implode( "\n", $warnings ) );
	echo "OK: $checks learner chrome checks passed.\n";
} catch ( Throwable $error ) {
	echo 'FAIL: ' . $error->getMessage() . "\n";
	$exit = 1;
}
exit( $exit );
