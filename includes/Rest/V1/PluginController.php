<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * API JobsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API JobsController class.
 *
 * @since 0.3.0
 */
class PluginController extends RestController {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $base = 'custom';

	/**
	 * Register all routes related with carts.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_collection_params(),
					'schema'              => array( $this, 'get_item_schema' ),
				),
			)
		);
	}

	/**
	 * Retrieves a collection of job items.
	 *
	 * @since 0.3.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ): ?WP_REST_Response {
		$data     = array();
		$response = rest_ensure_response( $data );
		return $response;
	}



	/**
	 * Retrieves the group schema, conforming to JSON Schema.
	 *
	 * @since 0.3.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'job',
			'type'       => 'object',
			'properties' => array(),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
