<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The pure part of the skill maps: layout, coherence checks and syllabus comparison.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

defined( 'ABSPATH' ) || exit;

/**
 * Arithmetic over plain arrays for the coherence map (domains x grades), the syllabus comparison and
 * the student skill map. No WordPress calls, so tests/php/skill-map-unit.php checks every rule.
 *
 * A skill is an array with id, domain, grade_from, grade_to and prerequisites (skill IDs). A skill
 * sits in the cell of its domain and its first grade; its band shows how far it runs.
 */
final class SkillMapModel {

	/** A prerequisite this many grades or more before the skill is flagged as a long gap. */
	const LONG_GAP = 4;

	/** Mapping relations that mean a syllabus teaches the shared skill; `related` is reference only. */
	const COVERING = array( 'equivalent', 'broader', 'narrower', 'combined' );

	/**
	 * Place skills on the grid.
	 *
	 * @param array[]  $skills Skills.
	 * @param string[] $order  Domain ids in display order; unknown domains follow.
	 * @return array domains (ids with skills, in order), grades (first to last grade used), cells (domain => grade => skill IDs), unplaced (skill IDs).
	 */
	public static function grid( array $skills, array $order = array() ) {
		$cells    = array();
		$unplaced = array();
		$grades   = array();
		foreach ( $skills as $skill ) {
			$domain = (string) ( $skill['domain'] ?? '' );
			$grade  = (int) ( $skill['grade_from'] ?? 0 );
			if ( '' === $domain || $grade < 1 ) {
				$unplaced[] = (int) $skill['id'];
				continue;
			}
			$cells[ $domain ][ $grade ][] = (int) $skill['id'];
			$grades[]                     = $grade;
			$grades[]                     = max( $grade, (int) ( $skill['grade_to'] ?? $grade ) );
		}
		$domains = array_values( array_intersect( $order, array_keys( $cells ) ) );
		$rest    = array_diff( array_keys( $cells ), $domains );
		sort( $rest );
		return array(
			'domains'  => array_merge( $domains, $rest ),
			'grades'   => $grades ? range( min( $grades ), max( $grades ) ) : array(),
			'cells'    => $cells,
			'unplaced' => $unplaced,
		);
	}

	/**
	 * Coherence checks over the prerequisite links of placed skills.
	 *
	 *  - later_prerequisite: the prerequisite is first taught after the skill that needs it.
	 *  - long_gap: the prerequisite ends LONG_GAP grades or more before the skill starts.
	 *  - no_prerequisites: a skill above the first grade of its domain needs nothing, which usually
	 *    means a link is missing.
	 *
	 * @param array[] $skills Skills keyed by ID.
	 * @return array[] Each: type, skill, and other (the prerequisite) for link checks, ordered by grade.
	 */
	public static function issues( array $skills ) {
		$first = array();
		foreach ( $skills as $skill ) {
			if ( self::placed( $skill ) ) {
				$domain           = $skill['domain'];
				$first[ $domain ] = min( $first[ $domain ] ?? PHP_INT_MAX, (int) $skill['grade_from'] );
			}
		}
		$issues = array();
		foreach ( $skills as $id => $skill ) {
			if ( ! self::placed( $skill ) ) {
				continue;
			}
			$from          = (int) $skill['grade_from'];
			$prerequisites = array_values( array_filter( array_map( 'intval', $skill['prerequisites'] ?? array() ) ) );
			foreach ( $prerequisites as $other ) {
				if ( ! isset( $skills[ $other ] ) || ! self::placed( $skills[ $other ] ) ) {
					continue;
				}
				$before = $skills[ $other ];
				$to     = max( (int) $before['grade_from'], (int) ( $before['grade_to'] ?? 0 ) );
				if ( (int) $before['grade_from'] > $from ) {
					$issues[] = self::issue( 'later_prerequisite', $id, $other, $from );
				} elseif ( $from - $to >= self::LONG_GAP ) {
					$issues[] = self::issue( 'long_gap', $id, $other, $from );
				}
			}
			if ( ! $prerequisites && $from > $first[ $skill['domain'] ] ) {
				$issues[] = self::issue( 'no_prerequisites', $id, 0, $from );
			}
		}
		$rank = array_flip( array( 'later_prerequisite', 'long_gap', 'no_prerequisites' ) );
		usort(
			$issues,
			static function ( $left, $right ) use ( $rank ) {
				return array( $rank[ $left['type'] ], $left['grade'], $left['skill'] ) <=> array( $rank[ $right['type'] ], $right['grade'], $right['skill'] );
			}
		);
		foreach ( $issues as &$issue ) {
			unset( $issue['grade'] );
		}
		return $issues;
	}

