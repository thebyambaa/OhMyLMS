<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Teacher-only marking rubric of short and long text questions.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Teacher-only marking rubric of short and long text questions.
 *
 * Settings.rubric = [[
 *   'id' => 'c1', 'title' => 'Argument', 'description' => '...', 'points' => 2,
 *   'levels' => [ [ 'points' => 2, 'description' => 'Clear and complete' ], ... ]
 * ], ...]
 * The criteria's points add up to the question's points. The rubric only guides manual grading: the
 * grader's total is written by {@see \OhMyLMS\Quiz\Review::save()}, so grading stays manual and pending
 * answers stay pending. It is never part of a learner view (the types publish no settings).
 */
final class Rubric {
	const TYPES             = array( 'short-text', 'long-text' );
	const MAX_CRITERIA      = 20;
	const MAX_LEVELS        = 10;
	const MAX_TITLE         = 200;
	const MAX_DESCRIPTION   = 2000;
	const MAX_LEVEL_TEXT    = 1000;
	const MAX_POINTS        = 10000;
	const CRITERION_KEYS    = array( 'id', 'title', 'description', 'points', 'levels' );
	const LEVEL_KEYS        = array( 'points', 'description' );
	const POINTS_TOLERANCE  = 0.0001;
	const IDENTIFIER_FORMAT = '/^[a-z0-9_-]{1,40}$/D';

	/**
	 * Whether a question type has a rubric.
	 *
	 * @param string $type Question type.
	 * @return bool
	 */
	public static function applies( $type ) {
		return in_array( $type, self::TYPES, true );
	}

	/**
	 * Check a submitted rubric against the question's points.
	 *
	 * @param mixed $rubric Submitted `settings.rubric`.
	 * @param mixed $score  Submitted `settings.score`.
	 * @return true|WP_Error 422 `ohmylms_question_invalid_settings` with `data.field`.
	 */
	public static function check( $rubric, $score ) {
		if ( ! is_array( $rubric ) || ! array_is_list( $rubric ) ) {
			return self::invalid( __( 'The rubric must be a list of criteria.', 'ohmylms' ), 'settings.rubric' );
		}
		if ( count( $rubric ) > self::MAX_CRITERIA ) {
			/* translators: %d: maximum number of criteria. */
			return self::invalid( sprintf( __( 'Use at most %d rubric criteria.', 'ohmylms' ), self::MAX_CRITERIA ), 'settings.rubric' );
		}
		$ids   = array();
		$total = 0.0;
		foreach ( $rubric as $index => $criterion ) {
			$field = "settings.rubric[$index]";
			if ( ! is_array( $criterion ) || array_diff( array_keys( $criterion ), self::CRITERION_KEYS ) ) {
				return self::invalid( __( 'A rubric criterion has an ID, a title, a description, points and levels only.', 'ohmylms' ), $field );
			}
			$id = $criterion['id'] ?? '';
			if ( ! is_string( $id ) || ! preg_match( self::IDENTIFIER_FORMAT, $id ) || isset( $ids[ $id ] ) ) {
				return self::invalid( __( 'Each rubric criterion needs a unique ID of lowercase letters, digits, - or _.', 'ohmylms' ), "$field.id" );
			}
			$ids[ $id ] = true;
			$title      = $criterion['title'] ?? '';
			if ( ! is_string( $title ) || '' === trim( wp_strip_all_tags( $title ) ) || mb_strlen( $title ) > self::MAX_TITLE ) {
				/* translators: %d: maximum length. */
				return self::invalid( sprintf( __( 'Give each rubric criterion a title of at most %d characters.', 'ohmylms' ), self::MAX_TITLE ), "$field.title" );
			}
			$description = $criterion['description'] ?? '';
			if ( ! is_string( $description ) || mb_strlen( $description ) > self::MAX_DESCRIPTION ) {
				/* translators: %d: maximum length. */
				return self::invalid( sprintf( __( 'Use a criterion description of at most %d characters.', 'ohmylms' ), self::MAX_DESCRIPTION ), "$field.description" );
			}
			$points = $criterion['points'] ?? null;
			if ( ! self::is_points( $points ) || (float) $points <= 0 ) {
				/* translators: %d: maximum points. */
				return self::invalid( sprintf( __( 'Give each rubric criterion more than 0 and at most %d points.', 'ohmylms' ), self::MAX_POINTS ), "$field.points" );
			}
			$total += (float) $points;
			$valid  = self::check_levels( $criterion['levels'] ?? array(), (float) $points, $field );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}
		if ( ! $rubric ) {
			return true;
		}
		$value = is_array( $score ) ? ( $score['value'] ?? null ) : null;
		if ( ! is_numeric( $value ) || abs( (float) $value - $total ) > self::POINTS_TOLERANCE ) {
			/* translators: %s: total points of the rubric. */
			return self::invalid( sprintf( __( 'The question\'s points must be the rubric total, %s.', 'ohmylms' ), (string) round( $total, 4 ) ), 'settings.score' );
		}

		return true;
	}

