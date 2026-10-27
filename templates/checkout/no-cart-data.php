<?php
/**
 * Template for displaying no cart template.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/no-cart-data.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
?>

<div class="ohmylms-no-cart-data">
	<div class="ohmylms-container">
		<div class="ohmylms-no-cart-data-wrapper">
			<h4 class="no-cart-title"><?php echo esc_html__( 'Your cart is empty', 'ohmylms' ); ?></h4>
			<a href="<?php echo esc_url( $archive_page_url ); ?>" class="ohmylms-button">
				<?php echo esc_html__( 'Continue Learning', 'ohmylms' ); ?>
			</a>
		</div>
	</div>
</div>
