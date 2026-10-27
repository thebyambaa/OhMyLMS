<?php
/**
 * The template for displaying reorder question
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/reorder.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Randomize the questions array.
$options = $question['questions'];
if ( empty( $question['frozen'] ) ) {
	shuffle( $options );
}

ohmylms_enqueue_interactivity_module( 'ohmylms/questions' );
?>

<div class="quiz-reorder-options" data-wp-interactive="ohmylms/questions" data-question-id="<?php echo (int) $question['id']; ?>" <?php echo wp_interactivity_data_wp_context( array( 'selectedId' => '' ) ); ?> data-wp-on--dragover="actions.dragover" data-wp-on--drop="actions.drop" id="reorder-<?php echo esc_attr( $question['id'] ); ?>">
	<?php foreach ( $options as $position => $option ) { ?>
		<div class="matching-reorder-option reorder-option" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Use Up and Down arrows to reorder', 'ohmylms' ); ?>" data-wp-on--keydown="actions.key" data-wp-on--dragstart="actions.dragstart" data-wp-on--dragend="actions.dragend" draggable="true" data-option-id="<?php echo esc_attr( $option['id'] ); ?>" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>">

			<span class="option-drag-icon">
				<svg width="8" height="13" fill="none" viewBox="0 0 8 13" xmlns="http://www.w3.org/2000/svg"><path fill="#8F959E" stroke="#fff" stroke-width=".2" d="M1.5 9.6c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4zm4.5 0c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4zM1.5 5.1c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4C.73 7.9.1 7.27.1 6.5c0-.77.63-1.4 1.4-1.4zm4.5 0c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4zM1.5.6c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4C.73 3.4.1 2.77.1 2 .1 1.23.73.6 1.5.6zM6 .6c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4z"/></svg>
			</span>

			<?php if ( ! empty( $option['image_url'] ) ) { ?>
				<figure class="option-image">
					<img src="<?php echo esc_url( $option['image_url'] ); ?>" alt="<?php echo esc_attr( $option['answer'] ); ?>">
				</figure>
			<?php } ?>

			<span class="option-label">
				<?php echo esc_html( $option['answer'] ); ?>
			</span>

			<span class="option-move">
				<button type="button" class="reorder-move" data-direction="-1" data-wp-on--click="actions.move" aria-label="<?php esc_attr_e( 'Move up', 'ohmylms' ); ?>" <?php disabled( 0, $position ); ?>>
					<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M6 15l6-6 6 6"/></svg>
				</button>
				<button type="button" class="reorder-move" data-direction="1" data-wp-on--click="actions.move" aria-label="<?php esc_attr_e( 'Move down', 'ohmylms' ); ?>" <?php disabled( count( $options ) - 1, $position ); ?>>
					<svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
				</button>
			</span>

			<input type="hidden" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]" value="<?php echo esc_attr( $option['id'] ); ?>">
		</div>
	<?php } ?>
</div>

