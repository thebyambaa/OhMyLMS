<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The provenance review queue: where skills and their mappings came from, and who approved them.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Curriculum\Access;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;

defined( 'ABSPATH' ) || exit;

/**
 * Lists skills by review state and source, shows one skill's origin, audit trail and the provenance
 * of its mappings, and approves or rejects several skills at once. Each decision names the review
 * state the reviewer saw; a skill whose state changed since is left alone and reported.
 */
final class ProvenanceReview {

	/** Skills per page. */
	const PER_PAGE = 30;

	/** Most skills in one decision. */
	const MAX_REVIEW = 100;

	/**
	 * Skills in a review state, newest first.
	 *
	 * @param array $params review (pending, rejected, approved, all), source, search, page.
	 * @return array items, total, counts, sources.
	 */
	public static function queue( array $params ) {
		$review = (string) ( $params['review'] ?? 'pending' );
		$source = sanitize_key( (string) ( $params['source'] ?? '' ) );
		$page   = max( 1, (int) ( $params['page'] ?? 1 ) );
		$args   = array(
			'taxonomy'   => Taxonomy::NAME,
			'hide_empty' => false,
			'search'     => sanitize_text_field( (string) ( $params['search'] ?? '' ) ),
			'orderby'    => 'term_id',
			'order'      => 'DESC',
		);
		$meta   = array( 'relation' => 'AND' );
		if ( 'all' !== $review ) {
			$meta[] = self::review_clause( $review );
		}
		if ( '' !== $source ) {
			$meta[] = 'manual' === $source ? array(
				'relation' => 'OR',
				array(
					'key'   => Provenance::SOURCE_META,
					'value' => 'manual',
				),
				array(
					'key'     => Provenance::SOURCE_META,
					'compare' => 'NOT EXISTS',
				),
			) : array(
				'key'   => Provenance::SOURCE_META,
				'value' => $source,
			);
		}
		if ( count( $meta ) > 1 ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded, paged admin queue.
		}
		$total = wp_count_terms( $args );
		$terms = get_terms(
			$args + array(
				'number' => self::PER_PAGE,
				'offset' => ( $page - 1 ) * self::PER_PAGE,
			)
		);
		$counts = array();
		foreach ( array( 'pending', 'rejected' ) as $state ) {
			$count            = wp_count_terms(
				array(
					'taxonomy'   => Taxonomy::NAME,
					'hide_empty' => false,
					'meta_query' => array( self::review_clause( $state ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Two counts for the queue's views.
				)
			);
			$counts[ $state ] = is_wp_error( $count ) ? 0 : (int) $count;
		}
		return array(
			'items'   => array_map( array( __CLASS__, 'row' ), is_array( $terms ) ? $terms : array() ),
			'total'   => is_wp_error( $total ) ? 0 : (int) $total,
			'counts'  => $counts,
			'sources' => array_values( array_unique( array_merge( Provenance::BUILT_IN, array_keys( SkillSources::all() ) ) ) ),
		);
	}

	/**
	 * Meta clause for a review state; skills without a recorded state are approved.
	 *
	 * @param string $review Review state.
	 * @return array
	 */
	private static function review_clause( $review ) {
		if ( 'approved' === $review ) {
			return array(
				'relation' => 'OR',
				array(
					'key'   => Provenance::REVIEW_META,
					'value' => 'approved',
				),
				array(
					'key'     => Provenance::REVIEW_META,
					'compare' => 'NOT EXISTS',
				),
			);
		}
		return array(
			'key'   => Provenance::REVIEW_META,
			'value' => $review,
		);
	}

	/**
	 * A skill as the queue shows it.
	 *
	 * @param \WP_Term $term Skill term.
	 * @return array
	 */
	private static function row( $term ) {
		$id    = (int) $term->term_id;
		$trail = Provenance::audit( $id );
		$last  = $trail ? end( $trail ) : null;
		return array(
			'id'         => $id,
			'name'       => Taxonomy::plain( $term->name ),
			'code'       => (string) get_term_meta( $id, Syllabus::CODE_META, true ),
			'shared'     => metadata_exists( 'term', $id, Comparison::SUBJECT_META ),
			'subject'    => (string) get_term_meta( $id, Comparison::SUBJECT_META, true ),
			'provenance' => self::provenance( $id ),
			'last_audit' => $last ? self::entry( $last ) : null,
		);
	}

	/**
	 * Provenance of a skill with the creator's and reviewer's names.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array
	 */
	private static function provenance( $term_id ) {
		$origin = Provenance::read( $term_id );
		return array(
			'source'      => (string) $origin['source'],
			'ref'         => (string) $origin['ref'],
			'review'      => (string) $origin['review'],
			'status'      => (string) $origin['status'],
			'provider'    => (string) $origin['provider'],
			'model'       => (string) $origin['model'],
			'prompt'      => (string) $origin['prompt'],
			'confidence'  => null === $origin['confidence'] ? null : (float) $origin['confidence'],
			'created_by'  => self::user( (int) $origin['created_by'] ),
			'created_at'  => (string) $origin['created_at'],
			'reviewed_by' => self::user( (int) $origin['reviewed_by'] ),
			'reviewed_at' => (string) ( $origin['reviewed_at'] ?? '' ),
		);
	}

	/**
	 * One skill's origin, full audit trail and the provenance of its mappings.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array|\WP_Error
	 */
	public static function detail( $term_id ) {
		$term = get_term( (int) $term_id, Taxonomy::NAME );
		if ( ! $term || is_wp_error( $term ) ) {
			return Access::error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), 404 );
		}
		$mappings = array();
		foreach ( SkillMappings::all( array( (int) $term_id ) ) as $row ) {
			$other      = (int) $row['specific_term_id'] === (int) $term_id ? (int) $row['shared_term_id'] : (int) $row['specific_term_id'];
			$named      = get_term( $other, Taxonomy::NAME );
			$mappings[] = array(
				'id'         => (int) $row['id'],
				'direction'  => (int) $row['specific_term_id'] === (int) $term_id ? 'maps_to' : 'mapped_from',
				'other'      => array(
					'id'   => $other,
					'name' => $named && ! is_wp_error( $named ) ? Taxonomy::plain( $named->name ) : '',
				),
				'relation'   => (string) $row['relation'],
				'source'     => (string) ( $row['source'] ?? 'manual' ),
				'confidence' => isset( $row['confidence'] ) && null !== $row['confidence'] ? (float) $row['confidence'] : null,
				'note'       => (string) $row['note'],
				'created_by' => self::user( (int) ( $row['created_by'] ?? 0 ) ),
				'created_at' => (string) $row['created_at'],
			);
		}
		return self::row( $term ) + array(
			'audit'    => array_map( array( __CLASS__, 'entry' ), array_reverse( Provenance::audit( (int) $term_id ) ) ),
			'mappings' => $mappings,
		);
	}

