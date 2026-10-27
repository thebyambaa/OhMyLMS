<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * School-scoped report reads for the hub.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;

defined( 'ABSPATH' ) || exit;

/** Request IDs select candidates; current school relationships authorize every read. */
final class SchoolReports {

	/**
	 * Resolve a class belonging to the administered school, including archived classes.
	 *
	 * @param int $school_id School ID.
	 * @param int $class_id Class ID.
	 * @return array
	 */
	public static function classroom( $school_id, $class_id ) {
		Access::require_access( SchoolWorkspace::available() && Access::school( $school_id ) );
		$class = Service::row( 'classes', absint( $class_id ) );
		Access::require_access( $class && (int) $class['school_id'] === (int) $school_id );
		return $class;
	}

	/**
	 * One page of class completion counts for current school and class students.
	 *
	 * @param int $school_id School ID.
	 * @param int $page Page number.
	 * @return array One extra row indicates another page.
	 */
	public static function overview( $school_id, $page = 1 ) {
		Access::require_access( SchoolWorkspace::available() && Access::school( $school_id ) );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh relationship-scoped report; never cache private roster data.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.id,c.name,c.status,COUNT(r.id) AS assigned,
				SUM(CASE WHEN r.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed,
				SUM(CASE WHEN r.id IS NOT NULL AND r.completed_at IS NULL AND a.due_at IS NOT NULL AND a.due_at<%s THEN 1 ELSE 0 END) AS overdue
				FROM %i c LEFT JOIN %i a ON a.class_id=c.id AND a.school_id=c.school_id AND a.status='active'
				LEFT JOIN %i r ON r.assignment_id=a.id
				AND EXISTS (SELECT 1 FROM %i sm WHERE sm.school_id=c.school_id AND sm.user_id=r.student_user_id AND sm.role='student' AND sm.status='active')
				AND EXISTS (SELECT 1 FROM %i cm WHERE cm.class_id=c.id AND cm.user_id=r.student_user_id AND cm.role='student' AND cm.status='active')
				WHERE c.school_id=%d GROUP BY c.id,c.name,c.status ORDER BY c.name,c.id LIMIT 21 OFFSET %d",
				Service::now(),
				Schema::table( 'classes' ),
				Schema::table( 'learning_assignments' ),
				Schema::table( 'assignment_recipients' ),
				Schema::table( 'school_memberships' ),
				Schema::table( 'class_memberships' ),
				$school_id,
				( min( 10000, max( 1, absint( $page ) ) ) - 1 ) * 20
			),
			ARRAY_A
		);
	}

	/**
	 * Assigned work for a class in this school, with service-level roster checks.
	 *
	 * @param int $school_id School ID.
	 * @param int $class_id Class ID.
	 * @param int $page Page number.
	 * @return array
	 */
	public static function work( $school_id, $class_id, $page = 1 ) {
		self::classroom( $school_id, $class_id );
		return Service::assignments( $class_id, 0, 0, $page );
	}

	/**
	 * Reviewable submissions. Archived classes retain reports but cannot be graded.
	 *
	 * @param int $school_id School ID.
	 * @param int $class_id Class ID.
	 * @param int $page Page number.
	 * @return array
	 */
	public static function submissions( $school_id, $class_id, $page = 1 ) {
		$class = self::classroom( $school_id, $class_id );
		$more  = false;
		$rows  = 'active' === $class['status'] ? Service::submissions( $class_id, $page, $more ) : array();
		return array(
			'rows' => $rows,
			'more' => $more,
		);
	}

	/**
	 * Stored skill summaries for one page of the current roster, without rebuilding evidence.
	 *
	 * @param int $school_id School ID.
	 * @param int $class_id Class ID.
	 * @param int $page Roster page number.
	 * @return array
	 */
	public static function skills( $school_id, $class_id, $page = 1 ) {
		self::classroom( $school_id, $class_id );
		if ( ! \OhMyLMS\Assessment\Schema::ready() || ! \OhMyLMS\Practice\Frontend::enabled() ) {
			return array();
		}
		$students = Service::roster( $school_id, $class_id, $page );
		foreach ( $students as &$student ) {
			$student['skills'] = 'student' === $student['role'] ? \OhMyLMS\Skills\Evidence::summary( (int) $student['user_id'] ) : array();
		}
		unset( $student );
		return $students;
	}

	/**
	 * Unit-grouped skill matrix for the class's current students, optionally narrowed to a course and unit.
	 *
	 * @param int $school_id School ID.
	 * @param int $class_id Class ID.
	 * @param int $page Roster page number.
	 * @param int $course Course ID the class is enrolled in, or 0 for all of them.
	 * @param int $chapter Unit ID, or 0 for all units.
	 * @return array Matrix with a 'courses' list; empty when skills are off.
	 */
	public static function matrix( $school_id, $class_id, $page = 1, $course = 0, $chapter = 0 ) {
		self::classroom( $school_id, $class_id );
		if ( ! \OhMyLMS\Assessment\Schema::ready() || ! \OhMyLMS\Practice\Frontend::enabled() ) {
			return array();
		}
		$ids = array();
		foreach ( Service::roster( $school_id, $class_id, $page ) as $member ) {
			if ( 'student' === $member['role'] ) {
				$ids[] = (int) $member['user_id'];
			}
		}
		$all     = \OhMyLMS\Skills\ReportData::enrolled_courses( $ids );
		$courses = in_array( (int) $course, $all, true ) ? array( (int) $course ) : $all;
		$data    = \OhMyLMS\Skills\ReportData::matrix( $ids, $courses, $chapter );
		if ( is_wp_error( $data ) ) {
			$data = \OhMyLMS\Skills\ReportData::matrix( $ids, $courses );
		}
		$data['courses'] = array_map(
			static function ( $id ) {
				return array(
					'id'    => $id,
					'title' => wp_strip_all_tags( get_the_title( $id ) ),
				);
			},
			$all
		);
		return $data;
	}
}
