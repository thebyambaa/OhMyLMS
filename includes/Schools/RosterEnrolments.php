<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course enrolments a class roster granted, and the two changes the school workflows make to them.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

use OhMyLMS\Student\EnrolmentSchema;
use OhMyLMS\Student\Enrollments;

defined( 'ABSPATH' ) || exit;

/**
 * Roster enrolments are the `ohmylms_enrolments` rows with source `roster` and a `class_id` (written by the
 * class sync, see docs/api/enrolments.md). Reads use that table; every write goes through
 * {@see Enrollments::update()}, so the legacy row and its projection change together and the source stays
 * `roster`. Completed enrolments are learning records and are never changed here.
 */
final class RosterEnrolments {

	/**
	 * Active roster enrolments of classes, optionally only of some students.
	 *
	 * @param int[]      $class_ids Classes.
	 * @param int[]|null $user_ids  Only these students; null for everyone.
	 * @return array[] Each: legacy_id, user_id, course_id, class_id.
	 */
	public static function active( array $class_ids, $user_ids = null ) {
		global $wpdb;
		$class_ids = array_values( array_unique( array_filter( array_map( 'intval', $class_ids ) ) ) );
		if ( ! $class_ids || ( is_array( $user_ids ) && ! $user_ids ) || ! EnrolmentSchema::exists() ) {
			return array();
		}
		$args = array_merge( array( EnrolmentSchema::table() ), $class_ids );
		$sql  = "SELECT legacy_id, user_id, course_id, class_id FROM %i WHERE source = 'roster' AND status = 'active' AND legacy_id IS NOT NULL AND class_id IN (" . implode( ',', array_fill( 0, count( $class_ids ), '%d' ) ) . ')';
		if ( is_array( $user_ids ) ) {
			$user_ids = array_values( array_unique( array_map( 'intval', $user_ids ) ) );
			$sql     .= ' AND user_id IN (' . implode( ',', array_fill( 0, count( $user_ids ), '%d' ) ) . ')';
			$args     = array_merge( $args, $user_ids );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Placeholders only; fresh state for a write.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY id', $args ), ARRAY_A );

		return array_map(
			static function ( $row ) {
				return array(
					'legacy_id' => (int) $row['legacy_id'],
					'user_id'   => (int) $row['user_id'],
					'course_id' => (int) $row['course_id'],
					'class_id'  => (int) $row['class_id'],
				);
			},
			(array) $rows
		);
	}

	/**
	 * Number of active roster enrolments of classes.
	 *
	 * @param int[] $class_ids Classes.
	 * @return int
	 */
	public static function count( array $class_ids ) {
		return count( self::active( $class_ids ) );
	}

	/**
	 * Cancel roster enrolments (status `cancelled`, source kept). Accounts and progress stay.
	 *
	 * @param array[] $rows Rows from {@see self::active()}.
	 * @return int Rows cancelled.
	 * @throws \RuntimeException When a row cannot be written.
	 */
	public static function revoke( array $rows ) {
		$done = 0;
		foreach ( $rows as $row ) {
			$result = Enrollments::update(
				array( 'status' => 'cancelled' ),
				array(
					'id'     => (int) $row['legacy_id'],
					'status' => 'enrolled',
				)
			);
			if ( false === $result ) {
				throw new \RuntimeException( 'Unable to revoke a roster enrolment.', 500 );
			}
			$done += (int) $result;
		}

		return $done;
	}

	/**
	 * Re-attribute roster enrolments to another class (rollover): same row, source `roster`, new class.
	 *
	 * @param array[] $rows     Rows from {@see self::active()}.
	 * @param int     $class_id New class.
	 * @return int Rows moved.
	 * @throws \RuntimeException When a row cannot be written.
	 */
	public static function move( array $rows, $class_id ) {
		$class_id = (int) $class_id;
		foreach ( $rows as $row ) {
			$result = Enrollments::update(
				array( 'status' => 'enrolled' ),
				array( 'id' => (int) $row['legacy_id'] ),
				array(
					'source'      => 'roster',
					'source_type' => 'class',
					'source_ref'  => $class_id,
					'class_id'    => $class_id,
				)
			);
			if ( false === $result ) {
				throw new \RuntimeException( 'Unable to move a roster enrolment.', 500 );
			}
		}

		return count( $rows );
	}
}
