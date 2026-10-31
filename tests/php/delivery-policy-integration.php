<?php
/**
 * Delivery policy integration suite (isolated test site only).
 *
 * One bank question may reach a learner as a quiz slot, an inline check or a practice run. This suite
 * checks that Assessment\DeliveryPolicy gives the same answer to the question player, the inline check and the
 * exam builder: exam-only, unapproved, archived and teacher-marked questions are refused where they must be.
 *
 *   OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/delivery-policy-integration.php
 */
if ( PHP_SAPI !== 'cli' ) {
	exit;
}
define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true );
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || DB_NAME !== 'ohmylms_source_test' ) {
	throw new RuntimeException( 'Requires disposable test site' );
}
wp_set_current_user( get_user_by( 'login', $config['username'] )->ID );

use OhMyLMS\Assessment\DeliveryPolicy;
use OhMyLMS\Practice\QuestionPlayer;
use OhMyLMS\QuestionBank\BankAttributes;

$checks = 0;
/**
 * Assert one contract.
 *
 * @param bool   $ok Condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function delivery_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}
/**
 * Call a REST route inside the process.
 *
 * @param string $method HTTP method.
 * @param string $path Route below ohmylms/v1.
 * @param array  $body JSON body.
 * @return array
 */
function delivery_call( $method, $path, $body = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $body ) );
	return rest_do_request( $request )->get_data();
}
/**
 * Create a question.
 *
 * @param string $type Question type.
 * @return int Question ID.
 */
function delivery_question( $type = 'numerical' ) {
	$data = delivery_call(
		'POST',
		'questions',
		array(
			'name'     => 'Delivery ' . $type,
			'settings' => array(
				'type'   => $type,
				'answer' => 4,
				'score'  => array(
					'enabled' => true,
					'value'   => 1,
				),
			),
		)
	);
	return (int) $data['id'];
}

// The asset loader that shares the Delivery name must stay intact: every quiz render calls it.
delivery_check( method_exists( 'OhMyLMS\Assessment\Delivery', 'require_script' ) && method_exists( 'OhMyLMS\Assessment\Delivery', 'enqueue' ), 'The quiz autosave loader is still available.' );

$fresh = delivery_question();
delivery_check( DeliveryPolicy::allows( $fresh, DeliveryPolicy::QUIZ ), 'A new question may sit in a quiz slot.' );
delivery_check( null === DeliveryPolicy::snapshot( $fresh, DeliveryPolicy::PRACTICE ) && ! DeliveryPolicy::allows( $fresh, DeliveryPolicy::PRACTICE ), 'Practice refuses an unapproved question.' );
$snapshot = DeliveryPolicy::snapshot( $fresh, DeliveryPolicy::INLINE, $preview );
delivery_check( $snapshot && true === $preview, 'An editor sees an unapproved question inline, flagged as a preview.' );

delivery_call( 'POST', "questions/$fresh/approve" );
$snapshot = DeliveryPolicy::snapshot( $fresh, DeliveryPolicy::PRACTICE );
delivery_check( $snapshot && 'numerical' === $snapshot->get_type(), 'An approved question reaches practice as its frozen version.' );
delivery_check( DeliveryPolicy::snapshot( $fresh, DeliveryPolicy::INLINE, $preview ) && false === $preview, 'An approved question inline is not a preview.' );
delivery_check( (bool) QuestionPlayer::eligible( $fresh, get_current_user_id() ), 'The question player agrees with the delivery policy.' );

BankAttributes::write( $fresh, array( 'secure' => 1 ) );
delivery_check( ! DeliveryPolicy::allows( $fresh, DeliveryPolicy::PRACTICE ) && ! DeliveryPolicy::allows( $fresh, DeliveryPolicy::INLINE ), 'Exam-only (secure) questions are refused by practice and inline.' );
delivery_check( DeliveryPolicy::allows( $fresh, DeliveryPolicy::QUIZ ) && null === QuestionPlayer::eligible( $fresh, get_current_user_id() ), 'They still fit a quiz slot, and the question player refuses them.' );
BankAttributes::write( $fresh, array( 'secure' => 0 ) );

$manual = delivery_question( 'long-text' );
delivery_call( 'POST', "questions/$manual/approve" );
delivery_check( DeliveryPolicy::allows( $manual, DeliveryPolicy::QUIZ ) && ! DeliveryPolicy::allows( $manual, DeliveryPolicy::PRACTICE ) && ! DeliveryPolicy::allows( $manual, DeliveryPolicy::INLINE ), 'Teacher-marked questions fit a quiz slot only.' );

