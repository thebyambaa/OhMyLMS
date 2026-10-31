<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * "Your exam results are ready" email sent when an exam author releases results.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\Emails\EmailManager;
use OhMyLMS\Emails\Emails;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * `student_exam_results_released` uses the plugin's email system: global email settings (colours,
 * sender, footer), the shared header hook, merge tags and stored per-email settings
 * (`create_lms_email_student_exam_results_released`, merged over the defaults below). It carries no
 * marks; the learner signs in to see them. It is not listed in the Emails screen yet.
 */
final class ResultsEmail {

	const ID = 'student_exam_results_released';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		$p = '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">';
		return array(
			'enable'             => true,
			'heading'            => __( 'Your exam results are ready', 'ohmylms' ),
			'subject'            => __( 'Your results for {exam_name} are ready', 'ohmylms' ),
			'additional_content' => $p . esc_html__( 'Hi {student_name},', 'ohmylms' ) . '</p>'
				. $p . esc_html__( 'Your results for {exam_name} have been released. Sign in to see your marks and feedback.', 'ohmylms' ) . '</p>',
			'button_text'        => __( 'View my results', 'ohmylms' ),
			'footer_text'        => __( 'You receive this email because you took this exam on {site_name}.', 'ohmylms' ),
		);
	}

	/**
	 * Stored settings over the defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		return Emails::get_template_settings( self::ID, self::defaults() );
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
				'button_url'     => Player::url( $exam_id ),
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
