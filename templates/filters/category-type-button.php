<?php
/**
 * Template for displaying the curriculum as course filter buttons.
 *
 * The buttons are links to the filtered list (`?course_curriculum[]=c<id>`), followed by the
 * `ohmylms/catalog` router without a page load.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

defined( 'ABSPATH' ) || exit();
if ( ! isset( $is_enable_category ) || 'yes' !== $is_enable_category ) {
	return;
}

// Curriculum items that have published courses; selecting one includes everything below it.
$items      = \OhMyLMS\Curriculum\Placement::public_items();
$filters    = \OhMyLMS\Extensions\Catalog::current_filters();
$selected   = $filters['curriculum'] ? $filters['curriculum'][0] : 'all';
$categories = array_merge(
	array(
		array(
			'slug' => 'all',
			'name' => __( 'All', 'ohmylms' ),
		),
	),
	$items
);
?>
<div class="ohmylms-category-type-button">
	<ul>
		<?php foreach ( $categories as $item ) : ?>
			<?php $is_active = $selected === $item['slug']; ?>
			<li
				class="ohmylms-category-filter<?php echo $is_active ? ' active' : ''; ?>"
				data-slug="<?php echo esc_attr( $item['slug'] ); ?>"
				<?php echo wp_interactivity_data_wp_context( array( 'slug' => $item['slug'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress. ?>
				data-wp-class--active="state.categoryActive"
			>
				<a
					href="<?php echo esc_url( \OhMyLMS\Extensions\Catalog::category_url( $item['slug'], $filters ) ); ?>"
					data-slug="<?php echo esc_attr( $item['slug'] ); ?>"
					<?php echo $is_active ? 'aria-current="true"' : ''; ?>
					data-wp-bind--aria-current="state.categoryCurrent"
					data-wp-on--click="actions.category"
				>
					<?php echo esc_html( $item['name'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
