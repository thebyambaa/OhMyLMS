<?php
/**
 * Review Comments Template
 *
 * Closing li is left out on purpose!. Please include it in your theme.
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="review-author-wrapper">
	<?php
		ohmylms_review_author();
		ohmylms_review_meta();
	?>
</div>

<p class="review-description">
	<?php echo wp_kses_post( get_comment_text( $comment ) ); ?>
</p>

<?php
/**
 * The ohmylms_review_before_comment_meta hook.
 *
 * @hooked ohmylms_review_display_rating - 10
 */
// do_action( 'ohmylms_review_before_comment_meta', $comment );.

?>
