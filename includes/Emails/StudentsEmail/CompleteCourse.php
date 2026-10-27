<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;
use function OhMyLMS\Commerce\ecommerce;

/**
 * Plugin complete course implementation.
 */
class CompleteCourse {

	/**
	 * Initialize Complete course.
	 */
	public function __construct() {
		add_action( 'ohmylms_course_completed', array( $this, 'trigger' ), 10, 3 );
	}

	/**
	 * Basic settings for Complete course.
	 */
	public function basic_settings(): array {
		$basic = array(
			'id'             => 'student_course_completed',
			'template_html'  => 'emails/creators/new-order.php',
			'title'          => __( 'Notify Student on Course Completion', 'ohmylms' ),
			'tooltip'        => __( 'Notify the students when they complete the course.', 'ohmylms' ),
			'recipient_type' => 'student',
			'description'    => __( 'Notify the students when they complete the course.', 'ohmylms' ),
		);
		return $basic;
	}

	/**
	 * Default settings for Complete course.
	 */
	public function default_settings(): array {
		$default_settings = array(
			'enable'                 => false,
			'heading'                => __( 'Congratulations! You\'ve Completed', 'ohmylms' ),
			'subject'                => __( 'Congratulations! complete course', 'ohmylms' ),
			'additional_content'     => '<p>Dear [student_name],</p><p>Congratulations on successfully completing <strong>[course_name]</strong>! Your hard work and dedication have paid off, and we\'re thrilled to celebrate this milestone with you.</p>',
			'button_text'            => 'Access My Certificate',
			'course_suggestion_text' => 'To continue on your implementation journey, We recommend you take the following courses next.',
			'footer_text'            => 'Click below to view your course and access your certificate:',
			'button_link'            => '',
			'recipient_email'        => array(),
		);
		return $default_settings;
	}

	/**
	 * Trigger for Complete course.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $order_id Order identifier.
	 */
	public function trigger( $student_id, $course_id, $order_id ) {
		$settings = get_option( 'create_lms_email_student_course_completed', $this->default_settings() );
		$to       = '';
		$user     = get_user_by( 'id', $student_id );
		$name     = '';
		if ( $user ) {
			$to   = $user->user_email;
			$name = $user->display_name;
		}

		if ( ! $to ) {
			return;
		}

		$course = ohmylms_get_course( $course_id );

		global $wpdb;
		$table_name  = $wpdb->prefix . 'ohmylms_user_enrollment';
		$enroll_data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d AND course_id = %d', $table_name, $student_id, $course_id ), ARRAY_A );

		$subject        = $settings['subject'];
		$certificate    = $course->get_certificate();
		$certificate_id = '';
		if ( $certificate ) {
			$certificate_id = $certificate->get_id();
		}
		$email_settings = Emails::get_email_settings();
		if ( is_array( $email_settings ) && is_array( $enroll_data ) && is_array( $settings ) && $course ) {
			$student = new \OhMyLMS\Data\Student( $student_id );
			// Use ohmylms_get_template to get the email body.
			ob_start();
			ohmylms_get_template(
				'emails/complete-course', // Template file name (without .php).
				array(
					'course'         => $course,
					'enroll_data'    => $enroll_data,
					'student_name'   => $name,
					'student'        => $student,
					'settings'       => $settings,
					'certificate_id' => $certificate_id,
					'email_settings' => $email_settings,
				)
			);

			$html_body = ob_get_clean();

			ob_start();
			ohmylms_get_template( 'emails/email-styles' );
			$styles    = ob_get_clean();
			$order     = ohmylms_ecommerce_get_order( $order_id );
			$html_body = Emails::replace_merge_tags( $html_body, $order, $course );

			$sender_name  = $email_settings['ohmylms_email_sender_name'];
			$sender_email = $email_settings['ohmylms_email_sender_email_address'];
			$headers      = array(
				'MIME-Version: 1.0',
				'Content-Type: text/html; charset=UTF-8',
			);

			if ( $sender_email && $sender_name ) {
				$from      = 'From: ' . $sender_name;
				$from      = 'From: ' . $sender_name . ' <' . $sender_email . '>';
				$headers[] = $from;
			}

			wp_mail( $to, $subject, $html_body, $headers );
		}
	}
}
