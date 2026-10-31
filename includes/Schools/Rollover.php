<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Year rollover: archive a year's classes, create next-year classes and promote their rosters.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * A rollover moves the active classes of one academic year of a school to another, active year. A grade
 * map says what each grade becomes (`7` → `8`); a grade mapped to nothing leaves the school's classes
 * (graduates). For every promoted class a successor class is created in the new year (or reused: the
 * successor carries `source_class_id`), its active students (and optionally teachers) join it, its course
 * links are copied and the students' active roster enrolments move to the new class through
 * {@see RosterEnrolments::move()} (source `roster`). Students of a leaving class keep their accounts and
 * school membership; their active roster enrolments are cancelled. Every source class and the old year are
 * archived. {@see self::plan()} writes nothing and returns a review token; {@see self::apply()} recomputes
 * the plan under a lock and refuses a different one, and applying the same review twice returns the first
 * result. Callers check that the user manages the school.
 */
final class Rollover {

	/** Most source classes in one rollover. */
	const MAX_CLASSES = 200;

	/**
	 * Option storing applied rollovers of a school, keyed by `<from>:<to>`.
	 *
	 * @param int $school_id School.
	 * @return string
	 */
	private static function option( $school_id ) {
		return 'ohmylms_rollover_' . (int) $school_id;
	}

