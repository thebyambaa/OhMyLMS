<?php
/**
 * Disposable WordPress multipart PDF-to-exam rehearsal with a mocked AI transport.
 * Run via PHP loopback server, then POST one synthetic PDF as multipart field file.
 *
 * @package OhMyLMS
 */

use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\ExamImport\Workflow;
use OhMyLMS\ExamImport\Extraction;
use OhMyLMS\Exams\Library;
use OhMyLMS\Exams\Player;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Quiz\Review;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Utility\Transaction;

if ( 'cli-server' !== PHP_SAPI || '127.0.0.1' !== ( $_SERVER['REMOTE_ADDR'] ?? '' ) || ! getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Pre-bootstrap exact loopback gate.
	http_response_code( 404 );
	exit;
}
$config = json_decode( file_get_contents( getenv( 'OHMYLMS_TEST_CREDENTIALS' ) ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI-local configuration.
require $config['site'] . '/wp-load.php';
if ( ! defined( 'OHMYLMS_TEST_SITE' ) || 'ohmylms_source_test' !== DB_NAME || wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'Requires disposable transactional WordPress.' );
}
$private_dir = sys_get_temp_dir() . '/ohmylms-pdf-exam-' . wp_generate_uuid4();
mkdir( $private_dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Temporary private fixture directory.
define( 'OHMYLMS_IMPORT_PRIVATE_DIR', $private_dir );
$admin = get_user_by( 'login', $config['username'] )->ID;
wp_set_current_user( $admin );
$checks    = 0;
$check     = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	} ++$checks;
};
$call      = static function ( $method, $path, $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
};
$saved_key = getenv( 'OHMYLMS_AI_API_KEY' );
putenv( 'OHMYLMS_AI_API_KEY=synthetic-not-a-real-key' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Synthetic fixture key exists only for this isolated request.
$network_calls = 0;
$skill_token   = '';
$uploaded      = $_FILES['file'] ?? array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CLI-server fixture passes raw multipart data through the real upload validator.
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$network_calls, &$skill_token ) {
		if ( 'https://api.openai.com/v1/responses' !== $url ) {
			return new WP_Error( 'fixture_network', 'External HTTP blocked.' ); }
		++$network_calls;
		$body = json_decode( $args['body'], true );
		if ( 2 !== count(
			array_filter(
				$body['input'][0]['content'],
				static function ( $part ) {
					return 'input_file' === $part['type'];
				}
			)
		) ) {
			throw new RuntimeException( 'Both PDFs must use native file inputs.' ); }
		if ( 1 === $network_calls || 4 === $network_calls ) {
			$data = array(
				'title'            => 'Synthetic six-question exam',
				'duration_minutes' => 30,
				'total_marks'      => 18,
				'page_count'       => 2,
				'question_numbers' => array( '1', '2', '3', '4', '5', '6' ),
			);
		} else {
			$numbers = in_array( $network_calls, array( 2, 5 ), true ) ? range( 1, 5 ) : array( 6 );
			$data    = array(
				'questions' => array_map(
					static function ( $number ) use ( &$skill_token ) {
						return array(
							'number'          => (string) $number,
							'question'        => 'Find 2 + 3.',
							'page'            => 1,
							'skills'          => $skill_token,
							'requires_source' => false,
							'reviewed'        => true,
							'parts'           => array(
								array(
									'id'     => 'a_i',
									'label'  => '(a)(i)',
									'prompt' => 'Number?',
									'kind'   => 'numerical',
									'marks'  => 2,
									'answer' => 5,
									'skills' => '',
								),
								array(
									'id'     => 'b',
									'kind'   => 'written',
									'prompt' => 'Explain.',
									'marks'  => 1,
									'rubric' => 'PRIVATE teacher rubric',
									'skills' => '',
								),
							),
						);
					},
					$numbers
				),
			);
		}
		return array(
			'headers'  => array(),
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'status' => 'completed',
					'output' => array(
						array(
							'type'    => 'message',
							'content' => array(
								array(
									'type' => 'output_text',
									'text' => wp_json_encode( $data ),
								),
							),
						),
					),
					'usage'  => array(
						'input_tokens'  => 100,
						'output_tokens' => 50,
					),
				)
			),
		);
	},
	PHP_INT_MAX,
	3
);
try {
	Transaction::run(
		static function () use ( $call, $check, $admin, $uploaded, &$skill_token, &$network_calls ) {
			global $wpdb;
			update_option(
				'ohmylms_ai',
				array(
					'enabled'                => false,
					'exam_converter_enabled' => true,
					'provider'               => 'openai',
					'model'                  => 'synthetic-pdf-fixture',
					'max_tokens'             => 400,
					'timeout'                => 20,
				)
			);
			$skill       = $call( 'POST', 'skills', array( 'name' => 'PDF exam fixture ' . wp_generate_uuid4() ) )->get_data();
			$skill_token = '#' . $skill['id'];
			$request     = new WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports' );
			$request->set_file_params(
				array(
					'file'        => $uploaded,
					'mark_scheme' => $uploaded,
				)
			); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Disposable CLI-server fixture; normal REST authentication stays unchanged.
			$response = rest_do_request( $request );
			$check( 201 === $response->get_status(), 'Upload: ' . wp_json_encode( $response->get_data() ) );
			$id = $response->get_data()['id'];
			Jobs::process( $id );
			$settings = get_option( 'ohmylms_ai' );
			$call(
				'POST',
				'ai',
				array_merge(
					$settings,
					array(
						'enabled'                => true,
						'exam_converter_enabled' => false,
					)
				)
			);
			$check( 503 === $call( 'POST', 'exam-imports/' . $id . '/extract' )->get_status() && 0 === $network_calls, 'Tutor enabled does not permit PDF extraction when converter is off' );
			$call( 'POST', 'ai', $settings );
			$response = $call( 'POST', 'exam-imports/' . $id . '/extract' );
			$check( 200 === $response->get_status(), 'Start extraction: ' . wp_json_encode( $response->get_data() ) );
			$repeated = $call( 'POST', 'exam-imports/' . $id . '/extract' );
			$check( 200 === $repeated->get_status() && 'extracting' === $repeated->get_data()['state'], 'Repeated resume keeps an already queued exact token active' );
			$job        = get_post_meta( $id, Jobs::META, true );
			$generation = $job['generation'];
			$call( 'POST', 'ai', array_merge( $settings, array( 'exam_converter_enabled' => false ) ) );
			Workflow::cancel( $id ); // Simulate dequeuing the action before its worker runs.
			Workflow::process( $id, $generation, -1 );
			$check( 'extraction_failed' === get_post_meta( $id, Jobs::META, true )['state'] && 0 === $network_calls, 'Queued extraction respects a disabled converter without provider traffic' );
			$call( 'POST', 'ai', $settings );
			$call( 'POST', 'exam-imports/' . $id . '/extract' );
			$generation      = get_post_meta( $id, Jobs::META, true )['generation'];
			$running_actions = as_get_scheduled_actions(
				array(
					'hook'     => Workflow::HOOK,
					'args'     => array( $id, $generation, -1 ),
					'group'    => Jobs::GROUP,
					'status'   => 'pending',
					'per_page' => 1,
				),
				'ids'
			);
			$wpdb->update( $wpdb->prefix . 'actionscheduler_actions', array( 'status' => 'in-progress' ), array( 'action_id' => $running_actions[0] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Simulate the real worker's running status inside the rolled-back fixture.
			Workflow::process( $id, $generation, -1 );
			$check( 'extracting' === get_post_meta( $id, Jobs::META, true )['state'] && as_has_scheduled_action( Workflow::HOOK, array( $id, $generation, 0 ), Jobs::GROUP ), 'A running stage can schedule its next cursor' );
			Workflow::process( $id, $generation, 0 );
			Workflow::process( $id, $generation, 5 );
			$job = get_post_meta( $id, Jobs::META, true );
			$check( 'ready_for_review' === $job['state'] && 6 === count( $job['draft'] ) && 3 === $network_calls, 'Batched extraction keeps every question' );
			$check( 0 === count( array_filter( array_column( $job['draft'], 'reviewed' ) ) ), 'AI cannot approve its own extracted work' );
			Workflow::process( $id, $generation, 0 );
			$check( 3 === $network_calls && 300 === $job['usage']['input_tokens'], 'Stale scheduler delivery is harmless; usage counted' );
			$check( 409 === $call( 'POST', 'exam-imports/' . $id . '/commit', array( 'revision' => 1 ) )->get_status(), 'Commit before review rejected' );
			$draft = $job['draft'];
			foreach ( $draft as &$row ) {
				$row['reviewed'] = true;
				$row['figures_complete'] = true;
			} unset( $row );
			$draft[0]['question']           = 'TEACHER EDIT: find 2 + 3.';
			$draft[0]['parts'][0]['prompt'] = 'Find $\\frac{10}{2}$. Keep \\(x\\) unchanged.';
			$response                       = $call(
				'POST',
				'exam-imports/' . $id . '/review',
				array(
					'revision'  => 1,
					'questions' => $draft,
				)
			);
			$check( 200 === $response->get_status(), 'Save review: ' . wp_json_encode( $response->get_data() ) );
			$check(
				409 === $call(
					'POST',
					'exam-imports/' . $id . '/review',
					array(
						'revision'  => 1,
						'questions' => $draft,
					)
				)->get_status(),
				'Conflicting review rejected'
			);
			$call( 'POST', 'exam-imports/' . $id . '/extract' );
			$new_generation = get_post_meta( $id, Jobs::META, true )['generation'];
			Workflow::process( $id, $new_generation, -1 );
			Workflow::process( $id, $new_generation, 0 );
			Workflow::process( $id, $new_generation, 5 );
			$job = get_post_meta( $id, Jobs::META, true );
			$check( $draft === $job['draft'] && 2 === $job['draft_revision'], 'Re-extraction preserves teacher edits and reviewed flags' );
			$response = $call( 'POST', 'exam-imports/' . $id . '/commit', array( 'revision' => 2 ) );
			$check( 200 === $response->get_status(), 'Commit: ' . wp_json_encode( $response->get_data() ) );
			$job = get_post_meta( $id, Jobs::META, true );
			$check( 6 === count( $job['question_ids'] ), 'Every question committed once' );
			$stored_settings = get_post_meta( $job['question_ids'][0], '_question_settings', true );
			$check( $draft[0]['parts'][0]['prompt'] === $stored_settings['parts'][0]['prompt'], 'Nested part prompts preserve LaTeX backslashes through the WordPress writer' );
			$again = $call( 'POST', 'exam-imports/' . $id . '/commit', array( 'revision' => 2 ) )->get_data();
			$check( $again['question_ids'] === $job['question_ids'], 'Repeat commit returns same questions' );
			$response = $call(
				'POST',
				'exams',
				array(
					'job_id'           => $id,
					'title'            => 'Synthetic exam fixture',
					'duration_minutes' => 30,
				)
			);
			$check( 200 === $response->get_status(), 'Exam create: ' . wp_json_encode( $response->get_data() ) );
			$exam    = $response->get_data();
			$exam_id = $exam['id'];
			$check( $job['version_ids'] === $exam['version_ids'] && (int) $id === $exam['job_id'], 'Paper review exposes exact pinned versions and the owned source job' );
			parse_str( wp_parse_url( $exam['preview_url'], PHP_URL_QUERY ), $preview_args );
			$check( (int) $exam['quiz_id'] === (int) $preview_args['ohmylms_quiz_preview'] && wp_verify_nonce( $preview_args['_wpnonce'], 'ohmylms_quiz_preview_' . $exam['quiz_id'] ), 'Draft exams expose the authenticated non-recording quiz preview' );
			$check( 'draft' === $exam['status'] && 18.0 === (float) $exam['total_marks'] && 6 === count( $exam['question_ids'] ), 'Draft exam contains the complete paper' );
			$check( 409 === $call( 'POST', 'exams/' . $exam_id . '/publish' )->get_status(), 'Publication requires approved question versions' );
			foreach ( $job['version_ids'] as $question_id => $version_id ) {
				$check( 200 === $call( 'POST', 'question-bank/' . $question_id . '/approve', array( 'version_id' => $version_id ) )->get_status(), 'Approve exact version' ); }
			$check( 200 === $call( 'POST', 'exams/' . $exam_id . '/publish' )->get_status(), 'Publish approved exam' );
			$branch = \OhMyLMS\Curriculum\Items::create(
				array(
					'name'      => 'PDF bank scope fixture',
					'item_type' => 'framework',
				)
			);
			$child  = \OhMyLMS\Curriculum\Items::create(
				array(
					'name'      => 'Nested topic',
					'parent_id' => (int) $branch['id'],
				)
			);
			\OhMyLMS\Curriculum\Links::add( $child['id'], 'skill', $skill['id'] );
			$scope = $call(
				'GET',
				'question-bank',
				array(
					'organisation' => 'curriculum',
					'scope'        => $branch['id'],
				)
			)->get_data();
			$check( 6 === $scope['total'], 'Curriculum branch includes descendant skill questions without copying them' );
			$empty        = \OhMyLMS\Curriculum\Items::create( array( 'name' => 'Empty bank scope' ) );
			$empty_result = $call(
				'GET',
				'question-bank',
				array(
					'organisation' => 'curriculum',
					'scope'        => $empty['id'],
				)
			)->get_data();
			$check(
				0 === $empty_result['total'],
				'Empty branch fails closed: ' . wp_json_encode(
					array(
						'empty'  => $empty,
						'result' => $empty_result,
					)
				)
			);
			$roadmap = \OhMyLMS\Curriculum\Items::create(
				array(
					'name'      => 'Roadmap bank scope',
					'item_type' => 'roadmap',
				)
			);
			\OhMyLMS\Curriculum\Links::add( $roadmap['id'], 'skill', $skill['id'] );
			$check(
				6 === $call(
					'GET',
					'question-bank',
					array(
						'organisation' => 'roadmap',
						'scope'        => $roadmap['id'],
					)
				)->get_data()['total'],
				'Roadmap skill references discover the same reusable questions'
			);
			$exam_data = get_post_meta( $exam_id, Library::META, true );
			$check(
				6 === $call(
					'GET',
					'question-bank',
					array(
						'organisation' => 'course',
						'scope'        => $exam_data['course_id'],
					)
				)->get_data()['total'],
				'Course placements discover the same questions'
			);
			$extension = static function ( $references, $view ) use ( $skill ) {
				return 'fixture' === $view ? array( 'skills' => array( $skill['id'] ) ) : $references;
			};
				add_filter( 'ohmylms_question_bank_resolve_scope', $extension, 10, 2 );
				$check(
					6 === $call(
						'GET',
						'question-bank',
						array(
							'organisation' => 'fixture',
							'scope'        => 1,
						)
					)->get_data()['total'],
					'Extension scope resolves existing identities'
				);
			$other_author = wp_insert_user(
				array(
					'user_login' => 'pdf-author-' . wp_generate_uuid4(),
					'user_pass'  => wp_generate_password(),
					'role'       => 'author',
				)
			);
			wp_set_current_user( $other_author );
			$check(
				0 === $call(
					'GET',
					'question-bank',
					array(
						'organisation' => 'fixture',
						'scope'        => 1,
					)
				)->get_data()['total'],
				'Organisation cannot expand question visibility'
			);
			wp_set_current_user( $admin );
			remove_filter( 'ohmylms_question_bank_resolve_scope', $extension, 10 );
			$student = wp_insert_user(
				array(
					'user_login' => 'pdf-learner-' . wp_generate_uuid4(),
					'user_pass'  => wp_generate_password(),
					'role'       => 'subscriber',
				)
			);
			wp_set_current_user( $student );
			$check( is_wp_error( Player::source( $exam_id, true, false ) ), 'Paper unavailable before starting' );
			$attempt = Player::start( $exam_id );
			$check( is_int( $attempt ), 'Standalone exam starts: ' . ( is_wp_error( $attempt ) ? $attempt->get_error_message() : '' ) );
			$check( is_array( Player::source( $exam_id, true, false ) ) && is_wp_error( Player::source( $exam_id, true, true ) ), 'Paper accessible during exam; mark scheme private' );
			$delivery = AttemptItems::delivery( $attempt );
			$check( 6 === count( $delivery ) && false === strpos( wp_json_encode( $delivery ), 'PRIVATE teacher rubric' ), 'Shared engine delivers all frozen learner-safe questions' );
			$check( null !== AttemptItems::context( $attempt )['deadline_at'], 'Exam gets server deadline' );
			$answers = array();
			foreach ( $job['question_ids'] as $question_id ) {
				$answers[ $question_id ] = array(
					'a_i' => '5',
					'b'   => 'I added two and three.',
				); }
			$result = Submission::submit( $exam['quiz_id'], $attempt, $student, $answers );
			$check( ! is_wp_error( $result ) && 12.0 === (float) $result['total'] && 'in-review' === $result['status'], 'Shared grading preserves auto marks while written work awaits review' );
			wp_set_current_user( $admin );
			$marks = array();
			foreach ( $job['question_ids'] as $question_id ) {
				$marks[ $question_id ] = array( 'b' => 1 ); }
			$review = Review::save( $exam['quiz_id'], $attempt, $marks, 'Synthetic PDF fixture' );
			Evidence::process();
			$check( ! is_wp_error( $review ), 'Existing teacher marking completes exam' );
			$check( 12 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE student_id=%d AND superseded=0 AND role=%s', Schema::table( 'skill_evidence' ), $student, 'primary' ) ), 'Exam evidence remains per part' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Verify actual fixture evidence.
			$check( 200 === $call( 'POST', 'exams/' . $exam_id . '/release' )->get_status(), 'Explicit results release' );
			wp_set_current_user( $student );
			$check( is_array( Player::source( $exam_id, true, true ) ), 'Mark scheme visible after submission and release' );
			$check( 403 === $call( 'GET', 'exam-imports/' . $id )->get_status(), 'Learner cannot read owner import review' );
			wp_set_current_user( 0 );
			$check( is_wp_error( Player::source( $exam_id, true, true ) ), 'Guest cannot read released private document' );
			throw new RuntimeException( 'rehearsal_rollback_success' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'rehearsal_rollback_success' !== $failure->getMessage() ) {
		http_response_code( 500 );
		$failure_message = $failure->getMessage(); }
} finally {
	putenv( false === $saved_key ? 'OHMYLMS_AI_API_KEY' : 'OHMYLMS_AI_API_KEY=' . $saved_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Restore the CLI fixture's previous environment.
	foreach ( glob( $private_dir . '/*.pdf' ) as $fixture_source ) {
		wp_delete_file( $fixture_source ); }
	rmdir( $private_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove synthetic private fixture directory.
	foreach ( $GLOBALS['wp_filter']['shutdown']->callbacks ?? array() as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = $callback['function'];
			if ( $function instanceof Closure && realpath( ( new ReflectionFunction( $function ) )->getFileName() ) === realpath( dirname( __DIR__, 2 ) . '/includes/Skills/Evidence.php' ) ) {
				remove_action( 'shutdown', $function, $priority ); }
		}
	}
}
echo isset( $failure_message ) ? esc_html( $failure_message ) : (int) $checks . " PDF-to-exam integration checks passed; database fixtures rolled back; AI transport mocked.\n";
