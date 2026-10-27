<?php
/**
 * Admin View: Settings
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_tab_label = isset( $tabs[ $ohmylms_current_tab ] ) ? $tabs[ $ohmylms_current_tab ] : '';

?>

<div class="wrap ohmylms-settings-wrapper">
	<?php do_action( 'ohmylms_before_settings_' . $ohmylms_current_tab ); ?>

	<form method="post" id="mainform" action="" enctype="multipart/form-data">
		<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
			<?php

			foreach ( $tabs as $slug => $label ) {
				echo '<a href="' . esc_html( admin_url( 'admin.php?page=ohmylms-settings&tab=' . esc_attr( $slug ) ) ) . '" class="nav-tab ' . ( $ohmylms_current_tab === $slug ? 'nav-tab-active' : '' ) . '">' . esc_html( $label ) . '</a>';
			}

			do_action( 'ohmylms_settings_tabs' );

			?>
		</nav>

		<?php
			do_action( 'ohmylms_sections_' . $ohmylms_current_tab );

			do_action( 'ohmylms_settings_' . $ohmylms_current_tab );
		?>

		<p class="submit">
			<?php wp_nonce_field( 'ohmylms-settings' ); ?>
			<button name="save" class="button-primary ohmylms-save-button" type="submit" value="<?php esc_attr_e( 'Save changes', 'ohmylms' ); ?>"><?php esc_html_e( 'Save changes', 'ohmylms' ); ?></button>
		</p>
	</form>

	<?php do_action( 'ohmylms_after_settings_' . $ohmylms_current_tab ); ?>
</div>
