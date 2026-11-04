<?php
/** Run only against the disposable source-test site. No external API calls. */
if ( PHP_SAPI !== 'cli' ) exit;
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$config_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $config_path || ! is_file( $config_path ) ) throw new RuntimeException( 'Set OHMYLMS_TEST_CREDENTIALS to the disposable site JSON.' );
$config = json_decode( file_get_contents( $config_path ), true );
require $config['site'] . '/wp-load.php';
if ( ( ! defined( 'OHMYLMS_TEST_SITE' ) && ! defined( 'OMLMS_TEST_SITE' ) ) || DB_NAME !== 'ohmylms_source_test' ) throw new RuntimeException( 'Requires disposable test site.' );
use OhMyLMS\MCP\Server;
use OhMyLMS\Student\SiteSetup;
require_once ABSPATH . 'wp-admin/includes/user.php';
$checks  = 0;
$cleanup = array();
$cleanup_users = array();
function setup_check( $condition, $message ) {
    global $checks;
    if ( ! $condition ) throw new RuntimeException( $message );
    $checks++;
}
function setup_call( $method, $route, $params = array() ) {
    $request = new WP_REST_Request( $method, '/ohmylms/v1/site-setup/' . $route );
    $request->set_header( 'content-type', 'application/json' );
    if ( 'GET' !== $method ) $request->set_body( wp_json_encode( $params ) );
    return rest_do_request( $request );
}
try {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
    setup_check( $admins, 'The test site has no administrator.' );
    wp_set_current_user( $admins[0]->ID );

    $tools = Server::tools();
    foreach ( array( 'list_navigation_menus', 'setup_subjects_page', 'setup_explore_menu' ) as $name ) {
        setup_check( isset( $tools[ $name ] ) && in_array( \OhMyLMS\MCP\Abilities::name( $name ), \OhMyLMS\MCP\Abilities::names(), true ), $name . ' is not registered as a tool.' );
    }
    setup_check( ! $tools['setup_explore_menu']['readonly'] && $tools['list_navigation_menus']['readonly'], 'Write and read annotations are wrong.' );
    setup_check( in_array( 'menu_id', $tools['setup_explore_menu']['required'], true ), 'menu_id must be required.' );
    setup_check( \WP_Block_Type_Registry::get_instance()->is_registered( 'ohmylms/explore-menu' ) && \WP_Block_Type_Registry::get_instance()->is_registered( 'ohmylms/browse-subjects' ), 'The blocks are not registered; build the assets first.' );

    // Navigation menu: preview, apply, repeat.
    $original = "<!-- wp:page-list /-->";
    $menu_id  = wp_insert_post( array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => 'Setup test menu', 'post_content' => $original ), true );
    setup_check( ! is_wp_error( $menu_id ), 'Could not create the test menu.' );
    $cleanup[] = $menu_id;
    $menus = setup_call( 'GET', 'navigation-menus' )->get_data();
    $mine  = array_values( array_filter( $menus, static function ( $menu ) use ( $menu_id ) { return $menu['id'] === $menu_id; } ) );
    setup_check( $mine && ! $mine[0]['has_explore'] && 'Setup test menu' === $mine[0]['title'], 'The menu list is wrong.' );

    $preview = setup_call( 'POST', 'explore-menu', array( 'menu_id' => $menu_id ) );
    setup_check( 200 === $preview->get_status() && 'preview' === $preview->get_data()['status'] && false === $preview->get_data()['changed'], 'Without confirm the menu must only be previewed.' );
    setup_check( $original === get_post( $menu_id )->post_content, 'A preview changed the menu.' );

    $applied = setup_call( 'POST', 'explore-menu', array( 'menu_id' => $menu_id, 'label' => 'Browse', 'confirm' => true ) );
    $content = get_post( $menu_id )->post_content;
    setup_check( 'updated' === $applied->get_data()['status'] && true === $applied->get_data()['changed'], 'Confirm did not update the menu.' );
    setup_check( 0 === strpos( $content, '<!-- wp:ohmylms/explore-menu {"label":"Browse"} /-->' ) && false !== strpos( $content, $original ), 'The Explore item must come first and keep the existing items.' );
    $again = setup_call( 'POST', 'explore-menu', array( 'menu_id' => $menu_id, 'confirm' => true ) );
    setup_check( 'exists' === $again->get_data()['status'] && $content === get_post( $menu_id )->post_content, 'A repeated call must change nothing.' );

    $not_menu = setup_call( 'POST', 'explore-menu', array( 'menu_id' => $admins[0]->ID ) );
    setup_check( 404 === $not_menu->get_status(), 'Only navigation menus may be changed.' );
    setup_check( 400 === setup_call( 'POST', 'explore-menu', array( 'menu_id' => 0 ) )->get_status(), 'menu_id must be positive.' );
    setup_check( 400 === setup_call( 'POST', 'explore-menu', array( 'menu_id' => $menu_id, 'label' => str_repeat( 'a', 41 ) ) )->get_status(), 'A long label must be refused.' );

    // The page: preview, create, repeat.
    $before = count( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 's' => 'ohmylms/browse-subjects' ) ) );
    $existing = SiteSetup::subjects_page( '', 'panel', false );
    if ( 'exists' === $existing['status'] ) {
        setup_check( false === $existing['changed'] && $existing['page_id'] > 0, 'An existing page must be reported unchanged.' );
    } else {
        $page_preview = setup_call( 'POST', 'subjects-page', array() );
        setup_check( 'preview' === $page_preview->get_data()['status'], 'Without confirm the page must only be previewed.' );
        setup_check( $before === count( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'posts_per_page' => -1, 's' => 'ohmylms/browse-subjects' ) ) ), 'A preview created a page.' );
        $created = setup_call( 'POST', 'subjects-page', array( 'title' => 'Setup test subjects', 'display' => 'dropdown', 'confirm' => true ) )->get_data();
        $cleanup[] = $created['page_id'];
        $page = get_post( $created['page_id'] );
        setup_check( 'created' === $created['status'] && 'publish' === $page->post_status && has_block( 'ohmylms/browse-subjects', $page ) && false !== strpos( $page->post_content, '"display":"dropdown"' ), 'The page was not created correctly.' );
        $repeat = setup_call( 'POST', 'subjects-page', array( 'confirm' => true ) )->get_data();
        setup_check( 'exists' === $repeat['status'] && $created['page_id'] === $repeat['page_id'], 'A repeated call must reuse the page.' );
    }

    // Everyone else is refused.
    $subscriber = wp_insert_user( array( 'user_login' => 'setup_test_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
    setup_check( ! is_wp_error( $subscriber ), 'Could not create a subscriber.' );
    $cleanup_users[] = $subscriber;
    wp_set_current_user( $subscriber );
    foreach ( array( array( 'GET', 'navigation-menus' ), array( 'POST', 'subjects-page' ), array( 'POST', 'explore-menu' ) ) as $call ) {
        $status = setup_call( $call[0], $call[1], array( 'menu_id' => $menu_id, 'confirm' => true ) )->get_status();
        setup_check( in_array( $status, array( 401, 403 ), true ), $call[1] . ' must be refused to non-administrators.' );
    }
    setup_check( $content === get_post( $menu_id )->post_content, 'The refused calls must not have touched the menu.' );
    echo 'Site setup checks passed: ' . $checks . "\n";
} finally {
    foreach ( $cleanup as $post_id ) wp_delete_post( $post_id, true );
    foreach ( $cleanup_users as $user_id ) wp_delete_user( $user_id );
}
