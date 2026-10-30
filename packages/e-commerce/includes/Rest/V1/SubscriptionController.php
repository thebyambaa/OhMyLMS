<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Rest\V1;

use OhMyLMS\Commerce\Abstracts\RestController;
use OhMyLMS\Commerce\Data\Subscription;
use function OhMyLMS\Commerce\ecommerce;
use OhMyLMS\Commerce\SubscriptionManager;
use WP_Error;

/**
 * Plugin subscription controller implementation.
 */
class SubscriptionController extends RestController {
	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'subscriptions';

	/**
	 * Post type.
	 *
	 * @var mixed
	 */
	protected $post_type = 'ohmylms-subscription';

	/**
	 * The schema for the subscription item.
	 *
	 * @var array
	 *
	 * This is used to define the structure of the subscription item in the REST API.
	 * It includes properties like student ID, membership ID, original order ID, payment gateway details, and subscription dates.
	 */
	public function check_order_permission() {
		return current_user_can( 'edit_posts' );
	}


	/**
	 * Retrieves the item's schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => $this->post_type,
			'type'       => 'object',
			'properties' => array(
				'id'                            => array(
					'description' => __( 'Unique identifier for the object.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
				// Define properties for each meta field.
				'_student_id'                   => array(
					'description' => __( 'Student ID associated with the subscription.', 'ohmylms' ),
					'type'        => 'integer', // Or string, depending on ID format.
					'context'     => array( 'view', 'edit' ),
				),
				'_membership_id'                => array(
					'description' => __( 'Membership ID associated with the subscription.', 'ohmylms' ),
					'type'        => 'integer', // Or string.
					'context'     => array( 'view', 'edit' ),
				),
				'_original_order_id'            => array(
					'description' => __( 'Original order ID that created the subscription.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'_payment_gateway_id'           => array(
					'description' => __( 'Payment gateway ID used for the subscription.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'_gateway_customer_id'          => array(
					'description' => __( 'Customer ID from the payment gateway.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'_gateway_payment_method_token' => array(
					'description' => __( 'Payment method token from the payment gateway.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'_schedule_start_date'          => array(
					'description' => __( 'Start date of the subscription.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time', // ISO8601.
					'context'     => array( 'view', 'edit' ),
				),
				'_schedule_next_payment_date'   => array(
					'description' => __( 'Next payment due date for the subscription.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time', // ISO8601.
					'context'     => array( 'view', 'edit' ),
				),
				'_schedule_end_date'            => array(
					'description' => __( 'End date for the subscription.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time', // ISO8601.
					'context'     => array( 'view', 'edit' ),
				),
				'_scheduled_renewal_action_id'  => array(
					'description' => __( 'ID of the scheduled action for renewal (e.g., Action Scheduler ID).', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'_status'                       => array(
					'description' => __( 'Status of the subscription.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'active', 'pending', 'on-hold', 'cancelled', 'expired', 'failed' ), // Example statuses.
					'context'     => array( 'view', 'edit' ),
				),
				// Add new fields.
				'_trial_end'                    => array(
					'description' => __( 'End date of the trial period.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'_last_payment_date'            => array(
					'description' => __( 'Date of the last payment.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),
				'_recurring_amount'             => array(
					'description' => __( 'Recurring payment amount.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the publicly-viewable item schema, conforming to JSON Schema.
	 * Used by `register_rest_route` for schema discovery.
	 *
	 * @return array Item schema data.
	 */
	public function get_public_item_schema() {
		$schema = $this->get_item_schema();
		// Filter out properties not available in 'view' context if necessary,
		// but typically get_item_schema handles context.
		return $schema;
	}

