<?php
/**
 * Student function.
 *
 * @package OhMyLMS
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get student object by student ID.
 *
 * @param int $student_id The student ID.
 * @return \OhMyLMS\Data\Student|false Returns a Student object if successful, otherwise false.
 * @since 1.0.0
 */
function ohmylms_get_student( $student_id ) {
	return ohmylms()->student_factory->get_student( $student_id );
}
