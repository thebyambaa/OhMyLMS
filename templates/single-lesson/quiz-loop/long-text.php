<?php
/**
 * The template for displaying long text question
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/long-text.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$quiz            = ohmylms_get_quiz( get_the_ID() );
$settings        = $quiz->get_settings();
$long_text_limit = is_array( $settings ) && isset( $settings['long_text_limit'] ) ? $settings['long_text_limit'] : '';
$class           = '';

if ( ! empty( $long_text_limit ) ) {
	$class = 'ohmylms-has-character-limit';
}

// A question saved without answer rows (for example through the API) still needs an answer box.
$answer_rows = ! empty( $question['questions'] ) ? $question['questions'] : array( array( 'question_id' => $question['id'] ) );
?>

<?php foreach ( $answer_rows as $option ) { ?>
	<div class="answer-type-text">
		<textarea class="textarea-auto-resize <?php echo esc_attr( $class ); ?>" data-limit="<?php echo esc_attr( $long_text_limit ); ?>" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]" placeholder="<?php echo esc_attr( 'Type your answer here ...', 'ohmylms' ); ?>"></textarea>

		<?php if ( ! empty( $long_text_limit ) ) { ?>
			<span class="ohmylms-character-limit-hints">
				<span>0</span>/<?php echo esc_html( $long_text_limit ); ?>
			</span>
		<?php } ?>
	</div>
<?php } ?>
