<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * The latest learner events of a scope, from existing enrolment, progress, attempt and order rows.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Read model of the `activity` ("Happening now") widget. Sources, each read with one bounded query
 * (newest first, at most the limit) and merged:
 *
 * - enrolments, course completions and lesson completions ({@see Enrolments::recent_events()});
 * - finished quiz attempts (passed, failed or completed) and assignment submissions;
 * - paid orders (`ohmylms-completed`), only in the `all` scope and without the buyer.
 *
 * Learners are named by display name only (a display name that looks like an e-mail address becomes
 * the first name, or "A learner"); no e-mail, login or id leaves the server.
 */
final class ActivityFeed {

	const DEFAULT_LIMIT = 10;
	const MAX_LIMIT     = 25;

	/**
	 * Most events listed.
	 *
	 * @return int
	 */
	public static function limit() {
		/**
		 * Filters how many events the "Happening now" widget lists.
		 *
		 * @param int $limit Events (default 10, 1-25).
		 */
		return min( self::MAX_LIMIT, max( 1, (int) apply_filters( 'ohmylms_dashboard_activity_limit', self::DEFAULT_LIMIT ) ) );
	}

	/**
	 * The newest events of a scope in a window.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Oldest datetime (site time).
	 * @param string $end   Newest datetime.
	 * @param int    $limit Most events.
	 * @return array[] Events `{ id, type, at, text }`, newest first.
	 * @throws \RuntimeException On a database error.
	 */
	public static function recent( Scope $scope, $start, $end, $limit ) {
		$rows = array_merge(
			Enrolments::recent_events( $scope, $start, $end, $limit ),
			self::attempts( $scope, $start, $end, $limit ),
			$scope->includes_commerce() ? self::orders( $start, $end, $limit ) : array()
		);
		usort(
			$rows,
			static function ( $a, $b ) {
				return array( $b['at'], $a['type'], $b['id'] ) <=> array( $a['at'], $b['type'], $a['id'] );
			}
		);
		$rows   = array_slice( $rows, 0, max( 1, (int) $limit ) );
		$names  = self::names( wp_list_pluck( $rows, 'user_id' ) );
		$titles = self::titles( array_merge( wp_list_pluck( $rows, 'course_id' ), wp_list_pluck( $rows, 'content_id' ) ) );
		$money  = $scope->includes_commerce() ? ( new \OhMyLMS\Analytics\Repository() )->currency() : array();
		$events = array();
		foreach ( $rows as $row ) {
			$text = self::text( $row, $names, $titles, $money );
			if ( '' === $text ) {
				continue;
			}
			$events[] = array(
				'id'   => $row['type'] . ':' . $row['id'],
				'type' => $row['type'],
				'at'   => self::iso( $row['at'] ),
				'text' => $text,
			);
		}
		return $events;
	}

