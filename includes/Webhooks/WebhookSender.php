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
			$webhook_objects[] = new Webhook( $webhook_data->id );
		}

		return $webhook_objects;
	}

	/**
	 * Send webhook with payload data
	 *
	 * @param Webhook $webhook Webhook object.
	 * @param array   $payload_data Raw payload data.
	 * .
	 * @since 1.0.0
	 */
	private function send_webhook( $webhook, $payload_data ) {
		if ( ! $webhook instanceof Webhook || ! $webhook->get_id() ) {
			return;
		}

		$webhook_url  = $webhook->get_webhook_url();
		$http_method  = $webhook->get_http_method();
		$data_type    = $webhook->get_data_type();
		$data_mapping = $webhook->get_data_mapping();

		if ( empty( $webhook_url ) ) {
			return;
		}

		// Build the final payload based on data mapping.
		$payload = $this->build_payload( $payload_data, $data_mapping );

		// Prepare request arguments.
		$args = array(
			'method'  => $http_method,
			'timeout' => 30,
			'headers' => $this->get_headers( $data_type ),
		);

		// Format payload based on data type.
		if ( 'GET' === $http_method ) {
			$webhook_url = add_query_arg( $payload, $webhook_url );
		} else {
			$args['body'] = $this->format_payload( $payload, $data_type );
		}

		// Send the webhook.
		$response = wp_remote_request( $webhook_url, $args );

		// Log the webhook (optional - you can implement logging if needed).
		$this->log_webhook( $webhook, $payload, $response );
	}

	/**
	 * Build payload from data mapping
	 *
	 * @param array  $payload_data Raw payload data.
	 * @param string $data_mapping Data mapping JSON string.
	 * @return array Mapped payload.
	 *
	 * @since 1.0.0
	 */
	private function build_payload( $payload_data, $data_mapping ) {
		if ( empty( $data_mapping ) ) {
			return $payload_data;
		}

		// Decode data mapping if it's a JSON string.
		if ( is_string( $data_mapping ) ) {
			$mappings = json_decode( $data_mapping, true );
		} else {
			$mappings = $data_mapping;
		}

		if ( ! is_array( $mappings ) || empty( $mappings ) ) {
			return $payload_data;
		}

		$payload = array();

		foreach ( $mappings as $mapping ) {
			if ( ! isset( $mapping['key'], $mapping['value'] ) ) {
				continue;
			}

			$key   = $mapping['key'];
			$value = $mapping['value'];

			// Skip empty keys.
			if ( empty( $key ) ) {
				continue;
			}

			// Map the value from payload_data.
			if ( isset( $payload_data[ $value ] ) ) {
				$payload[ $key ] = $payload_data[ $value ];
			}
		}

		return $payload;
	}

	/**
	 * Get headers based on data type
	 *
	 * @param string $data_type Data type (json, xml, form).
	 * @return array Headers array.
	 *
	 * @since 1.0.0
	 */
	private function get_headers( $data_type ) {
		$headers = array();

		switch ( $data_type ) {
			case 'json':
				$headers['Content-Type'] = 'application/json';
				break;
			case 'xml':
				$headers['Content-Type'] = 'application/xml';
				break;
			case 'form':
				$headers['Content-Type'] = 'application/x-www-form-urlencoded';
				break;
			default:
				$headers['Content-Type'] = 'application/json';
		}

		return $headers;
	}

	/**
	 * Format payload based on data type
	 *
	 * @param array  $payload Payload array.
	 * @param string $data_type Data type (json, xml, form).
	 * @return string Formatted payload.
	 *
	 * @since 1.0.0
	 */
	private function format_payload( $payload, $data_type ) {
		switch ( $data_type ) {
			case 'json':
				return wp_json_encode( $payload );
			case 'xml':
				return $this->array_to_xml( $payload );
			case 'form':
				return http_build_query( $payload );
			default:
				return wp_json_encode( $payload );
		}
	}

	/**
	 * Convert array to XML
	 *
	 * @param array  $data Data array.
	 * @param string $root Root element name.
	 * @param object $xml_obj XML object.
	 * @return string XML string.
	 *
	 * @since 1.0.0
	 */
	private function array_to_xml( $data, $root = 'root', $xml_obj = null ) {
		if ( null === $xml_obj ) {
			$xml_obj = new \SimpleXMLElement( "<?xml version=\"1.0\"?><{$root}></{$root}>" );
		}

		foreach ( $data as $key => $value ) {
			$key = is_numeric( $key ) ? "item{$key}" : $key;

			if ( is_array( $value ) ) {
				$subnode = $xml_obj->addChild( $key );
				$this->array_to_xml( $value, $root, $subnode );
			} else {
				$xml_obj->addChild( $key, htmlspecialchars( $value ) );
			}
		}

		return $xml_obj->asXML();
	}

	/**
	 * Log webhook request and response
	 *
	 * @param Webhook $webhook Webhook object.
	 * @param array   $payload Payload sent.
	 * @param mixed   $response Response from wp_remote_request.
	 * .
	 * @since 1.0.0
	 */
	private function log_webhook( $webhook, $payload, $response ) {
		// Optional: Implement webhook logging
		// This could be saved to a custom table or WordPress options.

		$log_data = array(
			'webhook_id'    => $webhook->get_id(),
			'webhook_name'  => $webhook->get_name(),
			'trigger_event' => $webhook->get_trigger_event(),
			'webhook_url'   => $webhook->get_webhook_url(),
			'payload'       => $payload,
			'timestamp'     => current_time( 'mysql' ),
		);

		if ( is_wp_error( $response ) ) {
			$log_data['status']  = 'error';
			$log_data['message'] = $response->get_error_message();
		} else {
			$log_data['status']        = 'success';
			$log_data['response_code'] = wp_remote_retrieve_response_code( $response );
			$log_data['response_body'] = wp_remote_retrieve_body( $response );
		}

		// Fire action for logging (can be hooked by other plugins/extensions).
		do_action( 'ohmylms_webhook_sent', $log_data );

		// Optional: Save to custom table or transient for debugging
		// You can implement this based on your logging requirements.
	}
}