	/**
	 * Clean a grade map: `from` grade => `to` grade ('' = leaves). Later entries win.
	 *
	 * @param array $entries List of `{ from, to }`.
	 * @return array<string,string>
	 */
	public static function grade_map( array $entries ) {
		$map = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['from'] ) ) {
				continue;
			}
			$from         = Service::text( (string) $entry['from'], 50 );
			$map[ $from ] = Service::text( (string) ( $entry['to'] ?? '' ), 50 );
		}
		ksort( $map, SORT_STRING );

		return $map;
	}

	/**
	 * Name of the successor class: the old grade inside the name is replaced by the new one ("7A" → "8A").
	 *
	 * @param string $name Old name.
	 * @param string $from Old grade.
	 * @param string $to   New grade.
	 * @return string
	 */
	public static function successor_name( $name, $from, $to ) {
		if ( '' === $from || $from === $to ) {
			return $name;
		}
		$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $from, '/' ) . '(?![\p{N}])/u';
		$renamed = preg_replace( $pattern, $to, $name, 1 );

		return is_string( $renamed ) ? mb_substr( $renamed, 0, 190 ) : $name;
	}

	/**
	 * Load and check the two years.
	 *
	 * @param int $school_id School.
	 * @param int $from_id   Source year.
	 * @param int $to_id     Target year.
	 * @return array{0:array,1:array}
	 * @throws \RuntimeException 422 for unusable years.
	 */
	private static function years( $school_id, $from_id, $to_id ) {
		$from = Service::row( 'academic_years', (int) $from_id );
		$to   = Service::row( 'academic_years', (int) $to_id );
		if ( ! $from || ! $to || (int) $from['school_id'] !== (int) $school_id || (int) $to['school_id'] !== (int) $school_id ) {
			throw new \RuntimeException( 'Choose two academic years of this school.', 422 );
		}
		if ( (int) $from['id'] === (int) $to['id'] ) {
			throw new \RuntimeException( 'Choose a different year to move the classes to.', 422 );
		}
		if ( 'active' !== $to['status'] ) {
			throw new \RuntimeException( 'The year to move the classes to must be active.', 422 );
		}

		return array( $from, $to );
	}

	/**
	 * Plan a rollover without writing anything.
	 *
	 * @param int   $school_id     School.
	 * @param int   $from_id       Source year.
	 * @param int   $to_id         Target year.
	 * @param array $grade_map     From {@see self::grade_map()}.
	 * @param bool  $copy_teachers Whether teachers join the new classes too.
	 * @param bool  $lock          Lock the rows read (inside {@see self::apply()}).
	 * @return array
	 * @throws \RuntimeException 409 when the source year is already archived.
	 */
	public static function plan( $school_id, $from_id, $to_id, array $grade_map, $copy_teachers, $lock = false ) {
		global $wpdb;
		list( $from, $to ) = self::years( $school_id, $from_id, $to_id );
		if ( 'active' !== $from['status'] ) {
			throw new \RuntimeException( 'This year has already been rolled over or archived.', 409 );
		}
		$for_update = $lock ? ' FOR UPDATE' : '';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Fixed locking suffix; fresh state.
		$classes = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id,name,subject,grade FROM %i WHERE school_id=%d AND academic_year_id=%d AND status='active' ORDER BY grade, name, id LIMIT %d{$for_update}", Schema::table( 'classes' ), $school_id, $from['id'], self::MAX_CLASSES ), ARRAY_A );
		$rows    = array();
		$totals  = array(
			'classes'            => 0,
			'promoted_classes'   => 0,
			'leaving_classes'    => 0,
			'classes_created'    => 0,
			'students_promoted'  => 0,
			'students_leaving'   => 0,
			'teachers_copied'    => 0,
			'enrolments_moved'   => 0,
			'enrolments_revoked' => 0,
			'course_links'       => 0,
		);
		foreach ( $classes as $class ) {
			$grade    = (string) $class['grade'];
			$to_grade = array_key_exists( $grade, $grade_map ) ? $grade_map[ $grade ] : $grade;
			$leaves   = '' === $to_grade;
			$students = self::members( (int) $class['id'], (int) $school_id, 'student' );
			$teachers = $copy_teachers && ! $leaves ? self::members( (int) $class['id'], (int) $school_id, 'teacher' ) : array();
			$enrolled = count( RosterEnrolments::active( array( (int) $class['id'] ), $students ) );
			$links    = $leaves ? 0 : count( self::course_links( (int) $class['id'] ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Successor lookup keeps apply idempotent.
			$existing = $leaves ? null : $wpdb->get_row( $wpdb->prepare( 'SELECT id,name,grade FROM %i WHERE source_class_id=%d AND academic_year_id=%d LIMIT 1', Schema::table( 'classes' ), $class['id'], $to['id'] ), ARRAY_A );
			$rows[]   = array(
				'class_id'   => (int) $class['id'],
				'name'       => (string) $class['name'],
				'subject'    => (string) $class['subject'],
				'grade'      => $grade,
				'action'     => $leaves ? 'leave' : 'promote',
				'students'   => count( $students ),
				'teachers'   => count( $teachers ),
				'enrolments' => $enrolled,
				'links'      => $links,
				'target'     => $leaves ? null : array(
					'id'     => $existing ? (int) $existing['id'] : null,
					'name'   => $existing ? (string) $existing['name'] : self::successor_name( (string) $class['name'], $grade, $to_grade ),
					'grade'  => $existing ? (string) $existing['grade'] : $to_grade,
					'exists' => (bool) $existing,
				),
			);
			++$totals['classes'];
			if ( $leaves ) {
				++$totals['leaving_classes'];
				$totals['students_leaving']   += count( $students );
				$totals['enrolments_revoked'] += $enrolled;
			} else {
				++$totals['promoted_classes'];
				$totals['classes_created']   += $existing ? 0 : 1;
				$totals['students_promoted'] += count( $students );
				$totals['teachers_copied']   += count( $teachers );
				$totals['enrolments_moved']  += $enrolled;
				$totals['course_links']      += $links;
			}
		}

		return array(
			'from'          => self::year( $from ),
			'to'            => self::year( $to ),
			'grade_map'     => $grade_map,
			'copy_teachers' => (bool) $copy_teachers,
			'classes'       => $rows,
			'totals'        => $totals,
		);
	}

	/**
	 * Public shape of a year.
	 *
	 * @param array $row Year row.
	 * @return array
	 */
	private static function year( array $row ) {
		return array(
			'id'     => (int) $row['id'],
			'label'  => (string) $row['label'],
			'status' => (string) $row['status'],
		);
	}

	/**
	 * Active class members in a role who are still on the school roster in that role.
	 *
	 * @param int    $class_id  Class.
	 * @param int    $school_id School.
	 * @param string $role      `student` or `teacher`.
	 * @return int[]
	 */
	private static function members( $class_id, $school_id, $role ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh roster.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT cm.user_id FROM %i cm JOIN %i sm ON sm.user_id=cm.user_id AND sm.school_id=%d AND sm.role=cm.role AND sm.status='active' WHERE cm.class_id=%d AND cm.role=%s AND cm.status='active' ORDER BY cm.user_id", Schema::table( 'class_memberships' ), Schema::table( 'school_memberships' ), $school_id, $class_id, $role ) ) );
	}

	/**
	 * Courses linked to a class.
	 *
	 * @param int $class_id Class.
	 * @return int[]
	 */
	private static function course_links( $class_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fresh links.
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT course_id FROM %i WHERE class_id=%d ORDER BY course_id', Schema::table( 'course_classes' ), $class_id ) ) );
	}

	/**
	 * Review token binding the caller to an exact plan.
	 *
	 * @param int   $school_id School.
	 * @param array $plan      Plan.
	 * @return string
	 */
	public static function review( $school_id, array $plan ) {
		return hash_hmac( 'sha256', (string) wp_json_encode( array( get_current_user_id(), (int) $school_id, $plan ) ), wp_salt( 'nonce' ) );
	}

	/**
	 * Apply a reviewed plan.
	 *
	 * @param int    $school_id     School.
	 * @param int    $from_id       Source year.
	 * @param int    $to_id         Target year.
	 * @param array  $grade_map     Grade map.
	 * @param bool   $copy_teachers Whether teachers join the new classes.
	 * @param string $review        Token from the plan.
	 * @return array Result; `already_applied` is true when this review was applied before.
	 * @throws \RuntimeException 412 for a stale review, 409 when the year is already archived, 423 when
	 *                           another rollover of the school runs, 500 on a database failure.
	 */
	public static function apply( $school_id, $from_id, $to_id, array $grade_map, $copy_teachers, $review ) {
		global $wpdb;
		$school_id = (int) $school_id;
		$lock      = 'ohmylms_rollover_' . $school_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Serialize rollovers of one school.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			throw new \RuntimeException( 'Another rollover of this school is running. Try again in a moment.', 423 );
		}
		try {
			$key     = (int) $from_id . ':' . (int) $to_id;
			$applied = get_option( self::option( $school_id ), array() );
			$applied = is_array( $applied ) ? $applied : array();
			if ( isset( $applied[ $key ] ) && hash_equals( (string) $applied[ $key ]['review'], (string) $review ) ) {
				return array_merge( $applied[ $key ]['result'], array( 'already_applied' => true ) );
			}
			$result = Transaction::run(
				static function () use ( $wpdb, $school_id, $from_id, $to_id, $grade_map, $copy_teachers, $review ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock both years in id order; class creation in them waits.
					$wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE id IN (%d,%d) ORDER BY id FOR UPDATE', Schema::table( 'academic_years' ), $from_id, $to_id ) );
					$plan = self::plan( $school_id, $from_id, $to_id, $grade_map, $copy_teachers, true );
					if ( ! hash_equals( self::review( $school_id, $plan ), (string) $review ) ) {
						throw new \RuntimeException( 'The classes or rosters changed since the preview. Preview the rollover again.', 412 );
					}

					return self::write( $school_id, $plan );
				}
			);
			$applied[ $key ] = array(
				'review' => (string) $review,
				'result' => $result,
			);
			update_option( self::option( $school_id ), array_slice( $applied, -20, null, true ), false );
			if ( class_exists( \OhMyLMS\Dashboard\Cache::class ) ) {
				\OhMyLMS\Dashboard\Cache::invalidate();
			}

			return array_merge( $result, array( 'already_applied' => false ) );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release the per-school lock.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Write a locked plan.
	 *
	 * @param int   $school_id School.
	 * @param array $plan      Plan.
	 * @return array
	 */
	private static function write( $school_id, array $plan ) {
		$to      = (int) $plan['to']['id'];
		$now     = Service::now();
		$classes = array();
		foreach ( $plan['classes'] as $row ) {
			$source   = (int) $row['class_id'];
			$students = self::members( $source, $school_id, 'student' );
			$enrolled = RosterEnrolments::active( array( $source ), $students );
			if ( 'leave' === $row['action'] ) {
				RosterEnrolments::revoke( $enrolled );
				$target = 0;
			} else {
				$target = $row['target']['id'] ? (int) $row['target']['id'] : Service::insert(
					'classes',
					array(
						'school_id'        => $school_id,
						'academic_year_id' => $to,
						'name'             => $row['target']['name'],
						'subject'          => $row['subject'],
						'grade'            => $row['target']['grade'],
						'source_class_id'  => $source,
					)
				);
				if ( ! $row['target']['id'] ) {
					Service::audit( $school_id, 'class_created', $target );
				}
				if ( $plan['copy_teachers'] ) {
					foreach ( self::members( $source, $school_id, 'teacher' ) as $teacher ) {
						Service::membership(
							'class_memberships',
							array(
								'class_id' => $target,
								'user_id'  => $teacher,
								'role'     => 'teacher',
							)
						);
					}
				}
				foreach ( $students as $student ) {
					Service::membership(
						'class_memberships',
						array(
							'class_id' => $target,
							'user_id'  => $student,
							'role'     => 'student',
						)
					);
				}
				self::copy_links( $source, $target, $students, $now );
				RosterEnrolments::move( $enrolled, $target );
			}
			Service::update( 'classes', array( 'status' => 'archived' ), array( 'id' => $source ) );
			Service::audit( $school_id, 'class_archived', $source );
			$classes[] = array(
				'source_id' => $source,
				'target_id' => $target ? $target : null,
				'action'    => $row['action'],
			);
		}
		Service::update( 'academic_years', array( 'status' => 'archived' ), array( 'id' => (int) $plan['from']['id'] ) );
		Service::audit( $school_id, 'year_rolled_over', (int) $plan['from']['id'] );

		return array(
			'from'    => array_merge( $plan['from'], array( 'status' => 'archived' ) ),
			'to'      => $plan['to'],
			'classes' => $classes,
			'totals'  => $plan['totals'],
		);
	}

	/**
	 * Copy a class's course links to its successor, with the promoted students.
	 *
	 * @param int    $source   Old class.
	 * @param int    $target   New class.
	 * @param int[]  $students Promoted students.
	 * @param string $now      Time (UTC).
	 * @throws \RuntimeException When a link cannot be written.
	 */
	private static function copy_links( $source, $target, array $students, $now ) {
		global $wpdb;
		foreach ( self::course_links( $source ) as $course ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Idempotent link copy (unique key course_class).
			$linked = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (course_id,class_id,created_by,created_at) VALUES (%d,%d,%d,%s)', Schema::table( 'course_classes' ), $course, $target, get_current_user_id(), $now ) );
			if ( false === $linked ) {
				throw new \RuntimeException( 'Unable to copy course links.', 500 );
			}
			foreach ( $students as $student ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Idempotent (unique key course_class_student).
				$added = $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (course_id,class_id,user_id,created_at) VALUES (%d,%d,%d,%s)', Schema::table( 'course_class_students' ), $course, $target, $student, $now ) );
				if ( false === $added ) {
					throw new \RuntimeException( 'Unable to copy course links.', 500 );
				}
			}
		}
	}
}
