<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * A syllabus is a curriculum item flagged `is_syllabus` (any item can be one: an exam board's
 * Mathematics 0580, a national grade 11 programme). Inside it:.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * A syllabus is a curriculum item flagged `is_syllabus` (any item can be one: an exam board's
 * Mathematics 0580, a national grade 11 programme). Inside it:
 *
 *   contents      ordinary curriculum items beneath it (topics, chapters, units), nested as deep as needed
 *   skill groups  named, coded, ordered groups of skills (a section, a skill family) that sit under the
 *                 syllabus or under one of its content items
 *   skills        library skills (the `ohmylms_skill` taxonomy) placed in a group, in order
 *
 * Skills created here are ordinary library skills, so questions, lessons, practice and evidence work on
 * them as on any other. They are created under one "root" library skill per syllabus, which keeps equal
 * objective texts from different syllabuses apart (skills are never merged by name) and gives the
 * library a tree that mirrors the syllabus. A placement is a reference: removing a skill from a group or
 * deleting a group never deletes the skill.
 */
final class Syllabus {
	const MAX_GROUPS    = SyllabusPlan::MAX_GROUPS;
	const MAX_SKILLS    = SyllabusPlan::MAX_SKILLS;
	const CODE_META     = '_ohmylms_skill_code';
	const CATEGORY_META = '_ohmylms_skill_category';
	const ROOT_META     = '_ohmylms_syllabus_item';

	/**
	 * Groups table for Syllabus.
	 */
	public static function groups_table() {
		return Schema::table( 'syllabus_groups' ); }
	/**
	 * Placements table for Syllabus.
	 */
	public static function placements_table() {
		return Schema::table( 'syllabus_group_skills' ); }

	/** Names and notes come back from WordPress with entities ("a &lt; 0"); the editor wants the real text.
	 *
	 * @param mixed $value Value to process.
	 */
	public static function text( $value ) {
		return Taxonomy::plain( $value );
	}

