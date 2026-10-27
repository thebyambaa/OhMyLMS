<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Explicit lesson targets and evidence-backed alignment reviews.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

use OhMyLMS\Learning\Catalog;
use OhMyLMS\MCP\ContentAuthoring;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Reviews describe suitability; they never award mastery or complete a course. */
final class LessonObjectives {
	const LINKS        = '_ohmylms_lesson_objectives';
	const REVIEWS      = '_ohmylms_alignment_reviews';
	const MANAGED_TAGS = '_ohmylms_objective_managed_tags';

	/**
	 * Read bounded metadata lists.
	 *
	 * @param int    $id Lesson ID.
	 * @param string $key Metadata key.
	 * @return array
	 */
	private static function stored( $id, $key ) {
		$value = get_post_meta( $id, $key, true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Resolve one reference against its official source.
	 *
	 * @param array $reference Syllabus and term IDs.
	 * @param array $cache Request-local syllabus outlines.
	 * @return array|null
	 */
	private static function objective( array $reference, array &$cache ) {
		$id = $reference['syllabus_id'];
		if ( ! array_key_exists( $id, $cache ) ) {
			$cache[ $id ] = CourseObjectives::objectives( $id );
		}
		$rows = $cache[ $id ];
		return is_wp_error( $rows ) ? null : ( $rows[ $reference['term_id'] ] ?? null );
	}

	/**
	 * Read mappings, full objective wording and review freshness.
	 *
	 * @param int $id Lesson ID.
	 * @return array|\WP_Error
	 */
	public static function read( $id ) {
		$content = ContentAuthoring::read( 'lesson', $id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$links   = self::stored( $id, self::LINKS );
		$reviews = self::stored( $id, self::REVIEWS );
		$rows    = array();
		$cache   = array();
		foreach ( $links as $link ) {
			$objective          = self::objective( $link, $cache );
			$key                = $link['syllabus_id'] . ':' . $link['term_id'];
			$review             = $reviews[ $key ] ?? null;
			$objective_revision = $objective ? ContentAuthoring::revision( $objective ) : '';
			if ( $review ) {
				$review['stale'] = $content['revision'] !== $review['content_revision'] || $objective_revision !== $review['objective_revision'];
			}
			$rows[] = $link + array(
				'objective'          => $objective,
				'objective_revision' => $objective_revision,
				'missing'            => null === $objective,
				'review'             => $review,
			);
		}
		return array(
			'id'               => (int) $id,
			'revision'         => ContentAuthoring::revision( $links ),
			'review_revision'  => ContentAuthoring::revision( $reviews ),
			'content_revision' => $content['revision'],
			'mappings'         => $rows,
		);
	}

	/**
	 * Replace explicit targets while retaining unrelated existing skill tags.
	 *
	 * @param int   $id Lesson ID.
	 * @param array $data Complete mappings and revision.
	 * @return array|\WP_Error
	 */
	public static function save( $id, array $data ) {
		return Items::exclusive(
			static function () use ( $id, $data ) {
				wp_cache_delete( $id, 'post_meta' );
				$state = self::read( $id );
				if ( is_wp_error( $state ) ) {
					return $state;
				}
				if ( ( $data['revision'] ?? '' ) !== $state['revision'] ) {
					return Catalog::error( __( 'Lesson mappings changed. Reload before saving.', 'ohmylms' ), 409 );
				}
				$rows = $data['mappings'] ?? null;
				if ( ! is_array( $rows ) || array_values( $rows ) !== $rows || count( $rows ) > 200 ) {
					return Catalog::error( __( 'Provide at most 200 lesson objective mappings.', 'ohmylms' ) );
				}
				$next  = array();
				$cache = array();
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) || ! isset( $row['syllabus_id'], $row['term_id'], $row['relation'] ) || ! is_int( $row['syllabus_id'] ) || ! is_int( $row['term_id'] ) || $row['syllabus_id'] < 1 || $row['term_id'] < 1 || ! in_array( $row['relation'], array( 'teaches', 'practices', 'assesses' ), true ) ) {
						return Catalog::error( __( 'Choose a valid syllabus objective and relationship.', 'ohmylms' ) );
					}
					$reference = array_intersect_key( $row, array_flip( array( 'syllabus_id', 'term_id', 'relation' ) ) );
					if ( ! self::objective( $reference, $cache ) ) {
						return Catalog::error( __( 'The objective is unavailable in this syllabus.', 'ohmylms' ), 409 );
					}
					$next[ $row['syllabus_id'] . ':' . $row['term_id'] ] = $reference;
				}
				$managed = self::stored( $id, self::MANAGED_TAGS );
				$tags    = wp_get_object_terms( $id, Taxonomy::NAME, array( 'fields' => 'ids' ) );
				if ( is_wp_error( $tags ) ) {
					return $tags;
				}
				$tags         = array_map( 'intval', $tags );
				$new_ids      = array_values( array_unique( array_column( $next, 'term_id' ) ) );
				$next_managed = array_values( array_intersect( array_unique( array_merge( $managed, array_diff( $new_ids, $tags ) ) ), $new_ids ) );
				$tags         = array_values( array_unique( array_merge( array_diff( $tags, $managed ), $new_ids ) ) );
				$saved        = Catalog::set_lesson_skills( $id, $tags );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				update_post_meta( $id, self::LINKS, array_values( $next ) );
				update_post_meta( $id, self::MANAGED_TAGS, $next_managed );
				update_post_meta( $id, self::REVIEWS, wp_slash( array_intersect_key( self::stored( $id, self::REVIEWS ), $next ) ) );
				return self::read( $id );
			}
		);
	}

