<?php
/**
 * Skill rows go with a deleted question, quiz, skill or user, and the sweep removes old leftovers.
 * Every fixture and result is rolled back.
 *
 * @package OhMyLMS
 */

$cleanup_site = getenv( 'OHMYLMS_TEST_SITE_PATH' );
if ( 'cli' !== PHP_SAPI || ! $cleanup_site || ! is_file( $cleanup_site . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set OHMYLMS_TEST_SITE_PATH to a local WordPress installation.' );
}
require $cleanup_site . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\Quiz\QuizSkills;
use OhMyLMS\Skills\Cleanup;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

$GLOBALS['cleanup_checks'] = 0;

/**
 * Assert a contract.
 *
 * @param bool   $condition Expected result.
 * @param string $message   Failure description.
 * @throws RuntimeException When the contract fails.
 */
function cleanup_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) );
	}
	++$GLOBALS['cleanup_checks'];
}

/**
 * Rows in a plugin table matching one column.
 *
 * @param string $table  Assessment table name.
 * @param string $column Column.
 * @param int    $value  Value.
 * @return int Rows.
 */
function cleanup_rows( $table, $column, $value ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The test inspects real, uncommitted records.
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i=%d', Schema::table( $table ), $column, $value ) );
}

/**
 * A skill term.
 *
 * @return int Term ID.
 */
function cleanup_term() {
	$term = wp_insert_term( 'Cleanup skill ' . wp_generate_password( 6, false ), Taxonomy::NAME );
	cleanup_check( ! is_wp_error( $term ), 'Skill fixture failed.' );
	return (int) $term['term_id'];
}

/**
 * A question with a primary and a supporting skill.
 *
 * @param int $primary    Primary skill.
 * @param int $supporting Supporting skill.
 * @return int Question ID.
 */
function cleanup_question( $primary, $supporting ) {
	$written = DraftWriter::save(
		array(
			'name'        => 'Cleanup fixture',
			'description' => 'Enter the number.',
			'settings'    => array(
				'type'   => 'numerical',
				'answer' => 4,
			),
			'skills'      => array(
				'p1' => array(
					'primary'    => $primary,
					'supporting' => array( $supporting ),
				),
			),
			'questions'   => array(),
		)
	);
	cleanup_check( ! is_wp_error( $written ), 'Question fixture failed.' );
	return (int) $written['id'];
}

/**
 * Learner skill rows for one learner and skill.
 *
 * @param int $student Learner.
 * @param int $term    Skill.
 */
