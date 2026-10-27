<?php
/**
 * Rejected AJAX nonce paths must stop before cart or upload work.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local nonce checks.\n" );
}
define( 'DOING_AJAX', true );
define( 'DISABLE_WP_CRON', true );
require dirname( __DIR__, 5 ) . '/wp-load.php';

add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'test_network_blocked', 'Network disabled for nonce checks.' );
	}
);
add_filter( 'pre_wp_mail', '__return_true' );
add_filter(
	'wp_die_ajax_handler',
	static function () {
		return static function ( $message ) {
			if ( -1 === $message ) {
				echo '-1';
			}
			throw new RuntimeException( 'nonce_test_response_finished' );
		};
	}
);
$nonce_methods = array( 'purchase_membership', 'add_to_cart', 'student_profile_image_upload', 'student_cover_image_upload', 'student_cover_image_delete' );
foreach ( $nonce_methods as $nonce_method ) {
	$_POST    = array( 'nonce' => 'invalid-test-nonce' );
	$_REQUEST = array( 'nonce' => 'invalid-test-nonce' );
	ob_start();
	try {
		call_user_func( array( OhMyLMS\Ajax::class, $nonce_method ) );
		throw new RuntimeException( 'The handler did not reject the invalid nonce.' );
	} catch ( RuntimeException $nonce_error ) {
		$nonce_response = ob_get_clean();
		if ( 'nonce_test_response_finished' !== $nonce_error->getMessage() || ( '-1' !== $nonce_response && ! str_contains( $nonce_response, 'Invalid request.' ) && ! str_contains( $nonce_response, 'Invalid nonce' ) ) ) {
			throw new RuntimeException( 'Unexpected rejection for ' . esc_html( $nonce_method ) );
		}
	}
}
// A valid token must still reject malformed upload metadata before calling the upload handler.
$nonce_upload = wp_create_nonce( 'student_profile_image_upload' );
$_POST        = array( 'nonce' => $nonce_upload );
$_REQUEST     = array( 'nonce' => $nonce_upload );
$_FILES       = array( 'file' => array( 'name' => 'missing-error.jpg' ) );
ob_start();
try {
	OhMyLMS\Ajax::student_profile_image_upload();
	throw new RuntimeException( 'Malformed upload metadata was accepted.' );
} catch ( RuntimeException $nonce_error ) {
	$nonce_response = ob_get_clean();
	if ( 'nonce_test_response_finished' !== $nonce_error->getMessage() || ! str_contains( $nonce_response, 'No file uploaded' ) ) {
		throw new RuntimeException( 'Malformed upload metadata must stop before upload processing.' );
	}
}

// Quiz forms must reject bad tokens before starting or grading an attempt.
$nonce_forms = array(
	array( 'save_quiz_attempt', 'quiz-action', 'save-quiz-attempt-nonce' ),
	array( 'save_quiz_submit', 'ohmylms-quiz-submission', 'save-quiz-submit-nonce' ),
	array( 'save_quiz_exit_submit', 'ohmylms-quiz-exit-submission', 'save-quiz-exit-submit-nonce' ),
);
foreach ( $nonce_forms as $nonce_form ) {
	$_POST = array(
		'action'       => $nonce_form[1],
		$nonce_form[2] => 'invalid-test-nonce',
	);
	ob_start();
	try {
		call_user_func( array( OhMyLMS\FormHandler::class, $nonce_form[0] ) );
		throw new RuntimeException( 'Quiz form accepted an invalid token.' );
	} catch ( RuntimeException $nonce_error ) {
		ob_end_clean();
		if ( 'nonce_test_response_finished' !== $nonce_error->getMessage() ) {
			throw $nonce_error;
		}
	}
}
echo "PASS: five AJAX nonce rejections, malformed upload metadata and three quiz-form nonce rejections.\n";
