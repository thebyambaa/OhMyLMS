<?php
/**
 * Exam dashboard panels: question palette (left) and timer with overview (right).
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

$sections = isset( $sections ) && is_array( $sections ) ? $sections : array();
$groups   = array();
foreach ( $sections as $number => $name ) {
	$groups[ $name ][] = (int) $number;
}
if ( ! $groups ) {
	$groups[''] = range( 1, max( 1, (int) $total_pages ) );
}
?>
<nav class="ohmylms-exam-palette" aria-label="<?php esc_attr_e( 'Question palette', 'ohmylms' ); ?>">
	<?php foreach ( $groups as $name => $numbers ) : ?>
		<div class="ohmylms-exam-palette-group">
			<?php if ( '' !== (string) $name ) : ?>
				<p class="ohmylms-exam-palette-title"><?php echo esc_html( $name ); ?></p>
			<?php endif; ?>
			<div class="ohmylms-exam-palette-grid">
				<?php foreach ( $numbers as $number ) : ?>
					<button type="button" class="ohmylms-exam-palette-item"
						data-wp-context='<?php echo esc_attr( wp_json_encode( array( 'questionNumber' => $number ) ) ); ?>'
						data-wp-on--click="actions.goTo"
						data-wp-class--is-current="state.isCurrentQuestion"
						data-wp-class--is-answered="state.isQuestionAnswered"
						data-wp-class--is-marked="state.isQuestionMarked"
						data-wp-class--is-visited="state.isQuestionVisited"
						data-wp-bind--aria-current="state.ariaCurrent"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %d: question number. */ __( 'Question %d', 'ohmylms' ), $number ) ); ?>"><?php echo (int) $number; ?></button>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
	<ul class="ohmylms-exam-legend">
		<li><span class="swatch is-answered"></span><?php esc_html_e( 'Answered', 'ohmylms' ); ?></li>
		<li><span class="swatch is-visited"></span><?php esc_html_e( 'Not answered', 'ohmylms' ); ?></li>
		<li><span class="swatch is-marked"></span><?php esc_html_e( 'Marked', 'ohmylms' ); ?></li>
		<li><span class="swatch"></span><?php esc_html_e( 'Not visited', 'ohmylms' ); ?></li>
	</ul>
</nav>

<aside class="ohmylms-exam-side" aria-label="<?php esc_attr_e( 'Exam status', 'ohmylms' ); ?>">
	<div class="ohmylms-exam-clock" role="timer" data-wp-bind--hidden="!context.timed" hidden>
		<span class="clock-unit"><strong data-wp-text="state.clockHours">00</strong><small><?php esc_html_e( 'Hrs', 'ohmylms' ); ?></small></span>
		<span class="clock-sep" aria-hidden="true">:</span>
		<span class="clock-unit"><strong data-wp-text="state.clockMinutes">00</strong><small><?php esc_html_e( 'Min', 'ohmylms' ); ?></small></span>
		<span class="clock-sep" aria-hidden="true">:</span>
		<span class="clock-unit"><strong data-wp-text="state.clockSeconds">00</strong><small><?php esc_html_e( 'Sec', 'ohmylms' ); ?></small></span>
	</div>
	<div class="ohmylms-exam-overview">
		<p class="ohmylms-exam-overview-title"><?php esc_html_e( 'Overview', 'ohmylms' ); ?></p>
		<dl>
			<dt><?php esc_html_e( 'Total Questions', 'ohmylms' ); ?></dt><dd><?php echo (int) $total_pages; ?></dd>
			<dt><?php esc_html_e( 'Visited', 'ohmylms' ); ?></dt><dd data-wp-text="state.visitedCount">1</dd>
			<dt><?php esc_html_e( 'Not Visited', 'ohmylms' ); ?></dt><dd data-wp-text="state.notVisitedCount"><?php echo (int) max( 0, $total_pages - 1 ); ?></dd>
			<dt><?php esc_html_e( 'Answered', 'ohmylms' ); ?></dt><dd data-wp-text="state.answeredCount">0</dd>
			<dt><?php esc_html_e( 'Not Answered', 'ohmylms' ); ?></dt><dd data-wp-text="state.notAnsweredCount">1</dd>
			<dt><?php esc_html_e( 'Marked to review', 'ohmylms' ); ?></dt><dd data-wp-text="state.markedCount">0</dd>
		</dl>
	</div>
</aside>
