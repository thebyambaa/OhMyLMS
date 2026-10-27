<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping.
/**
 * Shared, storage-independent ordering rules for course structures.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Keep legacy unplaced rows and unknown extension fields while normalizing order. */
final class StructurePlan {

	/**
	 * Order activities exactly as the unit/lesson outline is presented.
	 *
	 * @param array $items Normalized activities.
	 * @param array $groups Ordered lesson groups.
	 * @param array $chapter_ids Ordered native units.
	 * @return array
	 */
	public static function order_activities( array $items, array $groups, array $chapter_ids ) {
		$by_unit = array();
		foreach ( $items as $item ) {
			$by_unit[ (int) $item['chapter_id'] ][] = $item;
		}
		$result = array();
		foreach ( array_unique( array_merge( $chapter_ids, array( 0 ), array_keys( $by_unit ) ) ) as $chapter ) {
			$rows      = $by_unit[ $chapter ] ?? array();
			$group_ids = array();
			foreach ( $groups as $group ) {
				if ( (int) $chapter !== (int) $group['chapter_id'] ) {
					continue;
				}
				$group_ids[ $group['id'] ] = true;
				foreach ( $rows as $row ) {
					if ( ( $row['group_id'] ?? '' ) === $group['id'] ) {
						$result[] = $row;
					}
				}
			}
			foreach ( $rows as $row ) {
				if ( ! isset( $group_ids[ $row['group_id'] ?? '' ] ) ) {
					$result[] = $row;
				}
			}
		}
		return $result;
	}

	/**
	 * Order unit views by the canonical chapter list; retain unmatched legacy units last.
	 *
	 * @param array $units Unit views keyed by chapter ID.
	 * @param array $chapters Ordered canonical chapter rows.
	 * @return array
	 */
	public static function order_units( array $units, array $chapters ) {
		$ordered = array();
		foreach ( $chapters as $chapter ) {
			$id = (int) $chapter['id'];
			if ( isset( $units[ $id ] ) ) {
				$ordered[ $id ] = $units[ $id ];
				unset( $units[ $id ] );
			}
		}
		return $ordered + $units;
	}

	/**
	 * Normalize legacy explicit positions without accepting ties or incomplete positions.
	 *
	 * @param array $rows Submitted rows, in array order when positions are omitted.
	 * @return array|string Ordered rows, or an error code.
	 */
	public static function order_rows( array $rows ) {
		$rows      = array_values( $rows );
		$positions = array();
		$explicit  = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				return 'row';
			}
			if ( array_key_exists( 'order_number', $row ) ) {
				$position = filter_var( $row['order_number'], FILTER_VALIDATE_INT );
				if ( false === $position || $position < 0 || isset( $positions[ $position ] ) ) {
					return 'position';
				}
				$positions[ $position ] = true;
				++$explicit;
			}
		}
		if ( $explicit && count( $rows ) !== $explicit ) {
			return 'position';
		}
		if ( $explicit ) {
			usort(
				$rows,
				static function ( $left, $right ) {
					return (int) $left['order_number'] <=> (int) $right['order_number'];
				}
			);
		}
		return $rows;
	}
}
