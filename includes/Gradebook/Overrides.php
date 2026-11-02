<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Manual cell changes: override a score, excuse, revert; with history and stale-write protection.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use OhMyLMS\Schools\Schema as SchoolSchema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A cell row (`gradebook_cells`) holds the current manual state (`score`, `excused`, or `none` after a
 * revert) and a version raised by every change. A change names the version it read
 * (`expected_version`, 0 when the cell never had a row) and answers 409 `ohmylms_gradebook_stale` with the
 * current cell when it differs; the check runs under a row lock. Every change writes one history row
 * (who, when, old and new state and score, the computed score at the time, the reason). Score overrides
 * are mirrored to the legacy `gradebook_overrides` table so the older class gradebook shows them too.
 */
final class Overrides {

	const ACTIONS    = array( 'override', 'excuse', 'revert' );
	const MAX_REASON = 500;

	/**
	 * Apply a change.
	 *
	 * @param int   $course_id  Course.
	 * @param int   $content_id Item.
	 * @param int   $user_id    Learner.
	 * @param array $change     `action` (override|excuse|revert), `score` (override), `reason` (required to
	 *                          override or excuse), `expected_version`.
	 * @param int   $actor      Acting user.
	 * @return array|WP_Error The cell detail after the change.
	 */
	public static function apply( $course_id, $content_id, $user_id, array $change, $actor ) {
		global $wpdb;
		if ( ! Schema::ensure() ) {
			return self::storage();
		}
		$action = (string) ( $change['action'] ?? '' );
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			return new WP_Error( 'rest_invalid_param', __( 'Choose override, excuse or revert.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$context = self::context( $course_id, $content_id, $user_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$item     = $context['item'];
		$computed = $context['cell']['computed'];
		if ( 'revert' !== $action && GradeMath::NOT_ASSIGNED === $computed['state'] ) {
			return new WP_Error( 'ohmylms_gradebook_not_assigned', __( 'This item is not assigned to this learner, so it cannot be graded.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		$reason = trim( sanitize_textarea_field( (string) ( $change['reason'] ?? '' ) ) );
		if ( mb_strlen( $reason ) > self::MAX_REASON ) {
			/* translators: %d: most characters. */
			return self::invalid( 'reason', sprintf( __( 'Keep the reason under %d characters.', 'ohmylms' ), self::MAX_REASON ) );
		}
		if ( 'revert' !== $action && '' === $reason ) {
			return self::invalid( 'reason', __( 'Give a reason for the change.', 'ohmylms' ) );
		}
		$max   = null !== $computed['max'] && $computed['max'] > 0 ? (float) $computed['max'] : (float) $item['max'];
		$score = null;
		if ( 'override' === $action ) {
			$value = $change['score'] ?? null;
			if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 || $max <= 0 || (float) $value > $max ) {
				/* translators: %s: the maximum score. */
				return self::invalid( 'score', sprintf( __( 'Enter a score from 0 to %s.', 'ohmylms' ), (string) round( $max, 2 ) ) );
			}
			$score = round( (float) $value, 4 );
		}
		if ( 'revert' === $action && '' === $reason ) {
			$reason = __( 'Reverted to the computed score.', 'ohmylms' );
		}
		$kind     = 'override' === $action ? GradeMath::SCORE : ( 'excuse' === $action ? GradeMath::EXCUSED : 'none' );
		$expected = (int) ( $change['expected_version'] ?? -1 );
		$table    = Schema::table( 'gradebook_cells' );
		$now      = gmdate( 'Y-m-d H:i:s' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Locked read-modify-write; caches are versioned.
		$wpdb->query( 'START TRANSACTION' );
		$row     = $wpdb->get_row( $wpdb->prepare( 'SELECT id, kind, score, version FROM %i WHERE course_id = %d AND content_id = %d AND user_id = %d FOR UPDATE', $table, (int) $course_id, (int) $content_id, (int) $user_id ), ARRAY_A );
		$version = $row ? (int) $row['version'] : 0;
		if ( $expected !== $version ) {
			$wpdb->query( 'ROLLBACK' );
			return self::stale( $course_id, $content_id, $user_id );
		}
		$old_kind  = $row ? (string) $row['kind'] : 'none';
		$old_score = $row && null !== $row['score'] ? (float) $row['score'] : null;
		if ( 'revert' === $action && 'none' === $old_kind ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'ohmylms_gradebook_nothing_to_revert', __( 'This cell has no manual change to revert.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$values = array(
			'kind'       => $kind,
			'score'      => $score,
			'max_score'  => GradeMath::SCORE === $kind ? $max : null,
			'reason'     => $reason,
			'version'    => $version + 1,
			'updated_by' => (int) $actor,
			'updated_at' => $now,
		);
		if ( $row ) {
			$ok      = false !== $wpdb->update( $table, $values, array( 'id' => (int) $row['id'] ) );
			$cell_id = (int) $row['id'];
		} else {
			$ok      = false !== $wpdb->insert(
				$table,
				array(
					'course_id'  => (int) $course_id,
					'content_id' => (int) $content_id,
					'user_id'    => (int) $user_id,
				) + $values
			);
			$cell_id = (int) $wpdb->insert_id;
		}
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			// A concurrent first write took the unique slot.
			return self::stale( $course_id, $content_id, $user_id );
		}
		$logged = $wpdb->insert(
			Schema::table( 'gradebook_cell_history' ),
			array(
				'cell_id'        => $cell_id,
				'course_id'      => (int) $course_id,
				'content_id'     => (int) $content_id,
				'user_id'        => (int) $user_id,
				'action'         => $action,
				'old_kind'       => $old_kind,
				'old_score'      => $old_score,
				'new_kind'       => $kind,
				'new_score'      => $score,
				'max_score'      => $max > 0 ? $max : null,
				'computed_score' => $computed['score'],
				'reason'         => $reason,
				'actor_id'       => (int) $actor,
				'created_at'     => $now,
			)
		);
		if ( false === $logged || ! self::mirror( $course_id, $content_id, $user_id, $kind, $score, $max, $reason, $actor, $now ) ) {
			$wpdb->query( 'ROLLBACK' );
			return self::storage();
		}
		$wpdb->query( 'COMMIT' );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		Version::bump();

		/**
		 * Fires after a gradebook cell was overridden, excused or reverted.
		 *
		 * @param int    $course_id  Course.
		 * @param int    $content_id Item.
		 * @param int    $user_id    Learner.
		 * @param string $action     override, excuse or revert.
		 * @param int    $actor      Acting user.
		 */
		do_action( 'ohmylms_gradebook_cell_changed', (int) $course_id, (int) $content_id, (int) $user_id, $action, (int) $actor );
		return self::detail( $course_id, $content_id, $user_id );
	}

	/**
	 * Keep the legacy override table in step: a score override is written there, anything else removes it.
	 *
	 * @param int        $course_id  Course.
	 * @param int        $content_id Item.
	 * @param int        $user_id    Learner.
	 * @param string     $kind       New kind.
	 * @param float|null $score      Score.
	 * @param float      $max        Maximum.
	 * @param string     $reason     Reason (the legacy note).
	 * @param int        $actor      Acting user.
	 * @param string     $now        UTC time.
	 * @return bool
	 */
	private static function mirror( $course_id, $content_id, $user_id, $kind, $score, $max, $reason, $actor, $now ) {
		global $wpdb;
		$table = SchoolSchema::table( 'gradebook_overrides' );
		$key   = array(
			'course_id'  => (int) $course_id,
			'content_id' => (int) $content_id,
			'user_id'    => (int) $user_id,
		);
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Legacy mirror inside the caller's transaction.
		if ( GradeMath::SCORE !== $kind ) {
			return false !== $wpdb->delete( $table, $key );
		}
		return false !== $wpdb->replace(
			$table,
			$key + array(
				'score'      => $score,
				'max_score'  => $max,
				'note'       => $reason,
				'updated_by' => (int) $actor,
				'updated_at' => $now,
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Item, enrolment and the learner's computed cell, or an error.
	 *
	 * @param int $course_id  Course.
	 * @param int $content_id Item.
	 * @param int $user_id    Learner.
	 * @return array|WP_Error `item`, `cell` (GradeMath::cell shape), `row` (compact row).
	 */
	public static function context( $course_id, $content_id, $user_id ) {
		$grid = CourseGrid::compute( $course_id, array( (int) $user_id ), null );
		$row  = CourseGrid::row( $grid, $user_id );
		if ( ! $row ) {
			return new WP_Error( 'ohmylms_gradebook_not_enrolled', __( 'This learner is not enrolled in the course.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$item = null;
		foreach ( $grid['items'] as $candidate ) {
			if ( (int) $content_id === $candidate['id'] ) {
				$item = $candidate;
			}
		}
		if ( ! $item || ! isset( $row['cells'][ $item['id'] ] ) ) {
			return new WP_Error( 'ohmylms_gradebook_item_not_found', __( 'This item is not a graded item of the course.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return array(
			'item' => $item,
			'cell' => CourseGrid::expand_cell( $row['cells'][ $item['id'] ] ),
			'row'  => $row,
			'grid' => $grid,
		);
	}

	/**
	 * A cell with its computed and manual values, the reason, the learner's total and the history.
	 *
	 * @param int $course_id  Course.
	 * @param int $content_id Item.
	 * @param int $user_id    Learner.
	 * @return array|WP_Error
	 */
	public static function detail( $course_id, $content_id, $user_id ) {
		global $wpdb;
		$context = self::context( $course_id, $content_id, $user_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$reason = '';
		$by     = null;
		$at     = null;
		if ( Schema::ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One row, always fresh.
			$current = $wpdb->get_row( $wpdb->prepare( 'SELECT reason, updated_by, updated_at FROM %i WHERE course_id = %d AND content_id = %d AND user_id = %d', Schema::table( 'gradebook_cells' ), (int) $course_id, (int) $content_id, (int) $user_id ), ARRAY_A );
			if ( $current && $context['cell']['overridden'] ) {
				$reason = (string) $current['reason'];
				$by     = self::person( (int) $current['updated_by'] );
				$at     = Items::iso( (string) $current['updated_at'] );
			}
		}
		$row  = $context['row'];
		$item = $context['item'];
		return array(
			'course'  => $context['grid']['course'],
			'item'    => $item,
			'learner' => array(
				'id'   => (int) $user_id,
				'name' => $row['name'],
			),
			'cell'    => $context['cell'] + array(
				'reason'     => $reason,
				'updated_by' => $by,
				'updated_at' => $at,
			),
			'total'   => array(
				'percent'          => $row['total']['p'],
				'letter'           => $row['total']['l'],
				'computed_percent' => $row['total']['cp'],
				'mode'             => $row['total']['mode'],
			),
			'history' => self::history( $course_id, $content_id, $user_id ),
		);
	}

	/**
	 * History of a cell, newest first (at most 50).
	 *
	 * @param int $course_id  Course.
	 * @param int $content_id Item.
	 * @param int $user_id    Learner.
	 * @return array[]
	 */
	public static function history( $course_id, $content_id, $user_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded audit read.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, action, old_kind, old_score, new_kind, new_score, max_score, computed_score, reason, actor_id, created_at FROM %i WHERE course_id = %d AND content_id = %d AND user_id = %d ORDER BY id DESC LIMIT 50', Schema::table( 'gradebook_cell_history' ), (int) $course_id, (int) $content_id, (int) $user_id ), ARRAY_A );
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$out[] = array(
				'id'             => (int) $row['id'],
				'action'         => (string) $row['action'],
				'old'            => array(
					'kind'  => (string) $row['old_kind'],
					'score' => null === $row['old_score'] ? null : (float) $row['old_score'],
				),
				'new'            => array(
					'kind'  => (string) $row['new_kind'],
					'score' => null === $row['new_score'] ? null : (float) $row['new_score'],
				),
				'max'            => null === $row['max_score'] ? null : (float) $row['max_score'],
				'computed_score' => null === $row['computed_score'] ? null : (float) $row['computed_score'],
				'reason'         => (string) $row['reason'],
				'by'             => self::person( (int) $row['actor_id'] ),
				'at'             => Items::iso( (string) $row['created_at'] ),
			);
		}
		return $out;
	}

	/**
	 * Remove a deleted user's cells and history (as learner); keep rows they wrote about others.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public static function forget_user( $user_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return;
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Clean-up on user deletion.
		$wpdb->delete( Schema::table( 'gradebook_cells' ), array( 'user_id' => (int) $user_id ) );
		$wpdb->delete( Schema::table( 'gradebook_cell_history' ), array( 'user_id' => (int) $user_id ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		Version::touch();
	}

	/**
	 * `{ id, name }` of a user (display name only).
	 *
	 * @param int $user_id User.
	 * @return array{id:int,name:string}|null
	 */
	private static function person( $user_id ) {
		if ( ! $user_id ) {
			return null;
		}
		$user = get_userdata( $user_id );
		return array(
			'id'   => (int) $user_id,
			'name' => $user ? (string) $user->display_name : __( 'Deleted user', 'ohmylms' ),
		);
	}

	/**
	 * 409 with the current cell.
	 *
	 * @param int $course_id  Course.
	 * @param int $content_id Item.
	 * @param int $user_id    Learner.
	 * @return WP_Error
	 */
	private static function stale( $course_id, $content_id, $user_id ) {
		$current = self::detail( $course_id, $content_id, $user_id );
		return new WP_Error(
			'ohmylms_gradebook_stale',
			__( 'Someone changed this grade since you opened it. Review the current grade and try again.', 'ohmylms' ),
			array(
				'status'  => 409,
				'current' => is_wp_error( $current ) ? null : $current,
			)
		);
	}

	/**
	 * 422 for one field.
	 *
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function invalid( $field, $message ) {
		return new WP_Error(
			'ohmylms_gradebook_invalid_cell',
			$message,
			array(
				'status' => 422,
				'params' => array( $field => $message ),
			)
		);
	}

	/**
	 * 500 storage error.
	 *
	 * @return WP_Error
	 */
	private static function storage() {
		return new WP_Error( 'ohmylms_gradebook_storage', __( 'The grade could not be saved. Please try again.', 'ohmylms' ), array( 'status' => 500 ) );
	}
}
