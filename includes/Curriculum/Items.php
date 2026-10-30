<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The curriculum hierarchy: items of any type at any depth, each with a stable ID and UUID,
 * a parent, an item type, an order among its siblings and optional description, syllabus
 * code and version. Learning mode (traditional, skill-based, blended) lives with each
 * course's learning program and is deliberately not stored here.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * The curriculum hierarchy: items of any type at any depth, each with a stable ID and UUID,
 * a parent, an item type, an order among its siblings and optional description, syllabus
 * code and version. Learning mode (traditional, skill-based, blended) lives with each
 * course's learning program and is deliberately not stored here.
 *
 * Every structural write runs under one named lock and a transaction, so two
 * administrators cannot move items past each other into a cycle.
 */
final class Items {
	const MAX_DEPTH       = 10;
	const MAX_ITEMS       = 5000;
	const LOCK            = 'ohmylms-curriculum';
	const SUGGESTED_TYPES = array( 'framework', 'qualification', 'level', 'grade', 'section', 'subject', 'syllabus', 'strand', 'topic', 'unit', 'custom' );

	/**
	 * Table for Items.
	 */
	public static function table() {
		return Schema::table( 'curriculum_items' ); }

	/** Suggested item types. Any lowercase slug is accepted, so administrators can define their own. */
	public static function types() {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) apply_filters( 'ohmylms_curriculum_item_types', self::SUGGESTED_TYPES ) ) ) ) );
	}

	/**
	 * Get for Items.
	 *
	 * @param mixed $id Id.
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		return $id > 0 ? ( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', $id ), ARRAY_A ) ?: null ) : null;
	}

	/**
	 * Missing for Items.
	 */
	public static function missing() {
		return Access::error( 'ohmylms_curriculum_missing', __( 'Curriculum item not found.', 'ohmylms' ), 404 );
	}

	/**
	 * Number of items of every level, and how many are syllabuses.
	 *
	 * @return array{total:int,syllabuses:int}
	 */
	public static function counts() {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS total, SUM(is_syllabus = 1) AS syllabuses FROM %i', self::table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One fresh aggregate.
		return array(
			'total'      => $row ? (int) $row->total : 0,
			'syllabuses' => $row ? (int) $row->syllabuses : 0,
		);
	}

	/** id => parent id for every item. */
	public static function parents() {
		global $wpdb;
		$map = array();
		foreach ( $wpdb->get_results( 'SELECT id, parent_id FROM ' . self::table() . ' ORDER BY parent_id, position, id', ARRAY_A ) as $row ) {
			$map[ (int) $row['id'] ] = (int) $row['parent_id']; }
		return $map;
	}

	/**
	 * All for Items.
	 *
	 * @return array[] Rows ordered for display: by parent, then sibling position. */
	public static function all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY parent_id, position, id', ARRAY_A ) ?: array();
	}

	/** Ordered child IDs of a parent.
	 *
	 * @param mixed $parent_id Parent identifier.
	 */
	public static function child_ids( $parent_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE parent_id=%d ORDER BY position, id', (int) $parent_id ) ) );
	}

	/** The item itself followed by every descendant ID.
	 *
	 * @param mixed $id Id.
	 */
	public static function with_descendants( $id ) {
		return array_merge( array( (int) $id ), Tree::descendants( self::parents(), $id ) );
	}

	/** Ancestor rows from the root down to the item's parent.
	 *
	 * @param mixed $id Id.
	 */
	public static function path( $id ) {
		$parents = self::parents();
		$rows    = array();
		foreach ( array_reverse( Tree::ancestors( $parents, $id ) ) as $ancestor ) {
			$row = self::get( $ancestor );
			if ( $row ) {
				$rows[] = $row; }
		}
		return $rows;
	}

	/** Human readable ancestry such as "Cambridge > IGCSE > Mathematics".
	 *
	 * @param mixed $id Id.
	 * @param bool  $include_self Include self.
	 */
	public static function path_names( $id, $include_self = true ) {
		$names = array_map(
			static function ( $row ) {
				return $row['name'];
			},
			self::path( $id )
		);
		if ( $include_self ) {
			$row = self::get( $id );
			if ( $row ) {
				$names[] = $row['name']; }
		}
		return $names;
	}

	/** Validate and clean writable fields. With $partial, only supplied fields are returned.
	 *
	 * @param array $data Input data.
	 * @param bool  $partial Partial.
	 */
	public static function clean( array $data, $partial = false ) {
		$fields = array();
		if ( array_key_exists( 'icon', $data ) ) {
			$icon = Icons::clean( $data['icon'] );
			if ( is_wp_error( $icon ) ) {
				return $icon; }
			$fields['icon'] = $icon;
		}
		if ( ! $partial || array_key_exists( 'name', $data ) ) {
			$name = trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );
			if ( '' === $name ) {
				return Access::error( 'ohmylms_curriculum_invalid', __( 'A name is required.', 'ohmylms' ) ); }
			if ( mb_strlen( $name ) > 190 ) {
				return Access::error( 'ohmylms_curriculum_invalid', __( 'Names can have at most 190 characters.', 'ohmylms' ) ); }
			$fields['name'] = $name;
		}
		if ( ! $partial || array_key_exists( 'item_type', $data ) ) {
			$type = sanitize_key( (string) ( $data['item_type'] ?? '' ) );
			if ( '' === $type ) {
				$type = 'custom'; }
			if ( ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/', $type ) ) {
				return Access::error( 'ohmylms_curriculum_invalid', __( 'An item type must start with a letter and use only lowercase letters, numbers, hyphens or underscores.', 'ohmylms' ) ); }
			$fields['item_type'] = $type;
		}
		// Any item can be a syllabus. Typing the item "syllabus" turns the flag on unless it is given explicitly.
		if ( array_key_exists( 'is_syllabus', $data ) ) {
			$fields['is_syllabus'] = rest_sanitize_boolean( $data['is_syllabus'] ) ? 1 : 0;
		} elseif ( ( $fields['item_type'] ?? '' ) === 'syllabus' ) {
			$fields['is_syllabus'] = 1;
		}
		if ( ! $partial || array_key_exists( 'description', $data ) ) {
			$description = sanitize_textarea_field( (string) ( $data['description'] ?? '' ) );
			if ( mb_strlen( $description ) > 2000 ) {
				return Access::error( 'ohmylms_curriculum_invalid', __( 'Descriptions can have at most 2000 characters.', 'ohmylms' ) ); }
			$fields['description'] = $description;
		}
		foreach ( array(
			'code'    => __( 'The syllabus code', 'ohmylms' ),
			'version' => __( 'The version', 'ohmylms' ),
		) as $key => $label ) {
			if ( ! $partial || array_key_exists( $key, $data ) ) {
				$value = trim( sanitize_text_field( (string) ( $data[ $key ] ?? '' ) ) );
				if ( mb_strlen( $value ) > 60 ) {
					return Access::error( 'ohmylms_curriculum_invalid', sprintf( /* translators: %s: text value. */
					__( '%s can have at most 60 characters.', 'ohmylms' ), $label ) ); }
				$fields[ $key ] = $value;
			}
		}
		return $fields;
	}

	/** Run structural work under the global curriculum lock and one transaction.
	 *
	 * @param callable $work Work.
	 */
	public static function exclusive( callable $work ) {
		global $wpdb;
		$lock = self::LOCK . '-' . md5( $wpdb->prefix );
		if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== '1' ) {
			return Access::error( 'ohmylms_curriculum_busy', __( 'Another change is being saved. Try again in a moment.', 'ohmylms' ), 409 );
		}
		try {
			return Transaction::run( $work );
		} catch ( \Throwable $error ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OhMyLMS curriculum write failed: ' . $error->getMessage() ); }
			return Access::error( 'ohmylms_curriculum_failed', __( 'The change could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/** Write dense 0..n-1 positions for a sibling group, touching only rows that changed.
	 *
	 * @param mixed $parent_id Parent identifier.
	 * @param array $ordered Ordered.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function renumber( $parent_id, array $ordered ) {
		global $wpdb;
		$current = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, position FROM ' . self::table() . ' WHERE parent_id=%d', (int) $parent_id ), ARRAY_A ) as $row ) {
			$current[ (int) $row['id'] ] = (int) $row['position']; }
		foreach ( Tree::positions( $ordered ) as $id => $position ) {
			if ( ( $current[ $id ] ?? -1 ) !== $position && $wpdb->update( self::table(), array( 'position' => $position ), array( 'id' => (int) $id ) ) === false ) {
				throw new \RuntimeException( 'Position write failed' ); }
		}
	}

	/**
	 * Create for Items.
	 *
	 * @param array $data Input data.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$fields = self::clean( $data );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		$parent_id = (int) ( $data['parent_id'] ?? 0 );
		$position  = array_key_exists( 'position', $data ) && null !== $data['position'] && '' !== $data['position'] ? (int) $data['position'] : null;
		$result    = self::exclusive(
			static function () use ( $fields, $parent_id, $position ) {
				return self::insert_locked( $fields, $parent_id, $position );
			}
		);
		return is_wp_error( $result ) ? $result : self::get( $result );
	}

	/**
	 * Insert one item and place it among its siblings. The caller already holds the curriculum lock
	 * and a transaction (create() does, and so does a syllabus import that adds many items at once).
	 *
	 * @return int|\WP_Error The new item ID.
	 * @param array $fields Fields.
	 * @param mixed $parent_id Parent identifier.
	 * @param mixed $position Position.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function insert_locked( array $fields, $parent_id, $position = null ) {
		global $wpdb;
		$parent_id = (int) $parent_id;
		$parents   = self::parents();
		if ( count( $parents ) >= self::MAX_ITEMS ) {
			return Access::error( 'ohmylms_curriculum_limit', sprintf( /* translators: %d: number. */
			__( 'A curriculum can have at most %d items.', 'ohmylms' ), self::MAX_ITEMS ), 409 ); }
		if ( $parent_id && ! isset( $parents[ $parent_id ] ) ) {
			return Access::error( 'ohmylms_curriculum_invalid', __( 'The parent item does not exist.', 'ohmylms' ) ); }
		if ( $parent_id && Tree::depth( $parents, $parent_id ) + 1 > self::MAX_DEPTH ) {
			return Access::error( 'ohmylms_curriculum_depth', sprintf( /* translators: %d: number. */
			__( 'Curriculum items can be nested at most %d levels deep.', 'ohmylms' ), self::MAX_DEPTH ) ); }
		$now = current_time( 'mysql', true );
		$row = $fields + array(
			'uuid'       => wp_generate_uuid4(),
			'parent_id'  => $parent_id,
			'position'   => 0,
			'created_by' => get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		);
		if ( ! $wpdb->insert( self::table(), $row ) ) {
			throw new \RuntimeException( 'Insert failed' ); }
		$id = (int) $wpdb->insert_id;
		self::renumber( $parent_id, Tree::insert_at( self::child_ids_except( $parent_id, $id ), $id, $position ) );
		return $id;
	}

	/** Child IDs of a parent, leaving one item out (used while placing it).
	 *
	 * @param mixed $parent_id Parent identifier.
	 * @param mixed $except Except.
	 */
	private static function child_ids_except( $parent_id, $except ) {
		return array_values(
			array_filter(
				self::child_ids( $parent_id ),
				static function ( $id ) use ( $except ) {
					return $id !== (int) $except;
				}
			)
		);
	}

	/** Update details. The parent is changed only through move().
	 *
	 * @param mixed $id Id.
	 * @param array $data Input data.
	 * @param mixed $expected_updated_at Expected updated at.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function update( $id, array $data, $expected_updated_at = null ) {
		global $wpdb;
		$fields = self::clean( $data, true );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		$result = self::exclusive(
			static function () use ( $wpdb, $id, $fields, $expected_updated_at ) {
				$item = self::get( $id );
				if ( ! $item ) {
					return self::missing(); }
				if ( null !== $expected_updated_at && '' !== $expected_updated_at && (string) $expected_updated_at !== $item['updated_at'] ) {
					return Access::error( 'ohmylms_curriculum_stale', __( 'Someone else changed this item. Reload the curriculum and try again.', 'ohmylms' ), 409 );
				}
				// A syllabus that still has skill groups cannot quietly stop being one: its groups would be hidden.
				if ( array_key_exists( 'is_syllabus', $fields ) && ! $fields['is_syllabus'] && ! empty( $item['is_syllabus'] ) && Syllabus::group_count( self::with_descendants( $id ) ) > 0 ) {
					return Access::error( 'ohmylms_syllabus_has_groups', __( 'This syllabus still has skill groups. Remove them before turning it back into an ordinary item.', 'ohmylms' ), 409 );
				}
				if ( $fields ) {
					$fields['updated_at'] = current_time( 'mysql', true );
					if ( $wpdb->update( self::table(), $fields, array( 'id' => (int) $id ) ) === false ) {
						throw new \RuntimeException( 'Update failed' ); }
				}
				return true;
			}
		);
		return is_wp_error( $result ) ? $result : self::get( $id );
	}

	/**
	 * Move an item under a new parent (0 = root) at a zero-based index among its new siblings.
	 * Reordering is a move to a new index under the same parent.
	 *
	 * @param mixed $id Id.
	 * @param mixed $parent_id Parent identifier.
	 * @param mixed $position Position.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function move( $id, $parent_id, $position = null ) {
		$parent_id = (int) $parent_id;
		$result    = self::exclusive(
			static function () use ( $id, $parent_id, $position ) {
				$parents = self::parents();
				if ( ! isset( $parents[ (int) $id ] ) ) {
					return self::missing(); }
				if ( $parent_id && ! isset( $parents[ $parent_id ] ) ) {
					return Access::error( 'ohmylms_curriculum_invalid', __( 'The new parent does not exist.', 'ohmylms' ) ); }
				if ( Tree::would_cycle( $parents, $id, $parent_id ) ) {
					return Access::error( 'ohmylms_curriculum_cycle', __( 'An item cannot be moved under itself or one of its own descendants.', 'ohmylms' ) ); }
				$base = $parent_id ? Tree::depth( $parents, $parent_id ) : 0;
				if ( $base + Tree::height( $parents, $id ) > self::MAX_DEPTH ) {
					return Access::error( 'ohmylms_curriculum_depth', sprintf( /* translators: %d: number. */
					__( 'Curriculum items can be nested at most %d levels deep.', 'ohmylms' ), self::MAX_DEPTH ) ); }
				global $wpdb;
				$old_parent = (int) $parents[ (int) $id ];
				if ( $old_parent !== $parent_id ) {
					if ( $wpdb->update(
						self::table(),
						array(
							'parent_id'  => $parent_id,
							'updated_at' => current_time( 'mysql', true ),
						),
						array( 'id' => (int) $id )
					) === false ) {
						throw new \RuntimeException( 'Move failed' ); }
					self::renumber( $old_parent, self::child_ids_except( $old_parent, $id ) );
				}
				self::renumber( $parent_id, Tree::insert_at( self::child_ids_except( $parent_id, $id ), $id, $position ) );
				return true;
			}
		);
		if ( ! is_wp_error( $result ) ) {
			Placement::changed(); }
		return is_wp_error( $result ) ? $result : self::get( $id );
	}

	/** What deleting an item would affect: children, content links and bundle memberships.
	 *
	 * @param mixed $id Id.
	 */
	public static function dependents( $id ) {
		global $wpdb;
		$ids          = self::with_descendants( $id );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$bundles      = static function ( array $in ) use ( $wpdb ) {
			$in_placeholders = implode( ',', array_fill( 0, count( $in ), '%d' ) );
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT bundle_id) FROM ' . Schema::table( 'bundle_items' ) . " WHERE item_type='curriculum' AND item_id IN ($in_placeholders)", $in ) );
		};
		return array(
			'children'    => count( self::child_ids( $id ) ),
			'descendants' => count( $ids ) - 1,
			'links'       => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Schema::table( 'curriculum_links' ) . " WHERE item_id IN ($placeholders)", $ids ) ),
			'own_links'   => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE item_id=%d', Schema::table( 'curriculum_links' ), (int) $id ) ),
			'bundles'     => $bundles( $ids ),
			'own_bundles' => $bundles( array( (int) $id ) ),
			// Skill groups go with the item they sit under; the skills in them stay in the skill library.
			'groups'      => Syllabus::group_count( $ids ),
			'own_groups'  => Syllabus::group_count( array( (int) $id ) ),
		);
	}

	/**
	 * Delete an item. Linked content (courses, skills, banks, exams) is never deleted: only the
	 * association disappears. An item with children, links or bundle memberships needs
	 * `$confirm`; an item with children also needs a strategy for them:
	 *   'promote' - children move up to the item's parent, keeping their order;
	 *   'delete'  - the whole branch is removed.
	 *
	 * @param mixed       $id Id.
	 * @param string      $strategy Strategy.
	 * @param bool        $confirm Confirm.
	 * @param string|null $expected_revision Optional delete_revision() the confirmation was shown for.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function delete( $id, $strategy = '', $confirm = false, $expected_revision = null ) {
		global $wpdb;
		$item = self::get( $id );
		if ( ! $item ) {
			return self::missing(); }
		$strategy   = in_array( $strategy, array( 'promote', 'delete' ), true ) ? $strategy : '';
		$dependents = self::dependents( $id );
		if ( $dependents['children'] && '' === $strategy ) {
			return Access::error( 'ohmylms_curriculum_has_children', __( 'This item has children. Choose whether to move them up a level or delete the whole branch.', 'ohmylms' ), 409, array( 'dependents' => $dependents ) );
		}
		$branch           = 'delete' === $strategy;
		$affected_links   = $branch ? $dependents['links'] : $dependents['own_links'];
		$affected_bundles = $branch ? $dependents['bundles'] : $dependents['own_bundles'];
		$affected_groups  = $branch ? $dependents['groups'] : $dependents['own_groups'];
		if ( ! $confirm && ( $dependents['children'] || $affected_links || $affected_bundles || $affected_groups ) ) {
			return Access::error( 'ohmylms_curriculum_needs_confirmation', __( 'Deleting this item removes its structure links and skill groups. Confirm to continue; linked courses, skills, banks and exams, and the skills inside skill groups, are not deleted.', 'ohmylms' ), 409, array( 'dependents' => $dependents ) );
		}
		return self::exclusive(
			static function () use ( $wpdb, $id, $item, $strategy, $branch, $expected_revision ) {
				$parents = self::parents();
				if ( ! isset( $parents[ (int) $id ] ) ) {
					return self::missing(); }
				if ( null !== $expected_revision && ! hash_equals( self::delete_revision( $id ), (string) $expected_revision ) ) {
					return Access::error( 'ohmylms_curriculum_stale', __( 'This item or the branch under it changed. Review the deletion again.', 'ohmylms' ), 409 );
				}
				$parent_id = (int) $item['parent_id'];
				if ( 'promote' === $strategy ) {
					$children = self::child_ids( $id );
					$siblings = self::child_ids_except( $parent_id, $id );
					$index    = array_search( (int) $id, self::child_ids( $parent_id ), true );
					array_splice( $siblings, false === $index ? count( $siblings ) : $index, 0, $children );
					foreach ( $children as $child ) {
						if ( $wpdb->update(
							self::table(),
							array(
								'parent_id'  => $parent_id,
								'updated_at' => current_time( 'mysql', true ),
							),
							array( 'id' => $child )
						) === false ) {
							throw new \RuntimeException( 'Promote failed' ); }
					}
					$removed = array( (int) $id );
					$removal = self::delete_rows( $removed );
					self::renumber( $parent_id, $siblings );
					return array(
						'deleted'  => $removed,
						'promoted' => count( $children ),
					) + $removal;
				}
				$removed = array_merge( array( (int) $id ), $branch ? Tree::descendants( $parents, $id ) : array() );
				$removal = self::delete_rows( $removed );
				self::renumber( $parent_id, self::child_ids_except( $parent_id, $id ) );
				return array(
					'deleted'  => $removed,
					'promoted' => 0,
				) + $removal;
			}
		);
	}

	/**
	 * Hash of an item's details and everything deleting it would affect.
	 *
	 * @param int $id Item ID.
	 * @return string Empty for a missing item.
	 */
	public static function delete_revision( $id ) {
		$item = self::get( $id );
		return $item ? hash( 'sha256', (string) wp_json_encode( array( $item['updated_at'], self::with_descendants( $id ), self::dependents( $id ) ) ) ) : '';
	}

	/** Remove item rows plus every link and bundle membership that points at them.
	 *
	 * @param array $ids Ids.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function delete_rows( array $ids ) {
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$links        = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'curriculum_links' ) . " WHERE item_id IN ($placeholders)", $ids ) );
		$members      = $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Schema::table( 'bundle_items' ) . " WHERE item_type='curriculum' AND item_id IN ($placeholders)", $ids ) );
		$groups       = Syllabus::delete_for_items( $ids );
		if ( false === $links || false === $members || false === $groups || $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . " WHERE id IN ($placeholders)", $ids ) ) === false ) {
			throw new \RuntimeException( 'Delete failed' ); }
		do_action( 'ohmylms_curriculum_items_deleted', $ids );
		return array(
			'links_removed'   => (int) $links,
			'bundles_updated' => (int) $members,
			'groups_removed'  => (int) $groups,
		);
	}

	/** Public, JSON-safe description of one item.
	 *
	 * @param array $row Row.
	 * @param array $counts Counts.
	 */
	public static function describe( array $row, array $counts = array() ) {
		return array(
			'id'          => (int) $row['id'],
			'uuid'        => $row['uuid'],
			'parent_id'   => (int) $row['parent_id'],
			'position'    => (int) $row['position'],
			'item_type'   => $row['item_type'],
			'name'        => $row['name'],
			'icon'        => (string) ( $row['icon'] ?? '' ),
			'description' => (string) $row['description'],
			'code'        => $row['code'],
			'version'     => $row['version'],
			'is_syllabus' => ! empty( $row['is_syllabus'] ),
			'course_id'   => (int) ( $row['course_id'] ?? 0 ),
			'created_at'  => $row['created_at'],
			'updated_at'  => $row['updated_at'],
		) + $counts;
	}

	/** The whole tree as a flat, ordered list with per-item counts (one query per count). */
	public static function tree() {
		global $wpdb;
		$links = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT item_id, object_type, COUNT(*) AS total FROM %i GROUP BY item_id, object_type', Schema::table( 'curriculum_links' ) ), ARRAY_A ) as $row ) {
			$links[ (int) $row['item_id'] ][ $row['object_type'] ] = (int) $row['total']; }
		$bundles = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT item_id, COUNT(DISTINCT bundle_id) AS total FROM %i WHERE item_type=\'curriculum\' GROUP BY item_id', Schema::table( 'bundle_items' ) ), ARRAY_A ) as $row ) {
			$bundles[ (int) $row['item_id'] ] = (int) $row['total']; }
		$rows     = self::all();
		$children = array();
		foreach ( $rows as $row ) {
			$children[ (int) $row['parent_id'] ] = ( $children[ (int) $row['parent_id'] ] ?? 0 ) + 1; }
		$syllabuses = Syllabus::totals( $rows );
		return array_map(
			static function ( $row ) use ( $links, $bundles, $children, $syllabuses ) {
				$id     = (int) $row['id'];
				$counts = array(
					'child_count'  => $children[ $id ] ?? 0,
					'links'        => ( $links[ $id ] ?? array() ) + array_fill_keys( Links::TYPES, 0 ),
					'bundle_count' => $bundles[ $id ] ?? 0,
				);
				// Skill groups and skills inside a syllabus, counting everything under it.
				if ( isset( $syllabuses[ $id ] ) ) {
					$counts['syllabus'] = $syllabuses[ $id ]; }
				return self::describe( $row, $counts );
			},
			$rows
		);
	}
}
