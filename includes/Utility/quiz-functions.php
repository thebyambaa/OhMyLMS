<?php
/**
 * Quiz functions.
 *
 * @package OhMyLMS
 */


/**
 * Get quiz object
 *
 * @param int $quiz_id Quiz identifier.
 * @return bool|\OhMyLMS\Data\Quiz
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_quiz( $quiz_id ) {
	return ohmylms()->quiz_factory->get_quiz( $quiz_id );
}
