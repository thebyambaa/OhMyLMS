<?php
/**
 * Recurring Totals Template
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/recurring-totals.php.
 *
 * @package   OhMyLMSPro
 * @version  1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="ohmylms-checkout-order-review-table">
	<tbody>
<tr class="recurring-totals">
	<td><?php esc_html_e( 'Recurring totals', 'ohmylms' ); ?></td>
	<td>
<?php

		/**
		 * Membership.
		 *
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_recurring_totals_subtotals', $membership );


		do_action( 'ohmylms_recurring_subscription_totals', $membership );
?>
	</td>
</tr>
	</tbody>
</table>
