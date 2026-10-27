<?php
/**
 * Template Name: OhMyLMS Checkout - No Header
 *
 * @package OhMyLMS
 */
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?> >
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php
	// Start the page content.
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			the_content();
			endwhile;
		endif;
	?>
</body>

<?php
wp_footer();
