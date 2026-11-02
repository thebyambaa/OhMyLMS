<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Per-course health figures of a scope: enrolments, completion, rating, median progress and drop-off.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Learning\CourseStructure;

defined( 'ABSPATH' ) || exit;

/**
 * The read model behind the `course-health` widget and the drop-off insight (docs/api/dashboard.md,
 * "Course health"). The scope's most enrolled courses (default 12, filter
 * `ohmylms_dashboard_course_health_limit`, at most 50) are read with a fixed number of aggregate
 * queries plus one structure read per course; nothing loads a learner.
 *
 * - completion: enrolled learners of the scope whose enrolment is completed;
 * - rating: approved course reviews (comment meta `rating` 1-5) written by the scope's learners (any
 *   author for `all`); null without reviews;
 * - median progress: per enrolment, completed content items over the course's published items (100 for
 *   a completed enrolment), the median over the enrolments;
 * - drop-off: the published content item (in course order) with the largest loss of learners against
 *   the item before (or against everyone enrolled, for the first item); learners who reached the item
 *   before stopped there. Same rule as the "stalling course" item of Needs attention.
 */
final class CourseHealthReport {

	const DEFAULT_LIMIT = 12;
	const MAX_LIMIT     = 50;

	/**
	 * Reports per scope key, for the request.
	 *
	 * @var array<string,array>
	 */
	private static $memo = array();

	/**
	 * Forget memoised reports (tests).
	 *
	 * @return void
	 */
	public static function reset() {
		self::$memo = array();
	}

	/**
	 * Most courses a report covers.
	 *
	 * @return int
	 */
	public static function limit() {
		/**
		 * Filters how many courses (most enrolled first) the course health widget covers.
		 *
		 * @param int $limit Courses (default 12, 1-50).
		 */
		return min( self::MAX_LIMIT, max( 1, (int) apply_filters( 'ohmylms_dashboard_course_health_limit', self::DEFAULT_LIMIT ) ) );
	}

	/**
	 * Health of the scope's most enrolled courses.
	 *
	 * @param Scope $scope Scope.
	 * @return array{courses:array[],total:int,limit:int}
	 * @throws \RuntimeException On a database error.
	 */
	public static function build( Scope $scope ) {
		$limit = self::limit();
		$key   = $scope->key() . '|' . $limit;
		if ( isset( self::$memo[ $key ] ) ) {
			return self::$memo[ $key ];
		}
		$found = Enrolments::courses_by_enrolment( $scope, $limit );
		$ids   = array_map( 'intval', wp_list_pluck( $found['courses'], 'course_id' ) );

		$structures = array();
		$content    = array();
		foreach ( $ids as $id ) {
			$structures[ $id ] = array_values( array_unique( array_map( 'intval', wp_list_pluck( (array) CourseStructure::native_items( $id ), 'content_id' ) ) ) );
			$content           = array_merge( $content, $structures[ $id ] );
		}
		$posts     = self::published( array_merge( $ids, $content ) );
		$done      = Enrolments::completed_items_by_course( $scope, $ids );
		$histogram = Enrolments::progress_histogram( $scope, $ids );
		$ratings   = self::ratings( $scope, $ids );

		$courses = array();
		foreach ( $found['courses'] as $row ) {
			$id    = $row['course_id'];
			$items = array_values(
				array_filter(
					$structures[ $id ],
					static function ( $item ) use ( $posts ) {
						return isset( $posts[ $item ] ) && 'publish' === $posts[ $item ]['status'];
					}
				)
			);
			$stop  = self::drop_off( $items, $done[ $id ] ?? array(), $row['enrolled'] );
			if ( null !== $stop ) {
				$stop['title'] = $posts[ $stop['content_id'] ]['title'];
				$stop['type']  = $posts[ $stop['content_id'] ]['type'];
			}
			$courses[] = array(
				'id'              => $id,
				'title'           => isset( $posts[ $id ] ) ? $posts[ $id ]['title'] : '',
				'enrolled'        => $row['enrolled'],
				'completed'       => $row['completed'],
				'completion'      => self::percent( $row['completed'], $row['enrolled'] ),
				'rating'          => $ratings[ $id ] ?? null,
				'items'           => count( $items ),
				'lessons'         => count(
					array_filter(
						$items,
						static function ( $item ) use ( $posts ) {
							return 'lesson' === $posts[ $item ]['type'];
						}
					)
				),
				'median_progress' => self::median_progress( $histogram[ $id ] ?? array(), count( $items ) ),
				'drop_off'        => $stop,
			);
		}
		self::$memo[ $key ] = array(
			'courses' => $courses,
			'total'   => $found['total'],
			'limit'   => $limit,
		);
		return self::$memo[ $key ];
	}

	/**
	 * The item with the largest loss of learners against the one before. Pure.
	 *
	 * @param int[]          $items    Published content ids in course order.
	 * @param array<int,int> $done     Content id => learners who completed it.
	 * @param int            $enrolled Learners enrolled.
	 * @return array{content_id:int,position:int,lost:int,percent:int}|null Null when nobody is lost.
	 */
	public static function drop_off( array $items, array $done, $enrolled ) {
		$enrolled = (int) $enrolled;
		if ( ! $items || $enrolled <= 0 ) {
			return null;
		}
		$before = $enrolled;
		$best   = null;
		$worst  = 0;
		foreach ( array_values( $items ) as $index => $id ) {
			$now  = min( $before, (int) ( $done[ $id ] ?? 0 ) );
			$lost = $before - $now;
			if ( $lost > $worst ) {
				$worst = $lost;
				$best  = array( (int) $id, $index + 1 );
			}
			$before = $now;
		}
		if ( null === $best ) {
			return null;
		}
		return array(
			'content_id' => $best[0],
			'position'   => $best[1],
			'lost'       => $worst,
			'percent'    => self::percent( $worst, $enrolled ),
		);
	}

