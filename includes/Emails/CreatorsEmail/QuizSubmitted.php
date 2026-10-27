<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\CreatorsEmail;

use OhMyLMS\Emails\Emails;

/**
 * Plugin quiz submitted implementation.
 */
class QuizSubmitted {

	/**
	 * Initialize Quiz submitted.
	 */
	public function __construct() {
		add_action( 'ohmylms_quiz_submission', array( $this, 'trigger' ), 10, 4 );
		add_action( 'ohmylms_send_quiz_submission_digest', array( $this, 'send_digest' ) );
	}

	/**
	 * Basic settings for Quiz submitted.
	 */
	public function basic_settings(): array {
		return array(
			'id'             => 'instructor_quiz_submitted',
			'template_html'  => 'emails/instructor-quiz-submitted.php',
			'title'          => __( 'Notify Instructor on Quiz Submission', 'ohmylms' ),
			'tooltip'        => __( 'Sent to the instructor when a student completes a quiz. Supports instant or daily digest delivery.', 'ohmylms' ),
			'recipient_type' => 'creator',
			'description'    => __( 'Sent to the instructor when a student completes a quiz. Supports instant or daily digest delivery.', 'ohmylms' ),
		);
	}

	/**
	 * Default settings for Quiz submitted.
	 */
	public function default_settings(): array {
		return array(
			'enable'             => true,
			'delivery_type'      => 'instant',
			'heading'            => __( 'New Quiz Submission', 'ohmylms' ),
			'subject'            => __( 'New quiz submission: {quiz_name} by {student_name}', 'ohmylms' ),
			'additional_content' => '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">A student has completed a quiz in your course. You can review their result below.</p>',
			'button_text'        => __( 'View Results', 'ohmylms' ),
			'footer_text'        => __( 'Click the button below to view the quiz results.', 'ohmylms' ),
			'button_link'        => '',
			'recipient_email'    => array(),
		);
	}

	/**
	 * Trigger for Quiz submitted.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $attempt_data Attempt data.
	 */
	public function trigger( $quiz_id, $course_id, $student_id, $attempt_data ) {
		// Only notify instructor when quiz needs manual review.
		if ( ! isset( $attempt_data['status'] ) || 'in-review' !== $attempt_data['status'] ) {
			return;
		}

		$settings = get_option( 'create_lms_email_instructor_quiz_submitted', $this->default_settings() );

		if ( empty( $settings['enable'] ) ) {
			return;
		}

		global $wpdb;
		$attempt    = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE quiz_id = %d AND student_id = %d ORDER BY id DESC LIMIT 1", $quiz_id, $student_id ) );
		$attempt_id = $attempt ? $attempt->id : 0;

		if ( isset( $settings['delivery_type'] ) && 'digest' === $settings['delivery_type'] ) {
			$queue   = get_option( 'ohmylms_quiz_submission_queue', array() );
			$queue[] = array(
				'quiz_id'    => $quiz_id,
				'attempt_id' => $attempt_id,
				'course_id'  => $course_id,
				'student_id' => $student_id,
				'time'       => current_time( 'mysql' ),
			);
			update_option( 'ohmylms_quiz_submission_queue', $queue, false );
			return;
		}

		$this->send_single( $quiz_id, $attempt_id, $course_id, $student_id, $settings );
	}

	/**
	 * Send digest for Quiz submitted.
	 */
	public function send_digest() {
		$settings = get_option( 'create_lms_email_instructor_quiz_submitted', $this->default_settings() );

		if ( empty( $settings['enable'] ) ) {
			return;
		}

		$queue = get_option( 'ohmylms_quiz_submission_queue', array() );
		if ( empty( $queue ) ) {
			return;
		}

		update_option( 'ohmylms_quiz_submission_queue', array(), false );

		$by_course = array();
		foreach ( $queue as $item ) {
			$by_course[ $item['course_id'] ][] = $item;
		}

		foreach ( $by_course as $course_id => $items ) {
			$this->send_digest_for_course( $course_id, $items, $settings );
		}
	}

	/**
	 * Send single for Quiz submitted.
	 *
	 * @param mixed $quiz_id Quiz identifier.
	 * @param mixed $attempt_id Attempt identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $settings Configuration settings.
	 */
	private function send_single( $quiz_id, $attempt_id, $course_id, $student_id, $settings ) {
		$student = get_userdata( $student_id );
		if ( ! $student ) {
			return;
		}

		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return;
		}

