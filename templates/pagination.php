<?php
/**
 * OhMyLMS Pagination
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/duration.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Display pagination.
global $wp_query;

$big              = 999999999; // An unlikely number.
$pagination_links = paginate_links(
	array(
		'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
		'format'    => '?paged=%#%',
		'current'   => max( 1, get_query_var( 'paged' ) ),
		'total'     => $wp_query->max_num_pages,
		'prev_text' => '&laquo;',
		'next_text' => '&raquo;',
	)
);

if ( $pagination_links ) {
	echo '<div class="pagination">' . $pagination_links . '</div>';
}
