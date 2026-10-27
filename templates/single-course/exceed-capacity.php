<?php

/**
 * The template for displaying single course sidebar's pricebox
 *
 * This template can be overridden by copying it to yourtheme/single-course/continue-course.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<a href="<?php echo esc_url( '#' ); ?>" class="ohmylms-button ohmylms-button-disabled">
	<?php echo esc_html__( 'No Seat Available', 'ohmylms' ); ?>
</a>
