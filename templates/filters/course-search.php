<?php
/**
 * Template for the course search box.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/filters/course-search.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.1.0
 */

defined( 'ABSPATH' ) || exit();

$catalog      = \OhMyLMS\Extensions\Catalog::current();
$catalog_form = $catalog ? $catalog['form'] : '';
$search_id    = ( $catalog_form ? $catalog_form : 'ohmylms-catalog' ) . '-search';
$search_term  = \OhMyLMS\Extensions\Catalog::current_filters()['search'];
?>

<div class="course-searchbox">
	<svg class="search-icon" width="16" height="16" fill="none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill="#A1A1AA" d="M14.473 13.528l-1.887-1.88a6.347 6.347 0 10-.94.94l1.88 1.887a.665.665 0 00.947 0 .666.666 0 000-.947zM2.666 7.668a5 5 0 1110 0 5 5 0 01-10 0z"/></svg>

	<label for="<?php echo esc_attr( $search_id ); ?>" class="ohmylms-visually-hidden"><?php esc_html_e( 'Search courses', 'ohmylms' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $search_id ); ?>" name="course_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Search', 'ohmylms' ); ?>" autocomplete="off" enterkeyhint="search" maxlength="100" <?php echo $catalog_form ? 'form="' . esc_attr( $catalog_form ) . '"' : ''; ?>>
</div>
