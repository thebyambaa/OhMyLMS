<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * AccountHubReportsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Dashboard\Period;
use OhMyLMS\Schools\SchoolReport;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * School summary report under `ohmylms/v1/account-hub/schools/{id}/report` (docs/api/account-hub.md,
 * "School report"), computed by `Schools\SchoolReport` from the dashboard read models. Scope and
 * permissions are the Account Hub's (platform administrators, school administrators of the school).
 *
 * @since 1.0.0
 */
class AccountHubReportsController extends AccountHubController {

	/**
	 * Register the routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base . '/schools/(?P<id>[\d]+)/report';
		$args = array(
			'id'     => array(
				'description' => __( 'Unique identifier.', 'ohmylms' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'period' => Period::arg(),
		);
		foreach ( array(
			''        => 'get_report',
			'/export' => 'export_report',
		) as $suffix => $callback ) {
			register_rest_route(
				$this->namespace,
				$base . $suffix,
				array(
					'args' => $args,
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, $callback ),
						'permission_callback' => array( $this, 'read_permissions_check' ),
					),
				)
			);
		}
	}

	/**
	 * Build the report for a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	private function report( $request ) {
		$school = $this->school_or_error( $request['id'] );
		if ( is_wp_error( $school ) ) {
			return $school;
		}
		try {
			return SchoolReport::build( $school, Period::from_request( $request ) );
		} catch ( \RuntimeException $failure ) {
			return $this->error( 'report_failed', __( 'The report could not be computed. Try again.', 'ohmylms' ), 500 );
		}
	}

	/**
	 * Private response.
	 *
	 * @param array $data Body.
	 * @return WP_REST_Response
	 */
	private function no_store( array $data ) {
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}

	/**
	 * The school summary.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_report( $request ) {
		$report = $this->report( $request );

		return is_wp_error( $report ) ? $report : $this->no_store( $report );
	}

	/**
	 * The school summary as spreadsheet-safe CSV text.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function export_report( $request ) {
		$report = $this->report( $request );
		if ( is_wp_error( $report ) ) {
			return $report;
		}

		return $this->no_store(
			array(
				'filename'     => SchoolReport::filename( $report ),
				'content_type' => 'text/csv;charset=utf-8',
				'csv'          => SchoolReport::csv( $report ),
			)
		);
	}
}
