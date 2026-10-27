<?php
/**
 * The template for displaying lesson's Audio, Video, Text content
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/multiple-question.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="quiz-checkbox-radio-options">
	<?php foreach ( $question['questions'] as $option ) { ?>
		<label class="single-option">
			<input type="checkbox" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]" value="<?php echo esc_attr( $option['id'] ); ?>">
			<div class="option-box">
				<span class="checked-check">
					<svg width="10" height="8" fill="none" viewBox="0 0 10 8" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M8.375.818L3.747 5.447 1.625 3.325A.818.818 0 00.468 4.482l2.7 2.7a.818.818 0 001.157 0l5.207-5.207A.818.818 0 108.375.818z"/></svg>
				</span>
				<span class="option-title">
					<?php echo esc_html( $option['answer'] ); ?>
				</span>
			</div>
		</label>
	<?php } ?>
</div>
