<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Read model of the gradebook: items, enrolments, rosters, attempts, due dates and overrides.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Schools\Schema as SchoolSchema;
use OhMyLMS\Student\EnrolmentMirror;
use OhMyLMS\Student\EnrolmentSchema;
use OhMyLMS\Student\Enrollments;

defined( 'ABSPATH' ) || exit;

/**
 * Every query is bounded: by course, by an explicit id list (sent in chunks of {@see Repository::CHUNK})
 * or by a LIMIT. Enrolments come from `ohmylms_enrolments` (source, class) once the enrolment migration
 * is complete and from the legacy table otherwise (source `unknown`).
 */
final class Repository {

	/**
	 * Ids per `IN (…)` list.
	 */
	const CHUNK = 500;

	/**
	 * Most graded items read per course.
	 */
	const MAX_ITEMS = 100;

	/**
	 * Enrolment statuses listed in the gradebook, with their order of preference for a learner's main row.
	 */
	const STATUSES = array( 'active', 'completed', 'suspended' );

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
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Gradebook query failed: ' . $wpdb->last_error );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Run a query once per chunk of ids and merge the rows.
	 *
	 * @param int[]    $ids   Ids.
	 * @param callable $query Receives a chunk, returns rows.
	 * @return array[]
	 */
	private static function chunked( array $ids, callable $query ) {
		$rows = array();
		foreach ( array_chunk( array_values( array_unique( array_map( 'intval', $ids ) ) ), self::CHUNK ) as $chunk ) {
			$rows = array_merge( $rows, $query( $chunk ) );
		}
		return $rows;
	}

