<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ZoomWebhookController class.
 *
 * Receives Zoom's `recording.completed` event and auto-attaches the cloud
 * recording to the matching session lesson, so instructors on a paid/cloud
 * plan don't have to attach the replay by hand.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\Zoom\Includes\Rest;

use OhMyLMS\Integrations\Zoom\Includes\Helpers\ZoomApiHelper;
use OhMyLMS\Integrations\Zoom\Includes\Services\RecordingAttachService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class ZoomWebhookController
 *
 * @package OhMyLMS\Integrations\Zoom\Includes\Rest
 * @since 1.0.0
 */
class ZoomWebhookController extends WP_REST_Controller {
	/**
	 * The single instance of the class.
	 *
	 * @var ZoomWebhookController
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Path
	 *
	 * @var string
	 */
	protected $base = 'zoom/webhook';

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Get instance
	 *
	 * @since 1.0.0
	 *
	 * @return ZoomWebhookController
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register routes
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				// Zoom calls this unauthenticated; the payload is verified
				// via HMAC signature (or the URL-validation handshake) below.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle an incoming Zoom webhook request.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$raw_body = $request->get_body();
		$payload  = json_decode( $raw_body, true );

		if ( ! is_array( $payload ) || empty( $payload['event'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid payload.' ), 400 );
		}

		$secret_token = ZoomApiHelper::get_webhook_secret_token();

		// Zoom's one-time endpoint validation handshake, required before it
		// will start sending real events. Uses the secret token but has no
		// signature to verify against.
		if ( 'endpoint.url_validation' === $payload['event'] ) {
			$plain_token = $payload['payload']['plainToken'] ?? '';
			if ( ! $secret_token || ! $plain_token ) {
				return new WP_REST_Response( array( 'message' => 'Webhook secret token is not configured.' ), 400 );
			}
			return new WP_REST_Response(
				array(
					'plainToken'     => $plain_token,
					'encryptedToken' => hash_hmac( 'sha256', $plain_token, $secret_token ),
				),
				200
			);
		}

		if ( ! $this->verify_signature( $request, $raw_body, $secret_token ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid signature.' ), 401 );
		}

		if ( 'recording.completed' === $payload['event'] ) {
			RecordingAttachService::attach( $payload['payload']['object'] ?? array() );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Verify Zoom's HMAC request signature.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request      The request object.
	 * @param string          $raw_body     The raw request body.
	 * @param string|false    $secret_token The configured webhook secret token.
	 *
	 * @return bool
	 */
	private function verify_signature( WP_REST_Request $request, $raw_body, $secret_token ) {
		if ( ! $secret_token ) {
			return false;
		}

		$signature = $request->get_header( 'x-zm-signature' );
		$timestamp = $request->get_header( 'x-zm-request-timestamp' );

		if ( ! $signature || ! $timestamp ) {
			return false;
		}

		$expected = 'v0=' . hash_hmac( 'sha256', "v0:{$timestamp}:{$raw_body}", $secret_token );

		return hash_equals( $expected, $signature );
	}
}
