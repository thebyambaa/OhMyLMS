<?php
/**
 * REST checks for the skill workspace (combined mappings, AI runs and review, shared content links,
 * domain placement, skill-owned quizzes, provenance review) and the syllabus spreadsheet command:
 * typed rejections, capability checks, stale writes refused without changes, and the happy paths.
 * Run on the Local site with OHMYLMS_SKILL_WORKSPACE_TEST=1. No AI provider is called: runs and
 * suggestions are tagged fixture rows, and any outbound HTTP request fails the test. Every fixture is
 * removed in `finally`.
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery

use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\Schema;
use OhMyLMS\Curriculum\SkillMappings;
use OhMyLMS\Curriculum\Syllabus;
use OhMyLMS\Curriculum\SyllabusCourse;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\Quiz\QuizSkills;
use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_SKILL_WORKSPACE_TEST' ) ) {
	exit( "Set OHMYLMS_SKILL_WORKSPACE_TEST=1 to run the skill workspace REST tests.\n" );
}
define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$sw_checks = 0;

/**
 * Dispatch a REST request below /ohmylms/v1.
 *
 * @param string $method HTTP method.
 * @param string $path   Route below the namespace.
 * @param array  $data   Body (JSON) or query (GET).
 * @return WP_REST_Response
 */
