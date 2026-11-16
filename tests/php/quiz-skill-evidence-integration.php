<?php
/**
 * Quiz answers to questions with no skill of their own count towards the quiz's skills.
 * Every fixture and result is rolled back.
 *
 * @package OhMyLMS
 */

$quiz_skill_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $quiz_skill_site || ! is_file( $quiz_skill_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress installation.' );
}
require $quiz_skill_site . '/wp-load.php';

use OhMyLMS\Assessment\GradeEvents;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Quiz\QuizSkills;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

$GLOBALS['quiz_skill_checks'] = 0;

/**
 * Assert a contract.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure description.
 * @throws RuntimeException When the contract fails.
 */
function quiz_skill_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['quiz_skill_checks'];
}

/**
 * Skills a learner has primary evidence on, by term ID.
 *
 * @param int $student Learner.
 * @return int[] Term IDs.
 */
function quiz_skill_evidence( $student ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The test inspects real, uncommitted records.
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM %i WHERE student_id=%d AND role='primary' ORDER BY term_id", Schema::table( 'skill_evidence' ), $student ) ) );
}

/**
 * A skill term.
 *
 * @param string $name Name prefix.
 * @return int Term ID.
 */
function quiz_skill_term( $name ) {
	$term = wp_insert_term( $name . ' ' . wp_generate_password( 6, false ), Taxonomy::NAME );
	quiz_skill_check( ! is_wp_error( $term ), 'Skill fixture failed.' );
	return (int) $term['term_id'];
}

/**
 * A captured question version, with or without a skill of its own.
 *
 * @param int $skill Own primary skill, or 0 for none.
 * @return array{question:int,version:int}
 */
function quiz_skill_question( $skill ) {
	$written = DraftWriter::save(
		array(
			'name'        => 'Quiz skill fixture',
			'description' => 'Enter the number.',
			'settings'    => array(
				'type'   => 'numerical',
				'answer' => 4,
				'score'  => array(
					'enabled' => true,
					'value'   => 1,
				),
			),
			'skills'      => $skill ? array(
				'p1' => array(
					'primary'    => $skill,
					'supporting' => array(),
				),
			) : array(),
			'questions'   => array(),
		)
	);
	quiz_skill_check( ! is_wp_error( $written ), 'Question fixture failed: ' . ( is_wp_error( $written ) ? $written->get_error_message() : '' ) );
	$captured = VersionPublisher::capture( (int) $written['id'] );
	$version  = is_array( $captured ) ? (int) $captured['id'] : 0;
	quiz_skill_check( $version > 0, 'Question version capture failed.' );
	return array(
		'question' => (int) $written['id'],
		'version'  => $version,
	);
}

/**
 * An attempt on the quiz, under a revision with the given frozen settings.
 *
 * @param int        $quiz     Quiz.
 * @param int        $student  Learner.
 * @param array|null $settings Revision settings, or null for an attempt without a revision.
 * @return int Attempt ID.
 */
function quiz_skill_attempt( $quiz, $student, $settings ) {
	global $wpdb;
	$now = current_time( 'mysql', true );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Fixture rows inside the rolled-back transaction.
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_quiz_attempts',
		array(
			'course_id'  => 0,
			'quiz_id'    => $quiz,
			'student_id' => $student,
			'status'     => 'completed',
			'start_date' => $now,
			'end_date'   => $now,
		)
	);
	$attempt = (int) $wpdb->insert_id;
	if ( null !== $settings ) {
		$number = 1 + (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(revision_no) FROM %i WHERE quiz_id=%d', Schema::table( 'quiz_revisions' ), $quiz ) );
		$wpdb->insert(
			Schema::table( 'quiz_revisions' ),
			array(
				'quiz_id'      => $quiz,
				'revision_no'  => $number,
				'kind'         => 'quiz',
				'settings'     => wp_json_encode( $settings ),
				'content_hash' => hash( 'sha256', $attempt . wp_json_encode( $settings ) ),
				'created_at'   => $now,
			)
		);
		$wpdb->insert(
			Schema::table( 'attempt_context' ),
			array(
				'attempt_id'  => $attempt,
				'revision_id' => (int) $wpdb->insert_id,
				'started_at'  => $now,
			)
		);
	}
	// phpcs:enable
	return $attempt;
}

/**
 * Grade one correct answer and turn it into evidence.
 *
 * @param int   $attempt  Attempt.
 * @param int   $item     Item number in the attempt.
 * @param int   $student  Learner.
 * @param array $question Question fixture.
 */
