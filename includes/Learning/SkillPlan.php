<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The skill plan of a course: a curriculum's topics and skills next to the course's lessons.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Schema as CurriculumSchema;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Studio\CourseLayout;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * What the Curriculum step shows beside the outline: one chosen curriculum (a syllabus) with its topics, skill groups
 * and skills, how the course teaches each skill, and which topics each unit is meant to cover.
 *
 * The unit-to-topic choice is a course setting kept apart from the outline draft: it only scopes the skill list
 * while an author works on a unit, it never connects anything by itself. Lesson-to-skill connections are the
 * ordinary skill terms of the lesson, so questions, practice and evidence see them as they see any others.
 */
class SkillPlan {

	/** Course meta holding the units' topics: unit id => topic (curriculum item) ids. */
	const UNIT_TOPICS_META = '_ohmylms_unit_topics';

	/**
	 * Curriculums an author can choose from.
	 *
	 * @return array[] Each `{id, name}`, syllabuses only, in tree order.
	 */
	public static function curricula() {
		$rows = array();
		foreach ( Items::all() as $row ) {
			if ( ! empty( $row['is_syllabus'] ) ) {
				$rows[] = array(
					'id'   => (int) $row['id'],
					'name' => Syllabus::text( $row['name'] ),
				);
			}
		}
		return $rows;
	}

