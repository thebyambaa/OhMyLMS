<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class OhMyLmsTriggers.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Automation\Triggers;

use MintMail\App\Internal\Automation\HelperFunctions;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * Class OhMyLmsTriggers
 *
 * Handles OhMyLMS automation triggers.
 *
 * @package OhMyLMS\Automation\Triggers
 */
class OhMyLmsTriggers {
	use Singleton;

	/**
	 * The name of the connector used for automation.
	 *
	 * @var string
	 */
	public $connector_name = 'OhMyLMS';

	/**
	 * Register OhMyLMS action hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'ohmylms_after_enrolled_student', array( $this, 'after_enroll_student' ), 10 );
		add_action( 'ohmylms_update_order_status_to_cancelled', array( $this, 'after_cancelled_enrollment' ), 10 );
		add_action( 'ohmylms_checkout_after_create_order', array( $this, 'after_create_order' ), 10, 2 );
		add_action( 'ohmylms_after_lesson_completed', array( $this, 'after_lesson_completed' ), 10, 3 );
		add_action( 'ohmylms_course_completion_rate', array( $this, 'course_completion_rate' ), 10, 3 );
		add_action( 'ohmylms_after_assignment_submitted', array( $this, 'after_assignment_submitted' ), 10, 3 );
		add_action( 'ohmylms_after_assignment_review', array( $this, 'after_assignment_review' ), 10, 4 );
		add_action( 'ohmylms_quiz_submission', array( $this, 'after_quiz_submission' ), 10, 3 );
	}


	/**
	 * Validate the automation step settings.
	 *
	 * @param array $step_data The automation step data.
	 * @param array $data      Runtime trigger data.
	 *
	 * @return bool
	 */
	public function validate_settings( $step_data, $data ) {
		if ( ! isset( $data['trigger_name'], $step_data['automation_id'], $step_data['step_id'] ) ) {
			return false;
		}

		$trigger_name = $data['trigger_name'];
		$settings     = HelperFunctions::get_step_data( $step_data['automation_id'], $step_data['step_id'] );

		if ( isset( $settings['settings']['ohmylms_settings'] ) ) {
			$function_name = 'validate_' . $trigger_name;

			if ( method_exists( $this, $function_name ) ) {
				return call_user_func( array( $this, $function_name ), $settings['settings']['ohmylms_settings'], $data['data'] );
			}
		}

		return false;
	}

