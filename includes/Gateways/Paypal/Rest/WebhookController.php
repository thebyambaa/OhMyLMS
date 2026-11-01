<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * PayPal webhook REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gateways\Paypal\Rest;

use WP_REST_Controller;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `ohmylms/v1/paypal-webhook`: PayPal's deliveries to the gateway that registered it.
 *
 * The route is public because PayPal calls it unauthenticated. The gateway rejects every method but
 * POST and verifies each delivery with PayPal's verify-webhook-signature API before trusting the body.
 */
class WebhookController extends WP_REST_Controller {

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
	protected $rest_base = 'paypal-webhook';

	/**
	 * Gateway that handles the deliveries.
	 *
	 * @var \OhMyLMS\Gateways\Paypal\GatewayPaypal
	 */
	private $gateway;

	/**
	 * Constructor.
	 *
	 * @param \OhMyLMS\Gateways\Paypal\GatewayPaypal $gateway Gateway that verifies and applies the deliveries.
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
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'handle_webhook' ),
				// PayPal calls this unauthenticated; the gateway verifies the signature.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Pass the delivery to the gateway.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		return $this->gateway->handle_webhook( $request );
	}
}
