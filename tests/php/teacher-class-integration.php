<?php
/**
 * Run native class management checks against the credential-protected disposable site.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$teacher_credentials_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $teacher_credentials_path || ! is_file( $teacher_credentials_path ) ) {
	throw new RuntimeException( 'Disposable test credentials are required.' );
}
$teacher_credentials = json_decode( file_get_contents( $teacher_credentials_path ), true );
require $teacher_credentials['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Disposable database required.' );
}
require __DIR__ . '/support/teacher-class-checks.php';
echo esc_html( ohmylms_teacher_class_checks() . ' teacher class checks passed.' ) . "\n";
