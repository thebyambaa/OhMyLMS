<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Which class of a school (or of the site) is furthest behind the other classes on one skill.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Performance\Snapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Compares classes on skill levels for the "class behind" insight. Levels come from
 * `Performance\Snapshot::students()` (the shared read of `student_skill_state`); this class only picks
 * which skills to compare (those the most learners of the scope have started). Only for a school or
 * `all` scope with at least two active classes: a class scope has nothing to compare with.
 *
 * Points per level: attempted 25, familiar 50, proficient 80, mastered 100 (not started is not counted).
 */
final class ClassSkillGaps {

	const POINTS = array(
		'attempted'  => 25,
		'familiar'   => 50,
		'proficient' => 80,
		'mastered'   => 100,
	);

	/**
	 * The largest gap in the scope.
	 *
	 * @param Scope $scope Scope.
	 * @param array $rule  min_points, min_learners, max_classes, max_learners, max_skills.
	 * @return array|null See {@see ClassSkillGaps::compare()}, plus class_name and skill.
	 */
	public static function largest( Scope $scope, array $rule ) {
		if ( Scope::CLASSROOM === $scope->type() || ! AssessmentSchema::ready() ) {
			return null;
		}
		$classes = Directory::classes( $scope->is_all() ? null : array( $scope->id() ), $scope->is_all() );
		$classes = array_slice( $classes, 0, max( 2, (int) $rule['max_classes'] ) );
		if ( count( $classes ) < 2 ) {
			return null;
		}
		$rosters  = array();
		$names    = array();
		$learners = array();
		foreach ( $classes as $class ) {
			$id             = (int) $class['id'];
			$rosters[ $id ] = array_slice( Directory::class_learners( $id ), 0, (int) $rule['max_learners'] );
			$names[ $id ]   = (string) $class['name'];
			$learners       = array_merge( $learners, $rosters[ $id ] );
		}
		$learners = array_slice( array_values( array_unique( $learners ) ), 0, (int) $rule['max_learners'] );
		$terms    = self::started_skills( $learners, (int) $rule['max_skills'], 2 * (int) $rule['min_learners'] );
		if ( ! $terms ) {
			return null;
		}
		$states = Snapshot::students( $learners, $terms );
		$levels = array();
		foreach ( $rosters as $class => $roster ) {
			foreach ( $roster as $learner ) {
				foreach ( $states[ $learner ] ?? array() as $term => $entry ) {
					$levels[ $class ][ $learner ][ $term ] = (string) $entry['key'];
				}
			}
		}
		$gap = self::compare( $levels, $rule );
		if ( null === $gap ) {
			return null;
		}
		$term             = get_term( $gap['term_id'] );
		$gap['class']     = $names[ $gap['class_id'] ] ?? '';
		$gap['skill']     = $term instanceof \WP_Term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : '';
		$gap['school_id'] = $scope->school_id();
		return '' === $gap['skill'] ? null : $gap;
	}

	/**
	 * The class and skill with the largest gap against the other classes. Pure.
	 *
	 * @param array<int,array<int,array<int,string>>> $levels Class id => learner id => term id => level.
	 * @param array                                   $rule   min_points, min_learners.
	 * @return array{class_id:int,term_id:int,class_points:int,others_points:int,gap:int,class_learners:int,others_learners:int}|null
	 */
	public static function compare( array $levels, array $rule ) {
		$min_learners = max( 1, (int) $rule['min_learners'] );
		$min_points   = max( 1, (int) $rule['min_points'] );
		$best         = null;
		$terms        = array();
		foreach ( $levels as $learners ) {
			foreach ( $learners as $skills ) {
				$terms += array_fill_keys( array_keys( $skills ), true );
			}
		}
		$terms = array_keys( $terms );
		sort( $terms );
		$classes = array_keys( $levels );
		sort( $classes );
		foreach ( $terms as $term ) {
			foreach ( $classes as $class ) {
				$mine   = self::points( $levels[ $class ], $term );
				$others = array();
				foreach ( $levels as $other => $learners ) {
					if ( $other === $class ) {
						continue;
					}
					foreach ( $learners as $learner => $skills ) {
						if ( ! isset( $levels[ $class ][ $learner ] ) && isset( self::POINTS[ $skills[ $term ] ?? '' ] ) ) {
							$others[ $learner ] = self::POINTS[ $skills[ $term ] ];
						}
					}
				}
				if ( count( $mine ) < $min_learners || count( $others ) < $min_learners ) {
					continue;
				}
				$class_points  = (int) round( array_sum( $mine ) / count( $mine ) );
				$others_points = (int) round( array_sum( $others ) / count( $others ) );
				$gap           = $others_points - $class_points;
				if ( $gap >= $min_points && ( null === $best || $gap > $best['gap'] ) ) {
					$best = array(
						'class_id'        => (int) $class,
						'term_id'         => (int) $term,
						'class_points'    => $class_points,
						'others_points'   => $others_points,
						'gap'             => $gap,
						'class_learners'  => count( $mine ),
						'others_learners' => count( $others ),
					);
				}
			}
		}
		return $best;
	}

	/**
	 * Points of the learners of one class who started a skill.
	 *
	 * @param array<int,array<int,string>> $learners Learner id => term id => level.
	 * @param int                          $term     Skill.
	 * @return int[]
	 */
	private static function points( array $learners, $term ) {
		$points = array();
		foreach ( $learners as $skills ) {
			$level = $skills[ $term ] ?? '';
			if ( isset( self::POINTS[ $level ] ) ) {
				$points[] = self::POINTS[ $level ];
			}
		}
		return $points;
	}

	/**
	 * Skills the most of some learners have started.
	 *
	 * @param int[] $learners Learners.
	 * @param int   $limit    Most skills.
	 * @param int   $min      Fewest learners per skill.
	 * @return int[]
	 * @throws \RuntimeException On a database error.
	 */
	private static function started_skills( array $learners, $limit, $min ) {
		global $wpdb;
		if ( ! $learners ) {
			return array();
		}
		$args   = array( AssessmentSchema::table( 'student_skill_state' ) );
		$in     = Scope::in_condition( 'student_id', $learners, $args );
		$args[] = max( 1, (int) $min );
		$args[] = max( 1, (int) $limit );
		$sql    = "SELECT term_id FROM %i WHERE level <> 'not-started' AND $in GROUP BY term_id HAVING COUNT( DISTINCT student_id ) >= %d ORDER BY COUNT( DISTINCT student_id ) DESC, term_id ASC LIMIT %d";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders, id lists come from Scope::in_condition().
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $args ) );
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Dashboard query failed.' );
		}
		return array_map( 'intval', (array) $ids );
	}
}
