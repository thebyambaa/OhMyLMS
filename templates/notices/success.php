<?php
/**
 * Notices success template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $notices ) {
	return;
}

?>
<div class="ohmylms-success">
	<div class="ohmylms-NoticeGroup">
		<?php foreach ( $notices as $notice ) : ?>
			<div class="ohmylms-message"<?php echo \OhMyLMS\Commerce\ohmylmse_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> role="alert">
				<?php echo \OhMyLMS\Commerce\ohmylmse_kses_notice( $notice['notice'] ); ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
