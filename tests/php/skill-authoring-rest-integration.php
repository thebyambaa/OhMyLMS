<?php
/**
 * Real REST checks for the revision-checked skill editor and hardened skill routes.
 * Run on the Local site with OHMYLMS_SKILL_AUTHORING_TEST=1; tagged fixtures are removed in finally.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Skills\Objectives;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SKILL_AUTHORING_TEST' ) ) {
	exit( "Set OHMYLMS_SKILL_AUTHORING_TEST=1 to run the skill authoring REST tests.\n" );
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
 * @param string $path Route below the namespace.
 * @param array  $data Request fields.
 * @return WP_REST_Response
 */
function ohmylms_skill_authoring_request( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Check an invariant.
 *
 * @param bool   $condition Expected condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function ohmylms_skill_authoring_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
}

$authoring_tag   = 'skill-authoring-' . wp_generate_uuid4();
$authoring_terms = array();
$authoring_posts = array();
$authoring_users = array();
$authoring_admin = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
ohmylms_skill_authoring_check( ! empty( $authoring_admin ), 'An administrator is required.' );
$authoring_admin = (int) $authoring_admin[0];
try {
	foreach ( array( 'author', 'subscriber' ) as $authoring_role ) {
		$authoring_user = wp_insert_user(
			array(
				'user_login' => 'sa' . substr( md5( $authoring_tag ), 0, 12 ) . $authoring_role,
				'user_pass'  => wp_generate_password(),
				'role'       => $authoring_role,
			)
		);
		ohmylms_skill_authoring_check( ! is_wp_error( $authoring_user ), 'User fixture creation failed.' );
		$authoring_users[ $authoring_role ] = $authoring_user;
	}
	wp_set_current_user( $authoring_admin );
	foreach ( array( 'A', 'B' ) as $authoring_name ) {
		$authoring_term = wp_insert_term( $authoring_tag . $authoring_name, Taxonomy::NAME, array( 'description' => 'Notes ' . $authoring_name ) );
		ohmylms_skill_authoring_check( ! is_wp_error( $authoring_term ), 'Skill fixture creation failed.' );
		$authoring_terms[] = (int) $authoring_term['term_id'];
		update_term_meta( (int) $authoring_term['term_id'], '_ohmylms_skill_code', 'original' );
	}
	list( $authoring_a, $authoring_b ) = $authoring_terms;
	$authoring_path                    = '/authoring/skills/' . $authoring_a;
	$authoring_lesson                  = wp_insert_post(
		array(
			'post_title'  => $authoring_tag,
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'draft',
			'post_author' => $authoring_admin,
		),
		true
	);
	ohmylms_skill_authoring_check( ! is_wp_error( $authoring_lesson ), 'Lesson fixture creation failed.' );
	$authoring_posts[] = $authoring_lesson;
	wp_add_object_terms( $authoring_lesson, $authoring_a, Taxonomy::NAME );

	$authoring_read = ohmylms_skill_authoring_request( 'GET', $authoring_path );
	ohmylms_skill_authoring_check( 200 === $authoring_read->get_status(), 'Administrator could not read the skill.' );
	$authoring_data = $authoring_read->get_data();
	ohmylms_skill_authoring_check( 64 === strlen( $authoring_data['revision'] ) && '' === $authoring_data['skill']['objective'] && 'Notes A' === $authoring_data['skill']['effective_objective'], 'Initial editor state is wrong.' );
	ohmylms_skill_authoring_check( 1 === $authoring_data['resources']['lessons'] && 0 === $authoring_data['resources']['courses'] && true === $authoring_data['permissions']['public_practice'], 'Resource counts or permissions are wrong.' );
	ohmylms_skill_authoring_check( 404 === ohmylms_skill_authoring_request( 'GET', '/authoring/skills/' . PHP_INT_MAX )->get_status(), 'Missing skill was readable.' );

	$authoring_revision = $authoring_data['revision'];
	$authoring_rejected = array(
		'unknown field'        => array(
			'expected_revision' => $authoring_revision,
			'lessons'           => array( 1 ),
		),
		'missing revision'     => array( 'code' => 'changed' ),
		'wrong type'           => array(
			'expected_revision' => $authoring_revision,
			'name'              => 5,
		),
		'oversized objective'  => array(
			'expected_revision' => $authoring_revision,
			'objective'         => str_repeat( 'x', 2001 ),
		),
		'unknown locale'       => array(
			'expected_revision' => $authoring_revision,
			'translations'      => array( 'fr' => array( 'name' => 'Nom' ) ),
		),
		'empty translation'    => array(
			'expected_revision' => $authoring_revision,
			'code'              => 'changed',
			'translations'      => array( 'mn' => array( 'name' => '   ' ) ),
		),
		'blank name'           => array(
			'expected_revision' => $authoring_revision,
			'code'              => 'changed',
			'name'              => '   ',
		),
		'parent cycle'         => array(
			'expected_revision' => $authoring_revision,
			'code'              => 'changed',
			'parent'            => $authoring_a,
		),
		'missing prerequisite' => array(
			'expected_revision' => $authoring_revision,
			'code'              => 'changed',
			'prerequisites'     => array( PHP_INT_MAX ),
		),
	);
	foreach ( $authoring_rejected as $authoring_case => $authoring_body ) {
		$authoring_response = ohmylms_skill_authoring_request( 'PUT', $authoring_path, $authoring_body );
		ohmylms_skill_authoring_check( 400 === $authoring_response->get_status(), 'Accepted invalid update: ' . $authoring_case );
		ohmylms_skill_authoring_check( 'original' === get_term_meta( $authoring_a, '_ohmylms_skill_code', true ), 'Rejected update wrote data: ' . $authoring_case );
	}

	$authoring_saved = ohmylms_skill_authoring_request(
		'PUT',
		$authoring_path,
		array(
			'expected_revision' => $authoring_revision,
			'name'              => $authoring_tag . 'A edited',
			'code'              => 'A1',
			'description'       => 'a < b notes',
			'objective'         => 'Solve x < 2 inequalities',
			'prerequisites'     => array( $authoring_b ),
			'translations'      => array(
				'mn' => array(
					'name'      => 'Тэнцэтгэл биш',
					'objective' => 'Тэнцэтгэл биш бодох',
				),
			),
		)
	);
	ohmylms_skill_authoring_check( 200 === $authoring_saved->get_status(), 'Valid update failed.' );
	$authoring_after = ohmylms_skill_authoring_request( 'GET', $authoring_path )->get_data();
	ohmylms_skill_authoring_check( 'Solve x < 2 inequalities' === $authoring_after['skill']['objective'] && 'a < b notes' === $authoring_after['skill']['description'] && array( $authoring_b ) === $authoring_after['skill']['prerequisites'], 'Saved fields did not reopen.' );
	ohmylms_skill_authoring_check( 'Тэнцэтгэл биш' === Taxonomy::describe( $authoring_a )['translations']['mn']['name'] && $authoring_after['revision'] !== $authoring_revision, 'Translation or revision did not change.' );

	$authoring_stale = ohmylms_skill_authoring_request(
		'PUT',
		$authoring_path,
		array(
			'expected_revision' => $authoring_revision,
			'code'              => 'stale',
		)
	);
	ohmylms_skill_authoring_check( 409 === $authoring_stale->get_status() && 'ohmylms_skill_stale' === $authoring_stale->get_data()['code'] && 'A1' === get_term_meta( $authoring_a, '_ohmylms_skill_code', true ), 'Stale write was applied.' );

	$authoring_cycle = ohmylms_skill_authoring_request(
		'PUT',
		'/authoring/skills/' . $authoring_b,
		array(
			'expected_revision' => ohmylms_skill_authoring_request( 'GET', '/authoring/skills/' . $authoring_b )->get_data()['revision'],
			'code'              => 'changed',
			'prerequisites'     => array( $authoring_a ),
		)
	);
	ohmylms_skill_authoring_check( 400 === $authoring_cycle->get_status() && 'original' === get_term_meta( $authoring_b, '_ohmylms_skill_code', true ), 'Prerequisite cycle was accepted.' );

	$authoring_cleared = ohmylms_skill_authoring_request(
		'PUT',
		$authoring_path,
		array(
			'expected_revision' => $authoring_after['revision'],
			'objective'         => '',
		)
	)->get_data();
	ohmylms_skill_authoring_check( '' === $authoring_cleared['skill']['objective'] && 'a < b notes' === $authoring_cleared['skill']['effective_objective'] && '' === (string) get_term_meta( $authoring_a, Objectives::META_KEY, true ), 'Clearing the objective did not fall back.' );

	wp_set_current_user( $authoring_users['author'] );
	$authoring_author = ohmylms_skill_authoring_request( 'GET', $authoring_path )->get_data();
	ohmylms_skill_authoring_check( false === $authoring_author['permissions']['public_practice'], 'Author was offered public practice.' );
	$authoring_denied = ohmylms_skill_authoring_request(
		'PUT',
		$authoring_path,
		array(
			'expected_revision' => $authoring_author['revision'],
			'code'              => 'author',
			'public_practice'   => true,
		)
	);
	ohmylms_skill_authoring_check( 403 === $authoring_denied->get_status() && 'A1' === get_term_meta( $authoring_a, '_ohmylms_skill_code', true ), 'Author changed public practice.' );
	ohmylms_skill_authoring_check(
		200 === ohmylms_skill_authoring_request(
			'PUT',
			$authoring_path,
			array(
				'expected_revision' => $authoring_author['revision'],
				'code'              => 'author',
				'public_practice'   => false,
			)
		)->get_status(),
		'Author could not edit with an unchanged practice flag.'
	);
	wp_set_current_user( $authoring_users['subscriber'] );
	ohmylms_skill_authoring_check( 403 === ohmylms_skill_authoring_request( 'GET', $authoring_path )->get_status(), 'Subscriber read the editor.' );
	ohmylms_skill_authoring_check( 403 === ohmylms_skill_authoring_request( 'PUT', $authoring_path, array( 'expected_revision' => str_repeat( 'a', 64 ) ) )->get_status(), 'Subscriber reached the write.' );
	ohmylms_skill_authoring_check(
		403 === ohmylms_skill_authoring_request(
			'POST',
			'/skills/' . $authoring_a . '/translate',
			array(
				'locale' => 'en',
				'source' => array( 'name' => 'x' ),
			)
		)->get_status(),
		'Subscriber reached translation.'
	);

	wp_set_current_user( $authoring_admin );
	$authoring_public = ohmylms_skill_authoring_request(
		'PUT',
		$authoring_path,
		array(
			'expected_revision' => ohmylms_skill_authoring_request( 'GET', $authoring_path )->get_data()['revision'],
			'public_practice'   => true,
		)
	);
	ohmylms_skill_authoring_check( 200 === $authoring_public->get_status() && true === $authoring_public->get_data()['skill']['public_practice'], 'Administrator could not publish practice.' );

	$authoring_translate = '/skills/' . $authoring_a . '/translate';
	ohmylms_skill_authoring_check(
		400 === ohmylms_skill_authoring_request(
			'POST',
			$authoring_translate,
			array(
				'locale' => 'fr',
				'source' => array( 'name' => 'x' ),
			)
		)->get_status(),
		'Translation accepted an unknown locale.'
	);
	ohmylms_skill_authoring_check( 400 === ohmylms_skill_authoring_request( 'POST', $authoring_translate, array( 'locale' => 'en' ) )->get_status(), 'Translation accepted a missing source.' );
	ohmylms_skill_authoring_check(
		400 === ohmylms_skill_authoring_request(
			'POST',
			$authoring_translate,
			array(
				'locale' => 'en',
				'source' => array(
					'name'  => 'x',
					'extra' => 'y',
				),
			)
		)->get_status(),
		'Translation accepted an unknown source field.'
	);
	ohmylms_skill_authoring_check(
		404 === ohmylms_skill_authoring_request(
			'POST',
			'/skills/' . PHP_INT_MAX . '/translate',
			array(
				'locale' => 'en',
				'source' => array( 'name' => 'x' ),
			)
		)->get_status(),
		'Translation of a missing skill was accepted.'
	);
	ohmylms_skill_authoring_check( 400 === ohmylms_skill_authoring_request( 'PUT', '/skills/' . $authoring_a, array( 'objective' => 7 ) )->get_status(), 'Legacy update accepted a non-string objective.' );
	ohmylms_skill_authoring_check( 400 === ohmylms_skill_authoring_request( 'PUT', '/skills/' . $authoring_a, array( 'translations' => array( 'mn' => array( 'title' => 'x' ) ) ) )->get_status(), 'Legacy update accepted an unknown translation field.' );
	echo "Skill authoring REST integration checks passed.\n";
} finally {
	wp_set_current_user( $authoring_admin );
	foreach ( $authoring_posts as $authoring_post ) {
		wp_delete_post( $authoring_post, true );
	}
	foreach ( $authoring_terms as $authoring_term ) {
		wp_delete_term( $authoring_term, Taxonomy::NAME );
	}
	foreach ( $authoring_users as $authoring_user ) {
		wp_delete_user( $authoring_user );
	}
}
