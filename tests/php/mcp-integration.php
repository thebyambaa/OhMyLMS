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
$checks = 0;
// Prepare the plugin schema in this disposable database, which may predate the rename.
\OhMyLMS\Install::create_tables();
function mcp_check( $condition, $message ) {
    global $checks;
    if ( ! $condition ) throw new RuntimeException( $message );
    $checks++;
}
function mcp_request( $message, $token = '', $method = 'POST', $extra_headers = array() ) {
    global $mcp_sessions, $mcp_created_sessions;
    $decoded = is_string( $message ) ? json_decode( $message, true ) : $message;
    $initializing = ( $decoded['method'] ?? '' ) === 'initialize';
    if ( $token && ! $initializing && ! isset( $mcp_sessions[$token] ) ) {
        $initialized = mcp_request( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-11-25', 'capabilities' => (object) array(), 'clientInfo' => array( 'name' => 'test', 'version' => '1' ) ) ), $token );
        if ( $initialized->get_status() !== 200 ) return $initialized;
    }
    if ( ! $initializing && isset( $mcp_sessions[$token] ) ) {
        $extra_headers = array_merge( array( 'mcp-session-id' => $mcp_sessions[$token], 'mcp-protocol-version' => '2025-11-25' ), $extra_headers );
    }
    wp_set_current_user( 0 );
    $request = new WP_REST_Request( $method, Server::ROUTE );
    $request->set_header( 'content-type', 'application/json' );
    $request->set_header( 'accept', 'application/json, text/event-stream' );
    if ( $token ) $request->set_header( 'authorization', 'Bearer ' . $token );
    foreach ( $extra_headers as $key => $value ) $request->set_header( $key, $value );
    $request->set_body( is_string( $message ) ? $message : wp_json_encode( $message ) );
    $http_url = getenv( 'OHMYLMS_MCP_TEST_URL' );
    if ( $http_url ) {
        if ( ! preg_match( '#^http://127\.0\.0\.1:[0-9]+/#', $http_url ) ) throw new RuntimeException( 'HTTP tests require loopback.' );
        $headers = array( 'Content-Type: application/json', 'Accept: application/json, text/event-stream' );
        if ( $token ) $headers[] = 'Authorization: Bearer ' . $token;
        foreach ( $extra_headers as $key => $value ) $headers[] = $key . ': ' . $value;
        $context = stream_context_create( array( 'http' => array( 'method' => $method, 'header' => implode( "\r\n", $headers ), 'content' => $request->get_body(), 'ignore_errors' => true, 'timeout' => 10 ) ) );
        $body = file_get_contents( $http_url, false, $context );
        if ( $body === false ) throw new RuntimeException( 'HTTP connection failed.' );
        preg_match( '#HTTP/\S+ ([0-9]+)#', $http_response_header[0], $status );
        $response = new WP_REST_Response( $body === '' ? null : json_decode( $body, true ), (int) $status[1] );
        if ( $body !== '' && json_last_error() !== JSON_ERROR_NONE ) throw new RuntimeException( 'HTTP response was not JSON.' );
        foreach ( $http_response_header as $header ) {
            if ( stripos( $header, 'Mcp-Session-Id:' ) === 0 ) $response->header( 'Mcp-Session-Id', trim( substr( $header, 15 ) ) );
        }
    } else {
        $response = rest_do_request( $request );
        $response = apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
    }
    if ( $initializing && $response->get_status() === 200 ) {
        $headers = $response->get_headers();
        if ( isset( $headers['Mcp-Session-Id'] ) ) {
            $mcp_sessions[$token] = $headers['Mcp-Session-Id'];
            $mcp_created_sessions[] = $headers['Mcp-Session-Id'];
        }
    }
    $response->set_data( json_decode( wp_json_encode( $response->get_data() ), true ) );
    return $response;
}
function mcp_call( $name, $args, $token ) {
    return mcp_request( array( 'jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call', 'params' => array( 'name' => $name, 'arguments' => (object) $args ) ), $token );
}
$saved = get_option( Server::OPTION, null );
$saved_integrations = get_option( 'ohmylms_integrations', null );
$mcp_was_enabled = Server::enabled();
$owner = get_user_by( 'login', $config['username'] );
$token = 'oml_mcp_' . bin2hex( random_bytes( 32 ) );
$record = array( 'hash' => hash( 'sha256', $token ), 'user_id' => $owner->ID, 'created' => gmdate( 'c' ) );
$fixture = 0;
$other_fixtures = array();
$mcp_sessions = array();
$mcp_created_sessions = array();
// CLI has no TLS. Mark only these requests as HTTPS without changing site configuration.
$saved_https = $_SERVER['HTTPS'] ?? null;
$_SERVER['HTTPS'] = 'on';
try {
    $enabled_integrations = (array) $saved_integrations;
    $enabled_integrations['mcp'] = array( 'is_enable' => 1 );
    update_option( 'ohmylms_integrations', $enabled_integrations );
    if ( ! $mcp_was_enabled ) {
        \OhMyLMS\MCP\Abilities::init();
        Server::boot_adapter();
    }
    update_option( Server::OPTION, array( 'openai' => $record ), false );
    rest_get_server();
    mcp_check( Server::readiness()['ready'], 'Abilities API/adapter unavailable.' );
    $adapter_server = \WP\MCP\Core\McpAdapter::instance()->get_server( 'ohmylms' );
    mcp_check( $adapter_server !== null && count( $adapter_server->get_tools() ) === count( Server::tools() ), 'Official adapter server not registered with every tool.' );
    foreach ( \OhMyLMS\MCP\Abilities::names() as $ability_name ) {
        $ability = wp_get_ability( $ability_name );
        mcp_check( $ability instanceof WP_Ability && $ability->get_category() === 'ohmylms', 'Native ability missing.' );
    }
    wp_set_current_user( 0 );
    mcp_check( is_wp_error( wp_get_ability( 'ohmylms/list-courses' )->execute( array() ) ), 'Anonymous native ability execution accepted.' );
    wp_set_current_user( $owner->ID );
    $native = wp_get_ability( 'ohmylms/list-courses' )->execute( array( 'per_page' => 1 ) );
    mcp_check( ! is_wp_error( $native ) && isset( $native['data'] ), 'Native ability execution failed.' );
    $list = array( 'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list' );
    mcp_check( mcp_request( $list )->get_status() === 401, 'Anonymous access accepted.' );
    mcp_check( mcp_request( $list, 'oml_mcp_' . str_repeat( '0', 64 ) )->get_status() === 401, 'Invalid token accepted.' );
    mcp_check( mcp_request( $list, $token, 'POST', array( 'origin' => 'https://evil.example' ) )->get_status() >= 400, 'Foreign origin accepted.' );
    $init = mcp_request( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => array( 'protocolVersion' => '2025-11-25', 'capabilities' => (object) array(), 'clientInfo' => array( 'name' => 'test', 'version' => '1' ) ) ), $token );
    mcp_check( $init->get_status() === 200 && $init->get_data()['result']['protocolVersion'] === '2025-11-25', 'Initialize failed.' );
    $tools = mcp_request( $list, $token )->get_data()['result']['tools'];
    mcp_check( count( $tools ) === count( Server::tools() ) && in_array( 'save_lesson_alignment_review', array_column( $tools, 'name' ), true ) && in_array( 'get_student_placement', array_column( $tools, 'name' ), true ), 'Tool catalog incorrect: ' . count( $tools ) );
    foreach ( $tools as $tool ) {
        $definition = Server::tools()[ $tool['name'] ];
        mcp_check( $tool['annotations']['readOnlyHint'] === ( $definition['readonly'] ?? true ) && $tool['inputSchema']['additionalProperties'] === false, 'Unsafe tool schema.' );
    }
    $notice = mcp_request( array( 'jsonrpc' => '2.0', 'method' => 'notifications/initialized' ), $token );
    mcp_check( $notice->get_status() === 202 && $notice->get_data() === null, 'Notification response incorrect.' );
    $r = new WP_REST_Request( 'POST', Server::ROUTE );
    mcp_check( Server::serve_empty( false, $notice, $r, null ) === true, 'Notification body would be serialized.' );
    mcp_check( mcp_request( $list, $token, 'GET' )->get_status() === 405, 'GET should reject SSE.' );
    mcp_check( isset( $mcp_sessions[$token] ), 'Adapter initialize did not issue a session.' );
    mcp_check( mcp_request( '{bad', $token )->get_status() === 400, 'Malformed JSON not rejected.' );
    mcp_check( mcp_request( $list, $token, 'POST', array( 'mcp-session-id' => 'invalid-session' ) )->get_status() >= 400, 'Invalid adapter session accepted.' );
    mcp_check( mcp_request( $list, $token, 'POST', array( 'mcp-protocol-version' => 'bogus' ) )->get_status() === 400, 'Unsupported version header accepted.' );
    foreach ( array( array( 'per_page' => 0 ), array( 'per_page' => 21 ), array( 'per_page' => '5' ), array( 'page' => -1 ), array( 'filter' => array() ), array( 'search' => str_repeat( 'a', 201 ) ) ) as $args ) {
        $invalid = mcp_call( 'list_courses', $args, $token )->get_data();
        mcp_check( ! empty( $invalid['result']['isError'] ) || isset( $invalid['error'] ), 'Invalid list arguments accepted.' );
    }
    $missing_id = mcp_call( 'get_course', array(), $token )->get_data();
    mcp_check( ! empty( $missing_id['result']['isError'] ) || isset( $missing_id['error'] ), 'Missing ID accepted.' );
    mcp_check( isset( mcp_call( 'delete_course', array( 'id' => 1 ), $token )->get_data()['error'] ), 'Unregistered tool accepted.' );
    wp_set_current_user( $owner->ID );
    $fixture = wp_insert_post( array( 'post_type' => OHMYLMS_COURSE_CPT, 'post_title' => 'MCP isolated course fixture', 'post_status' => 'draft' ), true );
    mcp_check( ! is_wp_error( $fixture ) && $fixture > 0, 'Fixture creation failed.' );
    foreach ( array( 'list_courses', 'list_lessons', 'list_quizzes', 'list_questions' ) as $name ) {
        $response = mcp_call( $name, array( 'per_page' => 1 ), $token )->get_data();
        mcp_check( isset( $response['result'] ) && ! $response['result']['isError'], $name . ' dispatch failed: ' . wp_json_encode( $response ) );
    }
    $response = mcp_call( 'get_course', array( 'id' => $fixture ), $token )->get_data();
    mcp_check( ! $response['result']['isError'] && strpos( $response['result']['content'][0]['text'], 'MCP isolated course fixture' ) !== false, 'Course read failed.' );
    $chapters = mcp_call( 'get_course_chapters', array( 'id' => $fixture ), $token )->get_data();
    mcp_check( ! $chapters['result']['isError'], 'Chapter read failed.' );
    $missing = mcp_call( 'get_course', array( 'id' => 2147483647 ), $token )->get_data();
    mcp_check( $missing['result']['isError'], 'REST error not mapped to MCP tool error.' );
    foreach ( array( 'lesson' => 'lessons', 'quiz' => 'quiz', 'question' => 'question' ) as $kind => $route ) {
        wp_set_current_user( $owner->ID );
        $create = new WP_REST_Request( 'POST', '/ohmylms/v1/' . $route );
        $create->set_header( 'content-type', 'application/json' );
        $create->set_body( wp_json_encode( array( 'name' => 'MCP isolated ' . $kind, 'status' => 'draft' ) ) );
        $created = rest_do_request( $create );
        mcp_check( $created->get_status() === 201, $kind . ' fixture creation failed.' );
        $other_fixtures[] = (int) $created->get_data()['id'];
        $read = mcp_call( 'get_' . $kind, array( 'id' => end( $other_fixtures ) ), $token )->get_data();
        mcp_check( ! $read['result']['isError'], $kind . ' read failed.' );
    }
    // Exercise the administrator UI's actual credential lifecycle without displaying secrets.
    wp_set_current_user( $owner->ID );
    $generate_request = new WP_REST_Request( 'POST', '/ohmylms/v1/mcp/settings/gemini' );
    $generate_request->set_body_params( array( 'action' => 'generate' ) );
    $generated_reply = rest_do_request( $generate_request );
    $generated_token = $generated_reply->get_data()['token'] ?? '';
    mcp_check( $generated_reply->get_status() === 200 && preg_match( '/^oml_mcp_[a-f0-9]{64}$/', $generated_token ), 'Generated token was not returned.' );
    $stored = get_option( Server::OPTION );
    mcp_check( $stored['gemini']['hash'] === hash( 'sha256', $generated_token ) && strpos( wp_json_encode( $stored ), $generated_token ) === false, 'Credential is not stored as hash only.' );
    mcp_check( mcp_request( $list, $generated_token )->get_status() === 200, 'Settings-generated token cannot connect.' );
    wp_set_current_user( $owner->ID );
    $generate_request->set_body_params( array( 'action' => 'revoke' ) );
    rest_do_request( $generate_request );
    mcp_check( mcp_request( $list, $generated_token )->get_status() === 401, 'Settings revocation failed.' );
    mcp_check( mcp_request( $list, $token, 'DELETE' )->get_status() === 200, 'Adapter session termination failed.' );
    unset( $mcp_sessions[$token] );
    update_option( Server::OPTION, array(), false );
    mcp_check( mcp_request( $list, $token )->get_status() === 401, 'Revoked token accepted.' );
    wp_set_current_user( 0 );
    $settings_request = new WP_REST_Request( 'GET', '/ohmylms/v1/mcp/settings' );
    mcp_check( rest_do_request( $settings_request )->get_status() === 401, 'Anonymous settings access accepted.' );
    wp_set_current_user( $owner->ID );
    $manage_request = new WP_REST_Request( 'POST', '/ohmylms/v1/mcp/settings/anthropic' );
    $manage_request->set_body_params( array( 'action' => 'generate' ) );
    $managed = rest_do_request( $manage_request );
    mcp_check( $managed->get_status() === 200 && isset( $managed->get_data()['token'] ), 'LMS settings cannot generate token.' );
    $managed_token = $managed->get_data()['token'];
    $metadata = rest_do_request( $settings_request )->get_data();
    mcp_check( ! isset( $metadata['token'] ) && strpos( wp_json_encode( $metadata ), 'hash' ) === false, 'Settings metadata leaked credentials.' );
    $manage_request->set_body_params( array( 'action' => 'revoke' ) );
    rest_do_request( $manage_request );
    mcp_check( mcp_request( $list, $managed_token )->get_status() === 401, 'LMS settings cannot revoke token.' );
    wp_set_current_user( $owner->ID );
    $manage_request->set_body_params( array( 'action' => 'unsafe' ) );
    mcp_check( rest_do_request( $manage_request )->get_status() === 400, 'Invalid management action accepted.' );
    $subscriber = get_users( array( 'role' => 'subscriber', 'number' => 1 ) );
    if ( $subscriber ) {
        $record['user_id'] = $subscriber[0]->ID;
        update_option( Server::OPTION, array( 'openai' => $record ), false );
        mcp_check( mcp_request( $list, $token )->get_status() === 401, 'Non-admin token owner accepted.' );
        wp_set_current_user( $subscriber[0]->ID );
        mcp_check( is_wp_error( wp_get_ability( 'ohmylms/list-courses' )->execute( array() ) ), 'Subscriber can execute native LMS abilities.' );
    }
    echo $checks . " MCP integration checks passed.\n";
} finally {
    wp_set_current_user( $owner->ID );
    if ( is_int( $fixture ) && $fixture > 0 ) wp_delete_post( $fixture, true );
    foreach ( $other_fixtures as $post_id ) wp_delete_post( $post_id, true );
    foreach ( $mcp_created_sessions as $session_id ) \WP\MCP\Transport\Infrastructure\SessionManager::delete_session( (int) $owner->ID, $session_id );
    if ( $saved === null ) delete_option( Server::OPTION ); else update_option( Server::OPTION, $saved, false );
    if ( $saved_integrations === null ) delete_option( 'ohmylms_integrations' ); else update_option( 'ohmylms_integrations', $saved_integrations );
    if ( $saved_https === null ) unset( $_SERVER['HTTPS'] ); else $_SERVER['HTTPS'] = $saved_https;
}
