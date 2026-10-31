<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Runs automation rules on existing plugin hooks.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Automation;

use OhMyLMS\Cohorts\Cohorts;
use OhMyLMS\Student\Enrollments;

defined( 'ABSPATH' ) || exit;

/**
 * Listens to the plugin's purchase, enrolment and learning hooks, matches active rules and runs
 * their actions synchronously, logging every action. An action may raise another event (enrolling
 * fires `course_enrolled`); such chains stop after two levels and a rule runs at most once per
 * learner and object within a request, so rules cannot loop.
 *
 * @since 1.0.0
 */
final class RuleRunner {

	const MAX_DEPTH = 2;

	/**
	 * Current chain depth.
	 *
	 * @var int
	 */
	private static $depth = 0;

	/**
	 * Rule runs of this request, keyed by rule, learner and object.
	 *
	 * @var array<string, bool>
	 */
	private static $seen = array();

	/**
	 * Register the listeners.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'ohmylms_checkout_after_create_order', array( __CLASS__, 'on_order' ), 30, 1 );
		add_action( 'ohmylms_after_enrolled_student', array( __CLASS__, 'on_order_enrolment' ), 30, 2 );
		add_action( 'ohmylms_manual_student_enrollment', array( __CLASS__, 'on_manual_enrolment' ), 30, 2 );
		add_action( 'ohmylms_update_order_status_to_cancelled', array( __CLASS__, 'on_order_cancelled' ), 30, 1 );
		add_action( 'ohmylms_after_lesson_completed', array( __CLASS__, 'on_lesson_completed' ), 30, 3 );
		add_action( 'ohmylms_course_completion_rate', array( __CLASS__, 'on_progress' ), 30, 3 );
		add_action( 'ohmylms_course_completed', array( __CLASS__, 'on_course_completed' ), 30, 2 );
		add_action( 'ohmylms_quiz_submission', array( __CLASS__, 'on_quiz_submitted' ), 30, 3 );
		add_action( 'ohmylms_after_assignment_submitted', array( __CLASS__, 'on_assignment_submitted' ), 30, 3 );
		add_action( 'ohmylms_after_assignment_review', array( __CLASS__, 'on_assignment_reviewed' ), 30, 5 );
	}

	/**
	 * Reset the per-request state (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$depth = 0;
		self::$seen  = array();
	}

	/**
	 * Order from an id or object.
	 *
	 * @param mixed $order Order or id.
	 * @return object|null
	 */
	private static function order( $order ) {
		if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			return $order;
		}
		$id = absint( $order );
		return $id && function_exists( 'ohmylms_ecommerce_get_order' ) ? ohmylms_ecommerce_get_order( $id ) : null;
	}

	/**
	 * Course ids of an order's line items.
	 *
	 * @param object $order Order.
	 * @return int[]
	 */
	private static function order_courses( $order ) {
		$ids = array();
		foreach ( (array) $order->get_items() as $item ) {
			$id = method_exists( $item, 'get_course_id' ) ? (int) $item->get_course_id() : 0;
			if ( $id && 'ohmylms-course' === get_post_type( $id ) ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Purchase: course and plan events.
	 *
	 * @param mixed $order Order.
	 * @return void
	 */
	public static function on_order( $order ) {
		$order = self::order( $order );
		if ( ! $order ) {
			return;
		}
		$user_id = (int) $order->get_student_id();
		foreach ( self::order_courses( $order ) as $course_id ) {
			self::dispatch(
				'course_purchased',
				array(
					'user_id'   => $user_id,
					'course_id' => $course_id,
					'order_id'  => (int) $order->get_id(),
				)
			);
		}
		$plan = (int) get_post_meta( $order->get_id(), '_membership_id', true );
		if ( $plan ) {
			self::dispatch(
				'membership_purchased',
				array(
					'user_id'       => $user_id,
					'membership_id' => $plan,
					'order_id'      => (int) $order->get_id(),
				)
			);
		}
	}

	/**
	 * Enrolment through an order.
	 *
	 * @param mixed $order   Order.
	 * @param int   $user_id Learner.
	 * @return void
	 */
	public static function on_order_enrolment( $order, $user_id = 0 ) {
		global $wpdb;
		$order = self::order( $order );
		if ( ! $order ) {
			return;
		}
		$user_id = $user_id ? (int) $user_id : (int) $order->get_student_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh rows of this order.
		$courses = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT course_id FROM %i WHERE order_id = %d AND user_id = %d AND status = 'enrolled'", $wpdb->prefix . 'ohmylms_user_enrollment', (int) $order->get_id(), $user_id ) );
		foreach ( array_map( 'intval', $courses ) as $course_id ) {
			self::dispatch(
				'course_enrolled',
				array(
					'user_id'   => $user_id,
					'course_id' => $course_id,
					'order_id'  => (int) $order->get_id(),
				)
			);
		}
	}

	/**
	 * Manual enrolment (also raised by the enrol action).
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return void
	 */
	public static function on_manual_enrolment( $user_id, $course_id ) {
		self::dispatch(
			'course_enrolled',
			array(
				'user_id'   => (int) $user_id,
				'course_id' => (int) $course_id,
			)
		);
	}

	/**
	 * Order cancelled.
	 *
	 * @param mixed $order Order.
	 * @return void
	 */
	public static function on_order_cancelled( $order ) {
		$order = self::order( $order );
		if ( ! $order ) {
			return;
		}
		foreach ( self::order_courses( $order ) as $course_id ) {
			self::dispatch(
				'enrolment_cancelled',
				array(
					'user_id'   => (int) $order->get_student_id(),
					'course_id' => $course_id,
					'order_id'  => (int) $order->get_id(),
				)
			);
		}
	}

	/**
	 * Lesson completed.
	 *
	 * @param int $lesson_id  Lesson.
	 * @param int $course_id  Course.
	 * @param int $student_id Learner.
	 * @return void
	 */
	public static function on_lesson_completed( $lesson_id, $course_id, $student_id ) {
		self::dispatch(
			'lesson_completed',
			array(
				'user_id'    => (int) $student_id,
				'course_id'  => (int) $course_id,
				'content_id' => (int) $lesson_id,
			)
		);
	}

	/**
	 * Course progress changed.
	 *
	 * @param int       $student_id Learner.
	 * @param int       $course_id  Course.
	 * @param int|float $rate       Completion percentage.
	 * @return void
	 */
	public static function on_progress( $student_id, $course_id, $rate ) {
		self::dispatch(
			'course_progress',
			array(
				'user_id'   => (int) $student_id,
				'course_id' => (int) $course_id,
				'rate'      => (float) $rate,
			)
		);
	}

	/**
	 * Course completed.
	 *
	 * @param int $student_id Learner.
	 * @param int $course_id  Course.
	 * @return void
	 */
	public static function on_course_completed( $student_id, $course_id ) {
		self::dispatch(
			'course_completed',
			array(
				'user_id'   => (int) $student_id,
				'course_id' => (int) $course_id,
			)
		);
	}

	/**
	 * Quiz submitted.
	 *
	 * @param int $quiz_id    Quiz.
	 * @param int $course_id  Course.
	 * @param int $student_id Learner.
	 * @return void
	 */
	public static function on_quiz_submitted( $quiz_id, $course_id, $student_id ) {
		self::dispatch(
			'quiz_submitted',
			array(
				'user_id'    => (int) $student_id,
				'course_id'  => (int) $course_id,
				'content_id' => (int) $quiz_id,
			)
		);
	}

	/**
	 * Assignment submitted.
	 *
	 * @param int $assignment_id Assignment.
	 * @param int $course_id     Course.
	 * @param int $student_id    Learner.
	 * @return void
	 */
	public static function on_assignment_submitted( $assignment_id, $course_id, $student_id ) {
		self::dispatch(
			'assignment_submitted',
			array(
				'user_id'    => (int) $student_id,
				'course_id'  => (int) $course_id,
				'content_id' => (int) $assignment_id,
			)
		);
	}

	/**
	 * Assignment graded (fired once per graded submission by the shared grading pipeline,
	 * `Rest\V1\AssignmentController::update_attempt_report()`). The rule runs for the learner only when
	 * they own a submission of the assignment (the graded row when it is named).
	 *
	 * @param int    $assignment_id Assignment.
	 * @param int    $course_id     Course.
	 * @param int    $student_id    Learner.
	 * @param string $status        `passed` or `failed`.
	 * @param int    $attempt_id    Graded submission row, or 0.
	 * @return void
	 */
	public static function on_assignment_reviewed( $assignment_id, $course_id, $student_id, $status, $attempt_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ohmylms_assignment_attempts';
		if ( (int) $attempt_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$user_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id = %d AND assignment_id = %d AND user_id = %d', $table, (int) $attempt_id, (int) $assignment_id, (int) $student_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$user_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE assignment_id = %d AND user_id = %d LIMIT 1', $table, (int) $assignment_id, (int) $student_id ) );
		}
		if ( ! $user_id ) {
			return;
		}
		self::dispatch(
			'assignment_reviewed',
			array(
				'user_id'    => $user_id,
				'course_id'  => (int) $course_id,
				'content_id' => (int) $assignment_id,
				'result'     => 'passed' === $status ? 'pass' : ( 'failed' === $status ? 'fail' : '' ),
			)
		);
	}

	/**
	 * Run the active rules of an event.
	 *
	 * @param string $event   Event id.
	 * @param array  $context `{user_id, course_id?, membership_id?, content_id?, order_id?, rate?, result?}`.
	 * @return int Number of rules that ran.
	 */
	public static function dispatch( $event, array $context ) {
		if ( self::$depth >= self::MAX_DEPTH || empty( $context['user_id'] ) || ! get_userdata( (int) $context['user_id'] ) ) {
			return 0;
		}
		$ran = 0;
		foreach ( RuleStore::active_for( $event ) as $row ) {
			$rule       = RuleStore::prepare( $row );
			$conditions = is_array( $rule['conditions'] ) ? $rule['conditions'] : array();
			if ( ! Rules::matches( $conditions, $context ) ) {
				continue;
			}
			$object = (int) ( $context['content_id'] ?? $context['course_id'] ?? $context['membership_id'] ?? 0 );
			$key    = $rule['id'] . ':' . (int) $context['user_id'] . ':' . $object;
			if ( isset( self::$seen[ $key ] ) ) {
				continue;
			}
			self::$seen[ $key ] = true;
			++self::$depth;
			try {
				foreach ( $rule['actions'] as $action ) {
					self::run_action( $rule, $event, $action, $context, $object );
				}
			} finally {
				--self::$depth;
			}
			RuleStore::touch( $rule['id'] );
			++$ran;
		}
		return $ran;
	}

	/**
	 * Run and log one action.
	 *
	 * @param array  $rule    Rule.
	 * @param string $event   Event.
	 * @param array  $action  Action.
	 * @param array  $context Context.
	 * @param int    $object  Object id for the log.
	 * @return void
	 */
	private static function run_action( array $rule, $event, array $action, array $context, $object ) {
		try {
			$result = self::execute( $action, $context, (int) $rule['id'] );
		} catch ( \Throwable $e ) {
			$result = new \WP_Error( 'ohmylms_automation_failed', $e->getMessage() );
		}
		if ( is_wp_error( $result ) ) {
			$status  = in_array( $result->get_error_code(), array( 'ohmylms_automation_skipped', 'ohmylms_student_already_enrolled', 'ohmylms_cohort_member_exists' ), true ) ? 'skipped' : 'failed';
			$message = $result->get_error_message();
		} else {
			$status  = 'success';
			$message = (string) $result;
		}
		RuleStore::log(
			array(
				'automation_id' => (int) $rule['id'],
				'event'         => $event,
				'user_id'       => (int) $context['user_id'],
				'object_id'     => $object,
				'action'        => (string) $action['type'],
				'status'        => $status,
				'message'       => $message,
			)
		);
		/**
		 * Fires after an automation action ran.
		 *
		 * @param int    $rule_id Rule.
		 * @param string $action  Action type.
		 * @param string $status  success, skipped or failed.
		 * @param array  $context Event context.
		 */
		do_action( 'ohmylms_automation_action_ran', (int) $rule['id'], (string) $action['type'], $status, $context );
	}

	/**
	 * Execute an action. Parameters were validated when the rule was saved; targets are checked again
	 * because they may have changed since.
	 *
	 * @param array $action  Action.
	 * @param array $context Context.
	 * @param int   $rule_id Rule.
	 * @return string|\WP_Error Log message or error.
	 */
	private static function execute( array $action, array $context, $rule_id ) {
		$user_id = (int) $context['user_id'];
		switch ( $action['type'] ) {
			case 'send_email':
				$user = get_userdata( $user_id );
				$to   = 'admin' === $action['to'] ? (string) get_option( 'admin_email' ) : ( $user ? $user->user_email : '' );
				if ( ! is_email( $to ) ) {
					return new \WP_Error( 'ohmylms_automation_failed', __( 'No valid recipient address.', 'ohmylms' ) );
				}
				$sent = self::mail( $to, $action['subject'], $action['body'], $context );
				return $sent ? __( 'Email sent.', 'ohmylms' ) : new \WP_Error( 'ohmylms_automation_failed', __( 'The email could not be sent.', 'ohmylms' ) );

			case 'send_coupon':
				$coupon = (int) $action['coupon_id'];
				if ( 'ohmylms_coupon' !== get_post_type( $coupon ) || 'publish' !== get_post_status( $coupon ) ) {
					return new \WP_Error( 'ohmylms_automation_failed', __( 'The coupon is no longer published.', 'ohmylms' ) );
				}
				$user = get_userdata( $user_id );
				if ( ! $user || ! is_email( $user->user_email ) ) {
					return new \WP_Error( 'ohmylms_automation_failed', __( 'No valid recipient address.', 'ohmylms' ) );
				}
				$code = (string) get_post_field( 'post_title', $coupon, 'raw' );
				$sent = self::mail( $user->user_email, $action['subject'], $action['body'], array_merge( $context, array( 'coupon_code' => $code ) ) );
				return $sent ? __( 'Coupon code sent.', 'ohmylms' ) : new \WP_Error( 'ohmylms_automation_failed', __( 'The email could not be sent.', 'ohmylms' ) );

			case 'enrol_course':
				return self::enrol( $user_id, (int) $action['course_id'], $rule_id );

			case 'add_to_cohort':
				$added = Cohorts::add_member( (int) $action['cohort_id'], $user_id, 'automation', $rule_id );
				return is_wp_error( $added ) ? $added : __( 'Added to the cohort.', 'ohmylms' );

			case 'grant_membership':
				return self::grant_membership( $user_id, (int) $action['membership_id'] );
		}
		return new \WP_Error( 'ohmylms_automation_failed', __( 'Unknown action.', 'ohmylms' ) );
	}

	/**
	 * Replace merge tags with plain-text values.
	 *
	 * @param string $text    Template.
	 * @param array  $context Context.
	 * @return string
	 */
	public static function merge( $text, array $context ) {
		$user   = get_userdata( (int) $context['user_id'] );
		$course = (int) ( $context['course_id'] ?? 0 );
		$values = array(
			'{first_name}'   => $user ? (string) $user->first_name : '',
			'{last_name}'    => $user ? (string) $user->last_name : '',
			'{display_name}' => $user ? (string) $user->display_name : '',
			'{course_title}' => $course ? html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ) : '',
			'{course_url}'   => $course ? (string) get_permalink( $course ) : '',
			'{site_name}'    => html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ),
			'{site_url}'     => home_url( '/' ),
			'{coupon_code}'  => (string) ( $context['coupon_code'] ?? '' ),
		);
		return strtr( (string) $text, $values );
	}

	/**
	 * Send a plain-text email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject template.
	 * @param string $body    Body template.
	 * @param array  $context Context.
	 * @return bool
	 */
	private static function mail( $to, $subject, $body, array $context ) {
		$subject = wp_strip_all_tags( self::merge( $subject, $context ) );
		$body    = wp_strip_all_tags( self::merge( $body, $context ) );
		return (bool) wp_mail( $to, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * Enrol through {@see Enrollments} (source `manual`, type `automation`, reference = rule).
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @param int $rule_id   Rule.
	 * @return string|\WP_Error
	 */
	private static function enrol( $user_id, $course_id, $rule_id ) {
		global $wpdb;
		if ( 'ohmylms-course' !== get_post_type( $course_id ) || 'publish' !== get_post_status( $course_id ) ) {
			return new \WP_Error( 'ohmylms_automation_failed', __( 'The course is no longer published.', 'ohmylms' ) );
		}
		$student = new \OhMyLMS\Data\Student( $user_id );
		if ( $student->maybe_banned() ) {
			return new \WP_Error( 'ohmylms_automation_skipped', __( 'The learner is blocked.', 'ohmylms' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh write-side check.
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d AND status IN ('enrolled','pending')", $wpdb->prefix . 'ohmylms_user_enrollment', $user_id, $course_id ) ) ) {
			return new \WP_Error( 'ohmylms_automation_skipped', __( 'Already enrolled.', 'ohmylms' ) );
		}
		$inserted = Enrollments::create(
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array(
				'source'      => 'manual',
				'source_type' => 'automation',
				'source_ref'  => (int) $rule_id,
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new \WP_Error( 'ohmylms_automation_failed', __( 'The enrolment could not be saved.', 'ohmylms' ) );
		}
		update_user_meta( $user_id, '_is_ohmylms_student', 'yes' );
		/** This action is documented in includes/Student/Enrollments.php */
		do_action( 'ohmylms_manual_student_enrollment', $user_id, $course_id );
		return __( 'Enrolled.', 'ohmylms' );
	}

	/**
	 * Give a plan without an order or expiry, then synchronise its courses.
	 *
	 * @param int $user_id Learner.
	 * @param int $plan_id Plan.
	 * @return string|\WP_Error
	 */
	private static function grant_membership( $user_id, $plan_id ) {
		global $wpdb;
		if ( 'ohmylms-membership' !== get_post_type( $plan_id ) || 'publish' !== get_post_status( $plan_id ) ) {
			return new \WP_Error( 'ohmylms_automation_failed', __( 'The plan is no longer published.', 'ohmylms' ) );
		}
		$table = $wpdb->prefix . 'ohmylms_user_membership';
		$lock  = 'ohmylms-plan-grant-' . md5( $wpdb->prefix . ':' . $user_id );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return new \WP_Error( 'ohmylms_automation_failed', __( 'The learner is busy. Try again.', 'ohmylms' ) );
		}
		try {
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND membership_id = %d AND status IN ('enrolled','pending')", $table, $user_id, $plan_id ) ) ) {
				return new \WP_Error( 'ohmylms_automation_skipped', __( 'The learner already holds the plan.', 'ohmylms' ) );
			}
			$inserted = $wpdb->insert(
				$table,
				array(
					'user_id'       => $user_id,
					'membership_id' => $plan_id,
					'status'        => 'enrolled',
					'progress'      => 'running',
					'start_date'    => current_time( 'mysql' ),
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		// phpcs:enable
		if ( ! $inserted ) {
			return new \WP_Error( 'ohmylms_automation_failed', __( 'The plan could not be given.', 'ohmylms' ) );
		}
		\OhMyLMS\Membership\CourseSelection::sync( $plan_id );
		return __( 'Plan given.', 'ohmylms' );
	}
}
