<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Category weights of a course and their per-class overrides.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A course has one set of weights (`class_id` 0; none = points mode) and optionally one override per class
 * linked to it. Each row carries a version; a write names the version it read (`expected_version`, 0 for
 * none) and answers 409 `ohmylms_gradebook_stale` when it changed meanwhile.
 */
final class Weights {

	/**
	 * Weights that apply to a learner: the class override of the class that enrolled them (`via`) when it
	 * has one, else the override of their lowest-numbered class that has one, else the course's.
	 *
	 * @param array $weights Output of {@see Repository::weights()}.
	 * @param int[] $rosters The learner's classes in the course.
	 * @param int   $via     Class that enrolled the learner (0 for none).
	 * @return array{weights:array,class_id:int}
	 */
	public static function for_learner( array $weights, array $rosters, $via ) {
		$course = $weights[0]['weights'] ?? array();
		if ( $via && in_array( (int) $via, $rosters, true ) && isset( $weights[ (int) $via ] ) ) {
			return array(
				'weights'  => $weights[ (int) $via ]['weights'],
				'class_id' => (int) $via,
			);
		}
		$sorted = $rosters;
		sort( $sorted );
		foreach ( $sorted as $class_id ) {
			if ( $class_id && isset( $weights[ $class_id ] ) ) {
				return array(
					'weights'  => $weights[ $class_id ]['weights'],
					'class_id' => (int) $class_id,
				);
			}
		}
		return array(
			'weights'  => $course,
			'class_id' => 0,
		);
	}

	/**
	 * Save the weights of a course (`$class_id` 0) or a class override. An empty set clears them (points
	 * mode for the course; the course's weights again for a class).
	 *
	 * @param int   $course_id        Course.
	 * @param int   $class_id         Class (0 for the course).
	 * @param mixed $weights          Category => percentage.
	 * @param int   $expected_version Version the client read (0 when there was no row).
	 * @param int   $actor            Acting user.
	 * @return array|WP_Error `{ weights, version }`.
	 */
	public static function save( $course_id, $class_id, $weights, $expected_version, $actor ) {
		global $wpdb;
		if ( ! Schema::ensure() ) {
			return new WP_Error( 'ohmylms_gradebook_storage', __( 'The gradebook could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$clean = GradeMath::validate_weights( $weights );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		if ( $class_id && ! in_array( (int) $class_id, Repository::linked_classes( $course_id ), true ) ) {
			return new WP_Error( 'ohmylms_gradebook_class_not_linked', __( 'This class is not linked to the course.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		$table = Schema::table( 'gradebook_weights' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Locked read-modify-write; caches are versioned.
		$wpdb->query( 'START TRANSACTION' );
		$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT id, version FROM %i WHERE course_id = %d AND class_id = %d FOR UPDATE', $table, (int) $course_id, (int) $class_id ), ARRAY_A );
		$current = $row ? (int) $row['version'] : 0;
		if ( (int) $expected_version !== $current ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error(
				'ohmylms_gradebook_stale',
				__( 'Someone changed these weights since you opened them. Reload to see the current weights.', 'ohmylms' ),
				array(
					'status'  => 409,
					'current' => self::current( $course_id, $class_id ),
				)
			);
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( ! $clean ) {
			$ok = $row ? false !== $wpdb->delete( $table, array( 'id' => (int) $row['id'] ) ) : true;
		} elseif ( $row ) {
			$ok = false !== $wpdb->update(
				$table,
				array(
					'weights'    => wp_json_encode( $clean ),
					'version'    => $current + 1,
					'updated_by' => (int) $actor,
					'updated_at' => $now,
				),
				array( 'id' => (int) $row['id'] )
			);
		} else {
			$ok = false !== $wpdb->insert(
				$table,
				array(
					'course_id'  => (int) $course_id,
					'class_id'   => (int) $class_id,
					'weights'    => wp_json_encode( $clean ),
					'version'    => 1,
					'updated_by' => (int) $actor,
					'updated_at' => $now,
				)
			);
		}
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'ohmylms_gradebook_stale', __( 'Someone changed these weights since you opened them. Reload to see the current weights.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$wpdb->query( 'COMMIT' );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		Version::bump();

		/**
		 * Fires after course or class weights changed.
		 *
		 * @param int   $course_id Course.
		 * @param int   $class_id  Class (0 for the course).
		 * @param array $weights   New weights (empty when cleared).
		 * @param int   $actor     Acting user.
		 */
		do_action( 'ohmylms_gradebook_weights_saved', (int) $course_id, (int) $class_id, $clean, (int) $actor );
		return self::current( $course_id, $class_id );
	}

	/**
	 * Current weights and version of a course or class row.
	 *
	 * @param int $course_id Course.
	 * @param int $class_id  Class (0 for the course).
	 * @return array{class_id:int,weights:array,version:int}
	 */
	public static function current( $course_id, $class_id ) {
		$rows = Repository::weights( $course_id );
		return array(
			'class_id' => (int) $class_id,
			'weights'  => (object) ( $rows[ (int) $class_id ]['weights'] ?? array() ),
			'version'  => (int) ( $rows[ (int) $class_id ]['version'] ?? 0 ),
		);
	}
}
