<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Pure helpers for curriculum hierarchies stored as parent pointers. They take plain
 * arrays (id => parent id, 0 = root) so the rules can be unit tested without WordPress,
 * and they stay safe on corrupted data: every walk stops at a repeated or missing ID.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

/**
 * Pure helpers for curriculum hierarchies stored as parent pointers. They take plain
 * arrays (id => parent id, 0 = root) so the rules can be unit tested without WordPress,
 * and they stay safe on corrupted data: every walk stops at a repeated or missing ID.
 */
final class Tree {
	/** Ancestor IDs of $id, nearest parent first. Stops at a root, a missing parent or a loop.
	 *
	 * @param array $parents Parents.
	 * @param mixed $id Id.
	 */
	public static function ancestors( array $parents, $id ) {
		$result  = array();
		$seen    = array( (int) $id => true );
		$current = (int) ( $parents[ (int) $id ] ?? 0 );
		while ( $current > 0 && isset( $parents[ $current ] ) && ! isset( $seen[ $current ] ) ) {
			$result[]         = $current;
			$seen[ $current ] = true;
			$current          = (int) $parents[ $current ];
		}
		return $result;
	}

	/** Depth of $id with a root item at 1.
	 *
	 * @param array $parents Parents.
	 * @param mixed $id Id.
	 */
	public static function depth( array $parents, $id ) {
		return 1 + count( self::ancestors( $parents, $id ) );
	}

	/** Direct children of each parent, in the order the IDs appear in $parents.
	 *
	 * @param array $parents Parents.
	 */
	public static function children_map( array $parents ) {
		$map = array();
		foreach ( $parents as $id => $parent ) {
			$map[ (int) $parent ][] = (int) $id; }
		return $map;
	}

	/** Every descendant ID of $id, breadth first. Loops are ignored.
	 *
	 * @param array $parents Parents.
	 * @param mixed $id Id.
	 */
	public static function descendants( array $parents, $id ) {
		$map    = self::children_map( $parents );
		$result = array();
		$seen   = array( (int) $id => true );
		$queue  = array( (int) $id );
		while ( $queue ) {
			$current = array_shift( $queue );
			foreach ( $map[ $current ] ?? array() as $child ) {
				if ( isset( $seen[ $child ] ) ) {
					continue; }
				$seen[ $child ] = true;
				$result[]       = $child;
				$queue[]        = $child;
			}
		}
		return $result;
	}

	/** Number of levels in the subtree under $id, counting $id itself as 1.
	 *
	 * @param array $parents Parents.
	 * @param mixed $id Id.
	 */
	public static function height( array $parents, $id ) {
		$map   = self::children_map( $parents );
		$best  = 1;
		$seen  = array( (int) $id => true );
		$level = array( (int) $id );
		$depth = 1;
		while ( $level ) {
			$next = array();
			foreach ( $level as $current ) {
				foreach ( $map[ $current ] ?? array() as $child ) {
					if ( isset( $seen[ $child ] ) ) {
						continue; }
					$seen[ $child ] = true;
					$next[]         = $child;
				}
			}
			if ( $next ) {
				++$depth;
				$best = $depth; }
			$level = $next;
		}
		return $best;
	}

	/** Would placing $id under $new_parent make it its own ancestor?
	 *
	 * @param array $parents Parents.
	 * @param mixed $id Id.
	 * @param mixed $new_parent New parent.
	 */
	public static function would_cycle( array $parents, $id, $new_parent ) {
		$id         = (int) $id;
		$new_parent = (int) $new_parent;
		if ( 0 === $new_parent ) {
			return false; }
		if ( $new_parent === $id ) {
			return true; }
		return in_array( $id, self::ancestors( $parents, $new_parent ), true );
	}

	/** Insert $id into an ordered list of sibling IDs at a zero-based index (clamped; null appends).
	 *
	 * @param array $siblings Siblings.
	 * @param mixed $id Id.
	 * @param mixed $position Position.
	 */
	public static function insert_at( array $siblings, $id, $position = null ) {
		$siblings = array_values(
			array_filter(
				array_map( 'intval', $siblings ),
				static function ( $other ) use ( $id ) {
					return $other !== (int) $id;
				}
			)
		);
		$index    = null === $position ? count( $siblings ) : max( 0, min( count( $siblings ), (int) $position ) );
		array_splice( $siblings, $index, 0, array( (int) $id ) );
		return $siblings;
	}

	/** Dense positions 0..n-1 for an ordered ID list.
	 *
	 * @param array $ordered Ordered.
	 */
	public static function positions( array $ordered ) {
		return array_flip( array_values( array_map( 'intval', $ordered ) ) );
	}
}
