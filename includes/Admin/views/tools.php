<?php
/**
 * Tools.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="ohmylms-tools">
	<h2><?php echo esc_html__( 'Install Sample Data', 'ohmylms' ); ?></h2>
	<p class="ohmylms-install-actions">
		<a class="button button-primary ohmylms-tools-sample-data-install"
			data-installing-text="<?php echo esc_attr__( 'Installing...', 'ohmylms' ); ?>"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'index.php?page=ohmylms-tools' ), 'install-sample-course' ) ); ?>">
			<?php echo esc_html__( 'Install', 'ohmylms' ); ?>
		</a>

		<a class="button ohmylms-tools-sample-data-delete"
			data-installing-text="<?php echo esc_attr__( 'Deleting...', 'ohmylms' ); ?>"
			href="<?php echo esc_url( wp_nonce_url( admin_url( 'index.php?page=ohmylms-tools' ), 'delete-sample-course' ) ); ?>">
			<?php echo esc_html__( 'Delete Sample Data', 'ohmylms' ); ?>
		</a>
	</p>
</div>