	/**
	 * Registers the routes for subscriptions.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
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
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the subscription.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
				),
				// Note: DELETE method is not specified in the requirements.
				// If needed, it can be added here.
				'schema' => array( $this, 'get_public_item_schema' ), // Define schema for documentation.
			)
		);
	}

	/**
	 * Get a collection of subscriptions.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		// Ensure post type is registered.
		if ( ! post_type_exists( $this->post_type ) ) {
			return new WP_Error(
				'rest_post_type_invalid',
				__( 'The post type for subscriptions is not registered.', 'ohmylms' ),
				array( 'status' => 404 )
			);
		}

		$subscription_statuses = array(
			'ohmylms-pending',
			'ohmylms-active',
			'ohmylms-on-hold',
			'ohmylms-pending-cancel',
			'ohmylms-cancelled',
			'ohmylms-expired',
		);

		$args = array(
			'post_type'      => $this->post_type,
			'post_status'    => $subscription_statuses,
			'posts_per_page' => $request['per_page'],
			'paged'          => $request['page'],
			'orderby'        => $request['orderby'],
			'order'          => $request['order'],
			's'              => $request['search'],
			'date_query'     => array(),
			'meta_query'     => array( 'relation' => 'AND' ),
		);

		// Handle 'include' and 'exclude' parameters.
		if ( ! empty( $request['include'] ) ) {
			$args['post__in'] = $request['include'];
		}
		if ( ! empty( $request['exclude'] ) ) {
			$args['post__not_in'] = $request['exclude'];
		}

		// Handle 'offset' (note: WordPress pagination might behave unexpectedly if offset and paged are both used).
		if ( isset( $request['offset'] ) ) {
			$args['offset'] = $request['offset'];
		}

		// Handle date queries ('before', 'after').
		if ( isset( $request['before'] ) ) {
			$args['date_query'][] = array(
				'before'    => $request['before'],
				'inclusive' => true,
			);
		}
		if ( isset( $request['after'] ) ) {
			$args['date_query'][] = array(
				'after'     => $request['after'],
				'inclusive' => true,
			);
		}
		if ( empty( $args['date_query'] ) ) {
			unset( $args['date_query'] );
		}

		/**
		 * Filters the query arguments for a request.
		 *
		 * Enables adding extra arguments or changing existing ones.
		 *
		 * @param array           $args    Key value array of query var to query value.
		 * @param WP_REST_Request $request The request used.
		 */
		$args = apply_filters( "cx_ecommerce_rest_{$this->post_type}_query", $args, $request );

		$query = new \WP_Query();
		$posts = $query->query( $args );

		$total_posts = $query->found_posts;

		if ( 1 > $total_posts ) {
			// Out-of-bounds, run the query again without LIMIT for total count.
			unset( $args['paged'], $args['posts_per_page'] );
			$query_count = new \WP_Query();
			$query_count->query( $args );
			$total_posts = $query_count->found_posts;
		}

		$response_data = array();
		foreach ( $posts as $post ) {
			$data = $this->prepare_item_for_response( $post, $request );
			if ( is_wp_error( $data ) ) {
				// Log error or handle as needed.
				continue;
			}
			$response_data[] = $this->prepare_response_for_collection( $data );
		}

