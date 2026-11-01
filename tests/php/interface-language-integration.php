<?php
/**
 * Interface languages: `Accept-Language` matching (shared fixture with tests/js/language.test.mjs),
 * the locale resolution order (user meta > cookie > browser > English), where it applies, the
 * `ohmylms/v1/learner/locale` route, the `?ohmylms_locale=` switch without JavaScript, the `Vary`
 * header, the switcher markup and setting, and the script translation catalog.
 *
 * Creates one temporary learner and deletes it at the end; restores the switcher option.
 *
 * Usage (set OHMYLMS_INTERFACE_LANGUAGE_TEST=1):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/interface-language-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.Security.NonceVerification, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_INTERFACE_LANGUAGE_TEST' ) ) {
	exit( "Set OHMYLMS_INTERFACE_LANGUAGE_TEST=1 to run the interface language tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
unset( $_SERVER['HTTP_ACCEPT_LANGUAGE'], $_SERVER['HTTP_REFERER'] );
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$home = untrailingslashit( home_url() );

use OhMyLMS\I18n\AcceptLanguage;
use OhMyLMS\I18n\InterfaceLanguage;
use OhMyLMS\I18n\Switcher;
use OhMyLMS\Settings\SettingsRegistry;

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
 * Set the request's cookie, `Accept-Language`, referrer and path.
 *
 * @param array $request `cookie`, `accept`, `referer`, `uri`.
 */
function request_as( array $request ) {
	unset( $_COOKIE[ InterfaceLanguage::COOKIE ], $_SERVER['HTTP_ACCEPT_LANGUAGE'], $_SERVER['HTTP_REFERER'] );
	if ( isset( $request['cookie'] ) ) {
		$_COOKIE[ InterfaceLanguage::COOKIE ] = $request['cookie'];
	}
	if ( isset( $request['accept'] ) ) {
		$_SERVER['HTTP_ACCEPT_LANGUAGE'] = $request['accept'];
	}
	if ( isset( $request['referer'] ) ) {
		$_SERVER['HTTP_REFERER'] = $request['referer'];
	}
	$_SERVER['REQUEST_URI'] = isset( $request['uri'] ) ? $request['uri'] : '/';
}

/**
 * The `ohmylms_locale` cookie of this request.
 *
 * @return string
 */
function locale_cookie() {
	return isset( $_COOKIE[ InterfaceLanguage::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ InterfaceLanguage::COOKIE ] ) ) : '';
}

/**
 * Dispatch a REST request.
 *
 * @param string $method  Method.
 * @param array  $params  Body parameters.
 * @param array  $headers Headers.
 * @return WP_REST_Response
 */
function locale_request( $method, array $params = array(), array $headers = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/learner/locale' );
	foreach ( $headers as $name => $value ) {
		$request->set_header( $name, $value );
	}
	if ( $params ) {
		$request->set_body_params( $params );
	}
	return rest_do_request( $request );
}

