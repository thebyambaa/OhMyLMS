<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Lesson practice guidance from assigned targets and authorized question pools.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\PracticeAccessPolicy;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Practice\Presentation;

defined( 'ABSPATH' ) || exit;

/** Reads evaluated state; never awards mastery or selects questions outside the runner. */
final class PracticeGuidance {
	/**
	 * Build guidance only for the current enrolled learner's published skill.
	 *
	 * Lessons are block lesson items inside a unit since b0e21ca5, so guidance follows the skill's
	 * unit in the flattened outline order.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $lesson_id Ignored; lesson groups no longer exist. Kept for the REST `lesson` argument.
	 * @param int    $skill_id Practice entry skill.
	 * @return array|\WP_Error
	 */
	public static function get( $course_id, $lesson_id = '', $skill_id = 0 ) {
		$state = CompletionPolicy::status( get_current_user_id(), $course_id );
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		$outline = CourseOutline::get( $course_id, get_current_user_id() );
		if ( is_wp_error( $outline ) || ! $outline['enrolled'] || $outline['locked'] ) {
			return new \WP_Error( 'ohmylms_practice_guidance_denied', __( 'This practice guidance is not available.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		if ( ! $skill_id ) {
			return new \WP_Error( 'ohmylms_practice_guidance_lesson', __( 'Choose a skill.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$outcome = null;
		foreach ( $state['program']['outcomes'] as $row ) {
			if ( (int) $row['term_id'] === (int) $skill_id ) {
				$outcome = $row;
				break;
			}
		}
		if ( ! $outcome ) {
			return new \WP_Error( 'ohmylms_practice_guidance_skill', __( 'This skill is not in your course.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		// The practice activity places the skill in a unit; an outcome without one keeps its own unit.
		$unit_id = (int) ( $outcome['chapter_id'] ?? 0 );
		$current = array();
		foreach ( $outline['units'] as $unit ) {
			foreach ( $unit['items'] as $item ) {
				if ( 'practice' === $item['kind'] && (int) $item['term_id'] === (int) $skill_id ) {
					$unit_id = (int) $unit['id'];
					$current = $item;
					break 2;
				}
			}
		}
		$labels   = array_column( $outline['skills'], null, 'term_id' );
		$id       = (int) $outcome['term_id'];
		$allowed  = \OhMyLMS\Assessment\Engine::practice() && ! is_wp_error( PracticeAccessPolicy::check( array( 'student_id' => get_current_user_id() ), $id, $course_id ) );
		$count    = $allowed ? Presentation::available( $id, $course_id, 10 ) : 0;
		$met      = ! empty( $outcome['met'] );
		$due      = ! empty( $outcome['state']['review_due'] );
		$skills   = array(
			array(
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
			),
		);
		$primary  = self::recommend( $skills );
		$next     = null;
		$continue = ! $primary && empty( $state['pending'] ) ? LessonJourney::continue_from( $outline, $unit_id, $current ) : null;
		if ( $continue && $continue['same_unit'] ) {
			$next = array(
				/* translators: %s: next activity name. */
				'label' => sprintf( __( 'Continue unit: %s', 'ohmylms' ), $continue['item']['name'] ),
				'url'   => $continue['item']['url'],
			);
		} elseif ( $continue && $continue['unit'] ) {
			$next = array(
				/* translators: %s: next unit name. */
				'label' => sprintf( __( 'Next unit: %s', 'ohmylms' ), $continue['unit']['title'] ),
				'url'   => $continue['item']['url'],
			);
		} elseif ( $continue ) {
			$next = array(
				/* translators: %s: next activity name, such as the course challenge. */
				'label' => sprintf( __( 'Continue course: %s', 'ohmylms' ), $continue['item']['name'] ),
				'url'   => $continue['item']['url'],
			);
		}
		return array(
			'course_id'      => (int) $course_id,
			'lesson_id'      => '',
			'skill_id'       => (int) $skill_id,
			'lesson_url'     => PlayerContext::overview_url( $course_id ) . LessonJourney::unit_anchor( $outline, $unit_id ),
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
