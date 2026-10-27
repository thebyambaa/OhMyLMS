<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hooks class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes;

/**
 * Class Hooks
 *
 * @package OhMyLMS\Integrations\GoogleMeet
 * @since 1.0.0
 */
class Hooks {
	/**
	 * Hooks constructor.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Register GoogleMeet in integrations list.
		\add_filter( 'ohmylms_integrations', array( $this, 'register_googlemeet_integration' ), 10, 1 );
		\add_filter( 'init', array( $this, 'google_meet_authentication' ), 10 );

		// Add GoogleMeet as a live class platform option.
		\add_filter( 'ohmylms_live_class_platforms', array( $this, 'add_googlemeet_platform' ) );

		// Register Sessions menu when GoogleMeet is enabled.
		\add_filter( 'ohmylms_show_sessions_menu', array( $this, 'register_session_menu' ) );

		// Enqueue scripts for GoogleMeet.
		\add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// Add GoogleMeet template.
		\add_filter( 'ohmylms_lesson_template_path', array( $this, 'add_googlemeet_template' ), 10, 2 );

		// Session lifecycle hooks.

		\add_action( 'ohmylms_googlemeet_session_created', array( $this, 'create_googlemeet_session' ), 10, 2 );
		\add_action( 'ohmylms_googlemeet_session_updated', array( $this, 'update_googlemeet_session' ), 10, 2 );
		\add_action( 'ohmylms_googlemeet_session_deleted', array( $this, 'delete_googlemeet_session' ), 10, 2 );
	}

	/**
	 * Register GoogleMeet integration in the integrations list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $integrations The existing integrations.
	 *
	 * @return array The integrations with GoogleMeet added.
	 */
	public function register_googlemeet_integration( $integrations ) {
		$integrations['googlemeet'] = array(
			'label'       => __( 'Google Meet', 'ohmylms' ),
			'description' => __( 'Integrate Google Meet for live classes and video conferencing', 'ohmylms' ),
			'icon'        => OHMYLMS_GOOGLEMEET_INTEGRATION_URL . '/includes/Integrations/GoogleMeet/Assets/Images/googlemeet-icon.svg',
			'categories'  => array( 'live-classes' ),
			'hasSettings' => true,
			'dependency'  => __( 'Requires Cohorts', 'ohmylms' ),
			'class'       => 'OhMyLMS\\Integrations\\GoogleMeet\\GoogleMeet',
			'is_valid'    => true,
		);
		return $integrations;
	}

	/**
	 * Add GoogleMeet to available platforms.
	 *
	 * @since 1.0.0
	 *
	 * @param array $platforms The existing platforms.
	 *
	 * @return array The platforms with GoogleMeet added.
	 */
	public function add_googlemeet_platform( $platforms ) {
		$platforms['googlemeet'] = array(
			'name' => __( 'Google Meet', 'ohmylms' ),
			'icon' => OHMYLMS_GOOGLEMEET_INTEGRATION_URL . '/includes/Integrations/GoogleMeet/Assets/Images/googlemeet-icon.svg',
		);
		return $platforms;
	}

