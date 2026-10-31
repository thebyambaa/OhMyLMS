<?php
/**
 * Course loop start
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop-start.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$class_name = '';

if ( isset( $atts ) && is_array( $atts ) && ! empty( $atts ) ) {
	$layout       = isset( $atts['layout'] ) ? $atts['layout'] : 'grid';
	$layout_style = isset( $atts['layout_style'] ) ? $atts['layout_style'] : 'grid-style1';
	$per_row      = isset( $atts['columns'] ) ? (int) $atts['columns'] : 4;
} else {
	$layout       = get_option( 'ohmylms_archive_page_layout', 'grid' );
	$layout_style = get_option( 'ohmylms_archive_page_layout_style', 'grid-style1' );
	$per_row      = get_option( 'ohmylms_columns_per_row', 4 );
}

$per_row     = (int) $per_row;
$is_carousel = 'grid' === $layout && ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style );

if ( 'grid' === $layout ) {

	if ( 'grid-style1' === $layout_style || 'grid-style2' === $layout_style ) {
		if ( 3 === $per_row ) {
			$class_name .= ' ohmylms-col-3';

		} elseif ( 2 === $per_row ) {
			$class_name .= ' ohmylms-col-2';

		} elseif ( 1 === $per_row ) {
			$class_name .= ' ohmylms-col-1';
		}
	}

	if ( $is_carousel ) {
		$class_name .= ' ohmylms-course-cards-carousel ohmylms-carousel-track';
	}
} else {
	$class_name .= ' ohmylms-list-view';
}

if ( $is_carousel ) {
	// Opens .ohmylms-carousel-outer (region, arrows); loop-end.php closes it.
	echo \OhMyLMS\Design\Carousel::open( \OhMyLMS\Design\Carousel::course_settings( $per_row, isset( $atts ) && is_array( $atts ) ? $atts : array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper.
}
?>

<!-- use .ohmylms-col-1 class for 1 column, .ohmylms-col-2 class for 2 column, .ohmylms-col-3 class for 3 column by default its 4 column. If you use list view then use .ohmylms-list-view class -->
<div class="ohmylms-course-cards <?php echo esc_attr( $class_name ); ?>" data-col="<?php echo esc_attr( $per_row ); ?>" <?php echo $is_carousel ? \OhMyLMS\Design\Carousel::TRACK_ATTRIBUTES : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constant attribute string. ?>>
