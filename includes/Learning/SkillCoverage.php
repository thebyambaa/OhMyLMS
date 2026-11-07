<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Skill coverage of a course: which skills it teaches, which it can assess, and where it falls short.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\Practice\Selector;
use OhMyLMS\QuestionBank\Banks;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Answers one question for an author: "for each skill this course touches, is it taught and can it be
 * assessed?"
 *
 * A skill is in scope when the course has it as a skill outcome, when a placed lesson, quiz, assignment or
 * practice activity is connected to it, or when the course targets it as a syllabus objective. For each skill
 * the report lists where it is taught (placed lessons), where it is tagged for assessment (placed quizzes and
 * assignments) and how many approved questions and question families the allowed pool has, against what the
 * skill's target level needs. Alignment alone never counts as teaching, and a quiz tag alone never counts as
 * assessment: only approved questions whose primary skill is this one count, as in publication readiness.
 *
 * {@see SkillCoverage::assemble()} is pure and takes plain data; {@see SkillCoverage::report()} loads it.
 */
final class SkillCoverage {

	const COVERED         = 'covered';
	const NEEDS_TEACHING  = 'needs-teaching';
	const NEEDS_QUESTIONS = 'needs-questions';
	const NEEDS_BOTH      = 'needs-both';

	/**
	 * Target whose thresholds judge a skill that is not a skill outcome.
	 */
	const DEFAULT_TARGET = 'proficient';

