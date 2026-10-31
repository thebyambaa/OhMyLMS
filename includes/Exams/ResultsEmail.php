<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * "Your exam results are ready" email sent when an exam author releases results.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\Emails\EmailManager;
use OhMyLMS\Emails\Emails;
use OhMyLMS\Emails\StudentsEmail\ExamResultsReleased;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Sends `student_exam_results_released` with what the admin set under Emails ({@see ExamResultsReleased}:
 * toggle, subject, heading, body, button, footer, merge tags `student_name` and `exam_name`) and the global
 * email settings (colours, sender, footer, shared header hook). It carries no marks; the learner signs in
 * to see them.
 */
final class ResultsEmail {

	const ID = ExamResultsReleased::ID;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return ( new ExamResultsReleased() )->default_settings();
	}

	/**
	 * Stored settings over the defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		return ( new ExamResultsReleased() )->settings();
	}

	/**
	 * Whether the email is switched on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Emails::is_enabled( self::settings() );
	}

	/**
	 * Where the button leads: the link set under Emails, else the exam's results.
	 *
	 * @param array $settings Settings.
	 * @param int   $exam_id  Exam ID.
	 * @return string
	 */
	public static function button_url( array $settings, $exam_id ) {
		return ! empty( $settings['button_link'] ) ? (string) $settings['button_link'] : Player::url( $exam_id );
	}

	/**
	 * Render the email for one learner.
	 *
	 * @param WP_User $learner Learner.
	 * @param int     $exam_id Exam ID.
	 * @return array{subject:string,html:string}
	 */
	public static function render( WP_User $learner, $exam_id ) {
		$settings       = self::settings();
		$email_settings = Emails::get_email_settings();
		$values         = array(
			'student_name' => $learner->display_name,
			'exam_name'    => wp_specialchars_decode( get_the_title( $exam_id ), ENT_QUOTES ),
		);
		ob_start();
		ohmylms_get_template(
			'emails/student-exam-results-released',
			array(
				'settings'       => $settings,
				'email_settings' => $email_settings,
				'button_url'     => self::button_url( $settings, $exam_id ),
			)
		);
		return array(
			'subject' => sanitize_text_field( Emails::render_tags( (string) $settings['subject'], $values, false ) ),
			'html'    => Emails::render_tags( (string) ob_get_clean(), $values, true ),
		);
	}

	/**
	 * Send the email to the learner.
	 *
	 * @param WP_User $learner Learner.
	 * @param int     $exam_id Exam ID.
	 * @return bool Sent.
	 */
	public static function send( WP_User $learner, $exam_id ) {
		if ( ! is_email( $learner->user_email ) ) {
			return false;
		}
		$mail = self::render( $learner, $exam_id );
		return (bool) wp_mail( $learner->user_email, $mail['subject'], $mail['html'], EmailManager::mail_headers( Emails::get_email_settings() ) );
	}
}
