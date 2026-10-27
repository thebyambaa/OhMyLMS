<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * What one learner sees for a followed bundle. Everything here only reads existing records and
 * keeps three things apart:.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Bundles;

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Links;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\PracticeAccessPolicy;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Practice\Selector;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Recommendations;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * What one learner sees for a followed bundle. Everything here only reads existing records and
 * keeps three things apart:
 *
 *  - course progress: counts from the course's own completion rules, never merged into one score;
 *  - skill levels: the mastery engine's per-skill state, with "Not assessed" when no evidence exists;
 *  - combined skill views: pooled only where an administrator mapped skills explicitly.
 *
 * No percentage is invented, scores from different assessments are not compared, and nothing
 * here writes progress, skill state or completion.
 */
final class Progress {
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
	 * Mode label for Progress.
	 *
	 * @param mixed $mode Mode.
	 */
	public static function mode_label( $mode ) {
		return array(
			'traditional' => __( 'Traditional', 'ohmylms' ),
			'skill-based' => __( 'Skill-based', 'ohmylms' ),
			'blended'     => __( 'Blended', 'ohmylms' ),
		)[ $mode ] ?? ucfirst( (string) $mode );
	}

	/** Students only ever see published courses; drafts and private courses stay hidden.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	private static function visible_course( $course_id ) {
		$post = get_post( (int) $course_id );
		return $post && OHMYLMS_COURSE_CPT === $post->post_type && 'publish' === $post->post_status ? $post : null;
	}

	/**
	 * Evidence ready for Progress.
	 */
	private static function evidence_ready() {
		return AssessmentSchema::ready();
	}

	/** Skill term IDs a course is tied to: its linked skills and its published program's outcomes.
	 *
	 * @param mixed $course_id Course identifier.
	 */
	private static function course_skill_ids( $course_id ) {
		$ids     = array_map( 'intval', (array) wp_get_object_terms( (int) $course_id, Taxonomy::NAME, array( 'fields' => 'ids' ) ) );
		$program = \OhMyLMS\Learning\Schema::ready() ? CourseProgram::current( $course_id ) : null;
		if ( $program ) {
			$ids = array_merge( $ids, array_map( 'intval', array_column( $program['outcomes'], 'term_id' ) ) ); }
		return array_values( array_unique( $ids ) );
	}

