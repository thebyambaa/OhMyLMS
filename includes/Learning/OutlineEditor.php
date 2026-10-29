<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * One draft save for native units and program lesson/activity placements.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/** Called only inside the shared authorized course transaction and revision lock. */
final class OutlineEditor {

	/**
	 * Save a complete outline; new unit UUIDs are resolved before program validation.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $data Units and complete program draft.
	 * @return array|\WP_Error
	 */
	public static function save( $course_id, array $data ) {
		if ( ! is_array( $data['units'] ?? null ) || ! is_array( $data['program'] ?? null ) || count( $data['units'] ) > 2000 ) {
			return Catalog::error( __( 'Send the complete units and learning program.', 'ohmylms' ) );
		}
		$current = array_column( Catalog::chapters( $course_id ), 'id' );
		$stored  = CourseProgram::editor( $course_id )['draft'];
		$seen    = array();
		foreach ( $data['units'] as $row ) {
			$id       = is_array( $row ) ? ( $row['id'] ?? null ) : null;
			$existing = is_int( $id ) && in_array( $id, $current, true );
			$new      = is_string( $id ) && wp_is_uuid( $id );
			if ( ( ! $existing && ! $new ) || isset( $seen[ (string) $id ] ) || ( $existing && CourseStructure::owner( $id ) !== (int) $course_id ) ) {
				return Catalog::error( __( 'List this course’s units once, using a UUID for each new unit.', 'ohmylms' ) );
			}
			$seen[ (string) $id ] = true;
		}
		if ( array_diff( $current, array_keys( $seen ) ) ) {
			return Catalog::error( __( 'Keep existing units in the outline; remove empty units explicitly.', 'ohmylms' ) );
		}
		$map = array();
		foreach ( $data['units'] as $row ) {
			$unit = CourseStructure::save_unit(
				$course_id,
				is_int( $row['id'] ) ? $row['id'] : 0,
				array(
					'name'        => $row['title'] ?? '',
					'description' => $row['description'] ?? '',
					'number'      => $row['number'] ?? '',
				)
			);
			if ( is_wp_error( $unit ) ) {
				return $unit;
			}
			$map[ (string) $row['id'] ] = (int) $unit['id'];
		}
		$ordered = Catalog::reorder_chapters( $course_id, array_values( $map ) );
		if ( is_wp_error( $ordered ) ) {
			return $ordered;
		}
		$program = $data['program'];
		foreach ( array( 'items', 'outcomes', 'groups' ) as $key ) {
			if ( ! is_array( $program[ $key ] ?? array() ) ) {
				return Catalog::error( __( 'Send valid groups, outcomes and activities.', 'ohmylms' ) );
			}
			$identity = 'outcomes' === $key ? 'term_id' : 'id';
			$previous = array_column( $stored[ $key ] ?? array(), null, $identity );
			foreach ( $program[ $key ] ?? array() as $position => $row ) {
				if ( ! is_array( $row ) ) {
					return Catalog::error( __( 'Send valid outline rows.', 'ohmylms' ) );
				}
				$chapter = $row['chapter_id'] ?? 0;
				if ( ! is_int( $chapter ) && ! is_string( $chapter ) ) {
					return Catalog::error( __( 'Use a valid unit identifier for each outline row.', 'ohmylms' ) );
				}
				if ( $chapter && ! isset( $map[ (string) $chapter ] ) ) {
					$row_id = $row[ $identity ] ?? null;
					$old    = is_int( $row_id ) || is_string( $row_id ) ? ( $previous[ (string) $row_id ] ?? null ) : null;
					// Older drafts can retain skill/group associations after their native unit was removed.
					// Repair only an unchanged stored association. Activities and new references still fail validation.
					if ( 'items' !== $key && $old && (string) ( $old['chapter_id'] ?? 0 ) === (string) $chapter ) {
						if ( 'groups' === $key ) {
							unset( $program[ $key ][ $position ] );
						} else {
							unset( $program[ $key ][ $position ]['chapter_id'], $program[ $key ][ $position ]['group_id'] );
						}
						continue;
					}
					return Catalog::error( __( 'An activity or group references an unknown unit.', 'ohmylms' ) );
				}
				if ( $chapter ) {
					$program[ $key ][ $position ]['chapter_id'] = $map[ (string) $chapter ];
				}
			}
			if ( isset( $program[ $key ] ) ) {
				$program[ $key ] = array_values( $program[ $key ] );
			}
		}
		$saved = CourseProgram::save( $course_id, $program );
		if ( ! is_wp_error( $saved ) && true === ( $data['activate'] ?? false ) ) {
			$saved = self::activate( $course_id, $program );
		}
		return is_wp_error( $saved ) ? $saved : array(
			'saved'    => true,
			'unit_ids' => $map,
		);
	}

	/**
	 * Make a saved outline and its saved block resources available in one transaction.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $program Validated outline program.
	 * @return array|\WP_Error Active program or validation error.
	 */
	private static function activate( $course_id, array $program ) {
		$ids = array( $course_id );
		foreach ( $program['items'] as $item ) {
			if ( in_array( $item['type'], array( 'lesson', 'quiz' ), true ) ) {
				$ids[] = (int) $item['content_id'];
			}
		}
		foreach ( array_unique( $ids ) as $id ) {
			$type = get_post_type_object( get_post_type( $id ) );
			if ( ! $type || ! current_user_can( 'edit_post', $id ) || ! current_user_can( $type->cap->publish_posts ) ) {
				return Catalog::error( __( 'You cannot make this course or one of its resources available to learners.', 'ohmylms' ), 403 );
			}
		}
		foreach ( array_unique( $ids ) as $id ) {
			if ( 'draft' === get_post_status( $id ) ) {
				$result = wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		$editor                         = CourseProgram::editor( $course_id );
		$program['expected_program_id'] = (int) ( $editor['published']['id'] ?? 0 );
		$result                         = CourseProgram::save( $course_id, $program, true, true );
		if ( is_wp_error( $result ) ) {
			foreach ( array_unique( $ids ) as $id ) {
				clean_post_cache( $id );
			}
			return new \WP_Error( $result->get_error_code(), __( 'The course could not be saved for learners.', 'ohmylms' ), $result->get_error_data() );
		}
		return $result;
	}
}
