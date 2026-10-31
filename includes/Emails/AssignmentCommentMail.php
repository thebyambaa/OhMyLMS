<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Shared sending of the assignment comment emails.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Both comment emails listen to `ohmylms_assignment_comment_added`; each sends only for comments of the
 * other side of the thread (an instructor comment emails the learner, a learner comment the instructors),
 * never to the commenter. Settings, subject, body and button are editable under Emails like the others.
 */
abstract class AssignmentCommentMail {

	/**
	 * Email id.
	 *
	 * @return string
	 */
	abstract protected function id();

	/**
	 * Role of the comment author this email reacts to: `instructor` or `learner`.
	 *
	 * @return string
	 */
	abstract protected function author_role();

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	abstract public function default_settings(): array;

	/**
	 * Recipient addresses.
	 *
	 * @param object $submission Submission row.
	 * @param array  $settings   Settings.
	 * @param int    $author_id  Commenter.
	 * @return string[]
	 */
	abstract protected function recipients( $submission, array $settings, $author_id );

	/**
	 * Where the button leads.
	 *
	 * @param object $submission Submission row.
	 * @return string
	 */
	abstract protected function link( $submission );

	/**
	 * Listen for new comments.
	 */
	public function __construct() {
		add_action( 'ohmylms_assignment_comment_added', array( $this, 'trigger' ), 10, 3 );
	}

	/**
	 * Stored settings over the defaults.
	 *
	 * @return array
	 */
	public function settings() {
		return Emails::get_template_settings( $this->id(), $this->default_settings() );
	}

	/**
	 * Merge tag values of a comment.
	 *
	 * @param object $submission Submission row.
	 * @param array  $comment    Shaped comment.
	 * @return array<string,string>
	 */
	public static function values( $submission, array $comment ) {
		$learner = get_userdata( (int) $submission->user_id );
		$course  = (int) $submission->course_id ? get_post( (int) $submission->course_id ) : null;
		$body    = (string) $comment['body'];
		return array(
			'student_name'    => $learner ? (string) $learner->display_name : '',
			'course_name'     => $course ? html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ) : '',
			'assignment_name' => html_entity_decode( get_the_title( (int) $submission->assignment_id ), ENT_QUOTES, 'UTF-8' ),
			'commenter_name'  => (string) ( $comment['author']['display_name'] ?? '' ),
			'comment'         => mb_strlen( $body ) > 500 ? mb_substr( $body, 0, 500 ) . '…' : $body,
		);
	}

	/**
	 * Render the email with the shared template.
	 *
	 * @param array  $settings   Settings.
	 * @param array  $values     Tag values.
	 * @param string $button_url Button link.
	 * @return array{subject:string,html:string}
	 */
	public static function render( array $settings, array $values, $button_url ) {
		ob_start();
		ohmylms_get_template(
			'emails/assignment-comment',
			array(
				'settings'       => $settings,
				'email_settings' => Emails::get_email_settings(),
				'button_url'     => $button_url,
			)
		);
		return array(
			'subject' => sanitize_text_field( Emails::render_tags( (string) ( $settings['subject'] ?? '' ), $values, false ) ),
			'html'    => Emails::render_tags( (string) ob_get_clean(), $values, true ),
		);
	}

	/**
	 * Send for a new comment of the matching side.
	 *
	 * @param array  $comment       Shaped comment.
	 * @param object $submission    Submission row.
	 * @param int    $assignment_id Assignment ID.
	 * @return void
	 */
	public function trigger( $comment, $submission, $assignment_id ) {
		if ( ! is_array( $comment ) || ! is_object( $submission ) || (int) $submission->assignment_id !== (int) $assignment_id || $this->author_role() !== ( $comment['role'] ?? '' ) ) {
			return;
		}
		$settings = $this->settings();
		if ( ! Emails::is_enabled( $settings ) ) {
			return;
		}
		$author = (int) ( $comment['author']['id'] ?? 0 );
		$to     = array_values( array_unique( array_filter( $this->recipients( $submission, $settings, $author ), 'is_email' ) ) );
		if ( ! $to ) {
			return;
		}
		$button = ! empty( $settings['button_link'] ) ? (string) $settings['button_link'] : $this->link( $submission );
		$mail   = self::render( $settings, self::values( $submission, $comment ), $button );
		$header = EmailManager::mail_headers( Emails::get_email_settings() );
		foreach ( $to as $address ) {
			wp_mail( $address, $mail['subject'], $mail['html'], $header );
		}
	}

	/**
	 * Body paragraph in the style of the other emails.
	 *
	 * @param string $text Escaped text with tags.
	 * @return string
	 */
	protected static function paragraph( $text ) {
		return '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328; font-family: Helvetica Neue, Helvetica, Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.6;">' . $text . '</p>';
	}
}
