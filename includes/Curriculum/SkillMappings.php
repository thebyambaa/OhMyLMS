<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Explicit links from a curriculum-specific skill to a shared skill.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Explicit links from a curriculum-specific skill to a shared skill.
 *
 * Skills are never merged by name. Each curriculum keeps its own skill (with its own
 * requirements, difficulty and syllabus version) and its own evidence; a mapping only lets a
 * combined view show them together. Rules:
 *  - directional: a specific skill points at a shared skill;
 *  - no chains: a skill cannot be both a mapped (specific) skill and a shared target, so
 *    evidence is never pooled through two hops;
 *  - `equivalent` mappings pool evidence in combined views; `related` mappings are shown for
 *    reference only and add no evidence;
 *  - the note records how the requirements, difficulty or syllabus versions differ.
 */
final class SkillMappings {
	const RELATIONS = array( 'equivalent', 'broader', 'narrower', 'related', 'combined' );
	const LOCK      = 'ohmylms-skill-mappings';

	/**
	 * Table for Skill mappings.
	 */
	public static function table() {
		return Schema::table( 'skill_mappings' ); }

	/**
	 * Read only the identity fields that a mapping exposes.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array|null
	 */
	private static function term_summary( $term_id ) {
		$term = get_term( (int) $term_id, Taxonomy::NAME );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		return array(
			'id'   => (int) $term->term_id,
			'name' => Taxonomy::plain( $term->name ),
			'code' => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
		);
	}

	/**
	 * Describe for Skill mappings.
	 *
	 * @param array $row Row.
	 */
	private static function describe( array $row ) {
		return array(
			'id'          => (int) $row['id'],
			'specific_id' => (int) $row['specific_term_id'],
			'shared_id'   => (int) $row['shared_term_id'],
			'specific'    => self::term_summary( $row['specific_term_id'] ),
			'shared'      => self::term_summary( $row['shared_term_id'] ),
			'relation'    => $row['relation'],
			'group_uuid'  => $row['group_uuid'] ?? '',
			'note'        => (string) $row['note'],
			'updated_at'  => $row['updated_at'],
			'revision'    => self::revision( $row ),
		);
	}

