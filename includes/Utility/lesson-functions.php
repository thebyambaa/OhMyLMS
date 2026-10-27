<?php
/**
 * Lesson functions.
 *
 * @package OhMyLMS
 */


/**
 * Get lesson object
 *
 * @param mixed $lesson_id Lesson identifier.
 * @return bool|\OhMyLMS\Data\Lesson
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_lesson( $lesson_id ) {
	return ohmylms()->lesson_factory->get_lesson( $lesson_id );
}
