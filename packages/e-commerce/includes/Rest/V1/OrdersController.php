<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Rest\V1;

use OhMyLMS\Commerce\Abstracts\RestController;
use OhMyLMS\Commerce\Data\Order;
use OhMyLMS\Commerce\Data\OrderRefund;
use function OhMyLMS\Commerce\ecommerce;

/**
 * Plugin orders controller implementation.
 */
class OrdersController extends RestController {

	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'orders';

	/**
	 * Check order permission for Orders controller.
	 */
	public function check_order_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register the routes for handling orders.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/trash-bulk/',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'trash_bulk' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => array(
						'force' => array(
							'default'     => false,
							'type'        => 'boolean',
							'description' => __( 'Whether to bypass trash and force deletion.', 'ohmylms' ),
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)' . '/notes',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'create_note' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/stats/',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_order_stats' ),
					'permission_callback' => array( $this, 'check_order_permission' ),
				),
			)
		);
	}

	/**
	 * Retrieve a collection of orders.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object containing the list of orders.
	 *
	 * @since 1.0.0
	 */
	public function get_items( $request ) {
		$args = array(
			'offset'              => isset( $request['offset'] ) ? intval( $request['offset'] ) : 0,
			'order'               => isset( $request['order'] ) ? sanitize_text_field( $request['order'] ) : 'DESC',
			'orderby'             => isset( $request['orderby'] ) ? sanitize_text_field( $request['orderby'] ) : 'date',
			'paged'               => isset( $request['page'] ) ? intval( $request['page'] ) : 1,
			'post__in'            => isset( $request['include'] ) ? array_map( 'intval', (array) $request['include'] ) : array(),
			'post__not_in'        => isset( $request['exclude'] ) ? array_map( 'intval', (array) $request['exclude'] ) : array(),
			'posts_per_page'      => isset( $request['per_page'] ) ? intval( $request['per_page'] ) : 10,
			'name'                => isset( $request['slug'] ) ? sanitize_text_field( $request['slug'] ) : '',
			'post_parent__in'     => isset( $request['parent'] ) ? array_map( 'intval', (array) $request['parent'] ) : array(),
			'post_parent__not_in' => isset( $request['parent_exclude'] ) ? array_map( 'intval', (array) $request['parent_exclude'] ) : array(),
			'post_type'           => 'ohmylms-order',
			'post_status'         => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : 'any',
			'meta_query'          => isset( $request['meta_key'] ) && isset( $request['meta_value'] ) ? array(
				array(
					'key'     => sanitize_text_field( $request['meta_key'] ),
					'value'   => sanitize_text_field( $request['meta_value'] ),
					'compare' => 'LIKE',
				),
			) : array(),
		);

		$args['date_query'] = array();
		// if (isset($request['before'])) {
		// $args['date_query'][0]['before'] = sanitize_text_field($request['before']);
		// }
		// if (!empty($request['start_date'])) {
		// $args['date_query'][0] = array(
		// 'year'  => date('Y', strtotime(sanitize_text_field($request['start_date']))),
		// 'month' => date('m', strtotime(sanitize_text_field($request['start_date']))),
		// 'day'   => date('d', strtotime(sanitize_text_field($request['start_date']))),
		// );
		// $args['paged']  = 1;
		// $args['offset'] = 0;.

		// }
		// if (isset($request['after'])) {
		// $args['date_query'][0]['after'] = sanitize_text_field($request['after']);
		// }

		// Check if 's' is provided, and if so, add the meta_query.
		if ( ! empty( $request['search'] ) ) {
			$search_value = sanitize_text_field( $request['search'] );
			if ( is_numeric( $search_value ) ) {
				$args['post__in'] = array( intval( $search_value ) );
			} else {
				// Otherwise, add it to the meta_query for searching by first name, last name, or email.
				$args['meta_query'][] = array(
					'relation' => 'OR',
					array(
						'key'     => '_first_name',
						'value'   => $search_value,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_last_name',
						'value'   => $search_value,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_email',
						'value'   => $search_value,
						'compare' => 'LIKE',
					),
				);
			}
			$args['paged']  = 1;
			$args['offset'] = 0;
		}

		if ( isset( $request['filter'] ) && is_array( $request['filter'] ) ) {
			$args = array_merge( $args, $request['filter'] );
			unset( $args['filter'] );
		}

		if ( ! empty( $request['date_filter'] ) ) {
			$filter = sanitize_text_field( $request['date_filter'] );
			$today  = current_time( 'Y-m-d' );

			switch ( $filter ) {
				case 'last_30_days':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-d', strtotime( '-30 days' ) ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'current_month':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-01' ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'previous_month':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-01', strtotime( 'first day of last month' ) ),
						'before'    => date( 'Y-m-t', strtotime( 'last month' ) ),
						'inclusive' => true,
					);
					break;

				case 'current_year':
					$args['date_query'][] = array(
						'after'     => date( 'Y-01-01' ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'last_12_months':
					$args['date_query'][] = array(
						'after'     => date( 'Y-m-d', strtotime( '-12 months' ) ),
						'before'    => $today,
						'inclusive' => true,
					);
					break;

				case 'custom':
					$start_date           = isset( $request['start_date'] ) ? sanitize_text_field( $request['start_date'] ) : '';
					$end_date             = isset( $request['end_date'] ) ? sanitize_text_field( $request['end_date'] ) : '';
					$args['date_query'][] = array(
						'after'     => $start_date,
						'before'    => $end_date,
						'inclusive' => true,
					);
					break;
			}
		}

		if ( ! empty( $request['payment_method'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_payment_method', // Adjust key if different.
				'value'   => sanitize_text_field( $request['payment_method'] ),
				'compare' => '=',
			);
		}

		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new \WP_Query();
		$query_result = $posts_query->query( $query_args );
		$posts        = array();

		foreach ( $query_result as $post ) {
			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		$status_count = $this->get_post_status_counts();

		$page        = (int) $query_args['paged'];
		$total_posts = $posts_query->found_posts;

		if ( $total_posts < 1 && $page > 1 ) {
			unset( $query_args['paged'] );
			$count_query = new \WP_Query();
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
		}
		$max_pages = ceil( $total_posts / (int) $query_args['posts_per_page'] );

		if ( isset( $request['orderby'] ) && in_array( $request['orderby'], array( 'id', 'total', 'student_name' ) ) ) {
			$orderby = $request['orderby'];
			$order   = strtoupper( $request['order'] ?? 'ASC' ); // default to ASC if not provided.

			usort(
				$posts,
				function ( $a, $b ) use ( $orderby, $order ) {
					$a_val = $a[ $orderby ];
					$b_val = $b[ $orderby ];

					// For strings like student_name, use strcasecmp to sort case-insensitively.
					if ( is_string( $a_val ) ) {
						$result = strcasecmp( $a_val, $b_val );
					} else {
						$result = $a_val <=> $b_val;
					}

					return 'DESC' === $order ? -$result : $result;
				}
			);
		}

		$response = rest_ensure_response(
			array(
				'orders'        => $posts,
				'status_counts' => $status_count,
				'currency'      => array(
					'currency'     => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
					'currency_pos' => get_ohmylms_currency_position(),
				),
			)
		);

		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );
		$response->header( 'X-WP-Page', (int) $page );

		$request_params = $request->get_query_params();
		if ( ! empty( $request_params['filter'] ) ) {
			unset( $request_params['filter']['posts_per_page'] );
			unset( $request_params['filter']['paged'] );
		}
		return $response;
	}

	/**
	 * Retrieve a single order.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error The response object containing the order data or an error object.
	 *
	 * @since 1.0.0
	 */
	public function get_item( $request ) {

		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( ! empty( $post->post_type ) && 'ohmylms-order' !== $post->post_type ) {
			return new \WP_Error( 'ohmylms_rest_invalid_ohmylms-order_id', __( 'To manipulate order you should use the /orders/&lt;order_id&gt; endpoint.', 'ohmylms' ), array( 'status' => 404 ) );
		} elseif ( empty( $id ) || empty( $post->ID ) ) {
			return new \WP_Error( 'ohmylms_rest_invalid_ohmylms-order_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$response_data = $this->prepare_item_for_response( $post, $request );
		$data          = $response_data->get_data();
		$order         = ohmylms_ecommerce_get_order( $post );
		// get the line items.
		foreach ( $order->get_items() as $item_id => $item ) {
			$item_meta            = array(
				'key'      => 'course_id',
				'name'     => $item->get_name(),
				'price'    => ohmylms_format_decimal( $item->get_total(), ohmylms_get_price_decimals() ),
				'quantity' => 1,
			);
			$data['line_items'][] = $item_meta;
		}

		// get the coupon line items.
		foreach ( $order->get_items( 'coupon' ) as $item_id => $item ) {
			$coupon_line          = array(
				'title'    => $item->get_name(),
				'code'     => $item->get_code(),
				'discount' => ohmylms_format_decimal( $item->get_discount(), ohmylms_get_price_decimals() ),
			);
			$data['coupon_lines'] = $coupon_line;
		}

		foreach ( $order->get_refunds() as $refund ) {
			$refund_id         = $refund->ID;
			$data['refunds'][] = array(
				'id'     => $refund_id,
				'refund' => get_post_meta( $refund_id, '_refund_reason', true ),
				'total'  => '-' . ohmylms_format_decimal( get_post_meta( $refund_id, '_refund_amount', true ), ohmylms_get_price_decimals() ),
			);
		}

		$data['currency'] = array(
			'currency'     => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
			'currency_pos' => get_ohmylms_currency_position(),
		);

		// Save tracking meta data to check this order is already opened or not.
		update_post_meta( $order->get_id(), '_is_open', 'yes' );
		$response_data->set_data( $data );

		$response = rest_ensure_response( $data );
		return $response;
	}

	/**
	 * Get refund for Orders controller.
	 */
	public function get_refund() {
	}

	/**
	 * Update an existing order.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error The response object containing the updated order data or an error object.
	 *
	 * @since 1.0.0
	 */
	public function update_item( $request ) {
		try {
			$post_id = (int) $request['id'];

			if ( empty( $post_id ) ) {
				return new \WP_Error( 'ohmylms_rest_ohmylms-order_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			$previous_order      = ohmylms_ecommerce_get_order( $post_id );
			$get_previous_status = $previous_order->get_status();
			$order_id            = $this->update_order( $request );
			$current_order       = ohmylms_ecommerce_get_order( $order_id );

			if ( is_wp_error( $order_id ) ) {
				return $order_id;
			}

			$post = get_post( $order_id );
			$this->update_additional_fields_for_object( $post, $request );
			$response           = $this->prepare_item_for_response( $post, $request );
			$get_current_status = $current_order->get_status();

			if ( $get_previous_status !== $get_current_status ) {
				$note = sprintf(
					/* translators: 1: old status 2: new status */
					__( 'Order status changed from %1$s to %2$s.', 'ohmylms' ),
					$get_previous_status,
					$get_current_status
				);
				$current_order->add_order_note( $note );
			}

			if ( 'completed' !== $get_previous_status && 'completed' === $get_current_status ) {
				do_action( 'ohmylms_update_order_status_to_completed', $current_order );
			}
			if ( 'cancelled' !== $get_previous_status && 'cancelled' === $get_current_status ) {
				do_action( 'ohmylms_update_order_status_to_cancelled', $current_order );
				$student_data_instance = new \OhMyLMS\DataStores\StudentStore();
				$student_data_instance->delete_enrollment( $current_order->get_student_id(), $current_order->get_id() );
			}

			return rest_ensure_response( $response );
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}

	/**
	 * Delete an existing order.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		$order_id = $request->get_param( 'id' );

		if ( get_post_type( $order_id ) !== 'ohmylms-order' ) {
			return new \WP_REST_Response( array( 'message' => 'Invalid order ID.' ), 400 );
		}
		do_action( 'ohmylms_rest_before_delete_order', $order_id );
		if ( wp_trash_post( $order_id ) ) {
			do_action( 'ohmylms_rest_delete_order', $order_id );
			return new \WP_REST_Response( array( 'message' => 'Order trashed successfully.' ), 200 );
		}

		return new \WP_REST_Response( array( 'message' => 'Failed to trash the order.' ), 500 );
	}


	/**
	 * Delete bulk orders.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function trash_bulk( $request ) {
		$order_ids = $request->get_param( 'order_ids' );
		if ( is_array( $order_ids ) ) {
			foreach ( $order_ids as $order_id ) {
				if ( get_post_type( $order_id ) !== 'ohmylms-order' ) {
					return new \WP_REST_Response( array( 'message' => 'Invalid order ID.' ), 400 );
				}
				do_action( 'ohmylms_rest_before_delete_order', $order_id );
				wp_trash_post( $order_id );
				do_action( 'ohmylms_rest_delete_order', $order_id );
			}
			return new \WP_REST_Response( array( 'message' => 'Order trashed successfully.' ), 200 );
		}
		return new \WP_REST_Response( array( 'message' => 'Failed to trash the order.' ), 500 );
	}


	/**
	 * Update an existing order in the database.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return int|\WP_Error The ID of the updated order or a WP_Error object on failure.
	 *
	 * @since 1.0.0
	 */
	protected function update_order( $request ) {
		try {
			$order = $this->prepare_item_for_database( $request );
			$order->save();
			if ( $order->needs_payment() && true === $request['set_paid'] ) {
				$order->payment_complete( $request['transaction_id'] );
			}
			return $order->get_id();
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}

	/**
	 * Get the count of posts by status.
	 *
	 * This method retrieves the count of posts grouped by their status for the 'ohmylms-order' post type.
	 *
	 * @return array The array containing the count of posts for each status.
	 *
	 * @since 1.0.0
	 */
	private function get_post_status_counts() {
		global $wpdb;

		$post_type      = 'ohmylms-order';
		$prefix         = 'ohmylms-';
		$valid_statuses = array( 'pending', 'processing', 'completed', 'on-hold', 'failed', 'refunded', 'cancelled' );

		$prefixed_statuses = array_map(
			function ( $status ) use ( $prefix ) {
				return $prefix . $status;
			},
			$valid_statuses
		);

		$placeholders = implode( ',', array_fill( 0, count( $prefixed_statuses ), '%s' ) );

		$query = "
			SELECT post_status, COUNT(*) as count
			FROM {$wpdb->posts}
			WHERE post_type = %s
			AND post_status IN ($placeholders)
			GROUP BY post_status
		";

		$query_params = array_merge( array( $post_type ), $prefixed_statuses );
		$results      = $wpdb->get_results( $wpdb->prepare( $query, ...$query_params ) );

		// Format the response.
		$response = array();
		foreach ( $results as $result ) {
			$status     = ucfirst( str_replace( $prefix, '', $result->post_status ) );
			$response[] = array(
				'key'    => str_replace( $prefix, '', $result->post_status ),
				'status' => $status,
				'count'  => (int) $result->count,
			);
		}

		return $response;
	}


	/**
	 * Create a new note for an order.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error The response object containing the created note or an error object.
	 *
	 * @since 1.0.0
	 */
	public function create_note( $request ) {
		$order = ohmylms_ecommerce_get_order( (int) $request['id'] );

		// Create the note.
		$note_id = $order->add_order_note( $request['note'] );

		$note = get_comment( $note_id );

		$response = array(
			'success' => true,
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Get order statistics: total orders, total revenue, average order value.
	 *
	 * @return \WP_REST_Response
	 * @param mixed $request REST request.
	 */
	public function get_order_stats( $request ) {
		$args          = array(
			'post_type'      => 'ohmylms-order',
			'post_status'    => 'ohmylms-completed',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$query         = new \WP_Query( $args );
		$order_ids     = $query->posts;
		$total_orders  = count( $order_ids );
		$total_revenue = 0;
		foreach ( $order_ids as $order_id ) {
			$order          = new \OhMyLMS\Commerce\Data\Order( $order_id );
			$total_revenue += floatval( $order->get_total() );
		}
		$average_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
		$data                = array(
			'total_orders'        => $total_orders,
			'total_revenue'       => round( $total_revenue, 2 ),
			'average_order_value' => round( $average_order_value, 2 ),
		);
		return rest_ensure_response( $data );
	}

	/**
	 * Prepare an order item for database insertion.
	 *
	 * This method prepares the order item data from the request for insertion into the database.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the order data.
	 * @return \OhMyLMS\Commerce\Data\Order The prepared order object.
	 *
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id        = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$order     = new Order( $id );
		$data_keys = array(
			'parent_id',
			'status',
			'order_key',
			'number',
			'currency',
			'date_created',
			'date_modified',
			'student_id',
			'student_name',
			'student_email',
			'address',
			'country',
			'cart_discount',
			'total',
			'subtotal',
			'payment_method',
			'payment_method_title',
			'transaction_id',
			'date_completed',
			'date_created',
			'date_paid',
			'cart_hash',
			'line_items',
			'shipping_lines',
			'fee_lines',
			'coupon_lines',
			'refunds',
			'tax_amount',
			'tax_rate',
		);
		foreach ( $data_keys as $key ) {
			$value = $request[ $key ];
			if ( ! is_null( $value ) ) {
				if ( is_callable( array( $order, "set_{$key}" ) ) ) {
					$order->{"set_{$key}"}( $value );
				}
			}
		}
		return $order;
	}

	/**
	 * Prepare the query arguments for retrieving items.
	 *
	 * @param array                 $prepared_args The prepared arguments for the query.
	 * @param \WP_REST_Request|null $request The REST request object.
	 * @return array The query arguments.
	 *
	 * @since 1.0.0
	 */
	protected function prepare_items_query( $prepared_args = array(), $request = null ) {
		$valid_vars = array_flip( $this->get_allowed_query_vars() );
		$query_args = array();
		foreach ( $valid_vars as $var => $index ) {
			if ( isset( $prepared_args[ $var ] ) ) {
				/**
				 * Filter the query_vars used in `get_items` for the constructed query.
				 *
				 * The dynamic portion of the hook name, $var, refers to the query_var key.
				 *
				 * @param mixed $prepared_args[ $var ] The query_var value.
				 */
				$query_args[ $var ] = apply_filters( "ohmylms_rest_query_var-{$var}", $prepared_args[ $var ] );
			}
		}

		$query_args['ignore_sticky_posts'] = true;

		if ( 'include' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'post__in';
		} elseif ( 'id' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'ID'; // ID must be capitalized.
		} elseif ( 'slug' === $query_args['orderby'] ) {
			$query_args['orderby'] = 'name';
		}

		return $query_args;
	}

	/**
	 * Prepare a single course item for response.
	 *
	 * @param \WP_Post         $post The post object representing the course.
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object containing the course data.
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$order    = ohmylms_ecommerce_get_order( $post );
		$data     = $this->get_order_data( $order );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $order, $request ) );
		return apply_filters( 'ohmylms_rest_prepare_order', $response, $post, $request );
	}

	/**
	 * Retrieve the data for a specific order.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @return array The array containing order data.
	 *
	 * @since 1.0.0
	 */
	protected function get_order_data( $order ) {
		$payment_method_title = $order->get_payment_method_title();
		$payment_method       = $order->get_payment_method();
		$transaction_id       = $order->get_transaction_id();
		if ( ecommerce()->gateways() ) {
			$payment_gateways = ecommerce()->gateways()->get_payment_gateways();
		} else {
			$payment_gateways = array();
		}
		$payment_method_title = $order->get_payment_method_title();
		$student              = \ohmylms_get_student( $order->get_student_id() );
		$related_orders       = $order->get_related_orders();
		$purchased_by         = get_post_meta( $order->get_id(), '_purchased_by', true );
		$post                 = get_post( $order->get_id() );
		$data                 = array(
			'id'                   => $order->get_id(),
			'parent_id'            => $order->get_parent_id(),
			'status'               => $order->get_status(),
			'order_key'            => $order->get_order_key(),
			'number'               => $order->get_order_number(),
			'currency'             => $order->get_currency(),
			'date_created'         => $post->post_date,
			'date_modified'        => $post->post_modified,
			'student_id'           => $order->get_student_id(),
			'student_name'         => $order->get_student_name(),
			'student_email'        => $order->get_email(),
			'student_image'        => $order->get_student_image(),
			'student_profile'      => $order->get_student_profile_url(),
			'student_phone'        => $student ? $student->get_phone() : '',
			'student_whatsapp'     => $student ? $student->get_whatsapp() : '',
			'student_timezone'     => $student ? $student->get_timezone() : '',
			'address'              => $order->get_address(),
			'country'              => $order->get_country(),
			'cart_discount'        => ohmylms_format_decimal( $order->get_cart_discount(), ohmylms_get_price_decimals() ),
			'total'                => ohmylms_format_decimal( $order->get_total(), ohmylms_get_price_decimals() ),
			'formattedTotal'       => $order->get_formatted_order_total(),
			'subtotal'             => ohmylms_format_decimal( $order->get_cart_subtotal(), ohmylms_get_price_decimals() ),
			'payment_method'       => $payment_method,
			'purchased_by'         => $purchased_by ? $purchased_by : 'currency',
			'payment_method_title' => $payment_method_title ? $payment_method_title : 'N/A',
			'transaction_id'       => $transaction_id,
			'date_completed'       => ohmylms_ecommerce_rest_prepare_date_response( $order->get_date_completed(), false ),
			'date_paid'            => ohmylms_ecommerce_rest_prepare_date_response( $order->get_date_paid(), false ),
			'cart_hash'            => $order->get_cart_hash(),
			'order_notes'          => ohmylms_ecommerce_get_order_notes(
				array(
					'order_id' => $order->get_id(),
				)
			),
			'line_items'           => array(),
			'coupon_lines'         => array(),
			'refunds'              => array(),
			'total_orders'         => $student ? $student->get_total_orders() : 0,
			'total_revenue'        => $student ? $student->get_total_revenue() : 0,
			'aov'                  => $student ? $student->get_aov() : 0,
			'related_orders'       => $related_orders,
			'is_renewal_order'     => $order->is_renewal_order(),
			'is_parent_order'      => $order->is_parent_order(),
			'is_normal_order'      => ! $order->is_renewal_order() && ! $order->is_parent_order(),
			'tax_amount'           => ohmylms_format_decimal( $order->get_tax_amount(), ohmylms_get_price_decimals() ),
			'tax_rate'             => $order->get_tax_rate(),
			'is_included_tax'      => \OhMyLMS\Commerce\Includes\Tax\TaxService::get_instance()->prices_include_tax(),
		);
		if ( $transaction_id ) {
			if ( isset( $payment_gateways[ $payment_method ] ) ) {
				$data['transaction_url'] = $payment_gateways[ $payment_method ]->get_transaction_url( $order );
			} else {
				$data['transaction_url'] = '';
			}
		}

		$data['payment_gateway_meta'] = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_payment_gateway_meta( $order ) : array();
		return $data;
	}

	/**
	 * Prepare links for the order item.
	 *
	 * This method generates the self and collection links for the order item.
	 *
	 * @param \OhMyLMS\Commerce\Data\Order $order The order object.
	 * @param \WP_REST_Request             $request The REST request object.
	 * @return array The array containing the links for the order item.
	 *
	 * @since 1.0.0
	 */
	protected function prepare_links( $order, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $order->get_id() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->base ) ),
			),
		);

		return $links;
	}

	/**
	 * Get the allowed query variables for the REST API.
	 *
	 * This method retrieves the list of query variables that are allowed
	 * to be used in REST API requests for courses.
	 *
	 * @return array The array of allowed query variables.
	 *
	 * @since 1.0.0
	 */
	protected function get_allowed_query_vars() {
		global $wp;

		/**
		 * Filter the publicly allowed query vars.
		 *
		 * Allows adjusting of the default query vars that are made public.
		 *
		 * @param array  Array of allowed WP_Query query vars.
		 */
		$valid_vars = apply_filters( 'query_vars', $wp->public_query_vars );

		$post_type_obj = get_post_type_object( OHMYLMS_COURSE_CPT );
		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			$valid_vars = array_merge( $valid_vars, $wp->private_query_vars );
		}
		$rest_valid = array(
			'date_query',
			'ignore_sticky_posts',
			'offset',
			'post_status',
			'post__in',
			'post__not_in',
			'post_parent',
			'post_parent__in',
			'post_parent__not_in',
			'posts_per_page',
			'meta_query',
			'tax_query',
			'meta_key',
			'meta_value',
			'meta_compare',
			'meta_value_num',
		);
		$valid_vars = array_merge( $valid_vars, $rest_valid );

		/**
		 * Filter the valid query variables for the REST API.
		 *
		 * This filter allows developers to modify the list of valid query variables
		 * that can be used in REST API requests for courses.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
	}
}
