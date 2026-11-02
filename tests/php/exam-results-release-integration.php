<?php
/**
 * Exam results release integration test (`exams/library/{id}/results…`, `Exams\Results`).
 *
 * Runs against the Local site with real tables. Every fixture write happens inside one transaction that
 * is rolled back at the end (the two results tables are created first, outside it). Outgoing HTTP is
 * blocked and mail is captured, never sent.
 *
 * Usage (set OHMYLMS_EXAM_RESULTS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/exam-results-release-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\Exams\Player;
use OhMyLMS\Exams\Results;
use OhMyLMS\Exams\ResultsEmail;
use OhMyLMS\Quiz\AnswerReview;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_EXAM_RESULTS_TEST' ) ) {
	exit( "Set OHMYLMS_EXAM_RESULTS_TEST=1 to run the exam results release tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;
if ( 'local' !== wp_get_environment_type() || ! Schema::ready() || wp_using_ext_object_cache() ) {
	exit( "Requires the Local site, the assessment schema and a request-local object cache.\n" );
}
if ( ! Results::install() ) {
	exit( "The exam results tables could not be created.\n" );
}
foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table_status ) {
	if ( 0 === strpos( $table_status['Name'], $wpdb->prefix ) && 'InnoDB' !== $table_status['Engine'] ) {
		exit( "Requires InnoDB tables so fixture writes can be rolled back.\n" );
	}
}
$mails = array();
add_filter(
	'pre_wp_mail',
	static function ( $short, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	PHP_INT_MAX,
	2
);
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'exam_results_test', 'Network disabled.' );
	},
	PHP_INT_MAX
);

$checks = 0;
$tag    = 'exres' . strtolower( wp_generate_password( 6, false, false ) );

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value || is_wp_error( $value ) ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Dispatch a REST request as JSON (or query for GET).
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $label    Description.
 */
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	$got  = is_array( $data ) && isset( $data['code'] ) ? $data['code'] : '';
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got . ' ' . wp_json_encode( $data ) );
}

/**
 * Assert a success status and return the data.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $label    Description.
 * @return mixed
 */
function ok_status( $response, $status, $label ) {
	ok( $status === $response->get_status(), "$label: expected $status, got " . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );
	return $response->get_data();
}

/**
 * Create a temporary user (rolled back with the fixture).
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role ) {
	global $tag;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $suffix . '@example.invalid',
			'display_name' => $tag . ' ' . $suffix,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role created" );
	return (int) $id;
}

/**
 * A synthetic reviewed import: question 1 is auto-marked, question 2 has a written part.
 *
 * @param int    $owner Owner.
 * @param string $title Title.
 * @param string $skill Skill token.
 * @return int Job ID.
 */
function make_review_job( $owner, $title, $skill ) {
	$inventory = array(
		'title'            => $title,
		'duration_minutes' => 30,
		'total_marks'      => 4,
		'page_count'       => 2,
		'question_numbers' => array( '1', '2' ),
	);
	$rows      = array(
		array(
			'number'           => '1',
			'question'         => 'Question 1 stem',
			'page'             => 1,
			'requires_source'  => false,
			'notes'            => '',
			'skills'           => $skill,
			'parts'            => array(
				array(
					'id'     => 'p1',
					'label'  => '(a)',
					'prompt' => 'Work out 1 + 1.',
					'kind'   => 'numerical',
					'marks'  => 2,
					'answer' => 2,
				),
			),
			'reviewed'         => false,
			'figures'          => array(),
			'figures_complete' => false,
		),
		array(
			'number'           => '2',
			'question'         => 'Question 2 stem',
			'page'             => 2,
			'requires_source'  => false,
			'notes'            => '',
			'skills'           => $skill,
			'parts'            => array(
				array(
					'id'     => 'p1',
					'label'  => '(a)',
					'prompt' => 'Explain your method.',
					'kind'   => 'written',
					'marks'  => 2,
					'rubric' => 'M1 method, A1 answer.',
				),
			),
			'reviewed'         => false,
			'figures'          => array(),
			'figures_complete' => false,
		),
	);
	$id        = wp_insert_post(
		array(
			'post_type'   => Jobs::TYPE,
			'post_status' => 'private',
			'post_author' => $owner,
			'post_title'  => $title . '.pdf',
		),
		true
	);
	ok( ! is_wp_error( $id ), 'synthetic review job created' );
	update_post_meta(
		$id,
		Jobs::META,
		wp_slash(
			array(
				'document'        => array(
					'id'     => wp_generate_uuid4(),
					'name'   => $title . '.pdf',
					'bytes'  => 1024,
					'sha256' => hash( 'sha256', $title ),
				),
				'mark_scheme'     => null,
				'target'          => 'exam',
				'skills'          => $skill,
				'state'           => 'ready_for_review',
				'attempts'        => 1,
				'error'           => '',
				'updated_at'      => gmdate( 'c' ),
				'inventory'       => $inventory,
				'draft_inventory' => $inventory,
				'draft'           => $rows,
				'suggestions'     => $rows,
				'draft_revision'  => 1,
			)
		)
	);
	return (int) $id;
}