	/**
	 * Record one caller-supplied review against the inspected content and objective.
	 *
	 * @param int   $id Lesson ID.
	 * @param array $data Review evidence and snapshot revisions.
	 * @return array|\WP_Error
	 */
	public static function review( $id, array $data ) {
		return Items::exclusive(
			static function () use ( $id, $data ) {
				wp_cache_delete( $id, 'post_meta' );
				$state = self::read( $id );
				if ( is_wp_error( $state ) ) {
					return $state;
				}
				if ( ( $data['review_revision'] ?? '' ) !== $state['review_revision'] || ( $data['content_revision'] ?? '' ) !== $state['content_revision'] ) {
					return Catalog::error( __( 'Content or reviews changed. Read the latest snapshots before reviewing.', 'ohmylms' ), 409 );
				}
				$match = null;
				foreach ( $state['mappings'] as $mapping ) {
					if ( ( $data['syllabus_id'] ?? 0 ) === $mapping['syllabus_id'] && ( $data['term_id'] ?? 0 ) === $mapping['term_id'] ) {
						$match = $mapping;
					}
				}
				if ( ! $match || $match['missing'] || ( $data['objective_revision'] ?? '' ) !== $match['objective_revision'] ) {
					return Catalog::error( __( 'Map and inspect the current official objective before reviewing it.', 'ohmylms' ), 409 );
				}
				if ( ! in_array( $data['status'] ?? '', array( 'aligned', 'partial', 'not_aligned' ), true ) ) {
					return Catalog::error( __( 'Choose aligned, partial or not_aligned.', 'ohmylms' ) );
				}
				foreach ( array( 'evidence', 'recommendation' ) as $field ) {
					if ( ! isset( $data[ $field ] ) || ! is_string( $data[ $field ] ) || mb_strlen( $data[ $field ] ) > 4000 || ( 'evidence' === $field && '' === trim( $data[ $field ] ) ) ) {
						return Catalog::error( __( 'Provide evidence and a recommendation of up to 4000 characters each.', 'ohmylms' ) );
					}
					$data[ $field ] = sanitize_textarea_field( $data[ $field ] );
					if ( 'evidence' === $field && '' === trim( $data[ $field ] ) ) {
						return Catalog::error( __( 'Provide readable evidence for the alignment review.', 'ohmylms' ) );
					}
				}
				$reviews = self::stored( $id, self::REVIEWS );
				$reviews[ $match['syllabus_id'] . ':' . $match['term_id'] ] = array(
					'status'             => $data['status'],
					'evidence'           => sanitize_textarea_field( $data['evidence'] ),
					'recommendation'     => sanitize_textarea_field( $data['recommendation'] ),
					'content_revision'   => $state['content_revision'],
					'objective_revision' => $match['objective_revision'],
					'reviewed_by'        => get_current_user_id(),
					'reviewed_at'        => gmdate( 'c' ),
				);
				update_post_meta( $id, self::REVIEWS, wp_slash( $reviews ) );
				return self::read( $id );
			}
		);
	}
}
