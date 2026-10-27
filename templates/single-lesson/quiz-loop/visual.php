<?php
/**
 * Visual question types (number line, shade a model, blocks, clock, money, jug, chart, grid).
 *
 * The widget is built by interactive-visual.js from the learner-safe configuration below;
 * the answer key, tolerances and expected values are never output.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/visual.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

use OhMyLMS\Assessment\Interactive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$type   = (string) ( $question['settings']['type'] ?? '' );
$config = Interactive::learner_settings( $type, $question );
// Only the type's public keys go to the browser.
$config = array_diff_key( $config, array_flip( array( 'type', 'required', 'score', 'randomize', 'hint', 'explanation', 'question_code' ) ) );
Interactive::enqueue();
// Registers the required-answer check for touched widgets.
ohmylms_enqueue_interactivity_module( 'ohmylms/questions' );
?>
<div class="ohmylms-interactive ohmylms-visual ohmylms-<?php echo esc_attr( $type ); ?>" data-ohmylms-interactive="<?php echo esc_attr( $type ); ?>" data-name-prefix="<?php echo esc_attr( Interactive::field_prefix( $attempt, $question ) ); ?>" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
	<noscript><p><?php esc_html_e( 'This question needs JavaScript.', 'ohmylms' ); ?></p></noscript>
</div>
