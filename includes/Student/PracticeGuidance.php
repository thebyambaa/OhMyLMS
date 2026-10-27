<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Lesson practice guidance from assigned targets and authorized question pools.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\ProgramGroups;
use OhMyLMS\Learning\PracticeAccessPolicy;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Practice\Presentation;

defined( 'ABSPATH' ) || exit;

/** Reads evaluated state; never awards mastery or selects questions outside the runner. */
final class PracticeGuidance {
	/**
	 * Build guidance only for the current enrolled learner's published lesson/skill.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $lesson_id Optional lesson UUID.
	 * @param int    $skill_id Practice entry skill, used to resolve its lesson.
	 * @return array|\WP_Error
	 */
	public static function get( $course_id, $lesson_id = '', $skill_id = 0 ) {
		$state = CompletionPolicy::status( get_current_user_id(), $course_id );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		$outline = CourseOutline::get( $course_id, get_current_user_id() );
		if ( is_wp_error( $outline ) || ! $outline['enrolled'] || $outline['locked'] || ! is_string( $lesson_id ) ) {
			return new \WP_Error( 'ohmylms_practice_guidance_denied', __( 'This practice guidance is not available.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$outcomes      = $state['program']['outcomes'];
		$groups        = array_column( $state['program']['groups'] ?? array(), null, 'id' );
		$lesson_skills = isset( $groups[ $lesson_id ] ) ? ProgramGroups::skill_ids( $state['program'], $groups[ $lesson_id ] ) : array();
		if ( $skill_id ) {
			$matches = array_values(
				array_filter(
					$outcomes,
					static function ( $row ) use ( $skill_id ) {
						return (int) $row['term_id'] === (int) $skill_id;
					}
				)
			);
			if ( ! $matches || ( $lesson_id && ! in_array( (int) $skill_id, $lesson_skills, true ) ) ) {
				return new \WP_Error( 'ohmylms_practice_guidance_skill', __( 'This skill is not in your lesson.', 'ohmylms' ), array( 'status' => 403 ) );
			}
			$lesson_id     = $lesson_id ? $lesson_id : ( $matches[0]['group_id'] ?? '' );
			$lesson_skills = isset( $groups[ $lesson_id ] ) ? ProgramGroups::skill_ids( $state['program'], $groups[ $lesson_id ] ) : array();
		}
		$lesson = $lesson_id ? LessonJourney::find( $outline, $lesson_id ) : null;
		if ( is_wp_error( $lesson ) || ( ! $lesson && ! $skill_id ) ) {
			return is_wp_error( $lesson ) ? $lesson : new \WP_Error( 'ohmylms_practice_guidance_lesson', __( 'Choose a lesson or skill.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$labels = array_column( $outline['skills'], null, 'term_id' );
		$skills = array();
		foreach ( $outcomes as $outcome ) {
			if ( $lesson_id ? ! in_array( (int) $outcome['term_id'], $lesson_skills, true ) : (int) $outcome['term_id'] !== (int) $skill_id ) {
				continue;
			}
			$id       = (int) $outcome['term_id'];
			$allowed  = \OhMyLMS\Assessment\Engine::practice() && ! is_wp_error( PracticeAccessPolicy::check( array( 'student_id' => get_current_user_id() ), $id, $course_id ) );
			$count    = $allowed ? Presentation::available( $id, $course_id, 10 ) : 0;
			$met      = ! empty( $outcome['met'] );
			$due      = ! empty( $outcome['state']['review_due'] );
			$skills[] = array(
				'term_id'       => $id,
				'name'          => $outcome['name'],
				'label'         => $labels[ $id ]['label'] ?? Levels::label( 'not-started' ),
				'level'         => $labels[ $id ]['level'] ?? $outcome['state']['level'],
				'target'        => Levels::label( $outcome['target'] ),
				'met'           => $met,
				'review_due'    => $due,
				'session_limit' => $count,
				'practice_url'  => $count ? $outcome['practice_url'] : '',
				'need'          => $met ? ( $due ? 'review' : 'met' ) : 'gap',
			);
		}
		$primary = self::recommend( $skills );
		$next    = null;
		if ( ! $primary && empty( $state['pending'] ) ) {
			if ( $lesson && $lesson['next'] ) {
				$next = array(
					/* translators: %s: next activity name. */
					'label' => sprintf( __( 'Continue lesson: %s', 'ohmylms' ), $lesson['next']['name'] ),
					'url'   => $lesson['next']['url'],
				);
			} else {
				$passed = ! $lesson;
				foreach ( $outline['units'] as $unit ) {
					foreach ( $unit['groups'] ?? array() as $candidate ) {
						if ( $candidate['id'] === $lesson_id ) {
							$passed = true;
							continue;
						}
						if ( $passed && array_filter(
							$candidate['items'],
							static function ( $item ) {
								return ! empty( $item['available'] ) && empty( $item['complete'] ); }
						) ) {
							$next = array(
								/* translators: %s: next lesson name. */
								'label' => sprintf( __( 'Next lesson: %s', 'ohmylms' ), $candidate['title'] ),
								'url'   => LessonJourney::url( $course_id, $candidate['id'] ),
							);
							break 2;
						}
					}
				}
			}
		}
		return array(
			'course_id'      => (int) $course_id,
			'lesson_id'      => $lesson_id,
			'skill_id'       => (int) $skill_id,
			'lesson_url'     => $lesson ? $lesson['url'] : PlayerContext::overview_url( $course_id ),
			'skills'         => $skills,
			'recommended'    => $primary,
			'next'           => $next,
			'pending'        => ! empty( $state['pending'] ),
			'practice_label' => __( 'Practice', 'ohmylms' ),
		);
	}

	/**
	 * Prefer available unmet targets at lower levels, then due reviews; ties keep lesson order.
	 *
	 * @param array $skills Safe skill descriptions.
	 * @return array|null
	 */
	public static function recommend( array $skills ) {
		$candidates = array();
		foreach ( $skills as $index => $skill ) {
			if ( $skill['session_limit'] && 'met' !== $skill['need'] ) {
				$candidates[] = $skill + array( 'position' => $index );
			}
		}
		usort(
			$candidates,
			static function ( $left, $right ) {
				return array( 'gap' === $left['need'] ? 0 : 1, Levels::rank( $left['level'] ), $left['position'] ) <=> array( 'gap' === $right['need'] ? 0 : 1, Levels::rank( $right['level'] ), $right['position'] );
			}
		);
		return $candidates[0] ?? null;
	}

	/**
	 * Escaped initial and refreshed guidance markup.
	 *
	 * @param array $guidance Current learner guidance.
	 * @return string
	 */
	public static function render( array $guidance ) {
		wp_enqueue_script( 'ohmylms-practice-guidance', plugins_url( 'assets/js/practice-guidance.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/practice-guidance.js' ), true );
		ob_start();
		ohmylms_get_template( 'student-hub/practice-guidance.php', array( 'guidance' => $guidance ) );
		return ob_get_clean();
	}
}