	/**
	 * Finished quiz attempts and assignment submissions.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $start Oldest datetime.
	 * @param string $end   Newest datetime.
	 * @param int    $limit Most rows per source.
	 * @return array[]
	 */
	private static function attempts( Scope $scope, $start, $end, $limit ) {
		global $wpdb;
		$statuses = implode( ',', array_fill( 0, count( Repository::FINISHED_ATTEMPT ), '%s' ) );
		$args     = array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts' ), Repository::FINISHED_ATTEMPT, array( $start, $end ) );
		$learner  = $scope->learner_condition( 'q.student_id', $args );
		$course   = $scope->course_condition( 'q.course_id', $args );
		$args[]   = (int) $limit;
		$args[]   = $wpdb->prefix . 'ohmylms_assignment_attempts';
		$args[]   = $start;
		$args[]   = $end;
		$learner2 = $scope->learner_condition( 'a.user_id', $args );
		$course2  = $scope->course_condition( 'a.course_id', $args );
		$args[]   = (int) $limit;
		$rows     = self::rows(
			"( SELECT CONCAT( 'quiz_', q.status ) AS type, q.id, q.student_id AS user_id, q.course_id, q.quiz_id AS content_id, q.end_date AS at FROM %i q
				WHERE q.status IN ( $statuses ) AND q.end_date BETWEEN %s AND %s AND $learner AND $course ORDER BY q.end_date DESC, q.id DESC LIMIT %d )
			UNION ALL
			( SELECT 'assignment_submitted' AS type, a.id, a.user_id, a.course_id, a.assignment_id AS content_id, a.start_date AS at FROM %i a
				WHERE a.start_date BETWEEN %s AND %s AND $learner2 AND $course2 ORDER BY a.start_date DESC, a.id DESC LIMIT %d )",
			$args
		);
		return array_map( array( __CLASS__, 'normalise' ), $rows );
	}

	/**
	 * Paid orders with their total.
	 *
	 * @param string $start Oldest datetime.
	 * @param string $end   Newest datetime.
	 * @param int    $limit Most rows.
	 * @return array[]
	 */
	private static function orders( $start, $end, $limit ) {
		global $wpdb;
		$rows = self::rows(
			"SELECT 'order' AS type, p.ID AS id, 0 AS user_id, 0 AS course_id, 0 AS content_id, p.post_date AS at, CAST( t.meta_value AS DECIMAL(20,4) ) AS total
			FROM %i p LEFT JOIN %i t ON t.post_id = p.ID AND t.meta_key = '_order_total'
			WHERE p.post_type = %s AND p.post_status = 'ohmylms-completed' AND p.post_date BETWEEN %s AND %s
			ORDER BY p.post_date DESC, p.ID DESC LIMIT %d",
			array( $wpdb->posts, $wpdb->postmeta, Repository::ORDER_TYPE, $start, $end, (int) $limit )
		);
		return array_map( array( __CLASS__, 'normalise' ), $rows );
	}

	/**
	 * Typed row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function normalise( array $row ) {
		return array(
			'type'       => (string) $row['type'],
			'id'         => (int) $row['id'],
			'user_id'    => (int) $row['user_id'],
			'course_id'  => (int) $row['course_id'],
			'content_id' => (int) $row['content_id'],
			'at'         => (string) $row['at'],
			'total'      => isset( $row['total'] ) ? (float) $row['total'] : 0.0,
		);
	}

	/**
	 * Sentence of an event.
	 *
	 * @param array             $row    Event row.
	 * @param array<int,string> $names  Learner names.
	 * @param array<int,string> $titles Post titles.
	 * @param array             $money  Currency (orders only).
	 * @return string '' when the content no longer exists.
	 */
	private static function text( array $row, array $names, array $titles, array $money ) {
		$who     = $names[ $row['user_id'] ] ?? __( 'A learner', 'ohmylms' );
		$course  = $titles[ $row['course_id'] ] ?? '';
		$content = $titles[ $row['content_id'] ] ?? '';
		switch ( $row['type'] ) {
			case 'order':
				/* translators: 1: order number, 2: amount. */
				return sprintf( __( 'New order #%1$s · %2$s', 'ohmylms' ), number_format_i18n( $row['id'] ), InsightRules::money( $row['total'], $money ) );
			case 'enrolled':
				/* translators: 1: learner name, 2: course title. */
				return '' === $course ? '' : sprintf( __( '%1$s enrolled in %2$s', 'ohmylms' ), $who, $course );
			case 'course_completed':
				/* translators: 1: learner name, 2: course title. */
				return '' === $course ? '' : sprintf( __( '%1$s completed %2$s', 'ohmylms' ), $who, $course );
		}
		if ( '' === $content ) {
			return '';
		}
		$in = '' === $course ? '' : ' · ' . $course;
		switch ( $row['type'] ) {
			case 'lesson_completed':
				/* translators: 1: learner name, 2: lesson title. */
				return sprintf( __( '%1$s completed the lesson %2$s', 'ohmylms' ), $who, $content ) . $in;
			case 'quiz_passed':
				/* translators: 1: learner name, 2: quiz title. */
				return sprintf( __( '%1$s passed %2$s', 'ohmylms' ), $who, $content ) . $in;
			case 'quiz_failed':
				/* translators: 1: learner name, 2: quiz title. */
				return sprintf( __( '%1$s did not pass %2$s', 'ohmylms' ), $who, $content ) . $in;
			case 'quiz_completed':
				/* translators: 1: learner name, 2: quiz title. */
				return sprintf( __( '%1$s finished %2$s', 'ohmylms' ), $who, $content ) . $in;
			case 'assignment_submitted':
				/* translators: 1: learner name, 2: assignment title. */
				return sprintf( __( '%1$s submitted %2$s', 'ohmylms' ), $who, $content ) . $in;
		}
		return '';
	}

	/**
	 * Display names of users, with one query. A name that looks like an e-mail address is replaced.
	 *
	 * @param int[] $ids User ids.
	 * @return array<int,string>
	 */
	private static function names( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$args  = array( $wpdb->users, $wpdb->usermeta );
		$in    = Scope::in_condition( 'u.ID', $ids, $args );
		$names = array();
		foreach ( self::rows( "SELECT u.ID, u.display_name, f.meta_value AS first_name FROM %i u LEFT JOIN %i f ON f.user_id = u.ID AND f.meta_key = 'first_name' WHERE $in", $args ) as $row ) {
			$name = trim( wp_strip_all_tags( (string) $row['display_name'] ) );
			if ( '' === $name || false !== strpos( $name, '@' ) ) {
				$name = trim( wp_strip_all_tags( (string) $row['first_name'] ) );
			}
			if ( '' !== $name && false === strpos( $name, '@' ) ) {
				$names[ (int) $row['ID'] ] = html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
			}
		}
		return $names;
	}

	/**
	 * Titles of posts outside the trash, with one query.
	 *
	 * @param int[] $ids Post ids.
	 * @return array<int,string>
	 */
	private static function titles( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$args   = array( $wpdb->posts );
		$in     = Scope::in_condition( 'ID', $ids, $args );
		$titles = array();
		foreach ( self::rows( "SELECT ID, post_title FROM %i WHERE post_status NOT IN ( 'trash', 'auto-draft' ) AND $in", $args ) as $row ) {
			$titles[ (int) $row['ID'] ] = html_entity_decode( wp_strip_all_tags( (string) $row['post_title'] ), ENT_QUOTES, 'UTF-8' );
		}
		return $titles;
	}

	/**
	 * ISO 8601 time with the site offset.
	 *
	 * @param string $datetime Site-time MySQL datetime.
	 * @return string
	 */
	private static function iso( $datetime ) {
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, wp_timezone() );
		return $date ? $date->format( DATE_ATOM ) : '';
	}

	/**
	 * Run a prepared query returning rows.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return array[]
	 * @throws \RuntimeException On a database error.
	 */
	private static function rows( $sql, array $args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders, id lists come from Scope::in_condition().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Dashboard query failed.' );
		}
		return is_array( $rows ) ? $rows : array();
	}
}
