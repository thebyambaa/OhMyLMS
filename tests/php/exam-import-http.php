<?php
/**
 * Multipart job lifecycle rehearsal router for PHP's loopback development server.
 * Start with OHMYLMS_TEST_CREDENTIALS and php -S 127.0.0.1:8791 this-file.php.
 * POST a synthetic PDF using curl -F file=@fixture.pdf http://127.0.0.1:8791/.
 * Never authenticates a normal web-server request or loads the live database.
 *
 * @package OhMyLMS
 */

use OhMyLMS\ExamImport\Documents;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\Utility\Transaction;

if ( 'cli-server' !== PHP_SAPI || '127.0.0.1' !== ( $_SERVER['REMOTE_ADDR'] ?? '' ) || ! getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact loopback comparison before WordPress exists; no input is emitted or used as a path.
	http_response_code( 404 );
	exit;
}
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local credentials file supplied by the CLI operator.
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || 'ohmylms_source_test' !== DB_NAME || wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'Requires disposable WordPress with request-local cache.' );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
$private_dir = sys_get_temp_dir() . '/ohmylms-import-' . wp_generate_uuid4();
mkdir( $private_dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Temporary test directory outside the served tree.
define( 'OHMYLMS_IMPORT_PRIVATE_DIR', $private_dir );
$owner = get_user_by( 'login', $config['username'] )->ID;
wp_set_current_user( $owner );
$checks = 0;
$job_id = 0;
$check  = static function ( $value, $message ) use ( &$checks ) {
	if ( ! $value ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$checks;
};
try {
	Transaction::run(
		static function () use ( $check, $owner, &$job_id ) {
			$req = new WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports' );
			$req->set_file_params( $_FILES ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- CLI-server-only disposable rehearsal; real REST cookie authentication retains core nonce verification.
			$created = rest_do_request( $req );
			$check( 201 === $created->get_status(), 'Create: ' . wp_json_encode( $created->get_data() ) );
			$data   = $created->get_data();
			$job_id = $data['id'];
			$check( 'queued' === $data['state'] && false === strpos( wp_json_encode( $data ), 'sha256' ), 'Owner status projection' );
			$check( in_array( $job_id, array_column( Jobs::listing( $req )['items'], 'id' ), true ), 'Owner can recover persisted job' );
			$stored = get_post_meta( $job_id, Jobs::META, true );
			$path   = Documents::path( $stored['document']['id'] );
			$check( ! is_wp_error( $path ) && is_file( $path ), 'Private source persisted' );
			$check( is_wp_error( Documents::path( '../wp-config' ) ), 'Path traversal rejected' );
			$check(
				is_wp_error(
					Documents::upload(
						array(
							'tmp_name' => $path,
							'name'     => 'fake.pdf',
							'error'    => UPLOAD_ERR_OK,
						)
					)
				),
				'Client-selected local path rejected'
			);
			$id_req       = new WP_REST_Request( 'GET', '/ohmylms/v1/exam-imports/' . $job_id );
			$id_req['id'] = $job_id;
			wp_set_current_user( 0 );
			$check( ! Jobs::permission( $id_req ) && rest_do_request( $id_req )->get_status() >= 400, 'Guest cannot read source job' );
			$other = wp_insert_user(
				array(
					'user_login' => 'import-other-' . wp_generate_uuid4(),
					'user_pass'  => wp_generate_password(),
					'role'       => 'administrator',
				)
			);
			wp_set_current_user( $other );
			$check( ! Jobs::permission( $id_req ) && is_wp_error( Jobs::delete( $id_req ) ), 'Other administrator cannot read or delete owned job' );
			$list_req = new WP_REST_Request( 'GET', '/ohmylms/v1/exam-imports' );
			$check( ! in_array( $job_id, array_column( Jobs::listing( $list_req )['items'], 'id' ), true ), 'Other administrator cannot list owned job' );
			wp_set_current_user( $owner );
			do_action( Jobs::HOOK, $job_id );
			$ready = Jobs::status( $id_req );
			$check( 'ready_for_extraction' === $ready['state'] && 1 === $ready['attempts'], 'Scheduler stage validates source' );
			do_action( Jobs::HOOK, $job_id );
			$check( Jobs::status( $id_req ) === $ready, 'Duplicate delivery is idempotent' );
			$check( is_wp_error( Jobs::retry( $id_req ) ), 'Completed stage cannot be restarted' );
			$stored['state'] = 'queued';
			update_post_meta( $job_id, Jobs::META, $stored );
			file_put_contents( $path, '%PDF-tampered' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Deliberately corrupt only the synthetic private test source.
			do_action( Jobs::HOOK, $job_id );
			$check( 'failed' === Jobs::status( $id_req )['state'], 'Source integrity failure is persisted' );
			as_unschedule_all_actions( Jobs::HOOK, array( $job_id ), Jobs::GROUP );
			$check( 'queued' === Jobs::retry( $id_req )['state'], 'Failed validation may retry' );
			do_action( Jobs::HOOK, $job_id );
			as_unschedule_all_actions( Jobs::HOOK, array( $job_id ), Jobs::GROUP );
			$check( 'queued' === Jobs::retry( $id_req )['state'], 'Last bounded retry queued' );
			do_action( Jobs::HOOK, $job_id );
			$check( is_wp_error( Jobs::retry( $id_req ) ) && 3 === Jobs::status( $id_req )['attempts'], 'Three-attempt budget enforced' );
			$check( Jobs::delete( $id_req )['deleted'] && ! is_file( $path ), 'Delete removes private source and job' );
			throw new RuntimeException( 'rehearsal_rollback_success' );
		}
	);
} catch ( Throwable $error ) {
	if ( 'rehearsal_rollback_success' !== $error->getMessage() ) {
		http_response_code( 500 );
		$failure = $error->getMessage();
	}
} finally {
	foreach ( glob( $private_dir . '/*.pdf' ) as $source ) {
		wp_delete_file( $source );
	}
	rmdir( $private_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove the empty, server-generated test directory.
}
if ( isset( $failure ) ) {
	echo esc_html( $failure );
	return;
}
echo (int) $checks . " private PDF/job lifecycle checks passed; fixture writes rolled back.\n";
