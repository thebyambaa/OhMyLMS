<?php
/**
 * Course skill coverage REST integration test.
 *
 * Runs against a WordPress install (the Local site by default). It builds a course with units, lessons,
 * a quiz, skill outcomes, a syllabus objective and an approved question pool, reads
 * `ohmylms/v1/courses/{id}/skill-coverage`, and removes everything in `finally`.
 *
 * Usage (set OHMYLMS_SKILL_COVERAGE_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/skill-coverage-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SKILL_COVERAGE_TEST' ) ) {
	exit( "Set OHMYLMS_SKILL_COVERAGE_TEST=1 to run the skill coverage REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Studio\CourseLayout;

global $wpdb;

$checks  = 0;
$posts   = array();
$terms   = array();
$chapter = array();
$users   = array();
$course  = 0;
$failed  = false;
$mark    = 'covtest' . strtolower( wp_generate_password( 6, false, false ) );

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Dispatch a REST request.
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1.
 * @param array  $data   Body (JSON).
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( $data ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

try {
	$admins = get_users(
		array(
			'role'   => 'administrator',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	wp_set_current_user( (int) $admins[0] );
	$rules = Mastery::rules();

	// Four skills: A is required at "mastered" and well supplied; B is required but thin and only in a quiz;
	// C is taught but has no questions; D is only a syllabus objective.
	$skill = array();
	foreach ( array( 'A', 'B', 'C', 'D' ) as $name ) {
		$made = call_api( 'POST', '/skills', array( 'name' => "$mark skill $name" ) );
		ok( 201 === $made->get_status(), "skill $name is created" );
		$skill[ $name ] = (int) $made->get_data()['id'];
		$terms[]        = $skill[ $name ];
	}
	$make_question = static function ( $term_id, $family ) use ( $mark, &$posts ) {
		$made = call_api(
			'POST',
			'/questions',
			array(
				'name'     => "$mark question",
				'settings' => array(
					'type'  => 'single-choice',
					'score' => array(
						'enabled' => true,
						'value'   => 1,
					),
				),
				'options'  => array(
					array(
						'answer'     => 'yes',
						'is_correct' => true,
					),
					array(
						'answer'     => 'no',
						'is_correct' => false,
					),
				),
				'skills'   => array(
					'p1' => array(
						'primary'    => $term_id,
						'supporting' => array(),
					),
				),
				'bank'     => array(
					'difficulty' => 'standard',
					'family_id'  => $family,
				),
			)
		);
		ok( 201 === $made->get_status(), 'question is created' );
		$id      = (int) ( $made->get_data()['created_id'] ?? $made->get_data()['id'] );
		$posts[] = $id;
		ok( 200 === call_api( 'POST', "/questions/$id/approve" )->get_status(), 'question is approved' );
	};
	$need_a = max( (int) $rules['mastered_min'], (int) $rules['mastered_families'] );
	for ( $i = 0; $i < $need_a; ++$i ) {
		$make_question( $skill['A'], "$mark-fam-" . ( $i % max( 1, (int) $rules['mastered_families'] ) ) );
	}
	$make_question( $skill['B'], "$mark-fam-b" );

	$course  = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'draft',
			'post_title'  => "$mark course",
		)
	);
	$posts[] = $course;
	$unit    = array();
	foreach ( array( 'One', 'Two' ) as $name ) {
		$id        = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_CHAPTER_CPT,
				'post_status' => 'publish',
				'post_title'  => "$mark unit $name",
			)
		);
		$posts[]   = $id;
		$chapter[] = $id;
		CourseLayout::append_chapter( $course, $id );
		$unit[ $name ] = $id;
	}
	$lesson_a = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'publish',
			'post_title'  => "$mark lesson A",
		)
	);
	$lesson_c = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'publish',
			'post_title'  => "$mark lesson C",
		)
	);
	$quiz     = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_status' => 'publish',
			'post_title'  => "$mark quiz",
		)
	);
	array_push( $posts, $lesson_a, $lesson_c, $quiz );
	wp_set_object_terms( $lesson_a, array( $skill['A'] ), Taxonomy::NAME );
	wp_set_object_terms( $lesson_c, array( $skill['C'] ), Taxonomy::NAME );
	wp_set_object_terms( $quiz, array( $skill['B'] ), Taxonomy::NAME );
	update_post_meta(
		$course,
		CourseProgram::DRAFT,
		array(
			'mode'            => 'traditional',
			'recognize_prior' => true,
			'evidence_days'   => 0,
			'bank_ids'        => array(),
			'groups'          => array(),
			'items'           => array(
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'lesson',
					'content_id' => $lesson_a,
					'chapter_id' => $unit['One'],
					'required'   => true,
				),
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'lesson',
					'content_id' => $lesson_c,
					'chapter_id' => $unit['Two'],
					'required'   => true,
				),
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'quiz',
					'content_id' => $quiz,
					'chapter_id' => $unit['Two'],
					'required'   => true,
				),
			),
			'outcomes'        => array(
				array(
					'term_id'    => $skill['A'],
					'target'     => 'mastered',
					'required'   => true,
					'chapter_id' => $unit['One'],
					'group_id'   => '',
				),
				array(
					'term_id'    => $skill['B'],
					'target'     => 'proficient',
					'required'   => true,
					'chapter_id' => $unit['Two'],
					'group_id'   => '',
				),
			),
		)
	);
	update_post_meta(
		$course,
		'_ohmylms_course_objectives',
		array(
			array(
				'syllabus_id' => 1,
				'term_id'     => $skill['D'],
			),
		)
	);

	$response = call_api( 'GET', "/courses/$course/skill-coverage" );
	ok( 200 === $response->get_status(), 'the report is returned: ' . wp_json_encode( $response->get_data() ) );
	$report = $response->get_data();
	ok( $course === $report['course_id'], 'the report names the course' );
	$by = array();
	foreach ( $report['skills'] as $row ) {
		$by[ $row['term_id'] ] = $row;
	}
	ok( 4 === count( $by ) && isset( $by[ $skill['A'] ], $by[ $skill['B'] ], $by[ $skill['C'] ], $by[ $skill['D'] ] ), 'the four skills of the course are listed' );

	$a = $by[ $skill['A'] ];
	ok( 'covered' === $a['status'] && $a['required'] && 'mastered' === $a['target'], 'A is a covered required skill' );
	ok( 1 === count( $a['teaching'] ) && $lesson_a === $a['teaching'][0]['id'] && "$mark unit One" === $a['teaching'][0]['unit'], 'A is taught by its lesson, in its unit' );
	ok( $a['questions'] >= (int) $rules['mastered_min'] && $a['families'] >= (int) $rules['mastered_families'], 'A has the questions and families mastery needs' );

	$b = $by[ $skill['B'] ];
	ok( 'needs-both' === $b['status'] && $b['required'], 'B is required, not taught and short of questions' );
	ok( 0 === count( $b['teaching'] ) && 1 === count( $b['assessing'] ) && $quiz === $b['assessing'][0]['id'], 'B is only tagged on a quiz, which is not teaching' );
	ok( 1 === $b['questions'] && (int) $rules['proficient_min'] === $b['questions_need'], 'B reports one question against the proficient need' );

	$c = $by[ $skill['C'] ];
	ok( 'needs-questions' === $c['status'] && ! $c['outcome'], 'C is taught but has no questions' );
	ok( 'default' === $c['need_basis'] && 0 === $c['questions'], 'C is judged by the default thresholds' );

	$d = $by[ $skill['D'] ];
	ok( 'needs-both' === $d['status'] && $d['objective'] && ! $d['outcome'], 'D is a syllabus objective with nothing behind it' );

	$summary = $report['summary'];
	ok( 4 === $summary['skills'] && 2 === $summary['required'] && 1 === $summary['covered'] && 2 === $summary['needs_both'] && 1 === $summary['needs_questions'], 'the summary counts each status' );
	ok( 1 === $summary['objectives'] && 0 === $summary['objectives_taught'] && 1 === $summary['required_not_ready'], 'the summary counts objectives and required skills that are not ready' );
	ok( $by[ $skill['B'] ]['term_id'] === $report['skills'][0]['term_id'], 'the required gap is listed first' );

	// Publication readiness agrees with the report on the required skills.
	$ready = CourseProgram::readiness_report( CourseProgram::editor( $course )['draft'], $course );
	$flags = array();
	foreach ( $ready['skills'] as $row ) {
		$flags[ $row['term_id'] ] = $row['ready'];
	}
	ok( true === $flags[ $skill['A'] ] && false === $flags[ $skill['B'] ], 'publication readiness and the coverage report agree on which required skills have enough questions' );
	ok( $a['questions'] === $ready['skills'][0]['questions'] || $a['questions'] === $ready['skills'][1]['questions'], 'both count the same questions for a skill' );

	// A fixed pool of banks limits what counts, as it does for publication.
	$program             = CourseProgram::editor( $course )['draft'];
	$program['bank_ids'] = array( 999999 );
	update_post_meta( $course, CourseProgram::DRAFT, $program );
	$limited = call_api( 'GET', "/courses/$course/skill-coverage" )->get_data();
	$limit_a = array_values(
		array_filter(
			$limited['skills'],
			static function ( $row ) use ( $skill ) {
				return $row['term_id'] === $skill['A'];
			}
		)
	)[0];
	ok( 0 === $limit_a['questions'] && 'needs-questions' === $limit_a['status'], 'questions outside the course banks do not count' );

	// Who may read it.
	ok( 404 === call_api( 'GET', "/courses/$lesson_a/skill-coverage" )->get_status(), 'a post that is not a course is refused' );
	$sub     = wp_create_user( $mark . 'sub', wp_generate_password( 24 ), $mark . 'sub@example.invalid' );
	$users[] = $sub;
	( new WP_User( $sub ) )->set_role( 'subscriber' );
	wp_set_current_user( $sub );
	ok( in_array( call_api( 'GET', "/courses/$course/skill-coverage" )->get_status(), array( 401, 403 ), true ), 'a subscriber cannot read coverage' );
	wp_set_current_user( (int) $admins[0] );

	echo "Skill coverage REST: $checks checks passed." . PHP_EOL;
} catch ( Throwable $error ) {
	echo $error->getMessage() . PHP_EOL;
	$failed = true;
} finally {
	if ( $course ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $course ) );
	}
	foreach ( $posts as $post_id ) {
		if ( $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( $terms as $term_id ) {
		wp_delete_term( $term_id, Taxonomy::NAME );
	}
	foreach ( $users as $user_id ) {
		wp_delete_user( (int) $user_id );
	}
}
if ( $failed ) {
	exit( 1 );
}
