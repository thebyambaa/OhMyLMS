<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * A learner's own activity history: lessons completed, quiz and exam attempts, skill practice.
 *
 * Read-only. Only the current learner's rows are ever read, and each page is hydrated from at
 * most one page of source rows. Times from practice (stored in UTC) are shifted to site time so
 * every source sorts together.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Merges the three activity sources into one dated list. */
final class HistoryFeed {

	const TYPES = array( 'all', 'lesson', 'quiz', 'practice' );

	/**
	 * One page of activity, newest first, plus totals for the same period and type.
	 *
	 * @param int    $student_id Learner ID.
	 * @param int    $days Look back this many days; 0 for everything.
	 * @param string $type all, lesson, quiz or practice.
	 * @param int    $page 1-based page.
	 * @param int    $per_page Page size, 1-50.
	 * @return array{items:array[],total:int,pages:int,summary:array}
	 */
	public static function entries( $student_id, $days = 0, $type = 'all', $page = 1, $per_page = 20 ) {
		global $wpdb;
		$student_id = (int) $student_id;
		$type       = in_array( $type, self::TYPES, true ) ? $type : 'all';
		$page       = max( 1, (int) $page );
		$per_page   = max( 1, min( 50, (int) $per_page ) );
		$empty      = array(
			'items'   => array(),
			'total'   => 0,
			'pages'   => 1,
			'summary' => array(
				'activities'       => 0,
				'active_days'      => 0,
				'practice_answers' => 0,
			),
		);
		if ( ! $student_id ) {
			return $empty;
		}
		$since  = $days > 0 ? wp_date( 'Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS ) : '0000-00-00 00:00:00';
		$offset = (int) wp_timezone()->getOffset( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) );
		$union  = array();
		$args   = array();
		if ( in_array( $type, array( 'all', 'lesson' ), true ) ) {
			$union[] = 'SELECT %s AS kind, p.id AS ref, p.content_id AS item, e.course_id AS course, p.start_date AS at FROM %i p JOIN %i e ON e.id=p.enrollment_id WHERE e.user_id=%d AND p.status=%s AND p.start_date>=%s';
			array_push( $args, 'lesson', $wpdb->prefix . 'ohmylms_user_progress', $wpdb->prefix . 'ohmylms_user_enrollment', $student_id, 'completed', $since );
		}
		if ( in_array( $type, array( 'all', 'quiz' ), true ) ) {
			$union[] = "SELECT %s AS kind, a.id AS ref, a.quiz_id AS item, a.course_id AS course, a.end_date AS at FROM %i a WHERE a.student_id=%d AND a.status IN ('completed','passed','failed') AND a.end_date>%s AND a.end_date>=%s";
			array_push( $args, 'quiz', $wpdb->prefix . 'ohmylms_quiz_attempts', $student_id, '0000-00-00 00:00:00', $since );
		}
		if ( in_array( $type, array( 'all', 'practice' ), true ) && AssessmentSchema::ready() ) {
			$union[] = 'SELECT %s AS kind, s.id AS ref, s.term_id AS item, s.course_id AS course, DATE_ADD(s.completed_at, INTERVAL %d SECOND) AS at FROM %i s WHERE s.student_id=%d AND s.status=%s AND s.completed_at IS NOT NULL AND DATE_ADD(s.completed_at, INTERVAL %d SECOND)>=%s';
			array_push( $args, 'practice', $offset, AssessmentSchema::table( 'practice_sessions' ), $student_id, 'completed', $offset, $since );
		}
		if ( ! $union ) {
			return $empty;
		}
		$all = implode( ' UNION ALL ', $union );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Read-only, the learner's own rows; the union holds only placeholders and table identifiers.
		$totals  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COUNT(DISTINCT DATE(at)) AS days FROM ($all) h", $args ), ARRAY_A );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT kind, ref, item, course, at FROM ($all) h ORDER BY at DESC, ref DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		$answers = 0;
		if ( AssessmentSchema::ready() && in_array( $type, array( 'all', 'practice' ), true ) ) {
			$answers = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE student_id=%d AND source_type=%s AND superseded_by=0 AND DATE_ADD(created_at, INTERVAL %d SECOND)>=%s',
					AssessmentSchema::table( 'grade_events' ),
					$student_id,
					'practice',
					$offset,
					$since
				)
			);
		}
		// phpcs:enable
		$total = (int) ( $totals['n'] ?? 0 );
		return array(
			'items'   => self::hydrate( $rows ? $rows : array(), $student_id ),
			'total'   => $total,
			'pages'   => max( 1, (int) ceil( $total / $per_page ) ),
			'summary' => array(
				'activities'       => $total,
				'active_days'      => (int) ( $totals['days'] ?? 0 ),
				'practice_answers' => $answers,
			),
		);
	}

	/**
	 * Titles, results and course names for one page of rows.
	 *
	 * @param array[] $rows Source rows: kind, ref, item, course, at.
	 * @param int     $student_id Learner ID.
	 * @return array[]
	 */
	private static function hydrate( array $rows, $student_id ) {
		global $wpdb;
		$items = array();
		foreach ( $rows as $row ) {
			$item = array(
				'kind'   => $row['kind'],
				'name'   => '',
				'course' => (int) $row['course'] ? get_the_title( (int) $row['course'] ) : '',
				'at'     => $row['at'],
				'when'   => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['at'] ),
				'result' => '',
				'detail' => '',
				'label'  => '',
			);
			if ( 'practice' === $row['kind'] ) {
				$term          = get_term( (int) $row['item'], Taxonomy::NAME );
				$item['name']  = $term && ! is_wp_error( $term ) ? html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : __( 'Skill practice', 'ohmylms' );
				$item['label'] = __( 'Practice', 'ohmylms' );
				$stats         = AssessmentSchema::ready() ? $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS answered, COALESCE(SUM(correct),0) AS right_answers FROM %i WHERE source_type=%s AND source_id=%d AND student_id=%d AND superseded_by=0', AssessmentSchema::table( 'grade_events' ), 'practice', (int) $row['ref'], $student_id ), ARRAY_A ) : array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One learner's session, one page of rows.
				$answered      = (int) ( $stats['answered'] ?? 0 );
				/* translators: 1: correct answers, 2: answers given. */
				$item['result'] = $answered ? sprintf( __( '%1$d of %2$d correct', 'ohmylms' ), (int) $stats['right_answers'], $answered ) : '';
			} elseif ( 'quiz' === $row['kind'] ) {
				$quiz           = ohmylms_get_quiz( (int) $row['item'] );
				$item['name']   = get_the_title( (int) $row['item'] );
				$is_exam        = 'exam' === ( AssessmentSettings::get( (int) $row['item'] )['kind'] ?? 'quiz' );
				$item['label']  = $is_exam ? __( 'Exam', 'ohmylms' ) : __( 'Quiz', 'ohmylms' );
				$attempt        = $wpdb->get_row( $wpdb->prepare( 'SELECT total, status FROM %i WHERE id=%d AND student_id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts', (int) $row['ref'], $student_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One of the learner's own attempts.
				$marks          = $quiz ? (float) $quiz->get_total_marks() : 0.0;
				$released       = ! AssessmentSchema::ready() || AssessmentSettings::feedback_visible( (int) $row['ref'] );
				$item['result'] = $attempt && $marks > 0 && $released ? round( 100 * (float) $attempt['total'] / $marks ) . '%' : '';
				$item['detail'] = $attempt ? ucfirst( (string) $attempt['status'] ) : '';
			} else {
				$item['name']  = get_the_title( (int) $row['item'] );
				$item['label'] = __( 'Lesson', 'ohmylms' );
			}
			$item['name'] = '' !== $item['name'] ? $item['name'] : __( 'Removed item', 'ohmylms' );
			$items[]      = $item;
		}
		return $items;
	}
}
