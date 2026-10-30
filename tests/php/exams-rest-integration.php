<?php
/**
 * Exams and exam-import review REST API integration test (`exams/library`, `exam-imports/jobs`).
 *
 * Runs against the Local site with real tables. Every fixture write happens inside one transaction that
 * is rolled back at the end; the one private figure file it writes is removed in `finally`. Mail and
 * outgoing HTTP are blocked, so no AI provider is called.
 *
 * Usage (set OHMYLMS_EXAMS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/exams-rest-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions

use OhMyLMS\Assessment\Schema;
use OhMyLMS\ExamImport\Documents;
use OhMyLMS\ExamImport\Figures;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\Exams\Library;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_EXAMS_TEST' ) ) {
	exit( "Set OHMYLMS_EXAMS_TEST=1 to run the exams REST tests against the local site.\n" );
}

define( 'DISABLE_WP_CRON', true );
$_SERVER['HTTP_HOST']   = getenv( 'OHMYLMS_TEST_HOST' ) ? getenv( 'OHMYLMS_TEST_HOST' ) : '800.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';

global $wpdb;
if ( 'local' !== wp_get_environment_type() || ! Schema::ready() || wp_using_ext_object_cache() ) {
	exit( "Requires the Local site, the assessment schema and a request-local object cache.\n" );
}
foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table_status ) {
	if ( 0 === strpos( $table_status['Name'], $wpdb->prefix ) && 'InnoDB' !== $table_status['Engine'] ) {
		exit( "Requires InnoDB tables so fixture writes can be rolled back.\n" );
	}
}
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'exams_test', 'Network disabled.' );
	},
	PHP_INT_MAX
);

$checks = 0;
$files  = array();
$tag    = 'extest' . strtolower( wp_generate_password( 6, false, false ) );

/**
 * Assert a condition.
 *
 * @param mixed  $value   Value that must be truthy.
 * @param string $message Failure message.
 * @throws RuntimeException When the condition is falsy.
 */
function ok( $value, $message ) {
	global $checks;
	if ( ! $value || is_wp_error( $value ) ) {
		throw new RuntimeException( 'FAILED: ' . $message );
	}
	++$checks;
}

/**
 * Dispatch a REST request as JSON (or query for GET/DELETE).
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( 'GET' === $method || 'DELETE' === $method ) {
		$request->set_query_params( $data );
	} else {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
	}
	return rest_do_request( $request );
}

/**
 * Assert an error response.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $code     Expected code.
 * @param string           $label    Description.
 */
function ok_error( $response, $status, $code, $label ) {
	$data = $response->get_data();
	$got  = is_array( $data ) && isset( $data['code'] ) ? $data['code'] : '';
	ok( $status === $response->get_status() && $code === $got, "$label: expected $status $code, got " . $response->get_status() . ' ' . $got . ' ' . wp_json_encode( $data ) );
}

/**
 * Assert a success status and return the data.
 *
 * @param WP_REST_Response $response Response.
 * @param int              $status   Expected status.
 * @param string           $label    Description.
 * @return mixed
 */
function ok_status( $response, $status, $label ) {
	ok( $status === $response->get_status(), "$label: expected $status, got " . $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );
	return $response->get_data();
}

/**
 * Create a temporary user (rolled back with the fixture).
 *
 * @param string $suffix Login suffix.
 * @param string $role   Role.
 * @return int
 */
