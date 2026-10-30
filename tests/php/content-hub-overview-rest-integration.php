<?php
/**
 * Content Hub overview REST integration test (`GET /content-hub/overview`).
 *
 * Runs against a WordPress install (the Local site by default). Creates tagged users, posts of every
 * content type, a skill, a curriculum item, a bundle and an attempt, checks the counts as changes against
 * a baseline (other content on the site is never assumed), scoping, recent items and validation, and
 * removes everything it created in `finally`.
 *
 * Usage (set OHMYLMS_CONTENT_HUB_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/content-hub-overview-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, Generic.Formatting.MultipleStatementAlignment

use OhMyLMS\Curriculum\Schema as CurriculumSchema;
use OhMyLMS\Skills\Provenance;
use OhMyLMS\Skills\Taxonomy;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_CONTENT_HUB_TEST' ) ) {
	exit( "Set OHMYLMS_CONTENT_HUB_TEST=1 to run the Content Hub overview REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;

$checks    = 0;
$exit_code = 0;
$user_ids  = array();
$post_ids  = array();
$term_ids  = array();
$item_ids  = array();
$bundle_id = 0;
$tag       = 'chtest' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Read the overview.
 *
 * @param array $query Query parameters.
 * @return WP_REST_Response
 */
function overview( array $query = array() ) {
	$request = new WP_REST_Request( 'GET', '/ohmylms/v1/content-hub/overview' );
	$request->set_query_params( $query );
	return rest_do_request( $request );
}

/**
 * Areas of an overview by id.
 *
 * @param WP_REST_Response $response Response.
 * @return array<string,array>
 */
function areas_of( $response ) {
	return array_column( $response->get_data()['areas'], null, 'id' );
}

/**
 * Create a tagged user.
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role ) {
	global $tag, $user_ids;
	$id         = wp_insert_user(
		array(
			'user_login' => $tag . '_' . $suffix,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '.' . $suffix . '@example.invalid',
			'role'       => $role,
		)
	);
	$user_ids[] = $id;
	return $id;
}

/**
 * Create a tagged post.
 *
 * @param string $type   Post type.
 * @param string $status Status.
 * @param int    $author Author.
 * @return int
 */
function make_post( $type, $status, $author ) {
	global $tag, $post_ids;
	$id         = wp_insert_post(
		array(
			'post_type'   => $type,
			'post_title'  => "$tag $type $status",
			'post_status' => $status,
			'post_author' => $author,
		)
	);
	$post_ids[] = $id;
	return $id;
}

