<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Chooses the next practice question for a skill.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Chooses the next practice question for a skill.
 *
 * Pool: approved, non-secure (not exam-only), non-archived versions whose frozen mapping
 * assesses the skill (or a sub-skill) as primary, and that grade automatically.
 * Difficulty follows teacher bands: two correct in a row moves up, two misses move down.
 * Questions already issued in the session are excluded and unused families are preferred;
 * when nothing new remains the session reports that instead of repeating questions.
 */
final class Selector {
	const BANDS = array( 'easy', 'standard', 'challenge' );

	/** Question types a learner can answer by recognising the right option. */
	const RECOGNIZE = array( 'single-choice', 'multiple-choice', 'true-false', 'matching', 'dropdown-blanks', 'categorize', 'statement' );
	/** Types where the learner builds or places the answer from given pieces. */
	const GUIDED = array( 'fill-in-the-blank', 'reorder', 'build-expression', 'number-line', 'shade-model', 'count-blocks', 'set-clock', 'make-amount', 'fill-level', 'build-chart', 'grid-build' );

	/** recognize, guided or produce: the order a lesson moves through (easiest first).
	 *
	 * @param mixed $type Type.
	 */
	public static function stage( $type ) {
		if ( in_array( $type, self::RECOGNIZE, true ) ) {
			return 'recognize'; }
		return in_array( $type, self::GUIDED, true ) ? 'guided' : 'produce';
	}

	/**
	 * The stage the n-th planned question of a lesson belongs to: the first third recognises,
	 * the middle third is guided, the last third produces.
	 *
	 * @param mixed $done Done.
	 * @param mixed $planned Planned.
	 */
	public static function stage_for( $done, $planned ) {
		$third = max( 1, (int) ceil( $planned / 3 ) );
		return $done < $third ? 'recognize' : ( $done < 2 * $third ? 'guided' : 'produce' );
	}

	/**
	 * Next.
	 *
	 * @param array  $session Session.
	 * @param array  $issued Issued.
	 * @param mixed  $band Band.
	 * @param string $stage Optional lesson stage; used when the pool has a question of that stage.
	 * @return array{question_id:int,version_id:int,difficulty:string,family_id:string}|null
	 */
	public static function next( array $session, array $issued, $band, $stage = '' ) {
		$pool           = \OhMyLMS\Learning\PracticeAccessPolicy::pool( self::pool( (int) $session['term_id'] ), array( 'student_id' => (int) $session['student_id'] ), (int) $session['term_id'], (int) $session['course_id'] );
		$used_questions = array_map( 'intval', array_column( $issued, 'question_id' ) );
		$used_families  = array_filter( array_column( $issued, 'family_id' ) );
		$available      = array_values(
			array_filter(
				$pool,
				static function ( $row ) use ( $used_questions ) {
					// A template is endless: each issue gets new numbers, so it may come round again.
					return ! empty( $row['is_template'] ) || ! in_array( (int) $row['question_id'], $used_questions, true );
				}
			)
		);
		if ( ! $available ) {
			return null; }
		$fresh      = array_values(
			array_filter(
				$available,
				static function ( $row ) use ( $used_families ) {
					return '' === $row['family_id'] || ! in_array( $row['family_id'], $used_families, true );
				}
			)
		);
		$candidates = $fresh ? $fresh : $available;
		// A template can come round again, but only once nothing new is left to ask.
		$never = array_values(
			array_filter(
				$candidates,
				static function ( $row ) use ( $used_questions ) {
					return ! in_array( (int) $row['question_id'], $used_questions, true );
				}
			)
		);
		if ( $never ) {
			$candidates = $never; }
		if ( '' !== $stage ) {
			// Prefer the lesson's current stage, then the nearest stage that has questions.
			$order = array(
				'recognize' => array( 'recognize', 'guided', 'produce' ),
				'guided'    => array( 'guided', 'recognize', 'produce' ),
				'produce'   => array( 'produce', 'guided', 'recognize' ),
			)[ $stage ] ?? array();
			foreach ( $order as $wanted ) {
				$staged = array_values(
					array_filter(
						$candidates,
						static function ( $row ) use ( $wanted ) {
							return self::stage( $row['type'] ?? '' ) === $wanted;
						}
					)
				);
				if ( $staged ) {
					$candidates = $staged;
					break; }
			}
		}
		// Nearest band first: the target band, then the adjacent ones.
		$target = array_search( $band, self::BANDS, true );
		$target = false === $target ? 1 : $target;
		usort(
			$candidates,
			static function ( $left, $right ) use ( $target ) {
				return abs( array_search( $left['difficulty'], self::BANDS, true ) - $target ) <=> abs( array_search( $right['difficulty'], self::BANDS, true ) - $target );
			}
		);
		$best    = abs( array_search( $candidates[0]['difficulty'], self::BANDS, true ) - $target );
		$nearest = array_values(
			array_filter(
				$candidates,
				static function ( $row ) use ( $best, $target ) {
					return abs( array_search( $row['difficulty'], self::BANDS, true ) - $target ) === $best;
				}
			)
		);
		return $nearest[ random_int( 0, count( $nearest ) - 1 ) ];
	}

