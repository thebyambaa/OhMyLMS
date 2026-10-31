<?php
/**
 * JSXGraph figure proposal integration test (`exam-imports/jobs/{id}/rows/{index}/figures/{figure}/proposal…`).
 *
 * Runs against the Local site with real tables. Every fixture write happens inside one transaction that
 * is rolled back at the end; the private crop files it writes are removed in `finally`. Mail and
 * outgoing HTTP are blocked; the AI provider is a fixture whose transport is intercepted.
 *
 * Usage (set OHMYLMS_EXAM_PROPOSALS_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/exam-figure-proposals-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions

use OhMyLMS\Assessment\Schema;
use OhMyLMS\ExamImport\Documents;
use OhMyLMS\ExamImport\FigureContent;
use OhMyLMS\ExamImport\Figures;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_EXAM_PROPOSALS_TEST' ) ) {
	exit( "Set OHMYLMS_EXAM_PROPOSALS_TEST=1 to run the figure proposal tests against the local site.\n" );
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
$provider_reply = '';
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$provider_reply ) {
		if ( 'https://example.invalid/proposal-fixture' === $url ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'text' => $provider_reply ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		}
		return new WP_Error( 'proposals_test', 'Network disabled.' );
	},
	PHP_INT_MAX,
	3
);

$checks = 0;
$files  = array();
$tag    = 'exprop' . strtolower( wp_generate_password( 6, false, false ) );

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
 * Dispatch a REST request as JSON (or query for GET).
 *
 * @param string $method HTTP method.
 * @param string $path   Path below /ohmylms/v1/.
 * @param array  $data   Body or query.
 * @return WP_REST_Response
 */
function call_api( $method, $path, array $data = array() ) {
	$request = new WP_REST_Request( $method, '/ohmylms/v1/' . $path );
	if ( 'GET' === $method ) {
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
			'user_login' => $tag . '_' . $suffix,
			'user_pass'  => wp_generate_password( 24 ),
			'user_email' => $tag . '_' . $suffix . '@example.invalid',
			'role'       => $role,
		)
	);
	ok( ! is_wp_error( $id ), "temporary $role created" );
	return (int) $id;
}

/**
 * A synthetic PDF import waiting for review: one question that needs its diagram.
 *
 * @param int    $owner Owner.
 * @param string $title Title.
 * @param string $skill Skill token.
 * @return int Job ID.
 */