	/**
	 * Register Sessions menu when GoogleMeet is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $should_show Whether to show sessions menu.
	 *
	 * @return bool Whether to show sessions menu.
	 */
	public function register_session_menu( $should_show ) {
		$integrations       = get_option( 'ohmylms_integrations' );
		$googlemeet_enabled = isset( $integrations['googlemeet']['is_enable'] ) && $integrations['googlemeet']['is_enable'];

		if ( $googlemeet_enabled ) {
			return true;
		}

		return $should_show;
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_frontend_scripts() {
		if ( is_singular( 'lesson' ) ) {
			global $post;
			$platform = get_post_meta( $post->ID, '_lesson_platform', true );

			if ( 'googlemeet' === $platform ) {
				\wp_enqueue_script(
					'ohmylms-googlemeet-content',
					OHMYLMS_GOOGLEMEET_INTEGRATION_URL . '/includes/Integrations/GoogleMeet/Assets/js/content-googlemeet.js',
					array( 'jquery' ),
					'1.0.0',
					true
				);
			}
		}
	}

	/**
	 * Add GoogleMeet template path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $template The template path.
	 * @param string $platform The platform name.
	 *
	 * @return string The template path.
	 */
	public function add_googlemeet_template( $template, $platform ) {
		if ( 'googlemeet' === $platform ) {
			$googlemeet_template = plugin_dir_path( OHMYLMS_PRO_FILE ) . 'includes/Integrations/GoogleMeet/Templates/content-googlemeet.php';
			if ( file_exists( $googlemeet_template ) ) {
				return $googlemeet_template;
			}
		}
		return $template;
	}


	/**
	 * Create a GoogleMeet session.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The post object.
	 * @param array    $request The request data.
	 *
	 * @return void
	 */
	public function create_googlemeet_session( $post, $request ) {
		$session_id = $post->ID;

		$user_id      = get_current_user_id();
		$access_token = $this->get_valid_token( $user_id );

		if ( ! $access_token ) {
			error_log( 'Google Meet create failed: No valid token.' );
			return;
		}

		// Basic event details.
		$title    = $request->get_param( 'topic' );
		$start    = $request->get_param( 'date' );
		$end      = $request->get_param( 'endDate' );
		$desc     = $request->get_param( 'agenda' );
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
		$response   = wp_remote_post(
			'https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1',
			array(
				'headers' => array(
					'Authorization' => "Bearer $access_token",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event_data ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Google Meet create failed: ' . $response->get_error_message() );
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 || ! isset( $body['id'] ) ) {
			error_log( 'Google Meet create failed (' . $status_code . '): ' . print_r( $body, true ) );
			return;
		}

		update_post_meta( $session_id, '_is_googlemeet_session_created', 'yes' );
		update_post_meta( $session_id, '_googlemeet_meeting_id', $body['id'] );

		if ( isset( $body['hangoutLink'] ) ) {
			update_post_meta( $session_id, '_googlemeet_link', $body['hangoutLink'] );
			// For Google Meet, both instructor and student use the same link
			// But we save them separately for consistency with Zoom.
			update_post_meta( $session_id, '_start_url', $body['hangoutLink'] );
			update_post_meta( $session_id, '_join_url', $body['hangoutLink'] );
		}
		if ( isset( $body['htmlLink'] ) ) {
			update_post_meta( $session_id, '_googlemeet_html_link', $body['htmlLink'] );
		}
		update_post_meta( $session_id, '_googlemeet_event_data', $body );
	}


	/**
	 * Get valid token for Hooks.
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


	/**
	 * Refresh access token for Hooks.
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
	 * Update a GoogleMeet session.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post The post object.
	 * @param array    $request The request data.
	 *
	 * @return void
	 */
	public function update_googlemeet_session( $post, $request ) {
		$session_id          = $post->ID;
		$existing_meeting_id = get_post_meta( $session_id, '_googlemeet_meeting_id', true );

		if ( ! $existing_meeting_id ) {
			error_log( 'No existing meeting ID found for update.' );
			return;
		}

		$user_id      = get_current_user_id();
		$access_token = $this->get_valid_token( $user_id );

		if ( ! $access_token ) {
			error_log( 'Google Meet update failed: No valid token.' );
			return;
		}

		// Gather fields.
		$title    = sanitize_text_field( $request->get_param( 'topic' ) );
		$start    = sanitize_text_field( $request->get_param( 'date' ) );
		$end      = sanitize_text_field( $request->get_param( 'endDate' ) );
		$desc     = sanitize_textarea_field( $request->get_param( 'agenda' ) );
		$timezone = sanitize_text_field( $request->get_param( 'timezone' ) ?: 'UTC' );

		// Prepare event payload.
		$event_data = array(
			'summary'     => $title,
			'description' => $desc,
			'start'       => array(
				'dateTime' => $start,
				'timeZone' => $timezone,
			),
			'end'         => array(
				'dateTime' => $end,
				'timeZone' => $timezone,
			),
			'reminders'   => array(
				'useDefault' => true,
			),
		);

		/**
		 * Determine if a Meet link already exists.
		 * Google returns a 400 error if you send conferenceData.createRequest
		 * on an event that already has a Meet attached.
		 */
		$existing_meet_link = get_post_meta( $session_id, '_googlemeet_link', true );

		// Include conferenceData only if no Meet exists.
		if ( empty( $existing_meet_link ) ) {
			$event_data['conferenceData'] = array(
				'createRequest' => array(
					'requestId'             => uniqid( 'meet_', true ),
					'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
				),
			);
		}

		$api_url = sprintf(
			'https://www.googleapis.com/calendar/v3/calendars/primary/events/%s?conferenceDataVersion=1',
			$existing_meeting_id
		);

		// PATCH method is correct for partial updates.
		$response = wp_remote_request(
			$api_url,
			array(
				'method'  => 'PATCH',
				'headers' => array(
					'Authorization' => "Bearer $access_token",
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event_data ),
				'timeout' => 20,
			)
		);

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			error_log( 'Google Meet update failed (' . $status_code . '): ' . print_r( $body, true ) );
			return;
		}

		// ✅ Update meta with returned data
		update_post_meta( $session_id, '_is_googlemeet_session_created', 'yes' );

		if ( isset( $body['id'] ) ) {
			update_post_meta( $session_id, '_googlemeet_meeting_id', sanitize_text_field( $body['id'] ) );
		}

		if ( isset( $body['hangoutLink'] ) ) {
			update_post_meta( $session_id, '_googlemeet_link', esc_url_raw( $body['hangoutLink'] ) );
			// For Google Meet, both instructor and student use the same link
			// But we save them separately for consistency with Zoom.
			update_post_meta( $session_id, '_start_url', esc_url_raw( $body['hangoutLink'] ) );
			update_post_meta( $session_id, '_join_url', esc_url_raw( $body['hangoutLink'] ) );
		}

		if ( isset( $body['htmlLink'] ) ) {
			update_post_meta( $session_id, '_googlemeet_html_link', esc_url_raw( $body['htmlLink'] ) );
		}
		update_post_meta( $session_id, '_googlemeet_event_data', $body );
	}



	/**
	 * Delete a GoogleMeet session.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $session_id The session ID.
	 * @param array $request The request data.
	 *
	 * @return void
	 */
	public function delete_googlemeet_session( $session_id, $request ) {
		$meeting_id = \get_post_meta( $session_id, '_googlemeet_meeting_id', true );

		if ( $meeting_id ) {
			$meeting_service = new Services\MeetingService();
			$result          = $meeting_service->delete_meeting( $meeting_id );

			if ( isset( $result['success'] ) && $result['success'] ) {
				// Clean up all GoogleMeet-related post meta.
				\delete_post_meta( $session_id, '_googlemeet_meeting_id' );
				\delete_post_meta( $session_id, '_googlemeet_link' );
				\delete_post_meta( $session_id, '_googlemeet_html_link' );
				\delete_post_meta( $session_id, '_googlemeet_event_data' );
				\delete_post_meta( $session_id, '_is_googlemeet_session_created' );
			}
		}
	}


	/**
	 * Google meet authentication for Hooks.
	 */
	public function google_meet_authentication() {
		if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) && 'googlemeet_auth' === $_GET['state'] ) {
			$code    = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			$user_id = get_current_user_id();
			$creds   = get_user_meta( $user_id, 'ohmylms_googlemeet_api_credentials', true );

			if ( empty( $creds['client_id'] ) || empty( $creds['client_secret'] ) ) {
				// Redirect back with error.
				wp_safe_redirect( admin_url( 'admin.php?page=ohmylms#/integrations?auth=error&message=missing_credentials' ) );
				exit;
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
				// Redirect back with error.
				wp_safe_redirect( admin_url( 'admin.php?page=ohmylms#/integrations?auth=error&message=oauth_failed' ) );
				exit;
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

				// Redirect back to integrations page with success.
				wp_safe_redirect( admin_url( 'admin.php?page=ohmylms#/integrations?auth=success' ) );
				exit;
			} else {
				// Redirect back with error.
				wp_safe_redirect( admin_url( 'admin.php?page=ohmylms#/integrations?auth=error&message=no_access_token' ) );
				exit;
			}
		}
	}
}
