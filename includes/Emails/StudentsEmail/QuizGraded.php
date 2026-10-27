<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;

/**
 * Plugin quiz graded implementation.
 */
class QuizGraded {

	/**
	 * Initialize Quiz graded.
	 */
	public function __construct() {
		add_action( 'ohmylms_rest_review_quiz_attempt', array( $this, 'trigger' ), 10, 4 );
	}

	/**
	 * Basic settings for Quiz graded.
	 */
	public function basic_settings(): array {
		return array(
			'id'             => 'student_quiz_graded',
			'template_html'  => 'emails/student-quiz-graded.php',
			'title'          => __( 'Notify Student When Quiz Is Graded', 'ohmylms' ),
			'tooltip'        => __( 'Sent to the student after an instructor grades their quiz submission.', 'ohmylms' ),
			'recipient_type' => 'student',
			'description'    => __( 'Sent to the student after an instructor grades their quiz submission.', 'ohmylms' ),
		);
	}

	/**
	 * Default settings for Quiz graded.
	 */
	public function default_settings(): array {
		return array(
			'enable'             => true,
			'heading'            => __( 'Your Quiz Has Been Graded', 'ohmylms' ),
			'subject'            => __( 'Your quiz {quiz_name} has been graded', 'ohmylms' ),
			'additional_content' => '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Dear {student_name},</p><p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">Your instructor has reviewed and graded your quiz. Please log in to view your result.</p>',
			'button_text'        => __( 'View My Result', 'ohmylms' ),
			'footer_text'        => __( 'Click the button below to view your quiz result.', 'ohmylms' ),
			'button_link'        => '',
			'recipient_email'    => array(),
		);
	}

	/**
	 * Trigger for Quiz graded.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $score Score.
	 */
	public function trigger( $quiz_id, $course_id, $student_id, $score ) {
		$settings = get_option( 'create_lms_email_student_quiz_graded', $this->default_settings() );

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

		$quiz       = get_post( $quiz_id );
		$quiz_title = $quiz ? $quiz->post_title : '';

		$passing_mark   = get_post_meta( $quiz_id, '_passing_grade', true );
		$total_marks    = get_post_meta( $quiz_id, '_total_marks', true );
		$status         = ( $total_marks > 0 && $score >= $passing_mark ) ? 'passed' : 'failed';
		$email_settings = Emails::get_email_settings();

		$to      = $student->user_email;
		$subject = isset( $settings['subject'] ) ? $settings['subject'] : '';
		$subject = $this->replace_tags( $subject, $student->display_name, $course->get_name(), $quiz_title );

		if ( isset( $settings['button_link'] ) && empty( $settings['button_link'] ) ) {
			$settings['button_link'] = ohmylms_get_page_permalink( 'student_dashboard' );
		}

		ob_start();
		ohmylms_get_template(
			'emails/student-quiz-graded',
			array(
				'student'        => $student,
				'course'         => $course,
				'quiz_title'     => $quiz_title,
				'score'          => $score,
				'passing_mark'   => $passing_mark,
				'total_marks'    => $total_marks,
				'status'         => $status,
				'settings'       => $settings,
				'email_settings' => $email_settings,
			)
		);
		$html_body = ob_get_clean();
		$html_body = $this->replace_tags( $html_body, $student->display_name, $course->get_name(), $quiz_title );

		$headers      = array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8' );
		$sender_name  = $email_settings['ohmylms_email_sender_name'];
		$sender_email = $email_settings['ohmylms_email_sender_email_address'];
		if ( $sender_email && $sender_name ) {
			$headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
		}

		wp_mail( $to, $subject, $html_body, $headers );
	}

	/**
	 * Replace tags for Quiz graded.
	 *
	 * @param mixed $content Content.
	 * @param mixed $student_name Student name.
	 * @param mixed $course_name Course name.
	 * @param mixed $quiz_name Quiz name.
	 */
	private function replace_tags( $content, $student_name, $course_name, $quiz_name ) {
		return str_replace(
			array( '{student_name}', '{course_name}', '{quiz_name}' ),
			array( $student_name, $course_name, $quiz_name ),
			$content
		);
	}
}
