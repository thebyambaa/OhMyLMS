<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Learner-facing course outline: units, activities, skills and progress.
 *
 * Read-only adapter over the existing course program, completion policy and
 * skill-state services. It never stores anything and never decides mastery.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Assessment\SkillAssessment;
use OhMyLMS\Curriculum\CourseLinks;
use OhMyLMS\Curriculum\UnitNumber;
use OhMyLMS\Data\Student;
use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\LearningPath;
use OhMyLMS\Learning\ProgramGroups;
use OhMyLMS\Learning\Schema as LearningSchema;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Snapshot;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** One normalized outline for a course, for the current learner. */
final class CourseOutline {

	/**
	 * Build the outline for a course.
	 *
	 * Draft, private, password-protected and roadmap courses are never described.
	 * Learners who are not enrolled see titles only; activity links then lead to the
	 * course page, where enrollment and access rules apply.
	 *
	 * @param int $course_id Course post ID.
	 * @param int $student_id Current learner, or 0 for a visitor.
	 * @return array|\WP_Error
	 */
	public static function get( $course_id, $student_id ) {
		$course_id = absint( $course_id );
		$post      = $course_id ? get_post( $course_id ) : null;
		if ( ! $post || OHMYLMS_COURSE_CPT !== $post->post_type || in_array( $course_id, LearnerCatalog::roadmap_course_ids(), true ) ) {
			return self::missing();
		}
		$preview = current_user_can( 'edit_post', $course_id );
		if ( 'publish' !== $post->post_status && ! $preview ) {
			return self::missing();
		}
		$course = ohmylms_get_course( $course_id );
		$locked = post_password_required( $post ) && ! $preview;
		$base   = array(
			'id'          => $course_id,
			'title'       => get_the_title( $course_id ),
			'description' => $locked ? '' : wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 60 ),
			'url'         => self::enroll_url( $course_id ),
			'locked'      => $locked,
			'enrolled'    => false,
			'challenge'   => null,
			'units'       => array(),
			'links'       => array(
				'get_ready'  => array(),
				'subcourses' => array(),
			),
			'summary'     => array(),
			'continue'    => array(
				'state' => 'view',
				'label' => __( 'View course', 'ohmylms' ),
				'url'   => self::enroll_url( $course_id ),
			),
		);
		if ( $locked || ! $course ) {
			return $base;
		}
		$student    = $student_id ? new Student( $student_id ) : null;
		$enrollment = $student_id ? CourseProgram::enrollment( $student_id, $course_id ) : null;
		$state      = null;
		if ( $enrollment && LearningSchema::ready() ) {
			$status = CompletionPolicy::status( $student_id, $course_id );
			$state  = is_wp_error( $status ) ? null : $status;
		}
		if ( $state ) {
			$program = $state['program'];
		} else {
			$program = \OhMyLMS\Learning\UnitSnapshots::published_program( $course_id, $student_id );
		}
		$unit_metadata = array_column( $program['units'], null, 'id' );
		$lesson_skills = array();
		// Khan-style names are a view of the SmartScore; completion keeps using stored levels.
		$term_ids = array();
		foreach ( $program['items'] as $item ) {
			if ( 'practice' === ( $item['type'] ?? '' ) ) {
				$term_ids[] = (int) $item['content_id'];
			}
		}
		foreach ( $state ? $program['outcomes'] : array() as $outcome ) {
			$term_ids[] = (int) $outcome['term_id'];
		}
		$display = $enrollment ? Snapshot::student( $student_id, $term_ids ) : array();
		// Credit back: proficient work on the same shared skill under another syllabus counts here too.
		$credited = 0;
		foreach ( $state ? $program['outcomes'] : array() as $outcome ) {
			$term  = (int) $outcome['term_id'];
			$level = $display[ $term ]['key'] ?? 'not-started';
			if ( ! empty( $outcome['credit']['counts'] ) && Levels::rank( $outcome['credit']['level'] ) > Levels::rank( $level ) ) {
				$display[ $term ] = array(
					'key'      => $outcome['credit']['level'],
					'label'    => Levels::label( $outcome['credit']['level'] ),
					'score'    => null,
					'credited' => true,
				) + (array) ( $display[ $term ] ?? array() );
				++$credited;
			}
		}

