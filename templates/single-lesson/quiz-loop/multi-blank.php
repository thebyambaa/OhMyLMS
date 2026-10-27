<?php
/**
 * Multi-blank fill-in or table completion. Each {marker} becomes an input; expected values are never output.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/single-lesson/quiz-loop/multi-blank.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

use OhMyLMS\Assessment\Interactive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings = Interactive::learner_settings( 'multi-blank', $question );
$fields   = (array) ( $settings['fields'] ?? array() );
$input    = static function ( $id ) use ( $fields, $attempt, $question ) {
	if ( ! isset( $fields[ $id ] ) ) {
		return; }
	$spec     = (array) $fields[ $id ];
	$numeric  = ( $spec['kind'] ?? 'text' ) === 'numerical';
	$field_id = 'ohmylms-mb-' . (int) $attempt['id'] . '-' . (int) $question['id'] . '-' . $id;
	?>
	<input type="text" id="<?php echo esc_attr( $field_id ); ?>" class="ohmylms-text-input ohmylms-blank-input<?php echo ( $spec['kind'] ?? '' ) === 'expression' ? ' ohmylms-expression-input' : ''; ?>" autocomplete="off" <?php echo $numeric ? 'inputmode="decimal"' : ''; ?> size="6" data-answer-key="<?php echo esc_attr( $id ); ?>" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" name="<?php echo esc_attr( Interactive::field_name( $attempt, $question, $id ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: blank number */ __( 'Blank %s', 'ohmylms' ), $id ) ); ?>">
	<?php if ( ! empty( $spec['unit'] ) ) { ?>
		<span class="ohmylms-numerical-unit"><?php echo esc_html( $spec['unit'] ); ?></span>
	<?php } ?>
	<?php
};
Interactive::enqueue();
?>
<div class="ohmylms-interactive ohmylms-multi-blank" data-ohmylms-interactive="multi-blank">
	<?php if ( ( $settings['layout'] ?? 'inline' ) === 'table' ) { ?>
		<table class="ohmylms-blank-table">
			<?php if ( ! empty( $settings['columns'] ) ) { ?>
				<thead>
					<tr>
						<?php foreach ( (array) $settings['columns'] as $column ) { ?>
							<th scope="col"><?php echo esc_html( $column ); ?></th>
						<?php } ?>
					</tr>
				</thead>
			<?php } ?>
			<tbody>
				<?php foreach ( (array) ( $settings['rows'] ?? array() ) as $row ) { ?>
					<tr>
						<?php foreach ( (array) $row as $cell ) { ?>
							<td><?php Interactive::render_marked( $cell, $input ); ?></td>
						<?php } ?>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } else { ?>
		<p class="ohmylms-blank-sentence"><?php Interactive::render_marked( $settings['text'] ?? '', $input ); ?></p>
	<?php } ?>
</div>
