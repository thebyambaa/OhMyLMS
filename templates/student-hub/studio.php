<?php
/**
 * Studio workspace: one staff screen inside the hub. The React app draws the section; the hub draws the navigation.
 * Override in yourtheme/ohmylms/student-hub/studio.php.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;

?>
<div id="ohmylms-front-dashboard" class="oml-dashboard oml-dashboard--embedded" data-section="<?php echo esc_attr( $section ); ?>">
	<p class="oml-dash-note"><?php esc_html_e( 'Loading…', 'ohmylms' ); ?></p>
</div>
