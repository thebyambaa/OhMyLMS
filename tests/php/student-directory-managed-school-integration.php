<?php
/**
 * Disposable WordPress integration check: a student account created inside a school (it carries the
 * _ohmylms_managed_school meta) is listed by GET /ohmylms/v1/students before its first enrollment, while
 * unrelated accounts stay out. Fixture rows are rolled back.
 *
 * Usage: OHMYLMS_TEST_CREDENTIALS=/path/creds.json php student-directory-managed-school-integration.php /path/to/wordpress
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || empty( $argv[1] ) ) {
	exit( "Usage: php student-directory-managed-school-integration.php /path/to/wordpress\n" );
}
$site   = rtrim( $argv[1], '/\\' );
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
require $site . '/wp-load.php';
global $wpdb;
wp_set_current_user( get_user_by( 'login', $config['username'] )->ID );
$wpdb->query( 'START TRANSACTION' );
$suffix = 'managed-fixture-' . wp_generate_password( 8, false );
$mk     = function ( $tag, $role ) use ( $suffix ) {
	return wp_insert_user( array( 'user_login' => $tag . '-' . $suffix, 'user_pass' => wp_generate_password(), 'user_email' => $tag . '-' . $suffix . '@example.test', 'role' => $role ) );
};
$managed = $mk( 'managed', 'author' ); // Not a student role, not enrolled, but created inside a school.
$plain   = $mk( 'plain', 'author' );   // Same, but not school-managed.
$student = $mk( 'student', 'subscriber' ); // A plain student account with no enrollment.
update_user_meta( $managed, '_ohmylms_managed_school', 12345 );
$request = new WP_REST_Request( 'GET', '/ohmylms/v1/students' );
$request->set_query_params( array( 'search' => $suffix, 'per_page' => 50 ) );
$response = rest_do_request( $request );
$ids      = array_map( 'intval', array_column( (array) $response->get_data(), 'id' ) );
$ok       = array(
	'REST route answers 200'                          => 200 === $response->get_status(),
	'no SQL error'                                    => '' === $wpdb->last_error,
	'school-managed account is listed'                => in_array( $managed, $ids, true ),
	'plain student account is listed (main behavior)' => in_array( $student, $ids, true ),
	'unrelated non-student account is excluded'       => ! in_array( $plain, $ids, true ),
	'total header matches (2)'                        => 2 === (int) $response->get_headers()['X-WP-Total'],
);
$wpdb->query( 'ROLLBACK' );
foreach ( $ok as $label => $pass ) {
	echo ( $pass ? 'PASS ' : 'FAIL ' ), $label, "\n";
}
echo 'last_error: ', $wpdb->last_error ?: '(none)', "\n";
exit( in_array( false, $ok, true ) ? 1 : 0 );