		$units            = array();
		$skills           = array();
		$course_challenge = null;
		$skill_targets    = $state ? array_column( $program['outcomes'], 'met', 'term_id' ) : array();
		foreach ( $program['items'] as $item ) {
			$row = self::item( $item, $course, $student, $display, (bool) $enrollment );
			if ( ! $row ) {
				continue;
			}
			if ( 'practice' === $row['kind'] && array_key_exists( $row['term_id'], $skill_targets ) ) {
				$row['complete'] = (bool) $skill_targets[ $row['term_id'] ];
			}
			$row['group_id'] = (string) ( $item['group_id'] ?? '' );
			$chapter         = (int) ( $item['chapter_id'] ?? 0 );
			if ( 0 === $chapter && 'course-challenge' === ( $row['style'] ?? '' ) ) {
				$course_challenge = $row;
				continue;
			}
			if ( ! isset( $units[ $chapter ] ) ) {
				$units[ $chapter ] = array(
					'id'          => $chapter,
					'title'       => $unit_metadata[ $chapter ]['name'] ?? __( 'Activities', 'ohmylms' ),
					'description' => $unit_metadata[ $chapter ]['description'] ?? '',
					'items'       => array(),
				);
			}
			$units[ $chapter ]['items'][] = $row;
			if ( 'practice' === $row['kind'] ) {
				$skills[ $row['term_id'] ] = $row['level'];
			}
		}
		foreach ( $state ? $program['outcomes'] : array() as $outcome ) {
			$skills[ (int) $outcome['term_id'] ] = $display[ (int) $outcome['term_id'] ]['key'] ?? 'not-started';
			$term                                = get_term( $outcome['term_id'], Taxonomy::NAME );
			$linked_groups                       = array();
			foreach ( $program['groups'] ?? array() as $group ) {
				if ( in_array( (int) $outcome['term_id'], ProgramGroups::skill_ids( $program, $group ), true ) ) {
					$linked_groups[] = $group['id'];
				}
			}
			$lesson_skills[] = array(
				'term_id'   => (int) $outcome['term_id'],
				'group_id'  => $outcome['group_id'] ?? '',
				'group_ids' => $linked_groups,
				'name'      => $term && ! is_wp_error( $term ) ? $term->name : __( 'Unavailable skill', 'ohmylms' ),
				'label'     => $display[ (int) $outcome['term_id'] ]['label'] ?? Levels::label( 'not-started' ),
				'level'     => $display[ (int) $outcome['term_id'] ]['key'] ?? 'not-started',
				'met'       => ! empty( $outcome['met'] ),
			);
		}
		$units = \OhMyLMS\Learning\StructurePlan::order_units( $units, $program['units'] );

