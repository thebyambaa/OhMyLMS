<?php
/**
 * My Account page
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/profile/my-profile.php.
 *
 * @version 3.5.0
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

/**
 * My Account navigation.
 *
 * @since 2.6.0
 */
// do_action( 'ohmylms_account_navigation' );.
do_action( 'ohmylms_account_header' );

?>

<section class="ohmylms-dashboard">
	<div class="ohmylms-container">
		<?php
		/**
		 * My Account content.
		 *
		 * @since 2.6.0
		 */
		do_action( 'ohmylms_account_content' );
		?>
	</div>
</section>