		$response = rest_ensure_response( $response_data );
		$response->header( 'X-WP-Total', (int) $total_posts );
		$posts_per_page = isset( $args['posts_per_page'] ) && 0 < $args['posts_per_page'] ? $args['posts_per_page'] : 1;
		$response->header( 'X-WP-TotalPages', (int) ceil( $total_posts / $posts_per_page ) );
		return $response;
	}

	/**
	 * Prepares a single subscription output for response.
	 * (This is a placeholder, actual implementation will be in prepare_item_for_response)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	protected function prepare_item_for_database( $request ) {
		$id           = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$subscription = new \OhMyLMS\Commerce\Data\Subscription( $id );
		$data_keys    = array(
			'student_id',
			'membership_id',
			'original_order_id',
			'payment_gateway_id',
			'gateway_customer_id',
			'gateway_payment_method_token',
			'start_date',
			'next_payment_date',
			'status',
			'student_name',
			'student_email',
			'student_profile',
			'trial_end',
			'last_payment_date',
			'recurring_amount',
			'billing_period',
		);
		foreach ( $data_keys as $key ) {
			$value = $request[ $key ];
			if ( ! is_null( $value ) ) {
				$setter = 'set_' . $key;
				if ( is_callable( array( $subscription, $setter ) ) ) {
					$subscription->{$setter}( $value );
				}
			}
		}
		return $subscription;
	}

	/**
	 * Updates subscription meta fields.
	 *
	 * @param int             $post_id The post ID.
	 * @param WP_REST_Request $request The request object.
	 */
	protected function update_subscription_meta( $post_id, $request ) {
		$schema      = $this->get_item_schema();
		$meta_fields = array_filter(
			array_keys( $schema['properties'] ),
			function ( $key ) {
				return strpos( $key, '_' ) === 0; // Convention for meta keys.
			}
		);

		foreach ( $meta_fields as $meta_key ) {
			if ( isset( $request[ $meta_key ] ) ) {
				// Sanitize based on schema type if possible, or use general sanitization.
				// For example, if schema type is 'integer', use absint().
				// If 'string' with 'date-time' format, ensure it's a valid date.
				$value = $request[ $meta_key ];

				// Example sanitization (can be more specific based on schema types).
				if ( ! empty( $schema['properties'][ $meta_key ]['type'] ) ) {
					switch ( $schema['properties'][ $meta_key ]['type'] ) {
						case 'integer':
							$value = absint( $value );
							break;
						case 'string':
							if ( ! empty( $schema['properties'][ $meta_key ]['format'] ) && 'date-time' === $schema['properties'][ $meta_key ]['format'] ) {
								// Ensure it's a valid ISO8601 date or convert to one
								// For simplicity, we'll trust validated input from schema arg validation for now.
								// $value = sanitize_text_field( $value ); // Basic sanitization.
							} else {
								$value = sanitize_text_field( $value );
							}
							break;
						default:
							$value = sanitize_text_field( $value ); // Fallback.
					}
				} else {
					$value = sanitize_text_field( $value ); // General fallback.
				}
				update_post_meta( $post_id, $meta_key, $value );
			} elseif ( isset( $schema['properties'][ $meta_key ]['default'] ) && $request->is_creating() ) {
				// Set default value if creating and not provided in request
				// update_post_meta( $post_id, $meta_key, $schema['properties'][ $meta_key ]['default'] );.
			}
		}
	}

	/**
	 * Get a single subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$post_id = (int) $request['id'];
		$post    = get_post( $post_id );

		if ( ! $post || $post->post_type !== $this->post_type ) {
			return new WP_Error(
				"cx_ecommerce_rest_{$this->post_type}_not_found",
				__( 'Subscription not found.', 'ohmylms' ),
				array( 'status' => 404 )
			);
		}

		// Permission check should have already run if configured in register_routes.
		// Additional checks can be done here if needed, e.g., based on post content.

		$data = $this->prepare_item_for_response( $post, $request );
		if ( is_wp_error( $data ) ) {
			return $data; // The error should have status code set in prepare_item_for_response or by a filter.
		}
		$response = rest_ensure_response( $data );

		// Add context parameter.
		if ( 'view' === $request['context'] && is_callable( array( $this, 'add_links' ) ) ) {
			$this->add_links( $response, $post, $request );
		}

		return $response;
	}

	/**
	 * Create a single subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error(
				"cx_ecommerce_rest_{$this->post_type}_exists",
				__( 'Cannot create existing resource.', 'ohmylms' ),
				array( 'status' => 400 )
			);
		}

		// Ensure post type is registered.
		if ( ! post_type_exists( $this->post_type ) ) {
			return new WP_Error(
				'rest_post_type_invalid',
				__( 'The post type for subscriptions is not registered.', 'ohmylms' ),
				array( 'status' => 500 ) // Internal server error, as this should be configured correctly.
			);
		}

		$prepared_post = $this->prepare_item_for_database( $request );
		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		// Set default status if not provided, e.g., 'pending' or 'active'.
		if ( empty( $prepared_post->post_status ) ) {
			// Assuming 'cx_pending' is a registered post status for this CPT.
			// Or use a generic one like 'pending' if not using custom statuses for the CPT itself.
			// For subscriptions, 'active' might be a common starting status if payment is confirmed.
			// Let's assume a meta field `_status` handles the actual subscription status,
			// and the post status is something like 'publish'.
			$prepared_post->post_status = 'publish'; // Or a specific status for new subscriptions if registered.
		}

		// Create the post.
		$post_id = wp_insert_post( (array) $prepared_post, true );

		if ( is_wp_error( $post_id ) ) {
			if ( 'db_insert_error' === $post_id->get_error_code() ) {
				$post_id->add_data( array( 'status' => 500 ) );
			} else {
				$post_id->add_data( array( 'status' => 400 ) );
			}
			return $post_id;
		}

		// Update meta fields.
		$this->update_subscription_meta( $post_id, $request );

		// Set the location header.
		$response = $this->prepare_item_for_response( get_post( $post_id ), $request );
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post_id ) ) );

		/**
		 * Fires after a subscription is created or updated via the REST API.
		 *
		 * @param WP_Post         $post      Post object.
		 * @param WP_REST_Request $request   Request object.
		 * @param boolean         $creating  True when creating a post, false when updating.
		 */
		do_action( "cx_ecommerce_rest_insert_{$this->post_type}", get_post( $post_id ), $request, true );

		return $response;
	}

	/**
	 * Update a single subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$post_id               = (int) $request['id'];
		$previous_subscription = function_exists( 'ohmylms_ecommerce_get_subscription' ) ? ohmylms_ecommerce_get_subscription( $post_id ) : null;
		$get_previous_status   = $previous_subscription ? $previous_subscription->get_status() : null;
		$subscription_id       = $this->update_subscription( $request );
		$current_subscription  = ohmylms_ecommerce_get_subscription( $post_id );

		$post = get_post( $subscription_id );
		$this->update_additional_fields_for_object( $post, $request );
		$response = $this->prepare_item_for_response( $post, $request );

		// Get current status.
		$get_current_status = $current_subscription ? $current_subscription->get_status() : null;
		// If status changed, add a note.
		if ( $get_previous_status && $get_current_status && $get_previous_status !== $get_current_status ) {

			$note = sprintf(
				/* translators: 1: old status 2: new status */
				__( 'Subscription status changed from %1$s to %2$s by %3$s.', 'ohmylms' ),
				$get_previous_status,
				$get_current_status,
				wp_get_current_user()->display_name
			);
			\OhMyLMS\Commerce\SubscriptionManager::add_subscription_note( $post_id, $note );
			// // Add a comment as a note (type 'subscription_note')
			// wp_insert_comment( array(
			// 'comment_post_ID'      => $post_id,
			// 'comment_author'       => __( 'OhMyLMS', 'ohmylms' ),
			// 'comment_author_email' => 'noreply@' . ( isset( $_SERVER['HTTP_HOST'] ) ? str_replace( 'www.', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : 'noreply.com' ),
			// 'comment_content'      => $note,
			// 'comment_agent'        => 'OhMyLMS',
			// 'comment_type'         => 'subscription_note',
			// 'comment_approved'     => 1,
			// ) );
		}

		// Fire actions for status transitions.
		if ( 'active' !== $get_previous_status && 'active' === $get_current_status ) {
			do_action( 'ohmylms_update_subscription_status_to_active', $current_subscription );
		}
		if ( 'cancelled' !== $get_previous_status && 'cancelled' === $get_current_status ) {
			do_action( 'ohmylms_update_subscription_status_to_cancelled', $current_subscription );
		}
		if ( 'expired' !== $get_previous_status && 'expired' === $get_current_status ) {
			do_action( 'ohmylms_update_subscription_status_to_expired', $current_subscription );
		}

		$response = $this->prepare_item_for_response( get_post( $post_id ), $request );
		$response = rest_ensure_response( $response );

		/**
		 * Fires after a subscription is created or updated via the REST API.
		 *
		 * @param WP_Post         $post      Post object.
		 * @param WP_REST_Request $request   Request object.
		 * @param boolean         $creating  False when updating a post.
		 */
		do_action( "cx_ecommerce_rest_insert_{$this->post_type}", get_post( $post_id ), $request, false );

		return $response;
	}

	/**
	 * Update subscription in the database.
	 *
	 * This method is called by the update_item method to save the subscription data.
	 *
	 * @param WP_REST_Request $request The request object containing subscription data.
	 * @return int|WP_Error The ID of the updated subscription on success, or WP_Error on failure.
	 * @since 1.0.0
	 */
	protected function update_subscription( $request ) {
		try {
			$subscription = $this->prepare_item_for_database( $request );
			$subscription->save();
			return $subscription->get_id();
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}


	/**
	 * Checks if a given request has access to read subscriptions.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Checks if a given request has access to read a single subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Checks if a given request has access to create a subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error True if the request has create access, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Checks if a given request has access to update a subscription.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return boolean|WP_Error True if the request has update access, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get structured subscription data for API response.
	 *
	 * @param Subscription $subscription The subscription post object.
	 * @return array Subscription data.
	 */
	protected function get_subscription_data( $subscription ) {
		if ( ! $subscription ) {
			return array();
		}

		$student_id      = $subscription->get_student_id();
		$order_id        = $subscription->get_original_order_id();
		$order           = \ohmylms_ecommerce_get_order( $order_id );
		$membership_id   = $subscription->get_membership_id();
		$membership_name = get_the_title( $membership_id );

		$tax_amount       = \OhMyLMS_Tax_Calculator::get_instance()->calculate_tax( $order->get_tax_rate(), array( 'total' => $subscription->get_recurring_amount() ) );
		$recurring_amount = is_array( $tax_amount ) && isset( $tax_amount['total_with_tax'] ) ? $tax_amount['total_with_tax'] : $subscription->get_recurring_amount();

		$data = array(
			'id'                         => $subscription->get_id(),
			'student_id'                 => $student_id,
			'student_name'               => $subscription->get_student_name(),
			'student_email'              => $subscription->get_student_email(),
			'student_profile'            => $subscription->get_student_profile(),
			'membership_id'              => $subscription->get_membership_id(),
			'billing_period'             => $subscription->get_billing_period(),
			'plan_name'                  => $membership_name,
			'original_order_id'          => $subscription->get_original_order_id(),
			'schedule_start_date'        => $subscription->get_schedule_start_date(),
			'schedule_next_payment_date' => $subscription->get_schedule_next_payment_date(),
			'schedule_end_date'          => $subscription->get_schedule_end_date(),
			'last_payment_date'          => $subscription->get_last_payment_date(),
			'recurring_amount'           => $recurring_amount,
			'status'                     => $subscription->get_status(),
			'related_orders'             => $subscription->get_related_orders(),
			'subscription_notes'         => ohmylms_ecommerce_get_subscription_notes(
				array(
					'subscription_id' => $subscription->get_id(),
				)
			),
		);
		if ( $order ) {
			if ( ecommerce()->gateways() ) {
				$payment_gateways = ecommerce()->gateways()->get_payment_gateways();
			} else {
				$payment_gateways = array();
			}
			$payment_method_title = $order->get_payment_method_title();
			foreach ( $order->get_items() as $item_id => $item ) {
				$post_type = get_post_type( $item->get_course_id() );
				if ( OHMYLMS_COURSE_CPT === $post_type ) {
					$course = $item->get_course();
				} elseif ( OHMYLMS_MEMBERSHIP_CPT === $post_type ) {
					$course = $item->get_membership();
				} else {
					$course = $item->get_course();
				}

				if ( is_object( $course ) ) {
					$course_id = $item->get_course_id();
				}
				if ( $course ) {
					$item_meta = array(
						'key'      => 'membership_id',
						'name'     => $course->get_name(),
						'price'    => ohmylms_format_decimal( $course->get_price(), ohmylms_get_price_decimals() ),
						'quantity' => 1,
					);
					$courses   = method_exists( $course, 'get_products' ) ? $course->get_products() : array();
					if ( ! empty( $courses ) ) {
						$item_meta['courses'] = array();
						foreach ( $courses as $id => $course ) {
							$item_meta['courses'][] = array(
								'id'   => $course['id'],
								'name' => $course['label'],
							);
						}
					}

					$data['line_items'][] = $item_meta;
				}
			}
			$data['coupon_lines'] = array();
			foreach ( $order->get_items( 'coupon' ) as $item_id => $item ) {
				$coupon_line          = array(
					'title'    => $item->get_name(),
					'code'     => $item->get_code(),
					'discount' => ohmylms_format_decimal( $item->get_discount(), ohmylms_get_price_decimals() ),
				);
				$data['coupon_lines'] = $coupon_line;
			}

			$data['payment_gateway'] = array(
				'title' => $payment_method_title,
			);
		}
		return $data;
	}

	/**
	 * Prepares the item for the REST response.
	 *
	 * @param mixed           $item WordPress representation of the item.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$subscription = ohmylms_ecommerce_get_subscription( $item->ID );
		$data         = $this->get_subscription_data( $subscription );
		$response     = rest_ensure_response( $data );
		// Optionally add links, filters, etc. here as needed.
		return $response;
	}

	/**
	 * Retrieves the query params for collections.
	 *
	 * @return array Collection parameters.
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params(); // Gets 'context', 'page', 'per_page', 'search', 'author', 'author_exclude', 'exclude', 'include', 'offset', 'order', 'orderby', 'slug', 'status', 'tax_relation', 'before', 'after'.

		// Remove params we don't use or want to override for subscriptions.
		unset( $params['author'], $params['author_exclude'], $params['slug'], $params['status'] ); // 'status' here refers to post status, we'll use _status meta for subscription status

		// Add our specific filter parameters based on meta fields.
		$meta_fields_for_filtering = array(
			'_student_id'                   => 'integer',
			'_membership_id'                => 'integer',
			'_original_order_id'            => 'integer',
			'_payment_gateway_id'           => 'string',
			'_gateway_customer_id'          => 'string',
			'_gateway_payment_method_token' => 'string',
			'_status'                       => 'string', // For filtering by subscription status meta.
			'_trial_end'                    => 'string',
			'_last_payment_date'            => 'string',
			'_recurring_amount'             => 'string',
			// Dates (_start_date, _next_payment_date) can be filtered using 'before' and 'after' if mapped to post_date, or custom logic.
			// For now, we'll allow direct meta query for them if needed, or rely on 'before'/'after' for post_date.
		);

		foreach ( $meta_fields_for_filtering as $field_name => $type ) {
			$params[ $field_name ] = array(
				'description'       => sprintf( /* translators: %s: text value. */
				__( 'Filter by subscription meta field: %s.', 'ohmylms' ), $field_name ),
				'type'              => $type,
				'sanitize_callback' => ( 'integer' === $type ) ? 'absint' : 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			);
		}

		if ( ! isset( $params['orderby'] ) ) {
			$params['orderby'] = array();
		}
		if ( ! isset( $params['orderby']['enum'] ) ) {
			$params['orderby']['enum'] = array();
		}
		$params['orderby']['enum'] = array_merge( $params['orderby']['enum'], array_keys( $meta_fields_for_filtering ) );

		// Default 'per_page' to 10 as requested.
		$params['per_page']['default'] = 10;

		return $params;
	}

	/**
	 * Helper to get post ID from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return int Post ID, or 0 if not found.
	 */
	protected function get_post_id_from_request( $request ) {
		return (int) $request['id'];
	}
}
