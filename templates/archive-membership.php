<?php
/**
 * Template for displaying all membership.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/archive-membership.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit();

ohmylms_get_header();

/**
 * Hook: ohmylms_membership_before_main_content.
 */
do_action( 'ohmylms_membership_before_main_content' );
?>
<section class="ohmylms-membership">
	<div class="ohmylms-container adfadfad">
		<?php
		if ( ohmylms_is_pro() ) {
			$membership_page = get_post( ohmylms_get_page_id( 'membership' ) );
			$custom_title    = $membership_page ? \OhMyLMS\Shortcodes\ShortCodeMembershipPlan::extract_title_from_content( $membership_page->post_content ) : '';
			$title_filter    = \OhMyLMS\Shortcodes\ShortCodeMembershipPlan::apply_custom_title( $custom_title );

			/**
			 * Hook: ohmylms_membership_section_header.
			 *
			 * Hooked: ohmylms_membership_header (5).
			 */
			do_action( 'ohmylms_membership_section_header' );

			if ( have_posts() ) {

				/**
				 * Hook: ohmylms_before_membership_loop.
				 */
				do_action( 'ohmylms_before_membership_loop' );

				// ---this is used for membership listing wrapper div start----
				ohmylms_membership_loop_start();

				while ( have_posts() ) {
					the_post();

					ohmylms_get_template_part( 'content', 'membership' );
				}

				// ---this is used for membership listing wrapper div end----
				ohmylms_membership_loop_end();

				/**
				 * Hook: ohmylms_after_membership_loop.
				 */
				do_action( 'ohmylms_after_membership_loop' );

			} else {
				/**
				 * Hook: ohmylms_no_membership.
				 *
				 * Hooked: ohmylms_no_membership_found (5).
				 */
				do_action( 'ohmylms_no_membership' );
			}

			\OhMyLMS\Shortcodes\ShortCodeMembershipPlan::remove_custom_title( $title_filter );
		} else {

			?>
			<div class="pro-membership-message">
				<h2><?php echo esc_html__( 'No Membership Yet', 'ohmylms' ); ?></h2>
			</div>
			<?php
		}
		?>
	</div>
</section>
<?php

/**
 * Hook: ohmylms_membership_after_main_content.
 */
do_action( 'ohmylms_membership_after_main_content' );


ohmylms_get_footer();
