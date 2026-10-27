<?php
/**
 * Course Loop End
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/loop-end.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $atts ) && is_array( $atts ) && ! empty( $atts ) ) {
	$layout       = isset( $atts['layout'] ) ? $atts['layout'] : 'grid';
	$layout_style = isset( $atts['layout_style'] ) ? $atts['layout_style'] : 'grid-style1';
} else {
	$layout       = get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
}

// Check if we're in a block context by looking for block attributes.
$is_block_context = isset( $atts ) && is_array( $atts ) && (
	isset( $atts['layoutStyle'] ) ||
	isset( $atts['layout_style'] ) ||
	isset( $atts['postsPerPage'] ) ||
	isset( $atts['posts_per_page'] )
);

?>
</div>
<!-- .ohmylms-course-cards end. it start in loop-start.php -->

<?php if ( 'grid' === $layout && ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) ) { ?>
	</div>
<!-- .ohmylms-carousel-outer end. it start in loop-start.php -->
<?php } ?>
