<?php
/**
 * The template for displaying scroll to top
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/global/scrollto-top.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="ohmylms-scroll-to-top"
	data-wp-interactive="ohmylms/ui"
	data-wp-context='{"scrolled":false,"threshold":600}'
	data-wp-class--visible="context.scrolled"
	data-wp-init="callbacks.trackScroll"
	data-wp-on-window--scroll="callbacks.trackScroll">
	<button type="button" title="<?php esc_attr_e( 'Scroll to top', 'ohmylms' ); ?>" aria-label="<?php esc_attr_e( 'Scroll to top', 'ohmylms' ); ?>" data-wp-on--click="actions.scrollToTop">
		<svg width="44" height="44" fill="none" viewBox="0 0 44 44" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M11.38 19.63a1.833 1.833 0 11-2.593-2.593l10.62-10.62a3.667 3.667 0 015.186 0l10.62 10.62a1.833 1.833 0 11-2.593 2.593l-8.787-8.787v25.824a1.833 1.833 0 01-3.666 0V10.843L11.38 19.63z"/></svg>
	</button>
</div>
