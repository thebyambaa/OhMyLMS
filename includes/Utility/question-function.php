<?php
/**
 * Question function.
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
function ohmylms_get_question( $quiz_id ) {
	return ohmylms()->question_factory->get_question( $quiz_id );
}

/**
 *
 */
function ohmylms_get_question_layouts() {
	$layouts = array(
		array(
			'slug' => 'one-question-per-page',
			'name' => 'One question per page',
		),
	);
	return apply_filters( 'ohmylms_question_layout', $layouts );
}
