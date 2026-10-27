<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Part-level skill attribution for questions.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Part-level skill attribution for questions.
 *
 * Draft mapping lives in post meta `_ohmylms_skill_map`:
 *   ['p1' => ['primary' => term_id, 'supporting' => [term_id, ...]], ...]
 * Each atomic part assesses one primary skill; supporting skills inform
 * recommendations only. The mapping is part of the version content hash and
 * is frozen into qb_version_skills when a version is captured.
 */
final class SkillMap {
	const META = '_ohmylms_skill_map';

	/** Current normalized mapping for the editable question.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function current( $question_id ) {
		$map = get_post_meta( (int) $question_id, self::META, true );
		return self::normalize( is_array( $map ) ? $map : array() );
	}

	/**
	 * Normalize for Skill map.
	 *
	 * @param array $map Map.
	 */
	public static function normalize( array $map ) {
		$clean = array();
		foreach ( $map as $part => $roles ) {
			$part       = preg_replace( '/[^a-z0-9_-]/i', '', (string) $part ) ?: 'p1';
			$primary    = (int) ( $roles['primary'] ?? 0 );
			$supporting = array_values(
				array_unique(
					array_filter(
						array_map( 'intval', (array) ( $roles['supporting'] ?? array() ) ),
						static function ( $id ) use ( $primary ) {
							return $id > 0 && $id !== $primary;
						}
					)
				)
			);
			sort( $supporting );
			if ( $primary || $supporting ) {
				$clean[ $part ] = array(
					'primary'    => $primary,
					'supporting' => $supporting,
				); }
		}
		ksort( $clean );
		return $clean;
	}

	/** Validate term IDs against the skills taxonomy; returns normalized map or WP_Error.
	 *
	 * @param mixed $map Map.
	 */
	public static function validate( $map ) {
		if ( ! is_array( $map ) ) {
			return new \WP_Error( 'ohmylms_skill_map_invalid', __( 'Skill mapping must be an object keyed by part.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$map = self::normalize( $map );
		foreach ( $map as $roles ) {
			foreach ( array_merge( array( $roles['primary'] ), $roles['supporting'] ) as $term_id ) {
				if ( $term_id && ! term_exists( (int) $term_id, Taxonomy::NAME ) ) {
					return new \WP_Error(
						'ohmylms_skill_missing',
						__( 'A mapped skill does not exist.', 'ohmylms' ),
						array(
							'status'  => 400,
							'term_id' => $term_id,
						)
					);
				}
			}
		}
		return $map;
	}

	/** Save the draft mapping and keep taxonomy relationships in step for filtering.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param array $map Map.
	 */
	public static function save( $question_id, array $map ) {
		$map = self::normalize( $map );
		update_post_meta( (int) $question_id, self::META, $map );
		$terms = array();
		foreach ( $map as $roles ) {
			$terms = array_merge( $terms, array( $roles['primary'] ), $roles['supporting'] ); }
		wp_set_object_terms( (int) $question_id, array_values( array_unique( array_filter( $terms ) ) ), Taxonomy::NAME );
		self::sync_table( $question_id, $map );
		return $map;
	}

	/** Replace the question's rows in the question-to-skill connection table.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param array $map Map.
	 */
	public static function sync_table( $question_id, array $map ) {
		global $wpdb;
		$table = Schema::table( 'question_skills' );
		$wpdb->delete( $table, array( 'question_id' => (int) $question_id ), array( '%d' ) );
		foreach ( $map as $part => $roles ) {
			$rows = $roles['primary'] ? array( array( $roles['primary'], 'primary' ) ) : array();
			foreach ( $roles['supporting'] as $term_id ) {
				$rows[] = array( $term_id, 'supporting' ); }
			foreach ( $rows as [$term_id, $role] ) {
				$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (question_id, term_id, part_id, role) VALUES (%d, %d, %s, %s)', $table, (int) $question_id, $term_id, $part, $role ) );
			}
		}
	}

	/** Fill the connection table from the skill maps saved before it existed. */
	public static function backfill() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, meta_value FROM %i WHERE meta_key = %s', $wpdb->postmeta, self::META ) );
		foreach ( $rows as $row ) {
			$map = maybe_unserialize( $row->meta_value );
			if ( is_array( $map ) ) {
				self::sync_table( (int) $row->post_id, self::normalize( $map ) );
			}
		}
	}

	/** Skills connected to a question: list of ['term_id','part_id','role'].
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function connections( $question_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT term_id, part_id, role FROM %i WHERE question_id=%d ORDER BY part_id, role, term_id', Schema::table( 'question_skills' ), (int) $question_id ), ARRAY_A );
	}

	/** Freeze the mapping for a newly captured version.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param mixed $version_id Version identifier.
	 */
	public static function freeze( $question_id, $version_id ) {
		global $wpdb;
		$table = Schema::table( 'qb_version_skills' );
		foreach ( self::current( $question_id ) as $part => $roles ) {
			$rows = $roles['primary'] ? array( array( $roles['primary'], 'primary' ) ) : array();
			foreach ( $roles['supporting'] as $term_id ) {
				$rows[] = array( $term_id, 'supporting' ); }
			foreach ( $rows as [$term_id, $role] ) {
				$wpdb->query(
					$wpdb->prepare( 'INSERT IGNORE INTO %i (version_id, part_id, term_id, skill_uuid, role) VALUES (%d, %s, %d, %s, %s)', $table, $version_id, $part, $term_id, Taxonomy::uuid( $term_id ), $role )
				);
			}
		}
	}

	/** Frozen mapping of a version: list of ['part_id','term_id','role'].
	 *
	 * @param mixed $version_id Version identifier.
	 */
	public static function for_version( $version_id ) {
		global $wpdb;
		$table = Schema::table( 'qb_version_skills' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT part_id, term_id, skill_uuid, role FROM %i WHERE version_id=%d ORDER BY part_id, role', $table, (int) $version_id ), ARRAY_A );
	}
}
