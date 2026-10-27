<?php
/**
 * Attempt functions.
 *
 * @package OhMyLMS
 */


/**
 * OhMyLMS Attempt Functions
 *
 * This file contains functions to handle attempts in OhMyLMS.
 *
 * @param int $attempt_id The ID of the attempt to retrieve.
 * @return \OhMyLMS\Data\Attempt|bool Returns the Attempt object if found, or false if not.
 * @since 1.0.0
 */
function ohmylms_get_attempt( $attempt_id ) {
	return ohmylms()->attempt_factory->get_attempt( $attempt_id );
}
