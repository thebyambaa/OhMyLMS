<?php
/**
 * Admin navigation and legacy route integration test (includes/Admin/AdminNavigation.php,
 * includes/Admin/LegacyRoutes.php).
 *
 * Runs against a WordPress install (the Local site by default). Checks the navigation table and the
 * address of every app route against tests/fixtures/admin-navigation.json (also used by the app's
 * routing/navigation-contract.test.ts), the legacy route map against tests/fixtures/legacy-routes.json
 * (also used by tests/js/legacy-redirects.test.mjs), that every legacy and app route has a new address,
 * the URLs of menus, commands and helpers, the six submenus and their tabs, the server redirect of the
 * retired app page, the hash landing on the Dashboard, the unavailable notice and a rendered instructor
 * email. It creates a tagged course, assignment, learner and submission and removes them in `finally`;
 * mail is intercepted with `pre_wp_mail` and redirects with `wp_redirect`, so nothing is sent.
 *
 * Usage (set OHMYLMS_LEGACY_ROUTES_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/legacy-routes-integration.php
 * Set OHMYLMS_LEGACY_ROUTES_WRITE=1 as well to rewrite the generated parts of both fixtures (`routes`
 * of the legacy map; `groups` and `routes` of the navigation) after changing them.
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
require_once ABSPATH . 'wp-admin/includes/template.php';

use OhMyLMS\Admin\AdminNavigation;
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
		if ( trim( preg_replace( '/\s*\d+$/', '', wp_strip_all_tags( $item[0] ) ) ) === $title ) {
			return $item[2];
		}
	}
	return null;
}

/**
 * Absolute admin address of a query string.
 *
 * @param string $query Query such as `page=ohmylms-commerce&tab=orders`.
 * @return string
 */
function address_url( $query ) {
	return admin_url( 'admin.php?' . $query );
}

/**
 * Write a fixture with tabs, as the files are indented.
 *
 * @param string $file    Path.
 * @param array  $fixture Data.
 */