	/**
	 * The curriculum a course is linked to, else the first one.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $curricula Result of `curricula()`.
	 * @return int Curriculum ID, 0 when there is none.
	 */
	public static function default_curriculum( $course_id, array $curricula ) {
		global $wpdb;
		$ids   = array_column( $curricula, 'id' );
		$items = $wpdb->get_col( $wpdb->prepare( 'SELECT item_id FROM %i WHERE object_type=%s AND object_id=%d ORDER BY item_id', CurriculumSchema::table( 'curriculum_links' ), 'course', (int) $course_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( $items as $item ) {
			$guard = 0;
			$id    = (int) $item;
			while ( $id && $guard++ < 50 ) {
				if ( in_array( $id, $ids, true ) ) {
					return $id;
				}
				$row = Items::get( $id );
				$id  = $row ? (int) $row['parent_id'] : 0;
			}
		}
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * The plan of one course for one curriculum.
	 *
	 * @param int $course_id    Course ID.
	 * @param int $curriculum_id Curriculum (syllabus) ID; 0 for the course's own or the first.
	 * @return array|WP_Error
	 */
	public static function plan( $course_id, $curriculum_id = 0 ) {
		$course_id = (int) $course_id;
		$curricula = self::curricula();
		$chosen    = (int) $curriculum_id;
		if ( $chosen && ! in_array( $chosen, array_column( $curricula, 'id' ), true ) ) {
			return new WP_Error( 'ohmylms_curriculum_invalid', __( 'That curriculum does not exist.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$chosen = $chosen ? $chosen : self::default_curriculum( $course_id, $curricula );
		$topics = array();
		$names  = array();
		if ( $chosen ) {
			$outline = Syllabus::outline( $chosen );
			if ( is_wp_error( $outline ) ) {
				return $outline;
			}
			foreach ( $outline['contents'] as $content ) {
				$groups = array();
				foreach ( $content['groups'] as $group ) {
					$ids = array_map( 'intval', array_column( $group['skills'], 'term_id' ) );
					foreach ( $group['skills'] as $skill ) {
						$names[ (int) $skill['term_id'] ] = array(
							'name' => $skill['name'],
							'code' => $skill['code'],
						);
					}
					$groups[] = array(
						'id'     => (int) $group['id'],
						'name'   => $group['name'],
						'skills' => $ids,
					);
				}
				$topics[] = array(
					'id'     => (int) $content['id'],
					'name'   => $content['name'],
					'depth'  => (int) $content['depth'],
					'groups' => $groups,
				);
			}
		}
		$report = SkillCoverage::report( $course_id );
		return array(
			'course_id'   => $course_id,
			'revision'    => $report['revision'],
			'curricula'   => $curricula,
			'curriculum'  => $chosen,
			'topics'      => $topics,
			'names'       => (object) $names,
			'skills'      => (object) self::taught( $report ),
			'units'       => $report['units'],
			'unit_topics' => (object) self::unit_topics( $course_id ),
		);
	}

	/**
	 * How the course teaches each skill it touches, keyed by skill ID.
	 *
	 * @param array $report A coverage report.
	 * @return array
	 */
	public static function taught( array $report ) {
		$out = array();
		foreach ( $report['skills'] as $skill ) {
			$out[ (int) $skill['term_id'] ] = array(
				'name'           => $skill['name'],
				'code'           => $skill['code'],
				'status'         => $skill['status'],
				'teaching'       => $skill['teaching'],
				'questions'      => $skill['questions'],
				'questions_need' => $skill['questions_need'],
				'teaching_ok'    => $skill['teaching_ok'],
				'questions_ok'   => $skill['questions_ok'],
			);
		}
		return $out;
	}

	/**
	 * The topics chosen for each unit of the course.
	 *
	 * @param int $course_id Course ID.
	 * @return array unit id => topic ids; units that are no longer in the course are left out.
	 */
	public static function unit_topics( $course_id ) {
		$stored = get_post_meta( (int) $course_id, self::UNIT_TOPICS_META, true );
		$out    = array();
		foreach ( is_array( $stored ) ? $stored : array() as $unit => $topics ) {
			$unit = (int) $unit;
			if ( $unit && is_array( $topics ) && CourseLayout::chapter_in_course( $unit, (int) $course_id ) ) {
				$out[ $unit ] = array_values( array_unique( array_filter( array_map( 'intval', $topics ) ) ) );
			}
		}
		return $out;
	}

	/**
	 * Choose the topics a unit covers.
	 *
	 * @param int   $course_id Course ID.
	 * @param int   $unit_id   Unit (chapter) ID.
	 * @param array $topic_ids Curriculum item IDs; empty clears the choice.
	 * @return array|WP_Error The course's unit topics.
	 */
	public static function set_unit_topics( $course_id, $unit_id, array $topic_ids ) {
		$course_id = (int) $course_id;
		$unit_id   = (int) $unit_id;
		if ( ! $unit_id || ! CourseLayout::chapter_in_course( $unit_id, $course_id ) ) {
			return new WP_Error( 'ohmylms_unit_invalid', __( 'That unit is not in this course. Save the outline first.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$topic_ids = array_values( array_unique( array_filter( array_map( 'intval', $topic_ids ) ) ) );
		foreach ( $topic_ids as $topic ) {
			if ( ! Items::get( $topic ) ) {
				return new WP_Error( 'ohmylms_topic_invalid', __( 'A topic does not exist.', 'ohmylms' ), array( 'status' => 400 ) );
			}
		}
		$all = self::unit_topics( $course_id );
		if ( $topic_ids ) {
			$all[ $unit_id ] = $topic_ids;
		} else {
			unset( $all[ $unit_id ] );
		}
		update_post_meta( $course_id, self::UNIT_TOPICS_META, $all );
		return $all;
	}

	/**
	 * Connect or disconnect skills for a lesson that is placed in the course.
	 *
	 * @param int   $course_id Course ID.
	 * @param int   $lesson_id Lesson ID.
	 * @param array $add       Skill IDs to connect.
	 * @param array $remove    Skill IDs to disconnect.
	 * @return array|WP_Error `{lesson_skills, skills}`: the lesson's skills and the updated teaching map.
	 */
	public static function link_lesson( $course_id, $lesson_id, array $add, array $remove ) {
		$course_id = (int) $course_id;
		$lesson_id = (int) $lesson_id;
		$placed    = false;
		foreach ( CourseProgram::editor( $course_id )['draft']['items'] ?? array() as $item ) {
			if ( 'lesson' === ( $item['type'] ?? '' ) && (int) $item['content_id'] === $lesson_id ) {
				$placed = true;
				break;
			}
		}
		if ( ! $placed ) {
			return new WP_Error( 'ohmylms_lesson_not_in_course', __( 'That lesson is not in this course.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$current = wp_get_object_terms( $lesson_id, Taxonomy::NAME, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$remove = array_map( 'intval', $remove );
		$next   = array_values( array_diff( array_unique( array_merge( array_map( 'intval', $current ), array_map( 'intval', $add ) ) ), $remove ) );
		$saved  = Catalog::set_lesson_skills( $lesson_id, $next );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return array(
			'lesson_skills' => $saved,
			'skills'        => (object) self::taught( SkillCoverage::report( $course_id ) ),
		);
	}
}
