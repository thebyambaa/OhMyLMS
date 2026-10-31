<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The student directory: who counts as a student, paged reads and blocking.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The student directory behind `ohmylms/v1/students` (see docs/api/students.md).
 *
 * A student is a user with an enrolment (`enrolled` or `banned`), with the student role (`subscriber`,
 * `ohmylms_student` or the role returned by ohmylms_get_student_role()), or created inside a school.
 * Callers check capabilities; this class owns the queries and the blocked state.
 */
final class Directory {

	/**
	 * User meta flag set while a student is blocked.
	 */
	const BLOCKED_META = '_ohmylms_banned_student';

	/**
	 * Sort keys and the SQL expression behind each.
	 */
	const ORDERBY_COLUMNS = array(
		'name'             => 'u.display_name',
		'email'            => 'u.user_email',
		'registered'       => 'u.user_registered',
		'courses_enrolled' => 'courses_enrolled',
	);

	/**
	 * Role slugs that make a user part of the student directory.
	 *
	 * @return string[]
	 */
	private static function student_roles() {
		return array_values( array_unique( array( 'subscriber', 'ohmylms_student', ohmylms_get_student_role() ) ) );
	}

	/**
	 * Build the FROM/WHERE part shared by the page and count queries.
	 *
	 * @param array<string,mixed> $filters Filters: search, course, user_id.
	 * @return array{0:string,1:array} SQL fragment and its prepare() arguments.
	 */
	private static function directory_sql( array $filters ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$sql   = ' FROM %i u LEFT JOIN ( SELECT user_id, COUNT(DISTINCT course_id) AS courses_enrolled,'
			. " COUNT(DISTINCT CASE WHEN progress = 'completed' THEN course_id END) AS courses_completed"
			. " FROM %i WHERE status IN ('enrolled','banned') GROUP BY user_id ) agg ON agg.user_id = u.ID";
		$args  = array( $wpdb->users, $table );
		$likes = array();
		$roles = array( $wpdb->usermeta, $wpdb->get_blog_prefix() . 'capabilities' );
		foreach ( self::student_roles() as $role ) {
			$likes[] = 'm.meta_value LIKE %s';
			$roles[] = '%' . $wpdb->esc_like( '"' . $role . '";b:1;' ) . '%';
		}

		// New student accounts, and students created inside a school, belong in the directory before their first enrollment.
		$sql .= ' WHERE ( agg.user_id IS NOT NULL OR EXISTS ( SELECT 1 FROM %i m WHERE m.user_id = u.ID AND m.meta_key = %s AND ( ' . implode( ' OR ', $likes ) . ' ) ) OR EXISTS ( SELECT 1 FROM %i ms WHERE ms.user_id = u.ID AND ms.meta_key = %s ) )';
		$args = array_merge( $args, $roles, array( $wpdb->usermeta, '_ohmylms_managed_school' ) );

		if ( ! empty( $filters['user_id'] ) ) {
			$sql   .= ' AND u.ID = %d';
			$args[] = (int) $filters['user_id'];
		}

		if ( ! empty( $filters['course'] ) ) {
			$sql   .= " AND EXISTS ( SELECT 1 FROM %i ec WHERE ec.user_id = u.ID AND ec.course_id = %d AND ec.status IN ('enrolled','banned') )";
			$args[] = $table;
			$args[] = (int) $filters['course'];
		}

		if ( isset( $filters['search'] ) && '' !== $filters['search'] ) {
			$like   = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$sql   .= ' AND ( u.display_name LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s )';
			$args[] = $like;
			$args[] = $like;
			$args[] = $like;
		}

		return array( $sql, $args );
	}

	/**
	 * Read directory rows.
	 *
	 * @param array<string,mixed> $filters Filters accepted by directory_sql().
	 * @param string              $orderby Key of ORDERBY_COLUMNS.
	 * @param string              $order   ASC or DESC.
	 * @param int                 $limit   Row limit.
	 * @param int                 $offset  Row offset.
	 * @return array[] Rows with id, display_name, user_email, user_registered, courses_enrolled and courses_completed.
	 */
	public static function rows( array $filters, $orderby, $order, $limit, $offset ) {
		global $wpdb;

		list( $from, $args ) = self::directory_sql( $filters );
		$column              = self::ORDERBY_COLUMNS[ $orderby ] ?? self::ORDERBY_COLUMNS['name'];
		$direction           = 'DESC' === $order ? 'DESC' : 'ASC';
		$sql                 = 'SELECT u.ID AS id, u.display_name, u.user_email, u.user_registered,'
			. ' COALESCE(agg.courses_enrolled, 0) AS courses_enrolled, COALESCE(agg.courses_completed, 0) AS courses_completed'
			. $from
			. " ORDER BY {$column} {$direction}, u.ID ASC LIMIT %d OFFSET %d";
		$args[]              = (int) $limit;
		$args[]              = (int) $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared with %i identifiers and an allow-listed ORDER BY.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count directory rows.
	 *
	 * @param array<string,mixed> $filters Filters accepted by directory_sql().
	 * @return int
	 */
	public static function count( array $filters ) {
		global $wpdb;

		list( $from, $args ) = self::directory_sql( $filters );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Prepared with %i identifiers; the placeholders are in $from.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, $args ) );
	}

	/**
	 * Whether a user is flagged as blocked.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_blocked( $user_id ) {
		return 'yes' === get_user_meta( (int) $user_id, self::BLOCKED_META, true );
	}

	/**
	 * Block or unblock a student: their enrolments and memberships move between `enrolled` and `banned`,
	 * the flag is set or removed, then `ohmylms_student_blocked` or `ohmylms_student_unblocked` fires.
	 * The caller has checked that the user may be blocked.
	 *
	 * @param int  $user_id Student ID.
	 * @param bool $block   True to block, false to unblock.
	 * @return true|WP_Error 500 when rows could not be updated.
	 */
	public static function set_blocked( $user_id, $block ) {
		global $wpdb;

		$user_id = (int) $user_id;
		$from    = $block ? 'enrolled' : 'banned';
		$to      = $block ? 'banned' : 'enrolled';

		$tables = array( $wpdb->prefix . 'ohmylms_user_enrollment', $wpdb->prefix . 'ohmylms_user_membership' );
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Existence probe for an optional table.
			if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
				continue;
			}
			$where = array(
				'user_id' => $user_id,
				'status'  => $from,
			);
			if ( $tables[0] === $table ) {
				$result = Enrollments::update( array( 'status' => $to ), $where, null, array( '%s' ), array( '%d', '%s' ) );
			} else {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for membership rows.
				$result = $wpdb->update( $table, array( 'status' => $to ), $where, array( '%s' ), array( '%d', '%s' ) );
			}
			if ( false === $result ) {
				return new WP_Error( 'ohmylms_student_update_failed', __( 'The student could not be updated.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		}

		if ( $block ) {
			update_user_meta( $user_id, self::BLOCKED_META, 'yes' );
		} else {
			delete_user_meta( $user_id, self::BLOCKED_META );
		}

		/**
		 * Fires after a student was blocked.
		 *
		 * @param int $user_id Student (user) ID.
		 */
		do_action( $block ? 'ohmylms_student_blocked' : 'ohmylms_student_unblocked', $user_id );

		return true;
	}
}