	/**
	 * Approve or reject several skills. Each item names the review state the reviewer saw.
	 *
	 * @param array[] $items  id and expected_review.
	 * @param string  $review approved or rejected.
	 * @param string  $note   Optional reason for the audit trail.
	 * @return array|\WP_Error results: id => { review } or { error, code }.
	 */
	public static function review( array $items, $review, $note = '' ) {
		if ( ! $items || count( $items ) > self::MAX_REVIEW ) {
			/* translators: %d: most skills. */
			return Access::error( 'ohmylms_skill_review_invalid', sprintf( __( 'Review between 1 and %d skills at a time.', 'ohmylms' ), self::MAX_REVIEW ) );
		}
		$results = array();
		foreach ( $items as $item ) {
			$id = (int) ( $item['id'] ?? 0 );
			if ( isset( $results[ $id ] ) ) {
				continue;
			}
			$current = term_exists( $id, Taxonomy::NAME ) ? Provenance::read( $id )['review'] : null;
			if ( null === $current ) {
				$results[ $id ] = array(
					'code'  => 'ohmylms_skill_missing',
					'error' => __( 'Skill not found.', 'ohmylms' ),
				);
				continue;
			}
			if ( (string) ( $item['expected_review'] ?? '' ) !== $current ) {
				$results[ $id ] = array(
					'code'   => 'ohmylms_skill_review_stale',
					'error'  => __( 'Someone reviewed this skill after you loaded it.', 'ohmylms' ),
					'review' => $current,
				);
				continue;
			}
			$done           = Provenance::review( $id, $review, $note );
			$results[ $id ] = is_wp_error( $done ) ? array(
				'code'  => $done->get_error_code(),
				'error' => $done->get_error_message(),
			) : array( 'review' => $done['review'] );
		}
		return array( 'results' => $results );
	}

	/**
	 * An audit entry with the user's name.
	 *
	 * @param array $entry Stored entry.
	 * @return array
	 */
	private static function entry( array $entry ) {
		return array(
			'action'   => (string) ( $entry['action'] ?? '' ),
			'previous' => (string) ( $entry['previous'] ?? '' ),
			'note'     => (string) ( $entry['note'] ?? '' ),
			'by'       => self::user( (int) ( $entry['by'] ?? 0 ) ),
			'at'       => (string) ( $entry['at'] ?? '' ),
		);
	}

	/**
	 * A user's ID and display name.
	 *
	 * @param int $user_id User ID.
	 * @return array{id:int,name:string}
	 */
	private static function user( $user_id ) {
		$user = $user_id ? get_userdata( $user_id ) : null;
		return array(
			'id'   => (int) $user_id,
			'name' => $user ? (string) $user->display_name : '',
		);
	}
}
