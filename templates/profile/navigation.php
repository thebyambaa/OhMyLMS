<?php
/**
 * My Account navigation
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/myaccount/navigation.php.
 *
 * @version 1.0.0
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'ohmylms_before_account_navigation' );
?>


<aside class="ohmylms-student-profile-sidebar" id="ohmylms-profile-sidebar">
	<ul class="ohmylms-student-profile-tab">
		<li class="item-profile <?php echo esc_attr( ohmylms_get_account_menu_item_classes( 'profile' ) . ' ' . ohmylms_get_account_menu_item_classes( 'profile-edit' ) ); ?> ">
			<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'profile' ) ); ?>">
				<span class="icon icon-regular">
					<?php require OHMYLMS_DIR . '/assets/images/icon/profile-icon.php'; ?>
				</span>

				<span class="icon icon-active">
					<?php require OHMYLMS_DIR . '/assets/images/icon/profile-active-icon.php'; ?>
				</span>

				<?php echo esc_html__( 'Profile', 'ohmylms' ); ?>
			</a>
		</li>

		<!-- <li class="item-notifications <?php echo esc_attr( ohmylms_get_account_menu_item_classes( 'notification' ) ); ?>">
			<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'notification' ) ); ?>">
				<span class="icon icon-regular">
					<?php require OHMYLMS_DIR . '/assets/images/icon/notification-o-icon.php'; ?>
				</span>

				<span class="icon icon-active">
					<?php require OHMYLMS_DIR . '/assets/images/icon/notification-o-active-icon.php'; ?>
				</span>

				<?php echo esc_html__( 'Notifications', 'ohmylms' ); ?>
			</a>
		</li> -->

		<li class="item-transaction-history <?php echo esc_attr( ohmylms_get_account_menu_item_classes( 'transactions-history' ) ); ?>">
			<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'transactions-history' ) ); ?>">
				<span class="icon icon-regular">
					<?php require OHMYLMS_DIR . '/assets/images/icon/cart-icon.php'; ?>
				</span>

				<span class="icon icon-active">
					<?php require OHMYLMS_DIR . '/assets/images/icon/cart-active-icon.php'; ?>
				</span>

				<?php echo esc_html__( 'Transaction History', 'ohmylms' ); ?>
			</a>
		</li>

		<?php if ( ohmylms_is_pro() ) : ?>
			<li class="item-transaction-history <?php echo esc_attr( ohmylms_get_account_menu_item_classes( 'membership' ) ); ?>">
				<a href="<?php echo esc_url( ohmylms_get_account_endpoint_url( 'membership' ) ); ?>">
					<span class="icon icon-regular">
						<?php include OHMYLMS_DIR . '/assets/images/icon/membership-icon.php'; ?>
					</span>

					<span class="icon icon-active">
						<?php include OHMYLMS_DIR . '/assets/images/icon/membership-active-icon.php'; ?>
					</span>

					<?php echo esc_html__( 'Membership', 'ohmylms' ); ?>
				</a>
			</li>
		<?php endif; ?>

		<!-- <li class="item-billing <?php // echo ohmylms_get_account_menu_item_classes('billing-information'). ?>">
			<a href="<?php // echo esc_url( ohmylms_get_account_endpoint_url( 'billing-information' ) );. ?>">
				<span class="icon icon-regular">
					<?php // include(OHMYLMS_DIR . '/assets/images/icon/billing-icon.php');. ?>
				</span>

				<span class="icon icon-active">
					<?php // include(OHMYLMS_DIR . '/assets/images/icon/billing-active-icon.php');. ?>
				</span>

				<?php // echo __( 'Billing Information', 'ohmylms' );. ?>
			</a>
		</li> -->
	</ul>
</aside>

<?php do_action( 'ohmylms_after_account_navigation' ); ?>
