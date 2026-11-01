<?php
/**
 * Exam import source viewer integration test: the renderer probe, page images, the PDF document route
 * and crops saved from the review screen (`exam-imports/renderer`, `exam-imports/jobs/{id}/source`,
 * `/pages/{page}`, `/document`, `/figures`).
 *
 * Runs against the Local site with real tables and the configured Poppler renderer
 * (OHMYLMS_PDFTOPPM_BINARY in wp-config.php). Fixture writes happen inside one transaction that is rolled
 * back; the synthetic PDFs, cached pages and crops written to private storage are removed in `finally`.
 * Mail and outgoing HTTP are blocked.
 *
 * Usage (set OHMYLMS_EXAM_SOURCE_TEST=1; PHP needs mysqli, mbstring, openssl, curl):
 *   php -n -d extension_dir=<php>/ext -d extension=mysqli -d extension=mbstring -d extension=openssl \
 *       -d extension=curl -d mysqli.default_port=10006 tests/php/exam-import-source-integration.php
 *
 * @package OhMyLMS
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, WordPress.PHP.DevelopmentFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.WP.AlternativeFunctions

use OhMyLMS\Assessment\Schema;
use OhMyLMS\ExamImport\Documents;
use OhMyLMS\ExamImport\Figures;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\ExamImport\PageRenderer;
use OhMyLMS\Rest\V1\ExamImportSourceController;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'OHMYLMS_EXAM_SOURCE_TEST' ) ) {
	exit( "Set OHMYLMS_EXAM_SOURCE_TEST=1 to run the exam import source tests against the local site.\n" );
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
if ( ! PageRenderer::status()['available'] ) {
	exit( "Usage: define OHMYLMS_IMPORT_PRIVATE_DIR and OHMYLMS_PDFTOPPM_BINARY (a working pdftoppm) in wp-config.php to run these tests.\n" );
}
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'exam_source_test', 'Network disabled.' );
	},
	PHP_INT_MAX
);
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );

$checks = 0;
$files  = array();
$tag    = 'exsrc' . strtolower( wp_generate_password( 6, false, false ) );

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
 * A synthetic PDF with a landscape, a rotated and a cropped page, stored privately.
 *
 * @param string $label Text on the pages.
 * @return array Document metadata as the job stores it.
 */
function make_pdf( $label ) {
	global $files;
	$stream  = "0 0 0 RG 2 w 40 20 120 60 re S\nBT /F1 10 Tf 42 25 Td ($label) Tj ET\n";
	$objects = array(
		'<< /Type /Catalog /Pages 2 0 R >>',
		'<< /Type /Pages /Kids [3 0 R 5 0 R 6 0 R] /Count 3 >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
		'<< /Length ' . strlen( $stream ) . ">>\nstream\n" . $stream . 'endstream',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Rotate 90 /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /CropBox [20 10 180 90] /Resources << /Font << /F1 7 0 R >> >> /Contents 4 0 R >>',
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
	);
	$pdf     = "%PDF-1.4\n";
	$offsets = array();
	foreach ( $objects as $index => $object ) {
		$offsets[] = strlen( $pdf );
		$pdf      .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
	}
	$xref = strlen( $pdf );
	$pdf .= "xref\n0 8\n0000000000 65535 f \n";
	foreach ( $offsets as $offset ) {
		$pdf .= sprintf( "%010d 00000 n \n", $offset );
	}
	$pdf  .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	$id    = wp_generate_uuid4();
	$path  = Documents::root() . DIRECTORY_SEPARATOR . $id . '.pdf';
	$files = array_merge( $files, array( $path ) );
	ok( strlen( $pdf ) === file_put_contents( $path, $pdf ), 'synthetic PDF written to private storage' );
	return array(
		'id'     => $id,
		'name'   => $label . '.pdf',
		'bytes'  => strlen( $pdf ),
		'sha256' => hash( 'sha256', $pdf ),
	);
}

/**
 * A PDF import waiting for review over a real private source.
 *
 * @param int        $owner       Owner.
 * @param array|null $document    Paper, or null for a rows job.
 * @param array|null $mark_scheme Mark scheme.
 * @param string     $state       Job state.
 * @return int Job ID.
 */
