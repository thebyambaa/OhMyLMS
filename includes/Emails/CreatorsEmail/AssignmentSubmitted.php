<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Emails\CreatorsEmail;

use OhMyLMS\Emails\Emails;

/**
 * Plugin assignment submitted implementation.
 */
class AssignmentSubmitted {

	/**
	 * Initialize Assignment submitted.
	 */
	public function __construct() {
		add_action( 'ohmylms_after_assignment_submitted', array( $this, 'trigger' ), 10, 3 );
		add_action( 'ohmylms_send_assignment_submission_digest', array( $this, 'send_digest' ) );
	}

	/**
	 * Basic settings for Assignment submitted.
	 */
	public function basic_settings(): array {
		return array(
			'id'             => 'instructor_assignment_submitted',
			'template_html'  => 'emails/instructor-assignment-submitted.php',
			'title'          => __( 'Notify Instructor on Assignment Submission', 'ohmylms' ),
			'tooltip'        => __( 'Sent to the instructor when a student submits an assignment. Supports instant or daily digest delivery.', 'ohmylms' ),
			'recipient_type' => 'creator',
			'description'    => __( 'Sent to the instructor when a student submits an assignment. Supports instant or daily digest delivery.', 'ohmylms' ),
		);
	}

	/**
	 * Default settings for Assignment submitted.
	 */
	public function default_settings(): array {
		return array(
			'enable'             => true,
			'delivery_type'      => 'instant',
			'heading'            => __( 'New Assignment Submission', 'ohmylms' ),
			'subject'            => __( 'New submission: {assignment_name} by {student_name}', 'ohmylms' ),
			'additional_content' => '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">A student has submitted an assignment in your course. Please review it at your earliest convenience.</p>',
			'button_text'        => __( 'Review Submissions', 'ohmylms' ),
			'footer_text'        => __( 'Click the button below to review the submission.', 'ohmylms' ),
			'button_link'        => '',
			'recipient_email'    => array(),
		);
	}

	/**
	 * Trigger for Assignment submitted.
	 *
	 * @param mixed $assignment_id Assignment identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 */
	public function trigger( $assignment_id, $course_id, $student_id ) {
		$settings = get_option( 'create_lms_email_instructor_assignment_submitted', $this->default_settings() );

		if ( empty( $settings['enable'] ) ) {
			return;
		}

		if ( isset( $settings['delivery_type'] ) && 'digest' === $settings['delivery_type'] ) {
			$queue   = get_option( 'ohmylms_assignment_submission_queue', array() );
			$queue[] = array(
				'assignment_id' => $assignment_id,
				'course_id'     => $course_id,
				'student_id'    => $student_id,
				'time'          => current_time( 'mysql' ),
			);
			update_option( 'ohmylms_assignment_submission_queue', $queue, false );
			return;
		}

		$this->send_single( $assignment_id, $course_id, $student_id, $settings );
	}

	/**
	 * Send digest for Assignment submitted.
	 */
	public function send_digest() {
		$settings = get_option( 'create_lms_email_instructor_assignment_submitted', $this->default_settings() );

		if ( empty( $settings['enable'] ) ) {
			return;
		}

		$queue = get_option( 'ohmylms_assignment_submission_queue', array() );
		if ( empty( $queue ) ) {
			return;
		}

		update_option( 'ohmylms_assignment_submission_queue', array(), false );

		$by_course = array();
		foreach ( $queue as $item ) {
			$by_course[ $item['course_id'] ][] = $item;
		}

		foreach ( $by_course as $course_id => $items ) {
			$this->send_digest_for_course( $course_id, $items, $settings );
		}
	}

	/**
	 * Send single for Assignment submitted.
	 *
	 * @param mixed $assignment_id Assignment identifier.
	 * @param mixed $course_id Course identifier.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $settings Configuration settings.
	 */
	private function send_single( $assignment_id, $course_id, $student_id, $settings ) {
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

		$assignment       = get_post( $assignment_id );
		$assignment_title = $assignment ? $assignment->post_title : '';
		$email_settings   = Emails::get_email_settings();

		$subject = isset( $settings['subject'] ) ? $settings['subject'] : '';
		$subject = $this->replace_tags( $subject, $student->display_name, $course->get_name(), $assignment_title );

		$settings['button_link'] = admin_url( 'admin.php?page=ohmylms#/assignment-report/' . $assignment_id . '/grade-assignment/' . $student_id );

		ob_start();
		ohmylms_get_template(
			'emails/instructor-assignment-submitted',
			array(
				'student'          => $student,
				'course'           => $course,
				'assignment_title' => $assignment_title,
				'settings'         => $settings,
				'email_settings'   => $email_settings,
				'is_digest'        => false,
				'digest_items'     => array(),
			)
		);
		$html_body = ob_get_clean();
		$html_body = $this->replace_tags( $html_body, $student->display_name, $course->get_name(), $assignment_title );

		$this->send_email( $to, $subject, $html_body, $email_settings );
	}

	/**
	 * Send digest for course for Assignment submitted.
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
			__( 'Daily Digest: %1$d New Assignment Submission(s) in %2$s', 'ohmylms' ),
			count( $items ),
			$course->get_name()
		);

		$digest_items = array();
		foreach ( $items as $item ) {
			$s              = get_userdata( $item['student_id'] );
			$a              = get_post( $item['assignment_id'] );
			$digest_items[] = array(
				'student_name'     => $s ? $s->display_name : __( 'Unknown Student', 'ohmylms' ),
				'assignment_title' => $a ? $a->post_title : __( 'Unknown Assignment', 'ohmylms' ),
				'time'             => $item['time'],
				'grade_link'       => admin_url( 'admin.php?page=ohmylms#/assignment-report/' . $item['assignment_id'] . '/grade-assignment/' . $item['student_id'] ),
			);
		}

		$digest_settings                = $settings;
		$digest_settings['button_link'] = admin_url( 'admin.php?page=ohmylms#/assignment-report' );

		ob_start();
		ohmylms_get_template(
			'emails/instructor-assignment-submitted',
			array(
				'student'          => null,
				'course'           => $course,
				'assignment_title' => '',
				'settings'         => $digest_settings,
				'email_settings'   => $email_settings,
				'is_digest'        => true,
				'digest_items'     => $digest_items,
			)
		);
		$html_body = ob_get_clean();

		$this->send_email( $to, $subject, $html_body, $email_settings );
	}

	/**
	 * Get instructor email for Assignment submitted.
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
	 * Replace tags for Assignment submitted.
	 *
	 * @param mixed $content Content.
	 * @param mixed $student_name Student name.
	 * @param mixed $course_name Course name.
	 * @param mixed $assignment_name Assignment name.
	 */
	private function replace_tags( $content, $student_name, $course_name, $assignment_name ) {
		return str_replace(
			array( '{student_name}', '{course_name}', '{assignment_name}' ),
			array( $student_name, $course_name, $assignment_name ),
			$content
		);
	}

	/**
	 * Send email for Assignment submitted.
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
