<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * REST route for the question bulk import.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\BulkImport;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/** Check or import a batch of question rows. */
class QuestionImportController extends RestController {

	/** Register POST /question-bank/import. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/question-bank/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import' ),
					'permission_callback' => array( AccessPolicy::class, 'can_author' ),
					'args'                => array(
						'rows'            => array( 'type' => 'array' ),
						'text'            => array( 'type' => 'string' ),
						'format'          => array(
							'type' => 'string',
							'enum' => array( 'auto', 'json', 'csv' ),
						),
						'dry_run'         => array( 'type' => 'boolean' ),
						'approve'         => array( 'type' => 'boolean' ),
						'update_existing' => array( 'type' => 'boolean' ),
						'skip_invalid'    => array( 'type' => 'boolean' ),
						'bank_id'         => array( 'type' => 'integer' ),
						'default_skills'  => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * Check (default) or import rows.
	 *
	 * @param WP_REST_Request $request Request with either decoded rows or JSON/CSV text.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$input = $request->get_param( 'rows' );
		if ( null === $input ) {
			$input = (string) $request->get_param( 'text' );
		}
		$rows = BulkImport::parse( $input, (string) ( $request->get_param( 'format' ) ?? 'auto' ) );
		if ( is_wp_error( $rows ) ) {
			return $rows;
		}
		$report = BulkImport::run(
			$rows,
			array(
				// A real import must be asked for; the default only checks.
				'dry_run'         => ! $request->has_param( 'dry_run' ) || rest_sanitize_boolean( $request->get_param( 'dry_run' ) ),
				'bank_id'         => absint( $request->get_param( 'bank_id' ) ),
				'approve'         => rest_sanitize_boolean( $request->get_param( 'approve' ) ),
				'update_existing' => ! $request->has_param( 'update_existing' ) || rest_sanitize_boolean( $request->get_param( 'update_existing' ) ),
				'skip_invalid'    => rest_sanitize_boolean( $request->get_param( 'skip_invalid' ) ),
				'default_skills'  => \OhMyLMS\ExamImport\Jobs::skill_tokens( $request->get_param( 'default_skills' ) ),
			)
		);
		return is_wp_error( $report ) ? $report : rest_ensure_response( $report );
	}
}
