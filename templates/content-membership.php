<?php
/**
 * Template for displaying course content within loop.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/content-membership.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();
?>
<div class="ohmylms-single-membership" id="ohmylms-membership-<?php the_ID(); ?>">
	<?php
	/**
	 * Hook: ohmylms_before_membership_loop_item.
	 */
	do_action( 'ohmylms_before_membership_loop_item' );
	?>

	<div class="membership-header">
		<?php
			/**
			 * Hook: ohmylms_membership_table_header.
			 *
			 * Hooked: ohmylms_membership_title (5).
			 * Hooked: ohmylms_membership_price (10).
			 * Hooked: ohmylms_membership_description (15).
			 */
			do_action( 'ohmylms_membership_table_header' );
		?>
	</div>

	<div class="membership-body">
		<?php
			/**
			 * Hook: ohmylms_membership_table_body.
			 *
			 * Hooked: ohmylms_membership_product_list (5).
			 */
			do_action( 'ohmylms_membership_table_body' );
		?>
	</div>

	<div class="membership-footer">
		<?php
			/**
			 * Hook: ohmylms_membership_table_footer.
			 *
			 * Hooked: ohmylms_membership_add_to_cart (5).
			 */
			do_action( 'ohmylms_membership_table_footer' );
		?>
	</div>

	<?php
	/**
	 * Hook: ohmylms_after_membership_loop_item.
	 */
	do_action( 'ohmylms_after_membership_loop_item' );
	?>
</div>
