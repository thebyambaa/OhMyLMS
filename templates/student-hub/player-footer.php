<?php
/**
 * Explicit navigation never marks an activity complete.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="oml-player-journey" aria-label="<?php esc_attr_e( 'Activity navigation', 'ohmylms' ); ?>">
	<?php if ( $player_navigation['lesson'] ) : ?>
		<a href="<?php echo esc_url( $player_navigation['lesson']['url'] ); ?>"><?php esc_html_e( 'Lesson overview', 'ohmylms' ); ?>: <?php echo esc_html( $player_navigation['lesson']['title'] ); ?></a>
	<?php endif; ?>
	<?php
	foreach ( array(
		'previous' => __( 'Previous activity', 'ohmylms' ),
		'next'     => __( 'Next activity', 'ohmylms' ),
	) as $player_direction => $player_label ) :
		?>
		<?php if ( $player_navigation[ $player_direction ] ) : ?>
			<a href="<?php echo esc_url( $player_navigation[ $player_direction ]['url'] ); ?>"><?php echo esc_html( $player_label ); ?>: <?php echo esc_html( $player_navigation[ $player_direction ]['name'] ); ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
