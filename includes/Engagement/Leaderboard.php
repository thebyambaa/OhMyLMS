<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Leaderboard Helper class.
 *
 * This class handles the leaderboard settings and functionality.
 *
 * @since 1.0.0
 * @package OhMyLMSPro
 */
namespace OhMyLMS\Engagement;

/**
 * Plugin leaderboard implementation.
 */
class Leaderboard {

	/**
	 * Get the leaderboard settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( 'ohmylms_leaderboard_settings', array() );
		return apply_filters( 'ohmylms_leaderboard_settings', $settings );
	}

	/**
	 * Maybe enable the leaderboard.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function maybe_enable() {
		return Rules::enabled( 'leaderboard' );
	}


	/**
	 * Maybe enable the leaderboard for a specific course.
	 *
	 * @return bool
	 * @since 1.0.0
	 * @param mixed $course_id Course identifier.
	 */
	public static function maybe_enable_for_course( $course_id ) {
		$leaderboard_disabled = get_post_meta( $course_id, '_leaderboard_disabled', true );
		if ( 'yes' === $leaderboard_disabled ) {
			return false;
		}
		return true;
	}

	/**
	 * Get the leaderboard rule.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function get_leaderboard_rule() {
		$settings = self::get_settings();
		return ! empty( $settings['rules'] ) ? $settings['rules'] : 'completion_rate';
	}

	/**
	 * Get the student number for the leaderboard.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_students_number() {
		$settings = self::get_settings();
		return ! empty( $settings['students_number'] ) ? (int) $settings['students_number'] : 10;
	}

	/**
	 * Get the threshold for the leaderboard.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_threshold() {
		$settings = self::get_settings();
		return ! empty( $settings['threshold'] ) ? (int) $settings['threshold'] : 0;
	}

	/**
	 * Maybe met threshold.
	 *
	 * @return int
	 * @since 1.0.0
	 * @param mixed $number Number.
	 */
	public static function maybe_met_threshold( $number ) {
		$threshold = self::get_threshold();

		if ( $threshold > 0 ) {
			return $number >= $threshold;
		}
		return true;
	}