function make_job( $owner, $document, $mark_scheme = null, $state = 'ready_for_review' ) {
	global $tag;
	$inventory = array(
		'title'            => $tag,
		'duration_minutes' => 30,
		'total_marks'      => 2,
		'page_count'       => 3,
		'question_numbers' => array( '1' ),
	);
	$id        = wp_insert_post(
		array(
			'post_type'   => Jobs::TYPE,
			'post_status' => 'private',
			'post_author' => $owner,
			'post_title'  => $tag . '.pdf',
		),
		true
	);
	ok( ! is_wp_error( $id ), 'synthetic job created' );
	update_post_meta(
		$id,
		Jobs::META,
		wp_slash(
			array(
				'document'        => $document ? $document : array(),
				'mark_scheme'     => $mark_scheme,
				'target'          => 'exam',
				'skills'          => '',
				'state'           => $state,
				'attempts'        => 1,
				'error'           => '',
				'updated_at'      => gmdate( 'c' ),
				'inventory'       => $inventory,
				'draft_inventory' => $inventory,
				'draft'           => array(
					array(
						'number'           => '1',
						'question'         => 'Measure the rectangle.',
						'page'             => 1,
						'requires_source'  => true,
						'notes'            => '',
						'skills'           => '',
						'parts'            => array(
							array(
								'id'     => 'p1',
								'label'  => '(a)',
								'prompt' => 'Width in cm.',
								'kind'   => 'numerical',
								'marks'  => 2,
								'answer' => 6,
							),
						),
						'reviewed'         => false,
						'figures'          => array(),
						'figures_complete' => false,
					),
				),
				'draft_revision'  => 1,
			)
		)
	);
	return (int) $id;
}

/**
 * Cached page images of a document.
 *
 * @param array $document Document metadata.
 * @return string[]
 */
function cached_pages( array $document ) {
	return glob( Documents::root() . DIRECTORY_SEPARATOR . 'page-' . $document['id'] . '-*.png' );
}

/**
 * Send a dispatched file route through the streaming filter and capture the bytes.
 *
 * @param WP_REST_Response $response Dispatched response.
 * @param string           $path     Path below /ohmylms/v1/.
 * @return string Streamed bytes.
 */
function streamed( $response, $path ) {
	ob_start();
	$served = ExamImportSourceController::serve_file( false, $response, new WP_REST_Request( 'GET', '/ohmylms/v1/' . $path ) );
	$bytes  = ob_get_clean();
	ok( true === $served, "$path is streamed" );
	return $bytes;
}