function write_fixture( $file, array $fixture ) {
	$json = preg_replace_callback(
		'/^(?: {4})+/m',
		static function ( $indent ) {
			return str_repeat( "\t", strlen( $indent[0] ) / 4 );
		},
		wp_json_encode( $fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
	);
	file_put_contents( $file, $json . "\n" );
}

/**
 * Render a page as a request for the given query would.
 *
 * @param array $query Query parameters.
 * @return string HTML.
 */
function render_page( array $query ) {
	$_GET = $query;
	AdminNavigation::reset();
	ob_start();
	AdminNavigation::render();
	$html = ob_get_clean();
	$_GET = array();
	return $html;
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
	AdminNavigation::reset();
	foreach ( array( 'enabled', 'legacy_admin_enabled', 'opted_out', 'enqueue_redirect', 'redirect_legacy_page', 'legacy_redirect_url', 'render_legacy_page', 'from_legacy' ) as $removed ) {
		ok( ! method_exists( LegacyRoutes::class, $removed ), 'retired: LegacyRoutes::' . $removed . '()' );
	}
	ok( ! method_exists( AppPage::class, 'render' ) && ! method_exists( AppPage::class, 'hide_menu_entry' ), 'the app page has no screen of its own any more' );
	ok( ! defined( LegacyRoutes::class . '::LEGACY_ADMIN_CONSTANT' ) && ! defined( LegacyRoutes::class . '::OPT_OUT' ), 'the cutover switch constants are gone' );
	ok( ! method_exists( Menu::class, 'plugin_page' ) && ! method_exists( Menu::class, 'register_submenu' ) && ! is_file( OHMYLMS_INCLUDES . '/Admin/views/app.php' ), 'the legacy page view and the old submenu entries are gone' );
	ok( ! method_exists( \OhMyLMS\Hooks\CommonHook::class, 'register_submenu' ), 'CommonHook no longer adds an Add-ons submenu' );

	// Navigation: the shared fixture holds AdminNavigation's table, and every case has its address.
	$navigation_file = $root . '/tests/fixtures/admin-navigation.json';
	$navigation      = json_decode( file_get_contents( $navigation_file ), true );
	ok( is_array( $navigation ) && $navigation['cases'], 'navigation fixture loads' );
	if ( '1' === getenv( 'OHMYLMS_LEGACY_ROUTES_WRITE' ) ) {
		$navigation = array_merge( $navigation, AdminNavigation::export() );
		write_fixture( $navigation_file, $navigation );
	}
	ok( AdminNavigation::export() === array_intersect_key( $navigation, array_flip( array( 'groups', 'routes' ) ) ), 'navigation fixture matches AdminNavigation::export() (rerun with OHMYLMS_LEGACY_ROUTES_WRITE=1)' );
	foreach ( $navigation['cases'] as $case ) {
		$pairs  = AdminNavigation::address( $case[0] );
		$actual = null === $pairs ? null : AdminNavigation::format_query( $pairs );
		ok( $actual === $case[1], sprintf( 'address(%s) is %s, got %s', $case[0], var_export( $case[1], true ), var_export( $actual, true ) ) );
	}
	ok( count( AdminNavigation::groups() ) === 6 && array( 'ohmylms', 'ohmylms-content', 'ohmylms-accounts', 'ohmylms-commerce', 'ohmylms-engagement', 'ohmylms-settings' ) === array_column( AdminNavigation::groups(), 'page' ), 'six pages, in menu order' );
	foreach ( AdminNavigation::routes() as $entry ) {
		$sample = preg_replace( '#/:[A-Za-z]+\?$#', '', $entry['path'] );
		$sample = preg_replace( '/:[A-Za-z]+/', '7', $sample );
		$pairs  = AdminNavigation::address( $sample );
		ok( null !== $pairs, 'app route ' . $entry['path'] . ' has an address' );
		$query = array_column( $pairs, 1, 0 );
		ok( AdminNavigation::page_slug( $entry['group'] ) === $query['page'], 'app route ' . $entry['path'] . ' opens its group page' );
		if ( isset( $entry['tab'] ) && false === strpos( $entry['tab'], ':' ) ) {
			ok( isset( AdminNavigation::groups()[ $entry['group'] ]['tabs'][ $entry['tab'] ] ), 'tab ' . $entry['group'] . '/' . $entry['tab'] . ' of ' . $entry['path'] . ' exists' );
		}
		if ( isset( $entry['section'] ) ) {
			ok( isset( AdminNavigation::groups()[ $entry['group'] ]['tabs'][ $entry['tab'] ]['sections'][ $entry['section'] ] ), 'section ' . $entry['section'] . ' of ' . $entry['path'] . ' exists' );
		}
	}

	// Legacy route map: the shared fixture's rules are the PHP rules, and every case resolves as listed.
	$fixture_file = $root . '/tests/fixtures/legacy-routes.json';
	$fixture      = json_decode( file_get_contents( $fixture_file ), true );
	ok( is_array( $fixture ) && $fixture['cases'] && $fixture['addresses'], 'fixture loads' );
	if ( '1' === getenv( 'OHMYLMS_LEGACY_ROUTES_WRITE' ) ) {
		$fixture['routes'] = LegacyRoutes::routes();
		write_fixture( $fixture_file, $fixture );
	}
	ok( LegacyRoutes::routes() === $fixture['routes'], 'fixture routes match LegacyRoutes::routes() (rerun with OHMYLMS_LEGACY_ROUTES_WRITE=1)' );
	foreach ( $fixture['cases'] as $case ) {
		$actual = LegacyRoutes::resolve( $case[0] );
		ok( $actual === $case[1], sprintf( 'resolve(%s) is %s, got %s', $case[0], var_export( $case[1], true ), var_export( $actual, true ) ) );
	}
	foreach ( $fixture['addresses'] as $case ) {
		$expected = null === $case[1] ? admin_url( 'admin.php?page=' . OHMYLMS_SLUG . '#/' . ltrim( $case[0], '/' ) ) : address_url( $case[1] );
		ok( LegacyRoutes::url( $case[0] ) === $expected, sprintf( 'url(%s) is %s, got %s', $case[0], $expected, LegacyRoutes::url( $case[0] ) ) );
	}

	// Every legacy target is a route of the new app with an address, and every tab it names exists.
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
		ok( null !== AdminNavigation::address( preg_replace( '/:[A-Za-z]+/', '7', $rule['to'] ) ), 'legacy target ' . $rule['to'] . ' has an address' );
		if ( isset( $target[1] ) && preg_match( '/(?:^|&)tab=([a-z-]+)/', $target[1], $tab ) ) {
			ok( in_array( $tab[1], $tabs[ $target[0] ] ?? array(), true ), 'new app has tab ' . $rule['to'] );
		}
	}
	$settings_tab = AdminNavigation::groups()['settings']['tabs']['settings'];
	ok( array_keys( $settings_tab['sections'] ) === $tabs['/settings'], 'Settings sections are the settings screen tabs, in order' );
	ok( array_keys( AdminNavigation::groups()['engagement']['tabs']['gamification']['sections'] ) === array_merge( $tabs['/gamification'], array( 'certificates' ) ), 'Gamification sections are its tabs plus Certificates' );
	ok( array_slice( array_keys( AdminNavigation::groups()['accounts']['tabs'] ), 1 ) === $tabs['/account-hub'], 'Account Hub tabs are Students and the Account Hub screen tabs' );

	// Absolute URLs from the helpers and the screens that use them.
	$dashboard = admin_url( 'admin.php?page=' . OHMYLMS_SLUG );
	ok( LegacyRoutes::url( '/orders' ) === address_url( 'page=ohmylms-commerce&tab=orders' ), 'mapped url' );
	ok( LegacyRoutes::url( '#/order-edit/45' ) === address_url( 'page=ohmylms-commerce&tab=orders&id=45' ), 'mapped url keeps the id' );
	ok( LegacyRoutes::url( 'accounthub?tab=classes' ) === address_url( 'page=ohmylms-accounts&tab=classes' ), 'mapped url maps the tab' );
	ok( LegacyRoutes::url( '/extensions/unknown-addon-page' ) === $dashboard . '#/extensions/unknown-addon-page', 'an unmapped url opens the Dashboard with the legacy hash, got ' . LegacyRoutes::url( '/extensions/unknown-addon-page' ) );
	ok( LegacyRoutes::app_url( 'orders/3' ) === address_url( 'page=ohmylms-commerce&tab=orders&id=3' ), 'app_url' );
	ok( LegacyRoutes::app_url( '/' ) === $dashboard && LegacyRoutes::app_url( '/dashboard' ) === $dashboard, 'app_url of the Dashboard' );
	ok( LegacyRoutes::app_url( '/no-such-screen' ) === $dashboard . '&' . LegacyRoutes::FROM_APP . '=1#/no-such-screen', 'app_url of an unknown route keeps it in the hash for the app' );
	ok(
		array(
			'page'  => 'ohmylms-settings',
			'tab'   => 'settings',
			'route' => '/settings?tab=tax',
			'url'   => address_url( 'page=ohmylms-settings&tab=settings&section=tax' ),
		) === LegacyRoutes::target( '/settings/payments-settings/tax' ),
		'target of a mapped route'
	);
	ok(
		array(
			'page'  => OHMYLMS_SLUG,
			'tab'   => '',
			'route' => LegacyRoutes::APP_HOME,
			'url'   => $dashboard,
		) === LegacyRoutes::target( '/extensions/unknown-addon-page' ),
		'target of an unmapped route is the Dashboard'
	);
	ok( \OhMyLMS\AI\AdminPage::url() === address_url( 'page=ohmylms-settings&tab=settings&section=ai-tutor' ), 'AI tutor bookmark opens the new section' );
	ok( call_private( \OhMyLMS\XApi\AdminPage::class, 'url', array( 'saved' ) ) === add_query_arg( 'xapi_notice', 'saved', address_url( 'page=ohmylms-settings&tab=settings&section=xapi' ) ), 'xAPI redirect opens the new section' );
	ok( \OhMyLMS\Student\Hub::admin_home_url() === address_url( 'page=ohmylms-accounts&tab=schools' ), 'Student Home admin address opens Account Hub' );
	ok( \OhMyLMS\Assessment\SkillAssessmentPage::url() === address_url( 'page=ohmylms-content&tab=skills&section=assessments' ), 'skill assessments are a Skills section' );
	ok( \OhMyLMS\QuestionBank\ImportPage::url() === address_url( 'page=ohmylms-content&tab=question-bank&section=import-review' ), 'question import review is a Question bank section' );
	add_filter(
		'ohmylms_extension_admin_page_group',
		static function ( $group, $id ) {
			return 'probe-page' === $id ? 'content' : $group;
		},
		10,
		2
	);
	ok( LegacyRoutes::app_url( '/extensions/probe-page' ) === address_url( 'page=ohmylms-content&tab=ext-probe-page' ) && LegacyRoutes::app_url( '/extensions/other' ) === address_url( 'page=ohmylms-settings&tab=ext-other' ), 'extension pages open their group page, Settings by default' );

	// The old switch filters no longer change anything.
	add_filter( 'ohmylms_legacy_routes_enabled', '__return_false' );
	add_filter( 'ohmylms_legacy_admin_enabled', '__return_true' );
	LegacyRoutes::reset();
	ok( LegacyRoutes::url( '/orders' ) === address_url( 'page=ohmylms-commerce&tab=orders' ), 'the retired switch filters are ignored' );
	remove_filter( 'ohmylms_legacy_routes_enabled', '__return_false' );
	remove_filter( 'ohmylms_legacy_admin_enabled', '__return_true' );
	LegacyRoutes::reset();

	// Command palette: each command names the page and tab that serve it, and its address.
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
		'ohmylms/go-dashboard'      => array( OHMYLMS_SLUG, '/dashboard', 'page=ohmylms' ),
		'ohmylms/go-content-hub'    => array( 'ohmylms-content', '/content-hub', 'page=ohmylms-content&tab=courses&section=overview' ),
		'ohmylms/go-courses'        => array( 'ohmylms-content', '/courses', 'page=ohmylms-content&tab=courses' ),
		'ohmylms/go-exams'          => array( 'ohmylms-content', '/exams', 'page=ohmylms-content&tab=exams' ),
		'ohmylms/go-question-bank'  => array( 'ohmylms-content', '/content-hub/question-bank', 'page=ohmylms-content&tab=question-bank' ),
		'ohmylms/go-assignments'    => array( 'ohmylms-content', '/assignments', 'page=ohmylms-content&tab=assignments' ),
		'ohmylms/go-account-hub'    => array( 'ohmylms-accounts', '/account-hub', 'page=ohmylms-accounts&tab=schools' ),
		'ohmylms/go-memberships'    => array( 'ohmylms-commerce', '/memberships', 'page=ohmylms-commerce&tab=memberships' ),
		'ohmylms/go-settings-pay'   => array( 'ohmylms-settings', '/settings?tab=payment-gateways', 'page=ohmylms-settings&tab=settings&section=payment-gateways' ),
		'ohmylms/go-settings-email' => array( 'ohmylms-engagement', '/emails', 'page=ohmylms-engagement&tab=emails' ),
		'ohmylms/new-question'      => array( 'ohmylms-content', '/content-hub/question-bank?action=new', 'page=ohmylms-content&tab=question-bank&action=new' ),
		'ohmylms/new-exam'          => array( 'ohmylms-content', '/exams?action=new', 'page=ohmylms-content&tab=exams&action=new' ),
		'addon/orders'              => array( 'ohmylms-commerce', '/orders', 'page=ohmylms-commerce&tab=orders' ),
	);
	foreach ( $expect as $name => $target ) {
		ok( isset( $commands[ $name ] ) && array( $commands[ $name ]['page'], $commands[ $name ]['route'], $commands[ $name ]['url'] ) === array( $target[0], $target[1], address_url( $target[2] ) ), 'command ' . $name );
	}
	ok( 'my-addon' === $commands['addon/own']['page'] && '/orders' === $commands['addon/own']['route'] && ! isset( $commands['addon/own']['url'] ), 'command addon/own keeps its page' );

	// Menu: six submenus, each a real page; the retired pages have no entry; add-on entries that open
	// the legacy page or the retired app page are mapped or dropped; the badge is on the menu and Commerce.
	$redirect_to = static function ( $location ) {
		throw new RuntimeException( 'redirect:' . $location );
	};
	$menu_object = ( new ReflectionClass( Menu::class ) )->newInstanceWithoutConstructor();
	$build_menu  = static function () use ( $menu_object ) {
		$GLOBALS['menu']              = array();
		$GLOBALS['submenu']           = array();
		$GLOBALS['admin_page_hooks']  = array();
		$GLOBALS['_registered_pages'] = array();
		$GLOBALS['_parent_pages']     = array();
		AdminNavigation::reset();
		$menu_object->init_menu();
		AppPage::register_menu();
		\OhMyLMS\Assessment\SkillAssessmentPage::menu();
		\OhMyLMS\QuestionBank\ImportPage::menu();
	};
	$badge       = static function () {
		return 3;
	};
	add_filter( 'ohmylms_pending_orders_count', $badge );
	$build_menu();
	remove_filter( 'ohmylms_pending_orders_count', $badge );
	$entries = array_map(
		static function ( $item ) {
			return array( trim( preg_replace( '/\s*\d+$/', '', wp_strip_all_tags( $item[0] ) ) ), $item[2] );
		},
		$GLOBALS['submenu'][ OHMYLMS_SLUG ]
	);
	ok(
		array(
			array( 'Dashboard', OHMYLMS_SLUG ),
			array( 'Content Hub', 'ohmylms-content' ),
			array( 'Account Hub', 'ohmylms-accounts' ),
			array( 'Commerce', 'ohmylms-commerce' ),
			array( 'Engagement', 'ohmylms-engagement' ),
			array( 'Settings', 'ohmylms-settings' ),
		) === $entries,
		'menu: exactly the six pages, Dashboard first: ' . wp_json_encode( $entries )
	);
	foreach ( array( 'toplevel_page_ohmylms', 'ohmylms_page_ohmylms-content', 'ohmylms_page_ohmylms-accounts', 'ohmylms_page_ohmylms-commerce', 'ohmylms_page_ohmylms-engagement', 'ohmylms_page_ohmylms-settings' ) as $hook ) {
		ok( isset( $GLOBALS['_registered_pages'][ $hook ] ) && false !== has_action( $hook, array( AdminNavigation::class, 'render' ) ), 'menu: ' . $hook . ' renders the page' );
		ok( AdminNavigation::is_screen( $hook ) && AppPage::is_app_screen( $hook ), 'menu: ' . $hook . ' is an app screen' );
	}
	ok( ! AdminNavigation::is_screen( 'settings_page_ohmylms-app' ) && ! AdminNavigation::is_screen( 'edit.php' ), 'other screens are not app screens' );
	$top = array_values(
		array_filter(
			$GLOBALS['menu'],
			static function ( $item ) {
				return OHMYLMS_SLUG === $item[2];
			}
		)
	);
	ok( $top && false !== strpos( $top[0][0], 'count-3' ), 'menu: the top-level entry shows the pending orders' );
	$commerce_entry = array_values(
		array_filter(
			$GLOBALS['submenu'][ OHMYLMS_SLUG ],
			static function ( $item ) {
				return 'ohmylms-commerce' === $item[2];
			}
		)
	);
	ok( false !== strpos( $commerce_entry[0][0], 'count-3' ), 'menu: Commerce shows the pending orders' );
	$app_hook = get_plugin_page_hookname( AppPage::SLUG, 'options.php' );
	ok( isset( $GLOBALS['_registered_pages'][ $app_hook ] ) && false !== has_action( 'load-' . $app_hook, array( LegacyRoutes::class, 'redirect_app_page' ) ), 'menu: the retired app page stays registered and redirects' );
	foreach ( array( \OhMyLMS\Assessment\SkillAssessmentPage::SLUG, \OhMyLMS\QuestionBank\ImportPage::SLUG ) as $former ) {
		$former_hook = get_plugin_page_hookname( $former, 'options.php' );
		ok( isset( $GLOBALS['_registered_pages'][ $former_hook ] ) && has_action( 'load-' . $former_hook ), 'menu: ' . $former . ' stays registered to redirect' );
	}
	// Entries an add-on might add: mapped and unmapped legacy routes, the retired app page, the Dashboard,
	// another page, an external link.
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on orders', 'manage_options', admin_url( 'admin.php?page=' . OHMYLMS_SLUG . '#/orders' ) );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on page link', 'manage_options', 'admin.php?page=' . OHMYLMS_SLUG . '#/extensions/unknown-addon-page' );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on app link', 'manage_options', 'admin.php?page=' . AppPage::SLUG . '#/orders/3' );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on home', 'manage_options', OHMYLMS_SLUG );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on page', 'manage_options', 'ohmylms-addon-page' );
	$GLOBALS['submenu'][ OHMYLMS_SLUG ][] = array( 'Add-on docs', 'manage_options', 'https://example.test/docs' );
	$menu_object->map_legacy_submenu();
	ok( submenu_url( 'Add-on orders' ) === address_url( 'page=ohmylms-commerce&tab=orders' ), 'menu: a mapped legacy add-on entry opens its address' );
	ok( null === submenu_url( 'Add-on page link' ), 'menu: an unmapped legacy add-on entry is dropped' );
	ok( submenu_url( 'Add-on app link' ) === address_url( 'page=ohmylms-commerce&tab=orders&id=3' ), 'menu: an add-on entry for the retired app page opens its address' );
	ok( submenu_url( 'Add-on home' ) === OHMYLMS_SLUG && submenu_url( 'Dashboard' ) === OHMYLMS_SLUG, 'menu: the Dashboard slug is kept' );
	ok( submenu_url( 'Add-on page' ) === 'ohmylms-addon-page' && submenu_url( 'Add-on docs' ) === 'https://example.test/docs', 'menu: entries for other pages and external links are kept' );
	foreach ( $GLOBALS['submenu'][ OHMYLMS_SLUG ] as $item ) {
		ok( ! preg_match( '#admin\.php\?page=(?:' . preg_quote( OHMYLMS_SLUG, '#' ) . '|' . AppPage::SLUG . ')(?:[&\#]|$)#', (string) $item[2] ), 'menu: no submenu entry opens a hash route: ' . $item[2] );
	}

	// Tabs: capability, flags, fallback and sections.
	ok( ! isset( AdminNavigation::visible_tabs( 'engagement' )['sessions'] ), 'Sessions is hidden without ohmylms_show_sessions_menu' );
	add_filter( 'ohmylms_show_sessions_menu', '__return_true' );
	AdminNavigation::reset();
	ok( isset( AdminNavigation::visible_tabs( 'engagement' )['sessions'] ), 'Sessions shows with ohmylms_show_sessions_menu' );
	ok(
		'sessions' === AdminNavigation::current(
			array(
				'page' => 'ohmylms-engagement',
				'tab'  => 'sessions',
			)
		)['tab'],
		'the Sessions tab opens when enabled'
	);
	remove_filter( 'ohmylms_show_sessions_menu', '__return_true' );
	AdminNavigation::reset();
	$fallback = AdminNavigation::current(
		array(
			'page' => 'ohmylms-engagement',
			'tab'  => 'sessions',
		)
	);
	ok( 'gamification' === $fallback['tab'] && AdminNavigation::gamification_default_section() === $fallback['section'], 'a hidden tab falls back to the default tab and section' );
	add_filter( 'ohmylms_show_gamification_menu', '__return_false', 99 );
	ok( 'certificates' === AdminNavigation::current( array( 'page' => 'ohmylms-engagement' ) )['section'], 'without the Gamification add-on the tab opens Certificates' );
	remove_filter( 'ohmylms_show_gamification_menu', '__return_false', 99 );
	ok(
		'courses' === AdminNavigation::current(
			array(
				'page' => 'ohmylms-content',
				'tab'  => 'nope',
			)
		)['tab'],
		'an unknown tab falls back to the default'
	);
	ok( 'courses' === AdminNavigation::current( array( 'page' => 'ohmylms-content' ) )['tab'], 'no tab opens the default' );
	ok(
		'general' === AdminNavigation::current(
			array(
				'page'    => 'ohmylms-settings',
				'tab'     => 'settings',
				'section' => 'nope',
			)
		)['section'],
		'an unknown section falls back to the default'
	);
	ok(
		'' === AdminNavigation::current(
			array(
				'page'    => 'ohmylms-content',
				'tab'     => 'curriculum',
				'section' => 'nope',
			)
		)['section'],
		'an unknown section falls back to the tab screen'
	);
	ok(
		'ext-my-page' === AdminNavigation::current(
			array(
				'page' => 'ohmylms-settings',
				'tab'  => 'ext-my-page',
			)
		)['tab'],
		'an extension tab is accepted'
	);
	ok(
		is_callable(
			AdminNavigation::current(
				array(
					'page'    => 'ohmylms-content',
					'tab'     => 'skills',
					'section' => 'assessments',
				)
			)['render']
		),
		'the Assessments section is rendered by the server'
	);
	ok(
		null === AdminNavigation::current(
			array(
				'page' => 'ohmylms-content',
				'tab'  => 'skills',
			)
		)['render'],
		'the Skills tab shows the app'
	);
	if ( apply_filters( 'ohmylms_should_enable_webhooks', false ) ) {
		ok( isset( AdminNavigation::visible_tabs( 'settings' )['webhooks'] ), 'Webhooks shows with ohmylms_should_enable_webhooks' );
	} else {
		ok( ! isset( AdminNavigation::visible_tabs( 'settings' )['webhooks'] ), 'Webhooks is hidden without ohmylms_should_enable_webhooks' );
	}
	$probe_group = static function ( $groups ) {
		$groups['probe'] = array(
			'page'  => 'ohmylms-probe',
			'label' => 'Probe',
			'tabs'  => array(
				'hidden' => array(
					'label'      => 'Hidden',
					'capability' => 'do_not_allow',
				),
			),
		);
		return $groups;
	};
	add_filter( 'ohmylms_admin_navigation', $probe_group );
	$build_menu();
	ok( null === submenu_url( 'Probe' ), 'a page whose tabs the user cannot see gets no submenu' );
	remove_filter( 'ohmylms_admin_navigation', $probe_group );
	$editor = wp_insert_user(
		array(
			'user_login' => $tag . 'ed',
			'user_email' => $tag . 'ed@example.test',
			'user_pass'  => wp_generate_password( 20 ),
			'role'       => 'editor',
		)
	);
	ok( ! is_wp_error( $editor ), 'editor created' );
	$users[] = $editor;
	wp_set_current_user( $editor );
	AdminNavigation::reset();
	ok( ! AdminNavigation::can_open( 'content' ) && array() === AdminNavigation::visible_tabs( 'content' ), 'an editor without manage_options opens no OhMyLMS page' );
	wp_set_current_user( $admins[0]->ID );
	$build_menu();

	// Page markup: heading, tabs, sections and the mount point with its place.
	$content_html = render_page(
		array(
			'page' => 'ohmylms-content',
			'tab'  => 'courses',
			'id'   => '12',
		)
	);
	ok( false !== strpos( $content_html, '<h1 class="wp-heading-inline">Content Hub</h1>' ) && false !== strpos( $content_html, '<hr class="wp-header-end">' ), 'page: heading' );
	ok( false !== strpos( $content_html, 'class="nav-tab-wrapper wp-clearfix" id="' . AdminNavigation::TABS_ID . '"' ), 'page: tab row' );
	ok( 8 === preg_match_all( '/class="nav-tab(?: nav-tab-active)?"/', $content_html ), 'page: one link per Content Hub tab' );
	ok( false !== strpos( $content_html, 'href="' . esc_url( address_url( 'page=ohmylms-content&tab=courses' ) ) . '" class="nav-tab nav-tab-active" aria-current="page">Courses</a>' ), 'page: the current tab is a real, marked link' );
	ok( false !== strpos( $content_html, 'class="subsubsub" id="' . AdminNavigation::SECTIONS_ID . '"' ) && false !== strpos( $content_html, 'data-section="" class="current" aria-current="page">All courses</a>' ), 'page: sections with the current one marked' );
	ok( false !== strpos( $content_html, 'id="' . AdminNavigation::MOUNT_ID . '" class="ohmylms-app" data-page="ohmylms-content" data-tab="courses" data-section=""' ), 'page: the mount point carries the place' );
	ok( false === strpos( $content_html, LegacyRoutes::NOTICE_ID ), 'page: only the Dashboard has the legacy notice' );
	$fallback_html = render_page(
		array(
			'page' => 'ohmylms-commerce',
			'tab'  => 'nope',
		)
	);
	ok( false !== strpos( $fallback_html, 'data-page="ohmylms-commerce" data-tab="earnings"' ) && false !== strpos( $fallback_html, 'aria-current="page">Earnings</a>' ), 'page: an unknown tab shows the default tab' );
	$settings_html = render_page(
		array(
			'page'    => 'ohmylms-settings',
			'tab'     => 'settings',
			'section' => 'tax',
		)
	);
	ok( false !== strpos( $settings_html, 'data-default-section="general"' ) && false !== strpos( $settings_html, 'data-section="tax" class="current" aria-current="page">Tax</a>' ) && false !== strpos( $settings_html, 'data-tab="settings" data-section="tax"' ), 'page: Settings sections' );
	$assessments_html = render_page(
		array(
			'page'    => 'ohmylms-content',
			'tab'     => 'skills',
			'section' => 'assessments',
		)
	);
	ok( false !== strpos( $assessments_html, 'Skill assessments' ) && false === strpos( $assessments_html, AdminNavigation::MOUNT_ID ), 'page: the Assessments section is rendered by the server, without the app' );
	ok( false !== strpos( $assessments_html, 'name="section" value="assessments"' ) && false === strpos( $assessments_html, 'page=' . \OhMyLMS\Assessment\SkillAssessmentPage::SLUG ), 'page: the Assessments forms stay on the section' );
	$dashboard_html = render_page( array( 'page' => OHMYLMS_SLUG ) );
	ok( false !== strpos( $dashboard_html, '<h1 class="wp-heading-inline">Dashboard</h1>' ) && false === strpos( $dashboard_html, 'nav-tab-wrapper' ), 'page: the Dashboard has no tabs' );
	ok( false !== strpos( $dashboard_html, 'class="page-title-action" href="' . esc_url( AdminNavigation::HELP_URL ) . '" target="_blank"' ), 'page: Help & Feedback is a link on the Dashboard' );
	ok( false !== strpos( $dashboard_html, 'id="' . LegacyRoutes::NOTICE_ID . '"' ) && false !== strpos( $dashboard_html, 'hidden' ) && false !== strpos( $dashboard_html, 'data-ohmylms-legacy-route' ), 'page: the Dashboard prints the hidden unavailable notice' );
	ok( array() === LegacyRoutes::unavailable() && false === strpos( $dashboard_html, 'Not available:' ), 'every legacy screen has a new one, so the notice lists none' );
	$probe_screen = static function ( $screens ) {
		$screens['/extensions/unknown-addon-page'] = 'Probe add-on screen';
		return $screens;
	};
	add_filter( 'ohmylms_legacy_unavailable_screens', $probe_screen );
	$probe_html = render_page( array( 'page' => OHMYLMS_SLUG ) );
	remove_filter( 'ohmylms_legacy_unavailable_screens', $probe_screen );
	ok( false !== strpos( $probe_html, 'Not available:' ) && false !== strpos( $probe_html, 'Probe add-on screen' ), 'the notice lists screens added by add-ons' );

	// Plugin action link.
	$links = apply_filters( 'plugin_action_links_' . plugin_basename( OHMYLMS_FILE ), array() );
	ok( 1 === count( $links ) && false !== strpos( $links[0], esc_url( address_url( 'page=ohmylms-settings&tab=settings&section=general' ) ) ), 'plugins screen Settings link opens the new settings' );

	// The retired app page redirects on the server, whatever its hash; other parameters are kept.
	$_GET              = array(
		'page'                        => AppPage::SLUG,
		'xapi_notice'                 => 'saved',
		LegacyRoutes::RETIRED_OPT_OUT => '1',
	);
	$expected_redirect = add_query_arg(
		array(
			'page'                 => OHMYLMS_SLUG,
			'xapi_notice'          => 'saved',
			LegacyRoutes::FROM_APP => '1',
		),
		admin_url( 'admin.php' )
	);
	ok( LegacyRoutes::app_redirect_url() === $expected_redirect, 'the app page redirects to the Dashboard, keeps other parameters and drops the retired opt-out, got ' . LegacyRoutes::app_redirect_url() );
	ok( false === strpos( $expected_redirect, '#' ), 'the redirect has no fragment, so the browser keeps the app route' );
	$_GET[ LegacyRoutes::FROM_LEGACY ] = '1';
	ok( false !== strpos( LegacyRoutes::app_redirect_url(), LegacyRoutes::FROM_LEGACY . '=1' ) && false === strpos( LegacyRoutes::app_redirect_url(), LegacyRoutes::FROM_APP ), 'older unmapped legacy links keep their legacy marker' );
	unset( $_GET[ LegacyRoutes::FROM_LEGACY ] );
	$redirected = '';
	add_filter( 'wp_redirect', $redirect_to );
	try {
		LegacyRoutes::redirect_app_page();
	} catch ( RuntimeException $redirect ) {
		$redirected = $redirect->getMessage();
	}
	remove_filter( 'wp_redirect', $redirect_to );
	ok( 'redirect:' . $expected_redirect === $redirected, 'redirect_app_page() sends the redirect' );
	$_GET = array(
		'page'   => \OhMyLMS\Assessment\SkillAssessmentPage::SLUG,
		'course' => '5',
	);
	add_filter( 'wp_redirect', $redirect_to );
	try {
		\OhMyLMS\Assessment\SkillAssessmentPage::redirect();
	} catch ( RuntimeException $redirect ) {
		$redirected = $redirect->getMessage();
	}
	remove_filter( 'wp_redirect', $redirect_to );
	ok( 'redirect:' . address_url( 'page=ohmylms-content&tab=skills&section=assessments&course=5' ) === $redirected, 'the former skill assessments page redirects to the section with its filters, got ' . $redirected );
	$_GET = array();

	// Hash landing: on the Dashboard, in the head, with the map.
	wp_dequeue_script( LegacyRoutes::HANDLE );
	LegacyRoutes::enqueue_landing( 'ohmylms_page_ohmylms-content' );
	ok( ! wp_script_is( LegacyRoutes::HANDLE, 'enqueued' ), 'the hash landing only loads on the Dashboard' );
	LegacyRoutes::enqueue_landing( 'toplevel_page_' . OHMYLMS_SLUG );
	ok( wp_script_is( LegacyRoutes::HANDLE, 'enqueued' ), 'the hash landing loads on the Dashboard' );
	ok( ! wp_scripts()->get_data( LegacyRoutes::HANDLE, 'group' ), 'the hash landing prints in the head, before the app' );
	$landing = implode( "\n", (array) wp_scripts()->get_data( LegacyRoutes::HANDLE, 'before' ) );
	ok( false !== strpos( $landing, 'window.ohmylmsLegacyRoutes = ' . wp_json_encode( LegacyRoutes::script_data() ) ), 'the landing receives LegacyRoutes::script_data()' );
	ok(
		array(
			'routes'  => LegacyRoutes::routes(),
			'landing' => array(
				'param'    => LegacyRoutes::FROM_LEGACY,
				'appParam' => LegacyRoutes::FROM_APP,
				'home'     => LegacyRoutes::APP_HOME,
				'noticeId' => LegacyRoutes::NOTICE_ID,
			),
		) === LegacyRoutes::script_data(),
		'the landing knows the routes, both markers, the home route and the notice'
	);
	wp_dequeue_script( LegacyRoutes::HANDLE );
	ok( false !== has_action( 'admin_enqueue_scripts', array( LegacyRoutes::class, 'enqueue_landing' ) ), 'the hash landing is hooked' );
	ok( in_array( LegacyRoutes::FROM_LEGACY, wp_removable_query_args(), true ) && in_array( LegacyRoutes::FROM_APP, wp_removable_query_args(), true ), 'the canonical admin address drops both markers' );

	// Hard-coded links that now open the new addresses.
	ok( LegacyRoutes::app_url( '/account-hub' ) === address_url( 'page=ohmylms-accounts&tab=schools' ), 'View as returns to Account Hub' );
	ok( false === strpos( file_get_contents( OHMYLMS_DIR . '/includes/Schools/ViewAs.php' ), "page=' . OHMYLMS_SLUG" ), 'View as no longer links to the legacy page' );
	ok( false === strpos( file_get_contents( OHMYLMS_DIR . '/templates/admin/skill-assessments.php' ), "admin.php?page=ohmylms'" ), 'skill assessments no longer link to the legacy page' );
	$meet = file_get_contents( OHMYLMS_DIR . '/includes/Integrations/GoogleMeet/Includes/Hooks.php' );
	ok( 4 === substr_count( $meet, "LegacyRoutes::app_url( '/integrations?auth=" ), 'the Google Meet OAuth redirects land on the new Integrations tab' );

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
	ok( address_url( 'page=ohmylms-content&tab=assignments&id=' . $assignment . '&view=submissions&submissionId=' . $submission ) === $grading, 'grading url names the submission, got ' . $grading );
	ok( LegacyRoutes::assignment_grading_url( $assignment, $student + 100000 ) === address_url( 'page=ohmylms-content&tab=assignments&id=' . $assignment . '&view=submissions&student=' . ( $student + 100000 ) ), 'grading url without a submission opens the list' );

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
	ok( false === strpos( $mails[0]['message'], 'page=' . OHMYLMS_SLUG . '#' ) && false === strpos( $mails[0]['message'], AppPage::SLUG ), 'single email has no hash link' );
	ok( false !== strpos( $mails[1]['message'], 'href="' . esc_url( address_url( 'page=ohmylms-content&tab=assignments' ) ) . '"' ), 'digest email button opens the assignments list' );

	echo "Legacy routes and navigation: {$checks} checks passed.\n";
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
