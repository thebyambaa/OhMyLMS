<?php
/**
 * Native lesson REST saves using connection-local post tables.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$test_site_path = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( ! $test_site_path || ! is_file( $test_site_path . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress checkout.' );
}
require $test_site_path . '/wp-load.php';

use OhMyLMS\Learning\NativeLessonEditor;

/**
 * Assert one integration condition.
 *
 * @param bool   $condition Condition.
 * @param string $message Failure message.
 */
function native_lesson_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	++$GLOBALS['native_lesson_checks'];
}

/**
 * Dispatch a native WordPress REST request.
 *
 * @param string $method HTTP method.
 * @param string $route REST route.
 * @param array  $data Request parameters.
 * @return WP_REST_Response Response.
 */
function native_lesson_request( $method, $route, $data = array() ) {
	$request = new WP_REST_Request( $method, $route );
	$request->set_body_params( $data );
	return rest_do_request( $request );
}

global $wpdb;
$GLOBALS['native_lesson_checks'] = 0;
$original_posts                 = $wpdb->posts;
$original_postmeta              = $wpdb->postmeta;
$test_prefix                    = 'native_lesson_' . bin2hex( random_bytes( 6 ) ) . '_';
$administrators                 = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
if ( ! $administrators ) {
	throw new RuntimeException( 'An administrator is required for the integration test.' );
}
foreach ( array( 'posts' => $original_posts, 'postmeta' => $original_postmeta ) as $table => $original_table ) {
	$temporary_table = $test_prefix . $table;
	// Identifiers are fixed suffixes plus a locally generated hexadecimal prefix.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No external identifiers or values.
	native_lesson_check( false !== $wpdb->query( "CREATE TEMPORARY TABLE `{$temporary_table}` LIKE `{$original_table}`" ), 'Temporary table creation failed.' );
	$wpdb->$table = $temporary_table;
}
wp_set_current_user( $administrators[0]->ID );