		$done      = $course_challenge && $course_challenge['complete'] ? 1 : 0;
		$total     = $course_challenge ? 1 : 0;
		$challenge = $course_challenge;
		$position  = 0;
		foreach ( $units as &$unit ) {
			// The printed label may be an author's own ("3", "2A"); anchors and order stay by position.
			$unit['number'] = UnitNumber::label( $unit_metadata[ $unit['id'] ]['number'] ?? '', $position++ );

			$unit['done']  = count( array_filter( array_column( $unit['items'], 'complete' ) ) );
			$unit['total'] = count( $unit['items'] );
			// Lesson groups: 'items' stays the flat list; groups and what sits outside them are extra views of it.
			$arranged       = ProgramGroups::arrange( array(), $unit['items'], $unit['id'] );
			$unit['groups'] = $arranged['groups'];
			foreach ( $unit['groups'] as &$lesson ) {
				$lesson += LessonJourney::progress( $lesson['items'] );
			}
			unset( $lesson );
			$unit             += LessonJourney::progress( $unit['items'] );
			$unit['ungrouped'] = $arranged['ungrouped'];
			$unit['test']      = self::first_with_style( $unit['items'], 'unit-test' );
			$challenge         = $challenge ? $challenge : self::first_with_style( $unit['items'], 'course-challenge' );
			$done             += $unit['done'];
			$total            += $unit['total'];
		}
		unset( $unit );
		$proficient    = count(
			array_filter(
				$skills,
				static function ( $level ) {
					return $level && Levels::is_proficient( $level );
				}
			)
		);
		$summary       = array(
			'units'      => count( $units ),
			'skills'     => count( $skills ),
			'proficient' => $proficient,
			'credited'   => $credited,
			'done'       => $done,
			'total'      => $total,
			'tracked'    => (bool) $state,
			'percent'    => $skills ? (int) round( 100 * $proficient / count( $skills ) ) : ( $total ? (int) round( 100 * $done / $total ) : 0 ),
		);
		$visible_items = $course_challenge ? array( $course_challenge ) : array();
		foreach ( $units as $unit ) {
			$visible_items = array_merge( $visible_items, $unit['items'] );
		}
		$summary                   += LessonJourney::progress( $visible_items );
		$summary['mastery_percent'] = $skills ? (int) round( 100 * $proficient / count( $skills ) ) : 0;
		$summary['completed']       = ! empty( $state['completed'] );
		return array_merge(
			$base,
			array(
				'enrolled'  => (bool) $enrollment,
				'challenge' => $challenge,
				'units'     => array_values( $units ),
				'links'     => self::links( $course_id ),
				'summary'   => $summary,
				'skills'    => $lesson_skills,
				'continue'  => self::continue_action( $course, $student, $state, (bool) $enrollment, $summary ),
			)
		);
	}

	/**
	 * Advisory links to other courses: what to get ready with, and the sub-courses.
	 *
	 * Only published courses outside roadmaps are listed, so a link never reveals anything the
	 * learner could not already open. The links gate nothing.
	 *
	 * @param int $course_id Course post ID.
	 * @return array{get_ready: array[], subcourses: array[]} Each entry has id, title and url.
	 */
	private static function links( $course_id ) {
		$out     = array(
			'get_ready'  => array(),
			'subcourses' => array(),
		);
		$roadmap = LearnerCatalog::roadmap_course_ids();
		foreach ( CourseLinks::get( $course_id ) as $link ) {
			$id = (int) $link['course_id'];
			if ( OHMYLMS_COURSE_CPT !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || in_array( $id, $roadmap, true ) ) {
				continue;
			}
			$entry = array(
				'id'    => $id,
				'title' => get_the_title( $id ),
				'url'   => is_user_logged_in() && in_array( 'library', Hub::settings()['tabs'], true ) ? Hub::course_url( $id ) : get_permalink( $id ),
			);
			if ( 'subcourse' === $link['type'] ) {
				$out['subcourses'][] = $entry;
			} else {
				$out['get_ready'][] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Normalize one program item, or null when the learner should not see it.
	 *
	 * @param array        $item Program item.
	 * @param object       $course Course object.
	 * @param Student|null $student Current learner.
	 * @param array        $display Display mastery entry by skill term ID.
	 * @param bool         $enrolled Whether the learner is enrolled.
	 * @return array|null
	 */
	private static function item( array $item, $course, $student, array $display, $enrolled ) {
		$type      = (string) ( $item['type'] ?? 'lesson' );
		$available = $enrolled && LearningPath::available( $item + array( 'available' => true ), $student ? $student->get_id() : 0, $course->get_id() );
		$content   = (int) ( $item['content_id'] ?? 0 );
		if ( ! $content ) {
			return null;
		}
		if ( 'practice' === $type ) {
			$term = get_term( $content, Taxonomy::NAME );
			if ( ! $term || is_wp_error( $term ) ) {
				return null;
			}
			$entry = $enrolled ? ( $display[ $content ] ?? null ) : null;
			return array(
				'id'        => $item['id'] ?? '',
				'available' => $available,
				'kind'      => 'practice',
				'name'      => $item['title'] ?? $term->name,
				'term_id'   => $content,
				'level'     => $entry ? $entry['key'] : null,
				'label'     => $entry ? $entry['label'] . ( ! empty( $entry['credited'] ) ? ' ' . __( '(from other syllabuses)', 'ohmylms' ) : '' ) : '',
				'credited'  => ! empty( $entry['credited'] ),
				'score'     => $entry ? $entry['score'] : null,
				'complete'  => $entry && Levels::is_proficient( $entry['key'] ),
				'required'  => ! empty( $item['required'] ),
				'url'       => $enrolled ? add_query_arg(
					array(
						'ohmylms_practice' => $content,
						'learning_course'  => $course->get_id(),
					),
					home_url( '/' )
				) : self::enroll_url( $course->get_id() ),
			);
		}
		if ( 'publish' !== get_post_status( $content ) ) {
			return null;
		}
		if ( isset( $item['complete'] ) ) {
			$complete = (bool) $item['complete'];
		} else {
			$complete = $enrolled && $student && (bool) $student->maybe_completed( $content );
		}
		if ( $enrolled ) {
			$url = ! empty( $item['url'] ) ? $item['url'] : ohmylms_get_pretty_content_permalink( $content );
		} else {
			$url = self::enroll_url( $course->get_id() );
		}
		// A quiz may be a unit test or a course challenge (mixed-skill assessments); everything else is "quiz".
		$style = 'quiz' === $type && AssessmentSchema::ready() ? (string) AssessmentSettings::get( $content )['style'] : 'quiz';
		return array(
			'id'         => $item['id'] ?? '',
			'available'  => $available,
			'content_id' => $content,
			'kind'       => in_array( $type, array( 'quiz', 'assignment' ), true ) ? $type : 'lesson',
			'style'      => $style,
			'kind_label' => 'lesson' === $type ? ( array(
				'video' => __( 'Video', 'ohmylms' ),
				'audio' => __( 'Audio', 'ohmylms' ),
			)[ get_post_meta( $content, '_type', true ) ] ?? __( 'Reading', 'ohmylms' ) ) : ( 'quiz' === $style ? '' : SkillAssessment::label( $style ) ),
			'name'       => $item['title'] ?? get_the_title( $content ),
			'complete'   => $complete,
			'required'   => ! empty( $item['required'] ),
			'level'      => null,
			'label'      => '',
			'url'        => $url,
		);
	}

	/**
	 * The first activity that is a mixed-skill assessment of the given style.
	 *
	 * @param array[] $items Unit activities.
	 * @param string  $style unit-test or course-challenge.
	 * @return array|null
	 */
	private static function first_with_style( array $items, $style ) {
		foreach ( $items as $row ) {
			if ( 'quiz' === $row['kind'] && ( $row['style'] ?? '' ) === $style ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Where a learner who is not enrolled lands: the enroll card on the course overview.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	private static function enroll_url( $course_id ) {
		$base = is_user_logged_in() && in_array( 'library', Hub::settings()['tabs'], true ) ? Hub::course_url( $course_id ) : get_permalink( $course_id );
		return $base . '#oml-course-enroll';
	}

	/**
	 * The one primary action for the hero card.
	 *
	 * @param object       $course Course object.
	 * @param Student|null $student Current learner.
	 * @param array|null   $state Completion state for the enrollment.
	 * @param bool         $enrolled Whether the learner is enrolled.
	 * @param array        $summary Outline summary.
	 * @return array state, label, url.
	 */
	private static function continue_action( $course, $student, $state, $enrolled, array $summary ) {
		if ( ! $enrolled || ! $student ) {
			return array(
				'state' => 'view',
				'label' => __( 'View course', 'ohmylms' ),
				'url'   => self::enroll_url( $course->get_id() ),
			);
		}
		if ( $state ) {
			$next = LearningPath::next( $state );
			if ( $next ) {
				return array(
					'state' => 'continue',
					'label' => __( 'Continue learning', 'ohmylms' ),
					'url'   => $next['url'],
					'name'  => $next['name'],
				);
			}
			return array(
				'state' => 'review',
				'label' => __( 'Review course', 'ohmylms' ),
				'url'   => self::enroll_url( $course->get_id() ),
			);
		}
		return array(
			'state' => $summary['done'] ? 'continue' : 'start',
			'label' => $summary['done'] ? __( 'Continue learning', 'ohmylms' ) : __( 'Start learning', 'ohmylms' ),
			'url'   => $student->get_course_resume_url( $course->get_id() ),
		);
	}

	/**
	 * Error for anything the learner may not know exists.
	 *
	 * @return \WP_Error
	 */
	private static function missing() {
		return new \WP_Error( 'ohmylms_course_unavailable', __( 'This course is not available.', 'ohmylms' ), array( 'status' => 404 ) );
	}
}
