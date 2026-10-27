<?php
/**
 * Question ordinal and available marks, without exposing grading keys.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
// Paper View numbers questions and shows marks as the printed paper does.
if ( ! empty( $paper ) ) {
	?>
	<div class="quiz-box-header ohmylms-paper-question-header">
		<span class="question-number"><?php echo esc_html( ( 'q' === $paper['numbering'] ? 'Q' : '' ) . $ordinal ); ?></span>
		<?php if ( $paper['show_marks'] && 0.0 < (float) $marks ) { ?>
			<span class="ohmylms-player-marks">
				<span aria-hidden="true">[<?php echo esc_html( $marks ); ?>]</span>
				<span class="screen-reader-text">
					<?php
					/* translators: %s: Available question marks. */
					echo esc_html( sprintf( _n( '%s mark', '%s marks', $marks, 'ohmylms' ), $marks ) );
					?>
				</span>
			</span>
		<?php } ?>
	</div>
	<?php
	return;
}
?>
<div class="quiz-box-header">
	<span class="question-number"><?php echo esc_html( $ordinal . ' / ' . $question_count ); ?></span>
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
