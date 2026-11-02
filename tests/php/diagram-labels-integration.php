<?php
/**
 * Diagram labels and private state against the disposable migration site.
 *
 * @package OhMyLMS
 */

$diagram_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $diagram_site ) {
	throw new RuntimeException( 'Set the disposable site path.' );
}
require $diagram_site . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || ! OHMYLMS_TEST_SITE || 'ohmylms_gutenberg_migration_20261020' !== DB_NAME ) {
	throw new RuntimeException( 'Requires the dedicated migration database.' );
}
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Blocks\QuestionPlayerBlock;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\Utility\Transaction;

$GLOBALS['diagram_checks'] = 0;
/**
 * Assert one contract.
 *
 * @param bool   $ok Condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function diagram_check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( esc_html( $message ) ); }
	++$GLOBALS['diagram_checks'];
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
			$settings = array(
				'type'        => 'labeling',
				'image_url'   => home_url( '/wp-content/plugins/ohmylms/tests/fixtures/diagram.svg' ),
				'targets'     => array(
					array(
						'id'     => 'left',
						'x'      => 25,
						'y'      => 50,
						'answer' => 'Улаан',
					),
					array(
						'id'     => 'right',
						'x'      => 75,
						'y'      => 50,
						'answer' => 'Blue',
					),
				),
				'distractors' => array( 'Green' ),
				'required'    => true,
				'score'       => array(
					'enabled' => true,
					'value'   => 2,
				),
			);
			diagram_check( true === \OhMyLMS\Assessment\ExtendedQuestions::validate_settings( 'labeling', $settings ), 'Valid diagram rejected.' );
			foreach ( array( 'invalid', array( array() ), array( '' ), array_fill( 0, 51, 'X' ) ) as $invalid ) {
				$bad                = $settings;
				$bad['distractors'] = $invalid;
				diagram_check( is_wp_error( \OhMyLMS\Assessment\ExtendedQuestions::validate_settings( 'labeling', $bad ) ), 'Invalid distractor bank accepted.' );
			}
			$written = DraftWriter::save(
				array(
					'name'      => 'Label the colours',
					'settings'  => $settings,
					'questions' => array(),
				)
			);
			diagram_check( ! is_wp_error( $written ), 'Fixture save failed.' );
			$request = new WP_REST_Request( 'POST', '/ohmylms/v1/questions/' . $written['id'] . '/approve' );
			diagram_check( 200 === rest_do_request( $request )->get_status(), 'Approval failed.' );
			$snapshot = QuestionPlayer::eligible( $written['id'], $admin->ID );
			$public   = \OhMyLMS\Assessment\ExtendedQuestions::public_view( 'labeling', $snapshot->get_settings() );
			diagram_check( ! isset( $public['targets'][0]['answer'] ) && 3 === count( $public['choices'] ), 'Public diagram leaked the assignment map.' );
			$post               = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_author' => $admin->ID,
				)
			);
			$placement_settings = QuestionPlayerBlock::settings(
				array(
					'questionIds'   => array( $written['id'] ),
					'graded'        => true,
					'affectsSkills' => false,
				)
			);
			$placement          = array(
				'post'     => get_post( $post ),
				'key'      => QuestionPlayerBlock::key( $placement_settings ),
				'settings' => $placement_settings,
				'preview'  => true,
			);
			$state              = QuestionPlayer::start( $placement );
			diagram_check( ! is_wp_error( $state ), 'Launch failed.' );
			diagram_check( 2 === substr_count( $state['questions'][0]['html'], 'data-answer-key=' ), 'Native restore fields were not rendered before JavaScript.' );
			diagram_check( false === strpos( $state['questions'][0]['html'], '&quot;answer&quot;' ), 'Question HTML leaked target keys.' );
			$session   = Sessions::get( $state['session'] );
			$submitted = array(
				$session['id'] => array(
					'quiz_question' => array(
						$written['id'] => array(
							'left'  => 'Улаан',
							'right' => 'Blue',
						),
					),
				),
			);
			$wrong     = \OhMyLMS\Assessment\Grader::grade(
				$snapshot,
				array(
					'left'  => 'Blue',
					'right' => 'Улаан',
				)
			);
			diagram_check( ! $wrong['correct'] && 0.0 === $wrong['fraction'], 'Wrong placements earned marks.' );
			$extra = \OhMyLMS\Assessment\Grader::grade(
				$snapshot,
				array(
					'left'    => 'Улаан',
					'right'   => 'Blue',
					'unknown' => 'Green',
				)
			);
			diagram_check( ! $extra['correct'], 'An unknown target earned full marks.' );
			$missing = \OhMyLMS\Assessment\Grader::grade(
				$snapshot,
				array(
					'left'  => 'Улаан',
					'right' => '',
				)
			);
			diagram_check( ! $missing['correct'], 'Incomplete diagram earned full marks.' );
			// Change the draft after issuing this round: its earlier frozen answer must still grade.
			$changed                         = $settings;
			$changed['targets'][0]['answer'] = 'Red';
			DraftWriter::save(
				array(
					'id'       => $written['id'],
					'settings' => $changed,
				)
			);
			$result = QuestionPlayer::resume( $placement, $state['session'], $submitted );
			diagram_check( ! is_wp_error( $result ) && 'complete' === $result['status'] && 2.0 === (float) $result['score']['earned'], 'Frozen diagram did not grade correctly after draft edits.' );
			diagram_check( QuestionPlayer::resume( $placement, $state['session'], $submitted ) === $result, 'Repeated submission changed the result.' );
			diagram_check(
				2 === count(
					\OhMyLMS\Assessment\ReviewLines::given(
						'labeling',
						array(
							'left'  => 'Улаан',
							'right' => 'Blue',
						),
						$settings
					)
				),
				'Report labels were lost.'
			);
			throw new RuntimeException( 'DIAGRAM_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'DIAGRAM_TEST_SUCCESS' !== $failure->getMessage() ) {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
	echo (int) $GLOBALS['diagram_checks'] . " diagram integration checks passed; fixtures rolled back.\n";
}
