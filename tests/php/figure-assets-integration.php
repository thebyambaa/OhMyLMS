<?php
/**
 * Transactional real-Poppler crop rehearsal against this explicit Local checkout.
 *
 * Run: OHMYLMS_TEST_PDFTOPPM=/absolute/pdftoppm php this-file.php --local
 * Requires Local's configured PHP extensions and MySQL port. No external AI calls.
 *
 * @package OhMyLMS
 */

use OhMyLMS\ExamImport\Documents;
use OhMyLMS\ExamImport\Figures;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\ExamImport\PageRenderer;
use OhMyLMS\ExamImport\FigureContent;
use OhMyLMS\ExamImport\FigureSpec;
use OhMyLMS\QuestionBank\BulkImport;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\QuestionBank\ReviewQueue;
use OhMyLMS\Assessment\AttemptItems;
use OhMyLMS\Quiz\Submission;
use OhMyLMS\Utility\Transaction;

if ( 'cli' !== PHP_SAPI || ! in_array( '--local', $argv, true ) || ! getenv( 'OHMYLMS_TEST_PDFTOPPM' ) ) {
	exit( "Requires --local and OHMYLMS_TEST_PDFTOPPM.\n" );
}
define( 'DISABLE_WP_CRON', true );
define( 'OHMYLMS_PDFTOPPM_BINARY', getenv( 'OHMYLMS_TEST_PDFTOPPM' ) );
$_SERVER['HTTP_HOST']   = 'xyz.local';
$_SERVER['REQUEST_URI'] = '/';
require dirname( __DIR__, 5 ) . '/wp-load.php';
if ( 'xyz' !== basename( dirname( ABSPATH, 2 ) ) || 'local' !== wp_get_environment_type() || wp_using_ext_object_cache() ) {
	throw new RuntimeException( 'Requires this Local checkout and request-local object cache.' );
}
foreach ( $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A ) as $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture rollback requires transactional tables.
	if ( 0 === strpos( $table['Name'], $wpdb->prefix ) && 'InnoDB' !== $table['Engine'] ) {
		throw new RuntimeException( 'Requires InnoDB tables.' );
	}
}
add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter(
	'pre_http_request',
	static function () {
		return new WP_Error( 'fixture', 'Network disabled.' );
	},
	PHP_INT_MAX
);
add_filter( 'action_scheduler_allow_async_request_runner', '__return_false' );
foreach ( $GLOBALS['wp_filter']['shutdown']->callbacks ?? array() as $priority => $callbacks ) {
	foreach ( $callbacks as $callback ) {
		$function = $callback['function'];
		if ( is_array( $function ) && is_object( $function[0] ) && $function[0] instanceof \OhMyLMS\Commerce\SessionHandler ) {
			remove_action( 'shutdown', $function, $priority );
		}
	}
}
$checks = 0;
$check  = static function ( $condition, $message ) use ( &$checks ) {
	if ( ! $condition || is_wp_error( $condition ) ) {
		throw new RuntimeException( esc_html( $message . ( is_wp_error( $condition ) ? ': ' . $condition->get_error_code() : '' ) ) );
	}
	++$checks;
};
$root   = Documents::root();
$check( $root, 'Configured private fixture storage' );
$source_id   = wp_generate_uuid4();
$source_path = $root . '/' . $source_id . '.pdf';
$cleanup     = array( $source_path );
$fixture_tag = 'figure-rehearsal-' . wp_generate_uuid4();

