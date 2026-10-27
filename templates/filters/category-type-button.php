<?php
/**
 * Template for displaying the curriculum as course filter buttons.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
if ( ! isset( $is_enable_category ) || 'yes' !== $is_enable_category ) {
	return;
}

// Curriculum items that have published courses; selecting one includes everything below it.
$items = \OhMyLMS\Curriculum\Placement::public_items();
?>
<div class="ohmylms-category-type-button">
	<ul>
		<li class="active ohmylms-category-filter" data-slug="all">
			<a href="#" data-slug="all">
				<?php echo esc_html__( 'All', 'ohmylms' ); ?>
			</a>
		</li>
		<?php foreach ( $items as $item ) : ?>
			<li class="ohmylms-category-filter" data-slug="<?php echo esc_attr( $item['slug'] ); ?>">
				<a href="#" data-slug="<?php echo esc_attr( $item['slug'] ); ?>">
					<?php echo esc_html( $item['name'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