	/**
	 * Get students for the leaderboard based on course completion rate.
	 *
	 * @param array $students Students.
	 * .
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_students_by_completion_rate( $students ) {

		usort(
			$students,
			function ( $a, $b ) {
				if ( $a['completion_rate'] === $b['completion_rate'] ) {
					return $a['completion_duration'] <=> $b['completion_duration'];
				}
				return $b['completion_rate'] <=> $a['completion_rate'];
			}
		);
		$students = self::set_student_position( $students );
		$limit    = self::get_students_number();
		$students = array_slice( $students, 0, $limit );

		$threshold = self::get_threshold();
		if ( $threshold > 0 ) {
			$filtered_students = array_filter(
				$students,
				function ( $student ) use ( $threshold ) {

					return isset( $student['completion_rate'] ) && self::maybe_met_threshold( $student['completion_rate'] );
				}
			);
			$filtered_students = array_values( $filtered_students ); // reindex array.
			if ( $limit && count( $filtered_students ) >= (int) $limit ) {
				return $filtered_students;
			} else {
				return array();
			}
		}
		return $students;
	}

	/**
	 * Get a student's average quiz marks based on all attempts for a specific quiz.
	 *
	 * @param int $student_id The student ID.
	 * @param int $quiz_id The quiz ID
	 * .
	 * @return float|int The average score, or 0 if no attempts found
	 * @since 1.0.0
	 */
	public static function get_student_avg_quiz_marks( $student_id, $quiz_id ) {
		global $wpdb;

		if ( empty( $student_id ) || empty( $quiz_id ) ) {
			return 0;
		}

		// Get all completed attempts for this student and quiz.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT total
                FROM %i
                WHERE student_id = %d 
                AND quiz_id = %d 
                AND status = \'completed\'',
				$wpdb->prefix . 'ohmylms_quiz_attempts',
				$student_id,
				$quiz_id
			)
		);

		if ( empty( $results ) ) {
			return 0;
		}

		// Calculate average.
		$total_score   = 0;
		$attempt_count = count( $results );

		foreach ( $results as $attempt ) {
			$total_score += (float) $attempt->total;
		}

		return $attempt_count > 0 ? round( $total_score / $attempt_count, 2 ) : 0;
	}

	/**
	 * Get a student's average assignment marks based on all attempts for a specific assignment.
	 *
	 * @param int $student_id The student ID.
	 * @param int $assignment_id The assignment ID
	 * .
	 * @return float|int The average score, or 0 if no attempts found
	 * @since 1.0.0
	 */
	public static function get_student_avg_assignment_marks( $student_id, $assignment_id ) {
		global $wpdb;

		if ( empty( $student_id ) || empty( $assignment_id ) ) {
			return 0;
		}

		// Get all submitted assignments for this student and assignment.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT score
                FROM %i
                WHERE user_id = %d 
                AND assignment_id = %d',
				$wpdb->prefix . 'ohmylms_assignment_attempts',
				$student_id,
				$assignment_id
			)
		);

		if ( empty( $results ) ) {
			return 0;
		}

		// Calculate average.
		$total_marks      = 0;
		$submission_count = count( $results );

		foreach ( $results as $submission ) {
			$total_marks += (float) $submission->score;
		}

		return $total_marks > 0 ? round( $total_marks ) : 0;
	}

	/**
	 * Get students for the leaderboard based on highest quiz score.
	 *
	 * @param array $students Students.
	 * .
	 * @param mixed $course_id Course identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_students_by_highest_quiz( $students, $course_id ) {

		global $wpdb;

		$course = ohmylms_get_course( $course_id );
		if ( ! $course ) {
			return $students;
		}

		$quiz_ids = $course->get_quiz_ids();

		if ( empty( $quiz_ids ) ) {
			return array();
		}

		// Prepare quiz IDs for SQL IN clause.
		$quiz_ids_sql = implode( ',', array_map( 'intval', $quiz_ids ) );
		$student_ids  = wp_list_pluck( $students, 'student_id' );
		if ( empty( $student_ids ) ) {
			return $students;
		}
		$student_ids_sql = implode( ',', array_map( 'intval', $student_ids ) );

		// Normalize each completed attempt using its saved possible marks, then
		// average attempts per quiz. Missing quizzes contribute zero below.
		$results        = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.student_id, a.quiz_id,
                    AVG(LEAST(100, GREATEST(0, 100.0 * a.total / marks.possible))) AS average_percent
                FROM {$wpdb->prefix}ohmylms_quiz_attempts a
                INNER JOIN (
                    SELECT quiz_attempt_id, SUM(question_marks) AS possible
                    FROM {$wpdb->prefix}ohmylms_quiz_attempts_answers
                    GROUP BY quiz_attempt_id
                ) marks ON marks.quiz_attempt_id = a.id
                WHERE a.quiz_id IN ($quiz_ids_sql)
                AND a.student_id IN ($student_ids_sql)
                AND a.course_id = %d AND a.status = 'completed' AND marks.possible > 0
                GROUP BY a.student_id, a.quiz_id",
				$course_id
			)
		);
		$student_scores = array();
		foreach ( $results as $row ) {
			$student_scores[ $row->student_id ] = ( $student_scores[ $row->student_id ] ?? 0 )
				+ (float) $row->average_percent / count( array_unique( $quiz_ids ) );
		}
		// Attach score to students.
		foreach ( $students as &$student ) {
			$sid                           = isset( $student['student_id'] ) ? $student['student_id'] : ( $student['ID'] ?? null );
			$student['highest_quiz_score'] = isset( $student_scores[ $sid ] ) ? $student_scores[ $sid ] : 0;
		}
		unset( $student );

		// Sort students by highest_quiz_score descending.
		usort(
			$students,
			function ( $a, $b ) {
				return $b['highest_quiz_score'] <=> $a['highest_quiz_score'];
			}
		);
		$students = self::set_student_position( $students );
		$limit    = self::get_students_number();
		$students = array_slice( $students, 0, $limit );

		$threshold = self::get_threshold();
		if ( $threshold > 0 ) {
			$students = array_filter(
				$students,
				function ( $student ) use ( $threshold ) {
					return isset( $student['highest_quiz_score'] ) && self::maybe_met_threshold( $student['highest_quiz_score'] );
				}
			);
			$students = array_values( $students ); // reindex array.
		}
		return $students;
	}

	/**
	 * Get students for the leaderboard by fastest time.
	 *
	 * @param array $students Students.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_students_by_fastest_time( $students ) {
		$students = array_values(
			array_filter(
				$students,
				function ( $student ) {
					return ! empty( $student['is_completed'] )
					&& isset( $student['completion_duration'] )
					&& $student['completion_duration'] >= 0
					&& $student['completion_duration'] < PHP_INT_MAX;
				}
			)
		);
		usort(
			$students,
			function ( $a, $b ) {
				return $a['completion_duration'] <=> $b['completion_duration'];
			}
		);
		$students = self::set_student_position( $students );
		$limit    = self::get_students_number();
		$students = array_slice( $students, 0, $limit );

		return $students;
	}


	/**
	 * Set the position for each student in the leaderboard.
	 *
	 * @param array $students Students.
	 * @return array
	 * @since 1.0.0
	 */
	public static function set_student_position( $students ) {
		$position = 1;
		foreach ( $students as &$student ) {
			$student['position']         = $position;
			$student['position_in_text'] = self::get_position_in_text( $position );
			++$position;
		}
		unset( $student );
		return $students;
	}

	/**
	 * Convert a numeric position into ordinal text (e.g., 1 => "1st", 2 => "2nd").
	 *
	 * @param int $position Position.
	 * @return string
	 */
	public static function get_position_in_text( $position ) {
		$suffixes = array( 'th', 'st', 'nd', 'rd' );
		$mod100   = $position % 100;
		$suffix   = ( $mod100 >= 11 && $mod100 <= 13 ) ? 'th' : ( $suffixes[ $position % 10 ] ?? 'th' );
		return $position . $suffix;
	}
}
