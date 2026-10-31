<?php
/**
 * Real WordPress player contracts. Every fixture and result is rolled back.
 *
 * @package OhMyLMS
 */

$player_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $player_site || ! is_file( $player_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress installation.' );
}
require $player_site . '/wp-load.php';

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

$GLOBALS['question_player_checks'] = 0;

/**
 * Assert a real player contract.
 *
 * @param bool   $condition Expected result.
 * @param string $message Failure description.
 * @throws RuntimeException When the contract fails.
 */
function player_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['question_player_checks'];
}

/**
 * Dispatch through the actual REST permissions and handlers.
 *
 * @param string $method HTTP method.
 * @param string $route REST route.
 * @param array  $data Parameters.
 * @return WP_REST_Response REST result.
 */
function player_request( $method, $route, array $data = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_body_params( $data );
	}
	return rest_do_request( $request );
}

/**
 * Count this fixture's durable grade or evidence records.
 *
 * @param string $table Assessment table.
 * @param int    $student Student fixture.
 * @return int Stored records.
 */
function player_records( $table, $student ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The test must inspect real, uncommitted records.
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE student_id=%d', Schema::table( $table ), $student ) );
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
			player_check( Schema::ready(), 'Assessment storage unavailable.' );
			player_check( WP_Block_Type_Registry::get_instance()->is_registered( 'ohmylms/question-player' ), 'Question Player block was not registered.' );
			$student = wp_insert_user(
				array(
					'user_login' => 'question-player-' . wp_generate_password( 10, false ),
					'user_pass'  => wp_generate_password( 24 ),
					'role'       => 'subscriber',
				)
			);
			player_check( ! is_wp_error( $student ), 'Student fixture failed.' );
			$term = wp_insert_term( 'Question player fixture ' . wp_generate_password( 6, false ), Taxonomy::NAME );
			player_check( ! is_wp_error( $term ), 'Skill fixture failed.' );
			$questions = array();
			foreach ( array( 3719, 19 ) as $answer ) {
				$written = DraftWriter::save(
					array(
						'name'        => 'Question player fixture',
						'description' => 'Enter the number requested by your teacher.',
						'settings'    => array(
							'type'     => 'numerical',
							'answer'   => $answer,
							'required' => true,
							'score'    => array(
								'enabled' => true,
								'value'   => 2,
							),
						),
						'skills'      => array(
							'p1' => array(
								'primary'    => $term['term_id'],
								'supporting' => array(),
							),
						),
						'questions'   => array(),
					)
				);
				player_check( ! is_wp_error( $written ), 'Question fixture failed: ' . ( is_wp_error( $written ) ? $written->get_error_message() : '' ) );
				$approved = player_request( 'POST', '/ohmylms/v1/questions/' . $written['id'] . '/approve' );
				player_check( 200 === $approved->get_status(), 'Fixture approval failed.' );
				$questions[] = (int) $written['id'];
			}
			$post_id      = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_author' => $admin->ID,
					'post_title'  => 'Question player transaction fixture',
				)
			);
			$place        = static function ( array $settings ) use ( $post_id, $admin ) {
				wp_set_current_user( $admin->ID );
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => '<!-- wp:ohmylms/question-player ' . wp_json_encode( $settings ) . ' /-->',
					)
				);
				return '/ohmylms/v1/question-player/' . $post_id . '/' . QuestionPlayerBlock::key( QuestionPlayerBlock::settings( $settings ) );
			};
				$settings = array(
					'questionIds'   => array( $questions[0] ),
					'graded'        => true,
					'affectsSkills' => false,
				);
			$route        = $place( $settings );
			wp_set_current_user( 0 );
			player_check( 401 === player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_status(), 'Anonymous launch was allowed.' );
			wp_set_current_user( $student );
			player_check( 403 === player_request( 'GET', '/ohmylms/v1/question-player/choices' )->get_status(), 'Student can read the author picker.' );
			$started = player_request(
				'POST',
				$route,
				array(
					'operation'     => 'start',
					'graded'        => false,
					'affectsSkills' => true,
				)
			);
			player_check( 200 === $started->get_status(), 'Student launch failed: ' . wp_json_encode( $started->get_data() ) );
			$state = $started->get_data();
			player_check( $state['graded'] && ! $state['affectsSkills'], 'Client flags overrode the saved outcome policy.' );
			player_check( 1 === count( $state['questions'] ) && false === strpos( wp_json_encode( $state ), '3719' ), 'Active view exposed a key or lost its question.' );
			$session = Sessions::get( $state['session'] );
			$blank   = player_request(
				'POST',
				$route,
				array(
					'operation' => 'submit',
					'session'   => $state['session'],
					'attempt'   => array(),
				)
			);
			player_check( 400 === $blank->get_status() && 0 === player_records( 'grade_events', $student ), 'Required-answer validation wrote a grade.' );
			wp_set_current_user( $admin->ID );
			player_check(
				404 === player_request(
					'POST',
					$route,
					array(
						'operation' => 'resume',
						'session'   => $state['session'],
					)
				)->get_status(),
				'Another user resumed a private session.'
			);
			wp_set_current_user( $student );
			$body   = array(
				'operation' => 'submit',
				'session'   => $state['session'],
				'attempt'   => array( $session['id'] => array( 'quiz_question' => array( $questions[0] => array( 3719 ) ) ) ),
			);
			$graded = player_request( 'POST', $route, $body )->get_data();
			player_check( 'complete' === $graded['status'] && 2.0 === (float) $graded['score']['earned'], 'Correct frozen response did not receive full credit.' );
			player_check( 1 === player_records( 'grade_events', $student ), 'A graded student result was not recorded.' );
			$repeat = player_request( 'POST', $route, $body )->get_data();
			player_check( $graded === $repeat && 1 === player_records( 'grade_events', $student ), 'Repeated submit duplicated or changed a grade.' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify no opted-out grade can later enter the evidence pipeline.
			$outbox = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i o JOIN %i g ON g.id=o.grade_event_id WHERE g.student_id=%d', Schema::table( 'evidence_outbox' ), Schema::table( 'grade_events' ), $student ) );
			player_check( 0 === (int) $outbox && 0 === player_records( 'skill_evidence', $student ), 'Grading-only mode generated skill evidence.' );

			$settings['graded']        = false;
			$settings['affectsSkills'] = true;
			$route                     = $place( $settings );
			wp_set_current_user( $student );
			$ungraded = player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_data();
			$session  = Sessions::get( $ungraded['session'] );
			$done     = player_request(
				'POST',
				$route,
				array(
					'operation' => 'submit',
					'session'   => $session['uuid'],
					'attempt'   => array( $session['id'] => array( 'quiz_question' => array( $questions[0] => array( 8 ) ) ) ),
				)
			)->get_data();
			player_check( 'complete' === $done['status'] && ! isset( $done['score'] ) && ! $done['affectsSkills'], 'Ungraded mode returned scores or allowed skill effects.' );
			player_check( 1 === player_records( 'grade_events', $student ), 'Ungraded responses created grade events.' );

			$settings['graded'] = true;
			$route              = $place( $settings );
			$preview            = player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_data();
			player_check( $preview['preview'] && ! $preview['affectsSkills'], 'Instructor launch was not isolated from performance.' );
			$session = Sessions::get( $preview['session'] );
			player_request(
				'POST',
				$route,
				array(
					'operation' => 'submit',
					'session'   => $session['uuid'],
					'attempt'   => array( $session['id'] => array( 'quiz_question' => array( $questions[0] => array( 3719 ) ) ) ),
				)
			);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspect only this instructor-preview session.
			$preview_events = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type='question-player' AND source_id=%d", Schema::table( 'grade_events' ), $session['id'] ) );
			player_check( 0 === (int) $preview_events, 'Instructor preview wrote a real grade.' );

			wp_set_current_user( $student );
			$tracked = player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_data();
			player_check( $tracked['affectsSkills'], 'Skill participation did not freeze into the student run.' );
			$session = Sessions::get( $tracked['session'] );
			player_request(
				'POST',
				$route,
				array(
					'operation' => 'submit',
					'session'   => $session['uuid'],
					'attempt'   => array( $session['id'] => array( 'quiz_question' => array( $questions[0] => array( 3719 ) ) ) ),
				)
			);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspect this student's tracked grade only.
			$tracked_event = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE source_type='question-player' AND source_id=%d", Schema::table( 'grade_events' ), $session['id'] ) );
			$apply         = new ReflectionMethod( OhMyLMS\Skills\Evidence::class, 'apply' );
			$apply->invoke( null, (int) $tracked_event );
			player_check( player_records( 'skill_evidence', $student ) > 0, 'Opted-in results did not contribute to linked skills.' );

			$route = $place(
				array(
					'source'   => 'random',
					'skillIds' => array( (int) $term['term_id'] ),
					'count'    => 30,
				)
			);
			wp_set_current_user( $student );
			$random = player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_data();
			player_check( 2 === count( $random['questions'] ), 'A random pool did not respect available matching questions.' );
			$issued = Sessions::items( Sessions::get( $random['session'] )['id'] );
			player_check( 2 === count( array_unique( array_column( $issued, 'question_id' ) ) ), 'A random set contained duplicate questions.' );
			$new_run = player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_data();
			player_check( $new_run['session'] !== $random['session'], 'Play again reused the old session.' );
			$stale = '/ohmylms/v1/question-player/' . $post_id . '/' . str_repeat( '0', 32 );
			player_check( 404 === player_request( 'POST', $stale, array( 'operation' => 'start' ) )->get_status(), 'An unsaved or forged configuration launched.' );

			$route = $place( array( 'questionIds' => array( $questions[1] ) ) );
			wp_set_current_user( $student );
			$frozen         = player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_data();
			$frozen_session = Sessions::get( $frozen['session'] );
			wp_set_current_user( $admin->ID );
			$changed = DraftWriter::save(
				array(
					'id'       => $questions[1],
					'settings' => array(
						'type'     => 'numerical',
						'answer'   => 20,
						'required' => true,
						'score'    => array(
							'enabled' => true,
							'value'   => 2,
						),
					),
				)
			);
			player_check( ! is_wp_error( $changed ), 'Frozen-version fixture update failed.' );
			player_request( 'POST', '/ohmylms/v1/questions/' . $questions[1] . '/approve' );
			wp_set_current_user( $student );
			$frozen_result = player_request(
				'POST',
				$route,
				array(
					'operation' => 'submit',
					'session'   => $frozen_session['uuid'],
					'attempt'   => array( $frozen_session['id'] => array( 'quiz_question' => array( $questions[1] => array( 19 ) ) ) ),
				)
			)->get_data();
			player_check( 2.0 === (float) $frozen_result['score']['earned'], 'Approval of a new version changed an issued question key.' );
			$route = $place(
				array(
					'source'   => 'random',
					'skillIds' => array( 2147483647 ),
					'count'    => 2,
				)
			);
			wp_set_current_user( $student );
			player_check( 409 === player_request( 'POST', $route, array( 'operation' => 'start' ) )->get_status(), 'A missing skill was not handled as an empty pool.' );
			wp_set_current_user( $admin->ID );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Mark only this fixture exam-only, then verify exclusion.
			$wpdb->update( Schema::table( 'qb_questions' ), array( 'secure' => 1 ), array( 'question_id' => $questions[0] ) );
			player_check( null === QuestionPlayer::eligible( $questions[0], $admin->ID ), 'Exam-only questions were eligible for public embedding.' );
			throw new RuntimeException( 'OHMYLMS_PLAYER_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'OHMYLMS_PLAYER_TEST_SUCCESS' === $failure->getMessage() ) {
		echo 'Question player integration: ' . (int) $GLOBALS['question_player_checks'] . " checks passed; fixtures rolled back.\n";
	} else {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
}
