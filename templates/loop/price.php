<?php
/**
 * OhMyLMS Loop Price
 *
 * This template can be overridden by copying it to yourtheme/ohmylms/loop/price.php.
 *
 * @package OhMyLMS\Templates
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $course;
$price_html = $course->get_price_html();
?>

<?php if ( $price_html ) : ?>
	<p class="price"><?php echo wp_kses_post( $price_html ); ?></p>
<?php endif; ?>
