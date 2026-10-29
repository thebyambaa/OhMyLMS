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
use OhMyLMS\Integrations\Webhooks\Includes\WebhooksMigration;
use OhMyLMS\Webhooks\DeliveryLog;
use OhMyLMS\Webhooks\WebhookDelivery;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * WebhookController class.
 *
 * REST API for webhooks under `ohmylms/v1/webhooks`. See docs/api/webhooks.md for the contract.
 *
 * Authentication is WordPress core's: cookie authentication requires the `X-WP-Nonce` header
 * (verified by rest_cookie_check_errors()); application passwords also work. Every route requires
 * the CAPABILITY below.
 *
 * @since 1.0.0
 */
class WebhookController extends RestController {

	/**
	 * Capability required by every webhook route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Maximum ids accepted by the bulk route.
	 */
	const BULK_LIMIT = 100;

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
	protected $rest_base = 'webhooks';

	/**
	 * Latest deliveries for the current collection page, keyed by webhook ID.
	 *
	 * @var array|null
	 */
	private $latest_deliveries = null;

	/**
	 * Register the routes for the webhook endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the webhook.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$item,
			array(
				'args'   => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_actions' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'action' => array(
							'description' => __( 'Action to apply to every webhook in `ids`.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => array( 'delete', 'activate', 'deactivate' ),
							'required'    => true,
						),
						'ids'    => array(
							'description' => __( 'Webhook IDs.', 'ohmylms' ),
							'type'        => 'array',
							'items'       => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'minItems'    => 1,
							'maxItems'    => self::BULK_LIMIT,
							'uniqueItems' => true,
							'required'    => true,
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/triggers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_triggers' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'test_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_test_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/rotate-secret',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rotate_secret' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/deliveries',
			array(
				'args'   => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_deliveries' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_deliveries_collection_params(),
				),
				'schema' => array( $this, 'get_delivery_schema' ),
			)
		);
	}

	/**
	 * Check the capability shared by every route.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message used when access is denied.
	 * @return true|WP_Error
	 */
	private function require_capability( $code, $message ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( $code, $message, array( 'status' => rest_authorization_required_code() ) );
		}

		// Create or upgrade the tables on first use (cheap once the schema version matches).
		WebhooksMigration::ensure_schema();

