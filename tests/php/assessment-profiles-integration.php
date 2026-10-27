<?php
/**
 * Assessment profiles integration suite (isolated test site only).
 *
 * A quiz, a practice set and an exam are one stored thing; a profile chosen by the saved kind decides the
 * player design, layout, editor blocks, return route, editor panel and player context. This suite checks
 * the built-in profiles and that an add-on can register a new kind without touching shared code.
 *
 *   OHMYLMS_TEST_CREDENTIALS=/path/test-credentials.json php tests/php/assessment-profiles-integration.php
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

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Profiles;
use OhMyLMS\Exams\Composer;
use OhMyLMS\Learning\NativeQuizEditor;
use OhMyLMS\Quiz\PlayerTemplates;

$checks = 0;
/**
 * Assert one contract.
 *
 * @param bool   $ok Condition.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function profiles_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) {
		throw new RuntimeException( $message );
	}
	++$checks;
}
/**
 * Forget the resolved profiles so a filter added later is read.
 */
function profiles_reset() {
	$property = new ReflectionProperty( Profiles::class, 'all' );
	$property->setValue( null, null );
}
/**
 * Create a draft quiz.
 *
 * @param string $name Title.
 * @return int Quiz ID.
 */
function profiles_quiz( $name ) {
	$request = new WP_REST_Request( 'POST', '/ohmylms/v1/quiz' );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body(
		wp_json_encode(
			array(
				'name'   => $name,
				'status' => 'draft',
			)
		)
	);
	return (int) rest_do_request( $request )->get_data()['id'];
}

// Built-in kinds.
$kinds = Profiles::kinds();
profiles_check( array() === array_diff( array( 'quiz', 'practice', 'exam' ), $kinds ), 'Quiz, practice and exam are registered.' );
profiles_check( 'quiz' === Profiles::get( 'no-such-kind' )['kind'], 'An unknown kind falls back to the plain quiz profile.' );
$plain = profiles_quiz( 'Profiles plain quiz' );
$quiz  = Profiles::of( $plain );
profiles_check( 'quiz' === $quiz['kind'] && '' === $quiz['player_template'] && '' === $quiz['layout'] && array( 'quiz', 'inline', 'practice' ) === $quiz['deliveries'] && '/content-hub/quizzes' === $quiz['return_route'] && '' === Profiles::editor( $plain )['panel'] && array() === Profiles::editor( $plain )['data'], 'A plain quiz keeps its own design and layout and adds no editor panel.' );

// An exam is a quiz whose kind is exam.
$request = new WP_REST_Request( 'POST', '/ohmylms/v1/exams/compose' );
$request->set_header( 'Content-Type', 'application/json' );
$request->set_body(
	wp_json_encode(
		array(
			'title'            => 'Profiles exam',
			'duration_minutes' => 20,
		)
	)
);
$exam    = rest_do_request( $request )->get_data();
$exam_q  = (int) $exam['quiz_id'];
$profile = Profiles::of( $exam_q );
profiles_check( 'exam' === AssessmentSettings::get( $exam_q )['kind'] && 'exam' === $profile['kind'], 'The exam backing quiz stores kind exam.' );
profiles_check( 'exam' === $profile['player_template'] && 'one_question_per_page' === $profile['layout'] && '/content-hub/exams' === $profile['return_route'], 'The exam profile names its design, layout and return route.' );
profiles_check( array( 'quiz' ) === $profile['deliveries'] && ! Profiles::allows( $exam_q, 'practice' ) && Profiles::allows( $plain, 'practice' ), 'The exam allows formal attempts only; a quiz allows every delivery.' );
$context = (object) array( 'post' => get_post( $exam_q ) );
profiles_check( ! in_array( 'ohmylms/question-player', NativeQuizEditor::quiz_blocks( true, $context ), true ) && in_array( NativeQuizEditor::QUESTION_BLOCK, NativeQuizEditor::quiz_blocks( true, $context ), true ), 'The exam editor does not offer the question player.' );
$context = (object) array( 'post' => get_post( $plain ) );
profiles_check( in_array( 'ohmylms/question-player', NativeQuizEditor::quiz_blocks( true, $context ), true ), 'A plain quiz editor still offers the question player.' );
$editor = Profiles::editor( $exam_q );
profiles_check( 'exam' === $editor['panel'] && (int) $editor['data']['id'] === (int) $exam['id'], 'The exam editor panel receives the exam record.' );
profiles_check( array( 'quiz' ) === $editor['deliveries'] && array( 'quiz', 'inline', 'practice' ) === Profiles::editor( $plain )['deliveries'], 'The editor is told which deliveries the kind allows, so the question block offers the right sources.' );
$ctx = Profiles::context( array( 'quizId' => $exam_q ) );
profiles_check( isset( $ctx['marked'], $ctx['visited'] ) && 'Save and Next' === $ctx['nextText'], 'The exam profile adds palette state to the player context.' );
profiles_check( array( 'quizId' => $plain ) === Profiles::context( array( 'quizId' => $plain ) ), 'A plain quiz player context is unchanged.' );

