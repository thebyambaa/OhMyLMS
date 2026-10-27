<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the plugin PSR-4 class mapping; see docs/WORDWALL-ACTIVITIES.md.
/**
 * Server-owned word practice over immutable question versions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\TextEntry;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Answers and unguessed letters remain on the server until the round ends. */
final class WordActivity {

	/**
	 * Extract bounded answers from compatible content.
	 *
	 * @param object $snapshot Immutable question.
	 * @return array Accepted answers or an empty incompatible definition.
	 */
	public static function answers( $snapshot ) {
		$settings = $snapshot->get_settings();
		if ( 'text-entry' === $snapshot->get_type() ) {
			$answers = $settings['accepted'] ?? array();
		} elseif ( 'build-expression' === $snapshot->get_type() && 'letters' === ( $settings['tile_mode'] ?? '' ) ) {
			$answers = array( implode( '', $settings['correct'] ?? array() ) );
		} else {
			return array();
		}
		foreach ( $answers as $answer ) {
			if ( ! is_string( $answer ) || ! preg_match( '/\p{L}/u', $answer ) || ! preg_match_all( '/\X/u', $answer, $letters ) || count( $letters[0] ) > 64 ) {
				return array();
			}
		}
		return $answers;
	}

	/**
	 * Read the current frozen round.
	 *
	 * @param array $items Session items.
	 * @param array $state Saved progress.
	 * @return object|null Snapshot.
	 */
	private static function current( array $items, array $state ) {
		$item = $items[ (int) ( $state['index'] ?? 0 ) ] ?? null;
		return $item ? AttemptItems::snapshot( $item ) : null;
	}

	/**
	 * Project only revealed letters, without private keys.
	 *
	 * @param array $session Owned session.
	 * @param array $items Frozen items.
	 * @return array Learner state.
	 */
	public static function view( array $session, array $items ) {
		$state    = $session['policy']['activity_state'] ?? array();
		$snapshot = self::current( $items, $state );
		$answers  = $snapshot ? self::answers( $snapshot ) : array();
		$target   = $answers[0] ?? '';
		preg_match_all( '/\X/u', $target, $segments );
		$guessed = $state['guessed'] ?? array();
		$done    = ! empty( $state['done'] );
		$letters = array();
		foreach ( $segments[0] as $letter ) {
			$letters[] = $done || ! preg_match( '/\p{L}/u', $letter ) || in_array( mb_strtolower( $letter, 'UTF-8' ), $guessed, true ) ? $letter : null;
		}
		$result = array(
			'session'       => $session['uuid'],
			'status'        => $session['status'],
			'preview'       => (bool) $session['policy']['preview'],
			'activityMode'  => $session['policy']['activity_mode'],
			'revision'      => (int) ( $state['revision'] ?? 0 ),
			'round'         => (int) ( $state['index'] ?? 0 ) + 1,
			'total'         => count( $items ),
			'clue'          => $snapshot ? wp_strip_all_tags( $snapshot->get_description() ? $snapshot->get_description() : $snapshot->get_name() ) : '',
			'letters'       => 'hangman' === $session['policy']['activity_mode'] ? $letters : array(),
			'guessed'       => $guessed,
			'mistakes'      => (int) ( $state['mistakes'] ?? 0 ),
			'limit'         => 6,
			'done'          => $done,
			'solved'        => ! empty( $state['solved'] ),
			'graded'        => false,
			'affectsSkills' => false,
		);
		if ( $done ) {
			$result['answer'] = $target;
		}
		return $result;
	}

	/**
	 * Apply one bounded, revision-checked word action under a session lock.
	 *
	 * @param array $session Authorized session.
	 * @param array $items Frozen items.
	 * @param array $action Card command, value and revision.
	 * @return array|\WP_Error Learner state.
	 */
	public static function act( array $session, array $items, array $action ) {
		global $wpdb;
		$command = $action['card'] ?? '';
		$value   = $action['value'] ?? '';
		if ( ! in_array( $command, array( 'guess', 'next' ), true ) || ! is_string( $value ) || mb_strlen( $value ) > 2000 || ! isset( $action['revision'] ) || ! is_scalar( $action['revision'] ) || ! ctype_digit( (string) $action['revision'] ) ) {
			return QuestionPlayer::error( __( 'Enter a valid word action.', 'ohmylms' ) );
		}
		if ( 'guess' === $command && ( '' === trim( $value ) || ( 'hangman' === $session['policy']['activity_mode'] && ! preg_match( '/\A\p{L}\p{M}*\z/u', $value ) ) ) ) {
			return QuestionPlayer::error( __( 'Enter one letter for Hangman, or a word for spelling.', 'ohmylms' ) );
		}
		try {
			Transaction::run(
				static function () use ( $wpdb, $session, $items, $action, $command, $value ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize progress in the private session transaction.
					$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT policy, status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'practice_sessions' ), (int) $session['id'] ), ARRAY_A );
					$policy = json_decode( $row['policy'], true );
					$state  = $policy['activity_state'] ?? array(
						'revision' => 0,
						'index'    => 0,
						'guessed'  => array(),
						'mistakes' => 0,
						'done'     => false,
						'solved'   => false,
					);
					if ( 'active' !== $row['status'] || (int) $action['revision'] !== $state['revision'] ) {
						return;
					}
					if ( 'next' === $command ) {
						if ( ! $state['done'] ) {
							return;
						}
						++$state['index'];
						$state['guessed']  = array();
						$state['mistakes'] = 0;
						$state['done']     = false;
						$state['solved']   = false;
					} else {
						if ( $state['done'] ) {
							return;
						}
						$snapshot = self::current( $items, $state );
						$answers  = self::answers( $snapshot );
						if ( 'hangman' === $policy['activity_mode'] ) {
							$guess = mb_strtolower( $value, 'UTF-8' );
							if ( in_array( $guess, $state['guessed'], true ) ) {
								return;
							}
							$state['guessed'][] = $guess;
							preg_match_all( '/\X/u', mb_strtolower( $answers[0], 'UTF-8' ), $segments );
							if ( ! in_array( $guess, $segments[0], true ) ) {
								++$state['mistakes'];
							}
							$remaining       = array_filter(
								$segments[0],
								static function ( $letter ) use ( $state ) {
									return preg_match( '/\p{L}/u', $letter ) && ! in_array( $letter, $state['guessed'], true );
								}
							);
							$state['solved'] = ! $remaining;
						} else {
							$settings             = $snapshot->get_settings();
							$settings['accepted'] = $answers;
							$state['solved']      = TextEntry::grade( array( $value ), $settings )['correct'];
							if ( ! $state['solved'] ) {
								++$state['mistakes'];
							}
						}
						$state['done'] = $state['solved'] || 6 <= $state['mistakes'];
					}
					++$state['revision'];
					$policy['activity_state'] = $state;
					$complete                 = $state['done'] && count( $items ) - 1 === $state['index'];
					$update                   = array(
						'policy' => wp_json_encode( $policy ),
						'status' => $complete ? 'complete' : 'active',
					);
					if ( $complete ) {
						$update['completed_at'] = current_time( 'mysql', true );
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Persist locked practice progress without academic grade events.
					if ( false === $wpdb->update( Schema::table( 'practice_sessions' ), $update, array( 'id' => (int) $session['id'] ) ) ) {
						throw new \RuntimeException( 'Word progress save failed.' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return QuestionPlayer::error( __( 'Could not save word progress. Please retry.', 'ohmylms' ), 500 );
		}
		return self::view( Sessions::get( $session['uuid'] ), $items );
	}
}
