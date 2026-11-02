<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Email to the learner when an instructor comments on their submission.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\AssignmentCommentMail;

defined( 'ABSPATH' ) || exit;

/**
 * `student_assignment_comment`: sent to the submission's learner for each instructor comment.
 */
class AssignmentComment extends AssignmentCommentMail {

	const ID = 'student_assignment_comment';

	/**
	 * Email id.
	 *
	 * @return string
	 */
	protected function id() {
		return self::ID;
	}

	/**
	 * Author role this email reacts to.
	 *
	 * @return string
	 */
	protected function author_role() {
		return 'instructor';
	}

	/**
	 * Basic settings.
	 *
	 * @return array
	 */
	public function basic_settings(): array {
		return array(
			'id'             => self::ID,
			'title'          => __( 'Notify Student of an Assignment Comment', 'ohmylms' ),
			'tooltip'        => __( 'Sent to the student when an instructor comments on their assignment submission.', 'ohmylms' ),
			'recipient_type' => 'student',
			'description'    => __( 'Sent to the student when an instructor comments on their assignment submission.', 'ohmylms' ),
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function default_settings(): array {
		return array(
			'enable'             => true,
			'heading'            => __( 'New comment on your assignment', 'ohmylms' ),
			'subject'            => __( '{commenter_name} commented on {assignment_name}', 'ohmylms' ),
			'additional_content' => self::paragraph( esc_html__( 'Hi {student_name},', 'ohmylms' ) )
				. self::paragraph( esc_html__( '{commenter_name} commented on your submission for {assignment_name} in {course_name}:', 'ohmylms' ) )
				. self::paragraph( '{comment}' ),
			'button_text'        => __( 'Reply', 'ohmylms' ),
			'footer_text'        => __( 'Open the assignment to read the whole thread and reply.', 'ohmylms' ),
			'button_link'        => '',
		);
	}

	/**
	 * The submission's learner, unless they wrote the comment.
	 *
	 * @param object $submission Submission row.
	 * @param array  $settings   Settings.
	 * @param int    $author_id  Commenter.
	 * @return string[]
	 */
	protected function recipients( $submission, array $settings, $author_id ) {
		$learner = get_userdata( (int) $submission->user_id );
		return $learner && (int) $learner->ID !== (int) $author_id ? array( (string) $learner->user_email ) : array();
	}

	/**
	 * The assignment page at the learner's submission.
	 *
	 * @param object $submission Submission row.
	 * @return string
	 */
	protected function link( $submission ) {
		$url = (string) ohmylms_get_pretty_content_permalink( (int) $submission->assignment_id );
		return ( $url ? $url : home_url( '/' ) ) . '#ohmylms-submission-' . (int) $submission->id;
	}
}
