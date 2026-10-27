<?php
/**
 * The template for displaying True or False question
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/true-false.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="quiz-checkbox-radio-options type-radio true-false">
	<?php foreach ( $question['questions'] as $option ) { ?>
		<label class="single-option">
			<input type="radio" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]"  value="<?php echo esc_attr( $option['id'] ); ?>">
			<div class="option-box">
				<span class="checked-check">
					<svg width="8" height="8" fill="none" viewBox="0 0 8 8" xmlns="http://www.w3.org/2000/svg"><circle cx="4" cy="4" r="4" fill="#fff"/></svg>
				</span>
				<span class="option-title">
					<?php echo esc_html( $option['answer'] ); ?>
				</span>
			</div>
		</label>
	<?php } ?>
</div>