	/**
	 * Now for Syllabus.
	 */
	private static function now() {
		return current_time( 'mysql', true ); }
	/**
	 * Placeholders for Syllabus.
	 *
	 * @param array $ids Ids.
	 */
	private static function placeholders( array $ids ) {
		return implode( ',', array_fill( 0, count( $ids ), '%d' ) ); }
	/**
	 * Ints for Syllabus.
	 *
	 * @param array $ids Ids.
	 */
	private static function ints( array $ids ) {
		return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) ); }

	/**
	 * Is syllabus for Syllabus.
	 *
	 * @param mixed $item_id Item identifier.
	 */
	public static function is_syllabus( $item_id ) {
		$item = Items::get( $item_id );
		return $item && ! empty( $item['is_syllabus'] );
	}

	/** The syllabus an item belongs to: itself or the nearest ancestor flagged as one (0 for none).
	 *
	 * @param mixed $item_id Item identifier.
	 */
	public static function owner( $item_id ) {
		global $wpdb;
		$flagged = array_map( 'intval', $wpdb->get_col( 'SELECT id FROM ' . Items::table() . ' WHERE is_syllabus=1' ) );
		if ( ! $flagged ) {
			return 0; }
		foreach ( array_merge( array( (int) $item_id ), Tree::ancestors( Items::parents(), $item_id ) ) as $id ) {
			if ( in_array( $id, $flagged, true ) ) {
				return $id; }
		}
		return 0;
	}

	/**
	 * Group for Syllabus.
	 *
	 * @param mixed $group_id Group identifier.
	 */
	public static function group( $group_id ) {
		global $wpdb;
		$group_id = (int) $group_id;
		return $group_id > 0 ? ( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::groups_table() . ' WHERE id=%d', $group_id ), ARRAY_A ) ?: null ) : null;
	}

	/**
	 * Missing group for Syllabus.
	 */
	private static function missing_group() {
		return Access::error( 'ohmylms_syllabus_group_missing', __( 'Skill group not found.', 'ohmylms' ), 404 );
	}

	/**
	 * Not a syllabus for Syllabus.
	 */
	private static function not_a_syllabus() {
		return Access::error( 'ohmylms_syllabus_invalid', __( 'This item is not a syllabus. Turn on “This item is a syllabus” first.', 'ohmylms' ), 400 );
	}

	/** Skill groups sitting under any of these items.
	 *
	 * @param array $item_ids Item ids.
	 */
	public static function group_count( array $item_ids ) {
		global $wpdb;
		$item_ids = self::ints( $item_ids );
		if ( ! $item_ids || ! Schema::ready() ) {
			return 0; }
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::groups_table() . ' WHERE item_id IN (' . self::placeholders( $item_ids ) . ')', $item_ids ) );
	}

	/** Per syllabus: its skill groups and skill placements, counting everything beneath it.
	 *
	 * @param array $rows Rows.
	 */
	public static function totals( array $rows ) {
		global $wpdb;
		$syllabuses = array();
		$parents    = array();
		foreach ( $rows as $row ) {
			$parents[ (int) $row['id'] ] = (int) $row['parent_id'];
			if ( ! empty( $row['is_syllabus'] ) ) {
				$syllabuses[] = (int) $row['id']; }
		}
		if ( ! $syllabuses ) {
			return array(); }
		$per_item = array();
		foreach ( $wpdb->get_results( 'SELECT g.item_id, COUNT(DISTINCT g.id) AS groups_total, COUNT(p.term_id) AS skills_total FROM ' . self::groups_table() . ' g LEFT JOIN ' . self::placements_table() . ' p ON p.group_id=g.id GROUP BY g.item_id', ARRAY_A ) as $row ) {
			$per_item[ (int) $row['item_id'] ] = array( (int) $row['groups_total'], (int) $row['skills_total'] );
		}
		$totals = array();
		foreach ( $syllabuses as $id ) {
			$groups = 0;
			$skills = 0;
			foreach ( array_merge( array( $id ), Tree::descendants( $parents, $id ) ) as $item ) {
				$groups += $per_item[ $item ][0] ?? 0;
				$skills += $per_item[ $item ][1] ?? 0;
			}
			$totals[ $id ] = array(
				'groups' => $groups,
				'skills' => $skills,
			);
		}
		return $totals;
	}

	// ---- Reading ---------------------------------------------------------------------------------

	/**
	 * The whole syllabus for the editor: its content items in tree order (the syllabus itself first),
	 * each with its skill groups, each with its skills.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	public static function outline( $syllabus_id ) {
		global $wpdb;
		$item = Items::get( $syllabus_id );
		if ( ! $item ) {
			return Items::missing(); }
		if ( empty( $item['is_syllabus'] ) ) {
			return self::not_a_syllabus(); }
		$by_parent = array();
		foreach ( Items::all() as $row ) {
			$by_parent[ (int) $row['parent_id'] ][] = $row; }
		$order = array();
		$seen  = array();
		$walk  = static function ( $row, $depth ) use ( &$walk, &$order, &$seen, $by_parent ) {
			if ( isset( $seen[ (int) $row['id'] ] ) ) {
				return; }
			$seen[ (int) $row['id'] ] = true;
			$order[]                  = array( $row, $depth );
			foreach ( $by_parent[ (int) $row['id'] ] ?? array() as $child ) {
				$walk( $child, $depth + 1 ); }
		};
		$walk( $item, 0 );
		$ids        = array_map(
			static function ( $entry ) {
				return (int) $entry[0]['id'];
			},
			$order
		);
		$groups     = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::groups_table() . ' WHERE item_id IN (' . self::placeholders( $ids ) . ') ORDER BY item_id, position, id', $ids ), ARRAY_A ) ?: array();
		$group_ids  = array_map(
			static function ( $group ) {
				return (int) $group['id'];
			},
			$groups
		);
		$placements = $group_ids ? ( $wpdb->get_results( $wpdb->prepare( 'SELECT group_id, term_id FROM ' . self::placements_table() . ' WHERE group_id IN (' . self::placeholders( $group_ids ) . ') ORDER BY group_id, position, term_id', $group_ids ), ARRAY_A ) ?: array() ) : array();
		$term_ids   = self::ints( array_column( $placements, 'term_id' ) );
		$terms      = array();
		if ( $term_ids ) {
			foreach ( get_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'include'    => $term_ids,
					'hide_empty' => false,
					'number'     => 0,
				)
			) as $term ) {
				$terms[ (int) $term->term_id ] = $term; }
		}
		$connections = SkillMappings::connections_for_skills( $term_ids );
		$skills_of   = array();
		foreach ( $placements as $placement ) {
			$term = $terms[ (int) $placement['term_id'] ] ?? null;
			if ( ! $term ) {
				continue; }
			$skills_of[ (int) $placement['group_id'] ][] = array(
				'term_id'           => (int) $term->term_id,
				'connections'       => $connections[ (int) $term->term_id ] ?? array(),
				'name'              => self::text( $term->name ),
				'objective'         => \OhMyLMS\Skills\Objectives::get( $term ),
				'code'              => (string) get_term_meta( $term->term_id, self::CODE_META, true ),
				'category'          => (string) get_term_meta( $term->term_id, self::CATEGORY_META, true ),
				'translations'      => \OhMyLMS\Skills\Translations::get( $term->term_id ),
				'category_assigned' => metadata_exists( 'term', $term->term_id, self::CATEGORY_META ),
				'description'       => self::text( $term->description ),
			);
		}
		$groups_of = array();
		foreach ( $groups as $group ) {
			$groups_of[ (int) $group['item_id'] ][] = array(
				'id'          => (int) $group['id'],
				'uuid'        => $group['uuid'],
				'item_id'     => (int) $group['item_id'],
				'position'    => (int) $group['position'],
				'code'        => (string) $group['code'],
				'name'        => self::text( $group['name'] ),
				'icon'        => (string) ( $group['icon'] ?? '' ),
				'description' => self::text( $group['description'] ),
				'skills'      => $skills_of[ (int) $group['id'] ] ?? array(),
			);
		}
		$contents     = array();
		$skills_total = 0;
		foreach ( $order as [$row, $depth] ) {
			$entry_groups = $groups_of[ (int) $row['id'] ] ?? array();
			foreach ( $entry_groups as $group ) {
				$skills_total += count( $group['skills'] ); }
			$contents[] = array(
				'id'        => (int) $row['id'],
				'parent_id' => (int) $row['parent_id'],
				'depth'     => $depth,
				'item_type' => $row['item_type'],
				'name'      => self::text( $row['name'] ),
				'icon'      => (string) ( $row['icon'] ?? '' ),
				'code'      => (string) $row['code'],
				'groups'    => $entry_groups,
			);
		}
		return array(
			'syllabus'        => Items::describe( $item ),
			'roadmap_sources' => $item['course_id'] ? array_filter( (array) get_post_meta( $item['course_id'], Roadmaps::SOURCES_META, true ) ) : array(),
			'contents'        => $contents,
			'totals'          => array(
				'contents' => count( $contents ) - 1,
				'groups'   => count( $groups ),
				'skills'   => $skills_total,
			),
			'root_skill'      => (int) $item['skill_root_id'],
		);
	}

	/** The outline in the shape the import planner works on.
	 *
	 * @param array $outline Outline.
	 */
	private static function plan_state( array $outline ) {
		$state = array(
			'root'     => (int) $outline['syllabus']['id'],
			'contents' => array(),
			'groups'   => array(),
		);
		foreach ( $outline['contents'] as $content ) {
			$state['contents'][] = array(
				'id'        => $content['id'],
				'parent_id' => $content['parent_id'],
				'name'      => $content['name'],
				'code'      => $content['code'],
			);
			foreach ( $content['groups'] as $group ) {
				$state['groups'][] = array(
					'id'      => $group['id'],
					'item_id' => $group['item_id'],
					'name'    => $group['name'],
					'code'    => $group['code'],
					'skills'  => $group['skills'],
				);
			}
		}
		return $state;
	}

	/** Library skills placed in any group under these items.
	 *
	 * @param array $item_ids Item ids.
	 */
	public static function skill_ids( array $item_ids ) {
		global $wpdb;
		$item_ids = self::ints( $item_ids );
		if ( ! $item_ids || ! Schema::ready() ) {
			return array(); }
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT p.term_id FROM ' . self::placements_table() . ' p JOIN ' . self::groups_table() . ' g ON g.id=p.group_id WHERE g.item_id IN (' . self::placeholders( $item_ids ) . ') ORDER BY p.term_id', $item_ids ) ) );
	}

	/** The curriculum items whose skill groups hold this skill (for showing where a skill belongs).
	 *
	 * @param mixed $term_id Term identifier.
	 */
	public static function item_ids_for_skill( $term_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return array(); }
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT g.item_id FROM ' . self::placements_table() . ' p JOIN ' . self::groups_table() . ' g ON g.id=p.group_id WHERE p.term_id=%d ORDER BY g.item_id', (int) $term_id ) ) );
	}

	// ---- Ordering helpers -------------------------------------------------------------------------

	/**
	 * Group ids in for Syllabus.
	 *
	 * @param mixed $item_id Item identifier.
	 */
	private static function group_ids_in( $item_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::groups_table() . ' WHERE item_id=%d ORDER BY position, id', (int) $item_id ) ) );
	}

	/**
	 * Renumber groups for Syllabus.
	 *
	 * @param array $ordered Ordered.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function renumber_groups( array $ordered ) {
		global $wpdb;
		foreach ( array_values( $ordered ) as $position => $id ) {
			if ( $wpdb->update( self::groups_table(), array( 'position' => $position ), array( 'id' => (int) $id ) ) === false ) {
				throw new \RuntimeException( 'Group position write failed' ); }
		}
	}

	/**
	 * Term ids in for Syllabus.
	 *
	 * @param mixed $group_id Group identifier.
	 */
	private static function term_ids_in( $group_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT term_id FROM ' . self::placements_table() . ' WHERE group_id=%d ORDER BY position, term_id', (int) $group_id ) ) );
	}

	/**
	 * Renumber skills for Syllabus.
	 *
	 * @param mixed $group_id Group identifier.
	 * @param array $ordered Ordered.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function renumber_skills( $group_id, array $ordered ) {
		global $wpdb;
		foreach ( array_values( $ordered ) as $position => $term_id ) {
			if ( $wpdb->update(
				self::placements_table(),
				array( 'position' => $position ),
				array(
					'group_id' => (int) $group_id,
					'term_id'  => (int) $term_id,
				)
			) === false ) {
				throw new \RuntimeException( 'Skill position write failed' ); }
		}
	}

	/**
	 * Skill count for Syllabus.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 */
	private static function skill_count( $syllabus_id ) {
		return count( self::skill_ids( Items::with_descendants( $syllabus_id ) ) );
	}

	/** The group, if it belongs to this syllabus.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 */
	private static function owned_group( $syllabus_id, $group_id ) {
		$group = self::group( $group_id );
		if ( ! $group || self::owner( (int) $group['item_id'] ) !== (int) $syllabus_id ) {
			return null; }
		return $group;
	}

	// ---- Skill groups -----------------------------------------------------------------------------

	/**
	 * Clean group for Syllabus.
	 *
	 * @param array $data Input data.
	 * @param mixed $partial Partial.
	 */
	private static function clean_group( array $data, $partial ) {
		$fields = array();
		if ( array_key_exists( 'icon', $data ) ) {
			$icon = Icons::clean( $data['icon'] );
			if ( is_wp_error( $icon ) ) {
				return $icon; }
			$fields['icon'] = $icon;
		}
		if ( ! $partial || array_key_exists( 'name', $data ) ) {
			$name = SyllabusRows::line( $data['name'] ?? '' );
			// A new group may be given only a code; the code then names it.
			if ( '' === $name && ! $partial ) {
				$name = SyllabusRows::line( $data['code'] ?? '' ); }
			if ( '' === $name ) {
				return Access::error( 'ohmylms_syllabus_invalid', __( 'A skill group needs a name or a code.', 'ohmylms' ) ); }
			if ( mb_strlen( $name ) > SyllabusRows::NAME_LIMIT ) {
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( /* translators: %d: number. */
				__( 'A skill group name can have at most %d characters.', 'ohmylms' ), SyllabusRows::NAME_LIMIT ) ); }
			$fields['name'] = sanitize_text_field( $name );
		}
		if ( ! $partial || array_key_exists( 'code', $data ) ) {
			$code = SyllabusRows::line( $data['code'] ?? '' );
			if ( mb_strlen( $code ) > SyllabusRows::GROUP_CODE_LIMIT ) {
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( /* translators: %d: number. */
				__( 'A skill group code can have at most %d characters.', 'ohmylms' ), SyllabusRows::GROUP_CODE_LIMIT ) ); }
			$fields['code'] = sanitize_text_field( $code );
		}
		if ( ! $partial || array_key_exists( 'description', $data ) ) {
			$description = SyllabusRows::notes( $data['description'] ?? '' );
			if ( mb_strlen( $description ) > SyllabusRows::DESCRIPTION_LIMIT ) {
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( /* translators: %d: number. */
				__( 'Notes can have at most %d characters.', 'ohmylms' ), SyllabusRows::DESCRIPTION_LIMIT ) ); }
			$fields['description'] = sanitize_textarea_field( $description );
		}
		return $fields;
	}

	/**
	 * Insert group for Syllabus.
	 *
	 * @param mixed $item_id Item identifier.
	 * @param array $fields Fields.
	 * @param mixed $position Position.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function insert_group( $item_id, array $fields, $position = null ) {
		global $wpdb;
		$now  = self::now();
		$row  = $fields + array(
			'description' => '',
			'code'        => '',
		);
		$row += array(
			'uuid'       => wp_generate_uuid4(),
			'item_id'    => (int) $item_id,
			'position'   => 0,
			'created_by' => get_current_user_id(),
			'created_at' => $now,
			'updated_at' => $now,
		);
		if ( ! $wpdb->insert( self::groups_table(), $row ) ) {
			throw new \RuntimeException( 'Group insert failed' ); }
		$id = (int) $wpdb->insert_id;
		self::renumber_groups( Tree::insert_at( array_values( array_diff( self::group_ids_in( $item_id ), array( $id ) ) ), $id, $position ) );
		return $id;
	}

	/**
	 * Add group for Syllabus.
	 *
	 * @return int|\WP_Error The new group's ID.
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param array $data Input data.
	 */
	public static function add_group( $syllabus_id, array $data ) {
		if ( ! self::is_syllabus( $syllabus_id ) ) {
			return Items::get( $syllabus_id ) ? self::not_a_syllabus() : Items::missing(); }
		$fields = self::clean_group( $data, false );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		$item_id = (int) ( $data['item_id'] ?? 0 ) ?: (int) $syllabus_id;
		if ( ! in_array( $item_id, Items::with_descendants( $syllabus_id ), true ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'A skill group must sit under the syllabus or one of its content items.', 'ohmylms' ) ); }
		$position = isset( $data['position'] ) && '' !== $data['position'] && null !== $data['position'] ? (int) $data['position'] : null;
		return Items::exclusive(
			static function () use ( $syllabus_id, $item_id, $fields, $position ) {
				if ( self::group_count( Items::with_descendants( $syllabus_id ) ) >= self::MAX_GROUPS ) {
					return Access::error( 'ohmylms_syllabus_limit', sprintf( /* translators: %d: number. */
					__( 'A syllabus can have at most %d skill groups.', 'ohmylms' ), self::MAX_GROUPS ), 409 ); }
				return self::insert_group( $item_id, $fields, $position );
			}
		);
	}

	/**
	 * Update group for Syllabus.
	 *
	 * @return true|\WP_Error
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 * @param array $data Input data.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function update_group( $syllabus_id, $group_id, array $data ) {
		global $wpdb;
		if ( ! self::owned_group( $syllabus_id, $group_id ) ) {
			return self::missing_group(); }
		$fields = self::clean_group( $data, true );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		if ( ! $fields ) {
			return true; }
		return Items::exclusive(
			static function () use ( $wpdb, $group_id, $fields ) {
				if ( $wpdb->update( self::groups_table(), $fields + array( 'updated_at' => self::now() ), array( 'id' => (int) $group_id ) ) === false ) {
					throw new \RuntimeException( 'Group update failed' ); }
				return true;
			}
		);
	}

	/** Move a group under another content item of the same syllabus and/or to another position.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 * @param mixed $item_id Item identifier.
	 * @param mixed $position Position.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function move_group( $syllabus_id, $group_id, $item_id, $position = null ) {
		global $wpdb;
		$group = self::owned_group( $syllabus_id, $group_id );
		if ( ! $group ) {
			return self::missing_group(); }
		$item_id = (int) $item_id ?: (int) $group['item_id'];
		if ( ! in_array( $item_id, Items::with_descendants( $syllabus_id ), true ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'A skill group must sit under the syllabus or one of its content items.', 'ohmylms' ) ); }
		return Items::exclusive(
			static function () use ( $wpdb, $group, $item_id, $position ) {
				$old = (int) $group['item_id'];
				if ( $old !== $item_id && $wpdb->update(
					self::groups_table(),
					array(
						'item_id'    => $item_id,
						'updated_at' => self::now(),
					),
					array( 'id' => (int) $group['id'] )
				) === false ) {
					throw new \RuntimeException( 'Group move failed' ); }
				if ( $old !== $item_id ) {
					self::renumber_groups( self::group_ids_in( $old ) ); }
				self::renumber_groups( Tree::insert_at( array_values( array_diff( self::group_ids_in( $item_id ), array( (int) $group['id'] ) ) ), (int) $group['id'], null === $position || '' === $position ? null : (int) $position ) );
				return true;
			}
		);
	}

	/**
	 * Delete a group. Its skills stay in the skill library; they just stop being placed in a group.
	 * A group that holds skills needs $confirm.
	 *
	 * @return array|\WP_Error ['skills_detached' => n]
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 * @param bool  $confirm Confirm.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function delete_group( $syllabus_id, $group_id, $confirm = false ) {
		global $wpdb;
		$group = self::owned_group( $syllabus_id, $group_id );
		if ( ! $group ) {
			return self::missing_group(); }
		$skills = count( self::term_ids_in( $group_id ) );
		if ( $skills && ! $confirm ) {
			return Access::error( 'ohmylms_syllabus_needs_confirmation', sprintf( /* translators: %d: number. */
			_n( 'This group holds %d skill. Deleting the group keeps the skill in the skill library but removes it from the syllabus outline.', 'This group holds %d skills. Deleting the group keeps the skills in the skill library but removes them from the syllabus outline.', $skills, 'ohmylms' ), $skills ), 409, array( 'skills' => $skills ) );
		}
		return Items::exclusive(
			static function () use ( $wpdb, $group, $skills ) {
				$wpdb->delete( self::placements_table(), array( 'group_id' => (int) $group['id'] ) );
				if ( $wpdb->delete( self::groups_table(), array( 'id' => (int) $group['id'] ) ) === false ) {
					throw new \RuntimeException( 'Group delete failed' ); }
				self::renumber_groups( self::group_ids_in( (int) $group['item_id'] ) );
				return array( 'skills_detached' => $skills );
			}
		);
	}

	// ---- Skills ------------------------------------------------------------------------------------

	/**
	 * A short slug of our own for a new skill. WordPress would derive one from the name, and for a long
	 * name in Cyrillic or another script that slug is URL-encoded and cut at 200 characters; two names
	 * that begin the same then get the same slug, and the "-2" WordPress adds to tell them apart no
	 * longer fits the column, so the insert fails. Skills are never public, so the slug is never shown.
	 *
	 * With a slug given, WordPress also accepts equal names under one parent. That is wanted here: two
	 * skills may share their text (the same objective in a Core and an Extended section) and are told
	 * apart by their codes, and every name stays exactly as the administrator wrote it.
	 */
	private static function slug() {
		return 'skill-' . strtolower( wp_generate_password( 12, false, false ) );
	}

	/** The library skill that parents every skill created for this syllabus. Created on first use.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function root_skill( $syllabus_id ) {
		global $wpdb;
		$item = Items::get( $syllabus_id );
		if ( ! $item ) {
			return Items::missing(); }
		$root = (int) $item['skill_root_id'];
		if ( $root && term_exists( $root, Taxonomy::NAME ) ) {
			return $root; }
		$name = trim( self::text( $item['name'] ) );
		$term = \OhMyLMS\Skills\Provenance::with(
			array(
				'source' => 'syllabus',
				'ref'    => 'syllabus:' . (int) $item['id'],
			),
			static function () use ( $name ) {
				return wp_insert_term(
					wp_slash( mb_substr( $name, 0, 199 ) ),
					Taxonomy::NAME,
					array(
						'description' => wp_slash( sprintf( /* translators: %s: text value. */
						__( 'Skills of the syllabus “%s”.', 'ohmylms' ), $name ) ),
						'parent'      => 0,
						'slug'        => self::slug(),
					)
				);
			}
		);
		if ( is_wp_error( $term ) ) {
			return $term; }
		$id = (int) $term['term_id'];
		Taxonomy::uuid( $id );
		update_term_meta( $id, self::ROOT_META, (int) $item['id'] );
		if ( $wpdb->update( Items::table(), array( 'skill_root_id' => $id ), array( 'id' => (int) $item['id'] ) ) === false ) {
			throw new \RuntimeException( 'Root skill write failed' ); }
		return $id;
	}

	/**
	 * Clean skill for Syllabus.
	 *
	 * @param array $data Input data.
	 * @param mixed $partial Partial.
	 */
	private static function clean_skill( array $data, $partial ) {
		$fields = array();
		if ( array_key_exists( 'objective', $data ) ) {
			$objective = \OhMyLMS\Skills\Objectives::normalize( $data['objective'] );
			if ( is_wp_error( $objective ) ) {
				return $objective;
			}
			$fields['objective'] = $objective;
		}
		if ( array_key_exists( 'category', $data ) ) {
			$category = SyllabusRows::line( $data['category'] );
			if ( mb_strlen( $category ) > 60 ) {
				return Access::error( 'ohmylms_syllabus_invalid', __( 'A skill category can have at most 60 characters.', 'ohmylms' ) ); }
			$fields['category'] = sanitize_text_field( $category );
		}
		if ( ! $partial || array_key_exists( 'name', $data ) ) {
			$name = SyllabusRows::line( $data['name'] ?? '' );
			if ( '' === $name ) {
				return Access::error( 'ohmylms_syllabus_invalid', __( 'A skill needs a name.', 'ohmylms' ) ); }
			if ( mb_strlen( $name ) > SyllabusRows::NAME_LIMIT ) {
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( /* translators: %d: number. */
				__( 'A skill name can have at most %d characters.', 'ohmylms' ), SyllabusRows::NAME_LIMIT ) ); }
			$fields['name'] = $name;
		}
		if ( ! $partial || array_key_exists( 'code', $data ) ) {
			$code = SyllabusRows::line( $data['code'] ?? '' );
			if ( mb_strlen( $code ) > SyllabusRows::SKILL_CODE_LIMIT ) {
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( /* translators: %d: number. */
				__( 'A skill code can have at most %d characters.', 'ohmylms' ), SyllabusRows::SKILL_CODE_LIMIT ) ); }
			$fields['code'] = $code;
		}
		if ( ! $partial || array_key_exists( 'description', $data ) ) {
			$description = SyllabusRows::notes( $data['description'] ?? '' );
			if ( mb_strlen( $description ) > SyllabusRows::DESCRIPTION_LIMIT ) {
				return Access::error( 'ohmylms_syllabus_invalid', sprintf( /* translators: %d: number. */
				__( 'Notes can have at most %d characters.', 'ohmylms' ), SyllabusRows::DESCRIPTION_LIMIT ) ); }
			$fields['description'] = $description;
		}
		if ( array_key_exists( 'translations', $data ) ) {
			$translations = \OhMyLMS\Skills\Translations::normalize( $data['translations'] );
			if ( is_wp_error( $translations ) ) {
				return $translations;
			}
			$fields['translations'] = $translations;
		}
		return $fields;
	}

	/** Another skill of this syllabus that already uses the code (skill codes are unique per syllabus).
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $code Code.
	 * @param int   $except_term Except term.
	 */
	private static function code_taken( $syllabus_id, $code, $except_term = 0 ) {
		if ( '' === $code ) {
			return false; }
		foreach ( self::skill_ids( Items::with_descendants( $syllabus_id ) ) as $term_id ) {
			if ( $term_id !== (int) $except_term && SyllabusPlan::key( (string) get_term_meta( $term_id, self::CODE_META, true ) ) === SyllabusPlan::key( $code ) ) {
				return true; }
		}
		return false;
	}

	/**
	 * Create a library skill under the syllabus's root skill. Equal names are allowed (see slug()).
	 *
	 * @return array|\WP_Error ['term_id' => int, 'name' => string]
	 * @param mixed  $root Root.
	 * @param mixed  $name Name.
	 * @param mixed  $code Code.
	 * @param mixed  $description Description.
	 * @param string $category Category.
	 */
	private static function create_term( $root, $name, $code, $description, $category = '' ) {
		$term = \OhMyLMS\Skills\Provenance::with(
			array(
				'source' => 'syllabus',
				'ref'    => 'root:' . (int) $root,
			),
			static function () use ( $root, $name, $description ) {
				return wp_insert_term(
					wp_slash( mb_substr( $name, 0, 199 ) ),
					Taxonomy::NAME,
					array(
						'description' => wp_slash( sanitize_textarea_field( $description ) ),
						'parent'      => (int) $root,
						'slug'        => self::slug(),
					)
				);
			}
		);
		if ( is_wp_error( $term ) ) {
			return $term; }
		$id = (int) $term['term_id'];
		Taxonomy::uuid( $id );
		if ( '' !== $code ) {
			update_term_meta( $id, self::CODE_META, mb_substr( $code, 0, SyllabusRows::SKILL_CODE_LIMIT ) ); }
		if ( '' !== $category ) {
			update_term_meta( $id, self::CATEGORY_META, sanitize_text_field( $category ) ); }
		return array(
			'term_id' => $id,
			'name'    => $name,
		);
	}

	/**
	 * Place skill for Syllabus.
	 *
	 * @param mixed $group_id Group identifier.
	 * @param mixed $term_id Term identifier.
	 * @param mixed $position Position.
	 */
	private static function place_skill( $group_id, $term_id, $position = null ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO ' . self::placements_table() . ' (group_id, term_id, position) VALUES (%d, %d, 0)', (int) $group_id, (int) $term_id ) );
		self::renumber_skills( $group_id, Tree::insert_at( array_values( array_diff( self::term_ids_in( $group_id ), array( (int) $term_id ) ) ), (int) $term_id, $position ) );
	}

	/**
	 * Create a new skill in a group.
	 *
	 * @return array|\WP_Error ['term_id' => int, 'name' => string]
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 * @param array $data Input data.
	 */
	public static function create_skill( $syllabus_id, $group_id, array $data ) {
		$group = self::owned_group( $syllabus_id, $group_id );
		if ( ! $group ) {
			return self::missing_group(); }
		$fields = self::clean_skill( $data, false );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		$position = isset( $data['position'] ) && '' !== $data['position'] && null !== $data['position'] ? (int) $data['position'] : null;
		return Items::exclusive(
			static function () use ( $syllabus_id, $group, $fields, $position ) {
				$category_check = SyllabusSettings::check_category( $syllabus_id, $fields );
				if ( is_wp_error( $category_check ) ) {
					return $category_check; }
				if ( self::skill_count( $syllabus_id ) >= self::MAX_SKILLS ) {
					return Access::error( 'ohmylms_syllabus_limit', sprintf( /* translators: %d: number. */
					__( 'A syllabus can have at most %d skills.', 'ohmylms' ), self::MAX_SKILLS ), 409 ); }
				if ( self::code_taken( $syllabus_id, $fields['code'] ) ) {
					return Access::error( 'ohmylms_syllabus_code_taken', sprintf( /* translators: %s: text value. */
					__( 'Another skill in this syllabus already uses the code “%s”.', 'ohmylms' ), $fields['code'] ), 409 ); }
				$root = self::root_skill( $syllabus_id );
				if ( is_wp_error( $root ) ) {
					return $root; }
				$created = self::create_term( $root, $fields['name'], $fields['code'], $fields['description'], $fields['category'] ?? '' );
				if ( is_wp_error( $created ) ) {
					return $created; }
				$item = Items::get( $syllabus_id );
				if ( ! is_wp_error( $item ) && 'roadmap' === ( $item['item_type'] ?? '' ) ) {
					$settings = SyllabusSettings::get( $syllabus_id );
					update_term_meta( $created['term_id'], Comparison::SUBJECT_META, $settings['subject'] ?? '' );
					update_term_meta( $created['term_id'], Comparison::ROADMAP_META, (int) $syllabus_id );
				}
				if ( isset( $fields['translations'] ) ) {
					$saved = \OhMyLMS\Skills\Translations::save( $created['term_id'], $fields['translations'] );
					if ( is_wp_error( $saved ) ) {
						return $saved;
					}
				}
				if ( isset( $fields['objective'] ) ) {
					$saved = \OhMyLMS\Skills\Objectives::save( $created['term_id'], $fields['objective'] );
					if ( is_wp_error( $saved ) ) {
						return $saved;
					}
				}
				self::place_skill( (int) $group['id'], $created['term_id'], $position );
				return $created;
			}
		);
	}

	/** Place an existing library skill in a group (it keeps its own questions, lessons and evidence).
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 * @param mixed $term_id Term identifier.
	 */
	public static function add_skill( $syllabus_id, $group_id, $term_id ) {
		$group = self::owned_group( $syllabus_id, $group_id );
		if ( ! $group ) {
			return self::missing_group(); }
		$term_id = (int) $term_id;
		if ( $term_id <= 0 || ! term_exists( $term_id, Taxonomy::NAME ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'That skill does not exist.', 'ohmylms' ), 404 ); }
		return Items::exclusive(
			static function () use ( $syllabus_id, $group, $term_id ) {
				if ( in_array( $term_id, self::skill_ids( Items::with_descendants( $syllabus_id ) ), true ) ) {
					return Access::error( 'ohmylms_syllabus_skill_placed', __( 'That skill is already in a skill group of this syllabus.', 'ohmylms' ), 409 ); }
				if ( self::skill_count( $syllabus_id ) >= self::MAX_SKILLS ) {
					return Access::error( 'ohmylms_syllabus_limit', sprintf( /* translators: %d: number. */
					__( 'A syllabus can have at most %d skills.', 'ohmylms' ), self::MAX_SKILLS ), 409 ); }
				self::place_skill( (int) $group['id'], $term_id );
				return true;
			}
		);
	}

	/** Change a placed skill's name, code or notes. @return true|\WP_Error
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $term_id Term identifier.
	 * @param array $data Input data.
	 */
	public static function update_skill( $syllabus_id, $term_id, array $data ) {
		$term_id = (int) $term_id;
		if ( ! in_array( $term_id, self::skill_ids( Items::with_descendants( $syllabus_id ) ), true ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'That skill is not in this syllabus.', 'ohmylms' ), 404 ); }
		$fields = self::clean_skill( $data, true );
		if ( is_wp_error( $fields ) ) {
			return $fields; }
		return Items::exclusive(
			static function () use ( $syllabus_id, $term_id, $fields ) {
				$category_check = SyllabusSettings::check_category( $syllabus_id, $fields );
				if ( is_wp_error( $category_check ) ) {
					return $category_check; }
				return self::write_skill( $syllabus_id, $term_id, $fields );
			}
		);
	}

	/** Apply changed fields to a skill. Codes stay unique in the syllabus; names may repeat (see slug()).
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $term_id Term identifier.
	 * @param array $fields Fields.
	 */
	private static function write_skill( $syllabus_id, $term_id, array $fields ) {
		if ( isset( $fields['code'] ) && self::code_taken( $syllabus_id, $fields['code'], $term_id ) ) {
			return Access::error( 'ohmylms_syllabus_code_taken', sprintf( /* translators: %s: text value. */
			__( 'Another skill in this syllabus already uses the code “%s”.', 'ohmylms' ), $fields['code'] ), 409 );
		}
		$args = array();
		if ( isset( $fields['name'] ) ) {
			$args['name'] = wp_slash( mb_substr( $fields['name'], 0, 199 ) ); }
		if ( isset( $fields['description'] ) ) {
			$args['description'] = wp_slash( sanitize_textarea_field( $fields['description'] ) ); }
		if ( $args ) {
			$result = wp_update_term( $term_id, Taxonomy::NAME, $args );
			if ( is_wp_error( $result ) ) {
				return $result; }
		}
		if ( isset( $fields['code'] ) ) {
			update_term_meta( $term_id, self::CODE_META, mb_substr( $fields['code'], 0, SyllabusRows::SKILL_CODE_LIMIT ) ); }
		if ( isset( $fields['category'] ) ) {
			update_term_meta( $term_id, self::CATEGORY_META, $fields['category'] ); }
		if ( isset( $fields['objective'] ) ) {
			$saved = \OhMyLMS\Skills\Objectives::save( $term_id, $fields['objective'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		if ( isset( $fields['translations'] ) ) {
			return \OhMyLMS\Skills\Translations::save( $term_id, $fields['translations'] );
		}
		return true;
	}

	/** Take a skill out of a group. The skill stays in the library.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 * @param mixed $term_id Term identifier.
	 */
	public static function remove_skill( $syllabus_id, $group_id, $term_id ) {
		global $wpdb;
		if ( ! self::owned_group( $syllabus_id, $group_id ) ) {
			return self::missing_group(); }
		return Items::exclusive(
			static function () use ( $wpdb, $group_id, $term_id ) {
				$wpdb->delete(
					self::placements_table(),
					array(
						'group_id' => (int) $group_id,
						'term_id'  => (int) $term_id,
					)
				);
				self::renumber_skills( $group_id, self::term_ids_in( $group_id ) );
				return true;
			}
		);
	}

	/** Move a skill to another position, or into another group of the same syllabus.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param mixed $group_id Group identifier.
	 * @param mixed $term_id Term identifier.
	 * @param int   $to_group_id To group identifier.
	 * @param mixed $position Position.
	 */
	public static function move_skill( $syllabus_id, $group_id, $term_id, $to_group_id = 0, $position = null ) {
		global $wpdb;
		if ( ! self::owned_group( $syllabus_id, $group_id ) ) {
			return self::missing_group(); }
		$to = (int) $to_group_id ?: (int) $group_id;
		if ( ! self::owned_group( $syllabus_id, $to ) ) {
			return self::missing_group(); }
		$term_id = (int) $term_id;
		if ( ! in_array( $term_id, self::term_ids_in( $group_id ), true ) ) {
			return Access::error( 'ohmylms_syllabus_invalid', __( 'That skill is not in this skill group.', 'ohmylms' ), 404 ); }
		return Items::exclusive(
			static function () use ( $wpdb, $group_id, $to, $term_id, $position ) {
				self::move_placement( $group_id, $to, $term_id, null === $position || '' === $position ? null : (int) $position );
				return true;
			}
		);
	}

	/**
	 * Move placement for Syllabus.
	 *
	 * @param mixed $from From.
	 * @param mixed $to To.
	 * @param mixed $term_id Term identifier.
	 * @param mixed $position Position.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	private static function move_placement( $from, $to, $term_id, $position ) {
		global $wpdb;
		$from = (int) $from;
		$to   = (int) $to;
		if ( $from !== $to ) {
			if ( $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::placements_table() . ' WHERE group_id=%d AND term_id=%d', $to, (int) $term_id ) ) === false ) {
				throw new \RuntimeException( 'Skill move failed' ); }
			if ( $wpdb->update(
				self::placements_table(),
				array( 'group_id' => $to ),
				array(
					'group_id' => $from,
					'term_id'  => (int) $term_id,
				)
			) === false ) {
				throw new \RuntimeException( 'Skill move failed' ); }
			self::renumber_skills( $from, self::term_ids_in( $from ) );
		}
		self::renumber_skills( $to, Tree::insert_at( array_values( array_diff( self::term_ids_in( $to ), array( (int) $term_id ) ) ), (int) $term_id, $position ) );
	}

	// ---- Import ------------------------------------------------------------------------------------

	/**
	 * Check, and unless $dry_run apply, a CSV import. Nothing is deleted: rows that match existing
	 * contents, groups and skills (by code, else by name) update them; the rest are added. If any
	 * row has a problem nothing changes at all.
	 *
	 * @param mixed   $syllabus_id Syllabus identifier.
	 * @param array[] $rows Rows as the browser mapped them (content, content_code, group, group_code, skill, skill_code, description, line).
	 * @param bool    $dry_run Dry run.
	 * @return array|\WP_Error The report: counts, errors, warnings and whether it was `applied`.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function import( $syllabus_id, array $rows, $dry_run = true ) {
		$outline = self::outline( $syllabus_id );
		if ( is_wp_error( $outline ) ) {
			return $outline; }
		$normalized         = SyllabusRows::normalize( $rows );
		$plan               = SyllabusPlan::build( self::plan_state( $outline ), $normalized['rows'], array( 'default_group' => __( 'Skills', 'ohmylms' ) ) );
		$report             = $plan['report'];
		$report['errors']   = array_merge( $normalized['errors'], $report['errors'] );
		$report['warnings'] = array_merge( $normalized['warnings'], $report['warnings'] );
		$report['valid']    = ! $report['errors'];
		$report['rows']     = array(
			'used'    => count( $normalized['rows'] ),
			'skipped' => $normalized['skipped'],
		);
		$report['applied']  = false;
		if ( $dry_run || ! $report['valid'] ) {
			return $report; }
		if ( ! $plan['ops'] ) {
			$report['applied'] = true;
			return $report; }

		$failure       = '';
		$created_terms = array();
		$result        = Items::exclusive(
			static function () use ( $syllabus_id, $plan, &$failure, &$created_terms ) {
				$run = self::execute( $syllabus_id, $plan['ops'], $created_terms );
				if ( is_wp_error( $run ) ) {
					$failure = $run->get_error_message();
					throw new \RuntimeException( $failure ); }
				return $run;
			}
		);
		if ( is_wp_error( $result ) ) {
			if ( $created_terms ) {
				clean_term_cache( $created_terms, Taxonomy::NAME ); }
			return '' !== $failure ? Access::error( 'ohmylms_syllabus_import_failed', $failure . ' ' . __( 'Nothing was changed.', 'ohmylms' ), 500 ) : $result;
		}
		$report['applied'] = true;
		return $report;
	}

	/** Run planned operations in order inside the caller's lock and transaction.
	 *
	 * @param mixed $syllabus_id Syllabus identifier.
	 * @param array $ops Ops.
	 * @param array $created_terms Created terms.
	 */
	private static function execute( $syllabus_id, array $ops, array &$created_terms ) {
		global $wpdb;
		$map     = array();
		$resolve = static function ( $ref ) use ( &$map ) {
			return is_string( $ref ) ? (int) ( $map[ $ref ] ?? 0 ) : (int) $ref;
		};
		$root    = 0;
		foreach ( $ops as $op ) {
			switch ( $op['op'] ) {
				case 'content':
					$fields = Items::clean(
						array(
							'name'        => $op['name'],
							'item_type'   => 'topic',
							'code'        => $op['code'],
							'description' => '',
						)
					);
					if ( is_wp_error( $fields ) ) {
						return $fields; }
					$id = Items::insert_locked( $fields, $resolve( $op['parent'] ) );
					if ( is_wp_error( $id ) ) {
						return $id; }
					$map[ $op['ref'] ] = $id;
					break;
				case 'content_update':
					$fields = Items::clean( $op['fields'], true );
					if ( is_wp_error( $fields ) ) {
						return $fields; }
					if ( $wpdb->update( Items::table(), $fields + array( 'updated_at' => self::now() ), array( 'id' => (int) $op['id'] ) ) === false ) {
						return Access::error( 'ohmylms_syllabus_import_failed', __( 'A content item could not be updated.', 'ohmylms' ), 500 ); }
					break;
				case 'group':
					$fields = self::clean_group(
						array(
							'name' => $op['name'],
							'code' => $op['code'],
						),
						false
					);
					if ( is_wp_error( $fields ) ) {
						return $fields; }
					$map[ $op['ref'] ] = self::insert_group( $resolve( $op['item'] ), $fields );
					break;
				case 'group_update':
					$fields = self::clean_group( $op['fields'], true );
					if ( is_wp_error( $fields ) ) {
						return $fields; }
					if ( $fields && $wpdb->update( self::groups_table(), $fields + array( 'updated_at' => self::now() ), array( 'id' => (int) $op['id'] ) ) === false ) {
						return Access::error( 'ohmylms_syllabus_import_failed', __( 'A skill group could not be updated.', 'ohmylms' ), 500 ); }
					break;
				case 'skill':
					if ( ! $root ) {
						$root = self::root_skill( $syllabus_id );
						if ( is_wp_error( $root ) ) {
							return $root; }
					}
					$created = self::create_term( $root, $op['name'], $op['code'], $op['description'], $op['category'] ?? '' );
					if ( is_wp_error( $created ) ) {
						return Access::error( $created->get_error_code(), sprintf( /* translators: %1$s: text value; %2$s: text value. */
						__( 'The skill “%1$s” could not be created: %2$s', 'ohmylms' ), $op['name'], $created->get_error_message() ), 500 ); }
					$created_terms[] = $created['term_id'];
					self::place_skill( $resolve( $op['group'] ), $created['term_id'] );
					$map[ $op['ref'] ] = $created['term_id'];
					break;
				case 'skill_update':
					$fields = self::clean_skill( $op['fields'], true );
					if ( is_wp_error( $fields ) ) {
						return $fields; }
					$written = self::write_skill( $syllabus_id, (int) $op['id'], $fields );
					if ( is_wp_error( $written ) ) {
						return $written; }
					break;
				case 'skill_move':
					$from = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT p.group_id FROM ' . self::placements_table() . ' p JOIN ' . self::groups_table() . ' g ON g.id=p.group_id WHERE p.term_id=%d AND g.item_id IN (' . self::placeholders( Items::with_descendants( $syllabus_id ) ) . ') LIMIT 1', array_merge( array( (int) $op['term'] ), Items::with_descendants( $syllabus_id ) ) ) );
					if ( $from ) {
						self::move_placement( $from, $resolve( $op['group'] ), (int) $op['term'], null ); }
					break;
			}
		}
		return true;
	}

	// ---- Cleanup -----------------------------------------------------------------------------------

	/** Remove the skill groups under deleted items, and the placements in them. Skills stay in the library.
	 *
	 * @param array $item_ids Item ids.
	 */
	public static function delete_for_items( array $item_ids ) {
		global $wpdb;
		$item_ids = self::ints( $item_ids );
		if ( ! $item_ids ) {
			return 0; }
		$group_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . self::groups_table() . ' WHERE item_id IN (' . self::placeholders( $item_ids ) . ')', $item_ids ) ) );
		if ( ! $group_ids ) {
			return 0; }
		$placeholders = self::placeholders( $group_ids );
		if ( $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::placements_table() . " WHERE group_id IN ($placeholders)", $group_ids ) ) === false ) {
			return false; }
		if ( $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::groups_table() . " WHERE id IN ($placeholders)", $group_ids ) ) === false ) {
			return false; }
		return count( $group_ids );
	}

	/** A deleted library skill leaves no placements, and a deleted root skill is forgotten so it is made again.
	 *
	 * @param mixed $term_id Term identifier.
	 */
	public static function remove_term( $term_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return; }
		$wpdb->delete( self::placements_table(), array( 'term_id' => (int) $term_id ) );
		$wpdb->update( Items::table(), array( 'skill_root_id' => 0 ), array( 'skill_root_id' => (int) $term_id ) );
	}
}
