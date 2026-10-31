<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course enrolments: every write, the source it records, and the shared reads.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Owner of course enrolments (see docs/api/enrolments.md and docs/api/students.md).
 *
 * Writes: every writer of `ohmylms_user_enrollment` calls {@see self::create()}, {@see self::update()},
 * {@see self::delete()} or {@see self::resync()}. Each writes the legacy row exactly as before (same
 * columns, same `$wpdb` return value) and then its projection in `ohmylms_enrolments` with the source the
 * caller states. Legacy storage stays authoritative until every reader is switched.
 *
 * Reads: the methods below read `ohmylms_enrolments` once {@see EnrolmentMigration::complete()} says the
 * migration finished, and `ohmylms_user_enrollment` before that. Both return the same shapes; `id` is
 * always the legacy row ID, which `ohmylms_user_progress.enrollment_id` refers to.
 *
 * Callers check capabilities and the student; this class owns the rows and the
 * `ohmylms_user_progress` rows a removal leaves behind.
 */
final class Enrollments {

	/**
	 * Legacy enrolments table.
	 *
	 * @return string
	 */
	private static function table() {
		return EnrolmentMirror::legacy_table();
	}

	/**
	 * Whether reads use `ohmylms_enrolments`.
	 *
	 * @return bool
	 */
	public static function reads_new() {
		return EnrolmentMigration::complete();
	}

	/**
	 * Insert one legacy enrolment row and its projection.
	 *
	 * @param array        $row    Legacy columns (`user_id`, `course_id`, `status`, `progress`, `start_date`, ...).
	 * @param array|string $origin Source of the enrolment: `source` (purchase, self, manual, roster, seat, import),
	 *                             `source_type` (order, membership, subscription, class, seat, exam), `source_ref`,
	 *                             optional `class_id`, `expires_at` and `created_by` (defaults to the current
	 *                             user); or `detect` to infer it from the row's order and membership.
	 * @param array|null   $format Optional `$wpdb->insert()` formats.
	 * @return int|false What `$wpdb->insert()` returned; `$wpdb->insert_id` is the legacy row ID afterwards.
	 */
	public static function create( array $row, $origin, $format = null ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The enrolment owner writes its rows.
		$inserted = $wpdb->insert( self::table(), $row, $format );
		if ( $inserted ) {
			EnrolmentMirror::sync( array( (int) $wpdb->insert_id ), $origin );
		}
		return $inserted;
	}

	/**
	 * Update legacy enrolment rows and their projections.
	 *
	 * @param array             $data         Columns to set.
	 * @param array             $where        Equality conditions (null matches NULL).
	 * @param array|string|null $origin       New source of the matched rows, `detect`, or null to keep it.
	 * @param array|null        $format       Optional data formats.
	 * @param array|null        $where_format Optional condition formats.
	 * @return int|false What `$wpdb->update()` returned.
	 */
	public static function update( array $data, array $where, $origin = null, $format = null, $where_format = null ) {
		global $wpdb;
		$ids = self::ids_where( $where );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The enrolment owner writes its rows.
		$updated = $wpdb->update( self::table(), $data, $where, $format, $where_format );
		if ( false !== $updated && $ids ) {
			EnrolmentMirror::sync( $ids, $origin, EnrolmentMigration::finished() );
		}
		return $updated;
	}

	/**
	 * Delete legacy enrolment rows and their projections.
	 *
	 * @param array      $where        Equality conditions.
	 * @param array|null $where_format Optional condition formats.
	 * @return int|false What `$wpdb->delete()` returned.
	 */
	public static function delete( array $where, $where_format = null ) {
		global $wpdb;
		$ids = self::ids_where( $where );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The enrolment owner writes its rows.
		$deleted = $wpdb->delete( self::table(), $where, $where_format );
		if ( false !== $deleted && $ids ) {
			EnrolmentMirror::sync( $ids, null, false );
		}
		return $deleted;
	}

	/**
	 * Re-project the legacy rows matching conditions after a set-based SQL write that cannot be expressed
	 * as {@see self::update()} (subqueries, joins).
	 *
	 * @param array             $where  Equality conditions.
	 * @param array|string|null $origin New source, `detect`, or null to keep it.
	 * @return array<string,int> Counts by outcome.
	 */
	public static function resync( array $where, $origin = null ) {
		return EnrolmentMirror::sync( self::ids_where( $where ), $origin, EnrolmentMigration::finished() );
	}

