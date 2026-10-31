<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Place shared skills in domains (strands) and level bands, several at a time.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Domain placement for the skill workspace. Listing reuses the shared-skill search and can keep only
 * unplaced skills. A batch is all or nothing: every placement is checked against its skill's subject
 * profile, then every skill's revision is compared under the mapping lock before anything is stored.
 */
final class PlacementBatch {

	/** Most skills in one batch. */
	const MAX_ITEMS = 50;

	/** Skills per page. */
	const PER_PAGE = 30;

	/**
	 * Shared skills of a subject with their placement, optionally only those without a domain.
	 *
	 * @param array $params subject, search, domain, unplaced, page.
	 * @return array items, total, domains, levels.
	 */
	public static function listing( array $params ) {
		$subject = sanitize_text_field( (string) ( $params['subject'] ?? '' ) );
		$page    = max( 1, (int) ( $params['page'] ?? 1 ) );
		if ( empty( $params['unplaced'] ) ) {
			$found = Comparison::shared_skills(
				array(
					'subject' => $subject,
					'search'  => (string) ( $params['search'] ?? '' ),
					'domain'  => (string) ( $params['domain'] ?? '' ),
					'page'    => $page,
				)
			);
			$items = $found['items'];
			$total = $found['total'];
		} else {
			$args = array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'search'     => sanitize_text_field( (string) ( $params['search'] ?? '' ) ),
				'orderby'    => 'name',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded admin list of unplaced shared skills.
				'meta_query' => array(
					'relation' => 'AND',
					'' !== $subject ? array(
						'key'   => Comparison::SUBJECT_META,
						'value' => $subject,
					) : array(
						'key'     => Comparison::SUBJECT_META,
						'compare' => 'EXISTS',
					),
					array(
						'relation' => 'OR',
						array(
							'key'     => Comparison::DOMAIN_META,
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'   => Comparison::DOMAIN_META,
							'value' => '',
						),
					),
				),
			);
			$total = wp_count_terms( $args );
			$terms = get_terms(
				$args + array(
					'number' => self::PER_PAGE,
					'offset' => ( $page - 1 ) * self::PER_PAGE,
				)
			);
			$items = array_values( array_filter( array_map( static fn( $term ) => Comparison::shared_skill( $term->term_id ), is_array( $terms ) ? $terms : array() ) ) );
			$total = is_wp_error( $total ) ? 0 : (int) $total;
		}
		return array(
			'items'   => array_map( array( __CLASS__, 'row' ), $items ),
			'total'   => (int) $total,
			'domains' => Comparison::domains( $subject ),
			'levels'  => Comparison::levels( $subject ),
		);
	}

	/**
	 * A shared skill as the placement table shows it.
	 *
	 * @param array $skill From Comparison::shared_skill().
	 * @return array
	 */
	private static function row( array $skill ) {
		return array(
			'id'         => (int) $skill['id'],
			'name'       => (string) $skill['name'],
			'subject'    => (string) $skill['subject'],
			'topic'      => (string) $skill['topic'],
			'domain'     => (string) $skill['domain'],
			'grade_from' => (int) $skill['grade_from'],
			'grade_to'   => (int) $skill['grade_to'],
			'revision'   => (string) $skill['revision'],
		);
	}

	/**
	 * Store several placements, all or none.
	 *
	 * @param array[] $items id, domain, grade_from, grade_to, expected_revision.
	 * @return array|\WP_Error The saved rows.
	 */
	public static function save( array $items ) {
		global $wpdb;
		if ( ! $items || count( $items ) > self::MAX_ITEMS ) {
			/* translators: %d: most skills. */
			return Access::error( 'ohmylms_placement_invalid', sprintf( __( 'Place between 1 and %d skills at a time.', 'ohmylms' ), self::MAX_ITEMS ) );
		}
		$checked = array();
		foreach ( $items as $item ) {
			$id    = (int) ( $item['id'] ?? 0 );
			$skill = $id ? Comparison::shared_skill( $id ) : null;
			if ( ! $skill ) {
				return Access::error( 'ohmylms_shared_missing', __( 'Shared skill not found.', 'ohmylms' ), 404, array( 'skill_id' => $id ) );
			}
			if ( isset( $checked[ $id ] ) ) {
				return Access::error( 'ohmylms_placement_invalid', __( 'Place each skill once per batch.', 'ohmylms' ) );
			}
			$placement = Comparison::placement( $item['domain'] ?? '', $item['grade_from'] ?? 0, $item['grade_to'] ?? 0, $skill['subject'] );
			if ( is_wp_error( $placement ) ) {
				$placement->add_data( array( 'status' => 400, 'skill_id' => $id ) );
				return $placement;
			}
			$checked[ $id ] = array( $placement, (string) ( $item['expected_revision'] ?? '' ) );
		}
		$lock = SkillMappings::LOCK . '-' . md5( $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Non-cacheable advisory lock shared with shared-skill saves.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			return Access::error( 'ohmylms_mapping_busy', __( 'Another mapping change is being saved. Try again.', 'ohmylms' ), 409 );
		}
		try {
			$stale = array();
			foreach ( $checked as $id => $entry ) {
				$current = Comparison::shared_skill( $id );
				if ( ! $current || ! hash_equals( $current['revision'], $entry[1] ) ) {
					$stale[] = $id;
				}
			}
			if ( $stale ) {
				return Access::error( 'ohmylms_shared_stale', __( 'Some skills changed after you loaded them. Reload them before saving.', 'ohmylms' ), 409, array( 'skill_ids' => $stale ) );
			}
			foreach ( $checked as $id => $entry ) {
				Comparison::store_placement( $id, $entry[0] );
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Release the advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		return array(
			'items' => array_map(
				static fn( $id ) => self::row( Comparison::shared_skill( $id ) ),
				array_keys( $checked )
			),
		);
	}
}
