<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Where a bank question may be delivered, decided in one place.
 *
 * One question can reach a learner three ways: as a slot in a formal quiz or exam attempt, as an inline
 * check inside a lesson, or in a practice run of the question player. The quiz slot uses the question as
 * authored; the other two serve its frozen approved version and grade it automatically, so they refuse
 * exam-only (secure) questions, unapproved versions and questions a teacher must mark.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

use OhMyLMS\Extensions\Registry;
use OhMyLMS\QuestionBank\Usage;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/** The single authority on which questions each delivery accepts. */
final class DeliveryPolicy {
	/** A slot in a formal quiz or exam attempt. */
	const QUIZ = 'quiz';
	/** A one-question check inside lesson content. */
	const INLINE = 'inline';
	/** A run of the question player (practice, activities, games). */
	const PRACTICE = 'practice';
	/** Every delivery. */
	const ALL = array( self::QUIZ, self::INLINE, self::PRACTICE );

	/**
	 * Identity row of a question that exists and is neither trashed nor archived.
	 *
	 * @param int $question_id Question ID.
	 * @return array|null
	 */
	public static function usable( $question_id ) {
		$question_id = (int) $question_id;
		if ( OHMYLMS_QUESTION_CPT !== get_post_type( $question_id ) || in_array( get_post_status( $question_id ), array( 'trash', 'auto-draft', Usage::ARCHIVED ), true ) ) {
			return null;
		}
		return VersionPublisher::identity( $question_id, false );
	}

	/**
	 * Whether a question may be placed in a delivery. Authoring-time check; for the learner-facing
	 * deliveries it asks for the same snapshot the learner would be served.
	 *
	 * @param int    $question_id Question ID.
	 * @param string $delivery quiz, inline or practice.
	 * @return bool
	 */
	public static function allows( $question_id, $delivery ) {
		if ( self::QUIZ === $delivery ) {
			return (bool) self::usable( $question_id );
		}
		return (bool) self::snapshot( $question_id, $delivery );
	}

	/**
	 * The frozen version an inline check or practice run serves, or null when the question is not allowed
	 * there. Editors of a question see an unapproved current version inline as a preview.
	 *
	 * @param int       $question_id Question ID.
	 * @param string    $delivery inline or practice.
	 * @param bool|null $preview Set true when the version served is an unapproved preview.
	 * @return QuestionSnapshot|null
	 */
	public static function snapshot( $question_id, $delivery, &$preview = null ) {
		$preview  = false;
		$identity = in_array( $delivery, array( self::INLINE, self::PRACTICE ), true ) ? self::usable( $question_id ) : null;
		if ( ! $identity || ! empty( $identity['secure'] ) ) {
			return null;
		}
		$version_id = (int) $identity['approved_version_id'];
		if ( ! $version_id && self::INLINE === $delivery && current_user_can( 'edit_post', (int) $question_id ) ) {
			$version_id = (int) $identity['current_version_id'];
			$preview    = true;
		}
		$snapshot = $version_id ? VersionPublisher::snapshot( $version_id ) : null;
		if ( ! $snapshot ) {
			$preview = false;
			return null;
		}
		$definition = Registry::get( 'question', $snapshot->get_type() );
		$manual     = in_array( $snapshot->get_type(), array( 'structured', 'passage' ), true ) && Structured::has_manual( $snapshot->get_settings() );
		if ( ! $definition || empty( $definition['snapshot'] ) || ! empty( $definition['manual'] ) || $manual ) {
			$preview = false;
			return null;
		}
		return $snapshot;
	}

	/**
	 * Practice sets and inline checks that already use these questions, found in page, post and lesson
	 * content. Publishing an exam marks its questions exam-only, which removes them from those places.
	 *
	 * @param int[] $question_ids Question IDs.
	 * @return array[] Uses keyed by question ID; each use has post_id, title and delivery.
	 */
	public static function other_uses( array $question_ids ) {
		global $wpdb;
		$ids  = array_values( array_unique( array_filter( array_map( 'intval', $question_ids ) ) ) );
		$uses = array_fill_keys( $ids, array() );
		if ( ! $ids ) {
			return $uses;
		}
		$uuids = array();
		foreach ( $ids as $id ) {
			$identity = VersionPublisher::identity( $id, false );
			if ( $identity ) {
				$uuids[ strtolower( (string) $identity['uuid'] ) ] = $id;
			}
		}
		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status NOT IN ('trash', 'auto-draft') AND post_type NOT IN (%s, 'revision') AND (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s) LIMIT 500", OHMYLMS_QUESTION_CPT, '%wp:ohmylms/question-player%', '%wp:ohmylms/shared-multiple-choice%', '%[ohmylms_question%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only scan at publish time.
		foreach ( (array) $posts as $post ) {
			$found = array();
			$stack = parse_blocks( $post['post_content'] );
			while ( $stack ) {
				$block = array_shift( $stack );
				$stack = array_merge( $stack, (array) ( $block['innerBlocks'] ?? array() ) );
				$attrs = (array) ( $block['attrs'] ?? array() );
				if ( 'ohmylms/question-player' === ( $block['blockName'] ?? '' ) ) {
					foreach ( (array) ( $attrs['questionIds'] ?? array() ) as $question_id ) {
						$found[ (int) $question_id ][ self::PRACTICE ] = true;
					}
				} elseif ( 'ohmylms/shared-multiple-choice' === ( $block['blockName'] ?? '' ) && self::QUIZ !== ( $attrs['delivery'] ?? 'inline' ) ) {
					$found[ (int) ( $attrs['questionId'] ?? 0 ) ][ self::INLINE ] = true;
				}
			}
			if ( false !== strpos( $post['post_content'], '[ohmylms_question' ) && preg_match_all( '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $post['post_content'], $matches ) ) {
				foreach ( $matches[0] as $uuid ) {
					if ( isset( $uuids[ strtolower( $uuid ) ] ) ) {
						$found[ $uuids[ strtolower( $uuid ) ] ][ self::INLINE ] = true;
					}
				}
			}
			foreach ( $found as $question_id => $deliveries ) {
				if ( ! isset( $uses[ $question_id ] ) ) {
					continue;
				}
				foreach ( array_keys( $deliveries ) as $delivery ) {
					$uses[ $question_id ][] = array(
						'post_id'  => (int) $post['ID'],
						'title'    => '' !== (string) $post['post_title'] ? (string) $post['post_title'] : (string) $post['ID'],
						'delivery' => $delivery,
					);
				}
			}
		}
		return $uses;
	}
}
