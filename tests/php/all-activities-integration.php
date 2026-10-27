<?php
/**
 * All activity modes and private state against the disposable migration site.
 *
 * @package OhMyLMS
 */

$activities_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $activities_site ) {
	throw new RuntimeException( 'Set the disposable site path.' );
}
require $activities_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the dedicated migration database.' );
}
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\Utility\Transaction;

$GLOBALS['activities_checks'] = 0;
/**
 * Assert one contract.
 *
 * @param bool   $ok Condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function activities_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( esc_html( $message ) ); }
	++$GLOBALS['activities_checks'];
}
try {
	Transaction::run(
		static function () {
			global $wpdb;
			$admin = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
				)
			)[0];
			wp_set_current_user( $admin->ID );
			$definitions = array(
				'matching' => array(
					'settings'  => array( 'type' => 'matching' ),
					'questions' => array(
						array(
							'answer'        => 'One',
							'matching_data' => array( 'label' => '1' ),
						),
						array(
							'answer'        => 'Two',
							'matching_data' => array( 'label' => '2' ),
						),
						array(
							'answer'        => 'Three',
							'matching_data' => array( 'label' => '3' ),
						),
					),
				),
				'choice'   => array(
					'settings'  => array( 'type' => 'single-choice' ),
					'questions' => array(
						array(
							'answer'     => 'Yes',
							'is_correct' => true,
						),
						array(
							'answer'     => 'No',
							'is_correct' => false,
						),
						array(
							'answer'     => 'Maybe',
							'is_correct' => false,
						),
					),
				),
				'boolean'  => array(
					'settings'  => array( 'type' => 'true-false' ),
					'questions' => array(
						array(
							'answer'     => 'True',
							'is_correct' => true,
						),
						array(
							'answer'     => 'False',
							'is_correct' => false,
						),
					),
				),
				'sort'     => array(
					'settings'  => array(
						'type'    => 'categorize',
						'buckets' => array(
							array(
								'id'    => 'a',
								'label' => 'Odd',
							),
							array(
								'id'    => 'b',
								'label' => 'Even',
							),
						),
						'items'   => array(
							array(
								'id'   => 'one',
								'text' => '1',
							),
							array(
								'id'   => 'two',
								'text' => '2',
							),
						),
						'key'     => array(
							'one' => 'a',
							'two' => 'b',
						),
					),
					'questions' => array(),
				),
				'magnets'  => array(
					'settings'  => array(
						'type'        => 'build-expression',
						'tile_mode'   => 'words',
						'tiles'       => array( 'We', 'learn', 'together' ),
						'correct'     => array( 'We', 'learn', 'together' ),
						'equivalence' => false,
					),
					'questions' => array(),
				),
			);
			$ids         = array();
			foreach ( $definitions as $name => $definition ) {
				$definition['name']              = 'Activity ' . $name;
				$definition['settings']['score'] = array(
					'enabled' => true,
					'value'   => 1,
				);
				$q                               = DraftWriter::save( $definition );
				activities_check( ! is_wp_error( $q ), 'Fixture failed: ' . $name . ( is_wp_error( $q ) ? $q->get_error_message() : '' ) );
				$request = new WP_REST_Request( 'POST', '/ohmylms/v1/question-bank/' . $q['id'] . '/approve' );
				activities_check( 200 === rest_do_request( $request )->get_status(), 'Approval failed.' );
				$ids[ $name ] = $q['id'];
			}
			$post = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_author' => $admin->ID,
				)
			);
			foreach ( \OhMyLMS\Practice\ActivityModes::MODES as $mode => $family ) {
				$source = array(
					'deck'    => 'matching',
					'match'   => 'matching',
					'choice'  => 'choice',
					'sort'    => 'sort',
					'magnets' => 'magnets',
				)[ $family ];
				if ( 'timed-true-false' === $mode ) {
					$source = 'boolean';
				}
				$settings = QuestionPlayerBlock::settings(
					array(
						'activityMode'  => $mode,
						'questionIds'   => array( $ids[ $source ] ),
						'roundSeconds'  => 10,
						'graded'        => true,
						'affectsSkills' => true,
					)
				);
				activities_check( ! $settings['graded'] && ! $settings['affectsSkills'] && $mode === $settings['activityMode'], 'Unsafe mode configuration: ' . $mode );
				$placement = array(
					'post'     => get_post( $post ),
					'settings' => $settings,
					'key'      => QuestionPlayerBlock::key( $settings ),
					'preview'  => false,
				);
				$state     = QuestionPlayer::start( $placement );
				activities_check( ! is_wp_error( $state ), 'Start failed: ' . $mode . ( is_wp_error( $state ) ? $state->get_error_message() : '' ) );
				$act = static function ( $command, $value = '', $revision = null ) use ( $placement, &$state ) {
					return QuestionPlayer::resume(
						$placement,
						$state['session'],
						null,
						array(
							'card'     => $command,
							'value'    => $value,
							'revision' => null === $revision ? $state['revision'] : $revision,
						)
					);
				};
				activities_check( $state === $act( 'answer', 'forged' ), 'Ready action changed state: ' . $mode );
				if ( 'deck' === $family ) {
					activities_check( ! isset( $state['cards'][0]['back'] ), 'Hidden card back leaked.' );
					$state = $act( 'spin' );
					activities_check(
						1 === count(
							array_filter(
								$state['cards'],
								static function ( $card ) {
									return $card['opened'];
								}
							)
						),
						'Draw failed.'
					);
					$state = $act( 'finish' );
				} elseif ( 'magnets' === $family ) {
					$token = $state['cards'][0]['id'];
					activities_check( $state === $act( 'place', $token . '|999|1' ), 'Out-of-bounds magnet accepted.' );
					$state = $act( 'place', $token . '|25|30' );
					activities_check( array( 25.0, 30.0 ) === array_map( 'floatval', $state['positions'][ $token ] ), 'Magnet position lost.' );
					$state = $act( 'finish' );
				} else {
					while ( 'complete' !== $state['status'] ) {
									$state = $act( 'begin' );
									activities_check( 'play' === $state['phase'] && ! isset( $state['correctId'] ), 'Active key leaked: ' . $mode );
									$state = $act( 'pause' );
									activities_check( 'paused' === $state['phase'], 'Pause failed.' );
									$state = $act( 'resume' );
									activities_check( $state['revision'] === $act( 'timeout' )['revision'], 'Early timeout accepted.' );
						if ( 'gameshow' === $mode ) {
							$state = $act( 'help' );
							activities_check( 2 === count( $state['choices'] ), 'Lifeline failed.' ); }
						$wanted = 'timed-true-false' === $mode ? 'True' : 'Yes';
						if ( 'match' === $family ) {
							$key    = array(
								'One'   => '1',
								'Two'   => '2',
								'Three' => '3',
							)[ $state['prompt'] ];
							$wanted = 'pair-or-no-pair' === $mode ? ( $key === $state['body'] ? 'Pair' : 'No pair' ) : $key;
						}
						if ( 'sort' === $family ) {
							$wanted = '1' === $state['prompt'] ? 'Odd' : 'Even';
						}
						$choice = array_values(
							array_filter(
								$state['choices'],
								static function ( $option ) use ( $wanted ) {
									return $wanted === $option['text'];
								}
							)
						)[0]['id'];
									activities_check( $state['revision'] === $act( 'answer', 'forged' )['revision'], 'Forged option accepted.' );
									$previous = $state['revision'];
									$state    = $act( 'answer', $choice );
									activities_check( $state['correct'] && $state['roundPoints'] > 0, 'Correct answer failed: ' . $mode );
									activities_check( $state === $act( 'answer', $choice, $previous ), 'Stale replay scored twice.' );
									activities_check( QuestionPlayer::resume( $placement, $state['session'] )['revision'] === $state['revision'], 'Resume changed progress.' );
						if ( 'complete' !== $state['status'] ) {
							$state = $act( 'next' );
						}
					}
				}
				activities_check( 'complete' === $state['status'], 'Completion failed: ' . $mode );
				$session = Sessions::get( $state['session'] );
		 // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read only this transaction-local game.
				$events = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type='question-player' AND source_id=%d", Schema::table( 'grade_events' ), $session['id'] ) );
				activities_check( 0 === (int) $events, 'Practice emitted academic events.' );
				if ( 'gameshow' === $mode ) {
					$state = QuestionPlayer::start( $placement );
					$state = $act( 'begin' );
					$wrong = array_values(
						array_filter(
							$state['choices'],
							static function ( $option ) {
								return 'No' === $option['text'];
							}
						)
					)[0]['id'];
					$state = $act( 'answer', $wrong );
					activities_check( ! $state['correct'] && 0 === $state['points'], 'Wrong answer earned practice points.' );
					$state                                        = QuestionPlayer::start( $placement );
					$state                                        = $act( 'begin' );
					$expired_session                              = Sessions::get( $state['session'] );
					$expired_policy                               = $expired_session['policy'];
					$expired_policy['activity_state']['deadline'] = time() - 1;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Expire only the disposable transaction-local game clock.
					$wpdb->update( Schema::table( 'practice_sessions' ), array( 'policy' => wp_json_encode( $expired_policy ) ), array( 'id' => $expired_session['id'] ) );
					$state = $act( 'timeout' );
					activities_check( 'complete' === $state['status'] && ! $state['correct'] && 0 === $state['points'], 'Expired round earned practice points.' );
					wp_set_current_user( 0 );
					activities_check( is_wp_error( QuestionPlayer::resume( $placement, $state['session'] ) ), 'An anonymous user read a private session.' );
					wp_set_current_user( $admin->ID );
				}
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the checked-in local test fixture; no remote request is involved.
			$presets = json_decode( file_get_contents( __DIR__ . '/../fixtures/maths-generator.json' ), true );
			foreach ( $presets as $kind => $preset ) {
				$q = DraftWriter::save(
					array(
						'name'      => $preset['prompt'],
						'settings'  => $preset['settings'] + array(
							'score' => array(
								'enabled' => true,
								'value'   => 1,
							),
						),
						'questions' => array(),
					)
				);
				activities_check( ! is_wp_error( $q ), 'Maths preset invalid: ' . $kind . ( is_wp_error( $q ) ? $q->get_error_message() : '' ) );
				$request = new WP_REST_Request( 'POST', '/ohmylms/v1/question-bank/' . $q['id'] . '/approve' );
				activities_check( 200 === rest_do_request( $request )->get_status(), 'Maths approval failed.' );
				$snapshot = QuestionPlayer::eligible( $q['id'], $admin->ID );
				foreach ( array( 11, 42, 97 ) as $seed ) {
					$instance = $snapshot->instantiate( $seed );
					$grade    = \OhMyLMS\Assessment\Grader::grade( $instance, array( $instance->get_settings()['answer'] ) );
					activities_check( ! is_wp_error( $grade ) && $grade['correct'], 'Generated instance grading failed: ' . $kind );
				}
			}
			throw new RuntimeException( 'ACTIVITIES_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'ACTIVITIES_TEST_SUCCESS' !== $failure->getMessage() ) {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 ); }
	echo (int) $GLOBALS['activities_checks'] . " activity integration checks passed; fixtures rolled back.\n";
}
