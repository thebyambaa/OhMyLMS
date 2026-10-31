<?php
/**
 * Template for the course sort select.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/filters/course-sort.php
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

defined( 'ABSPATH' ) || exit();

$catalog      = \OhMyLMS\Extensions\Catalog::current();
$catalog_form = $catalog ? $catalog['form'] : '';
$sort_by      = \OhMyLMS\Extensions\Catalog::current_filters()['sort'];
$sort_by      = '' !== $sort_by ? $sort_by : 'date';
$sort_options = array(
	'date'      => __( 'Date', 'ohmylms' ),
	'max_price' => __( 'Price High to Low', 'ohmylms' ),
	'min_price' => __( 'Price Low to High', 'ohmylms' ),
	'rating'    => __( 'Top Rated', 'ohmylms' ),
	'review'    => __( 'Top Reviewed', 'ohmylms' ),
);
?>
<div class="course-sortbox">
	<label for="course-sort" class="ohmylms-visually-hidden"><?php esc_html_e( 'Sort courses', 'ohmylms' ); ?></label>
	<select name="course_sort" class="course-sort" id="course-sort" <?php echo $catalog_form ? 'form="' . esc_attr( $catalog_form ) . '"' : ''; ?>>
		<?php foreach ( $sort_options as $sort_value => $sort_label ) : ?>
			<option value="<?php echo esc_attr( $sort_value ); ?>" <?php selected( $sort_by, $sort_value ); ?>>
				<?php echo esc_html( $sort_label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