	/** Every eligible question for a skill and its sub-skills.
	 *
	 * @param mixed $term_id Term identifier.
	 */
	public static function pool( $term_id ) {
		$terms = array_merge( array( (int) $term_id ), array_map( 'intval', get_term_children( (int) $term_id, Taxonomy::NAME ) ?: array() ) );
		return self::eligible( $terms );
	}

	/**
	 * Eligible questions of many skills in one query, grouped by the skill each is mapped to (no
	 * sub-skills). Used by the placement test, which looks across a whole subject.
	 *
	 * @param int[] $term_ids Skills.
	 * @return array<int,array[]> Skill => questions, as pool() returns them.
	 */
	public static function pools( array $term_ids ) {
		$terms  = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		$result = array();
		foreach ( array_chunk( $terms, 500 ) as $chunk ) {
			foreach ( self::eligible( $chunk ) as $row ) {
				$result[ (int) $row['primary_term_id'] ][] = $row;
			}
		}
		return $result;
	}

	/**
	 * Approved, non-secure, auto-marked questions whose primary skill is one of the terms.
	 *
	 * @param int[] $terms Skills.
	 * @return array[]
	 */
	private static function eligible( array $terms ) {
		global $wpdb;
		if ( ! $terms ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $terms ), '%d' ) );
		$manual       = array_keys(
			array_filter(
				\OhMyLMS\Extensions\Registry::all( 'question' ),
				static function ( $definition ) {
					return ! empty( $definition['manual'] ) || empty( $definition['snapshot'] );
				}
			)
		);
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT q.question_id, q.approved_version_id AS version_id, q.difficulty, q.family_id, q.bank_id, q.author_id, s.term_id AS primary_term_id, v.type, v.settings
             FROM ' . Schema::table( 'qb_questions' ) . ' q
             JOIN ' . Schema::table( 'qb_version_skills' ) . " s ON s.version_id=q.approved_version_id AND s.role='primary'
             JOIN " . Schema::table( 'qb_question_versions' ) . " v ON v.id=q.approved_version_id
             JOIN {$wpdb->posts} p ON p.ID=q.question_id
             WHERE q.approved_version_id>0 AND q.secure=0 AND p.post_status NOT IN ('trash', %s) AND s.term_id IN ($placeholders)",
				array_merge( array( Usage::ARCHIVED ), $terms )
			),
			ARRAY_A
		);
		return array_values(
			array_filter(
				array_map(
					static function ( $row ) use ( $manual ) {
						if ( in_array( $row['type'], $manual, true ) ) {
							return null; }
						// Structured questions with teacher-marked parts cannot give immediate practice feedback.
						if ( in_array( $row['type'], array( 'structured', 'passage' ), true ) ) {
							if ( \OhMyLMS\Assessment\Structured::has_manual( json_decode( $row['settings'], true ) ?: array() ) ) {
								return null;
							}
						}
						$row['is_template'] = \OhMyLMS\Assessment\Template::has( json_decode( $row['settings'], true ) ?: array() );
						unset( $row['settings'] );
						return $row;
					},
					$rows
				)
			)
		);
	}

	/** Adjust the band from the most recent answers in the session.
	 *
	 * @param mixed $band Band.
	 * @param array $answered Answered.
	 */
	public static function band( $band, array $answered ) {
		$index = array_search( $band, self::BANDS, true );
		$index = false === $index ? 1 : $index;
		$last  = array_slice( $answered, -2 );
		if ( count( $last ) === 2 ) {
			$correct = count(
				array_filter(
					$last,
					static function ( $item ) {
						return 1 === (int) $item['correct'];
					}
				)
			);
			if ( 2 === $correct ) {
				$index = min( 2, $index + 1 ); }
			if ( 0 === $correct ) {
				$index = max( 0, $index - 1 ); }
		}
		return self::BANDS[ $index ];
	}
}
