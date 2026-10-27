<?php
/**
 * Student dashboard XP card: total, today against the daily goal, this week and a goal picker.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

$xp_user = isset( $user_id ) ? (int) $user_id : get_current_user_id();
if ( ! \OhMyLMS\Engagement\Xp::enabled() ) {
	return;
}
$xp_data = \OhMyLMS\Engagement\Xp::summary( $xp_user, 7 );
$xp_max  = 1;
foreach ( $xp_data['days'] as $xp_day ) {
	$xp_max = max( $xp_max, (int) $xp_day['xp'] );
}
?>
<section class="oml-xp-card" aria-labelledby="oml-xp-heading" data-xp-endpoint="<?php echo esc_url( rest_url( 'ohmylms/v1/engagement/xp' ) ); ?>">
	<h3 id="oml-xp-heading"><?php esc_html_e( 'Experience points', 'ohmylms' ); ?></h3>
	<p class="oml-xp-total"><strong><?php echo esc_html( number_format_i18n( (int) $xp_data['total'] ) ); ?></strong> <?php esc_html_e( 'XP', 'ohmylms' ); ?></p>
	<p data-xp-today>
		<?php
		/* translators: 1: XP earned today, 2: the daily goal */
		echo esc_html( sprintf( __( '%1$d of %2$d XP today', 'ohmylms' ), (int) $xp_data['today'], (int) $xp_data['goal'] ) );
		?>
	</p>
	<progress data-earned="<?php echo esc_attr( (int) $xp_data['today'] ); ?>" max="<?php echo esc_attr( (int) $xp_data['goal'] ); ?>" value="<?php echo esc_attr( min( (int) $xp_data['today'], (int) $xp_data['goal'] ) ); ?>" aria-label="<?php esc_attr_e( 'Daily XP goal progress', 'ohmylms' ); ?>"></progress>
	<?php if ( $xp_data['goal_met'] ) : ?>
		<p class="oml-xp-met"><?php esc_html_e( 'Daily goal reached!', 'ohmylms' ); ?></p>
	<?php endif; ?>
	<ol class="oml-xp-week" aria-label="<?php esc_attr_e( 'XP this week', 'ohmylms' ); ?>">
		<?php foreach ( $xp_data['days'] as $xp_day ) : ?>
			<li>
				<span class="oml-xp-bar" style="height:<?php echo esc_attr( max( 4, (int) round( 100 * (int) $xp_day['xp'] / $xp_max ) ) ); ?>%" title="<?php echo esc_attr( (int) $xp_day['xp'] ); ?>"></span>
				<span class="screen-reader-text"><?php echo esc_html( sprintf( '%s: %d', $xp_day['date'], (int) $xp_day['xp'] ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
	<p class="oml-xp-week-total">
		<?php
		/* translators: %d: XP earned this week */
		echo esc_html( sprintf( __( '%d XP this week', 'ohmylms' ), (int) $xp_data['week'] ) );
		?>
	</p>
	<div class="oml-xp-goals" role="group" aria-label="<?php esc_attr_e( 'Daily goal', 'ohmylms' ); ?>">
		<?php foreach ( $xp_data['goals'] as $xp_goal ) : ?>
			<button type="button" data-xp-goal="<?php echo esc_attr( (int) $xp_goal ); ?>" aria-pressed="<?php echo (int) $xp_goal === (int) $xp_data['goal'] ? 'true' : 'false'; ?>"><?php echo esc_html( sprintf( '%d XP', (int) $xp_goal ) ); ?></button>
		<?php endforeach; ?>
	</div>
	<p role="status" data-xp-message></p>
</section>
