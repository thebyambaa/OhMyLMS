<?php
/**
 * Fixture for tests/browser/question-editor-parity.spec.cjs on the Local site (disposable data).
 *
 *   OHMYLMS_QUESTIONS_TEST=1 php tests/php/question-editor-browser-fixture.php setup          # prints JSON
 *   OHMYLMS_QUESTIONS_TEST=1 php tests/php/question-editor-browser-fixture.php attempt <tag>  # learner's latest attempt, JSON
 *   OHMYLMS_QUESTIONS_TEST=1 php tests/php/question-editor-browser-fixture.php cleanup <tag>
 *
 * Setup creates a temporary administrator and learner, a course with a chapter holding a draft quiz
 * (all questions on one page) that the learner is enrolled in, a skill and one approved question of
 * that skill for a random pool. Cleanup removes them and every question whose name contains the tag,
 * with attempts, versions, revisions and placements.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions -- CLI fixture script.

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_QUESTIONS_TEST' ) ) {
	exit( "Set OHMYLMS_QUESTIONS_TEST=1 to run the question editor browser fixture against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$action = $argv[1] ?? '';
$tag    = $argv[2] ?? '';

/**
 * Path of the state file of a fixture.
 *
 * @param string $tag Fixture tag.
 * @return string
 * @throws RuntimeException For a malformed tag.
 */
function fixture_state_file( $tag ) {
	if ( ! preg_match( '/^qe[a-z0-9]{8}$/', $tag ) ) {
		throw new RuntimeException( 'Invalid fixture tag' );
	}
	return sys_get_temp_dir() . '/ohmylms-question-editor-' . $tag . '.json';
}

/**
 * Dispatch a REST request as the current user and fail on errors.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/.
 * @param array  $data   JSON body.
 * @return mixed Response data.
 * @throws RuntimeException When the request fails.
 */
function fixture_call( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	$response = rest_do_request( $request );
	if ( $response->is_error() ) {
		throw new RuntimeException( $method . ' ' . $path . ': ' . wp_json_encode( $response->get_data() ) );
	}
	return $response->get_data();
}

