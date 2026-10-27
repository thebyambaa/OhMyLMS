<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The pure part of the adaptive placement test: what the answers so far say about every skill, which
 * skill to ask about next, and the placement that results.
 *
 * @package OhMyLMS\Placement
 */

namespace OhMyLMS\Placement;

defined( 'ABSPATH' ) || exit;

/**
 * Knowledge is estimated per shared skill as a probability that the learner already has it. The
 * prerequisite links carry each answer through the subject: getting a skill right makes everything
 * it builds on likely known, and missing one makes everything built on it likely unknown. Within a
 * domain, grades do the same more weakly, so a subject with few links still places sensibly.
 *
 * A skill is an array with domain, grade_from, grade_to and prerequisites (skill IDs in the same
 * subject). An observation is skill and outcome: right, wrong or unknown ("I have not learned this").
 * No WordPress calls: tests/php/placement-unit.php checks every rule.
 */
final class PlacementModel {

	/** At or above this the skill is placed as known. */
	const KNOWN = 0.7;

	/** At or below this the skill is placed as not known yet. */
	const UNKNOWN = 0.3;

	/** A skill closer to 0 or 1 than this margin is not worth a question. */
	const WORTH_ASKING = 0.2;

	/** Questions in a test by default, and the bounds a site or learner may choose within. */
	const DEFAULT_LIMIT = 15;
	const MIN_LIMIT     = 5;
	const MAX_LIMIT     = 30;

	/** Share of a domain's skills up to a grade that must be known to place the learner at that grade. */
	const GRADE_SHARE = 0.7;

	/**
	 * Starting estimate from the grade the learner says they are in (0 when they are not sure).
	 *
	 * @param array $skill Skill.
	 * @param int   $grade Stated grade.
	 * @return float
	 */
	public static function prior( array $skill, $grade ) {
		$grade = (int) $grade;
		$from  = (int) ( $skill['grade_from'] ?? 0 );
		if ( ! $grade || ! $from ) {
			return 0.5;
		}
		$to = max( $from, (int) ( $skill['grade_to'] ?? 0 ) );
		if ( $to < $grade ) {
			return $to < $grade - 1 ? 0.85 : 0.75;
		}
		if ( $from > $grade ) {
			return $from > $grade + 1 ? 0.15 : 0.25;
		}
		return 0.5;
	}

	/**
	 * The estimate for every skill after the observations, in order.
	 *
	 * @param array<int,array> $skills       Skills keyed by ID.
	 * @param int              $grade        Stated grade, or 0.
	 * @param array[]          $observations Each: skill, outcome.
	 * @return array<int,float> Skill => probability the learner has it.
	 */
	public static function estimate( array $skills, $grade, array $observations ) {
		$p = array();
		foreach ( $skills as $id => $skill ) {
			$p[ $id ] = self::prior( $skill, $grade );
		}
		$graph  = self::graph( $skills );
		$direct = array();
		foreach ( $observations as $observation ) {
			$id      = (int) $observation['skill'];
			$outcome = (string) $observation['outcome'];
			if ( ! isset( $skills[ $id ] ) ) {
				continue;
			}
			$skill = $skills[ $id ];
			if ( 'right' === $outcome ) {
				foreach ( $graph['before'][ $id ] ?? array() as $other => $depth ) {
					$p[ $other ] = max( $p[ $other ], max( 0.8, round( 0.95 - 0.05 * $depth, 3 ) ) );
				}
				foreach ( self::same_domain( $skills, $skill, 'earlier' ) as $other ) {
					$p[ $other ] = max( $p[ $other ], 0.75 );
				}
				$direct[ $id ] = 0.95;
			} else {
				$floor = 'unknown' === $outcome ? 0.05 : 0.1;
				foreach ( $graph['after'][ $id ] ?? array() as $other => $depth ) {
					$p[ $other ] = min( $p[ $other ], min( 0.25, round( $floor + 0.05 * $depth, 3 ) ) );
				}
				foreach ( self::same_domain( $skills, $skill, 'later' ) as $other ) {
					$p[ $other ] = min( $p[ $other ], 'unknown' === $outcome ? 0.25 : 0.3 );
				}
				$direct[ $id ] = $floor;
			}
		}
		// What was asked directly stands, whatever later answers suggested.
		foreach ( $direct as $id => $value ) {
			$p[ $id ] = $value;
		}
		return $p;
	}