function cleanup_learner_rows( $student, $term ) {
	global $wpdb;
	$now = current_time( 'mysql', true );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Fixture rows inside the rolled-back transaction.
	$wpdb->insert(
		Schema::table( 'skill_evidence' ),
		array(
			'grade_event_id' => wp_rand( 900000000, 999999999 ),
			'student_id'     => $student,
			'term_id'        => $term,
			'source_type'    => 'quiz',
			'source_id'      => 1,
			'question_id'    => 1,
			'version_id'     => 1,
			'evidence_at'    => $now,
		)
	);
	$wpdb->insert(
		Schema::table( 'student_skill_state' ),
		array(
			'student_id' => $student,
			'term_id'    => $term,
			'updated_at' => $now,
		)
	);
	$wpdb->insert(
		Schema::table( 'skill_level_events' ),
		array(
			'student_id'   => $student,
			'term_id'      => $term,
			'source_type'  => 'quiz',
			'source_id'    => wp_rand( 900000000, 999999999 ),
			'level_before' => 'not-started',
			'level_after'  => 'attempted',
			'created_at'   => $now,
		)
	);
	// phpcs:enable
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
			cleanup_check( Schema::ready(), 'Assessment storage unavailable.' );

			// A deleted question takes its skill connections with it.
			$keep     = cleanup_term();
			$doomed   = cleanup_term();
			$question = cleanup_question( $keep, $doomed );
			cleanup_check( 2 === cleanup_rows( 'question_skills', 'question_id', $question ), 'The question fixture has no skill rows.' );
			wp_delete_post( $question, true );
			cleanup_check( 0 === cleanup_rows( 'question_skills', 'question_id', $question ), 'A deleted question kept its skill rows.' );

			// A deleted quiz takes its skill connections with it.
			$quiz = wp_insert_post(
				array(
					'post_type'   => OHMYLMS_QUIZ_CPT,
					'post_status' => 'publish',
					'post_title'  => 'Cleanup fixture',
				)
			);
			QuizSkills::set( $quiz, array( $keep, $doomed ) );
			wp_delete_post( $quiz, true );
			cleanup_check( 0 === cleanup_rows( 'quiz_skills', 'quiz_id', $quiz ), 'A deleted quiz kept its skill rows.' );

			// A deleted skill leaves questions, quizzes and learners, and is dropped from saved maps.
			$question = cleanup_question( $keep, $doomed );
			$quiz     = wp_insert_post(
				array(
					'post_type'   => OHMYLMS_QUIZ_CPT,
					'post_status' => 'publish',
					'post_title'  => 'Cleanup fixture',
				)
			);
			QuizSkills::set( $quiz, array( $keep, $doomed ) );
			$student = wp_insert_user(
				array(
					'user_login' => 'skill-cleanup-' . wp_generate_password( 10, false ),
					'user_pass'  => wp_generate_password( 24 ),
					'role'       => 'subscriber',
				)
			);
			cleanup_check( ! is_wp_error( $student ), 'Student fixture failed.' );
			cleanup_learner_rows( $student, $keep );
			cleanup_learner_rows( $student, $doomed );
			wp_delete_term( $doomed, Taxonomy::NAME );
			foreach ( array( 'question_skills', 'quiz_skills', 'skill_evidence', 'student_skill_state', 'skill_level_events' ) as $table ) {
				cleanup_check( 0 === cleanup_rows( $table, 'term_id', $doomed ), "A deleted skill kept rows in $table." );
			}
			cleanup_check( 1 === cleanup_rows( 'question_skills', 'question_id', $question ), 'Deleting one skill removed the question’s other skill.' );
			cleanup_check( array( $keep ) === QuizSkills::get( $quiz ), 'Deleting one skill removed the quiz’s other skill.' );
			cleanup_check(
				array(
					'p1' => array(
						'primary'    => $keep,
						'supporting' => array(),
					),
				) === SkillMap::current( $question ),
				'The question’s saved skill map still names the deleted skill.'
			);
			cleanup_check( 1 === cleanup_rows( 'student_skill_state', 'student_id', $student ), 'Deleting one skill removed the learner’s other level.' );

			// A deleted user takes their evidence, levels and level history.
			wp_delete_user( $student );
			foreach ( array( 'skill_evidence', 'student_skill_state', 'skill_level_events' ) as $table ) {
				cleanup_check( 0 === cleanup_rows( $table, 'student_id', $student ), "A deleted user kept rows in $table." );
			}

			// The sweep removes rows left behind before the hooks existed, and nothing else.
			$ghost_user = 2147483000;
			$ghost_post = 2147483001;
			$ghost_term = 2147483002;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Leftover fixtures inside the rolled-back transaction.
			$wpdb->insert(
				Schema::table( 'question_skills' ),
				array(
					'question_id' => $ghost_post,
					'term_id'     => $keep,
					'part_id'     => 'p1',
					'role'        => 'primary',
				)
			);
			$wpdb->insert(
				Schema::table( 'question_skills' ),
				array(
					'question_id' => $question,
					'term_id'     => $ghost_term,
					'part_id'     => 'p2',
					'role'        => 'primary',
				)
			);
			$wpdb->insert(
				Schema::table( 'quiz_skills' ),
				array(
					'quiz_id'  => $ghost_post,
					'term_id'  => $keep,
					'position' => 0,
				)
			);
			// phpcs:enable
			cleanup_learner_rows( $ghost_user, $keep );
			$kept_before = cleanup_rows( 'question_skills', 'term_id', $keep );
			$removed     = Cleanup::sweep();
			cleanup_check( 0 === cleanup_rows( 'question_skills', 'question_id', $ghost_post ), 'The sweep kept a deleted question’s rows.' );
			cleanup_check( 0 === cleanup_rows( 'question_skills', 'term_id', $ghost_term ), 'The sweep kept a deleted skill’s rows.' );
			cleanup_check( 0 === cleanup_rows( 'quiz_skills', 'quiz_id', $ghost_post ), 'The sweep kept a deleted quiz’s rows.' );
			cleanup_check( 0 === cleanup_rows( 'student_skill_state', 'student_id', $ghost_user ), 'The sweep kept a deleted user’s levels.' );
			cleanup_check( $kept_before - 1 === cleanup_rows( 'question_skills', 'term_id', $keep ), 'The sweep removed a live question’s skill.' );
			cleanup_check( 1 === cleanup_rows( 'question_skills', 'question_id', $question ), 'The sweep removed a live question’s live skill.' );
			cleanup_check( $removed['question_skills'] >= 1 && $removed['quiz_skills'] >= 1 && $removed['student_skill_state'] >= 1, 'The sweep did not report what it removed.' );

			throw new RuntimeException( 'OHMYLMS_SKILL_CLEANUP_TEST_SUCCESS' );
		}
	);
} catch ( Throwable $failure ) {
	if ( 'OHMYLMS_SKILL_CLEANUP_TEST_SUCCESS' === $failure->getMessage() ) {
		echo 'Skill cleanup: ' . (int) $GLOBALS['cleanup_checks'] . " checks passed; fixtures rolled back.\n";
	} else {
		echo esc_html( $failure->getMessage() ) . "\n";
		exit( 1 );
	}
}
