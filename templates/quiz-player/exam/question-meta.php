<?php
/**
 * Exam question heading: number, section, available marks and the mark-for-review toggle.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="quiz-box-header ohmylms-exam-question-head">
	<span class="question-number"><?php echo esc_html( sprintf( /* translators: %d: question number. */ __( 'Q: %d', 'ohmylms' ), $ordinal ) ); ?></span>
	<?php if ( ! empty( $section ) ) : ?>
		<span class="ohmylms-exam-question-section"><?php echo esc_html( $section ); ?></span>
	<?php endif; ?>
	<span class="ohmylms-player-marks">
		<?php
		if ( 0.0 === (float) $marks ) {
			esc_html_e( 'Not scored', 'ohmylms' );
		} else {
			/* translators: %s: Available question marks. */
			echo esc_html( sprintf( _n( '%s point', '%s points', $marks, 'ohmylms' ), $marks ) );
		}
		?>
	</span>
</div>
