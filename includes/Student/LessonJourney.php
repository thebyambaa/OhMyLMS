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
	 * Fragment of a unit's section on the course overview; anchors follow unit position.
	 *
	 * @param array $outline Assigned outline.
	 * @param int   $unit_id Unit (chapter) ID.
	 * @return string `#hub-unit-N`, or empty when the outline does not list the unit.
	 */
	public static function unit_anchor( array $outline, $unit_id ) {
		foreach ( array_values( $outline['units'] ) as $index => $unit ) {
			if ( (int) $unit['id'] === (int) $unit_id ) {
				return '#hub-unit-' . ( $index + 1 );
			}
		}
		return '';
	}

	/**
	 * Where to continue from a unit: its first open activity, else the first open one in a later unit,
	 * else the course challenge. A unit the outline does not list continues from the first unit.
	 *
	 * @param array $outline Assigned outline.
	 * @param int   $unit_id Unit (chapter) ID to continue from.
	 * @param array $skip    Activity to leave out, such as the practice the learner just finished.
	 * @return array|null `item`, `unit` (null for the course challenge) and `same_unit`, or null when nothing is open.
	 */
	public static function continue_from( array $outline, $unit_id, array $skip = array() ) {
		$open    = static function ( $item ) use ( $skip ) {
			return $item && $item !== $skip && ! empty( $item['available'] ) && empty( $item['complete'] );
		};
		$reached = '' === self::unit_anchor( $outline, $unit_id );
		foreach ( $outline['units'] as $unit ) {
			$same    = (int) $unit['id'] === (int) $unit_id;
			$reached = $reached || $same;
			foreach ( $reached ? $unit['items'] : array() as $item ) {
				if ( $open( $item ) ) {
					return array(
						'item'      => $item,
						'unit'      => $unit,
						'same_unit' => $same,
					);
				}
			}
		}
		return $open( $outline['challenge'] ?? null ) ? array(
			'item'      => $outline['challenge'],
			'unit'      => null,
			'same_unit' => false,
		) : null;
	}

	/**
	 * Resolve adjacent available activities for the player.
	 *
	 * @param array  $outline Assigned outline.
	 * @param int    $current Content or skill ID.
	 * @param string $kind Content/practice namespace.
	 * @return array
	 */
	public static function activity( array $outline, $current, $kind ) {
		$items = array();
		foreach ( $outline['units'] as $unit ) {
			$items = array_merge( $items, $unit['items'] );
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
				'previous' => $previous,
				'next'     => $next,
			);
		}
		return array();
	}
}
