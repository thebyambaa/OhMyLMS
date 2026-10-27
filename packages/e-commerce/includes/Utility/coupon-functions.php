<?php
/**
 * Coupon functions.
 *
 * @package OhMyLMS
 */

/**
 * Get the coupon ID by code.
 *
 * @param string $code The coupon code.
 * @param int    $exclude Optional. The ID to exclude from the results. Default is 0.
 * @return int The coupon ID.
 *
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_coupon_id_by_code( $code, $exclude = 0 ) {
	$data_store = \OhMyLMS\Commerce\DataStores::load( 'coupon' );
	$ids        = wp_cache_get( 'ohmylmse_coupon_id_from_code_' . $code, 'coupons' );
	if ( false === $ids ) {
		$ids = $data_store->get_ids_by_code( $code );
		if ( $ids ) {
			wp_cache_set( 'ohmylmse_coupon_id_from_code_' . $code, $ids, 'coupons' );
		}
	}

	$ids = array_diff( array_filter( array_map( 'absint', (array) $ids ) ), array( $exclude ) );
	return absint( current( $ids ) );
}