$paper  = null;
$scheme = null;
try {
	Transaction::run(
		static function () use ( $tag, &$paper, &$scheme ) {
			global $files;
			$routes = rest_get_server()->get_routes();
			foreach ( array( '/ohmylms/v1/exam-imports/renderer', '/ohmylms/v1/exam-imports/jobs/(?P<id>[\d]+)/source', '/ohmylms/v1/exam-imports/jobs/(?P<id>[\d]+)/document', '/ohmylms/v1/exam-imports/jobs/(?P<id>[\d]+)/pages/(?P<page>[\d]+)', '/ohmylms/v1/exam-import-renderer' ) as $route ) {
				ok( isset( $routes[ $route ] ), "route $route registered" );
			}

			$admin       = make_user( 'admin', 'administrator' );
			$author      = make_user( 'author', 'author' );
			$other       = make_user( 'other', 'author' );
			$contributor = make_user( 'contrib', 'contributor' );
			$subscriber  = make_user( 'sub', 'subscriber' );

			$paper  = make_pdf( 'PAPER' );
			$scheme = make_pdf( 'SCHEME' );
			$job    = make_job( $author, $paper, $scheme );
			$bare   = make_job( $author, make_pdf( 'BARE' ) );
			$rows   = make_job( $author, null );
			$queued = make_job( $author, make_pdf( 'QUEUED' ), null, 'queued' );
			$theirs = make_job( $other, make_pdf( 'THEIRS' ) );

			// Renderer probe.
			wp_set_current_user( 0 );
			ok_error( call_api( 'GET', 'exam-imports/renderer' ), 401, 'ohmylms_exam_import_cannot_view', 'guest renderer probe' );
			wp_set_current_user( $subscriber );
			ok_error( call_api( 'GET', 'exam-imports/renderer' ), 403, 'ohmylms_exam_import_cannot_view', 'subscriber renderer probe' );
			wp_set_current_user( $contributor );
			ok_error( call_api( 'GET', 'exam-imports/renderer' ), 403, 'ohmylms_exam_import_cannot_view', 'contributor (no upload_files) renderer probe' );
			wp_set_current_user( $author );
			$probe    = call_api( 'GET', 'exam-imports/renderer' );
			$renderer = ok_status( $probe, 200, 'author renderer probe' );
			ok( true === $renderer['available'] && 'poppler' === $renderer['engine'] && '' === $renderer['reason'] && '' === $renderer['poppler_reason'], 'Poppler is available and preferred' );
			ok( preg_match( '/^\d+\.\d+(?:\.\d+)?$/', $renderer['version'] ), 'Poppler version reported' );
			ok( true === $renderer['crops'] && 'poppler-crop-1' === $renderer['contract'] && 2400 === $renderer['max_side'] && 20 === $renderer['timeout_seconds'], 'crop capability and limits reported' );
			ok( 'rotated-cropbox-normalized-top-left' === $renderer['coordinate_space'] && is_bool( $renderer['imagick'] ), 'coordinate space and Imagick flag reported' );
			$encoded = wp_json_encode( $renderer );
			ok( false === stripos( $encoded, 'pdftoppm.exe' ) && false === stripos( $encoded, wp_normalize_path( Documents::root() ) ) && false === stripos( $encoded, wp_json_encode( Documents::root() ) ), 'probe hides paths' );
			ok( 'private, no-store' === $probe->get_headers()['Cache-Control'], 'probe is not cached' );
			ok( 200 === call_api( 'GET', 'exam-import-renderer' )->get_status(), 'legacy renderer probe unchanged' );

			// Source descriptor.
			wp_set_current_user( 0 );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/source" ), 401, 'ohmylms_exam_import_forbidden', 'guest source' );
			wp_set_current_user( $other );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/source" ), 403, 'ohmylms_exam_import_forbidden', 'other author source' );
			wp_set_current_user( $admin );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/source" ), 403, 'ohmylms_exam_import_forbidden', 'administrator who is not the owner' );
			ok_error( call_api( 'GET', 'exam-imports/jobs/999999999/source' ), 404, 'ohmylms_exam_import_not_found', 'unknown job before capability' );
			wp_set_current_user( $author );
			$source = ok_status( call_api( 'GET', "exam-imports/jobs/$job/source" ), 200, 'owner source' );
			ok( 3 === $source['page_count'] && true === $source['renderer']['available'], 'source carries page count and renderer' );
			ok( false !== strpos( $source['page_url'], "/exam-imports/jobs/$job/pages/{page}" ) && false !== strpos( $source['page_url'], '_wpnonce=' ), 'page address template is authenticated' );
			ok( false !== strpos( $source['document'], "/exam-imports/jobs/$job/document" ) && false !== strpos( $source['mark_scheme'], 'part=mark_scheme' ), 'document addresses' );
			ok( 0 === strpos( $source['page_url'], rest_url() ) || 0 === strpos( $source['page_url'], home_url() ), 'addresses are REST routes, not files' );
			$encoded = wp_json_encode( $source );
			ok( false === strpos( $encoded, $paper['id'] ) && false === strpos( $encoded, $paper['sha256'] ) && false === strpos( $encoded, $scheme['id'] ), 'source hides document handles and hashes' );
			ok( '' === ok_status( call_api( 'GET', "exam-imports/jobs/$bare/source" ), 200, 'source without mark scheme' )['mark_scheme'], 'no mark scheme address without one' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$rows/source" ), 404, 'ohmylms_exam_import_no_source', 'rows job has no source' );

			// PDF document.
			$document = call_api( 'GET', "exam-imports/jobs/$job/document" );
			ok( $paper['bytes'] === ok_status( $document, 200, 'owner document' )['bytes'], 'document size' );
			$headers = $document->get_headers();
			ok( 'application/pdf' === $headers['Content-Type'] && 'nosniff' === $headers['X-Content-Type-Options'] && 'SAMEORIGIN' === $headers['X-Frame-Options'] && 'private, no-store' === $headers['Cache-Control'], 'document headers' );
			ok( hash( 'sha256', streamed( $document, "exam-imports/jobs/$job/document" ) ) === $paper['sha256'], 'streamed document is the paper' );
			$marking = call_api( 'GET', "exam-imports/jobs/$job/document", array( 'part' => 'mark_scheme' ) );
			ok( $scheme['bytes'] === ok_status( $marking, 200, 'mark scheme document' )['bytes'], 'mark scheme size' );
			ok( hash( 'sha256', streamed( $marking, "exam-imports/jobs/$job/document" ) ) === $scheme['sha256'], 'streamed mark scheme' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$bare/document", array( 'part' => 'mark_scheme' ) ), 404, 'ohmylms_exam_import_no_source', 'no mark scheme' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/document", array( 'part' => 'answers' ) ), 400, 'rest_invalid_param', 'unknown document part' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$queued/document" ), 409, 'ohmylms_exam_import_source_unchecked', 'unchecked source' );
			wp_set_current_user( $other );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/document" ), 403, 'ohmylms_exam_import_forbidden', 'other author document' );
			wp_set_current_user( $contributor );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/document" ), 403, 'ohmylms_exam_import_forbidden', 'contributor document' );

			// Page images.
			wp_set_current_user( 0 );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/pages/1" ), 401, 'ohmylms_exam_import_forbidden', 'guest page' );
			wp_set_current_user( $other );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/pages/1" ), 403, 'ohmylms_exam_import_forbidden', 'other author page' );
			wp_set_current_user( $admin );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/pages/1" ), 403, 'ohmylms_exam_import_forbidden', 'non-owner administrator page' );
			ok( ! cached_pages( $paper ), 'denied requests render nothing' );
			wp_set_current_user( $author );
			$page = call_api( 'GET', "exam-imports/jobs/$job/pages/1" );
			$view = ok_status( $page, 200, 'owner page 1' );
			ok( 2400 === $view['width'] && 1200 === $view['height'], 'page 1 renders at the crop raster size' );
			$headers = $page->get_headers();
			ok( 'image/png' === $headers['Content-Type'] && 'nosniff' === $headers['X-Content-Type-Options'] && 'same-origin' === $headers['Cross-Origin-Resource-Policy'] && 'private, no-store' === $headers['Cache-Control'], 'page headers' );
			$bytes = streamed( $page, "exam-imports/jobs/$job/pages/1" );
			$size  = getimagesizefromstring( $bytes );
			ok( $size && 'image/png' === $size['mime'] && 2400 === $size[0] && strlen( $bytes ) === $view['bytes'], 'streamed bytes are the PNG page' );
			$cached = cached_pages( $paper );
			$files  = array_merge( $files, $cached );
			ok( 1 === count( $cached ) && 0 === strpos( wp_normalize_path( $cached[0] ), wp_normalize_path( Documents::root() ) ), 'one cached page, in private storage' );
			$rotated = ok_status( call_api( 'GET', "exam-imports/jobs/$job/pages/2" ), 200, 'rotated page 2' );
			ok( 1200 === $rotated['width'] && 2400 === $rotated['height'], 'rotation follows the visible page' );
			$cropped = ok_status( call_api( 'GET', "exam-imports/jobs/$job/pages/3" ), 200, 'cropped page 3' );
			ok( 2400 === $cropped['width'] && 1200 === $cropped['height'], 'CropBox page renders the visible area' );
			$files = array_merge( $files, cached_pages( $paper ) );
			ok( 3 === count( cached_pages( $paper ) ), 'each page cached once' );
			clearstatcache();
			$before = filemtime( $cached[0] );
			ok( $view === ok_status( call_api( 'GET', "exam-imports/jobs/$job/pages/1" ), 200, 'page 1 again' ), 'cached page answers the same' );
			ok( filemtime( $cached[0] ) === $before && 3 === count( cached_pages( $paper ) ), 'second request reads the cache' );
			file_put_contents( $cached[0], 'not a png' );
			ok( 2400 === ok_status( call_api( 'GET', "exam-imports/jobs/$job/pages/1" ), 200, 'damaged cache' )['width'], 'a damaged cached page is rendered again' );
			ok( false === strpos( wp_json_encode( $view ), $paper['id'] ), 'page response hides the document handle' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/pages/4" ), 404, 'ohmylms_exam_import_page_not_found', 'page after the last' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/pages/0" ), 400, 'rest_invalid_param', 'page zero' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$job/pages/501" ), 400, 'rest_invalid_param', 'page over the limit' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$rows/pages/1" ), 404, 'ohmylms_exam_import_no_source', 'rows job page' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$queued/pages/1" ), 409, 'ohmylms_exam_import_source_unchecked', 'unchecked source page' );
			$changed                       = get_post_meta( $bare, Jobs::META, true );
			$changed['document']['sha256'] = str_repeat( 'a', 64 );
			update_post_meta( $bare, Jobs::META, wp_slash( $changed ) );
			ok_error( call_api( 'GET', "exam-imports/jobs/$bare/pages/1" ), 409, 'ohmylms_exam_import_source_unavailable', 'changed source page' );
			ok_error( call_api( 'GET', "exam-imports/jobs/$bare/document" ), 409, 'ohmylms_exam_import_source_unavailable', 'changed source document' );
			ok( ! cached_pages( $changed['document'] ), 'nothing cached for a changed source' );

			// Crops drawn on the page: the existing figures route, then the row edit.
			wp_set_current_user( 0 );
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.2, 0.8, 0.8 ),
					)
				),
				401,
				'ohmylms_exam_import_forbidden',
				'guest crop'
			);
			wp_set_current_user( $other );
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.2, 0.8, 0.8 ),
					)
				),
				403,
				'ohmylms_exam_import_forbidden',
				'other author crop'
			);
			wp_set_current_user( $contributor );
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.2, 0.8, 0.8 ),
					)
				),
				403,
				'ohmylms_exam_import_forbidden',
				'contributor crop'
			);
			wp_set_current_user( $author );
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.2, 1.2, 0.8 ),
					)
				),
				400,
				'rest_invalid_param',
				'bound over 1'
			);
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.2, 0.8 ),
					)
				),
				400,
				'rest_invalid_param',
				'three bounds'
			);
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.8, 0.2, 0.2, 0.8 ),
					)
				),
				400,
				'exam_crop_bounds',
				'left after right'
			);
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.5, 0.8, 0.5 ),
					)
				),
				400,
				'exam_crop_bounds',
				'empty height'
			);
			$tiny = call_api(
				'POST',
				"exam-imports/jobs/$job/figures",
				array(
					'page'   => 1,
					'bounds' => array( 0.2, 0.2, 0.204, 0.8 ),
				)
			);
			ok_error( $tiny, 400, 'exam_crop_bounds', 'a crop narrower than half a percent of the page' );
			ok( 'too_small' === $tiny->get_data()['data']['reason'], 'the too-small crop says why' );
			$past = call_api(
				'POST',
				"exam-imports/jobs/$job/figures",
				array(
					'page'   => 4,
					'bounds' => array( 0.2, 0.2, 0.8, 0.8 ),
				)
			);
			ok_error( $past, 400, 'exam_crop_bounds', 'a crop on a page after the last' );
			ok( 'page' === $past->get_data()['data']['reason'] && false !== strpos( $past->get_data()['message'], '3 pages' ), 'the page error names the page count' );
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 0,
						'bounds' => array( 0.2, 0.2, 0.8, 0.8 ),
					)
				),
				400,
				'rest_invalid_param',
				'crop page zero'
			);
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.2, 0.8, 0.8 ),
						'asset'  => 'x',
					)
				),
				400,
				'ohmylms_exam_import_unknown_field',
				'unknown crop field'
			);
			ok_error(
				call_api(
					'POST',
					"exam-imports/jobs/$queued/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.2, 0.2, 0.8, 0.8 ),
					)
				),
				409,
				'exam_crop_source',
				'crop before the source is checked'
			);
			$bounds = array( 0.25, 0.125, 0.75, 0.875 );
			$crop   = ok_status(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => $bounds,
					)
				),
				201,
				'owner crop queued'
			);
			ok( wp_is_uuid( $crop['id'] ) && 'queued' === $crop['state'] && 1 === $crop['page'] && $bounds === $crop['bounds'] && '' === $crop['url'], 'queued crop view' );
			$again = ok_status(
				call_api(
					'POST',
					"exam-imports/jobs/$job/figures",
					array(
						'page'   => 1,
						'bounds' => $bounds,
					)
				),
				201,
				'same crop again'
			);
			ok( $crop['id'] === $again['id'], 'repeating a crop reuses its asset' );
			$asset = Figures::asset( $crop['id'] );
			Figures::process(
				(int) get_posts(
					array(
						'post_type'   => Figures::TYPE,
						'post_status' => 'private',
						'name'        => $crop['id'],
						'fields'      => 'ids',
					)
				)[0]
			);
			$files[] = Documents::root() . DIRECTORY_SEPARATOR . 'figure-' . $crop['id'] . '.png';
			$listed  = ok_status( call_api( 'GET', "exam-imports/jobs/$job/figures" ), 200, 'crops listed' );
			$ready   = current( wp_list_filter( $listed, array( 'id' => $crop['id'] ) ) );
			ok( $asset && $ready && 'ready' === $ready['state'] && 1200 === $ready['width'] && 900 === $ready['height'], 'crop rendered from the drawn bounds on the page raster' );
			ok( false !== strpos( $ready['url'], "/exam-imports/jobs/$job/figures/" . $crop['id'] ), 'crop served through the authenticated figure route' );

			$revision = (int) get_post_meta( $job, Jobs::META, true )['draft_revision'];
			$figure   = array(
				'id'           => 'f1',
				'page'         => 1,
				'bounds'       => $bounds,
				'part_ids'     => array(),
				'asset_id'     => $crop['id'],
				'alt'          => 'A rectangle',
				'not_to_scale' => false,
				'approved'     => false,
			);
			$saved    = ok_status(
				call_api(
					'PATCH',
					"exam-imports/jobs/$job/rows/0",
					array(
						'revision' => $revision,
						'figures'  => array( $figure ),
					)
				),
				200,
				'crop saved on the row'
			);
			$placed   = $saved['rows'][0]['figures'][0];
			ok( $crop['id'] === $placed['asset_id'] && $bounds === array_map( 'floatval', $placed['bounds'] ) && 'original' === $placed['selected'], 'row keeps the drawn crop and starts on the original' );

			wp_set_current_user( $other );
			$foreign = ok_status(
				call_api(
					'POST',
					"exam-imports/jobs/$theirs/figures",
					array(
						'page'   => 1,
						'bounds' => array( 0.1, 0.1, 0.5, 0.5 ),
					)
				),
				201,
				'other author crops their own job'
			);
			wp_set_current_user( $author );
			ok_error(
				call_api(
					'PATCH',
					"exam-imports/jobs/$job/rows/0",
					array(
						'revision' => $saved['revision'],
						'figures'  => array( array_merge( $figure, array( 'asset_id' => $foreign['id'] ) ) ),
					)
				),
				403,
				'ohmylms_exam_import_figure_forbidden',
				'a crop of another job cannot be placed'
			);
			ok_error(
				call_api(
					'PATCH',
					"exam-imports/jobs/$job/rows/0",
					array(
						'revision' => $revision,
						'figures'  => array( $figure ),
					)
				),
				409,
				'ohmylms_exam_import_conflict',
				'stale crop save'
			);

			// A figure keeps the page and bounds of its crop; an accepted crop lies inside its page.
			foreach ( array(
				'other bounds' => array( 'bounds' => array( 0.25, 0.125, 0.75, 0.5 ) ),
				'other page'   => array( 'page' => 2 ),
			) as $label => $change ) {
				$moved = call_api(
					'PATCH',
					"exam-imports/jobs/$job/rows/0",
					array(
						'revision' => $saved['revision'],
						'figures'  => array( array_merge( $figure, $change ) ),
					)
				);
				ok_error( $moved, 400, 'ohmylms_exam_import_figure_bounds', "a figure with $label than its crop" );
				$data = $moved->get_data()['data'];
				ok( 'mismatch' === $data['reason'] && 0 === $data['index'] && 'f1' === $data['figure'], "the $label mismatch names the row and figure" );
			}
			ok( $saved['revision'] === (int) get_post_meta( $job, Jobs::META, true )['draft_revision'], 'refused figure saves change nothing' );
			$approved = ok_status(
				call_api(
					'PATCH',
					"exam-imports/jobs/$job/rows/0",
					array(
						'revision' => $saved['revision'],
						'figures'  => array( array_merge( $figure, array( 'approved' => true ) ) ),
					)
				),
				200,
				'approved figure saved'
			);
			$asset_post = (int) get_posts(
				array(
					'post_type'   => Figures::TYPE,
					'post_status' => 'private',
					'name'        => $crop['id'],
					'fields'      => 'ids',
				)
			)[0];
			$rendered   = get_post_meta( $asset_post, Figures::META, true );
			ok( PageRenderer::inside( $rendered['pixel_bounds'], $rendered['page_width'], $rendered['page_height'] ) && array( 600, 150, 1800, 1050 ) === $rendered['pixel_bounds'], 'the rendered crop lies inside its page raster: ' . wp_json_encode( $rendered['pixel_bounds'] ) );
			$outside                    = $rendered;
			$outside['pixel_bounds'][2] = $rendered['page_width'] + 10;
			update_post_meta( $asset_post, Figures::META, wp_slash( $outside ) );
			$refused = call_api( 'POST', "exam-imports/jobs/$job/rows/0/accept", array( 'revision' => $approved['revision'] ) );
			ok_error( $refused, 400, 'ohmylms_exam_import_figure_bounds', 'accepting a crop that leaves its page' );
			ok( 'outside' === $refused->get_data()['data']['reason'], 'the outside crop says why' );
			$stored = get_post_meta( $job, Jobs::META, true );
			$narrow = array( 0.25, 0.125, 0.252, 0.875 );
			$draft  = $stored;

			$draft['draft'][0]['figures'][0]['bounds'] = $narrow;
			update_post_meta( $asset_post, Figures::META, wp_slash( array_merge( $rendered, array( 'bounds' => $narrow ) ) ) );
			update_post_meta( $job, Jobs::META, wp_slash( $draft ) );
			$refused = call_api( 'POST', "exam-imports/jobs/$job/rows/0/accept", array( 'revision' => $approved['revision'] ) );
			ok_error( $refused, 400, 'ohmylms_exam_import_figure_bounds', 'accepting a degenerate crop' );
			ok( 'too_small' === $refused->get_data()['data']['reason'], 'the degenerate crop says why' );
			update_post_meta( $asset_post, Figures::META, wp_slash( $rendered ) );
			update_post_meta( $job, Jobs::META, wp_slash( $stored ) );
			$accepted = ok_status( call_api( 'POST', "exam-imports/jobs/$job/rows/0/accept", array( 'revision' => $approved['revision'] ) ), 200, 'the matching crop inside its page is accepted' );
			ok( 'accepted' === $accepted['rows'][0]['status'], 'row accepted' );

			// Imagick (Ghostscript) as the fallback renderer, when this PHP has it.
			$only_imagick = static function () {
				return array( 'imagick' );
			};
			add_filter( 'ohmylms_exam_import_renderers', $only_imagick );
			$fallback = PageRenderer::status();
			if ( $fallback['available'] ) {
				$probe = ok_status( call_api( 'GET', 'exam-imports/renderer' ), 200, 'probe with the Imagick fallback' );
				ok( 'imagick' === $probe['engine'] && 'imagick-crop-1' === $probe['contract'] && '' === $probe['reason'] && 'not_configured' === $probe['poppler_reason'] && true === $probe['imagick'] && preg_match( '/^\d/', $probe['version'] ), 'Imagick is the engine and says why Poppler is not: ' . wp_json_encode( $probe ) );
				$imagick_page = ok_status( call_api( 'GET', "exam-imports/jobs/$job/pages/1" ), 200, 'Imagick page 1' );
				ok( 2400 === $imagick_page['width'] && 1200 === $imagick_page['height'], 'Imagick landscape raster equals Poppler\'s' );
				$imagick_rotated = ok_status( call_api( 'GET', "exam-imports/jobs/$job/pages/2" ), 200, 'Imagick rotated page' );
				ok( 1200 === $imagick_rotated['width'] && 2400 === $imagick_rotated['height'], 'Imagick follows the page rotation' );
				$imagick_cropped = ok_status( call_api( 'GET', "exam-imports/jobs/$job/pages/3" ), 200, 'Imagick CropBox page' );
				ok( 2400 === $imagick_cropped['width'] && 1200 === $imagick_cropped['height'], 'Imagick renders the CropBox' );
				$files = array_merge( $files, cached_pages( $paper ) );
				ok( 6 === count( cached_pages( $paper ) ), 'Imagick pages are cached under their own contract, beside Poppler\'s' );
				$imagick_crop = ok_status(
					call_api(
						'POST',
						"exam-imports/jobs/$job/figures",
						array(
							'page'   => 1,
							'bounds' => $bounds,
						)
					),
					201,
					'crop queued for the Imagick renderer'
				);
				ok( $crop['id'] !== $imagick_crop['id'], 'the same bounds under another renderer contract are a new crop' );
				Figures::process(
					(int) get_posts(
						array(
							'post_type'   => Figures::TYPE,
							'post_status' => 'private',
							'name'        => $imagick_crop['id'],
							'fields'      => 'ids',
						)
					)[0]
				);
				$files[]       = Documents::root() . DIRECTORY_SEPARATOR . 'figure-' . $imagick_crop['id'] . '.png';
				$imagick_asset = Figures::asset( $imagick_crop['id'] );
				ok( 'ready' === $imagick_asset['state'] && 'imagick-crop-1' === $imagick_asset['engine'] && 1200 === $imagick_asset['width'] && 900 === $imagick_asset['height'] && array( 600, 150, 1800, 1050 ) === $imagick_asset['pixel_bounds'], 'Imagick cuts the same pixels as Poppler: ' . wp_json_encode( array_intersect_key( $imagick_asset, array_flip( array( 'state', 'error', 'engine', 'width', 'height', 'pixel_bounds' ) ) ) ) );
				ok( isset( $imagick_asset['width_mm'] ) && $imagick_asset['width_mm'] > 0, 'the Imagick crop keeps its physical size' );
			} else {
				ok( in_array( $fallback['reason'], array( 'not_configured', 'storage' ), true ) && false === $fallback['available'], 'without Imagick reading PDF the fallback reports itself unavailable (' . ( $fallback['imagick'] ? 'Imagick lists PDF but cannot read it' : 'no Imagick' ) . ')' );
				echo "Note: this PHP has no Imagick reading PDF; the fallback renderer was not exercised.\n";
			}
			remove_filter( 'ohmylms_exam_import_renderers', $only_imagick );
			ok( 'poppler' === PageRenderer::status()['engine'], 'Poppler stays preferred when configured' );

			// Deleting the job removes its cached pages.
			wp_delete_post( $job, true );
			ok( ! cached_pages( $paper ), 'cached pages removed with the job' );

			throw new RuntimeException( 'exam_source_rollback_success' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'exam_source_rollback_success' !== $error->getMessage() ) {
		echo $error->getMessage() . "\n";
		$exit_code = 1;
	}
} finally {
	wp_set_current_user( 0 );
	foreach ( array( $paper, $scheme ) as $document ) {
		if ( $document ) {
			$files = array_merge( $files, cached_pages( $document ) );
		}
	}
	foreach ( array_unique( $files ) as $file ) {
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
}

ok( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login=%s", $tag . '_author' ) ), 'fixture transaction rolled back' );
echo ( empty( $exit_code ) ? 'OK' : 'FAILED' ) . ": $checks exam import source checks; fixture writes rolled back.\n";
exit( isset( $exit_code ) ? $exit_code : 0 );