	/**
	 * The stored rubric, normalized for graders. Malformed entries (written by other writers) are skipped.
	 *
	 * @param array $settings Question or version settings.
	 * @return array[] Criteria `{id, title, description, points, levels[]}`; empty without a rubric.
	 */
	public static function criteria( array $settings ) {
		if ( ! self::applies( (string) ( $settings['type'] ?? '' ) ) || ! is_array( $settings['rubric'] ?? null ) ) {
			return array();
		}
		$criteria = array();
		foreach ( array_slice( $settings['rubric'], 0, self::MAX_CRITERIA ) as $index => $criterion ) {
			if ( ! is_array( $criterion ) || ! self::is_points( $criterion['points'] ?? null ) || (float) $criterion['points'] <= 0 ) {
				continue;
			}
			$levels = array();
			foreach ( array_slice( (array) ( $criterion['levels'] ?? array() ), 0, self::MAX_LEVELS ) as $level ) {
				if ( is_array( $level ) && self::is_points( $level['points'] ?? null ) && (float) $level['points'] <= (float) $criterion['points'] ) {
					$levels[] = array(
						'points'      => (float) $level['points'],
						'description' => is_string( $level['description'] ?? null ) ? $level['description'] : '',
					);
				}
			}
			$id         = is_string( $criterion['id'] ?? null ) ? $criterion['id'] : '';
			$criteria[] = array(
				'id'          => preg_match( self::IDENTIFIER_FORMAT, $id ) ? $id : 'c' . ( $index + 1 ),
				'title'       => is_string( $criterion['title'] ?? null ) ? $criterion['title'] : '',
				'description' => is_string( $criterion['description'] ?? null ) ? $criterion['description'] : '',
				'points'      => (float) $criterion['points'],
				'levels'      => $levels,
			);
		}

		return $criteria;
	}