	/**
	 * The skill to ask about next: the most uncertain one that would tell the most about others,
	 * reaching every domain early.
	 *
	 * @param array<int,array> $skills   Skills keyed by ID.
	 * @param array<int,float> $p        Current estimate.
	 * @param int[]            $askable  Skills that have questions the learner may see.
	 * @param int[]            $asked    Skills already asked.
	 * @param int              $grade    Stated grade, or 0.
	 * @return int Skill ID, or 0 when nothing is worth asking.
	 */
	public static function next( array $skills, array $p, array $askable, array $asked, $grade ) {
		$graph   = self::graph( $skills );
		$asked   = array_flip( array_map( 'intval', $asked ) );
		$covered = array();
		foreach ( array_keys( $asked ) as $id ) {
			$covered[ (string) ( $skills[ $id ]['domain'] ?? '' ) ] = true;
		}
		$best  = 0;
		$score = null;
		foreach ( array_map( 'intval', $askable ) as $id ) {
			if ( isset( $asked[ $id ] ) || ! isset( $skills[ $id ], $p[ $id ] ) ) {
				continue;
			}
			$uncertainty = 1 - abs( 2 * $p[ $id ] - 1 );
			if ( abs( $p[ $id ] - 0.5 ) >= 0.5 - self::WORTH_ASKING ) {
				continue;
			}
			// Like a binary search: a skill that splits its open chain evenly settles the most either way.
			$open = array();
			foreach ( array( 'before', 'after' ) as $side ) {
				$open[ $side ] = 0;
				foreach ( array_keys( $graph[ $side ][ $id ] ?? array() ) as $other ) {
					if ( ! isset( $asked[ $other ] ) && abs( $p[ $other ] - 0.5 ) < 0.5 - self::WORTH_ASKING ) {
						++$open[ $side ];
					}
				}
			}
			$reach  = min( $open ) + 0.25 * max( $open );
			$domain = (string) $skills[ $id ]['domain'];
			$value  = $uncertainty * ( log( 2 + $reach ) + ( isset( $covered[ $domain ] ) ? 0 : 1 ) );
			$from   = (int) ( $skills[ $id ]['grade_from'] ?? 0 );
			$key    = array( round( $value, 6 ), -abs( ( $from ? $from : (int) $grade ) - (int) $grade ), -$id );
			if ( null === $score || $key > $score ) {
				$score = $key;
				$best  = $id;
			}
		}
		return $best;
	}

	/**
	 * The placement: each skill known, not known yet or unsure; a working grade per domain; and where
	 * to start.
	 *
	 * @param array<int,array> $skills Skills keyed by ID.
	 * @param array<int,float> $p      Final estimate.
	 * @param int              $starts Most skills to suggest starting with.
	 * @return array known, unknown, unsure (skill IDs), domains (domain, known, total, grade), start (skill IDs).
	 */
	public static function result( array $skills, array $p, $starts = 6 ) {
		$result  = array(
			'known'   => array(),
			'unknown' => array(),
			'unsure'  => array(),
			'domains' => array(),
			'start'   => array(),
		);
		$domains = array();
		foreach ( $skills as $id => $skill ) {
			$value              = $p[ $id ] ?? 0.5;
			$state              = $value >= self::KNOWN ? 'known' : ( $value <= self::UNKNOWN ? 'unknown' : 'unsure' );
			$result[ $state ][] = (int) $id;
			$domain             = (string) ( $skill['domain'] ?? '' );
			if ( '' !== $domain ) {
				$domains[ $domain ][] = array(
					'from'  => (int) ( $skill['grade_from'] ?? 0 ),
					'known' => 'known' === $state,
				);
			}
		}
		foreach ( $domains as $domain => $entries ) {
			$result['domains'][] = array(
				'domain' => $domain,
				'known'  => count( array_filter( array_column( $entries, 'known' ) ) ),
				'total'  => count( $entries ),
				'grade'  => self::working_grade( $entries ),
			);
		}
		usort(
			$result['domains'],
			static function ( $left, $right ) {
				return strcmp( $left['domain'], $right['domain'] );
			}
		);
		$candidates = array();
		foreach ( $skills as $id => $skill ) {
			if ( ( $p[ $id ] ?? 0.5 ) >= self::KNOWN ) {
				continue;
			}
			foreach ( $skill['prerequisites'] ?? array() as $other ) {
				if ( isset( $skills[ $other ] ) && ( $p[ $other ] ?? 0.5 ) < self::KNOWN ) {
					continue 2;
				}
			}
			$from         = (int) ( $skill['grade_from'] ?? 0 );
			$candidates[] = array( $from ? $from : 99, -( $p[ $id ] ?? 0.5 ), (int) $id );
		}
		sort( $candidates );
		$result['start'] = array_slice( array_column( $candidates, 2 ), 0, max( 0, (int) $starts ) );
		return $result;
	}