	/**
	 * IDs of legacy rows matching equality conditions.
	 *
	 * @param array $where Column => value; null matches NULL.
	 * @return int[]
	 */
	private static function ids_where( array $where ) {
		global $wpdb;
		if ( ! $where || ! EnrolmentSchema::exists() ) {
			return array();
		}
		$clauses = array();
		$args    = array( self::table() );
		foreach ( $where as $column => $value ) {
			$column = preg_replace( '/[^a-z_]/', '', (string) $column );
			if ( null === $value ) {
				$clauses[] = "`$column` IS NULL";
				continue;
			}
			$clauses[] = "`$column` = " . ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ? '%d' : '%s' );
			$args[]    = $value;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Column names are reduced to [a-z_]; values are placeholders.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE ' . implode( ' AND ', $clauses ), $args ) ) );
	}

	/**
	 * FROM and WHERE of a student's enrolments in existing courses, with its arguments.
	 *
	 * @param int    $user_id     Student ID.
	 * @param string $course_type Course post type.
	 * @param string $progress    `all`, `running` or `completed`.
	 * @param string $search      Course title search.
	 * @param bool   $read_new         Whether to read `ohmylms_enrolments`.
	 * @return array{0:string,1:array}
	 */
	private static function filtered( $user_id, $course_type, $progress, $search, $read_new ) {
		global $wpdb;

		if ( $read_new ) {
			$from = " FROM %i e INNER JOIN %i p ON p.ID = e.course_id AND p.post_type = %s WHERE e.user_id = %d AND e.status IN ('active','completed','suspended') AND e.legacy_id IS NOT NULL";
			$args = array( EnrolmentSchema::table(), $wpdb->posts, $course_type, (int) $user_id );
			if ( 'running' === $progress ) {
				$from .= ' AND e.completed_at IS NULL';
			} elseif ( 'completed' === $progress ) {
				$from .= ' AND e.completed_at IS NOT NULL';
			}
		} else {
			$from = " FROM %i e INNER JOIN %i p ON p.ID = e.course_id AND p.post_type = %s WHERE e.user_id = %d AND e.status IN ('enrolled','banned')";
			$args = array( self::table(), $wpdb->posts, $course_type, (int) $user_id );
			if ( in_array( $progress, array( 'running', 'completed' ), true ) ) {
				$from  .= ' AND e.progress = %s';
				$args[] = $progress;
			}
		}
		if ( '' !== (string) $search ) {
			$from  .= ' AND p.post_title LIKE %s';
			$args[] = '%' . $wpdb->esc_like( (string) $search ) . '%';
		}
		return array( $from, $args );
	}

	/**
	 * Columns of a row in the legacy shape: id, course_id, status, progress, start_date, end_date.
	 *
	 * @param bool $read_new Whether to read `ohmylms_enrolments`.
	 * @return string
	 */
	private static function columns( $read_new ) {
		if ( $read_new ) {
			return 'e.legacy_id AS id, e.course_id, ' . EnrolmentMirror::legacy_status_sql( 'e' ) . ' AS status, ' . EnrolmentMirror::legacy_progress_sql( 'e' ) . " AS progress, COALESCE(e.enrolled_at,'0000-00-00 00:00:00') AS start_date, COALESCE(e.completed_at,'0000-00-00 00:00:00') AS end_date";
		}
		return 'e.id, e.course_id, e.status, e.progress, e.start_date, e.end_date';
	}

