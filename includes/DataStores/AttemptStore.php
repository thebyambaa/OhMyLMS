<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin attempt store implementation.
 */
class AttemptStore extends DataStore {
	/**
	 * Create for Attempt store.
	 *
	 * @param mixed $data Input data.
	 */
	public function create( &$data ) {}

	/**
	 * Read an attempt from the database and populate the data object.
	 *
	 * @param \OhMyLMS\Data\Attempt $data The attempt data object to populate.
	 * @return bool Returns true on success, false on failure.
	 *
	 * @since 1.0.0
	 */
	public function read( &$data ) {
		if ( ! $data instanceof \OhMyLMS\Data\Attempt ) {
			return false;
		}

		global $wpdb;
		$attempt_id = $data->get_id();
		if ( ! $attempt_id ) {
			return false;
		}

		$result = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts', $attempt_id ),
			ARRAY_A
		);

		if ( ! $result ) {
			return false;
		}
		$data->set_props( $result );
	}

	/**
	 * Update for Attempt store.
	 *
	 * @param mixed $data Input data.
	 */
	public function update( &$data ) {}
	/**
	 * Delete for Attempt store.
	 *
	 * @param mixed $data Input data.
	 * @param array $args Operation arguments.
	 */
	public function delete( &$data, $args = array() ) {}

	/**
	 * Get the student associated with an attempt.
	 *
	 * @param \OhMyLMS\Data\Attempt $attempt The attempt object.
	 * @return \OhMyLMS\Data\Student|bool Returns the Student object if found, or false if not.
	 *
	 * @since 1.0.0
	 */
	public function get_student( &$attempt ) {
		if ( ! $attempt instanceof \OhMyLMS\Data\Attempt ) {
			return false;
		}

		global $wpdb;
		$student_id = $wpdb->get_var(
			$wpdb->prepare( 'SELECT student_id FROM %i WHERE id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts', $attempt->get_id() )
		);
		if ( ! $student_id ) {
			return false;
		}
		$student = ohmylms_get_student( $student_id );
		return $student;
	}


	/**
	 * Get the total score for an attempt.
	 *
	 * @param \OhMyLMS\Data\Attempt $attempt The attempt object.
	 * @return float|bool Returns the total score as a float, or false on failure.
	 * @since 1.0.0
	 */
	public function get_total_score( &$attempt ) {
		if ( ! $attempt instanceof \OhMyLMS\Data\Attempt ) {
			return false;
		}
		global $wpdb;
		$total_score = $wpdb->get_var(
			$wpdb->prepare( 'SELECT SUM(achive_mark) FROM %i WHERE quiz_attempt_id = %d', $wpdb->prefix . 'ohmylms_quiz_attempts_answers', $attempt->get_id() )
		);
		return $total_score ? (float) $total_score : 0.0;
	}
}
