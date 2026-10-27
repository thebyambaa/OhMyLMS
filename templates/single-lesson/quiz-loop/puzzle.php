<?php
/**
 * Frozen puzzle configuration; crossword answers and search placements stay private.
 *
 * @package OhMyLMS\Templates
 */

use OhMyLMS\Assessment\Interactive;

defined( 'ABSPATH' ) || exit;
$puzzle_type = $question['settings']['type'];
$config      = Interactive::learner_settings( $puzzle_type, $question );
Interactive::enqueue();
ohmylms_enqueue_interactivity_module( 'ohmylms/questions' );
?>
<div class="ohmylms-interactive ohmylms-puzzle" data-question-id="<?php echo esc_attr( $question['id'] ); ?>" data-ohmylms-interactive="<?php echo esc_attr( $puzzle_type ); ?>" data-name-prefix="<?php echo esc_attr( Interactive::field_prefix( $attempt, $question ) ); ?>" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
	<?php
	// Native fields exist before the lazy renderer so server autosave can restore immediately.
	$puzzle_fields = 'crossword' === $puzzle_type ? $config['cells'] : array_map(
		static function ( $index ) {
			return 'p' . $index;
		},
		range( 0, count( $config['clues'] ) - 1 )
	);
	foreach ( $puzzle_fields as $puzzle_key ) :
		?>
		<input type="hidden" data-answer-key="<?php echo esc_attr( $puzzle_key ); ?>" name="<?php echo esc_attr( Interactive::field_name( $attempt, $question, $puzzle_key ) ); ?>" value="">
	<?php endforeach; ?>
	<noscript><p><?php esc_html_e( 'This puzzle needs JavaScript.', 'ohmylms' ); ?></p></noscript>
</div>
