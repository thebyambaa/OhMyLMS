<?php
/**
 * Frozen puzzle contracts against the disposable migration site.
 *
 * @package OhMyLMS
 */

$puzzle_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $puzzle_site ) {
	throw new RuntimeException( 'Set the disposable site path.' );
}
require $puzzle_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the dedicated migration database.' );
}

use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\WordPuzzle;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\Utility\Transaction;

$GLOBALS['puzzle_checks'] = 0;
/**
 * Assert a puzzle contract.
 *
 * @param bool   $ok Expected condition.
 * @param string $message Failure description.
 * @throws RuntimeException For a failed condition.
 */
function puzzle_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['puzzle_checks'];
}

try {
	Transaction::run(
		static function () {
			$admin = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
				)
			)[0];
			wp_set_current_user( $admin->ID );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read a checked-in local fixture, never a remote URL.
			$fixtures = json_decode( file_get_contents( __DIR__ . '/../fixtures/word-puzzles.json' ), true );
			$post_id  = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_title'  => 'Puzzle transactional fixture',
					'post_status' => 'publish',
					'post_author' => $admin->ID,
				)
			);
			foreach ( $fixtures as $type => $settings ) {
				puzzle_check( true === WordPuzzle::validate_settings( $type, $settings ), 'Generated puzzle is not valid: ' . $type );
				$bad         = $settings;
				$bad['size'] = 99;
				puzzle_check( true !== WordPuzzle::validate_settings( $type, $bad ), 'Oversized grid accepted.' );
				$bad                     = $settings;
				$bad['entries'][1]['id'] = $bad['entries'][0]['id'];
				puzzle_check( true !== WordPuzzle::validate_settings( $type, $bad ), 'Duplicate entry ID accepted.' );
				$bad                      = $settings;
				$bad['entries'][0]['row'] = -1;
				puzzle_check( true !== WordPuzzle::validate_settings( $type, $bad ), 'Out-of-bounds word accepted.' );
				$view = Interactive::public_view( $type, $settings );
				puzzle_check( ! isset( $view['entries'] ) && ! isset( $view['partial_credit'] ), 'Private definition leaked.' );
				puzzle_check( Interactive::learner_settings( $type, array( 'settings' => $settings ) ) === $view, 'Legacy projection leaked private settings.' );
				$answer = array();
				foreach ( $settings['entries'] as $index => $entry ) {
					$cells = WordPuzzle::cells( $entry );
					if ( 'crossword' === $type ) {
						foreach ( WordPuzzle::letters( $entry['answer'] ) as $position => $letter ) {
							$answer[ $cells[ $position ] ] = mb_strtolower( $letter, 'UTF-8' );
						}
					} else {
						$answer[ 'p' . $index ] = end( $cells ) . ':' . $cells[0];
					}
				}
				$grade      = WordPuzzle::grade( $type, $answer, $settings );
				$unicode    = $settings;
				$letter_map = array(
					'C' => 'Ү',
					'A' => 'Ү',
					'T' => 'Л',
					'H' => 'Х',
				);
				foreach ( $unicode['entries'] as &$unicode_entry ) {
					$unicode_entry['answer'] = strtr( $unicode_entry['answer'], $letter_map );
				}
				unset( $unicode_entry );
				if ( 'wordsearch' === $type ) {
					$unicode['grid'] = array_map(
						static function ( $row ) use ( $letter_map ) {
							return array_map(
								static function ( $letter ) use ( $letter_map ) {
												return strtr( $letter, $letter_map );
								},
								$row
							);
						},
						$unicode['grid']
					);
				}
				$unicode_answer = 'crossword' === $type ? array_map(
					static function ( $letter ) use ( $letter_map ) {
						return mb_strtolower( strtr( strtoupper( $letter ), $letter_map ), 'UTF-8' );
					},
					$answer
				) : $answer;
				puzzle_check( true === WordPuzzle::validate_settings( $type, $unicode ), 'Mongolian layout was rejected.' );
				puzzle_check( WordPuzzle::grade( $type, $unicode_answer, $unicode )['correct'], 'Repeated Mongolian letters did not grade correctly.' );
				puzzle_check( $grade['correct'] && 1.0 === (float) $grade['fraction'], 'Correct puzzle did not earn full credit.' );
				puzzle_check( ! WordPuzzle::grade( $type, array(), $settings )['correct'], 'Blank puzzle earned credit.' );
				$partial = $answer;
				if ( 'crossword' === $type ) {
					$first_cells  = WordPuzzle::cells( $settings['entries'][0] );
					$second_cells = WordPuzzle::cells( $settings['entries'][1] );
					$unique       = array_values( array_diff( $second_cells, $first_cells ) );
					unset( $partial[ $unique[0] ] );
					puzzle_check( 0.0 === (float) WordPuzzle::grade( $type, $answer + array( 'r99c99' => 'A' ), $settings )['fraction'], 'Forged crossword cells accepted.' );
					puzzle_check( false === strpos( wp_json_encode( $view ), 'CAT' ), 'Crossword solution leaked.' );
				} else {
					$partial['p1']       = '';
					$extra               = $settings;
					$extra['grid'][0][0] = 'C';
					$extra['grid'][0][1] = 'A';
					$extra['grid'][0][2] = 'T';
					puzzle_check(
						WordPuzzle::grade(
							$type,
							array(
								'p0' => 'r0c0:r0c2',
								'p1' => $answer['p1'],
							),
							$extra
						)['correct'],
						'Alternate legitimate word occurrence rejected.'
					);
					puzzle_check(
						0.0 === (float) WordPuzzle::grade(
							$type,
							array(
								'p0' => 'r0c0:r0c2',
								'p1' => $answer['p0'],
							),
							$extra
						)['fraction'],
						'Two occurrences of one word earned duplicate credit.'
					);
					puzzle_check( 0.0 === (float) WordPuzzle::grade( $type, array( 'p0' => 'r99c99:r99c98' ), $settings )['fraction'], 'Out-of-bounds selection earned credit.' );
					puzzle_check(
						0.0 === (float) WordPuzzle::grade(
							$type,
							array(
								'p0' => $answer['p0'],
								'p1' => $answer['p0'],
							),
							$settings
						)['fraction'],
						'Duplicate path received credit.'
					);
					puzzle_check( ! isset( $view['clues'][0]['row'] ) && ! isset( $view['clues'][0]['direction'] ), 'Wordsearch placement leaked.' );
				}
				puzzle_check( 0.5 === (float) WordPuzzle::grade( $type, $partial, $settings )['fraction'], 'Partial word credit failed.' );
				$all_or_nothing                   = $settings;
				$all_or_nothing['partial_credit'] = false;
				puzzle_check( 0.0 === (float) WordPuzzle::grade( $type, $partial, $all_or_nothing )['fraction'], 'Whole-puzzle policy ignored.' );
				$settings['type']  = $type;
				$settings['score'] = array(
					'enabled' => true,
					'value'   => 1,
				);
				$written           = DraftWriter::save(
					array(
						'name'      => 'Solve the puzzle',
						'settings'  => $settings,
						'questions' => array(),
					)
				);
				puzzle_check( ! is_wp_error( $written ), 'Puzzle save failed: ' . ( is_wp_error( $written ) ? $written->get_error_message() : '' ) );
				$request = new WP_REST_Request( 'POST', '/ohmylms/v1/question-bank/' . $written['id'] . '/approve' );
				puzzle_check( 200 === rest_do_request( $request )->get_status(), 'Puzzle approval failed.' );
				$player_settings = QuestionPlayerBlock::settings( array( 'questionIds' => array( $written['id'] ) ) );
				$placement       = array(
					'post'     => get_post( $post_id ),
					'key'      => QuestionPlayerBlock::key( $player_settings ),
					'settings' => $player_settings,
					'preview'  => true,
				);
				$run             = QuestionPlayer::start( $placement );
				puzzle_check( ! is_wp_error( $run ), 'Puzzle player failed to start.' );
				puzzle_check( QuestionPlayer::resume( $placement, $run['session'] ) === $run, 'Frozen puzzle changed on resume.' );
				$session = Sessions::get( $run['session'] );
				$result  = QuestionPlayer::resume( $placement, $run['session'], array( $session['id'] => array( 'quiz_question' => array( $written['id'] => $answer ) ) ) );
				puzzle_check( ! is_wp_error( $result ) && 1.0 === (float) $result['score']['earned'], 'Real player submission failed.' );
			}
			throw new RuntimeException( 'PUZZLE_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'PUZZLE_TEST_SUCCESS' !== $failure->getMessage() ) {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
	echo (int) $GLOBALS['puzzle_checks'] . " puzzle integration checks passed; fixtures rolled back.\n";
}
