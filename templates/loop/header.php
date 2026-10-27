<?php
/**
 * Courses listing page header
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/header.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<header class="ohmylms-courses-header">
	<?php

	if ( apply_filters( 'ohmylms_show_page_title', true ) ) :
		?>
		<h1 class="courses-section-title page-title">
			<?php echo esc_html__( 'Discover Courses', 'ohmylms' ); ?>
		</h1>
	<?php endif; ?>

	<?php
	do_action( 'ohmylms_archive_description' );
	?>
</header>
