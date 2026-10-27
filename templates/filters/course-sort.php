<?php
/**
 * Template for displaying course content within loop.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/filters/course-sort.php
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
?>
<div class="course-sortbox">
	<select name="course-sort" class="course-sort" id="course-sort">
		<option value="date">
			<?php esc_html_e( 'Date', 'ohmylms' ); ?>
		</option>
		<option value="max_price">
			<?php esc_html_e( 'Price High to Low', 'ohmylms' ); ?>
		</option>
		<option value="min_price">
			<?php esc_html_e( 'Price Low to High', 'ohmylms' ); ?>
		</option>
		<option value="rating">
			<?php esc_html_e( 'Top Rated', 'ohmylms' ); ?>
		</option>
		<option value="review">
			<?php esc_html_e( 'Top Reviewed', 'ohmylms' ); ?>
		</option>       
	</select>
</div>
