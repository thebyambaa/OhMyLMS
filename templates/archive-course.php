<?php
/**
 * Template for displaying all courses.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/archive-course.php.
 *
 * Search, filters, sort, curriculum buttons and "load more" are GET parameters (see
 * docs/LEARNER-FRONTEND.md); the `ohmylms/catalog` module swaps the two router regions.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

use OhMyLMS\Extensions\Catalog;

defined( 'ABSPATH' ) || exit();

// Handle both shortcode attributes and regular archive page.
if ( isset( $atts ) && is_array( $atts ) && ! empty( $atts ) ) {
	$layout            = $atts['layout'];
	$layout_style      = $atts['layout_style'];
	$is_filter_enabled = $atts['show_filter'];
	$posts_per_page    = $atts['posts_per_page'];
} else {
	$layout            = get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style      = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
	$is_filter_enabled = get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' );
	$posts_per_page    = get_option( 'ohmylms_archive_page_per_page', 10 );
}

// Get custom classes for shortcode styling.
$container_class = '';
$card_class      = '';

// Use shortcode attributes if available (when called from shortcode).
if ( isset( $atts ) && is_array( $atts ) ) {
	$container_class = ! empty( $atts['container_class'] ) ? $atts['container_class'] : '';
	$card_class      = ! empty( $atts['course_card_class'] ) ? $atts['course_card_class'] : '';
}

$course_outer_classes = '';
$has_drawer           = 'grid' === $layout && 'yes' === $is_filter_enabled;

if ( 'grid' === $layout ) {
	if ( 'yes' === $is_filter_enabled ) {
		$course_outer_classes .= ' ohmylms-filter-enabled';
	}

	$course_outer_classes .= ' ' . $layout_style . '';
}
if ( ! isset( $atts ) || ! is_array( $atts ) ) {
	ohmylms_get_header();
}

$catalog_atts = isset( $atts ) && is_array( $atts ) ? $atts : null;
$catalog      = Catalog::begin( $catalog_atts );
$drawer       = array(
	'drawerOpen'   => false,
	'drawerPanel'  => '.ohmylms-course-sidebar',
	'drawerInside' => '.ohmylms-course-sidebar,.filter-hamburger',
);

/**
 * Hook: ohmylms_before_main_content.
 */
do_action( 'ohmylms_before_main_content' );
?>

