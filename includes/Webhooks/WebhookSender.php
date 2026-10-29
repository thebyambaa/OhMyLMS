<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class WebhookSender.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Webhooks;

use OhMyLMS\Data\Webhook;
use OhMyLMS\DataStores\WebhookStore;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookSender
 *
 * Handles sending webhook notifications based on triggers.
 * Similar to how emails are sent in the system.
 *
 * @package OhMyLMS\Webhooks
 * @since 1.0.0
 */
class WebhookSender {

	/**
	 * Initialize webhook triggers
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Course related triggers.
		add_action( 'ohmylms_after_checkout_process', array( $this, 'trigger_course_purchase' ), 10 );
		add_action( 'ohmylms_after_enrolled_student', array( $this, 'trigger_course_enrollment' ), 10, 1 );
		add_action( 'ohmylms_lesson_already_completed', array( $this, 'trigger_course_completion' ), 10, 2 );
		// add_action( 'ohmylms_student_completed_course_after_reviewing_quiz', array( $this, 'trigger_course_completion' ), 10, 2 );.
		add_action( 'ohmylms_course_completed', array( $this, 'trigger_course_completion' ), 10, 2 );

		// Lesson related triggers.
		add_action( 'ohmylms_lesson_completed', array( $this, 'trigger_lesson_completion' ), 10, 3 );

		// Quiz related triggers.
		add_action( 'ohmylms_quiz_submission', array( $this, 'trigger_quiz_submission' ), 10, 4 );

		// Quiz achievement after review.
		add_action( 'ohmylms_rest_review_quiz_attempt', array( $this, 'trigger_quiz_achievement' ), 10, 4 );

		// Assignment related triggers.
		add_action( 'ohmylms_after_assignment_submitted', array( $this, 'trigger_assignment_submission' ), 10, 3 );

		add_action( 'ohmylms_pro_after_assignment_review', array( $this, 'trigger_assignment_achievement' ), 10, 4 );
	}

	/**
	 * Trigger webhook for course purchase
	 *
	 * @param int $order _id   Order ID.
	 * @since 1.0.0
	 */
	public function trigger_course_purchase( $order ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'course_purchase' );
		if ( empty( $webhooks ) ) {
			return;
		}
		if ( ! is_object( $order ) ) {
			return;
		}

		$student_id  = $order->get_student_id();
		$order_id    = $order->get_id();
		$order_items = $order ? $order->get_items() : array();