	/**
	 * Median progress (0-100) from a histogram of completed items per enrolment. Pure.
	 *
	 * @param array<int,int> $histogram Items completed (-1 = course completed) => enrolments.
	 * @param int            $items     Published items of the course.
	 * @return int|null Null without enrolments.
	 */
	public static function median_progress( array $histogram, $items ) {
		$values = array();
		foreach ( $histogram as $done => $count ) {
			if ( $count <= 0 ) {
				continue;
			}
			if ( $done < 0 ) {
				$value = 100.0;
			} else {
				$value = $items > 0 ? 100 * min( (int) $done, (int) $items ) / $items : 0.0;
			}
			$key            = (string) $value;
			$values[ $key ] = array( $value, ( $values[ $key ][1] ?? 0 ) + (int) $count );
		}
		if ( ! $values ) {
			return null;
		}
		usort(
			$values,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		$total = array_sum( array_column( $values, 1 ) );
		$low   = self::nth( $values, intdiv( $total - 1, 2 ) );
		$high  = self::nth( $values, intdiv( $total, 2 ) );
		return (int) round( ( $low + $high ) / 2 );
	}

	/**
	 * The n-th (0-based) value of a sorted run-length list.
	 *
	 * @param array[] $values `[ value, count ]` pairs, ascending.
	 * @param int     $n      Index.
	 * @return float
	 */
	private static function nth( array $values, $n ) {
		foreach ( $values as $pair ) {
			if ( $n < $pair[1] ) {
				return $pair[0];
			}
			$n -= $pair[1];
		}
		return (float) end( $values )[0];
	}

	/**
	 * Whole percentage.
	 *
	 * @param int $part  Part.
	 * @param int $whole Whole.
	 * @return int
	 */
	public static function percent( $part, $whole ) {
		return $whole > 0 ? (int) round( 100 * $part / $whole ) : 0;
	}

	/**
	 * Average rating and number of rated reviews per course.
	 *
	 * @param Scope $scope      Scope (reviews by its learners; every review for `all`).
	 * @param int[] $course_ids Courses.
	 * @return array<int,array{average:float,count:int}>
	 * @throws \RuntimeException On a database error.
	 */
	public static function ratings( Scope $scope, array $course_ids ) {
		global $wpdb;
		if ( ! $course_ids ) {
			return array();
		}
		$args    = array( $wpdb->comments, $wpdb->commentmeta );
		$in      = Scope::in_condition( 'c.comment_post_ID', $course_ids, $args );
		$learner = $scope->learner_condition( 'c.user_id', $args );
		$rows    = self::rows(
			"SELECT c.comment_post_ID AS course_id, COUNT(*) AS n, AVG( CAST( m.meta_value AS DECIMAL(4,2) ) ) AS average
			FROM %i c INNER JOIN %i m ON m.comment_id = c.comment_ID AND m.meta_key = 'rating'
			WHERE c.comment_approved = '1' AND c.comment_parent = 0 AND CAST( m.meta_value AS DECIMAL(4,2) ) BETWEEN 1 AND 5 AND $in AND $learner
			GROUP BY c.comment_post_ID",
			$args
		);
		$ratings = array();
		foreach ( $rows as $row ) {
			$ratings[ (int) $row['course_id'] ] = array(
				'average' => round( (float) $row['average'], 1 ),
				'count'   => (int) $row['n'],
			);
		}
		return $ratings;
	}

	/**
	 * Status, plain title and post type of posts outside the trash, with one query.
	 *
	 * @param int[] $ids Post ids.
	 * @return array<int,array{status:string,title:string,type:string}>
	 */
	private static function published( array $ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$args  = array( $wpdb->posts );
		$in    = Scope::in_condition( 'ID', $ids, $args );
		$posts = array();
		foreach ( self::rows( "SELECT ID, post_status, post_title, post_type FROM %i WHERE post_status NOT IN ( 'trash', 'auto-draft' ) AND $in", $args ) as $row ) {
			$posts[ (int) $row['ID'] ] = array(
				'status' => (string) $row['post_status'],
				'title'  => html_entity_decode( wp_strip_all_tags( (string) $row['post_title'] ), ENT_QUOTES, 'UTF-8' ),
				'type'   => self::content_type( (string) $row['post_type'] ),
			);
		}
		return $posts;
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

	/**
	 * Short content type of a post type (`lesson`, `quiz`, `assignment`, `course` or `content`).
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private static function content_type( $post_type ) {
		$types = array(
			OHMYLMS_LESSON_CPT     => 'lesson',
			OHMYLMS_QUIZ_CPT       => 'quiz',
			OHMYLMS_ASSIGNMENT_CPT => 'assignment',
			OHMYLMS_COURSE_CPT     => 'course',
		);
		return $types[ $post_type ] ?? 'content';
	}
}
