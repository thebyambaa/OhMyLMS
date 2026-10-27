<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the plugin PSR-4 class mapping; see docs/WORDWALL-ACTIVITIES.md.
/**
 * Study modes over frozen matching questions, without academic grade events.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Private pairs are projected only when the learner reveals a card. */
final class StudyActivity {
	/**
	 * Derive stable opaque card identities from frozen question options.
	 *
	 * @param array  $items Frozen session items.
	 * @param string $uuid Session identifier.
	 * @return array Private deck.
	 */
	public static function deck( array $items, $uuid ) {
		$deck = array();
		foreach ( $items as $item ) {
			$snapshot = AttemptItems::snapshot( $item );
			if ( ! $snapshot || 'matching' !== $snapshot->get_type() ) {
				continue;
			}
			foreach ( $snapshot->get_questions() as $index => $option ) {
				$pair = $item['id'] . ':' . $index;
				foreach ( array(
					'front' => (string) $option['answer'],
					'back'  => (string) ( $option['matching_data']['label'] ?? '' ),
				) as $side => $text ) {
					$id          = substr( hash_hmac( 'sha256', $uuid . ':' . $pair . ':' . $side, wp_salt( 'auth' ) ), 0, 32 );
					$deck[ $id ] = array(
						'pair' => $pair,
						'side' => $side,
						'text' => $text,
					);
				}
			}
		}
		return $deck;
	}

	/**
	 * Public state never includes the hidden matching map or card backs.
	 *
	 * @param array $session Owned frozen session.
	 * @param array $items Frozen items.
	 * @return array Learner state.
	 */
	public static function view( array $session, array $items ) {
		$mode  = $session['policy']['activity_mode'];
		$state = $session['policy']['activity_state'] ?? array();
		$deck  = self::deck( $items, $session['uuid'] );
		$cards = array();
		foreach ( $deck as $id => $card ) {
			if ( 'flashcards' === $mode && 'back' === $card['side'] ) {
				continue;
			}
			$visible = in_array( $id, $state['open'] ?? array(), true ) || in_array( $id, $state['matched'] ?? array(), true );
			$entry   = array(
				'id'       => $id,
				'text'     => $visible || 'flashcards' === $mode ? $card['text'] : null,
				'revealed' => $visible,
				'matched'  => in_array( $id, $state['matched'] ?? array(), true ),
			);
			if ( 'flashcards' === $mode && in_array( $id, $state['seen'] ?? array(), true ) ) {
				foreach ( $deck as $back ) {
					if ( $back['pair'] === $card['pair'] && 'back' === $back['side'] ) {
						$entry['answer'] = $back['text'];
					}
				}
			}
			$cards[] = $entry;
		}
		return array(
			'session'       => $session['uuid'],
			'status'        => $session['status'],
			'preview'       => (bool) $session['policy']['preview'],
			'activityMode'  => $mode,
			'revision'      => (int) ( $state['revision'] ?? 0 ),
			'turns'         => (int) ( $state['turns'] ?? 0 ),
			'cards'         => Interactive::shuffled( $cards, $session['uuid'] ),
			'graded'        => false,
			'affectsSkills' => false,
		);
	}

	/**
	 * Apply one revision-checked reveal, serializing concurrent tabs.
	 *
	 * @param array $session Authorized session.
	 * @param array $items Frozen items.
	 * @param array $action Card ID and expected revision.
	 * @return array|\WP_Error Current state.
	 */
	public static function act( array $session, array $items, array $action ) {
		global $wpdb;
		$deck = self::deck( $items, $session['uuid'] );
		$id   = $action['card'] ?? '';
		if ( ! is_string( $id ) || ! isset( $deck[ $id ] ) || ! isset( $action['revision'] ) || ! is_scalar( $action['revision'] ) || ! ctype_digit( (string) $action['revision'] ) ) {
			return QuestionPlayer::error( __( 'Choose a valid study card.', 'ohmylms' ) );
		}
		try {
			Transaction::run(
				static function () use ( $wpdb, $session, $action, $deck, $id ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize study reveals against the current session policy.
					$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT policy, status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'practice_sessions' ), (int) $session['id'] ), ARRAY_A );
					$policy = json_decode( $row['policy'], true );
					$state  = $policy['activity_state'] ?? array(
						'revision' => 0,
						'open'     => array(),
						'matched'  => array(),
						'seen'     => array(),
						'turns'    => 0,
					);
					if ( 'active' !== $row['status'] || (int) $action['revision'] !== $state['revision'] ) {
						return;
					}
					$complete = false;
					if ( 'flashcards' === $policy['activity_mode'] ) {
						if ( 'front' !== $deck[ $id ]['side'] || in_array( $id, $state['seen'], true ) ) {
							return;
						}
						$state['seen'] = array_values( array_unique( array_merge( $state['seen'], array( $id ) ) ) );
						$complete      = count( $state['seen'] ) === count( $deck ) / 2;
					} else {
						if ( in_array( $id, $state['matched'], true ) || in_array( $id, $state['open'], true ) ) {
							return;
						}
						if ( 2 === count( $state['open'] ) ) {
							$state['open'] = array();
						}
						$state['open'][] = $id;
						if ( 2 === count( $state['open'] ) ) {
							++$state['turns'];
							if ( $deck[ $state['open'][0] ]['pair'] === $deck[ $id ]['pair'] ) {
								$state['matched'] = array_merge( $state['matched'], $state['open'] );
							}
						}
						$complete = count( $state['matched'] ) === count( $deck );
					}
					++$state['revision'];
					$policy['activity_state'] = $state;
					$update                   = array(
						'policy' => wp_json_encode( $policy ),
						'status' => $complete ? 'complete' : 'active',
					);
					if ( $complete ) {
						$update['completed_at'] = current_time( 'mysql', true );
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Persist game state inside the locked session transaction; no grades or evidence are written.
					if ( false === $wpdb->update( Schema::table( 'practice_sessions' ), $update, array( 'id' => (int) $session['id'] ) ) ) {
						throw new \RuntimeException( 'Study save failed.' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return QuestionPlayer::error( __( 'Could not save study progress. Please retry.', 'ohmylms' ), 500 );
		}
		return self::view( Sessions::get( $session['uuid'] ), $items );
	}
}
