<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Unit (chapter) grouping and comparison helpers for skill reports.
 *
 * Pure functions over plain arrays: a skill sits in the unit its program outcome or practice item
 * names (the program's chapter_id), with no schema change. Display levels are the Khan names from
 * Performance\Levels. Nothing here reads or writes the database.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Performance\Levels;

defined( 'ABSPATH' ) || exit;

/** Which unit a skill belongs to, and how learners and groups compare per skill. */
final class UnitReport {

	/** A staff-facing class average is only shown for at least this many learners, so one child is never identifiable. */
	const MIN_COHORT = 5;

	/**
	 * A guardian-facing class comparison needs at least this many classmates besides their own child.
	 * Larger than MIN_COHORT because the guardian already knows one value in the group and sees no counts.
	 */
	const MIN_PEERS = 8;

	/** Share of classmates at Proficient or above from which "Most" is shown, and below which "Few" is. */
	const BAND_MOST = 70;
	const BAND_FEW  = 30;

	/** Id of the group holding skills that no unit claims. */
	const OTHER = 0;

	/**
	 * Skill (term) ID => unit (chapter) ID from one learning program.
	 *
	 * Outcomes win, then practice items (their content_id is the skill), then items attached to
	 * chosen skills. A skill is kept in the first unit found, and skills without a unit are absent.
	 *
	 * @param array|null $program Learning program with outcomes and items, or null.
	 * @return array<int,int>
	 */
	public static function unit_map( $program ) {
		$map = array();
		if ( ! is_array( $program ) ) {
			return $map;
		}
		$claim = static function ( $term, $chapter ) use ( &$map ) {
			$term    = (int) $term;
			$chapter = (int) $chapter;
			if ( $term > 0 && $chapter > 0 && ! isset( $map[ $term ] ) ) {
				$map[ $term ] = $chapter;
			}
		};
		foreach ( (array) ( $program['outcomes'] ?? array() ) as $outcome ) {
			$claim( $outcome['term_id'] ?? 0, $outcome['chapter_id'] ?? 0 );
		}
		$items = (array) ( $program['items'] ?? array() );
		foreach ( $items as $item ) {
			if ( 'practice' === ( $item['type'] ?? '' ) ) {
				$claim( $item['content_id'] ?? 0, $item['chapter_id'] ?? 0 );
			}
		}
		foreach ( $items as $item ) {
			if ( 'practice' !== ( $item['type'] ?? '' ) ) {
				foreach ( (array) ( $item['skill_ids'] ?? array() ) as $term ) {
					$claim( $term, $item['chapter_id'] ?? 0 );
				}
			}
		}
		return $map;
	}

	/**
	 * Keep the skills sitting in one unit; a unit of 0 keeps everything.
	 *
	 * @param int[]          $term_ids Skill IDs, in order.
	 * @param array<int,int> $map Skill => unit.
	 * @param int            $chapter Unit ID, or 0 for all.
	 * @return int[]
	 */
	public static function filter( array $term_ids, array $map, $chapter ) {
		$chapter = (int) $chapter;
		if ( ! $chapter ) {
			return array_values( array_map( 'intval', $term_ids ) );
		}
		return array_values(
			array_filter(
				array_map( 'intval', $term_ids ),
				static function ( $term ) use ( $map, $chapter ) {
					return isset( $map[ $term ] ) && (int) $map[ $term ] === $chapter;
				}
			)
		);
	}

	/**
	 * Group skills under their unit headers, in unit order, with unmapped skills last.
	 *
	 * @param int[]                                $term_ids Skill IDs, in the order they should appear within a unit.
	 * @param array<int,int>                       $map Skill => unit.
	 * @param array<int,array{id:int,name:string}> $units Units in course order.
	 * @param string                               $other Header for skills without a unit.
	 * @return array<int,array{id:int,name:string,skills:int[]}> Empty groups are left out.
	 */
	public static function group( array $term_ids, array $map, array $units, $other = '' ) {
		$groups = array();
		foreach ( $units as $unit ) {
			$groups[ (int) $unit['id'] ] = array(
				'id'     => (int) $unit['id'],
				'name'   => (string) $unit['name'],
				'skills' => array(),
			);
		}
		$groups[ self::OTHER ] = array(
			'id'     => self::OTHER,
			'name'   => (string) $other,
			'skills' => array(),
		);
		foreach ( array_map( 'intval', $term_ids ) as $term ) {
			$unit = (int) ( $map[ $term ] ?? 0 );
			if ( ! isset( $groups[ $unit ] ) ) {
				$unit = self::OTHER;
			}
			$groups[ $unit ]['skills'][] = $term;
		}
		return array_values(
			array_filter(
				$groups,
				static function ( $group ) {
					return (bool) $group['skills'];
				}
			)
		);
	}

	/**
	 * Units with how many of the given skills sit in each, for a unit selector.
	 *
	 * @param array<int,array{id:int,name:string}> $units Units in course order.
	 * @param array<int,int>                       $map Skill => unit.
	 * @param int[]                                $term_ids Skills to count (all mapped skills when empty).
	 * @return array<int,array{id:int,name:string,skills:int}>
	 */
	public static function with_counts( array $units, array $map, array $term_ids = array() ) {
		$terms = $term_ids ? array_flip( array_map( 'intval', $term_ids ) ) : $map;
		$out   = array();
		foreach ( $units as $unit ) {
			$count = 0;
			foreach ( $map as $term => $chapter ) {
				if ( (int) $chapter === (int) $unit['id'] && isset( $terms[ $term ] ) ) {
					++$count;
				}
			}
			$out[] = array(
				'id'     => (int) $unit['id'],
				'name'   => (string) $unit['name'],
				'skills' => $count,
			);
		}
		return $out;
	}

	/**
	 * Average and distribution of display levels for one skill across a cohort.
	 *
	 * Learners who have not started count as Not started. Returns null below MIN_COHORT, so a small
	 * group never reveals an individual.
	 *
	 * @param string[] $keys Display level of each learner in the cohort.
	 * @return array{key:string,total:int,counts:array<string,int>,proficient_share:int}|null
	 */
	public static function average( array $keys ) {
		$total = count( $keys );
		if ( $total < self::MIN_COHORT ) {
			return null;
		}
		$counts = array_fill_keys( Levels::SCALE, 0 );
		$sum    = 0;
		$strong = 0;
		foreach ( $keys as $key ) {
			$key = isset( $counts[ $key ] ) ? $key : 'not-started';
			++$counts[ $key ];
			$sum += Levels::rank( $key );
			if ( Levels::is_proficient( $key ) ) {
				++$strong;
			}
		}
		return array(
			'key'              => Levels::SCALE[ (int) round( $sum / $total ) ],
			'total'            => $total,
			'counts'           => $counts,
			'proficient_share' => (int) round( 100 * $strong / $total ),
		);
	}

	/**
	 * Anonymous classmate summary for a guardian: the typical level and a coarse band, never counts or an exact share.
	 *
	 * Returns null below MIN_PEERS. The levels must be the classmates' only, without the guardian's own child.
	 *
	 * @param string[] $keys Display level of each classmate.
	 * @return array{key:string,band:string}|null Band is most, half or few: how many classmates are Proficient or above.
	 */
	public static function peer_average( array $keys ) {
		$total = count( $keys );
		if ( $total < self::MIN_PEERS ) {
			return null;
		}
		$sum    = 0;
		$strong = 0;
		foreach ( $keys as $key ) {
			$key  = in_array( $key, Levels::SCALE, true ) ? $key : 'not-started';
			$sum += Levels::rank( $key );
			if ( Levels::is_proficient( $key ) ) {
				++$strong;
			}
		}
		$share = 100 * $strong / $total;
		return array(
			'key'  => Levels::SCALE[ (int) round( $sum / $total ) ],
			'band' => $share >= self::BAND_MOST ? 'most' : ( $share < self::BAND_FEW ? 'few' : 'half' ),
		);
	}

	/**
	 * Side-by-side rows: one per skill, one cell per learner, optional class average per skill.
	 *
	 * @param int[]                          $term_ids Skills to show, in order.
	 * @param array<int,array<int,string>>   $levels Learner => skill => display level (missing = Not started).
	 * @param array<int,array<int,string[]>> $cohorts Optional learner => skill => levels of their classmates (not the learner).
	 * @return array<int,array{skill:int,cells:array<int,string>,class:array<int,array|null>}>
	 */
	public static function compare( array $term_ids, array $levels, array $cohorts = array() ) {
		$rows = array();
		foreach ( array_map( 'intval', $term_ids ) as $term ) {
			$cells = array();
			$class = array();
			foreach ( $levels as $learner => $by_skill ) {
				$cells[ $learner ] = $by_skill[ $term ] ?? 'not-started';
				if ( isset( $cohorts[ $learner ] ) ) {
					$class[ $learner ] = self::peer_average( $cohorts[ $learner ][ $term ] ?? array() );
				}
			}
			$rows[] = array(
				'skill' => $term,
				'cells' => $cells,
				'class' => $class,
			);
		}
		return $rows;
	}

	/**
	 * Skills any learner has started, ordered by name via the given names, for a comparison table.
	 *
	 * @param array<int,array<int,string>> $levels Learner => skill => display level.
	 * @param array<int,string>            $names Skill => name.
	 * @return int[]
	 */
	public static function started_skills( array $levels, array $names ) {
		$ids = array();
		foreach ( $levels as $by_skill ) {
			foreach ( $by_skill as $term => $key ) {
				if ( 'not-started' !== $key && isset( $names[ $term ] ) ) {
					$ids[ (int) $term ] = true;
				}
			}
		}
		$ids = array_keys( $ids );
		usort(
			$ids,
			static function ( $left, $right ) use ( $names ) {
				return strcmp( $names[ $left ], $names[ $right ] );
			}
		);
		return $ids;
	}

	/**
	 * Short text for a display level so colour is never the only signal.
	 *
	 * @param string $key Display level.
	 * @return string
	 */
	public static function short( $key ) {
		return array(
			'not-started' => '–',
			'attempted'   => 'A',
			'familiar'    => 'F',
			'proficient'  => 'P',
			'mastered'    => 'M',
		)[ $key ] ?? '–';
	}
}
