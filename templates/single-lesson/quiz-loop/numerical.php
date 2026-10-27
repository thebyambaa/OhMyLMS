<?php
/**
 * Numerical answer input.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/numerical.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$unit     = isset( $question['settings']['unit'] ) ? (string) $question['settings']['unit'] : '';
$field_id = 'ohmylms-numerical-' . (int) $attempt['id'] . '-' . (int) $question['id'];
?>
<div class="answer-type-text ohmylms-numerical-answer">
	<label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Your answer', 'ohmylms' ); ?></label>
	<input type="text" inputmode="decimal" autocomplete="off" id="<?php echo esc_attr( $field_id ); ?>" class="ohmylms-text-input" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $question['id'] ); ?>][]" placeholder="<?php esc_attr_e( 'Enter a number', 'ohmylms' ); ?>">
	<?php if ( '' !== $unit ) { ?>
		<span class="ohmylms-numerical-unit"><?php echo esc_html( $unit ); ?></span>
	<?php } ?>
</div>
