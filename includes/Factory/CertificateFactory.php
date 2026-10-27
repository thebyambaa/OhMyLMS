<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class LessonFactory.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Factory;

use OhMyLMS\Data\Certificate;
/**
 * Class LessonFactory
 *
 * Factory class for creating and retrieving Lesson objects.
 *
 * @package OhMyLMS\Factory
 * @since 1.0.0
 */
class CertificateFactory {

	/**
	 * Get certificate object
	 *
	 * @param bool $certificate_id Certificate identifier.
	 * @return bool|Certificate
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function get_certificate( $certificate_id = false ) {
		$certificate_id = $this->get_certificate_id( $certificate_id );
		if ( ! $certificate_id ) {
			return false;
		}
		return new Certificate( $certificate_id );
	}


	/**
	 * Get certificate id
	 *
	 * @param mixed $certificate Certificate.
	 * @return bool|int
	 * @since 1.0.0
	 */
	private function get_certificate_id( $certificate ) {
		global $post;

		// Check if input is false and post is set.
		if ( false === $certificate && isset( $post, $post->ID ) && OHMYLMS_LESSON_CPT === get_post_type( $post->ID ) ) {
			return absint( $post->ID );
		}

		// If input is numeric, check if certificate exists.
		elseif ( is_numeric( $certificate ) ) {
			return $this->is_certificate_exist( $certificate ) ? $certificate : false;
		}

		// If input is an instance of Certificate.
		elseif ( $certificate instanceof Certificate ) {
			$id = $certificate->get_id();
			return $this->is_certificate_exist( $id ) ? $id : false;
		}

		// If input contains a valid ID property.
		elseif ( ! empty( $certificate->ID ) ) {
			return $this->is_certificate_exist( $certificate->ID ) ? $certificate->ID : false;
		}

		// Otherwise, return false.
		else {
			return false;
		}
	}


	/**
	 * Checks whether a certificate with the given ID exists.
	 *
	 * This method verifies that the certificate exists in the database and is of
	 * the correct post type (`OHMYLMS_LESSON_CPT`).
	 *
	 * @param int $certificate_id The ID of the certificate to check.
	 * @return bool Returns true if the certificate exists, otherwise false.
	 * @since 1.0.0
	 */
	public function is_certificate_exist( $certificate_id ) {
		if ( ! $certificate_id ) {
			return false;
		}

		$certificate = get_post( $certificate_id );

		// Check if the post exists and the post type matches.
		if ( $certificate && OHMYLMS_CERTIFICATE_CPT === get_post_type( $certificate_id ) ) {
			return true;
		} else {
			return false;
		}
	}
}
