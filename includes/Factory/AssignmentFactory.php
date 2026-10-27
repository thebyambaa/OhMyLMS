<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class AssignmentFactory.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Factory;

use OhMyLMS\Data\Assignment;
/**
 * Class AssignmentFactory
 *
 * Factory class for creating and retrieving Lesson objects.
 *
 * @package OhMyLMS\Factory
 * @since 1.0.0
 */
class AssignmentFactory {

	/**
	 * Get assignment object
	 *
	 * @param bool $assignment_id Assignment identifier.
	 * @return bool|Assignment
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_assignment( $assignment_id = false ) {
		$assignment_id = $this->get_assignment_id( $assignment_id );
		if ( ! $assignment_id ) {
			return false;
		}
		return new Assignment( $assignment_id );
	}


	/**
	 * Get assignment id
	 *
	 * @param mixed $assignment Assignment.
	 * @return bool|int
	 * @since 1.0.0
	 */
	private function get_assignment_id( $assignment ) {
		global $post;

		// Check if input is false and post is set.
		if ( false === $assignment && isset( $post, $post->ID ) && OHMYLMS_LESSON_CPT === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		}

		// If input is numeric, check if assignment exists.
		elseif ( is_numeric( $assignment ) ) {
			return $this->is_assignment_exist( $assignment ) ? $assignment : false;
		}

		// If input is an instance of Assignment.
		elseif ( $assignment instanceof Assignment ) {
			$id = $assignment->get_id();
			return $this->is_assignment_exist( $id ) ? $id : false;
		}

		// If input contains a valid ID property.
		elseif ( ! empty( $assignment->ID ) ) {
			return $this->is_assignment_exist( $assignment->ID ) ? $assignment->ID : false;
		}

		// Otherwise, return false.
		else {
			return false;
		}
	}


	/**
	 * Checks whether a assignment with the given ID exists.
	 *
	 * This method verifies that the assignment exists in the database and is of
	 * the correct post type (`OHMYLMS_LESSON_CPT`).
	 *
	 * @param int $assignment_id The ID of the assignment to check.
	 * @return bool Returns true if the assignment exists, otherwise false.
	 * @since 1.0.0
	 */
	public function is_assignment_exist( $assignment_id ) {
		if ( ! $assignment_id ) {
			return false;
		}

		$assignment = get_post( $assignment_id );

		// Check if the post exists and the post type matches.
		if ( $assignment && OHMYLMS_ASSIGNMENT_CPT === get_post_type( $assignment_id ) ) {
			return true;
		} else {
			return false;
		}
	}
}
