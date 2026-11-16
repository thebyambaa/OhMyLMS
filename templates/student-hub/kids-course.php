<?php
/**
 * Kids learning map using the existing authorized course outline.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
$kids_units = $kids_outline['units'];
$kids_next  = null;
$kids_done  = 0;
$kids_total = 0;
// A course challenge outside the units is the final stop, never a second copy of a unit test.
$kids_ids = array();
foreach ( $kids_units as $kids_unit ) {
	foreach ( $kids_unit['items'] as $kids_item ) {
		$kids_ids[] = $kids_item['id'];
	}
}
if ( ! empty( $kids_outline['challenge'] ) && ! in_array( $kids_outline['challenge']['id'], $kids_ids, true ) ) {
	$kids_units[] = array(
		'title' => __( 'Final challenge', 'ohmylms' ),
		'items' => array( $kids_outline['challenge'] ),
	);
}
foreach ( $kids_units as $kids_unit ) {
	foreach ( $kids_unit['items'] as $kids_item ) {
		++$kids_total;
		$kids_done += $kids_item['complete'] ? 1 : 0;
		if ( ! $kids_next && $kids_item['available'] && ! $kids_item['complete'] ) {
			$kids_next = $kids_item;
		}
	}
}
?>
<main class="oml-kids-map">
	<header class="oml-kids-heading">
		<a href="<?php echo esc_url( $kids_back ); ?>">&larr; <?php esc_html_e( 'My courses', 'ohmylms' ); ?></a>
		<p class="oml-kids-eyebrow"><?php esc_html_e( 'My learning adventure', 'ohmylms' ); ?></p>
		<h1><?php echo esc_html( $kids_outline['title'] ); ?></h1>
		<p><?php esc_html_e( 'One little step at a time. You can replay any finished step.', 'ohmylms' ); ?></p>
		<progress value="<?php echo esc_attr( $kids_done ); ?>" max="<?php echo esc_attr( max( 1, $kids_total ) ); ?>" aria-label="<?php esc_attr_e( 'Finished steps', 'ohmylms' ); ?>"></progress>
		<p><?php /* translators: 1: finished steps, 2: total steps. */ echo esc_html( sprintf( __( '%1$d of %2$d steps finished', 'ohmylms' ), $kids_done, $kids_total ) ); ?></p>
		<?php if ( $kids_next ) : ?>
			<a class="oml-kids-continue" href="<?php echo esc_url( $kids_next['url'] ); ?>"><?php esc_html_e( 'Continue', 'ohmylms' ); ?> <span aria-hidden="true">&rarr;</span><small><?php echo esc_html( $kids_next['name'] ); ?></small></a>
		<?php elseif ( $kids_total && $kids_done === $kids_total ) : ?>
			<p class="oml-kids-celebrate"><?php esc_html_e( 'You finished every step! Pick a favorite to play again.', 'ohmylms' ); ?></p>
		<?php elseif ( ! $kids_total ) : ?>
			<p><?php esc_html_e( 'Your teacher is getting your adventure ready.', 'ohmylms' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Your next step is not open yet. Ask your teacher for help.', 'ohmylms' ); ?></p>
		<?php endif; ?>
	</header>
	<?php $kids_number = 0; ?>
	<?php foreach ( $kids_units as $kids_index => $kids_unit ) : ?>
		<section class="oml-kids-unit" id="hub-unit-<?php echo esc_attr( $kids_index + 1 ); ?>" aria-labelledby="kids-unit-<?php echo esc_attr( $kids_index ); ?>">
			<h2 id="kids-unit-<?php echo esc_attr( $kids_index ); ?>"><?php echo esc_html( $kids_unit['title'] ); ?></h2>
			<ol class="oml-kids-path">
				<?php foreach ( $kids_unit['items'] as $kids_item ) : ?>
					<?php
					++$kids_number;
					$kids_open    = ! empty( $kids_item['available'] );
					$kids_current = $kids_next && $kids_next['id'] === $kids_item['id'];
					$kids_status  = $kids_item['complete'] ? __( 'Finished — play again', 'ohmylms' ) : ( $kids_open ? __( 'Ready to start', 'ohmylms' ) : __( 'Locked — finish earlier steps', 'ohmylms' ) );
					?>
					<li class="oml-kids-step <?php echo $kids_item['complete'] ? 'is-complete' : ( $kids_open ? 'is-ready' : 'is-locked' ); ?>">
						<?php if ( $kids_open ) : ?>
							<a href="<?php echo esc_url( $kids_item['url'] ); ?>" <?php echo $kids_current ? 'aria-current="step"' : ''; ?>>
						<?php else : ?>
							<div aria-disabled="true">
						<?php endif; ?>
							<span class="oml-kids-node" aria-hidden="true"><?php echo $kids_item['complete'] ? '✓' : esc_html( $kids_number ); ?></span>
							<span class="oml-kids-step-title"><?php echo esc_html( $kids_item['name'] ); ?></span>
							<small><?php echo esc_html( $kids_status ); ?></small>
						<?php if ( $kids_open ) : ?>
							</a>
						<?php else : ?>
							</div>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>
</main>