// Designs brought by a profile are applied by it and hidden from the per-quiz picker.
profiles_check( 'exam' === ( PlayerTemplates::all()['exam']['profile'] ?? '' ) && 'ohmylms-exam-template' === PlayerTemplates::all()['exam']['stylesheet'], 'The exam design comes from the exam profile with its stylesheet.' );
profiles_check( ! in_array( 'exam', array_column( PlayerTemplates::choices(), 'value' ), true ) && in_array( 'playful', array_column( PlayerTemplates::choices(), 'value' ), true ), 'Profile designs are not offered in the quiz design picker.' );

// An add-on registers a new kind through the filter alone.
add_filter(
	'ohmylms_assessment_profiles',
	static function ( $profiles ) {
		$profiles['placement'] = array(
			'label'           => 'Placement test',
			'player_template' => 'paper',
			'layout'          => 'all_questions_in_one_page',
			'deliveries'      => array( 'quiz', 'bogus' ),
			'return_route'    => '/content-hub/quizzes',
			'panel'           => 'placement',
			'editor_data'     => static function ( $quiz_id ) {
				return array( 'quiz' => $quiz_id );
			},
			'context'         => static function ( $context ) {
				$context['placement'] = true;
				return $context;
			},
		);
		$profiles['bad Kind!']    = array( 'label' => 'Rejected id' );
		return $profiles;
	}
);
profiles_reset();
profiles_check( in_array( 'placement', Profiles::kinds(), true ) && ! in_array( 'bad Kind!', Profiles::kinds(), true ), 'A filter adds a kind; invalid ids are ignored.' );
$placement = profiles_quiz( 'Profiles placement' );
$saved     = AssessmentSettings::save( $placement, array( 'kind' => 'placement' ) );
profiles_check( ! is_wp_error( $saved ) && 'placement' === Profiles::of( $placement )['kind'], 'A quiz can be saved with the new kind.' );
profiles_check( is_wp_error( AssessmentSettings::save( $placement, array( 'kind' => 'bogus' ) ) ), 'An unregistered kind is refused.' );
$custom = Profiles::of( $placement );
profiles_check( 'paper' === $custom['player_template'] && 'all_questions_in_one_page' === $custom['layout'] && true === Profiles::context( array( 'quizId' => $placement ) )['placement'] && 'placement' === Profiles::editor( $placement )['panel'], 'The new kind drives design, layout, context and editor panel.' );
profiles_check( array( 'quiz' ) === Profiles::of( $placement )['deliveries'] && array( NativeQuizEditor::QUESTION_BLOCK ) === array_intersect( array( NativeQuizEditor::QUESTION_BLOCK, 'ohmylms/question-player' ), NativeQuizEditor::quiz_blocks( true, (object) array( 'post' => get_post( $placement ) ) ) ), 'The new kind narrows the editor blocks through its deliveries; unknown deliveries are dropped.' );

echo "Assessment profiles: {$checks} checks passed.\n";
