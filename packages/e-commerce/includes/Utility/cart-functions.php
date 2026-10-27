<?php
/**
 * Cart functions.
 *
 * @package OhMyLMS
 */


if ( ! function_exists( 'apply_filters' ) ) {
	require_once ABSPATH . 'wp-includes/plugin.php';
}

/**
 * Clears the cart session when called.
 */
function ohmylms_empty_cart() {
	if ( ! isset( \OhMyLMS\Commerce\ecommerce()->cart ) || '' === \OhMyLMS\Commerce\ecommerce()->cart ) {
		\OhMyLMS\Commerce\ecommerce()->cart = new \OhMyLMS\Commerce\Cart();
	}
	\OhMyLMS\Commerce\ecommerce()->cart->empty_cart( false );
}


/**
 * Ohmylmse cart totals subtotal html for Cart-functions.
 */
function ohmylmse_cart_totals_subtotal_html() {
	echo \OhMyLMS\Commerce\ecommerce()->cart->get_cart_subtotal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Outputs or returns the label for a given coupon.
 *
 * @param \OhMyLMS\Commerce\Data\Coupon|string $coupon Coupon object or coupon code.
 * @param bool                                 $echo Whether to echo the label or return it. Default true.
 * @return string|null The coupon label if $echo is false, otherwise null.
 *
 * @since 1.0.0
 */
function ohmylmse_cart_totals_coupon_label( $coupon, $echo = true ) {
	if ( is_string( $coupon ) ) {
		$coupon = new \OhMyLMS\Commerce\Data\Coupon( $coupon );
	}

	if ( ! $coupon->get_code() ) {
		return ''; // Return null if the coupon is not valid.
	}

	$coupon_html = sprintf(
		'<span class="applied-coupon">%s <a href="#" class="ohmylms-remove-coupon" data-coupon="' . esc_attr( $coupon->get_code() ) . '"><svg width="16" height="16" fill="none" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path fill="var(--ohmylms-primary-color)" fill-rule="evenodd" d="M8 15.5a7.5 7.5 0 100-15 7.5 7.5 0 000 15zm-2.78-4.72a.75.75 0 010-1.06L6.94 8 5.22 6.28a.75.75 0 011.06-1.06L8 6.94l1.72-1.72a.75.75 0 111.06 1.06L9.06 8l1.72 1.72a.75.75 0 01-1.06 1.06L8 9.06l-1.72 1.72a.75.75 0 01-1.06 0z" clip-rule="evenodd"/></svg></a></span>',
		esc_html( $coupon->get_code() )
	);
	$label       = sprintf(
		/* translators: %s: formatted coupon code and its remove control. */
		__( 'Order discount: %s', 'ohmylms' ),
		$coupon_html
	);
	if ( $echo ) {
		echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		return $label;
	}
}

/**
 * Outputs the HTML for a given coupon.
 *
 * @param \OhMyLMS\Commerce\Data\Coupon|string $coupon Coupon object or coupon code.
 * @return void
 *
 * @since 1.0.0
 */
function ohmylmse_cart_totals_coupon_html( $coupon ) {
	if ( is_string( $coupon ) ) {
		$coupon = new \OhMyLMS\Commerce\Data\Coupon( $coupon );
	}

	$amount               = \OhMyLMS\Commerce\ecommerce()->cart->get_coupon_discount_amount( $coupon->get_code() );
	$discount_amount_html = '-' . ohmylms_price( $amount );
	$coupon_html          = $discount_amount_html;

	echo $coupon_html;
}

/**
 * Outputs the HTML for the order total.
 *
 * @since 1.0.0
 */
function ohmylmse_cart_totals_order_total_html() {
	$value = '<strong>' . \OhMyLMS\Commerce\ecommerce()->cart->get_totals_by_key( 'total' ) . '</strong> ';
	$value = apply_filters( 'ohmylmse_cart_totals_order_total_html', ohmylms_price( $value ), );
	echo $value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
