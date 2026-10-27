<?php
/**
 * Assignment function.
 *
 * @package OhMyLMS
 */


/**
 * Get chapter object
 *
 * @param mixed $assignment_id Assignment identifier.
 * @return bool|\OhMyLMS\Data\Chapter
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_assignment( $assignment_id ) {
	return ohmylms()->assignment_factory->get_assignment( $assignment_id );
}
