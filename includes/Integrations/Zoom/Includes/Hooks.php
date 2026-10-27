<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\Zoom\Includes;

use OhMyLMS\Integrations\Zoom\Includes\Api\Endpoints\MeetingApi;
use OhMyLMS\Integrations\Zoom\Includes\Api\ZoomApiClient;
use OhMyLMS\Integrations\Zoom\Includes\Services\MeetingService;
use OhMyLMS\Integrations\Zoom\Includes\Services\TokenService;

/**
 * Plugin hooks implementation.
 */
class Hooks {
	/**
	 * Initialize Hooks.
	 */
	public function __construct() {
		add_filter( 'ohmylms_integrations', array( $this, 'register_zoom_integrations' ), 10, 1 );
		add_action( 'ohmylms_zoom_session_created', array( $this, 'create_zoom_session' ), 10, 2 );
		add_action( 'ohmylms_zoom_session_updated', array( $this, 'create_zoom_session' ), 10, 2 );
		add_action( 'ohmylms_zoom_session_deleted', array( $this, 'delete_zoom_session' ), 10, 2 );
	}

	/**
	 * Create a Zoom session.
	 *
	 * @param WP_Post $post The post object.
	 * @param array   $request The request data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function create_zoom_session( $post, $request ) {
		$session_id      = $post->ID;
		$token_service   = new TokenService();
		$api_client      = new ZoomApiClient( $token_service );
		$meeting_api     = new MeetingApi( $api_client );
		$meeting_service = new MeetingService( $meeting_api );

		// Prepare Zoom Data.
		$meeting_duration = ! empty( $request['duration'] ) ? intval( $request['duration'] ) : 60;
		$duration_unit    = ! empty( $request['duration_unit'] ) ? $request['duration_unit'] : 'min';
		$duration         = ( 'hr' == $duration_unit ) ? $meeting_duration * 60 : $meeting_duration;
		$start_date       = $request['date'];
		$time_zone        = $request['timezone'];
		$password         = ! empty( $request['password'] ) ? sanitize_text_field( $request['password'] ) : '';

		$host_video        = isset( $request['host_video'] ) ? (bool) $request['host_video'] : true;
		$participant_video = isset( $request['participant_video'] ) ? (bool) $request['participant_video'] : true;
		$join_before_host  = isset( $request['join_before_host'] ) ? (bool) $request['join_before_host'] : true;
		$mute_upon_entry   = isset( $request['mute_upon_entry'] ) ? (bool) $request['mute_upon_entry'] : true;

		// Auto-record: cloud for paid plans (fetchable via API), local for free.
		$auto_record    = isset( $request['auto_record'] ) ? (bool) $request['auto_record'] : false;
		$zoom_plan      = isset( $request['zoom_plan'] ) ? sanitize_text_field( $request['zoom_plan'] ) : 'free';
		$auto_recording = 'none';
		if ( $auto_record ) {
			$auto_recording = ( 'paid' === $zoom_plan ) ? 'cloud' : 'local';
		}

		$meeting_time    = ohmylms_convert_to_utc( $start_date, $time_zone );
		$meeting_details = array(
			'topic'      => $post->post_title,
			'type'       => 2,
			'start_time' => $meeting_time,
			'duration'   => $duration,
			'timezone'   => $time_zone,
			'agenda'     => wp_strip_all_tags( $post->post_content ),
			'password'   => $password,
			'settings'   => array(
				'host_video'        => $host_video,
				'participant_video' => $participant_video,
				'join_before_host'  => $join_before_host,
				'mute_upon_entry'   => $mute_upon_entry,
				'auto_recording'    => $auto_recording,
			),
		);
		$zoom_user_id    = 'me';
		try {
			$meeting_data = get_post_meta( $session_id, '_zoom_meeting_data', true );
			$meeting_data = json_decode( $meeting_data, true );
			if ( $meeting_data && isset( $meeting_data['id'] ) ) {
				$response = $meeting_service->update_meeting( $meeting_data['id'], $meeting_details );
			} else {
				$response = $meeting_service->create_meeting( $zoom_user_id, $meeting_details );
			}
			if ( ( isset( $response['code'] ) && 400 == $response['code'] ) || ( isset( $response['success'], $response['code'] ) && ! $response['success'] && 204 !== $response['code'] ) ) {
				update_post_meta( $session_id, '_is_zoom_session_created', 'no' );
				delete_post_meta( $session_id, '_zoom_meeting_data' );
				// remove user token meta for invalid token.
				delete_user_meta( get_current_user_id(), 'clms_zoom_access_token' );
				delete_user_meta( get_current_user_id(), 'clms_zoom_token_expires' );
				return;
			}

			if ( isset( $response['code'] ) && 204 == $response['code'] ) {
				$response = $meeting_service->get_meeting( $meeting_data['id'] );
			}

			if ( isset( $response['data'] ) && ! empty( $response['data'] ) ) {
				$encoded_meeting_data = wp_json_encode( $response['data'] );
				if ( false !== $encoded_meeting_data ) {
					update_post_meta( $session_id, '_zoom_meeting_data', $encoded_meeting_data );
				} else {
					error_log( 'OhMyLMS Zoom: failed to json_encode meeting data for session ' . $session_id . ': ' . json_last_error_msg() );
				}
			}
			update_post_meta( $session_id, '_is_zoom_session_created', 'yes' );
			update_post_meta( $session_id, '_type', 2 );
			update_post_meta( $session_id, '_password', $request['password'] );
			update_post_meta( $session_id, '_start_date', $request['date'] );
			update_post_meta( $session_id, '_session_type', $request['type'] );
			update_post_meta( $session_id, '_timezone', $request['timezone'] );
			update_post_meta( $session_id, '_platform', $request['platform'] );
			update_post_meta( $session_id, '_duration', $request['duration'] );

			update_post_meta( $session_id, '_host_video', $request['host_video'] );
			update_post_meta( $session_id, '_participant_video', $request['participant_video'] );
			update_post_meta( $session_id, '_join_before_host', $request['join_before_host'] );
			update_post_meta( $session_id, '_mute_upon_entry', $request['mute_upon_entry'] );
		} catch ( Exception $e ) {
			error_log( print_r( $e, true ) );
		}
	}

	/**
	 * Delete a Zoom session.
	 *
	 * @param int   $session_id The session ID.
	 * @param array $request The request data.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function delete_zoom_session( $session_id, $request ) {
		$meeting_data = get_post_meta( $session_id, '_zoom_meeting_data', true );
		$meeting_data = json_decode( $meeting_data, true );
		if ( $meeting_data && isset( $meeting_data['id'] ) ) {
			$token_service   = new TokenService();
			$api_client      = new ZoomApiClient( $token_service );
			$meeting_api     = new MeetingApi( $api_client );
			$meeting_service = new MeetingService( $meeting_api );
			$meeting_service->delete_meeting( $meeting_data['id'] );
		}
	}

	/**
	 * Register zoom integrations for Hooks.
	 *
	 * @param mixed $integrations Integrations.
	 */
	public function register_zoom_integrations( $integrations ) {
		$integrations['zoom'] = array(
			'label'       => __( 'Zoom', 'ohmylms' ),
			'icon'        => OHMYLMS_PRO_URL . '/includes/Integrations/Zoom/Assets/Images/zoom-icon.svg',
			'description' => __( 'Integrate Zoom to host live, interactive classes directly within your LMS for better student engagement.', 'ohmylms' ),
			'categories'  => array( 'live-classes' ),
			'hasSettings' => true,
			'class'       => 'OhMyLMS\Integrations\Zoom',
			'dependency'  => __( 'Requires Cohorts', 'ohmylms' ),
			'is_valid'    => true,

		);
		return $integrations;
	}
}
