<?php
/**
 * Legacy admin route map integration test (includes/Admin/LegacyRoutes.php).
 *
 * Runs against a WordPress install (the Local site by default). Checks the route map against the shared
 * fixture tests/fixtures/legacy-routes.json (also used by tests/js/legacy-redirects.test.mjs), that every
 * target exists in the new app, the URLs of menus, commands and helpers, that the cutover switch and the
 * legacy-page redirect script are gone, the server redirect of the legacy page, the hash mapping on the
 * app, the unavailable notice (see docs/CUTOVER.md) and a rendered instructor email. It creates a tagged
 * course, assignment, learner and submission and removes them in `finally`;
 * mail is intercepted with `pre_wp_mail` and redirects with `wp_redirect`, so nothing is sent.
 *
 * Usage (set OHMYLMS_LEGACY_ROUTES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/legacy-routes-integration.php
 * Set OHMYLMS_LEGACY_ROUTES_WRITE=1 as well to rewrite the fixture's `routes` after changing the map.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_LEGACY_ROUTES_TEST' ) ) {
	exit( "Set OHMYLMS_LEGACY_ROUTES_TEST=1 to run the legacy route tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

use OhMyLMS\Admin\AppPage;
use OhMyLMS\Admin\Commands;
use OhMyLMS\Admin\LegacyRoutes;
use OhMyLMS\Admin\Menu;

global $wpdb;

$checks   = 0;
$posts    = array();
$users    = array();
$attempts = array();
$tag      = 'lrtest' . strtolower( wp_generate_password( 6, false, false ) );
$root     = dirname( __DIR__, 2 );

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
 * Call a private method.
 *
 * @param object|string $target Object or class name.
 * @param string        $method Method name.
 * @param array         $args   Arguments.
 * @return mixed
 */
function call_private( $target, $method, array $args = array() ) {
	$reflection = new ReflectionMethod( $target, $method );
	return $reflection->invokeArgs( is_object( $target ) ? $target : null, $args );
}

/**
 * Submenu URL by title (badges and markup removed).
 *
 * @param string $title Visible title.
 * @return string|null
 */
function submenu_url( $title ) {
	global $submenu;
	foreach ( $submenu[ OHMYLMS_SLUG ] ?? array() as $item ) {
		if ( trim( wp_strip_all_tags( $item[0] ) ) === $title ) {
			return $item[2];
		}
	}
	return null;
}

