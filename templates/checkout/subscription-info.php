<?php
/**
 * Subscription info template
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/subscription-info.php.
 *
 * @package   OhMyLMSPro
 * @version  1.0.0
 * @global \OhMyLMS\Commerce\Data\Subscription $subscription
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use function OhMyLMS\Commerce\ecommerce;
global $wpdb;
$post_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s",
		'_original_order_id',
		$order_id
	)
);
$post_ids = ! empty( $post_ids ) ? (array) $post_ids : array();
if ( empty( $post_ids ) ) {
	return;
}
// if ( ! empty( $post_ids ) ) {.
?>

<div class="ohmylms-thankyou-table-wrapper subscription-info">
	<h2 class="ohmylms-order-summary-title">
		<?php echo esc_html__( 'Subscription Details', 'ohmylms' ); ?>
	</h2>
	<?php
	foreach ( $post_ids as $post_id ) {
		$subscription = ohmylms_ecommerce_get_subscription( $post_id );
		if ( ! isset( $subscription ) || ! $subscription instanceof \OhMyLMS\Commerce\Data\Subscription ) {
			continue;
		}
		$membership = ohmylms_get_membership( $subscription->get_membership_id() );
		if ( ! $membership ) {
			continue;
		}
		$name           = $membership->get_name() ? $membership->get_name() : '';
		$order_id       = $subscription->get_original_order_id();
		$original_order = ohmylms_ecommerce_get_order( $order_id );
		if ( ! $original_order ) {
			continue;
		}
		$tax_amount       = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $original_order->get_tax_rate(), array( 'total' => $subscription->get_recurring_amount() ) );
		$recurring_amount = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $subscription->get_recurring_amount();

		$price          = $membership->get_regular_price();
		$billing_period = $membership->get_subscription_period();

		if ( 'one_time' !== $billing_period ) {
			$billing_interval = $membership->get_subscription_period_interval();

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



		?>
		<table>
			<tbody>
				<tr class="membership-name">
					<td style="font-weight: bold;"><?php echo esc_html( $name ); ?></td>
					<td></td>

				</tr>
				<tr class="subscription-status">
					<td><?php echo esc_html__( 'Status', 'ohmylms' ); ?></td>
					<td class="status-<?php echo esc_attr( $subscription->get_status() ); ?>"><?php echo esc_html( $subscription->get_status() ); ?></td>
				</tr>
				<tr class="subscription-started">
					<td><?php echo esc_html__( 'Started', 'ohmylms' ); ?></td>
					<td><?php echo date_i18n( 'F j, Y', strtotime( $subscription->get_schedule_start_date() ) ); ?></td>
				</tr>
				<?php if ( $renewal_date ) : ?>
					<tr class="subscription-next-payment">
						<td><?php echo esc_html__( 'Next Billing', 'ohmylms' ); ?></td>
						<td><?php echo esc_html( $renewal_date ); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="subscription-amount">
					<td><?php echo esc_html__( 'Amount', 'ohmylms' ); ?></td>
					<td style="font-weight: bold;"><?php echo wp_kses_post( ohmylms_price( $recurring_amount ) . '/' . $subscription->get_billing_period() ); ?></td>
				</tr>
			</tbody>
		</table>
		<br>
	<?php } // End foreach ?>
</div>
