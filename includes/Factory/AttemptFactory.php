<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Factory;

use OhMyLMS\Data\Attempt;

/**
 * Plugin attempt factory implementation.
 */
class AttemptFactory {
	/**
	 * Get attempt object
	 *
	 * @param bool $attempt_id Attempt identifier.
	 * @return bool|Attempt
	 */
	public function get_attempt( $attempt_id = false ) {
		$attempt_id = $this->get_attempt_id( $attempt_id );
		if ( ! $attempt_id ) {
			return false;
		}
		return new Attempt( $attempt_id );
	}

	/**
	 * Get attempt id
	 *
	 * @param mixed $attempt Attempt.
	 * @return bool|int
	 */
	private function get_attempt_id( $attempt ) {
		if ( is_numeric( $attempt ) ) {
			return $attempt;
		} elseif ( $attempt instanceof Attempt ) {
			return $attempt->get_id();
		} elseif ( ! empty( $attempt->ID ) ) {
			return $attempt->ID;
		} else {
			return false;
		}
	}
}
