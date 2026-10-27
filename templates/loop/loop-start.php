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

$per_row = (int) $per_row;

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

	if ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) {
		$class_name .= ' ohmylms-course-cards-carousel';
	}
} else {
	$class_name .= ' ohmylms-list-view';
}
?>

<?php
// Check if we're in Gutenberg editor context (admin area or REST API).
$is_editor_context = is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_is_json_request();

// Check if we're in a block context by looking for block attributes.
$is_block_context = isset( $atts ) && is_array( $atts ) && (
	isset( $atts['layoutStyle'] ) ||
	isset( $atts['layout_style'] ) ||
	isset( $atts['postsPerPage'] ) ||
	isset( $atts['posts_per_page'] )
);

if ( 'grid' === $layout && ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) && ! $is_editor_context ) {
	?>
	<!-- carousel skeleton loader -->
	<div class="ohmylms-carousel-outer" style="--per-row:<?php echo esc_attr( $per_row ); ?>">
		<!-- .ohmylms-carousel-outer ends in the loop-end.php -->
		<div class="ohmylms-carousel-skeleton">
			<?php
			for ( $i = 1; $i <= $per_row; $i++ ) {
				ohmylms_course_skeleton();
			}
			?>
		</div>
<?php } elseif ( 'grid' === $layout && ( 'grid-style3' === $layout_style || 'grid-style4' === $layout_style ) && ( $is_editor_context || $is_block_context ) ) { ?>
	<!-- carousel container for editor/blocks - no skeleton -->
	<div class="ohmylms-carousel-outer" style="--per-row:<?php echo esc_attr( $per_row ); ?>">
		<!-- .ohmylms-carousel-outer ends in the loop-end.php -->
<?php } ?>

<!-- use .ohmylms-col-1 class for 1 column, .ohmylms-col-2 class for 2 column, .ohmylms-col-3 class for 3 column by default its 4 column. If you use list view then use .ohmylms-list-view class -->
<div class="ohmylms-course-cards <?php echo esc_attr( $class_name ); ?>" data-col="<?php echo esc_attr( $per_row ); ?>" >