/**
 * Release row of an attempt.
 *
 * @param int $attempt Attempt ID.
 * @return array|null
 */
function release_row( $attempt ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE attempt_id=%d', Results::table(), $attempt ), ARRAY_A );
}

/**
 * Item of the results list for an attempt.
 *
 * @param array $view    Results view.
 * @param int   $attempt Attempt ID.
 * @return array|null
 */
function result_item( array $view, $attempt ) {
	foreach ( $view['items'] as $item ) {
		if ( (int) $attempt === $item['attempt_id'] ) {
			return $item;
		}
	}
	return null;
}

try {
	Transaction::run(
		static function () use ( $tag, &$mails ) {
			global $wpdb;
			$routes = rest_get_server()->get_routes();
			foreach ( array( '/results', '/results/policy', '/results/release', '/results/revoke', '/results/log' ) as $route ) {
				ok( isset( $routes[ '/ohmylms/v1/exams/library/(?P<id>[\d]+)' . $route ] ), "route $route registered" );
			}

			$admin      = make_user( 'admin', 'administrator' );
			$author     = make_user( 'author', 'author' );
			$other      = make_user( 'other', 'author' );
			$subscriber = make_user( 'sub', 'subscriber' );
			$learners   = array( make_user( 'l1', 'subscriber' ), make_user( 'l2', 'subscriber' ), make_user( 'l3', 'subscriber' ) );

			wp_set_current_user( $admin );
			$skill = (int) ok_status( call_api( 'POST', 'skills', array( 'name' => $tag . ' skill' ) ), 201, 'skill created' )['id'];

			// An imported exam, published.
			wp_set_current_user( $author );
			$job   = make_review_job( $author, $tag . ' paper', '#' . $skill );
			$state = ok_status( call_api( 'POST', 'exam-imports/jobs/' . $job . '/rows/0/accept', array( 'revision' => 1 ) ), 200, 'row 1 accepted' );
			$state = ok_status( call_api( 'POST', 'exam-imports/jobs/' . $job . '/rows/1/accept', array( 'revision' => $state['revision'] ) ), 200, 'row 2 accepted' );
			$done  = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs/' . $job . '/apply',
					array(
						'revision'         => $state['revision'],
						'title'            => $tag . ' exam',
						'duration_minutes' => 30,
					)
				),
				200,
				'import applied'
			);
			$exam  = (int) $done['exam_id'];
			wp_set_current_user( $admin );
			foreach ( $done['question_ids'] as $question_id ) {
				$identity = \OhMyLMS\QuestionBank\VersionPublisher::identity( $question_id, false );
				ok_status( call_api( 'POST', 'questions/' . $question_id . '/approve', array( 'version_id' => (int) $identity['current_version_id'] ) ), 200, 'version approved' );
			}
			wp_set_current_user( $author );
			$detail = ok_status( call_api( 'GET', 'exams/library/' . $exam ), 200, 'exam read' );
			ok( 'manual' === $detail['results']['mode'] && 0 === $detail['results']['pending'] && false === $detail['released'], 'an imported exam holds results by default' );
			ok_status( call_api( 'POST', 'exams/library/' . $exam . '/publish', array( 'revision' => $detail['revision'] ) ), 200, 'exam published' );
			$quiz = (int) $detail['quiz_id'];

			// Learner 1 and 2 submit (written part waits for marking), learner 3 is still writing.
			$attempts = array();
			foreach ( $learners as $at => $learner ) {
				wp_set_current_user( $learner );
				$attempt = Player::start( $exam );
				ok( is_int( $attempt ), 'learner starts the exam: ' . ( is_wp_error( $attempt ) ? $attempt->get_error_message() : '' ) );
				$attempts[] = $attempt;
				if ( 2 === $at ) {
					continue;
				}
				$answers = array(
					$done['question_ids'][0] => array( 'p1' => '2' ),
					$done['question_ids'][1] => array( 'p1' => 'I added one and one.' ),
				);
				$result  = Submission::submit( $quiz, $attempt, $learner, $answers );
				ok( ! is_wp_error( $result ) && 'in-review' === $result['status'], 'written work waits for marking' );
			}
			list( $a1, $a2, $a3 ) = $attempts;
			wp_set_current_user( $admin );
			ok( ! is_wp_error( \OhMyLMS\Quiz\Review::save( $quiz, $a1, array( $done['question_ids'][1] => array( 'p1' => 2 ) ), 'Marked' ) ), 'teacher marks learner 1' );

			// Held: nothing is visible to learners.
			foreach ( array( $a1, $a2, $a3 ) as $attempt ) {
				ok( false === AssessmentSettings::feedback_visible( $attempt ), "attempt $attempt is held" );
			}
			ok( ! AnswerReview::can_review( array(), array( 'quiz_attempt_id' => $a1, 'status' => 'completed' ) ), 'answer review is held' );
			ok( false === apply_filters( 'ohmylms_quiz_graded_email', true, $quiz, $learners[0] ), 'no graded email (with the score) while results are held' );
			ok( null === Results::feedback_filter( null, 0 ), 'attempts outside exams are left to the quiz policy' );
			ok( 'pending' === Results::learner_state( $a1 )['state'] && 'awaiting_marking' === Results::learner_state( $a2 )['state'], 'learners see pending and awaiting-marking notices' );
			$held = Results::held( array( $a1, $a2, $a3, 999999999 ) );
			sort( $held );
			$all_attempts = array( $a1, $a2, $a3 );
			sort( $all_attempts );
			ok( $all_attempts === $held, 'Results::held() lists every attempt learners may not see (gradebooks use it)' );
			$changed = array();
			add_action(
				'ohmylms_exam_results_changed',
				static function ( $exam_id ) use ( &$changed ) {
					$changed[] = (int) $exam_id;
				}
			);

			wp_set_current_user( $author );
			$view = ok_status( call_api( 'GET', 'exams/library/' . $exam . '/results' ), 200, 'results read' );
			ok( 'manual' === $view['policy']['mode'] && 1 === $view['counts']['pending'] && 1 === $view['counts']['awaiting_marking'] && 1 === $view['counts']['in_progress'] && 3 === $view['counts']['total'], 'counts by release state' );
			$item = result_item( $view, $a1 );
			ok( $item && 'pending' === $item['state'] && true === $item['can_release'] && false === $item['can_revoke'] && 4.0 === (float) $item['max'] && $learners[0] === $item['learner']['id'] && ! isset( $item['learner']['email'] ), 'attempt row: state, actions, marks, learner without email' );

			// Who may read and release.
			wp_set_current_user( 0 );
			ok_error( call_api( 'GET', 'exams/library/' . $exam . '/results' ), 401, 'ohmylms_exam_forbidden', 'guest results' );
			wp_set_current_user( $subscriber );
			ok_error( call_api( 'POST', 'exams/library/' . $exam . '/results/release', array( 'all' => true ) ), 403, 'ohmylms_exam_forbidden', 'subscriber release' );
			wp_set_current_user( $learners[0] );
			ok_error( call_api( 'GET', 'exams/library/' . $exam . '/results' ), 403, 'ohmylms_exam_forbidden', 'learner reads the release list' );
			wp_set_current_user( $other );
			ok_error( call_api( 'POST', 'exams/library/' . $exam . '/results/release', array( 'attempt_ids' => array( $a1 ) ) ), 403, 'ohmylms_exam_forbidden', 'another author releases' );
			ok_error( call_api( 'GET', 'exams/library/' . $exam . '/results/log' ), 403, 'ohmylms_exam_forbidden', 'another author reads the log' );
			wp_set_current_user( $admin );
			ok_error( call_api( 'PUT', 'exams/library/' . $exam . '/results/policy', array( 'mode' => 'immediate' ) ), 403, 'ohmylms_exam_forbidden', 'an administrator who is not the author' );
			ok_error( call_api( 'GET', 'exams/library/999999999/results' ), 404, 'ohmylms_exam_not_found', 'unknown exam' );

			// Validation.
			wp_set_current_user( $author );
			ok_error( call_api( 'POST', 'exams/library/' . $exam . '/results/release', array() ), 422, 'ohmylms_exam_invalid_field', 'release needs attempts or all' );
			ok_error(
				call_api(
					'POST',
					'exams/library/' . $exam . '/results/release',
					array(
						'all'         => true,
						'attempt_ids' => array( $a1 ),
					)
				),
				422,
				'ohmylms_exam_invalid_field',
				'release takes attempts or all, not both'
			);
			ok_error( call_api( 'POST', 'exams/library/' . $exam . '/results/release', array( 'attempt_ids' => array() ) ), 400, 'rest_invalid_param', 'empty attempt list' );
			ok_error( call_api( 'POST', 'exams/library/' . $exam . '/results/release', array( 'attempt_ids' => array( 'x' ) ) ), 400, 'rest_invalid_param', 'attempt IDs are integers' );
			ok_error(
				call_api(
					'POST',
					'exams/library/' . $exam . '/results/release',
					array(
						'all'   => true,
						'extra' => 1,
					)
				),
				400,
				'ohmylms_exam_unknown_field',
				'unknown release field'
			);
			ok_error( call_api( 'PUT', 'exams/library/' . $exam . '/results/policy', array( 'mode' => 'after_close' ) ), 400, 'rest_invalid_param', 'unknown policy' );
			ok_error( call_api( 'PUT', 'exams/library/' . $exam . '/results/policy', array( 'mode' => 'scheduled' ) ), 422, 'ohmylms_exam_results_invalid_policy', 'a date is required' );
			ok_error(
				call_api(
					'PUT',
					'exams/library/' . $exam . '/results/policy',
					array(
						'mode'       => 'scheduled',
						'release_at' => 'tomorrow',
					)
				),
				422,
				'ohmylms_exam_results_invalid_policy',
				'the date is RFC 3339'
			);
			ok_error( call_api( 'POST', 'exams/library/' . $exam . '/results/revoke', array() ), 400, 'rest_missing_callback_param', 'undo needs attempts' );

			// The release email follows its entry under Emails: switched off, nothing is sent and the
			// attempt stays unnotified; edited, the learner gets the edited subject and button link.
			ok( in_array( ResultsEmail::ID, \OhMyLMS\Emails\EmailManager::ids(), true ), 'the release email is listed under Emails' );
			update_option( \OhMyLMS\Emails\Emails::OPTION_PREFIX . ResultsEmail::ID, array( 'enable' => false ) );
			$mails = array();
			ok( 0 === Results::release( $exam, array( $a1 ), true )['notified'] && ! $mails && empty( release_row( $a1 )['notified_at'] ), 'a disabled release email is not sent' );
			ok( array( $a1 ) === Results::revoke( $exam, array( $a1 ) )['revoked'], 'test release undone' );
			update_option(
				\OhMyLMS\Emails\Emails::OPTION_PREFIX . ResultsEmail::ID,
				array(
					'subject'     => 'Edited: {exam_name} for {student_name}',
					'button_link' => 'https://example.invalid/results',
				)
			);
			$mails = array();
			ok( 1 === Results::release( $exam, array( $a1 ), true )['notified'] && 1 === count( $mails ) && 'Edited: ' . $tag . ' exam for ' . $tag . ' l1' === $mails[0]['subject'] && false !== strpos( $mails[0]['message'], 'https://example.invalid/results' ), 'the edited subject and button link are sent: ' . ( $mails[0]['subject'] ?? '' ) );
			ok( array( $a1 ) === Results::revoke( $exam, array( $a1 ) )['revoked'], 'test release undone again' );
			$wpdb->update( Results::table(), array( 'notified_at' => null ), array( 'attempt_id' => $a1 ) );
			delete_option( \OhMyLMS\Emails\Emails::OPTION_PREFIX . ResultsEmail::ID );

			// Release one attempt; the others are skipped with a reason.
			$mails    = array();
			$released = ok_status(
				call_api(
					'POST',
					'exams/library/' . $exam . '/results/release',
					array( 'attempt_ids' => array( $a1, $a2, $a3, 999999999 ) )
				),
				200,
				'release chosen attempts'
			);
			$reasons  = array_column( $released['skipped'], 'reason', 'id' );
			ok( array( $a1 ) === $released['released'] && 'awaiting_marking' === $reasons[ $a2 ] && 'in_progress' === $reasons[ $a3 ] && 'not_found' === $reasons[999999999] && 1 === $released['notified'] && 0 === $released['remaining'], 'only the marked attempt is released: ' . wp_json_encode( $released ) );
			ok( 1 === count( $mails ) && $tag . '_l1@example.invalid' === $mails[0]['to'] && false !== strpos( $mails[0]['subject'], $tag . ' exam' ) && false === strpos( $mails[0]['message'], '4' . ' / ' ) && false !== strpos( $mails[0]['message'], 'ohmylms_exam=' . $exam ), 'one notification to the learner, linking to the exam, without marks' );
			ok( true === AssessmentSettings::feedback_visible( $a1 ) && false === AssessmentSettings::feedback_visible( $a2 ), 'released attempt visible, others still held' );
			ok( ! in_array( $a1, Results::held( array( $a1, $a2 ) ), true ) && in_array( $a2, Results::held( array( $a1, $a2 ) ), true ), 'Results::held() follows the release' );
			$changed = array();
			ok( true === apply_filters( 'ohmylms_quiz_graded_email', true, $quiz, $learners[0] ), 'graded email allowed once released' );
			$again = ok_status( call_api( 'POST', 'exams/library/' . $exam . '/results/release', array( 'attempt_ids' => array( $a1 ) ) ), 200, 'release again' );
			ok( ! $again['released'] && 'already_visible' === $again['skipped'][0]['reason'], 'releasing twice changes nothing' );
			ok( array() === $changed, 'a release that releases nothing announces no change' );

			// Undo before the learner looked.
			$mails   = array();
			$revoked = ok_status( call_api( 'POST', 'exams/library/' . $exam . '/results/revoke', array( 'attempt_ids' => array( $a1, $a2 ) ) ), 200, 'undo release' );
			ok( array( $a1 ) === $revoked['revoked'] && 'not_released' === $revoked['skipped'][0]['reason'], 'undo applies to the released attempt only' );
			ok( array( $exam ) === $changed, 'an undo announces the change once' );
			ok( false === AssessmentSettings::feedback_visible( $a1 ), 'undone release is held again' );
			$released = ok_status( call_api( 'POST', 'exams/library/' . $exam . '/results/release', array( 'attempt_ids' => array( $a1 ) ) ), 200, 'release after undo' );
			ok( array( $a1 ) === $released['released'] && 0 === $released['notified'] && ! $mails, 'a learner is notified once' );

			// The learner looks; the release can no longer be undone.
			wp_set_current_user( $learners[1] );
			ok( false === Results::claim_view( $a2 ), 'held results cannot be claimed' );
			wp_set_current_user( $learners[0] );
			ok( true === Results::claim_view( $a1 ) && ! empty( release_row( $a1 )['viewed_at'] ), 'the first view is recorded' );
			wp_set_current_user( $author );
			$revoked = ok_status( call_api( 'POST', 'exams/library/' . $exam . '/results/revoke', array( 'attempt_ids' => array( $a1 ) ) ), 200, 'undo after view' );
			ok( ! $revoked['revoked'] && 'viewed' === $revoked['skipped'][0]['reason'] && true === AssessmentSettings::feedback_visible( $a1 ), 'seen results stay released' );

			// An undo that wins the race is never shown.
			ok( ! is_wp_error( \OhMyLMS\Quiz\Review::save( $quiz, $a2, array( $done['question_ids'][1] => array( 'p1' => 1 ) ), 'Marked' ) ), 'teacher marks learner 2' );
			ok( 'pending' === Results::learner_state( $a2 )['state'], 'marked attempt waits for release' );
			$all = ok_status(
				call_api(
					'POST',
					'exams/library/' . $exam . '/results/release',
					array(
						'all'    => true,
						'notify' => false,
					)
				),
				200,
				'release all pending'
			);
			ok( array( $a2 ) === $all['released'] && ! $all['skipped'] && 0 === $all['notified'] && 0 === $all['counts']['pending'], 'bulk release takes every pending attempt, without email when asked' );
			ok_status( call_api( 'POST', 'exams/library/' . $exam . '/results/revoke', array( 'attempt_ids' => array( $a2 ) ) ), 200, 'undo learner 2' );
			wp_set_current_user( $learners[1] );
			ok( false === Results::claim_view( $a2 ) && empty( release_row( $a2 )['viewed_at'] ), 'a view after the undo shows nothing' );

			// Policies.
			wp_set_current_user( $author );
			$changed = array();
			$policy  = ok_status( call_api( 'PUT', 'exams/library/' . $exam . '/results/policy', array( 'mode' => 'immediate' ) ), 200, 'immediate policy' );
			ok( array( $exam ) === $changed, 'a policy change announces the change' );
			ok( 'immediate' === $policy['policy']['mode'] && 'released' === result_item( $policy, $a2 )['state'] && true === AssessmentSettings::feedback_visible( $a2 ), 'immediate policy shows results' );
			$revoked = ok_status( call_api( 'POST', 'exams/library/' . $exam . '/results/revoke', array( 'attempt_ids' => array( $a2 ) ) ), 200, 'undo under immediate' );
			ok( 'policy' === $revoked['skipped'][0]['reason'], 'policy releases are changed through the policy' );
			$future = gmdate( 'Y-m-d\TH:i:s\Z', time() + DAY_IN_SECONDS );
			$policy = ok_status(
				call_api(
					'PUT',
					'exams/library/' . $exam . '/results/policy',
					array(
						'mode'       => 'scheduled',
						'release_at' => $future,
					)
				),
				200,
				'scheduled policy'
			);
			ok( $future === $policy['policy']['release_at'] && false === AssessmentSettings::feedback_visible( $a2 ) && true === AssessmentSettings::feedback_visible( $a1 ) && wp_next_scheduled( Results::HOOK, array( $exam ) ), 'a future date holds unseen results, keeps seen ones and schedules the release' );
			$state = Results::learner_state( $a2 );
			ok( 'scheduled' === $state['mode'] && gmdate( 'Y-m-d H:i:s', strtotime( $future ) ) === $state['release_at'], 'learners see the release date' );
			$past = gmdate( 'Y-m-d\TH:i:s\Z', time() - 60 );
			ok_status(
				call_api(
					'PUT',
					'exams/library/' . $exam . '/results/policy',
					array(
						'mode'       => 'scheduled',
						'release_at' => $past,
					)
				),
				200,
				'past date'
			);
			ok( true === AssessmentSettings::feedback_visible( $a2 ), 'a past date shows results' );
			$mails   = array();
			$changed = array();
			Results::run_schedule( $exam );
			ok( 'schedule' === release_row( $a2 )['source'] && ! empty( release_row( $a2 )['notified_at'] ) && 1 === count( $mails ) && $tag . '_l2@example.invalid' === $mails[0]['to'], 'the scheduled release is recorded and notified once' );
			ok( array( $exam ) === $changed, 'the scheduled release announces the change' );
			$mails   = array();
			$changed = array();
			Results::run_schedule( $exam );
			ok( ! $mails && ! $changed, 'running the schedule again sends and announces nothing' );
			ok_status( call_api( 'PUT', 'exams/library/' . $exam . '/results/policy', array( 'mode' => 'manual' ) ), 200, 'back to manual' );
			ok( false === wp_next_scheduled( Results::HOOK, array( $exam ) ) && true === AssessmentSettings::feedback_visible( $a2 ), 'manual clears the schedule; a recorded release stays' );

			// The legacy release route releases every pending attempt through the same service.
			wp_set_current_user( $learners[2] );
			$answers = array(
				$done['question_ids'][0] => array( 'p1' => '3' ),
				$done['question_ids'][1] => array( 'p1' => 'Guess.' ),
			);
			ok( ! is_wp_error( Submission::submit( $quiz, $a3, $learners[2], $answers ) ), 'learner 3 submits' );
			wp_set_current_user( $admin );
			ok( ! is_wp_error( \OhMyLMS\Quiz\Review::save( $quiz, $a3, array( $done['question_ids'][1] => array( 'p1' => 0 ) ), 'Marked' ) ), 'teacher marks learner 3' );
			wp_set_current_user( $author );
			ok_status( call_api( 'POST', 'exams/' . $exam . '/release' ), 200, 'legacy release route' );
			ok( 'manual' === release_row( $a3 )['source'] && true === AssessmentSettings::feedback_visible( $a3 ), 'legacy release is a logged manual release' );
			$summary = ok_status( call_api( 'GET', 'exams/library/' . $exam ), 200, 'exam summary' );
			ok( true === $summary['released'] && 0 === $summary['results']['pending'], 'every attempt released' );

			// The log.
			$log     = call_api( 'GET', 'exams/library/' . $exam . '/results/log', array( 'per_page' => 100 ) );
			$entries = ok_status( $log, 200, 'log read' );
			$actions = array_count_values( array_column( $entries, 'action' ) );
			ok( 'private, no-store' === $log->get_headers()['Cache-Control'] && (int) $log->get_headers()['X-WP-Total'] === count( $entries ), 'log is private and paged' );
			ok( 7 === $actions['release'] && 4 === $actions['revoke'] && 4 === $actions['policy'] && 4 === $actions['notify'] && 1 === $actions['view'], 'every release, undo, policy change, notification and first view is logged: ' . wp_json_encode( $actions ) );
			$first_view = current( wp_list_filter( $entries, array( 'action' => 'view' ) ) );
			ok( $a1 === $first_view['attempt_id'] && $learners[0] === $first_view['actor']['id'], 'a view is logged with the learner' );
			ok_error( call_api( 'GET', 'exams/library/' . $exam . '/results', array( 'page' => 9 ) ), 400, 'ohmylms_exam_invalid_page', 'results page past the end' );

			throw new RuntimeException( 'exam_results_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'exam_results_rollback_success' !== $error->getMessage() ) {
		echo $error->getMessage() . "\n";
		$exit_code = 1;
	}
} finally {
	wp_set_current_user( 0 );
}

ok( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login=%s", $tag . '_author' ) ), 'fixture transaction rolled back' );
echo ( empty( $exit_code ) ? 'OK' : 'FAILED' ) . ": $checks exam results checks; fixture writes rolled back.\n";
exit( isset( $exit_code ) ? $exit_code : 0 );