	/**
	 * Skill coverage of a course's draft program.
	 *
	 * @param int $course_id Course ID.
	 * @return array{course_id:int,revision:string,units:array,summary:array,skills:array}
	 */
	public static function report( $course_id ) {
		$course_id = (int) $course_id;
		$draft     = CourseProgram::editor( $course_id )['draft'];
		$units     = array();
		foreach ( (array) ( $draft['units'] ?? array() ) as $unit ) {
			$units[ (int) $unit['id'] ] = (string) ( $unit['name'] ?? '' );
		}
		$items       = (array) ( $draft['items'] ?? array() );
		$content_ids = array();
		foreach ( $items as $item ) {
			if ( 'practice' !== $item['type'] ) {
				$content_ids[] = (int) $item['content_id'];
			}
		}
		$tagged = array();
		$terms  = $content_ids ? wp_get_object_terms( array_values( array_unique( $content_ids ) ), Taxonomy::NAME, array( 'fields' => 'all_with_object_id' ) ) : array();
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$tagged[ (int) $term->object_id ][] = (int) $term->term_id;
		}
		// A quiz keeps its skills in its own table, apart from the skill terms of other content.
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			foreach ( $items as $item ) {
				if ( 'quiz' === $item['type'] ) {
					$tagged[ (int) $item['content_id'] ] = array_merge( $tagged[ (int) $item['content_id'] ] ?? array(), \OhMyLMS\Quiz\QuizSkills::get( (int) $item['content_id'] ) );
				}
			}
		}
		$placed = array();
		foreach ( $items as $item ) {
			$practice = 'practice' === $item['type'];
			$skills   = $practice ? array( (int) $item['content_id'] ) : array_merge( $tagged[ (int) $item['content_id'] ] ?? array(), array_map( 'intval', (array) ( $item['skill_ids'] ?? array() ) ) );
			$placed[] = array(
				'type'       => (string) $item['type'],
				'content_id' => (int) $item['content_id'],
				'chapter_id' => (int) ( $item['chapter_id'] ?? 0 ),
				'title'      => $practice ? '' : html_entity_decode( (string) get_the_title( (int) $item['content_id'] ), ENT_QUOTES, 'UTF-8' ),
				'skills'     => array_values( array_unique( $skills ) ),
			);
		}
		$objectives = array_values( array_unique( array_filter( array_map( 'intval', array_column( CourseObjectives::references( $course_id ), 'term_id' ) ) ) ) );
		$outcomes   = (array) ( $draft['outcomes'] ?? array() );

		$ids = $objectives;
		foreach ( $outcomes as $outcome ) {
			$ids[] = (int) $outcome['term_id'];
		}
		foreach ( $placed as $row ) {
			$ids = array_merge( $ids, $row['skills'] );
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );

		return self::assemble(
			array(
				'course_id'  => $course_id,
				'revision'   => Catalog::revision( $course_id ),
				'units'      => $units,
				'placed'     => $placed,
				'outcomes'   => $outcomes,
				'objectives' => $objectives,
				'terms'      => self::term_info( $ids ),
				'pools'      => self::pool_counts( $draft, $ids ),
				'rules'      => Mastery::rules(),
			)
		);
	}

	/**
	 * Build the report from plain data.
	 *
	 * @param array $in {
	 *     Input.
	 *
	 *     @type int    $course_id  Course ID.
	 *     @type string $revision   Draft revision, for the writes the report offers.
	 *     @type array  $units      Unit ID => title.
	 *     @type array $placed     Placed activities: type, content_id, chapter_id, title, skills (term IDs).
	 *     @type array $outcomes   Skill outcomes: term_id, target, required, chapter_id.
	 *     @type int[] $objectives Skill IDs of the syllabus objectives the course targets.
	 *     @type array $terms      Skill ID => name and code.
	 *     @type array $pools      Skill ID => questions and families in the allowed pool.
	 *     @type array $rules      Mastery thresholds (`<target>_min`, `<target>_families`).
	 * }
	 * @return array{course_id:int,revision:string,units:array,summary:array,skills:array}
	 */
	public static function assemble( array $in ) {
		$units  = (array) ( $in['units'] ?? array() );
		$rules  = (array) ( $in['rules'] ?? array() );
		$skills = array();
		$row    = static function ( $term_id ) use ( &$skills, $in ) {
			if ( ! isset( $skills[ $term_id ] ) ) {
				$info               = $in['terms'][ $term_id ] ?? array();
				$skills[ $term_id ] = array(
					'term_id'   => (int) $term_id,
					'name'      => (string) ( $info['name'] ?? '' ),
					'code'      => (string) ( $info['code'] ?? '' ),
					'outcome'   => false,
					'required'  => false,
					'target'    => '',
					'objective' => false,
					'practice'  => false,
					'teaching'  => array(),
					'assessing' => array(),
				);
			}
			return $term_id;
		};
		foreach ( (array) ( $in['outcomes'] ?? array() ) as $outcome ) {
			$id                        = $row( (int) $outcome['term_id'] );
			$skills[ $id ]['outcome']  = true;
			$skills[ $id ]['required'] = ! empty( $outcome['required'] );
			$skills[ $id ]['target']   = (string) ( $outcome['target'] ?? '' );
		}
		foreach ( (array) ( $in['objectives'] ?? array() ) as $term_id ) {
			$id                         = $row( (int) $term_id );
			$skills[ $id ]['objective'] = true;
		}
		foreach ( (array) ( $in['placed'] ?? array() ) as $activity ) {
			foreach ( (array) $activity['skills'] as $term_id ) {
				$id = $row( (int) $term_id );
				if ( 'practice' === $activity['type'] ) {
					$skills[ $id ]['practice'] = true;
					continue;
				}
				$where = array(
					'id'      => (int) $activity['content_id'],
					'type'    => (string) $activity['type'],
					'title'   => (string) $activity['title'],
					'unit_id' => (int) $activity['chapter_id'],
					'unit'    => (string) ( $units[ (int) $activity['chapter_id'] ] ?? '' ),
				);
				$key   = 'lesson' === $activity['type'] ? 'teaching' : 'assessing';
				if ( ! in_array( $where, $skills[ $id ][ $key ], true ) ) {
					$skills[ $id ][ $key ][] = $where;
				}
			}
		}

		foreach ( $skills as $id => &$skill ) {
			$target                  = in_array( $skill['target'], array( 'familiar', 'proficient', 'mastered' ), true ) ? $skill['target'] : self::DEFAULT_TARGET;
			$pool                    = $in['pools'][ $id ] ?? array();
			$skill['questions']      = (int) ( $pool['questions'] ?? 0 );
			$skill['families']       = (int) ( $pool['families'] ?? 0 );
			$skill['questions_need'] = (int) ( $rules[ $target . '_min' ] ?? 1 );
			$skill['families_need']  = (int) ( $rules[ $target . '_families' ] ?? 1 );
			$skill['need_basis']     = $skill['outcome'] && $target === $skill['target'] ? 'target' : 'default';
			$skill['teaching_ok']    = count( $skill['teaching'] ) > 0;
			$skill['questions_ok']   = $skill['questions'] >= $skill['questions_need'] && $skill['families'] >= $skill['families_need'];
			$skill['status']         = self::status( $skill['teaching_ok'], $skill['questions_ok'] );
		}
		unset( $skill );

		$order = array(
			self::NEEDS_BOTH      => 0,
			self::NEEDS_TEACHING  => 1,
			self::NEEDS_QUESTIONS => 2,
			self::COVERED         => 3,
		);
		$list  = array_values( $skills );
		usort(
			$list,
			static function ( $a, $b ) use ( $order ) {
				return array( $order[ $a['status'] ], $a['required'] ? 0 : 1, strtolower( $a['name'] ) ) <=> array( $order[ $b['status'] ], $b['required'] ? 0 : 1, strtolower( $b['name'] ) );
			}
		);

		$unit_list = array();
		foreach ( $units as $unit_id => $unit_title ) {
			$unit_list[] = array(
				'id'    => (int) $unit_id,
				'title' => (string) $unit_title,
			);
		}
		return array(
			'course_id' => (int) ( $in['course_id'] ?? 0 ),
			'revision'  => (string) ( $in['revision'] ?? '' ),
			'units'     => $unit_list,
			'summary'   => self::summary( $list ),
			'skills'    => $list,
		);
	}

	/**
	 * Status of a skill from the two questions that matter.
	 *
	 * @param bool $taught     A placed lesson teaches it.
	 * @param bool $assessable Its allowed question pool is large and varied enough.
	 * @return string
	 */
	public static function status( $taught, $assessable ) {
		if ( $taught && $assessable ) {
			return self::COVERED;
		}
		if ( $taught ) {
			return self::NEEDS_QUESTIONS;
		}
		return $assessable ? self::NEEDS_TEACHING : self::NEEDS_BOTH;
	}

	/**
	 * Counts for the top of the view.
	 *
	 * @param array[] $skills Skill rows from {@see SkillCoverage::assemble()}.
	 * @return array<string,int>
	 */
	public static function summary( array $skills ) {
		$count = static function ( callable $test ) use ( $skills ) {
			return count( array_filter( $skills, $test ) );
		};
		return array(
			'skills'             => count( $skills ),
			'required'           => $count(
				static function ( $skill ) {
					return $skill['required'];
				}
			),
			'covered'            => $count(
				static function ( $skill ) {
					return self::COVERED === $skill['status'];
				}
			),
			'needs_teaching'     => $count(
				static function ( $skill ) {
					return self::NEEDS_TEACHING === $skill['status'];
				}
			),
			'needs_questions'    => $count(
				static function ( $skill ) {
					return self::NEEDS_QUESTIONS === $skill['status'];
				}
			),
			'needs_both'         => $count(
				static function ( $skill ) {
					return self::NEEDS_BOTH === $skill['status'];
				}
			),
			'objectives'         => $count(
				static function ( $skill ) {
					return $skill['objective'];
				}
			),
			'objectives_taught'  => $count(
				static function ( $skill ) {
					return $skill['objective'] && $skill['teaching_ok'];
				}
			),
			'required_not_ready' => $count(
				static function ( $skill ) {
					return $skill['required'] && ! $skill['questions_ok'];
				}
			),
		);
	}

	/**
	 * Name and code of each skill, in one query.
	 *
	 * @param int[] $ids Skill IDs.
	 * @return array<int,array{name:string,code:string}>
	 */
	private static function term_info( array $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'include'    => $ids,
				'hide_empty' => false,
				'number'     => 0,
			)
		);
		$info  = array();
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$info[ (int) $term->term_id ] = array(
				'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
				'code' => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
			);
		}
		return $info;
	}

	/**
	 * Approved questions and question families each skill has in the course's allowed pool, with the same
	 * bank and author rules as {@see CourseProgram::pool()} but in one query for all skills.
	 *
	 * @param array $program Draft program.
	 * @param int[] $ids     Skill IDs.
	 * @return array<int,array{questions:int,families:int}>
	 */
	private static function pool_counts( array $program, array $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$author = (int) ( $program['author_id'] ?? get_current_user_id() );
		$banks  = array_map( 'intval', (array) ( $program['bank_ids'] ?? array() ) );
		$counts = array();
		foreach ( Selector::pools( $ids ) as $term_id => $rows ) {
			$questions = array();
			$families  = array();
			foreach ( $rows as $question ) {
				$bank = (int) $question['bank_id'];
				if ( $banks ) {
					$allowed = in_array( $bank, $banks, true );
				} else {
					$allowed = 0 === $bank ? (int) $question['author_id'] === $author : Banks::can( $bank, 'use', $author );
				}
				if ( ! $allowed ) {
					continue;
				}
				$questions[ (int) $question['question_id'] ] = true;
				$families[ $question['family_id'] ? $question['family_id'] : 'q:' . $question['question_id'] ] = true;
			}
			$counts[ (int) $term_id ] = array(
				'questions' => count( $questions ),
				'families'  => count( $families ),
			);
		}
		return $counts;
	}
}
