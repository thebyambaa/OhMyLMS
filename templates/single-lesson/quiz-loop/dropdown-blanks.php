<?php
/**
 * Dropdown in a sentence: each {marker} becomes a menu. Correct choices are never output.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/dropdown-blanks.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

use OhMyLMS\Assessment\Interactive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings       = Interactive::learner_settings( 'dropdown-blanks', $question );
$slots          = array();
$multiple_slots = array();
foreach ( (array) ( $settings['slots'] ?? array() ) as $slot ) {
	$slots[ (string) $slot['id'] ]          = (array) ( $slot['choices'] ?? array() );
	$multiple_slots[ (string) $slot['id'] ] = ! empty( $slot['multiple'] );
}
Interactive::enqueue();
?>
<div class="ohmylms-interactive ohmylms-dropdown-blanks" data-ohmylms-interactive="dropdown-blanks">
	<p class="ohmylms-dropdown-sentence">
		<?php
		Interactive::render_marked(
			$settings['text'] ?? '',
			static function ( $id ) use ( $slots, $multiple_slots, $attempt, $question ) {
				if ( ! isset( $slots[ $id ] ) ) {
					return; }
				$field_id = 'ohmylms-dd-' . (int) $attempt['id'] . '-' . (int) $question['id'] . '-' . $id;
				?>
				<select 
				<?php
				if ( $multiple_slots[ $id ] ) {
					echo 'multiple'; }
				?>
				id="<?php echo esc_attr( $field_id ); ?>" class="ohmylms-inline-select" data-answer-key="<?php echo esc_attr( $id ); ?>" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" name="<?php echo esc_attr( Interactive::field_name( $attempt, $question, $id ) . ( $multiple_slots[ $id ] ? '[]' : '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: blank number */ __( 'Choice %s', 'ohmylms' ), $id ) ); ?>">
					<?php if ( ! $multiple_slots[ $id ] ) { ?>
						<option value=""><?php esc_html_e( 'Choose…', 'ohmylms' ); ?></option>
					<?php } ?>
					<?php foreach ( $slots[ $id ] as $choice ) { ?>
						<option value="<?php echo esc_attr( $choice ); ?>"><?php echo esc_html( $choice ); ?></option>
					<?php } ?>
				</select>
				<?php
			}
		);
		?>
	</p>
</div>
