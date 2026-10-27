<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Read-only skill lookup for classification: code, name, objective and where a skill sits.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank;

use OhMyLMS\Skills\Objectives;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Finds skills by code or words and describes their place in the skill tree. */
final class SkillCatalogue {

	const CODE_META = '_ohmylms_skill_code';
	const UUID_META = '_ohmylms_skill_uuid';

	/** Words too common to help find a skill. */
	const STOP_WORDS = array( 'the', 'and', 'for', 'with', 'use', 'using', 'find', 'from', 'that', 'this', 'into', 'then', 'are', 'its', 'any' );

	/**
	 * Search skills, best match first.
	 *
	 * A skill matches when any search word appears in its name, code, full objective or description
	 * (or equals its UUID). Skills matching more words, and matching them in the name or code,
	 * rank higher, so "bearing scale drawing" finds the bearings skill first. Long words are cut
	 * three letters ("differentiation" also finds "differentiate"). An exact code is listed first.
	 *
	 * @param string $search Words to look for. Empty lists all in code order.
	 * @param int    $page 1-based page.
	 * @param int    $per_page Page size, 1-50.
	 * @param int    $parent_id Parent skill ID to list only its direct children; -1 for any.
	 * @return array{items:array[],total:int}
	 */
	public static function search( $search = '', $page = 1, $per_page = 20, $parent_id = -1 ) {
		global $wpdb;
		$search   = trim( (string) $search );
		$page     = max( 1, (int) $page );
		$per_page = max( 1, min( 50, (int) $per_page ) );
		$words    = array();
		foreach ( array_slice( array_filter( preg_split( '/[^\p{L}\p{N}.]+/u', mb_strtolower( $search ) ) ), 0, 12 ) as $word ) {
			if ( mb_strlen( $word ) >= 3 && ! in_array( $word, self::STOP_WORDS, true ) ) {
				$words[] = mb_strlen( $word ) >= 7 ? mb_substr( $word, 0, mb_strlen( $word ) - 3 ) : $word;
			}
		}
		$words      = array_values( array_unique( $words ) );
		$joins      = "FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id AND tt.taxonomy=%s LEFT JOIN {$wpdb->termmeta} c ON c.term_id=t.term_id AND c.meta_key=%s LEFT JOIN {$wpdb->termmeta} u ON u.term_id=t.term_id AND u.meta_key=%s LEFT JOIN {$wpdb->termmeta} o ON o.term_id=t.term_id AND o.meta_key=%s";
		$args       = array( Taxonomy::NAME, self::CODE_META, self::UUID_META, Objectives::META_KEY );
		$score      = array();
		$score_args = array();
		foreach ( $words as $word ) {
			$like = '%' . $wpdb->esc_like( $word ) . '%';
			// A skill without a code, objective or UUID has NULL there; COALESCE keeps its score from turning NULL and hiding it.
			$score[]    = '(COALESCE(MAX(t.name LIKE %s),0)*3 + COALESCE(MAX(c.meta_value LIKE %s),0)*3 + COALESCE(MAX(o.meta_value LIKE %s),0) + COALESCE(MAX(tt.description LIKE %s),0) + COALESCE(MAX(u.meta_value=%s),0)*10)';
			$score_args = array_merge( $score_args, array( $like, $like, $like, $like, $word ) );
		}
		$score_sql   = $score ? implode( ' + ', $score ) : '0';
		$having      = $score ? 'HAVING score>0' : '';
		$filter      = $parent_id >= 0 ? ' WHERE tt.parent=%d' : '';
		$filter_args = $parent_id >= 0 ? array( (int) $parent_id ) : array();
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only lookup; the fragments hold only placeholders and core table names.
		$inner = "SELECT t.term_id, MIN(c.meta_value) AS code, MIN(t.name) AS name, COALESCE(MAX(c.meta_value=%s),0) AS exact, $score_sql AS score $joins$filter GROUP BY t.term_id $having";
		$base  = array_merge( array( $search ), $score_args, $args, $filter_args );
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ($inner) m", $base ) );
		$ids   = array_map(
			'intval',
			$wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM ($inner) m ORDER BY exact DESC, score DESC, code, name LIMIT %d OFFSET %d", array_merge( $base, array( $per_page, ( $page - 1 ) * $per_page ) ) ) )
		);
		// phpcs:enable
		return array(
			'items' => self::describe( $ids ),
			'total' => $total,
		);
	}

	/**
	 * Describe skills with their full path, in the order given.
	 *
	 * @param int[] $ids Skill term IDs.
	 * @return array[]
	 */
	public static function describe( array $ids ) {
		if ( ! $ids ) {
			return array();
		}
		update_termmeta_cache( $ids );
		$all     = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'hide_empty' => false,
				'fields'     => 'id=>parent',
			)
		);
		$parents = is_array( $all ) ? array_map( 'intval', $all ) : array();
		$items   = array();
		foreach ( $ids as $id ) {
			$term = get_term( $id, Taxonomy::NAME );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$path  = array();
			$walk  = (int) ( $parents[ $id ] ?? 0 );
			$guard = 0;
			while ( $walk && $guard++ < 12 ) {
				$ancestor = get_term( $walk, Taxonomy::NAME );
				if ( ! $ancestor || is_wp_error( $ancestor ) ) {
					break;
				}
				array_unshift(
					$path,
					array(
						'id'   => (int) $ancestor->term_id,
						'code' => (string) get_term_meta( $ancestor->term_id, self::CODE_META, true ),
						'name' => html_entity_decode( $ancestor->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
					)
				);
				$walk = (int) ( $parents[ $walk ] ?? 0 );
			}
			$items[] = array(
				'id'        => (int) $term->term_id,
				'uuid'      => (string) get_term_meta( $term->term_id, self::UUID_META, true ),
				'code'      => (string) get_term_meta( $term->term_id, self::CODE_META, true ),
				'name'      => html_entity_decode( $term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'objective' => mb_substr( Objectives::get( $term ), 0, 600 ),
				'parent_id' => (int) $term->parent,
				'path'      => $path,
				'children'  => count( array_keys( $parents, (int) $term->term_id, true ) ),
			);
		}
		return $items;
	}
}
