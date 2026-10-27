<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class mapping.
/**
 * Shared frozen practice state for decks, matching, timed quizzes and sorting.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/** All points are practice points; this handler never emits academic grades. */
final class GameActivity {
	/**
	 * Opaque identity scoped to one game.
	 *
	 * @param string $uuid Session UUID.
	 * @param string $key Private item identity.
	 * @return string Opaque token.
	 */
	private static function token( $uuid, $key ) {
		return substr( hash_hmac( 'sha256', $uuid . ':' . $key, wp_salt( 'auth' ) ), 0, 24 );
	}

	/**
	 * Build the bounded private deck from issued immutable instances.
	 *
	 * @param array $session Authorized session.
	 * @param array $items Frozen practice items.
	 * @return array Private rounds.
	 */
	private static function rounds( array $session, array $items ) {
		$family = ActivityModes::family( $session['policy']['activity_mode'] );
		$rounds = array();
		foreach ( $items as $item ) {
			$q    = AttemptItems::snapshot( $item );
			$s    = $q->get_settings();
			$base = array(
				'prompt' => RecallActivity::label( $q->get_name() ),
				'image'  => $q->get_image_url(),
				'body'   => RecallActivity::label( $q->get_description() ),
			);
			if ( in_array( $family, array( 'deck', 'match' ), true ) ) {
				if ( 'statement' === $q->get_type() ) {
					$base['prompt'] = $base['body'] ? $base['body'] : $base['prompt'];
					$base['body']   = '';
					$rounds[]       = $base + array(
						'id'   => self::token( $session['uuid'], (string) $item['id'] ),
						'back' => $base['body'],
					);
				} else {
					foreach ( $q->get_questions() as $index => $option ) {
						$rounds[] = array(
							'id'     => self::token( $session['uuid'], $item['id'] . ':' . $index ),
							'prompt' => RecallActivity::label( $option['answer'] ),
							'back'   => RecallActivity::label( $option['matching_data']['label'] ),
							'image'  => (string) ( $option['image_url'] ?? '' ),
							'body'   => '',
						);
					}
				}
			} elseif ( 'magnets' === $family ) {
				foreach ( $s['tiles'] as $index => $tile ) {
					$rounds[] = array(
						'id'     => self::token( $session['uuid'], $item['id'] . ':' . $index ),
						'prompt' => $tile,
					);
				}
			} elseif ( 'sort' === $family ) {
				$options = array();
				foreach ( $s['buckets'] as $bucket ) {
					$options[] = array(
						'id'      => self::token( $session['uuid'], $item['id'] . ':bucket:' . $bucket['id'] ),
						'text'    => $bucket['label'],
						'private' => $bucket['id'],
					);
				}
				foreach ( $s['items'] as $entry ) {
					$correct = '';
					foreach ( $options as $option ) {
						if ( $option['private'] === $s['key'][ $entry['id'] ] ) {
							$correct = $option['id'];
						}
					}
					$rounds[] = array(
						'id'      => self::token( $session['uuid'], $item['id'] . ':sort:' . $entry['id'] ),
						'prompt'  => $entry['text'],
						'image'   => $entry['image_url'] ?? '',
						'options' => $options,
						'correct' => $correct,
						'body'    => $base['prompt'],
					);
				}
			} else {
				$options = array();
				$correct = '';
				foreach ( $q->get_questions() as $option ) {
					$id        = self::token( $session['uuid'], $item['id'] . ':option:' . $option['id'] );
					$options[] = array(
						'id'    => $id,
						'text'  => RecallActivity::label( $option['answer'] ),
						'image' => $option['image_url'] ?? '',
					);
					if ( ! empty( $option['is_correct'] ) ) {
						$correct = $id;
					}
				}
				$rounds[] = $base + array(
					'id'      => self::token( $session['uuid'], (string) $item['id'] ),
					'options' => $options,
					'correct' => $correct,
				);
			}
		}
		$seed = self::token( $session['uuid'], 'order' );
		return Interactive::shuffled( $rounds, $seed );
	}

