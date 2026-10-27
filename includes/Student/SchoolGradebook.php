<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class mapping.
/**
 * School-scoped assessment matrices and CSV exports.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Schools\Schema;
use OhMyLMS\Schools\Service;
use OhMyLMS\Schools\Gradebook;
use OhMyLMS\Schools\GradebookMath;

defined( 'ABSPATH' ) || exit;

/** Only distributed course activities and current class recipients enter this gradebook. */
final class SchoolGradebook {

	const EXPORT_ACTION = 'ohmylms_school_gradebook_export';

	/**
	 * Courses explicitly distributed to this class.
	 *
	 * @param int $school School ID.
	 * @param int $class_id Class ID.
	 * @return array
	 */
	public static function courses( $school, $class_id ) {
		SchoolReports::classroom( $school, $class_id );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current scoped course selector.
		return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT p.ID AS id,p.post_title AS title FROM %i a JOIN %i p ON p.ID=a.course_id WHERE a.school_id=%d AND a.class_id=%d AND a.status='active' AND p.post_type=%s AND p.post_status<>'trash' ORDER BY p.post_title,p.ID", Schema::table( 'learning_assignments' ), $wpdb->posts, $school, $class_id, OHMYLMS_COURSE_CPT ), ARRAY_A );
	}

	/**
	 * Build a matrix page. No platform gradebook endpoint or global enrollment read is used.
	 *
	 * @param int $school School ID.
	 * @param int $class_id Class ID.
	 * @param int $course Course ID.
	 * @param int $page Page number.
	 * @return array
	 */
	public static function read( $school, $class_id, $course, $page = 1 ) {
		$courses = self::courses( $school, $class_id );
		\OhMyLMS\Schools\Access::require_access( in_array( (int) $course, array_map( 'intval', array_column( $courses, 'id' ) ), true ) );
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Current class and school memberships, bounded to one roster page.
		$students = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID AS id,u.display_name AS name FROM %i cm JOIN %i sm ON sm.user_id=cm.user_id AND sm.school_id=%d AND sm.role='student' AND sm.status='active' JOIN %i u ON u.ID=cm.user_id WHERE cm.class_id=%d AND cm.role='student' AND cm.status='active' ORDER BY u.display_name,u.ID LIMIT 51 OFFSET %d", Schema::table( 'class_memberships' ), Schema::table( 'school_memberships' ), $school, $wpdb->users, $class_id, ( max( 1, absint( $page ) ) - 1 ) * 50 ), ARRAY_A );
		$more     = count( $students ) > 50;
		$students = array_slice( $students, 0, 50 );
		$ids      = array_map( 'intval', array_column( $students, 'id' ) );
		$items    = Gradebook::items( $course );
		// Keep assessment columns stable between pages while excluding undistributed activities.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Explicit class distribution controls which columns may be read.
		$assigned_items = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT content_id FROM %i WHERE school_id=%d AND class_id=%d AND course_id=%d AND status='active'", Schema::table( 'learning_assignments' ), $school, $class_id, $course ) ) );
		$items          = array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $assigned_items ) {
					return in_array( 0, $assigned_items, true ) || in_array( $item['id'], $assigned_items, true );
				}
			)
		);
		$distributions  = array();
		$attempts       = array();
		if ( $ids ) {
			$distribution_args = array_merge( array( Schema::table( 'learning_assignments' ), Schema::table( 'assignment_recipients' ), $school, $class_id, $course ), $ids );
			$quiz_args         = array_merge( array( Schema::table( 'quiz_attempts' ), Schema::table( 'quiz_attempts_answers' ), $course ), $ids );
			$written_args      = array_merge( array( Schema::table( 'assignment_attempts' ), $course ), $ids );
			$placeholders      = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Static sniff cannot count the dynamic variadic ID list. Only fixed %d tokens are interpolated; all recipient IDs are prepared values.
			$distributions = $wpdb->get_results( $wpdb->prepare( "SELECT r.student_user_id AS user_id,a.content_id,a.prior_completion,r.assigned_at FROM %i a JOIN %i r ON r.assignment_id=a.id WHERE a.school_id=%d AND a.class_id=%d AND a.course_id=%d AND a.status='active' AND r.student_user_id IN ($placeholders)", ...$distribution_args ), ARRAY_A );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Static sniff cannot count the dynamic variadic ID list. Only fixed %d tokens are interpolated; all student IDs are prepared values. Quiz maximum comes from frozen attempt answers, not earned points.
			$quizzes = $wpdb->get_results( $wpdb->prepare( "SELECT q.id,q.student_id AS user_id,q.quiz_id AS content_id,q.status,q.start_date,SUM(a.question_marks) AS max,COALESCE(SUM(a.achive_mark),0) AS score FROM %i q LEFT JOIN %i a ON a.quiz_attempt_id=q.id WHERE q.course_id=%d AND q.student_id IN ($placeholders) GROUP BY q.id,q.student_id,q.quiz_id,q.status,q.start_date ORDER BY q.id DESC", ...$quiz_args ), ARRAY_A );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Static sniff cannot count the dynamic variadic ID list. Only fixed %d tokens are interpolated; all student IDs are prepared values.
			$written = $wpdb->get_results( $wpdb->prepare( "SELECT id,user_id,assignment_id AS content_id,status,start_date,score FROM %i WHERE course_id=%d AND user_id IN ($placeholders) ORDER BY id DESC", ...$written_args ), ARRAY_A );
			foreach ( array_merge( $quizzes, $written ) as $attempt ) {
				$attempts[ (int) $attempt['user_id'] ][ (int) $attempt['content_id'] ][] = $attempt;
			}
		}
		foreach ( $students as &$student ) {
			$student['cells'] = array();
			foreach ( $items as $item ) {
				$scope                           = array_values(
					array_filter(
						$distributions,
						static function ( $row ) use ( $student, $item ) {
							return (int) $row['user_id'] === (int) $student['id'] && ( 0 === (int) $row['content_id'] || (int) $row['content_id'] === $item['id'] );
						}
					)
				);
				$student['cells'][ $item['id'] ] = self::cell( $item, $scope, $attempts[ (int) $student['id'] ][ $item['id'] ] ?? array() );
			}
			$student['total'] = GradebookMath::total( $student['cells'] );
		}
		unset( $student );
		return array(
			'items'    => $items,
			'students' => $students,
			'more'     => $more,
		);
	}

	/**
	 * Resolve latest graded work without letting a pending retake hide it.
	 *
	 * @param array $item Assessment column.
	 * @param array $scope Distributions to this student.
	 * @param array $attempts Attempts in descending ID order.
	 * @return array
	 */
	public static function cell( $item, $scope, $attempts ) {
		$cell = array(
			'score'  => null,
			'max'    => $item['max'],
			'status' => $scope ? 'missing' : 'not-assigned',
		);
		foreach ( $attempts as $attempt ) {
			$allowed = false;
			foreach ( $scope as $distribution ) {
				if ( $distribution['prior_completion'] || ( ! empty( $attempt['start_date'] ) && get_gmt_from_date( $attempt['start_date'] ) >= $distribution['assigned_at'] ) ) {
					$allowed = true;
					break; }
			}
			if ( ! $allowed ) {
				continue; }
			if ( in_array( $attempt['status'], array( 'completed', 'passed', 'failed' ), true ) ) {
				return array(
					'score'  => max( 0.0, (float) $attempt['score'] ),
					'max'    => isset( $attempt['max'] ) ? (float) $attempt['max'] : $item['max'],
					'status' => 'graded',
				);
			}
			$cell['status'] = 'pending';
		}
		return $cell;
	}

	/**
	 * Human-readable, translatable cell status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status( $status ) {
		$labels = array(
			'missing'      => __( 'Missing', 'ohmylms' ),
			'not-assigned' => __( 'Not assigned', 'ohmylms' ),
			'pending'      => __( 'Pending review', 'ohmylms' ),
			'graded'       => __( 'Graded', 'ohmylms' ),
		);
		return $labels[ $status ] ?? __( 'Pending review', 'ohmylms' );
	}

	/**
	 * Neutralize spreadsheet formula prefixes in untrusted text cells.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_value( $value ) {
		$value = (string) $value;
		return preg_match( '/^[\x00-\x20]*[=+@-]/', $value ) ? "'" . $value : $value;
	}

	/**
	 * Keep exported cells and totals aligned with the header chosen before streaming began.
	 *
	 * @param array $student Current authorized student and cells.
	 * @param array $items Original export columns.
	 * @return array
	 */
	public static function csv_row( array $student, array $items ) {
		$row   = array( $student['id'], $student['name'] );
		$cells = array();
		foreach ( $items as $item ) {
			$cell    = $student['cells'][ $item['id'] ] ?? array(
				'score'  => null,
				'max'    => $item['max'],
				'status' => 'not-assigned',
			);
			$cells[] = $cell;
			$row[]   = null === $cell['score'] ? self::status( $cell['status'] ) : $cell['score'] . ' / ' . $cell['max'];
		}
		$total = GradebookMath::total( $cells );
		return array_merge( $row, array( $total['score'], $total['max'], $total['percent'] ?? '' ) );
	}

	/** Download the entire scoped class/course matrix as CSV, reauthorizing each page. */
	public static function export() {
		check_admin_referer( self::EXPORT_ACTION );
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'Invalid request.', 'ohmylms' ), '', array( 'response' => 405 ) ); }
		$school   = isset( $_POST['school_id'] ) ? absint( $_POST['school_id'] ) : 0;
		$class_id = isset( $_POST['class_id'] ) ? absint( $_POST['class_id'] ) : 0;
		$course   = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
		try {
			$matrix = self::read( $school, $class_id, $course );
		} catch ( \RuntimeException $error ) {
			wp_die( esc_html__( 'This gradebook is not available.', 'ohmylms' ), '', array( 'response' => 403 ) ); }
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="school-' . $school . '-class-' . $class_id . '-course-' . $course . '.csv"' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream the authorized download, not a filesystem file.
		$output       = fopen( 'php://output', 'w' );
		$export_items = $matrix['items'];
		$headers      = array( __( 'Student ID', 'ohmylms' ), __( 'Student', 'ohmylms' ) );
		foreach ( $export_items as $item ) {
			$headers[] = $item['title']; }
		$headers = array_merge( $headers, array( __( 'Earned points', 'ohmylms' ), __( 'Possible points', 'ohmylms' ), __( 'Percent', 'ohmylms' ) ) );
		fputcsv( $output, array_map( array( __CLASS__, 'csv_value' ), $headers ), ',', '"', '' );
		$page = 1;
		do {
			foreach ( $matrix['students'] as $student ) {
				$row = self::csv_row( $student, $export_items );
				fputcsv( $output, array_map( array( __CLASS__, 'csv_value' ), $row ), ',', '"', '' );
			}
			if ( ! $matrix['more'] ) {
				break; }
			++$page;
			$matrix = self::read( $school, $class_id, $course, $page );
		} while ( true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the response stream.
		fclose( $output );
		exit;
	}
}
