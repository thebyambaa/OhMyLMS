<?php
/**
 * Membership function.
 *
 * @package OhMyLMS
 */


/**
 * Get membership object
 *
 * @param mixed $membership_id Membership identifier.
 * @return bool|\OhMyLMS\Data\Membership
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_membership( $membership_id ) {
	return ohmylms()->membership_factory->get_membership( $membership_id );
}
