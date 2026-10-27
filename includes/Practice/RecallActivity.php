<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the plugin PSR-4 class mapping; see docs/WORDWALL-ACTIVITIES.md.
/**
 * Server-timed observation and recall over frozen matching content.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Practice feedback is separate from academic marks and skill evidence. */
final class RecallActivity {
	/**
	 * Collapse formatting into the visible text used to detect duplicates.
	 *
	 * @param string $text Source text.
	 * @return string Visible label.
	 */
	public static function label( $text ) {
		return trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	}

	/**
	 * Reuse matching fronts as a text item pool, with session-scoped opaque IDs.
	 *
	 * @param array $session Owned session.
	 * @param array $items Frozen items.
	 * @return array Shuffled private pool.
	 */
	private static function pool( array $session, array $items ) {
		$pool = array();
		foreach ( StudyActivity::deck( $items, $session['uuid'] ) as $id => $card ) {
			if ( 'front' === $card['side'] ) {
				$pool[] = array(
					'id'   => $id,
					'text' => self::label( $card['text'] ),
				);
			}
		}
		return Interactive::shuffled( $pool, hash_hmac( 'sha256', $session['uuid'] . ':recall-targets', wp_salt( 'auth' ) ) );
	}

	/**
	 * Expired study sessions always project recall, even before another write.
	 *
	 * @param array $state Saved phase.
	 * @return string Effective phase.
	 */
	private static function phase( array $state ) {
		$phase = $state['phase'] ?? 'ready';
		return 'study' === $phase && time() >= $state['until'] ? 'recall' : $phase;
	}

	/**
	 * Reveal only the data appropriate to the current phase.
	 *
	 * @param array $session Owned session.
	 * @param array $items Frozen items.
	 * @return array Safe learner view.
	 */
	public static function view( array $session, array $items ) {
		$state      = $session['policy']['activity_state'] ?? array();
		$phase      = self::phase( $state );
		$pool       = self::pool( $session, $items );
		$count      = $session['policy']['remember_count'];
		$targets    = array_slice( $pool, 0, $count );
		$target_ids = array_column( $targets, 'id' );
		$view       = array(
			'session'       => $session['uuid'],
			'status'        => $session['status'],
			'activityMode'  => 'memory-recall',
			'phase'         => $phase,
			'preview'       => (bool) $session['policy']['preview'],
			'revision'      => (int) ( $state['revision'] ?? 0 ),
			'rememberCount' => $count,
			'studySeconds'  => $session['policy']['study_seconds'],
			'graded'        => false,
			'affectsSkills' => false,
		);
		if ( 'study' === $phase ) {
			$view['studyItems']       = $targets;
			$view['remainingSeconds'] = max( 0, $state['until'] - time() );
		} elseif ( in_array( $phase, array( 'recall', 'complete' ), true ) ) {
			$choices = Interactive::shuffled( $pool, hash_hmac( 'sha256', $session['uuid'] . ':recall-choices', wp_salt( 'auth' ) ) );
			foreach ( $choices as &$choice ) {
				$choice['selected'] = in_array( $choice['id'], $state['selected'] ?? array(), true );
				if ( 'complete' === $phase ) {
					$choice['wasShown'] = in_array( $choice['id'], $target_ids, true );
				}
			}
			unset( $choice );
			$view['choices'] = $choices;
		}
		if ( 'complete' === $phase ) {
			$selected             = $state['selected'];
			$hits                 = count( array_intersect( $target_ids, $selected ) );
			$wrong                = count( array_diff( $selected, $target_ids ) );
			$view['recallResult'] = array(
				'remembered' => $hits,
				'missed'     => $count - $hits,
				'wrong'      => $wrong,
				'maximum'    => $count,
				'fraction'   => max( 0, $hits - $wrong ) / $count,
			);
		}
		return $view;
	}

	/**
	 * Save one revision-checked phase change or selection under a session lock.
	 *
	 * @param array $session Authorized session.
	 * @param array $items Frozen items.
	 * @param array $action Command, choice ID and revision.
	 * @return array|\WP_Error Safe progress.
	 */
	public static function act( array $session, array $items, array $action ) {
		global $wpdb;
		$command = $action['card'] ?? '';
		$value   = $action['value'] ?? '';
		$ids     = array_column( self::pool( $session, $items ), 'id' );
		if ( ! in_array( $command, array( 'begin', 'recall', 'toggle', 'finish' ), true ) || ! isset( $action['revision'] ) || ! is_scalar( $action['revision'] ) || ! ctype_digit( (string) $action['revision'] ) || ( 'toggle' === $command && ( ! is_string( $value ) || ! in_array( $value, $ids, true ) ) ) ) {
			return QuestionPlayer::error( __( 'Choose a valid memory activity action.', 'ohmylms' ) );
		}
		try {
			Transaction::run(
				static function () use ( $wpdb, $session, $action, $command, $value ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Serialize progress and timer changes in the private session transaction.
					$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT policy, status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'practice_sessions' ), (int) $session['id'] ), ARRAY_A );
					$policy = json_decode( $row['policy'], true );
					$state  = $policy['activity_state'] ?? array(
						'revision' => 0,
						'phase'    => 'ready',
						'selected' => array(),
					);
					if ( 'active' !== $row['status'] || (int) $action['revision'] !== $state['revision'] ) {
						return;
					}
					$phase = self::phase( $state );
					if ( 'begin' === $command && 'ready' === $phase ) {
						$state['phase'] = 'study';
						$state['until'] = time() + $policy['study_seconds'];
					} elseif ( 'recall' === $command && in_array( $phase, array( 'study', 'recall' ), true ) ) {
						$state['phase'] = 'recall';
						$state['until'] = min( time(), $state['until'] );
					} elseif ( 'toggle' === $command && 'recall' === $phase ) {
						$state['phase'] = 'recall';
						if ( in_array( $value, $state['selected'], true ) ) {
							$state['selected'] = array_values( array_diff( $state['selected'], array( $value ) ) );
						} elseif ( count( $state['selected'] ) < $policy['remember_count'] ) {
							$state['selected'][] = $value;
						} else {
							return;
						}
					} elseif ( 'finish' === $command && 'recall' === $phase ) {
						$state['phase'] = 'complete';
					} else {
						return;
					}
					++$state['revision'];
					$policy['activity_state'] = $state;
					$complete                 = 'complete' === $state['phase'];
					$update                   = array(
						'policy' => wp_json_encode( $policy ),
						'status' => $complete ? 'complete' : 'active',
					);
					if ( $complete ) {
						$update['completed_at'] = current_time( 'mysql', true );
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Save practice progress without writing grade events or skill evidence.
					if ( false === $wpdb->update( Schema::table( 'practice_sessions' ), $update, array( 'id' => (int) $session['id'] ) ) ) {
						throw new \RuntimeException( 'Recall progress save failed.' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return QuestionPlayer::error( __( 'Could not save memory progress. Please retry.', 'ohmylms' ), 500 );
		}
		return self::view( Sessions::get( $session['uuid'] ), $items );
	}
}