function quiz_skill_answer( $attempt, $item, $student, array $question ) {
	GradeEvents::record(
		array(
			'source_type' => 'quiz',
			'source_id'   => $attempt,
			'item_id'     => $item,
			'student_id'  => $student,
			'question_id' => $question['question'],
			'version_id'  => $question['version'],
			'awarded'     => 1,
			'max_marks'   => 1,
			'fraction'    => 1,
			'correct'     => true,
		)
	);
	Evidence::process( 50 );
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
			quiz_skill_check( Schema::ready(), 'Assessment storage unavailable.' );
			$student = wp_insert_user(
				array(
					'user_login' => 'quiz-skill-' . wp_generate_password( 10, false ),
					'user_pass'  => wp_generate_password( 24 ),
					'role'       => 'subscriber',
				)
			);
			quiz_skill_check( ! is_wp_error( $student ), 'Student fixture failed.' );
			$quiz_skill  = quiz_skill_term( 'Quiz skill' );
			$own_skill   = quiz_skill_term( 'Own skill' );
			$later_skill = quiz_skill_term( 'Later skill' );
			$quiz        = wp_insert_post(
				array(
					'post_type'   => OHMYLMS_QUIZ_CPT,
					'post_status' => 'publish',
					'post_title'  => 'Quiz skill fixture',
				)
			);
			quiz_skill_check( $quiz > 0, 'Quiz fixture failed.' );
			$unmapped = quiz_skill_question( 0 );
			$mapped   = quiz_skill_question( $own_skill );
			QuizSkills::set( $quiz, array( $quiz_skill ) );

			// An unmapped question counts towards the skills frozen in the attempt's revision.
			$attempt = quiz_skill_attempt( $quiz, $student, array( 'skills' => array( $quiz_skill ) ) );
			quiz_skill_answer( $attempt, 1, $student, $unmapped );
			quiz_skill_check( array( $quiz_skill ) === quiz_skill_evidence( $student ), 'An unmapped question did not count towards the quiz skill.' );

			// A question with its own skill counts only towards that skill, never the quiz's.
			quiz_skill_answer( $attempt, 2, $student, $mapped );
			quiz_skill_check( array( $quiz_skill, $own_skill ) === quiz_skill_evidence( $student ), 'A mapped question did not count towards its own skill.' );
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspect the uncommitted evidence.
			$mapped_terms = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT term_id FROM %i WHERE student_id=%d AND question_id=%d', Schema::table( 'skill_evidence' ), $student, $mapped['question'] ) ) );
			quiz_skill_check( array( $own_skill ) === $mapped_terms, 'A mapped question also counted towards the quiz skill.' );

			// The state reflects the new evidence.
			$state = \OhMyLMS\Performance\Snapshot::student( $student, array( $quiz_skill ) );
			quiz_skill_check( 'not-started' !== (string) ( $state[ $quiz_skill ]['key'] ?? 'not-started' ), 'The quiz skill state was not updated.' );

			// Editing the quiz later does not change what a frozen revision counts towards.
			QuizSkills::set( $quiz, array( $later_skill ) );
			quiz_skill_answer( $attempt, 3, $student, $unmapped );
			quiz_skill_check( ! in_array( $later_skill, quiz_skill_evidence( $student ), true ), 'A later quiz edit changed a frozen attempt.' );

			// Attempts without frozen quiz skills use the quiz's skills now.
			$legacy = quiz_skill_attempt( $quiz, $student, array( 'kind' => 'quiz' ) );
			quiz_skill_answer( $legacy, 1, $student, $unmapped );
			quiz_skill_check( in_array( $later_skill, quiz_skill_evidence( $student ), true ), 'A revision without frozen skills did not use the quiz skills.' );
			$bare = quiz_skill_attempt( $quiz, $student, null );
			quiz_skill_answer( $bare, 1, $student, $mapped );
			quiz_skill_check( 3 === count( quiz_skill_evidence( $student ) ), 'An attempt without a revision misattributed a mapped question.' );

			// A quiz with no skills still records nothing for an unmapped question.
			QuizSkills::set( $quiz, array() );
			$empty = quiz_skill_attempt( $quiz, $student, array( 'skills' => array() ) );
			$before = count( quiz_skill_evidence( $student ) );
			quiz_skill_answer( $empty, 1, $student, $unmapped );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Inspect the uncommitted evidence.
			$rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i e JOIN %i g ON g.id=e.grade_event_id WHERE g.source_id=%d AND g.source_type=%s', Schema::table( 'skill_evidence' ), Schema::table( 'grade_events' ), $empty, 'quiz' ) );
			quiz_skill_check( 0 === $rows && count( quiz_skill_evidence( $student ) ) === $before, 'A quiz without skills produced evidence.' );

			// Publishing a revision freezes the quiz's current skills.
			QuizSkills::set( $quiz, array( $quiz_skill ) );
			$reflection = new ReflectionClass( \OhMyLMS\Assessment\RevisionPublisher::class );
			$source     = (string) file_get_contents( (string) $reflection->getFileName() );
			quiz_skill_check( false !== strpos( $source, "\$settings['skills'] = \\OhMyLMS\\Quiz\\QuizSkills::get(" ), 'Published revisions do not freeze the quiz skills.' );

			throw new RuntimeException( 'OHMYLMS_QUIZ_SKILL_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'OHMYLMS_QUIZ_SKILL_TEST_SUCCESS' === $failure->getMessage() ) {
		echo 'Quiz skill evidence: ' . (int) $GLOBALS['quiz_skill_checks'] . " checks passed; fixtures rolled back.\n";
	} else {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
}
