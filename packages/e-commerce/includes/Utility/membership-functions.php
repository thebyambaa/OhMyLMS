<?php
/**
 * Membership functions.
 *
 * @package OhMyLMS
 */


/**
 * Get membership object
 *
 * @param int|bool $membership_id Membership ID or false to get current post's membership.
 * @return bool|OhMyLMS\Membership
 * @throws \Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_ecommerce_get_membership( $membership_id ) {
	return ohmylms()->membership_factory->get_membership( $membership_id );
}