	/**
	 * One course for one learner, or null when it is not visible to students.
	 * Progress is a set of counts from the course's own rules, read without side effects.
	 *
	 * @param mixed $student Student.
	 * @param mixed $course_id Course identifier.
	 */
	public static function course( $student, $course_id ) {
		global $wpdb;
		$post = self::visible_course( $course_id );
		if ( ! $post ) {
			return null; }
		$course_id  = (int) $post->ID;
		$enrollment = CourseProgram::enrollment( (int) $student, $course_id );
		$summary    = array(
			'id'        => $course_id,
			'title'     => get_the_title( $post ) ?: '#' . $course_id,
			'url'       => get_permalink( $post ),
			'enrolled'  => (bool) $enrollment,
			'mode'      => 'traditional',
			'completed' => false,
			'progress'  => null,
			'note'      => '',
			'skill_ids' => self::evidence_ready() ? self::course_skill_ids( $course_id ) : array(),
		);
		$program    = \OhMyLMS\Learning\Schema::ready() ? CourseProgram::current( $course_id ) : null;
		if ( ! $enrollment ) {
			if ( $program ) {
				$summary['mode'] = $program['mode']; }
			return $summary;
		}
		$summary['completed'] = 'completed' === $enrollment['progress'];
		if ( $program && CourseProgram::managed( (int) $student, $course_id ) ) {
			$state = CompletionPolicy::status( (int) $student, $course_id );
			if ( ! is_wp_error( $state ) ) {
				$summary['mode']      = $state['program']['mode'];
				$summary['completed'] = (bool) $state['completed'];
				$summary['progress']  = array(
					'kind'       => 'program',
					'dimensions' => $state['dimensions'],
				);
				$summary['url']       = \OhMyLMS\Learning\Frontend::url( $course_id );
				if ( ! empty( $state['blocked'] ) ) {
					$summary['note'] = __( 'Skill practice is currently unavailable for this course.', 'ohmylms' ); } elseif ( ! empty( $state['pending'] ) ) {
					$summary['note'] = __( 'Skill evidence is being evaluated. Progress will update when it finishes.', 'ohmylms' ); }
			}
			return $summary;
		}
		$course              = ohmylms_get_course( $course_id );
		$total               = $course ? (int) $course->get_lessons_count() + (int) $course->get_quiz_count() + (int) $course->get_assignment_count() : 0;
		$met                 = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE enrollment_id=%d AND status=\'completed\'', $wpdb->prefix . 'ohmylms_user_progress', (int) $enrollment['id'] ) );
		$summary['progress'] = array(
			'kind'  => 'activities',
			'met'   => min( $met, $total ),
			'total' => $total,
		);
		return $summary;
	}

	/** A curriculum item: where it sits, the published courses linked under it and its skills.
	 *
	 * @param mixed $student Student.
	 * @param mixed $item_id Item identifier.
	 */
	public static function curriculum( $student, $item_id ) {
		$item = Items::get( $item_id );
		if ( ! $item ) {
			return null; }
		$ids     = Items::with_descendants( $item_id );
		$courses = array();
		foreach ( Links::object_ids( $ids, 'course' ) as $course_id ) {
			$summary = self::course( $student, $course_id );
			if ( $summary ) {
				$courses[] = $summary; }
		}
		$skill_ids = array();
		if ( self::evidence_ready() ) {
			foreach ( Links::object_ids( $ids, 'skill' ) as $term_id ) {
				if ( term_exists( $term_id, Taxonomy::NAME ) ) {
					$skill_ids[] = $term_id; }
			}
			foreach ( $courses as $course ) {
				$skill_ids = array_merge( $skill_ids, $course['skill_ids'] ); }
		}
		return array(
			'id'        => (int) $item['id'],
			'name'      => $item['name'],
			'item_type' => $item['item_type'],
			'code'      => $item['code'],
			'version'   => $item['version'],
			'path'      => Items::path_names( $item_id, false ),
			'courses'   => $courses,
			'skill_ids' => array_values( array_unique( array_map( 'intval', $skill_ids ) ) ),
		);
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
		$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT term_id, level, review_due, evidence_count, independent_correct, families, last_evidence_at FROM ' . AssessmentSchema::table( 'student_skill_state' ) . " WHERE student_id=%d AND term_id IN ($placeholders)", array_merge( array( (int) $student ), $term_ids ) ), ARRAY_A ) as $row ) {
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
		return self::$practice[ $key ] = $url;
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

	/** Sort order for practice suggestions: gaps, then reviews, then in progress, then not yet assessed.
	 *
	 * @param array $entry Entry.
	 */
	private static function practice_rank( array $entry ) {
		if ( 'gap' === $entry['classification'] ) {
			return 0; }
		if ( $entry['review_due'] ) {
			return 1; }
		return 'developing' === $entry['classification'] ? 2 : 3;
	}

	/**
	 * Summarize for Progress.
	 *
	 * @return array{total:int,strengths:int,gaps:int,developing:int,not_assessed:int}
	 * @param array $entries Entries.
	 */
	public static function summarize( array $entries ) {
		$summary = array(
			'total'        => count( $entries ),
			'strengths'    => 0,
			'gaps'         => 0,
			'developing'   => 0,
			'not_assessed' => 0,
		);
		foreach ( $entries as $entry ) {
			$key = array(
				'strength'     => 'strengths',
				'gap'          => 'gaps',
				'developing'   => 'developing',
				'not-assessed' => 'not_assessed',
			)[ $entry['classification'] ];
			++$summary[ $key ];
		}
		return $summary;
	}

	/**
	 * Combined views for shared skills. A shared skill appears when at least one skill in the
	 * bundle is mapped to it (or it is in the bundle itself and has mappings). Only `equivalent`
	 * mappings pool evidence; `related` ones are listed for reference. Each source keeps its own
	 * level and curriculum context, and nothing is averaged across them.
	 *
	 * @param mixed $student Student.
	 * @param array $bundle_terms Bundle terms.
	 * @param array $own_entries Own entries.
	 */
	private static function combined( $student, array $bundle_terms, array $own_entries ) {
		global $wpdb;
		if ( ! $bundle_terms || ! self::evidence_ready() ) {
			return array(); }
		$shared_ids = array();
		foreach ( SkillMappings::all( $bundle_terms ) as $mapping ) {
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
		$placeholders = implode( ',', array_fill( 0, count( $pooled_terms ), '%d' ) );
		$evidence     = $wpdb->get_results( $wpdb->prepare( 'SELECT id, grade_event_id, term_id, part_id, question_id, awarded, available, independent, first_try, difficulty, family_id, source_type, source_id, evidence_at FROM ' . AssessmentSchema::table( 'skill_evidence' ) . " WHERE student_id=%d AND term_id IN ($placeholders) AND role='primary' AND superseded=0 ORDER BY evidence_at, id", array_merge( array( (int) $student ), $pooled_terms ) ), ARRAY_A ) ?: array();
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
					'in_bundle'      => in_array( $term, $bundle_terms, true ),
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
	 * Read syllabus objectives and shared mastery without bundle membership or writes.
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

	/** Full progress view of one bundle for one learner.
	 *
	 * @param array $bundle Bundle.
	 * @param mixed $student Student.
	 */
	public static function for_bundle( array $bundle, $student ) {
		$student   = (int) $student;
		$members   = array();
		$sources   = array();
		$skill_ids = array();
		$remember  = static function ( array $ids, $label ) use ( &$skill_ids, &$sources ) {
			foreach ( $ids as $term_id ) {
				$skill_ids[ $term_id ] = true;
				$sources[ $term_id ][] = $label; }
		};
		foreach ( Bundles::members( (int) $bundle['id'] ) as $member ) {
			if ( 'course' === $member['type'] ) {
				$course = self::course( $student, $member['id'] );
				if ( ! $course ) {
					continue; }
				$members[] = array( 'type' => 'course' ) + $course;
				$remember( $course['skill_ids'], $course['title'] );
			} else {
				$item = self::curriculum( $student, $member['id'] );
				if ( ! $item ) {
					continue; }
				$members[] = array( 'type' => 'curriculum' ) + $item;
				$remember( $item['skill_ids'], $item['name'] );
			}
		}
		$enrolled = array();
		foreach ( $members as $member ) {
			foreach ( 'course' === $member['type'] ? array( $member ) : $member['courses'] as $course ) {
				if ( $course['enrolled'] ) {
					$enrolled[] = $course['id']; }
			}
		}
		$enrolled  = array_values( array_unique( $enrolled ) );
		$all_terms = array_keys( $skill_ids );
		$truncated = count( $all_terms ) > self::MAX_SKILLS;
		$entries   = self::skill_entries( $student, array_slice( $all_terms, 0, self::MAX_SKILLS ), $sources, $enrolled );
		uasort(
			$entries,
			static function ( $left, $right ) {
				return array_search( $left['classification'], self::CLASSES, true ) <=> array_search( $right['classification'], self::CLASSES, true ) ?: strcmp( $left['name'], $right['name'] );
			}
		);
		foreach ( $members as &$member ) {
			$mine                    = array_values( array_intersect_key( $entries, array_flip( $member['skill_ids'] ) ) );
			$member['skill_summary'] = self::summarize( $mine );
			$member['skills']        = array_map(
				static function ( $entry ) {
					return array(
						'term_id'              => $entry['term_id'],
						'name'                 => $entry['name'],
						'level_label'          => $entry['level_label'],
						'classification'       => $entry['classification'],
						'classification_label' => $entry['classification_label'],
					);
				},
				$mine
			);
			unset( $member['skill_ids'] );
			if ( 'curriculum' === $member['type'] ) {
				foreach ( $member['courses'] as &$course ) {
					unset( $course['skill_ids'] );
				} unset( $course ); }
		}
		unset( $member );
		$practice = array_values(
			array_filter(
				$entries,
				static function ( $entry ) {
					return null !== $entry['practice_url'];
				}
			)
		);
		usort(
			$practice,
			static function ( $left, $right ) {
				return self::practice_rank( $left ) <=> self::practice_rank( $right ) ?: strcmp( $left['name'], $right['name'] );
			}
		);
		return array(
			'bundle'           => array(
				'id'          => (int) $bundle['id'],
				'title'       => $bundle['title'],
				'description' => (string) $bundle['description'],
			),
			'members'          => $members,
			'skills'           => array_values( $entries ),
			'skills_truncated' => $truncated,
			'skill_summary'    => self::summarize( $entries ),
			'next_practice'    => array_slice( $practice, 0, self::PRACTICE_LIMIT ),
			'combined'         => self::combined( $student, array_keys( $entries ), $entries ),
		);
	}
}
