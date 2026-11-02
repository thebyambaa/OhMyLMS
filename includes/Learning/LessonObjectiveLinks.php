<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Lesson objectives for the lesson editor: linked skills and syllabus objective mappings.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Curriculum\LessonObjectives;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * One editable view over two existing stores: the lesson's skill terms (`ohmylms_skill`, what coverage
 * and practice read) and {@see LessonObjectives} mappings (`_ohmylms_lesson_objectives`: syllabus objective
 * + teaches/practices/assesses). A mapped objective tags the lesson with its skill; such tags are
 * "managed" and leave with the mapping. Explicit skills stay until removed. The courses that place the
 * lesson offer their course objectives as quick choices. Nothing here awards mastery or changes a course.
 */
final class LessonObjectiveLinks {

	const MAX_SKILLS = 200;

	const RELATIONS = array( 'teaches', 'practices', 'assesses' );

	/**
	 * Skill term as the editor shows it.
	 *
	 * @param \WP_Term $term Term.
	 * @param int[]    $managed Managed skill IDs.
	 * @return array
	 */
	private static function skill( $term, array $managed ) {
		return array(
			'id'      => (int) $term->term_id,
			'name'    => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			'code'    => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
			'managed' => in_array( (int) $term->term_id, $managed, true ),
		);
	}

	/**
	 * Courses that place a lesson in a chapter, at most 20.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return int[]
	 */
	private static function courses( $lesson_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded placement read.
		$ids = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT ch.course_id FROM %i c INNER JOIN %i ch ON ch.chapter_id = c.chapter_id WHERE c.content_id = %d LIMIT 20', $wpdb->prefix . 'ohmylms_content_relationship', $wpdb->prefix . 'ohmylms_chapter_relationship', (int) $lesson_id ) );
		return array_values( array_unique( array_merge( array_map( 'intval', $ids ), array_map( 'intval', Placements::courses_for( (int) $lesson_id ) ) ) ) );
	}

	/**
	 * Read skills, mappings with live objective text, and the placing courses' objectives.
	 *
	 * @param int $lesson_id Lesson ID.
	 * @return array|\WP_Error
	 */
	public static function read( $lesson_id ) {
		wp_cache_delete( $lesson_id, 'post_meta' );
		$objectives = LessonObjectives::read( $lesson_id );
		if ( is_wp_error( $objectives ) ) {
			return $objectives;
		}
		$managed = array_map( 'intval', (array) get_post_meta( $lesson_id, LessonObjectives::MANAGED_TAGS, true ) );
		$terms   = wp_get_object_terms( $lesson_id, Taxonomy::NAME, array( 'orderby' => 'name' ) );
		$terms   = is_wp_error( $terms ) ? array() : $terms;
		$names   = array_column( CourseObjectives::syllabuses(), 'name', 'id' );
		$cache   = array();
		$targets = array();
		foreach ( self::courses( $lesson_id ) as $course_id ) {
			$course = get_post( $course_id );
			if ( ! $course || OHMYLMS_COURSE_CPT !== $course->post_type ) {
				continue;
			}
			foreach ( CourseObjectives::references( $course_id ) as $reference ) {
				$syllabus = (int) ( $reference['syllabus_id'] ?? 0 );
				$term     = (int) ( $reference['term_id'] ?? 0 );
				if ( ! array_key_exists( $syllabus, $cache ) ) {
					$cache[ $syllabus ] = CourseObjectives::objectives( $syllabus );
				}
				$row = is_wp_error( $cache[ $syllabus ] ) ? null : ( $cache[ $syllabus ][ $term ] ?? null );
				if ( $row && count( $targets ) < 100 ) {
					$targets[ $syllabus . ':' . $term ] = $row + array(
						'syllabus_name' => (string) ( $names[ $syllabus ] ?? '' ),
						'course_id'     => (int) $course_id,
						'course_title'  => html_entity_decode( get_the_title( $course ), ENT_QUOTES, 'UTF-8' ),
					);
				}
			}
		}
		$mappings = array();
		foreach ( $objectives['mappings'] as $mapping ) {
			$objective  = is_array( $mapping['objective'] ) ? $mapping['objective'] : array();
			$mappings[] = array(
				'syllabus_id'   => (int) $mapping['syllabus_id'],
				'term_id'       => (int) $mapping['term_id'],
				'relation'      => (string) $mapping['relation'],
				'name'          => (string) ( $objective['name'] ?? '' ),
				'code'          => (string) ( $objective['code'] ?? '' ),
				'objective'     => (string) ( $objective['objective'] ?? '' ),
				'topic'         => (string) ( $objective['topic'] ?? '' ),
				'syllabus_name' => (string) ( $names[ (int) $mapping['syllabus_id'] ] ?? '' ),
				'missing'       => (bool) $mapping['missing'],
			);
		}
		$skills = array();
		foreach ( $terms as $term ) {
			$skills[] = self::skill( $term, $managed );
		}
		return array(
			'id'             => (int) $lesson_id,
			'revision'       => hash( 'sha256', wp_json_encode( array( $objectives['revision'], array_column( $skills, 'id' ), $managed ) ) ),
			'skills'         => $skills,
			'mappings'       => $mappings,
			'course_targets' => array_values( $targets ),
		);
	}

