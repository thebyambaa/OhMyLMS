<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Read-only views of the Account Hub tables the dashboard scopes need.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Schools\Gradebook;
use OhMyLMS\Schools\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Schools, classes, rosters and class-course links (docs/api/account-hub.md data model), read-only.
 *
 * The rules match `Schools\Access`: only active schools and classes count; a school administrator is an
 * active `school_admin` member; a teacher is an active `teacher` member of the class and, for a school
 * class, an active `teacher` member of that school (an independent class needs the teacher role or a
 * school teacher membership). The class roster is `Schools\Gradebook::roster()`.
 */
final class Directory {

	/**
	 * Run a prepared query returning rows.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return array[]
	 */
	private static function rows( $sql, array $args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Run a prepared query returning one column of ids.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return int[] Sorted, unique ids.
	 */
	private static function ids( $sql, array $args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders.
		$ids = array_values( array_unique( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) ) ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Active schools, by name.
	 *
	 * @return array[] Rows with id and name.
	 */
	public static function all_schools() {
		return self::rows( "SELECT id, name FROM %i WHERE status = 'active' ORDER BY name, id", array( Schema::table( 'schools' ) ) );
	}

	/**
	 * Schools a user administers (active membership, active school), by name.
	 *
	 * @param int $user_id User.
	 * @return array[] Rows with id and name.
	 */
	public static function administered_schools( $user_id ) {
		return self::rows(
			"SELECT s.id, s.name FROM %i s INNER JOIN %i m ON m.school_id = s.id
			WHERE m.user_id = %d AND m.role = 'school_admin' AND m.status = 'active' AND s.status = 'active'
			GROUP BY s.id, s.name ORDER BY s.name, s.id",
			array( Schema::table( 'schools' ), Schema::table( 'school_memberships' ), (int) $user_id )
		);
	}

	/**
	 * Active classes of active schools, plus independent classes when asked, by school then class name.
	 *
	 * @param int[]|null $school_ids  Schools; null for every school.
	 * @param bool       $independent Include classes without a school.
	 * @return array[] Rows with id, name, school_id and school_name.
	 */
	public static function classes( $school_ids, $independent ) {
		$args  = array( Schema::table( 'classes' ), Schema::table( 'schools' ) );
		$where = array();
		if ( null === $school_ids ) {
			$where[] = "s.status = 'active'";
		} elseif ( $school_ids ) {
			$where[] = "s.status = 'active' AND " . Scope::in_condition( 'c.school_id', $school_ids, $args );
		}
		if ( $independent ) {
			$where[] = 'c.school_id = 0';
		}
		if ( ! $where ) {
			return array();
		}
		return self::rows(
			"SELECT c.id, c.name, c.school_id, COALESCE( s.name, '' ) AS school_name FROM %i c LEFT JOIN %i s ON s.id = c.school_id
			WHERE c.status = 'active' AND ( ( " . implode( ' ) OR ( ', $where ) . " ) )
			ORDER BY c.school_id = 0, s.name, c.name, c.id",
			$args
		);
	}

	/**
	 * Active classes a user teaches, by school then class name.
	 *
	 * @param int $user_id User.
	 * @return array[] Rows with id, name, school_id and school_name.
	 */
	public static function taught_classes( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return array();
		}
		$args = array(
			Schema::table( 'classes' ),
			Schema::table( 'class_memberships' ),
			Schema::table( 'schools' ),
			(int) $user_id,
			Schema::table( 'school_memberships' ),
			(int) $user_id,
		);
		$rows = self::rows(
			"SELECT c.id, c.name, c.school_id, COALESCE( s.name, '' ) AS school_name FROM %i c
			INNER JOIN %i cm ON cm.class_id = c.id
			LEFT JOIN %i s ON s.id = c.school_id
			WHERE cm.user_id = %d AND cm.role = 'teacher' AND cm.status = 'active' AND c.status = 'active'
			AND ( c.school_id = 0 OR ( s.status = 'active' AND EXISTS (
				SELECT 1 FROM %i sm WHERE sm.school_id = c.school_id AND sm.user_id = %d AND sm.role = 'teacher' AND sm.status = 'active'
			) ) )
			GROUP BY c.id, c.name, c.school_id, s.name
			ORDER BY c.school_id = 0, s.name, c.name, c.id",
			$args
		);
		$teacher = in_array( 'ohmylms_teacher', (array) $user->roles, true ) || self::teaches_in_a_school( $user_id );
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $teacher ) {
					return (int) $row['school_id'] > 0 || $teacher;
				}
			)
		);
	}

	/**
	 * Whether a user is an active teacher of an active school.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	private static function teaches_in_a_school( $user_id ) {
		$rows = self::rows(
			"SELECT m.id FROM %i m INNER JOIN %i s ON s.id = m.school_id WHERE m.user_id = %d AND m.role = 'teacher' AND m.status = 'active' AND s.status = 'active' LIMIT 1",
			array( Schema::table( 'school_memberships' ), Schema::table( 'schools' ), (int) $user_id )
		);
		return (bool) $rows;
	}

	/**
	 * Active student members of a school.
	 *
	 * @param int $school_id School.
	 * @return int[]
	 */
	public static function school_learners( $school_id ) {
		global $wpdb;
		return self::ids(
			"SELECT m.user_id FROM %i m INNER JOIN %i u ON u.ID = m.user_id WHERE m.school_id = %d AND m.role = 'student' AND m.status = 'active'",
			array( Schema::table( 'school_memberships' ), $wpdb->users, (int) $school_id )
		);
	}

	/**
	 * Students of a class (its roster; empty when the class or its school is no longer active).
	 *
	 * @param int $class_id Class.
	 * @return int[]
	 */
	public static function class_learners( $class_id ) {
		try {
			$ids = array_values( array_unique( array_map( 'intval', Gradebook::roster( (int) $class_id ) ) ) );
		} catch ( \RuntimeException $exception ) {
			return array();
		}
		sort( $ids );
		return $ids;
	}

	/**
	 * Courses linked to a class.
	 *
	 * @param int $class_id Class.
	 * @return int[]
	 */
	public static function class_linked_courses( $class_id ) {
		return self::ids( 'SELECT course_id FROM %i WHERE class_id = %d', array( Schema::table( 'course_classes' ), (int) $class_id ) );
	}

	/**
	 * Courses linked to the active classes of a school.
	 *
	 * @param int $school_id School.
	 * @return int[]
	 */
	public static function school_linked_courses( $school_id ) {
		return self::ids(
			"SELECT l.course_id FROM %i l INNER JOIN %i c ON c.id = l.class_id WHERE c.school_id = %d AND c.status = 'active'",
			array( Schema::table( 'course_classes' ), Schema::table( 'classes' ), (int) $school_id )
		);
	}

	/**
	 * The ids that are courses outside the trash.
	 *
	 * @param int[] $ids Candidate ids.
	 * @return int[]
	 */
	public static function existing_courses( array $ids ) {
		global $wpdb;
		if ( ! $ids ) {
			return array();
		}
		$args = array( $wpdb->posts, OHMYLMS_COURSE_CPT );
		$in   = Scope::in_condition( 'ID', $ids, $args );
		return self::ids( "SELECT ID FROM %i WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' ) AND $in", $args );
	}
}