function make_user( $suffix, $role ) {
	global $tag;
	$id = wp_insert_user(
		array(
			'user_login'   => $tag . '_' . $suffix,
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $tag . '_' . $suffix . '@example.invalid',
			'display_name' => $tag . ' ' . $suffix,
			'role'         => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role created" );
	return (int) $id;
}

/**
 * A synthetic PDF import waiting for review, without a real source file.
 *
 * @param int    $owner Owner.
 * @param string $title Inventory title.
 * @param string $skill Skill token.
 * @return int Job ID.
 */
function make_review_job( $owner, $title, $skill ) {
	$numbers   = array( '1', '2', '3' );
	$inventory = array(
		'title'            => $title,
		'duration_minutes' => 60,
		'total_marks'      => 6,
		'page_count'       => 4,
		'question_numbers' => $numbers,
	);
	$rows      = array();
	foreach ( $numbers as $index => $number ) {
		$rows[] = array(
			'number'           => $number,
			'question'         => "Question $number stem",
			'page'             => $index + 1,
			'requires_source'  => '2' === $number,
			'notes'            => '',
			'skills'           => $skill,
			'parts'            => array(
				'3' === $number ? array(
					'id'     => 'p1',
					'label'  => '(a)',
					'prompt' => 'Explain your method.',
					'kind'   => 'written',
					'marks'  => 2,
					'rubric' => 'M1 for method, A1 for answer.',
				) : array(
					'id'     => 'p1',
					'label'  => '(a)',
					'prompt' => "Work out $number + $number.",
					'kind'   => 'numerical',
					'marks'  => 2,
					'answer' => 2 * (int) $number,
				),
			),
			'reviewed'         => false,
			'figures'          => array(),
			'figures_complete' => false,
		);
	}
	$id = wp_insert_post(
		array(
			'post_type'   => Jobs::TYPE,
			'post_status' => 'private',
			'post_author' => $owner,
			'post_title'  => $title . '.pdf',
		),
		true
	);
	ok( ! is_wp_error( $id ), 'synthetic review job created' );
	update_post_meta(
		$id,
		Jobs::META,
		wp_slash(
			array(
				'document'        => array(
					'id'     => wp_generate_uuid4(),
					'name'   => $title . '.pdf',
					'bytes'  => 1024,
					'sha256' => hash( 'sha256', $title ),
				),
				'mark_scheme'     => null,
				'target'          => 'exam',
				'skills'          => $skill,
				'state'           => 'ready_for_review',
				'attempts'        => 1,
				'error'           => '',
				'updated_at'      => gmdate( 'c' ),
				'inventory'       => $inventory,
				'draft_inventory' => $inventory,
				'draft'           => $rows,
				'suggestions'     => $rows,
				'draft_revision'  => 1,
			)
		)
	);
	return (int) $id;
}

/**
 * A ready private crop record for a job; optionally with its private PNG file.
 *
 * @param int  $job_id Job ID.
 * @param int  $owner  Owner.
 * @param bool $file   Write the PNG into private storage.
 * @return string Asset UUID.
 */
function make_figure( $job_id, $owner, $file ) {
	global $files;
	$uuid  = wp_generate_uuid4();
	$bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A 1x1 PNG fixture.
	if ( $file ) {
		$root = Documents::root();
		ok( ! is_wp_error( $root ), 'private import storage is configured' );
		$path = $root . DIRECTORY_SEPARATOR . 'figure-' . $uuid . '.png';
		ok( strlen( $bytes ) === file_put_contents( $path, $bytes ), 'private crop written' );
		$files[] = $path;
	}
	$job = get_post_meta( $job_id, Jobs::META, true );
	$id  = wp_insert_post(
		array(
			'post_type'   => Figures::TYPE,
			'post_status' => 'private',
			'post_author' => $owner,
			'post_parent' => $job_id,
			'post_name'   => $uuid,
			'post_title'  => 'Private exam figure',
		),
		true
	);
	ok( ! is_wp_error( $id ), 'crop record created' );
	add_post_meta(
		$id,
		Figures::META,
		wp_slash(
			array(
				'uuid'          => $uuid,
				'request_key'   => hash( 'sha256', $uuid ),
				'document'      => $job['document'],
				'document_role' => 'question',
				'page'          => 2,
				'bounds'        => array( 0.1, 0.1, 0.6, 0.5 ),
				'state'         => 'ready',
				'attempts'      => 1,
				'error'         => '',
				'width'         => 1,
				'height'        => 1,
				'sha256'        => hash( 'sha256', $bytes ),
				'byte_count'    => strlen( $bytes ),
				'mime'          => 'image/png',
			)
		),
		true
	);
	return $uuid;
}

try {
	Transaction::run(
		static function () use ( $tag ) {
			global $wpdb;
			$routes = rest_get_server()->get_routes();
			foreach ( array( '/ohmylms/v1/exams/library', '/ohmylms/v1/exams/library/(?P<id>[\d]+)', '/ohmylms/v1/exams/library/(?P<id>[\d]+)/composition', '/ohmylms/v1/exam-imports/jobs', '/ohmylms/v1/exam-imports/jobs/(?P<id>[\d]+)/rows/(?P<index>[\d]+)', '/ohmylms/v1/exam-imports/jobs/(?P<id>[\d]+)/apply', '/ohmylms/v1/exam-imports/skills' ) as $route ) {
				ok( isset( $routes[ $route ] ), "route $route registered" );
			}
			ok( isset( $routes['/ohmylms/v1/exams'], $routes['/ohmylms/v1/exam-imports'] ), 'legacy exam routes still registered' );

			$admin       = make_user( 'admin', 'administrator' );
			$author      = make_user( 'author', 'author' );
			$other       = make_user( 'other', 'author' );
			$contributor = make_user( 'contrib', 'contributor' );
			$subscriber  = make_user( 'sub', 'subscriber' );

			wp_set_current_user( $admin );
			$skill = (int) ok_status( call_api( 'POST', 'skills', array( 'name' => $tag . ' skill' ) ), 201, 'skill created' )['id'];
			$token = '#' . $skill;
			$csv   = static function ( $exam, array $numbers ) use ( $token, $tag ) {
				$text = "exam,paper,number,type,title,question,answers,correct,skills\n";
				foreach ( $numbers as $number ) {
					$text .= "$exam,P1,$number,single-choice,$tag Q$number,\"What is $number+$number?\",1|" . ( 2 * $number ) . "|9,B,$token\n";
				}
				return $text;
			};

			// Denied users.
			wp_set_current_user( 0 );
			ok_error( call_api( 'GET', 'exams/library' ), 401, 'ohmylms_exam_cannot_view', 'guest exam list' );
			ok_error( call_api( 'GET', 'exam-imports/jobs' ), 401, 'ohmylms_exam_import_cannot_view', 'guest import list' );
			wp_set_current_user( $subscriber );
			ok_error( call_api( 'GET', 'exams/library' ), 403, 'ohmylms_exam_cannot_view', 'subscriber exam list' );
			ok_error(
				call_api(
					'POST',
					'exams/library',
					array(
						'title'            => 'x',
						'duration_minutes' => 5,
					)
				),
				403,
				'ohmylms_exam_cannot_create',
				'subscriber create'
			);
			wp_set_current_user( $contributor );
			ok_error( call_api( 'GET', 'exam-imports/jobs' ), 403, 'ohmylms_exam_import_cannot_view', 'contributor without upload_files' );
			ok_error( call_api( 'GET', 'exam-imports/skills', array( 'search' => 'algebra' ) ), 403, 'ohmylms_exam_import_cannot_view', 'contributor skill search' );

			// Question rows: dry run, then committed jobs for the bank.
			wp_set_current_user( $author );
			$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s", Jobs::TYPE ) );
			$dry    = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs',
					array(
						'text'    => $csv( $tag . 'bank', array( 1 ) ) . "$tag,P1,9,single-choice,$tag bad,\"Bad\",1|2,A,NO.SUCH.SKILL\n",
						'format'  => 'csv',
						'dry_run' => true,
					)
				),
				200,
				'CSV dry run'
			);
			ok( true === $dry['dry_run'] && 1 === (int) $dry['counts']['error'] && 'error' === $dry['rows'][1]['status'] && $dry['rows'][1]['errors'], 'dry run reports the invalid row' );
			ok( $before === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s", Jobs::TYPE ) ), 'dry run creates no job' );
			ok_error( call_api( 'POST', 'exam-imports/jobs', array( 'text' => '  ' ) ), 400, 'ohmylms_exam_import_empty', 'empty rows' );
			ok_error(
				call_api(
					'POST',
					'exam-imports/jobs',
					array(
						'text'     => 'a',
						'surprise' => 1,
					)
				),
				400,
				'ohmylms_exam_import_unknown_field',
				'unknown create field'
			);
			ok_error(
				call_api(
					'POST',
					'exam-imports/jobs',
					array(
						'text'   => 'a',
						'target' => 'course',
					)
				),
				400,
				'rest_invalid_param',
				'invalid target'
			);
			$bank = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs',
					array(
						'text'   => $csv( $tag . 'bank', array( 1, 2, 3 ) ),
						'format' => 'csv',
						'target' => 'questions',
						'name'   => $tag . ' bank',
					)
				),
				201,
				'CSV rows job'
			);
			ok( 'rows' === $bank['kind'] && 'committed' === $bank['state'] && 3 === count( $bank['question_ids'] ) && false === $bank['can_apply'], 'rows job is committed with three questions' );
			list( $q1, $q2, $q3 ) = $bank['question_ids'];
			wp_set_current_user( $other );
			$foreign = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs',
					array(
						'text'   => $csv( $tag . 'other', array( 7 ) ),
						'format' => 'csv',
						'target' => 'questions',
					)
				),
				201,
				'other author rows'
			)['question_ids'][0];

			// Exams: create, read, validation and ownership.
			wp_set_current_user( $author );
			ok_error( call_api( 'POST', 'exams/library', array( 'duration_minutes' => 30 ) ), 400, 'rest_missing_callback_param', 'missing title' );
			ok_error(
				call_api(
					'POST',
					'exams/library',
					array(
						'title'            => '   ',
						'duration_minutes' => 30,
					)
				),
				422,
				'ohmylms_exam_invalid_field',
				'blank title'
			);
			ok_error(
				call_api(
					'POST',
					'exams/library',
					array(
						'title'            => 'x',
						'duration_minutes' => 0,
					)
				),
				400,
				'rest_invalid_param',
				'zero duration'
			);
			ok_error(
				call_api(
					'POST',
					'exams/library',
					array(
						'title'            => 'x',
						'duration_minutes' => 5,
						'status'           => 'publish',
					)
				),
				400,
				'ohmylms_exam_unknown_field',
				'unknown exam field'
			);
			$created = call_api(
				'POST',
				'exams/library',
				array(
					'title'            => $tag . ' mock',
					'duration_minutes' => 45,
				)
			);
			$exam    = ok_status( $created, 201, 'exam created' );
			$id      = (int) $exam['id'];
			ok( false !== strpos( (string) $created->get_headers()['Location'], 'exams/library/' . $id ) && 'private, no-store' === $created->get_headers()['Cache-Control'], 'Location and no-store headers' );
			ok( 'composed' === $exam['origin'] && 'draft' === $exam['status'] && 45 === $exam['duration_minutes'] && array() === $exam['composition'] && 40 === strlen( $exam['revision'] ), 'new exam is an empty composed draft' );
			ok( 'a4' === $exam['paper']['size'] && true === $exam['permissions']['can_compose'], 'paper defaults and permissions' );
			$list = call_api( 'GET', 'exams/library', array( 'search' => $tag ) );
			ok( in_array( $id, array_column( ok_status( $list, 200, 'exam list' ), 'id' ), true ) && '1' === (string) $list->get_headers()['X-WP-Total'], 'list shows the exam with totals' );
			ok_error( call_api( 'GET', 'exams/library', array( 'page' => 9 ) ), 400, 'ohmylms_exam_invalid_page', 'page past the end' );
			ok_error( call_api( 'GET', 'exams/library/999999999' ), 404, 'ohmylms_exam_not_found', 'unknown exam' );
			ok_error( call_api( 'GET', 'exams/library/' . $q1 ), 404, 'ohmylms_exam_not_found', 'another post type' );
			wp_set_current_user( $other );
			ok_error( call_api( 'GET', 'exams/library/' . $id ), 403, 'ohmylms_exam_forbidden', 'other author read' );
			ok_error(
				call_api(
					'PATCH',
					'exams/library/' . $id,
					array(
						'revision' => $exam['revision'],
						'title'    => 'Mine',
					)
				),
				403,
				'ohmylms_exam_forbidden',
				'other author update'
			);
			ok( ! in_array( $id, array_column( call_api( 'GET', 'exams/library' )->get_data(), 'id' ), true ), 'other author list omits the exam' );
			wp_set_current_user( $admin );
			ok_error( call_api( 'GET', 'exams/library/' . $id ), 403, 'ohmylms_exam_forbidden', 'administrator is not the owner' );

			// Settings: stale writes and validation.
			wp_set_current_user( $author );
			ok_error( call_api( 'PATCH', 'exams/library/' . $id, array( 'title' => 'No revision' ) ), 400, 'rest_missing_callback_param', 'missing revision' );
			ok_error(
				call_api(
					'PATCH',
					'exams/library/' . $id,
					array(
						'revision' => str_repeat( 'a', 40 ),
						'title'    => 'Stale',
					)
				),
				409,
				'ohmylms_exam_conflict',
				'stale settings write'
			);
			ok_error(
				call_api(
					'PATCH',
					'exams/library/' . $id,
					array(
						'revision' => $exam['revision'],
						'paper'    => array( 'size' => 'a3' ),
					)
				),
				400,
				'rest_invalid_param',
				'invalid paper size'
			);
			ok_error(
				call_api(
					'PATCH',
					'exams/library/' . $id,
					array(
						'revision' => $exam['revision'],
						'header'   => array( 'school' => 'x' ),
					)
				),
				400,
				'rest_invalid_param',
				'unknown header field'
			);
			ok_error(
				call_api(
					'PATCH',
					'exams/library/' . $id,
					array(
						'revision' => $exam['revision'],
						'timing'   => array(
							'available_from'  => '2026-11-02T10:00:00Z',
							'available_until' => '2026-11-01T10:00:00Z',
						),
					)
				),
				422,
				'ohmylms_exam_invalid_timing',
				'closing before opening'
			);
			$saved = ok_status(
				call_api(
					'PATCH',
					'exams/library/' . $id,
					array(
						'revision'         => $exam['revision'],
						'title'            => $tag . ' mock paper',
						'duration_minutes' => 90,
						'instructions'     => 'Answer all questions.',
						'header'           => array(
							'board'   => 'Cambridge',
							'session' => 'Oct/Nov 2026',
						),
						'timing'           => array(
							'grace_seconds'    => 60,
							'feedback_release' => 'manual',
							'available_from'   => '2026-11-01T09:00:00Z',
						),
						'paper'            => array(
							'size'     => 'letter',
							'versions' => 2,
						),
					)
				),
				200,
				'settings saved'
			);
			ok( $tag . ' mock paper' === $saved['title'] && 90 === $saved['duration_minutes'] && 'Cambridge' === $saved['header']['board'] && 'Answer all questions.' === $saved['instructions'], 'title, duration, header and instructions saved' );
			ok( 60 === $saved['timing']['grace_seconds'] && 'manual' === $saved['timing']['feedback_release'] && '2026-11-01T09:00:00Z' === $saved['timing']['available_from'], 'timing saved' );
			ok( 'letter' === $saved['paper']['size'] && 2 === $saved['paper']['versions'] && 20 === $saved['paper']['margin'], 'paper settings merged' );
			ok( ! hash_equals( $exam['revision'], $saved['revision'] ) && get_the_title( $saved['quiz_id'] ) === $tag . ' mock paper', 'revision changes and the backing quiz follows' );
			ok_error(
				call_api(
					'PATCH',
					'exams/library/' . $id,
					array(
						'revision' => $exam['revision'],
						'title'    => 'Again',
					)
				),
				409,
				'ohmylms_exam_conflict',
				'second write with the old revision'
			);

			// Composition.
			$rev       = $saved['revision'];
			$bank_rows = ok_status( call_api( 'GET', 'exams/library/' . $id . '/bank', array( 'search' => $tag . ' Q' ) ), 200, 'bank search' );
			ok( array() === array_diff( array( $q1, $q2, $q3 ), array_column( $bank_rows, 'id' ) ) && ! in_array( $foreign, array_column( $bank_rows, 'id' ), true ), 'bank lists own questions only' );
			ok( ! isset( $bank_rows[0]['settings'] ) && ! isset( $bank_rows[0]['options'] ), 'bank rows carry no answers' );
			$composition = static function ( array $items, $revision ) use ( $id ) {
				return call_api(
					'PUT',
					'exams/library/' . $id . '/composition',
					array(
						'revision' => $revision,
						'items'    => $items,
					)
				);
			};
			ok_error(
				$composition(
					array(
						array(
							'kind'        => 'question',
							'question_id' => $q1,
						),
					),
					str_repeat( 'b', 40 )
				),
				409,
				'ohmylms_exam_conflict',
				'stale composition'
			);
			ok_error(
				$composition(
					array(
						array(
							'kind'        => 'question',
							'question_id' => $q1,
						),
						array(
							'kind'        => 'question',
							'question_id' => $q1,
						),
					),
					$rev
				),
				422,
				'ohmylms_exam_invalid_field',
				'duplicate question'
			);
			ok_error(
				$composition(
					array(
						array(
							'kind'  => 'section',
							'title' => ' ',
						),
					),
					$rev
				),
				422,
				'ohmylms_exam_invalid_field',
				'blank section title'
			);
			ok_error( $composition( array( array( 'kind' => 'page' ) ), $rev ), 400, 'rest_invalid_param', 'unknown item kind' );
			ok_error(
				$composition(
					array(
						array(
							'kind'        => 'question',
							'question_id' => $foreign,
						),
					),
					$rev
				),
				403,
				'ohmylms_exam_question_unavailable',
				'another author\'s private question'
			);
			$composed = ok_status(
				$composition(
					array(
						array(
							'kind'        => 'question',
							'question_id' => $q3,
						),
						array(
							'kind'     => 'section',
							'title'    => 'Section A',
							'new_page' => true,
						),
						array(
							'kind'        => 'question',
							'question_id' => $q1,
						),
						array(
							'kind'        => 'question',
							'question_id' => $q2,
						),
						array(
							'kind'  => 'section',
							'title' => 'Section B',
						),
					),
					$rev
				),
				200,
				'composition saved'
			);
			$kinds    = array_map(
				static function ( $item ) {
					return 'section' === $item['kind'] ? 'S:' . $item['title'] : 'Q:' . $item['question_id'];
				},
				$composed['composition']
			);
			ok( array( 'Q:' . $q3, 'S:Section A', 'Q:' . $q1, 'Q:' . $q2, 'S:Section B' ) === $kinds && 3 === $composed['question_count'], 'composition order with sections and an empty trailing section' );
			$sections = \OhMyLMS\Assessment\AssessmentSettings::get( $composed['quiz_id'] )['sections'];
			ok( 2 === count( $sections ) && array( $q1, $q2 ) === array_map( 'intval', $sections[0]['questions'] ) && $sections[0]['new_page'], 'sections stored on the backing quiz' );
			$reordered = ok_status(
				$composition(
					array(
						array(
							'kind'  => 'section',
							'title' => 'Only',
						),
						array(
							'kind'        => 'question',
							'question_id' => $q2,
						),
						array(
							'kind'        => 'question',
							'question_id' => $q1,
						),
					),
					$composed['revision']
				),
				200,
				'reorder and remove'
			);
			ok( array( $q2, $q1 ) === \OhMyLMS\Exams\Editor::placements( $reordered['quiz_id'] ) && 2 === $reordered['question_count'], 'placements reordered and the removed question unlinked' );
			wp_set_current_user( $other );
			ok_error( call_api( 'GET', 'exams/library/' . $id . '/bank' ), 403, 'ohmylms_exam_forbidden', 'other author bank search' );
			ok_error( call_api( 'POST', 'exams/library/' . $id . '/publish', array( 'revision' => $reordered['revision'] ) ), 403, 'ohmylms_exam_forbidden', 'other author publish' );
			wp_set_current_user( $author );
			ok_error( call_api( 'POST', 'exams/library/' . $id . '/publish', array( 'revision' => $rev ) ), 409, 'ohmylms_exam_conflict', 'stale publish' );

			// An exam from imported rows: questions are locked, sections are not; apply is idempotent.
			$rows_job = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs',
					array(
						'text'   => $csv( $tag . 'paper', array( 4, 5 ) ),
						'format' => 'csv',
						'target' => 'exam',
						'name'   => $tag . ' rows paper',
					)
				),
				201,
				'exam rows job'
			);
			ok( true === $rows_job['can_apply'], 'committed exam job can be applied' );
			$applied = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs/' . $rows_job['id'] . '/apply',
					array(
						'revision'         => 0,
						'title'            => $tag . ' imported',
						'duration_minutes' => 30,
						'board'            => 'AQA',
					)
				),
				200,
				'apply rows job'
			);
			$again   = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs/' . $rows_job['id'] . '/apply',
					array(
						'revision' => 0,
						'title'    => 'Different',
					)
				),
				200,
				'apply again'
			);
			ok( $applied['exam_id'] > 0 && $applied['exam_id'] === $again['exam_id'] && false === $again['can_apply'], 'apply is idempotent' );
			$imported = ok_status( call_api( 'GET', 'exams/library/' . $applied['exam_id'] ), 200, 'imported exam read' );
			ok( 'imported' === $imported['origin'] && 30 === $imported['duration_minutes'] && 'AQA' === $imported['header']['board'] && false === $imported['permissions']['can_compose'], 'imported exam keeps its header' );
			$ids = array_column( $imported['composition'], 'question_id' );
			ok_error(
				call_api(
					'PUT',
					'exams/library/' . $imported['id'] . '/composition',
					array(
						'revision' => $imported['revision'],
						'items'    => array(
							array(
								'kind'        => 'question',
								'question_id' => $ids[1],
							),
							array(
								'kind'        => 'question',
								'question_id' => $ids[0],
							),
						),
					)
				),
				409,
				'ohmylms_exam_questions_locked',
				'imported questions cannot be reordered'
			);
			$sectioned = ok_status(
				call_api(
					'PUT',
					'exams/library/' . $imported['id'] . '/composition',
					array(
						'revision' => $imported['revision'],
						'items'    => array(
							array(
								'kind'  => 'section',
								'title' => 'Paper 1',
							),
							array(
								'kind'        => 'question',
								'question_id' => $ids[0],
							),
							array(
								'kind'        => 'question',
								'question_id' => $ids[1],
							),
						),
					)
				),
				200,
				'imported exam sections'
			);
			ok( 'section' === $sectioned['composition'][0]['kind'], 'imported exam accepts sections' );
			ok_error( call_api( 'DELETE', 'exam-imports/jobs/' . $rows_job['id'] ), 409, 'exam_source_in_use', 'job used by an exam is kept' );

			// PDF review rows: ownership, figures, stale writes, accept/reject and apply.
			$job      = make_review_job( $author, $tag . ' paper', $token );
			$theirs   = make_review_job( $other, $tag . ' theirs', $token );
			$figure   = make_figure( $job, $author, true );
			$stranger = make_figure( $theirs, $other, false );
			$detail   = ok_status( call_api( 'GET', 'exam-imports/jobs/' . $job ), 200, 'review job read' );
			ok( 'pdf' === $detail['kind'] && 3 === count( $detail['rows'] ) && 'pending' === $detail['rows'][0]['status'] && 1 === $detail['revision'] && 'p1' === $detail['rows'][0]['parts'][0]['id'], 'review rows' );
			ok( ! isset( $detail['document'] ) && false === strpos( wp_json_encode( $detail ), 'sha256' ), 'no source handles or hashes in the view' );
			$jobs = call_api( 'GET', 'exam-imports/jobs' );
			ok( in_array( $job, array_column( $jobs->get_data(), 'id' ), true ) && ! in_array( $theirs, array_column( $jobs->get_data(), 'id' ), true ), 'job list is owner scoped' );
			ok_error( call_api( 'GET', 'exam-imports/jobs/' . $theirs ), 403, 'ohmylms_exam_import_forbidden', 'cross-owner job read' );
			ok_error( call_api( 'GET', 'exam-imports/jobs/' . $theirs . '/figures' ), 403, 'ohmylms_exam_import_forbidden', 'cross-owner figure list' );
			ok_error( call_api( 'GET', 'exam-imports/jobs/' . $theirs . '/figures/' . $stranger ), 403, 'ohmylms_exam_import_forbidden', 'cross-owner figure image' );
			ok_error( call_api( 'GET', 'exam-imports/jobs/' . $job . '/figures/' . $stranger ), 404, 'ohmylms_exam_import_figure_not_found', 'foreign figure through an own job' );
			ok_error( call_api( 'GET', 'exam-imports/jobs/' . $id ), 404, 'ohmylms_exam_import_not_found', 'exam ID is not a job' );
			$listed = ok_status( call_api( 'GET', 'exam-imports/jobs/' . $job . '/figures' ), 200, 'figure list' );
			ok( 1 === count( $listed ) && $figure === $listed[0]['id'] && false !== strpos( $listed[0]['url'], '/exam-imports/jobs/' . $job . '/figures/' . $figure ) && false !== strpos( $listed[0]['url'], '_wpnonce=' ), 'figure list uses the authorised route' );
			$image = call_api( 'GET', 'exam-imports/jobs/' . $job . '/figures/' . $figure );
			ok( 200 === $image->get_status() && 'image/png' === $image->get_headers()['Content-Type'] && 'private, no-store' === $image->get_headers()['Cache-Control'], 'owner receives the crop' );
			wp_set_current_user( $other );
			ok_error( call_api( 'GET', 'exam-imports/jobs/' . $job . '/figures/' . $figure ), 403, 'ohmylms_exam_import_forbidden', 'other author cannot fetch the crop' );
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => 1,
						'notes'    => 'x',
					)
				),
				403,
				'ohmylms_exam_import_forbidden',
				'cross-owner row edit'
			);
			ok_error( call_api( 'POST', 'exam-imports/jobs/' . $job . '/apply', array( 'revision' => 1 ) ), 403, 'ohmylms_exam_import_forbidden', 'cross-owner apply' );
			ok_error( call_api( 'DELETE', 'exam-imports/jobs/' . $job ), 403, 'ohmylms_exam_import_forbidden', 'cross-owner delete' );
			wp_set_current_user( $admin );
			ok_error( call_api( 'GET', 'exam-imports/jobs/' . $job ), 403, 'ohmylms_exam_import_forbidden', 'administrator is not the job owner' );
			wp_set_current_user( $author );

			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => 0,
						'notes'    => 'x',
					)
				),
				409,
				'ohmylms_exam_import_conflict',
				'stale row edit'
			);
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/9',
					array(
						'revision' => 1,
						'notes'    => 'x',
					)
				),
				404,
				'ohmylms_exam_import_row_not_found',
				'unknown row'
			);
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => 1,
						'number'   => '7',
					)
				),
				400,
				'ohmylms_exam_import_unknown_field',
				'number is not editable'
			);
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => 1,
						'page'     => 0,
					)
				),
				400,
				'rest_invalid_param',
				'page below 1'
			);
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => 1,
						'page'     => 9,
					)
				),
				422,
				'ohmylms_exam_import_invalid_row',
				'page beyond the paper'
			);
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => 1,
						'parts'    => array(
							array(
								'id'     => 'p1',
								'kind'   => 'numerical',
								'marks'  => -1,
								'answer' => 2,
							),
						),
					)
				),
				422,
				'ohmylms_exam_import_invalid_row',
				'negative marks'
			);
			$figure_row = array(
				'id'           => 'f1',
				'page'         => 2,
				'bounds'       => array( 0.1, 0.1, 0.6, 0.5 ),
				'part_ids'     => array(),
				'asset_id'     => $stranger,
				'alt'          => 'A right-angled triangle',
				'not_to_scale' => true,
				'selected'     => 'original',
				'approved'     => true,
			);
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/1',
					array(
						'revision' => 1,
						'figures'  => array( $figure_row ),
					)
				),
				403,
				'ohmylms_exam_import_figure_forbidden',
				'figure of another owner'
			);
			ok_error( call_api( 'POST', 'exam-imports/jobs/' . $job . '/rows/1/accept', array( 'revision' => 1 ) ), 409, 'ohmylms_exam_import_figures_required', 'diagram question needs its figure' );
			ok_error( call_api( 'POST', 'exam-imports/jobs/' . $job . '/apply', array( 'revision' => 1 ) ), 409, 'ohmylms_exam_import_unreviewed', 'apply before review' );

			$edited = ok_status(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => 1,
						'question' => 'Edited stem <script>x</script>',
						'skills'   => $token,
					)
				),
				200,
				'row edited'
			);
			ok( 2 === $edited['revision'] && 'pending' === $edited['rows'][0]['status'] && false === strpos( $edited['rows'][0]['question'], '<script>' ), 'edit bumps the revision and sanitises text' );
			$state = ok_status( call_api( 'POST', 'exam-imports/jobs/' . $job . '/rows/0/accept', array( 'revision' => 2 ) ), 200, 'row 1 accepted' );
			ok( 'accepted' === $state['rows'][0]['status'] && 3 === $state['revision'], 'accepted' );
			ok_error( call_api( 'POST', 'exam-imports/jobs/' . $job . '/rows/2/accept', array( 'revision' => 2 ) ), 409, 'ohmylms_exam_import_conflict', 'stale accept' );
			$figure_row['asset_id'] = $figure;
			$state                  = ok_status(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/1',
					array(
						'revision' => 3,
						'figures'  => array( $figure_row ),
					)
				),
				200,
				'own figure attached'
			);
			$state                  = ok_status( call_api( 'POST', 'exam-imports/jobs/' . $job . '/rows/1/accept', array( 'revision' => $state['revision'] ) ), 200, 'diagram question accepted' );
			$state                  = ok_status( call_api( 'POST', 'exam-imports/jobs/' . $job . '/rows/2/reject', array( 'revision' => $state['revision'] ) ), 200, 'row 3 rejected' );
			ok( 'rejected' === $state['rows'][2]['status'] && 1 === $state['rejected_count'] && 2 === $state['accepted_count'] && true === $state['can_apply'], 'decisions recorded' );
			ok_error( call_api( 'POST', 'exam-imports/jobs/' . $job . '/apply', array( 'revision' => $state['revision'] - 1 ) ), 409, 'ohmylms_exam_import_conflict', 'stale apply' );
			$done = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs/' . $job . '/apply',
					array(
						'revision'         => $state['revision'],
						'title'            => $tag . ' reviewed paper',
						'duration_minutes' => 75,
					)
				),
				200,
				'apply review job'
			);
			ok( 'committed' === $done['state'] && 2 === count( $done['question_ids'] ) && $done['exam_id'] > 0, 'accepted rows committed, rejected row left out, exam created' );
			$repeat = ok_status( call_api( 'POST', 'exam-imports/jobs/' . $job . '/apply', array( 'revision' => $state['revision'] ) ), 200, 'apply review job again' );
			ok( $done['exam_id'] === $repeat['exam_id'] && $done['question_ids'] === $repeat['question_ids'], 'second apply changes nothing' );
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_title=%s", Library::TYPE, $tag . ' reviewed paper' ) );
			ok( 1 === $count, 'exactly one exam for the job' );
			$identity = \OhMyLMS\QuestionBank\VersionPublisher::identity( $done['question_ids'][1], false );
			$version  = \OhMyLMS\QuestionBank\VersionPublisher::version( (int) $identity['current_version_id'] );
			$settings = json_decode( (string) $version['settings'], true );
			ok( hash_equals( $figure, (string) ( $settings['figures'][0]['asset_id'] ?? '' ) ), 'committed question keeps the reviewed figure' );
			ok_error(
				call_api(
					'PATCH',
					'exam-imports/jobs/' . $job . '/rows/0',
					array(
						'revision' => $repeat['revision'],
						'notes'    => 'late',
					)
				),
				409,
				'ohmylms_exam_import_not_reviewable',
				'committed rows are read only'
			);

			// Skill tagging search.
			$skills = ok_status( call_api( 'GET', 'exam-imports/skills', array( 'search' => $tag . ' skill' ) ), 200, 'skill search' );
			ok( in_array( $skill, array_column( $skills, 'id' ), true ), 'skill search finds the catalogue skill' );
			ok_error( call_api( 'GET', 'exam-imports/skills' ), 400, 'rest_missing_callback_param', 'skill search needs text' );

			throw new RuntimeException( 'exams_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'exams_rollback_success' !== $error->getMessage() ) {
		echo $error->getMessage() . "\n";
		$exit_code = 1;
	}
} finally {
	wp_set_current_user( 0 );
	foreach ( $files as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
}

ok( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login=%s", $tag . '_author' ) ), 'fixture transaction rolled back' );
echo ( empty( $exit_code ) ? 'OK' : 'FAILED' ) . ": $checks exam API checks; fixture writes rolled back.\n";
exit( isset( $exit_code ) ? $exit_code : 0 );
