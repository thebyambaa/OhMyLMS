<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The pure part of the guidance engine: everything here is arithmetic over plain arrays.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * Guidance read from skill states, the shared skill library and its links. No WordPress calls, so
 * every rule can be checked in isolation (tests/php/performance-unit.php).
 *
 * Skills are first pooled: a syllabus objective with an `equivalent` mapping counts as its shared
 * skill, and the pooled skill takes the best level any of its members reached. All guidance is then
 * read from the pooled skills:
 *
 *  - areas: strength per domain, from the levels of the skills started in it;
 *  - reviews: proficient skills whose review is due, minus the credit earned by later work on skills
 *    that include them ("encompasses" links, weights multiplied along a chain);
 *  - ready: skills not started whose prerequisites are all proficient or mastered, plus, for a
 *    learner whose grade is known, the skills of that grade that need nothing first (so a new learner
 *    who skipped the placement test still has somewhere to start);
 *  - readiness: per syllabus, how much of its objectives the learner already has;
 *  - next steps: help with struggling skills, due reviews, skills to keep going with, then skills ready
 *    to learn (none while three skills are already in progress);
 *  - session: the next steps that fit one sitting of about fifteen minutes.
 */
final class GuidanceModel {

	/** How much each level counts toward an area's strength (0 to 1). */
	const STRENGTH_POINTS = array(
		'attempted'  => 0.2,
		'familiar'   => 0.5,
		'proficient' => 0.8,
		'mastered'   => 1.0,
	);

	/** How much each level counts toward readiness for a syllabus: proficient work is ready. */
	const READY_POINTS = array(
		'attempted'  => 0.2,
		'familiar'   => 0.5,
		'proficient' => 1.0,
		'mastered'   => 1.0,
	);

	/** Longest chain of "encompasses" links that still earns review credit. */
	const MAX_DEPTH = 3;

	/** Credit below this is ignored, so tiny weights never show as "covered". */
	const MIN_CREDIT = 0.05;

	/** Skills in progress (started, not yet proficient) before no new skills are suggested. */
	const WIP_LIMIT = 3;

	/** Rough minutes one step of each kind takes, for sizing a session. */
	const STEP_MINUTES = array(
		'help'     => 5,
		'review'   => 3,
		'continue' => 5,
		'ready'    => 5,
	);

	/** A session: about this many minutes, with this many steps at least and at most. */
	const SESSION_MINUTES = 15;
	const SESSION_MIN     = 3;
	const SESSION_MAX     = 5;

	/**
	 * Pool states onto shared skills.
	 *
	 * @param array<int,array> $states Term => level, last_evidence_at (timestamp or null), struggling.
	 * @param array<int,int>   $pool   Term => shared skill it is equivalent to (missing: the term itself).
	 * @return array<int,array> Skill => level, last (timestamp or null), struggling, terms (member terms, best first).
	 */
	public static function pooled( array $states, array $pool ) {
		$result = array();
		foreach ( $states as $term => $state ) {
			$term  = (int) $term;
			$key   = (int) ( $pool[ $term ] ?? $term );
			$level = (string) ( $state['level'] ?? 'not-started' );
			$last  = isset( $state['last_evidence_at'] ) ? (int) $state['last_evidence_at'] : null;
			if ( ! isset( $result[ $key ] ) ) {
				$result[ $key ] = array(
					'level'      => 'not-started',
					'last'       => null,
					'struggling' => false,
					'terms'      => array(),
				);
			}
			$entry = &$result[ $key ];
			if ( Levels::rank( $level ) > Levels::rank( $entry['level'] ) ) {
				$entry['level'] = $level;
				array_unshift( $entry['terms'], $term );
			} else {
				$entry['terms'][] = $term;
			}
			if ( null !== $last && ( null === $entry['last'] || $last > $entry['last'] ) ) {
				$entry['last'] = $last;
			}
			// A struggle only counts while the pooled skill is still developing.
			$entry['struggling'] = $entry['struggling'] || ! empty( $state['struggling'] );
			unset( $entry );
		}
		foreach ( $result as &$entry ) {
			$entry['struggling'] = $entry['struggling'] && Levels::is_developing( $entry['level'] );
		}
		return $result;
	}

