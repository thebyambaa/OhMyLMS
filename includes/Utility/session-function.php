<?php
/**
 * Session function.
 *
 * @package OhMyLMS
 */


/**
 * Get chapter object
 *
 * @param mixed $session_id Session identifier.
 * @return bool|\OhMyLMS\Data\Chapter
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_session( $session_id ) {
	return ohmylms()->session_factory->get_session( $session_id );
}
