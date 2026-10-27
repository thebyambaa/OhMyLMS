<?php
/**
 * Template for displaying checkout billing form.
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/billing-form.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

use OhMyLMS\Membership\MembershipHelper;

defined( 'ABSPATH' ) || exit();
?>

<?php

$args = array(
	'post_type'      => 'ohmylms-membership', // Specify the custom post type.
	'posts_per_page' => -1,                 // Get all posts.
	'fields'         => 'ids',               // Only retrieve post IDs.
);

$membership_plans = get_posts( $args );


if ( empty( $membership_plans ) || ! is_array( $membership_plans ) ) {
	echo apply_filters( 'ohmylms_membership_plans_no_plans_message', __( 'No membership plans found.', 'ohmylms' ) );
}

// Apply filter before displaying plans.
echo apply_filters( 'ohmylms_before_display_membership_plans', '', $membership_id );

// Change buy now button text.
$buy_now_text = apply_filters( 'ohmylms_membership_plans_buy_button_text', __( 'Buy Now', 'ohmylms' ) );

ob_start();
?>
<div class="membership-plans-container">
	<?php foreach ( $membership_plans as $plan_id ) : ?>
		<div class="membership-plan">
			<h3>
				<?php
					$title = get_the_title( $plan_id );
					echo esc_html( $title );
				?>
			</h3>
			<p>
				<?php
				$pricing_type = get_post_meta( $plan_id, 'ohmylms_membership_pricing_type', true );
				if ( 'Paid' == $pricing_type ) {
					$price = get_post_meta( $plan_id, 'ohmylms_membership_regular_price', true );
					echo esc_html__( 'Price: ', 'ohmylms' ) . '$' . number_format( (float) $price, 2, '.', '' );
				} else {
					echo esc_html__( 'Price: ', 'ohmylms' ) . 'Free';
				}
				?>
			</p>
			<p>
			<?php
				$billing_period = get_post_meta( $plan_id, 'ohmylms_membership_billing_period', true );
				echo esc_html__( 'Subscription: ', 'ohmylms' ) . esc_html( $billing_period )
			?>
			</p>
			<button class="buy-now-button crlm_purchase" data-membership="<?php echo esc_attr( $plan_id ); ?>" data-plan="<?php echo esc_attr( $plan['plan_id'] ); ?>" ><?php echo esc_html( $buy_now_text ); ?></button>
			<?php
			$courses = unserialize( get_post_meta( $plan_id, 'ohmylms_membership_selected_products', true ) );
			if ( ! empty( $courses ) && is_array( $courses ) ) :
				?>
				<p><?php __( 'Courses: ', 'ohmylms' ); ?></p>
				<ul>
					<?php foreach ( $courses as $course ) : ?>
						<li><?php echo esc_html( get_the_title( $course['id'] ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>

<style>
	.membership-plans-container {
		display: flex;
		flex-wrap: wrap;
		gap: 20px;
	}

	.membership-plan {
		border: 1px solid #ccc;
		padding: 20px;
		width: calc(33.333% - 20px);
		box-sizing: border-box;
	}

	.membership-plan h3 {
		margin-top: 0;
	}

	.buy-now-button {
		background-color: #0073aa;
		width: 100%;
		color: #fff;
		border: none;
		padding: 10px 20px;
		cursor: pointer;
		margin: 15px 0;
	}

	.buy-now-button:hover {
		background-color: #005a87;
	}
</style>
<?php

// Apply filter after displaying plans.
echo apply_filters( 'ohmylms_after_display_membership_plans', '', $membership_id );

echo ob_get_clean();
?>
