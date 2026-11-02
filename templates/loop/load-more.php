<?php
/**
 * Load more template
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/load-more.php
 *
 * The button submits the catalogue's GET form with `paged=N+1`; the server then lists pages
 * 1..N+1, so the cards already shown stay. The area always renders (hidden on the last page)
 * because it is a router region.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_query;

$catalog     = \OhMyLMS\Extensions\Catalog::current();
$total       = $total ?? ( $catalog ? $catalog['total'] : $wp_query->max_num_pages );
$pages_shown = max( 1, (int) ( $paged ?? ( $catalog ? $catalog['pages'] : get_query_var( 'paged' ) ) ) );
$region      = $catalog ? $catalog['more'] : 'ohmylms/catalog-more';
$has_more    = $pages_shown < (int) $total;

$loadmor_text = apply_filters( 'ohmylms_course_loadmore_text', 'See More Courses' );
?>

<div class="ohmylms-course-loadmore-area" data-wp-interactive="ohmylms/catalog" data-wp-router-region="<?php echo esc_attr( $region ); ?>" <?php echo $has_more ? '' : 'hidden'; ?>>
	<?php if ( $has_more ) : ?>
		<button
			class="ohmylms-course-loadmore-btn ohmylms-button"
			type="submit"
			name="paged"
			value="<?php echo absint( $pages_shown + 1 ); ?>"
			<?php echo $catalog ? 'form="' . esc_attr( $catalog['form'] ) . '"' : ''; ?>
			data-paged="<?php echo absint( $pages_shown ); ?>"
			data-posts-per-page="<?php echo absint( $posts_per_page ?? 0 ); ?>"
			data-total="<?php echo esc_attr( $total ); ?>"
			data-wp-on--click="actions.loadMore"
			data-wp-bind--disabled="state.loading"
			data-wp-bind--aria-busy="state.loading"
		>
			<?php echo esc_html( $loadmor_text ); ?>
			<span class="ohmylms-loader" hidden data-wp-bind--hidden="!state.loading"></span>
		</button>
	<?php endif; ?>
</div>