	/**
	 * Strength per domain.
	 *
	 * @param array<int,array> $pooled Pooled skills.
	 * @param array<int,array> $info   Skill => domain, grade_from, grade_to.
	 * @return array[] Each: domain, strength (0-100), started, counts per level, grade (highest grade reached at proficient or above, 0 when none), sorted strongest first.
	 */
	public static function areas( array $pooled, array $info ) {
		$areas = array();
		foreach ( $pooled as $skill => $entry ) {
			$domain = (string) ( $info[ $skill ]['domain'] ?? '' );
			if ( '' === $domain || ! isset( self::STRENGTH_POINTS[ $entry['level'] ] ) ) {
				continue;
			}
			if ( ! isset( $areas[ $domain ] ) ) {
				$areas[ $domain ] = array(
					'domain'  => $domain,
					'points'  => 0.0,
					'started' => 0,
					'counts'  => array_fill_keys( array_keys( self::STRENGTH_POINTS ), 0 ),
					'grade'   => 0,
				);
			}
			$area            = &$areas[ $domain ];
			$area['points'] += self::STRENGTH_POINTS[ $entry['level'] ];
			++$area['started'];
			++$area['counts'][ $entry['level'] ];
			if ( Levels::is_proficient( $entry['level'] ) ) {
				$area['grade'] = max( $area['grade'], (int) ( $info[ $skill ]['grade_to'] ?? 0 ) );
			}
			unset( $area );
		}
		$result = array();
		foreach ( $areas as $area ) {
			$area['strength'] = (int) round( 100 * $area['points'] / $area['started'] );
			unset( $area['points'] );
			$result[] = $area;
		}
		usort(
			$result,
			static function ( $left, $right ) {
				return array( $right['strength'], $right['started'], $left['domain'] ) <=> array( $left['strength'], $left['started'], $right['domain'] );
			}
		);
		return $result;
	}

	/**
	 * Name an area's strength.
	 *
	 * @param int $strength 0 to 100.
	 * @return string strong, steady or building.
	 */
	public static function strength_band( $strength ) {
		if ( $strength >= 75 ) {
			return 'strong';
		}
		return $strength >= 45 ? 'steady' : 'building';
	}

	/**
	 * Review state of each proficient skill, with credit from later work on skills that include it.
	 *
	 * A skill B is due for review `$days` after its last evidence. Each skill A worked after that,
	 * at familiar or above and not struggling, that includes B (directly or through a chain of
	 * "encompasses" links, weights multiplied) adds its weight to B's credit, capped at 1. Credit
	 * moves the review later by that share of the interval: full credit counts as a whole review.
	 *
	 * @param array<int,array>            $pooled      Pooled skills.
	 * @param array<int,array<int,float>> $encompasses Skill => included skill => weight.
	 * @param int                         $now         Timestamp.
	 * @param int                         $days        Review interval in days.
	 * @return array<int,array> Skill => status (fresh, covered or due), due_at, effective_due_at, credit (0-1), covered_by (skill => weight).
	 */
	public static function reviews( array $pooled, array $encompasses, $now, $days ) {
		$interval = max( 1, (int) $days ) * DAY_IN_SECONDS;
		$reach    = self::reach( $encompasses );
		$result   = array();
		foreach ( $pooled as $skill => $entry ) {
			if ( ! Levels::is_proficient( $entry['level'] ) || null === $entry['last'] ) {
				continue;
			}
			$credit = 0.0;
			$by     = array();
			foreach ( $reach as $broader => $inside ) {
				if ( ! isset( $inside[ $skill ] ) || ! isset( $pooled[ $broader ] ) ) {
					continue;
				}
				$other = $pooled[ $broader ];
				if ( null === $other['last'] || $other['last'] <= $entry['last'] || Levels::rank( $other['level'] ) < Levels::rank( 'familiar' ) || $other['struggling'] ) {
					continue;
				}
				$by[ $broader ] = round( $inside[ $skill ], 3 );
				$credit        += $inside[ $skill ];
			}
			$credit = $credit < self::MIN_CREDIT ? 0.0 : min( 1.0, round( $credit, 3 ) );
			if ( ! $credit ) {
				$by = array();
			}
			arsort( $by );
			$due       = $entry['last'] + $interval;
			$effective = $due + (int) round( $credit * $interval );
			$status    = 'fresh';
			if ( $now >= $effective ) {
				$status = 'due';
			} elseif ( $now >= $due ) {
				$status = 'covered';
			}
			$result[ $skill ] = array(
				'status'           => $status,
				'due_at'           => $due,
				'effective_due_at' => $effective,
				'credit'           => $credit,
				'covered_by'       => $by,
			);
		}
		return $result;
	}