	/**
	 * Validate course enrollment trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_course_enrollment( $settings, $data ) {
		if ( isset( $settings['courses'] ) && is_array( $settings['courses'] ) ) {
			foreach ( $settings['courses'] as $course ) {
				if ( isset( $course['value'] ) && (int) $course['value'] === (int) $data['course_id'] ) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Validate course enrollment trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_submit_quiz( $settings, $data ) {
		if ( isset( $settings['quizes'] ) && is_array( $settings['quizes'] ) ) {
			foreach ( $settings['quizes'] as $quiz ) {
				if ( isset( $quiz['value'] ) && (int) $quiz['value'] === (int) $data['content_id'] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Validate course canceled enrollment trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_course_enrollment_cancel( $settings, $data ) {
		if ( isset( $settings['courses'] ) && is_array( $settings['courses'] ) ) {
			foreach ( $settings['courses'] as $course ) {
				if ( isset( $course['value'] ) && (int) $course['value'] === (int) $data['course_id'] ) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Validate new order trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_new_course_order( $settings, $data ) {
		if ( isset( $settings['courses'] ) && is_array( $settings['courses'] ) ) {
			foreach ( $settings['courses'] as $course ) {
				if ( isset( $course['value'] ) && (int) $course['value'] === (int) $data['course_id'] ) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Validate new order trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_submit_assignment( $settings, $data ) {
		if ( isset( $settings['assignments'] ) && is_array( $settings['assignments'] ) ) {
			foreach ( $settings['assignments'] as $assignment ) {
				if ( isset( $assignment['value'] ) && (int) $assignment['value'] === (int) $data['content_id'] ) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Validate new order trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_pass_fail_status_assignment( $settings, $data ) {
		if ( isset( $settings['compare_with'] ) && is_array( $settings['compare_with'] ) ) {
			if ( isset( $data['status'] ) ) {
				if ( 'passed' === $data['status'] && 'pass' === $settings['compare_with']['value'] ) {
					return true;
				}
				if ( 'failed' === $data['status'] && 'fail' === $settings['compare_with']['value'] ) {
					return true;
				}
			}
		}
		return false;
	}


	/**
	 * Validate course completion trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_course_completion_rate( $settings, $data ) {
		$maybe_validate = false;
		if ( isset( $settings['courses'] ) && is_array( $settings['courses'] ) ) {
			foreach ( $settings['courses'] as $course ) {
				if ( isset( $course['value'] ) && (int) $course['value'] === (int) $data['course_id'] ) {
					$maybe_validate = true;
				}
			}
		}

		if ( $maybe_validate && isset( $settings['compare_with'] ) && is_array( $settings['compare_with'] ) ) {
			if ( isset( $settings['compare_with']['value'], $settings['compare_with_value'] ) ) {
				$expected_rate = floatval( $settings['compare_with_value'] );
				$actual_rate   = floatval( $data['completion_rate'] );
				if ( 'less_than' === $settings['compare_with']['value'] ) {
					return $actual_rate < $expected_rate;
				}
				if ( 'greater_than' === $settings['compare_with']['value'] ) {
					return $actual_rate > $expected_rate;
				}
				if ( 'equal_to' === $settings['compare_with']['value'] ) {
					return $actual_rate === $expected_rate;
				}
				if ( 'greater_than_or_equal' === $settings['compare_with']['value'] ) {
					return $actual_rate >= $expected_rate;
				}
				if ( 'less_than_or_equal' === $settings['compare_with']['value'] ) {
					return $actual_rate <= $expected_rate;
				}
			}
		}

		return false;
	}

	/**
	 * Validate lesson completion trigger.
	 *
	 * @param array $settings Automation settings.
	 * @param array $data     Runtime data.
	 *
	 * @return bool
	 */
	private function validate_lms_complete_lesson( $settings, $data ) {
		if ( isset( $settings['lessons'] ) && is_array( $settings['lessons'] ) ) {
			foreach ( $settings['lessons'] as $lesson ) {
				if ( isset( $lesson['value'] ) && (int) $lesson['value'] === (int) $data['content_id'] ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Trigger action after a student is enrolled in a course.
	 *
	 * @param object|int $order Order object or ID.
	 *
	 * @return void
	 */
	public function after_enroll_student( $order ) {
		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = ohmylms_ecommerce_get_order( $order_id );
		}

		if ( ! is_object( $order ) ) {
			return;
		}

		$student_id = $order->get_student_id();
		$user       = get_user_by( 'id', $student_id );

		if ( $user ) {
			global $wpdb;
			$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
			$enroll_data = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d', $table_name, $student_id, $order->get_id() ),
				ARRAY_A
			);

			if ( empty( $enroll_data['course_id'] ) ) {
				return;
			}

			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'lms_course_enrollment',
				'data'           => array(
					'user_email' => $user->user_email ?? '',
					'first_name' => $first_name ? $first_name : '',
					'last_name'  => $last_name ? $last_name : '',
					'course_id'  => $enroll_data['course_id'],
				),
			);

			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}

	/**
	 * Trigger action after a lesson is completed by the student.
	 *
	 * @param int $lesson_id   Lesson ID.
	 * @param int $course_id   Course ID.
	 * @param int $student_id  Student ID.
	 *
	 * @return void
	 */
	public function after_lesson_completed( $lesson_id, $course_id, $student_id ) {
		$user = get_user_by( 'id', $student_id );
		if ( $user ) {
			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'lms_complete_lesson',
				'data'           => array(
					'user_email' => $user->user_email ?? '',
					'first_name' => $first_name ? $first_name : '',
					'last_name'  => $last_name ? $last_name : '',
					'course_id'  => $course_id,
					'content_id' => $lesson_id,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
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
		$user       = get_user_by( 'id', $student_id );

		if ( $user ) {
			global $wpdb;
			$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
			$enroll_data = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND order_id = %d', $table_name, $student_id, $order->get_id() ),
				ARRAY_A
			);

			if ( empty( $enroll_data['course_id'] ) ) {
				return;
			}

			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'lms_course_enrollment_cancel',
				'data'           => array(
					'user_email' => $user->user_email ?? '',
					'first_name' => $first_name ? $first_name : '',
					'last_name'  => $last_name ? $last_name : '',
					'course_id'  => $enroll_data['course_id'],
				),
			);

			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}



	/**
	 * Trigger when a new course order is created
	 *
	 * @param mixed $order       Order object or ID.
	 * @param array $course_data Course-related data.
	 * @return void
	 */
	public function after_create_order( $order, $course_data ) {

		if ( ! is_object( $order ) ) {
			$order_id = absint( $order );
			$order    = ohmylms_ecommerce_get_order( $order_id );
		}

		if ( ! is_object( $order ) ) {
			return;
		}

		$student_id = $order->get_student_id();
		$user       = get_user_by( 'id', $student_id );
		if ( $user ) {

			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			foreach ( $order->get_items() as $item ) {
				$course_id = $item->get_course_id();
				if ( $course_id ) {
					$data = array(
						'connector_name' => $this->connector_name,
						'trigger_name'   => 'lms_new_course_order',
						'data'           => array(
							'user_email' => $user->user_email ?? '',
							'first_name' => $first_name ? $first_name : '',
							'last_name'  => $last_name ? $last_name : '',
							'course_id'  => $course_id,
						),
					);
					do_action( MINT_TRIGGER_AUTOMATION, $data );
				}
			}
		}
	}


	/**
	 * Trigger when course completion rate is updated
	 *
	 * @param int   $student_id       Student ID.
	 * @param int   $course_id        Course ID.
	 * @param float $completion_rate  Completion rate percentage.
	 * @return void
	 */
	public function course_completion_rate( $student_id, $course_id, $completion_rate ) {
		$user = get_user_by( 'id', $student_id );

		if ( $user ) {
			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'lms_course_completion_rate',
				'data'           => array(
					'user_email'      => $user->user_email ?? '',
					'first_name'      => $first_name ? $first_name : '',
					'last_name'       => $last_name ? $last_name : '',
					'course_id'       => $course_id,
					'completion_rate' => $completion_rate,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}


	/**
	 * Trigger when an assignment is submitted
	 *
	 * @param int $assignment_id Assignment ID.
	 * @param int $course_id     Course ID.
	 * @param int $student_id    Student ID.
	 * @return void
	 */
	public function after_assignment_submitted( $assignment_id, $course_id, $student_id ) {
		$user = get_user_by( 'id', $student_id );

		if ( $user ) {
			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'lms_submit_assignment',
				'data'           => array(
					'user_email' => $user->user_email ?? '',
					'first_name' => $first_name ? $first_name : '',
					'last_name'  => $last_name ? $last_name : '',
					'course_id'  => $course_id,
					'content_id' => $assignment_id,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}


	/**
	 * Trigger after an assignment is reviewed (pass/fail)
	 *
	 * @param int    $assignment_id Assignment ID.
	 * @param int    $course_id     Course ID.
	 * @param int    $student_id    Student ID.
	 * @param string $status        Review status (pass/fail).
	 * @return void
	 */
	public function after_assignment_review( $assignment_id, $course_id, $student_id, $status ) {
		$user = get_user_by( 'id', $student_id );

		if ( $user ) {
			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'lms_pass_fail_status_assignment',
				'data'           => array(
					'user_email' => $user->user_email ?? '',
					'first_name' => $first_name ? $first_name : '',
					'last_name'  => $last_name ? $last_name : '',
					'course_id'  => $course_id,
					'content_id' => $assignment_id,
					'status'     => $status,
				),
			);
			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}


	/**
	 * Trigger when a quiz is submitted
	 *
	 * @param int $quiz_id     Quiz ID.
	 * @param int $course_id   Course ID.
	 * @param int $student_id  Student ID.
	 * @return void
	 */
	public function after_quiz_submission( $quiz_id, $course_id, $student_id ) {
		$user = get_user_by( 'id', $student_id );

		if ( $user ) {
			$first_name = get_user_meta( $student_id, 'first_name', true );
			$last_name  = get_user_meta( $student_id, 'last_name', true );

			$data = array(
				'connector_name' => $this->connector_name,
				'trigger_name'   => 'lms_submit_quiz',
				'data'           => array(
					'user_email' => $user->user_email ?? '',
					'first_name' => $first_name ? $first_name : '',
					'last_name'  => $last_name ? $last_name : '',
					'course_id'  => $course_id,
					'content_id' => $quiz_id,
				),
			);

			do_action( MINT_TRIGGER_AUTOMATION, $data );
		}
	}
}