try {
	$post_type = get_post_type_object( 'ohmylms-lesson' );
	native_lesson_check( $post_type->show_in_rest && $post_type->show_ui && use_block_editor_for_post_type( 'ohmylms-lesson' ), 'Native editor is not enabled.' );
	$created = native_lesson_request( 'POST', '/wp/v2/ohmylms-lessons', array( 'title' => 'Native lesson fixture', 'status' => 'draft', 'content' => '<!-- wp:paragraph --><p>Original.</p><!-- /wp:paragraph -->' ) );
	native_lesson_check( 201 === $created->get_status(), 'Native creation failed: ' . wp_json_encode( $created->get_data() ) );
	$lesson_id = $created->get_data()['id'];
	native_lesson_check( 'text' === get_post_meta( $lesson_id, '_type', true ), 'Native creation did not initialize the lesson type.' );
	update_post_meta( $lesson_id, '_content', array( 'extension' => 'keep' ) );
	update_post_meta( $lesson_id, '_prerequisites', array( 'enable' => true, 'data' => array( array( 'label' => 'Existing requirement', 'value' => 987654 ) ) ) );
	$blocks = '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Teacher\'s button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
	$saved  = native_lesson_request( 'POST', '/wp/v2/ohmylms-lessons/' . $lesson_id, array( 'content' => $blocks, 'ohmylms_lesson_settings' => array( 'preview_enable' => true, 'drip_settings' => array( 'enable' => true, 'type' => 'enrollment-from-x-days', 'days' => 3 ), 'download_resource' => array( 'file' => array( array( 'id' => 1, 'name' => '<b>Notes</b>', 'url' => 'javascript:alert(1)' ) ) ) ) ) );
	native_lesson_check( 200 === $saved->get_status(), 'Native save failed: ' . wp_json_encode( $saved->get_data() ) );
	native_lesson_check( $blocks === get_post( $lesson_id )->post_content, 'Native save changed serialized block content.' );
	native_lesson_check( true === (bool) get_post_meta( $lesson_id, '_preview_enable', true ), 'Preview setting did not persist.' );
	native_lesson_check( 3 === get_post_meta( $lesson_id, '_drip_settings', true )['days'], 'Drip settings did not persist.' );
	native_lesson_check( 'Notes' === get_post_meta( $lesson_id, '_download_resource', true )['file'][0]['name'] && '' === get_post_meta( $lesson_id, '_download_resource', true )['file'][0]['url'], 'Resource text or URLs were not sanitized.' );
	native_lesson_check( 987654 === get_post_meta( $lesson_id, '_prerequisites', true )['data'][0]['value'], 'An unsubmitted prerequisite was lost.' );
	native_lesson_check( 'keep' === get_post_meta( $lesson_id, '_content', true )['extension'], 'An extension field was lost.' );
	$legacy = native_lesson_request( 'GET', '/ohmylms/v1/lessons/' . $lesson_id );
	native_lesson_check( $blocks === $legacy->get_data()['description'] && true === (bool) $legacy->get_data()['preview_enable'] && ! empty( $legacy->get_data()['native_editor_url'] ), 'Legacy lesson API no longer agrees with core.' );
	$invalid = native_lesson_request( 'POST', '/wp/v2/ohmylms-lessons/' . $lesson_id, array( 'ohmylms_lesson_settings' => array( 'drip_settings' => array( 'days' => -1 ) ) ) );
	native_lesson_check( 400 === $invalid->get_status(), 'Invalid settings were accepted.' );
	$revisions = native_lesson_request( 'GET', '/wp/v2/ohmylms-lessons/' . $lesson_id . '/revisions' );
	native_lesson_check( 200 === $revisions->get_status() && count( $revisions->get_data() ) > 0, 'Native revisions are unavailable.' );
	$published = native_lesson_request( 'POST', '/wp/v2/ohmylms-lessons/' . $lesson_id, array( 'status' => 'publish' ) );
	native_lesson_check( 200 === $published->get_status(), 'Native publishing failed.' );
	$autosave = native_lesson_request( 'POST', '/wp/v2/ohmylms-lessons/' . $lesson_id . '/autosaves', array( 'content' => '<!-- wp:paragraph --><p>Autosave.</p><!-- /wp:paragraph -->' ) );
	native_lesson_check( in_array( $autosave->get_status(), array( 200, 201 ), true ), 'Native autosave failed.' );
	native_lesson_check( $blocks === get_post( $lesson_id )->post_content, 'An autosave changed published content.' );
	$_GET['ohmylms_return'] = '/content-hub/curriculum/syllabus/24?node=s%3A1';
	native_lesson_check( str_ends_with( NativeLessonEditor::return_url(), '#/content-hub/curriculum/syllabus/24?node=s%3A1' ), 'Syllabus return path was lost.' );
	$_GET['ohmylms_return'] = '//example.invalid/';
	native_lesson_check( str_ends_with( NativeLessonEditor::return_url(), '#/content-hub/lessons' ), 'Unsafe return route was accepted.' );
	unset( $_GET['ohmylms_return'] );
	wp_set_current_user( 0 );
	foreach ( array( '/wp/v2/ohmylms-lessons', '/wp/v2/ohmylms-lessons/' . $lesson_id ) as $route ) {
		$denied = native_lesson_request( 'GET', $route );
		native_lesson_check( 401 === $denied->get_status(), 'Anonymous users can read lesson content on the new authoring API.' );
	}
	$denied = native_lesson_request( 'POST', '/wp/v2/ohmylms-lessons/' . $lesson_id, array( 'content' => 'Unauthorized' ) );
	native_lesson_check( 401 === $denied->get_status(), 'Anonymous updates were accepted.' );
	echo $GLOBALS['native_lesson_checks'] . " native lesson integration checks passed.\n";
} finally {
	$wpdb->posts    = $original_posts;
	$wpdb->postmeta = $original_postmeta;
	wp_set_current_user( 0 );
}
