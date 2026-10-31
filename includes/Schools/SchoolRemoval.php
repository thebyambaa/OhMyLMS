<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Archive, restore and permanently delete a school.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Platform administrators only. Archiving is reversible: the school row becomes `archived`, so
 * `Access::school()` stops granting its staff and families access, invitations cannot be accepted and
 * nothing else changes. Deleting is permanent, needs an archived school and removes the school's rows:
 * academic years, classes, class and school memberships, student profiles, guardian links, invitations,
 * distributed work, course-class links; active roster enrolments of its classes are cancelled through
 * {@see RosterEnrolments}. User accounts, course enrolments from other sources, progress, attempts and
 * grades are never deleted; the audit log is kept.
 */
final class SchoolRemoval {

	/**
	 * Count rows of a table matching `column IN (ids)`.
	 *
	 * @param string $table  Table suffix.
	 * @param string $column Trusted column.
	 * @param int[]  $ids    Ids.
	 * @param string $extra  Trusted extra condition.
	 * @return int
	 */
	private static function count_in( $table, $column, array $ids, $extra = '' ) {
		global $wpdb;
		if ( ! $ids ) {
			return 0;
		}
		$in = implode( ',', array_map( 'intval', $ids ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer list and trusted fragments.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE {$column} IN ({$in}){$extra}", Schema::table( $table ) ) );
	}

	/**
	 * Ids of a school's classes (every status).
	 *
	 * @param int $school_id School.
	 * @return int[]
	 */
	public static function class_ids( $school_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh state.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE school_id=%d ORDER BY id', Schema::table( 'classes' ), (int) $school_id ) ) );
	}

	/**
	 * What deleting a school would remove, counted now.
	 *
	 * @param int $school_id School.
	 * @return array<string,int>
	 */
	public static function impact( $school_id ) {
		global $wpdb;
		$school_id = (int) $school_id;
		$classes   = self::class_ids( $school_id );
		$school    = array( $school_id );
		$members   = Schema::table( 'school_memberships' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh counts by role.
		$roles = (array) $wpdb->get_results( $wpdb->prepare( "SELECT role, COUNT(*) AS n FROM %i WHERE school_id=%d AND status='active' GROUP BY role", $members, $school_id ), ARRAY_A );
		$by    = array();
		foreach ( $roles as $row ) {
			$by[ (string) $row['role'] ] = (int) $row['n'];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh count.
		$managed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE meta_key=%s AND meta_value=%s', $wpdb->usermeta, '_ohmylms_managed_school', (string) $school_id ) );

		return array(
			'classes'             => count( $classes ),
			'academic_years'      => self::count_in( 'academic_years', 'school_id', $school ),
			'students'            => $by['student'] ?? 0,
			'teachers'            => $by['teacher'] ?? 0,
			'school_admins'       => $by['school_admin'] ?? 0,
			'class_memberships'   => self::count_in( 'class_memberships', 'class_id', $classes, " AND status='active'" ),
			'teacher_links'       => self::count_in( 'class_memberships', 'class_id', $classes, " AND status='active' AND role='teacher'" ),
			'guardian_links'      => self::count_in( 'guardian_links', 'school_id', $school, " AND status='active'" ),
			'invitations'         => self::count_in( 'school_invitations', 'school_id', $school, ' AND consumed_at IS NULL AND revoked_at IS NULL' ),
			'assignments'         => self::count_in( 'learning_assignments', 'school_id', $school ),
			'course_links'        => self::count_in( 'course_classes', 'class_id', $classes ),
			'roster_enrolments'   => RosterEnrolments::count( $classes ),
			'managed_accounts'    => $managed,
		);
	}

	/**
	 * Load a school for a platform administrator.
	 *
	 * @param int $school_id School.
	 * @return array
	 * @throws \RuntimeException When the caller is not a platform administrator (403) or the school is unknown (404).
	 */
	private static function school( $school_id ) {
		Access::require_access( Access::platform() );
		$row = Service::row( 'schools', (int) $school_id );
		if ( ! $row ) {
			throw new \RuntimeException( 'School not found.', 404 );
		}

		return $row;
	}

	/**
	 * Archive (reversible) or restore a school.
	 *
	 * @param int  $school_id School.
	 * @param bool $archive   True to archive, false to restore.
	 * @return array The school row after the change.
	 * @throws \RuntimeException When the school is already in that state (409).
	 */
	public static function set_archived( $school_id, $archive ) {
		$school = self::school( $school_id );
		$from   = $archive ? 'active' : 'archived';
		if ( $from !== $school['status'] ) {
			throw new \RuntimeException( $archive ? 'This school is already archived.' : 'This school is not archived.', 409 );
		}
		Transaction::run(
			static function () use ( $school, $from, $archive ) {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock, then change only from the expected state.
				$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'schools' ), $school['id'] ) );
				if ( $from !== $status ) {
					throw new \RuntimeException( 'This school was changed by someone else.', 409 );
				}
				Service::update( 'schools', array( 'status' => $archive ? 'archived' : 'active' ), array( 'id' => (int) $school['id'] ) );
				Service::audit( (int) $school['id'], $archive ? 'school_archived' : 'school_restored', (int) $school['id'] );
			}
		);
		self::flush();

		return Service::row( 'schools', (int) $school['id'] );
	}

	/**
	 * Permanently delete an archived school. Never deletes user accounts.
	 *
	 * @param int $school_id School.
	 * @return array<string,int> What was removed (same keys as {@see self::impact()}).
	 * @throws \RuntimeException When the school is still active (409).
	 */
	public static function delete( $school_id ) {
		$school = self::school( $school_id );
		if ( 'archived' !== $school['status'] ) {
			throw new \RuntimeException( 'Archive the school before deleting it.', 409 );
		}
		$removed = Transaction::run(
			static function () use ( $school ) {
				global $wpdb;
				$id = (int) $school['id'];
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock the school; a concurrent restore waits.
				$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'schools' ), $id ) );
				if ( 'archived' !== $status ) {
					throw new \RuntimeException( 'Archive the school before deleting it.', 409 );
				}
				$counts  = self::impact( $id );
				$classes = self::class_ids( $id );
				RosterEnrolments::revoke( RosterEnrolments::active( $classes ) );
				if ( $classes ) {
					$in = implode( ',', $classes );
					foreach ( array( 'class_memberships', 'course_class_students', 'course_classes' ) as $table ) {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Integer list of this school's classes.
						self::check( $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE class_id IN ({$in})", Schema::table( $table ) ) ) );
					}
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Recipients of this school's distributed work.
				self::check( $wpdb->query( $wpdb->prepare( 'DELETE r FROM %i r JOIN %i a ON a.id=r.assignment_id WHERE a.school_id=%d', Schema::table( 'assignment_recipients' ), Schema::table( 'learning_assignments' ), $id ) ) );
				foreach ( array( 'learning_assignments', 'school_invitations', 'guardian_links', 'school_student_profiles', 'school_memberships', 'classes', 'academic_years' ) as $table ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Only this school's rows.
					self::check( $wpdb->delete( Schema::table( $table ), array( 'school_id' => $id ), array( '%d' ) ) );
				}
				// Accounts stay; they are no longer managed by a school that is gone.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Accounts this school created.
				$managed = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT user_id FROM %i WHERE meta_key=%s AND meta_value=%s', $wpdb->usermeta, '_ohmylms_managed_school', (string) $id ) );
				foreach ( $managed as $user_id ) {
					delete_user_meta( (int) $user_id, '_ohmylms_managed_school', (string) $id );
				}
				self::check( $wpdb->delete( Schema::table( 'schools' ), array( 'id' => $id ), array( '%d' ) ) );
				Service::audit( 0, 'school_deleted', $id );

				return $counts;
			}
		);
		self::flush();

		return $removed;
	}

	/**
	 * Fail the transaction on a database error.
	 *
	 * @param mixed $result `$wpdb` result.
	 * @throws \RuntimeException When the write failed.
	 */
	private static function check( $result ) {
		if ( false === $result ) {
			throw new \RuntimeException( 'Unable to delete the school.', 500 );
		}
	}

	/** Drop cached dashboard figures that counted the school. */
	private static function flush() {
		if ( class_exists( \OhMyLMS\Dashboard\Cache::class ) ) {
			\OhMyLMS\Dashboard\Cache::invalidate();
		}
		if ( class_exists( \OhMyLMS\Dashboard\ScopeResolver::class ) ) {
			\OhMyLMS\Dashboard\ScopeResolver::reset();
		}
	}
}
