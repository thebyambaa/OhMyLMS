<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * NotificationController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_Error;

/**
 * Retired reminder route `notification/course/{course_id}/student/{student_id}`.
 *
 * It mailed any caller-supplied address, subject and HTML body (an open mail relay for every
 * `edit_posts` user). Reminders now go through `POST /analytics/courses/{id}/reminders`
 * (CourseRosterController and Analytics\Reminders): recipients come from enrolments and the text is a
 * fixed template. The route stays registered so old screens get a clear 410 instead of a 404.
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
	 * Register the retired route.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/course/(?P<course_id>[\d]+)/student/(?P<student_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'send_reminder' ),
					'permission_callback' => array( $this, 'check_notification_permission' ),
				),
			)
		);
	}

	/**
	 * Answer that the route was replaced; nothing is sent.
	 *
	 * @return WP_Error
	 * @since 1.0.0
	 */
	public function send_reminder() {
		return new WP_Error(
			'ohmylms_reminder_route_retired',
			__( 'This reminder route was retired. Send reminders from the course roster.', 'ohmylms' ),
			array(
				'status'      => 410,
				'replacement' => '/ohmylms/v1/analytics/courses/{id}/reminders',
			)
		);
	}

	/**
	 * Only users who may send reminders learn that the route moved.
	 *
	 * @return true|WP_Error
	 */
	public function check_notification_permission() {
		return StudentController::authorize( 'ohmylms_reminder_cannot_send', __( 'Sorry, you are not allowed to send reminders.', 'ohmylms' ) );
	}
}
