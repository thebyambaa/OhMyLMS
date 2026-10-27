<?php
/**
 * Math expression answer: a text field with a row of symbol keys. Graded by equivalence,
 * so 2x+6 and 2(x+3) both match; the expected answer is never output.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/expression.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

use OhMyLMS\Assessment\Interactive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings = Interactive::learner_settings( 'expression', $question );
$forms    = array(
	'expanded'   => __( 'Write your answer in expanded form.', 'ohmylms' ),
	'factored'   => __( 'Write your answer in factored form.', 'ohmylms' ),
	'simplified' => __( 'Write your answer in simplest form.', 'ohmylms' ),
);
$form     = $settings['form'] ?? 'any';
$field_id = 'ohmylms-expr-' . (int) $attempt['id'] . '-' . (int) $question['id'];
Interactive::enqueue();
\OhMyLMS\Assessment\MathLive::enqueue();
?>
<div class="ohmylms-interactive ohmylms-expression" data-wp-on--input="ohmylms/quiz::actions.answerChanged" data-wp-on--change="ohmylms/quiz::actions.answerChanged" data-ohmylms-interactive="expression" data-math-format="latex">
	<label class="screen-reader-text" for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Your answer', 'ohmylms' ); ?></label>
	<input type="text" id="<?php echo esc_attr( $field_id ); ?>" class="ohmylms-text-input ohmylms-expression-input" autocomplete="off" autocapitalize="off" spellcheck="false" data-answer-key="answer" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" name="<?php echo esc_attr( Interactive::field_name( $attempt, $question ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. 2(x+3)', 'ohmylms' ); ?>">
	<?php if ( isset( $forms[ $form ] ) ) { ?>
		<p class="ohmylms-expression-form"><?php echo esc_html( $forms[ $form ] ); ?></p>
	<?php } ?>
</div>
