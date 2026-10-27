<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * NotificationController class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_Query;

/**
 * NotificationController class.
 *
 * Handles REST API endpoints for notification.
 *
 * @since 1.0.0
 */
class NotificationController extends RestController {

	/**
	 * The base route for notification endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'notification';

	/**
	 * Register the routes for the notification endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/course/(?P<course_id>[\d]+)/student/(?P<student_id>[\d]+)',
			array(
				'args' => array(
					'student_id' => array(
						'description' => __( 'Unique identifier for the notification.', 'ohmylms' ),
						'type'        => 'integer',
					),
					'course_id'  => array(
						'description' => __( 'Unique identifier for the notification.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'send_reminder' ),
					'permission_callback' => array( $this, 'check_notification_permission' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);
	}

	/**
	 * Send reminder to student.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 * @since 1.0.0
	 */
	public function send_reminder( $request ) {
		$student_id = absint( $request['student_id'] );

		if ( ! $student_id ) {
			return new \WP_Error(
				'rest_invalid_student',
				__( 'Invalid student ID.', 'ohmylms' ),
				array( 'status' => 400 )
			);
		}

		$course_id = absint( $request['course_id'] );

		if ( ! $course_id ) {
			return new \WP_Error(
				'rest_invalid_course',
				__( 'Invalid course ID.', 'ohmylms' ),
				array( 'status' => 400 )
			);
		}

		// Validate required parameters.
		$required_params = array( 'email', 'subject', 'message' );
		foreach ( $required_params as $param ) {
			if ( empty( $request[ $param ] ) ) {
				return new \WP_Error(
					'rest_missing_param',
					sprintf( /* translators: %s: text value. */
					__( 'Missing parameter: %s', 'ohmylms' ), $param ),
					array( 'status' => 400 )
				);
			}
		}

		$email   = sanitize_email( $request['email'] );
		$subject = sanitize_text_field( $request['subject'] );
		$message = wp_kses_post( $request['message'] );
		// Set up email headers for HTML content.
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);

		// Send email with HTML formatting.
		$sent = wp_mail( $email, $subject, $message, $headers );

		if ( ! $sent ) {
			return new \WP_Error(
				'rest_email_failed',
				__( 'Failed to send reminder email.', 'ohmylms' ),
				array( 'status' => 500 )
			);
		}

		$save_result = $this->save_notification_record(
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
				'email'      => $email,
				'subject'    => $subject,
				'message'    => $message,
			)
		);

		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return new \WP_REST_Response(
			array(
				'message' => __( 'Reminder sent successfully', 'ohmylms' ),
				'status'  => true,
			),
			200
		);
	}

	/**
	 * Save notification record to database.
	 *
	 * @param array $data Notification data.
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	private function save_notification_record( $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ohmylms_notifications';

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'student_id' => $data['student_id'],
				'course_id'  => $data['course_id'],
				'email'      => $data['email'],
				'subject'    => $data['subject'],
				'message'    => $data['message'],
				'status'     => 'sent',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new \WP_Error(
				'rest_notification_save_failed',
				__( 'Failed to save notification record.', 'ohmylms' ),
				array( 'status' => 500 )
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Check notification permission for Notification controller.
	 */
	public function check_notification_permission() {
		return current_user_can( 'edit_posts' );
	}
}
