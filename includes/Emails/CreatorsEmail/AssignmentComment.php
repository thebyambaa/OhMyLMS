<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Email to instructors when a learner comments on their submission.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails\CreatorsEmail;

use OhMyLMS\Admin\LegacyRoutes;
use OhMyLMS\Emails\AssignmentCommentMail;

defined( 'ABSPATH' ) || exit;

/**
 * `instructor_assignment_comment`: sent for each learner comment to the configured staff addresses, or
 * else to the assignment's author and the course's author.
 */
class AssignmentComment extends AssignmentCommentMail {

	const ID = 'instructor_assignment_comment';

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
		return 'learner';
	}

	/**
	 * Basic settings.
	 *
	 * @return array
	 */
	public function basic_settings(): array {
		return array(
			'id'             => self::ID,
			'title'          => __( 'Notify Instructor of an Assignment Comment', 'ohmylms' ),
			'tooltip'        => __( 'Sent to the instructor when a student comments on their assignment submission.', 'ohmylms' ),
			'recipient_type' => 'creator',
			'description'    => __( 'Sent to the instructor when a student comments on their assignment submission.', 'ohmylms' ),
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
			'heading'            => __( 'New comment from a student', 'ohmylms' ),
			'subject'            => __( '{student_name} commented on {assignment_name}', 'ohmylms' ),
			'additional_content' => self::paragraph( esc_html__( '{student_name} commented on their submission for {assignment_name} in {course_name}:', 'ohmylms' ) )
				. self::paragraph( '{comment}' ),
			'button_text'        => __( 'Open the submission', 'ohmylms' ),
			'footer_text'        => __( 'Reply from the submission\'s grading screen.', 'ohmylms' ),
			'button_link'        => '',
			'recipient_email'    => array(),
		);
	}

	/**
	 * Configured staff addresses, else the assignment author and the course author, without the commenter.
	 *
	 * @param object $submission Submission row.
	 * @param array  $settings   Settings.
	 * @param int    $author_id  Commenter.
	 * @return string[]
	 */
	protected function recipients( $submission, array $settings, $author_id ) {
		if ( ! empty( $settings['recipient_email'] ) && is_array( $settings['recipient_email'] ) ) {
			return array_map( 'strval', $settings['recipient_email'] );
		}
		$addresses = array();
		foreach ( array( (int) $submission->assignment_id, (int) $submission->course_id ) as $post_id ) {
			$owner = $post_id ? get_userdata( (int) get_post_field( 'post_author', $post_id ) ) : false;
			if ( $owner && (int) $owner->ID !== (int) $author_id ) {
				$addresses[] = (string) $owner->user_email;
			}
		}
		return $addresses ? $addresses : array( (string) get_option( 'admin_email' ) );
	}

	/**
	 * The grading screen of the submission.
	 *
	 * @param object $submission Submission row.
	 * @return string
	 */
	protected function link( $submission ) {
		return LegacyRoutes::app_url( '/assignments/' . (int) $submission->assignment_id . '/submissions/' . (int) $submission->id );
	}
}
