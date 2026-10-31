<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Questions classified by AI that wait for a person to approve them.
 *
 * Approval is what lets a question count toward practice and learner progress, so AI-imported
 * questions stay unapproved and flagged here until someone reviews them.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank;

defined( 'ABSPATH' ) || exit;

/** Flags, lists and decides on questions that need review. */
final class ReviewQueue {

	const STATUS_META = '_ohmylms_review_status';
	const DATA_META   = '_ohmylms_review_data';
	const PENDING     = 'pending';

	/**
	 * Flag a question for review.
	 *
	 * @param int   $question_id Question post ID.
	 * @param array $data Optional: by, source, confidence (0-1), notes.
	 */
	public static function flag( $question_id, array $data = array() ) {
		$notes = isset( $data['notes'] ) ? mb_substr( sanitize_textarea_field( (string) $data['notes'] ), 0, 1000 ) : '';
		update_post_meta( (int) $question_id, self::STATUS_META, self::PENDING );
		update_post_meta(
			(int) $question_id,
			self::DATA_META,
			array(
				'by'         => sanitize_key( $data['by'] ?? 'ai' ),
				'source'     => sanitize_key( $data['source'] ?? '' ),
				'confidence' => isset( $data['confidence'] ) && is_numeric( $data['confidence'] ) ? max( 0, min( 1, (float) $data['confidence'] ) ) : null,
				'notes'      => $notes,
				'flagged_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Questions waiting for review, least confident first, then oldest first.
	 *
	 * @param int    $page 1-based page.
	 * @param int    $per_page Page size.
	 * @param string $search Optional words in the question title.
	 * @return array{items:array[],total:int}
	 */
	public static function pending( $page = 1, $per_page = 10, $search = '' ) {
		$query = new \WP_Query(
			array(
				'post_type'              => OHMYLMS_QUESTION_CPT,
				'post_status'            => 'any',
				'posts_per_page'         => max( 1, min( 50, (int) $per_page ) ),
				'paged'                  => max( 1, (int) $page ),
				's'                      => (string) $search,
				'meta_key'               => self::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Narrow marker meta.
				'meta_value'             => self::PENDING, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Narrow marker meta.
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'update_post_term_cache' => false,
			)
		);
		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = self::summary( $post );
		}
		return array(
			'items' => $items,
			'total' => (int) $query->found_posts,
		);
	}

	/**
	 * What a reviewer needs to see about one question.
	 *
	 * @param \WP_Post $post Question post.
	 * @return array
	 */
	public static function summary( \WP_Post $post ) {
		$id         = (int) $post->ID;
		$attributes = BankAttributes::get( $id );
		$map        = SkillMap::current( $id );
		$skills     = array();
		foreach ( $map as $part => $roles ) {
			if ( ! empty( $roles['primary'] ) ) {
				$skills[] = array(
					'term_id' => (int) $roles['primary'],
					'role'    => 'primary',
					'part'    => $part,
				);
			}
			foreach ( (array) ( $roles['supporting'] ?? array() ) as $term_id ) {
				$skills[] = array(
					'term_id' => (int) $term_id,
					'role'    => 'supporting',
					'part'    => $part,
				);
			}
		}
		$described = array();
		foreach ( SkillCatalogue::describe( array_column( $skills, 'term_id' ) ) as $skill ) {
			$described[ $skill['id'] ] = $skill;
		}
		foreach ( $skills as &$skill ) {
			$found         = $described[ $skill['term_id'] ] ?? array();
			$skill['code'] = $found['code'] ?? '';
			$skill['name'] = $found['name'] ?? '';
		}
		unset( $skill );
		$data     = (array) get_post_meta( $id, self::DATA_META, true );
		$question = ohmylms_get_question( $id );
		$settings = $question ? (array) $question->get_settings() : array();
		return array(
			'id'                 => $id,
			'name'               => get_the_title( $id ),
			'text'               => mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $post->post_content ) ) ), 0, 400 ),
			'type'               => $settings['type'] ?? '',
			'source'             => $attributes['source'] ?? '',
			'difficulty'         => $attributes['difficulty'] ?? '',
			'family_id'          => $attributes['family_id'] ?? '',
			'bank_id'            => (int) ( $attributes['bank_id'] ?? 0 ),
			'skills'             => $skills,
			'confidence'         => $data['confidence'] ?? null,
			'notes'              => $data['notes'] ?? '',
			'flagged_at'         => $data['flagged_at'] ?? '',
			'approved_version'   => (int) ( $attributes['approved_version_id'] ?? 0 ),
			'current_version'    => (int) ( $attributes['current_version_id'] ?? 0 ),
			'approved_is_latest' => ! empty( $attributes['approved_version_id'] ) && (int) ( $attributes['current_version_id'] ?? 0 ) === (int) $attributes['approved_version_id'],
		);
	}

	/**
	 * Approve or dismiss flagged questions.
	 *
	 * "approve" approves the current version and clears the flag. "dismiss" clears the flag
	 * without approving, for questions a person has fixed or wants to keep as drafts.
	 *
	 * @param int[]  $ids Question IDs.
	 * @param string $decision approve or dismiss.
	 * @return array[] One result per ID: id, status, message.
	 */
	public static function decide( array $ids, $decision ) {
		$results = array();
		foreach ( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) as $id ) {
			$result = array(
				'id'      => $id,
				'status'  => 'skipped',
				'message' => '',
			);
			if ( OHMYLMS_QUESTION_CPT !== get_post_type( $id ) || self::PENDING !== get_post_meta( $id, self::STATUS_META, true ) ) {
				$result['message'] = __( 'This question is not waiting for review.', 'ohmylms' );
			} elseif ( ! AccessPolicy::can_approve_question( $id ) ) {
				$result['message'] = __( 'You cannot approve this question.', 'ohmylms' );
			} elseif ( 'approve' === $decision ) {
				$approved = self::approve( $id );
				if ( true === $approved ) {
					self::clear( $id, 'approved' );
					$result['status'] = 'approved';
				} else {
					$result['message'] = $approved;
				}
			} else {
				self::clear( $id, 'dismissed' );
				$result['status'] = 'dismissed';
			}
			$results[] = $result;
		}
		return $results;
	}

	/**
	 * Approve a question's current version through the normal approval route.
	 *
	 * @param int $question_id Question post ID.
	 * @return true|string True, or an error message.
	 */
	public static function approve( $question_id ) {
		$response = rest_do_request( new \WP_REST_Request( 'POST', '/ohmylms/v1/questions/' . (int) $question_id . '/approve' ) );
		return $response->is_error() ? $response->as_error()->get_error_message() : true;
	}

	/**
	 * Close a review, keeping a record of how it ended.
	 *
	 * @param int    $question_id Question post ID.
	 * @param string $status approved or dismissed.
	 */
	private static function clear( $question_id, $status ) {
		update_post_meta( $question_id, self::STATUS_META, $status );
		$data                = (array) get_post_meta( $question_id, self::DATA_META, true );
		$data['reviewed_at'] = gmdate( 'Y-m-d H:i:s' );
		$data['reviewed_by'] = get_current_user_id();
		update_post_meta( $question_id, self::DATA_META, $data );
	}
}
