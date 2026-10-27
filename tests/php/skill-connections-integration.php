<?php
/**
 * Placed skill connections on the disposable migration database.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Skills\Taxonomy;

$connections_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $connections_site || ! is_file( $connections_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to the disposable migration installation.' );
}
require $connections_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'This test requires the dedicated migration database.' );
}

/**
 * Assert a connection invariant.
 *
 * @param bool   $condition Expected result.
 * @param string $message Failure message.
 * @throws RuntimeException When an invariant fails.
 */
function skill_connection_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

/**
 * Invoke the real curriculum REST route.
 *
 * @param string $path REST path.
 * @param array  $data Request body.
 * @return array Response data.
 * @throws RuntimeException When a fixture cannot be saved.
 */
function skill_connection_fixture( $path, array $data ) {
	$request = new WP_REST_Request( 'POST', '/ohmylms/v1/' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data ) );
	$response = rest_do_request( $request );
	if ( $response->is_error() ) {
		throw new RuntimeException( esc_html( wp_json_encode( $response->get_data() ) ) );
	}
	return $response->get_data();
}

wp_set_current_user( get_user_by( 'login', 'admin' )->ID );
$connection_terms = array();
$connection_item  = 0;
try {
	$connection_tag = wp_generate_uuid4();
	foreach ( array( 'Source A', 'Source B', 'Shared' ) as $connection_name ) {
		$connection_term = wp_insert_term( $connection_name . ' ' . $connection_tag, Taxonomy::NAME );
		skill_connection_check( ! is_wp_error( $connection_term ), 'Fixture skill creation failed.' );
		$connection_terms[] = (int) $connection_term['term_id'];
	}
	list( $connection_a, $connection_b, $connection_shared ) = $connection_terms;
	SkillMappings::save( $connection_a, $connection_shared, 'broader', 'Scope A' );
	SkillMappings::save( $connection_b, $connection_shared, 'narrower', 'Scope B' );
	$connection_rows = SkillMappings::connections_for_skills( array( $connection_a, $connection_shared ) );
	skill_connection_check( 1 === count( $connection_rows[ $connection_a ] ), 'Source connections must include the shared target.' );
	skill_connection_check( 2 === count( $connection_rows[ $connection_shared ] ), 'Shared skills must include all connected sources, including unplaced ones.' );
	skill_connection_check( 'broader' === $connection_rows[ $connection_a ][0]['relation'], 'Source-oriented relationship changed.' );
	skill_connection_check( $connection_shared === $connection_rows[ $connection_a ][0]['shared']['id'], 'Connected skill identity changed.' );
	skill_connection_check( array() === SkillMappings::connections_for_skills( array() ), 'Empty outlines must not read every mapping.' );

	skill_connection_check( 'Scope A' === $connection_rows[ $connection_a ][0]['note'], 'Connection context was lost.' );
	skill_connection_check( ! isset( $connection_rows[ $connection_b ] ), 'Unplaced source skill leaked into the outline index.' );
	skill_connection_check( $connection_a === $connection_rows[ $connection_shared ][0]['specific']['id'], 'The incoming source identity changed.' );

	$connection_created = skill_connection_fixture(
		'curriculum/items',
		array(
			'name'        => 'Connections ' . $connection_tag,
			'item_type'   => 'roadmap',
			'is_syllabus' => true,
		)
	);
	$connection_item    = (int) $connection_created['item']['id'];
	$connection_group   = skill_connection_fixture( "curriculum/items/$connection_item/syllabus/groups", array( 'name' => 'Skills' ) );
	Syllabus::add_skill( $connection_item, (int) $connection_group['group_id'], $connection_shared );
	$connection_outline = Syllabus::outline( $connection_item );
	skill_connection_check( 2 === count( $connection_outline['contents'][0]['groups'][0]['skills'][0]['connections'] ), 'The outline omitted saved skill connections.' );
	$connection_revision = $connection_rows[ $connection_a ][0]['revision'];
	skill_connection_check( is_string( $connection_revision ) && '' !== $connection_revision, 'The workspace needs a revision for safe writes.' );
	$connection_saved = SkillMappings::save( $connection_a, $connection_shared, 'related', 'Workspace update', $connection_revision );
	skill_connection_check( ! is_wp_error( $connection_saved ) && 'related' === $connection_saved['relation'], 'Revision-bearing connection edit failed.' );
	$connection_stale = SkillMappings::save( $connection_a, $connection_shared, 'broader', 'Stale overwrite', $connection_revision );
	skill_connection_check( is_wp_error( $connection_stale ), 'A stale workspace must not overwrite a newer connection.' );
	$connection_new = Syllabus::create_skill( $connection_item, (int) $connection_group['group_id'], array( 'name' => 'New roadmap skill' ) );
	skill_connection_check( ! is_wp_error( $connection_new ), 'New roadmap skill creation failed.' );
	$connection_terms[] = $connection_new['term_id'];
	skill_connection_check( null !== \OhMyLMS\Curriculum\Comparison::shared_skill( $connection_new['term_id'] ), 'New roadmap skills must accept source connections.' );
	echo "14 skill connection integration checks passed.\n";
} finally {
	if ( $connection_item ) {
		Items::delete( $connection_item, 'delete', true );
	}
	foreach ( $connection_terms as $connection_term_id ) {
		wp_delete_term( $connection_term_id, Taxonomy::NAME );
	}
}
