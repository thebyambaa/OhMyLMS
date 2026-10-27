<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Factory;

use OhMyLMS\Data\Membership;

/**
 * Plugin membership factory implementation.
 */
class MembershipFactory {

	/**
	 * Get membership object
	 *
	 * @param bool $membership_id Membership identifier.
	 * @return bool|Membership
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_membership( $membership_id = false ) {
		$membership_id = $this->get_membership_id( $membership_id );
		if ( ! $membership_id ) {
			return false;
		}
		return new Membership( $membership_id );
	}


	/**
	 * Get membership id
	 *
	 * @param mixed $membership Membership.
	 * @return bool|int
	 * @since 1.0.0
	 */
	private function get_membership_id( $membership ) {
		global $post;
		if ( false === $membership && isset( $post, $post->ID ) && OHMYLMS_MEMBERSHIP_CPT === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		} elseif ( is_numeric( $membership ) ) {
			return $this->is_membership_exist( $membership ) ? $membership : false;
		} elseif ( $membership instanceof Membership ) {
			$id = $membership->get_id();
			return $this->is_membership_exist( $id ) ? $id : false;
		} elseif ( ! empty( $membership->ID ) ) {
			return $this->is_membership_exist( $membership->ID ) ? $membership->ID : false;
		} else {
			return false;
		}
	}

	/**
	 * Check whether the membership exist or not
	 *
	 * @param mixed $membership_id Membership ID
	 * .
	 * @return bool If membership is exist then return true, otherwise return false
	 *
	 * @since 1.0.0
	 */
	public function is_membership_exist( $membership_id ) {
		if ( ! $membership_id ) {
			return false;
		}

		$membership = get_post( $membership_id );

		// Check if the post exists and the post type is OHMYLMS_COURSE_CPT.
		if ( $membership && OHMYLMS_MEMBERSHIP_CPT === get_post_type( $membership_id ) ) {
			return true;  // Post exists and is of the correct type.
		} else {
			return false; // Either post doesn't exist or the post type doesn't match.
		}
	}
}
