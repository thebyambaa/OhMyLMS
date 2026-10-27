<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the plugin PSR-4 class mapping; see docs/WORDWALL-ACTIVITIES.md.
/**
 * Timed image reveal over frozen single-choice questions.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** Visual practice points never produce academic grade events. */
final class ImageActivity {

	/**
	 * Require a primary image and a bounded, unambiguous choice definition.
	 *
	 * @param object $snapshot Immutable question.
	 * @return bool Whether the content can be played.
	 */
	public static function compatible( $snapshot ) {
		$options = $snapshot->get_questions();
		if ( 'single-choice' !== $snapshot->get_type() || ! $snapshot->get_image_url() || count( $options ) < 2 || count( $options ) > 6 || 1 !== count( $snapshot->get_correct_options() ) || $snapshot->is_template() ) {
			return false;
		}
		foreach ( $options as $option ) {
			if ( '' === RecallActivity::label( $option['answer'] ) && empty( $option['image_url'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Calculate reveal progress from the server clock, never client points.
	 *
	 * @param array $state Saved round.
	 * @param int   $seconds Full reveal duration.
	 * @return int Visible tiles out of 36.
	 */
	private static function revealed( array $state, $seconds ) {
		return 'reveal' === ( $state['phase'] ?? 'ready' ) ? min( 36, max( 0, (int) floor( ( time() - $state['started'] ) * 36 / $seconds ) ) ) : (int) ( $state['revealed'] ?? 0 );
	}

	/**
	 * Project the current round without disclosing its answer key.
	 *
	 * @param array $session Owned session.
	 * @param array $items Frozen items.
	 * @return array Safe learner state.
	 */
	public static function view( array $session, array $items ) {
		$state    = $session['policy']['activity_state'] ?? array();
		$index    = (int) ( $state['index'] ?? 0 );
		$item     = $items[ $index ];
		$snapshot = AttemptItems::snapshot( $item );
		$phase    = $state['phase'] ?? 'ready';
		$seconds  = $session['policy']['reveal_seconds'];
		$view     = array(
			'session'       => $session['uuid'],
			'status'        => $session['status'],
			'preview'       => (bool) $session['policy']['preview'],
			'activityMode'  => 'image-quiz',
			'revision'      => (int) ( $state['revision'] ?? 0 ),
			'phase'         => $phase,
			'round'         => $index + 1,
			'total'         => count( $items ),
			'prompt'        => RecallActivity::label( $snapshot->get_name() ),
			'image'         => $snapshot->get_image_url(),
			'revealed'      => self::revealed( $state, $seconds ),
			'revealSeconds' => $seconds,
			'elapsed'       => 'reveal' === $phase ? max( 0, time() - $state['started'] ) : 0,
			'points'        => (int) ( $state['points'] ?? 0 ),
			'graded'        => false,
			'affectsSkills' => false,
		);
		if ( in_array( $phase, array( 'answer', 'review' ), true ) ) {
			$choices = array();
			foreach ( $snapshot->get_questions() as $option ) {
				$choice = array(
					'id'    => AttemptItems::token( 'image:' . $session['uuid'] . ':' . $item['id'], $option['id'] ),
					'text'  => RecallActivity::label( $option['answer'] ),
					'image' => (string) ( $option['image_url'] ?? '' ),
				);
				if ( 'review' === $phase ) {
					$choice['correct']  = ! empty( $option['is_correct'] );
					$choice['selected'] = ( $state['selected'] ?? '' ) === $choice['id'];
				}
				$choices[] = $choice;
			}
			$view['choices'] = Interactive::shuffled( $choices, hash_hmac( 'sha256', $session['uuid'] . ':image:' . $index, wp_salt( 'auth' ) ) );
		}
		if ( 'review' === $phase ) {
			$view['correct']     = ! empty( $state['correct'] );
			$view['roundPoints'] = (int) $state['round_points'];
			$view['skipped']     = ! empty( $state['skipped'] );
		}
		return $view;
	}

	/**
	 * Persist a revision-checked reveal, buzzer or answer under a row lock.
	 *
	 * @param array $session Authorized session.
	 * @param array $items Frozen items.
	 * @param array $action Command, option token and revision.
	 * @return array|\WP_Error Safe saved progress.
	 */
	public static function act( array $session, array $items, array $action ) {
		global $wpdb;
		$command = $action['card'] ?? '';
		$value   = $action['value'] ?? '';
		if ( ! in_array( $command, array( 'begin', 'buzz', 'answer', 'next', 'skip' ), true ) || ! is_string( $value ) || strlen( $value ) > 100 || ! isset( $action['revision'] ) || ! is_scalar( $action['revision'] ) || ! ctype_digit( (string) $action['revision'] ) ) {
			return QuestionPlayer::error( __( 'Choose a valid image activity action.', 'ohmylms' ) );
		}
		try {
			Transaction::run(
				static function () use ( $wpdb, $session, $items, $action, $command, $value ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock private progress to prevent duplicate points and timer resets.
					$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT policy, status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'practice_sessions' ), (int) $session['id'] ), ARRAY_A );
					$policy = json_decode( $row['policy'], true );
					$state  = $policy['activity_state'] ?? array(
						'revision' => 0,
						'index'    => 0,
						'phase'    => 'ready',
						'points'   => 0,
					);
					if ( 'active' !== $row['status'] || (int) $action['revision'] !== $state['revision'] ) {
						return;
					}
					$phase = $state['phase'];
					if ( 'begin' === $command && 'ready' === $phase ) {
						$state['started'] = time();
						$state['phase']   = 'reveal';
					} elseif ( 'buzz' === $command && 'reveal' === $phase ) {
						$state['revealed'] = self::revealed( $state, $policy['reveal_seconds'] );
						$state['phase']    = 'answer';
					} elseif ( ( 'answer' === $command && 'answer' === $phase ) || ( 'skip' === $command && in_array( $phase, array( 'ready', 'reveal', 'answer' ), true ) ) ) {
						$item     = $items[ $state['index'] ];
						$snapshot = AttemptItems::snapshot( $item );
						$selected = null;
						foreach ( $snapshot->get_questions() as $option ) {
							if ( AttemptItems::token( 'image:' . $session['uuid'] . ':' . $item['id'], $option['id'] ) === $value ) {
								$selected = $option;
							}
						}
						if ( 'answer' === $command && null === $selected ) {
							return;
						}
						$state['correct']      = 'answer' === $command && ! empty( $selected['is_correct'] );
						$state['selected']     = 'answer' === $command ? $value : '';
						$state['skipped']      = 'skip' === $command;
						$state['round_points'] = $state['correct'] ? max( 1, 36 - $state['revealed'] ) : 0;
						$state['points']      += $state['round_points'];
						$state['phase']        = 'review';
					} elseif ( 'next' === $command && 'review' === $phase && count( $items ) > $state['index'] + 1 ) {
						$state = array(
							'revision' => $state['revision'],
							'index'    => $state['index'] + 1,
							'phase'    => 'ready',
							'points'   => $state['points'],
						);
					} else {
						return;
					}
					++$state['revision'];
					$policy['activity_state'] = $state;
					$complete                 = 'review' === $state['phase'] && count( $items ) === $state['index'] + 1;
					$update                   = array(
						'policy' => wp_json_encode( $policy ),
						'status' => $complete ? 'complete' : 'active',
					);
					if ( $complete ) {
						$update['completed_at'] = current_time( 'mysql', true );
					}
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Save practice state without academic marks or skill evidence.
					if ( false === $wpdb->update( Schema::table( 'practice_sessions' ), $update, array( 'id' => (int) $session['id'] ) ) ) {
						throw new \RuntimeException( 'Image progress save failed.' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return QuestionPlayer::error( __( 'Could not save image quiz progress. Please retry.', 'ohmylms' ), 500 );
		}
		return self::view( Sessions::get( $session['uuid'] ), $items );
	}
}
