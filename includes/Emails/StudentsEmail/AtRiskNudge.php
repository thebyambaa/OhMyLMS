<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Encouragement email sent from the dashboard's At-risk learners widget.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\EmailManager;
use OhMyLMS\Emails\Emails;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * `student_at_risk_nudge`: a fixed template (subject, heading, body, button and footer editable under
 * Emails) sent to a learner when staff press "Nudge". There is no trigger hook: the dashboard's
 * `AtRiskNudges` service calls {@see AtRiskNudge::send()} after its scope checks and rate limits.
 */
class AtRiskNudge {

	const ID = 'student_at_risk_nudge';

	/**
	 * Basic settings.
	 *
	 * @return array
	 */
	public function basic_settings(): array {
		return array(
			'id'             => self::ID,
			'title'          => __( 'Nudge an At-Risk Learner', 'ohmylms' ),
			'tooltip'        => __( 'Sent to a learner when a teacher or administrator presses "Nudge" in the dashboard\'s At-risk learners widget.', 'ohmylms' ),
			'recipient_type' => 'student',
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public function default_settings(): array {
		$p = '<p style="margin: 0 0 15px 0; padding:0; color: #1F2328;">';
		return array(
			'enable'             => true,
			'heading'            => __( 'We miss you!', 'ohmylms' ),
			'subject'            => __( '{sender_name} checked in on your learning', 'ohmylms' ),
			'additional_content' => $p . esc_html__( 'Hi {student_name},', 'ohmylms' ) . '</p>'
				. $p . esc_html__( '{sender_name} noticed you could use a little boost and wanted to check in. Here is what we noticed: {reasons}.', 'ohmylms' ) . '</p>'
				. $p . esc_html__( 'Pick up where you left off. Even a few minutes today makes a difference, and your teacher is happy to help if you are stuck.', 'ohmylms' ) . '</p>',
			'button_text'        => __( 'Continue learning', 'ohmylms' ),
			'button_link'        => '',
			'footer_text'        => __( 'You receive this email because you are enrolled on {site_name}.', 'ohmylms' ),
		);
	}

	/**
	 * Stored settings over the defaults.
	 *
	 * @return array
	 */
	public function settings() {
		return Emails::get_template_settings( self::ID, $this->default_settings() );
	}

	/**
	 * Whether the email is switched on.
	 *
	 * @return bool
	 */
	public function enabled() {
		return Emails::is_enabled( $this->settings() );
	}

	/**
	 * Where the button leads: the configured link, else the learner dashboard page, else the home page.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function button_url( array $settings ) {
		if ( ! empty( $settings['button_link'] ) ) {
			return (string) $settings['button_link'];
		}
		$page = function_exists( 'ohmylms_get_page_permalink' ) ? (string) ohmylms_get_page_permalink( 'student_dashboard' ) : '';
		return '' !== $page ? $page : home_url( '/' );
	}

	/**
	 * Render the email.
	 *
	 * @param array $settings Settings.
	 * @param array $values   Tag values: student_name, sender_name, reasons.
	 * @return array{subject:string,html:string}
	 */
	public static function render( array $settings, array $values ) {
		$email_settings = Emails::get_email_settings();
		ob_start();
		ohmylms_get_template(
			'emails/at-risk-nudge',
			array(
				'settings'       => $settings,
				'email_settings' => $email_settings,
				'button_url'     => self::button_url( $settings ),
			)
		);
		$html    = Emails::render_tags( (string) ob_get_clean(), $values, true );
		$subject = sanitize_text_field( Emails::render_tags( (string) ( $settings['subject'] ?? '' ), $values, false ) );
		return array(
			'subject' => $subject,
			'html'    => $html,
		);
	}

	/**
	 * Send the nudge to the learner, and a separate copy to each extra address (guardians).
	 *
	 * @param WP_User  $learner     Learner.
	 * @param string   $sender_name Display name of the sender.
	 * @param string   $reasons     Reasons, such as "Inactive 9 days · 2 overdue items".
	 * @param string[] $copies      Guardian addresses.
	 * @return array{sent:bool,guardians:int}
	 */
	public function send( WP_User $learner, $sender_name, $reasons, array $copies = array() ) {
		$settings = $this->settings();
		$values   = array(
			'student_name' => $learner->display_name,
			'sender_name'  => '' !== (string) $sender_name ? (string) $sender_name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'reasons'      => (string) $reasons,
		);
		$mail     = self::render( $settings, $values );
		$headers  = EmailManager::mail_headers( Emails::get_email_settings() );
		$sent     = wp_mail( $learner->user_email, $mail['subject'], $mail['html'], $headers );
		$copied   = 0;
		if ( $sent ) {
			foreach ( array_unique( $copies ) as $address ) {
				if ( is_email( $address ) && strtolower( $address ) !== strtolower( $learner->user_email ) && wp_mail( $address, $mail['subject'], $mail['html'], $headers ) ) {
					++$copied;
				}
			}
		}
		return array(
			'sent'      => (bool) $sent,
			'guardians' => $copied,
		);
	}
}