	/**
	 * Every prerequisite a skill builds on, however far back, and every skill that builds on it.
	 *
	 * @param array[] $skills Skills keyed by ID.
	 * @param int     $id     Skill.
	 * @return array{before:int[],after:int[]}
	 */
	public static function chain( array $skills, $id ) {
		$needed_by = array();
		foreach ( $skills as $skill_id => $skill ) {
			foreach ( $skill['prerequisites'] ?? array() as $other ) {
				$needed_by[ (int) $other ][] = (int) $skill_id;
			}
		}
		$walk   = static function ( $start, $next ) {
			$seen  = array();
			$queue = $next( $start );
			while ( $queue ) {
				$current = (int) array_shift( $queue );
				if ( isset( $seen[ $current ] ) || $current === (int) $start ) {
					continue;
				}
				$seen[ $current ] = true;
				$queue            = array_merge( $queue, $next( $current ) );
			}
			return array_keys( $seen );
		};
		$before = $walk(
			(int) $id,
			static function ( $skill ) use ( $skills ) {
				return array_map( 'intval', $skills[ $skill ]['prerequisites'] ?? array() );
			}
		);
		$after  = $walk(
			(int) $id,
			static function ( $skill ) use ( $needed_by ) {
				return $needed_by[ $skill ] ?? array();
			}
		);
		sort( $before );
		sort( $after );
		return array(
			'before' => $before,
			'after'  => $after,
		);
	}

	/**
	 * Compare two syllabuses through the shared skills their objectives map onto.
	 *
	 * @param int[]                        $a        Objective IDs of syllabus A, in order.
	 * @param int[]                        $b        Objective IDs of syllabus B, in order.
	 * @param array<int,array<int,string>> $mappings Objective => shared skill => relation.
	 * @param array<int,array>             $skills   Shared skills keyed by ID (domain, grade_from, grade_to).
	 * @return array rows (skill, a and b: objective => relation), both/only_a/only_b (skill IDs), unmapped_a/unmapped_b (objective IDs), overlap (0-100), domains (per domain: domain, both, only_a, only_b).
	 */
	public static function compare( array $a, array $b, array $mappings, array $skills ) {
		$rows    = array();
		$missing = array(
			'a' => array(),
			'b' => array(),
		);
		foreach ( array(
			'a' => $a,
			'b' => $b,
		) as $side => $objectives ) {
			foreach ( array_values( array_unique( array_map( 'intval', $objectives ) ) ) as $objective ) {
				$covered = false;
				foreach ( $mappings[ $objective ] ?? array() as $skill => $relation ) {
					if ( ! in_array( $relation, self::COVERING, true ) ) {
						continue;
					}
					$covered = true;
					if ( ! isset( $rows[ $skill ] ) ) {
						$rows[ $skill ] = array(
							'skill' => (int) $skill,
							'a'     => array(),
							'b'     => array(),
						);
					}
					$rows[ $skill ][ $side ][ $objective ] = $relation;
				}
				if ( ! $covered ) {
					$missing[ $side ][] = $objective;
				}
			}
		}
		$sort = static function ( $skill ) use ( $skills ) {
			$info  = $skills[ $skill ] ?? array();
			$grade = (int) ( $info['grade_from'] ?? 0 );
			return array( (string) ( $info['domain'] ?? '~' ), $grade ? $grade : 99, (int) $skill );
		};
		uksort(
			$rows,
			static function ( $left, $right ) use ( $sort ) {
				return $sort( $left ) <=> $sort( $right );
			}
		);
		$result  = array(
			'rows'       => array_values( $rows ),
			'both'       => array(),
			'only_a'     => array(),
			'only_b'     => array(),
			'unmapped_a' => $missing['a'],
			'unmapped_b' => $missing['b'],
			'overlap'    => 0,
			'domains'    => array(),
		);
		$domains = array();
		foreach ( $rows as $skill => $row ) {
			$bucket = $row['a'] && $row['b'] ? 'both' : ( $row['a'] ? 'only_a' : 'only_b' );
			$domain = (string) ( $skills[ $skill ]['domain'] ?? '' );
			if ( ! isset( $domains[ $domain ] ) ) {
				$domains[ $domain ] = array(
					'domain' => $domain,
					'both'   => 0,
					'only_a' => 0,
					'only_b' => 0,
				);
			}
			$result[ $bucket ][] = (int) $skill;
			++$domains[ $domain ][ $bucket ];
		}
		if ( $rows ) {
			$result['overlap'] = (int) round( 100 * count( $result['both'] ) / count( $rows ) );
		}
		$result['domains'] = array_values( $domains );
		return $result;
	}

	/**
	 * Whether a skill has a domain and a grade.
	 *
	 * @param array $skill Skill.
	 * @return bool
	 */
	private static function placed( array $skill ) {
		return '' !== (string) ( $skill['domain'] ?? '' ) && (int) ( $skill['grade_from'] ?? 0 ) > 0;
	}

	/**
	 * One issue, with the grade it sorts by.
	 *
	 * @param string $type  Issue type.
	 * @param int    $skill Skill.
	 * @param int    $other Prerequisite, or 0.
	 * @param int    $grade Skill's first grade.
	 * @return array
	 */
	private static function issue( $type, $skill, $other, $grade ) {
		return array(
			'type'  => $type,
			'skill' => (int) $skill,
			'other' => (int) $other,
			'grade' => (int) $grade,
		);
	}
}