		return true;
	}

	/**
	 * Check if current user can list webhooks.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_webhook_cannot_view', __( 'Sorry, you are not allowed to view webhooks.', 'ohmylms' ) );
	}

	/**
	 * Check if current user can get a single webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_webhook_cannot_view', __( 'Sorry, you are not allowed to view webhooks.', 'ohmylms' ) );
	}

	/**
	 * Check if current user can create a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_webhook_cannot_create', __( 'Sorry, you are not allowed to create webhooks.', 'ohmylms' ) );
	}

	/**
	 * Check if current user can update a webhook (also used by bulk, test and rotate-secret).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_webhook_cannot_edit', __( 'Sorry, you are not allowed to edit webhooks.', 'ohmylms' ) );
	}

	/**
	 * Check if current user can delete a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_webhook_cannot_delete', __( 'Sorry, you are not allowed to delete webhooks.', 'ohmylms' ) );
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found_error() {
		return new WP_Error( 'ohmylms_webhook_not_found', __( 'Webhook not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Load a webhook or return a 404 error.
	 *
	 * @param int $id Webhook ID.
	 * @return Webhook|WP_Error
	 */
	private function get_webhook( $id ) {
		$id = absint( $id );
		if ( ! $id || ! WebhookStore::exists( $id ) ) {
			return $this->not_found_error();
		}

		try {
			return new Webhook( $id );
		} catch ( \Exception $e ) {
			return $this->not_found_error();
		}
	}

	/**
	 * Get webhooks.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$filters  = array(
			'status'        => $request['status'],
			'trigger_event' => $request['trigger_event'],
			'search'        => $request['search'],
		);

		$total       = WebhookStore::get_webhook_count( $filters );
		$total_pages = (int) ceil( $total / $per_page );

		if ( $page > $total_pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_webhook_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$rows = WebhookStore::get_webhooks(
			$filters + array(
				'orderby' => $request['orderby'],
				'order'   => $request['order'],
				'limit'   => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
			)
		);

		$this->latest_deliveries = DeliveryLog::latest_for( wp_list_pluck( $rows, 'id' ) );

		$data = array();
		foreach ( $rows as $row ) {
			$webhook = $this->hydrate( $row );
			$data[]  = $this->prepare_response_for_collection( $this->prepare_item_for_response( $webhook, $request ) );
		}

		$this->latest_deliveries = null;

		$response = rest_ensure_response( $data );
		$this->add_pagination_headers( $response, $request, $total, $total_pages, $page );

		return $response;
	}

	/**
	 * Build a Webhook from a database row without a second query.
	 *
	 * @param object $row Row from WebhookStore::get_webhooks().
	 * @return Webhook
	 */
	private function hydrate( $row ) {
		$webhook = new Webhook();
		$webhook->set_id( (int) $row->id );
		$webhook->set_props(
			array(
				'name'          => $row->name,
				'trigger_event' => $row->trigger_event,
				'webhook_url'   => $row->webhook_url,
				'http_method'   => $row->http_method,
				'data_type'     => $row->data_type,
				'data_mapping'  => $row->data_mapping,
				'secret'        => isset( $row->secret ) ? $row->secret : '',
				'status'        => $row->status,
				'created_at'    => $row->created_at,
				'updated_at'    => $row->updated_at,
			)
		);

		/** This action is documented in includes/DataStores/WebhookStore.php */
		do_action( 'ohmylms_webhook_loaded', $webhook->get_id(), $webhook );

		return $webhook;
	}

	/**
	 * Add X-WP-Total, X-WP-TotalPages and Link headers.
	 *
	 * @param WP_REST_Response $response    Response.
	 * @param WP_REST_Request  $request     Request.
	 * @param int              $total       Total items.
	 * @param int              $total_pages Total pages.
	 * @param int              $page        Current page.
	 * @return void
	 */
	private function add_pagination_headers( WP_REST_Response $response, WP_REST_Request $request, $total, $total_pages, $page ) {
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $total_pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}
	}

	/**
	 * Get a specific webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$webhook = $this->get_webhook( $request['id'] );
		if ( is_wp_error( $webhook ) ) {
			return $webhook;
		}

		return $this->prepare_item_for_response( $webhook, $request );
	}

	/**
	 * Create a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_webhook_exists', __( 'Cannot create an existing webhook.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$webhook = new Webhook();
		$valid   = $this->apply_request( $webhook, $request );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( '' === $webhook->get_secret( 'edit' ) ) {
			$webhook->set_secret( Webhook::generate_secret() );
		}

		try {
			$webhook->save();
		} catch ( \Exception $e ) {
			return new WP_Error( 'ohmylms_webhook_create_failed', __( 'The webhook could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		// The signing secret is shown in full exactly once, in this response.
		$response = $this->prepare_item( $webhook, $request, true );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $webhook->get_id() ) ) );

		return $response;
	}

	/**
	 * Update a webhook. PUT, PATCH and POST all behave as a partial update.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$webhook = $this->get_webhook( $request['id'] );
		if ( is_wp_error( $webhook ) ) {
			return $webhook;
		}

		$valid = $this->apply_request( $webhook, $request );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		try {
			$webhook->save();
		} catch ( \Exception $e ) {
			return new WP_Error( 'ohmylms_webhook_update_failed', __( 'The webhook could not be updated.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return $this->prepare_item_for_response( $webhook, $request );
	}

	/**
	 * Delete a webhook (and its delivery log).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$webhook = $this->get_webhook( $request['id'] );
		if ( is_wp_error( $webhook ) ) {
			return $webhook;
		}

		$previous = $this->prepare_item_for_response( $webhook, $request )->get_data();

		try {
			$webhook->delete();
		} catch ( \Exception $e ) {
			return new WP_Error( 'ohmylms_webhook_delete_failed', __( 'The webhook could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Handle bulk actions. Always 200; per-id outcomes are in `succeeded` and `failed`.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function bulk_actions( $request ) {
		$action    = $request['action'];
		$succeeded = array();
		$failed    = array();

		foreach ( array_map( 'absint', (array) $request['ids'] ) as $id ) {
			$webhook = $this->get_webhook( $id );
			if ( is_wp_error( $webhook ) ) {
				$failed[] = array(
					'id'      => $id,
					'code'    => $webhook->get_error_code(),
					'message' => $webhook->get_error_message(),
				);
				continue;
			}

			try {
				if ( 'delete' === $action ) {
					$webhook->delete();
				} else {
					$webhook->set_status( 'activate' === $action ? 'active' : 'inactive' );
					$webhook->save();
				}
				$succeeded[] = $id;
			} catch ( \Exception $e ) {
				$failed[] = array(
					'id'      => $id,
					'code'    => 'ohmylms_webhook_bulk_item_failed',
					'message' => __( 'The action could not be applied to this webhook.', 'ohmylms' ),
				);
			}
		}

		return rest_ensure_response(
			array(
				'action'    => $action,
				'succeeded' => $succeeded,
				'failed'    => $failed,
			)
		);
	}

	/**
	 * Get available triggers, methods, data types and statuses.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object.
	 */
	public function get_triggers( $request ) {
		$to_options = static function ( array $map ) {
			$options = array();
			foreach ( $map as $value => $label ) {
				$options[] = array(
					'value' => (string) $value,
					'label' => (string) $label,
				);
			}
			return $options;
		};

		return rest_ensure_response(
			array(
				'triggers'   => $to_options( Webhook::get_available_triggers() ),
				'methods'    => $to_options( Webhook::get_available_methods() ),
				'data_types' => $to_options( Webhook::get_available_data_types() ),
				'statuses'   => $to_options( Webhook::get_available_statuses() ),
			)
		);
	}

	/**
	 * Send a test delivery with a sample payload.
	 *
	 * The delivery goes through WebhookDelivery::send(): SSRF checks, wp_safe_remote_request(),
	 * no redirects, a short timeout. Responds 200 whenever an attempt was made, even if the remote
	 * endpoint failed (see `success` and `error`); 422 when the URL is unsafe; 429 when rate limited.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function test_item( $request ) {
		$limited = $this->consume_test_quota();
		if ( true !== $limited ) {
			return $limited;
		}

		$logged = ! empty( $request['id'] );
		if ( $logged ) {
			$webhook = $this->get_webhook( $request['id'] );
			if ( is_wp_error( $webhook ) ) {
				return $webhook;
			}
		} else {
			if ( empty( $request['webhook_url'] ) ) {
				return new WP_Error( 'ohmylms_webhook_test_target_required', __( 'Provide either an existing webhook `id` or a `webhook_url` to test.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$webhook = new Webhook();
		}

		// Unsaved edits from the request override the stored values for this one delivery.
		foreach ( array( 'webhook_url', 'trigger_event', 'http_method', 'data_type', 'secret' ) as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$webhook->{"set_$field"}( $request[ $field ] );
			}
		}
		if ( isset( $request['data_mapping'] ) ) {
			$mapping = $this->normalize_mapping( $request['data_mapping'] );
			if ( is_wp_error( $mapping ) ) {
				return $mapping;
			}
			$webhook->set_data_mapping( $mapping );
		}

		$valid = WebhookDelivery::validate_url( $webhook->get_webhook_url( 'edit' ) );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$event   = '' !== $webhook->get_trigger_event( 'edit' ) ? $webhook->get_trigger_event( 'edit' ) : 'webhook.test';
		$payload = WebhookDelivery::map_payload( WebhookDelivery::sample_payload( $event ), $webhook->get_data_mapping() );

		$result = WebhookDelivery::send(
			$webhook,
			$payload,
			array(
				'event'   => $event,
				'is_test' => true,
				'log'     => $logged,
				'timeout' => (int) apply_filters( 'ohmylms_webhook_test_timeout', 5, $webhook ),
			)
		);

		$request_body = WebhookDelivery::truncate( (string) $result['request']['body'], WebhookDelivery::REQUEST_BODY_LIMIT );

		return rest_ensure_response(
			array(
				'success'     => (bool) $result['success'],
				'delivery_id' => $result['delivery_id'],
				'request'     => array(
					'url'       => $result['request']['url'],
					'method'    => $result['request']['method'],
					'data_type' => $result['request']['data_type'],
					'event'     => $result['request']['event'],
					'body'      => $request_body[0],
				),
				'response'    => array(
					'status_code' => $result['status_code'],
					'duration_ms' => $result['duration_ms'],
					'body'        => $result['response_body'],
					'truncated'   => $result['response_truncated'],
				),
				'error'       => $result['error'],
			)
		);
	}

	/**
	 * Allow a limited number of test deliveries per user per minute.
	 *
	 * @return true|WP_Error
	 */
	private function consume_test_quota() {
		$limit = (int) apply_filters( 'ohmylms_webhook_test_rate_limit', 10 );
		if ( $limit < 1 ) {
			return true;
		}

		$key   = 'ohmylms_wh_test_' . get_current_user_id();
		$state = get_transient( $key );
		$now   = time();

		if ( ! is_array( $state ) || $state['reset'] <= $now ) {
			$state = array(
				'count' => 0,
				'reset' => $now + MINUTE_IN_SECONDS,
			);
		}

		if ( $state['count'] >= $limit ) {
			$retry = max( 1, $state['reset'] - $now );
			// Same body shape as a WP_Error response, plus the Retry-After header.
			$response = new WP_REST_Response(
				array(
					'code'    => 'ohmylms_webhook_test_rate_limited',
					'message' => __( 'Too many test deliveries. Wait a moment and try again.', 'ohmylms' ),
					'data'    => array(
						'status'      => 429,
						'retry_after' => $retry,
					),
				),
				429
			);
			$response->header( 'Retry-After', (string) $retry );
			return $response;
		}

		++$state['count'];
		set_transient( $key, $state, max( 1, $state['reset'] - $now ) );

		return true;
	}

	/**
	 * Replace the signing secret with a new one and return it once.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function rotate_secret( $request ) {
		$webhook = $this->get_webhook( $request['id'] );
		if ( is_wp_error( $webhook ) ) {
			return $webhook;
		}

		$webhook->set_secret( Webhook::generate_secret() );

		try {
			$webhook->save();
		} catch ( \Exception $e ) {
			return new WP_Error( 'ohmylms_webhook_update_failed', __( 'The webhook could not be updated.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		return $this->prepare_item( $webhook, $request, true );
	}

	/**
	 * List the recent deliveries of a webhook.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_deliveries( $request ) {
		$webhook = $this->get_webhook( $request['id'] );
		if ( is_wp_error( $webhook ) ) {
			return $webhook;
		}

		$page        = max( 1, (int) $request['page'] );
		$per_page    = min( 100, max( 1, (int) $request['per_page'] ) );
		$total       = DeliveryLog::count( $webhook->get_id(), $request['status'] );
		$total_pages = (int) ceil( $total / $per_page );

		if ( $page > $total_pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_webhook_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$items = DeliveryLog::query(
			$webhook->get_id(),
			array(
				'status' => $request['status'],
				'limit'  => $per_page,
				'offset' => ( $page - 1 ) * $per_page,
			)
		);

		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Apply request fields to a webhook object and validate the result.
	 *
	 * Schema-level validation (types, enums, lengths) has already run and answers 400; the checks
	 * here answer 422.
	 *
	 * @param Webhook         $webhook Webhook object.
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	private function apply_request( Webhook $webhook, WP_REST_Request $request ) {
		foreach ( array( 'name', 'trigger_event', 'webhook_url', 'http_method', 'data_type', 'status' ) as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$webhook->{"set_$field"}( $request[ $field ] );
			}
		}

		if ( ! $webhook->get_id() && isset( $request['secret'] ) ) {
			$webhook->set_secret( $request['secret'] );
		}

		if ( isset( $request['data_mapping'] ) ) {
			$mapping = $this->normalize_mapping( $request['data_mapping'] );
			if ( is_wp_error( $mapping ) ) {
				return $mapping;
			}
			$webhook->set_data_mapping( $mapping );
		}

		if ( '' === trim( $webhook->get_name( 'edit' ) ) ) {
			return new WP_Error( 'ohmylms_webhook_invalid_name', __( 'The webhook name cannot be empty.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		if ( isset( $request['webhook_url'] ) || ! $webhook->get_id() ) {
			return WebhookDelivery::validate_url( $webhook->get_webhook_url( 'edit' ) );
		}

		return true;
	}

	/**
	 * Normalise a data mapping to a list of unique `{ key, value }` rows.
	 *
	 * @param mixed $mapping Mapping from the request.
	 * @return array|WP_Error
	 */
	private function normalize_mapping( $mapping ) {
		$rows = array();
		$seen = array();

		foreach ( (array) $mapping as $row ) {
			$key   = isset( $row['key'] ) ? trim( (string) $row['key'] ) : '';
			$value = isset( $row['value'] ) ? trim( (string) $row['value'] ) : '';

			if ( '' === $key || '' === $value ) {
				return new WP_Error( 'ohmylms_webhook_invalid_mapping', __( 'Every data mapping row needs a key and a value.', 'ohmylms' ), array( 'status' => 422 ) );
			}
			if ( isset( $seen[ $key ] ) ) {
				return new WP_Error( 'ohmylms_webhook_invalid_mapping', __( 'Data mapping keys must be unique.', 'ohmylms' ), array( 'status' => 422 ) );
			}

			$seen[ $key ] = true;
			$rows[]       = array(
				'key'   => $key,
				'value' => $value,
			);
		}

		return $rows;
	}

	/**
	 * Prepare a webhook for the REST response (the signing secret is never included).
	 *
	 * @param Webhook         $item    Webhook object.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		return $this->prepare_item( $item, $request, false );
	}

	/**
	 * Prepare a webhook for the REST response.
	 *
	 * @param Webhook         $webhook       Webhook object.
	 * @param WP_REST_Request $request       Request object.
	 * @param bool            $reveal_secret Include the full signing secret (create and rotate only).
	 * @return WP_REST_Response
	 */
	private function prepare_item( Webhook $webhook, WP_REST_Request $request, $reveal_secret ) {
		$id     = (int) $webhook->get_id();
		$fields = $this->get_fields_for_response( $request );
		$data   = array();

		$latest = null === $this->latest_deliveries ? DeliveryLog::latest_for( array( $id ) ) : $this->latest_deliveries;
		$last   = isset( $latest[ $id ] ) ? array_diff_key( $latest[ $id ], array_flip( array( 'request_body', 'response_body', 'url', 'http_method', 'webhook_id' ) ) ) : null;

		$values = array(
			'id'            => $id,
			'name'          => $webhook->get_name(),
			'trigger_event' => $webhook->get_trigger_event(),
			'webhook_url'   => $webhook->get_webhook_url(),
			'http_method'   => $webhook->get_http_method(),
			'data_type'     => $webhook->get_data_type(),
			'data_mapping'  => $webhook->get_data_mapping(),
			'status'        => $webhook->get_status(),
			'secret_hint'   => $webhook->get_secret_hint(),
			'last_delivery' => $last,
			'created_at'    => (string) mysql_to_rfc3339( (string) $webhook->get_created_at() ),
			'updated_at'    => (string) mysql_to_rfc3339( (string) $webhook->get_updated_at() ),
		);
		if ( $reveal_secret ) {
			$values['secret'] = $webhook->get_secret( 'edit' );
		}

		foreach ( $values as $field => $value ) {
			if ( rest_is_field_included( $field, $fields ) ) {
				$data[ $field ] = $value;
			}
		}

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
				'deliveries' => array( 'href' => rest_url( sprintf( '%s/%s/%d/deliveries', $this->namespace, $this->rest_base, $id ) ) ),
			)
		);

		return $response;
	}

	/**
	 * Get the webhook schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$context = array( 'view', 'edit' );

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'webhook',
			'type'       => 'object',
			'properties' => array(
				'id'            => array(
					'description' => __( 'Unique identifier for the webhook.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => $context,
					'readonly'    => true,
				),
				'name'          => array(
					'description' => __( 'Human-readable webhook name.', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 255,
					'context'     => $context,
					'required'    => true,
					'arg_options' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
				'trigger_event' => array(
					'description' => __( 'Event that fires the webhook (see GET /webhooks/triggers).', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_map( 'strval', array_keys( Webhook::get_available_triggers() ) ),
					'context'     => $context,
					'required'    => true,
				),
				'webhook_url'   => array(
					'description' => __( 'Public http(s) endpoint that receives the request.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'uri',
					'maxLength'   => 2048,
					'context'     => $context,
					'required'    => true,
					'arg_options' => array(
						// Trim only: a rewritten URL would hide mistakes; WebhookDelivery::validate_url() rejects bad ones.
						'sanitize_callback' => static function ( $value ) {
							return is_string( $value ) ? trim( $value ) : $value;
						},
					),
				),
				'http_method'   => array(
					'description' => __( 'HTTP method used for the request.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_keys( Webhook::get_available_methods() ),
					'default'     => 'POST',
					'context'     => $context,
				),
				'data_type'     => array(
					'description' => __( 'Request body encoding.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_keys( Webhook::get_available_data_types() ),
					'default'     => 'json',
					'context'     => $context,
				),
				'data_mapping'  => array(
					'description' => __( 'Optional rows that rename and pick payload fields. Empty sends the full payload.', 'ohmylms' ),
					'type'        => 'array',
					'maxItems'    => 50,
					'default'     => array(),
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'key', 'value' ),
						'properties'           => array(
							'key'   => array(
								'description' => __( 'Field name in the request sent to the endpoint.', 'ohmylms' ),
								'type'        => 'string',
								'minLength'   => 1,
								'maxLength'   => 100,
								'pattern'     => '^[A-Za-z_][A-Za-z0-9_.-]*$',
							),
							'value' => array(
								'description' => __( 'Source field in the event payload.', 'ohmylms' ),
								'type'        => 'string',
								'minLength'   => 1,
								'maxLength'   => 100,
								'pattern'     => '^[A-Za-z0-9_.-]+$',
							),
						),
					),
					'context'     => $context,
				),
				'status'        => array(
					'description' => __( 'Whether the webhook is sent.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array_keys( Webhook::get_available_statuses() ),
					'default'     => 'active',
					'context'     => $context,
				),
				'secret'        => array(
					'description' => __( 'Signing secret. Optional on create (generated when omitted). Returned in full only by create and rotate-secret; every other response has secret_hint.', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 16,
					'maxLength'   => 128,
					'context'     => $context,
				),
				'secret_hint'   => array(
					'description' => __( 'Masked signing secret (whsec_**** plus the last four characters). Empty when no secret is set.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $context,
					'readonly'    => true,
				),
				'last_delivery' => array(
					'description' => __( 'Most recent delivery attempt, or null.', 'ohmylms' ),
					'type'        => array( 'object', 'null' ),
					'context'     => $context,
					'readonly'    => true,
					'properties'  => array(
						'id'            => array( 'type' => 'integer' ),
						'delivery_id'   => array( 'type' => 'string' ),
						'event'         => array( 'type' => 'string' ),
						'status'        => array(
							'type' => 'string',
							'enum' => array( 'success', 'failed' ),
						),
						'response_code' => array( 'type' => 'integer' ),
						'duration_ms'   => array( 'type' => 'integer' ),
						'error_code'    => array( 'type' => 'string' ),
						'error_message' => array( 'type' => 'string' ),
						'is_test'       => array( 'type' => 'boolean' ),
						'created_at'    => array( 'type' => 'string' ),
					),
				),
				'created_at'    => array(
					'description' => __( 'Creation time, in the site timezone.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => $context,
					'readonly'    => true,
				),
				'updated_at'    => array(
					'description' => __( 'Last modification time, in the site timezone.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => $context,
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Get the delivery schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_delivery_schema() {
		$context = array( 'view' );
		$string  = static function ( $description ) use ( $context ) {
			return array(
				'description' => $description,
				'type'        => 'string',
				'context'     => $context,
				'readonly'    => true,
			);
		};
		$integer = static function ( $description ) use ( $context ) {
			return array(
				'description' => $description,
				'type'        => 'integer',
				'context'     => $context,
				'readonly'    => true,
			);
		};

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'webhook-delivery',
			'type'       => 'object',
			'properties' => array(
				'id'            => $integer( __( 'Delivery row ID.', 'ohmylms' ) ),
				'webhook_id'    => $integer( __( 'Webhook ID.', 'ohmylms' ) ),
				'delivery_id'   => $string( __( 'UUID sent in the X-OhMyLMS-Delivery header.', 'ohmylms' ) ),
				'event'         => $string( __( 'Event key.', 'ohmylms' ) ),
				'url'           => $string( __( 'URL requested.', 'ohmylms' ) ),
				'http_method'   => $string( __( 'HTTP method used.', 'ohmylms' ) ),
				'status'        => array_merge(
					$string( __( 'success for a 2xx response, failed otherwise.', 'ohmylms' ) ),
					array( 'enum' => array( 'success', 'failed' ) )
				),
				'response_code' => $integer( __( 'HTTP status returned, 0 when there was no response.', 'ohmylms' ) ),
				'duration_ms'   => $integer( __( 'Elapsed milliseconds.', 'ohmylms' ) ),
				'error_code'    => $string( __( 'Error code, empty on success.', 'ohmylms' ) ),
				'error_message' => $string( __( 'Error message, empty on success.', 'ohmylms' ) ),
				'request_body'  => $string( __( 'Request body, truncated to 4000 characters.', 'ohmylms' ) ),
				'response_body' => $string( __( 'Response body, truncated to 2000 characters. Render as text.', 'ohmylms' ) ),
				'is_test'       => array(
					'description' => __( 'Whether this was a test delivery.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $context,
					'readonly'    => true,
				),
				'created_at'    => array_merge(
					$string( __( 'Attempt time, in the site timezone.', 'ohmylms' ) ),
					array( 'format' => 'date-time' )
				),
			),
		);
	}

	/**
	 * Get arguments for the update route: every writable field, optional, without defaults.
	 *
	 * Schema defaults would otherwise be injected into partial updates and silently reset fields.
	 *
	 * @return array
	 */
	private function get_update_args() {
		$args = $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE );
		unset( $args['secret'] );

		foreach ( $args as $name => $arg ) {
			unset( $args[ $name ]['default'], $args[ $name ]['required'] );
		}

		return $args;
	}

	/**
	 * Get arguments for the test route.
	 *
	 * @return array
	 */
	private function get_test_args() {
		$args = $this->get_update_args();
		unset( $args['name'], $args['status'] );

		$args['id']     = array(
			'description' => __( 'Existing webhook to test. Other fields override its stored values for this delivery only.', 'ohmylms' ),
			'type'        => 'integer',
			'minimum'     => 1,
		);
		$args['secret'] = array(
			'description' => __( 'Signing secret to use for this delivery (for a webhook that is not saved yet).', 'ohmylms' ),
			'type'        => 'string',
			'minLength'   => 16,
			'maxLength'   => 128,
		);

		return $args;
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();

		$params['per_page']['default']   = 10;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to webhooks whose name, URL or trigger matches the string.', 'ohmylms' );

		$params['status'] = array(
			'description' => __( 'Limit result set to webhooks with a specific status.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'active', 'inactive' ),
		);

		$params['trigger_event'] = array(
			'description' => __( 'Limit result set to webhooks for one trigger event.', 'ohmylms' ),
			'type'        => 'string',
			'enum'        => array_map( 'strval', array_keys( Webhook::get_available_triggers() ) ),
		);

		$params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'created_at',
			'enum'        => array_keys( WebhookStore::ORDERBY_COLUMNS ),
		);

		$params['order'] = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}

	/**
	 * Get collection parameters for the deliveries route.
	 *
	 * @return array
	 */
	private function get_deliveries_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page' ) ) );

		$params['per_page']['default'] = 10;
		$params['per_page']['maximum'] = 100;
		$params['status']              = array(
			'description' => __( 'Limit result set to deliveries with a specific outcome.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'success', 'failed' ),
		);

		return $params;
	}
}
