<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * QPay payment callback REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Gateways\QPay\Rest;

use WP_REST_Controller;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/qpay/callback`: QPay's payment return to the gateway that registered it.
 *
 * The route is public because QPay calls it unauthenticated. The gateway checks the order's callback
 * token and settles only after verifying the payment with QPay server to server.
 */
class CallbackController extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'qpay/callback';

	/**
	 * Gateway that handles the callbacks.
	 *
	 * @var \OhMyLMS\Commerce\Gateways\QPay\GatewayQPay
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param \OhMyLMS\Commerce\Gateways\QPay\GatewayQPay $gateway Gateway that verifies and settles payments.
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_callback' ),
				// QPay calls this unauthenticated; the gateway checks the token and verifies the payment.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Pass the callback to the gateway.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_callback( $request ) {
		return $this->gateway->handle_callback( $request );
	}
}
