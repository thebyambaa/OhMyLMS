<?php
/**
 * Membership page section header
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/membership-loop/header.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="membership-section-title">
	<?php

	if ( apply_filters( 'ohmylms_membership_show_page_title', true ) ) :
		?>
		<h2>
			<?php echo wp_kses_post( apply_filters( 'ohmylms_membership_archive_title', __( 'Select plan that works best for you.', 'ohmylms' ) ) ); ?>
		</h2>
	<?php endif; ?>

	<?php
	do_action( 'ohmylms_membership_archive_description' );
	?>
</div>
