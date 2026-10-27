<?php
/**
 * The template for displaying fill in the blank question
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/fill-in-the-blank.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$question = \OhMyLMS\Assessment\InlineBlanks::public_view( $question );
if ( ! empty( $question['inline_blanks'] ) ) {
	return; }
?>

<?php foreach ( $question['questions'] as $option ) { ?>
	<div class="answer-type-text fillin-blanks">
		<input type="text" class="ohmylms-text-input" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]" placeholder="<?php echo esc_attr( 'Type your answer here ...', 'ohmylms' ); ?>">
	</div>
<?php } ?>