	/**
	 * Published quizzes and assignments placed in a course, in course order, with their module.
	 *
	 * @param int $course_id Course.
	 * @return array[] Rows: id, title, post_type, module_id, module, position.
	 */
	public static function placed_items( $course_id ) {
		global $wpdb;
		$rows = self::rows(
			"SELECT p.ID AS id, p.post_title AS title, p.post_type, c.chapter_id AS module_id, COALESCE( ch.post_title, '' ) AS module,
				c.order_number AS chapter_order, r.order_number AS item_order
			FROM %i r INNER JOIN %i c ON c.chapter_id = r.chapter_id INNER JOIN %i p ON p.ID = r.content_id LEFT JOIN %i ch ON ch.ID = c.chapter_id
			WHERE c.course_id = %d AND p.post_status = 'publish' AND p.post_type IN ( %s, %s )
			ORDER BY c.order_number, r.order_number, p.ID LIMIT %d",
			array(
				SchoolSchema::table( 'content_relationship' ),
				SchoolSchema::table( 'chapter_relationship' ),
				$wpdb->posts,
				$wpdb->posts,
				(int) $course_id,
				OHMYLMS_QUIZ_CPT,
				OHMYLMS_ASSIGNMENT_CPT,
				self::MAX_ITEMS * 2,
			)
		);
		$items = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			if ( isset( $items[ $id ] ) || count( $items ) >= self::MAX_ITEMS ) {
				continue;
			}
			$items[ $id ] = array(
				'id'        => $id,
				'title'     => (string) $row['title'],
				'post_type' => (string) $row['post_type'],
				'module_id' => (int) $row['module_id'],
				'module'    => (string) $row['module'],
				'position'  => count( $items ),
			);
		}
		return array_values( $items );
	}

	/**
	 * The quizzes among the ids that back an exam (`_ohmylms_exam_id`).
	 *
	 * @param int[] $quiz_ids Quiz ids.
	 * @return int[]
	 */
	public static function exam_quiz_ids( array $quiz_ids ) {
		global $wpdb;
		if ( ! $quiz_ids ) {
			return array();
		}
		$args = array( $wpdb->postmeta );
		$in   = Scope::in_condition( 'post_id', $quiz_ids, $args );
		return array_map( 'intval', wp_list_pluck( self::rows( "SELECT DISTINCT post_id FROM %i WHERE meta_key = '_ohmylms_exam_id' AND meta_value <> '' AND meta_value <> '0' AND $in", $args ), 'post_id' ) );
	}

	/**
	 * Saved item settings of a course.
	 *
	 * @param int $course_id Course.
	 * @return array<int,array> Content id => row.
	 */
	public static function item_settings( $course_id ) {
		if ( ! Schema::ready() ) {
			return array();
		}
		$settings = array();
		foreach ( self::rows( 'SELECT content_id, category, max_marks, audience, class_ids, due_at FROM %i WHERE course_id = %d', array( Schema::table( 'gradebook_items' ), (int) $course_id ) ) as $row ) {
			$settings[ (int) $row['content_id'] ] = $row;
		}
		return $settings;
	}

	/**
	 * Category weights of a course: the course row (`class_id` 0) and the class overrides.
	 *
	 * @param int $course_id Course.
	 * @return array<int,array{weights:array,version:int,updated_at:string}> Class id (0 = course) => row.
	 */
	public static function weights( $course_id ) {
		if ( ! Schema::ready() ) {
			return array();
		}
		$weights = array();
		foreach ( self::rows( 'SELECT class_id, weights, version, updated_at FROM %i WHERE course_id = %d ORDER BY class_id', array( Schema::table( 'gradebook_weights' ), (int) $course_id ) ) as $row ) {
			$decoded                             = json_decode( (string) $row['weights'], true );
			$weights[ (int) $row['class_id'] ] = array(
				'weights'    => is_array( $decoded ) ? array_map( 'floatval', $decoded ) : array(),
				'version'    => (int) $row['version'],
				'updated_at' => (string) $row['updated_at'],
			);
		}
		return $weights;
	}

	/**
	 * Enrolments listed in a course (active, completed, suspended), optionally for some learners only.
	 *
	 * @param int        $course_id Course.
	 * @param int[]|null $learners  Learners; null for everyone.
	 * @return array[] Rows: user_id, status, source, source_type, class_id, enrolled_at, id.
	 */
	public static function enrolments( $course_id, $learners = null ) {
		global $wpdb;
		if ( is_array( $learners ) && ! $learners ) {
			return array();
		}
		$read = static function ( $chunk ) use ( $wpdb, $course_id ) {
			if ( Enrollments::reads_new() ) {
				$args = array( EnrolmentSchema::table(), $wpdb->users, (int) $course_id );
				$in   = Scope::in_condition( 'e.user_id', $chunk, $args );
				return self::rows(
					"SELECT e.id, e.user_id, e.status, e.source, e.source_type, e.class_id, e.enrolled_at FROM %i e INNER JOIN %i u ON u.ID = e.user_id
					WHERE e.course_id = %d AND e.status IN ( 'active', 'completed', 'suspended' ) AND $in ORDER BY e.id",
					$args
				);
			}
			$args = array( EnrolmentMirror::legacy_table(), $wpdb->users, (int) $course_id );
			$in   = Scope::in_condition( 'e.user_id', $chunk, $args );
			return self::rows(
				"SELECT e.id, e.user_id, CASE WHEN e.status = 'banned' THEN 'suspended' WHEN e.progress = 'completed' THEN 'completed' ELSE 'active' END AS status,
					'unknown' AS source, '' AS source_type, NULL AS class_id, e.start_date AS enrolled_at FROM %i e INNER JOIN %i u ON u.ID = e.user_id
				WHERE e.course_id = %d AND e.status IN ( 'enrolled', 'banned' ) AND $in ORDER BY e.id",
				$args
			);
		};
		return null === $learners ? $read( null ) : self::chunked( $learners, $read );
	}

	/**
	 * One row per learner: the preferred enrolment (active, then completed, then suspended; newest first)
	 * with every source the learner has in the course.
	 *
	 * @param array[] $rows Output of {@see Repository::enrolments()}.
	 * @return array<int,array{user_id:int,status:string,source:string,source_type:string,sources:string[],via:int}>
	 */
	public static function learners( array $rows ) {
		$rank     = array_flip( self::STATUSES );
		$learners = array();
		foreach ( $rows as $row ) {
			$user = (int) $row['user_id'];
			$row  = array(
				'user_id'     => $user,
				'status'      => (string) $row['status'],
				'source'      => (string) $row['source'],
				'source_type' => (string) $row['source_type'],
				'via'         => (int) $row['class_id'],
				'id'          => (int) $row['id'],
			);
			if ( ! isset( $learners[ $user ] ) ) {
				$learners[ $user ] = $row + array( 'sources' => array() );
			} else {
				$current = $learners[ $user ];
				$better  = $rank[ $row['status'] ] < $rank[ $current['status'] ] || ( $rank[ $row['status'] ] === $rank[ $current['status'] ] && $row['id'] > $current['id'] );
				if ( $better ) {
					$learners[ $user ] = $row + array( 'sources' => $current['sources'] );
				}
				if ( ! $learners[ $user ]['via'] && $row['via'] ) {
					$learners[ $user ]['via'] = $row['via'];
				}
			}
			if ( ! in_array( $row['source'], $learners[ $user ]['sources'], true ) ) {
				$learners[ $user ]['sources'][] = $row['source'];
			}
		}
		foreach ( $learners as &$learner ) {
			$learner['sources'] = array_values( array_unique( array_merge( array( $learner['source'] ), $learner['sources'] ) ) );
			unset( $learner['id'] );
		}
		unset( $learner );
		return $learners;
	}

	/**
	 * Course × class memberships (`course_class_students`) of a course.
	 *
	 * @param int $course_id Course.
	 * @return array<int,int[]> User id => class ids.
	 */
	public static function course_rosters( $course_id ) {
		$rosters = array();
		$rows    = self::rows(
			"SELECT s.user_id, s.class_id FROM %i s INNER JOIN %i c ON c.id = s.class_id WHERE s.course_id = %d AND c.status = 'active' ORDER BY s.class_id",
			array( SchoolSchema::table( 'course_class_students' ), SchoolSchema::table( 'classes' ), (int) $course_id )
		);
		foreach ( $rows as $row ) {
			$rosters[ (int) $row['user_id'] ][] = (int) $row['class_id'];
		}
		return $rosters;
	}

	/**
	 * Active classes linked to a course (`course_classes`).
	 *
	 * @param int $course_id Course.
	 * @return int[]
	 */
	public static function linked_classes( $course_id ) {
		return array_map(
			'intval',
			wp_list_pluck(
				self::rows(
					"SELECT l.class_id FROM %i l INNER JOIN %i c ON c.id = l.class_id WHERE l.course_id = %d AND c.status = 'active' ORDER BY l.class_id",
					array( SchoolSchema::table( 'course_classes' ), SchoolSchema::table( 'classes' ), (int) $course_id )
				),
				'class_id'
			)
		);
	}

	/**
	 * Classes by id (any status) with their school.
	 *
	 * @param int[] $ids Class ids.
	 * @return array<int,array{id:int,name:string,school_id:int,school_name:string,status:string}>
	 */
	public static function classes( array $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$args    = array( SchoolSchema::table( 'classes' ), SchoolSchema::table( 'schools' ) );
		$in      = Scope::in_condition( 'c.id', $ids, $args );
		$classes = array();
		foreach ( self::rows( "SELECT c.id, c.name, c.school_id, c.status, COALESCE( s.name, '' ) AS school_name FROM %i c LEFT JOIN %i s ON s.id = c.school_id WHERE $in ORDER BY c.name, c.id", $args ) as $row ) {
			$classes[ (int) $row['id'] ] = array(
				'id'          => (int) $row['id'],
				'name'        => (string) $row['name'],
				'school_id'   => (int) $row['school_id'],
				'school_name' => (string) $row['school_name'],
				'status'      => (string) $row['status'],
			);
		}
		return $classes;
	}

	/**
	 * Display names.
	 *
	 * @param int[] $ids User ids.
	 * @return array<int,string>
	 */
	public static function names( array $ids ) {
		global $wpdb;
		$names = array();
		foreach (
			self::chunked(
				$ids,
				static function ( $chunk ) use ( $wpdb ) {
					$args = array( $wpdb->users );
					$in   = Scope::in_condition( 'ID', $chunk, $args );
					return self::rows( "SELECT ID, display_name FROM %i WHERE $in", $args );
				}
			) as $row
		) {
			$names[ (int) $row['ID'] ] = (string) $row['display_name'];
		}
		return $names;
	}

	/**
	 * Quiz attempts of some learners in a course, newest first, with marks from their answers.
	 *
	 * @param int   $course_id Course.
	 * @param int[] $quiz_ids  Quizzes.
	 * @param int[] $users     Learners.
	 * @return array[] Rows: id, user_id, content_id, status, score, max.
	 */
	public static function quiz_attempts( $course_id, array $quiz_ids, array $users ) {
		if ( ! $quiz_ids || ! $users ) {
			return array();
		}
		return self::chunked(
			$users,
			static function ( $chunk ) use ( $course_id, $quiz_ids ) {
				$args = array( SchoolSchema::table( 'quiz_attempts' ), SchoolSchema::table( 'quiz_attempts_answers' ), (int) $course_id );
				$quiz = Scope::in_condition( 'q.quiz_id', $quiz_ids, $args );
				$user = Scope::in_condition( 'q.student_id', $chunk, $args );
				// quiz_attempts.total holds earned points; the maximum comes from the frozen answer rows.
				return self::rows(
					"SELECT q.id, q.student_id AS user_id, q.quiz_id AS content_id, q.status, COALESCE( SUM( a.achive_mark ), 0 ) AS score, SUM( a.question_marks ) AS max
					FROM %i q LEFT JOIN %i a ON a.quiz_attempt_id = q.id WHERE q.course_id = %d AND $quiz AND $user
					GROUP BY q.id, q.student_id, q.quiz_id, q.status ORDER BY q.id DESC",
					$args
				);
			}
		);
	}

	/**
	 * Assignment submissions of some learners in a course, newest first.
	 *
	 * @param int   $course_id      Course.
	 * @param int[] $assignment_ids Assignments.
	 * @param int[] $users          Learners.
	 * @return array[] Rows: id, user_id, content_id, status, score.
	 */
	public static function assignment_attempts( $course_id, array $assignment_ids, array $users ) {
		if ( ! $assignment_ids || ! $users ) {
			return array();
		}
		return self::chunked(
			$users,
			static function ( $chunk ) use ( $course_id, $assignment_ids ) {
				$args = array( SchoolSchema::table( 'assignment_attempts' ), (int) $course_id );
				$item = Scope::in_condition( 'assignment_id', $assignment_ids, $args );
				$user = Scope::in_condition( 'user_id', $chunk, $args );
				return self::rows( "SELECT id, user_id, assignment_id AS content_id, status, score FROM %i WHERE course_id = %d AND $item AND $user ORDER BY id DESC", $args );
			}
		);
	}

	/**
	 * Earliest due date (UTC) of active Account Hub class assignments per learner and item of a course;
	 * content 0 means the whole course.
	 *
	 * @param int   $course_id Course.
	 * @param int[] $users     Learners.
	 * @return array<int,array<int,string>> User id => content id => due date.
	 */
	public static function class_due_dates( $course_id, array $users ) {
		if ( ! $users ) {
			return array();
		}
		$due = array();
		foreach (
			self::chunked(
				$users,
				static function ( $chunk ) use ( $course_id ) {
					$args = array( SchoolSchema::table( 'learning_assignments' ), SchoolSchema::table( 'assignment_recipients' ), (int) $course_id );
					$in   = Scope::in_condition( 'r.student_user_id', $chunk, $args );
					return self::rows(
						"SELECT r.student_user_id AS user_id, a.content_id, MIN( a.due_at ) AS due_at FROM %i a INNER JOIN %i r ON r.assignment_id = a.id
						WHERE a.course_id = %d AND a.status = 'active' AND a.due_at IS NOT NULL AND $in GROUP BY r.student_user_id, a.content_id",
						$args
					);
				}
			) as $row
		) {
			$due[ (int) $row['user_id'] ][ (int) $row['content_id'] ] = (string) $row['due_at'];
		}
		return $due;
	}

	/**
	 * Current manual cells of a course (score or excused), optionally for some learners.
	 *
	 * @param int        $course_id Course.
	 * @param int[]|null $users     Learners; null for everyone.
	 * @return array<int,array<int,array>> User id => content id => kind, score, max, version.
	 */
	public static function cells( $course_id, $users = null ) {
		if ( ! Schema::ready() || ( is_array( $users ) && ! $users ) ) {
			return array();
		}
		$read  = static function ( $chunk ) use ( $course_id ) {
			$args = array( Schema::table( 'gradebook_cells' ), (int) $course_id );
			$in   = Scope::in_condition( 'user_id', $chunk, $args );
			return self::rows( "SELECT user_id, content_id, kind, score, max_score, version FROM %i WHERE course_id = %d AND $in", $args );
		};
		$cells = array();
		foreach ( null === $users ? $read( null ) : self::chunked( $users, $read ) as $row ) {
			$cells[ (int) $row['user_id'] ][ (int) $row['content_id'] ] = array(
				'kind'    => (string) $row['kind'],
				'score'   => null === $row['score'] ? null : (float) $row['score'],
				'max'     => null === $row['max_score'] ? null : (float) $row['max_score'],
				'version' => (int) $row['version'],
			);
		}
		return $cells;
	}

	/**
	 * Courses a learner is listed in (active, completed or suspended enrolments), most recent first.
	 *
	 * @param int $user_id Learner.
	 * @return int[]
	 */
	public static function learner_courses( $user_id ) {
		global $wpdb;
		if ( Enrollments::reads_new() ) {
			$rows = self::rows(
				"SELECT e.course_id FROM %i e INNER JOIN %i p ON p.ID = e.course_id WHERE e.user_id = %d AND e.status IN ( 'active', 'completed', 'suspended' )
				AND p.post_status NOT IN ( 'trash', 'auto-draft' ) GROUP BY e.course_id ORDER BY MAX( e.id ) DESC LIMIT 50",
				array( EnrolmentSchema::table(), $wpdb->posts, (int) $user_id )
			);
		} else {
			$rows = self::rows(
				"SELECT e.course_id FROM %i e INNER JOIN %i p ON p.ID = e.course_id WHERE e.user_id = %d AND e.status IN ( 'enrolled', 'banned' )
				AND p.post_status NOT IN ( 'trash', 'auto-draft' ) GROUP BY e.course_id ORDER BY MAX( e.id ) DESC LIMIT 50",
				array( EnrolmentMirror::legacy_table(), $wpdb->posts, (int) $user_id )
			);
		}
		return array_map( 'intval', wp_list_pluck( $rows, 'course_id' ) );
	}

	/**
	 * Active class memberships (as student) of some learners.
	 *
	 * @param int[] $users Learners.
	 * @return array<int,int[]> User id => class ids.
	 */
	public static function class_memberships( array $users ) {
		$classes = array();
		foreach (
			self::chunked(
				$users,
				static function ( $chunk ) {
					$args = array( SchoolSchema::table( 'class_memberships' ), SchoolSchema::table( 'classes' ) );
					$in   = Scope::in_condition( 'm.user_id', $chunk, $args );
					return self::rows( "SELECT m.user_id, m.class_id FROM %i m INNER JOIN %i c ON c.id = m.class_id WHERE m.role = 'student' AND m.status = 'active' AND c.status = 'active' AND $in", $args );
				}
			) as $row
		) {
			$classes[ (int) $row['user_id'] ][] = (int) $row['class_id'];
		}
		return $classes;
	}

	/**
	 * Courses with listed enrolments of learners who belong to no active class, most such learners first.
	 *
	 * @param int $limit Most courses.
	 * @return int[]
	 */
	public static function courses_with_individuals( $limit ) {
		global $wpdb;
		$table  = Enrollments::reads_new() ? EnrolmentSchema::table() : EnrolmentMirror::legacy_table();
		$status = Enrollments::reads_new() ? "e.status IN ( 'active', 'completed', 'suspended' )" : "e.status IN ( 'enrolled', 'banned' )";
		$rows   = self::rows(
			"SELECT e.course_id, COUNT( DISTINCT e.user_id ) AS n FROM %i e INNER JOIN %i p ON p.ID = e.course_id
			WHERE $status AND p.post_status NOT IN ( 'trash', 'auto-draft' ) AND NOT EXISTS (
				SELECT 1 FROM %i m INNER JOIN %i c ON c.id = m.class_id WHERE m.user_id = e.user_id AND m.role = 'student' AND m.status = 'active' AND c.status = 'active'
			) GROUP BY e.course_id ORDER BY n DESC, e.course_id LIMIT %d",
			array( $table, $wpdb->posts, SchoolSchema::table( 'class_memberships' ), SchoolSchema::table( 'classes' ), max( 1, (int) $limit ) )
		);
		return array_map( 'intval', wp_list_pluck( $rows, 'course_id' ) );
	}
}
