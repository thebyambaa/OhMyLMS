<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Skill maps: the coherence map of a subject, syllabus comparison and the student skill map.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Performance\Guidance;
use OhMyLMS\Performance\GuidanceModel;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the shared skill library of a subject (domains, grade bands, prerequisite and "includes"
 * links), the syllabuses mapped onto it and a learner's pooled levels, and hands them to
 * SkillMapModel. Read only.
 */
final class SkillMap {

	/** Most shared skills read for one map. */
	const MAX_SKILLS = 2000;

	/**
	 * Subjects that have shared skills or syllabuses, with their syllabuses.
	 *
	 * @return array[] subject, skills (count), syllabuses (id, name, code).
	 */
	public static function subjects() {
		global $wpdb;
		$counts = array();
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT meta_value AS subject, COUNT(*) AS skills FROM %i WHERE meta_key=%s GROUP BY meta_value', $wpdb->termmeta, Comparison::SUBJECT_META ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One grouped read of the shared library.
		foreach ( $rows as $row ) {
			if ( '' !== (string) $row['subject'] ) {
				$counts[ (string) $row['subject'] ] = (int) $row['skills'];
			}
		}
		$configured = get_option( Comparison::OPTION, array() );
		$configured = is_array( $configured ) ? $configured : array();
		$names      = array_column( Comparison::syllabuses(), null, 'id' );
		$result     = array();
		foreach ( array_unique( array_merge( array_keys( $counts ), array_map( 'strval', array_keys( $configured ) ) ) ) as $subject ) {
			$syllabuses = array();
			foreach ( (array) ( $configured[ $subject ] ?? array() ) as $id ) {
				if ( isset( $names[ (int) $id ] ) ) {
					$syllabuses[] = array(
						'id'   => (int) $id,
						'name' => $names[ (int) $id ]['name'],
						'code' => $names[ (int) $id ]['code'],
					);
				}
			}
			$result[] = array(
				'subject'    => $subject,
				'skills'     => $counts[ $subject ] ?? 0,
				'syllabuses' => $syllabuses,
			);
		}
		usort(
			$result,
			static function ( $left, $right ) {
				return array( $right['skills'], $left['subject'] ) <=> array( $left['skills'], $right['subject'] );
			}
		);
		return $result;
	}

	/**
	 * The coherence map of a subject, optionally with a syllabus and a learner laid over it.
	 *
	 * @param string $subject     Subject.
	 * @param int    $syllabus_id Syllabus to show coverage for, or 0.
	 * @param int    $student_id  Learner whose levels to show, or 0.
	 * @return array|\WP_Error subject, domains, grades, skills, unplaced, issues, syllabus, student.
	 */
	public static function map( $subject, $syllabus_id = 0, $student_id = 0 ) {
		$subject = sanitize_text_field( (string) $subject );
		if ( '' === $subject ) {
			return Access::error( 'ohmylms_skill_map_subject', __( 'Choose a subject.', 'ohmylms' ) );
		}
		$skills  = self::library( $subject );
		$labels  = Comparison::domains( $subject );
		$grid    = SkillMapModel::grid( $skills, array_keys( $labels ) );
		$domains = array();
		foreach ( $grid['domains'] as $domain ) {
			$domains[] = array(
				'id'    => $domain,
				'label' => $labels[ $domain ] ?? ucfirst( $domain ),
			);
		}
		$grades = array();
		foreach ( $grid['grades'] as $grade ) {
			$grades[] = array(
				'value' => $grade,
				'label' => Comparison::grade_label( $grade, $subject ),
			);
		}
		$result = array(
			'subject'  => $subject,
			'domains'  => $domains,
			'grades'   => $grades,
			'skills'   => array_values( $skills ),
			'unplaced' => $grid['unplaced'],
			'issues'   => SkillMapModel::issues( $skills ),
			'syllabus' => null,
			'student'  => null,
		);
		if ( $syllabus_id ) {
			$overlay = self::coverage( (int) $syllabus_id, array_keys( $skills ) );
			if ( is_wp_error( $overlay ) ) {
				return $overlay;
			}
			$result['syllabus'] = $overlay;
		}
		if ( $student_id ) {
			$result['student'] = self::student( (int) $student_id, $skills, $subject );
		}
		return $result;
	}