		$to = $this->get_instructor_email( $course_id, $settings );
		if ( ! $to ) {
			return;
		}

		$quiz           = get_post( $quiz_id );
		$quiz_title     = $quiz ? $quiz->post_title : '';
		$email_settings = Emails::get_email_settings();

		$subject = isset( $settings['subject'] ) ? $settings['subject'] : '';
		$subject = $this->replace_tags( $subject, $student->display_name, $course->get_name(), $quiz_title );

		$settings['button_link'] = admin_url( 'admin.php?page=ohmylms#/quiz-report/' . $quiz_id . '/grade-quiz/' . $attempt_id );

		ob_start();
		ohmylms_get_template(
			'emails/instructor-quiz-submitted',
			array(
				'student'        => $student,
				'course'         => $course,
				'quiz_title'     => $quiz_title,
				'settings'       => $settings,
				'email_settings' => $email_settings,
				'is_digest'      => false,
				'digest_items'   => array(),
			)
		);
		$html_body = ob_get_clean();
		$html_body = $this->replace_tags( $html_body, $student->display_name, $course->get_name(), $quiz_title );

		$this->send_email( $to, $subject, $html_body, $email_settings );
	}

	/**
	 * Send digest for course for Quiz submitted.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $items Items.
	 * @param mixed $settings Configuration settings.
	 */
	private function send_digest_for_course( $course_id, $items, $settings ) {
		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return;
		}

		$to = $this->get_instructor_email( $course_id, $settings );
		if ( ! $to ) {
			return;
		}

		$email_settings = Emails::get_email_settings();

		/* translators: 1: submission count, 2: course name */
		$subject = sprintf(
			/* translators: %1$d: number; %2$s: text value. */
			__( 'Daily Digest: %1$d New Quiz Submission(s) in %2$s', 'ohmylms' ),
			count( $items ),
			$course->get_name()
		);

		$digest_items = array();
		foreach ( $items as $item ) {
			$s              = get_userdata( $item['student_id'] );
			$q              = get_post( $item['quiz_id'] );
			$digest_items[] = array(
				'student_name' => $s ? $s->display_name : __( 'Unknown Student', 'ohmylms' ),
				'quiz_title'   => $q ? $q->post_title : __( 'Unknown Quiz', 'ohmylms' ),
				'time'         => $item['time'],
				'grade_link'   => ! empty( $item['attempt_id'] )
					? admin_url( 'admin.php?page=ohmylms#/quiz-report/' . $item['quiz_id'] . '/grade-quiz/' . $item['attempt_id'] )
					: '',
			);
		}

		$digest_settings                = $settings;
		$digest_settings['button_link'] = admin_url( 'admin.php?page=ohmylms#/quiz-report' );

		ob_start();
		ohmylms_get_template(
			'emails/instructor-quiz-submitted',
			array(
				'student'        => null,
				'course'         => $course,
				'quiz_title'     => '',
				'settings'       => $digest_settings,
				'email_settings' => $email_settings,
				'is_digest'      => true,
				'digest_items'   => $digest_items,
			)
		);
		$html_body = ob_get_clean();

		$this->send_email( $to, $subject, $html_body, $email_settings );
	}

	/**
	 * Get instructor email for Quiz submitted.
	 *
	 * @param mixed $course_id Course identifier.
	 * @param mixed $settings Configuration settings.
	 */
	private function get_instructor_email( $course_id, $settings ) {
		if ( ! empty( $settings['recipient_email'] ) ) {
			return $settings['recipient_email'];
		}
		$author_id = (int) get_post_field( 'post_author', $course_id );
		if ( $author_id ) {
			$author = get_userdata( $author_id );
			if ( $author ) {
				return $author->user_email;
			}
		}
		return get_option( 'admin_email' );
	}

	/**
	 * Replace tags for Quiz submitted.
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

	/**
	 * Send email for Quiz submitted.
	 *
	 * @param mixed $to To.
	 * @param mixed $subject Subject.
	 * @param mixed $body Body.
	 * @param mixed $email_settings Email settings.
	 */
	private function send_email( $to, $subject, $body, $email_settings ) {
		$headers      = array( 'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8' );
		$sender_name  = $email_settings['ohmylms_email_sender_name'];
		$sender_email = $email_settings['ohmylms_email_sender_email_address'];
		if ( $sender_email && $sender_name ) {
			$headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
		}
		wp_mail( $to, $subject, $body, $headers );
	}
}
