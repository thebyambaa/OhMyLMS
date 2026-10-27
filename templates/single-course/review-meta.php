<?php
/**
 * Template for displaying the review meta in a single course.
 *
 * This template can be overridden by copying it to yourtheme/single-course/review-meta.php.
 *
 * @package OhMyLMS\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

global $comment;
$verified          = true;
$rating            = intval( get_comment_meta( $comment->comment_ID, 'rating', true ) );
$rating_percentage = ( $rating / 5 ) * 100;

?>
<?php if ( '0' === $comment->comment_approved ) { ?>
	<!-- <p class="meta">
		<em class="ohmylms-review__awaiting-approval">
			<?php // esc_html_e( 'Your review is awaiting approval', 'ohmylms' );. ?>
		</em>
	</p> -->
<?php } ?>

<div class="review-content">
	<p class="author-name"><?php echo esc_html( get_comment_author( $comment ) ); ?></p>

	<?php do_action( 'ohmylms_review_rating_area', $comment ); ?>
</div>
