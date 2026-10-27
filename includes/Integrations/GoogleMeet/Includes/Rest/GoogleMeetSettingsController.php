<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * GoogleMeetSettingsController class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Plugin google meet settings controller implementation.
 */
class GoogleMeetSettingsController {
	/**
	 * Instance.
	 *
	 * @var mixed
	 */
	protected static $instance = null;
	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'googlemeet/settings';
	/**
	 * Namespace.
	 *
	 * @var mixed
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Instance for Google meet settings controller.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register routes for Google meet settings controller.
	 */
	public function register_routes() {
		// Save & Get credentials.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/credentials',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_credentials' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => $this->get_save_credentials_args(),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_credentials' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);

		// OAuth callback.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/oauth-callback',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_oauth_callback' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => array(
						'code' => array(
							'description' => __( 'Authorization code', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);

		// Get Google OAuth2 Auth URL.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/oauth-url',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_auth_url' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);

		// Create meeting.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/create-meeting',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_meeting' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => array(
						'title'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'start'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'end'         => array(
							'type'     => 'string',
							'required' => true,
						),
						'description' => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
			)
		);

		// Update meeting.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/update-meeting',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_meeting' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => array(
						'event_id'    => array(
							'type'     => 'string',
							'required' => true,
						),
						'title'       => array(
							'type'     => 'string',
							'required' => false,
						),
						'start'       => array(
							'type'     => 'string',
							'required' => false,
						),
						'end'         => array(
							'type'     => 'string',
							'required' => false,
						),
						'description' => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Admin permission for Google meet settings controller.
	 */
	public function admin_permission() {
		return \current_user_can( 'manage_options' );
	}

	// -------------------------
	// SETTINGS METHODS
	// -------------------------
	/**
	 * Save credentials for Google meet settings controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function save_credentials( WP_REST_Request $request ) {
		$client_id     = \sanitize_text_field( $request->get_param( 'client_id' ) );
		$client_secret = \sanitize_text_field( $request->get_param( 'client_secret' ) );
		$redirect_url  = \sanitize_text_field( $request->get_param( 'redirect_url' ) );

		$user_id  = \get_current_user_id();
		$settings = array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'redirect_url'  => $redirect_url,
		);
		\update_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', $settings );
		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Settings saved successfully', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Get credentials for Google meet settings controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_credentials( $request ) {
		$user_id            = \get_current_user_id();
		$settings           = \get_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', true );
		$tokens             = \get_user_meta( $user_id, 'ohmylms_googlemeet_tokens', true );
		$settings['tokens'] = $tokens;
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $settings ? $settings : array(),
			),
			200
		);
	}

	// -------------------------
	// OAUTH HANDLING
	// -------------------------
	/**
	 * Get auth url for Google meet settings controller.
	 */
	public function get_auth_url() {
		$user_id     = \get_current_user_id();
		$credentials = \get_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', true );

		if ( empty( $credentials ) || empty( $credentials['client_id'] ) || empty( $credentials['redirect_url'] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Client credentials or redirect URI not found', 'ohmylms' ),
				),
				400
			);
		}

		// Google OAuth parameters.
		$params = array(
			'access_type'   => 'offline',
			'scope'         => implode(
				' ',
				array(
					'https://www.googleapis.com/auth/calendar',
					'https://www.googleapis.com/auth/userinfo.email',
					'openid',
				)
			),
			'response_type' => 'code',
			'redirect_uri'  => $credentials['redirect_url'],
			'prompt'        => 'consent',
			'flowName'      => 'GeneralOAuthFlow',
			'client_id'     => $credentials['client_id'],
			'state'         => 'googlemeet_auth',
		);

