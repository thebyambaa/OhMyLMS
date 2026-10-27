<?php
/**
 * Build an expression by tapping tiles from a bank into the answer row.
 * The accepted orders are never output; the answer is the ordered list of tile texts.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/build-expression.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

use OhMyLMS\Assessment\Interactive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings = Interactive::learner_settings( 'build-expression', $question );
Interactive::enqueue();
// Registers the required-answer check for tile answers.
ohmylms_enqueue_interactivity_module( 'ohmylms/questions' );
?>
<div class="ohmylms-interactive ohmylms-build-expression" data-wp-on--change="ohmylms/quiz::actions.answerChanged" data-ohmylms-interactive="build-expression" data-field-name="<?php echo esc_attr( Interactive::field_name( $attempt, $question ) ); ?>" data-question-id="<?php echo esc_attr( $question['id'] ); ?>">
	<p class="ohmylms-build-hint"><?php esc_html_e( 'Tap the tiles in the right order. Tap a placed tile to take it back.', 'ohmylms' ); ?></p>
	<div class="ohmylms-build-answer" role="group" aria-label="<?php esc_attr_e( 'Your answer', 'ohmylms' ); ?>" data-placeholder="<?php esc_attr_e( 'Your answer goes here', 'ohmylms' ); ?>"></div>
	<div class="ohmylms-build-bank" role="group" aria-label="<?php esc_attr_e( 'Available tiles', 'ohmylms' ); ?>">
		<?php foreach ( (array) ( $settings['tiles'] ?? array() ) as $tile ) { ?>
			<button type="button" class="ohmylms-tile" data-tile="<?php echo esc_attr( $tile ); ?>"><?php echo ' ' === $tile ? esc_html__( 'Space', 'ohmylms' ) : esc_html( $tile ); ?></button>
		<?php } ?>
	</div>
	<button type="button" class="ohmylms-build-clear" hidden><?php esc_html_e( 'Clear answer', 'ohmylms' ); ?></button>
	<noscript><p><?php esc_html_e( 'This question needs JavaScript to build the answer.', 'ohmylms' ); ?></p></noscript>
</div>
