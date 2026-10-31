<?php
/**
 * Profile loop membership card template.
 *
 * @package OhMyLMS
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use function OhMyLMS\Commerce\ecommerce;
// Extract data from membership object.
$subscription = isset( $memebership->subscription ) ? $memebership->subscription : null;
$order        = isset( $memebership->order ) ? $memebership->order : null;
$products     = $memebership->get_products();
$course_count = count( $products );
$is_one_time  = $memebership->get_subscription_period() === 'one_time';
if ( ! $order ) {
	return;
}

$recurring_amount = 0;

if ( ! $is_one_time ) {
	$tax_amount       = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $order->get_tax_rate(), array( 'total' => $subscription->get_recurring_amount() ) );
	$recurring_amount = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $subscription->get_recurring_amount();
}


// Safely get status.
$status         = $subscription ? $subscription->get_status() : '';
$status_classes = 'ohmylms-membership-status ' . 'ohmylms-status-' . strtolower( $status );

// Safely get price.
$price_html = __( 'Not available', 'ohmylms' );

if ( $is_one_time ) {
	$price_html = ohmylms_price( $memebership->get_regular_price() ) . ' / ' . __( 'One Time', 'ohmylms' );
} elseif ( $subscription ) {
		$price_html = ohmylms_price( $recurring_amount ) . ' / ' . $subscription->get_billing_period();
} else {
	$price_html = ohmylms_price( $memebership->get_regular_price() ) . ' / ' . $memebership->get_subscription_period();
}

// Safely get purchase date.
$purchase_date = $order ? ohmylms_format_datetime( $order->get_date_created() ) : __( 'Not available', 'ohmylms' );

if ( ! $is_one_time ) {

	$price          = $memebership->get_regular_price();
	$billing_period = $memebership->get_subscription_period();

	$billing_interval = $memebership->get_subscription_period_interval();

	$stored_totals = ecommerce()->session->get( 'cart_totals' );
	if ( ! empty( $stored_totals ) && isset( $stored_totals['tax_rate'] ) && $stored_totals['tax_rate'] > 0 ) {
		$tax_rate = $stored_totals['tax_rate'];
		// Now calculate tax based on the discounted total.
		$tax_data = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax(
			$tax_rate,
			array(
				'total' => $price,
			)
		);
		$price    = isset( $tax_data['total_with_tax'] ) ? $tax_data['total_with_tax'] : $price;
	}
	$price_html = ohmylms_price( $price ) . ' / ' . $billing_period;
	$now        = current_time( 'timestamp' );

	switch ( $billing_period ) {
		case 'month':
			$renewal = strtotime( "+$billing_interval month", $now );
			break;
		case 'week':
			$renewal = strtotime( "+$billing_interval week", $now );
			break;
		case 'day':
			$renewal = strtotime( "+$billing_interval day", $now );
			break;
		case 'year':
		default:
			$renewal = strtotime( "+$billing_interval year", $now );
			break;
	}
	$renewal_date = date_i18n( get_option( 'date_format' ), $renewal );
} else {
	$renewal_date = '';
}

// Safely get payment method.
$payment_method = $order && $order->get_payment_method_title() ? $order->get_payment_method_title() : __( 'Not available', 'ohmylms' );
$plan_dialog_id = wp_unique_id( 'ohmylms-plan-details-' );
?>
<div class="ohmylms-membership-card ohmylms-dashboard-single-membership" data-wp-interactive="ohmylms/account" data-wp-context='{"planOpen":false}'>
	<div class="ohmylms-membership-card-header">
		<h3 class="ohmylms-membership-name"><?php echo esc_html( $memebership->get_name() ); ?></h3>
		<span class="<?php echo esc_attr( $status_classes ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
	</div>
	<div class="ohmylms-membership-card-body">
		<ul class="ohmylms-membership-details">
			<li>
				<strong><?php echo esc_html__( 'Price:', 'ohmylms' ); ?></strong>
				<span><?php echo wp_kses_post( $price_html ); ?></span>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Courses Included:', 'ohmylms' ); ?></strong>
				<span><?php echo esc_html( $course_count > 0 ? $course_count : esc_html__( 'No courses yet', 'ohmylms' ) ); ?></span>
			</li>
			<li>
				<strong><?php echo esc_html__( 'Purchase Date:', 'ohmylms' ); ?></strong>
				<span><?php echo esc_html( $purchase_date ); ?></span>
			</li>
			<?php if ( ! $is_one_time ) : ?>
			<li>
				<strong><?php echo esc_html__( 'Next Renewal:', 'ohmylms' ); ?></strong>
				<span><?php echo esc_html( $renewal_date ); ?></span>
			</li>
			<?php endif; ?>
			<li>
				<strong><?php echo esc_html__( 'Payment Method:', 'ohmylms' ); ?></strong>
				<span><?php echo esc_html( $payment_method ); ?></span>
			</li>
		</ul>
	</div>
	<div class="ohmylms-membership-card-footer">
		<a href="#<?php echo esc_attr( $plan_dialog_id ); ?>" class="ohmylms-button view-plan" role="button" aria-haspopup="dialog" aria-controls="<?php echo esc_attr( $plan_dialog_id ); ?>" aria-expanded="false" data-wp-on--click="actions.openPlan" data-wp-bind--aria-expanded="context.planOpen"><?php echo esc_html__( 'View Plan', 'ohmylms' ); ?></a>
	</div>

	<div class="ohmylms-membership-modal" id="<?php echo esc_attr( $plan_dialog_id ); ?>" aria-hidden="true" data-wp-style--display="state.planDisplay" data-wp-bind--aria-hidden="!context.planOpen" data-wp-on--click="actions.planBackdrop" data-wp-watch="callbacks.planFocus">
		<div class="ohmylms-membership-modal-wrapper">
			<div class="ohmylms-membership-modal-inner" role="dialog" aria-modal="true" tabindex="-1" aria-labelledby="<?php echo esc_attr( $plan_dialog_id . '-title' ); ?>">
				<h4 class="membership-modal-title" id="<?php echo esc_attr( $plan_dialog_id . '-title' ); ?>">
					<?php echo esc_html__( 'Plan Details', 'ohmylms' ); ?>
					<a href="#" role="button" class="close-modal" aria-label="<?php esc_attr_e( 'Close', 'ohmylms' ); ?>" data-wp-on--click="actions.closePlan">
						<svg width="14" height="14" fill="none" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg"><path stroke="#A1A1AA" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 1L1 13M1 1l12 12"/></svg>
					</a>
				</h4>
				<div class="membership-plan-wrapper">
					<div class="membership-table-header">
						<p class="plan-name"><?php echo esc_html( $memebership->get_name() ); ?></p>
						<span class="price"><?php echo wp_kses_post( $price_html ); ?></span>
					</div>
					<?php if ( $course_count > 0 ) : ?>
					<ul class="membership-table-list">
						<?php foreach ( $products as $product ) : ?>
							<li>
								<svg width="12" height="10" fill="none" viewBox="0 0 12 10" xmlns="http://www.w3.org/2000/svg"><path stroke="#35BD4C" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.697 1.667L4.03 8.332 1 5.303"/></svg>
								<?php echo isset( $product['label'] ) ? esc_html( $product['label'] ) : ''; ?>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php else : ?>
						<p class="ohmylms-no-courses-message"><?php echo esc_html__( 'No courses are included in this membership yet.', 'ohmylms' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
