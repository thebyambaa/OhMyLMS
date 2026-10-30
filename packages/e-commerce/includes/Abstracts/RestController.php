<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Base controller for the OhMyLMS commerce API.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Abstracts;

use WP_Error;
use WP_REST_Controller;

/**
 * Rest Controller base class.
 *
 * @since 0.3.0
 */
abstract class RestController extends WP_REST_Controller {

	/**
	 * Capability required by the order, coupon and refund routes.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms-commerce/v1';

	/**
	 * Register the previous commerce routes on installations without CreatorLMS.
	 *
	 * The caller uses a separate controller so callbacks retain their namespace.
	 *
	 * @return void
	 */
	public function register_legacy_routes(): void {
		$this->namespace = 'cx-ecommerce/v1';
		$this->register_routes();
	}

	/**
	 * Check the commerce capability.
	 *
	 * Cookie authentication is verified by WordPress core (X-WP-Nonce) before any permission
	 * callback runs, so no custom nonce code is needed.
	 *
	 * @param string $code    Error code used when access is denied.
	 * @param string $message Error message used when access is denied.
	 * @return true|WP_Error
	 */
	protected function authorize( $code, $message ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Take a short, atomic lock so a double click or a retry cannot run an action twice.
	 *
	 * Uses add_option(), whose unique option_name makes the insert atomic even without an object cache.
	 * A lock older than $ttl seconds is treated as abandoned.
	 *
	 * @param string $name Lock name (letters, digits, dashes and underscores).
	 * @param int    $ttl  Seconds after which a lock is considered stale.
	 * @return bool True when the lock was taken.
	 */
	protected function acquire_lock( $name, $ttl = 60 ) {
		$key = 'ohmylms_lock_' . sanitize_key( $name );
		if ( add_option( $key, time(), '', 'no' ) ) {
			return true;
		}
		$taken = (int) get_option( $key );
		if ( $taken && ( time() - $taken ) > $ttl ) {
			update_option( $key, time(), 'no' );
			return true;
		}
		return false;
	}

	/**
	 * Release a lock taken with acquire_lock().
	 *
	 * @param string $name Lock name.
	 * @return void
	 */
	protected function release_lock( $name ) {
		delete_option( 'ohmylms_lock_' . sanitize_key( $name ) );
	}

	/**
	 * Check default permission for rest routes.
	 *
	 * @since 0.3.0
	 *
	 * @TODO: manage permissions from capabilities.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return true;
	}
}
