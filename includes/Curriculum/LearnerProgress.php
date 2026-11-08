<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * What one learner has reached in a subject, a course or a syllabus. Everything here only reads existing
 * records and keeps three things apart:.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Learning\PracticeAccessPolicy;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Practice\Selector;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Recommendations;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * What one learner has reached in a subject, a course or a syllabus. Everything here only reads existing
 * records and keeps three things apart:
 *
 *  - course progress: counts from the course's own completion rules, never merged into one score;
 *  - skill levels: the mastery engine's per-skill state, with "Not assessed" when no evidence exists;
 *  - combined skill views: pooled only where an administrator mapped skills explicitly.
 *
 * No percentage is invented, scores from different assessments are not compared, and nothing
 * here writes progress, skill state or completion.
 */
final class LearnerProgress {
	const MAX_SKILLS     = 120;
	const PRACTICE_LIMIT = 5;
	const CLASSES        = array( 'strength', 'gap', 'developing', 'not-assessed' );

	/** Strength, gap, in progress or not assessed. Not-assessed skills are never counted as gaps.
	 *
	 * @param mixed $level Level.
	 * @param mixed $struggling Struggling.
	 */
	public static function classify( $level, $struggling ) {
		if ( in_array( $level, array( 'proficient', 'mastered' ), true ) ) {
			return 'strength'; }
		if ( Levels::is_developing( $level ) ) {
			return $struggling ? 'gap' : 'developing'; }
		return 'not-assessed';
	}

	/**
	 * Class label for Progress.
	 *
	 * @param mixed $class Class.
	 */
	public static function class_label( $class ) {
		return array(
			'strength'     => __( 'Strength', 'ohmylms' ),
			'gap'          => __( 'Needs practice', 'ohmylms' ),
			'developing'   => __( 'In progress', 'ohmylms' ),
			'not-assessed' => __( 'Not assessed', 'ohmylms' ),
		)[ $class ] ?? $class;
	}

	/**
	 * Evidence ready for Progress.
	 */
	private static function evidence_ready() {
		return AssessmentSchema::ready();
	}

