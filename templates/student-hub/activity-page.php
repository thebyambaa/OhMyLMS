<?php
/**
 * Focused course activity document, preserving WordPress integration hooks.
 *
 * @package OhMyLMS
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
		<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<?php endif; ?>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'oml-activity-page' ); ?>>
	<?php
	wp_body_open();
	echo $activity_shell . $activity_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped player and practice templates passed by the caller.
	\OhMyLMS\Student\PlayerContext::close();
	wp_footer();
	?>
</body>
</html>
