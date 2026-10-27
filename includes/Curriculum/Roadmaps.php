<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * General roadmaps assembled from stable shared skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Uses the existing ordered syllabus workspace without duplicating skills. */
final class Roadmaps {
	const SOURCES_META = '_ohmylms_roadmap_sources';

	/**
	 * Resolve saved mappings in source order, retaining each shared identity once.
	 *
	 * @param mixed $syllabus_ids Selected source IDs.
	 * @return array|\WP_Error
	 */
	public static function preview( $syllabus_ids ) {
		if ( ! is_array( $syllabus_ids ) || ! $syllabus_ids || count( $syllabus_ids ) > 10 ) {
			return Access::error( 'ohmylms_roadmap_sources_invalid', __( 'Choose between one and ten distinct syllabuses.', 'ohmylms' ) );
		}
		$skills   = array();
		$sources  = array();
		$unmapped = 0;
		foreach ( $syllabus_ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				return Access::error( 'ohmylms_roadmap_sources_invalid', __( 'Syllabus IDs must be positive integers.', 'ohmylms' ) );
			}
		}
		if ( count( array_unique( $syllabus_ids ) ) !== count( $syllabus_ids ) ) {
			return Access::error( 'ohmylms_roadmap_sources_invalid', __( 'Choose distinct syllabuses.', 'ohmylms' ) );
		}
		foreach ( $syllabus_ids as $id ) {
			$item = Items::get( $id );
			if ( ! $item || empty( $item['is_syllabus'] ) ) {
				return Access::error( 'ohmylms_roadmap_sources_invalid', __( 'A selected syllabus is no longer available.', 'ohmylms' ) );
			}
			$rows = Comparison::objectives( $id );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			$sources[] = array(
				'id'      => $id,
				'name'    => $item['name'],
				'version' => $item['version'],
			);
			foreach ( Comparison::attach_mappings( $rows ) as $row ) {
				$ids   = $row['is_shared'] ? array( $row['term_id'] ) : array_column( $row['maps_to'], 'shared_id' );
				$found = false;
				foreach ( $ids as $shared_id ) {
					if ( isset( $skills[ $shared_id ] ) ) {
						$found = true;
						continue;
					}
					$term = get_term( (int) $shared_id, Taxonomy::NAME );
					if ( $term && ! is_wp_error( $term ) && metadata_exists( 'term', $shared_id, Comparison::SUBJECT_META ) ) {
						$found                = true;
						$skills[ $shared_id ] = array(
							'id'   => (int) $shared_id,
							'name' => Taxonomy::plain( $term->name ),
						);
					}
				}
				if ( ! $found ) {
					++$unmapped;
				}
			}
		}
		return array(
			'skills'   => array_values( $skills ),
			'sources'  => $sources,
			'unmapped' => $unmapped,
		);
	}

	/**
	 * Create a draft roadmap referencing ordered shared IDs.
	 *
	 * @param array $data Name, subject, grade and skill_ids.
	 * @return array|\WP_Error
	 */
	public static function create( array $data ) {
		$sources = array();
		if ( isset( $data['syllabus_ids'] ) ) {
			$preview = self::preview( $data['syllabus_ids'] );
			if ( is_wp_error( $preview ) ) {
				return $preview;
			}
			$sources = $preview['sources'];
			if ( ! isset( $data['skill_ids'] ) ) {
				$data['skill_ids'] = array_column( $preview['skills'], 'id' );
			}
			if ( ! is_array( $data['skill_ids'] ) ) {
				return Access::error( 'ohmylms_roadmap_invalid', __( 'Choose shared skills in learning order.', 'ohmylms' ) );
			}
			foreach ( $data['skill_ids'] as $skill_id ) {
				if ( ! is_int( $skill_id ) || ! in_array( $skill_id, array_column( $preview['skills'], 'id' ), true ) ) {
					return Access::error( 'ohmylms_roadmap_sources_changed', __( 'The source mappings changed. Load the shared skills again before saving.', 'ohmylms' ), 409 );
				}
			}
		}
		$ids = $data['skill_ids'] ?? null;
		if ( ! is_array( $ids ) || ( ! $ids && ! $sources ) || count( $ids ) > 200 ) {
			return Access::error( 'ohmylms_roadmap_invalid', __( 'Choose between one and 200 distinct shared skills in learning order.', 'ohmylms' ) );
		}
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				return Access::error( 'ohmylms_roadmap_invalid', __( 'Roadmap skill IDs must be positive integers.', 'ohmylms' ) );
			}
			if ( ! Comparison::shared_skill( absint( $id ) ) ) {
				return Access::error( 'ohmylms_roadmap_invalid', __( 'Every roadmap step must reference an existing shared skill.', 'ohmylms' ) );
			}
		}
		if ( count( $ids ) !== count( array_unique( $ids ) ) ) {
			return Access::error( 'ohmylms_roadmap_invalid', __( 'A shared skill can appear only once in a roadmap.', 'ohmylms' ) );
		}
		$settings = SyllabusSettings::clean(
			array(
				'subject' => $data['subject'] ?? '',
				'grade'   => $data['grade'] ?? '',
			)
		);
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		$item = Items::create(
			array(
				'name'        => $data['name'] ?? '',
				'item_type'   => 'roadmap',
				'parent_id'   => 0,
				'position'    => 0,
				'is_syllabus' => true,
				'description' => __( 'General roadmap using reusable shared skills.', 'ohmylms' ),
			)
		);
		if ( is_wp_error( $item ) ) {
			return $item;
		}
		$id     = (int) $item['id'];
		$course = SyllabusCourse::ensure( $id );
		$result = is_wp_error( $course ) ? $course : SyllabusSettings::save( $id, $settings );
		if ( ! is_wp_error( $result ) ) {
			$group  = Syllabus::add_group( $id, array( 'name' => __( 'Roadmap skills', 'ohmylms' ) ) );
			$result = $group;
			if ( ! is_wp_error( $group ) ) {
				foreach ( $ids as $skill_id ) {
					$result = Syllabus::add_skill( $id, $group, absint( $skill_id ) );
					if ( is_wp_error( $result ) ) {
						break;
					}
				}
			}
		}
		if ( is_wp_error( $result ) ) {
			Items::delete( $id, 'delete', true );
			if ( ! is_wp_error( $course ) ) {
				wp_delete_post( $course, true );
			}
			return $result;
		}
		if ( $sources ) {
			update_post_meta( $course, self::SOURCES_META, $sources );
		}
		return Syllabus::outline( $id );
	}

	/**
	 * Read an editable roadmap with a content revision.
	 *
	 * @param int $id Roadmap ID.
	 * @return array|\WP_Error
	 */
	public static function get( $id ) {
		$item = Items::get( $id );
		if ( ! $item || 'roadmap' !== $item['item_type'] || empty( $item['is_syllabus'] ) ) {
			return Access::error( 'ohmylms_roadmap_missing', __( 'Roadmap not found.', 'ohmylms' ), 404 );
		}
		$outline = Syllabus::outline( $id );
		if ( is_wp_error( $outline ) ) {
			return $outline;
		}
		$skills  = array();
		$folders = array();
		foreach ( $outline['contents'] as $content ) {
			foreach ( $content['groups'] as $group ) {
				$folders[] = array(
					'id'        => (int) $group['id'],
					'name'      => $group['name'],
					'skill_ids' => array_map( 'intval', array_column( $group['skills'], 'term_id' ) ),
				);
				foreach ( $group['skills'] as $skill ) {
					$skills[] = array(
						'id'        => (int) $skill['term_id'],
						'name'      => $skill['name'],
						'folder_id' => (int) $group['id'],
					);
				}
			}
		}
		$state             = array(
			'id'           => (int) $id,
			'name'         => $item['name'],
			'syllabus_ids' => array_column( $outline['roadmap_sources'], 'id' ),
			'skills'       => $skills,
			'folders'      => $folders,
		);
		$state['revision'] = Comparison::revision( $state );
		return $state;
	}

	/**
	 * Save a roadmap's sources, name and ordered shared references together.
	 *
	 * @param int   $id Roadmap ID.
	 * @param array $data Name, source IDs, skill IDs and expected revision.
	 * @return array|\WP_Error
	 */
	public static function save( $id, array $data ) {
		global $wpdb;
		$fields = Items::clean( array( 'name' => $data['name'] ?? '' ), true );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		if ( in_array( $id, (array) ( $data['syllabus_ids'] ?? array() ), true ) ) {
			return Access::error( 'ohmylms_roadmap_sources_invalid', __( 'A Roadmap cannot be its own source.', 'ohmylms' ) );
		}
		$preview = self::preview( $data['syllabus_ids'] ?? null );
		if ( is_wp_error( $preview ) ) {
			return $preview;
		}
		$ids = $data['skill_ids'] ?? null;
		if ( ! is_array( $ids ) || count( $ids ) > 200 ) {
			return Access::error( 'ohmylms_roadmap_invalid', __( 'Choose up to 200 shared skills in learning order.', 'ohmylms' ) );
		}
		foreach ( $ids as $skill_id ) {
			if ( ! is_int( $skill_id ) || $skill_id < 1 || ! Comparison::shared_skill( $skill_id ) ) {
				return Access::error( 'ohmylms_roadmap_invalid', __( 'Every step must reference an existing shared skill.', 'ohmylms' ) );
			}
		}
		if ( count( array_unique( $ids ) ) !== count( $ids ) ) {
			return Access::error( 'ohmylms_roadmap_invalid', __( 'A shared skill can appear only once in a Roadmap.', 'ohmylms' ) );
		}
		$result = Items::exclusive(
			static function () use ( $wpdb, $id, $data, $fields, $preview, $ids ) {
				$state = self::get( $id );
				if ( is_wp_error( $state ) ) {
					return $state;
				}
				if ( ( $data['expected_revision'] ?? '' ) !== $state['revision'] ) {
					return Access::error( 'ohmylms_roadmap_stale', __( 'This Roadmap changed. Reload it before saving.', 'ohmylms' ), 409 );
				}
				$group_ids = array_column( $state['folders'], 'id' );
				if ( ! $group_ids ) {
					return Access::error( 'ohmylms_roadmap_invalid', __( 'This Roadmap has no skill group.', 'ohmylms' ) );
				}
				$folders = $data['folders'] ?? null;
				if ( null === $folders ) {
					$folders  = $state['folders'];
					$assigned = array();
					foreach ( $folders as &$folder ) {
						$folder['skill_ids'] = array_values( array_intersect( $ids, $folder['skill_ids'] ) );
						$assigned            = array_merge( $assigned, $folder['skill_ids'] );
					}
					unset( $folder );
					$folders[0]['skill_ids'] = array_values( array_intersect( $ids, array_merge( $folders[0]['skill_ids'], array_diff( $ids, $assigned ) ) ) );
				}
				if ( ! is_array( $folders ) || ! $folders || count( $folders ) > Syllabus::MAX_GROUPS ) {
					return Access::error( 'ohmylms_roadmap_folder_invalid', __( 'Choose valid Roadmap folders.', 'ohmylms' ) );
				}
				$assigned = array();
				$existing = array();
				foreach ( $folders as &$folder ) {
					if ( ! is_array( $folder ) || ! is_int( $folder['id'] ?? null ) || $folder['id'] < 0 || ! is_string( $folder['name'] ?? null ) || ! is_array( $folder['skill_ids'] ?? null ) ) {
						return Access::error( 'ohmylms_roadmap_folder_invalid', __( 'Invalid Roadmap folder.', 'ohmylms' ) );
					}
					$folder['name'] = sanitize_text_field( $folder['name'] );
					if ( '' === $folder['name'] || mb_strlen( $folder['name'] ) > 190 || ( $folder['id'] && ! in_array( $folder['id'], $group_ids, true ) ) ) {
						return Access::error( 'ohmylms_roadmap_folder_invalid', __( 'Enter a folder name up to 190 characters and choose folders belonging to this Roadmap.', 'ohmylms' ) );
					}
					foreach ( $folder['skill_ids'] as $skill_id ) {
						if ( ! is_int( $skill_id ) || ! in_array( $skill_id, $ids, true ) || in_array( $skill_id, $assigned, true ) ) {
							return Access::error( 'ohmylms_roadmap_folder_invalid', __( 'Place each shared skill in exactly one Roadmap folder.', 'ohmylms' ) );
						}
						$assigned[] = $skill_id;
					}
					if ( $folder['id'] ) {
						$existing[] = $folder['id'];
					}
				}
				unset( $folder );
				if ( count( array_unique( $existing ) ) !== count( $existing ) || array_diff( $group_ids, $existing ) || array_diff( $ids, $assigned ) ) {
					return Access::error( 'ohmylms_roadmap_folder_invalid', __( 'Retain the existing folders and assign every shared skill.', 'ohmylms' ) );
				}
				foreach ( $group_ids as $group_id ) {
					if ( false === $wpdb->delete( Syllabus::placements_table(), array( 'group_id' => $group_id ) ) ) {
						throw new \RuntimeException( 'Roadmap steps could not be saved.' );
					}
				}
				foreach ( $folders as $folder_position => $folder ) {
					if ( ! $folder['id'] ) {
						$now = current_time( 'mysql', true );
						if ( false === $wpdb->insert(
							Syllabus::groups_table(),
							array(
								'uuid'        => wp_generate_uuid4(),
								'item_id'     => $id,
								'name'        => $folder['name'],
								'code'        => '',
								'description' => '',
								'position'    => $folder_position,
								'created_by'  => get_current_user_id(),
								'created_at'  => $now,
								'updated_at'  => $now,
							)
						) ) {
							throw new \RuntimeException( 'Roadmap folder could not be saved.' );
						}
						$folder['id'] = (int) $wpdb->insert_id;
					}
					foreach ( $folder['skill_ids'] as $position => $skill_id ) {
						if ( false === $wpdb->insert(
							Syllabus::placements_table(),
							array(
								'group_id' => $folder['id'],
								'term_id'  => $skill_id,
								'position' => $position,
							)
						) ) {
							throw new \RuntimeException( 'Roadmap step could not be saved.' );
						}
					}
				}
				if ( false === $wpdb->update( Items::table(), $fields + array( 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) ) ) {
					throw new \RuntimeException( 'Roadmap name could not be saved.' );
				}
				$course_id = SyllabusCourse::course_id( $id );
				update_post_meta( $course_id, self::SOURCES_META, $preview['sources'] );
				return true;
			}
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		SyllabusCourse::sync_quietly( $id );
		return self::get( $id );
	}
}