	/**
	 * Skills not started whose prerequisites are all proficient or mastered.
	 *
	 * A placement test can add to this: skills it placed as known count as met prerequisites (they
	 * are not levels, so they are never suggested themselves), and the skills it said to start with
	 * come first, even when they need nothing first.
	 *
	 * @param array<int,array> $pooled    Pooled skills.
	 * @param array<int,array> $info      Skill => prerequisites (skill IDs), domain, grade_from.
	 * @param array[]          $areas     Areas, so weaker domains come first among skills of the same grade.
	 * @param int[]            $known     Skills a placement test placed as known.
	 * @param int[]            $preferred Skills a placement test said to start with.
	 * @param int              $grade     The learner's grade (0 when unknown): skills of that grade that need nothing first are ready too.
	 * @param array<int,int[]> $clears    Skill => due reviews that practising it would also cover.
	 * @return array[] Each: skill, prerequisites, placement (bool), reviews (due reviews it also covers), ordered placement first, then by grade (earliest first), skills that also cover reviews, weaker area, then ID.
	 */
	public static function ready( array $pooled, array $info, array $areas = array(), array $known = array(), array $preferred = array(), $grade = 0, array $clears = array() ) {
		$strength  = array_column( $areas, 'strength', 'domain' );
		$known     = array_flip( array_map( 'intval', $known ) );
		$preferred = array_flip( array_map( 'intval', $preferred ) );
		$grade     = (int) $grade;
		$result    = array();
		foreach ( $info as $skill => $details ) {
			$prerequisites = array_values( array_map( 'intval', $details['prerequisites'] ?? array() ) );
			$placement     = isset( $preferred[ $skill ] );
			$from          = (int) ( $details['grade_from'] ?? 0 );
			// A root skill of the learner's own grade is a place to start, with or without a placement test.
			$at_grade = $grade && $from && $from <= $grade && $grade <= max( $from, (int) ( $details['grade_to'] ?? 0 ) );
			if ( ( ! $prerequisites && ! $placement && ! $at_grade ) || isset( $pooled[ $skill ] ) || isset( $known[ $skill ] ) ) {
				continue;
			}
			foreach ( $prerequisites as $prerequisite ) {
				$met = isset( $known[ $prerequisite ] ) || ( isset( $pooled[ $prerequisite ] ) && Levels::is_proficient( $pooled[ $prerequisite ]['level'] ) );
				if ( ! $met ) {
					continue 2;
				}
			}
			$also     = array_values( array_map( 'intval', $clears[ $skill ] ?? array() ) );
			$result[] = array(
				'skill'         => (int) $skill,
				'prerequisites' => $prerequisites,
				'placement'     => $placement,
				'reviews'       => $also,
				'sort'          => array( $placement ? 0 : 1, $from ? $from : 99, -count( $also ), $strength[ $details['domain'] ?? '' ] ?? 100, (int) $skill ),
			);
		}
		usort(
			$result,
			static function ( $left, $right ) {
				return $left['sort'] <=> $right['sort'];
			}
		);
		foreach ( $result as &$item ) {
			unset( $item['sort'] );
		}
		return $result;
	}