try {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	);
	ok( $admins, 'an administrator exists' );
	wp_set_current_user( $admins[0]->ID );
	LegacyRoutes::reset();
	foreach ( array( 'enabled', 'legacy_admin_enabled', 'opted_out', 'enqueue_redirect' ) as $removed ) {
		ok( ! method_exists( LegacyRoutes::class, $removed ), 'the cutover switch is gone: LegacyRoutes::' . $removed . '()' );
	}
	ok( ! defined( LegacyRoutes::class . '::LEGACY_ADMIN_CONSTANT' ) && ! defined( LegacyRoutes::class . '::OPT_OUT' ), 'the cutover switch constants are gone' );
	ok( ! method_exists( Menu::class, 'plugin_page' ) && ! is_file( OHMYLMS_INCLUDES . '/Admin/views/app.php' ), 'the legacy page view is gone' );

	// Route map: the shared fixture's rules are the PHP rules, and every case resolves as listed.
	$fixture_file = $root . '/tests/fixtures/legacy-routes.json';
	$fixture      = json_decode( file_get_contents( $fixture_file ), true );
	ok( is_array( $fixture ) && $fixture['cases'], 'fixture loads' );
	if ( '1' === getenv( 'OHMYLMS_LEGACY_ROUTES_WRITE' ) ) {
		$fixture['routes'] = LegacyRoutes::routes();
		$json              = preg_replace_callback(
			'/^(?: {4})+/m',
			static function ( $indent ) {
				return str_repeat( "\t", strlen( $indent[0] ) / 4 );
			},
			wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);
		file_put_contents( $fixture_file, $json . "\n" );
	}
	ok( LegacyRoutes::routes() === $fixture['routes'], 'fixture routes match LegacyRoutes::routes() (rerun with OHMYLMS_LEGACY_ROUTES_WRITE=1)' );
	foreach ( $fixture['cases'] as $case ) {
		$actual = LegacyRoutes::resolve( $case[0] );
		ok( $actual === $case[1], sprintf( 'resolve(%s) is %s, got %s', $case[0], var_export( $case[1], true ), var_export( $actual, true ) ) );
	}

	// Every target is a route of the new app, and every tab it names is a tab of that screen.
	$paths = array();
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/assets/src/app/features', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( preg_match( '/(?<!\.test)\.ts$/', $file->getFilename() ) && preg_match_all( "/^\tpath: '([^']+)'/m", file_get_contents( $file->getPathname() ), $found ) ) {
			foreach ( $found[1] as $path ) {
				// A trailing optional segment (`/courses/:id/:tab?`) also matches the path without it.
				while ( true ) {
					$paths[] = preg_replace( '/:[A-Za-z]+/', ':', $path );
					if ( ! preg_match( '#/:[A-Za-z]+\?$#', $path ) ) {
						break;
					}
					$path = preg_replace( '#/:[A-Za-z]+\?$#', '', $path );
				}
			}
		}
	}
	$tab_ids = static function ( $relative, $constant ) use ( $root ) {
		$source = file_get_contents( $root . '/assets/src/app/features/' . $relative );
		preg_match( '/export const ' . $constant . ' = \[([^\]]*)\]/', $source, $list );
		preg_match_all( "/'([a-z-]+)'/", $list[1] ?? '', $ids );
		return $ids[1];
	};
	$tabs    = array(
		'/settings'     => array_merge( $tab_ids( 'settings/types.ts', 'GROUP_IDS' ), $tab_ids( 'settings/types.ts', 'EXTRA_TAB_IDS' ) ),
		'/gamification' => $tab_ids( 'gamification/types.ts', 'TAB_IDS' ),
		'/account-hub'  => $tab_ids( 'account-hub/types.ts', 'TAB_IDS' ),
	);
	ok( in_array( 'payment-gateways', $tabs['/settings'], true ) && in_array( 'xp', $tabs['/gamification'], true ), 'tab ids were read from the app' );
	foreach ( LegacyRoutes::routes() as $rule ) {
		$target = explode( '?', $rule['to'], 2 );
		ok( in_array( preg_replace( '/:[A-Za-z]+/', ':', $target[0] ), $paths, true ), 'new app has route ' . $target[0] );
		if ( isset( $target[1] ) && preg_match( '/(?:^|&)tab=([a-z-]+)/', $target[1], $tab ) ) {
			ok( in_array( $tab[1], $tabs[ $target[0] ] ?? array(), true ), 'new app has tab ' . $rule['to'] );
		}
	}

	// Absolute URLs from the helpers and the screens that use them.
	$app    = admin_url( 'admin.php?page=' . AppPage::SLUG );
	$landed = admin_url( 'admin.php?page=' . AppPage::SLUG . '&' . LegacyRoutes::FROM_LEGACY . '=1' );
	ok( LegacyRoutes::url( '/orders' ) === $app . '#/orders', 'mapped url' );
	ok( LegacyRoutes::url( '#/order-edit/45' ) === $app . '#/orders/45', 'mapped url keeps the id' );
	ok( LegacyRoutes::url( 'accounthub?tab=classes' ) === $app . '#/account-hub?tab=classes', 'mapped url maps the tab' );
	ok( LegacyRoutes::url( '/extensions/unknown-addon-page' ) === $landed . '#/extensions/unknown-addon-page', 'an unmapped url opens the app home with the notice, got ' . LegacyRoutes::url( '/extensions/unknown-addon-page' ) );
	ok( LegacyRoutes::url( '/content-hub/exams?action=import' ) === $app . '#/exams?action=import', 'exam dialogs open the new exams screen' );
	ok( LegacyRoutes::url( '/quiz-edit/5' ) === $app . '#/quizzes/5', 'quiz edit opens the new quiz editor' );
	ok( LegacyRoutes::url( '/content-hub/question-bank?action=new' ) === $app . '#/content-hub/question-bank?action=new', 'question dialogs open the new question bank' );
	ok( LegacyRoutes::url( '/quizzes?action=bulk' ) === $landed . '#/quizzes?action=bulk', 'an unknown dialog opens the app home with the notice' );
	ok( LegacyRoutes::app_url( 'orders/3' ) === $app . '#/orders/3', 'app_url' );
	ok(
		array(
			'page'  => AppPage::SLUG,
			'route' => '/settings?tab=tax',
		) === LegacyRoutes::target( '/settings/payments-settings/tax' ),
		'target of a mapped route'
	);
	ok(
		array(
			'page'  => AppPage::SLUG,
			'route' => LegacyRoutes::APP_HOME,
		) === LegacyRoutes::target( '/extensions/unknown-addon-page' ),
		'target of an unmapped route is the app home'
	);
	ok( \OhMyLMS\AI\AdminPage::url() === $app . '#/settings?tab=ai-tutor', 'AI tutor bookmark opens the new tab' );
	ok( call_private( \OhMyLMS\XApi\AdminPage::class, 'url', array( 'saved' ) ) === add_query_arg( 'xapi_notice', 'saved', $app . '#/settings?tab=xapi' ), 'xAPI redirect opens the new tab' );
	ok( \OhMyLMS\Student\Hub::admin_home_url() === $app . '#/account-hub', 'Student Home admin address opens Account Hub' );

	// The old switch filters no longer change anything.
	add_filter( 'ohmylms_legacy_routes_enabled', '__return_false' );
	add_filter( 'ohmylms_legacy_admin_enabled', '__return_true' );
	LegacyRoutes::reset();
	ok( LegacyRoutes::url( '/orders' ) === $app . '#/orders', 'the retired switch filters are ignored' );
	remove_filter( 'ohmylms_legacy_routes_enabled', '__return_false' );
	remove_filter( 'ohmylms_legacy_admin_enabled', '__return_true' );
	LegacyRoutes::reset();

	// Command palette: each command names the page that serves it.
	$add_on = static function ( $commands ) {
		$commands[] = array(
			'name'  => 'addon/orders',
			'label' => 'Orders',
			'route' => '/orders',
		);
		$commands[] = array(
			'name'  => 'addon/own',
			'label' => 'Own page',
			'route' => '/orders',
			'page'  => 'my-addon',
		);
		return $commands;
	};
	add_filter( 'ohmylms_admin_commands', $add_on );
	$commands = array_column( Commands::all(), null, 'name' );
	remove_filter( 'ohmylms_admin_commands', $add_on );
	$expect = array(
		'ohmylms/go-dashboard'      => array( AppPage::SLUG, '/dashboard' ),
		'ohmylms/go-courses'        => array( AppPage::SLUG, '/courses' ),
		'ohmylms/go-exams'          => array( AppPage::SLUG, '/exams' ),
		'ohmylms/go-question-bank'  => array( AppPage::SLUG, '/content-hub/question-bank' ),
		'ohmylms/go-assignments'    => array( AppPage::SLUG, '/assignments' ),
		'ohmylms/go-account-hub'    => array( AppPage::SLUG, '/account-hub' ),
		'ohmylms/go-settings-pay'   => array( AppPage::SLUG, '/settings?tab=payment-gateways' ),
		'ohmylms/go-settings-email' => array( AppPage::SLUG, '/emails' ),
		'ohmylms/new-question'      => array( AppPage::SLUG, '/content-hub/question-bank?action=new' ),
		'ohmylms/new-exam'          => array( AppPage::SLUG, '/exams?action=new' ),
		'addon/orders'              => array( AppPage::SLUG, '/orders' ),
		'addon/own'                 => array( 'my-addon', '/orders' ),
	);
	foreach ( $expect as $name => $target ) {
		ok( isset( $commands[ $name ] ) && array( $commands[ $name ]['page'], $commands[ $name ]['route'] ) === $target, 'command ' . $name );
	}

	// Menu: every entry opens the new app; the app page has no entry of its own; add-on entries that open
	// the legacy page are mapped or dropped.
	$legacy_page_hook = 'load-toplevel_page_' . OHMYLMS_SLUG;
	$app_hook         = 'ohmylms_page_' . AppPage::SLUG;
	$redirect_to      = static function ( $location ) {
		throw new RuntimeException( 'redirect:' . $location );
	};
	$menu_object      = ( new ReflectionClass( Menu::class ) )->newInstanceWithoutConstructor();
	add_filter( 'ohmylms_show_sessions_menu', '__return_true' );
	$GLOBALS['menu']              = array();
	$GLOBALS['submenu']           = array();
	$GLOBALS['admin_page_hooks']  = array();
	$GLOBALS['_registered_pages'] = array();
	$GLOBALS['_parent_pages']     = array();
	$menu_object->init_menu();
	$menu_object->register_submenu();
	( new ReflectionClass( \OhMyLMS\Hooks\CommonHook::class ) )->newInstanceWithoutConstructor()->register_submenu();
	AppPage::register_menu();
	// Entries an add-on might add: mapped and unmapped legacy routes, the bare legacy page, another page,
	// an external link.
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on orders', 'manage_options', admin_url( 'admin.php?page=' . OHMYLMS_SLUG . '#/orders' ) );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on page link', 'manage_options', 'admin.php?page=' . OHMYLMS_SLUG . '#/extensions/unknown-addon-page' );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on home', 'manage_options', OHMYLMS_SLUG );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on page', 'manage_options', 'ohmylms-addon-page' );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on docs', 'manage_options', 'https://example.test/docs' );
	ok( isset( $GLOBALS['_registered_pages'][ 'toplevel_page_' . OHMYLMS_SLUG ] ), 'menu: the legacy page stays a registered admin page' );
	ok( false !== has_action( 'toplevel_page_' . OHMYLMS_SLUG, array( LegacyRoutes::class, 'render_legacy_page' ) ), 'menu: the legacy page has a body, so WordPress fires its load hook' );
	ok( isset( $GLOBALS['_registered_pages'][ $app_hook ] ), 'menu: the app page is registered' );
	ok( submenu_url( 'OhMyLMS' ) === AppPage::SLUG, 'menu: the app page entry exists until admin_head' );
	$menu_object->map_legacy_submenu();
	AppPage::hide_menu_entry();
	ok( false !== has_action( 'admin_head', array( AppPage::class, 'hide_menu_entry' ) ), 'menu: the app page hides its entry on admin_head' );
	ok( submenu_url( 'Dashboard' ) === $app . '#/dashboard', 'menu: Dashboard' );
	ok( array_values( $GLOBALS['submenu'][ OHMYLMS_SLUG ] )[0][2] === $app . '#/dashboard', 'menu: the top-level entry opens the first submenu, the new dashboard' );
	ok( submenu_url( 'Content Hub' ) === $app . '#/content-hub', 'menu: Content Hub opens the overview' );
	ok( submenu_url( 'Sessions' ) === $app . '#/sessions', 'menu: Sessions' );
	ok( submenu_url( 'Membership' ) === $app . '#/memberships', 'menu: Membership' );
	ok( submenu_url( 'Account Hub' ) === $app . '#/account-hub', 'menu: Account Hub' );
	ok( submenu_url( 'Account Hub' ) === \OhMyLMS\Student\Hub::admin_home_url(), 'menu: Account Hub matches the highlighted submenu file' );
	ok( submenu_url( 'Settings' ) === $app . '#/settings?tab=general', 'menu: Settings' );
	ok( submenu_url( 'Add-ons' ) === $app . '#/integrations', 'menu: Add-ons' );
	ok( null === submenu_url( 'OhMyLMS (new)' ) && null === submenu_url( 'OhMyLMS' ), 'menu: the app page has no entry of its own' );
	if ( apply_filters( 'ohmylms_show_gamification_menu', false ) ) {
		ok( submenu_url( 'Gamification' ) === $app . '#/gamification?tab=points', 'menu: Gamification' );
	} else {
		ok( submenu_url( 'Certificates' ) === $app . '#/certificates', 'menu: Certificates' );
	}
	if ( apply_filters( 'ohmylms_should_enable_webhooks', false ) ) {
		ok( submenu_url( 'Webhooks' ) === $app . '#/webhooks', 'menu: Webhooks' );
	}
	ok( submenu_url( 'Add-on orders' ) === $app . '#/orders', 'menu: a mapped add-on entry opens the new app' );
	ok( submenu_url( 'Add-on home' ) === $app . '#/dashboard', 'menu: an add-on entry for the bare legacy page opens the dashboard' );
	ok( null === submenu_url( 'Add-on page link' ), 'menu: an unmapped add-on entry is dropped' );
	ok( submenu_url( 'Add-on page' ) === 'ohmylms-addon-page', 'menu: entries for other pages are kept' );
	ok( submenu_url( 'Add-on docs' ) === 'https://example.test/docs', 'menu: external links are kept' );
	foreach ( $GLOBALS['submenu'][ OHMYLMS_SLUG ] as $item ) {
		ok( ! preg_match( '#admin\.php\?page=' . preg_quote( OHMYLMS_SLUG, '#' ) . '(?:[&\#]|$)#', (string) $item[2] ), 'menu: no submenu entry opens the legacy page: ' . $item[2] );
	}
	remove_filter( 'ohmylms_show_sessions_menu', '__return_true' );

	// Plugin action link.
	$links = apply_filters( 'plugin_action_links_' . plugin_basename( OHMYLMS_FILE ), array() );
	ok( 1 === count( $links ) && false !== strpos( $links[0], esc_url( $app . '#/settings?tab=general' ) ), 'plugins screen Settings link opens the new settings' );

	// The legacy page redirects on the server, whatever its hash; other parameters are kept.
	ok( false !== has_action( $legacy_page_hook, array( LegacyRoutes::class, 'redirect_legacy_page' ) ), 'the legacy page has the redirect hook' );
	$_GET              = array(
		'page'                        => OHMYLMS_SLUG,
		'xapi_notice'                 => 'saved',
		LegacyRoutes::RETIRED_OPT_OUT => '1',
	);
	$expected_redirect = add_query_arg(
		array(
			'xapi_notice'             => 'saved',
			'page'                    => AppPage::SLUG,
			LegacyRoutes::FROM_LEGACY => '1',
		),
		admin_url( 'admin.php' )
	);
	ok( LegacyRoutes::legacy_redirect_url() === $expected_redirect, 'the legacy page redirects to the app, keeps other parameters and drops the retired opt-out, got ' . LegacyRoutes::legacy_redirect_url() );
	ok( false === strpos( $expected_redirect, '#' ), 'the redirect has no fragment, so the browser keeps the legacy one' );
	$redirected = '';
	add_filter( 'wp_redirect', $redirect_to );
	try {
		LegacyRoutes::redirect_legacy_page();
	} catch ( RuntimeException $redirect ) {
		$redirected = $redirect->getMessage();
	}
	remove_filter( 'wp_redirect', $redirect_to );
	ok( 'redirect:' . $expected_redirect === $redirected, 'redirect_legacy_page() sends the redirect' );
	ob_start();
	LegacyRoutes::render_legacy_page();
	ok( false !== strpos( ob_get_clean(), esc_url( LegacyRoutes::app_url( LegacyRoutes::APP_HOME ) ) ), 'the legacy page body links to the app' );

	// Hash mapping: only on the app, only when opened from a legacy address, in the head, with the map.
	$_GET = array();
	wp_dequeue_script( LegacyRoutes::HANDLE );
	LegacyRoutes::enqueue_landing( $app_hook );
	ok( ! wp_script_is( LegacyRoutes::HANDLE, 'enqueued' ), 'the hash mapping does not load on a normal app visit' );
	$_GET[ LegacyRoutes::FROM_LEGACY ] = '1';
	LegacyRoutes::enqueue_landing( 'toplevel_page_' . OHMYLMS_SLUG );
	ok( ! wp_script_is( LegacyRoutes::HANDLE, 'enqueued' ), 'the hash mapping only loads on the app' );
	LegacyRoutes::enqueue_landing( $app_hook );
	ok( wp_script_is( LegacyRoutes::HANDLE, 'enqueued' ), 'the hash mapping loads on the app' );
	ok( ! wp_scripts()->get_data( LegacyRoutes::HANDLE, 'group' ), 'the hash mapping prints in the head, before the app' );
	$landing = implode( "\n", (array) wp_scripts()->get_data( LegacyRoutes::HANDLE, 'before' ) );
	ok( false !== strpos( $landing, 'window.ohmylmsLegacyRoutes = ' . wp_json_encode( LegacyRoutes::script_data() ) ), 'the mapping receives LegacyRoutes::script_data()' );
	$landing_data = array(
		'param'    => LegacyRoutes::FROM_LEGACY,
		'home'     => LegacyRoutes::APP_HOME,
		'noticeId' => LegacyRoutes::NOTICE_ID,
	);
	ok(
		array(
			'routes'  => LegacyRoutes::routes(),
			'landing' => $landing_data,
		) === LegacyRoutes::script_data(),
		'the mapping knows the routes, the marker, the home route and the notice'
	);
	wp_dequeue_script( LegacyRoutes::HANDLE );
	ok( false !== has_action( 'admin_enqueue_scripts', array( LegacyRoutes::class, 'enqueue_landing' ) ), 'the hash mapping is hooked' );
	ok( in_array( LegacyRoutes::FROM_LEGACY, wp_removable_query_args(), true ), 'the canonical admin address drops the marker, so WordPress does not put it back after the mapping' );

	// Unavailable notice: hidden, filled in by the browser, no link to a legacy admin.
	ob_start();
	AppPage::render();
	$page_html = ob_get_clean();
	ok( false !== strpos( $page_html, 'id="' . LegacyRoutes::NOTICE_ID . '"' ) && false !== strpos( $page_html, 'hidden' ), 'the app prints the hidden unavailable notice' );
	ok( false !== strpos( $page_html, 'data-ohmylms-legacy-route' ), 'the notice has the slot for the legacy route' );
	ok( false === strpos( $page_html, 'data-ohmylms-legacy-link' ) && false === strpos( $page_html, 'legacy admin' ) && false === strpos( $page_html, 'page=' . OHMYLMS_SLUG ), 'the notice does not link to a legacy admin' );
	ok( array() === LegacyRoutes::unavailable() && false === strpos( $page_html, 'Not available:' ), 'every legacy screen has a new one, so the notice lists none' );
	$probe_screen = static function ( $screens ) {
		$screens['/extensions/unknown-addon-page'] = 'Probe add-on screen';
		return $screens;
	};
	add_filter( 'ohmylms_legacy_unavailable_screens', $probe_screen );
	ob_start();
	AppPage::render();
	$probe_html = ob_get_clean();
	remove_filter( 'ohmylms_legacy_unavailable_screens', $probe_screen );
	ok( false !== strpos( $probe_html, 'Not available:' ) && false !== strpos( $probe_html, 'Probe add-on screen' ), 'the notice lists screens added by add-ons' );
	unset( $_GET[ LegacyRoutes::FROM_LEGACY ] );
	ob_start();
	AppPage::render();
	ok( false === strpos( ob_get_clean(), LegacyRoutes::NOTICE_ID ), 'a normal app visit has no notice' );

	// Hard-coded legacy links that now open the app.
	ok( \OhMyLMS\Admin\LegacyRoutes::app_url( '/account-hub' ) === $app . '#/account-hub', 'View as returns to Account Hub' );
	ok( false === strpos( file_get_contents( OHMYLMS_DIR . '/includes/Schools/ViewAs.php' ), "page=' . OHMYLMS_SLUG" ), 'View as no longer links to the legacy page' );
	ok( false === strpos( file_get_contents( OHMYLMS_DIR . '/templates/admin/skill-assessments.php' ), "admin.php?page=ohmylms'" ), 'skill assessments no longer link to the legacy page' );
	$meet = file_get_contents( OHMYLMS_DIR . '/includes/Integrations/GoogleMeet/Includes/Hooks.php' );
	ok( 4 === substr_count( $meet, "LegacyRoutes::app_url( '/integrations?auth=" ), 'the Google Meet OAuth redirects land on the new Integrations screen' );
	$_GET = array();

	// Rendered instructor email links to the exact submission in the new grading screen.
	$student = wp_insert_user(
		array(
			'user_login' => $tag,
			'user_email' => $tag . '@example.test',
			'user_pass'  => wp_generate_password( 20 ),
			'role'       => 'subscriber',
		)
	);
	ok( ! is_wp_error( $student ), 'learner created' );
	$users[]    = $student;
	$course     = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-course',
			'post_status' => 'publish',
			'post_title'  => $tag . ' course',
			'post_author' => $admins[0]->ID,
		)
	);
	$posts[]    = $course;
	$assignment = wp_insert_post(
		array(
			'post_type'   => 'ohmylms-assignment',
			'post_status' => 'publish',
			'post_title'  => $tag . ' assignment',
		)
	);
	$posts[]    = $assignment;
	ok( $course && $assignment, 'course and assignment created' );
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_assignment_attempts',
		array(
			'user_id'       => $student,
			'course_id'     => $course,
			'assignment_id' => $assignment,
			'content'       => 'Answer',
			'files'         => '[]',
			'status'        => 'submitted',
			'start_date'    => current_time( 'mysql', true ),
			'end_date'      => current_time( 'mysql', true ),
		)
	);
	$submission = (int) $wpdb->insert_id;
	$attempts[] = $submission;
	ok( $submission > 0, 'submission created' );
	$grading = LegacyRoutes::assignment_grading_url( $assignment, $student );
	ok( $grading === $app . '#/assignments/' . $assignment . '/submissions/' . $submission, 'grading url names the submission' );
	ok( LegacyRoutes::assignment_grading_url( $assignment, $student + 100000 ) === $app . '#/assignments/' . $assignment . '/submissions?student=' . ( $student + 100000 ), 'grading url without a submission opens the list' );

	$mails   = array();
	$capture = static function ( $short_circuit, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	};
	add_filter( 'pre_wp_mail', $capture, 10, 2 );
	$email    = ( new ReflectionClass( \OhMyLMS\Emails\CreatorsEmail\AssignmentSubmitted::class ) )->newInstanceWithoutConstructor();
	$settings = array_merge( $email->default_settings(), array( 'recipient_email' => 'instructor-' . $tag . '@example.test' ) );
	call_private( $email, 'send_single', array( $assignment, $course, $student, $settings ) );
	call_private(
		$email,
		'send_digest_for_course',
		array(
			$course,
			array(
				array(
					'assignment_id' => $assignment,
					'course_id'     => $course,
					'student_id'    => $student,
					'time'          => current_time( 'mysql' ),
				),
			),
			$settings,
		)
	);
	remove_filter( 'pre_wp_mail', $capture, 10 );
	ok( 2 === count( $mails ), 'single and digest emails rendered' );
	ok( false !== strpos( $mails[0]['message'], 'href="' . esc_url( $grading ) . '"' ), 'single email button opens the grading screen' );
	ok( false === strpos( $mails[0]['message'], 'page=' . OHMYLMS_SLUG . '#' ), 'single email has no legacy admin link' );
	ok( false !== strpos( $mails[1]['message'], 'href="' . esc_url( $app . '#/assignments' ) . '"' ), 'digest email button opens the assignments list' );

	echo "Legacy routes: {$checks} checks passed.\n";
} catch ( Throwable $error ) {
	echo $error->getMessage() . "\n" . $error->getTraceAsString() . "\n";
	$failed = true;
} finally {
	foreach ( $attempts as $id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_assignment_attempts', array( 'id' => $id ) );
	}
	foreach ( $posts as $id ) {
		if ( $id ) {
			wp_delete_post( $id, true );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
}
exit( empty( $failed ) ? 0 : 1 );
