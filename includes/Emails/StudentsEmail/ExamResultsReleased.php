<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * "Your exam results are ready" email, as listed under Emails.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails\StudentsEmail;

use OhMyLMS\Emails\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * `student_exam_results_released`: sent once to a learner when the results of their exam attempt are
 * released (manually or on the scheduled date). There is no trigger hook: `Exams\Results` sends it through
 * {@see \OhMyLMS\Exams\ResultsEmail}, which reads these settings. It carries no marks.
 */
class ExamResultsReleased {

	const ID = 'student_exam_results_released';

	/**
	 * Basic settings.
	 *
	 * @return array
	 */
	public function basic_settings(): array {
		return array(
			'id'             => self::ID,
			'title'          => __( 'Exam Results Released', 'ohmylms' ),
			'tooltip'        => __( 'Sent to a learner when the results of their exam are released, by the author or on the scheduled date. It carries no marks; the button opens the results unless a link is set.', 'ohmylms' ),
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
			'heading'            => __( 'Your exam results are ready', 'ohmylms' ),
			'subject'            => __( 'Your results for {exam_name} are ready', 'ohmylms' ),
			'additional_content' => $p . esc_html__( 'Hi {student_name},', 'ohmylms' ) . '</p>'
				. $p . esc_html__( 'Your results for {exam_name} have been released. Sign in to see your marks and feedback.', 'ohmylms' ) . '</p>',
			'button_text'        => __( 'View my results', 'ohmylms' ),
			'button_link'        => '',
			'footer_text'        => __( 'You receive this email because you took this exam on {site_name}.', 'ohmylms' ),
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
}
