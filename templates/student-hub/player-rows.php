<?php
/**
 * Available activity links within one published lesson or ungrouped unit.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<ul class="oml-player-activities">
	<?php foreach ( $player_rows as $player_item ) : ?>
		<?php $player_is_current = 'practice' === $player_kind ? 'practice' === $player_item['kind'] && (int) ( $player_item['term_id'] ?? 0 ) === (int) $player_current : 'practice' !== $player_item['kind'] && (int) ( $player_item['content_id'] ?? 0 ) === (int) $player_current; ?>
		<li>
			<?php if ( ! empty( $player_item['available'] ) ) : ?>
				<a class="oml-player-activity" href="<?php echo esc_url( $player_item['url'] ); ?>" <?php echo $player_is_current ? 'aria-current="page"' : ''; ?>>
			<?php else : ?>
				<span class="oml-player-activity" aria-disabled="true">
			<?php endif; ?>
				<span class="oml-player-activity-kind" data-practice-skill="<?php echo esc_attr( 'practice' === $player_item['kind'] ? $player_item['term_id'] : 0 ); ?>" data-practice-course="<?php echo esc_attr( $player_course_id ); ?>"><?php echo esc_html( ! empty( $player_item['kind_label'] ) ? $player_item['kind_label'] : $player_kinds[ $player_item['kind'] ] ); ?> · <?php echo esc_html( 'practice' === $player_item['kind'] ? $player_item['label'] : ( $player_item['complete'] ? __( 'Completed', 'ohmylms' ) : __( 'Not completed', 'ohmylms' ) ) ); ?></span>
				<span><?php echo esc_html( $player_item['name'] ); ?></span>
			<?php echo ! empty( $player_item['available'] ) ? '</a>' : '</span>'; ?>
		</li>
	<?php endforeach; ?>
</ul>
