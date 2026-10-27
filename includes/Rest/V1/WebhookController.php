<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WebhookController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Webhook;
use OhMyLMS\DataStores\WebhookStore;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * WebhookController class.
 *
 * Handles REST API endpoints for webhooks.
 *
 * @since 1.0.0
 */
class WebhookController extends RestController {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for webhook endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'webhooks';

	/**
	 * Register the routes for the webhook endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Get all webhooks.
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_webhook_args(),
				),
			)
		);

		// Get, update, delete specific webhook.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the webhook.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_webhook_args(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);

		// Bulk actions.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_actions' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array(
						'action' => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'delete', 'active', 'inactive' ),
						),
						'ids'    => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		// Get webhook triggers.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/triggers',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_triggers' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check if current user can get webhooks.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_view', __( 'Sorry, you are not allowed to view webhooks.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Check if current user can get a single webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_view', __( 'Sorry, you are not allowed to view webhooks.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Check if current user can create a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_create', __( 'Sorry, you are not allowed to create webhooks.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Check if current user can update a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_edit', __( 'Sorry, you are not allowed to edit webhooks.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Check if current user can delete a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_delete', __( 'Sorry, you are not allowed to delete webhooks.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Get webhooks.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page     = $request->get_param( 'page' ) ?? 1;
		$per_page = $request->get_param( 'per_page' ) ?? 10;
		$status   = $request->get_param( 'status' ) ?? 'all';
		$search   = $request->get_param( 'search' ) ?? '';
		$orderby  = $request->get_param( 'orderby' ) ?? 'created_at';
		$order    = $request->get_param( 'order' ) ?? 'DESC';

		$args     = array(
			'status'  => $status,
			'search'  => $search,
			'orderby' => $orderby,
			'order'   => $order,
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
		);
		$webhooks = WebhookStore::get_webhooks( $args );
		$total    = WebhookStore::get_webhook_count(
			array(
				'status' => $status,
				'search' => $search,
			)
		);

		$data = array();
		foreach ( $webhooks as $webhook_data ) {
			$webhook = new Webhook( $webhook_data->id );
			$data[]  = $this->prepare_item_for_response( $webhook, $request );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Get a specific webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$webhook_id = (int) $request->get_param( 'id' );

		try {
			$webhook = new Webhook( $webhook_id );
			if ( ! $webhook->get_id() ) {
				return new WP_Error( 'webhook_not_found', __( 'Webhook not found.', 'ohmylms' ), array( 'status' => 404 ) );
			}

			return rest_ensure_response( $this->prepare_item_for_response( $webhook, $request ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'webhook_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Create a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		try {

			$webhook = new Webhook();
			$this->update_webhook_from_request( $webhook, $request );
			$webhook->save();

			$response = $this->prepare_item_for_response( $webhook, $request );
			$response = rest_ensure_response( $response );
			$response->set_status( 201 );

			return $response;
		} catch ( \Exception $e ) {
			return new WP_Error( 'webhook_create_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Update a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$webhook_id = (int) $request->get_param( 'id' );

		try {
			$webhook = new Webhook( $webhook_id );
			if ( ! $webhook->get_id() ) {
				return new WP_Error( 'webhook_not_found', __( 'Webhook not found.', 'ohmylms' ), array( 'status' => 404 ) );
			}

			$this->update_webhook_from_request( $webhook, $request );
			$webhook->save();

			return rest_ensure_response( $this->prepare_item_for_response( $webhook, $request ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'webhook_update_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Delete a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$webhook_id = (int) $request->get_param( 'id' );

		try {
			$webhook = new Webhook( $webhook_id );
			if ( ! $webhook->get_id() ) {
				return new WP_Error( 'webhook_not_found', __( 'Webhook not found.', 'ohmylms' ), array( 'status' => 404 ) );
			}

			$webhook->delete();

			return rest_ensure_response( array( 'deleted' => true ) );
		} catch ( \Exception $e ) {
			return new WP_Error( 'webhook_delete_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function bulk_actions( $request ) {
		$action = $request->get_param( 'action' );
		$ids    = $request->get_param( 'ids' );

		$results = array();

		foreach ( $ids as $id ) {
			try {
				$webhook = new Webhook( $id );
				if ( ! $webhook->get_id() ) {
					continue;
				}

				switch ( $action ) {
					case 'delete':
						$webhook->delete();
						$results[ $id ] = 'deleted';
						break;
					case 'active':
						$webhook->set_status( 'active' );
						$webhook->save();
						$results[ $id ] = 'active';
						break;
					case 'inactive':
						$webhook->set_status( 'inactive' );
						$webhook->save();
						$results[ $id ] = 'inactive';
						break;
				}
			} catch ( \Exception $e ) {
				$results[ $id ] = 'error: ' . $e->getMessage();
			}
		}

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * Get available triggers.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_triggers( $request ) {
		$triggers   = Webhook::get_available_triggers();
		$methods    = Webhook::get_available_methods();
		$data_types = Webhook::get_available_data_types();

		return rest_ensure_response(
			array(
				'triggers'   => $triggers,
				'methods'    => $methods,
				'data_types' => $data_types,
			)
		);
	}

	/**
	 * Update webhook from request.
	 *
	 * @param Webhook         $webhook Webhook object.
	 * @param WP_REST_Request $request Request object.
	 */
	private function update_webhook_from_request( $webhook, $request ) {
		$fields = array(
			'name',
			'trigger_event',
			'webhook_url',
			'http_method',
			'data_type',
			'data_mapping',
			'status',
		);

		foreach ( $fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$value  = $request->get_param( $field );
				$method = "set_{$field}";
				if ( method_exists( $webhook, $method ) ) {
					$webhook->$method( $value );
				}
			}
		}
	}

	/**
	 * Prepare webhook for response.
	 *
	 * @param Webhook         $webhook Webhook object.
	 * @param WP_REST_Request $request Request object.
	 * @return array Webhook data.
	 */
	public function prepare_item_for_response( $webhook, $request ) {
		return array(
			'id'            => $webhook->get_id(),
			'name'          => $webhook->get_name(),
			'trigger_event' => $webhook->get_trigger_event(),
			'webhook_url'   => $webhook->get_webhook_url(),
			'http_method'   => $webhook->get_http_method(),
			'data_type'     => $webhook->get_data_type(),
			'data_mapping'  => $webhook->get_data_mapping(),
			'status'        => $webhook->get_status(),
			'created_at'    => $webhook->get_created_at(),
			'updated_at'    => $webhook->get_updated_at(),
		);
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		return array(
			'page'     => array(
				'description' => __( 'Current page of the collection.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 1,
			),
			'per_page' => array(
				'description' => __( 'Maximum number of items to be returned in result set.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 10,
			),
			'status'   => array(
				'description' => __( 'Limit result set to webhooks with a specific status.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'all',
				'enum'        => array( 'all', 'active', 'inactive' ),
			),
			'search'   => array(
				'description' => __( 'Limit results to those matching a string.', 'ohmylms' ),
				'type'        => 'string',
			),
		);
	}

	/**
	 * Get webhook arguments for create/update.
	 *
	 * @return array Webhook arguments.
	 */
	public function get_webhook_args() {
		return array(
			'name'          => array(
				'description' => __( 'Webhook name.', 'ohmylms' ),
				'type'        => 'string',
				'required'    => false,
			),
			'trigger_event' => array(
				'description' => __( 'Trigger event for the webhook.', 'ohmylms' ),
				'type'        => 'string',
				'required'    => false,
			),
			'webhook_url'   => array(
				'description' => __( 'Webhook URL.', 'ohmylms' ),
				'type'        => 'string',
				'required'    => false,
			),
			'http_method'   => array(
				'description' => __( 'HTTP method.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'POST',
				'enum'        => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ),
			),
			'data_type'     => array(
				'description' => __( 'Data type.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'json',
				'enum'        => array( 'json', 'xml', 'form' ),
			),
			'data_mapping'  => array(
				'description' => __( 'Data mapping configuration.', 'ohmylms' ),
				'type'        => 'string',
			),
			'status'        => array(
				'description' => __( 'Webhook status.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'active',
				'enum'        => array( 'active', 'inactive' ),
			),
		);
	}
}
