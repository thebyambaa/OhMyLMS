<?php
/**
 * Recall phases and private state against the disposable migration site.
 *
 * @package OhMyLMS
 */

$recall_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $recall_site ) {
	throw new RuntimeException( 'Set the disposable site path.' );
}
require $recall_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the dedicated migration database.' );
}
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\Utility\Transaction;

$GLOBALS['recall_checks'] = 0;
/**
 * Assert one contract.
 *
 * @param bool   $ok Condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function recall_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( esc_html( $message ) ); }
	++$GLOBALS['recall_checks'];
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
			$options = array_map(
				static function ( $text ) {
					return array(
						'answer'        => $text,
						'matching_data' => array( 'label' => 'Private reverse ' . $text ),
					);
				},
				array( 'Үүл', 'Rain', 'Sun', 'Snow' )
			);
			$written = DraftWriter::save(
				array(
					'name'      => 'Recall fixture',
					'settings'  => array(
						'type'  => 'matching',
						'score' => array(
							'enabled' => true,
							'value'   => 1,
						),
					),
					'questions' => $options,
				)
			);
			recall_check( ! is_wp_error( $written ), 'Fixture save failed.' );
			$request = new WP_REST_Request( 'POST', '/ohmylms/v1/questions/' . $written['id'] . '/approve' );
			recall_check( 200 === rest_do_request( $request )->get_status(), 'Approval failed.' );
			$post     = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_title'  => 'Recall transactional fixture',
					'post_status' => 'publish',
					'post_author' => $admin->ID,
				)
			);
			$settings = QuestionPlayerBlock::settings(
				array(
					'questionIds'   => array( $written['id'] ),
					'activityMode'  => 'memory-recall',
					'rememberCount' => 2,
					'studySeconds'  => 5,
					'graded'        => true,
					'affectsSkills' => true,
				)
			);
			recall_check( ! $settings['graded'] && ! $settings['affectsSkills'], 'Recall allowed academic outcomes.' );
			recall_check( ! isset( QuestionPlayerBlock::settings( array() )['studySeconds'] ), 'Standard mode hash changed.' );
			$placement = array(
				'post'     => get_post( $post ),
				'key'      => QuestionPlayerBlock::key( $settings ),
				'settings' => $settings,
				'preview'  => false,
			);
			$state     = QuestionPlayer::start( $placement );
			recall_check( ! is_wp_error( $state ) && 'ready' === $state['phase'] && ! isset( $state['studyItems'] ) && ! isset( $state['choices'] ), 'Ready phase leaked content.' );
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
				recall_check( 'ready' === $act( 'finish' )['phase'], 'Skipped the study phase.' );
				$state = $act( 'begin' );
				recall_check( 'study' === $state['phase'] && 2 === count( $state['studyItems'] ) && ! isset( $state['choices'] ), 'Study projection failed.' );
				$targets = array_column( $state['studyItems'], 'id' );
				recall_check( false === strpos( wp_json_encode( $state ), 'Private reverse' ), 'Matching reverse leaked.' );
				$session = Sessions::get( $state['session'] );
				$until   = $session['policy']['activity_state']['until'];
				$act( 'begin' );
				recall_check( Sessions::get( $state['session'] )['policy']['activity_state']['until'] === $until, 'Begin replay reset the deadline.' );
				recall_check( 'study' === $act( 'toggle', $targets[0] )['phase'], 'Recall response accepted during study.' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Expire only this transactional test session without sleeping.
				$policy                            = $session['policy'];
				$policy['activity_state']['until'] = time() - 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test-only deadline transition in the dedicated database.
				$wpdb->update( Schema::table( 'practice_sessions' ), array( 'policy' => wp_json_encode( $policy ) ), array( 'id' => $session['id'] ) );
				$state = QuestionPlayer::resume( $placement, $state['session'] );
				recall_check( 'recall' === $state['phase'] && ! isset( $state['studyItems'] ) && 4 === count( $state['choices'] ), 'Expired study resumed with targets exposed.' );
				recall_check( ! isset( $state['choices'][0]['wasShown'] ), 'Recall answer map leaked.' );
				recall_check( is_wp_error( $act( 'toggle', 'forged' ) ), 'Forged choice accepted.' );
				$state = $act( 'toggle', $targets[0] );
				recall_check( $state === $act( 'toggle', $targets[1], 0 ), 'Stale action changed selections.' );
				$resumed = QuestionPlayer::resume( $placement, $state['session'] );
				recall_check( $state === $resumed, 'Resume lost selections.' );
				$state = $act( 'toggle', $targets[1] );
				$extra = array_values( array_diff( array_column( $state['choices'], 'id' ), $targets ) )[0];
				recall_check( $state === $act( 'toggle', $extra ), 'Select-all bypassed target count.' );
				$state = $act( 'finish' );
				recall_check( 'complete' === $state['status'] && 1.0 === (float) $state['recallResult']['fraction'], 'Correct recall did not finish.' );
				recall_check( isset( $state['choices'][0]['wasShown'] ), 'Completed review missing.' );
				recall_check( $state === $act( 'begin' ), 'Completed session reopened.' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify only transaction-local practice outcomes.
				$grades = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type='question-player' AND source_id=%d", Schema::table( 'grade_events' ), $session['id'] ) );
				recall_check( 0 === (int) $grades, 'Recall wrote academic grades.' );
				$state   = QuestionPlayer::start( $placement );
				$state   = $act( 'begin' );
				$targets = array_column( $state['studyItems'], 'id' );
				$state   = $act( 'recall' );
				$wrong   = array_values( array_diff( array_column( $state['choices'], 'id' ), $targets ) )[0];
				$state   = $act( 'toggle', $targets[0] );
				$state   = $act( 'toggle', $wrong );
				$state   = $act( 'finish' );
				recall_check( 0.0 === (float) $state['recallResult']['fraction'] && 1 === $state['recallResult']['wrong'], 'False choices were not penalized.' );
				throw new RuntimeException( 'RECALL_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'RECALL_TEST_SUCCESS' !== $failure->getMessage() ) {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 ); }
	echo (int) $GLOBALS['recall_checks'] . " recall integration checks passed; fixtures rolled back.\n";
}
