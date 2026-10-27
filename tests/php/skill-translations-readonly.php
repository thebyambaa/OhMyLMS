<?php
/**
 * Read-only REST authorization and validation checks against local WordPress.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) ) {
	exit( 1 );
}
require rtrim( $argv[1], '/\\' ) . '/wp-load.php';

$checks     = 0;
$check      = static function ( $condition, $label ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $label ) );
	}
	++$checks;
};
$terms      = get_terms(
	array(
		'taxonomy'   => \OhMyLMS\Skills\Taxonomy::NAME,
		'hide_empty' => false,
		'number'     => 1,
	)
);
$skill_id   = $terms[0]->term_id;
$route_path = '/ohmylms/v1/skills/' . $skill_id . '/translate';
$before     = get_term_meta( $skill_id, \OhMyLMS\Skills\Translations::META_KEY, true );
$admin      = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( 0 );
$request = new WP_REST_Request( 'POST', $route_path );
$request->set_body_params(
	array(
		'locale' => 'en',
		'source' => array( 'name' => 'Example objective' ),
	)
);
$check( 401 === rest_do_request( $request )->get_status(), 'Translation requires authentication.' );
wp_set_current_user( (int) $admin[0] );
$request->set_body_params(
	array(
		'locale' => array(),
		'source' => array( 'name' => 'Example' ),
	)
);
$check( 400 === rest_do_request( $request )->get_status(), 'Malformed locale fails safely.' );
$request->set_body_params(
	array(
		'locale' => 'en',
		'source' => array( 'name' => str_repeat( 'a', 191 ) ),
	)
);
$check( 400 === rest_do_request( $request )->get_status(), 'Bounds checked before provider calls.' );
if ( ! \OhMyLMS\AI\Settings::configured() ) {
	$request->set_body_params(
		array(
			'locale' => 'mn',
			'source' => array( 'name' => 'Example objective' ),
		)
	);
	$check( 503 === rest_do_request( $request )->get_status(), 'Unconfigured provider returns actionable error.' );
}
$skill = rest_do_request( new WP_REST_Request( 'GET', '/ohmylms/v1/skills/' . $skill_id ) )->get_data();
$check( isset( $skill['translations'] ) && (int) $skill_id === $skill['id'], 'Translations use the existing skill ID.' );
$check( isset( $skill['objective'] ) && is_string( $skill['objective'] ), 'Full objectives are available alongside canonical titles.' );
$legacy = (object) array(
	'term_id'     => 0,
	'name'        => 'Sets',
	'description' => 'Use set notation and Venn diagrams.',
);
$check( \OhMyLMS\Skills\Objectives::get( $legacy ) === $legacy->description, 'Legacy definitions appear as the main objective.' );
$legacy->description = '';
$check( \OhMyLMS\Skills\Objectives::get( $legacy ) === $legacy->name, 'Old title-only skills remain usable.' );
$check( 1901 === mb_strlen( \OhMyLMS\Skills\Objectives::normalize( str_repeat( 'а', 1901 ) ) ), 'Objectives can exceed the unchanged short-title limit.' );
foreach ( array( '', array(), str_repeat( 'а', 2001 ), '<b></b>' ) as $invalid_objective ) {
	$check( is_wp_error( \OhMyLMS\Skills\Objectives::normalize( $invalid_objective ) ), 'Objective shape, content and bounds are validated.' );
}
$check( get_term_meta( $skill_id, \OhMyLMS\Skills\Translations::META_KEY, true ) === $before, 'Preview and validation never save skill data.' );
echo (int) $checks . " read-only translation REST checks passed.\n";