		foreach ( $order_items as $item ) {
			$course_id = $item->get_course_id();
			$course    = ohmylms_get_course( $course_id );
			if ( ! $course ) {
				continue;
			}

			$user = get_user_by( 'id', $student_id );
			if ( ! $user || ! $course ) {
				return;
			}
			$student      = new \OhMyLMS\Data\Student( $student_id );
			$payload_data = array(
				'user_id'      => $student_id,
				'user_email'   => $student->get_email(),
				'user_name'    => $student->get_name(),
				'course_id'    => $course_id,
				'course_title' => $course->get_name(),
				'course_price' => $course->get_sale_price() ?: $course->get_regular_price(),
				'order_id'     => $order_id,
				'order_total'  => $order ? $order->get_total() : 0,
				'event_time'   => current_time( 'mysql' ),
				'site_url'     => get_site_url(),
			);

			foreach ( $webhooks as $webhook ) {
				$this->send_webhook( $webhook, $payload_data );
			}
		}
	}

	/**
	 * Trigger webhook for course enrollment
	 *
	 * @param object|int $order Order object or ID.
	 * .
	 * @since 1.0.0
	 */
	public function trigger_course_enrollment( $order ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'course_enrollment' );

		if ( empty( $webhooks ) ) {
			return;
		}

		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = function_exists( 'ohmylms_ecommerce_get_order' ) ? ohmylms_ecommerce_get_order( $order_id ) : null;
		}

		if ( ! is_object( $order ) ) {
			return;
		}

		$student_id = $order->get_student_id();
		$user       = get_user_by( 'id', $student_id );

		if ( ! $user ) {
			return;
		}

		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d LIMIT 1', $table_name, $student_id, $order->get_id() ),
			ARRAY_A
		);

		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}

		$course = ohmylms_get_course( $enroll_data['course_id'] );

		if ( ! $course ) {
			return;
		}
		$student      = new \OhMyLMS\Data\Student( $student_id );
		$payload_data = array(
			'user_id'         => $student_id,
			'user_email'      => $student->get_email(),
			'user_name'       => $student->get_name(),
			'course_id'       => $course->get_id(),
			'course_title'    => $course->get_name(),
			'enrollment_date' => $enroll_data['enrollment_date'] ?? current_time( 'mysql' ),
			'event_time'      => current_time( 'mysql' ),
			'site_url'        => get_site_url(),
		);

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $payload_data );
		}
	}

	/**
	 * Trigger webhook for course completion
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @since 1.0.0
	 */
	public function trigger_course_completion( $student_id, $course_id ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'course_completion' );
		if ( empty( $webhooks ) ) {
			return;
		}

		$user   = get_user_by( 'id', $student_id );
		$course = ohmylms_get_course( $course_id );

		if ( ! $user || ! $course ) {
			return;
		}

		// Get completion percentage.
		$completion_percentage = 100; // Default to 100% on completion.
		$student               = new \OhMyLMS\Data\Student( $student_id );
		$payload_data          = array(
			'user_id'               => $student_id,
			'user_email'            => $student->get_email(),
			'user_name'             => $student->get_name(),
			'course_id'             => $course_id,
			'course_title'          => $course->get_name(),
			'completion_date'       => current_time( 'mysql' ),
			'completion_percentage' => $completion_percentage,
			'event_time'            => current_time( 'mysql' ),
			'site_url'              => get_site_url(),
		);

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $payload_data );
		}
	}

	/**
	 * Trigger webhook for lesson completion
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @param int $student_id Student ID.
	 * .
	 * @since 1.0.0
	 */
	public function trigger_lesson_completion( $lesson_id, $course_id, $student_id ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'lesson_completion' );
		if ( empty( $webhooks ) ) {
			return;
		}

		$user   = get_user_by( 'id', $student_id );
		$course = ohmylms_get_course( $course_id );
		$lesson = get_post( $lesson_id );

		if ( ! $user || ! $course || ! $lesson ) {
			return;
		}
		$student      = new \OhMyLMS\Data\Student( $student_id );
		$payload_data = array(
			'user_id'      => $student_id,
			'user_email'   => $student->get_email(),
			'user_name'    => $student->get_name(),
			'lesson_id'    => $lesson_id,
			'lesson_title' => $lesson->post_title,
			'course_id'    => $course_id,
			'course_title' => $course->get_name(),
			'event_time'   => current_time( 'mysql' ),
			'site_url'     => get_site_url(),
		);

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $payload_data );
		}
	}

	/**
	 * Trigger webhook for quiz submission
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param int   $course_id Course ID.
	 * @param int   $student_id Student ID.
	 * @param array $result Quiz result data.
	 * .
	 * @since 1.0.0
	 */
	public function trigger_quiz_submission( $quiz_id, $course_id, $student_id, $result = array() ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'quiz_submission' );

		if ( empty( $webhooks ) ) {
			return;
		}

		$user   = get_user_by( 'id', $student_id );
		$course = ohmylms_get_course( $course_id );
		$quiz   = get_post( $quiz_id );

		if ( ! $user || ! $course || ! $quiz ) {
			return;
		}

		$score        = isset( $result['total'] ) ? $result['total'] : 0;
		$student      = new \OhMyLMS\Data\Student( $student_id );
		$payload_data = array(
			'user_id'      => $student_id,
			'user_email'   => $student->get_email(),
			'user_name'    => $student->get_name(),
			'quiz_id'      => $quiz_id,
			'quiz_title'   => $quiz->post_title,
			'score'        => $score,
			'course_id'    => $course_id,
			'course_title' => $course->get_name(),
			'event_time'   => current_time( 'mysql' ),
			'site_url'     => get_site_url(),
		);

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $payload_data );
		}
	}


	/**
	 * Trigger webhook for quiz submission
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param int   $course_id Course ID.
	 * @param int   $student_id Student ID.
	 * @param array $result Quiz result data.
	 * .
	 * @since 1.0.0
	 */
	public function trigger_quiz_achievement( $quiz_id, $course_id, $student_id, $result = array() ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'quiz_achievement' );
		if ( empty( $webhooks ) ) {
			return;
		}

		$user   = get_user_by( 'id', $student_id );
		$course = ohmylms_get_course( $course_id );
		$quiz   = get_post( $quiz_id );

		if ( ! $user || ! $course || ! $quiz ) {
			return;
		}

		$score        = is_array( $result ) && isset( $result['total'] ) ? $result['total'] : $result;
		$student      = new \OhMyLMS\Data\Student( $student_id );
		$payload_data = array(
			'user_id'      => $student_id,
			'user_email'   => $student->get_email(),
			'user_name'    => $student->get_name(),
			'quiz_id'      => $quiz_id,
			'quiz_title'   => $quiz->post_title,
			'score'        => $score,
			'course_id'    => $course_id,
			'course_title' => $course->get_name(),
			'event_time'   => current_time( 'mysql' ),
			'site_url'     => get_site_url(),
		);

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $payload_data );
		}
	}

	/**
	 * Trigger webhook for assignment submission
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $course_id Course ID.
	 * @param int $student_id Student ID.
	 * @since 1.0.0
	 */
	public function trigger_assignment_submission( $assignment_id, $course_id, $student_id ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'assignment_submission' );

		if ( empty( $webhooks ) ) {
			return;
		}

		$user       = get_user_by( 'id', $student_id );
		$course     = ohmylms_get_course( $course_id );
		$assignment = get_post( $assignment_id );

		if ( ! $user || ! $course || ! $assignment ) {
			return;
		}

		$submission_date = current_time( 'mysql' );
		$student         = new \OhMyLMS\Data\Student( $student_id );
		$payload_data    = array(
			'user_id'          => $student_id,
			'user_email'       => $student->get_email(),
			'user_name'        => $student->get_name(),
			'assignment_id'    => $assignment_id,
			'assignment_title' => $assignment->post_title,
			'submission_date'  => $submission_date,
			'course_id'        => $course_id,
			'course_title'     => $course->get_name(),
			'event_time'       => current_time( 'mysql' ),
			'site_url'         => get_site_url(),
		);

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $payload_data );
		}
	}


	/**
	 * Trigger webhook for assignment achievement
	 *
	 * @param int   $assignment_id Assignment ID.
	 * @param int   $course_id Course ID.
	 * @param int   $student_id Student ID.
	 * @param mixed $marks Marks.
	 * @since 1.0.0
	 */
	public function trigger_assignment_achievement( $assignment_id, $course_id, $student_id, $marks ) {
		$webhooks = $this->get_active_webhooks_for_trigger( 'assignment_achievement' );

		if ( empty( $webhooks ) ) {
			return;
		}

		$user       = get_user_by( 'id', $student_id );
		$course     = ohmylms_get_course( $course_id );
		$assignment = get_post( $assignment_id );
		if ( ! $user || ! $course || ! $assignment ) {
			return;
		}

		$submission_date = current_time( 'mysql' );
		$student         = new \OhMyLMS\Data\Student( $student_id );
		$payload_data    = array(
			'user_id'          => $student_id,
			'user_email'       => $student->get_email(),
			'user_name'        => $student->get_name(),
			'assignment_id'    => $assignment_id,
			'assignment_title' => $assignment->post_title,
			'marks'            => $marks,
			'submission_date'  => $submission_date,
			'course_id'        => $course_id,
			'course_title'     => $course->get_name(),
			'event_time'       => current_time( 'mysql' ),
			'site_url'         => get_site_url(),
		);

		foreach ( $webhooks as $webhook ) {
			$this->send_webhook( $webhook, $payload_data );
		}
	}


	/**
	 * Get active webhooks for a specific trigger
	 *
	 * @param string $trigger_event Trigger event name.
	 * @return array Array of webhook objects.
	 *
	 * @since 1.0.0
	 */
	private function get_active_webhooks_for_trigger( $trigger_event ) {
		$args = array(
			'status'        => 'active',
			'trigger_event' => $trigger_event,
			'limit'         => -1, // Get all active webhooks for this trigger.
		);

		$webhooks = WebhookStore::get_webhooks( $args );

		if ( empty( $webhooks ) ) {
			return array();
		}

		// Convert to Webhook objects.
		$webhook_objects = array();
		foreach ( $webhooks as $webhook_data ) {
			try {
				$webhook_objects[] = new Webhook( $webhook_data->id );
			} catch ( \Exception $e ) {
				// The row was removed between the query and the load; skip it.
				continue;
			}
		}

		return $webhook_objects;
	}

	/**
	 * Send webhook with payload data.
	 *
	 * Delivery (SSRF checks, signing, timeout, logging) is handled by WebhookDelivery.
	 *
	 * @param Webhook $webhook Webhook object.
	 * @param array   $payload_data Raw payload data.
	 *
	 * @since 1.0.0
	 */
	private function send_webhook( $webhook, $payload_data ) {
		if ( ! $webhook instanceof Webhook || ! $webhook->get_id() || '' === $webhook->get_webhook_url() ) {
			return;
		}

		$payload = WebhookDelivery::map_payload( (array) $payload_data, $webhook->get_data_mapping() );

		WebhookDelivery::send( $webhook, $payload, array( 'event' => $webhook->get_trigger_event() ) );
	}
}
