<?php
/**
 * Recurring Totals Template
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/checkout/recurring-subscription-totals.php.
 *
 * @package   OhMyLMSPro
 * @version  1.0.0
 */
defined( 'ABSPATH' ) || exit;
?>

<strong><?php echo wp_kses_post( $price_html ); ?></strong><br>
<span><?php echo esc_html__( 'First renewal:', 'ohmylms' ) . ' ' . esc_html( $renewal_date ); ?></span><br>
