<?php
/**
 * The template for displaying short text question
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/short-text.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$quiz             = ohmylms_get_quiz( get_the_ID() );
$settings         = $quiz->get_settings();
$short_text_limit = is_array( $settings ) && isset( $settings['short_text_limit'] ) ? $settings['short_text_limit'] : '';
$class            = '';

if ( ! empty( $short_text_limit ) ) {
	$class = 'ohmylms-has-character-limit';
}

// A question saved without answer rows (for example through the API) still needs an answer box.
$answer_rows = ! empty( $question['questions'] ) ? $question['questions'] : array( array( 'question_id' => $question['id'] ) );
?>

<?php foreach ( $answer_rows as $option ) { ?>
	<div class="answer-type-text">
		<input type="text" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>"class="ohmylms-text-input <?php echo esc_attr( $class ); ?>" data-limit="<?php echo esc_attr( $short_text_limit ); ?>" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]" placeholder="Type your answer here ... ">

		<?php if ( ! empty( $short_text_limit ) ) { ?>
			<span class="ohmylms-character-limit-hints">
				<span>0</span>/<?php echo esc_html( $short_text_limit ); ?>
			</span>
		<?php } ?>
	</div>
<?php } ?>
