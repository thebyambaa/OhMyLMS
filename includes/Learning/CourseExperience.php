<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Course presentation and ordered Kids mode access over existing progress.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Switching modes never rewrites content, enrollment or completion records. */
final class CourseExperience {
	/**
	 * Saved presentation; old courses keep their existing layout.
	 *
	 * @param int $course_id Course ID.
	 * @return string
	 */
	public static function mode( $course_id ) {
		return 'kids' === get_post_meta( $course_id, '_ohmylms_course_experience', true ) ? 'kids' : 'book';
	}

	/**
	 * Decide access from the ordered published steps, keeping completed steps replayable.
	 *
	 * @param array  $items Ordered items with complete and available flags.
	 * @param int    $content_id Content or skill ID.
	 * @param string $kind Content or practice namespace.
	 * @return bool
	 */
	public static function step_locked( array $items, $content_id, $kind = 'content' ) {
		$blocked = false;
		foreach ( $items as $item ) {
			if ( empty( $item['available'] ) ) {
				continue;
			}
			$matches = (int) $item['content_id'] === (int) $content_id && ( 'practice' === $kind ) === ( 'practice' === $item['type'] );
			if ( $matches ) {
				return empty( $item['complete'] ) && $blocked;
			}
			$blocked = $blocked || empty( $item['complete'] );
		}
		return false;
	}

	/**
	 * Enforce the same sequence for map links, direct content URLs and practice requests.
	 *
	 * @param int    $course_id Course ID.
	 * @param int    $student_id Learner ID.
	 * @param int    $content_id Content or skill ID.
	 * @param string $kind Content or practice namespace.
	 * @return bool
	 */
	public static function locked( $course_id, $student_id, $content_id, $kind = 'content' ) {
		if ( ! $student_id || 'kids' !== self::mode( $course_id ) || user_can( $student_id, 'edit_post', $course_id ) ) {
			return false;
		}
		// Cache only while rendering; completion and REST writes always read fresh progress.
		$items = \OhMyLMS\Utility\RenderCache::remember(
			'kids-steps:' . $student_id . ':' . $course_id,
			static function () use ( $student_id, $course_id ) {
				$state = Schema::ready() ? CompletionPolicy::status( $student_id, $course_id ) : null;
				if ( $state && ! is_wp_error( $state ) ) {
					$program = $state['program'];
					$targets = array_column( $program['outcomes'], 'met', 'term_id' );
					foreach ( $program['items'] as &$item ) {
						if ( 'practice' === $item['type'] ) {
							$item['complete'] = ! empty( $targets[ $item['content_id'] ] );
						}
					}
					unset( $item );
				} else {
					$program = UnitSnapshots::published_program( $course_id, $student_id );
					$student = ohmylms_get_student( $student_id );
					foreach ( $program['items'] as &$item ) {
						$item['available'] = 'practice' === $item['type'] || 'publish' === get_post_status( $item['content_id'] );
						$item['complete']  = 'practice' !== $item['type'] && $student && $student->maybe_completed( $item['content_id'] );
					}
					unset( $item );
				}
				$units = array();
				$final = array();
				foreach ( $program['items'] as $item ) {
					if ( empty( $item['chapter_id'] ) && 'quiz' === $item['type'] && \OhMyLMS\Assessment\Schema::ready() && 'course-challenge' === \OhMyLMS\Assessment\AssessmentSettings::get( $item['content_id'] )['style'] ) {
						$final[] = $item;
						continue;
					}
					$units[ (int) ( $item['chapter_id'] ?? 0 ) ][] = $item;
				}
				$units   = StructurePlan::order_units( $units, $program['units'] ?? array() );
				$units[] = $final;
				return array_merge( ...array_values( $units ) );
			}
		);
		return self::step_locked( $items, $content_id, $kind );
	}
}