	/**
	 * How ready a learner is for a syllabus.
	 *
	 * @param int[]            $objectives Objective terms in syllabus order.
	 * @param array<int,array> $pooled     Pooled skills.
	 * @param array<int,int>   $pool       Term => shared skill it is equivalent to.
	 * @param int              $gaps       How many objectives still to learn to list.
	 * @return array percent (0-100), total, ready, learning, not_started, gaps (objective IDs not yet ready, started ones first).
	 */
	public static function readiness( array $objectives, array $pooled, array $pool, $gaps = 3 ) {
		$result  = array(
			'percent'     => 0,
			'total'       => 0,
			'ready'       => 0,
			'learning'    => 0,
			'not_started' => 0,
			'gaps'        => array(),
		);
		$points  = 0.0;
		$started = array();
		$fresh   = array();
		foreach ( array_values( array_unique( array_map( 'intval', $objectives ) ) ) as $term ) {
			$key   = (int) ( $pool[ $term ] ?? $term );
			$level = $pooled[ $key ]['level'] ?? 'not-started';
			++$result['total'];
			$points += self::READY_POINTS[ $level ] ?? 0.0;
			if ( Levels::is_proficient( $level ) ) {
				++$result['ready'];
			} elseif ( Levels::is_developing( $level ) ) {
				++$result['learning'];
				$started[] = $term;
			} else {
				++$result['not_started'];
				$fresh[] = $term;
			}
		}
		if ( $result['total'] ) {
			$result['percent'] = (int) floor( 100 * $points / $result['total'] );
		}
		$result['gaps'] = array_slice( array_merge( $started, $fresh ), 0, max( 0, (int) $gaps ) );
		return $result;
	}

	/**
	 * Next steps, most useful first: help, due reviews (longest overdue first), skills to keep going
	 * with (started, not struggling, not yet proficient; most recently worked first), then ready
	 * skills. A learner with WIP_LIMIT skills in progress is not offered new ones: finishing what was
	 * started comes first.
	 *
	 * @param array<int,array> $pooled  Pooled skills.
	 * @param array<int,array> $reviews Review states.
	 * @param array[]          $ready   Ready skills, already ordered.
	 * @param int              $limit   How many.
	 * @return array[] Each: type (help, review, continue or ready), skill, minutes, and for reviews credit and covered_by; for ready, prerequisites, reviews (due reviews it also covers) and reason (placement when a placement test suggested it).
	 */
	public static function next_steps( array $pooled, array $reviews, array $ready, $limit = 6 ) {
		$help = array();
		foreach ( $pooled as $skill => $entry ) {
			if ( $entry['struggling'] ) {
				$help[] = array(
					'type'  => 'help',
					'skill' => (int) $skill,
					'last'  => (int) $entry['last'],
				);
			}
		}
		usort(
			$help,
			static function ( $left, $right ) {
				return $right['last'] <=> $left['last'];
			}
		);
		$due = array();
		foreach ( $reviews as $skill => $review ) {
			if ( 'due' === $review['status'] ) {
				$due[] = array(
					'type'       => 'review',
					'skill'      => (int) $skill,
					'credit'     => $review['credit'],
					'covered_by' => $review['covered_by'],
					'due'        => $review['effective_due_at'],
				);
			}
		}
		usort(
			$due,
			static function ( $left, $right ) {
				return $left['due'] <=> $right['due'];
			}
		);
		$continue    = array();
		$in_progress = 0;
		foreach ( $pooled as $skill => $entry ) {
			if ( ! Levels::is_developing( $entry['level'] ) ) {
				continue;
			}
			++$in_progress;
			if ( ! $entry['struggling'] ) {
				$continue[] = array(
					'type'  => 'continue',
					'skill' => (int) $skill,
					'last'  => (int) $entry['last'],
				);
			}
		}
		usort(
			$continue,
			static function ( $left, $right ) {
				return array( $right['last'], $left['skill'] ) <=> array( $left['last'], $right['skill'] );
			}
		);
		$items = array();
		foreach ( $help as $item ) {
			unset( $item['last'] );
			$items[] = $item;
		}
		foreach ( $due as $item ) {
			unset( $item['due'] );
			$items[] = $item;
		}
		foreach ( $continue as $item ) {
			unset( $item['last'] );
			$items[] = $item;
		}
		if ( $in_progress < self::WIP_LIMIT ) {
			foreach ( $ready as $item ) {
				$items[] = array(
					'type'          => 'ready',
					'skill'         => (int) $item['skill'],
					'prerequisites' => $item['prerequisites'],
					'reviews'       => array_values( (array) ( $item['reviews'] ?? array() ) ),
					'reason'        => empty( $item['placement'] ) ? '' : 'placement',
				);
			}
		}
		foreach ( $items as &$item ) {
			$item['minutes'] = self::STEP_MINUTES[ $item['type'] ];
		}
		unset( $item );
		return array_slice( $items, 0, max( 0, (int) $limit ) );
	}

