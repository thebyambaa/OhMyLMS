<?php
/**
 * Structured (multi-part) question: the shared stem is the question text and image;
 * each part has its own answer field. Answer keys and marking notes are never output.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/structured.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$parts = isset( $question['settings']['parts'] ) && is_array( $question['settings']['parts'] ) ? $question['settings']['parts'] : array();
?>
<div class="ohmylms-structured-question">
	<?php if ( ! empty( $question['description'] ) ) { ?>
		<div class="ohmylms-structured-stem"><?php echo wp_kses_post( apply_filters( 'the_content', $question['description'] ) ); ?></div>
	<?php } ?>
	<?php
	foreach ( $parts as $part ) {
		$part_id  = preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $part['id'] ?? '' ) );
		$field_id = 'ohmylms-part-' . (int) $attempt['id'] . '-' . (int) $question['id'] . '-' . $part_id;
		/* translators: %s: question part label. */
		$answer_label = sprintf( __( 'Part %s answer', 'ohmylms' ), $part['label'] ?? $part_id );
		$name         = 'attempt[' . (int) $attempt['id'] . '][quiz_question][' . (int) $question['id'] . '][' . $part_id . ']';
		?>
		<fieldset class="ohmylms-structured-part" data-part-kind="<?php echo esc_attr( $part['kind'] ?? '' ); ?>">
			<legend>
				<strong><?php echo esc_html( $part['label'] ?? '' ); ?></strong>
				<?php echo wp_kses_post( $part['prompt'] ?? '' ); ?>
				<?php
				if ( ! empty( $part['marks'] ) ) {
					?>
					<span class="ohmylms-part-marks">[<?php echo esc_html( \OhMyLMS\Assessment\Scoring::display( $part['marks'] ) ); ?>]</span><?php } ?>
			</legend>
			<?php if ( 'question' === ( $part['kind'] ?? '' ) && isset( $part['question'] ) ) { ?>
				<div class="ohmylms-structured-child" data-part-type="<?php echo esc_attr( $part['question']['settings']['type'] ); ?>">
					<?php echo \OhMyLMS\Assessment\StructuredPart::render( $part['question'], $attempt, $name, $field_id, $question['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing learner-safe question templates escape their fields. ?>
				</div>
			<?php } elseif ( ( $part['kind'] ?? '' ) === 'written' ) { ?>
				<textarea id="<?php echo esc_attr( $field_id ); ?>" class="ohmylms-text-input" aria-label="<?php echo esc_attr( $answer_label ); ?>" rows="4" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>"></textarea>
			<?php } else { ?>
				<input type="text" id="<?php echo esc_attr( $field_id ); ?>" class="ohmylms-text-input" aria-label="<?php echo esc_attr( $answer_label ); ?>" autocomplete="off" <?php echo ( $part['kind'] ?? '' ) === 'numerical' ? 'inputmode="decimal"' : ''; ?> data-question-id="<?php echo esc_attr( $question['id'] ); ?>" name="<?php echo esc_attr( $name ); ?>">
				<?php
				if ( ! empty( $part['unit'] ) ) {
					?>
					<span class="ohmylms-numerical-unit"><?php echo esc_html( $part['unit'] ); ?></span><?php } ?>
			<?php } ?>
		</fieldset>
	<?php } ?>
</div>
