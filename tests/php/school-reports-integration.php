<?php
/**
 * School reports and grading checks against the disposable database.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$report_credentials_path = getenv( 'OHMYLMS_TEST_CREDENTIALS' );
if ( ! $report_credentials_path || ! is_file( $report_credentials_path ) ) {
	throw new RuntimeException( 'Disposable test credentials are required.' );
}
$report_credentials = json_decode( file_get_contents( $report_credentials_path ), true );
require $report_credentials['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_source_test' !== DB_NAME ) {
	throw new RuntimeException( 'Disposable database required.' );
}
require __DIR__ . '/support/school-report-checks.php';
require __DIR__ . '/support/school-lifecycle-checks.php';
require __DIR__ . '/support/school-matrix-checks.php';
try {
	echo esc_html( ohmylms_school_report_checks() . ' school report checks passed.' ) . "\n";
	echo esc_html( ohmylms_school_lifecycle_checks() . ' school lifecycle checks passed.' ) . "\n";
	echo esc_html( ohmylms_school_matrix_checks() . ' school matrix checks passed.' ) . "\n";
} catch ( Throwable $report_error ) {
	fwrite( STDERR, esc_html( $report_error->getMessage() ) . "\n" );
	exit( 1 );
}
