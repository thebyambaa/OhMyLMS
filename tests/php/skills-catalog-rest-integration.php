<?php
/**
 * Real REST checks using temporary, tagged fixtures only; no database reset.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SKILLS_TEST' ) ) {
	exit( "Set OHMYLMS_SKILLS_TEST=1 to run the skills REST tests.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

/**
 * Dispatch a real REST request.
 *
 * @param string $method HTTP method.
 * @param string $path Route suffix.
 * @param array  $data Request fields.
 * @return WP_REST_Response
 */
function ohmylms_catalog_request( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/skills' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Check a catalogue invariant.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function ohmylms_catalog_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$catalog_tag   = 'catalog-' . wp_generate_uuid4();
$catalog_terms = array();
$catalog_posts = array();
$catalog_users = array();
$catalog_admin = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
ohmylms_catalog_check( ! empty( $catalog_admin ), 'An administrator is required.' );
try {
	foreach ( array( 'author', 'subscriber' ) as $catalog_role ) {
		$catalog_user = wp_insert_user(
			array(
				'user_login' => $catalog_tag . $catalog_role,
				'user_pass'  => wp_generate_password(),
				'role'       => $catalog_role,
			)
		);
		ohmylms_catalog_check( ! is_wp_error( $catalog_user ), 'User fixture creation failed.' );
		$catalog_users[ $catalog_role ] = $catalog_user;
	}
	wp_set_current_user( (int) $catalog_admin[0] );
	foreach ( array( 'A', 'B', 'C' ) as $catalog_name ) {
		$catalog_created = ohmylms_catalog_request(
			'POST',
			'',
			array(
				'name' => $catalog_tag . $catalog_name,
				'code' => 'original',
			)
		);
		ohmylms_catalog_check( 201 === $catalog_created->get_status(), 'Create failed.' );
		$catalog_terms[] = $catalog_created->get_data()['id'];
	}
	list( $catalog_a, $catalog_b, $catalog_c ) = $catalog_terms;
	$catalog_save                              = ohmylms_catalog_request(
		'PUT',
		'/' . $catalog_c,
		array(
			'name'        => $catalog_tag . 'C edited',
			'description' => 'Saved notes',
			'parent'      => $catalog_a,
		)
	);
	ohmylms_catalog_check( 200 === $catalog_save->get_status(), 'Valid skill update failed.' );
	$catalog_reopened = ohmylms_catalog_request( 'GET', '/' . $catalog_c )->get_data();
	ohmylms_catalog_check( 'Saved notes' === $catalog_reopened['description'] && $catalog_a === $catalog_reopened['parent'], 'Saved skill did not reopen.' );
	$catalog_parent_cycle = ohmylms_catalog_request(
		'PUT',
		'/' . $catalog_a,
		array(
			'parent' => $catalog_c,
			'code'   => 'changed',
		)
	);
	ohmylms_catalog_check( 400 === $catalog_parent_cycle->get_status() && 'original' === get_term_meta( $catalog_a, '_ohmylms_skill_code', true ), 'Hierarchy cycle changed metadata.' );
	$catalog_list = ohmylms_catalog_request(
		'GET',
		'/catalog',
		array(
			'search'   => $catalog_tag,
			'per_page' => 2,
		)
	);
	ohmylms_catalog_check( 2 === count( $catalog_list->get_data() ) && 3 === $catalog_list->get_headers()['X-WP-Total'] && 2 === $catalog_list->get_headers()['X-WP-TotalPages'], 'Pagination is incorrect.' );
	ohmylms_catalog_check( ! isset( $catalog_list->get_data()[0]['lessons'] ), 'Catalogue must not load linked posts.' );
	ohmylms_catalog_check( 400 === ohmylms_catalog_request( 'GET', '/catalog', array( 'per_page' => 51 ) )->get_status(), 'Unbounded query accepted.' );
	ohmylms_catalog_check( 400 === ohmylms_catalog_request( 'GET', '/catalog', array( 'include' => range( 1, 51 ) ) )->get_status(), 'Unbounded include accepted.' );
	$catalog_selected = ohmylms_catalog_request( 'GET', '/catalog', array( 'include' => array( $catalog_b ) ) )->get_data();
	ohmylms_catalog_check( 1 === count( $catalog_selected ) && $catalog_b === $catalog_selected[0]['id'], 'Include must constrain catalogue results.' );
	$catalog_second = ohmylms_catalog_request(
		'GET',
		'/catalog',
		array(
			'search'   => $catalog_tag,
			'per_page' => 2,
			'page'     => 2,
		)
	);
	ohmylms_catalog_check( 1 === count( $catalog_second->get_data() ), 'Final page must contain remaining item.' );
	Taxonomy::set_prerequisites( $catalog_b, array( $catalog_a ) );
	$catalog_cycle = ohmylms_catalog_request(
		'PUT',
		'/' . $catalog_a,
		array(
			'name'          => 'Changed',
			'code'          => 'changed',
			'prerequisites' => array( $catalog_b ),
		)
	);
	ohmylms_catalog_check( 400 === $catalog_cycle->get_status(), 'Prerequisite cycle accepted.' );
	ohmylms_catalog_check( 0 === strcmp( $catalog_tag . 'A', get_term( $catalog_a )->name ) && 'original' === get_term_meta( $catalog_a, '_ohmylms_skill_code', true ), 'Failed prerequisite validation changed skill data.' );
	$catalog_invalid_parent = ohmylms_catalog_request(
		'PUT',
		'/' . $catalog_a,
		array(
			'code'   => 'changed',
			'parent' => PHP_INT_MAX,
		)
	);
	ohmylms_catalog_check( 400 === $catalog_invalid_parent->get_status() && 'original' === get_term_meta( $catalog_a, '_ohmylms_skill_code', true ), 'Invalid parent changed metadata.' );
	$catalog_duplicate = ohmylms_catalog_request(
		'PUT',
		'/' . $catalog_a,
		array(
			'name' => '   ',
			'code' => 'changed',
		)
	);
	ohmylms_catalog_check( 400 === $catalog_duplicate->get_status() && 'original' === get_term_meta( $catalog_a, '_ohmylms_skill_code', true ), 'Empty name changed metadata.' );
	wp_set_current_user( $catalog_users['author'] );
	ohmylms_catalog_check( 200 === ohmylms_catalog_request( 'GET', '/catalog' )->get_status(), 'Author could not read the catalogue.' );
	ohmylms_catalog_check( 200 === ohmylms_catalog_request( 'PUT', '/' . $catalog_c, array( 'description' => 'Author notes' ) )->get_status(), 'Author could not edit a skill.' );
	ohmylms_catalog_check( 403 === ohmylms_catalog_request( 'POST', '/' . $catalog_a . '/archive' )->get_status(), 'Author archived a skill.' );
	ohmylms_catalog_check(
		403 === ohmylms_catalog_request(
			'PUT',
			'/' . $catalog_a,
			array(
				'code'            => 'changed',
				'public_practice' => true,
			)
		)->get_status() && 'original' === get_term_meta(
			$catalog_a,
			'_ohmylms_skill_code',
			true
		),
		'Public-practice denial changed metadata.'
	);
	wp_set_current_user( $catalog_users['subscriber'] );
	ohmylms_catalog_check( 403 === ohmylms_catalog_request( 'GET', '/catalog' )->get_status(), 'Subscriber read the author catalogue.' );
	wp_set_current_user( (int) $catalog_admin[0] );
	foreach ( array( OHMYLMS_LESSON_CPT, OHMYLMS_COURSE_CPT ) as $catalog_post_type ) {
		$catalog_post = wp_insert_post(
			array(
				'post_title'  => $catalog_tag,
				'post_type'   => $catalog_post_type,
				'post_status' => 'draft',
				'post_author' => (int) $catalog_admin[0],
			),
			true
		);
		ohmylms_catalog_check( ! is_wp_error( $catalog_post ), 'Post fixture creation failed.' );
		$catalog_posts[] = $catalog_post;
		wp_add_object_terms( $catalog_post, $catalog_c, Taxonomy::NAME );
		$catalog_kind = OHMYLMS_COURSE_CPT === $catalog_post_type ? 'courses' : 'lessons';
		$catalog_path = '/' . $catalog_a . '/relationships/' . $catalog_kind;
		ohmylms_catalog_check( 200 === ohmylms_catalog_request( 'PUT', $catalog_path . '/' . $catalog_post, array( 'linked' => true ) )->get_status(), 'Relationship creation failed.' );
		$catalog_targets = ohmylms_catalog_request(
			'GET',
			$catalog_path,
			array(
				'linked_only' => true,
				'search'      => $catalog_tag,
			)
		);
		ohmylms_catalog_check( 1 === count( $catalog_targets->get_data() ) && true === $catalog_targets->get_data()[0]['linked'], 'Linked content query failed.' );
		wp_set_current_user( $catalog_users['author'] );
		ohmylms_catalog_check( 403 === ohmylms_catalog_request( 'PUT', $catalog_path . '/' . $catalog_post, array( 'linked' => false ) )->get_status(), 'Author changed another author\'s post.' );
		wp_set_current_user( (int) $catalog_admin[0] );
		ohmylms_catalog_check( 200 === ohmylms_catalog_request( 'PUT', $catalog_path . '/' . $catalog_post, array( 'linked' => false ) )->get_status(), 'Unlink failed.' );
		ohmylms_catalog_check( ! has_term( $catalog_a, Taxonomy::NAME, $catalog_post ) && has_term( $catalog_c, Taxonomy::NAME, $catalog_post ), 'Unlink changed an unrelated relationship.' );
	}
	ohmylms_catalog_check( true === ohmylms_catalog_request( 'POST', '/' . $catalog_a . '/archive' )->get_data()['archived'], 'Archive failed.' );
	ohmylms_catalog_check( false === ohmylms_catalog_request( 'POST', '/' . $catalog_a . '/restore' )->get_data()['archived'], 'Restore failed.' );
	echo "Skills catalogue REST integration checks passed.\n";
} finally {
	wp_set_current_user( (int) $catalog_admin[0] );
	foreach ( $catalog_posts as $catalog_post ) {
		wp_delete_post( $catalog_post, true );
	}
	foreach ( array_reverse( $catalog_terms ) as $catalog_term ) {
		wp_delete_term( $catalog_term, Taxonomy::NAME );
	}
	foreach ( $catalog_users as $catalog_user ) {
		wp_delete_user( $catalog_user );
	}
}
