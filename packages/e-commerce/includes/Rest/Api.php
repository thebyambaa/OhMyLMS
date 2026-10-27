<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Rest;

use OhMyLMS\Commerce\Rest\V1\CouponController;
use OhMyLMS\Commerce\Rest\V1\OrderRefundController;
use OhMyLMS\Commerce\Rest\V1\OrdersController;
use OhMyLMS\Commerce\Rest\V1\SubscriptionController;

/**
 * Plugin api implementation.
 */
class Api {

	/**
	 * Class dir and class name mapping.
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 */
	protected $class_map;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		$controllers_v1 = array(
			OrderRefundController::class,
			CouponController::class,
			OrdersController::class,
			SubscriptionController::class,
		);

		$controllers_v2 = array();

		$this->class_map = array_merge( $controllers_v1, $controllers_v2 );

		// Init REST API routes.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		foreach ( $this->class_map as $controller_class ) {
			$controller_instance = new $controller_class();
			$controller_instance->register_routes();
			if ( ! defined( 'CRLMS_FILE' ) && ! class_exists( 'CreatorLms', false ) ) {
				$legacy_controller = new $controller_class();
				$legacy_controller->register_legacy_routes();
			}
		}
	}
}
