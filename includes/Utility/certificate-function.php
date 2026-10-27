<?php
/**
 * Certificate function.
 *
 * @package OhMyLMS
 */


/**
 * Get certificate object
 *
 * @param mixed $certificate_id Certificate identifier.
 * @return bool|\OhMyLMS\Data\Certificate
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_certificate( $certificate_id ) {
	return ohmylms()->certificate_factory->get_certificate( $certificate_id );
}
