<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course enrolments of one student: list, lookup, manual enrolment and removal.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Course enrolments of one student behind `ohmylms/v1/students/{id}/enrollments` and the student report
 * (see docs/api/students.md). Owns the `ohmylms_user_enrollment` rows it reads and the
 * `ohmylms_user_progress` rows a removal leaves behind; callers check capabilities and the student.
 */
final class Enrollments {

	/**
	 * Enrolments table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_user_enrollment';
	}

	/**
	 * FROM and WHERE of a student's enrolments in existing courses, with its arguments.
	 *
	 * @param int    $user_id     Student ID.
	 * @param string $course_type Course post type.
	 * @param string $progress    `all`, `running` or `completed`.
	 * @param string $search      Course title search.
	 * @return array{0:string,1:array}
	 */
	private static function filtered( $user_id, $course_type, $progress, $search ) {
		global $wpdb;

		$from = " FROM %i e INNER JOIN %i p ON p.ID = e.course_id AND p.post_type = %s WHERE e.user_id = %d AND e.status IN ('enrolled','banned')";
		$args = array( self::table(), $wpdb->posts, $course_type, (int) $user_id );
		if ( in_array( $progress, array( 'running', 'completed' ), true ) ) {
			$from  .= ' AND e.progress = %s';
			$args[] = $progress;
		}
		if ( '' !== (string) $search ) {
			$from  .= ' AND p.post_title LIKE %s';
			$args[] = '%' . $wpdb->esc_like( (string) $search ) . '%';
		}
		return array( $from, $args );
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
		list( $from, $args ) = self::filtered( $user_id, $course_type, $progress, $search );
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
		list( $from, $args ) = self::filtered( $user_id, $course_type, $progress, $search );
		$args[]              = (int) $per_page;
		$args[]              = ( (int) $page - 1 ) * (int) $per_page;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Prepared with %i identifiers; the other placeholders are in $from.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT e.id, e.course_id, e.status, e.progress, e.start_date, e.end_date, p.post_title' . $from . ' ORDER BY e.start_date DESC, e.id DESC LIMIT %d OFFSET %d', $args ), ARRAY_A );
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh enrolment row.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT e.id, e.course_id, e.status, e.progress, e.start_date, e.end_date, p.post_title FROM %i e LEFT JOIN %i p ON p.ID = e.course_id WHERE e.user_id = %d AND e.course_id = %d ORDER BY e.id ASC LIMIT 1', self::table(), $wpdb->posts, (int) $user_id, (int) $course_id ), ARRAY_A );
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Aggregate over plugin tables; the report must be fresh.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT course_id) AS enrolled, COUNT(DISTINCT CASE WHEN progress = 'completed' THEN course_id END) AS completed FROM %i WHERE user_id = %d AND status IN ('enrolled','banned')",
				self::table(),
				(int) $user_id
			),
			ARRAY_A
		);
		return array(
			'enrolled'  => (int) ( $row['enrolled'] ?? 0 ),
			'completed' => (int) ( $row['completed'] ?? 0 ),
		);
	}

	/**
	 * Number of enrolment rows of a student in a course, whatever their status.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return int
	 */
	private static function rows_in_course( $user_id, $course_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d AND course_id = %d', self::table(), (int) $user_id, (int) $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh duplicate check.
	}

	/**
	 * Enrol a student by hand: one `enrolled`/`running` row, marked as a plugin student, then
	 * `ohmylms_manual_student_enrollment`. The caller has checked the student, the course and blocking.
	 *
	 * @param int $user_id   Student ID.
	 * @param int $course_id Course ID.
	 * @return true|WP_Error 409 when an enrolment exists, 500 when the row could not be saved.
	 */
	public static function enroll_manually( $user_id, $course_id ) {
		global $wpdb;

		$user_id   = (int) $user_id;
		$course_id = (int) $course_id;
		$table     = self::table();
		if ( self::rows_in_course( $user_id, $course_id ) > 0 ) {
			return self::already_enrolled();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'course_id'  => $course_id,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
		if ( ! $inserted ) {
			return new WP_Error( 'ohmylms_enrollment_create_failed', __( 'The enrolment could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		// Two simultaneous requests can both pass the check above: the later row gives way.
		$row_id = (int) $wpdb->insert_id;
		if ( self::rows_in_course( $user_id, $course_id ) > 1 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
			$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );
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
		$table     = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d AND course_id = %d', $table, $user_id, $course_id ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- No API for enrolment rows.
		if ( false === $wpdb->delete(
			$table,
			array(
				'user_id'   => $user_id,
				'course_id' => $course_id,
			),
			array( '%d', '%d' )
		) ) { // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment
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
