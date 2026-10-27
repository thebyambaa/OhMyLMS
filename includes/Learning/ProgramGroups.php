<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Lesson groups inside a unit: named, ordered sets of a unit's program items. Groups live in the
 * program JSON only (no table), and a program without groups behaves exactly as before.
 *
 * Pure rules (no WordPress calls) so they can be checked without a database.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Validation and arrangement of the optional "groups" list of a course program. */
final class ProgramGroups {
	const MAX_GROUPS = 200;
	const MAX_TITLE  = 120;

	/**
	 * Validate the raw group list.
	 *
	 * @param mixed $raw      Submitted groups: [{id, chapter_id, title}], array order is the order within a unit.
	 * @param int[] $chapters Chapter (unit) IDs of this course.
	 * @return array|string   Clean groups, or an error code: oversized|invalid|id|chapter|title.
	 */
	public static function normalize( $raw, array $chapters ) {
		if ( null === $raw || array() === $raw ) {
			return array(); }
		if ( ! is_array( $raw ) ) {
			return 'invalid'; }
		if ( count( $raw ) > self::MAX_GROUPS ) {
			return 'oversized'; }
		$groups = array();
		$seen   = array();
		foreach ( $raw as $group ) {
			if ( ! is_array( $group ) ) {
				return 'invalid'; }
			$id = $group['id'] ?? '';
			if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $id ) || isset( $seen[ $id ] ) ) {
				return 'id'; }
			$seen[ $id ] = true;
			$chapter     = (int) ( $group['chapter_id'] ?? 0 );
			if ( $chapter <= 0 || ! in_array( $chapter, $chapters, true ) ) {
				return 'chapter'; }
			$title = self::title( $group['title'] ?? '' );
			if ( '' === $title ) {
				return 'title'; }
			$entry = array(
				'id'         => $id,
				'chapter_id' => $chapter,
				'title'      => $title,
			);
			if ( array_key_exists( 'skill_ids', $group ) ) {
				if ( ! is_array( $group['skill_ids'] ) || count( $group['skill_ids'] ) > 200 ) {
					return 'skills';
				}
				foreach ( $group['skill_ids'] as $skill_id ) {
					if ( ! is_int( $skill_id ) || $skill_id <= 0 ) {
						return 'skills';
					}
				}
				$entry['skill_ids'] = array_values( array_unique( $group['skill_ids'] ) );
			}
			$groups[] = $entry;
		}
		return $groups;
	}

	/**
	 * Resolve explicit lesson links, falling back to the legacy single placement.
	 *
	 * @param array $program Course program.
	 * @param array $group Lesson group.
	 * @return int[] Skill identifiers.
	 */
	public static function skill_ids( array $program, array $group ) {
		if ( isset( $group['skill_ids'] ) ) {
			return $group['skill_ids'];
		}
		$ids = array();
		foreach ( $program['outcomes'] ?? array() as $outcome ) {
			if ( ( $outcome['group_id'] ?? '' ) === $group['id'] ) {
				$ids[] = (int) $outcome['term_id'];
			}
		}
		return $ids;
	}

	/**
	 * Preserve explicit skill links when an older editor sends only lesson titles.
	 *
	 * @param array $groups Submitted groups.
	 * @param array $stored Stored groups.
	 * @return array Groups retaining omitted links; an explicit empty list clears them.
	 */
	public static function carry_skills( array $groups, array $stored ) {
		$previous = array_column( $stored, null, 'id' );
		foreach ( $groups as &$group ) {
			if ( is_array( $group ) && ! array_key_exists( 'skill_ids', $group ) && isset( $previous[ $group['id'] ?? '' ]['skill_ids'] ) ) {
				$group['skill_ids'] = $previous[ $group['id'] ]['skill_ids'];
			}
		}
		unset( $group );
		return $groups;
	}

	/**
	 * Plain, single-line, length-limited title.
	 *
	 * @param mixed $value Submitted title.
	 * @return string
	 */
	public static function title( $value ) {
		// strip_tags(), not wp_strip_all_tags(): these rules stay free of WordPress calls so they run without a database.
		$text = trim( preg_replace( '/\s+/u', ' ', strip_tags( is_scalar( $value ) ? (string) $value : '' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_TITLE ) : substr( $text, 0, self::MAX_TITLE );
	}

	/**
	 * Check an item's or outcome's optional group_id against the clean groups.
	 *
	 * @param array $groups  Clean groups from normalize().
	 * @param mixed $group_id Submitted group_id (empty = ungrouped).
	 * @param int   $chapter The item's chapter (unit).
	 * @return string|null   Error code (group|group_chapter), or null when fine.
	 */
	public static function check_member( array $groups, $group_id, $chapter ) {
		if ( null === $group_id || '' === $group_id ) {
			return null; }
		foreach ( $groups as $group ) {
			if ( $group['id'] === $group_id ) {
				return (int) $group['chapter_id'] === (int) $chapter ? null : 'group_chapter'; }
		}
		return 'group';
	}

	/**
	 * Split one unit's rows into its groups and the rest.
	 *
	 * @param array   $groups  Program groups (all units).
	 * @param array[] $rows    The unit's rows, each optionally with 'group_id'.
	 * @param int     $chapter The unit's chapter ID.
	 * @return array{groups: array[], ungrouped: array[]} Groups keep program order; empty groups are left out.
	 */
	public static function arrange( array $groups, array $rows, $chapter ) {
		$by_group = array();
		$loose    = array();
		foreach ( $rows as $row ) {
			$id = (string) ( $row['group_id'] ?? '' );
			if ( '' === $id ) {
				$loose[] = $row;
			} else {
				$by_group[ $id ][] = $row; }
		}
		$out = array();
		foreach ( $groups as $group ) {
			if ( (int) $group['chapter_id'] !== (int) $chapter ) {
				continue; }
			if ( ! empty( $by_group[ $group['id'] ] ) ) {
				$out[] = array(
					'id'    => $group['id'],
					'title' => $group['title'],
					'items' => $by_group[ $group['id'] ],
				);
				unset( $by_group[ $group['id'] ] ); }
		}
		// A row pointing at a group that no longer exists is shown as ungrouped, never dropped.
		foreach ( $by_group as $orphans ) {
			$loose = array_merge( $loose, $orphans ); }
		return array(
			'groups'    => $out,
			'ungrouped' => $loose,
		);
	}

	/**
	 * Replace all lesson groups of ONE unit in a program, leaving every other unit untouched. Rows of the unit that
	 * no group lists become ungrouped. The caller still runs the whole program through CourseProgram::normalize().
	 *
	 * @param array    $program Program (draft) with 'items', 'outcomes' and optionally 'groups'.
	 * @param int      $chapter The unit's chapter ID.
	 * @param array    $spec    New groups in order: [{id?, title, item_ids?: [placement id], skill_ids?: [term id]}].
	 *                            An id must be an existing group of this unit; without one a group is created.
	 * @param callable $new_id  Returns a new group identifier.
	 * @return array|string     The program with the new groups, or an error code:
	 *                            chapter|invalid|id|group|group_chapter|title|item|duplicate.
	 */
	public static function replace_unit( array $program, $chapter, array $spec, callable $new_id ) {
		$chapter = (int) $chapter;
		if ( $chapter <= 0 ) {
			return 'chapter'; }
		$here   = array();
		$others = array();
		$kept   = array();
		foreach ( (array) ( $program['groups'] ?? array() ) as $group ) {
			if ( (int) $group['chapter_id'] === $chapter ) {
				$here[ $group['id'] ] = true;
			} else {
				$others[ $group['id'] ] = true;
				$kept[]                 = $group; }
		}
		$items  = array();
		$skills = array();
		foreach ( (array) ( $program['items'] ?? array() ) as $item ) {
			if ( (int) ( $item['chapter_id'] ?? 0 ) === $chapter ) {
				$items[ (string) $item['id'] ] = true; }
		}
		foreach ( (array) ( $program['outcomes'] ?? array() ) as $outcome ) {
			$skills[ (int) $outcome['term_id'] ] = true;
		}
		$seen        = array();
		$item_group  = array();
		$skill_group = array();
		$created     = array();
		foreach ( $spec as $row ) {
			if ( ! is_array( $row ) ) {
				return 'invalid'; }
			$id = $row['id'] ?? '';
			if ( null === $id || '' === $id ) {
				$id = (string) $new_id();
			} elseif ( ! is_string( $id ) || ! isset( $here[ $id ] ) ) {
				return is_string( $id ) && isset( $others[ $id ] ) ? 'group_chapter' : 'group'; }
			if ( isset( $seen[ $id ] ) ) {
				return 'id'; }
			$seen[ $id ] = true;
			$title       = self::title( $row['title'] ?? '' );
			if ( '' === $title ) {
				return 'title'; }
			foreach ( (array) ( $row['item_ids'] ?? array() ) as $item_id ) {
				if ( ! is_string( $item_id ) || ! isset( $items[ $item_id ] ) ) {
					return 'item'; }
				if ( isset( $item_group[ $item_id ] ) ) {
					return 'duplicate'; }
				$item_group[ $item_id ] = $id;
			}
			foreach ( (array) ( $row['skill_ids'] ?? array() ) as $term_id ) {
				if ( ! is_int( $term_id ) || ! isset( $skills[ $term_id ] ) ) {
					return 'item'; }
				$skill_group[ $term_id ] = $skill_group[ $term_id ] ?? $id;
			}
			$created[] = array(
				'skill_ids'  => array_values( array_unique( $row['skill_ids'] ?? array() ) ),
				'id'         => $id,
				'chapter_id' => $chapter,
				'title'      => $title,
			);
		}
		foreach ( array(
			'items'    => array( 'id', $item_group ),
			'outcomes' => array( 'term_id', $skill_group ),
		) as $list => $map ) {
			foreach ( $program[ $list ] ?? array() as $index => $row ) {
				if ( (int) ( $row['chapter_id'] ?? 0 ) !== $chapter ) {
					continue; }
				unset( $row['group_id'] );
				$key = (string) $row[ $map[0] ];
				if ( isset( $map[1][ $key ] ) ) {
					$row['group_id'] = $map[1][ $key ]; }
				$program[ $list ][ $index ] = $row;
			}
		}
		$groups = array_merge( $kept, $created );
		if ( $groups ) {
			$program['groups'] = $groups;
		} else {
			unset( $program['groups'] ); }
		return $program;
	}

	/**
	 * Carry lesson-group membership over from the stored draft for rows whose payload omits `group_id`
	 * (editors that do not know about groups). A row that moved to another unit is left ungrouped, and a
	 * row that sends `group_id` itself (even empty) is never overridden.
	 * A course-specific activity title is also retained when an older client omits it.
	 *
	 * @param array  $rows     Submitted rows.
	 * @param array  $stored   Rows of the stored draft.
	 * @param string $key      Field that identifies a row: `id` for items, `term_id` for outcomes.
	 * @return array
	 */
	public static function carry( array $rows, array $stored, $key ) {
		$by_key = array();
		foreach ( $stored as $row ) {
			if ( isset( $row[ $key ] ) ) {
				$by_key[ (string) $row[ $key ] ] = $row;
			}
		}
		foreach ( $rows as &$row ) {
			if ( ! isset( $row[ $key ] ) || ! isset( $by_key[ (string) $row[ $key ] ] ) ) {
				continue;
			}
			$old = $by_key[ (string) $row[ $key ] ];
			if ( ! array_key_exists( 'title', $row ) && isset( $old['title'] ) ) {
				$row['title'] = $old['title'];
			}
			if ( array_key_exists( 'group_id', $row ) || empty( $old['group_id'] ) ) {
				continue;
			}
			if ( (int) ( $old['chapter_id'] ?? 0 ) === (int) ( $row['chapter_id'] ?? 0 ) ) {
				$row['group_id'] = $old['group_id'];
			}
		}
		unset( $row );
		return $rows;
	}
}
