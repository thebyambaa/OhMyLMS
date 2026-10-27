<?php
/**
 * Checkout.
 *
 * @package OhMyLMS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$user_id = get_current_user_id();

?>
<form id="userForm" action="#" method="POST">
<div class="ohmylms-checkout-wrapper container">
	<div class="col-1">
		<div class="ohmylms-form-group">
			<label for="name">Name:</label>
			<input type="text" id="name" name="name" required>
		</div>

		<div class="ohmylms-form-group">
			<label for="email">Email:</label>
			<input type="email" id="email" name="email" required>

			<input type="hidden" id="user_id" value="<?php echo $user_id; ?>">
		</div>

	</div>
	<div class="col-2">
		<h2>Order Details</h2>
		<div id="orderDetails">
			<?php

			$totalPrice = 0;
			if ( ! is_admin() ) {
				foreach ( ohmylms()->order_loader->cart->get_cart() as $cart_item_key => $cart_item ) {
					$course              = $cart_item['data'];
					$product['price']    = 50;
					$product['quantity'] = 1;

					echo "<div>
                                <p>Product Name: {$course->get_name()}</p>
                                <p>Quantity: 1</p>
                                <p>Price: $50 </p>
                                <hr>
                              </div>";
					$totalPrice += $product['price'] * $product['quantity'];
				}
			}


			echo '<p><strong>Total Price: $' . number_format( $totalPrice, 2 ) . '</strong></p>';
			?>
		</div>

		<button class="checkout-btn" type="submit">Checkout</button>
	</div>
	</div>
</div>
</form>
