<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Emails;

/**
 * Plugin emails implementation.
 */
class Emails {


	/**
	 * Get emails for Emails.
	 */
	public function get_emails() {
		$emails = array(
			\OhMyLMS\Emails\CreatorsEmail\NewOrder::class,
			\OhMyLMS\Emails\CreatorsEmail\CancelledOrder::class,
			\OhMyLMS\Emails\CreatorsEmail\AssignmentSubmitted::class,
			\OhMyLMS\Emails\CreatorsEmail\QuizSubmitted::class,
			\OhMyLMS\Emails\StudentsEmail\NewOrder::class,
			\OhMyLMS\Emails\StudentsEmail\CompleteCourse::class,
			\OhMyLMS\Emails\StudentsEmail\ConfirmEnrollment::class,
			\OhMyLMS\Emails\StudentsEmail\CancelEnrollment::class,
			\OhMyLMS\Emails\StudentsEmail\AssignmentGraded::class,
			\OhMyLMS\Emails\StudentsEmail\QuizGraded::class,
			\OhMyLMS\Emails\StudentsEmail\ManualEnrollment::class,
			\OhMyLMS\Emails\StudentsEmail\EmailVerification::class,
		);
		return $emails;
	}

	/**
	 * Register email for Emails.
	 */
	public function register_email() {
		$emails = $this->get_emails();
		foreach ( $emails as $email ) {
			new $email();
		}
		$this->register_digest_cron();
	}

	/**
	 * Register digest cron for Emails.
	 */
	public function register_digest_cron() {
		if ( ! wp_next_scheduled( 'ohmylms_send_assignment_submission_digest' ) ) {
			wp_schedule_event( time(), 'daily', 'ohmylms_send_assignment_submission_digest' );
		}
		if ( ! wp_next_scheduled( 'ohmylms_send_quiz_submission_digest' ) ) {
			wp_schedule_event( time(), 'daily', 'ohmylms_send_quiz_submission_digest' );
		}
	}

	/**
	 * Replace merge tags for Emails.
	 *
	 * @param mixed $email Email.
	 * @param mixed $order Order.
	 * @param mixed $course Course.
	 */
	public static function replace_merge_tags( $email, $order, $course = null ) {

		$replacements = array(
			'[student_name]'               => $order ? $order->get_student_name() : '',
			'[Student Name]'               => $order ? $order->get_student_name() : '',
			'Student Name'                 => $order ? $order->get_student_name() : '',
			'{student_name}'               => $order ? $order->get_student_name() : '',
			'{Student_name}'               => $order ? $order->get_student_name() : '',
			'{Student_Name}'               => $order ? $order->get_student_name() : '',
			'[course_name]'                => $course ? $course->get_name() : '',
			'[course name]'                => $course ? $course->get_name() : '',
			'[Course Name]'                => $course ? $course->get_name() : '',
			'[Course_Name]'                => $course ? $course->get_name() : '',
			'{course_name}'                => $course ? $course->get_name() : '',
			'{course name}'                => $course ? $course->get_name() : '',
			'{Course_name}'                => $course ? $course->get_name() : '',
			'{Course name}'                => $course ? $course->get_name() : '',
			'{Course_Name}'                => $course ? $course->get_name() : '',
			'{Course Name}'                => $course ? $course->get_name() : '',
			'<strong>Course Name</strong>' => $course ? '<strong>' . $course->get_name() . '</strong>' : '',
			'[Expected Processing Time]'   => '24 hours',
			'[Your Platform Name]'         => get_bloginfo( 'name' ),
			'[Site Name]'                  => get_bloginfo( 'name' ),
			'[email@example.com]'          => get_option( 'admin_email' ),
		);

		// Ensure the input is valid.
		if ( ! is_string( $email ) || ! is_array( $replacements ) ) {
			return false; // Return false for invalid input.
		}

		// Replace all occurrences in the HTML.
		$updated_email = strtr( $email, $replacements );

		return $updated_email;
	}

	/**
	 * Get email settings for Emails.
	 */
	public static function get_email_settings() {
		$settings = array();
		$keys     = array(
			'ohmylms_email_branding_image',
			'ohmylms_email_base_color',
			'ohmylms_email_background_color',
			'ohmylms_email_body_background_color',
			'ohmylms_email_body_text_color',
			'ohmylms_email_button_possition',
			'ohmylms_email_sender_email_address',
			'ohmylms_email_sender_name',
			'ohmylms_email_footer_text',
		);

		foreach ( $keys as $key ) {
			$settings[ $key ] = get_option( $key, '' );
		}
		return $settings;
	}
}
