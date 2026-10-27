<?php
/**
 * Public subject browser document, preserving WordPress integrations.
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
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'oml-subject-page' ); ?>>
	<?php wp_body_open(); ?>
	<main><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped contextually by the subject browser template. ?></main>
	<?php wp_footer(); ?>
</body>
</html>