// Synthetic geometry with landscape, rotated, and cropped page variants.
$stream  = "0 0 0 RG 2 w 40 20 120 60 re S\nBT /F1 10 Tf 42 25 Td (NOT TO SCALE) Tj ET\n";
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
$offsets = array( 0 );
foreach ( $objects as $index => $object ) {
	$offsets[] = strlen( $pdf );
	$pdf      .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
}
$xref = strlen( $pdf );
$pdf .= "xref\n0 8\n0000000000 65535 f \n";
foreach ( array_slice( $offsets, 1 ) as $offset ) {
	$pdf .= sprintf( "%010d 00000 n \n", $offset );
}
$pdf .= "trailer\n<< /Size 8 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
$check( strlen( $pdf ) === file_put_contents( $source_path, $pdf ), 'Write synthetic PDF' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Unique private test fixture, removed in finally.
$document = array(
	'id'     => $source_id,
	'sha256' => hash( 'sha256', $pdf ),
	'bytes'  => strlen( $pdf ),
	'name'   => 'Synthetic geometry.pdf',
);

try {
	Transaction::run(
		static function () use ( $check, $fixture_tag, $document, $root, &$cleanup ) {
			$owner = wp_insert_user(
				array(
					'user_login' => $fixture_tag,
					'user_pass'  => wp_generate_password(),
					'role'       => 'administrator',
				)
			);
			$check( $owner, 'Fixture author created' );
			wp_set_current_user( $owner );
			$capability = PageRenderer::capability();
			$check( $capability, 'Configured Poppler executes under WordPress PHP' );
			$check( false === strpos( wp_json_encode( $capability ), $root ) && ! isset( $capability['path'] ), 'Capability hides paths' );
			foreach ( array( array( 0, 0, 0, 1 ), array( 0, 0, 1.1, 1 ), array( 0, NAN, 1, 1 ), array( 0, 0, '1', 1 ), array( 0, 0, 1 ), array( 'x' => 0 ) ) as $bad ) {
				$check( is_wp_error( PageRenderer::bounds( $bad ) ), 'Reject malformed bounds' );
			}
			$check( is_wp_error( PageRenderer::crop( $document, 0, array( 0, 0, 1, 1 ) ) ), 'Reject page zero' );
			$check( is_wp_error( PageRenderer::crop( $document, 4, array( 0, 0, 1, 1 ) ) ), 'Reject missing page' );
			$wrong           = $document;
			$wrong['sha256'] = str_repeat( '0', 64 );
			$check( is_wp_error( PageRenderer::crop( $wrong, 1, array( 0, 0, 1, 1 ) ) ), 'Reject changed source' );
			foreach ( array(
				1 => array( 2400, 1200 ),
				2 => array( 1200, 2400 ),
				3 => array( 2400, 1200 ),
			) as $page => $expected ) {
				$render = PageRenderer::crop( $document, $page, array( 0.25, 0.25, 0.75, 0.75 ) );
				$check( $render, 'Render crop for page variant' );
				$check( array( $render['page_width'], $render['page_height'] ) === $expected && $render['width'] === $expected[0] / 2 && $render['height'] === $expected[1] / 2, 'Crop coordinates follow rotated visible page' );
				$check( 'image/png' === getimagesizefromstring( $render['bytes'] )['mime'], 'Actual PNG output' );
				$physical_widths = array(
					1 => 35.278,
					2 => 17.639,
					3 => 28.222,
				);
				$check( isset( $render['width_mm'] ) && abs( $render['width_mm'] - $physical_widths[ $page ] ) < 0.02, 'Original physical scale preserved for print' );
			}
			$full   = PageRenderer::crop( $document, 1, array( 0, 0, 1, 1 ) );
			$png    = $full['bytes'];
			$header = unpack( 'Nwidth/Nheight/Cbits/Ccolor', substr( $png, 16, 10 ) );
			$check( 8 === $header['bits'] && 2 === $header['color'], 'Raster fixture uses RGB PNG' );
			$offset     = 8;
			$image_data = '';
			$png_length = strlen( $png );
			while ( $offset + 12 <= $png_length ) {
				$length = unpack( 'Nsize', substr( $png, $offset, 4 ) )['size'];
				if ( 'IDAT' === substr( $png, $offset + 4, 4 ) ) {
					$image_data .= substr( $png, $offset + 8, $length );
				}
				$offset += $length + 12;
			}
			$image_commands = "q 200 0 0 100 0 0 cm /Im0 Do Q\n";
			$image_objects  = array(
				'<< /Type /Catalog /Pages 2 0 R >>',
				'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>',
				'<< /Type /XObject /Subtype /Image /Width ' . $header['width'] . ' /Height ' . $header['height'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns ' . $header['width'] . ' >> /Length ' . strlen( $image_data ) . ">>\nstream\n" . $image_data . "\nendstream",
				'<< /Length ' . strlen( $image_commands ) . ">>\nstream\n" . $image_commands . 'endstream',
			);
			$scanned        = "%PDF-1.4\n";
			$image_offsets  = array();
			foreach ( $image_objects as $index => $object ) {
				$image_offsets[] = strlen( $scanned );
				$scanned        .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
			}
			$image_xref = strlen( $scanned );
			$scanned   .= "xref\n0 6\n0000000000 65535 f \n";
			foreach ( $image_offsets as $image_offset ) {
				$scanned .= sprintf( "%010d 00000 n \n", $image_offset );
			}
			$scanned  .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $image_xref . "\n%%EOF\n";
			$scan_id   = wp_generate_uuid4();
			$scan_path = $root . '/' . $scan_id . '.pdf';
			$cleanup[] = $scan_path;
			file_put_contents( $scan_path, $scanned ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Private synthetic raster-only PDF fixture.
			$scan_crop = PageRenderer::crop(
				array(
					'id'     => $scan_id,
					'sha256' => hash( 'sha256', $scanned ),
				),
				1,
				array( 0.25, 0.25, 0.75, 0.75 )
			);
			$check( ! is_wp_error( $scan_crop ) && 1200 === $scan_crop['width'] && 600 === $scan_crop['height'], 'Scanned raster-only PDF crop preserved' );
			$job_id = wp_insert_post(
				array(
					'post_type'   => Jobs::TYPE,
					'post_status' => 'private',
					'post_author' => $owner,
				),
				true
			);
			$check( $job_id, 'Fixture source job' );
			add_post_meta(
				$job_id,
				Jobs::META,
				array(
					'state'    => 'ready_for_extraction',
					'document' => $document,
				)
			);
			$make    = static function ( $bounds, $page = 1 ) use ( $job_id ) {
				$request = new WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports/' . $job_id . '/figures' );
				$request->set_body_params(
					array(
						'page'   => $page,
						'bounds' => $bounds,
					)
				);
				return rest_do_request( $request );
			};
			$created = $make( array( 0.25, 0.25, 0.75, 0.75 ) );
			$check( 200 === $created->get_status(), 'REST queues crop' );
			$view      = $created->get_data();
			$cleanup[] = $root . '/figure-' . $view['id'] . '.png';
			$check( 'queued' === $view['state'] && '' === $view['url'], 'Queued assets expose no image URL' );
			$check( $make( array( 0.25, 0.25, 0.75, 0.75 ) )->get_data()['id'] === $view['id'], 'Duplicate requests reuse asset' );
			$posts    = get_posts(
				array(
					'post_type'   => Figures::TYPE,
					'post_status' => 'private',
					'name'        => $view['id'],
				)
			);
			$asset_id = $posts[0]->ID;
			$check( false !== as_has_scheduled_action( Figures::HOOK, array( $asset_id ), Jobs::GROUP ), 'Crop scheduled' );
			Figures::process( $asset_id );
			$ready = Figures::view( get_post( $asset_id ) );
			$check( 'ready' === $ready['state'] && 1 === $ready['attempts'] && 1200 === $ready['width'], 'Worker preserves cropped asset' );
			$serialized = wp_json_encode( $ready );
			$check( false === strpos( $serialized, 'sha256' ) && false === strpos( $serialized, $document['id'] ) && false === strpos( $serialized, $root ), 'Author view hides private source metadata' );
			$path = Figures::author_path( $view['id'] );
			$check( $path, 'Owner can retrieve protected crop' );
			$spec = array(
				'version' => 1,
				'bounds'  => array( -1, 5, 5, -1 ),
				'objects' => array(
					array(
						'id'    => 'A',
						'type'  => 'point',
						'x'     => 0,
						'y'     => 0,
						'label' => 'A',
					),
					array(
						'id'    => 'B',
						'type'  => 'point',
						'x'     => 4,
						'y'     => 0,
						'label' => 'B',
					),
					array(
						'id'     => 'AB',
						'type'   => 'segment',
						'points' => array( 'A', 'B' ),
						'label'  => '4 cm',
					),
				),
			);
			$check( ! is_wp_error( FigureSpec::validate( $spec ) ), 'Geometry spec accepted' );
			$function_spec = array(
				'version' => 1,
				'bounds'  => array( -5, 5, 5, -5 ),
				'objects' => array(
					array(
						'id'   => 'axes',
						'type' => 'axes',
					),
					array(
						'id'         => 'curve',
						'type'       => 'function',
						'expression' => array(
							'op'   => '^',
							'args' => array( 'x', 2 ),
						),
					),
				),
			);
			$check( ! is_wp_error( FigureSpec::validate( $function_spec ) ), 'Restricted function plots accepted' );
			$function_spec['objects'][1]['expression'] = array(
				'op'   => 'eval',
				'args' => array( 'x' ),
			);
			$check( is_wp_error( FigureSpec::validate( $function_spec ) ), 'Executable expression operators rejected' );
			$function_spec['objects'] = array_map(
				static function ( $index ) {
					return array(
						'id'         => 'f' . $index,
						'type'       => 'function',
						'expression' => 'x',
					);
				},
				range( 0, 8 )
			);
			$check( is_wp_error( FigureSpec::validate( $function_spec ) ), 'Excessive plot count rejected' );
			$original_ai_settings = get_option( \OhMyLMS\AI\Settings::OPTION, array() );
			\OhMyLMS\AI\Settings::update(
				array(
					'provider'               => 'openai',
					'model'                  => 'fixture',
					'key'                    => 'fixture-only',
					'exam_converter_enabled' => true,
				)
			);
			$fixture_provider = new class() implements \OhMyLMS\AI\Providers\Provider {
				/**
				 * Build a nonroutable test request.
				 *
				 * @param array  $settings Settings.
				 * @param string $key Fixture key.
				 * @param array  $request Prompt.
				 * @return array Request.
				 */
				public function request( array $settings, $key, array $request ) {
					return array(
						'url'     => 'https://example.invalid/figure-fixture',
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
				 * Parse the intercepted fixture response.
				 *
				 * @param array $decoded Fixture JSON.
				 * @param int   $status Status.
				 * @return array|WP_Error Reply.
				 */
				public function parse( array $decoded, $status ) {
					return 200 === $status ? array(
						'text'          => $decoded['text'],
						'input_tokens'  => 1,
						'output_tokens' => 1,
					) : new WP_Error( 'fixture_response', 'Fixture error.' );
				}
			};
			$provider_filter  = static function ( $providers ) use ( $fixture_provider ) {
				foreach ( array_keys( $providers ) as $name ) {
					$providers[ $name ] = $fixture_provider;
				}
				return $providers;
			};
			$figure_reply     = wp_json_encode( $spec );
			$transport        = static function ( $pre, $args, $url ) use ( &$figure_reply, $check ) {
				if ( 'https://example.invalid/figure-fixture' !== $url ) {
					return $pre;
				}
				$check( false !== strpos( $args['body'], 'iVBOR' ), 'Reconstruction receives the protected PNG, not a public URL' );
				return array(
					'headers'  => array(),
					'body'     => wp_json_encode( array( 'text' => $figure_reply ) ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			};
			add_filter( 'ohmylms_ai_providers', $provider_filter );
			add_filter( 'pre_http_request', $transport, PHP_INT_MAX, 3 );
			$reconstruct_request = new WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports/' . $job_id . '/reconstruct' );
			$reconstruct_request->set_body_params(
				array(
					'asset_id' => $view['id'],
					'question' => 'Reconstruct the shown segment.',
				)
			);
			$generated = rest_do_request( $reconstruct_request );
			$check( 200 === $generated->get_status() && 1 === $generated->get_data()['version'], 'Reconstruction endpoint returns a validated declarative proposal' );
			$check( ! isset( $generated->get_data()['approved'] ), 'Provider cannot approve a reconstruction' );
			$figure_reply = '{"version":999,"objects":[]}';
			$check( 400 === rest_do_request( $reconstruct_request )->get_status(), 'Invalid reconstruction fails visibly' );
			$check( 'ready' === Figures::view( get_post( $asset_id ) )['state'], 'Reconstruction failure retains the protected original' );
			remove_filter( 'ohmylms_ai_providers', $provider_filter );
			remove_filter( 'pre_http_request', $transport, PHP_INT_MAX );
			update_option( \OhMyLMS\AI\Settings::OPTION, $original_ai_settings, false );
			$unsafe                          = $spec;
			$unsafe['objects'][0]['onclick'] = 'alert(1)';
			$check( is_wp_error( FigureSpec::validate( $unsafe ) ), 'Executable attributes rejected' );
			$unsafe                         = $spec;
			$unsafe['objects'][2]['points'] = array( 'A', 'missing' );
			$check( is_wp_error( FigureSpec::validate( $unsafe ) ), 'Invalid references rejected' );
			$parts    = array(
				array(
					'id'     => 'a',
					'kind'   => 'numerical',
					'marks'  => 1,
					'answer' => 5,
					'prompt' => 'PRIVATE expected answer is not public.',
				),
			);
			$figure   = array(
				'id'           => 'f1',
				'page'         => 1,
				'bounds'       => array( 0.25, 0.25, 0.75, 0.75 ),
				'asset_id'     => $view['id'],
				'part_ids'     => array(),
				'alt'          => 'Segment AB',
				'not_to_scale' => true,
				'selected'     => 'jsxgraph',
				'spec'         => FigureSpec::validate( $spec ),
				'approved'     => true,
			);
			$settings = array(
				'parts'         => $parts,
				'figures'       => array( $figure ),
				'figure_review' => array(
					'complete' => true,
					'required' => true,
				),
			);
			$check( is_wp_error( FigureContent::publishable( $settings ) ), 'Unattested approval cannot publish' );
			$settings = FigureContent::seal( $settings );
			$check( $settings, 'Explicit owner figure review sealed' );
			$check( true === FigureContent::publishable( $settings ), 'Reviewed geometry can publish' );
			$changed_settings                      = $settings;
			$changed_settings['figures'][0]['alt'] = 'Changed after review';
			$check( is_wp_error( FigureContent::publishable( $changed_settings ) ), 'Edits invalidate figure approval' );
			$skill = wp_insert_term( $fixture_tag, \OhMyLMS\Skills\Taxonomy::NAME );
			$check( $skill, 'Figure skill fixture' );
			$import_row = array(
				'number'           => '1',
				'question'         => 'Use the figure.',
				'page'             => 1,
				'requires_source'  => true,
				'notes'            => '',
				'skills'           => '#' . $skill['term_id'],
				'parts'            => $parts,
				'figures'          => array( $figure ),
				'reviewed'         => true,
				'figures_complete' => false,
			);
			$inventory  = array(
				'title'            => 'Synthetic geometry',
				'duration_minutes' => 60,
				'total_marks'      => 1,
				'page_count'       => 3,
				'question_numbers' => array( '1' ),
			);
			$job_state  = get_post_meta( $job_id, Jobs::META, true );
			$job_state  = array_merge(
				$job_state,
				array(
					'state'           => 'ready_for_review',
					'draft_inventory' => $inventory,
					'inventory'       => $inventory,
					'draft'           => array( $import_row ),
					'draft_revision'  => 1,
					'attempts'        => 0,
					'error'           => '',
					'updated_at'      => gmdate( 'c' ),
					'target'          => 'questions',
				)
			);
			update_post_meta( $job_id, Jobs::META, wp_slash( $job_state ) );
			$commit_request = new WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports/' . $job_id . '/commit' );
			$commit_request->set_body_params( array( 'revision' => 1 ) );
			$check( 409 === rest_do_request( $commit_request )->get_status(), 'Incomplete figure review blocks native commit' );
			$import_row['figures_complete'] = true;
			$review_request                 = new WP_REST_Request( 'POST', '/ohmylms/v1/exam-imports/' . $job_id . '/review' );
			$review_request->set_body_params(
				array(
					'revision'  => 1,
					'questions' => array( $import_row ),
				)
			);
			$reviewed = rest_do_request( $review_request );
			$check( 200 === $reviewed->get_status(), 'Figure placements survive draft review save' );
			$commit_request->set_body_params( array( 'revision' => 2 ) );
			$committed = rest_do_request( $commit_request );
			$check( 200 === $committed->get_status(), 'Reviewed figures commit through the actual import workflow' );
			$row   = array(
				'exam'     => $fixture_tag,
				'paper'    => 'crop',
				'number'   => '1',
				'type'     => 'structured',
				'question' => 'Use the figure.',
				'skills'   => '#' . $skill['term_id'],
				'parts'    => $parts,
				'settings' => $settings,
			);
			$saved = BulkImport::run( array( $row ), array( 'dry_run' => false ) );
			$check( ! is_wp_error( $saved ) && 0 === $saved['counts']['error'], 'Figure settings survive native bulk import' );
			$question_id = $saved['rows'][0]['id'];
			$version_id  = (int) VersionPublisher::identity( $question_id )['current_version_id'];
			$check( true === ReviewQueue::approve( $question_id ), 'Question figure version approved' );
			$frozen = VersionPublisher::snapshot( $version_id );
			$check( $view['id'] === $frozen->get_settings()['figures'][0]['asset_id'], 'Original reference frozen in version' );
			$shared                                       = $frozen->to_array();
			$shared['settings']['parts'][]                = array(
				'id'     => 'b',
				'kind'   => 'written',
				'marks'  => 1,
				'prompt' => 'Use the shared figure.',
			);
			$shared['settings']['figures'][0]['part_ids'] = array( 'a', 'b' );
			$shared['settings']                           = FigureContent::seal( $shared['settings'] );
			$shared_view                                  = ( new \OhMyLMS\Assessment\QuestionSnapshot( $shared ) )->student_view();
			$check( false === strpos( $shared_view['description'], 'data-figure-spec' ) && false !== strpos( $shared_view['settings']['parts'][0]['prompt'], 'data-figure-spec' ) && false !== strpos( $shared_view['settings']['parts'][1]['prompt'], 'data-figure-spec' ), 'One original can be shared by several stable part IDs' );
			$projection = $frozen->student_view();
			$check( false !== strpos( $projection['description'], 'data-figure-spec' ) && false !== strpos( $projection['description'], 'Not to scale' ), 'Selected reconstruction and visible scale note delivered' );
			$check( ! isset( $projection['settings']['figure_review'] ) && ! isset( $projection['settings']['figures'] ), 'Private figure approval data omitted from learners' );
			$check( ! is_wp_error( FigureContent::path( $view['id'], $version_id, 'print:1' ) ), 'Authorized author print can retrieve frozen original' );
			$call    = static function ( $endpoint, array $body ) use ( $check ) {
				$request = new WP_REST_Request( 'POST', '/ohmylms/v1/' . $endpoint );
				$request->set_body_params( $body );
				$response = rest_do_request( $request );
				$check( $response->get_status() < 300, 'Fixture API ' . $endpoint );
				return $response->get_data();
			};
			$course  = $call(
				'courses',
				array(
					'name'   => $fixture_tag,
					'status' => 'publish',
				)
			)['id'];
			$quiz    = $call(
				'quiz',
				array(
					'name'     => $fixture_tag,
					'status'   => 'publish',
					'settings' => array( 'allow_attempts' => 5 ),
				)
			)['id'];
			$chapter = wp_insert_post(
				array(
					'post_type'   => 'ohmylms-chapter',
					'post_title'  => $fixture_tag,
					'post_status' => 'publish',
				)
			);
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_chapter_relationship',
				array(
					'course_id'    => $course,
					'chapter_id'   => $chapter,
					'order_number' => 0,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transactional fixture.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_content_relationship',
				array(
					'chapter_id'   => $chapter,
					'content_id'   => $quiz,
					'content_type' => 'quiz',
					'order_number' => 0,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transactional fixture.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
				array(
					'quiz_id'      => $quiz,
					'question_id'  => $question_id,
					'order_number' => 0,
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transactional fixture.
			$student = wp_insert_user(
				array(
					'user_login' => $fixture_tag . '-s',
					'user_pass'  => wp_generate_password(),
					'role'       => 'subscriber',
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Transactional fixture relationship, rolled back after rehearsal.
			$wpdb->insert(
				$wpdb->prefix . 'ohmylms_user_enrollment',
				array(
					'user_id'    => $student,
					'course_id'  => $course,
					'status'     => 'enrolled',
					'progress'   => 'running',
					'start_date' => current_time( 'mysql' ),
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Transactional fixture.
			$check( $student, 'Create fixture student' );
			wp_set_current_user( $student );
			$check( is_wp_error( FigureContent::path( $view['id'], $version_id, '123456789' ) ), 'Guessed delivery scope denied' );
			$attempt = Submission::start( $quiz, $student );
			$check( $attempt, 'Student starts exact frozen figure version' );
			$check( ! is_wp_error( FigureContent::path( $view['id'], $version_id, (string) $attempt ) ), 'Actual attempt authorizes only its frozen asset' );
			$delivery = wp_json_encode( AttemptItems::delivery( $attempt ) );
			wp_set_current_user( $owner );
			$row['settings']['figures'][0]['alt'] = 'A later crop description';
			$row['settings']                      = FigureContent::seal( $row['settings'] );
			$updated                              = BulkImport::run( array( $row ), array( 'dry_run' => false ) );
			$check( ! is_wp_error( $updated ) && 0 === $updated['counts']['error'], 'Later figure edit creates a new draft' );
			$check( (int) VersionPublisher::identity( $question_id )['current_version_id'] !== $version_id, 'Figure content changes create new immutable version' );
			$check( wp_json_encode( AttemptItems::delivery( $attempt ) ) === $delivery, 'Existing attempt retains selected figure and labels' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transactional fixture deletion; request-local caches are cleared immediately below.
			$wpdb->delete(
				$wpdb->prefix . 'ohmylms_quiz_questions_relationship',
				array(
					'quiz_id'     => $quiz,
					'question_id' => $question_id,
				)
			);
			wp_cache_flush();
			wp_set_current_user( $student );
			$paper = \OhMyLMS\Quiz\PaperPresentation::student( $quiz, array( 'presentation' => 'paper' ) );
			$check( in_array( (int) $question_id, \OhMyLMS\Quiz\PaperPresentation::order( $paper['items'] ), true ), 'Existing paper retains frozen questions after live removal' );
			$check( ! is_wp_error( FigureContent::path( $view['id'], $version_id, (string) $attempt ) ), 'Live removal does not revoke an existing attempt figure' );
			wp_set_current_user( $owner );
			$digest = hash_file( 'sha256', $path );
			Figures::process( $asset_id );
			$check( Figures::view( get_post( $asset_id ) ) === $ready && hash_file( 'sha256', $path ) === $digest, 'Duplicate delivery leaves ready asset immutable' );
			wp_set_current_user( 0 );
			$check( is_wp_error( Figures::author_path( $view['id'] ) ), 'Guest denied' );
			$check( 400 <= $make( array( 0, 0, 1, 1 ) )->get_status(), 'Guest cannot queue crops' );
			$other = wp_insert_user(
				array(
					'user_login' => $fixture_tag . '-other',
					'user_pass'  => wp_generate_password(),
					'role'       => 'administrator',
				)
			);
			wp_set_current_user( $other );
			$check( is_wp_error( Figures::author_path( $view['id'] ) ), 'Other administrator denied' );
			$check( 400 <= $make( array( 0, 0, 1, 1 ) )->get_status(), 'Other administrator cannot crop owner source' );
			wp_set_current_user( $owner );
			$check( is_wp_error( Figures::author_path( '../wp-config' ) ), 'Path traversal denied' );
			$owner_user = new WP_User( $owner );
			$owner_user->remove_role( 'administrator' );
			wp_set_current_user( 0 );
			wp_set_current_user( $owner );
			$check( is_wp_error( Figures::author_path( $view['id'] ) ), 'Revoked author capabilities deny crop access' );
			$owner_user->add_role( 'administrator' );
			wp_set_current_user( 0 );
			wp_set_current_user( $owner );
			$changed   = $make( array( 0, 0, 0.5, 0.5 ) )->get_data();
			$cleanup[] = $root . '/figure-' . $changed['id'] . '.png';
			$check( $changed['id'] !== $view['id'], 'Corrected bounds create a new asset' );
			$bad      = $make( array( 0, 0, 1, 1 ), 4 )->get_data();
			$bad_post = get_posts(
				array(
					'post_type'   => Figures::TYPE,
					'post_status' => 'private',
					'name'        => $bad['id'],
				)
			)[0];
			for ( $attempt = 0; $attempt < 3; ++$attempt ) {
				$make( array( 0, 0, 1, 1 ), 4 );
				Figures::process( $bad_post->ID );
			}
			$failure = $make( array( 0, 0, 1, 1 ), 4 )->get_data();
			$check( 'failed' === $failure['state'] && 3 === $failure['attempts'] && '' === $failure['url'], 'Failure is visible and retries bounded' );
			$interrupted          = get_post_meta( $bad_post->ID, Figures::META, true );
			$interrupted['state'] = 'rendering';
			$interrupted['error'] = '';
			update_post_meta( $bad_post->ID, Figures::META, wp_slash( $interrupted ) );
			$recovered = $make( array( 0, 0, 1, 1 ), 4 )->get_data();
			$check( 'failed' === $recovered['state'] && 'exam_render_attempts' === $recovered['error'], 'Interrupted final attempt resolves to explicit failure' );
			$run = new ReflectionMethod( PageRenderer::class, 'run' );
			$run->setAccessible( true );
			$check( is_wp_error( $run->invoke( null, array( '-v' ), microtime( true ) - 1 ) ), 'Expired deadline rejects and terminates process' );
			$check( array() === glob( $root . '/render-*' ), 'Renderer workspaces removed after success and failure' );
			wp_delete_post( $job_id, true );
			$check( ! is_file( $root . '/' . $document['id'] . '.pdf' ) && ! is_wp_error( Figures::author_path( $view['id'] ) ), 'Source deletion preserves independent crop' );
			file_put_contents( $path, 'corrupted' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Corrupt only the synthetic crop to verify integrity enforcement.
			$check( is_wp_error( Figures::author_path( $view['id'] ) ), 'Changed crop bytes cannot be served' );
			throw new RuntimeException( 'figure_fixture_rollback' );
		}
	);
} catch ( RuntimeException $error ) {
	if ( 'figure_fixture_rollback' !== $error->getMessage() ) {
		throw $error;
	}
} finally {
	wp_set_current_user( 0 );
	foreach ( $cleanup as $cleanup_path ) {
		// Only explicitly recorded fixture files under the checked private root.
		if ( realpath( dirname( $cleanup_path ) ) === $root ) {
			wp_delete_file( $cleanup_path );
		}
	}
}
$check( ! $wpdb->get_var( $wpdb->prepare( 'SELECT ID FROM %i WHERE user_login=%s', $wpdb->users, $fixture_tag ) ), 'Database fixture rolled back' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Check rollback against the database, not stale request-local user cache.
echo (int) $checks . " figure asset checks passed; fixture writes rolled back.\n";