function make_review_job( $owner, $title, $skill ) {
	$inventory = array(
		'title'            => $title,
		'duration_minutes' => 20,
		'total_marks'      => 2,
		'page_count'       => 2,
		'question_numbers' => array( '1' ),
	);
	$rows      = array(
		array(
			'number'           => '1',
			'question'         => 'Triangle ABC is shown.',
			'page'             => 1,
			'requires_source'  => true,
			'notes'            => '',
			'skills'           => $skill,
			'parts'            => array(
				array(
					'id'     => 'p1',
					'label'  => '(a)',
					'prompt' => 'Work out angle ABC.',
					'kind'   => 'numerical',
					'marks'  => 2,
					'answer' => 60,
				),
			),
			'reviewed'         => false,
			'figures'          => array(),
			'figures_complete' => false,
		),
	);
	$id        = wp_insert_post(
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
 * A ready private crop of a job, with its PNG file in private storage.
 *
 * @param int $job_id Job ID.
 * @param int $owner  Owner.
 * @return string Asset UUID.
 */
function make_figure( $job_id, $owner ) {
	global $files;
	$uuid  = wp_generate_uuid4();
	$bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- A 1x1 PNG fixture.
	$root  = Documents::root();
	ok( ! is_wp_error( $root ), 'private import storage is configured' );
	$path = $root . DIRECTORY_SEPARATOR . 'figure-' . $uuid . '.png';
	ok( strlen( $bytes ) === file_put_contents( $path, $bytes ), 'private crop written' );
	$files[] = $path;
	$job     = get_post_meta( $job_id, Jobs::META, true );
	$id      = wp_insert_post(
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
				'page'          => 1,
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

/**
 * The first figure of the first row of a job view.
 *
 * @param array $view Job detail.
 * @return array
 */
function first_figure( array $view ) {
	return $view['rows'][0]['figures'][0];
}

try {
	Transaction::run(
		static function () use ( $tag, &$provider_reply ) {
			$routes = rest_get_server()->get_routes();
			$base   = '/ohmylms/v1/exam-imports/jobs/(?P<id>[\d]+)/rows/(?P<index>[\d]+)/figures/(?P<figure>[a-zA-Z0-9_-]{1,40})/proposal';
			foreach ( array( '', '/accept', '/reject' ) as $suffix ) {
				ok( isset( $routes[ $base . $suffix ] ), "route proposal$suffix registered" );
			}
			ok( isset( $routes['/ohmylms/v1/exam-imports/(?P<id>\d+)/reconstruct'] ), 'legacy reconstruct route still registered' );

			$admin  = make_user( 'admin', 'administrator' );
			$author = make_user( 'author', 'author' );
			$other  = make_user( 'other', 'author' );
			wp_set_current_user( $admin );
			$skill = (int) ok_status( call_api( 'POST', 'skills', array( 'name' => $tag . ' skill' ) ), 201, 'skill created' )['id'];
			\OhMyLMS\AI\Settings::update( array( 'exam_converter_enabled' => false ) );

			wp_set_current_user( $author );
			$job    = make_review_job( $author, $tag . ' paper', '#' . $skill );
			$crop   = make_figure( $job, $author );
			$second = make_figure( $job, $author );
			$path   = 'exam-imports/jobs/' . $job . '/rows/0';
			$figure = array(
				'id'           => 'f1',
				'page'         => 1,
				'bounds'       => array( 0.1, 0.1, 0.6, 0.5 ),
				'part_ids'     => array(),
				'asset_id'     => $crop,
				'alt'          => 'Triangle ABC with angle B marked.',
				'not_to_scale' => true,
				'selected'     => 'original',
				'approved'     => false,
			);
			$view   = ok_status(
				call_api(
					'PATCH',
					$path,
					array(
						'revision' => 1,
						'figures'  => array( $figure ),
					)
				),
				200,
				'figure attached'
			);
			ok( false === $view['can_reconstruct'], 'no converter, no generated proposals' );
			$proposal = $path . '/figures/f1/proposal';
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'generate',
					)
				),
				409,
				'ohmylms_exam_import_converter_unavailable',
				'generating needs the configured converter'
			);

			$spec = array(
				'version' => 1,
				'bounds'  => array( -1, 5, 6, -1 ),
				'objects' => array(
					array(
						'id'     => 'A',
						'type'   => 'point',
						'x'      => 0,
						'y'      => 0,
						'label'  => 'A',
						'marker' => true,
					),
					array(
						'id'    => 'B',
						'type'  => 'point',
						'x'     => 4,
						'y'     => 0,
						'label' => 'B',
					),
					array(
						'id'    => 'C',
						'type'  => 'point',
						'x'     => 2,
						'y'     => 3,
						'label' => 'C',
					),
					array(
						'id'     => 'tri',
						'type'   => 'polygon',
						'points' => array( 'A', 'B', 'C' ),
					),
					array(
						'id'     => 'angB',
						'type'   => 'angle',
						'points' => array( 'A', 'B', 'C' ),
						'label'  => 'x°',
					),
				),
			);

			// Authored configurations are validated by the server; nothing executable gets through.
			$invalid = array(
				'script label'       => array( 3, 'label', '<script>alert(1)</script>' ),
				'event handler'      => array( 0, 'onclick', 'alert(1)' ),
				'javascript URL'     => array( 1, 'label', 'javascript:alert(1)' ),
				'external URL'       => array( 2, 'label', 'https://evil.example/x.js' ),
				'unknown reference'  => array( 3, 'points', array( 'A', 'B', 'Z' ) ),
				'unsupported object' => array( 4, 'type', 'html' ),
			);
			foreach ( $invalid as $label => list( $at, $key, $value ) ) {
				$bad                       = $spec;
				$bad['objects'][ $at ][ $key ] = $value;
				ok_error(
					call_api(
						'POST',
						$proposal,
						array(
							'revision' => $view['revision'],
							'source'   => 'authored',
							'spec'     => $bad,
						)
					),
					422,
					'ohmylms_exam_import_invalid_proposal',
					"rejected: $label"
				);
			}
			$function_spec = array(
				'version' => 1,
				'bounds'  => array( -5, 5, 5, -5 ),
				'objects' => array(
					array(
						'id'         => 'f',
						'type'       => 'function',
						'expression' => array(
							'op'   => 'eval',
							'args' => array( 'x' ),
						),
					),
				),
			);
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => $function_spec,
					)
				),
				422,
				'ohmylms_exam_import_invalid_proposal',
				'rejected: expression outside the grammar'
			);
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => array_merge( $spec, array( 'version' => 2 ) ),
					)
				),
				422,
				'ohmylms_exam_import_invalid_proposal',
				'rejected: unknown spec version'
			);
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
					)
				),
				400,
				'rest_missing_callback_param',
				'an authored proposal needs its configuration'
			);
			ok_error(
				call_api(
					'POST',
					$path . '/figures/zz/proposal',
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => $spec,
					)
				),
				404,
				'ohmylms_exam_import_figure_not_found',
				'unknown figure'
			);
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'] - 1,
						'source'   => 'authored',
						'spec'     => $spec,
					)
				),
				409,
				'ohmylms_exam_import_conflict',
				'stale proposal'
			);
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => $spec,
						'selected' => 'jsxgraph',
					)
				),
				400,
				'ohmylms_exam_import_unknown_field',
				'proposal routes accept only their fields'
			);
			ok_error( call_api( 'POST', $proposal . '/accept', array( 'revision' => $view['revision'] ) ), 409, 'ohmylms_exam_import_no_proposal', 'nothing to accept yet' );
			wp_set_current_user( $other );
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => $spec,
					)
				),
				403,
				'ohmylms_exam_import_forbidden',
				'another author proposes'
			);
			ok_error( call_api( 'POST', $proposal . '/accept', array( 'revision' => $view['revision'] ) ), 403, 'ohmylms_exam_import_forbidden', 'another author accepts' );
			wp_set_current_user( $author );

			// Propose, then accept to replace the original.
			$view  = ok_status(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => $spec,
					)
				),
				200,
				'authored proposal saved'
			);
			$found = first_figure( $view );
			ok( 'proposed' === $found['proposal']['status'] && 'authored' === $found['proposal']['origin'] && 'original' === $found['selected'] && ! isset( $found['spec'] ) && false === $found['approved'] && 'pending' === $view['rows'][0]['status'], 'a proposal is not delivered until accepted' );
			ok( 'x°' === $found['proposal']['spec']['objects'][4]['label'] && '#202020' === $found['proposal']['spec']['objects'][0]['color'], 'the spec is stored canonicalised' );

			// A row edit cannot select a reconstruction or forge an accepted proposal.
			$forged             = $found;
			$forged['selected'] = 'jsxgraph';
			$forged['spec']     = $spec;
			$forged['proposal'] = array_merge( $found['proposal'], array( 'status' => 'accepted' ) );
			$forged['alt']      = 'Triangle ABC, angle B marked x.';
			$view               = ok_status(
				call_api(
					'PATCH',
					$path,
					array(
						'revision' => $view['revision'],
						'figures'  => array( $forged ),
					)
				),
				200,
				'row edit with forged figure fields'
			);
			$found              = first_figure( $view );
			ok( 'original' === $found['selected'] && ! isset( $found['spec'] ) && 'proposed' === $found['proposal']['status'] && 'Triangle ABC, angle B marked x.' === $found['alt'], 'row edits keep the server-owned reconstruction fields' );

			$view  = ok_status( call_api( 'POST', $proposal . '/accept', array( 'revision' => $view['revision'] ) ), 200, 'proposal accepted' );
			$found = first_figure( $view );
			ok( 'accepted' === $found['proposal']['status'] && 'replace' === $found['proposal']['display'] && 'jsxgraph' === $found['selected'] && $found['spec'] === $found['proposal']['spec'] && false === $found['approved'], 'accepting replaces the original and asks for approval again' );
			ok_error( call_api( 'POST', $proposal . '/accept', array( 'revision' => $view['revision'], 'display' => 'inline' ) ), 400, 'rest_invalid_param', 'display is replace or accompany' );

			// Editing an accepted proposal by hand puts it back to proposed.
			$edited                          = $found['proposal']['spec'];
			$edited['objects'][4]['label']   = 'y°';
			$view                            = ok_status(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => $edited,
					)
				),
				200,
				'proposal edited'
			);
			$found                           = first_figure( $view );
			ok( 'proposed' === $found['proposal']['status'] && 'authored' === $found['proposal']['origin'] && 'original' === $found['selected'] && 'y°' === $found['proposal']['spec']['objects'][4]['label'], 'an edit needs a new acceptance' );

			// Generated through the configured converter (fixture provider, intercepted transport).
			wp_set_current_user( $admin );
			\OhMyLMS\AI\Settings::update(
				array(
					'provider'               => 'openai',
					'model'                  => 'fixture',
					'key'                    => 'fixture-only',
					'exam_converter_enabled' => true,
				)
			);
			wp_set_current_user( $author );
			$fixture = new class() implements \OhMyLMS\AI\Providers\Provider {
				/**
				 * Nonroutable fixture request.
				 *
				 * @param array  $settings Settings.
				 * @param string $key      Key.
				 * @param array  $request  Prompt.
				 * @return array
				 */
				public function request( array $settings, $key, array $request ) {
					return array(
						'url'     => 'https://example.invalid/proposal-fixture',
						'headers' => array( 'authorization' => 'Bearer ' . $key ),
						'body'    => array(
							'model'            => $settings['model'],
							'instructions'     => $request['system'],
							'input'            => $request['user'],
							'messages'         => array( array( 'content' => array() ) ),
							'contents'         => array( array( 'parts' => array() ) ),
							'generationConfig' => array(),
						),
					);
				}
				/**
				 * Parse the intercepted reply.
				 *
				 * @param array $decoded Reply.
				 * @param int   $status  Status.
				 * @return array|WP_Error
				 */
				public function parse( array $decoded, $status ) {
					return 200 === $status ? array(
						'text'          => $decoded['text'],
						'input_tokens'  => 3,
						'output_tokens' => 5,
					) : new WP_Error( 'fixture_response', 'Fixture error.' );
				}
			};
			add_filter(
				'ohmylms_ai_providers',
				static function ( $providers ) use ( $fixture ) {
					return array_map(
						static function () use ( $fixture ) {
							return $fixture;
						},
						$providers
					);
				}
			);
			$view = ok_status( call_api( 'GET', 'exam-imports/jobs/' . $job ), 200, 'job read' );
			ok( true === $view['can_reconstruct'], 'the configured converter can propose' );
			$requests       = $view['usage']['requests'];
			$provider_reply = wp_json_encode( $spec );
			$view           = ok_status(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'generate',
					)
				),
				200,
				'generated proposal'
			);
			$found          = first_figure( $view );
			ok( 'generated' === $found['proposal']['origin'] && 'proposed' === $found['proposal']['status'] && $requests + 1 === $view['usage']['requests'], 'a generated proposal counts against the import request budget' );
			$edited         = $found['proposal']['spec'];
			$edited['objects'][1]['label'] = 'B′';
			$view           = ok_status(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'authored',
						'spec'     => $edited,
					)
				),
				200,
				'generated proposal edited'
			);
			ok( 'edited' === first_figure( $view )['proposal']['origin'], 'editing a generated proposal marks it edited' );
			$provider_reply = '{"version":1,"bounds":[0,1,1,0],"objects":[{"id":"s","type":"script","src":"https://evil.example"}]}';
			ok_error(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $view['revision'],
						'source'   => 'generate',
					)
				),
				422,
				'ohmylms_exam_import_invalid_proposal',
				'an unsafe provider reply is rejected'
			);
			$view = ok_status( call_api( 'GET', 'exam-imports/jobs/' . $job ), 200, 'job reread' );
			ok( 'edited' === first_figure( $view )['proposal']['origin'] && 'B′' === first_figure( $view )['proposal']['spec']['objects'][1]['label'], 'a failed generation keeps the previous proposal' );

			// Reject, then accept to accompany the original.
			$view  = ok_status( call_api( 'POST', $proposal . '/reject', array( 'revision' => $view['revision'] ) ), 200, 'proposal rejected' );
			$found = first_figure( $view );
			ok( 'rejected' === $found['proposal']['status'] && 'original' === $found['selected'] && ! isset( $found['spec'] ), 'rejecting keeps the original for learners' );
			$view  = ok_status(
				call_api(
					'POST',
					$proposal . '/accept',
					array(
						'revision' => $view['revision'],
						'display'  => 'accompany',
					)
				),
				200,
				'accepted beside the original'
			);
			$found = first_figure( $view );
			ok( 'both' === $found['selected'] && 'accompany' === $found['proposal']['display'] && isset( $found['spec'] ), 'accompany shows both' );

			// Replacing the crop drops a proposal made for the old one.
			$moved             = $found;
			$moved['asset_id'] = $second;
			$after             = ok_status(
				call_api(
					'PATCH',
					$path,
					array(
						'revision' => $view['revision'],
						'figures'  => array( $moved ),
					)
				),
				200,
				'crop replaced'
			);
			ok( ! isset( first_figure( $after )['proposal'] ) && 'original' === first_figure( $after )['selected'], 'a new crop starts on the original' );
			$found['approved'] = true;
			$after             = ok_status(
				call_api(
					'PATCH',
					$path,
					array(
						'revision' => $after['revision'],
						'figures'  => array( array_merge( $found, array( 'asset_id' => $crop ) ) ),
					)
				),
				200,
				'original crop restored'
			);
			ok( ! isset( first_figure( $after )['proposal'] ), 'a restored crop does not bring back the dropped proposal' );
			$view  = ok_status(
				call_api(
					'POST',
					$proposal,
					array(
						'revision' => $after['revision'],
						'source'   => 'authored',
						'spec'     => $spec,
					)
				),
				200,
				'proposed again'
			);
			$view  = ok_status(
				call_api(
					'POST',
					$proposal . '/accept',
					array(
						'revision' => $view['revision'],
						'display'  => 'accompany',
					)
				),
				200,
				'accepted again'
			);
			ok_error( call_api( 'POST', $path . '/accept', array( 'revision' => $view['revision'] ) ), 409, 'ohmylms_exam_import_figures_unreviewed', 'the figure must be approved after accepting its proposal' );
			$found             = first_figure( $view );
			$found['approved'] = true;
			$view              = ok_status(
				call_api(
					'PATCH',
					$path,
					array(
						'revision' => $view['revision'],
						'figures'  => array( $found ),
					)
				),
				200,
				'figure approved'
			);
			$view              = ok_status( call_api( 'POST', $path . '/accept', array( 'revision' => $view['revision'] ) ), 200, 'question accepted' );
			$done              = ok_status(
				call_api(
					'POST',
					'exam-imports/jobs/' . $job . '/apply',
					array(
						'revision'         => $view['revision'],
						'title'            => $tag . ' exam',
						'duration_minutes' => 20,
					)
				),
				200,
				'import applied'
			);
			ok_error(
				call_api(
					'POST',
					$proposal . '/reject',
					array( 'revision' => $done['revision'] )
				),
				409,
				'ohmylms_exam_import_not_reviewable',
				'committed proposals are read only'
			);

			// The committed question carries the accepted reconstruction, not the review state.
			$identity = VersionPublisher::identity( $done['question_ids'][0], false );
			$version  = (int) $identity['current_version_id'];
			$settings = json_decode( (string) VersionPublisher::version( $version )['settings'], true );
			$placed   = $settings['figures'][0];
			ok( 'both' === $placed['selected'] && ! isset( $placed['proposal'] ) && 'polygon' === $placed['spec']['objects'][3]['type'] && hash_equals( $crop, $placed['asset_id'] ), 'the question version keeps the accepted spec and the original, without proposal metadata' );
			ok( true === FigureContent::publishable( $settings ), 'the sealed figure passes the publication gate' );
			$precision = ini_get( 'serialize_precision' );
			ini_set( 'serialize_precision', '17' === $precision ? '-1' : '17' );
			$portable = FigureContent::publishable( $settings );
			ini_set( 'serialize_precision', $precision );
			ok( true === $portable, 'the attestation verifies under another serialize_precision (web server versus CLI)' );
			$html = FigureContent::html( VersionPublisher::snapshot( $version ) );
			ok( false !== strpos( $html, 'data-figure-spec="' ) && false !== strpos( $html, 'data-figure-display="accompany"' ) && false !== strpos( $html, 'ohmylms_figure=' . $crop ) && false === strpos( $html, '<script' ), 'delivery renders the declarative spec beside the protected original' );
			$tampered                         = $settings;
			$tampered['figures'][0]['spec']['objects'][0]['label'] = 'Z';
			ok( is_wp_error( FigureContent::publishable( $tampered ) ), 'a spec changed after review fails the publication gate' );
			$missing                          = $settings;
			unset( $missing['figures'][0]['spec'] );
			ok( is_wp_error( FigureContent::validate( $missing['figures'], array( 'p1' ) ) ), 'a reconstruction selection needs its spec' );

			throw new RuntimeException( 'proposals_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'proposals_rollback_success' !== $error->getMessage() ) {
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
echo ( empty( $exit_code ) ? 'OK' : 'FAILED' ) . ": $checks figure proposal checks; fixture writes rolled back.\n";
exit( isset( $exit_code ) ? $exit_code : 0 );
