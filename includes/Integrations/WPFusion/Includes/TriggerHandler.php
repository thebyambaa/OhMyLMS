<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Trigger Handler
 *
 * Listens to OhMyLMS events and executes corresponding WP Fusion actions
 *
 * @package OhMyLMS\Integrations\WPFusion\Includes
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\WPFusion\Includes;

use OhMyLMS\Integrations\WPFusion\Includes\Api\WPFusionApiClient;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin trigger handler implementation.
 */
class TriggerHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_event_listeners();
		$this->register_background_processor();
	}

	/**
	 * Register background processor action
	 *
	 * @since 1.0.0
	 */
	private function register_background_processor() {
		add_action( 'ohmylms_wpfusion_process_integration', array( $this, 'process_integration_background' ), 10, 2 );
	}

	/**
	 * Register event listeners for OhMyLMS events
	 *
	 * @since 1.0.0
	 */
	private function register_event_listeners() {
		// Course events.
		add_action( 'ohmylms_course_completed', array( $this, 'handle_course_completed' ), 10, 3 );
		add_action( 'ohmylms_manual_student_enrollment', array( $this, 'handle_manual_course_enrollment' ), 10, 2 );

		add_action( 'ohmylms_update_order_status_to_completed', array( $this, 'handle_enrollment' ), 10 );
		add_action( 'ohmylms_payment_completed', array( $this, 'handle_enrollment' ), 10 );
		add_action( 'ohmylms_after_enrolled_student', array( $this, 'handle_enrollment' ), 10 );

		add_action( 'ohmylms_student_unenrolled', array( $this, 'handle_course_unenrollment' ), 10, 2 );

		// Lesson events.
		add_action( 'ohmylms_lesson_completed', array( $this, 'handle_lesson_completed' ), 10, 3 );

		// Quiz events.
		add_action( 'ohmylms_quiz_submission', array( $this, 'handle_quiz_submitted' ), 10, 4 );

		// Assignment events.
		add_action( 'ohmylms_after_assignment_submitted', array( $this, 'handle_assignment_submitted' ), 10, 3 );

		add_action( 'ohmylms_course_completion_rate', array( $this, 'course_completion_rate' ), 10, 3 );

		add_action( 'ohmylms_update_order_status_to_cancelled', array( $this, 'after_cancelled_enrollment' ), 10 );

		add_action( 'ohmylms_rest_delete_course', array( $this, 'after_delete_course' ), 10 );

		add_action( 'ohmylms_after_assignment_review', array( $this, 'after_assignment_review' ), 10, 4 );

		add_action( 'ohmylms_order_refunded', array( $this, 'cancel_student_enrollment' ), 10, 2 );
		add_action( 'ohmylms_rest_before_delete_order', array( $this, 'rest_delete_order' ), 10 );
	}

	/**
	 * Cancel student enrollment when an order is refunded.
	 *
	 * @param \OhMyLMS\Commerce\Data\OrderRefund $refund The refund object.
	 * @param \OhMyLMS\Commerce\Data\Order       $order The order object.
	 * .
	 * @since 1.0.0
	 */
	public function cancel_student_enrollment( $refund, $order ) {

		$total_refunded = ohmylms_format_decimal( abs( $refund->get_total() ), ohmylms_get_price_decimals() );
		$order_total    = ohmylms_format_decimal( $order->get_total(), ohmylms_get_price_decimals() );
		$student_id     = $order->get_student_id();
		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d', $table_name, $student_id, $order->get_id() ), ARRAY_A );
		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}
		$course_id = $enroll_data['course_id'];

		if ( $total_refunded == $order_total ) {
			$this->process_triggers(
				'ohmylms_student_unenrolled',
				array(
					'student_id' => $student_id,
					'course_id'  => $course_id,
				),
				'course_unenrollment'
			);
		}
	}


	/**
	 * Delete order.
	 *
	 * @param int $order_id The order ID.
	 * .
	 * @since 1.0.0
	 */
	public function rest_delete_order( $order_id ) {
		global $wpdb;

		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d', $table_name, $order_id ), ARRAY_A );
		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}
		$student_id = $enroll_data['user_id'];
		$course_id  = $enroll_data['course_id'];
		$this->process_triggers(
			'ohmylms_student_unenrolled',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
			),
			'course_unenrollment'
		);
	}

	/**
	 * Trigger after a course is deleted
	 *
	 * @param int $course_id The ID of the course.
	 * .
	 * @return void
	 * @since 1.0.0
	 */
	public function after_delete_course( $course_id ) {
		if ( ! $course_id ) {
			return;
		}

		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE course_id = %d', $table_name, $course_id ), ARRAY_A );
		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}
		$student_id = $enroll_data['user_id'];
		$this->process_triggers(
			'ohmylms_student_unenrolled',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
			),
			'course_unenrollment'
		);
	}


	/**
	 * Trigger after an assignment is reviewed (pass/fail)
	 *
	 * @param int    $assignment_id Assignment ID.
	 * @param int    $course_id Course ID.
	 * @param int    $student_id Student ID.
	 * @param string $status Review status (pass/fail).
	 * @return void
	 */
	public function after_assignment_review( $assignment_id, $course_id, $student_id, $status ) {
		$student         = new \OhMyLMS\Data\Student( $student_id );
		$completion_rate = $student->get_over_all_completion_rate( $course_id );
		if ( $completion_rate >= 100 ) {
			$this->process_triggers(
				'ohmylms_course_completed',
				array(
					'student_id' => $student_id,
					'course_id'  => $course_id,
				),
				'course_completed'
			);
		}
	}


	/**
	 * Trigger when a course enrollment is cancelled
	 *
	 * @param mixed $order Order object or order ID.
	 * @return void
	 */
	public function after_cancelled_enrollment( $order ) {
		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = ohmylms_ecommerce_get_order( $order_id );
		}
		if ( ! is_object( $order ) ) {
			return;
		}
		$student_id = $order->get_student_id();
		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d', $table_name, $student_id, $order->get_id() ), ARRAY_A );
		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}
		$course_id = $enroll_data['course_id'];
		$this->process_triggers(
			'ohmylms_student_unenrolled',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
			),
			'course_unenrollment'
		);
	}


	/**
	 * Trigger when course completion rate is updated
	 *
	 * @param int   $student_id Student ID.
	 * @param int   $course_id Course ID.
	 * @param float $completion_rate Completion rate percentage.
	 * @return void
	 */
	public function course_completion_rate( $student_id, $course_id, $completion_rate ) {
		if ( $completion_rate >= 100 ) {
			$this->process_triggers(
				'ohmylms_course_completed',
				array(
					'student_id' => $student_id,
					'course_id'  => $course_id,
				),
				'course_completed'
			);
		}
	}

	/**
	 * Handle course completed event
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @param int $order_id Order ID.
	 * @since 1.0.0
	 */
	public function handle_course_completed( $student_id, $course_id, $order_id = 0 ) {
		$this->process_triggers(
			'ohmylms_course_completed',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
				'order_id'   => $order_id,
			),
			'course_completed'
		);
	}

	/**
	 * Handle course enrollment event
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @since 1.0.0
	 */
	public function handle_manual_course_enrollment( $student_id, $course_id ) {
		$this->process_triggers(
			'ohmylms_manual_student_enrollment',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
			),
			'course_enrollment'
		);
	}


	/**
	 * Handle enrollment event from order completion
	 *
	 * @param int|object $order Order ID or Order object.
	 * @since 1.0.0
	 */
	public function handle_enrollment( $order ) {
		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = ohmylms_ecommerce_get_order( $order_id );
		}
		if ( ! is_object( $order ) ) {
			return;
		}
		$student_id = $order->get_student_id();
		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d', $table_name, $student_id, $order->get_id() ), ARRAY_A );

		if ( empty( $enroll_data['course_id'] ) ) {
			return;
		}
		$course_id = $enroll_data['course_id'];
		$this->process_triggers(
			'ohmylms_manual_student_enrollment',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
			),
			'course_enrollment'
		);
	}

	/**
	 * Handle course unenrollment event
	 *
	 * @param int $student_id Student ID.
	 * @param int $course_id Course ID.
	 * @since 1.0.0
	 */
	public function handle_course_unenrollment( $student_id, $course_id ) {
		$this->process_triggers(
			'ohmylms_student_unenrolled',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
			),
			'course_unenrollment'
		);
	}

	/**
	 * Handle lesson completed event
	 *
	 * @param int $lesson_id Lesson ID.
	 * @param int $course_id Course ID.
	 * @param int $student_id Student ID.
	 * @since 1.0.0
	 */
	public function handle_lesson_completed( $lesson_id, $course_id, $student_id ) {
		$this->process_triggers(
			'ohmylms_lesson_completed',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
				'lesson_id'  => $lesson_id,
			),
			'lesson_completed'
		);
	}

	/**
	 * Handle quiz submitted event
	 *
	 * @param int   $quiz_id Quiz ID.
	 * @param int   $course_id Course ID.
	 * @param int   $student_id Student ID.
	 * @param array $argc Additional arguments.
	 * @since 1.0.0
	 */
	public function handle_quiz_submitted( $quiz_id, $course_id, $student_id, $argc ) {
		$this->process_triggers(
			'ohmylms_quiz_submission',
			array(
				'student_id' => $student_id,
				'course_id'  => $course_id,
				'quiz_id'    => $quiz_id,
				'args'       => $argc,
			),
			'quiz_submitted'
		);
	}

	/**
	 * Handle assignment submitted event
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $course_id Course ID.
	 * @param int $student_id Student ID.
	 * @since 1.0.0
	 */
	public function handle_assignment_submitted( $assignment_id, $course_id, $student_id ) {
		$this->process_triggers(
			'ohmylms_after_assignment_submitted',
			array(
				'student_id'    => $student_id,
				'course_id'     => $course_id,
				'assignment_id' => $assignment_id,
			),
			'assignment_submitted'
		);
	}

	/**
	 * Process triggers for a given event
	 *
	 * @param string $event_name Event name (WordPress action hook).
	 * @param array  $event_data Event data.
	 * @param string $trigger_type Trigger type for matching (e.g., 'course_completed', 'lesson_completed').
	 * @since 1.0.0
	 */
	private function process_triggers( $event_name, $event_data, $trigger_type = '' ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		// Extract content type and ID from event data.
		$content_type = '';
		$content_id   = 0;

		if ( isset( $event_data['assignment_id'] ) ) {
			$content_type = 'assignment';
			$content_id   = $event_data['assignment_id'];
		} elseif ( isset( $event_data['lesson_id'] ) ) {
			$content_type = 'lesson';
			$content_id   = $event_data['lesson_id'];
		} elseif ( isset( $event_data['quiz_id'] ) ) {
			$content_type = 'quiz';
			$content_id   = $event_data['quiz_id'];
		} elseif ( isset( $event_data['course_id'] ) ) {
			$content_type = 'course';
			$content_id   = $event_data['course_id'];
		}

		// Build query to find matching triggers
		// All events (including course events) can be stored as either:
		// 1. Direct match: trigger_event = 'course_enrollment'
		// 2. Multiple events: trigger_event = 'multiple' with actions array.
		$triggers = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i
                 WHERE (trigger_event = %s OR trigger_event = \'multiple\')
                 AND crm_type = \'wpfusion\'
                 AND content_type = %s 
                 AND content_id = %d 
                 AND status = \'active\'',
				$table_name,
				$trigger_type,
				$content_type,
				$content_id
			),
			ARRAY_A
		);

		if ( empty( $triggers ) ) {
			return;
		}

		// Filter triggers for 'multiple' type to ensure they have the current event configured.
		$valid_triggers = array();
		foreach ( $triggers as $trigger ) {
			if ( 'multiple' === $trigger['trigger_event'] ) {
				// Decode action_data to check if this event is configured.
				$action_data = json_decode( $trigger['action_data'], true );

				if ( isset( $action_data['actions'] ) && is_array( $action_data['actions'] ) ) {
					$has_event = false;
					foreach ( $action_data['actions'] as $action ) {
						// Check against the full event name (e.g., 'ohmylms_manual_student_enrollment')
						// NOT the trigger_type (e.g., 'course_enrollment').
						if ( isset( $action['event'] ) && $action['event'] === $event_name ) {
							$has_event = true;
							break;
						}
					}

					if ( $has_event ) {
						$valid_triggers[] = $trigger;
					}
				}
			} else {
				// Direct trigger_event match, always include.
				$valid_triggers[] = $trigger;
			}
		}

		if ( empty( $valid_triggers ) ) {
			return;
		}

		// Check if WP Fusion is enabled and connected.
		if ( defined( 'WP_FUSION_VERSION' ) === false ) {
			return;
		}

		if ( ! wp_fusion()->crm ) {
			return;
		}

		// Add trigger_type to event_data for matching in execute_trigger.
		$event_data['trigger_type'] = $trigger_type;
		$event_data['event_name']   = $event_name; // Add full event name for matching.

		// Queue each valid trigger for background processing.
		foreach ( $valid_triggers as $trigger ) {
			$this->queue_integration_processing( $trigger, $event_data );
		}
	}

	/**
	 * Queue integration for background processing
	 *
	 * @param array $trigger Trigger data.
	 * @param array $event_data Event data.
	 * @since 1.0.0
	 */
	private function queue_integration_processing( $trigger, $event_data ) {
		// Use Action Scheduler if available (comes with WooCommerce).
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'ohmylms_wpfusion_process_integration',
				array(
					'trigger'    => $trigger,
					'event_data' => $event_data,
				),
				'ohmylms-integrations'
			);
		} else {
			// Fallback to WordPress Cron.
			wp_schedule_single_event(
				time(),
				'ohmylms_wpfusion_process_integration',
				array(
					'trigger'    => $trigger,
					'event_data' => $event_data,
				)
			);

			// Spawn WP Cron immediately in non-blocking way.
			$this->spawn_cron();
		}
	}

	/**
	 * Spawn WP Cron in non-blocking way
	 *
	 * @since 1.0.0
	 */
	private function spawn_cron() {
		// Only spawn if not already running.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		// Get cron URL.
		$cron_url = site_url( 'wp-cron.php?doing_wp_cron=' . time() );

		// Spawn cron in non-blocking way.
		wp_remote_post(
			$cron_url,
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);
	}

	/**
	 * Process integration in background
	 *
	 * @param array $trigger Trigger data.
	 * @param array $event_data Event data.
	 * @since 1.0.0
	 */
	public function process_integration_background( $trigger, $event_data ) {
		// Check if WP Fusion is still available.
		if ( defined( 'WP_FUSION_VERSION' ) === false || ! wp_fusion()->crm ) {
			// Retry once after 30 seconds if WP Fusion is not available.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time() + 30,
					'ohmylms_wpfusion_process_integration',
					array(
						'trigger'    => $trigger,
						'event_data' => $event_data,
					),
					'ohmylms-integrations'
				);
			}
			return;
		}

		try {
			// Process the trigger.
			$this->execute_trigger( $trigger, $event_data );
		} catch ( \Exception $e ) {
			do_action( 'ohmylms_wpfusion_integration_failed', $trigger, $event_data, $e );
		}
	}

	/**
	 * Execute a single trigger
	 *
	 * @param array $trigger Trigger data.
	 * @param array $event_data Event data.
	 * @since 1.0.0
	 */
	private function execute_trigger( $trigger, $event_data ) {
		$action_type = $trigger['action_type'];
		$action_data = json_decode( $trigger['action_data'], true );
		$student_id  = isset( $event_data['student_id'] ) ? $event_data['student_id'] : 0;

		if ( ! $student_id ) {
			return;
		}

		$trigger_event = isset( $trigger['trigger_event'] ) ? $trigger['trigger_event'] : '';

		// Check if action_data has the new 'actions' array structure (multiple actions in one integration).
		if ( isset( $action_data['actions'] ) && is_array( $action_data['actions'] ) ) {
			// New format: multiple actions with different events
			// Get the actual event name from event_data (full WordPress hook name).
			$current_event = '';
			if ( isset( $event_data['event_name'] ) ) {
				$current_event = $event_data['event_name'];
			}

			foreach ( $action_data['actions'] as $action ) {
				// Check if this action's event matches the current event being triggered
				// Compare full event names (e.g., 'ohmylms_manual_student_enrollment').
				if ( isset( $action['event'] ) && $action['event'] === $current_event ) {
					// Execute this specific action.
					switch ( $action_type ) {
						case 'apply_tags':
							$this->execute_apply_tags( $student_id, $action );
							break;

						case 'remove_tags':
							$this->execute_remove_tags( $student_id, $action );
							break;
					}
				}
				// Note: Non-matching actions are silently skipped (this is expected for multiple-event integrations).
			}
		} else {
			// Old format: single action (backward compatibility).
			switch ( $action_type ) {
				case 'apply_tags':
					$this->execute_apply_tags( $student_id, $action_data );
					break;

				case 'remove_tags':
					$this->execute_remove_tags( $student_id, $action_data );
					break;
			}
		}

		// Log trigger execution.
		do_action( 'ohmylms_wpfusion_trigger_executed', $trigger, $event_data );
	}

	/**
	 * Execute apply tags action
	 *
	 * @param int   $student_id Student ID.
	 * @param array $action_data Action data.
	 * @since 1.0.0
	 */
	private function execute_apply_tags( $student_id, $action_data ) {
		if ( empty( $action_data['tag_ids'] ) ) {
			return;
		}

		// Get user by student ID.
		$user = get_user_by( 'ID', $student_id );
		if ( ! $user ) {
			return;
		}

		// Prepare user data.
		$email     = $user->user_email;
		$firstName = get_user_meta( $student_id, 'first_name', true );
		$lastName  = get_user_meta( $student_id, 'last_name', true );
		$phone     = get_user_meta( $student_id, 'billing_phone', true );

		$contact_data = array(
			'user_email' => $email,
			'first_name' => $firstName ? $firstName : '',
			'last_name'  => $lastName ? $lastName : '',
			'phone'      => $phone ? $phone : '',
		);

		// Create contact in CRM.
		$contact_id = wp_fusion()->crm->add_contact( $contact_data );

		if ( is_wp_error( $contact_id ) ) {
			$contact_id = wp_fusion()->crm->get_contact_id( $email );
			if ( is_wp_error( $contact_id ) ) {
				return;
			}
		}

		// Ensure tags is an array.
		$tags = is_array( $action_data['tag_ids'] ) ? $action_data['tag_ids'] : array( $action_data['tag_ids'] );

		// Remove any empty values.
		$tags = array_filter( $tags );

		if ( empty( $tags ) ) {
			return;
		}

		// Apply tags to contact.
		if ( ! is_wp_error( $contact_id ) ) {
			wp_fusion()->crm->apply_tags( $tags, $contact_id );
		}

		// Log the action for tracking.
		do_action( 'ohmylms_wpfusion_tags_applied', $student_id, $tags );
	}

	/**
	 * Execute remove tags action
	 *
	 * @param int   $student_id Student ID.
	 * @param array $action_data Action data.
	 * @since 1.0.0
	 */
	private function execute_remove_tags( $student_id, $action_data ) {
		if ( empty( $action_data['tag_ids'] ) ) {
			return;
		}

		// Get user by student ID.
		$user = get_user_by( 'ID', $student_id );
		if ( ! $user ) {
			return;
		}

		// Ensure tags is an array.
		$tags = is_array( $action_data['tag_ids'] ) ? $action_data['tag_ids'] : array( $action_data['tag_ids'] );

		// Remove any empty values.
		$tags = array_filter( $tags );

		if ( empty( $tags ) ) {
			return;
		}

		// Remove tags using WP Fusion.
		wp_fusion()->user->remove_tags( $tags, $student_id );

		// Log the action for tracking.
		do_action( 'ohmylms_wpfusion_tags_removed', $student_id, $tags );
	}
}