	/** Mappings in which the skill is the specific side (pointing at shared skills) or the shared target.
	 *
	 * @param mixed $term_id Term identifier.
	 */
	public static function for_skill( $term_id ) {
		global $wpdb;
		$term_id = (int) $term_id;
		$up      = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE specific_term_id=%d ORDER BY id', $term_id ), ARRAY_A );
		$down    = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE shared_term_id=%d ORDER BY id', $term_id ), ARRAY_A );
		$rows    = array_merge( $up, $down );
		if ( $rows ) {
			get_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'include'    => array_unique( array_merge( array_column( $rows, 'specific_term_id' ), array_column( $rows, 'shared_term_id' ) ) ),
					'hide_empty' => false,
					'number'     => 0,
				)
			);
		}
		return array(
			'maps_to'     => array_map( array( __CLASS__, 'describe' ), $up ),
			'mapped_from' => array_map( array( __CLASS__, 'describe' ), $down ),
		);
	}

	/**
	 * Describe both directions for a set of placed skills in one mapping read.
	 *
	 * @param array $term_ids Placed skill IDs.
	 * @return array Mapping descriptions indexed by placed skill ID.
	 */
	public static function connections_for_skills( array $term_ids ) {
		$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
		if ( ! $term_ids ) {
			return array();
		}
		$rows = self::all( $term_ids );
		if ( $rows ) {
			get_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'include'    => array_unique( array_merge( array_column( $rows, 'specific_term_id' ), array_column( $rows, 'shared_term_id' ) ) ),
					'hide_empty' => false,
					'number'     => 0,
				)
			);
		}
		$connections = array_fill_keys( $term_ids, array() );
		foreach ( $rows as $row ) {
			$mapping = self::describe( $row );
			foreach ( array( $mapping['specific_id'], $mapping['shared_id'] ) as $term_id ) {
				if ( isset( $connections[ $term_id ] ) ) {
					$connections[ $term_id ][] = $mapping;
				}
			}
		}
		return $connections;
	}

	/** Every mapping, optionally limited to a set of skill IDs on either side.
	 *
	 * @param array $term_ids Term ids.
	 */
	public static function all( array $term_ids = array() ) {
		global $wpdb;
		$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
		if ( $term_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
			$rows         = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . " WHERE specific_term_id IN ($placeholders) OR shared_term_id IN ($placeholders) ORDER BY id", array_merge( $term_ids, $term_ids ) ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id', ARRAY_A );
		}
		return $rows ? $rows : array();
	}

	/**
	 * Create or update one mapping under the shared mapping lock.
	 *
	 * @param int         $specific_id Source skill ID.
	 * @param int         $shared_id Shared target ID.
	 * @param string      $relation Equivalent or related.
	 * @param string      $note Scope and version differences.
	 * @param string|null $expected_revision Current pair revision; null preserves legacy callers.
	 * @param string      $group_uuid Internal combined group identity.
	 * @param bool        $allow_group_edit Internal atomic group edit.
	 * @return array|\WP_Error
	 */
	public static function save( $specific_id, $shared_id, $relation = 'equivalent', $note = '', $expected_revision = null, $group_uuid = '', $allow_group_edit = false ) {
		global $wpdb;
		$specific_id = (int) $specific_id;
		$shared_id   = (int) $shared_id;
		$relation    = sanitize_key( (string) $relation );
		$note        = sanitize_textarea_field( (string) $note );
		if ( ! in_array( $relation, self::RELATIONS, true ) ) {
			return Access::error( 'ohmylms_mapping_invalid', __( 'Choose a supported mapping relationship.', 'ohmylms' ) ); }
		if ( 'combined' === $relation && ! $group_uuid ) {
			return Access::error( 'ohmylms_mapping_invalid', __( 'Save combined coverage as a group of at least two objectives from one syllabus.', 'ohmylms' ) );
		}
		if ( mb_strlen( $note ) > 1000 ) {
			return Access::error( 'ohmylms_mapping_invalid', __( 'Notes can have at most 1000 characters.', 'ohmylms' ) ); }
		if ( $specific_id === $shared_id ) {
			return Access::error( 'ohmylms_mapping_invalid', __( 'A skill cannot be mapped to itself.', 'ohmylms' ) ); }
		if ( metadata_exists( 'term', $specific_id, Comparison::SUBJECT_META ) ) {
			return Access::error( 'ohmylms_mapping_chain', __( 'A reusable shared skill cannot point to another shared skill. Map a source syllabus objective instead.', 'ohmylms' ), 409 );
		}
		foreach ( array( $specific_id, $shared_id ) as $term_id ) {
			if ( $term_id <= 0 || ! term_exists( $term_id, Taxonomy::NAME ) ) {
				return Access::error( 'ohmylms_mapping_invalid', __( 'Both skills must exist.', 'ohmylms' ), 404 ); }
		}
		$lock = self::LOCK . '-' . md5( $wpdb->prefix );
		if ( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) !== '1' ) {
			return Access::error( 'ohmylms_mapping_busy', __( 'Another mapping change is being saved. Try again.', 'ohmylms' ), 409 ); }
		try {
			$table   = self::table();
			$current = $wpdb->get_row( $wpdb->prepare( 'SELECT relation, note, group_uuid FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', self::table(), $specific_id, $shared_id ), ARRAY_A );
			if ( ! empty( $current['group_uuid'] ) && ! $allow_group_edit ) {
				return Access::error( 'ohmylms_mapping_group_invalid', __( 'Edit combined coverage as a complete group.', 'ohmylms' ), 409 );
			}
			if ( null !== $expected_revision ) {
				$revision = $current ? self::revision( $current ) : '';
				if ( $expected_revision !== $revision ) {
					return Access::error( 'ohmylms_mapping_stale', __( 'This mapping changed. Reload before saving.', 'ohmylms' ), 409 );
				}
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE shared_term_id=%d LIMIT 1', $table, $specific_id ) ) ) {
				return Access::error( 'ohmylms_mapping_chain', __( 'This skill is already the shared skill for other skills. A skill cannot be both, so evidence is never combined through two steps.', 'ohmylms' ), 409 );
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE specific_term_id=%d LIMIT 1', $table, $shared_id ) ) ) {
				return Access::error( 'ohmylms_mapping_chain', __( 'The chosen shared skill is itself mapped to another skill. Choose that skill instead.', 'ohmylms' ), 409 );
			}
			$now      = current_time( 'mysql', true );
			$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', $table, $specific_id, $shared_id ) );
			if ( $existing ) {
				$ok = $wpdb->update(
					$table,
					array(
						'relation'   => $relation,
						'group_uuid' => $group_uuid,
						'note'       => $note,
						'updated_at' => $now,
					),
					array( 'id' => $existing )
				);
			} else {
				$ok       = $wpdb->insert(
					$table,
					array(
						'specific_term_id' => $specific_id,
						'shared_term_id'   => $shared_id,
						'relation'         => $relation,
						'group_uuid'       => $group_uuid,
						'note'             => $note,
						'created_by'       => get_current_user_id(),
						'created_at'       => $now,
						'updated_at'       => $now,
					)
				);
				$existing = (int) $wpdb->insert_id;
			}
			if ( false === $ok ) {
				return Access::error( 'ohmylms_mapping_failed', __( 'The mapping could not be saved.', 'ohmylms' ), 500 ); }
			return self::describe( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $table, $existing ), ARRAY_A ) );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Remove for Skill mappings.
	 *
	 * @param mixed $specific_id Specific identifier.
	 * @param mixed $shared_id Shared identifier.
	 */
	public static function remove( $specific_id, $shared_id ) {
		global $wpdb;
		$group_uuid = $wpdb->get_var( $wpdb->prepare( 'SELECT group_uuid FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', self::table(), $specific_id, $shared_id ) );
		if ( $group_uuid ) {
			return false;
		}
		$result = $wpdb->delete(
			self::table(),
			array(
				'specific_term_id' => (int) $specific_id,
				'shared_term_id'   => (int) $shared_id,
			)
		);
		return false !== $result;
	}

	/** Drop mappings of a deleted skill.
	 *
	 * @param mixed $term_id Term identifier.
	 */
	public static function remove_skill( $term_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return; }
		$wpdb->delete( self::table(), array( 'specific_term_id' => (int) $term_id ) );
		$wpdb->delete( self::table(), array( 'shared_term_id' => (int) $term_id ) );
		$wpdb->delete( Schema::table( 'skill_mapping_groups' ), array( 'shared_term_id' => (int) $term_id ) );
	}

	/**
	 * Pair revision, preserving revisions for existing ungrouped mappings.
	 *
	 * @param array $row Mapping fields.
	 * @return string
	 */
	public static function revision( array $row ) {
		$value = array( $row['relation'], (string) $row['note'] );
		if ( ! empty( $row['group_uuid'] ) ) {
			$value[] = $row['group_uuid'];
		}
		return Comparison::revision( $value );
	}

	/**
	 * Read a combined requirement and its source objectives.
	 *
	 * @param string $uuid Group UUID.
	 * @return array|\WP_Error
	 */
	public static function group( $uuid ) {
		global $wpdb;
		$group = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE uuid=%s', Schema::table( 'skill_mapping_groups' ), $uuid ), ARRAY_A );
		if ( ! $group ) {
			return Access::error( 'ohmylms_mapping_group_missing', __( 'Combined coverage group not found.', 'ohmylms' ), 404 );
		}
		$ids  = json_decode( $group['member_ids'], true );
		$rows = Comparison::objectives( (int) $group['source_id'] );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$rows = array_values(
			array_filter(
				Comparison::attach_mappings( $rows ),
				static function ( $row ) use ( $ids ) {
					return in_array( $row['term_id'], $ids, true );
				}
			)
		);
		return array(
			'uuid'         => $uuid,
			'source_id'    => (int) $group['source_id'],
			'shared_id'    => (int) $group['shared_term_id'],
			'required_ids' => $ids,
			'items'        => $rows,
		);
	}

	/**
	 * Atomically save explicit combined coverage, grouped by source syllabus.
	 * Combined links are descriptive and never pool mastery evidence.
	 *
	 * @param array  $groups Source IDs and revision-bearing mapping pairs.
	 * @param int    $shared_id Shared target.
	 * @param string $note Scope differences.
	 * @param string $relation New relationship for the complete group.
	 * @return array|\WP_Error
	 */
	public static function save_groups( array $groups, $shared_id, $note, $relation = 'combined' ) {
		global $wpdb;
		if ( ! in_array( $relation, self::RELATIONS, true ) || ! $groups || count( $groups ) > 10 || ! Comparison::shared_skill( $shared_id ) ) {
			return Access::error( 'ohmylms_mapping_group_invalid', __( 'Choose a shared skill and up to ten source groups.', 'ohmylms' ) );
		}
		$seen = array();
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) || ! is_int( $group['source_id'] ?? null ) || ! is_array( $group['pairs'] ?? null ) || count( $group['pairs'] ) < 2 || count( $group['pairs'] ) > 200 ) {
				return Access::error( 'ohmylms_mapping_group_invalid', __( 'Combined coverage requires two or more objectives from each selected syllabus.', 'ohmylms' ) );
			}
			$rows = Comparison::objectives( $group['source_id'] );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			$ids = array_column( $rows, 'term_id' );
			foreach ( $group['pairs'] as $pair ) {
				if ( ! is_array( $pair ) || ! is_int( $pair['specific_id'] ?? null ) || ! is_string( $pair['expected_revision'] ?? null ) || ! in_array( $pair['specific_id'], $ids, true ) || in_array( $pair['specific_id'], $seen, true ) ) {
					return Access::error( 'ohmylms_mapping_group_invalid', __( 'Choose distinct objectives belonging to their source syllabus, with current revisions.', 'ohmylms' ) );
				}
				$seen[] = $pair['specific_id'];
				if ( count( $seen ) > 200 ) {
					return Access::error( 'ohmylms_mapping_group_invalid', __( 'Save at most 200 objectives in one combined batch.', 'ohmylms' ) );
				}
			}
		}
		$lock = self::LOCK . '-' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_mapping_busy', __( 'Another mapping change is being saved. Try again.', 'ohmylms' ), 409 );
		}
		$failure = null;
		try {
			foreach ( $seen as $specific_id ) {
				$current_uuid = $wpdb->get_var( $wpdb->prepare( 'SELECT group_uuid FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', self::table(), $specific_id, $shared_id ) );
				if ( $current_uuid ) {
					$members = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT specific_term_id FROM %i WHERE group_uuid=%s', self::table(), $current_uuid ) ) );
					if ( array_diff( $members, $seen ) ) {
						return Access::error( 'ohmylms_mapping_group_invalid', __( 'Include every member of the existing combined group before changing it.', 'ohmylms' ), 409 );
					}
				}
			}
			return Transaction::run(
				static function () use ( $wpdb, $groups, $shared_id, $note, $relation, &$failure ) {
					$result = array();
					foreach ( $groups as $group ) {
						$uuid = 'combined' === $relation ? wp_generate_uuid4() : '';
						if ( $uuid && false === $wpdb->insert(
							Schema::table( 'skill_mapping_groups' ),
							array(
								'uuid'           => $uuid,
								'source_id'      => $group['source_id'],
								'shared_term_id' => $shared_id,
								'member_ids'     => wp_json_encode( array_column( $group['pairs'], 'specific_id' ) ),
								'created_by'     => get_current_user_id(),
								'created_at'     => current_time( 'mysql', true ),
							)
						) ) {
							throw new \RuntimeException( 'Combined coverage could not be saved.' );
						}
						foreach ( $group['pairs'] as $pair ) {
							$saved = self::save( $pair['specific_id'], $shared_id, $relation, $note, $pair['expected_revision'], $uuid, true );
							if ( is_wp_error( $saved ) ) {
								$failure = $saved;
								throw new \RuntimeException( 'Combined mapping rejected.' );
							}
							$result[] = $saved;
						}
					}
					return $result;
				}
			);
		} catch ( \Throwable $error ) {
			if ( $failure ) {
				return $failure;
			}
			return Access::error( 'ohmylms_mapping_failed', __( 'Combined coverage could not be saved. Nothing was changed.', 'ohmylms' ), 500 );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
}
