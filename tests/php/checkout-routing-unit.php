<?php
/**
 * Reject checkout routing failures before any cart mutation.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

/** Local WordPress doubles; no database, sessions or payment gateways are loaded. */
function nocache_headers() {}
/**
 * Ignore registration when the Ajax class initializes.
 *
 * @param string   $hook Hook name.
 * @param callable $callback Registered callback.
 */
function add_action( $hook, $callback ) {}
/**
 * Test input is already unslashed.
 *
 * @param mixed $value Input value.
 */
function wp_unslash( $value ) {
	return $value;
}
/**
 * Test input contains no markup.
 *
 * @param mixed $value Input value.
 */
function ohmylms_clean( $value ) {
	return $value;
}
/**
 * Accept only the expected action and test nonce.
 *
 * @param string $value Nonce value.
 * @param string $action Nonce action.
 */
function wp_verify_nonce( $value, $action ) {
	return 'valid' === $value && 'add-to-cart' === $action;
}
/**
 * Match WordPress's AJAX referer check (without dying) against the posted field.
 *
 * @param string $action    Nonce action.
 * @param string $query_arg Field name.
 * @param bool   $stop      Whether to die on failure (ignored).
 */
function check_ajax_referer( $action, $query_arg = false, $stop = true ) {
	$value = $query_arg && isset( $_POST[ $query_arg ] ) ? $_POST[ $query_arg ] : '';
	return 'valid' === $value || wp_verify_nonce( $value, $action );
}
/**
 * Match WordPress integer conversion.
 *
 * @param mixed $value Input value.
 */
function absint( $value ) {
	return abs( (int) $value );
}
/**
 * The synthetic course is published.
 *
 * @param int $post_id Course ID.
 */
function get_post_status( $post_id ) {
	return 'publish';
}
/**
 * Keep the untranslated test message.
 *
 * @param string $value Message.
 * @param string $domain Translation domain.
 */
function __( $value, $domain ) {
	return $value;
}
/** Simulate an unconfigured checkout. */
function ohmylms_get_checkout_url() {
	return '';
}
/**
 * Supply a purchasable, available course.
 *
 * @param int $post_id Course ID.
 */
function ohmylms_get_course( $post_id ) {
	return new class() {
		/** Allow purchase. */
		public function is_purchasable() {
			return true;
		}
		/** Allow enrollment capacity. */
		public function is_in_stock() {
			return true;
		}
	};
}
/**
 * Capture JSON without exiting the test process.
 *
 * @param array    $response Response payload.
 * @param int|null $status_code HTTP status.
 * @throws Error Stops the request without exiting the process.
 */
function wp_send_json( $response, $status_code = null ) {
	$GLOBALS['checkout_routing_response'] = array(
		'response'    => $response,
		'status_code' => $status_code,
	);
	throw new Error( 'checkout_routing_response' );
}
/**
 * Capture the WordPress nonce rejection envelope.
 *
 * @param array $data Error payload.
 */
function wp_send_json_error( $data ) {
	wp_send_json(
		array(
			'success' => false,
			'data'    => $data,
		)
	);
}

foreach ( array( 'invalid', 'valid' ) as $checkout_nonce ) {
	$_POST = array(
		'nonce'     => $checkout_nonce,
		'course_id' => 123,
	);
	try {
		\OhMyLMS\Ajax::add_to_cart();
		throw new RuntimeException( 'Routing failure did not stop the request.' );
	} catch ( Error $error ) {
		if ( 'checkout_routing_response' !== $error->getMessage() ) {
			throw $error;
		}
	}
	$checkout_result = $GLOBALS['checkout_routing_response'];
	if ( 'invalid' === $checkout_nonce ) {
		if ( false !== ( $checkout_result['response']['success'] ?? null ) ) {
			throw new RuntimeException( 'An invalid nonce was accepted.' );
		}
	} elseif ( 503 !== $checkout_result['status_code'] || 'error' !== $checkout_result['response']['status'] || isset( $checkout_result['response']['redirect_url'] ) ) {
		throw new RuntimeException( 'Missing checkout produced a successful or relative redirect.' );
	}
}
echo "2 checkout routing checks passed; no cart or payment service was loaded.\n";
