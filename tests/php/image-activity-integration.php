<?php
/**
 * Image reveal phases and private state against the disposable migration site.
 *
 * @package OhMyLMS
 */

$image_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $image_site ) {
	throw new RuntimeException( 'Set the disposable site path.' );
}
require $image_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the dedicated migration database.' );
}
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\Utility\Transaction;

$GLOBALS['image_checks'] = 0;
/**
 * Assert one contract.
 *
 * @param bool   $ok Condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function image_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( esc_html( $message ) ); }
	++$GLOBALS['image_checks'];
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
			$media = wp_insert_attachment(
				array(
					'post_title'     => 'Image fixture',
					'post_mime_type' => 'image/png',
					'guid'           => home_url( '/wp-includes/images/w-logo-blue.png' ),
				)
			);
			update_attached_file( $media, ABSPATH . 'wp-includes/images/w-logo-blue.png' );
			wp_update_attachment_metadata(
				$media,
				array(
					'width'  => 84,
					'height' => 84,
					'file'   => 'w-logo-blue.png',
				)
			);
			$written = DraftWriter::save(
				array(
					'name'         => 'What logo is this?',
					'thumbnail_id' => $media,
					'settings'     => array(
						'type'  => 'single-choice',
						'score' => array(
							'enabled' => true,
							'value'   => 1,
						),
					),
					'questions'    => array(
						array(
							'answer'     => 'WordPress',
							'is_correct' => true,
						),
						array(
							'answer'     => 'Other',
							'is_correct' => false,
						),
					),
				)
			);
			image_check( ! is_wp_error( $written ), 'Question save failed.' );
			$request = new WP_REST_Request( 'POST', '/ohmylms/v1/questions/' . $written['id'] . '/approve' );
			image_check( 200 === rest_do_request( $request )->get_status(), 'Approval failed.' );
			$snapshot = QuestionPlayer::eligible( $written['id'], $admin->ID );
			image_check( \OhMyLMS\Practice\ImageActivity::compatible( $snapshot ), 'Image fixture incompatible.' );
			$definition = array(
				'type'     => 'single-choice',
				'settings' => array(),
				'media'    => array( 'image_url' => $snapshot->get_image_url() ),
				'options'  => $snapshot->get_questions(),
			);
			foreach ( array(
				array( 'media' => array() ),
				array( 'type' => 'multiple-choice' ),
				array( 'options' => array_slice( $definition['options'], 0, 1 ) ),
				array( 'options' => array_fill( 0, 7, $definition['options'][0] ) ),
				array( 'options' => array( $definition['options'][0], $definition['options'][0] ) ),
				array( 'settings' => array( 'template' => array( 'variables' => array( 'a' => array() ) ) ) ),
			) as $override ) {
				$invalid = new \OhMyLMS\Assessment\QuestionSnapshot( array_replace( $definition, $override ) );
				image_check( ! \OhMyLMS\Practice\ImageActivity::compatible( $invalid ), 'An incompatible definition passed the picker filter.' );
			}
			$post     = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_title'  => 'Image fixture',
					'post_status' => 'publish',
					'post_author' => $admin->ID,
				)
			);
			$settings = QuestionPlayerBlock::settings(
				array(
					'questionIds'   => array( $written['id'] ),
					'activityMode'  => 'image-quiz',
					'revealSeconds' => 10,
					'graded'        => true,
					'affectsSkills' => true,
				)
			);
			image_check( ! $settings['graded'] && ! $settings['affectsSkills'], 'Practice permitted academic grades.' );
			image_check( ! isset( QuestionPlayerBlock::settings( array() )['revealSeconds'] ), 'Standard settings hash changed.' );
			image_check(
				120 === QuestionPlayerBlock::settings(
					array(
						'activityMode'  => 'image-quiz',
						'revealSeconds' => 999,
					)
				)['revealSeconds'],
				'Duration unbounded.'
			);
			$placement = array(
				'post'     => get_post( $post ),
				'key'      => QuestionPlayerBlock::key( $settings ),
				'settings' => $settings,
				'preview'  => false,
			);
			$state     = QuestionPlayer::start( $placement );
			image_check( ! is_wp_error( $state ) && 'ready' === $state['phase'] && ! isset( $state['choices'] ), 'Ready phase failed.' );
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
			image_check( 'ready' === $act( 'buzz' )['phase'], 'Buzz before reveal accepted.' );
			$state = $act( 'begin' );
			image_check( 'reveal' === $state['phase'] && ! isset( $state['choices'] ), 'Reveal exposed choices.' );
			$session                             = Sessions::get( $state['session'] );
			$policy                              = $session['policy'];
			$policy['activity_state']['started'] = time() - 5;
	 // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Move only the transaction-local fixture clock.
			$wpdb->update( Schema::table( 'practice_sessions' ), array( 'policy' => wp_json_encode( $policy ) ), array( 'id' => $session['id'] ) );
			$state = QuestionPlayer::resume( $placement, $state['session'] );
			image_check( 18 <= $state['revealed'], 'Refresh reset the reveal clock.' );
			$state = $act( 'buzz' );
			image_check( 'answer' === $state['phase'] && 2 === count( $state['choices'] ), 'Buzzer failed.' );
			image_check( ! isset( $state['choices'][0]['correct'] ), 'Active view leaked correctness.' );
			$frozen = $state['revealed'];
			image_check( $frozen === $act( 'begin' )['revealed'], 'Begin reset paused reveal.' );
			image_check( $state === $act( 'answer', 'forged' ), 'Forged answer changed state.' );
			$correct = array_values(
				array_filter(
					$state['choices'],
					static function ( $choice ) {
						return 'WordPress' === $choice['text'];
					}
				)
			)[0]['id'];
			image_check( $state === $act( 'answer', $correct, 0 ), 'Stale answer accepted.' );
			$state = $act( 'answer', $correct );
			image_check( 'complete' === $state['status'] && $state['correct'] && max( 1, 36 - $frozen ) === $state['points'], 'Correct practice score failed.' );
			image_check( $state === $act( 'answer', $correct ), 'Replay awarded points twice.' );
			image_check( QuestionPlayer::resume( $placement, $state['session'] ) === $state, 'Refresh lost final result.' );
	 // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify this fixture never produced academic outcomes.
			$grades = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_type='question-player' AND source_id=%d", Schema::table( 'grade_events' ), $session['id'] ) );
			image_check( 0 === (int) $grades, 'Academic grades were written.' );
			$state = QuestionPlayer::start( $placement );
			$state = $act( 'skip' );
			image_check( 'complete' === $state['status'] && 0 === $state['points'] && $state['skipped'], 'Skip awarded points.' );
			$state = QuestionPlayer::start( $placement );
			$state = $act( 'begin' );
			$state = $act( 'buzz' );
			$wrong = array_values(
				array_filter(
					$state['choices'],
					static function ( $choice ) {
						return 'Other' === $choice['text'];
					}
				)
			)[0]['id'];
			$state = $act( 'answer', $wrong );
			image_check( ! $state['correct'] && 0 === $state['points'], 'Incorrect answer earned points.' );
			throw new RuntimeException( 'IMAGE_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'IMAGE_TEST_SUCCESS' !== $failure->getMessage() ) {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
	echo (int) $GLOBALS['image_checks'] . " image integration checks passed; fixtures rolled back.\n";
}