$learner_id    = 0;
$saved_option  = get_option( Switcher::OPTION, null );
$saved_cookies = $_COOKIE;
try {
	// Accept-Language: the same cases as the JavaScript mirror.
	$fixture = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/accept-language.json' ), true );
	ok( is_array( $fixture ) && $fixture['match'], 'the shared Accept-Language fixture loads' );
	foreach ( $fixture['parse'] as $case ) {
		$expected = array_map(
			static function ( $range ) {
				return array(
					'tag' => $range['tag'],
					'q'   => (float) $range['q'],
				);
			},
			$case['ranges']
		);
		ok( AcceptLanguage::parse( $case['header'] ) === $expected, 'parse: ' . $case['header'] . ' => ' . wp_json_encode( AcceptLanguage::parse( $case['header'] ) ) );
	}
	foreach ( $fixture['match'] as $case ) {
		ok( AcceptLanguage::negotiate( $case['header'], $fixture['locales'], $fixture['fallback'] ) === $case['locale'], 'match: ' . $case['header'] );
	}
	ok( 32 === count( AcceptLanguage::parse( implode( ',', array_fill( 0, 50, 'mn' ) ) ) ), 'reads at most 32 ranges' );

	// Interface locales: the plugin's Mongolian code is `mn`, the filter can add more.
	ok( array( 'en_US', 'mn' ) === array_keys( InterfaceLanguage::locales() ), 'English and Mongolian are offered' );
	ok( is_readable( OHMYLMS_DIR . '/languages/ohmylms-mn.mo' ), 'the plugin ships ohmylms-mn.mo' );
	$add_kk = static function ( $locales ) {
		$locales['kk']      = 'Қазақ';
		$locales['bad one'] = 'x';
		$locales['de_DE']   = '';
		return $locales;
	};
	add_filter( 'ohmylms_interface_locales', $add_kk );
	ok( array( 'en_US', 'mn', 'kk' ) === array_keys( InterfaceLanguage::locales() ), 'ohmylms_interface_locales adds locales and drops invalid entries' );
	remove_filter( 'ohmylms_interface_locales', $add_kk );

	// Resolution order: user meta > cookie > Accept-Language > English.
	$learner_id = wp_insert_user(
		array(
			'user_login' => 'oml_locale_' . wp_generate_password( 6, false ),
			'user_pass'  => wp_generate_password( 20 ),
			'user_email' => 'oml-locale-' . wp_generate_password( 6, false ) . '@example.test',
			'role'       => 'subscriber',
		)
	);
	ok( is_int( $learner_id ) && $learner_id > 0, 'temporary learner created' );
	update_user_meta( $learner_id, 'locale', 'mn' );
	ok( array( 'mn', 'user' ) === array_values( InterfaceLanguage::resolve( $learner_id, 'en_US', 'en' ) ), 'saved user meta wins over the cookie and the browser' );
	update_user_meta( $learner_id, 'locale', 'de_DE' );
	ok( array( 'mn', 'cookie' ) === array_values( InterfaceLanguage::resolve( $learner_id, 'mn', 'en' ) ), 'a profile language that is not an interface locale falls through to the cookie' );
	delete_user_meta( $learner_id, 'locale' );
	ok( array( 'mn', 'cookie' ) === array_values( InterfaceLanguage::resolve( $learner_id, 'mn', 'en' ) ), 'without saved meta the cookie wins over the browser' );
	ok( array( 'mn', 'browser' ) === array_values( InterfaceLanguage::resolve( $learner_id, '', 'mn-MN,en;q=0.5' ) ), 'a signed-in user without a choice follows the browser' );
	ok( array( 'mn', 'browser' ) === array_values( InterfaceLanguage::resolve( 0, 'xx', 'mn-MN' ) ), 'an unknown cookie is ignored' );
	ok( array( 'en_US', 'default' ) === array_values( InterfaceLanguage::resolve( 0, '', 'de-DE' ) ), 'otherwise English' );

	// determine_locale on front-end requests.
	wp_set_current_user( 0 );
	request_as( array( 'accept' => 'mn-MN,mn;q=0.9,en;q=0.5' ) );
	ok( 'mn' === determine_locale(), 'a Mongolian browser gets mn' );
	ok( false !== strpos( get_language_attributes(), 'lang="mn"' ), '<html lang> follows the locale: ' . get_language_attributes() );
	request_as( array( 'accept' => 'en-US' ) );
	ok( 'en_US' === determine_locale(), 'an English browser gets en_US' );
	ok( false !== strpos( get_language_attributes(), 'lang="en-US"' ), '<html lang> is en-US for English' );
	request_as(
		array(
			'accept' => 'en-US',
			'cookie' => 'mn',
		)
	);
	ok( 'mn' === determine_locale(), 'the cookie wins over the browser' );
	unload_textdomain( 'ohmylms' );
	load_plugin_textdomain( 'ohmylms', false, dirname( plugin_basename( OHMYLMS_FILE ) ) . '/languages/' );
	ok( 'Ерөнхий' === __( 'General', 'ohmylms' ), 'plugin strings load in Mongolian: ' . __( 'General', 'ohmylms' ) );
	request_as( array( 'accept' => 'en' ) );
	unload_textdomain( 'ohmylms' );
	load_plugin_textdomain( 'ohmylms', false, dirname( plugin_basename( OHMYLMS_FILE ) ) . '/languages/' );
	ok( 'General' === __( 'General', 'ohmylms' ), 'and in English again' );

	// Where it does not apply.
	request_as( array( 'cookie' => 'mn' ) );
	switch_to_locale( 'en_US' );
	ok( 'en_US' === determine_locale(), 'switch_to_locale() (emails) keeps its locale' );
	restore_previous_locale();
	request_as(
		array(
			'cookie'  => 'mn',
			'uri'     => '/wp-json/ohmylms/v1/courses',
			'referer' => admin_url( 'admin.php?page=ohmylms' ),
		)
	);
	ok( InterfaceLanguage::is_rest_request(), 'a /wp-json/ path is a REST request before parsing' );
	ok( 'mn' !== determine_locale(), 'REST calls from wp-admin keep WordPress\' locale' );
	request_as(
		array(
			'cookie'  => 'mn',
			'uri'     => '/wp-json/ohmylms/v1/learner/locale',
			'referer' => home_url( '/courses/' ),
		)
	);
	ok( 'mn' === determine_locale(), 'REST calls from learner pages use the learner locale' );
	// is_admin() asks the current screen.
	$GLOBALS['current_screen'] = new class() {
		/**
		 * Whether the screen is in wp-admin.
		 *
		 * @return bool
		 */
		public function in_admin() {
			return true;
		}
	};
	request_as( array( 'cookie' => 'mn' ) );
	ok( is_admin() && 'mn' !== determine_locale(), 'wp-admin keeps the profile language' );
	unset( $GLOBALS['current_screen'] );
	request_as( array() );

	// REST: POST ohmylms/v1/learner/locale.
	$response = locale_request( 'POST', array( 'locale' => 'mn' ) );
	ok( 200 === $response->get_status() && 'cookie' === $response->get_data()['source'] && 'mn' === $response->get_data()['locale'], 'a visitor saves the choice in the cookie' );
	ok( 'mn' === locale_cookie(), 'the cookie is set for the request' );
	ok( 'no-store' === $response->get_headers()['Cache-Control'], 'the answer is not cached' );
	$response = locale_request( 'POST', array( 'locale' => 'de_DE' ) );
	ok( 400 === $response->get_status() && 'rest_invalid_param' === $response->get_data()['code'], 'unknown locales are rejected' );
	$response = locale_request( 'POST' );
	ok( 400 === $response->get_status(), 'the locale is required' );
	$response = locale_request( 'GET' );
	ok( 200 === $response->get_status() && 'mn' === $response->get_data()['locale'] && 'cookie' === $response->get_data()['source'] && 2 === count( $response->get_data()['locales'] ), 'GET reports the locale, its source and the choices' );

	wp_set_current_user( $learner_id );
	request_as( array() );
	$response = locale_request( 'POST', array( 'locale' => 'mn' ) );
	ok( 403 === $response->get_status() && 'ohmylms_locale_invalid_nonce' === $response->get_data()['code'], 'a signed-in user needs the REST nonce' );
	ok( '' === get_user_meta( $learner_id, 'locale', true ), 'without it the profile is unchanged' );
	$response = locale_request( 'POST', array( 'locale' => 'mn' ), array( 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) ) );
	ok( 200 === $response->get_status() && 'user' === $response->get_data()['source'], 'with the nonce the choice is saved to the profile' );
	ok( 'mn' === get_user_meta( $learner_id, 'locale', true ), 'the locale user meta (Users › Profile › Language) is mn' );
	ok( 'mn' === get_user_locale( $learner_id ), 'WordPress reads the same field' );
	request_as( array( 'cookie' => 'en_US' ) );
	ok( 'mn' === determine_locale(), 'the saved profile language wins over a cookie' );

	// No JavaScript: ?ohmylms_locale= then a redirect to the clean URL.
	$url = $home . '/courses/?paged=2&ohmylms_locale=en_US&ohmylms_locale_nonce=bad';
	ok(
		$home . '/courses/?paged=2' === InterfaceLanguage::switch_from_query(
			array(
				'ohmylms_locale'       => 'en_US',
				'ohmylms_locale_nonce' => 'bad',
			),
			$url
		),
		'the redirect drops only the switcher parameters'
	);
	ok( 'mn' === get_user_meta( $learner_id, 'locale', true ), 'a signed-in user with a bad nonce keeps the profile language' );
	InterfaceLanguage::switch_from_query(
		array(
			'ohmylms_locale'       => 'en_US',
			'ohmylms_locale_nonce' => wp_create_nonce( InterfaceLanguage::NONCE_ACTION ),
		),
		$url
	);
	ok( 'en_US' === get_user_meta( $learner_id, 'locale', true ), 'with the form nonce the profile language changes' );
	wp_set_current_user( 0 );
	request_as( array() );
	InterfaceLanguage::switch_from_query( array( 'ohmylms_locale' => 'mn' ), $home . '/?ohmylms_locale=mn' );
	ok( 'mn' === locale_cookie(), 'a visitor switches with the link alone (cookie)' );
	InterfaceLanguage::switch_from_query( array( 'ohmylms_locale' => 'fr_FR' ), $home . '/?ohmylms_locale=fr_FR' );
	ok( 'mn' === locale_cookie(), 'an unknown locale changes nothing' );
	ok( has_action( 'template_redirect', array( InterfaceLanguage::class, 'redirect_query' ) ), 'the switch runs on template_redirect' );

	// Vary.
	ok( 'Origin, Accept-Language, Cookie' === InterfaceLanguage::vary( array( 'Vary' => 'Origin' ) )['Vary'], 'Vary keeps existing tokens and adds Accept-Language and Cookie' );
	ok( 'Accept-Language, Cookie' === InterfaceLanguage::vary( array( 'Vary' => 'cookie, Accept-Language' ) )['Vary'] || 'cookie, Accept-Language' === InterfaceLanguage::vary( array( 'Vary' => 'cookie, Accept-Language' ) )['Vary'], 'Vary does not repeat tokens' );
	$headers = apply_filters( 'wp_headers', array(), $GLOBALS['wp'] );
	ok( isset( $headers['Vary'] ) && false !== strpos( $headers['Vary'], 'Accept-Language' ) && false !== strpos( $headers['Vary'], 'Cookie' ), 'front-end responses send Vary: Accept-Language, Cookie' );

	// Switcher markup.
	delete_option( Switcher::OPTION );
	ok( true === SettingsRegistry::group( 'general' )['fields']['language_switcher']['default'], 'the setting defaults to on' );
	request_as(
		array(
			'cookie' => 'mn',
			'uri'    => '/courses/?course_search=math&course_level[]=beginner&ohmylms_locale=mn',
		)
	);
	$html = Switcher::render( array( 'variant' => 'compact' ) );
	$tags = new WP_HTML_Tag_Processor( $html );
	ok( $tags->next_tag( 'form' ) && 'get' === $tags->get_attribute( 'method' ) && 'ohmylms/language' === $tags->get_attribute( 'data-wp-interactive' ) && 'actions.choose' === $tags->get_attribute( 'data-wp-on--submit' ), 'the switcher is an ohmylms/language GET form' );
	ok( $home . '/courses/' === $tags->get_attribute( 'action' ), 'it submits to the current page' );
	ok(
		array(
			'current' => 'mn',
			'busy'    => false,
		) === json_decode( $tags->get_attribute( 'data-wp-context' ), true ),
		'its context holds the current locale'
	);
	ok( $tags->next_tag( 'span' ) && $tags->has_class( 'ohmylms-visually-hidden' ), 'the compact variant keeps its label for screen readers' );
	$label_id = $tags->get_attribute( 'id' );
	ok( $tags->next_tag( 'span' ) && 'group' === $tags->get_attribute( 'role' ) && $label_id === $tags->get_attribute( 'aria-labelledby' ), 'the buttons are a labelled group' );
	ok( $tags->next_tag( 'button' ) && 'en_US' === $tags->get_attribute( 'value' ) && 'en-US' === $tags->get_attribute( 'lang' ) && 'false' === $tags->get_attribute( 'aria-pressed' ) && 'submit' === $tags->get_attribute( 'type' ) && 'ohmylms_locale' === $tags->get_attribute( 'name' ), 'English is a submit button with lang="en-US"' );
	ok( $tags->next_tag( 'button' ) && 'mn' === $tags->get_attribute( 'value' ) && 'mn' === $tags->get_attribute( 'lang' ) && 'true' === $tags->get_attribute( 'aria-pressed' ), 'Монгол has lang="mn" and is pressed' );
	ok( false !== strpos( $html, '>Монгол</button>' ) && false !== strpos( $html, '>English</button>' ), 'each language is named in itself' );
	ok( false !== strpos( $html, 'name="course_search" value="math"' ) && false !== strpos( $html, 'name="course_level[]" value="beginner"' ), 'the current query is kept' );
	ok( false === strpos( $html, 'type="hidden" name="ohmylms_locale"' ) && false === strpos( $html, InterfaceLanguage::NONCE_FIELD ), 'visitors get no nonce and no stale switcher field' );
	ok( wp_script_is( 'ohmylms-language-switcher', 'enqueued' ) || wp_style_is( Switcher::STYLE, 'enqueued' ), 'the stylesheet is queued' );
	ok( false !== strpos( do_shortcode( '[ohmylms_language_switcher label="Хэл"]' ), '>Хэл</span>' ), 'the shortcode renders with a custom label' );
	wp_set_current_user( $learner_id );
	ok( false !== strpos( Switcher::render(), 'name="' . InterfaceLanguage::NONCE_FIELD . '"' ), 'signed-in users get the form nonce' );
	ok( false !== strpos( ohmylms_get_language_switcher(), 'ohmylms-language-switcher is-inline' ), 'the theme helper renders the inline variant' );
	update_option( Switcher::OPTION, 'no' );
	ok( '' === Switcher::render() && '' === do_shortcode( '[ohmylms_language_switcher]' ), 'turned off in Settings › General, nothing is shown' );
	delete_option( Switcher::OPTION );
	wp_set_current_user( 0 );

	// Script translations: every plugin script that uses wp-i18n gets the ohmylms domain.
	request_as( array( 'cookie' => 'mn' ) );
	$probes = array( 'ohmylms-i18n-probe', 'ohmylms-no-i18n-probe', 'ohmylms-own-domain-probe' );
	wp_register_script( $probes[0], plugins_url( 'assets/js/i18n-probe.js', OHMYLMS_FILE ), array( 'wp-i18n' ), '1', true );
	wp_register_script( $probes[1], plugins_url( 'assets/js/no-i18n-probe.js', OHMYLMS_FILE ), array(), '1', true );
	wp_register_script( $probes[2], plugins_url( 'assets/js/own-domain-probe.js', OHMYLMS_FILE ), array( 'wp-i18n' ), '1', true );
	wp_set_script_translations( $probes[2], 'other-domain' );
	InterfaceLanguage::translate_scripts();
	ok( 'ohmylms' === wp_scripts()->registered[ $probes[0] ]->textdomain, 'plugin scripts that depend on wp-i18n get the ohmylms domain' );
	ok( empty( wp_scripts()->registered[ $probes[1] ]->textdomain ), 'scripts without wp-i18n are left alone' );
	ok( 'other-domain' === wp_scripts()->registered[ $probes[2] ]->textdomain, 'a text domain set by the script owner is kept' );
	foreach ( $probes as $probe ) {
		wp_deregister_script( $probe );
	}
	if ( is_readable( OHMYLMS_DIR . '/languages/ohmylms-mn-ohmylms.json' ) ) {
		wp_register_script( 'ohmylms-i18n-probe', plugins_url( 'assets/js/i18n-probe.js', OHMYLMS_FILE ), array( 'wp-i18n' ), '1', true );
		wp_register_script( 'ohmylms-i18n-probe-2', plugins_url( 'assets/js/i18n-probe-2.js', OHMYLMS_FILE ), array( 'wp-i18n' ), '1', true );
		InterfaceLanguage::translate_scripts();
		$json = load_script_textdomain( 'ohmylms-i18n-probe', 'ohmylms', OHMYLMS_DIR . '/languages' );
		ok( is_string( $json ) && false !== strpos( $json, '"lang":"mn"' ), 'a script without its own file gets the all-strings catalog' );
		ok( false === load_script_textdomain( 'ohmylms-i18n-probe-2', 'ohmylms', OHMYLMS_DIR . '/languages' ), 'the catalog is printed once per page' );
		wp_deregister_script( 'ohmylms-i18n-probe' );
		wp_deregister_script( 'ohmylms-i18n-probe-2' );
	}
	if ( is_readable( OHMYLMS_DIR . '/languages/ohmylms-mn-ohmylms-admin-app.json' ) ) {
		wp_register_script( 'ohmylms-admin-app', plugins_url( 'assets/dist/admin/admin-app.js', OHMYLMS_FILE ), array( 'wp-i18n' ), '1', true );
		InterfaceLanguage::translate_scripts();
		$json = load_script_textdomain( 'ohmylms-admin-app', 'ohmylms', OHMYLMS_DIR . '/languages' );
		ok( is_string( $json ) && false !== strpos( $json, '"mn"' ), 'the admin app gets its Mongolian catalog in mn' );
		wp_deregister_script( 'ohmylms-admin-app' );
	}
	ok( ! $warnings, implode( "\n", $warnings ) );
} finally {
	wp_set_current_user( 0 );
	if ( $learner_id ) {
		wp_delete_user( $learner_id );
	}
	if ( null === $saved_option ) {
		delete_option( Switcher::OPTION );
	} else {
		update_option( Switcher::OPTION, $saved_option );
	}
	$_COOKIE = $saved_cookies;
}

echo "Interface language checks passed: $checks\n";
