<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;

/**
 * Plugin manual enrollment implementation.
 */
class ManualEnrollment {

	/**
	 * Initialize Manual enrollment.
	 */
	public function __construct() {
		add_action( 'ohmylms_manual_enrollment_by_email', array( $this, 'trigger' ), 10, 5 );
	}

	/**
	 * Basic settings for Manual enrollment.
	 */
	public function basic_settings(): array {
		return array(
			'id'             => 'student_manual_enrollment',
			'title'          => __( 'Notify Student on Manual Enrollment', 'ohmylms' ),
			'tooltip'        => __( 'Sent to a student when an admin manually enrolls them in a course.', 'ohmylms' ),
			'recipient_type' => 'student',
		);
	}

	/**
	 * Default settings for Manual enrollment.
	 */
	public function default_settings(): array {
		return array(
			'enable'             => true,
			'heading'            => __( 'You\'ve been enrolled!', 'ohmylms' ),
			'subject'            => __( 'You now have access to [course_name]', 'ohmylms' ),
			'additional_content' => '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">Hi [student_name],</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">You have been enrolled in <strong>[course_name]</strong>. Click below to start learning right away.</p>',
			'button_text'        => __( 'Access My Course', 'ohmylms' ),
			'footer_text'        => __( 'Happy learning!', 'ohmylms' ),
		);
	}

	/**
	 * Returns the student profile page URL via the plugin's page helper.
	 * Falls back to home URL if no profile page is configured.
	 *
	 * @return string
	 */
	private function get_profile_url() {
		return ohmylms_get_page_permalink( 'student_profile' );
	}


	/**
	 * Trigger for Manual enrollment.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $username Username.
	 * @param mixed $plain_password Plain password.
	 * @param mixed $is_new_user Is new user.
	 */
	public function trigger( $student_id, $course_id, $username, $plain_password, $is_new_user ) {
		$user = get_user_by( 'id', $student_id );
		if ( ! $user ) {
			return;
		}

		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return;
		}

		$settings = Emails::get_template_settings( 'student_manual_enrollment', $this->default_settings() );

		if ( ! Emails::is_enabled( $settings ) ) {
			return;
		}
		$email_settings = Emails::get_email_settings();

		$subject = isset( $settings['subject'] ) ? $settings['subject'] : $this->default_settings()['subject'];
		$subject = Emails::render_tags(
			$subject,
			array(
				'student_name' => $user->display_name,
				'course_name'  => $course->get_name(),
			)
		);

		ob_start();
		ohmylms_get_template(
			'emails/manual-enrollment',
			array(
				'student_name'   => $user->display_name,
				'course'         => $course,
				'settings'       => $settings,
				'email_settings' => $email_settings,
				'username'       => $username,
				'plain_password' => $plain_password,
				'is_new_user'    => $is_new_user,
				'login_url'      => $this->get_profile_url(),
				'start_date'     => current_time( 'mysql' ),
			)
		);
		$html_body = ob_get_clean();

		$html_body = Emails::render_tags(
			$html_body,
			array(
				'student_name' => $user->display_name,
				'course_name'  => $course->get_name(),
			)
		);

		$sender_name  = $email_settings['ohmylms_email_sender_name'];
		$sender_email = $email_settings['ohmylms_email_sender_email_address'];
		$headers      = array(
			'MIME-Version: 1.0',
			'Content-Type: text/html; charset=UTF-8',
		);
		if ( $sender_email && $sender_name ) {
			$headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
		}

		wp_mail( $user->user_email, $subject, $html_body, $headers );
	}
}
