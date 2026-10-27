<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;

/**
 * Plugin assignment graded implementation.
 */
class AssignmentGraded {

	/**
	 * Initialize Assignment graded.
	 */
	public function __construct() {
		add_action( 'ohmylms_after_assignment_review', array( $this, 'trigger' ), 10, 4 );
	}

	/**
	 * Basic settings for Assignment graded.
	 */
	public function basic_settings(): array {
		return array(
			'id'             => 'student_assignment_graded',
			'template_html'  => 'emails/student-assignment-graded.php',
			'title'          => __( 'Notify Student When Assignment Is Graded', 'ohmylms' ),
			'tooltip'        => __( 'Sent to the student after an instructor grades their assignment submission.', 'ohmylms' ),
			'recipient_type' => 'student',
			'description'    => __( 'Sent to the student after an instructor grades their assignment submission.', 'ohmylms' ),
		);
	}

	/**
	 * Default settings for Assignment graded.
	 */
	public function default_settings(): array {
		return array(
			'enable'             => true,
			'heading'            => __( 'Your Assignment Has Been Graded', 'ohmylms' ),
			'subject'            => __( 'Your assignment {assignment_name} has been graded', 'ohmylms' ),
			'additional_content' => '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Dear {student_name},</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Your instructor has reviewed and graded your assignment submission. Please log in to view your result and any feedback provided.</p>',
			'button_text'        => __( 'View My Grade', 'ohmylms' ),
			'footer_text'        => __( 'Click the button below to view your grade and instructor feedback.', 'ohmylms' ),
			'button_link'        => '',
			'recipient_email'    => array(),
		);
	}

	/**
	 * Trigger for Assignment graded.
	 *
	 * @param mixed $assignment_id Assignment identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $status Status.
	 */
	public function trigger( $assignment_id, $course_id, $student_id, $status ) {
		$settings = get_option( 'create_lms_email_student_assignment_graded', $this->default_settings() );

		if ( empty( $settings['enable'] ) ) {
			return;
		}

		$student = get_userdata( $student_id );
		if ( ! $student ) {
			return;
		}

		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return;
		}

		$assignment       = get_post( $assignment_id );
		$assignment_title = $assignment ? $assignment->post_title : '';

		global $wpdb;
		$attempt     = $wpdb->get_row( $wpdb->prepare( "SELECT score FROM {$wpdb->prefix}ohmylms_assignment_attempts WHERE user_id = %d AND assignment_id = %d ORDER BY id DESC LIMIT 1", $student_id, $assignment_id ) );
		$score       = $attempt ? $attempt->score : '';
		$total_marks = get_post_meta( $assignment_id, '_total_points', true );

		$email_settings = Emails::get_email_settings();

		$to      = $student->user_email;
		$subject = isset( $settings['subject'] ) ? $settings['subject'] : '';
		$subject = $this->replace_tags( $subject, $student->display_name, $course->get_name(), $assignment_title, $status );

		if ( isset( $settings['button_link'] ) && empty( $settings['button_link'] ) ) {
			$settings['button_link'] = ohmylms_get_page_permalink( 'student_dashboard' );
		}

		ob_start();
		ohmylms_get_template(
			'emails/student-assignment-graded',
			array(
				'student'          => $student,
				'course'           => $course,
				'assignment_title' => $assignment_title,
				'status'           => $status,
				'score'            => $score,
				'total_marks'      => $total_marks,
				'settings'         => $settings,
				'email_settings'   => $email_settings,
			)
		);
		$html_body = ob_get_clean();
		$html_body = $this->replace_tags( $html_body, $student->display_name, $course->get_name(), $assignment_title, $status );

		$headers      = array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8' );
		$sender_name  = $email_settings['ohmylms_email_sender_name'];
		$sender_email = $email_settings['ohmylms_email_sender_email_address'];
		if ( $sender_email && $sender_name ) {
			$headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
		}

		wp_mail( $to, $subject, $html_body, $headers );
	}

	/**
	 * Replace tags for Assignment graded.
	 *
	 * @param mixed $content Content.
	 * @param mixed $student_name Student name.
	 * @param mixed $course_name Course name.
	 * @param mixed $assignment_name Assignment name.
	 * @param mixed $status Status.
	 */
	private function replace_tags( $content, $student_name, $course_name, $assignment_name, $status ) {
		$status_label = 'passed' === $status ? __( 'Passed', 'ohmylms' ) : __( 'Failed', 'ohmylms' );
		return str_replace(
			array( '{student_name}', '{course_name}', '{assignment_name}', '{status}' ),
			array( $student_name, $course_name, $assignment_name, $status_label ),
			$content
		);
	}
}
