<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Connector class for MRM Automation
 *
 * @package OhMyLMS\Automation
 */

namespace OhMyLMS\Automation;

use MintMail\App\Internal\Automation\Automation_Connector;
use OhMyLMS\Automation\Triggers\OhMyLmsTriggers;
use Mint\Mrm\Internal\Traits\Singleton;

/**
 * Class ConnectorOhMyLms
 *
 * Main connector class for OhMyLMS Automation.
 */
class ConnectorOhMyLms extends Automation_Connector {

	use Singleton;

	/**
	 * OhMyLMS triggers
	 *
	 * @var array $triggers
	 */
	public $triggers;

	/**
	 * Constructor - Initializes the connector and hooks if connected.
	 */
	public function __construct() {
		if ( $this->maybe_connected() ) {
			OhMyLmsTriggers::get_instance()->init();
		}
	}

	/**
	 * Get the connector name.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_name() {
		return 'OhMyLMS';
	}

	/**
	 * Check if the connector is connected.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function maybe_connected() {
		return defined( 'MAILMINT' );
	}

	/**
	 * Get all available triggers for OhMyLMS.
	 *
	 * @return array List of supported triggers.
	 */
	public function get_triggers() {
		$this->triggers = $this->get_supported_triggers();
		return $this->triggers;
	}

	/**
	 * Return all supported triggers by the OhMyLMS connector.
	 *
	 * @return array
	 */
	public function get_supported_triggers() {
		return array(
			array(
				'key'   => 'lms_course_enrollment',
				'label' => 'Course Enrollment',
			),
			array(
				'key'   => 'lms_complete_lesson',
				'label' => 'Complete Lesson',
			),
			array(
				'key'   => 'lms_course_enrollment_cancel',
				'label' => 'Enrollment Cancellation',
			),
			array(
				'key'   => 'lms_new_course_order',
				'label' => 'New Course order',
			),
			array(
				'key'   => 'lms_course_completion_rate',
				'label' => 'Course Completion Rate',
			),
			array(
				'key'   => 'lms_submit_assignment',
				'label' => 'Submit Assignment',
			),
			array(
				'key'   => 'lms_pass_fail_status_assignment',
				'label' => 'Pass/Fail Status',
			),
			array(
				'key'   => 'lms_submit_quiz',
				'label' => 'Submit Quiz',
			),
		);
	}
}