	/**
	 * Check a grader's points per criterion against the delivered rubric and the marks they are sent
	 * with: every criterion scored once, within its points (one of its levels when it has levels), and
	 * the marks equal to the rubric score scaled to the question's maximum in the attempt.
	 *
	 * @param array[] $criteria    {@see self::criteria()} of the delivered version.
	 * @param mixed   $scores      Points by criterion ID.
	 * @param mixed   $marks       Marks sent for the question.
	 * @param float   $max         Maximum marks of the question in the attempt.
	 * @param int     $question_id Question ID, for the error data.
	 * @return array<string,float>|WP_Error Points by criterion ID in rubric order; 422 errors otherwise.
	 */
	public static function check_scores( array $criteria, $scores, $marks, $max, $question_id ) {
		$fail = static function ( $code, $message, array $data = array() ) use ( $question_id ) {
			return new WP_Error(
				$code,
				$message,
				array(
					'status'      => 422,
					'question_id' => (int) $question_id,
				) + $data
			);
		};
		if ( ! $criteria ) {
			return $fail( 'ohmylms_grade_rubric_unavailable', __( 'This question has no rubric in this attempt.', 'ohmylms' ) );
		}
		if ( ! is_array( $scores ) || ! is_numeric( $marks ) ) {
			return $fail( 'ohmylms_grade_rubric_invalid', __( 'Send the points of every rubric criterion with the marks of the question.', 'ohmylms' ) );
		}
		$known = array_column( $criteria, null, 'id' );
		foreach ( array_keys( $scores ) as $id ) {
			if ( ! isset( $known[ (string) $id ] ) ) {
				return $fail( 'ohmylms_grade_rubric_invalid', __( 'A scored criterion is not in the rubric of this question.', 'ohmylms' ), array( 'criterion_id' => (string) $id ) );
			}
		}
		$checked = array();
		$score   = 0.0;
		$total   = 0.0;
		foreach ( $criteria as $criterion ) {
			$points = $scores[ $criterion['id'] ] ?? null;
			if ( ! is_int( $points ) && ! is_float( $points ) ) {
				return $fail( 'ohmylms_grade_rubric_invalid', __( 'Score every criterion of the rubric.', 'ohmylms' ), array( 'criterion_id' => $criterion['id'] ) );
			}
			$levels = array_column( $criterion['levels'], 'points' );
			$level  = ! $levels;
			foreach ( $levels as $value ) {
				$level = $level || abs( (float) $value - (float) $points ) < self::POINTS_TOLERANCE;
			}
			if ( $points < 0 || $points > $criterion['points'] + self::POINTS_TOLERANCE || ! $level ) {
				return $fail( 'ohmylms_grade_rubric_invalid', __( 'Give each criterion points from 0 to its maximum, or one of its levels.', 'ohmylms' ), array( 'criterion_id' => $criterion['id'] ) );
			}
			$checked[ $criterion['id'] ] = round( (float) $points, 4 );
			$score                      += (float) $points;
			$total                      += $criterion['points'];
		}
		$expected = abs( $total - (float) $max ) < self::POINTS_TOLERANCE ? $score : $score / $total * (float) $max;
		if ( abs( (float) $marks - $expected ) > self::POINTS_TOLERANCE ) {
			return $fail(
				'ohmylms_grade_rubric_total',
				__( 'The marks must match the rubric score.', 'ohmylms' ),
				array( 'expected' => round( $expected, 4 ) )
			);
		}

		return $checked;
	}

	/**
	 * Check the levels of one criterion.
	 *
	 * @param mixed  $levels Submitted levels.
	 * @param float  $max    Points of the criterion.
	 * @param string $field  Field path of the criterion.
	 * @return true|WP_Error
	 */
	private static function check_levels( $levels, $max, $field ) {
		if ( ! is_array( $levels ) || ! array_is_list( $levels ) || count( $levels ) > self::MAX_LEVELS ) {
			/* translators: %d: maximum number of levels. */
			return self::invalid( sprintf( __( 'Use a list of at most %d levels.', 'ohmylms' ), self::MAX_LEVELS ), "$field.levels" );
		}
		$seen = array();
		foreach ( $levels as $index => $level ) {
			$path = "$field.levels[$index]";
			if ( ! is_array( $level ) || array_diff( array_keys( $level ), self::LEVEL_KEYS ) ) {
				return self::invalid( __( 'A rubric level has points and a description only.', 'ohmylms' ), $path );
			}
			$points = $level['points'] ?? null;
			if ( ! self::is_points( $points ) || (float) $points > $max || in_array( (float) $points, $seen, true ) ) {
				return self::invalid( __( 'Each level needs different points from 0 up to the criterion\'s points.', 'ohmylms' ), "$path.points" );
			}
			$seen[]      = (float) $points;
			$description = $level['description'] ?? '';
			if ( ! is_string( $description ) || '' === trim( wp_strip_all_tags( $description ) ) || mb_strlen( $description ) > self::MAX_LEVEL_TEXT ) {
				/* translators: %d: maximum length. */
				return self::invalid( sprintf( __( 'Describe each level in at most %d characters.', 'ohmylms' ), self::MAX_LEVEL_TEXT ), "$path.description" );
			}
		}

		return true;
	}

	/**
	 * A finite number of points from 0 to the maximum.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_points( $value ) {
		return ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) && $value >= 0 && $value <= self::MAX_POINTS;
	}

	/**
	 * Build a 422 settings error.
	 *
	 * @param string $message Message.
	 * @param string $field   Field path.
	 * @return WP_Error
	 */
	private static function invalid( $message, $field ) {
		return new WP_Error(
			'ohmylms_question_invalid_settings',
			$message,
			array(
				'status' => 422,
				'field'  => $field,
			)
		);
	}
}
