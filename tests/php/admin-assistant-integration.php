<?php
/** Disposable WordPress integration: mocked provider HTTP, real MCP dispatch. */
if ( 'cli' !== PHP_SAPI ) { exit; }
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$config_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $config_path || ! is_file( $config_path ) ) { throw new RuntimeException( 'Disposable test credentials required.' ); }
$config = json_decode( file_get_contents( $config_path ), true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) { throw new RuntimeException( 'Disposable database required.' ); }
use OhMyLMS\AI\AssistantSettings;
use OhMyLMS\AI\AssistantTools;
use OhMyLMS\AI\AssistantMcpClient;
use OhMyLMS\AI\CodingJobs;
use OhMyLMS\MCP\Server;
$owner = get_user_by( 'login', $config['username'] );
$saved = array();
foreach ( array( AssistantSettings::OPTION, CodingJobs::OPTION, CodingJobs::JOBS, 'ohmylms_integrations' ) as $option ) { $saved[ $option ] = get_option( $option, null ); }
$checks = 0;
$created = array();
$conversations = array();
$student = 0;
function assistant_check( $condition, $message ) { global $checks; if ( ! $condition ) { throw new RuntimeException( $message ); } ++$checks; }
function assistant_request( $suffix, $data = null, $nonce = true, $worker = '' ) {
    $request = new WP_REST_Request( null === $data ? 'GET' : 'POST', '/ohmylms/v1/admin-assistant' . $suffix );
    if ( $nonce ) { $request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) ); }
    if ( $worker ) { $request->set_header( 'Authorization', 'Bearer ' . $worker ); }
    if ( null !== $data ) { $request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( $data ) ); }
    return rest_do_request( $request );
}
$outputs = array();
$inputs = array();
$mock = static function ( $preempt, $args, $url ) use ( &$outputs, &$inputs ) {
    if ( 'https://api.openai.com/v1/responses' !== $url ) { return $preempt; }
    $inputs[] = json_decode( $args['body'], true );
    $next = array_shift( $outputs );
    if ( 'outage' === $next || null === $next ) { return new WP_Error( 'mock_outage', 'Provider unavailable.' ); }
    return array( 'headers' => array(), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'completed', 'output' => $next ) ) );
};
$text = static function ( $value ) { return array( array( 'type' => 'message', 'role' => 'assistant', 'content' => array( array( 'type' => 'output_text', 'text' => $value ) ) ) ); };
$call = static function ( $name, $arguments ) { return array( array( 'type' => 'function_call', 'call_id' => wp_generate_uuid4(), 'name' => $name, 'arguments' => wp_json_encode( (object) $arguments ) ) ); };
try {
    update_option( CodingJobs::JOBS, array(), false );
    wp_set_current_user( $owner->ID );
    $integrations = (array) $saved['ohmylms_integrations']; $integrations['mcp'] = array( 'is_enable' => 1 );
    $enabled = Server::enabled(); update_option( 'ohmylms_integrations', $integrations );
    if ( ! $enabled ) { \OhMyLMS\MCP\Abilities::init(); Server::boot_adapter(); }
    rest_get_server();
    assistant_check( assistant_request( '', null, false )->get_status() === 403, 'Missing nonce accepted.' );
    $student = wp_insert_user( array( 'user_login' => 'assistant-isolated-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
    wp_set_current_user( $student );
    assistant_check( assistant_request( '' )->get_status() === 403, 'Subscriber admitted.' );
    wp_set_current_user( 0 );
    assistant_check( assistant_request( '', null, false )->get_status() === 403, 'Anonymous admitted.' );
    wp_set_current_user( $owner->ID );
    assistant_check( is_wp_error( AssistantTools::validate( 'save_lesson', array( 'id' => 1, 'name' => 'Bad', 'content' => '' ) ) ), 'Existing lesson edit accepted.' );
    assistant_check( is_wp_error( AssistantTools::validate( 'delete_course', array( 'id' => 1 ) ) ), 'Deletion exposed.' );
    assistant_check( count( AssistantTools::definitions() ) === 14, 'Unexpected assistant tool coverage.' );
    $assistant_tools = AssistantTools::definitions();
    assistant_check( $assistant_tools['create_skill_assessment']['write'] && ! $assistant_tools['list_unit_skills']['write'] && ! $assistant_tools['search_skill_catalogue']['write'], 'Skill assessment tool write flags wrong.' );
    assistant_check( ! is_wp_error( AssistantTools::validate( 'create_skill_assessment', array( 'title' => 'T', 'style' => 'unit-test', 'skills' => array( 1, 2 ), 'per_skill' => 1, 'chapter_id' => 0, 'time_limit' => 0 ) ) ), 'A valid skill assessment proposal was refused.' );
    assistant_check( is_wp_error( AssistantTools::validate( 'create_skill_assessment', array( 'title' => 'T', 'style' => 'unit-test', 'skills' => array( 1 ), 'per_skill' => 1, 'chapter_id' => 0, 'time_limit' => 0, 'publish' => true ) ) ), 'The assistant could ask to publish.' );
    assistant_check( is_wp_error( AssistantTools::validate( 'create_skill_assessment', array( 'title' => 'T', 'style' => 'exam', 'skills' => array( 1 ), 'per_skill' => 1, 'chapter_id' => 0, 'time_limit' => 0 ) ) ), 'An unknown style was accepted.' );
    $status = assistant_request( '' ); assistant_check( 200 === $status->get_status() && ! isset( $status->get_data()['settings']['key'] ), 'Unsafe configuration response.' );
    $settings = AssistantSettings::save( array( 'enabled' => true, 'model' => 'test-model', 'key' => 'isolated-test-key-123456789' ) );
    assistant_check( ! is_wp_error( $settings ) && $settings['configured'] && ! str_contains( wp_json_encode( $settings ), 'isolated-test-key' ), 'Key exposure or settings failure.' );
    $read = AssistantMcpClient::call( 'list_syllabuses', array() );
    assistant_check( ! is_wp_error( $read ) && empty( $read['isError'] ), 'Actual MCP read failed: ' . wp_json_encode( $read ) );
    $forged = new WP_REST_Request( 'POST', Server::ROUTE ); $forged->set_header( 'X-OhMyLMS-Local', '1' );
    assistant_check( is_wp_error( Server::authorize( $forged ) ), 'Forged local request bypassed MCP authentication.' );
    add_filter( 'pre_http_request', $mock, PHP_INT_MAX, 3 );
    $outputs = array( $call( 'list_syllabuses', array() ), $text( 'Verified syllabus data.' ) );
    $result = assistant_request( '/message', array( 'message' => 'What syllabuses exist?' ) )->get_data();
    $conversations[] = $result['conversation_id'];
    assistant_check( empty( $result['error'] ) && 'Verified syllabus data.' === end( $result['messages'] )['text'], 'Read conversation failed.' );
    assistant_check( 'function_call_output' === end( $inputs[1]['input'] )['type'] && false === $inputs[1]['store'], 'Tool continuity/store policy failed.' );
    $lesson_name = 'Assistant isolated draft ' . wp_generate_uuid4();
    $outputs = array( $call( 'save_lesson', array( 'name' => $lesson_name, 'content' => '<!-- wp:paragraph --><p>Isolated draft.</p><!-- /wp:paragraph -->' ) ) );
    $result = assistant_request( '/message', array( 'message' => 'Create a draft lesson.' ) )->get_data();
    $conversations[] = $result['conversation_id'];
    assistant_check( ! empty( $result['pending'] ) && ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s", $lesson_name ) ), 'Write executed without review.' );
    $decision = array( 'conversation_id' => $result['conversation_id'], 'action_id' => $result['pending']['call_id'], 'decision' => 'approve' );
    $outputs = array( 'outage' );
    $approved = assistant_request( '/message', $decision )->get_data();
    $lesson_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s", $lesson_name ) );
    if ( $lesson_id ) { $created[] = $lesson_id; }
    assistant_check( $lesson_id > 0 && 'draft' === get_post_status( $lesson_id ) && ! $approved['pending'] && ! empty( $approved['receipts'][0]['success'] ) && $approved['error'], 'Approved write/outage receipt failed: ' . wp_json_encode( $approved ) );
    $again = assistant_request( '/message', $decision )->get_data();
    assistant_check( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_title = %s", $lesson_name ) ) === '1' && ! $again['pending'], 'Write replayed.' );
    wp_set_current_user( $student );
    assistant_check( assistant_request( '/message', array( 'conversation_id' => $result['conversation_id'], 'message' => 'Read history.' ) )->get_status() === 403, 'Conversation accessible to learner.' );
    ( new WP_User( $student ) )->set_role( 'administrator' );
    wp_set_current_user( 0 ); wp_set_current_user( $student );
    assistant_check( assistant_request( '/message', array( 'conversation_id' => $result['conversation_id'], 'message' => 'Read another administrator history.' ) )->get_status() === 410, 'Conversation ownership boundary failed.' );
    wp_set_current_user( $owner->ID );
    assistant_check( assistant_request( '/coding/pair', array() )->get_status() === 404, 'Development endpoint remains active.' );
    assistant_check( ! isset( assistant_request( '' )->get_data()['coding'] ), 'Development metadata remains visible.' );
    $file_id = wp_generate_uuid4();
    $image_id = wp_generate_uuid4();
    foreach ( array( $file_id => array( 'mime' => 'application/pdf', 'name' => 'reference.pdf' ), $image_id => array( 'mime' => 'image/png', 'name' => 'diagram.png' ) ) as $handle => $metadata ) {
        set_transient( 'oml_file_' . $owner->ID . '_' . $handle, array_merge( $metadata, array( 'id' => $handle, 'file_id' => 'file-isolated-' . $handle, 'bytes' => 128, 'expires' => time() + 3600 ) ), 3600 );
    }
    assistant_check( is_wp_error( \OhMyLMS\AI\AssistantAttachments::validate( array( 'error' => 0, 'name' => 'wp-config.php', 'tmp_name' => ABSPATH . 'wp-config.php' ) ) ), 'Local-path read accepted as an upload.' );
    assistant_check( is_wp_error( \OhMyLMS\AI\AssistantAttachments::resolve( array_fill( 0, 4, $file_id ) ) ), 'Attachment limit bypassed.' );
    wp_set_current_user( $student );
    assistant_check( is_wp_error( \OhMyLMS\AI\AssistantAttachments::resolve( array( $file_id ) ) ), 'Cross-administrator attachment exposed.' );
    wp_set_current_user( $owner->ID );
    $outputs = array( $text( 'Reviewed the attachments.' ) );
    $attached = assistant_request( '/message', array( 'message' => '', 'attachments' => array( $file_id, $image_id ) ) )->get_data();
    $conversations[] = $attached['conversation_id'];
    $latest_input = end( $inputs );
    assistant_check( array( 'input_text', 'input_file', 'input_image' ) === array_column( $latest_input['input'][0]['content'], 'type' ), 'Provider attachment content missing.' );
    assistant_check( count( $attached['messages'][0]['attachments'] ) === 2 && ! str_contains( wp_json_encode( $attached ), 'file-isolated-' ), 'Unsafe or missing attachment metadata.' );
    foreach ( array( $file_id, $image_id ) as $handle ) { delete_transient( 'oml_file_' . $owner->ID . '_' . $handle ); }
    echo $checks . " assistant integration checks passed; provider HTTP mocked, MCP real, attachment references validated.\n";
} finally {
    remove_filter( 'pre_http_request', $mock, PHP_INT_MAX );
    wp_set_current_user( $owner->ID );
    foreach ( $created as $post_id ) { wp_delete_post( $post_id, true ); }
    foreach ( $conversations as $id ) { delete_transient( 'oml_chat_' . $owner->ID . '_' . $id ); }
    delete_transient( 'oml_chat_rate_' . $owner->ID );
    if ( $student && ! is_wp_error( $student ) ) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $student ); }
    foreach ( $saved as $option => $value ) { null === $value ? delete_option( $option ) : update_option( $option, $value, false ); }
}