		$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
		return new \WP_REST_Response(
			array(
				'success'  => true,
				'auth_url' => $auth_url,
			),
			200
		);
	}

	/**
	 * Handle oauth callback for Google meet settings controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function handle_oauth_callback( WP_REST_Request $request ) {
		$code    = $request->get_param( 'code' );
		$user_id = get_current_user_id();
		$creds   = get_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', true );

		if ( empty( $creds['client_id'] ) || empty( $creds['client_secret'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing client credentials',
				),
				400
			);
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $creds['client_id'],
					'client_secret' => $creds['client_secret'],
					'redirect_uri'  => $creds['redirect_url'],
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['access_token'] ) ) {
			update_user_meta(
				$user_id,
				'ohmylms_googlemeet_tokens',
				array(
					'access_token'  => $body['access_token'],
					'refresh_token' => $body['refresh_token'] ?? '',
					'expires_in'    => time() + ( $body['expires_in'] ?? 3600 ),
				)
			);

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Google account connected successfully!',
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => 'Failed to get access token',
				'error'   => $body,
			),
			400
		);
	}

	// -------------------------
	// TOKEN HELPER
	// -------------------------
	/**
	 * Refresh access token for Google meet settings controller.
	 *
	 * @param mixed $user_id User identifier.
	 */
	private function refresh_access_token( $user_id ) {
		$creds  = get_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', true );
		$tokens = get_user_meta( $user_id, 'ohmylms_googlemeet_tokens', true );

		if ( empty( $tokens['refresh_token'] ) ) {
			return false;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'client_id'     => $creds['client_id'],
					'client_secret' => $creds['client_secret'],
					'refresh_token' => $tokens['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['access_token'] ) ) {
			$tokens['access_token'] = $body['access_token'];
			$tokens['expires_in']   = time() + ( $body['expires_in'] ?? 3600 );
			update_user_meta( $user_id, 'ohmylms_googlemeet_tokens', $tokens );
			return $tokens['access_token'];
		}

		return false;
	}

	/**
	 * Get valid token for Google meet settings controller.
	 *
	 * @param mixed $user_id User identifier.
	 */
	private function get_valid_token( $user_id ) {
		$tokens = get_user_meta( $user_id, 'ohmylms_googlemeet_tokens', true );
		if ( empty( $tokens['access_token'] ) ) {
			return false;
		}

		if ( time() > ( $tokens['expires_in'] ?? 0 ) ) {
			return $this->refresh_access_token( $user_id );
		}

		return $tokens['access_token'];
	}

	// -------------------------
	// MEETING METHODS
	// -------------------------
	/**
	 * Create meeting for Google meet settings controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function create_meeting( WP_REST_Request $request ) {
		$user_id      = get_current_user_id();
		$access_token = $this->get_valid_token( $user_id );

		if ( ! $access_token ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No valid access token found.',
				),
				401
			);
		}

		// Basic event details.
		$title    = $request->get_param( 'title' );
		$start    = $request->get_param( 'start' );
		$end      = $request->get_param( 'end' );
		$desc     = $request->get_param( 'description' );
		$timezone = $request->get_param( 'timezone' ) ?: 'UTC';

		$event_data = array(
			'summary'        => $title,
			'description'    => $desc,
			'start'          => array(
				'dateTime' => $start,
				'timeZone' => $timezone,
			),
			'end'            => array(
				'dateTime' => $end,
				'timeZone' => $timezone,
			),
			'reminders'      => array( 'useDefault' => true ),
			'conferenceData' => array(
				'createRequest' => array(
					'requestId'             => uniqid(),
					'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
				),
			),
		);

		$response = wp_remote_post(
			'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1',
			array(
				'headers' => array(
					'Authorization' => "Bearer $access_token",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event_data ),
			)
		);

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['hangoutLink'] ) ) {
			return new WP_REST_Response(
				array(
					'success'     => true,
					'meet_link'   => $body['hangoutLink'],
					'event_id'    => $body['id'],
					'calendar_id' => $body['organizer']['email'] ?? '',
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => $body,
			),
			400
		);
	}

	/**
	 * Update meeting for Google meet settings controller.
	 *
	 * @param WP_REST_Request $request REST request.
	 */
	public function update_meeting( WP_REST_Request $request ) {
		$user_id      = get_current_user_id();
		$access_token = $this->get_valid_token( $user_id );
		if ( ! $access_token ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'No valid access token found.',
				),
				401
			);
		}

		$event_id = $request->get_param( 'event_id' );
		$data     = array();

		foreach ( array(
			'title'       => 'summary',
			'start'       => 'start',
			'end'         => 'end',
			'description' => 'description',
		) as $param => $field ) {
			if ( $request->get_param( $param ) ) {
				if ( in_array( $param, array( 'start', 'end' ), true ) ) {
					$timezone       = $request->get_param( 'timezone' ) ?: 'UTC';
					$data[ $field ] = array(
						'dateTime' => $request->get_param( $param ),
						'timeZone' => $timezone,
					);
				} else {
					$data[ $field ] = $request->get_param( $param );
				}
			}
		}

		$response = wp_remote_request(
			'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id . '?conferenceDataVersion=1',
			array(
				'method'  => 'PATCH',
				'headers' => array(
					'Authorization' => "Bearer $access_token",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['error'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $body['error'],
				),
				400
			);
		}

		// Return meet link if available.
		$meet_link = $body['hangoutLink'] ?? '';
		return new WP_REST_Response(
			array(
				'success'   => true,
				'data'      => $body,
				'meet_link' => $meet_link,
			),
			200
		);
	}

	/**
	 * Get save credentials args for Google meet settings controller.
	 */
	public function get_save_credentials_args() {
		return array(
			'client_id'     => array(
				'type'     => 'string',
				'required' => true,
			),
			'client_secret' => array(
				'type'     => 'string',
				'required' => true,
			),
			'redirect_url'  => array(
				'type'     => 'string',
				'required' => true,
			),
		);
	}
}
