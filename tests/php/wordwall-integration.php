<?php
/**
 * Wordwall first-slice contracts against the disposable site.
 *
 * @package OhMyLMS
 */

$wordwall_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $wordwall_site ) {
	throw new RuntimeException( 'Set the disposable site path.' ); }
require $wordwall_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the dedicated migration database.' ); }
use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\TextEntry;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Practice\StudyActivity;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\Utility\Transaction;
$GLOBALS['wordwall_checks'] = 0;
/**
 * Assert a player contract.
 *
 * @param bool   $ok Expected result.
 * @param string $message Failure message.
 * @throws RuntimeException When a contract fails.
 */
function wordwall_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( esc_html( $message ) );
	} ++$GLOBALS['wordwall_checks']; }
/**
 * Dispatch a real REST request.
 *
 * @param string $route API route.
 * @param array  $data Request body.
 * @return WP_REST_Response Response.
 */
function wordwall_request( $route, array $data ) {
	$request = new WP_REST_Request( 'POST', '/ohmylms/v1/' . $route );
	$request->set_body_params( $data );
	return rest_do_request( $request ); }
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
			$text = array(
				'accepted'            => array( 'ӨНДӨР ҮҮЛ' ),
				'collapse_whitespace' => true,
			);
			wordwall_check( TextEntry::grade( array( ' өндөр   үүл ' ), $text )['correct'], 'Mongolian case and whitespace normalization failed.' );
			wordwall_check( ! TextEntry::grade( array( 'өндөр үүл', 'other' ), $text )['correct'], 'Multiple guesses were accepted.' );
			wordwall_check( ! TextEntry::grade( array( '' ), $text )['correct'], 'Blank answer received credit.' );
			wordwall_check( ! TextEntry::grade( array( 'өндөр үүл' ), $text + array( 'case_sensitive' => true ) )['correct'], 'Case-sensitive rule ignored.' );
			wordwall_check( true !== TextEntry::validate_settings( array( 'accepted' => array( array() ) ) ), 'Nested accepted answer was allowed.' );
			wordwall_check( ! isset( Interactive::public_view( 'text-entry', $text )['accepted'] ), 'Text keys exposed.' );
			wordwall_check(
				Interactive::grade(
					'build-expression',
					array( 'ү', 'ү', 'л' ),
					array(
						'tile_mode' => 'letters',
						'correct'   => array( 'ү', 'ү', 'л' ),
					)
				)['correct'],
				'Repeated language letters failed sequence grading.'
			);
			$written = DraftWriter::save(
				array(
					'name'      => 'Type the cloud',
					'settings'  => array(
						'type'     => 'text-entry',
						'accepted' => array( 'үүл' ),
						'score'    => array(
							'enabled' => true,
							'value'   => 1,
						),
					),
					'questions' => array(),
				)
			);
			wordwall_check( ! is_wp_error( $written ), 'Text entry could not save: ' . ( is_wp_error( $written ) ? $written->get_error_message() : '' ) );
			wordwall_check( 200 === wordwall_request( 'questions/' . $written['id'] . '/approve', array() )->get_status(), 'Text approval failed.' );
			wordwall_check( null !== QuestionPlayer::eligible( $written['id'], $admin->ID ), 'Automatic text entry is not player eligible.' );
			$match = DraftWriter::save(
				array(
					'name'      => 'Study pairs',
					'settings'  => array(
						'type'  => 'matching',
						'score' => array(
							'enabled' => true,
							'value'   => 1,
						),
					),
					'questions' => array(
						array(
							'answer'        => 'Cloud',
							'matching_data' => array( 'label' => 'Hidden cloud back' ),
						),
						array(
							'answer'        => 'Rain',
							'matching_data' => array( 'label' => 'Hidden rain back' ),
						),
					),
				)
			);
			wordwall_check( ! is_wp_error( $match ), 'Matching fixture failed.' );
			wordwall_request( 'questions/' . $match['id'] . '/approve', array() );
			$post           = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_author' => $admin->ID,
					'post_title'  => 'Study transactional fixture',
				)
			);
			$text_settings  = QuestionPlayerBlock::settings( array( 'questionIds' => array( $written['id'] ) ) );
			$text_placement = array(
				'post'     => get_post( $post ),
				'key'      => QuestionPlayerBlock::key( $text_settings ),
				'settings' => $text_settings,
				'preview'  => true,
			);
			$text_run       = QuestionPlayer::start( $text_placement );
			wordwall_check( ! is_wp_error( $text_run ) && false === strpos( wp_json_encode( $text_run ), 'үүл' ), 'Automatic text player leaked its accepted answer.' );
			$text_session = Sessions::get( $text_run['session'] );
			$text_done    = QuestionPlayer::resume( $text_placement, $text_run['session'], array( $text_session['id'] => array( 'quiz_question' => array( $written['id'] => array( 'үүл' ) ) ) ) );
			wordwall_check( ! is_wp_error( $text_done ) && 1.0 === (float) $text_done['score']['earned'], 'Frozen automatic text was not graded by the player.' );
			foreach ( array( 'flashcards', 'memory-pairs' ) as $mode ) {
				$settings = QuestionPlayerBlock::settings(
					array(
						'questionIds'   => array( $match['id'] ),
						'activityMode'  => $mode,
						'graded'        => true,
						'affectsSkills' => true,
					)
				);
				wordwall_check( ! $settings['graded'] && ! $settings['affectsSkills'], 'Study mode allowed academic outcomes.' );
				$placement = array(
					'post'     => get_post( $post ),
					'key'      => QuestionPlayerBlock::key( $settings ),
					'settings' => $settings,
					'preview'  => false,
				);
				$state     = QuestionPlayer::start( $placement );
				wordwall_check( ! is_wp_error( $state ), 'Study start failed.' );
				wordwall_check( false === strpos( wp_json_encode( $state ), 'Hidden' ), 'Unrevealed answer leaked.' );
				$session  = Sessions::get( $state['session'] );
				$items    = Sessions::items( $session['id'] );
				$private  = StudyActivity::deck( $items, $session['uuid'] );
				$first    = $state['cards'][0]['id'];
				$revealed = QuestionPlayer::resume(
					$placement,
					$state['session'],
					null,
					array(
						'card'     => $first,
						'revision' => 0,
					)
				);
				wordwall_check( 1 === $revealed['revision'], 'Reveal did not save.' );
				$stale = QuestionPlayer::resume(
					$placement,
					$state['session'],
					null,
					array(
						'card'     => $state['cards'][1]['id'],
						'revision' => 0,
					)
				);
				wordwall_check( $revealed === $stale, 'Stale action changed study state.' );
				wordwall_check( QuestionPlayer::resume( $placement, $state['session'] ) === $revealed, 'Resume lost revealed cards.' );
				wordwall_check(
					is_wp_error(
						QuestionPlayer::resume(
							$placement,
							$state['session'],
							null,
							array(
								'card'     => 'forged',
								'revision' => 1,
							)
						)
					),
					'Forged card was accepted.'
				);
				foreach ( $state['cards'] as $card ) {
					if ( 'flashcards' === $mode ) {
						$revealed = QuestionPlayer::resume(
							$placement,
							$state['session'],
							null,
							array(
								'card'     => $card['id'],
								'revision' => $revealed['revision'],
							)
						); }
				}
				if ( 'memory-pairs' === $mode ) {
					$first_pair = $private[ $first ]['pair'];
					foreach ( $private as $id => $card ) {
						if ( $id !== $first && $card['pair'] === $first_pair ) {
							$revealed = QuestionPlayer::resume(
								$placement,
								$state['session'],
								null,
								array(
									'card'     => $id,
									'revision' => $revealed['revision'],
								)
							); }
					}
					$pairs = array();
					foreach ( $private as $id => $card ) {
									$pairs[ $card['pair'] ][] = $id; }
					foreach ( $pairs as $ids ) {
						foreach ( $ids as $id ) {
								$revealed = QuestionPlayer::resume(
									$placement,
									$state['session'],
									null,
									array(
										'card'     => $id,
										'revision' => $revealed['revision'],
									)
								); }
					}
				}
				wordwall_check( 'complete' === $revealed['status'], 'Study activity did not complete.' );
			 // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspect only transaction-local fixture outcomes.
				$grades = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type='question-player' AND source_id=%d", Schema::table( 'grade_events' ), $session['id'] ) );
				wordwall_check( 0 === (int) $grades, 'Study wrote grade events.' );
			}
			foreach ( array( 'hangman', 'spell-word' ) as $mode ) {
				$settings = QuestionPlayerBlock::settings(
					array(
						'questionIds'   => array( $written['id'] ),
						'activityMode'  => $mode,
						'graded'        => true,
						'affectsSkills' => true,
					)
				);
				wordwall_check( ! $settings['graded'] && ! $settings['affectsSkills'], 'Word mode allowed grades.' );
				$placement = array(
					'post'     => get_post( $post ),
					'key'      => QuestionPlayerBlock::key( $settings ),
					'settings' => $settings,
					'preview'  => false,
				);
				$state     = QuestionPlayer::start( $placement );
				wordwall_check( ! is_wp_error( $state ) && ! isset( $state['answer'] ), 'Word start failed or leaked answer.' );
				wordwall_check( 'hangman' !== $mode || array( null, null, null ) === $state['letters'], 'Hidden Hangman word was exposed.' );
				$guess = static function ( $value, $revision = null ) use ( $placement, &$state ) {
					return QuestionPlayer::resume(
						$placement,
						$state['session'],
						null,
						array(
							'card'     => 'guess',
							'value'    => $value,
							'revision' => null === $revision ? $state['revision'] : $revision,
						)
					);
				};
				wordwall_check( is_wp_error( $guess( array() ) ), 'Nested word guess allowed.' );
				if ( 'hangman' === $mode ) {
					wordwall_check( is_wp_error( $guess( 'ab' ) ), 'Multi-letter Hangman guess allowed.' );
				}
				$state = $guess( 'z' );
				wordwall_check( 1 === $state['mistakes'] && ! isset( $state['answer'] ), 'Wrong guess did not save or leaked answer.' );
				wordwall_check( $state === $guess( 'x', 0 ), 'Stale request changed word state.' );
				wordwall_check( QuestionPlayer::resume( $placement, $state['session'] ) === $state, 'Word resume lost state.' );
				if ( 'hangman' === $mode ) {
					wordwall_check( $state === $guess( 'Z' ), 'Repeated Hangman letter counted twice.' );
					$state = $guess( 'Ү' );
					wordwall_check( array( 'ү', 'ү', null ) === $state['letters'], 'Repeated Cyrillic letters did not reveal together.' );
					$state = $guess( 'л' );
				} else {
					$state = $guess( 'ҮҮЛ' );
				}
				wordwall_check( $state['done'] && $state['solved'] && 'complete' === $state['status'], 'Word round failed to complete.' );
				wordwall_check( 'үүл' === $state['answer'], 'Finished word answer missing.' );
				wordwall_check( $state === $guess( 'q' ), 'Completed word round changed.' );
				$session = Sessions::get( $state['session'] );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspect only transaction-local fixture outcomes.
				$grades = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type='question-player' AND source_id=%d", Schema::table( 'grade_events' ), $session['id'] ) );
				wordwall_check( 0 === (int) $grades, 'Word activity wrote grade events.' );
				$state = QuestionPlayer::start( $placement );
				foreach ( array( 'a', 'b', 'c', 'd', 'e', 'f' ) as $wrong ) {
					$state = $guess( $wrong );
				}
				wordwall_check( $state['done'] && ! $state['solved'] && 6 === $state['mistakes'] && 'complete' === $state['status'], 'Mistake limit failed.' );
			}
			throw new RuntimeException( 'WORDWALL_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'WORDWALL_TEST_SUCCESS' !== $failure->getMessage() ) {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 ); }
	echo (int) $GLOBALS['wordwall_checks'] . " Wordwall integration checks passed; fixtures rolled back.\n";
}