	/**
	 * Build opaque matching choices, or a deliberately true/false pairing.
	 *
	 * @param array  $rounds Private deck.
	 * @param int    $index Current round.
	 * @param string $mode Activity.
	 * @return array Current round with private key.
	 */
	private static function round( array $rounds, $index, $mode ) {
		$current = $rounds[ $index ];
		if ( 'match' !== ActivityModes::family( $mode ) ) {
			return $current;
		}
		if ( 'pair-or-no-pair' === $mode ) {
			$truth_order = Interactive::shuffled( $rounds, wp_salt( 'auth' ) . ':pair-truth' );
			$truth       = array_search( $current['id'], array_column( $truth_order, 'id' ), true ) < (int) ceil( count( $rounds ) / 2 );
			$other       = $current;
			if ( ! $truth ) {
				foreach ( $rounds as $candidate ) {
					if ( $candidate['back'] !== $current['back'] ) {
						$other = $candidate;
						break;
					}
				}
			}
			$current['body']    = $other['back'];
			$current['options'] = array(
				array(
					'id'   => 'pair',
					'text' => __( 'Pair', 'ohmylms' ),
				),
				array(
					'id'   => 'no-pair',
					'text' => __( 'No pair', 'ohmylms' ),
				),
			);
			$current['correct'] = $truth ? 'pair' : 'no-pair';
		} else {
			$current['options'] = array_map(
				static function ( $entry ) {
					return array(
						'id'   => $entry['id'],
						'text' => $entry['back'],
					);
				},
				array_slice( $rounds, $index )
			);
			$current['correct'] = $current['id'];
		}
		return $current;
	}

	/**
	 * Project the allowed phase without private answer fields.
	 *
	 * @param array $session Owned session.
	 * @param array $items Issued items.
	 * @return array Public game state.
	 */
	public static function view( array $session, array $items ) {
		$mode   = $session['policy']['activity_mode'];
		$family = ActivityModes::family( $mode );
		$state  = $session['policy']['activity_state'] ?? array();
		$rounds = self::rounds( $session, $items );
		$index  = (int) ( $state['index'] ?? 0 );
		$view   = array(
			'session'       => $session['uuid'],
			'status'        => $session['status'],
			'activityMode'  => $mode,
			'gameFamily'    => $family,
			'phase'         => $state['phase'] ?? 'ready',
			'revision'      => (int) ( $state['revision'] ?? 0 ),
			'round'         => $index + 1,
			'total'         => count( $rounds ),
			'points'        => $state['points'] ?? ( 'win-or-lose' === $mode ? 100 : 0 ),
			'mistakes'      => (int) ( $state['mistakes'] ?? 0 ),
			'graded'        => false,
			'affectsSkills' => false,
			'preview'       => (bool) $session['policy']['preview'],
		);
		if ( in_array( $family, array( 'deck', 'magnets' ), true ) ) {
			if ( 'magnets' === $family ) {
				$source               = AttemptItems::snapshot( $items[0] );
				$view['instructions'] = RecallActivity::label( $source->get_description() ? $source->get_description() : $source->get_name() );
			}
			$view['cards'] = array();
			foreach ( $rounds as $number => $round ) {
				$open  = in_array( $round['id'], $state['opened'] ?? array(), true );
				$entry = array(
					'id'     => $round['id'],
					'number' => $number + 1,
					'opened' => $open,
				);
				if ( in_array( $mode, array( 'spin-wheel', 'flip-tiles', 'word-magnets' ), true ) || $open ) {
					$entry['text'] = $round['prompt'];
				}
				if ( $open ) {
					$entry['back']  = $round['back'] ?? '';
					$entry['image'] = $round['image'] ?? '';
				}
				$view['cards'][] = $entry;
			}
			$view['selected']  = $state['selected'] ?? '';
			$view['positions'] = $state['positions'] ?? array();
			return $view;
		}
		$current                  = self::round( $rounds, $index, $mode );
		$view['prompt']           = $current['prompt'];
		$view['body']             = $current['body'] ?? '';
		$view['image']            = $current['image'] ?? '';
		$view['remainingSeconds'] = 0;
		if ( 'paused' === $view['phase'] ) {
			$view['remainingSeconds'] = $state['remaining'];
		} elseif ( 'play' === $view['phase'] ) {
			$view['remainingSeconds'] = max( 0, $state['deadline'] - time() );
		}
		$view['stake']    = min( $state['stake'] ?? 10, $view['points'] );
		$view['helpUsed'] = ! empty( $state['help_used'] );
		if ( in_array( $view['phase'], array( 'play', 'review', 'paused' ), true ) ) {
			$options = $current['options'];
			if ( ! empty( $state['help'] ) && 'play' === $view['phase'] ) {
				$wrong   = array_values(
					array_filter(
						$options,
						static function ( $option ) use ( $current ) {
							return $option['id'] !== $current['correct'];
						}
					)
				);
				$options = array_values(
					array_filter(
						$options,
						static function ( $option ) use ( $current, $wrong ) {
							return $option['id'] === $current['correct'] || $option['id'] === $wrong[0]['id'];
						}
					)
				);
			}
			$view['choices'] = array_map(
				static function ( $option ) {
					return array_intersect_key( $option, array_flip( array( 'id', 'text', 'image' ) ) );
				},
				Interactive::shuffled( $options, self::token( $session['uuid'], 'options:' . $index ) )
			);
		}
		if ( 'review' === $view['phase'] ) {
			$view['correctId']   = $current['correct'];
			$view['correct']     = ! empty( $state['correct'] );
			$view['selected']    = $state['selected'] ?? '';
			$view['roundPoints'] = $state['round_points'];
		}
		return $view;
	}

