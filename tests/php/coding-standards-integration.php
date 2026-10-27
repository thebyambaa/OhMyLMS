<?php
/**
 * Read-only checks for the shared sanitization and markup contracts changed during cleanup.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local standards checks.\n" );
}
define( 'DISABLE_WP_CRON', true );
require dirname( __DIR__, 5 ) . '/wp-load.php';
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'test_network_blocked', 'No external requests during standards checks.' );
	}
);
add_filter( 'pre_wp_mail', '__return_true' );

$standards_clean = ohmylms_clean(
	array(
		'name'   => '<b>Name</b>',
		'nested' => array( '<script>bad()</script>Text' ),
	)
);
if ( array(
	'name'   => 'Name',
	'nested' => array( 'Text' ),
) !== $standards_clean ) {
	throw new RuntimeException( 'The registered custom sanitizer must sanitize nested HTTP values.' );
}
$standards_coupon = new OhMyLMS\Commerce\Data\Coupon();
$standards_coupon->set_code( 'SAVE" data-untrusted="yes' );
$standards_label = ohmylmse_cart_totals_coupon_label( $standards_coupon, false );
if ( ! str_contains( $standards_label, 'Order discount:' ) || ! str_contains( $standards_label, '<svg' ) || ! str_contains( $standards_label, '&quot;' ) || str_contains( $standards_label, ' data-untrusted="yes"' ) ) {
	throw new RuntimeException( 'Coupon labels must preserve their control and escape the coupon text and attribute.' );
}
$standards_order_type = get_post_type_object( 'ohmylms-order' );
if ( ! $standards_order_type || __( 'Orders', 'ohmylms' ) !== $standards_order_type->labels->name || str_contains( $standards_order_type->labels->add_new_item, 'ohmylms-order' ) ) {
	throw new RuntimeException( 'Post type labels must use the localized display names rather than the storage slug.' );
}
$standards_comparisons = array(
	array( 1, '1', true ),
	array( 1.0, 1, true ),
	array( 0, '0.0', true ),
	array( PHP_INT_MAX, (string) PHP_INT_MAX, true ),
	array( PHP_INT_MAX, PHP_INT_MAX - 1, false ),
	array( null, 0, false ),
	array( 'invalid', 0, false ),
);
foreach ( $standards_comparisons as $standards_comparison ) {
	if ( $standards_comparison[2] !== OhMyLMS\Engagement\Rules::compare( $standards_comparison[0], '==', $standards_comparison[1] ) ) {
		throw new RuntimeException( 'Numeric achievement equality changed its valid-value contract.' );
	}
}
if ( null !== OhMyLMS\Assessment\NumericAnswer::parse( '1/0' ) || 0.0 !== OhMyLMS\Assessment\NumericAnswer::parse( '0/3' ) ) {
	throw new RuntimeException( 'Fraction parsing must preserve zero and reject a zero denominator.' );
}
if ( null !== OhMyLMS\Assessment\Cas\Expression::evaluate( OhMyLMS\Assessment\Cas\Expression::parse( 'x^0' ), array( 'x' => 0 ) ) || OhMyLMS\Assessment\Cas\Expression::has_form( OhMyLMS\Assessment\Cas\Expression::parse( 'x+0' ), 'simplified' ) || ! OhMyLMS\Assessment\Cas\Expression::has_form( OhMyLMS\Assessment\Cas\Expression::parse( '1/2' ), 'simplified' ) ) {
	throw new RuntimeException( 'Symbolic zero handling and simplified-form checks changed.' );
}
echo "PASS: sanitizer, coupon markup/escaping, post type labels and numeric comparison contracts.\n";
