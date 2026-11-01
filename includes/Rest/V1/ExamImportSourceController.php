<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * ExamImportSourceController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\ExamImport\Jobs;
use OhMyLMS\ExamImport\Review;
use OhMyLMS\ExamImport\SourcePages;
use WP_Error;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Renderer probe and the source viewer of the import review screen (docs/api/exams.md): the PDF paper,
 * the mark scheme and rendered page images, streamed to the job owner only. Crops still go through
 * `exam-imports/jobs/{id}/figures` ({@see ExamImportsController}).
 */
class ExamImportSourceController extends RestController {

	/**
	 * The base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'exam-imports/jobs';

	/**
	 * Verified file waiting to be streamed by {@see self::serve_file()}.
	 *
	 * @var string|null
	 */
	private static $file = null;

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$item = '/' . $this->rest_base . '/(?P<id>[\d]+)';
		$args = array(
			'id' => array(
				'description' => __( 'Unique identifier for the import job.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
		);

		register_rest_route(
			$this->namespace,
			'/exam-imports/renderer',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_renderer' ),
					'permission_callback' => array( $this, 'collection_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/source',
			array(
				'args' => $args,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_source' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/document',
			array(
				'args' => $args,
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_document' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
					'args'                => array(
						'part' => array(
							'type'    => 'string',
							'enum'    => array( 'paper', 'mark_scheme' ),
							'default' => 'paper',
						),
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			$item . '/pages/(?P<page>[\d]+)',
			array(
				'args' => $args + array(
					'page' => array(
						'description' => __( 'One-based page of the PDF paper.', 'ohmylms' ),
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 500,
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_page' ),
					'permission_callback' => array( $this, 'item_permissions_check' ),
				),
			)
		);

		if ( ! has_filter( 'rest_pre_serve_request', array( self::class, 'serve_file' ) ) ) {
			add_filter( 'rest_pre_serve_request', array( self::class, 'serve_file' ), 10, 3 );
		}
	}

	/**
	 * 401 for guests, 403 for everyone else.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private function forbidden( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * The renderer probe: the import capabilities, `edit_posts` and `upload_files`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function collection_permissions_check( $request ) {
		return current_user_can( 'edit_posts' ) && current_user_can( 'upload_files' ) ? true : $this->forbidden( 'ohmylms_exam_import_cannot_view', __( 'Sorry, you are not allowed to import exams.', 'ohmylms' ) );
	}

	/**
	 * Job routes: 404 for unknown IDs first, then the job owner only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function item_permissions_check( $request ) {
		if ( ! Review::job( (int) $request['id'] ) ) {
			return new WP_Error( 'ohmylms_exam_import_not_found', __( 'Import not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$owner = new WP_REST_Request( 'GET', '/ohmylms/v1/exam-imports/' . (int) $request['id'] );
		$owner->set_param( 'id', (int) $request['id'] );
		if ( ! Jobs::permission( $owner ) ) {
			return $this->forbidden( 'ohmylms_exam_import_forbidden', __( 'Sorry, you are not allowed to open this import.', 'ohmylms' ) );
		}
		return true;
	}

	/**
	 * No-store response.
	 *
	 * @param mixed $data Body.
	 * @return WP_REST_Response
	 */
	private function respond( $data ) {
		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * Which PDF renderer this server has, and what is missing when it has none.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_renderer( $request ) {
		return $this->respond( SourcePages::renderer() );
	}

	/**
	 * What the side-by-side viewer of one job can show, with authenticated addresses.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_source( $request ) {
		$id  = (int) $request['id'];
		$job = (array) Review::job( $id );
		if ( empty( $job['document']['id'] ) ) {
			return new WP_Error( 'ohmylms_exam_import_no_source', __( 'This import has no PDF paper.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$nonce = wp_create_nonce( 'wp_rest' );
		$base  = sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id );
		return $this->respond(
			array(
				'page_count'  => SourcePages::page_count( $job ),
				'renderer'    => SourcePages::renderer(),
				'page_url'    => add_query_arg( '_wpnonce', $nonce, rest_url( $base . '/pages/{page}' ) ),
				'document'    => add_query_arg( '_wpnonce', $nonce, rest_url( $base . '/document' ) ),
				'mark_scheme' => empty( $job['mark_scheme'] ) ? '' : add_query_arg(
					array(
						'part'     => 'mark_scheme',
						'_wpnonce' => $nonce,
					),
					rest_url( $base . '/document' )
				),
			)
		);
	}

	/**
	 * Stream the paper or the mark scheme as PDF, for the embedded viewer. Internal dispatches get the
	 * size; the body is replaced in {@see self::serve_file()}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_document( $request ) {
		$path = SourcePages::document( (array) Review::job( (int) $request['id'] ), (string) $request['part'] );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		self::$file = $path;
		$response   = $this->respond( array( 'bytes' => (int) filesize( $path ) ) );
		$response->header( 'Content-Type', 'application/pdf' );
		$response->header( 'Content-Disposition', 'inline; filename="exam-source.pdf"' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-Frame-Options', 'SAMEORIGIN' );
		$response->header( 'Cross-Origin-Resource-Policy', 'same-origin' );
		return $response;
	}

	/**
	 * Stream one rendered page as PNG.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_page( $request ) {
		$path = SourcePages::page( (array) Review::job( (int) $request['id'] ), (int) $request['page'] );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		$size       = getimagesize( $path );
		self::$file = $path;
		$response   = $this->respond(
			array(
				'bytes'  => (int) filesize( $path ),
				'width'  => (int) ( $size[0] ?? 0 ),
				'height' => (int) ( $size[1] ?? 0 ),
			)
		);
		$response->header( 'Content-Type', 'image/png' );
		$response->header( 'Content-Disposition', 'inline; filename="exam-page.png"' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'Cross-Origin-Resource-Policy', 'same-origin' );
		return $response;
	}

	/**
	 * Send a verified file instead of the JSON body of the document and page routes.
	 *
	 * @param bool                  $served  Whether the request has already been served.
	 * @param WP_HTTP_Response|null $result  Result to send.
	 * @param WP_REST_Request|null  $request Served request.
	 * @return bool
	 */
	public static function serve_file( $served, $result, $request = null ) {
		$file       = self::$file;
		self::$file = null;
		if (
			$served || null === $file || ! ( $result instanceof WP_HTTP_Response ) || 200 !== $result->get_status()
			|| ! ( $request instanceof WP_REST_Request ) || ! preg_match( '#^/ohmylms/v1/exam-imports/jobs/\d+/(?:document|pages/\d+)$#', $request->get_route() )
		) {
			return $served;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream an authorised, verified private file.
		readfile( $file );
		return true;
	}
}
