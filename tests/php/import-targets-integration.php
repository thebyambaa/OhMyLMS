<?php
/**
 * Import targets rehearsal: rows become an exam or a quiz through the same import job. All fixture
 * writes are rolled back.
 *
 * Run with OHMYLMS_TEST_CREDENTIALS, or pass --local for this checkout's Local site.
 *
 * @package OhMyLMS
 */

use OhMyLMS\Assessment\Schema;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI ) {
	exit;
}
$local = in_array( '--local', $argv, true );
$site  = $local ? dirname( __DIR__, 5 ) : json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true )['site']; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI-local credentials file, never a URL.
if ( $local ) {
	define( 'DISABLE_WP_CRON', true );
}
$_SERVER['HTTP_HOST']   = '800.local';
$_SERVER['REQUEST_URI'] = '/';
require $site . '/wp-load.php';
if ( $local ? ( 'xyz' !== basename( dirname( ABSPATH, 2 ) ) || 'local' !== wp_get_environment_type() ) : ( ! defined( 'OHMYLMS_TEST_SITE' ) || 'ohmylms_source_test' !== DB_NAME ) ) {
	throw new RuntimeException( 'Requires the explicit Local checkout or disposable test site.' );
}
if ( ! Schema::ready() || wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'Requires installed assessment schema and request-local object cache.' );
}
foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Read current engines to guarantee fixture rollback; cached state is unsafe.
	if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && 'InnoDB' !== $table['Engine'] ) {
		throw new RuntimeException( 'Requires InnoDB tables.' );
	}
}
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'rehearsal', 'Network disabled.' );
	},
	PHP_INT_MAX
);
$checks      = 0;
$check       = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition || is_wp_error( $condition ) ) {
		throw new RuntimeException( esc_html( $message . ( is_wp_error( $condition ) ? ': ' . $condition->get_error_message() : '' ) ) );
	}
	++$checks;
};
$request     = static function ( $method, $path, $data = array(), $expect = 299 ) use ( $check ) {
	$req = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$req->set_body_params( $data );
	$res = rest_do_request( $req );
	$check( $res->get_status() <= $expect, $path . ': ' . wp_json_encode( $res->get_data() ) );
	return $res;
};
$fixture_tag = 'import-targets-' . wp_generate_uuid4();
try {
	Transaction::run(
		static function () use ( $check, $request, $fixture_tag ) {
			global $wpdb;
			$admin = wp_insert_user(
				array(
					'user_login' => $fixture_tag,
					'user_pass'  => wp_generate_password(),
					'role'       => 'administrator',
				)
			);
			$check( $admin, 'Create fixture administrator' );
			wp_set_current_user( $admin );
			$skill = (int) $request( 'POST', 'skills', array( 'name' => $fixture_tag . 'skill' ) )->get_data()['id'];
			$text  = "exam,paper,number,type,question,answers,correct,skills\n"
				. $fixture_tag . ',Paper,1,single-choice,"What is 2+2?",3|4|5,B,#' . $skill . "\n"
				. $fixture_tag . ',Paper,2,single-choice,"What is 3+3?",5|6|7,B,#' . $skill . "\n";

			// A broken row stops everything unless invalid rows are skipped.
			$broken = $request(
				'POST',
				'exam-imports/rows',
				array(
					'text'   => $text . $fixture_tag . ",Paper,3,single-choice,\"Bad\",1|2,A,NO.SUCH.SKILL\n",
					'format' => 'csv',
					'target' => 'exam',
				),
				499
			);
			$check( 400 === $broken->get_status() && 'exam_questions' === $broken->get_data()['code'], 'Rows with errors are refused whole' );

			// Rows that name no skill are refused, unless the import was started for a skill.
			$no_skill = "exam,paper,number,type,question,answers,correct\n" . $fixture_tag . ",Paper S,1,single-choice,\"What is 1+1?\",1|2|3,B\n";
			$refused  = $request(
				'POST',
				'question-bank/import',
				array(
					'text'    => $no_skill,
					'format'  => 'csv',
					'dry_run' => true,
				)
			)->get_data();
			$check( 1 === $refused['counts']['error'], 'A row without a skill is an error' );
			$filled = $request(
				'POST',
				'question-bank/import',
				array(
					'text'           => $no_skill,
					'format'         => 'csv',
					'dry_run'        => true,
					'default_skills' => '#' . $skill,
				)
			)->get_data();
			$check( 0 === $filled['counts']['error'] && 1 === $filled['counts']['create'], 'The chosen skill fills a row without one' );
			$check( '#5;#7' === Jobs::skill_tokens( '5, 7; x ' ) && '' === Jobs::skill_tokens( 'none' ) && 20 === substr_count( Jobs::skill_tokens( implode( ',', range( 1, 30 ) ) ), '#' ), 'Skill references are limited to IDs, at most 20' );

			// Rows become a committed exam job, ready for the exam details step.
			$job = $request(
				'POST',
				'exam-imports/rows',
				array(
					'text'   => $text,
					'format' => 'csv',
					'target' => 'exam',
					'name'   => 'Sample paper',
				)
			)->get_data();
			$check( 'committed' === $job['state'] && 'exam' === $job['target'] && 2 === count( $job['question_ids'] ) && 2 === count( $job['version_ids'] ), 'Exam rows give a committed job' );
			foreach ( $job['question_ids'] as $question_id ) {
				$check( 1 === (int) \OhMyLMS\QuestionBank\BankAttributes::get( $question_id )['secure'], 'Exam questions are secure' );
			}
			$exam = $request(
				'POST',
				'exams',
				array(
					'job_id'           => $job['id'],
					'title'            => 'Sample paper',
					'duration_minutes' => 45,
				)
			)->get_data();
			$check( 45 === (int) $exam['duration_minutes'] && 2 === count( $exam['question_ids'] ), 'Exam is created from the imported rows' );

			// Other rows as a quiz: no secure flag, and the quiz links the questions in order.
			$quiz_text = str_replace( ',Paper,', ',Paper B,', $text );
			$quiz_job = $request(
				'POST',
				'exam-imports/rows',
				array(
					'text'   => $quiz_text,
					'format' => 'csv',
					'target' => 'quiz',
					'name'   => 'Sample quiz',
				)
			)->get_data();
			$check( 'quiz' === $quiz_job['target'], 'Quiz target is kept' );
			$refused = $request( 'POST', 'exam-imports/' . $quiz_job['id'] . '/quiz', array( 'title' => '' ), 499 );
			$check( 400 === $refused->get_status(), 'A quiz needs a title' );
			$exam_refused = $request( 'POST', 'exam-imports/' . $job['id'] . '/quiz', array( 'title' => 'Nope' ), 499 );
			$check( 409 === $exam_refused->get_status(), 'An exam job cannot become a quiz' );
			$made    = $request( 'POST', 'exam-imports/' . $quiz_job['id'] . '/quiz', array( 'title' => 'Sample quiz' ) )->get_data();
			$quiz_id = (int) $made['quiz_id'];
			$check( $quiz_id > 0 && get_post( $quiz_id ), 'A quiz was created' );
			$linked = $wpdb->get_col( $wpdb->prepare( "SELECT question_id FROM {$wpdb->prefix}ohmylms_quiz_questions_relationship WHERE quiz_id=%d ORDER BY order_number", $quiz_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify the persisted fixture links without cache.
			$check( array_map( 'intval', $linked ) === array_map( 'intval', $quiz_job['question_ids'] ), 'The quiz links the questions in order' );
			$again = $request( 'POST', 'exam-imports/' . $quiz_job['id'] . '/quiz', array( 'title' => 'Again' ) )->get_data();
			$check( (int) $again['quiz_id'] === $quiz_id, 'Creating the quiz twice returns the same quiz' );
			foreach ( $quiz_job['question_ids'] as $question_id ) {
				$check( empty( \OhMyLMS\QuestionBank\BankAttributes::get( $question_id )['secure'] ), 'Quiz questions stay usable in practice' );
			}

			// The target defaults to an exam, and unknown values cannot widen it.
			$check( 'exam' === Jobs::target( '' ) && 'exam' === Jobs::target( 'admin' ) && 'quiz' === Jobs::target( 'quiz' ) && 'questions' === Jobs::target( 'questions' ), 'Targets are limited to exam, quiz and questions' );

			// Another user cannot build from this job.
			wp_set_current_user( 0 );
			$guest = $request( 'POST', 'exam-imports/' . $quiz_job['id'] . '/quiz', array( 'title' => 'x' ), 499 );
			$check( $guest->get_status() >= 400, 'Guests cannot use an import job' );
			throw new RuntimeException( 'rehearsal_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'rehearsal_rollback_success' !== $error->getMessage() ) {
		throw $error;
	}
} finally {
	wp_set_current_user( 0 );
}
$check( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login=%s", $fixture_tag ) ), 'Fixture transaction rolled back' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify rollback against the actual database.
echo (int) $checks . " import target checks passed; fixture writes rolled back.\n";
