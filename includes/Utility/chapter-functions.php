<?php
/**
 * Chapter functions.
 *
 * @package OhMyLMS
 */


/**
 * Get chapter object
 *
 * @param mixed $chapter_id Chapter identifier.
 * @return bool|\OhMyLMS\Data\Chapter
 * @throws Exception When the operation cannot be completed.
 * @since 1.0.0
 */
function ohmylms_get_chapter( $chapter_id ) {
	return ohmylms()->chapter_factory->get_chapter( $chapter_id );
}