	/**
	 * The next steps that fit one sitting: steps in order while they fit in SESSION_MINUTES, never
	 * fewer than SESSION_MIN (when there are that many) nor more than SESSION_MAX.
	 *
	 * @param array[] $steps   From next_steps().
	 * @param int     $minutes Minutes available.
	 * @return array[] The session's steps.
	 */
	public static function session( array $steps, $minutes = self::SESSION_MINUTES ) {
		$result = array();
		$used   = 0;
		foreach ( $steps as $step ) {
			$cost = (int) ( $step['minutes'] ?? self::STEP_MINUTES[ $step['type'] ] ?? 5 );
			if ( count( $result ) >= self::SESSION_MAX || ( count( $result ) >= self::SESSION_MIN && $used + $cost > $minutes ) ) {
				break;
			}
			$result[] = $step;
			$used    += $cost;
		}
		return $result;
	}

	/**
	 * Due reviews that practising each skill would also cover: skill => the due skills it includes,
	 * directly or along a chain.
	 *
	 * @param array<int,array>            $reviews     Review states.
	 * @param array<int,array<int,float>> $encompasses Skill => included skill => weight.
	 * @return array<int,int[]>
	 */
	public static function clears( array $reviews, array $encompasses ) {
		$due = array();
		foreach ( $reviews as $skill => $review ) {
			if ( 'due' === $review['status'] ) {
				$due[ (int) $skill ] = true;
			}
		}
		$result = array();
		foreach ( self::reach( $encompasses ) as $skill => $inside ) {
			$covered = array_values( array_map( 'intval', array_keys( array_intersect_key( $inside, $due ) ) ) );
			if ( $covered ) {
				$result[ (int) $skill ] = $covered;
			}
		}
		return $result;
	}

	/**
	 * Every skill each skill includes, through chains of up to MAX_DEPTH links, with the best weight.
	 *
	 * @param array<int,array<int,float>> $encompasses Skill => included skill => weight.
	 * @return array<int,array<int,float>> Skill => included skill => weight (product along the best chain).
	 */
	public static function reach( array $encompasses ) {
		$result = array();
		foreach ( array_keys( $encompasses ) as $start ) {
			$best     = array();
			$frontier = array( (int) $start => 1.0 );
			for ( $depth = 0; $depth < self::MAX_DEPTH && $frontier; $depth++ ) {
				$next = array();
				foreach ( $frontier as $from => $carried ) {
					foreach ( $encompasses[ $from ] ?? array() as $to => $weight ) {
						$to    = (int) $to;
						$value = $carried * (float) $weight;
						if ( $to === (int) $start || $value < self::MIN_CREDIT || $value <= ( $best[ $to ] ?? 0.0 ) ) {
							continue;
						}
						$best[ $to ] = $value;
						$next[ $to ] = $value;
					}
				}
				$frontier = $next;
			}
			if ( $best ) {
				$result[ (int) $start ] = $best;
			}
		}
		return $result;
	}
}