	/**
	 * Compare two syllabuses through the shared skills they map onto.
	 *
	 * @param int $a Syllabus A.
	 * @param int $b Syllabus B.
	 * @return array|\WP_Error a, b (id, name, code, objectives), skills (involved shared skills keyed by ID), plus the comparison.
	 */
	public static function compare( $a, $b ) {
		$a = (int) $a;
		$b = (int) $b;
		if ( ! $a || ! $b || $a === $b ) {
			return Access::error( 'ohmylms_compare_pair', __( 'Choose two different syllabuses.', 'ohmylms' ) );
		}
		$sides = array();
		$names = array_column( Comparison::syllabuses(), null, 'id' );
		foreach ( array(
			'a' => $a,
			'b' => $b,
		) as $side => $id ) {
			$rows = Comparison::objectives( $id );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			$objectives = array();
			foreach ( $rows as $row ) {
				$objectives[ (int) $row['term_id'] ] = array(
					'id'   => (int) $row['term_id'],
					'name' => Taxonomy::plain( (string) ( $row['name'] ?? '' ) ),
					'code' => (string) ( $row['code'] ?? '' ),
				);
			}
			$sides[ $side ] = array(
				'id'         => $id,
				'name'       => $names[ $id ]['name'] ?? '',
				'code'       => $names[ $id ]['code'] ?? '',
				'objectives' => $objectives,
			);
		}
		$mappings = array();
		$ids      = array_merge( array_keys( $sides['a']['objectives'] ), array_keys( $sides['b']['objectives'] ) );
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			foreach ( SkillMappings::all( $chunk ) as $mapping ) {
				$mappings[ (int) $mapping['specific_term_id'] ][ (int) $mapping['shared_term_id'] ] = (string) $mapping['relation'];
			}
		}
		$skills = array();
		foreach ( $mappings as $targets ) {
			foreach ( array_keys( $targets ) as $id ) {
				if ( ! isset( $skills[ $id ] ) ) {
					$skill = self::describe( $id );
					if ( $skill ) {
						$skills[ $id ] = $skill;
					}
				}
			}
		}
		foreach ( $mappings as $objective => $targets ) {
			$mappings[ $objective ] = array_intersect_key( $targets, $skills );
		}
		$comparison = SkillMapModel::compare( array_keys( $sides['a']['objectives'] ), array_keys( $sides['b']['objectives'] ), $mappings, $skills );
		$labels     = Comparison::domains( Comparison::subject_of_syllabus( (int) $a ) );
		foreach ( $comparison['domains'] as &$domain ) {
			$domain['label'] = '' === $domain['domain'] ? __( 'No domain yet', 'ohmylms' ) : ( $labels[ $domain['domain'] ] ?? ucfirst( $domain['domain'] ) );
		}
		unset( $domain );
		foreach ( array( 'a', 'b' ) as $side ) {
			$sides[ $side ]['objectives'] = array_values( $sides[ $side ]['objectives'] );
		}
		return array(
			'a'      => $sides['a'],
			'b'      => $sides['b'],
			'skills' => (object) $skills,
		) + $comparison;
	}

	/**
	 * A learner's skill map: the subjects they have started, and for one of them the map with their levels.
	 *
	 * @param int    $student_id Learner.
	 * @param string $subject    Subject; empty picks the one with most started skills.
	 * @return array subjects (subject, started), and map (as map(), with student) or null.
	 */
	public static function for_student( $student_id, $subject = '' ) {
		$levels  = Guidance::levels( (int) $student_id );
		$started = array();
		foreach ( array_keys( $levels ) as $skill ) {
			$name = (string) get_term_meta( $skill, Comparison::SUBJECT_META, true );
			if ( '' !== $name && 'not-started' !== $levels[ $skill ] ) {
				$started[ $name ] = ( $started[ $name ] ?? 0 ) + 1;
			}
		}
		arsort( $started );
		$subjects = array();
		foreach ( $started as $name => $count ) {
			$subjects[] = array(
				'subject' => $name,
				'started' => $count,
			);
		}
		$subject = (string) $subject;
		if ( '' === $subject || ! isset( $started[ $subject ] ) ) {
			$subject = (string) array_key_first( $started );
		}
		$map = '' === $subject ? null : self::map( $subject, 0, (int) $student_id );
		return array(
			'subjects' => $subjects,
			'map'      => is_wp_error( $map ) ? null : $map,
		);
	}

	/**
	 * Shared skills of a subject, keyed by ID.
	 *
	 * @param string $subject Subject.
	 * @return array<int,array>
	 */
	private static function library( $subject ) {
		$terms  = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'meta_key'   => Comparison::SUBJECT_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Shared skills are designated by this key.
				'meta_value' => $subject, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One subject's library.
				'number'     => self::MAX_SKILLS,
				'orderby'    => 'name',
			)
		);
		$skills = array();
		if ( is_wp_error( $terms ) ) {
			return $skills;
		}
		foreach ( $terms as $term ) {
			$skills[ (int) $term->term_id ] = self::describe( $term );
		}
		$skills   = array_filter( $skills );
		$includes = SkillEdges::encompasses_many( array_keys( $skills ) );
		$mapped   = array();
		foreach ( array_chunk( array_keys( $skills ), 500 ) as $chunk ) {
			foreach ( SkillMappings::all( $chunk ) as $mapping ) {
				$shared = (int) $mapping['shared_term_id'];
				if ( isset( $skills[ $shared ] ) ) {
					$mapped[ $shared ] = ( $mapped[ $shared ] ?? 0 ) + 1;
				}
			}
		}
		foreach ( $skills as $id => &$skill ) {
			$skill['includes'] = array();
			foreach ( $includes[ $id ] ?? array() as $other => $weight ) {
				$skill['includes'][] = array(
					'id'     => (int) $other,
					'weight' => (float) $weight,
				);
			}
			$skill['mapped'] = $mapped[ $id ] ?? 0;
		}
		return $skills;
	}

	/**
	 * A shared skill for the maps.
	 *
	 * @param int|\WP_Term $term Skill.
	 * @return array|null
	 */
	private static function describe( $term ) {
		$term = $term instanceof \WP_Term ? $term : get_term( (int) $term, Taxonomy::NAME );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		$id   = (int) $term->term_id;
		$from = (int) get_term_meta( $id, Comparison::GRADE_FROM_META, true );
		return array(
			'id'            => $id,
			'name'          => Taxonomy::plain( $term->name ),
			'code'          => (string) get_term_meta( $id, Syllabus::CODE_META, true ),
			'domain'        => (string) get_term_meta( $id, Comparison::DOMAIN_META, true ),
			'grade_from'    => $from,
			'grade_to'      => max( $from, (int) get_term_meta( $id, Comparison::GRADE_TO_META, true ) ),
			'prerequisites' => Taxonomy::prerequisites( $id ),
		);
	}

	/**
	 * Which shared skills of the map a syllabus teaches.
	 *
	 * @param int   $syllabus_id Syllabus.
	 * @param int[] $skill_ids   Shared skills on the map.
	 * @return array|\WP_Error id, name, objectives, covered (skill => objectives), unmapped (objectives with no covering mapping).
	 */
	private static function coverage( $syllabus_id, array $skill_ids ) {
		$rows = Comparison::objectives( $syllabus_id );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$names     = array_column( Comparison::syllabuses(), null, 'id' );
		$on_map    = array_flip( $skill_ids );
		$covered   = array();
		$mapped    = array();
		$objective = array();
		foreach ( $rows as $row ) {
			$objective[ (int) $row['term_id'] ] = array(
				'id'   => (int) $row['term_id'],
				'name' => Taxonomy::plain( (string) ( $row['name'] ?? '' ) ),
				'code' => (string) ( $row['code'] ?? '' ),
			);
		}
		foreach ( array_chunk( array_keys( $objective ), 500 ) as $chunk ) {
			foreach ( SkillMappings::all( $chunk ) as $mapping ) {
				$specific = (int) $mapping['specific_term_id'];
				$shared   = (int) $mapping['shared_term_id'];
				if ( ! isset( $objective[ $specific ] ) || ! in_array( $mapping['relation'], SkillMapModel::COVERING, true ) ) {
					continue;
				}
				$mapped[ $specific ] = true;
				if ( isset( $on_map[ $shared ] ) ) {
					$covered[ $shared ][] = $objective[ $specific ] + array( 'relation' => (string) $mapping['relation'] );
				}
			}
		}
		return array(
			'id'         => (int) $syllabus_id,
			'name'       => $names[ $syllabus_id ]['name'] ?? '',
			'objectives' => count( $objective ),
			'covered'    => (object) $covered,
			'unmapped'   => count( $objective ) - count( $mapped ),
		);
	}

	/**
	 * A learner's levels on the map's skills, and their strength per domain.
	 *
	 * @param int     $student_id Learner.
	 * @param array[] $skills     Skills on the map, keyed by ID.
	 * @param string  $subject    Subject whose domain names to use.
	 * @return array id, levels (skill => level, started skills only), areas, counts (per level, over the map).
	 */
	private static function student( $student_id, array $skills, $subject = '' ) {
		$levels = array_intersect_key( Guidance::levels( $student_id ), $skills );
		$pooled = array();
		$counts = array_fill_keys( Levels::SCALE, 0 );
		foreach ( $skills as $id => $skill ) {
			$level = $levels[ $id ] ?? 'not-started';
			++$counts[ isset( $counts[ $level ] ) ? $level : 'not-started' ];
			if ( isset( $levels[ $id ] ) ) {
				$pooled[ $id ] = array(
					'level'      => $level,
					'last'       => null,
					'struggling' => false,
					'terms'      => array( $id ),
				);
			}
		}
		$labels = Comparison::domains( $subject );
		$areas  = array();
		foreach ( GuidanceModel::areas( $pooled, $skills ) as $area ) {
			$areas[] = $area + array(
				'label' => $labels[ $area['domain'] ] ?? ucfirst( $area['domain'] ),
				'band'  => GuidanceModel::strength_band( $area['strength'] ),
			);
		}
		return array(
			'id'     => (int) $student_id,
			'levels' => (object) $levels,
			'areas'  => $areas,
			'counts' => $counts,
		);
	}
}