try {
	$admins     = get_users(
		array(
			'role'    => 'administrator',
			'number'  => 1,
			'orderby' => 'ID',
			'fields'  => 'ID',
		)
	);
	$admin      = (int) $admins[0];
	$author     = make_user( 'author', 'author' );
	$subscriber = make_user( 'sub', 'subscriber' );
	$student    = make_user( 'student', 'subscriber' );

	// Authorisation and validation.
	wp_set_current_user( 0 );
	$guest = overview();
	ok( 401 === $guest->get_status() && 'ohmylms_content_hub_cannot_view' === $guest->get_data()['code'], 'guest is 401' );
	wp_set_current_user( $subscriber );
	ok( 403 === overview()->get_status(), 'subscriber is 403' );
	wp_set_current_user( $admin );
	ok( 400 === overview( array( 'recent' => 21 ) )->get_status(), 'recent maximum' );
	ok( 400 === overview( array( 'recent' => -1 ) )->get_status(), 'recent minimum' );

	// Administrator baseline.
	$before = overview();
	ok( 200 === $before->get_status() && 'private, no-store' === $before->get_headers()['Cache-Control'], 'admin overview is 200 and not cached' );
	$expected = array( 'courses', 'lessons', 'quizzes', 'exams', 'question-bank', 'assignments', 'skills' );
	if ( CurriculumSchema::ready() ) {
		$expected[] = 'curriculum';
		$expected[] = 'bundles';
	}
	$area_ids = wp_list_pluck( $before->get_data()['areas'], 'id' );
	ok( $area_ids === $expected, 'admin sees every area: ' . wp_json_encode( $area_ids ) );
	$base = areas_of( $before );
	foreach ( $base as $id => $area ) {
		ok( is_int( $area['total'] ) && $area['total'] >= 0 && is_array( $area['counts'] ), "area $id shape" );
	}

	// Fixtures.
	$course_pub   = make_post( OHMYLMS_COURSE_CPT, 'publish', $admin );
	$course_draft = make_post( OHMYLMS_COURSE_CPT, 'draft', $admin );
	make_post( OHMYLMS_COURSE_CPT, 'trash', $admin );
	make_post( OHMYLMS_LESSON_CPT, 'pending', $admin );
	$quiz       = make_post( OHMYLMS_QUIZ_CPT, 'publish', $admin );
	$exam       = make_post( \OhMyLMS\Exams\Library::TYPE, 'draft', $admin );
	$question   = make_post( OHMYLMS_QUESTION_CPT, 'draft', $admin );
	$assignment = make_post( OHMYLMS_ASSIGNMENT_CPT, 'publish', $admin );
	$own_course = make_post( OHMYLMS_COURSE_CPT, 'draft', $author );
	$own_quiz   = make_post( OHMYLMS_QUIZ_CPT, 'draft', $author );
	$wpdb->insert(
		$wpdb->prefix . 'ohmylms_quiz_attempts',
		array(
			'course_id'  => 0,
			'quiz_id'    => $own_quiz,
			'student_id' => $student,
			'total'      => 1,
			'status'     => 'in-review',
			'start_date' => '2026-10-01 09:00:00',
			'end_date'   => '2026-10-01 09:30:00',
		)
	);
	$skill = wp_insert_term( "$tag skill", Taxonomy::NAME );
	ok( ! is_wp_error( $skill ), 'skill created' );
	$term_ids[] = (int) $skill['term_id'];
	update_term_meta( (int) $skill['term_id'], Provenance::STATUS_META, 'archived' );
	if ( CurriculumSchema::ready() ) {
		$now = current_time( 'mysql', true );
		$wpdb->insert(
			CurriculumSchema::table( 'curriculum_items' ),
			array(
				'uuid'        => wp_generate_uuid4(),
				'item_type'   => 'syllabus',
				'name'        => "$tag syllabus",
				'is_syllabus' => 1,
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);
		$item_ids[] = (int) $wpdb->insert_id;
		$wpdb->insert(
			CurriculumSchema::table( 'bundles' ),
			array(
				'uuid'       => wp_generate_uuid4(),
				'title'      => "$tag bundle",
				'status'     => 'published',
				'created_at' => $now,
				'updated_at' => $now,
			)
		);
		$bundle_id = (int) $wpdb->insert_id;
	}

	// Administrator counts change by exactly the fixtures.
	$after = areas_of( overview() );
	$delta = static function ( $area, $key = null ) use ( $after, $base ) {
		return null === $key ? $after[ $area ]['total'] - $base[ $area ]['total'] : $after[ $area ]['counts'][ $key ] - $base[ $area ]['counts'][ $key ];
	};
	ok( 3 === $delta( 'courses' ) && 1 === $delta( 'courses', 'published' ) && 2 === $delta( 'courses', 'drafts' ), 'course counts, trash excluded' );
	ok( 1 === $delta( 'lessons' ) && 1 === $delta( 'lessons', 'drafts' ), 'pending lessons count as drafts' );
	ok( 2 === $delta( 'quizzes' ) && 1 === $delta( 'quizzes', 'to_grade' ), 'quiz counts and attempts to grade' );
	ok( 1 === $delta( 'exams' ) && 1 === $delta( 'question-bank' ) && 1 === $delta( 'assignments' ) && 1 === $delta( 'assignments', 'published' ), 'exam, question and assignment counts' );
	ok( 1 === $delta( 'skills' ) && 1 === $delta( 'skills', 'archived' ), 'skill counts' );
	if ( CurriculumSchema::ready() ) {
		ok( 1 === $delta( 'curriculum' ) && 1 === $delta( 'curriculum', 'syllabuses' ), 'curriculum counts' );
		ok( 1 === $delta( 'bundles' ) && 1 === $delta( 'bundles', 'published' ) && 0 === $delta( 'bundles', 'drafts' ), 'bundle counts' );
	}

	// Recent items.
	$recent = overview( array( 'recent' => 20 ) )->get_data()['recent'];
	ok( count( $recent ) <= 20, 'recent limit' );
	$mine = array_column( array_filter( $recent, static fn( $item ) => 0 === strpos( $item['title'], $tag ) ), null, 'id' );
	ok( isset( $mine[ $course_pub ], $mine[ $course_draft ], $mine[ $exam ], $mine[ $question ], $mine[ $assignment ] ), 'fixtures, drafts included, are recent' );
	ok( array( 'area', 'id', 'title', 'status', 'updated_at' ) === array_keys( $mine[ $course_pub ] ) && 'courses' === $mine[ $course_pub ]['area'] && 'publish' === $mine[ $course_pub ]['status'], 'recent item shape' );
	ok( 'exams' === $mine[ $exam ]['area'] && 'draft' === $mine[ $exam ]['status'] && 'question-bank' === $mine[ $question ]['area'], 'recent areas' );
	ok( 1 === preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', (string) $mine[ $course_pub ]['updated_at'] ), 'recent updated_at is ISO 8601 UTC' );
	ok( 'draft' === $mine[ $course_draft ]['status'] && 1 === preg_match( '/Z$/', (string) $mine[ $course_draft ]['updated_at'] ), 'a new draft has a status and an update time' );
	ok( array() === overview( array( 'recent' => 0 ) )->get_data()['recent'], 'recent can be turned off' );

	// An author sees their own content only, and no administrator areas.
	wp_set_current_user( $author );
	$as_author = overview( array( 'recent' => 20 ) );
	ok( 200 === $as_author->get_status(), 'author overview is 200' );
	$own = areas_of( $as_author );
	ok( array( 'courses', 'quizzes', 'exams', 'question-bank', 'assignments', 'skills' ) === array_keys( $own ), 'author areas: no lessons, curriculum or bundles' );
	ok( 1 === $own['courses']['total'] && 1 === $own['courses']['counts']['drafts'] && 1 === $own['quizzes']['total'] && 0 === $own['exams']['total'], 'author counts only own posts' );
	ok( 1 === $own['quizzes']['counts']['to_grade'], 'author sees attempts to grade in own quizzes' );
	$ids = array_map( 'intval', wp_list_pluck( $as_author->get_data()['recent'], 'id' ) );
	sort( $ids );
	$want = array( $own_course, $own_quiz );
	sort( $want );
	ok( $want === $ids, 'author recent items are own only' );

	fwrite( STDOUT, "OK content-hub-overview-rest-integration: {$checks} checks\n" );
} catch ( Throwable $failure ) {
	fwrite( STDERR, $failure->getMessage() . ' (' . basename( $failure->getFile() ) . ':' . $failure->getLine() . ")\n" );
	$exit_code = 1;
} finally {
	wp_set_current_user( 0 );
	foreach ( $user_ids as $user_id ) {
		$wpdb->delete( $wpdb->prefix . 'ohmylms_quiz_attempts', array( 'student_id' => $user_id ) );
	}
	foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( $term_ids as $term_id ) {
		wp_delete_term( $term_id, Taxonomy::NAME );
	}
	foreach ( $item_ids as $item_id ) {
		$wpdb->delete( CurriculumSchema::table( 'curriculum_items' ), array( 'id' => $item_id ) );
	}
	if ( $bundle_id ) {
		$wpdb->delete( CurriculumSchema::table( 'bundles' ), array( 'id' => $bundle_id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $user_ids as $user_id ) {
		wp_delete_user( $user_id );
	}
}

exit( $exit_code );
