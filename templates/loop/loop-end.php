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

?>
</div>
<!-- .ohmylms-course-cards end. it start in loop-start.php -->

<?php
if ( \OhMyLMS\Design\Carousel::is_open() ) {
	// Dots and the end of .ohmylms-carousel-outer, opened in loop-start.php.
	echo \OhMyLMS\Design\Carousel::close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper.
} elseif ( 'grid' === $layout && ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) ) {
	// A theme's loop-start.php override opened .ohmylms-carousel-outer itself.
	echo '</div>';
}