	/**
	 * Apply one bounded, locked command. Client points and elapsed times are ignored.
	 *
	 * @param array $session Authorized session.
	 * @param array $items Frozen items.
	 * @param array $action Command, value and revision.
	 * @return array|\WP_Error Saved view.
	 */
	public static function act( array $session, array $items, array $action ) {
		global $wpdb;
		$command = $action['card'] ?? '';
		$value   = $action['value'] ?? '';
		if ( ! in_array( $command, array( 'begin', 'answer', 'miss', 'timeout', 'next', 'pause', 'resume', 'help', 'stake', 'pick', 'spin', 'flip', 'finish', 'place' ), true ) || ! is_string( $value ) || 200 < strlen( $value ) || ! isset( $action['revision'] ) || ! is_scalar( $action['revision'] ) || ! ctype_digit( (string) $action['revision'] ) ) {
			return QuestionPlayer::error( __( 'Invalid activity action.', 'ohmylms' ) );
		}
		$rounds = self::rounds( $session, $items );
		try {
			Transaction::run(
				static function () use ( $wpdb, $session, $action, $command, $value, $rounds ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock the private session before applying a practice action.
					$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT policy, status FROM %i WHERE id=%d FOR UPDATE', Schema::table( 'practice_sessions' ), (int) $session['id'] ), ARRAY_A );
					$policy = json_decode( $row['policy'], true );
					$mode   = $policy['activity_mode'];
					$family = ActivityModes::family( $mode );
					$state  = $policy['activity_state'] ?? array(
						'revision' => 0,
						'index'    => 0,
						'phase'    => 'ready',
						'points'   => 'win-or-lose' === $mode ? 100 : 0,
						'mistakes' => 0,
					);
					if ( 'active' !== $row['status'] || (int) $action['revision'] !== $state['revision'] ) {
						return;
					}
					$phase    = $state['phase'];
					$complete = false;
					if ( 'deck' === $family ) {
						$ids    = array_column( $rounds, 'id' );
						$opened = $state['opened'] ?? array();
						if ( 'finish' === $command ) {
							$complete = true;
						} elseif ( in_array( $command, array( 'spin', 'pick', 'flip' ), true ) ) {
							if ( 'spin' === $command ) {
								$remaining = array_values( array_diff( $ids, $opened ) );
								if ( ! $remaining ) {
									return;
								}
								$id = $remaining[ random_int( 0, count( $remaining ) - 1 ) ];
							} else {
								$id = $value;
							}
							if ( ! in_array( $id, $ids, true ) ) {
								return;
							}
							$state['selected'] = $id;
							if ( 'flip' === $command && 'flip-tiles' === $mode && in_array( $id, $opened, true ) ) {
								$opened = array_values( array_diff( $opened, array( $id ) ) );
							} elseif ( ! in_array( $id, $opened, true ) ) {
								$opened[] = $id;
							}
							$state['opened'] = $opened;
							$state['phase']  = 'play';
						} else {
							return;
						}
					} elseif ( 'magnets' === $family ) {
						if ( 'finish' === $command ) {
							$complete = true;
						} elseif ( 'place' === $command ) {
							$parts = explode( '|', $value );
							if ( 3 !== count( $parts ) || ! in_array( $parts[0], array_column( $rounds, 'id' ), true ) || ! is_numeric( $parts[1] ) || ! is_numeric( $parts[2] ) || ! is_finite( (float) $parts[1] ) || ! is_finite( (float) $parts[2] ) || 0 > $parts[1] || 100 < $parts[1] || 0 > $parts[2] || 100 < $parts[2] ) {
								return;
							}
							$state['positions'][ $parts[0] ] = array( round( (float) $parts[1], 1 ), round( (float) $parts[2], 1 ) );
							$state['phase']                  = 'play';
						} else {
							return;
						}
					} else {
						$current = self::round( $rounds, $state['index'], $mode );
						if ( 'begin' === $command && 'ready' === $phase ) {
							$state['phase']    = 'play';
							$state['deadline'] = time() + $policy['round_seconds'];
							if ( 'win-or-lose' === $mode ) {
								$state['stake'] = min( $state['stake'] ?? 10, $state['points'] );
							}
						} elseif ( 'pause' === $command && 'play' === $phase ) {
							$state['remaining'] = max( 0, $state['deadline'] - time() );
							$state['phase']     = 'paused';
						} elseif ( 'resume' === $command && 'paused' === $phase ) {
								$state['deadline'] = time() + $state['remaining'];
								$state['phase']    = 'play';
						} elseif ( 'help' === $command && 'gameshow' === $mode && 'play' === $phase && empty( $state['help_used'] ) ) {
							$state['help']      = true;
							$state['help_used'] = true;
						} elseif ( 'stake' === $command && 'win-or-lose' === $mode && 'ready' === $phase && ctype_digit( $value ) && ( in_array( (int) $value, array( 10, 25, 50 ), true ) || ( 10 > $state['points'] && (int) $value === $state['points'] ) ) && (int) $value <= $state['points'] ) {
							$state['stake'] = (int) $value;
						} elseif ( in_array( $command, array( 'answer', 'miss', 'timeout' ), true ) && 'play' === $phase ) {
							if ( 'miss' === $command && ! in_array( $mode, array( 'maze-chase', 'airplane' ), true ) ) {
								return;
							}
							if ( 'timeout' === $command && time() < $state['deadline'] ) {
								return;
							}
							if ( 'answer' === $command && ! in_array( $value, array_column( $current['options'], 'id' ), true ) ) {
								return;
							}
							$state['selected'] = 'answer' === $command ? $value : '';
							$state['correct']  = 'answer' === $command && time() < $state['deadline'] && $value === $current['correct'];
							$award             = $state['correct'] ? 10 : 0;
							if ( 'gameshow' === $mode && $state['correct'] ) {
								$award += min( 30, max( 0, $state['deadline'] - time() ) );
							}
							if ( 'win-or-lose' === $mode ) {
								$award = ( $state['correct'] ? 1 : -1 ) * ( $state['stake'] ?? 10 );
							}
							$state['round_points'] = $award;
							$state['points']       = max( 0, $state['points'] + $award );
							if ( ! $state['correct'] ) {
								++$state['mistakes'];
							}
							$state['phase'] = 'review';
							$complete       = ( count( $rounds ) === $state['index'] + 1 && ( ! in_array( $mode, array( 'find-match', 'balloon-pop' ), true ) || $state['correct'] ) ) || ( 'win-or-lose' === $mode && 0 === $state['points'] );
						} elseif ( 'next' === $command && 'review' === $phase && ( count( $rounds ) > $state['index'] + 1 || in_array( $mode, array( 'find-match', 'balloon-pop' ), true ) ) ) {
							if ( ! in_array( $mode, array( 'find-match', 'balloon-pop' ), true ) || $state['correct'] ) {
								++$state['index'];
							}
							$state['phase'] = 'ready';
							unset( $state['help'], $state['selected'] );
						} else {
							return;
						}
					}
					++$state['revision'];
					if ( $complete ) {
						$state['phase'] = in_array( $family, array( 'deck', 'magnets' ), true ) ? 'complete' : 'review';
					}
					$policy['activity_state'] = $state;
					$update                   = array(
						'policy' => wp_json_encode( $policy ),
						'status' => $complete ? 'complete' : 'active',
					);
					if ( $complete ) {
						$update['completed_at'] = current_time( 'mysql', true );
					}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Save practice state without grade events or evidence.
					if ( false === $wpdb->update( Schema::table( 'practice_sessions' ), $update, array( 'id' => (int) $session['id'] ) ) ) {
						throw new \RuntimeException( 'Activity write failed.' );
					}
				}
			);
		} catch ( \Throwable $error ) {
			return QuestionPlayer::error( __( 'Could not save activity progress. Please retry.', 'ohmylms' ), 500 );
		}
		return self::view( Sessions::get( $session['uuid'] ), $items );
	}
}
