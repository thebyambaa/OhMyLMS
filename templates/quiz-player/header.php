<?php
/**
 * Shared player header. Designs can override this presentation part.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ohmylms-quiz-header">
	<div class="ohmylms-container">
		<div class="quiz-header-wrapper">
			<div class="quiz-header-left">
				<p class="header-title"><?php echo esc_html( $quiz_name ); ?></p>
				<?php if ( $is_preview ) : ?>
					<small><?php esc_html_e( 'Preview of saved quiz · Responses are not recorded', 'ohmylms' ); ?></small>
				<?php endif; ?>
			</div>
			<a href="#" class="quiz-page-close" aria-label="<?php esc_attr_e( 'Close quiz', 'ohmylms' ); ?>"><span aria-hidden="true">×</span></a>
		</div>
	</div>
</div>
