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
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class ZoomWebhookController
 *
 * @package OhMyLMS\Integrations\Zoom\Includes\Rest
 * @since 1.0.0
 */
class ZoomWebhookController {
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
			$this->handle_recording_completed( $payload['payload']['object'] ?? array() );
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

	/**
	 * Find the matching session lesson and attach the cloud recording.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meeting_object The webhook `payload.object` (the Zoom meeting).
	 *
	 * @return void
	 */
	private function handle_recording_completed( $meeting_object ) {
		$meeting_id = $meeting_object['id'] ?? null;
		$files      = $meeting_object['recording_files'] ?? array();

		if ( ! $meeting_id || empty( $files ) ) {
			return;
		}

		$play_url = $this->pick_replay_url( $files );
		if ( ! $play_url ) {
			return;
		}

		$session_id = $this->find_session_by_meeting_id( $meeting_id );
		if ( ! $session_id ) {
			return;
		}

		// Don't clobber a recording the instructor already attached by hand.
		if ( 'attached' === get_post_meta( $session_id, '_recording_state', true ) ) {
			return;
		}

		update_post_meta( $session_id, '_recording_source', 'Zoom' );
		update_post_meta( $session_id, '_recording_url', esc_url_raw( $play_url ) );
		update_post_meta( $session_id, '_recording_state', 'attached' );
	}

	/**
	 * Pick the best playable URL out of a meeting's recording files, preferring
	 * the composite gallery/speaker-view video over screen-share-only or audio-only tracks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $files The `recording_files` array from the webhook payload.
	 *
	 * @return string
	 */
	private function pick_replay_url( $files ) {
		$preferred = null;
		$fallback  = null;

		foreach ( $files as $file ) {
			if ( 'MP4' !== ( $file['file_type'] ?? '' ) ) {
				continue;
			}
			$url = $file['play_url'] ?? ( $file['download_url'] ?? '' );
			if ( ! $url ) {
				continue;
			}
			if ( 'shared_screen_with_speaker_view' === ( $file['recording_type'] ?? '' ) ) {
				$preferred = $url;
				break;
			}
			if ( ! $fallback ) {
				$fallback = $url;
			}
		}

		return $preferred ? $preferred : $fallback;
	}

	/**
	 * Find the session post whose stored Zoom meeting matches the given ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int|string $meeting_id The Zoom meeting ID from the webhook payload.
	 *
	 * @return int|null
	 */
	private function find_session_by_meeting_id( $meeting_id ) {
		global $wpdb;

		$like = '%"id":' . (int) $meeting_id . '%';

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_zoom_meeting_data' AND meta_value LIKE %s LIMIT 1",
				$like
			)
		);

		return $post_id ? (int) $post_id : null;
	}
}
