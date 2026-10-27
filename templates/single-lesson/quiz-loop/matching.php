<?php
/**
 * The template for displaying matching question
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/matching.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Randomize the questions array.
$options = $question['questions'];
// Frozen (versioned) deliveries keep the order stored at attempt start.
$frozen = ! empty( $question['frozen'] );
if ( ! $frozen ) {
	shuffle( $options );
}

// 'wire' draws a line for each pair; 'grid' places answer tiles under their definitions.
$layout = 'grid' === ( $question['settings']['match_layout'] ?? '' ) ? 'grid' : 'wire';

ohmylms_enqueue_interactivity_module( 'ohmylms/questions' );
?>

<div class="quiz-matching-options is-<?php echo esc_attr( $layout ); ?>" data-wp-interactive="ohmylms/questions" data-question-id="<?php echo (int) $question['id']; ?>" <?php echo wp_interactivity_data_wp_context( array( 'selectedId' => '' ) ); ?> data-wp-on--dragover="actions.dragover" data-wp-on--drop="actions.drop" id="matching-<?php echo esc_attr( $question['id'] ); ?>">
	<div class="quiz-match-items">
		<?php foreach ( $options as $option ) { ?>
			<div class="matching-reorder-option matching-option" role="button" tabindex="0" aria-pressed="false" data-wp-on--click="actions.select" data-wp-on--dblclick="actions.unlink" data-wp-on--keydown="actions.key" data-wp-on--dragstart="actions.dragstart" data-wp-on--dragend="actions.dragend" draggable="true" data-option-id="<?php echo esc_attr( $option['id'] ); ?>" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>">

				<span class="option-drag-icon">
					<svg width="8" height="13" fill="none" viewBox="0 0 8 13" xmlns="http://www.w3.org/2000/svg"><path fill="#8F959E" stroke="#fff" stroke-width=".2" d="M1.5 9.6c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4zm4.5 0c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4zM1.5 5.1c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4C.73 7.9.1 7.27.1 6.5c0-.77.63-1.4 1.4-1.4zm4.5 0c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4zM1.5.6c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4C.73 3.4.1 2.77.1 2 .1 1.23.73.6 1.5.6zM6 .6c.77 0 1.4.63 1.4 1.4 0 .77-.63 1.4-1.4 1.4-.77 0-1.4-.63-1.4-1.4 0-.77.63-1.4 1.4-1.4z"/></svg>
				</span>

				<?php $item_image = ! empty( $option['image_url'] ) ? $option['image_url'] : ( $option['matching_data']['image_url'] ?? '' ); ?>
				<?php if ( ! empty( $item_image ) ) { ?>
					<figure class="option-image">
						<img src="<?php echo esc_url( $item_image ); ?>" alt="<?php echo esc_attr( $option['answer'] ); ?>">
					</figure>
				<?php } ?>

				<span class="option-label">
					<?php echo esc_html( $option['answer'] ); ?>
				</span>

				<!-- <input type="hidden" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][]" value="<?php echo esc_attr( $option['id'] ); ?>"> -->
			</div>
		<?php } ?>
	</div>

	<div class="quiz-matching-definition">
		<?php
		$definitions = $frozen && ! empty( $question['definitions'] ) ? $question['definitions'] : $options;
		if ( ! $frozen ) {
			shuffle( $definitions );
		}
		foreach ( $definitions as $option ) {
			?>
			<div class="matching-definition" data-definition-id="<?php echo esc_attr( $option['id'] ); ?>" data-question-id="<?php echo esc_attr( $option['question_id'] ); ?>">
				<span class="definition-label">
					<?php echo esc_html( isset( $option['matching_data']['label'] ) ? $option['matching_data']['label'] : '' ); ?>
				</span>

				<div class="option-drop-box" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Place selected answer here; Delete clears it', 'ohmylms' ); ?>" data-wp-on--click="actions.place" data-wp-on--dblclick="actions.unlink" data-wp-on--keydown="actions.key" data-definition-id="<?php echo esc_attr( $option['id'] ); ?>">
					<span class="placeholder-text">
						<?php echo esc_html__( 'Drag answer here', 'ohmylms' ); ?>
					</span>
				</div>

				<input type="hidden" class="matching-answer-input" name="attempt[<?php echo esc_attr( $attempt['id'] ); ?>][quiz_question][<?php echo esc_attr( $option['question_id'] ); ?>][<?php echo esc_attr( $option['id'] ); ?>]" value="">
			</div>
		<?php } ?>
	</div>
</div>

