<?php
/**
 * Membership Loop Price
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/membership-loop/price.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
global $membership;
if ( null == $membership ) {
	return;
}
$currency = ohmylms_get_currency_symbol();

$regular_price           = $membership->get_regular_price();
$sale_price              = $membership->get_sale_price();
$signup_fee              = $membership->get_sign_up_fee();
$get_subscription_length = $membership->get_subscription_length();
$price                   = $membership->get_price();
$subscription_duration   = $membership->get_subscription_duration();
$subscription_period     = $membership->get_subscription_period();
$period                  = 'one_time' === $subscription_period ? __( 'one time', 'ohmylms' ) : $subscription_period;
$save_calculation        = $regular_price ? number_format( ( ( floatval( $regular_price ) - floatval( $sale_price ) ) / floatval( $regular_price ) ) * 100, 2 ) : 0;
?>


<p class="membership-discount-price">
	<?php
		$price           = $membership->validate_on_sale() ? $price : $regular_price;
		$negative        = $price < 0;
		$formatted_price = ( $negative ? '-' : '' ) . sprintf( ohmylms_get_price_format(), '<span class="ohmylms-price-currency-symbol">' . get_ohmylms_currency_symbol( get_ohmylms_currency() ) . '</span>', $price );

		echo wp_kses_post( $formatted_price );


		echo '<small>' . '/' . esc_html( $period ) . '</small>';

		// Append sign-up fee if greater than zero, with shorter wording and small font.
	if ( floatval( $signup_fee ) > 0 ) {
		$negative_signup      = $signup_fee < 0;
		$formatted_signup_fee = ( $negative_signup ? '-' : '' ) . sprintf( ohmylms_get_price_format(), '<span class="ohmylms-price-currency-symbol">' . get_ohmylms_currency_symbol( get_ohmylms_currency() ) . '</span>', $signup_fee );
		echo ' <small>+ ' . wp_kses_post( $formatted_signup_fee ) . ' sign-up</small>';
	}
	?>
</p>


<?php if ( $membership->is_on_sale() && $membership->validate_on_sale() ) { ?>
	<p class="membership-regular-price">
		<?php
			echo esc_html__( 'Normally ', 'ohmylms' );

			$negative        = $regular_price < 0;
			$formatted_price = ( $negative ? '-' : '' ) . sprintf( ohmylms_get_price_format(), '<span class="ohmylms-price-currency-symbol">' . get_ohmylms_currency_symbol( get_ohmylms_currency() ) . '</span>', $regular_price );

			echo '<del>' . wp_kses_post( $formatted_price ) . '</del>';

		?>
		<span class="discount-percent">save <?php echo esc_html( $save_calculation ); ?>%</span>
	</p>
<?php } ?>