	/**
	 * Number of a student's enrolments.
	 *
	 * @param int    $user_id     Student ID.
	 * @param string $course_type Course post type.
	 * @param string $progress    `all`, `running` or `completed`.
	 * @param string $search      Course title search.
	 * @return int
	 */
	public static function count( $user_id, $course_type, $progress = 'all', $search = '' ) {
		global $wpdb;
		list( $from, $args ) = self::filtered( $user_id, $course_type, $progress, $search, self::reads_new() );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Prepared with %i identifiers; the placeholders are in $from.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*)' . $from, $args ) );
	}

	/**
	 * One page of a student's enrolments, newest first, joined with the course title.
	 *
	 * @param int    $user_id     Student ID.
	 * @param string $course_type Course post type.
	 * @param string $progress    `all`, `running` or `completed`.
	 * @param string $search      Course title search.
	 * @param int    $page        Page, from 1.
	 * @param int    $per_page    Page size.
	 * @return array[] Rows with id, course_id, status, progress, start_date, end_date and post_title.
	 */
	public static function page( $user_id, $course_type, $progress, $search, $page, $per_page ) {
		global $wpdb;
		$read_new            = self::reads_new();
		list( $from, $args ) = self::filtered( $user_id, $course_type, $progress, $search, $read_new );
		$args[]              = (int) $per_page;
		$args[]              = ( (int) $page - 1 ) * (int) $per_page;
		$order               = $read_new ? 'e.enrolled_at DESC, e.legacy_id DESC' : 'e.start_date DESC, e.id DESC';
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Prepared with %i identifiers; the other placeholders are in $from.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ' . self::columns( $read_new ) . ', p.post_title' . $from . ' ORDER BY ' . $order . ' LIMIT %d OFFSET %d', $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * The first enrolment row of a student in a course, joined with the course title.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return array|null
	 */
	public static function find( $user_id, $course_id ) {
		global $wpdb;
		if ( self::reads_new() ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed column list; values are placeholders.
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT ' . self::columns( true ) . ', p.post_title FROM %i e LEFT JOIN %i p ON p.ID = e.course_id WHERE e.user_id = %d AND e.course_id = %d AND e.legacy_id IS NOT NULL ORDER BY e.legacy_id ASC LIMIT 1', EnrolmentSchema::table(), $wpdb->posts, (int) $user_id, (int) $course_id ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh enrolment row.
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT e.id, e.course_id, e.status, e.progress, e.start_date, e.end_date, p.post_title FROM %i e LEFT JOIN %i p ON p.ID = e.course_id WHERE e.user_id = %d AND e.course_id = %d ORDER BY e.id ASC LIMIT 1', self::table(), $wpdb->posts, (int) $user_id, (int) $course_id ), ARRAY_A );
		}
		return $row ? $row : null;
	}

	/**
	 * Completed items of enrolments by kind, counted from progress rows.
	 *
	 * @param int[] $enrollment_ids Enrolment row IDs.
	 * @return array<int,array<string,int>> Enrolment ID => `lesson`|`quiz`|`assignment` => completed items.
	 */
	public static function completed_items( array $enrollment_ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $enrollment_ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Placeholders are generated from the id count.
		$done = $wpdb->get_results( $wpdb->prepare( "SELECT enrollment_id, content_type, COUNT(DISTINCT content_id) AS total FROM %i WHERE status = 'completed' AND enrollment_id IN ($placeholders) GROUP BY enrollment_id, content_type", array_merge( array( $wpdb->prefix . 'ohmylms_user_progress' ), $ids ) ), ARRAY_A );

		$completed = array();
		foreach ( is_array( $done ) ? $done : array() as $row ) {
			$kind = in_array( $row['content_type'], array( 'quiz', 'assignment' ), true ) ? $row['content_type'] : 'lesson';

			$completed[ (int) $row['enrollment_id'] ][ $kind ] = ( $completed[ (int) $row['enrollment_id'] ][ $kind ] ?? 0 ) + (int) $row['total'];
		}
		return $completed;
	}

	/**
	 * Courses a student is enrolled in and how many of them they completed.
	 *
	 * @param int $user_id Student ID.
	 * @return array{enrolled:int,completed:int}
	 */
	public static function course_counts( $user_id ) {
		global $wpdb;
		if ( self::reads_new() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregate over plugin tables; the report must be fresh.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(DISTINCT course_id) AS enrolled, COUNT(DISTINCT CASE WHEN completed_at IS NOT NULL THEN course_id END) AS completed FROM %i WHERE user_id = %d AND status IN ('active','completed','suspended')", EnrolmentSchema::table(), (int) $user_id ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregate over plugin tables; the report must be fresh.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(DISTINCT course_id) AS enrolled, COUNT(DISTINCT CASE WHEN progress = 'completed' THEN course_id END) AS completed FROM %i WHERE user_id = %d AND status IN ('enrolled','banned')", self::table(), (int) $user_id ), ARRAY_A );
		}
		return array(
			'enrolled'  => (int) ( $row['enrolled'] ?? 0 ),
			'completed' => (int) ( $row['completed'] ?? 0 ),
		);
	}

	/**
	 * Enrolment rows with legacy status `enrolled` per course.
	 *
	 * @param int[] $course_ids Course IDs.
	 * @return array<int,int> Course ID => rows (0 for none).
	 */
	public static function count_by_course( array $course_ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $course_ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		$read_new     = self::reads_new();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$where        = $read_new ? "status IN ('active','completed')" : "status = 'enrolled'";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed condition; placeholders are generated from the id count.
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT course_id, COUNT(*) AS total FROM %i WHERE {$where} AND course_id IN ({$placeholders}) GROUP BY course_id", array_merge( array( $read_new ? EnrolmentSchema::table() : self::table() ), $ids ) ) );
		$counts = array_fill_keys( $ids, 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->course_id ] = (int) $row->total;
		}
		return $counts;
	}

	/**
	 * Students enrolled (legacy `enrolled`) in a course, one per row in enrolment order, optionally paged.
	 *
	 * @param int  $course_id Course ID.
	 * @param int  $limit     Rows, 0 for all.
	 * @param int  $offset    Rows to skip.
	 * @param bool $distinct  One entry per student.
	 * @return int[]
	 */
	public static function course_user_ids( $course_id, $limit = 0, $offset = 0, $distinct = false ) {
		global $wpdb;
		$read_new = self::reads_new();
		$where    = $read_new ? "status IN ('active','completed')" : "status = 'enrolled'";
		$sql      = 'SELECT ' . ( $distinct ? 'DISTINCT ' : '' ) . "user_id FROM %i WHERE course_id = %d AND {$where}" . ( $distinct ? '' : ' ORDER BY ' . ( $read_new ? 'legacy_id' : 'id' ) );
		$args     = array( $read_new ? EnrolmentSchema::table() : self::table(), (int) $course_id );
		if ( $limit > 0 ) {
			$sql   .= ' LIMIT %d OFFSET %d';
			$args[] = (int) $limit;
			$args[] = max( 0, (int) $offset );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed SQL; values are placeholders.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $args ) ) );
	}

	/**
	 * Number of enrolment rows with legacy status `enrolled` in a course.
	 *
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function count_in_course( $course_id ) {
		return (int) ( self::count_by_course( array( $course_id ) )[ (int) $course_id ] ?? 0 );
	}

	/**
	 * Course roster for attendance: enrolled or blocked students with an existing account, by name.
	 *
	 * @param int $course_id  Course ID.
	 * @param int $content_id Content whose completion counts as attended.
	 * @param int $limit      Page size.
	 * @param int $offset     Rows to skip.
	 * @return array{total:int,rows:array[]} Rows with user_id, status (`enrolled`|`banned`), display_name, attended.
	 */
	public static function course_roster( $course_id, $content_id, $limit, $offset ) {
		global $wpdb;
		if ( self::reads_new() ) {
			$table = EnrolmentSchema::table();
			$where = "e.status IN ('active','completed','suspended') AND e.legacy_id IS NOT NULL";
			$id    = 'e.legacy_id';
			$state = EnrolmentMirror::legacy_status_sql( 'e' );
		} else {
			$table = self::table();
			$where = "e.status IN ('enrolled','banned')";
			$id    = 'e.id';
			$state = 'e.status';
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed SQL fragments; values are placeholders.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i e INNER JOIN %i u ON u.ID = e.user_id WHERE e.course_id = %d AND {$where}", $table, $wpdb->users, (int) $course_id ) );
		$rows  = $total ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.user_id, {$state} AS status, u.display_name, EXISTS ( SELECT 1 FROM %i p WHERE p.enrollment_id = {$id} AND p.content_id = %d AND p.status = 'completed' ) AS attended FROM %i e INNER JOIN %i u ON u.ID = e.user_id WHERE e.course_id = %d AND {$where} ORDER BY u.display_name ASC, e.user_id ASC LIMIT %d OFFSET %d", $wpdb->prefix . 'ohmylms_user_progress', (int) $content_id, $table, $wpdb->users, (int) $course_id, (int) $limit, max( 0, (int) $offset ) ), ARRAY_A ) : array();
		// phpcs:enable
		return array(
			'total' => $total,
			'rows'  => $rows,
		);
	}

	/**
	 * Students listed in a course (enrolled or blocked), with name and e-mail, by name.
	 *
	 * @param int $course_id Course ID.
	 * @return array[] Rows with id, name and email.
	 */
	public static function listed_students( $course_id ) {
		global $wpdb;
		$read_new = self::reads_new();
		$where    = $read_new ? "e.status IN ('active','completed','suspended')" : "e.status IN ('enrolled','banned')";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed condition; values are placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT u.ID AS id,u.display_name AS name,u.user_email AS email FROM %i e JOIN %i u ON u.ID=e.user_id WHERE e.course_id=%d AND {$where} ORDER BY u.display_name,u.ID", $read_new ? EnrolmentSchema::table() : self::table(), $wpdb->users, (int) $course_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Whether a student is listed in a course (enrolled or blocked).
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return bool
	 */
	public static function is_listed( $user_id, $course_id ) {
		global $wpdb;
		$read_new = self::reads_new();
		$where    = $read_new ? "status IN ('active','completed','suspended')" : "status IN ('enrolled','banned')";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed condition; values are placeholders.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE course_id=%d AND user_id=%d AND {$where} LIMIT 1", $read_new ? EnrolmentSchema::table() : self::table(), (int) $course_id, (int) $user_id ) );
	}

	/**
	 * Number of enrolment rows of a student in a course, whatever their status. Write-side checks read
	 * the legacy table, which stays authoritative during dual write.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return int
	 */
	public static function rows_in_course( $user_id, $course_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', self::table(), (int) $user_id, (int) $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh duplicate check.
	}

	/**
	 * Legacy status of the first enrolment row of a student in a course (write-side check), or null.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return string|null
	 */
	public static function legacy_status( $user_id, $course_id ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE user_id = %d AND course_id = %d', self::table(), (int) $user_id, (int) $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh duplicate check.
	}

	/**
	 * Enrol a student by hand: one `enrolled`/`running` row (source `manual`), marked as a plugin student,
	 * then `ohmylms_manual_student_enrollment`. The caller has checked the student, the course and blocking.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error 409 when an enrolment exists, 500 when the row could not be saved.
	 */
	public static function enroll_manually( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;
		if ( self::rows_in_course( $user_id, $course_id ) > 0 ) {
			return self::already_enrolled();
		}

		$inserted = self::create(
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array( 'source' => 'manual' ),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'ohmylms_enrollment_create_failed', __( 'The enrolment could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		// Two simultaneous requests can both pass the check above: the later row gives way.
		$row_id = (int) $wpdb->insert_id;
		if ( self::rows_in_course( $user_id, $course_id ) > 1 ) {
			self::delete( array( 'id' => $row_id ), array( '%d' ) );
			return self::already_enrolled();
		}

		update_user_meta( $user_id, '_is_ohmylms_student', 'yes' );
		update_user_meta( $user_id, '_is_ohmylms_user', 'yes' );

		/**
		 * Fires after an administrator enrolled a student by hand.
		 *
		 * @param int $user_id   Student (user) ID.
		 * @param int $course_id Course ID.
		 */
		do_action( 'ohmylms_manual_student_enrollment', $user_id, $course_id );

		return true;
	}

	/**
	 * Remove every enrolment row of a student in a course and their progress rows, then fire
	 * `ohmylms_student_unenrolled`.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error 500 when the enrolment could not be removed.
	 */
	public static function remove( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh enrolment rows.
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND course_id = %d', self::table(), $user_id, $course_id ) ) );

		$deleted = self::delete(
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
			),
			array( '%d', '%d' )
		);
		if ( false === $deleted ) {
			return new WP_Error( 'ohmylms_enrollment_delete_failed', __( 'The enrolment could not be removed.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		// Progress records of a removed enrolment would be orphans; an unfinished delete leaves only those.
		foreach ( $ids as $enrollment_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for progress rows.
			$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => $enrollment_id ), array( '%d' ) );
		}

		/**
		 * Fires after a student's enrolment was removed.
		 *
		 * @param int $user_id   Student (user) ID.
		 * @param int $course_id Course ID.
		 */
		do_action( 'ohmylms_student_unenrolled', $user_id, $course_id );

		return true;
	}

	/**
	 * The duplicate-enrolment error.
	 *
	 * @return WP_Error
	 */
	private static function already_enrolled() {
		return new WP_Error( 'ohmylms_student_already_enrolled', __( 'The student is already enrolled in this course.', 'ohmylms' ), array( 'status' => 409 ) );
	}
}
