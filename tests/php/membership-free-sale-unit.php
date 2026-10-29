<?php
/**
 * Calendar boundaries for fixed-date free membership access.
 *
 * @package OhMyLMS
 */

define( 'ABSPATH', __DIR__ );
require dirname( __DIR__, 2 ) . '/includes/Membership/FreeSale.php';

use OhMyLMS\Membership\FreeSale;

/**
 * Assert a free-sale calendar result.
 *
 * @param bool $condition Expected condition.
 * @throws RuntimeException When a result differs.
 */
function ohmylms_free_sale_check( $condition ) {
	if ( ! $condition ) {
		throw new RuntimeException( 'Free-sale calendar assertion failed.' );
	}
}

ohmylms_free_sale_check( '' === FreeSale::date( '2027-02-30' ) );
ohmylms_free_sale_check( '' === FreeSale::date( 'tomorrow' ) );
ohmylms_free_sale_check( '' === FreeSale::date( array() ) );
ohmylms_free_sale_check( '2028-02-29' === FreeSale::date( array( 'date' => '2028-02-29T00:00:00' ) ) );
$timezone = new DateTimeZone( 'Asia/Ulaanbaatar' );
ohmylms_free_sale_check( strtotime( '2027-02-01T00:00:00+08:00' ) === FreeSale::cutoff( '2027-01-31', $timezone ) );
ohmylms_free_sale_check( 0 === FreeSale::cutoff( '2027-02-30', $timezone ) );
$timezone = new DateTimeZone( 'America/New_York' );
ohmylms_free_sale_check( strtotime( '2027-03-15T00:00:00-04:00' ) === FreeSale::cutoff( '2027-03-14', $timezone ) );
echo "Free-sale calendar checks passed.\n";
