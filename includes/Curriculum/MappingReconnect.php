<?php // phpcs:ignore WordPress.Files.FileName -- Existing PSR-4 mapping requires the class-matching filename.
/**
 * Replace a mapping endpoint without removing the original connection first.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Revision-checked, single-row reconnection of individual mappings. */
final class MappingReconnect {

	/**
	 * Replace one source endpoint, retaining the shared skill and mapping ID.
	 *
	 * @param array $data Old and new source IDs, source syllabus, relationship and revision.
	 * @return array|\WP_Error Saved mapping or a conflict without changes.
	 */
	public static function save( array $data ) {
		global $wpdb;

		foreach ( array( 'old_specific_id', 'specific_id', 'shared_id', 'source_id' ) as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_int( $data[ $key ] ) || 0 >= $data[ $key ] ) {
				return Access::error( 'ohmylms_reconnect_invalid', __( 'Choose an existing source objective and shared skill.', 'ohmylms' ) );
			}
		}
		if ( ! is_string( $data['relation'] ?? null ) || ! in_array( $data['relation'], array( 'equivalent', 'broader', 'narrower', 'related' ), true ) || ! is_string( $data['note'] ?? '' ) || ! is_string( $data['expected_revision'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $data['expected_revision'] ) ) {
			return Access::error( 'ohmylms_reconnect_invalid', __( 'Choose an individual relationship and reload its current revision.', 'ohmylms' ) );
		}
		$note = sanitize_textarea_field( $data['note'] ?? '' );
		if ( 1000 < mb_strlen( $note ) ) {
			return Access::error( 'ohmylms_reconnect_invalid', __( 'Notes can have at most 1000 characters.', 'ohmylms' ) );
		}
		$objectives = Comparison::objectives( $data['source_id'] );
		if ( is_wp_error( $objectives ) || ! in_array( $data['specific_id'], array_column( $objectives, 'term_id' ), true ) ) {
			return Access::error( 'ohmylms_reconnect_invalid', __( 'The replacement objective must belong to the chosen source syllabus.', 'ohmylms' ) );
		}
		$lock = SkillMappings::LOCK . '-' . md5( $wpdb->prefix );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_mapping_busy', __( 'Another mapping change is being saved. Try again.', 'ohmylms' ), 409 );
		}
		try {
			if ( ! Comparison::shared_skill( $data['shared_id'] ) || ! term_exists( $data['specific_id'], Taxonomy::NAME ) || metadata_exists( 'term', $data['specific_id'], Comparison::SUBJECT_META ) || $data['specific_id'] === $data['shared_id'] ) {
				return Access::error( 'ohmylms_mapping_chain', __( 'Connect a source objective to a reusable shared skill.', 'ohmylms' ), 409 );
			}
			$current = $wpdb->get_row(
				$wpdb->prepare( 'SELECT * FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', SkillMappings::table(), $data['old_specific_id'], $data['shared_id'] ),
				ARRAY_A
			);
			if ( ! $current || SkillMappings::revision( $current ) !== $data['expected_revision'] ) {
				return Access::error( 'ohmylms_mapping_stale', __( 'This mapping changed. Reload before reconnecting it.', 'ohmylms' ), 409 );
			}
			if ( ! empty( $current['group_uuid'] ) ) {
				return Access::error( 'ohmylms_mapping_group_invalid', __( 'Edit combined coverage as a complete group.', 'ohmylms' ), 409 );
			}
			$duplicate = $wpdb->get_var(
				$wpdb->prepare( 'SELECT id FROM %i WHERE specific_term_id=%d AND shared_term_id=%d AND id<>%d', SkillMappings::table(), $data['specific_id'], $data['shared_id'], $current['id'] )
			);
			if ( $duplicate ) {
				return Access::error( 'ohmylms_reconnect_duplicate', __( 'That objective is already connected. Edit its existing connection instead.', 'ohmylms' ), 409 );
			}
			$chain = $wpdb->get_var(
				$wpdb->prepare( 'SELECT id FROM %i WHERE shared_term_id=%d OR specific_term_id=%d LIMIT 1', SkillMappings::table(), $data['specific_id'], $data['shared_id'] )
			);
			if ( $chain ) {
				return Access::error( 'ohmylms_mapping_chain', __( 'Shared skills cannot be connected through another shared skill.', 'ohmylms' ), 409 );
			}
			$result = $wpdb->update(
				SkillMappings::table(),
				array(
					'specific_term_id' => $data['specific_id'],
					'relation'         => $data['relation'],
					'note'             => $note,
					'updated_at'       => current_time( 'mysql', true ),
				),
				array( 'id' => (int) $current['id'] ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $result ) {
				return Access::error( 'ohmylms_mapping_failed', __( 'The connection could not be changed. The original connection remains.', 'ohmylms' ), 500 );
			}
			return array(
				'id'          => (int) $current['id'],
				'specific_id' => $data['specific_id'],
				'shared_id'   => $data['shared_id'],
				'relation'    => $data['relation'],
				'note'        => $note,
				'revision'    => SkillMappings::revision(
					array(
						'relation' => $data['relation'],
						'note'     => $note,
					)
				),
			);
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}
}
