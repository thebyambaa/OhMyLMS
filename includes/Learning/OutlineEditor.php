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
			foreach ( $program[ $key ] ?? array() as $position => $row ) {
				if ( ! is_array( $row ) ) {
					return Catalog::error( __( 'Send valid outline rows.', 'ohmylms' ) );
				}
				$chapter = $row['chapter_id'] ?? 0;
				if ( ! is_int( $chapter ) && ! is_string( $chapter ) ) {
					return Catalog::error( __( 'Use a valid unit identifier for each outline row.', 'ohmylms' ) );
				}
				if ( $chapter && ! isset( $map[ (string) $chapter ] ) ) {
					return Catalog::error( __( 'An activity or group references an unknown unit.', 'ohmylms' ) );
				}
				if ( $chapter ) {
					$program[ $key ][ $position ]['chapter_id'] = $map[ (string) $chapter ];
				}
			}
		}
		$saved = CourseProgram::save( $course_id, $program );
		return is_wp_error( $saved ) ? $saved : array(
			'saved'    => true,
			'unit_ids' => $map,
		);
	}
}