	/**
	 * Replace the explicit skills and the objective mappings together.
	 *
	 * @param int   $lesson_id Lesson ID.
	 * @param mixed $data      `{revision, skill_ids, mappings: [{syllabus_id, term_id, relation}]}`.
	 * @return array|\WP_Error The refreshed view.
	 */
	public static function save( $lesson_id, $data ) {
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), array( 'revision', 'skill_ids', 'mappings' ) ) || ! is_string( $data['revision'] ?? null ) || ! is_array( $data['skill_ids'] ?? null ) || ! is_array( $data['mappings'] ?? null ) || count( $data['skill_ids'] ) > self::MAX_SKILLS ) {
			return Catalog::error( __( 'Send the revision, up to 200 skill IDs and the objective mappings.', 'ohmylms' ) );
		}
		$skill_ids = array();
		foreach ( $data['skill_ids'] as $skill ) {
			if ( ! is_int( $skill ) || $skill < 1 || ! term_exists( $skill, Taxonomy::NAME ) ) {
				return Catalog::error( __( 'Choose existing skills.', 'ohmylms' ) );
			}
			$skill_ids[ $skill ] = $skill;
		}
		foreach ( $data['mappings'] as $row ) {
			if ( ! is_array( $row ) || array_diff( array_keys( $row ), array( 'syllabus_id', 'term_id', 'relation' ) ) ) {
				return Catalog::error( __( 'Choose a valid syllabus objective and relationship.', 'ohmylms' ) );
			}
		}
		return LessonAuthoring::locked(
			$lesson_id,
			static function () use ( $lesson_id, $data, $skill_ids ) {
				$state = self::read( $lesson_id );
				if ( is_wp_error( $state ) ) {
					return $state;
				}
				if ( ! hash_equals( $state['revision'], $data['revision'] ) ) {
					return Catalog::error( __( 'This lesson\'s objectives changed in another session. Your choices are kept; reload before saving.', 'ohmylms' ), 409 );
				}
				$error = null;
				try {
					Transaction::run(
						static function () use ( $lesson_id, $data, $skill_ids, &$error ) {
							$current = LessonObjectives::read( $lesson_id );
							$saved   = is_wp_error( $current ) ? $current : LessonObjectives::save(
								$lesson_id,
								array(
									'revision' => $current['revision'],
									'mappings' => array_values( $data['mappings'] ),
								)
							);
							if ( is_wp_error( $saved ) ) {
								$error = $saved;
								throw new \RuntimeException( 'Objective mappings rejected.' );
							}
							$mapped = array_values( array_unique( array_map( 'intval', array_column( $data['mappings'], 'term_id' ) ) ) );
							$tags   = Catalog::set_lesson_skills( $lesson_id, array_values( array_unique( array_merge( array_values( $skill_ids ), $mapped ) ) ) );
							if ( is_wp_error( $tags ) ) {
								$error = $tags;
								throw new \RuntimeException( 'Skills rejected.' );
							}
							update_post_meta( $lesson_id, LessonObjectives::MANAGED_TAGS, array_values( array_diff( $mapped, array_values( $skill_ids ) ) ) );
						}
					);
				} catch ( \Throwable $failure ) {
					clean_post_cache( $lesson_id );
					return $error ? $error : Catalog::error( __( 'The lesson objectives could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
				}
				clean_object_term_cache( $lesson_id, OHMYLMS_LESSON_CPT );
				return self::read( $lesson_id );
			}
		);
	}

	/**
	 * Picker choices: `syllabuses` (teaching syllabuses), `objectives` of one syllabus, or `skills`.
	 * Objectives and skills are searched and paged by 20.
	 *
	 * @param string $type        `syllabuses`, `objectives` or `skills`.
	 * @param int    $syllabus_id Syllabus for objectives.
	 * @param string $search      Search text.
	 * @param int    $page        Page from 1.
	 * @return array|\WP_Error
	 */
	public static function options( $type, $syllabus_id, $search, $page ) {
		$page   = max( 1, (int) $page );
		$search = trim( (string) $search );
		if ( 'syllabuses' === $type ) {
			$rows = array_values(
				array_filter(
					CourseObjectives::syllabuses(),
					static function ( $row ) {
						return 'roadmap' !== $row['item_type'];
					}
				)
			);
			return array(
				'items' => array_map(
					static function ( $row ) {
						return array(
							'id'      => (int) $row['id'],
							'name'    => (string) $row['name'],
							'code'    => (string) $row['code'],
							'version' => (string) $row['version'],
							'grade'   => (string) $row['grade'],
							'subject' => (string) $row['subject'],
						);
					},
					$rows
				),
				'total' => count( $rows ),
				'pages' => 1,
			);
		}
		if ( 'skills' === $type ) {
			$args  = array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'search'     => $search,
				'number'     => 20,
				'offset'     => ( $page - 1 ) * 20,
				'orderby'    => 'name',
			);
			$terms = get_terms( $args );
			$total = (int) wp_count_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'hide_empty' => false,
					'search'     => $search,
				)
			);
			return array(
				'items' => array_map(
					static function ( $term ) {
						return self::skill( $term, array() );
					},
					is_wp_error( $terms ) ? array() : $terms
				),
				'total' => $total,
				'pages' => (int) ceil( $total / 20 ),
			);
		}
		$rows = CourseObjectives::objectives( (int) $syllabus_id );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		if ( '' !== $search ) {
			$needle = mb_strtolower( $search );
			$rows   = array_filter(
				$rows,
				static function ( $row ) use ( $needle ) {
					return false !== mb_strpos( mb_strtolower( $row['name'] . ' ' . $row['code'] . ' ' . $row['objective'] . ' ' . $row['topic'] ), $needle );
				}
			);
		}
		$rows = array_values( $rows );
		return array(
			'items' => array_slice( $rows, ( $page - 1 ) * 20, 20 ),
			'total' => count( $rows ),
			'pages' => (int) ceil( count( $rows ) / 20 ),
		);
	}
}