<div
	class="ohmylms-container ohmylms-catalog <?php echo esc_attr( $container_class ); ?>"
	data-wp-interactive="ohmylms/catalog"
	<?php echo wp_interactivity_data_wp_context( Catalog::context( $catalog ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress. ?>
	data-wp-on--change="actions.change"
	data-wp-on--input="actions.input"
	data-wp-on--submit="actions.submit"
	data-wp-on--keydown="actions.keydown"
	data-wp-on--mouseover="actions.cardOver"
	data-wp-on--mouseout="actions.cardOut"
	data-wp-on-window--popstate="actions.popstate"
	data-wp-on-async-window--resize="callbacks.reposition"
>
	<form id="<?php echo esc_attr( $catalog['form'] ); ?>" class="ohmylms-catalog-form" method="get" action="<?php echo esc_url( Catalog::url( array() ) ); ?>">
		<?php foreach ( Catalog::base_fields() as $field_name => $field_value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( $field_value ); ?>">
		<?php endforeach; ?>
		<noscript>
			<button type="submit" class="ohmylms-button ohmylms-catalog-apply"><?php esc_html_e( 'Apply filters', 'ohmylms' ); ?></button>
		</noscript>
	</form>
	<?php
	do_action( 'ohmylms_course_loop_header' );
	if ( have_posts() || $catalog['filtered'] ) {
		?>
		
		<div
			class="ohmylms-course-outer <?php echo esc_attr( $course_outer_classes ); ?>"
			<?php if ( $has_drawer ) : ?>
				data-wp-interactive="ohmylms/ui"
				<?php echo wp_interactivity_data_wp_context( $drawer ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress. ?>
				data-wp-class--open-sidebar="context.drawerOpen"
				data-wp-on-document--click="actions.drawerOutside"
				data-wp-on-document--keydown="actions.drawerEscape"
			<?php endif; ?>
		>
			<?php if ( $has_drawer ) { ?>
				<aside class="ohmylms-course-sidebar" id="<?php echo esc_attr( $catalog['panel'] ); ?>" aria-label="<?php esc_attr_e( 'Course filters', 'ohmylms' ); ?>" data-wp-interactive="ohmylms/catalog">
					<?php
					/**
					 * Hook: ohmylms_course_filter.
					 *
					 * @hooked: ohmylms_course_filter_header (5).
					 * @hooked: ohmylms_course_filters (10).
					 */
					do_action( 'ohmylms_course_filter', isset( $atts ) && is_array( $atts ) ? $atts : null );
					?>
				</aside>
			<?php } ?>

			<div class="ohmylms-course-main" data-wp-interactive="ohmylms/catalog">
				<?php
				/**
				 * Hook: ohmylms_before_course_loop.
				 *
				 * @hooked: ohmylms_course_loop_before_category_filter (5).
				 * @hooked: ohmylms_course_loop_before_filter (10).
				 */
				do_action( 'ohmylms_before_course_loop', isset( $atts ) && is_array( $atts ) ? $atts : null );
				?>
				<div
					class="ohmylms-catalog-results"
					data-wp-interactive="ohmylms/catalog"
					data-wp-router-region="<?php echo esc_attr( $catalog['results'] ); ?>"
					data-wp-bind--aria-busy="state.loading"
					data-wp-class--is-loading="state.loading"
				>
					<h2 id="<?php echo esc_attr( $catalog['heading'] ); ?>" class="ohmylms-catalog-heading ohmylms-visually-hidden" tabindex="-1"><?php esc_html_e( 'Course results', 'ohmylms' ); ?></h2>
					<p class="ohmylms-catalog-count ohmylms-visually-hidden" role="status" aria-live="polite" aria-atomic="true"><?php echo esc_html( Catalog::count_label( $catalog ) ); ?></p>
					<?php
					if ( ! have_posts() ) {
						?>
						<p class="no-courses-found ohmylms-catalog-empty"><?php esc_html_e( 'No courses match your search or filters.', 'ohmylms' ); ?></p>
						<?php
					} elseif (
						'grid' === $layout &&
						( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style )
					) {
						// Prepare template variables for carousel.
						$template_vars = isset( $atts ) && is_array( $atts ) ? $atts : array();
						if ( isset( $shortcode_atts ) && is_array( $shortcode_atts ) ) {
							$template_vars['card_class'] = $card_class;
						}
						ohmylms_get_template_part( 'content', 'course-carousel', $template_vars );

					} else {
						// Use the existing global $wp_query which is already set up properly
						// by the shortcode or archive page.
						ohmylms_course_loop_start( true, isset( $atts ) && is_array( $atts ) ? $atts : null );
						while ( have_posts() ) {
							the_post();
							// Prepare template variables for course content.
							$template_vars = isset( $atts ) && is_array( $atts ) ? $atts : array();
							if ( isset( $shortcode_atts ) && is_array( $shortcode_atts ) ) {
								$template_vars['card_class'] = $card_class;
							}
							ohmylms_get_template_part( 'content', 'course', $template_vars );
						}
						ohmylms_course_loop_end( true, isset( $atts ) && is_array( $atts ) ? $atts : null );
					}
					/**
					 * Hook: ohmylms_after_course_loop.
					 *
					 * @hooked: ohmylms_course_carousel_item_hover (5).
					 */
					do_action( 'ohmylms_after_course_loop', isset( $atts ) && is_array( $atts ) ? $atts : null );
					?>
				</div>
			</div>
		</div>

		<?php

		if ( 'list' === $layout || 'grid-style1' === $layout_style || 'grid-style2' === $layout_style ) {
			// pass variable to load-more template.
			ob_start();
			ohmylms_get_template(
				'loop/load-more.php',
				array(
					'posts_per_page' => $posts_per_page,
				)
			);
			$load_more = (string) ob_get_clean();
			if ( false === strpos( $load_more, 'ohmylms-course-loadmore-area' ) ) {
				// A load-more.php override renders nothing on the last page; the router region must exist.
				$load_more = '<div class="ohmylms-course-loadmore-area" hidden data-wp-interactive="ohmylms/catalog" data-wp-router-region="' . esc_attr( $catalog['more'] ) . '"></div>';
			}
			echo $load_more; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output.
		}
	} else {
		/**
		 * Hook: ohmylms_no_products_found.
		 *
		 * @hooked: ohmylms_no_products_found (5)
		 */
		do_action( 'ohmylms_no_products_found' );
	}
	?>
</div>

<?php
Catalog::end();

/**
 * Hook: ohmylms_after_main_content.
 */
do_action( 'ohmylms_after_main_content' );

if ( ! isset( $atts ) || ! is_array( $atts ) ) {
	ohmylms_get_footer();
}
