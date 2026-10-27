<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Ajax class.
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\GoogleMeet\Includes;

use OhMyLMS\Integrations\GoogleMeet\Includes\Services\MeetingService;

/**
 * Class Ajax
 *
 * @package OhMyLMS\Integrations\GoogleMeet
 * @since 1.0.0
 */
class Ajax {
	/**
	 * Ajax constructor.
	 */
	public function __construct() {
		$this->init_ajax_handlers();
	}

	/**
	 * Initialize AJAX handlers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_ajax_handlers() {
		\add_action( 'wp_ajax_ohmylms_create_googlemeet', array( $this, 'create_meeting' ) );
		\add_action( 'wp_ajax_ohmylms_update_googlemeet', array( $this, 'update_meeting' ) );
		\add_action( 'wp_ajax_ohmylms_delete_googlemeet', array( $this, 'delete_meeting' ) );
		\add_action( 'wp_ajax_ohmylms_get_googlemeet', array( $this, 'get_meeting' ) );
	}

	/**
	 * Create GoogleMeet meeting via AJAX.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function create_meeting() {
		\check_ajax_referer( 'ohmylms_nonce', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => __( 'Unauthorized', 'ohmylms' ) ), 403 );
		}

		$meeting_data = array(
			'topic'    => \sanitize_text_field( $_POST['topic'] ?? '' ),
			'agenda'   => \sanitize_textarea_field( $_POST['agenda'] ?? '' ),
			'date'     => \sanitize_text_field( $_POST['date'] ?? '' ),
			'duration' => intval( $_POST['duration'] ?? 60 ),
			'timezone' => \sanitize_text_field( $_POST['timezone'] ?? 'UTC' ),
		);

		$meeting_service = new MeetingService();
		$result          = $meeting_service->create_meeting( $meeting_data );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * Update GoogleMeet meeting via AJAX.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function update_meeting() {
		\check_ajax_referer( 'ohmylms_nonce', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => __( 'Unauthorized', 'ohmylms' ) ), 403 );
		}

		$meeting_id   = \sanitize_text_field( $_POST['meeting_id'] ?? '' );
		$meeting_data = array(
			'topic'    => \sanitize_text_field( $_POST['topic'] ?? '' ),
			'agenda'   => \sanitize_textarea_field( $_POST['agenda'] ?? '' ),
			'date'     => \sanitize_text_field( $_POST['date'] ?? '' ),
			'duration' => intval( $_POST['duration'] ?? 60 ),
			'timezone' => \sanitize_text_field( $_POST['timezone'] ?? 'UTC' ),
		);

		$meeting_service = new MeetingService();
		$result          = $meeting_service->update_meeting( $meeting_id, $meeting_data );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * Delete GoogleMeet meeting via AJAX.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function delete_meeting() {
		\check_ajax_referer( 'ohmylms_nonce', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => __( 'Unauthorized', 'ohmylms' ) ), 403 );
		}

		$meeting_id = \sanitize_text_field( $_POST['meeting_id'] ?? '' );

		$meeting_service = new MeetingService();
		$result          = $meeting_service->delete_meeting( $meeting_id );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}

	/**
	 * Get GoogleMeet meeting via AJAX.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function get_meeting() {
		\check_ajax_referer( 'ohmylms_nonce', 'nonce' );

		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_send_json_error( array( 'message' => __( 'Unauthorized', 'ohmylms' ) ), 403 );
		}

		$meeting_id = \sanitize_text_field( $_GET['meeting_id'] ?? '' );

		$meeting_service = new MeetingService();
		$result          = $meeting_service->get_meeting( $meeting_id );

		if ( $result['success'] ) {
			\wp_send_json_success( $result );
		} else {
			\wp_send_json_error( $result );
		}
	}
}
