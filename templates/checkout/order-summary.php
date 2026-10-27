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

	$title = '';
	$total = 0;
if ( isset( $_GET['membership_id'] ) ) {
	$membership_id = sanitize_text_field( wp_unslash( $_GET['membership_id'] ));
	$title         = get_the_title( $membership_id );
	$total         = MembershipHelper::get_plan_price( $membership_id );
} else {
	$cart_items = \OhMyLMS\Commerce\ecommerce()->cart->get_cart_contents();
	$total      = \OhMyLMS\Commerce\ecommerce()->cart->get_total( $cart_items );
}
?>
<h2><?php esc_html_e( 'Order Summary', 'ohmylms' ); ?></h2>
<table class="order-summary">
	<thead>
	<tr>
		<th><?php esc_html_e( 'Product', 'ohmylms' ); ?></th>
		<th><?php esc_html_e( 'Quantity', 'ohmylms' ); ?></th>
		<th><?php esc_html_e( 'Price', 'ohmylms' ); ?></th>
	</tr>
	</thead>
	<tbody>
	<?php

	if ( ! empty( $membership_id ) ) {
		?>
		<tr>
			<td><?php echo esc_html( $title ); ?></td>
			<td>
				<?php
				echo 1;
				?>
			</td>
			<td><?php echo wp_kses_post( ohmylms_price( $total ) ); ?></td>
		</tr>
		<?php
	} else {
		foreach ( $cart_items as $index => $cart_item ) {
			if ( isset( $cart_item['data'] ) ) {
				?>
				<tr>
					<td><?php echo esc_html( get_the_title( $cart_item['course_id'] ) ); ?></td>
					<td>
						<?php
						if ( isset( $cart_item['data']->quantity ) ) {
							echo esc_html( $cart_item['data']->quantity );
						} else {
							echo 1;
						}
						?>
					</td>
					<td><?php echo wp_kses_post( ohmylms_price( $cart_item['data']->is_on_sale() && $cart_item['data']->validate_on_sale() ? $cart_item['data']->get_price() : $cart_item['data']->get_regular_price() ) ); ?></td>
				</tr>
				<?php
			}
		}
	}
	?>
	</tbody>
	<tfoot>
	<tr>
		<td colspan="2"><?php esc_html_e( 'Subtotal', 'ohmylms' ); ?></td>
		<td><?php echo wp_kses_post( ohmylms_price( $total ) ); ?></td>
	</tr>
	<tr>
		<td colspan="2"><?php esc_html_e( 'Tax', 'ohmylms' ); ?></td>
		<td>$0.0</td>
	</tr>
	<tr>
		<td colspan="2"><?php esc_html_e( 'Total', 'ohmylms' ); ?></td>
		<td><?php echo wp_kses_post( ohmylms_price( $total ) ); ?></td>
	</tr>
	</tfoot>
</table>
