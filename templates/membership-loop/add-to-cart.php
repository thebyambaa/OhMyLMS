<?php
/**
 * Membership Loop Add to Cart
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/membership-loop/add-to-cart.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $membership;
if ( null == $membership ) {
	return;
}
$args = array(
	'attributes' => array(
		'data-membership_id'   => $membership->get_id(),
		'rel'                  => 'nofollow',
		'is_already_purchased' => $membership->is_already_purchased(),
	),
);

$add_to_cart = apply_filters(
	'ohmylms_membership_loop_add_to_cart_link', // WPCS: XSS ok.
	sprintf(
		'<a href="%s" data-quantity="%s" class="%s" %s>%s</a>',
		esc_url( $membership->add_to_cart_url() ),
		esc_attr( 1 ),
		esc_attr( 'ohmylms-button add_to_cart_button' ),
		isset( $args['attributes'] ) ? ohmylms_implode_html_attributes( $args['attributes'] ) : '',
		wp_kses_post( $membership->add_to_cart_text() )
	),
	$membership,
	$args
);


?>


<div class="cta-button-ara">
	<?php echo $add_to_cart; ?>
</div>
