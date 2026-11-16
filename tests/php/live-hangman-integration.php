<?php
/**
 * Live game capabilities and REST authorization.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_HANGMAN_TEST' ) ) {
	exit( "Set OHMYLMS_HANGMAN_TEST=1 for local checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

$hangman_owner = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
)[0];
$hangman_token = wp_generate_uuid4();
$hangman_actor = get_current_user_id();
set_transient(
	'ohmylms_hangman_' . $hangman_token,
	array(
		'user'  => (int) $hangman_owner,
		'word'  => 'ball',
		'limit' => 6,
	),
	60
);
try {
	$hangman_request = new WP_REST_Request( 'POST', '/ohmylms/v1/hangman/play' );
	$hangman_request->set_param( 'token', $hangman_token );
	$hangman_request->set_param( 'guesses', 'b' );
	wp_set_current_user( 0 );
	if ( 401 !== rest_do_request( $hangman_request )->get_status() ) {
		throw new RuntimeException( 'Anonymous games must be denied.' );
	}
	wp_set_current_user( $hangman_owner );
	$hangman_response = rest_do_request( $hangman_request );
	if ( 200 !== $hangman_response->get_status() || array( 'b', null, null, null ) !== $hangman_response->get_data()['letters'] ) {
		throw new RuntimeException( 'The owner must receive only revealed letters.' );
	}
	set_transient(
		'ohmylms_hangman_' . $hangman_token,
		array(
			'user'  => -1,
			'word'  => 'ball',
			'limit' => 6,
		),
		60
	);
	if ( 410 !== rest_do_request( $hangman_request )->get_status() ) {
		throw new RuntimeException( 'Another user cannot use the game token.' );
	}
	delete_transient( 'ohmylms_hangman_' . $hangman_token );
	if ( 410 !== rest_do_request( $hangman_request )->get_status() ) {
		throw new RuntimeException( 'Expired game tokens must be rejected.' );
	}
	echo "Live Hangman REST ownership, hidden answers and expiry passed.\n";
} finally {
	delete_transient( 'ohmylms_hangman_' . $hangman_token );
	wp_set_current_user( $hangman_actor );
}
