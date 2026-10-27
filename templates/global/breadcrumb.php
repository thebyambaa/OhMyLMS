<?php
/**
 * The template for displaying single course breadcrumb
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/global/breadcrumb.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! empty( $breadcrumb ) ) {

	echo '<nav class="ohmylms-breadcrumb"><div class="ohmylms-container"><ul>';

	foreach ( $breadcrumb as $key => $crumb ) {

		echo $before;

		if ( ! empty( $crumb[1] ) && sizeof( $breadcrumb ) !== $key + 1 ) {
			echo '<a href="' . esc_url( $crumb[1] ) . '">' . esc_html( $crumb[0] ) . '</a>';
		} else {
			echo esc_html( $crumb[0] );
		}

		echo $after;
	}

	echo $wrap_after;

}
