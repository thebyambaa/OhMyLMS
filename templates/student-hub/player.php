<?php
/**
 * Focused activity shell. The caller renders the existing activity inside main.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Student\PlayerContext;

defined( 'ABSPATH' ) || exit;

$player_kinds      = array(
	'lesson'     => __( 'Lesson', 'ohmylms' ),
	'practice'   => __( 'Practice', 'ohmylms' ),
	'quiz'       => __( 'Quiz', 'ohmylms' ),
	'assignment' => __( 'Assignment', 'ohmylms' ),
);
$player_kind       = $player_kind ?? 'content';
$player_unit_index = null;
foreach ( $player_outline['units'] as $player_index => $player_candidate ) {
	foreach ( $player_candidate['items'] as $player_candidate_item ) {
		if ( ( 'practice' === $player_kind ? 'practice' === $player_candidate_item['kind'] && (int) ( $player_candidate_item['term_id'] ?? 0 ) === (int) $player_current : 'practice' !== $player_candidate_item['kind'] && (int) ( $player_candidate_item['content_id'] ?? 0 ) === (int) $player_current ) ) {
			$player_unit_index = $player_index;
			break 2;
		}
	}
}
?>
<div class="oml-player<?php echo $player_quiz ? ' oml-player-taking-quiz' : ''; ?>" data-theme="light">
	<header class="oml-player-context">
		<?php if ( ! $player_quiz ) : ?>
			<a class="oml-player-exit" href="<?php echo esc_url( PlayerContext::overview_url( $player_outline['id'] ) ); ?>">&larr; <?php esc_html_e( 'Course overview', 'ohmylms' ); ?></a>
		<?php endif; ?>
		<span class="oml-player-course"><?php echo esc_html( $player_outline['title'] ); ?></span>
		<?php if ( ! $player_quiz && null !== $player_unit_index ) : ?>
			<a class="oml-player-unit-return" href="<?php echo esc_url( PlayerContext::overview_url( $player_outline['id'] ) . '#hub-unit-' . ( $player_unit_index + 1 ) ); ?>"><?php echo esc_html( $player_outline['units'][ $player_unit_index ]['title'] ); ?></a>
		<?php endif; ?>
		<?php if ( ! $player_quiz ) : ?>
			<button type="button" class="oml-player-outline-toggle" aria-expanded="true" aria-controls="oml-player-outline" data-player-outline-toggle hidden><?php esc_html_e( 'Course content', 'ohmylms' ); ?></button>
		<?php endif; ?>
		<a class="oml-player-skip" href="#oml-player-main"><?php esc_html_e( 'Skip to activity', 'ohmylms' ); ?></a>
	</header>
	<div class="oml-player-layout">
		<?php if ( ! $player_quiz ) : ?>
			<nav class="oml-player-outline" id="oml-player-outline" aria-label="<?php esc_attr_e( 'Course units', 'ohmylms' ); ?>">
				<h2 class="oml-player-outline-title"><?php esc_html_e( 'Course content', 'ohmylms' ); ?></h2>
				<?php foreach ( $player_outline['units'] as $player_index => $player_unit ) : ?>
					<?php $player_selected = $player_index === $player_unit_index; ?>
					<details class="oml-player-unit" <?php echo $player_selected ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $player_unit['title'] ); ?></summary>
						<?php foreach ( $player_unit['groups'] ?? array() as $player_lesson ) : ?>
							<section class="oml-player-lesson">
								<h3><a href="<?php echo esc_url( \OhMyLMS\Student\LessonJourney::url( $player_outline['id'], $player_lesson['id'] ) ); ?>"><?php echo esc_html( $player_lesson['title'] ); ?></a></h3>
								<p><?php /* translators: 1: complete, 2: total activities. */ echo esc_html( sprintf( __( '%1$d of %2$d activities complete', 'ohmylms' ), $player_lesson['activity_done'], $player_lesson['activity_total'] ) ); ?></p>
								<?php
								ohmylms_get_template(
									'student-hub/player-rows.php',
									array(
										'player_course_id' => $player_outline['id'],
										'player_rows'      => $player_lesson['items'],
										'player_kind'      => $player_kind,
										'player_current'   => $player_current,
										'player_kinds'     => $player_kinds,
									)
								);
								?>
							</section>
						<?php endforeach; ?>
						<?php
						ohmylms_get_template(
							'student-hub/player-rows.php',
							array(
								'player_course_id' => $player_outline['id'],
								'player_rows'      => $player_unit['ungrouped'] ?? $player_unit['items'],
								'player_kind'      => $player_kind,
								'player_current'   => $player_current,
								'player_kinds'     => $player_kinds,
							)
						);
						?>
					</details>
				<?php endforeach; ?>
				<?php
				if ( ! empty( $player_outline['challenge'] ) ) {
					ohmylms_get_template(
						'student-hub/player-rows.php',
						array(
							'player_course_id' => $player_outline['id'],
							'player_rows'      => array( $player_outline['challenge'] ),
							'player_kind'      => $player_kind,
							'player_current'   => $player_current,
							'player_kinds'     => $player_kinds,
						)
					); }
				?>
			</nav>
		<?php endif; ?>
		<main class="oml-player-main" id="oml-player-main" tabindex="-1">
			<?php if ( ! $player_quiz && 'practice' !== $player_kind ) {
				\OhMyLMS\Family\Provenance::render( $player_outline['id'], $player_current ); } ?>
