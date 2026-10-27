<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\Rest\V1;

use OhMyLMS\Commerce\Abstracts\RestController;
use OhMyLMS\Commerce\Data\Coupon;
use OhMyLMS\Commerce\EcommerceDateTime;

/**
 * Plugin coupon controller implementation.
 */
class CouponController extends RestController {

	/**
	 * Base.
	 *
	 * @var mixed
	 */
	protected $base = 'coupons';

	/**
	 * Check coupon permission for Coupon controller.
	 */
	public function check_coupon_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Register routes for Coupon controller.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_coupon_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_coupon_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_coupon_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the coupon.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_coupon_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_coupon_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item_status' ),
					'permission_callback' => array( $this, 'check_coupon_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/status',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the coupon.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item_status' ),
					'permission_callback' => array( $this, 'check_coupon_permission' ),
				),
			)
		);
	}

	/**
	 * Get items for Coupon controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function get_items( $request ) {
		$args = array(
			'post_type'      => 'ohmylms_coupon',
			'posts_per_page' => ! empty( $request['per_page'] ) ? intval( $request['per_page'] ) : -1,
			'paged'          => ! empty( $request['page'] ) ? intval( $request['page'] ) : 1,
			'post_status'    => array( 'draft', 'publish' ),
		);

		// Add meta_query for title search if provided.
		if ( ! empty( $request['search'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'title',
					'value'   => esc_html( $request['search'] ),
					'compare' => 'LIKE',
				),
			);
		}

		$query   = new \WP_Query( $args );
		$coupons = array();

		foreach ( $query->posts as $post ) {
			$response  = $this->prepare_item_for_response( $post, $request );
			$coupons[] = $response->get_data();
		}

		$rest_response = rest_ensure_response( $coupons );

		// Add the total found posts header.
		$rest_response->header( 'X-WP-Total', (int) $query->found_posts );

		return $rest_response;
	}

	/**
	 * Create a new coupon item.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_Error|\WP_REST_Response The response object containing the result of the coupon creation or an error.
	 *
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			/* translators: %s: post type */
			return new \WP_Error( 'ohmylms_rest_ohmylms_coupon_exists', __( 'Cannot create existing %s.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$coupon_id = $this->save_coupon( $request );
		if ( is_wp_error( $coupon_id ) ) {
			return $coupon_id;
		}

		$post = get_post( $coupon_id );

		$this->update_additional_fields_for_object( $post, $request );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ) );

		return $response;
	}

	/**
	 * Update item status for Coupon controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function update_item_status( $request ) {
		$id     = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$status = isset( $request['status'] ) ? sanitize_key( $request['status'] ) : 'publish';

		if ( ! $id || empty( $status ) ) {
			return new \WP_Error( 'ohmylms_rest_invalid_params', __( 'Invalid coupon ID or status.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$post = get_post( $id );
		if ( ! $post || 'ohmylms_coupon' !== $post->post_type ) {
			return new \WP_Error( 'ohmylms_rest_coupon_not_found', __( 'Coupon not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$updated = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		// Return the updated coupon data.
		$post     = get_post( $id );
		$response = $this->prepare_item_for_response( $post, $request );
		return rest_ensure_response( $response );
	}


	/**
	 * Create a new coupon item.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_Error|\WP_REST_Response The response object containing the result of the coupon creation or an error.
	 *
	 * @since 1.0.0
	 */
	public function update_item( $request ) {
		if ( empty( $request['id'] ) ) {
			/* translators: %s: post type */
			return new \WP_Error( 'ohmylms_rest_ohmylms_coupon_not_exists', __( 'Cannot update %s.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$coupon_id = $this->save_coupon( $request );
		if ( is_wp_error( $coupon_id ) ) {
			return $coupon_id;
		}

		$post = get_post( $coupon_id );

		$this->update_additional_fields_for_object( $post, $request );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $response );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ) );

		return $response;
	}

	/**
	 * Get a coupon item.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_Error|\WP_REST_Response The response object containing the result of the coupon creation or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( ! $id ) {
			return new \WP_Error( 'ohmylms_rest_invalid_id', __( 'Invalid coupon ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$post = get_post( $id );
		if ( ! $post || 'ohmylms_coupon' !== $post->post_type ) {
			return new \WP_Error( 'ohmylms_rest_coupon_not_found', __( 'Coupon not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$response = $this->prepare_item_for_response( $post, $request );
		return rest_ensure_response( $response );
	}

	/**
	 * Delete a coupon item.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_Error|\WP_REST_Response The response object containing the result of the coupon deletion or an error.
	 *
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		$ids = isset( $request['ids'] ) ? $request['ids'] : array();
		if ( ! $ids ) {
			return new \WP_Error( 'ohmylms_rest_invalid_id', __( 'Invalid coupon ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || 'ohmylms_coupon' !== $post->post_type ) {
				return new \WP_Error( 'ohmylms_rest_coupon_not_found', __( 'Coupon not found.', 'ohmylms' ), array( 'status' => 404 ) );
			}

			$deleted = wp_delete_post( $id, true );
			if ( ! $deleted ) {
				return new \WP_Error( 'ohmylms_rest_cannot_delete', __( 'Could not delete coupon.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		}

		return rest_ensure_response(
			array(
				'deleted' => true,
			)
		);
	}

	/**
	 * Save a coupon item to the database.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the coupon data.
	 * @return int|\WP_Error The ID of the saved coupon or a WP_Error object on failure.
	 *
	 * @since 1.0.0
	 */
	protected function save_coupon( $request ) {
		try {
			$coupon = $this->prepare_item_for_database( $request );
			if ( is_wp_error( $coupon ) ) {
				return $coupon;
			}
			$coupon->save();
			return $coupon->get_id();
		} catch ( \Exception $e ) {
			return new \WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}

	/**
	 * Prepare a coupon item for the database.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the coupon data.
	 * @return \OhMyLMS\Commerce\Data\Coupon|\WP_Error The coupon object or a WP_Error object on failure.
	 *
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id        = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		$coupon    = new Coupon( $id );
		$schema    = $this->get_item_schema();
		$data_keys = array_keys( $schema['properties'] );

		// Handle all writable props.
		foreach ( $data_keys as $key ) {
			$value = $request[ $key ];
			if ( ! is_null( $value ) ) {
				switch ( $key ) {
					case 'code':
						$coupon_code  = ohmylms_ecommerce_format_coupon_code( $value );
						$id           = $coupon->get_id() ? $coupon->get_id() : 0;
						$id_from_code = ohmylms_ecommerce_get_coupon_id_by_code( $coupon_code, $id );

						if ( $id_from_code ) {
							return new \WP_Error( 'ohmylms_rest_coupon_code_already_exists', __( 'The coupon code already exists', 'ohmylms' ), array( 'status' => 400 ) );
						}
						$coupon->set_code( $coupon_code );
						break;
					case 'description':
						$coupon->set_description( wp_filter_post_kses( $value ) );
						break;
					case 'title':
						$coupon->set_title( esc_html( $value ) );
						break;
					case 'course_id_type':
						$coupon->set_course_id_type( esc_html( $value ) );
						break;
					case 'date_expires':
						// Handle DateTime array format.
						if ( is_array( $value ) && isset( $value['date'] ) ) {
							$coupon->set_date_expires( $value['date'] );
						} else {
							$coupon->set_date_expires( $value );
						}
						break;
					case 'date_start':
						// Handle DateTime array format.
						if ( is_array( $value ) && isset( $value['date'] ) ) {
							$coupon->set_date_start( $value['date'] );
						} else {
							$coupon->set_date_start( $value );
						}
						break;
					default:
						if ( is_callable( array( $coupon, "set_{$key}" ) ) ) {
							$coupon->{"set_{$key}"}( $value );
						}
						break;
				}
			}
		}

		return $coupon;
	}

	/**
	 * Prepare a coupon item for the REST API response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object containing the coupon data.
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {
		$coupon = new Coupon( (int) $post->ID );
		$_data  = $coupon->get_data();

		$format_decimal = array( 'amount', 'minimum_amount', 'maximum_amount' );
		$format_date    = array( 'date_created', 'date_modified', 'date_start', 'date_expires' );
		$format_null    = array( 'usage_limit', 'usage_limit_per_user' );

		// Format decimal values.
		foreach ( $format_decimal as $key ) {
			$_data[ $key ] = ohmylms_format_decimal( $_data[ $key ], 2 );
		}

		// Format null values.
		foreach ( $format_null as $key ) {
			$_data[ $key ] = $_data[ $key ] ? $_data[ $key ] : null;
		}

		$data = array(
			'id'                   => $_data['id'],
			'title'                => isset( $_data['title'] ) ? $_data['title'] : '',
			'course_id_type'       => isset( $_data['course_id_type'] ) ? $_data['course_id_type'] : 'all',
			'code'                 => $_data['code'],
			'date_created'         => $_data['date_created'],
			'date_modified'        => $_data['date_modified'],
			'discount_type'        => $_data['discount_type'],
			'description'          => $_data['description'],
			'amount'               => $_data['amount'],
			'date_expires'         => $_data['date_expires'],
			'date_start'           => isset( $_data['date_start'] ) ? $_data['date_start'] : null,
			'usage_count'          => $_data['usage_count'],
			'individual_use'       => $_data['individual_use'],
			'course_ids'           => $_data['course_ids'],
			'excluded_course_ids'  => isset( $_data['excluded_course_ids'] ) ? $_data['excluded_course_ids'] : array(),
			'usage_limit'          => $_data['usage_limit'],
			'usage_limit_per_user' => $_data['usage_limit_per_user'],
			'exclude_sale_items'   => $_data['exclude_sale_items'],
			'minimum_amount'       => $_data['minimum_amount'],
			'maximum_amount'       => $_data['maximum_amount'],
			'email_restrictions'   => isset( $_data['email_restrictions'] ) ? ( $_data['email_restrictions'] ) : array(),
			'used_by'              => $_data['used_by'],
			'status'               => $_data['status'],
		);

		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->add_additional_fields_to_object( $data, $request );
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $post, $request ) );
		return $response;
	}


	/**
	 * Prepare links for the REST API response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array The prepared links.
	 */
	protected function prepare_links( $post, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '/%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);
		return $links;
	}


	/**
	 * Get the schema for a single coupon item.
	 *
	 * @return array The schema for a REST API coupon item.
	 *
	 * @since 1.0.0
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'Coupon Schema',
			'type'       => 'object',
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier for the object.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'code'                 => array(
					'description' => __( 'Coupon code.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => '',
					'context'     => array( 'view', 'edit' ),
				),
				'title'                => array(
					'description' => __( 'Coupon title.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => '',
					'context'     => array( 'view', 'edit' ),
				),
				'amount'               => array(
					'description' => __( 'Discount amount.', 'ohmylms' ),
					'type'        => 'number',
					'default'     => 0,
					'context'     => array( 'view', 'edit' ),
				),
				'status'               => array(
					'description' => __( 'Status of the coupon.', 'ohmylms' ),
					'type'        => array( 'null', 'string' ),
					'default'     => null,
					'context'     => array( 'view', 'edit' ),
				),
				'date_created'         => array(
					'description' => __( "The date the coupon was created, in the site's timezone.", 'ohmylms' ),
					'type'        => array( 'null', 'string' ),
					'format'      => 'date-time',
					'default'     => null,
					'context'     => array( 'view', 'edit' ),
				),
				'date_modified'        => array(
					'description' => __( "The date the coupon was last modified, in the site's timezone.", 'ohmylms' ),
					'type'        => array( 'null', 'string' ),
					'format'      => 'date-time',
					'default'     => null,
					'context'     => array( 'view', 'edit' ),
				),
				'date_expires'         => array(
					'description' => __( "The date the coupon expires, in the site's timezone.", 'ohmylms' ),
					'type'        => array( 'null', 'string' ),
					'format'      => 'date-time',
					'default'     => null,
					'context'     => array( 'view', 'edit' ),
				),
				'date_start'           => array(
					'description' => __( "The date the coupon starts, in the site's timezone.", 'ohmylms' ),
					'type'        => array( 'null', 'string' ),
					'format'      => 'date-time',
					'default'     => null,
					'context'     => array( 'view', 'edit' ),
				),
				'discount_type'        => array(
					'description' => __( 'Type of discount.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'fixed_cart', 'percent', 'fixed_course', 'other' ),
					'default'     => 'fixed_cart',
					'context'     => array( 'view', 'edit' ),
				),
				'description'          => array(
					'description' => __( 'Description of the coupon.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => '',
					'context'     => array( 'view', 'edit' ),
				),
				'usage_count'          => array(
					'description' => __( 'Number of times the coupon has been used.', 'ohmylms' ),
					'type'        => 'integer',
					'default'     => 0,
					'context'     => array( 'view', 'edit' ),
				),
				'individual_use'       => array(
					'description' => __( 'If true, the coupon can only be used individually.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'no',
					'context'     => array( 'view', 'edit' ),
				),
				'exclude_sale_items'   => array(
					'description' => __( 'If true, this coupon will not be applied to items that have sale prices.', 'ohmylms' ),
					'type'        => 'string',
					'default'     => 'no',
					'context'     => array( 'view', 'edit' ),
				),
				'course_ids'           => array(
					'description' => __( 'List of course IDs the coupon can be used on.', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'default'     => array(),
					'context'     => array( 'view', 'edit' ),
				),
				'excluded_course_ids'  => array(
					'description' => __( 'List of course IDs the coupon cannot be used on.', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'default'     => array(),
					'context'     => array( 'view', 'edit' ),
				),
				'usage_limit'          => array(
					'description' => __( 'How many times the coupon can be used in total.', 'ohmylms' ),
					'type'        => 'integer',
					'default'     => 0,
					'context'     => array( 'view', 'edit' ),
				),
				'usage_limit_per_user' => array(
					'description' => __( 'How many times the coupon can be used per customer.', 'ohmylms' ),
					'type'        => 'integer',
					'default'     => 0,
					'context'     => array( 'view', 'edit' ),
				),
			),
		);
		return $schema;
	}
}
