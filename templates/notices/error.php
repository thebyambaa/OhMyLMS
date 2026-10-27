<?php
/**
 * Notices error template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! $notices ) {
	return;
}

$className = count( $notices ) === 1 ? 'only-one-error' : '';

?>
<div class="ohmylms-error-notices">
	<div class="ohmylms-NoticeGroup">
		<ul class="ohmylms-error <?php echo esc_attr( $className ); ?>" role="alert">
			<?php
			foreach ( $notices as $notice ) :
				?>
				<li
					<?php echo \OhMyLMS\Commerce\ohmylmse_get_notice_data_attr( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
					<?php echo \OhMyLMS\Commerce\ohmylmse_kses_notice( $notice['notice'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>