delivery_call( 'POST', "questions/$fresh/archive" );
delivery_check( ! DeliveryPolicy::allows( $fresh, DeliveryPolicy::QUIZ ) && ! DeliveryPolicy::allows( $fresh, DeliveryPolicy::PRACTICE ), 'Archived questions are refused everywhere.' );
delivery_check( ! DeliveryPolicy::allows( 999999, DeliveryPolicy::QUIZ ) && ! DeliveryPolicy::allows( $manual, 'nonsense' ), 'Missing questions and unknown deliveries are refused.' );

// The exam builder uses the same rule.
$exam = delivery_call(
	'POST',
	'exams/compose',
	array(
		'title'            => 'Delivery exam',
		'duration_minutes' => 10,
	)
);
$gone = delivery_call( 'POST', "exams/{$exam['id']}/questions", array( 'question_ids' => array( $fresh ) ) );
delivery_check( isset( $gone['code'] ) && 'exam_questions' === $gone['code'], 'An archived question cannot be added to an exam.' );
$added = delivery_call( 'POST', "exams/{$exam['id']}/questions", array( 'question_ids' => array( $manual ) ) );
delivery_check( array( $manual ) === array_map( 'intval', $added['question_ids'] ), 'A teacher-marked question can be added to an exam.' );

// Publishing an exam makes its questions exam-only: warn about practice sets and inline checks that use them.
$shared  = delivery_question();
$spare   = delivery_question();
delivery_call( 'POST', "questions/$shared/approve" );
$uuid    = \OhMyLMS\QuestionBank\VersionPublisher::identity( $shared, false )['uuid'];
$player  = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Overlap practice page',
		'post_content' => '<!-- wp:ohmylms/question-player {"source":"selected","questionIds":[' . $shared . ']} /-->',
	)
);
$inline  = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Overlap inline page',
		'post_content' => '<!-- wp:ohmylms/shared-multiple-choice {"questionId":' . $shared . ',"delivery":"inline"} /-->[ohmylms_question uuid="' . $uuid . '"]',
	)
);
$quizzed = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Quiz slot is not an overlap',
		'post_content' => '<!-- wp:ohmylms/shared-multiple-choice {"questionId":' . $spare . ',"delivery":"quiz"} /-->',
	)
);
$uses = DeliveryPolicy::other_uses( array( $shared, $spare ) );
$kinds = array_map(
	static function ( $use ) {
		return $use['post_id'] . ':' . $use['delivery'];
	},
	$uses[ $shared ]
);
sort( $kinds );
$expected = array( $player . ':practice', $inline . ':inline' );
sort( $expected );
delivery_check( $kinds === $expected, 'Practice sets and inline checks (block and shortcode) are found: ' . wp_json_encode( $kinds ) );
delivery_check( array() === $uses[ $spare ], 'A question only used in a quiz slot has no other uses.' );

$trial = delivery_call(
	'POST',
	'exams/compose',
	array(
		'title'            => 'Overlap exam',
		'duration_minutes' => 10,
	)
);
delivery_call( 'POST', "exams/{$trial['id']}/questions", array( 'question_ids' => array( $shared, $spare ) ) );
$listing = delivery_call( 'GET', 'exams' );
$mine    = array_values(
	array_filter(
		$listing['items'],
		static function ( $item ) use ( $trial ) {
			return (int) $item['id'] === (int) $trial['id'];
		}
	)
)[0];
delivery_check( 2 === count( $mine['warnings'] ) && false !== strpos( $mine['warnings'][0], 'Question 1' ), 'The exam summary lists what publishing would break.' );
$refused = delivery_call( 'POST', "exams/{$trial['id']}/publish" );
delivery_check( 'exam_overlap' === ( $refused['code'] ?? '' ) && 2 === count( $refused['data']['warnings'] ), 'Publishing asks for confirmation first.' );
delivery_check( 'draft' === get_post_status( $trial['id'] ) && DeliveryPolicy::allows( $shared, DeliveryPolicy::PRACTICE ), 'A refused publish changes nothing.' );
$done = delivery_call( 'POST', "exams/{$trial['id']}/publish", array( 'confirm_overlap' => true ) );
delivery_check( 'publish' === ( $done['status'] ?? '' ), 'Confirming publishes the exam.' );
delivery_check( ! DeliveryPolicy::allows( $shared, DeliveryPolicy::PRACTICE ) && ! DeliveryPolicy::allows( $shared, DeliveryPolicy::INLINE ), 'The exam question is now exam-only, as warned.' );
foreach ( array( $player, $inline, $quizzed ) as $page ) {
	wp_delete_post( $page, true );
}

echo "Delivery policy: {$checks} checks passed.\n";