if ( 'setup' === $action ) {
	$tag   = 'qe' . strtolower( wp_generate_password( 8, false, false ) );
	$state = array(
		'tag'   => $tag,
		'posts' => array(),
		'users' => array(),
		'terms' => array(),
	);
	try {
		foreach ( array(
			'admin'   => 'administrator',
			'learner' => 'subscriber',
		) as $key => $role ) {
			$login    = "$tag-$key";
			$password = wp_generate_password( 20, false, false );
			$id       = wp_insert_user(
				array(
					'user_login'   => $login,
					'user_pass'    => $password,
					'user_email'   => "$login@example.invalid",
					'display_name' => "$tag $key",
					'role'         => $role,
				)
			);
			if ( is_wp_error( $id ) ) {
				throw new RuntimeException( $id->get_error_message() );
			}
			$state['users'][ $key ] = array(
				'id'       => $id,
				'login'    => $login,
				'password' => $password,
			);
		}
		wp_set_current_user( $state['users']['admin']['id'] );
		$course  = fixture_call(
			'POST',
			'courses',
			array(
				'title'  => "$tag course",
				'status' => 'publish',
			)
		)['id'];
		$quiz    = fixture_call(
			'POST',
			'quizzes',
			array(
				'title'    => "$tag quiz",
				'settings' => array(
					'layout'           => 'all_questions_in_one_page',
					'attempts_allowed' => 5,
					'feedback_mode'    => 'immediate',
				),
			)
		)['id'];
		$chapter = wp_insert_post(
			array(
				'post_type'   => 'ohmylms-chapter',
				'post_title'  => "$tag chapter",
				'post_status' => 'publish',
			)
		);
		array_push( $state['posts'], $course, $quiz, $chapter );
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_chapter_relationship',
			array(
				'course_id'    => $course,
				'chapter_id'   => $chapter,
				'order_number' => 0,
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_content_relationship',
			array(
				'chapter_id'   => $chapter,
				'content_id'   => $quiz,
				'content_type' => 'quiz',
				'order_number' => 0,
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_user_enrollment',
			array(
				'user_id'    => $state['users']['learner']['id'],
				'course_id'  => $course,
				'status'     => 'enrolled',
				'progress'   => 'running',
				'start_date' => current_time( 'mysql' ),
			)
		);
		$skill            = wp_insert_term( "$tag skill", Taxonomy::NAME );
		$state['terms'][] = (int) $skill['term_id'];
		$pooled           = fixture_call(
			'POST',
			'questions',
			array(
				'name'     => "$tag pooled: 1 + 1 = 2",
				'settings' => array(
					'type'  => 'true-false',
					'score' => array(
						'enabled' => true,
						'value'   => 1,
					),
				),
				'options'  => array(
					array(
						'answer'     => 'True',
						'is_correct' => true,
					),
					array(
						'answer'     => 'False',
						'is_correct' => false,
					),
				),
				'skills'   => array(
					'p1' => array(
						'primary'    => (int) $skill['term_id'],
						'supporting' => array(),
					),
				),
			)
		);
		fixture_call( 'POST', 'questions/' . $pooled['id'] . '/approve' );
		$state += array(
			'course'   => $course,
			'quiz'     => $quiz,
			'quiz_url' => get_permalink( $quiz ),
			'skill'    => (int) $skill['term_id'],
			'pooled'   => (int) $pooled['id'],
		);
		file_put_contents( fixture_state_file( $tag ), wp_json_encode( $state ) );
		echo wp_json_encode( $state ), "\n";
	} catch ( Throwable $error ) {
		file_put_contents( fixture_state_file( $tag ), wp_json_encode( $state ) );
		fwrite( STDERR, $error->getMessage() . "\nCleanup tag: $tag\n" );
		exit( 1 );
	}
	exit;
}

$state = json_decode( (string) @file_get_contents( fixture_state_file( $tag ) ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A missing state file is reported below.
if ( ! $state ) {
	throw new RuntimeException( 'Unknown fixture ' . $tag );
}

if ( 'attempt' === $action ) {
	$attempt = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, total FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE student_id = %d AND quiz_id = %d ORDER BY id DESC LIMIT 1", $state['users']['learner']['id'], $state['quiz'] ), ARRAY_A );
	$answers = $attempt ? $wpdb->get_results( $wpdb->prepare( "SELECT question_id, question_marks, achive_mark, is_correct, given_answer FROM {$wpdb->prefix}ohmylms_quiz_attempts_answers WHERE quiz_attempt_id = %d ORDER BY id", $attempt['id'] ), ARRAY_A ) : array();
	foreach ( $answers as &$answer ) {
		$settings       = get_post_meta( (int) $answer['question_id'], '_question_settings', true );
		$answer['type'] = is_array( $settings ) ? (string) ( $settings['type'] ?? '' ) : '';
	}
	unset( $answer );
	echo wp_json_encode(
		array(
			'attempt' => $attempt,
			'answers' => $answers,
		)
	), "\n";
	exit;
}

if ( 'cleanup' === $action ) {
	$users     = array_column( $state['users'], 'id' );
	$questions = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", OHMYLMS_QUESTION_CPT, '%' . $wpdb->esc_like( $tag ) . '%' ) ) );
	foreach ( $users as $user ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_quiz_attempts WHERE student_id = %d", $user ) ) as $attempt ) {
			foreach ( array( 'attempt_context', 'attempt_items', 'response_drafts', 'response_events' ) as $table ) {
				$wpdb->delete( Schema::table( $table ), array( 'attempt_id' => (int) $attempt ) );
			}
		}
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE student_id = %d', Schema::table( 'grade_events' ), $user ) ) as $event ) {
			$wpdb->delete( Schema::table( 'evidence_outbox' ), array( 'grade_event_id' => (int) $event ) );
		}
		foreach ( array( 'grade_events', 'skill_evidence', 'student_skill_state' ) as $table ) {
			$wpdb->delete( Schema::table( $table ), array( 'student_id' => $user ) );
		}
		foreach ( array( 'ohmylms_quiz_attempts_answers', 'ohmylms_quiz_attempts' ) as $table ) {
			$wpdb->delete( $wpdb->prefix . $table, array( 'student_id' => $user ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'user_id' => $user ) );
	}
	foreach ( array_merge( $state['posts'], $questions ) as $post ) {
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE question_id = %d', Schema::table( 'qb_question_versions' ), $post ) ) as $version ) {
			$wpdb->delete( Schema::table( 'qb_version_skills' ), array( 'version_id' => (int) $version ) );
		}
		$wpdb->delete( Schema::table( 'qb_question_versions' ), array( 'question_id' => $post ) );
		$wpdb->delete( Schema::table( 'qb_questions' ), array( 'question_id' => $post ) );
		$wpdb->delete( Schema::table( 'question_skills' ), array( 'question_id' => $post ) );
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id = %d', Schema::table( 'quiz_revisions' ), $post ) ) as $revision ) {
			$wpdb->delete( Schema::table( 'quiz_revision_slots' ), array( 'revision_id' => (int) $revision ) );
		}
		$wpdb->delete( Schema::table( 'quiz_revisions' ), array( 'quiz_id' => $post ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_questions_relationship', array( 'quiz_id' => $post ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_questions_relationship', array( 'question_id' => $post ) );
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_question_answers WHERE question_id = %d", $post ) ) as $answer_id ) {
			$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answermeta', array( 'answer_id' => (int) $answer_id ) );
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_question_answers', array( 'question_id' => $post ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $post ) );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'content_id' => $post ) );
		wp_delete_post( $post, true );
	}
	foreach ( $users as $user ) {
		wp_delete_user( $user );
	}
	foreach ( $state['terms'] as $term ) {
		wp_delete_term( $term, Taxonomy::NAME );
	}
	@unlink( fixture_state_file( $tag ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The state file may already be gone.
	echo 'Question editor browser fixture cleaned: ' . count( $questions ) . " questions.\n";
	exit;
}

throw new RuntimeException( 'Usage: setup | attempt <tag> | cleanup <tag>' );
