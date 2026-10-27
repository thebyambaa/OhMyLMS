<?php
/**
 * Full-width canonical Student Home, retaining WordPress head/footer integrations.
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
<body <?php body_class( 'oml-hub-page' ); ?>>
	<?php wp_body_open(); ?>
	<a class="screen-reader-text" href="#student-home-content"><?php esc_html_e( 'Skip to learning content', 'ohmylms' ); ?></a>
	<div <?php echo \OhMyLMS\Student\Hub::uses_workspace_landmark() ? '' : 'role="main" id="student-home-content" tabindex="-1"'; ?>>
		<?php
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