function sw_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1' . $path );
	if ( 'GET' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert a condition.
 *
 * @param mixed  $value   Truthy when the check passes.
 * @param string $message Failure description.
 * @throws RuntimeException On failure.
 */
function sw_ok( $value, $message ) {
	global $sw_checks;
	if ( ! $value ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$sw_checks;
}

/**
 * Assert a status and error code.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $label    Description.
 */
function sw_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	sw_ok( $response->get_status() === $status && ( $data['code'] ?? '' ) === $code, "$label: expected $status $code, got " . $response->get_status() . ' ' . wp_json_encode( $data ) );
}

$tag      = 'sw' . strtolower( wp_generate_password( 6, false, false ) );
$items    = array();
$terms    = array();
$posts    = array();
$users    = array();
$runs     = array();
$uuids    = array();
$external = 0;
$admins   = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$exit_code = 0;
add_filter(
	'pre_http_request',
	static function () use ( &$external ) {
		++$external;
		return new WP_Error( 'blocked', 'External HTTP is blocked in this test.' );
	},
	1000
);
try {
	global $wpdb;
	sw_ok( ! empty( $admins ), 'an administrator exists' );
	$admin = (int) $admins[0];
	foreach ( array( 'editor', 'subscriber' ) as $role ) {
		$user = wp_insert_user(
			array(
				'user_login' => $tag . $role,
				'user_pass'  => wp_generate_password( 24 ),
				'role'       => $role,
			)
		);
		sw_ok( ! is_wp_error( $user ), "$role fixture" );
		$users[ $role ] = $user;
	}
	wp_set_current_user( $admin );
	Schema::install();

	// Routes carry typed arguments and real permission callbacks.
	$routes = rest_get_server()->get_routes( 'ohmylms/v1' );
	foreach ( array( '/skill-workspace/skills/(?P<id>\d+)/mappings', '/skill-workspace/content', '/skill-workspace/content/(?P<kind>[a-z][a-z0-9_-]{0,39})/(?P<id>\d+)', '/skill-workspace/placement', '/skill-workspace/skills/(?P<id>\d+)/quizzes', '/skill-workspace/skills/(?P<id>\d+)/quizzes/(?P<quiz_id>\d+)', '/skill-workspace/provenance', '/skill-workspace/provenance/(?P<id>\d+)', '/skill-workspace/provenance/review', '/syllabus-comparison/ai/runs', '/syllabus-comparison/ai/suggestions/review', '/syllabus-comparison/mapping-groups' ) as $route ) {
		$key = '/ohmylms/v1' . $route;
		sw_ok( isset( $routes[ $key ] ), "route $route" );
		foreach ( $routes[ $key ] as $endpoint ) {
			sw_ok( is_callable( $endpoint['permission_callback'] ) && ! empty( $endpoint['args'] ), "permission and arguments on $route" );
		}
	}

	// Fixtures: a syllabus with a unit, a group of three objectives, and a shared skill.
	$subject  = $tag . ' Mathematics';
	$syllabus = Items::create(
		array(
			'name'        => $tag . ' Syllabus',
			'is_syllabus' => true,
		)
	);
	sw_ok( ! is_wp_error( $syllabus ), 'syllabus' );
	$syllabus = (int) $syllabus['id'];
	$items[]  = $syllabus;
	$unit     = Items::create(
		array(
			'name'      => $tag . ' Unit',
			'parent_id' => $syllabus,
			'code'      => 'U1',
		)
	);
	sw_ok( ! is_wp_error( $unit ), 'unit: ' . wp_json_encode( $unit ) );
	$unit    = (int) $unit['id'];
	$items[] = $unit;
	$group   = Syllabus::add_group(
		$syllabus,
		array(
			'name'    => 'Algebra',
			'item_id' => $unit,
		)
	);
	sw_ok( is_int( $group ), 'group' );
	$objectives = array();
	foreach ( array( 'Solve equations', 'Expand brackets', 'Factorise' ) as $index => $name ) {
		$term = Syllabus::create_skill(
			$syllabus,
			$group,
			array(
				'name' => $tag . ' ' . $name,
				'code' => 'A.' . ( $index + 1 ),
			)
		);
		sw_ok( ! is_wp_error( $term ), "objective $name" );
		$terms[]      = (int) $term['term_id'];
		$objectives[] = (int) $term['term_id'];
	}
	$shared = Comparison::save_shared(
		0,
		array(
			'name'    => $tag . ' Linear equations',
			'subject' => $subject,
			'topic'   => 'Algebra',
		)
	);
	sw_ok( ! is_wp_error( $shared ), 'shared skill' );
	$shared  = (int) $shared['id'];
	$terms[] = $shared;
	$mapped  = SkillMappings::save( $objectives[0], $shared, 'equivalent', 'Same', '' );
	sw_ok( ! is_wp_error( $mapped ), 'mapping' );
	$wpdb->update(
		SkillMappings::table(),
		array(
			'source'     => 'ai',
			'confidence' => 0.91,
		),
		array( 'id' => $mapped['id'] )
	);

	// Capability: editors, subscribers and anonymous visitors are refused everywhere.
	$denied = array(
		array( 'GET', '/skill-workspace/skills/' . $shared . '/mappings' ),
		array( 'GET', '/skill-workspace/content', array( 'kind' => 'lesson' ) ),
		array( 'GET', '/skill-workspace/placement' ),
		array( 'GET', '/skill-workspace/skills/' . $shared . '/quizzes' ),
		array( 'GET', '/skill-workspace/provenance' ),
		array(
			'POST',
			'/skill-workspace/provenance/review',
			array(
				'items'  => array(
					array(
						'id'              => $shared,
						'expected_review' => 'approved',
					),
				),
				'review' => 'rejected',
			),
		),
		array( 'GET', '/syllabus-comparison/ai/runs' ),
	);
	foreach ( array( 'editor', 'subscriber' ) as $role ) {
		wp_set_current_user( $users[ $role ] );
		foreach ( $denied as $call ) {
			sw_ok( 403 === sw_api( $call[0], $call[1], $call[2] ?? array() )->get_status(), "$role refused on {$call[0]} {$call[1]}" );
		}
	}
	wp_set_current_user( 0 );
	sw_ok( 401 === sw_api( 'GET', '/skill-workspace/provenance' )->get_status(), 'anonymous refused' );
	wp_set_current_user( $admin );
	sw_ok( 'approved' === Provenance::read( $shared )['review'], 'denied review changed nothing' );

	// Combined mappings: a combined group over two objectives, then one view of everything.
	$revisions = array();
	foreach ( array( $objectives[1], $objectives[2] ) as $objective ) {
		$revisions[] = array(
			'specific_id'       => $objective,
			'expected_revision' => '',
		);
	}
	sw_error(
		sw_api(
			'POST',
			'/syllabus-comparison/mapping-groups',
			array(
				'shared_id' => $shared,
				'groups'    => array(
					array(
						'source_id' => $syllabus,
						'pairs'     => array( $revisions[0] ),
					),
				),
			)
		),
		400,
		'rest_invalid_param',
		'a combined group needs two objectives'
	);
	$combined = sw_api(
		'POST',
		'/syllabus-comparison/mapping-groups',
		array(
			'shared_id' => $shared,
			'note'      => 'Together',
			'groups'    => array(
				array(
					'source_id' => $syllabus,
					'pairs'     => $revisions,
				),
			),
		)
	);
	sw_ok( 200 === $combined->get_status() && 2 === count( $combined->get_data() ), 'combined group saved: ' . wp_json_encode( $combined->get_data() ) );
	$uuids[] = $combined->get_data()[0]['group_uuid'];
	sw_error( sw_api( 'GET', '/skill-workspace/skills/0/mappings' ), 400, 'rest_invalid_param', 'skill id minimum' );
	sw_error( sw_api( 'GET', '/skill-workspace/skills/999999999/mappings' ), 404, 'ohmylms_skill_missing', 'unknown skill' );
	$view = sw_api( 'GET', '/skill-workspace/skills/' . $shared . '/mappings' )->get_data();
	sw_ok( 3 === count( $view['mappings'] ) && 1 === count( $view['groups'] ) && 2 === count( $view['groups'][0]['members'] ), 'combined view lists every mapping and the group: ' . wp_json_encode( $view ) );
	$ai_row = current( array_filter( $view['mappings'], static fn( $row ) => $objectives[0] === $row['specific']['id'] ) );
	sw_ok( 'ai' === $ai_row['source'] && abs( 0.91 - $ai_row['confidence'] ) < 0.001 && $syllabus === $ai_row['syllabuses'][0]['id'], 'mapping provenance and syllabus' );
	sw_ok( true === $view['skill']['shared'] && $subject === $view['skill']['subject'], 'skill summary' );
	$objective_view = sw_api( 'GET', '/skill-workspace/skills/' . $objectives[0] . '/mappings' )->get_data();
	sw_ok( 1 === count( $objective_view['placements'] ) && $group === $objective_view['placements'][0]['group_id'], 'objective placement' );

	// Shared content links: a lesson and a question linked to several skills.
	$lesson = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_LESSON_CPT,
			'post_title'  => $tag . ' Lesson',
			'post_status' => 'draft',
		)
	);
	$posts[]  = $lesson;
	$question = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUESTION_CPT,
			'post_title'  => $tag . ' Question',
			'post_status' => 'draft',
		)
	);
	$posts[] = $question;
	SkillMap::save(
		$question,
		array(
			'p1' => array(
				'primary'    => $objectives[0],
				'supporting' => array(),
			),
		)
	);
	sw_error( sw_api( 'GET', '/skill-workspace/content', array( 'kind' => 'video' ) ), 400, 'rest_invalid_param', 'unknown content kind' );
	$found = sw_api(
		'GET',
		'/skill-workspace/content',
		array(
			'kind'   => 'lesson',
			'search' => $tag,
		)
	)->get_data();
	sw_ok( 1 === $found['total'] && $lesson === $found['items'][0]['id'], 'content search' );
	$read = sw_api( 'GET', '/skill-workspace/content/lesson/' . $lesson )->get_data();
	sw_ok( array() === $read['skills'] && preg_match( '/^[a-f0-9]{64}$/', $read['revision'] ), 'an unlinked lesson' );
	sw_error(
		sw_api(
			'PUT',
			'/skill-workspace/content/lesson/' . $lesson,
			array(
				'add'               => array( $shared ),
				'expected_revision' => 'nope',
			)
		),
		400,
		'rest_invalid_param',
		'revision format'
	);
	sw_error(
		sw_api(
			'PUT',
			'/skill-workspace/content/lesson/' . $lesson,
			array(
				'add'               => array( $shared ),
				'remove'            => array( $shared ),
				'expected_revision' => $read['revision'],
			)
		),
		400,
		'ohmylms_content_links_invalid',
		'add and remove the same skill'
	);
	$linked = sw_api(
		'PUT',
		'/skill-workspace/content/lesson/' . $lesson,
		array(
			'add'               => array( $shared, $objectives[1] ),
			'expected_revision' => $read['revision'],
		)
	);
	sw_ok( 200 === $linked->get_status() && 2 === count( $linked->get_data()['skills'] ), 'lesson linked to two skills' );
	sw_error(
		sw_api(
			'PUT',
			'/skill-workspace/content/lesson/' . $lesson,
			array(
				'remove'            => array( $shared ),
				'expected_revision' => $read['revision'],
			)
		),
		409,
		'ohmylms_content_links_stale',
		'stale content links'
	);
	sw_ok( has_term( $shared, Taxonomy::NAME, $lesson ), 'stale removal changed nothing' );
	$unlinked = sw_api(
		'PUT',
		'/skill-workspace/content/lesson/' . $lesson,
		array(
			'remove'            => array( $shared ),
			'expected_revision' => $linked->get_data()['revision'],
		)
	)->get_data();
	sw_ok( array( $objectives[1] ) === array_column( $unlinked['skills'], 'id' ), 'one link removed, the other kept' );
	$qread = sw_api( 'GET', '/skill-workspace/content/question/' . $question )->get_data();
	sw_ok( 'primary' === $qread['skills'][0]['role'], 'question primary skill is shown' );
	sw_error(
		sw_api(
			'PUT',
			'/skill-workspace/content/question/' . $question,
			array(
				'remove'            => array( $objectives[0] ),
				'expected_revision' => $qread['revision'],
			)
		),
		409,
		'ohmylms_content_links_primary',
		'a primary skill is not removed here'
	);
	$qlinked = sw_api(
		'PUT',
		'/skill-workspace/content/question/' . $question,
		array(
			'add'               => array( $shared ),
			'expected_revision' => $qread['revision'],
		)
	)->get_data();
	sw_ok( array( $shared ) === SkillMap::current( $question )['p1']['supporting'] && $objectives[0] === SkillMap::current( $question )['p1']['primary'], 'question gains a supporting skill: ' . wp_json_encode( $qlinked ) );
	wp_set_current_user( $users['editor'] );
	sw_ok( 403 === sw_api( 'GET', '/skill-workspace/content/lesson/' . $lesson )->get_status(), 'editor refused on content links' );
	wp_set_current_user( $admin );

	// Domain placement: listing unplaced skills, an all-or-nothing batch and stale revisions.
	$second = Comparison::save_shared(
		0,
		array(
			'name'    => $tag . ' Quadratics',
			'subject' => $subject,
		)
	);
	$terms[] = (int) $second['id'];
	$list    = sw_api(
		'GET',
		'/skill-workspace/placement',
		array(
			'subject'  => $subject,
			'unplaced' => true,
		)
	)->get_data();
	sw_ok( 2 === $list['total'] && isset( $list['domains']['algebra'] ) && count( $list['levels'] ) > 0, 'unplaced shared skills: ' . wp_json_encode( $list['total'] ) );
	$by_id = array_column( $list['items'], null, 'id' );
	sw_error(
		sw_api(
			'PUT',
			'/skill-workspace/placement',
			array(
				'items' => array(
					array(
						'id'                => $shared,
						'domain'            => 'algebra',
						'grade_from'        => 7,
						'grade_to'          => 8,
						'expected_revision' => $by_id[ $shared ]['revision'],
					),
					array(
						'id'                => (int) $second['id'],
						'domain'            => 'algebra',
						'grade_from'        => 9,
						'grade_to'          => 9,
						'expected_revision' => str_repeat( 'a', 64 ),
					),
				),
			)
		),
		409,
		'ohmylms_shared_stale',
		'one stale skill refuses the batch'
	);
	sw_ok( '' === Comparison::shared_skill( $shared )['domain'], 'refused batch stored nothing' );
	sw_error(
		sw_api(
			'PUT',
			'/skill-workspace/placement',
			array(
				'items' => array(
					array(
						'id'                => $shared,
						'domain'            => 'astrology',
						'grade_from'        => 7,
						'grade_to'          => 8,
						'expected_revision' => $by_id[ $shared ]['revision'],
					),
				),
			)
		),
		400,
		'ohmylms_shared_invalid',
		'unknown domain'
	);
	$placed = sw_api(
		'PUT',
		'/skill-workspace/placement',
		array(
			'items' => array(
				array(
					'id'                => $shared,
					'domain'            => 'algebra',
					'grade_from'        => 7,
					'grade_to'          => 8,
					'expected_revision' => $by_id[ $shared ]['revision'],
				),
				array(
					'id'                => (int) $second['id'],
					'domain'            => 'algebra',
					'grade_from'        => 9,
					'grade_to'          => 9,
					'expected_revision' => $by_id[ (int) $second['id'] ]['revision'],
				),
			),
		)
	);
	sw_ok( 200 === $placed->get_status() && 'algebra' === Comparison::shared_skill( $shared )['domain'] && 9 === Comparison::shared_skill( (int) $second['id'] )['grade_from'], 'batch placed both skills' );
	sw_ok(
		0 === sw_api(
			'GET',
			'/skill-workspace/placement',
			array(
				'subject'  => $subject,
				'unplaced' => true,
			)
		)->get_data()['total'],
		'nothing unplaced remains'
	);

	// Skill-owned quizzes.
	$quiz = wp_insert_post(
		array(
			'post_type'   => OHMYLMS_QUIZ_CPT,
			'post_title'  => $tag . ' Quiz',
			'post_status' => 'draft',
		)
	);
	$posts[] = $quiz;
	QuizSkills::set( $quiz, array( $objectives[2] ) );
	$owned = sw_api(
		'PUT',
		'/skill-workspace/skills/' . $shared . '/quizzes/' . $quiz,
		array( 'linked' => true )
	);
	sw_ok( 200 === $owned->get_status() && true === $owned->get_data()['quizzes'][0]['connected'], 'quiz connected to the skill' );
	sw_ok( array( $objectives[2], $shared ) === QuizSkills::get( $quiz ), 'the quiz keeps its other skill first' );
	update_post_meta(
		$quiz,
		\OhMyLMS\Assessment\Pools::META,
		array(
			array(
				'id'      => 'pool-1',
				'term_id' => $shared,
				'count'   => 3,
			),
		)
	);
	$listing = sw_api( 'GET', '/skill-workspace/skills/' . $shared . '/quizzes' )->get_data();
	sw_ok( 3 === $listing['quizzes'][0]['drawn'] && 0 === $listing['available'], 'pooled draws and available questions' );
	sw_api( 'PUT', '/skill-workspace/skills/' . $shared . '/quizzes/' . $quiz, array( 'linked' => false ) );
	sw_ok( array( $objectives[2] ) === QuizSkills::get( $quiz ), 'quiz disconnected, other skill kept' );
	sw_ok( 1 === count( sw_api( 'GET', '/skill-workspace/skills/' . $shared . '/quizzes' )->get_data()['quizzes'] ), 'a pooled quiz still belongs to the skill' );
	sw_error( sw_api( 'POST', '/skill-workspace/skills/' . $shared . '/quizzes', array( 'per_skill' => 11 ) ), 400, 'rest_invalid_param', 'per_skill maximum' );
	sw_error( sw_api( 'POST', '/skill-workspace/skills/' . $shared . '/quizzes', array( 'per_skill' => 2 ) ), 409, 'ohmylms_skill_quiz_short', 'not enough approved questions' );
	sw_error( sw_api( 'PUT', '/skill-workspace/skills/' . $shared . '/quizzes/' . $lesson, array( 'linked' => true ) ), 404, 'ohmylms_quiz_missing', 'a lesson is not a quiz' );

	// Provenance review: a pending AI skill, a stale decision and the audit trail.
	$pending = Provenance::with(
		array(
			'source'     => 'ai',
			'ref'        => 'run:0/suggestion:0',
			'review'     => 'pending',
			'model'      => 'fixture',
			'confidence' => 0.7,
		),
		static function () use ( $tag ) {
			return wp_insert_term( $tag . ' Proposed skill', Taxonomy::NAME, array( 'slug' => $tag . '-proposed' ) );
		}
	);
	sw_ok( ! is_wp_error( $pending ), 'pending skill' );
	$pending = (int) $pending['term_id'];
	$terms[] = $pending;
	$queue   = sw_api(
		'GET',
		'/skill-workspace/provenance',
		array(
			'review' => 'pending',
			'source' => 'ai',
			'search' => $tag,
		)
	)->get_data();
	sw_ok( 1 === $queue['total'] && $pending === $queue['items'][0]['id'] && 'fixture' === $queue['items'][0]['provenance']['model'] && $queue['counts']['pending'] >= 1, 'pending queue: ' . wp_json_encode( $queue ) );
	sw_error( sw_api( 'GET', '/skill-workspace/provenance', array( 'review' => 'maybe' ) ), 400, 'rest_invalid_param', 'review state enum' );
	$stale = sw_api(
		'POST',
		'/skill-workspace/provenance/review',
		array(
			'items'  => array(
				array(
					'id'              => $pending,
					'expected_review' => 'approved',
				),
			),
			'review' => 'rejected',
		)
	)->get_data();
	sw_ok( 'ohmylms_skill_review_stale' === $stale['results'][ $pending ]['code'] && 'pending' === Provenance::read( $pending )['review'], 'a stale decision is reported and changes nothing' );
	$approved = sw_api(
		'POST',
		'/skill-workspace/provenance/review',
		array(
			'items'  => array(
				array(
					'id'              => $pending,
					'expected_review' => 'pending',
				),
			),
			'review' => 'approved',
			'note'   => 'Checked against the syllabus',
		)
	)->get_data();
	sw_ok( 'approved' === $approved['results'][ $pending ]['review'] && 'approved' === Provenance::read( $pending )['review'], 'skill approved' );
	$detail = sw_api( 'GET', '/skill-workspace/provenance/' . $pending )->get_data();
	sw_ok( 'approved' === $detail['audit'][0]['action'] && 'pending' === $detail['audit'][0]['previous'] && 'Checked against the syllabus' === $detail['audit'][0]['note'] && $admin === $detail['audit'][0]['by']['id'], 'audit trail records the decision' );
	$shared_detail = sw_api( 'GET', '/skill-workspace/provenance/' . $shared )->get_data();
	sw_ok( 3 === count( $shared_detail['mappings'] ) && in_array( 'ai', array_column( $shared_detail['mappings'], 'source' ), true ), 'mapping provenance in the detail' );

	// AI runs and review over fixture rows; no provider is called.
	$now = current_time( 'mysql', true );
	$wpdb->insert(
		Schema::table( 'ai_analysis_runs' ),
		array(
			'uuid'        => wp_generate_uuid4(),
			'kind'        => 'syllabus',
			'syllabus_id' => $syllabus,
			'subject'     => $subject,
			'status'      => 'done',
			'total'       => 3,
			'processed'   => 3,
			'suggested'   => 2,
			'model'       => 'fixture',
			'queue'       => wp_json_encode( array( 'ids' => array() ) ),
			'created_by'  => $admin,
			'created_at'  => $now,
			'updated_at'  => $now,
		)
	);
	$run    = (int) $wpdb->insert_id;
	$runs[] = $run;
	$suggestion = static function ( array $fields ) use ( $wpdb, $run, $now ) {
		$wpdb->insert(
			Schema::table( 'ai_suggestions' ),
			$fields + array(
				'run_id'     => $run,
				'status'     => 'pending',
				'reason'     => 'Fixture',
				'created_at' => $now,
			)
		);
		return (int) $wpdb->insert_id;
	};
	$mapping_suggestion   = $suggestion(
		array(
			'kind'             => 'mapping',
			'specific_term_id' => $objectives[0],
			'shared_term_id'   => (int) $second['id'],
			'relation'         => 'equivalent',
			'confidence'       => 0.8,
		)
	);
	$placement_suggestion = $suggestion(
		array(
			'kind'           => 'placement',
			'shared_term_id' => $shared,
			'payload'        => wp_json_encode(
				array(
					'domain'     => 'geometry',
					'grade_from' => 5,
					'grade_to'   => 5,
				)
			),
			'confidence'     => 0.6,
		)
	);
	$listed = sw_api( 'GET', '/syllabus-comparison/ai/runs', array( 'syllabus_id' => $syllabus ) )->get_data();
	sw_ok( $run === $listed[0]['id'] && 2 === $listed[0]['pending'], 'runs of the syllabus' );
	sw_error( sw_api( 'GET', '/syllabus-comparison/ai/runs', array( 'syllabus_id' => -1 ) ), 400, 'rest_invalid_param', 'syllabus id minimum' );
	sw_error( sw_api( 'POST', '/syllabus-comparison/ai/runs', array( 'kind' => 'course' ) ), 400, 'rest_invalid_param', 'course runs start from the course editor' );
	sw_error( sw_api( 'POST', '/syllabus-comparison/ai/runs/' . $run . '/accept-confident', array( 'min_confidence' => 0.2 ) ), 400, 'rest_invalid_param', 'confidence minimum' );
	sw_error(
		sw_api(
			'POST',
			'/syllabus-comparison/ai/suggestions/review',
			array(
				'ids'       => array( $mapping_suggestion ),
				'action'    => 'accept',
				'overrides' => array( (string) $mapping_suggestion => array( 'colour' => 'red' ) ),
			)
		),
		400,
		'rest_invalid_param',
		'unknown override field'
	);
	sw_error( sw_api( 'POST', '/syllabus-comparison/ai/suggestions/review', array( 'ids' => array( $mapping_suggestion ), 'action' => 'maybe' ) ), 400, 'rest_invalid_param', 'review action enum' );
	$queue = sw_api(
		'GET',
		'/syllabus-comparison/ai/suggestions',
		array(
			'run_id' => $run,
			'status' => 'pending',
		)
	)->get_data();
	sw_ok( 2 === $queue['total'], 'two pending suggestions' );
	$stepped = sw_api( 'POST', '/syllabus-comparison/ai/runs/' . $run . '/step' )->get_data();
	sw_ok( 'done' === $stepped['status'] && 0 === $external, 'a finished run does not call the provider' );
	$rejected = sw_api(
		'POST',
		'/syllabus-comparison/ai/suggestions/review',
		array(
			'ids'    => array( $placement_suggestion ),
			'action' => 'reject',
		)
	)->get_data();
	sw_ok( 'rejected' === $rejected['results'][ $placement_suggestion ]['status'] && 'algebra' === Comparison::shared_skill( $shared )['domain'], 'rejecting changes nothing' );
	$accepted = sw_api(
		'POST',
		'/syllabus-comparison/ai/suggestions/review',
		array(
			'ids'       => array( $mapping_suggestion ),
			'action'    => 'accept',
			'overrides' => array( (string) $mapping_suggestion => array( 'relation' => 'related' ) ),
		)
	)->get_data();
	sw_ok( 'accepted' === $accepted['results'][ $mapping_suggestion ]['status'], 'mapping accepted: ' . wp_json_encode( $accepted ) );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT relation, source FROM %i WHERE specific_term_id=%d AND shared_term_id=%d', SkillMappings::table(), $objectives[0], (int) $second['id'] ), ARRAY_A );
	sw_ok( 'related' === $row['relation'] && 'ai' === $row['source'], 'accepted mapping records the override and its AI source' );
	$again = sw_api(
		'POST',
		'/syllabus-comparison/ai/suggestions/review',
		array(
			'ids'    => array( $mapping_suggestion ),
			'action' => 'reject',
		)
	)->get_data();
	sw_ok( 'accepted' === $again['results'][ $mapping_suggestion ]['status'], 'a decided suggestion is not decided again' );
	$confident = sw_api( 'POST', '/syllabus-comparison/ai/runs/' . $run . '/accept-confident', array( 'min_confidence' => 0.5 ) )->get_data();
	sw_ok( 0 === $confident['accepted'] && 0 === $confident['failed'], 'nothing left to accept' );
	sw_ok( 0 === $external, 'no outbound HTTP request was made' );

	// Syllabus spreadsheet: one atomic batch, stale cells and code rules.
	$sheet = static function ( array $changes ) use ( $syllabus ) {
		return sw_api(
			'POST',
			'/authoring/syllabuses/' . $syllabus . '/commands',
			array(
				'operation' => 'sheet_edit',
				'changes'   => $changes,
			)
		);
	};
	sw_error( $sheet( array( array( 'kind' => 'topic', 'id' => $group, 'field' => 'name', 'previous' => 'Algebra', 'value' => 'X' ) ) ), 400, 'ohmylms_syllabus_invalid', 'unknown row kind' );
	sw_error( $sheet( array( array( 'kind' => 'item', 'id' => $syllabus, 'field' => 'name', 'previous' => $tag . ' Syllabus', 'value' => 'X' ) ) ), 400, 'ohmylms_syllabus_invalid', 'the syllabus itself is renamed elsewhere' );
	sw_error( $sheet( array( array( 'kind' => 'group', 'id' => $group, 'field' => 'name', 'previous' => 'Algebra', 'value' => '   ' ) ) ), 400, 'ohmylms_syllabus_invalid', 'names cannot be empty' );
	$stale = $sheet(
		array(
			array(
				'kind'     => 'group',
				'id'       => $group,
				'field'    => 'name',
				'previous' => 'Algebra',
				'value'    => 'Algebra 1',
			),
			array(
				'kind'     => 'skill',
				'id'       => $objectives[1],
				'field'    => 'name',
				'previous' => 'Something else',
				'value'    => 'Expand',
			),
		)
	);
	sw_error( $stale, 409, 'ohmylms_syllabus_sheet_stale', 'a stale cell refuses the batch' );
	sw_ok( $objectives[1] === $stale->get_data()['data']['conflicts'][0]['id'] && 'Algebra' === Syllabus::group( $group )['name'], 'conflicts are listed and nothing was written' );
	sw_error(
		$sheet(
			array(
				array(
					'kind'     => 'skill',
					'id'       => $objectives[1],
					'field'    => 'code',
					'previous' => 'A.2',
					'value'    => 'A.1',
				),
			)
		),
		409,
		'ohmylms_syllabus_code_taken',
		'skill codes stay unique'
	);
	$saved = $sheet(
		array(
			array(
				'kind'     => 'group',
				'id'       => $group,
				'field'    => 'name',
				'previous' => 'Algebra',
				'value'    => 'Algebra 1',
			),
			array(
				'kind'     => 'item',
				'id'       => $unit,
				'field'    => 'name',
				'previous' => $tag . ' Unit',
				'value'    => $tag . ' Unit renamed',
			),
			array(
				'kind'     => 'skill',
				'id'       => $objectives[0],
				'field'    => 'code',
				'previous' => 'A.1',
				'value'    => 'A.2',
			),
			array(
				'kind'     => 'skill',
				'id'       => $objectives[1],
				'field'    => 'code',
				'previous' => 'A.2',
				'value'    => 'A.1',
			),
		)
	);
	sw_ok( 200 === $saved->get_status(), 'batch saved: ' . wp_json_encode( $saved->get_data() ) );
	sw_ok( 'Algebra 1' === Syllabus::group( $group )['name'] && $tag . ' Unit renamed' === Items::get( $unit )['name'], 'group and unit renamed' );
	sw_ok( 'A.2' === get_term_meta( $objectives[0], Syllabus::CODE_META, true ) && 'A.1' === get_term_meta( $objectives[1], Syllabus::CODE_META, true ), 'two skills swapped codes' );
	$names = array();
	foreach ( $saved->get_data()['contents'] as $content ) {
		$names[] = $content['name'];
	}
	sw_ok( in_array( $tag . ' Unit renamed', $names, true ), 'the response is the refreshed outline' );
	wp_set_current_user( $users['editor'] );
	sw_ok( 403 === $sheet( array( array( 'kind' => 'group', 'id' => $group, 'field' => 'name', 'previous' => 'Algebra 1', 'value' => 'X' ) ) )->get_status(), 'editor refused on the spreadsheet' );
	wp_set_current_user( $admin );

	echo "OK skill-workspace-rest-integration: {$sw_checks} checks\n";
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	$exit_code = 1;
} finally {
	global $wpdb;
	if ( isset( $admin ) ) {
		wp_set_current_user( $admin );
	}
	foreach ( $runs as $run ) {
		$wpdb->delete( Schema::table( 'ai_suggestions' ), array( 'run_id' => $run ) );
		$wpdb->delete( Schema::table( 'ai_analysis_runs' ), array( 'id' => $run ) );
	}
	foreach ( $uuids as $uuid ) {
		$wpdb->delete( SkillMappings::table(), array( 'group_uuid' => $uuid ) );
		$wpdb->delete( Schema::table( 'skill_mapping_groups' ), array( 'uuid' => $uuid ) );
	}
	foreach ( $terms as $term ) {
		SkillMappings::remove_skill( $term );
	}
	foreach ( $posts as $post ) {
		wp_delete_post( $post, true );
	}
	foreach ( array_reverse( $items ) as $item ) {
		$row = Items::get( $item );
		if ( $row ) {
			$course = SyllabusCourse::course_id( $item );
			if ( ! empty( $row['skill_root_id'] ) ) {
				$terms[] = (int) $row['skill_root_id'];
			}
			Items::delete( $item, 'delete', true );
			if ( $course ) {
				wp_delete_post( $course, true );
			}
		}
	}
	foreach ( $terms as $term ) {
		wp_delete_term( $term, Taxonomy::NAME );
	}
	foreach ( $users as $user ) {
		wp_delete_user( $user );
	}
}
exit( $exit_code );