	/**
	 * A question limit within the allowed bounds.
	 *
	 * @param mixed $limit Requested limit.
	 * @return int
	 */
	public static function limit( $limit ) {
		$limit = (int) $limit;
		return $limit ? max( self::MIN_LIMIT, min( self::MAX_LIMIT, $limit ) ) : self::DEFAULT_LIMIT;
	}

	/**
	 * The highest grade at which enough of a domain's skills, up to and including that grade, are known.
	 *
	 * @param array[] $entries Each: from (grade), known.
	 * @return int 0 when none.
	 */
	private static function working_grade( array $entries ) {
		$grades = array_unique( array_filter( array_column( $entries, 'from' ) ) );
		sort( $grades );
		$placed = 0;
		foreach ( $grades as $grade ) {
			$upto  = array_filter(
				$entries,
				static function ( $entry ) use ( $grade ) {
					return $entry['from'] && $entry['from'] <= $grade;
				}
			);
			$known = count( array_filter( array_column( $upto, 'known' ) ) );
			if ( $known / count( $upto ) < self::GRADE_SHARE ) {
				break;
			}
			$placed = $grade;
		}
		return $placed;
	}

	/**
	 * Every skill each skill builds on and every skill built on it, with the number of links between.
	 *
	 * @param array<int,array> $skills Skills keyed by ID.
	 * @return array{before:array<int,array<int,int>>,after:array<int,array<int,int>>}
	 */
	private static function graph( array $skills ) {
		$up   = array();
		$down = array();
		foreach ( $skills as $id => $skill ) {
			foreach ( $skill['prerequisites'] ?? array() as $other ) {
				$other = (int) $other;
				if ( isset( $skills[ $other ] ) && $other !== (int) $id ) {
					$up[ (int) $id ][] = $other;
					$down[ $other ][]  = (int) $id;
				}
			}
		}
		$walk  = static function ( $start, array $edges ) {
			$depth = array();
			$queue = array( array( (int) $start, 0 ) );
			while ( $queue ) {
				list( $current, $level ) = array_shift( $queue );
				foreach ( $edges[ $current ] ?? array() as $next ) {
					if ( $next !== (int) $start && ! isset( $depth[ $next ] ) ) {
						$depth[ $next ] = $level + 1;
						$queue[]        = array( $next, $level + 1 );
					}
				}
			}
			return $depth;
		};
		$graph = array(
			'before' => array(),
			'after'  => array(),
		);
		foreach ( array_keys( $skills ) as $id ) {
			$graph['before'][ $id ] = $walk( $id, $up );
			$graph['after'][ $id ]  = $walk( $id, $down );
		}
		return $graph;
	}

	/**
	 * Skills of the same domain wholly before, or wholly after, a skill's grade band.
	 *
	 * @param array<int,array> $skills Skills keyed by ID.
	 * @param array            $skill  The observed skill.
	 * @param string           $side   earlier or later.
	 * @return int[]
	 */
	private static function same_domain( array $skills, array $skill, $side ) {
		$domain = (string) ( $skill['domain'] ?? '' );
		$from   = (int) ( $skill['grade_from'] ?? 0 );
		if ( '' === $domain || ! $from ) {
			return array();
		}
		$to     = max( $from, (int) ( $skill['grade_to'] ?? 0 ) );
		$result = array();
		foreach ( $skills as $id => $other ) {
			$other_from = (int) ( $other['grade_from'] ?? 0 );
			if ( (string) ( $other['domain'] ?? '' ) !== $domain || ! $other_from ) {
				continue;
			}
			$other_to = max( $other_from, (int) ( $other['grade_to'] ?? 0 ) );
			if ( ( 'earlier' === $side && $other_to < $from ) || ( 'later' === $side && $other_from > $to ) ) {
				$result[] = (int) $id;
			}
		}
		return $result;
	}
}
