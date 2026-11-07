<?php
/**
 * Course skill plan REST integration test.
 *
 * Runs against a WordPress install (the Local site by default). It builds a syllabus with a topic, two skill
 * groups and three skills, and a course with two units and two lessons, then reads
 * `ohmylms/v1/courses/{id}/skill-plan`, chooses unit topics and connects lessons to skills. Everything it creates
 * is removed in `finally`.
 *
 * Usage (set OHMYLMS_SKILL_PLAN_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/skill-plan-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SKILL_PLAN_TEST' ) ) {
	exit( "Set OHMYLMS_SKILL_PLAN_TEST=1 to run the skill plan REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Learning\CourseProgram;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Studio\CourseLayout;

global $wpdb;

$checks    = 0;
$posts     = array();
$terms     = array();
$users     = array();
$syllabus  = 0;
// Not `$course`: the plugin assigns to a global of that name when a lesson is created, which would clobber it here.
$course_id = 0;
$failed    = false;
$mark      = 'plantest' . strtolower( wp_generate_password( 6, false, false ) );

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
	$query   = array();
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . strtok( $path, '?' ) );
	if ( false !== strpos( $path, '?' ) ) {
		parse_str( (string) substr( strstr( $path, '?' ), 1 ), $query );
		$request->set_query_params( $query );
	}
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

	// A syllabus with one topic, two groups and three skills.
	$item = Items::create(
		array(
			'name'      => "$mark syllabus",
			'item_type' => 'syllabus',
		)
	);
	ok( ! is_wp_error( $item ), 'the syllabus is created' );
	$syllabus = (int) $item['id'];
	$topic    = Items::create(
		array(
			'name'      => "$mark topic",
			'item_type' => 'topic',
			'parent_id' => $syllabus,
		)
	);
	ok( ! is_wp_error( $topic ), 'the topic is created' );
	$topic_id = (int) $topic['id'];
	$other    = Items::create(
		array(
			'name'      => "$mark other topic",
			'item_type' => 'topic',
			'parent_id' => $syllabus,
		)
	);
	$other_id = (int) $other['id'];
	$groups   = array();
	foreach ( array( 'Linear', 'Shapes' ) as $name ) {
		$group = Syllabus::add_group(
			$syllabus,
			array(
				'name'    => "$mark $name",
				'code'    => strtoupper( substr( $name, 0, 1 ) ),
				'item_id' => 'Linear' === $name ? $topic_id : $other_id,
			)
		);
		ok( ! is_wp_error( $group ), "group $name is created: " . ( is_wp_error( $group ) ? $group->get_error_message() : '' ) );
		$groups[ $name ] = (int) ( is_array( $group ) ? $group['id'] : $group );
	}
	$skill = array();
	foreach ( array(
		'one'   => 'Linear',
		'two'   => 'Linear',
		'three' => 'Shapes',
	) as $name => $group ) {
		$made = Syllabus::create_skill(
			$syllabus,
			$groups[ $group ],
			array(
				'name' => "$mark skill $name",
				'code' => "$mark.$name",
			)
		);
		ok( ! is_wp_error( $made ), "skill $name is created: " . ( is_wp_error( $made ) ? $made->get_error_message() : '' ) );
		$skill[ $name ] = (int) $made['term_id'];
		$terms[]        = $skill[ $name ];
	}

	// A course with two units and two lessons placed in them.
	$course_id = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_COURSE_CPT,
			'post_status' => 'draft',
			'post_title'  => "$mark course",
		)
	);
	$posts[]   = $course_id;
	$unit      = array();
	foreach ( array( 'One', 'Two' ) as $name ) {
		$id            = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_CHAPTER_CPT,
				'post_status' => 'publish',
				'post_title'  => "$mark unit $name",
			)
		);
		$posts[]       = $id;
		CourseLayout::append_chapter( $course_id, $id );
		$unit[ $name ] = $id;
	}
	$lesson = array();
	foreach ( array( 'A', 'B' ) as $name ) {
		$lesson[ $name ] = wp_insert_post(
			array(
				'post_type'   => OHMYLMS_LESSON_CPT,
				'post_status' => 'publish',
				'post_title'  => "$mark lesson $name",
			)
		);
		$posts[]         = $lesson[ $name ];
	}
	$quiz    = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_status' => 'publish',
			'post_title'  => "$mark quiz",
		)
	);
	$posts[] = $quiz;
	$outside = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_status' => 'publish',
			'post_title'  => "$mark lesson outside",
		)
	);
	$posts[] = $outside;
	update_post_meta(
		$course_id,
		CourseProgram::DRAFT,
		array(
			'mode'            => 'traditional',
			'recognize_prior' => true,
			'evidence_days'   => 0,
			'bank_ids'        => array(),
			'groups'          => array(),
			'outcomes'        => array(),
			'items'           => array(
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'lesson',
					'content_id' => $lesson['A'],
					'chapter_id' => $unit['One'],
					'required'   => true,
				),
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'lesson',
					'content_id' => $lesson['B'],
					'chapter_id' => $unit['Two'],
					'required'   => true,
				),
				array(
					'id'         => wp_generate_uuid4(),
					'type'       => 'quiz',
					'content_id' => $quiz,
					'chapter_id' => $unit['One'],
					'required'   => true,
				),
			),
		)
	);
	wp_set_object_terms( $lesson['A'], array( $skill['one'] ), Taxonomy::NAME );

	// Reading the plan for the syllabus.
	$response = call_api( 'GET', "/courses/$course_id/skill-plan?curriculum=$syllabus" );
	ok( 200 === $response->get_status(), 'the plan is returned: ' . wp_json_encode( $response->get_data() ) );
	$plan = $response->get_data();
	ok( $course_id === $plan['course_id'] && $syllabus === $plan['curriculum'], 'the plan names the course and the chosen curriculum' );
	ok( in_array( $syllabus, array_column( $plan['curricula'], 'id' ), true ), 'the syllabus can be chosen' );
	$by_topic = array();
	foreach ( $plan['topics'] as $row ) {
		$by_topic[ $row['id'] ] = $row;
	}
	ok( isset( $by_topic[ $topic_id ], $by_topic[ $other_id ] ), 'both topics are listed' );
	ok( array( $skill['one'], $skill['two'] ) === $by_topic[ $topic_id ]['groups'][0]['skills'], 'the topic lists its group and skills in order' );
	ok( 3 === count( (array) $plan['names'] ) && "$mark skill one" === ( (array) $plan['names'] )[ $skill['one'] ]['name'], 'the skill names are listed' );
	$taught = (array) $plan['skills'];
	ok( isset( $taught[ $skill['one'] ] ) && $lesson['A'] === $taught[ $skill['one'] ]['teaching'][0]['id'], 'a skill connected to a placed lesson is taught by it' );
	ok( ! isset( $taught[ $skill['two'] ] ), 'a skill no lesson teaches is not in the course' );
	ok( 2 === count( $plan['units'] ), 'the units of the course are listed' );
	ok( 0 === count( (array) $plan['unit_topics'] ), 'no unit has topics yet' );

	$bad = call_api( 'GET', "/courses/$course_id/skill-plan?curriculum=999999" );
	ok( 400 === $bad->get_status(), 'an unknown curriculum is refused' );

	// Unit topics.
	$set = call_api( 'PUT', "/courses/$course_id/skill-plan/units/{$unit['One']}", array( 'topic_ids' => array( $topic_id, $topic_id ) ) );
	ok( 200 === $set->get_status(), 'topics are chosen for a unit: ' . wp_json_encode( $set->get_data() ) );
	$map = (array) $set->get_data()['unit_topics'];
	ok( array( $topic_id ) === $map[ $unit['One'] ], 'the unit keeps its topics, once each' );
	$again = (array) call_api( 'GET', "/courses/$course_id/skill-plan?curriculum=$syllabus" )->get_data()['unit_topics'];
	ok( array( $topic_id ) === $again[ $unit['One'] ], 'the plan reads them back' );
	ok( 400 === call_api( 'PUT', "/courses/$course_id/skill-plan/units/$lesson[A]", array( 'topic_ids' => array( $topic_id ) ) )->get_status(), 'a post that is not a unit of the course is refused' );
	ok( 400 === call_api( 'PUT', "/courses/$course_id/skill-plan/units/{$unit['One']}", array( 'topic_ids' => array( 99999999 ) ) )->get_status(), 'a topic that does not exist is refused' );
	$cleared = (array) call_api( 'PUT', "/courses/$course_id/skill-plan/units/{$unit['One']}", array( 'topic_ids' => array() ) )->get_data()['unit_topics'];
	ok( ! isset( $cleared[ $unit['One'] ] ), 'an empty list clears the choice' );

	// Connecting lessons.
	$link = call_api( 'PUT', "/courses/$course_id/skill-plan/lessons/{$lesson['B']}", array( 'add' => array( $skill['two'], $skill['three'] ) ) );
	ok( 200 === $link->get_status(), 'skills are connected to a lesson: ' . wp_json_encode( $link->get_data() ) );
	$data = $link->get_data();
	ok( array( $skill['three'], $skill['two'] ) === array_values( array_intersect( array( $skill['three'], $skill['two'] ), $data['content_skills'] ) ) || 2 === count( $data['content_skills'] ), 'the lesson has both skills' );
	$taught = (array) $data['skills'];
	ok( isset( $taught[ $skill['two'] ] ) && $lesson['B'] === $taught[ $skill['two'] ]['teaching'][0]['id'], 'the teaching map is updated at once' );
	$after = wp_get_object_terms( $lesson['B'], Taxonomy::NAME, array( 'fields' => 'ids' ) );
	ok( 2 === count( $after ), 'the skills are the ordinary terms of the lesson' );
	$drop = call_api( 'PUT', "/courses/$course_id/skill-plan/lessons/{$lesson['B']}", array( 'remove' => array( $skill['three'] ) ) );
	ok( 200 === $drop->get_status() && array( $skill['two'] ) === array_map( 'intval', $drop->get_data()['content_skills'] ), 'a skill is disconnected and the others stay' );
	ok( 400 === call_api( 'PUT', "/courses/$course_id/skill-plan/lessons/$outside", array( 'add' => array( $skill['one'] ) ) )->get_status(), 'a lesson that is not in the course is refused' );
	ok( empty( wp_get_object_terms( $outside, Taxonomy::NAME, array( 'fields' => 'ids' ) ) ), 'and nothing was connected to it' );
	ok( 400 === call_api( 'PUT', "/courses/$course_id/skill-plan/lessons/{$lesson['A']}", array( 'add' => array( 99999999 ) ) )->get_status(), 'a skill that does not exist is refused' );

	// Connecting quizzes: their skills live in the quiz's own table.
	$qlink = call_api( 'PUT', "/courses/$course_id/skill-plan/quizzes/$quiz", array( 'add' => array( $skill['one'], $skill['two'] ) ) );
	ok( 200 === $qlink->get_status(), 'skills are connected to a quiz: ' . wp_json_encode( $qlink->get_data() ) );
	ok( 2 === count( \OhMyLMS\Quiz\QuizSkills::get( $quiz ) ), 'they are stored as the quiz skills the quiz editor and evidence use' );
	$assessing = (array) $qlink->get_data()['skills'];
	ok( isset( $assessing[ $skill['two'] ] ) && $quiz === $assessing[ $skill['two'] ]['assessing'][0]['id'], 'the quiz is listed as assessing the skill' );
	ok( empty( $assessing[ $skill['two'] ]['teaching'] ) || $lesson['B'] === $assessing[ $skill['two'] ]['teaching'][0]['id'], 'a quiz never counts as teaching' );
	$qdrop = call_api( 'PUT', "/courses/$course_id/skill-plan/quizzes/$quiz", array( 'remove' => array( $skill['one'] ) ) );
	ok( 200 === $qdrop->get_status() && array( $skill['two'] ) === array_map( 'intval', $qdrop->get_data()['content_skills'] ), 'a skill is disconnected from the quiz and the others stay' );
	ok( 400 === call_api( 'PUT', "/courses/$course_id/skill-plan/quizzes/{$lesson['A']}", array( 'add' => array( $skill['one'] ) ) )->get_status(), 'a lesson is not accepted on the quiz route' );
	ok( 400 === call_api( 'PUT', "/courses/$course_id/skill-plan/lessons/$quiz", array( 'add' => array( $skill['one'] ) ) )->get_status(), 'a quiz is not accepted on the lesson route' );
	$plan2 = (array) call_api( 'GET', "/courses/$course_id/skill-plan?curriculum=$syllabus" )->get_data()['skills'];
	ok( isset( $plan2[ $skill['two'] ] ) && $quiz === $plan2[ $skill['two'] ]['assessing'][0]['id'], 'the plan reads the quiz connection back' );

	// Who may use it.
	ok( 404 === call_api( 'GET', "/courses/$lesson[A]/skill-plan" )->get_status(), 'a post that is not a course is refused' );
	$sub     = wp_create_user( $mark . 'sub', wp_generate_password( 24 ), $mark . 'sub@example.invalid' );
	$users[] = $sub;
	( new WP_User( $sub ) )->set_role( 'subscriber' );
	wp_set_current_user( $sub );
	ok( in_array( call_api( 'GET', "/courses/$course_id/skill-plan" )->get_status(), array( 401, 403 ), true ), 'a subscriber cannot read the plan' );
	ok( in_array( call_api( 'PUT', "/courses/$course_id/skill-plan/units/{$unit['One']}", array( 'topic_ids' => array( $topic_id ) ) )->get_status(), array( 401, 403 ), true ), 'a subscriber cannot choose unit topics' );
	ok( in_array( call_api( 'PUT', "/courses/$course_id/skill-plan/lessons/{$lesson['A']}", array( 'add' => array( $skill['two'] ) ) )->get_status(), array( 401, 403 ), true ), 'a subscriber cannot connect skills' );
	ok( in_array( call_api( 'PUT', "/courses/$course_id/skill-plan/quizzes/$quiz", array( 'add' => array( $skill['one'] ) ) )->get_status(), array( 401, 403 ), true ), 'a subscriber cannot connect skills to a quiz' );
	wp_set_current_user( (int) $admins[0] );

	echo "Skill plan REST: $checks checks passed." . PHP_EOL;
} catch ( Throwable $error ) {
	echo $error->getMessage() . PHP_EOL;
	$failed = true;
} finally {
	if ( $course_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_chapter_relationship', array( 'course_id' => $course_id ) );
	}
	if ( ! empty( $quiz ) ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_skills', array( 'quiz_id' => $quiz ) );
	}
	foreach ( $posts as $post_id ) {
		if ( $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	if ( $syllabus ) {
		$root = (int) ( Items::get( $syllabus )['skill_root_id'] ?? 0 );
		Items::delete( $syllabus, 'delete', true );
		if ( $root ) {
			$terms[] = $root;
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