	/**
	 * Skill labels for Progress.
	 *
	 * @return array<int,array> term id => ['name' => ..., 'code' => ...]
	 * @param array $term_ids Term ids.
	 */
	private static function skill_labels( array $term_ids ) {
		$labels = array();
		if ( ! $term_ids ) {
			return $labels; }
		foreach ( get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'include'    => $term_ids,
				'hide_empty' => false,
				'number'     => 0,
			)
		) as $term ) {
			$labels[ (int) $term->term_id ] = array(
				'name' => $term->name,
				'code' => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
			);
		}
		return $labels;
	}

	/**
	 * Skill states for Progress.
	 *
	 * @return array<int,array> term id => stored state row (absent when never assessed)
	 * @param mixed $student Student.
	 * @param array $term_ids Term ids.
	 */
	private static function skill_states( $student, array $term_ids ) {
		global $wpdb;
		$states = array();
		if ( ! $term_ids || ! self::evidence_ready() ) {
			return $states; }
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT term_id, level, review_due, evidence_count, independent_correct, families, last_evidence_at FROM %i WHERE student_id=%d AND term_id IN (' . implode( ',', array_fill( 0, count( $term_ids ), '%d' ) ) . ')', array_merge( array( AssessmentSchema::table( 'student_skill_state' ), (int) $student ), $term_ids ) ), ARRAY_A ) as $row ) {
			$states[ (int) $row['term_id'] ] = $row;
		}
		return $states;
	}

	/**
	 * Practice.
	 *
	 * @var mixed
	 */
	private static $practice = array();

	/** A practice link only when the learner may actually practise this skill and questions exist.
	 *
	 * @param mixed $student Student.
	 * @param mixed $term_id Term identifier.
	 * @param array $enrolled_courses Enrolled courses.
	 */
	private static function practice_url( $student, $term_id, array $enrolled_courses ) {
		if ( ! \OhMyLMS\Practice\Frontend::enabled() ) {
			return null; }
		$key = (int) $student . ':' . (int) $term_id . ':' . implode( ',', $enrolled_courses );
		if ( array_key_exists( $key, self::$practice ) ) {
			return self::$practice[ $key ]; }
		$owner = array( 'student_id' => (int) $student );
		$pool  = Selector::pool( (int) $term_id );
		$url   = null;
		if ( PracticeAccessPolicy::pool( $pool, $owner, (int) $term_id, 0 ) ) {
			$url = add_query_arg( 'ohmylms_practice', (int) $term_id, home_url( '/' ) );
		} else {
			foreach ( $enrolled_courses as $course_id ) {
				if ( PracticeAccessPolicy::check( $owner, (int) $term_id, (int) $course_id ) === true && PracticeAccessPolicy::pool( $pool, $owner, (int) $term_id, (int) $course_id ) ) {
					$url = add_query_arg(
						array(
							'ohmylms_practice' => (int) $term_id,
							'learning_course'  => (int) $course_id,
						),
						home_url( '/' )
					);
					break;
				}
			}
		}
		self::$practice[ $key ] = $url;
		return $url;
	}

	/** One entry per skill with its level, classification and where it appears.
	 *
	 * @param mixed $student Student.
	 * @param array $term_ids Term ids.
	 * @param array $sources Sources.
	 * @param array $enrolled_courses Enrolled courses.
	 */
	private static function skill_entries( $student, array $term_ids, array $sources, array $enrolled_courses ) {
		$labels  = self::skill_labels( $term_ids );
		$states  = self::skill_states( $student, $term_ids );
		$entries = array();
		foreach ( $term_ids as $term_id ) {
			if ( ! isset( $labels[ $term_id ] ) ) {
				continue; }
			$state               = $states[ $term_id ] ?? null;
			$level               = $state ? $state['level'] : 'not-started';
			$struggling          = Levels::is_developing( $level ) && Recommendations::struggling( (int) $student, $term_id );
			$class               = self::classify( $level, $struggling );
			$entries[ $term_id ] = array(
				'term_id'              => $term_id,
				'name'                 => $labels[ $term_id ]['name'],
				'code'                 => $labels[ $term_id ]['code'],
				'level'                => $level,
				'level_label'          => Mastery::label( $level ),
				'classification'       => $class,
				'classification_label' => self::class_label( $class ),
				'review_due'           => $state ? (bool) $state['review_due'] : false,
				'evidence_count'       => $state ? (int) $state['evidence_count'] : 0,
				'independent_correct'  => $state ? (int) $state['independent_correct'] : 0,
				'sources'              => array_values( array_unique( $sources[ $term_id ] ?? array() ) ),
				'practice_url'         => 'strength' === $class && ! ( $state && $state['review_due'] ) ? null : self::practice_url( $student, $term_id, $enrolled_courses ),
			);
		}
		return $entries;
	}

	/**
	 * Combined views for shared skills. A shared skill appears when at least one of the objectives
	 * is mapped to it (or it is one of them and has mappings). Only `equivalent`
	 * mappings pool evidence; `related` ones are listed for reference. Each source keeps its own
	 * level and curriculum context, and nothing is averaged across them.
	 *
	 * @param mixed $student Student.
	 * @param array $objective_terms Objective terms.
	 * @param array $own_entries Own entries.
	 */
	private static function combined( $student, array $objective_terms, array $own_entries ) {
		global $wpdb;
		if ( ! $objective_terms || ! self::evidence_ready() ) {
			return array(); }
		$shared_ids = array();
		foreach ( SkillMappings::all( $objective_terms ) as $mapping ) {
			$shared_ids[ (int) $mapping['shared_term_id'] ] = true;
		}
		if ( ! $shared_ids ) {
			return array(); }
		$groups = array();
		foreach ( SkillMappings::all( array_keys( $shared_ids ) ) as $mapping ) {
			$shared = (int) $mapping['shared_term_id'];
			if ( isset( $shared_ids[ $shared ] ) ) {
				$groups[ $shared ][] = $mapping; }
		}
		$all_terms = array_keys( $shared_ids );
		foreach ( $groups as $mappings ) {
			foreach ( $mappings as $mapping ) {
				$all_terms[] = (int) $mapping['specific_term_id']; }
		}
		$all_terms    = array_values( array_unique( $all_terms ) );
		$labels       = self::skill_labels( $all_terms );
		$states       = self::skill_states( $student, $all_terms );
		$pooled_terms = array();
		foreach ( $groups as $shared => $mappings ) {
			$pooled_terms[] = $shared;
			foreach ( $mappings as $mapping ) {
				if ( 'equivalent' === $mapping['relation'] ) {
					$pooled_terms[] = (int) $mapping['specific_term_id']; }
			}
		}
		$pooled_terms = array_values( array_unique( $pooled_terms ) );
		$evidence     = $wpdb->get_results( $wpdb->prepare( 'SELECT id, grade_event_id, term_id, part_id, question_id, awarded, available, independent, first_try, difficulty, family_id, source_type, source_id, evidence_at FROM %i WHERE student_id=%d AND term_id IN (' . implode( ',', array_fill( 0, count( $pooled_terms ), '%d' ) ) . ") AND role='primary' AND superseded=0 ORDER BY evidence_at, id", array_merge( array( AssessmentSchema::table( 'skill_evidence' ), (int) $student ), $pooled_terms ) ), ARRAY_A );
		$evidence     = $evidence ? $evidence : array();
		$by_term      = array();
		foreach ( $evidence as $row ) {
			$by_term[ (int) $row['term_id'] ][] = $row; }
		$rules    = Mastery::rules();
		$combined = array();
		foreach ( $groups as $shared => $mappings ) {
			if ( ! isset( $labels[ $shared ] ) ) {
				continue; }
			$rows    = $by_term[ $shared ] ?? array();
			$sources = array();
			foreach ( $mappings as $mapping ) {
				$term = (int) $mapping['specific_term_id'];
				if ( ! isset( $labels[ $term ] ) ) {
					continue; }
				if ( 'equivalent' === $mapping['relation'] ) {
					$rows = array_merge( $rows, $by_term[ $term ] ?? array() ); }
				$level     = isset( $states[ $term ] ) ? $states[ $term ]['level'] : 'not-started';
				$sources[] = array(
					'term_id'        => $term,
					'name'           => $labels[ $term ]['name'],
					'code'           => $labels[ $term ]['code'],
					'relation'       => $mapping['relation'],
					'note'           => (string) $mapping['note'],
					'level'          => $level,
					'level_label'    => Mastery::label( $level ),
					'evidence_count' => isset( $states[ $term ] ) ? (int) $states[ $term ]['evidence_count'] : 0,
					'in_objectives'  => in_array( $term, $objective_terms, true ),
					'context'        => array_map(
						static function ( $membership ) {
							return array(
								'path'    => $membership['path'],
								'name'    => $membership['name'],
								'code'    => $membership['code'],
								'version' => $membership['version'],
							); },
						array_slice( Links::memberships( 'skill', $term ), 0, 3 )
					),
				);
			}
			$state      = Combined::state( $rows, time() );
			$merged     = Combined::merge( $rows );
			$combined[] = array(
				'shared'         => array(
					'term_id' => $shared,
					'name'    => $labels[ $shared ]['name'],
					'code'    => $labels[ $shared ]['code'],
				),
				'level'          => $state['level'],
				'level_label'    => Mastery::label( $state['level'] ),
				'evidence_count' => count( $merged ),
				'pooled_sources' => count(
					array_filter(
						$sources,
						static function ( $source ) {
							return 'equivalent' === $source['relation']; }
					)
				),
				'sources'        => $sources,
			);
		}
		usort(
			$combined,
			static function ( $left, $right ) {
				return strcmp( $left['shared']['name'], $right['shared']['name'] );
			}
		);
		return $combined;
	}

	/**
	 * Read syllabus objectives and shared mastery without writes.
	 *
	 * @param int   $student Current learner ID, validated by the caller.
	 * @param array $term_ids Syllabus objective IDs.
	 * @return array
	 */
	public static function for_objectives( $student, array $term_ids ) {
		$entries = self::skill_entries( (int) $student, $term_ids, array(), array() );
		return array(
			'direct' => $entries,
			'shared' => self::combined( (int) $student, $term_ids, $entries ),
		);
	}

}
