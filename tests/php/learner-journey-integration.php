<?php
/**
 * Learner lesson navigation, saved completion and publication isolation.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local learner checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CompletionPolicy;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\Schema;
use OhMyLMS\Student\CourseOutline;
use OhMyLMS\Student\Hub;
use OhMyLMS\Student\LessonJourney;
use OhMyLMS\Skills\Taxonomy;

/**
 * Assert a learner behavior.
 *
 * @param bool   $condition Expected result.
 * @param string $message Failure reason.
 * @throws RuntimeException On failure.
 */
function journey_check( $condition, $message ) {
	global $journey_checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI diagnostic.
	}
	++$journey_checks;
}

/**
 * Dispatch through the real enrollment and completion controllers.
 *
 * @param string $method Method.
 * @param string $path REST suffix.
 * @param array  $data JSON body.
 * @return WP_REST_Response
 */
function journey_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

/**
 * Delete only this fixture's data and restore presentation settings.
 *
 * @param array $state Fixture identifiers.
 */
function journey_cleanup( array $state ) {
	global $wpdb;
	wp_set_current_user( $state['actor'] );
	journey_practice_reset( $state );
	$enrollments = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ohmylms_user_enrollment WHERE user_id = %d AND course_id = %d", $state['learner'], $state['course'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture learner and course only.
	foreach ( $enrollments as $id ) {
		foreach ( array( 'awards', 'enrollments' ) as $table ) {
			$wpdb->delete( Schema::table( $table ), array( 'enrollment_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture learning bindings/awards only.
		}
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture completion only.
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture enrollment only.
	}
	$programs = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE course_id = %d', Schema::table( 'programs' ), $state['course'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture program versions only.
	foreach ( $programs as $id ) {
		$wpdb->delete( Schema::table( 'outcomes' ), array( 'program_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture outcome index only.
	}
	$wpdb->delete( Schema::table( 'programs' ), array( 'course_id' => $state['course'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture publication only.
	foreach ( Catalog::chapters( $state['course'] ) as $unit ) {
		wp_delete_post( $unit['id'], true );
	}
	$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $state['course'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture unit relationships only.
	foreach ( array_reverse( $state['posts'] ) as $id ) {
		wp_delete_post( $id, true );
	}
	wp_delete_term( $state['skill'], Taxonomy::NAME );
	wp_delete_user( $state['learner'] );
	if ( null === $state['hub_settings'] ) {
		delete_option( Hub::OPTION );
	} else {
		update_option( Hub::OPTION, $state['hub_settings'] );
	}
}

/**
 * Remove practice records belonging to this temporary learner only.
 *
 * @param array $state Fixture identifiers.
 */
function journey_practice_reset( array $state ) {
	global $wpdb;
	$schema   = \OhMyLMS\Assessment\Schema::class;
	$sessions = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE student_id = %d', $schema::table( 'practice_sessions' ), $state['learner'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary learner sessions only.
	foreach ( $sessions as $session_id ) {
		$wpdb->delete( $schema::table( 'practice_items' ), array( 'session_id' => $session_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary learner's issued items only.
	}
	$events = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE student_id = %d', $schema::table( 'grade_events' ), $state['learner'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary learner grade events only.
	foreach ( $events as $event_id ) {
		$wpdb->delete( $schema::table( 'evidence_outbox' ), array( 'grade_event_id' => $event_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary learner evidence queue only.
	}
	foreach ( array( 'practice_sessions', 'grade_events', 'skill_evidence', 'student_skill_state', 'skill_level_events' ) as $table ) {
		$wpdb->delete( $schema::table( $table ), array( 'student_id' => $state['learner'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Explicit temporary learner ID only.
	}
	if ( \OhMyLMS\Engagement\XpSchema::ready() ) {
		foreach ( array( 'xp_events', 'skill_scores' ) as $table ) {
			$wpdb->delete( \OhMyLMS\Engagement\XpSchema::table( $table ), array( 'student_id' => $state['learner'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary learner sidecars only.
		}
	}
	if ( \OhMyLMS\Engagement\StreakSchema::ready() ) {
		foreach ( array( 'state', 'days', 'activities', 'milestones' ) as $table ) {
			$wpdb->delete( \OhMyLMS\Engagement\StreakSchema::table( $table ), array( 'user_id' => $state['learner'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Temporary learner streak sidecars only.
		}
	}
}

$journey_path = dirname( __DIR__, 2 ) . '/test-results/learner-journey-session.json';
if ( in_array( $argv[1] ?? '', array( 'cleanup', 'enroll' ), true ) ) {
	$journey_saved = json_decode( file_get_contents( $journey_path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Ignored ephemeral fixture session.
	if ( 'cleanup' === $argv[1] ) {
		journey_cleanup( $journey_saved );
		wp_delete_file( $journey_path );
	} else {
		wp_set_current_user( $journey_saved['actor'] );
		$journey_enroll = journey_api( 'PUT', 'courses/' . $journey_saved['course'] . '/enroll', array( 'student_id' => $journey_saved['learner'] ) );
		if ( $journey_enroll->get_status() >= 400 ) {
			throw new RuntimeException( 'Fixture enrollment failed.' );
		}
	}
	exit;
}
$journey_checks = 0;
$journey_keep   = false;
$journey_state  = array(
	'actor'        => get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
		)
	)[0]->ID,
	'posts'        => array(),
	'hub_settings' => get_option( Hub::OPTION, null ),
	'course'       => 0,
	'learner'      => 0,
	'skill'        => 0,
);
try {
	wp_set_current_user( $journey_state['actor'] );
	$journey_config         = Hub::settings();
	$journey_config['tabs'] = array_values( array_unique( array_merge( $journey_config['tabs'], array( 'library' ) ) ) );
	update_option( Hub::OPTION, $journey_config );
	$journey_state['learner'] = wp_insert_user(
		array(
			'user_login'   => 'journey_' . wp_generate_uuid4(),
			'user_pass'    => wp_generate_password( 32 ),
			'role'         => 'subscriber',
			'display_name' => 'Journey learner',
		)
	);
	update_user_meta( $journey_state['learner'], '_is_ohmylms_student', 'yes' );
	update_user_meta( $journey_state['learner'], '_is_ohmylms_user', 'yes' );
	$journey_state['course']  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Learner journey course',
			'post_status' => 'publish',
			'post_author' => $journey_state['actor'],
		)
	);
	$journey_state['posts'][] = $journey_state['course'];
	update_post_meta( $journey_state['course'], '_price_type', 'free' );
	update_post_meta( $journey_state['course'], '_type', 'self-paced' );
	$journey_unit                = Catalog::add_chapter( $journey_state['course'], 'Foundations' )['id'];
	$journey_unit_two            = Catalog::add_chapter( $journey_state['course'], 'Applications' )['id'];
	$journey_term                = wp_insert_term( 'Compare numbers ' . wp_generate_uuid4(), Taxonomy::NAME );
	$journey_state['skill']      = (int) $journey_term['term_id'];
	$journey_group               = wp_generate_uuid4();
	$journey_group_two           = wp_generate_uuid4();
	$journey_program             = CourseProgram::editor( $journey_state['course'] )['draft'];
	$journey_program['groups']   = array(
		array(
			'id'         => $journey_group,
			'title'      => 'Introduction',
			'chapter_id' => $journey_unit,
		),
		array(
			'id'         => $journey_group_two,
			'title'      => 'Everyday numbers',
			'chapter_id' => $journey_unit_two,
		),
	);
	$journey_program['outcomes'] = array(
		array(
			'term_id'    => $journey_state['skill'],
			'chapter_id' => $journey_unit,
			'group_id'   => $journey_group,
			'required'   => false,
			'target'     => 'proficient',
		),
	);
	$journey_program['items']    = array();
	foreach ( array( 'Read numbers', 'Watch numbers', 'Optional extension' ) as $journey_index => $journey_title ) {
		$journey_content_id = wp_insert_post(
			array(
				'post_type'    => OHMYLMS_LESSON_CPT,
				'post_title'   => $journey_title,
				'post_content' => '<p>Published activity body for ' . $journey_title . '.</p>',
				'post_status'  => 'publish',
				'post_author'  => $journey_state['actor'],
			)
		);
		update_post_meta( $journey_content_id, '_type', 'text' );
		$journey_state['posts'][]   = $journey_content_id;
		$journey_program['items'][] = array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'lesson',
			'content_id' => $journey_content_id,
			'chapter_id' => 2 === $journey_index ? $journey_unit_two : $journey_unit,
			'group_id'   => 2 === $journey_index ? $journey_group_two : $journey_group,
			'required'   => 2 !== $journey_index,
		);
	}
	$journey_program['items'][] = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'practice',
		'content_id' => $journey_state['skill'],
		'chapter_id' => $journey_unit,
		'group_id'   => $journey_group,
	);
	journey_check( ! is_wp_error( CourseProgram::save( $journey_state['course'], $journey_program, true ) ), 'Fixture publishes its course outline.' );
	$journey_report = CourseProgram::readiness_report( $journey_program, $journey_state['course'] );
	journey_check( $journey_report['ready'] && ! $journey_report['skills'] && 1 === count( $journey_report['practice_skills'] ) && 0 === $journey_report['practice_skills'][0]['questions'], 'An optional skill with no approved questions is reported without blocking publication.' );
	$journey_required                            = $journey_program;
	$journey_required['outcomes'][0]['required'] = true;
	$journey_report                              = CourseProgram::readiness_report( $journey_required, $journey_state['course'] );
	journey_check( ! $journey_report['ready'] && 1 === count( $journey_report['skills'] ) && $journey_group === $journey_report['skills'][0]['group_id'], 'The same empty required skill blocks publication and identifies its repair lesson.' );
	journey_check( ! CourseProgram::readiness( $journey_program, $journey_state['course'] ) && (bool) CourseProgram::readiness( $journey_required, $journey_state['course'] ), 'Readiness report and authoritative publication checks agree.' );
	wp_update_post(
		array(
			'ID'          => $journey_program['items'][0]['content_id'],
			'post_status' => 'draft',
		)
	);
	$journey_report = CourseProgram::readiness_report( $journey_program, $journey_state['course'] );
	journey_check( ! $journey_report['ready'] && 1 === count( $journey_report['resources'] ) && $journey_program['items'][0]['content_id'] === $journey_report['resources'][0]['content_id'], 'Unpublished resources block publication and expose their exact repair resource.' );
	wp_update_post(
		array(
			'ID'          => $journey_program['items'][0]['content_id'],
			'post_status' => 'publish',
		)
	);
	wp_set_current_user( $journey_state['learner'] );
	$journey_outline = CourseOutline::get( $journey_state['course'], $journey_state['learner'] );
	journey_check( is_wp_error( LessonJourney::find( $journey_outline, $journey_group ) ), 'Unenrolled learners cannot open lesson pages.' );
	journey_check( 403 === journey_api( 'POST', 'courses/' . $journey_state['course'] . '/learning/activities/' . $journey_program['items'][0]['id'] . '/complete' )->get_status(), 'Unenrolled learners cannot write completion.' );
	wp_set_current_user( $journey_state['actor'] );
	$journey_enrolled = journey_api( 'PUT', 'courses/' . $journey_state['course'] . '/enroll', array( 'student_id' => $journey_state['learner'] ) );
	journey_check( $journey_enrolled->get_status() < 300, 'The existing enrollment controller enrolls the learner.' );
	wp_set_current_user( $journey_state['learner'] );
	$journey_outline = CourseOutline::get( $journey_state['course'], $journey_state['learner'] );
	$journey_lesson  = LessonJourney::find( $journey_outline, $journey_group );
	journey_check( ! is_wp_error( $journey_lesson ) && 3 === count( $journey_lesson['items'] ), 'The lesson includes its reading, video/reading and practice in published order.' );
	journey_check( 2 === $journey_lesson['activity_total'] && 3 === $journey_outline['summary']['activity_total'], 'Practice stays outside activity completion denominators.' );
	journey_check( $journey_outline['continue']['url'] === $journey_lesson['items'][0]['url'], 'Continue and lesson start agree on the first required activity.' );
	$journey_nav = LessonJourney::activity( $journey_outline, $journey_state['posts'][1], 'content' );
	journey_check( 'Introduction' === $journey_nav['lesson']['title'] && 'Watch numbers' === $journey_nav['next']['name'], 'Player context keeps the lesson and next activity.' );
	journey_check( is_wp_error( LessonJourney::find( $journey_outline, wp_generate_uuid4() ) ), 'Unknown lesson IDs do not expose another lesson.' );
	$journey_collision          = $journey_outline;
	$journey_collision['units'] = array(
		array(
			'items'  => array(
				array(
					'kind'       => 'lesson',
					'content_id' => 42,
					'name'       => 'Content',
					'available'  => true,
				),
				array(
					'kind'      => 'practice',
					'term_id'   => 42,
					'name'      => 'Skill',
					'available' => true,
				),
			),
			'groups' => array(),
		),
	);
	journey_check( 'Content' === LessonJourney::activity( $journey_collision, 42, 'content' )['current']['name'] && 'Skill' === LessonJourney::activity( $journey_collision, 42, 'practice' )['current']['name'], 'Navigation keeps content IDs separate from skill IDs.' );
	journey_check( 403 === journey_api( 'POST', 'courses/' . $journey_state['course'] . '/learning/activities/' . $journey_program['items'][3]['id'] . '/complete' )->get_status(), 'Practice cannot be manually marked complete as a reading.' );
	$journey_complete = journey_api( 'POST', 'courses/' . $journey_state['course'] . '/learning/activities/' . $journey_program['items'][0]['id'] . '/complete' );
	journey_check( 200 === $journey_complete->get_status(), 'The existing completion writer records the activity.' );
	$journey_outline = CourseOutline::get( $journey_state['course'], $journey_state['learner'] );
	journey_check( 1 === $journey_outline['summary']['activity_done'] && 0 === $journey_outline['summary']['proficient'], 'Completing an activity does not grant skill proficiency.' );
	journey_check( $journey_outline['continue']['url'] === $journey_lesson['items'][1]['url'], 'Reopening resumes at the next required activity.' );
	$journey_retry = journey_api( 'POST', 'courses/' . $journey_state['course'] . '/learning/activities/' . $journey_program['items'][0]['id'] . '/complete' );
	journey_check( 200 === $journey_retry->get_status() && 1 === CourseOutline::get( $journey_state['course'], $journey_state['learner'] )['summary']['activity_done'], 'Retrying completion does not double count activity progress.' );
	wp_set_current_user( $journey_state['actor'] );
	Catalog::rename_chapter( $journey_state['course'], $journey_unit, 'Draft-only unit title' );
	wp_set_current_user( $journey_state['learner'] );
	journey_check( 'Foundations' === CourseOutline::get( $journey_state['course'], $journey_state['learner'] )['units'][0]['title'], 'Learners retain published unit metadata while authors edit.' );
	$journey_locked = static function ( $locked, $content ) use ( $journey_state ) {
		return (int) $content === (int) $journey_state['posts'][2] ? true : $locked;
	};
	add_filter( 'ohmylms_is_lesson_locked', $journey_locked, 10, 2 );
	$journey_filtered = CourseOutline::get( $journey_state['course'], $journey_state['learner'] );
	journey_check( ! $journey_filtered['units'][0]['items'][1]['available'], 'Player navigation respects the existing activity lock filter.' );
	journey_check( 'Watch numbers' !== LessonJourney::activity( $journey_filtered, $journey_state['posts'][1], 'content' )['next']['name'], 'Next navigation skips unavailable activities.' );
	remove_filter( 'ohmylms_is_lesson_locked', $journey_locked, 10 );
	if ( in_array( $argv[1] ?? '', array( 'practice', 'practice-fixture' ), true ) ) {
		$journey_state['practice'] = true;
		$journey_guidance          = \OhMyLMS\Student\PracticeGuidance::get( $journey_state['course'], $journey_group );
		journey_check( 0 === $journey_guidance['skills'][0]['session_limit'] && null === $journey_guidance['recommended'], 'Empty question pools are visible and never recommended.' );
		wp_set_current_user( $journey_state['actor'] );
		for ( $journey_q = 0; $journey_q < 5; ++$journey_q ) {
			$journey_question = journey_api(
				'POST',
				'questions',
				array(
					'name'     => 'Guidance question ' . wp_generate_uuid4(),
					'settings' => array(
						'type'  => 'single-choice',
						'score' => array(
							'enabled' => true,
							'value'   => 1,
						),
					),
					'options'  => array(
						array(
							'answer'     => 'Right',
							'is_correct' => true,
						),
						array(
							'answer'     => 'Wrong',
							'is_correct' => false,
						),
					),
					'skills'   => array( 'p1' => array( 'primary' => $journey_state['skill'] ) ),
					'bank'     => array(
						'family_id' => wp_generate_uuid4(),
						'secure'    => 4 === $journey_q,
					),
				)
			);
			journey_check( 201 === $journey_question->get_status(), 'A practice fixture question is created through the question writer.' );
			$journey_question_id      = (int) $journey_question->get_data()['id'];
			$journey_state['posts'][] = $journey_question_id;
			if ( 3 !== $journey_q ) {
				journey_check( 200 === journey_api( 'POST', 'questions/' . $journey_question_id . '/approve' )->get_status(), 'Eligible and secure fixture versions are explicitly approved.' );
			}
		}
		wp_set_current_user( $journey_state['learner'] );
		$journey_guidance = \OhMyLMS\Student\PracticeGuidance::get( $journey_state['course'], $journey_group );
		$journey_report   = CourseProgram::readiness_report( CourseProgram::editor( $journey_state['course'] )['draft'], $journey_state['course'] );
		journey_check( 3 === $journey_report['practice_skills'][0]['questions'], 'Author readiness counts approved eligible questions and excludes unapproved and secure questions.' );
		journey_check( 3 === $journey_guidance['skills'][0]['session_limit'] && $journey_guidance['recommended']['term_id'] === $journey_state['skill'], 'Only approved non-secure questions are offered for the assigned skill.' );
		journey_check( is_wp_error( \OhMyLMS\Student\PracticeGuidance::get( $journey_state['course'], $journey_group, PHP_INT_MAX ) ), 'Foreign skill guidance is denied.' );
		$journey_started = journey_api(
			'POST',
			'practice/sessions',
			array(
				'term_id'    => $journey_state['skill'],
				'course_id'  => $journey_state['course'],
				'item_limit' => 3,
			)
		);
		journey_check( 201 === $journey_started->get_status(), 'The existing adaptive practice engine starts a course session.' );
		$journey_session = $journey_started->get_data();
		for ( $journey_answer = 0; $journey_answer < 3; ++$journey_answer ) {
			$journey_options  = array_column( $journey_session['current']['questions'], 'id', 'answer' );
			$journey_answered = journey_api(
				'POST',
				'practice/sessions/' . $journey_session['uuid'] . '/answer',
				array(
					'item_id'  => $journey_session['current']['item_id'],
					'response' => array( $journey_options['Right'] ),
				)
			);
			journey_check( 200 === $journey_answered->get_status(), 'The native grader accepts the fixture response token.' );
			$journey_session = $journey_answered->get_data()['session'];
		}
		journey_check( 'complete' === $journey_session['status'], 'The approved question session completes.' );
		$journey_after_practice = \OhMyLMS\Student\PracticeGuidance::get( $journey_state['course'], $journey_group );
		journey_check( 'not-started' !== $journey_after_practice['skills'][0]['level'], 'Evaluated practice updates the guidance level.' );
		journey_check( $journey_after_practice['skills'][0]['met'] ? null === $journey_after_practice['recommended'] && ! empty( $journey_after_practice['next'] ) : ! empty( $journey_after_practice['recommended'] ), 'Guidance chooses further practice or continuation from evaluated targets.' );
		wp_set_current_user( 0 );
		journey_check( 401 === journey_api( 'GET', 'courses/' . $journey_state['course'] . '/learning/practice-guidance' )->get_status(), 'Anonymous guidance requests are denied.' );
		wp_set_current_user( $journey_state['learner'] );
	}
	if ( in_array( $argv[1] ?? '', array( 'fixture', 'practice-fixture' ), true ) ) {
		journey_practice_reset( $journey_state );
		// Browser starts before enrollment and completion, then uses the real controllers again.
		global $wpdb;
		$journey_binding = CourseProgram::enrollment( $journey_state['learner'], $journey_state['course'] );
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_progress', array( 'enrollment_id' => $journey_binding['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reset this fixture's completed activity only.
		$wpdb->delete( Schema::table( 'enrollments' ), array( 'enrollment_id' => $journey_binding['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reset this fixture's binding only.
		$wpdb->delete( $wpdb->prefix . 'ohmylms_user_enrollment', array( 'id' => $journey_binding['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reset this fixture's enrollment only.
		$journey_expiry              = time() + HOUR_IN_SECONDS;
		$journey_token               = WP_Session_Tokens::get_instance( $journey_state['learner'] )->create( $journey_expiry );
		$journey_state['base']       = home_url();
		$journey_state['php_binary'] = PHP_BINARY;
		$journey_state['php_args']   = array( '-d', 'mysqli.default_port=' . ini_get( 'mysqli.default_port' ) );
		if ( 'Windows' === PHP_OS_FAMILY ) {
			$journey_state['php_args'] = array_merge( $journey_state['php_args'], array( '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=mysqli', '-d', 'extension=mbstring' ) );
		}
		$journey_state['overview'] = Hub::course_url( $journey_state['course'] );
		$journey_state['lesson']   = $journey_group;
		$journey_state['first']    = $journey_program['items'][0]['id'];
		foreach ( array(
			LOGGED_IN_COOKIE => 'logged_in',
			AUTH_COOKIE      => 'auth',
		) as $cookie => $scheme ) {
			$journey_state['cookies'][] = array(
				'name'     => $cookie,
				'value'    => wp_generate_auth_cookie( $journey_state['learner'], $journey_expiry, $scheme, $journey_token ),
				'domain'   => wp_parse_url( home_url(), PHP_URL_HOST ),
				'path'     => '/',
				'expires'  => $journey_expiry,
				'httpOnly' => true,
				'secure'   => false,
				'sameSite' => 'Lax',
			);
		}
		file_put_contents( $journey_path, wp_json_encode( $journey_state ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Ignored fixture removed during cleanup.
		$journey_keep = true;
	}
	echo 'PASS: ' . (int) $journey_checks . " learner journey checks.\n";
} finally {
	if ( ! $journey_keep ) {
		journey_cleanup( $journey_state );
	}
}
