<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Read-only lesson navigation over the learner's assigned outline.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

defined( 'ABSPATH' ) || exit;

/** Presentation only; completion and skill evidence keep their existing writers. */
final class LessonJourney {
	/**
	 * Count activity completion separately from practice proficiency.
	 *
	 * @param array $items Visible activities.
	 * @return array
	 */
	public static function progress( array $items ) {
		$activities = array_filter(
			$items,
			static function ( $item ) {
				return 'practice' !== $item['kind'];
			}
		);
		$total      = count( $activities );
		$done       = count( array_filter( array_column( $activities, 'complete' ) ) );
		return array(
			'activity_done'    => $done,
			'activity_total'   => $total,
			'activity_percent' => $total ? (int) round( 100 * $done / $total ) : 0,
		);
	}

	/**
	 * Link through the existing course overview route.
	 *
	 * @param int    $course_id Course ID.
	 * @param string $lesson_id Lesson UUID.
	 * @return string
	 */
	public static function url( $course_id, $lesson_id ) {
		return add_query_arg( 'hub_lesson', $lesson_id, PlayerContext::overview_url( $course_id ) );
	}

	/**
	 * Resolve a lesson only in the enrolled learner's assigned publication.
	 *
	 * @param array  $outline Assigned outline.
	 * @param string $lesson_id Lesson UUID.
	 * @return array|\WP_Error
	 */
	public static function find( array $outline, $lesson_id ) {
		if ( ! empty( $outline['enrolled'] ) && empty( $outline['locked'] ) && is_string( $lesson_id ) && wp_is_uuid( $lesson_id ) && 'publish' === get_post_status( $outline['id'] ) ) {
			foreach ( $outline['units'] as $unit ) {
				foreach ( $unit['groups'] ?? array() as $lesson ) {
					if ( $lesson['id'] !== $lesson_id ) {
						continue;
					}
					$next = null;
					foreach ( $lesson['items'] as $item ) {
						if ( ! empty( $item['available'] ) && empty( $item['complete'] ) ) {
							$next = $item;
							break;
						}
					}
					return $lesson + array(
						'unit'   => $unit,
						'next'   => $next,
						'url'    => self::url( $outline['id'], $lesson_id ),
						'skills' => array_values(
							array_filter(
								$outline['skills'] ?? array(),
								static function ( $skill ) use ( $lesson_id ) {
									return in_array( $lesson_id, $skill['group_ids'] ?? array( $skill['group_id'] ), true );
								}
							)
						),
					);
				}
			}
		}
		return new \WP_Error( 'ohmylms_lesson_unavailable', __( 'This lesson is not available. Enroll in the course to open its published lessons.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Resolve adjacent available activities and the current lesson for the player.
	 *
	 * @param array  $outline Assigned outline.
	 * @param int    $current Content or skill ID.
	 * @param string $kind Content/practice namespace.
	 * @return array
	 */
	public static function activity( array $outline, $current, $kind ) {
		$items   = array();
		$lessons = array();
		foreach ( $outline['units'] as $unit ) {
			$items = array_merge( $items, $unit['items'] );
			foreach ( $unit['groups'] ?? array() as $lesson ) {
				$lessons[ $lesson['id'] ] = $lesson + array( 'url' => self::url( $outline['id'], $lesson['id'] ) );
			}
		}
		if ( ! empty( $outline['challenge'] ) && ! in_array( $outline['challenge'], $items, true ) ) {
			$items[] = $outline['challenge'];
		}
		foreach ( $items as $index => $item ) {
			$matches = 'practice' === $kind ? 'practice' === $item['kind'] && (int) ( $item['term_id'] ?? 0 ) === (int) $current : 'practice' !== $item['kind'] && (int) ( $item['content_id'] ?? 0 ) === (int) $current;
			if ( ! $matches ) {
				continue;
			}
			$previous = null;
			$next     = null;
			foreach ( array_reverse( array_slice( $items, 0, $index ) ) as $candidate ) {
				if ( ! empty( $candidate['available'] ) ) {
					$previous = $candidate;
					break;
				}
			}
			foreach ( array_slice( $items, $index + 1 ) as $candidate ) {
				if ( ! empty( $candidate['available'] ) ) {
					$next = $candidate;
					break;
				}
			}
			return array(
				'current'  => $item,
				'lesson'   => $lessons[ $item['group_id'] ?? '' ] ?? null,
				'previous' => $previous,
				'next'     => $next,
			);
		}
		return array();
	}
}
