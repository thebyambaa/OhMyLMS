<?php
/**
 * Unified outline writes and assessment placement integration checks.
 *
 * @package OhMyLMS
 */

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_STRUCTURE_INTEGRATION' ) ) {
	exit( "Set OHMYLMS_STRUCTURE_INTEGRATION=1 for local outline checks.\n" );
}
require dirname( __DIR__, 5 ) . '/wp-load.php';

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Learning\Catalog;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Learning\Schema;

/**
 * Call a structure API with JSON and a read revision.
 *
 * @param string $method HTTP method.
 * @param string $path Route suffix.
 * @param array  $data Body.
 * @return WP_REST_Response
 */
function outline_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	$request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( $data ) );
	return rest_do_request( $request );
}

/**
 * Assert one behavior.
 *
 * @param bool   $result Expected result.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function outline_check( $result, $message ) {
	global $outline_checks;
	if ( ! $result ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Local CLI diagnostic.
	}
	++$outline_checks;
}

$outline_checks = 0;
$outline_posts  = array();
$outline_admin  = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
	)
)[0]->ID;
try {
	wp_set_current_user( $outline_admin );
	$outline_course  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_title'  => 'Unified outline fixture',
			'post_status' => 'publish',
		)
	);
	$outline_posts[] = $outline_course;
	$outline_first   = Catalog::add_chapter( $outline_course, 'First unit' )['id'];
	$outline_second  = Catalog::add_chapter( $outline_course, 'Second unit' )['id'];
	foreach ( array( 'Resource one', 'Resource two' ) as $outline_title ) {
		$outline_posts[] = wp_insert_post(
			array(
				'post_type'    => OHMYLMS_LESSON_CPT,
				'post_title'   => $outline_title,
				'post_content' => 'Reusable content.',
				'post_status'  => 'publish',
			)
		);
	}
	$outline_one     = $outline_posts[1];
	$outline_two     = $outline_posts[2];
	$outline_quizzes = array();
	foreach ( array( 'unit-test', 'unit-test', 'course-challenge', 'course-challenge' ) as $outline_style ) {
		$outline_quiz      = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_QUIZ_CPT,
				'post_title'  => 'Outline assessment fixture',
				'post_status' => 'publish',
			)
		);
		$outline_posts[]   = $outline_quiz;
		$outline_quizzes[] = $outline_quiz;
		update_post_meta( $outline_quiz, AssessmentSettings::META, array( 'style' => $outline_style ) );
	}
	$outline_program          = CourseProgram::defaults( $outline_course );
	$outline_program['items'] = array(
		array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'lesson',
			'content_id' => $outline_one,
			'chapter_id' => $outline_first,
			'required'   => true,
		),
		array(
			'id'         => wp_generate_uuid4(),
			'type'       => 'lesson',
			'content_id' => $outline_two,
			'chapter_id' => $outline_second,
			'required'   => true,
		),
	);
	$outline_published        = outline_api( 'POST', 'courses/' . $outline_course . '/learning/publish', $outline_program + array( 'revision' => Catalog::revision( $outline_course ) ) );
	outline_check( 200 === $outline_published->get_status(), 'Initial program publishes.' );
	$outline_old_program                       = CourseProgram::current( $outline_course );
	$outline_read                              = outline_api( 'GET', 'courses/' . $outline_course . '/structure' )->get_data();
	$outline_temp                              = wp_generate_uuid4();
	$outline_lesson                            = wp_generate_uuid4();
	$outline_units                             = array(
		array(
			'id'          => $outline_second,
			'title'       => 'Second unit',
			'number'      => '',
			'description' => '',
		),
		array(
			'id'          => $outline_first,
			'title'       => 'Renamed first unit',
			'number'      => '1A',
			'description' => '<p>Draft detail.</p>',
		),
		array(
			'id'          => $outline_temp,
			'title'       => 'New unit',
			'number'      => '3',
			'description' => '',
		),
	);
	$outline_program['items'][0]['chapter_id'] = $outline_temp;
	$outline_program['items'][0]['group_id']   = $outline_lesson;
	$outline_program['items'][0]['title']      = 'Course-specific activity name';
	$outline_program['groups']                 = array(
		array(
			'id'         => $outline_lesson,
			'chapter_id' => $outline_temp,
			'title'      => 'Introduction',
		),
	);
	$outline_program['items'][]                = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'quiz',
		'content_id' => $outline_quizzes[0],
		'chapter_id' => $outline_temp,
		'required'   => true,
	);
	$outline_program['items'][]                = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'quiz',
		'content_id' => $outline_quizzes[2],
		'chapter_id' => 0,
		'required'   => true,
	);
	$outline_saved                             = outline_api(
		'PUT',
		'courses/' . $outline_course . '/structure',
		array(
			'revision' => $outline_read['revision'],
			'units'    => $outline_units,
			'program'  => $outline_program,
		)
	);
	outline_check( 200 === $outline_saved->get_status(), 'Atomic outline save succeeds: ' . wp_json_encode( $outline_saved->get_data() ) );
	$outline_data         = $outline_saved->get_data();
	$outline_native_units = array_values(
		array_filter(
			$outline_data['units'],
			static function ( $row ) {
				return $row['id'] > 0;
			}
		)
	);
	$outline_new          = $outline_data['units'][2]['id'];
	outline_check( is_int( $outline_new ) && $outline_new > 0 && $outline_new !== $outline_first, 'A temporary unit UUID resolves to a real native unit.' );
	outline_check( array_column( $outline_native_units, 'id' ) === array( $outline_second, $outline_first, $outline_new ), 'Unit order and existing IDs are retained.' );
	// Since b0e21ca5 a lesson is a block lesson item, not a folder: submitted lesson groups are flattened into their unit.
	outline_check( ! isset( $outline_data['program']['groups'] ) && ! array_filter( array_column( $outline_data['program']['items'], 'group_id' ) ), 'Lesson groups are flattened into their unit.' );
	outline_check( $outline_data['program']['items'][1]['id'] === $outline_program['items'][0]['id'] && $outline_data['program']['items'][1]['chapter_id'] === $outline_new, 'Activity identity and its new unit are retained.' );
	outline_check( 'Course-specific activity name' === $outline_data['program']['items'][1]['title'], 'The course-specific activity label is kept.' );
	outline_check( 'Resource one' === get_post_field( 'post_title', $outline_one ) && 'Reusable content.' === get_post_field( 'post_content', $outline_one ), 'Inline course labels do not rename or overwrite shared resources.' );
	outline_check( CourseProgram::current( $outline_course ) === $outline_old_program, 'Outline save remains a draft.' );
	outline_check(
		409 === outline_api(
			'PUT',
			'courses/' . $outline_course . '/structure',
			array(
				'revision' => $outline_read['revision'],
				'units'    => $outline_units,
				'program'  => $outline_program,
			)
		)->get_status(),
		'A stale unified save is rejected.'
	);
	$outline_revision               = Catalog::revision( $outline_course );
	$outline_bad_units              = $outline_native_units;
	$outline_bad_units[0]['title']  = 'Must roll back';
	$outline_bad_units[1]['number'] = array();
	$outline_bad                    = outline_api(
		'PUT',
		'courses/' . $outline_course . '/structure',
		array(
			'revision' => $outline_revision,
			'units'    => $outline_bad_units,
			'program'  => $outline_data['program'],
		)
	);
	outline_check( 400 === $outline_bad->get_status() && 'Second unit' === get_post_field( 'post_title', $outline_second ) && Catalog::revision( $outline_course ) === $outline_revision, 'A later unit validation failure rolls back earlier unit writes and the revision.' );
	$outline_bad_units                             = $outline_native_units;
	$outline_bad_units[]                           = array(
		'id'    => wp_generate_uuid4(),
		'title' => 'Rollback temporary unit',
	);
	$outline_bad_program                           = $outline_data['program'];
	$outline_bad_program['items'][0]['chapter_id'] = 999999999;
	$outline_bad                                   = outline_api(
		'PUT',
		'courses/' . $outline_course . '/structure',
		array(
			'revision' => $outline_revision,
			'units'    => $outline_bad_units,
			'program'  => $outline_bad_program,
		)
	);
	outline_check( 400 === $outline_bad->get_status() && 3 === count( Catalog::chapters( $outline_course ) ) && Catalog::revision( $outline_course ) === $outline_revision, 'Invalid program references roll back newly created units.' );
	$outline_good                                  = $outline_data['program'];
	$outline_cases                                 = array();
	$outline_bad_program                           = $outline_good;
	$outline_bad_program['items'][2]['chapter_id'] = 0;
	$outline_cases[]                               = $outline_bad_program;
	$outline_bad_program                           = $outline_good;
	$outline_bad_program['items'][3]['chapter_id'] = $outline_new;
	$outline_cases[]                               = $outline_bad_program;
	$outline_bad_program                           = $outline_good;
	$outline_bad_program['items'][]                = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'quiz',
		'content_id' => $outline_quizzes[1],
		'chapter_id' => $outline_new,
	);
	$outline_cases[]                               = $outline_bad_program;
	$outline_bad_program                           = $outline_good;
	$outline_bad_program['items'][]                = array(
		'id'         => wp_generate_uuid4(),
		'type'       => 'quiz',
		'content_id' => $outline_quizzes[3],
		'chapter_id' => 0,
	);
	$outline_cases[]                               = $outline_bad_program;
	$outline_bad_program                           = $outline_good;
	$outline_bad_program['items']                  = array( $outline_good['items'][2], $outline_good['items'][0], $outline_good['items'][1], $outline_good['items'][3] );
	$outline_cases[]                               = $outline_bad_program;
	$outline_bad_program                           = $outline_good;
	$outline_bad_program['items']                  = array( $outline_good['items'][3], $outline_good['items'][0], $outline_good['items'][1], $outline_good['items'][2] );
	$outline_cases[]                               = $outline_bad_program;
	foreach ( $outline_cases as $outline_invalid ) {
		outline_check( 400 === outline_api( 'PUT', 'courses/' . $outline_course . '/learning', $outline_invalid + array( 'revision' => $outline_revision ) )->get_status(), 'Invalid unit-test/challenge placement is rejected by the shared learning writer.' );
	}
	$outline_publish = outline_api( 'POST', 'courses/' . $outline_course . '/structure/publish', array( 'revision' => Catalog::revision( $outline_course ) ) );
	outline_check( 200 === $outline_publish->get_status(), 'The valid grouped outline publishes.' );
	$outline_learner = \OhMyLMS\Student\CourseOutline::get( $outline_course, 0 );
	outline_check( $outline_new === $outline_learner['units'][1]['id'] && array() === $outline_learner['units'][1]['groups'] && 'Course-specific activity name' === $outline_learner['units'][1]['ungrouped'][0]['name'], 'Learners see the activity ungrouped in its new unit with the course-specific label: ' . wp_json_encode( $outline_learner['units'][1] ?? null ) );
	outline_check( 'unit-test' === $outline_learner['units'][1]['test']['style'] && 'course-challenge' === $outline_learner['challenge']['style'], 'Published checkpoint locations are exposed correctly.' );
	wp_set_current_user( 0 );
	outline_check( 401 === outline_api( 'PUT', 'courses/' . $outline_course . '/structure' )->get_status(), 'Anonymous unified writes are denied.' );
	echo 'PASS: ' . (int) $outline_checks . " unified outline checks.\n";
} finally {
	wp_set_current_user( $outline_admin );
	global $wpdb;
	foreach ( Catalog::chapters( $outline_course ?? 0 ) as $outline_unit ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_content_relationship', array( 'chapter_id' => $outline_unit['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture rows only.
		wp_delete_post( $outline_unit['id'], true );
	}
	$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $outline_course ?? 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture unit relationships only.
	$wpdb->delete( Schema::table( 'programs' ), array( 'course_id' => $outline_course ?? 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture programs have no skill outcomes.
	foreach ( array_reverse( $outline_posts ) as $outline_post_id ) {
		wp_delete_post( $outline_post_id, true );
	}
}
