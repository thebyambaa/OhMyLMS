<?php
/**
 * Template for displaying course content within loop.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/filters/search-sort.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

// Check if we're in a shortcode context with custom attributes.
if ( isset( $atts ) && is_array( $atts ) ) {
	// Use shortcode attributes.
	$is_filter_enabled = isset( $atts['show_filter'] ) ? $atts['show_filter'] : get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' );
	$is_search_enabled = isset( $atts['show_search'] ) ? $atts['show_search'] : get_option( 'ohmylms_archive_page_search_is_enabled', 'no' );
	$is_sort_enabled   = isset( $atts['show_sort'] ) ? $atts['show_sort'] : get_option( 'ohmylms_archive_page_sorting_is_enabled', 'no' );
} else {
	// Use global options for regular archive pages.
	$is_filter_enabled = get_option( 'ohmylms_archive_page_filter_is_enabled', 'no' );
	$is_search_enabled = get_option( 'ohmylms_archive_page_search_is_enabled', 'no' );
	$is_sort_enabled   = get_option( 'ohmylms_archive_page_sorting_is_enabled', 'no' );
}

global $courses_count;

?>
<?php if ( 'yes' === $is_search_enabled || 'yes' === $is_sort_enabled || 'yes' === $is_filter_enabled ) { ?>
	<div class="ohmylms-search-sort">
		<div class="search-sort-left">
			<?php if ( 'yes' === $is_filter_enabled ) { ?>
				<button type="button" class="filter-hamburger">
					<svg width="18" height="18" fill="none" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg"><path fill="#A1A1AA" fill-rule="evenodd" d="M13 3a.75.75 0 01.75.75v.75h3a.75.75 0 010 1.5h-3v.75a.75.75 0 01-1.5 0v-3A.75.75 0 0113 3zM2.5 5.25a.75.75 0 01.75-.75H10A.75.75 0 0110 6H3.25a.75.75 0 01-.75-.75zM7 7.5a.75.75 0 01.75.75v3a.75.75 0 01-1.5 0v-.75h-3a.75.75 0 010-1.5h3v-.75A.75.75 0 017 7.5zm2.25 2.25A.75.75 0 0110 9h6.75a.75.75 0 010 1.5H10a.75.75 0 01-.75-.75zM13 12a.75.75 0 01.75.75v.75h3a.75.75 0 010 1.5h-3v.75a.75.75 0 01-1.5 0v-3A.75.75 0 0113 12zM2.5 14.25a.75.75 0 01.75-.75H10a.75.75 0 010 1.5H3.25a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>
					<?php
						echo esc_html__( 'Filter', 'ohmylms' );
					?>
				</button>
			<?php } ?>

			<p class="course-showing">
				<?php
					$showing_course = $courses_count;
					// echo sprintf(__('Showing %d Courses', 'ohmylms'), $showing_course);.
				?>
			</p>
		</div>
		
		<div class="search-sort-right">
			<?php
			if ( 'yes' === $is_search_enabled ) {
				echo ohmylms_get_template( 'filters/course-search.php' );
			}

			if ( 'yes' === $is_sort_enabled ) {
				echo ohmylms_get_template( 'filters/course-sort.php' );
			}
			?>
		</div>
		
	</div>
<?php } ?>
