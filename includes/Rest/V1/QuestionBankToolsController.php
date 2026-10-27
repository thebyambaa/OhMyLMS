<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Internal REST routes behind the question bank MCP tools.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\MCP\QuestionBankTools;
use OhMyLMS\MCP\Server;
use OhMyLMS\QuestionBank\BulkImport;
use OhMyLMS\QuestionBank\ReviewQueue;
use OhMyLMS\QuestionBank\RowExporter;
use OhMyLMS\QuestionBank\SkillCatalogue;

defined( 'ABSPATH' ) || exit;

/** One validated contract for the REST routes and the native abilities. */
class QuestionBankToolsController extends RestController {

	/** Register each concrete operation without exposing arbitrary dispatch. */
	public function register_routes() {
		foreach ( QuestionBankTools::definitions() as $name => $tool ) {
			$args = $tool['properties'];
			foreach ( $args as $key => &$schema ) {
				$schema['required'] = in_array( $key, $tool['required'], true );
			}
			unset( $schema );
			register_rest_route(
				$this->namespace,
				$tool['route'],
				array(
					'methods'             => $tool['method'],
					'args'                => $args,
					'permission_callback' => array( $this, 'permission' ),
					'callback'            => function ( $request ) use ( $name ) {
						return $this->dispatch( $name, $request ); },
				)
			);
		}
	}

	/**
	 * Administrator access, as for the other MCP authoring routes.
	 *
	 * @return true|\WP_Error
	 */
	public function permission() {
		return Server::can_manage() ? true : new \WP_Error( 'ohmylms_forbidden', __( 'Administrator access is required.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
	}

	/**
	 * Run one known operation.
	 *
	 * @param string           $name Tool name.
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function dispatch( $name, $request ) {
		switch ( $name ) {
			case 'search_skill_catalogue':
				$page   = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );
				$per    = max( 1, min( 50, absint( $request->get_param( 'per_page' ) ?? 20 ) ) );
				$parent = $request->has_param( 'parent_id' ) ? absint( $request->get_param( 'parent_id' ) ) : -1;
				$found  = SkillCatalogue::search( (string) $request->get_param( 'search' ), $page, $per, $parent );
				return $this->paged( $found['items'], $found['total'], $per );
			case 'export_questions':
				$ids      = array_map( 'absint', (array) $request->get_param( 'ids' ) );
				$sections = array();
				$label    = sanitize_text_field( (string) $request->get_param( 'label' ) );
				$quiz_id  = absint( $request->get_param( 'quiz_id' ) );
				if ( $quiz_id ) {
					if ( OHMYLMS_QUIZ_CPT !== get_post_type( $quiz_id ) ) {
						return new \WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
					}
					$ids   = array_map( 'intval', array_column( ohmylms_get_quiz( $quiz_id )->get_questions(), 'id' ) );
					$label = '' !== $label ? $label : get_the_title( $quiz_id );
					foreach ( (array) \OhMyLMS\Assessment\AssessmentSettings::get( $quiz_id )['sections'] as $section ) {
						foreach ( (array) ( $section['questions'] ?? array() ) as $question_id ) {
							$sections[ (int) $question_id ] = (string) ( $section['title'] ?? '' );
						}
					}
				}
				$file = RowExporter::export( $ids, 'csv' === $request->get_param( 'format' ) ? 'csv' : 'json', $label, $sections );
				return is_wp_error( $file ) ? $file : rest_ensure_response( $file );
			case 'import_questions':
				$rows = BulkImport::parse( (array) $request->get_param( 'rows' ) );
				if ( is_wp_error( $rows ) ) {
					return $rows;
				}
				$report = BulkImport::run(
					$rows,
					array(
						// A real import must be asked for. Rows from a tool are AI-classified, so they are
						// always flagged for review and never approved here.
						'dry_run'         => ! $request->has_param( 'dry_run' ) || rest_sanitize_boolean( $request->get_param( 'dry_run' ) ),
						'bank_id'         => absint( $request->get_param( 'bank_id' ) ),
						'approve'         => false,
						'review'          => true,
						'update_existing' => ! $request->has_param( 'update_existing' ) || rest_sanitize_boolean( $request->get_param( 'update_existing' ) ),
						'skip_invalid'    => rest_sanitize_boolean( $request->get_param( 'skip_invalid' ) ),
					)
				);
				return is_wp_error( $report ) ? $report : rest_ensure_response( $report );
			case 'list_questions_for_review':
				$per   = max( 1, min( 20, absint( $request->get_param( 'per_page' ) ?? 10 ) ) );
				$found = ReviewQueue::pending( max( 1, absint( $request->get_param( 'page' ) ?? 1 ) ), $per, (string) $request->get_param( 'search' ) );
				return $this->paged( $found['items'], $found['total'], $per );
			default:
				$decision = 'approve' === $request->get_param( 'decision' ) ? 'approve' : 'dismiss';
				return rest_ensure_response( ReviewQueue::decide( (array) $request->get_param( 'ids' ), $decision ) );
		}
	}

	/**
	 * A list response with the standard pagination headers.
	 *
	 * @param array $items Items.
	 * @param int   $total Total matches.
	 * @param int   $per_page Page size.
	 * @return \WP_REST_Response
	 */
	private function paged( array $items, $total, $per_page ) {
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
		return $response;
	}
}
