<?php
/**
 * The template for displaying statement question
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/statement.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<?php foreach ( $question['questions'] as $option ) { ?>
	<div class="answer-type-text statement">
		<input type="text" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>" class="ohmylms-text-input" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]" placeholder="Type your answer here ... ">
	</div>
<?php } ?>
