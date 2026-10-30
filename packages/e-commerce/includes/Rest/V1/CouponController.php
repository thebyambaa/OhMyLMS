<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Coupon REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Rest\V1;

use OhMyLMS\Commerce\Abstracts\RestController;
use OhMyLMS\Commerce\Data\Coupon;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Coupons under `ohmylms-commerce/v1/coupons`. See docs/api/commerce.md for the contract.
 *
 * Only rules the cart enforces are exposed: discount type and amount, start and expiry date, total
 * and per-user usage limits, individual use and the course scope.
 *
 * @since 1.0.0
 */
class CouponController extends RestController {

	/**
	 * Post type of coupons.
	 */
	const POST_TYPE = 'ohmylms_coupon';

	/**
	 * Discount types understood by the cart (Discounts::apply_coupon).
	 */
	const DISCOUNT_TYPES = array( 'percent', 'flat-rate' );

	/**
	 * Sort keys and the WP_Query orderby behind each.
	 */
	const ORDERBY = array(
		'date'        => 'date',
		'code'        => 'title',
		'usage_count' => 'meta_value_num',
	);

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'coupons';

	/**
	 * Register the routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
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
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the coupon.', 'ohmylms' ),
						'type'        => 'integer',
						'minimum'     => 1,
					),
				),
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Permission: list coupons.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_coupon_cannot_view', __( 'Sorry, you are not allowed to view coupons.', 'ohmylms' ) );
	}

	/**
	 * Permission: read a coupon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_coupon_cannot_view', __( 'Sorry, you are not allowed to view coupons.', 'ohmylms' ) );
	}

	/**
	 * Permission: create a coupon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_coupon_cannot_create', __( 'Sorry, you are not allowed to create coupons.', 'ohmylms' ) );
	}

	/**
	 * Permission: update a coupon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_coupon_cannot_edit', __( 'Sorry, you are not allowed to edit coupons.', 'ohmylms' ) );
	}

	/**
	 * Permission: delete a coupon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->authorize( 'ohmylms_coupon_cannot_delete', __( 'Sorry, you are not allowed to delete coupons.', 'ohmylms' ) );
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found() {
		return new WP_Error( 'ohmylms_coupon_not_found', __( 'Coupon not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Load a coupon post.
	 *
	 * @param int $id Coupon ID.
	 * @return WP_Post|WP_Error
	 */
	private function get_post( $id ) {
		$post = $id ? get_post( (int) $id ) : null;
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'draft' ), true ) ) {
			return $this->not_found();
		}
		return $post;
	}

	/**
	 * Get coupons.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$orderby  = self::ORDERBY[ $request['orderby'] ];
		$args     = array(
			'post_type'              => self::POST_TYPE,
			'post_status'            => 'any' === $request['status'] ? array( 'publish', 'draft' ) : $request['status'],
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => array(
				$orderby => strtoupper( $request['order'] ),
				'ID'     => 'DESC',
			),
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_term_cache' => false,
		);
		if ( 'meta_value_num' === $orderby ) {
			$args['meta_key'] = 'usage_count'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by a single indexed key.
		}
		if ( '' !== (string) $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}

		$query = new WP_Query( $args );
		$total = (int) $query->found_posts;
		if ( $total < 1 && $page > 1 ) {
			// Past the last page WP_Query reports no total; count again without paging.
			$count_args = array_merge(
				$args,
				array(
					'paged'          => 1,
					'posts_per_page' => 1,
				)
			);
			$total      = (int) ( new WP_Query( $count_args ) )->found_posts;
		}
		$total_pages = (int) ceil( $total / $per_page );
		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error( 'ohmylms_coupon_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$items = array();
		foreach ( $query->posts as $post ) {
			$items[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $post, $request ) );
		}
		$response = rest_ensure_response( $items );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $total_pages );
		return $response;
	}

	/**
	 * Get one coupon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		return is_wp_error( $post ) ? $post : $this->prepare_item_for_response( $post, $request );
	}

	/**
	 * Create a coupon.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$coupon = new Coupon();
		$error  = $this->apply_request( $coupon, $request, true );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$coupon->save();
		if ( ! $coupon->get_id() ) {
			return new WP_Error( 'ohmylms_coupon_not_saved', __( 'The coupon could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		$this->apply_status( $coupon->get_id(), $request );
		$post     = get_post( $coupon->get_id() );
		$response = $this->prepare_item_for_response( $post, $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $post->ID ) ) );
		return $response;
	}

	/**
	 * Update a coupon. Only the fields in the request change.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$coupon = new Coupon( (int) $post->ID );
		$error  = $this->apply_request( $coupon, $request, false );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$coupon->save();
		$this->apply_status( $post->ID, $request );
		clean_post_cache( $post->ID );
		return $this->prepare_item_for_response( get_post( $post->ID ), $request );
	}

	/**
	 * Delete a coupon permanently. Past orders keep the code they used.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$post = $this->get_post( (int) $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$previous = $this->prepare_item_for_response( $post, $request )->get_data();
		if ( ! wp_delete_post( $post->ID, true ) ) {
			return new WP_Error( 'ohmylms_coupon_not_deleted', __( 'The coupon could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Check that no other coupon uses a code.
	 *
	 * @param string $code       Formatted code.
	 * @param int    $exclude_id Coupon to ignore (the one being edited).
	 * @return bool
	 */
	private function code_in_use( $code, $exclude_id ) {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'title'          => $code,
				'fields'         => 'ids',
				'posts_per_page' => 5,
				'post__not_in'   => array( (int) $exclude_id ),
				'no_found_rows'  => true,
			)
		);
		return ! empty( $ids );
	}

	/**
	 * Validate the request against the business rules and copy it onto the coupon.
	 *
	 * Schema validation (types, enums, minimums) has already run. This checks rules that need several
	 * fields together, so a rejected request changes nothing.
	 *
	 * @param Coupon          $coupon   Coupon to fill.
	 * @param WP_REST_Request $request  Request.
	 * @param bool            $creating True when creating.
	 * @return true|WP_Error
	 */
	private function apply_request( Coupon $coupon, $request, $creating ) {
		$fail = static function ( $code, $message, $field ) {
			return new WP_Error(
				$code,
				$message,
				array(
					'status' => 422,
					'field'  => $field,
				)
			);
		};

		$type   = $request->has_param( 'discount_type' ) ? $request['discount_type'] : $coupon->get_discount_type( 'edit' );
		$amount = $request->has_param( 'amount' ) ? (float) $request['amount'] : (float) $coupon->get_amount( 'edit' );
		if ( $creating && ! $request->has_param( 'amount' ) ) {
			return $fail( 'ohmylms_coupon_amount_required', __( 'A discount amount is required.', 'ohmylms' ), 'amount' );
		}
		if ( $amount <= 0 ) {
			return $fail( 'ohmylms_coupon_amount_invalid', __( 'The discount amount must be greater than zero.', 'ohmylms' ), 'amount' );
		}
		if ( 'percent' === $type && $amount > 100 ) {
			return $fail( 'ohmylms_coupon_amount_invalid', __( 'A percentage discount cannot exceed 100.', 'ohmylms' ), 'amount' );
		}

		if ( $request->has_param( 'code' ) || $creating ) {
			$code = trim( ohmylms_ecommerce_format_coupon_code( (string) $request['code'] ) );
			if ( '' === $code || strlen( $code ) > 50 ) {
				return $fail( 'ohmylms_coupon_code_invalid', __( 'The coupon code is required and can have at most 50 characters.', 'ohmylms' ), 'code' );
			}
			if ( $this->code_in_use( $code, $coupon->get_id() ) ) {
				return new WP_Error(
					'ohmylms_coupon_code_exists',
					__( 'A coupon with this code already exists.', 'ohmylms' ),
					array(
						'status' => 409,
						'field'  => 'code',
					)
				);
			}
			$coupon->set_code( $code );
		}

		$start   = $request->has_param( 'date_start' ) ? $this->parse_date( $request['date_start'] ) : $coupon->get_date_start( 'edit' );
		$expires = $request->has_param( 'date_expires' ) ? $this->parse_date( $request['date_expires'] ) : $coupon->get_date_expires( 'edit' );
		if ( false === $start || false === $expires ) {
			return $fail( 'ohmylms_coupon_date_invalid', __( 'Dates must be valid ISO 8601 date-times.', 'ohmylms' ), false === $start ? 'date_start' : 'date_expires' );
		}
		$start_ts   = $this->timestamp_of( $start );
		$expires_ts = $this->timestamp_of( $expires );
		if ( $start_ts && $expires_ts && $expires_ts <= $start_ts ) {
			return $fail( 'ohmylms_coupon_date_range', __( 'The expiry date must be after the start date.', 'ohmylms' ), 'date_expires' );
		}

		$limit_per_user = $request->has_param( 'usage_limit_per_user' ) ? (int) $request['usage_limit_per_user'] : (int) $coupon->get_usage_limit_per_user( 'edit' );
		$limit          = $request->has_param( 'usage_limit' ) ? (int) $request['usage_limit'] : (int) $coupon->get_usage_limit( 'edit' );
		if ( $limit && $limit_per_user && $limit_per_user > $limit ) {
			return $fail( 'ohmylms_coupon_usage_limit', __( 'The per-customer limit cannot exceed the total usage limit.', 'ohmylms' ), 'usage_limit_per_user' );
		}

		$scope      = $request->has_param( 'course_id_type' ) ? $request['course_id_type'] : ( $coupon->get_course_id_type( 'edit' ) ? $coupon->get_course_id_type( 'edit' ) : 'all' );
		$course_ids = $request->has_param( 'course_ids' ) ? array_values( array_unique( array_map( 'absint', (array) $request['course_ids'] ) ) ) : array_map( 'absint', (array) $coupon->get_course_ids( 'edit' ) );
		if ( 'selected_course' === $scope ) {
			if ( empty( $course_ids ) ) {
				return $fail( 'ohmylms_coupon_courses_required', __( 'Choose at least one course, or apply the coupon to all courses.', 'ohmylms' ), 'course_ids' );
			}
			foreach ( $course_ids as $course_id ) {
				if ( ! $course_id || OHMYLMS_COURSE_CPT !== get_post_type( $course_id ) ) {
					return $fail( 'ohmylms_coupon_course_invalid', __( 'One of the selected courses does not exist.', 'ohmylms' ), 'course_ids' );
				}
			}
		} else {
			$course_ids = array();
		}

		// All rules passed: write.
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		$coupon->set_date_start( $start_ts ? $start_ts : null );
		$coupon->set_date_expires( $expires_ts ? $expires_ts : null );
		$coupon->set_usage_limit( $limit );
		$coupon->set_usage_limit_per_user( $limit_per_user );
		$coupon->set_course_id_type( $scope );
		$coupon->set_course_ids( $course_ids );
		if ( $request->has_param( 'title' ) ) {
			$coupon->set_title( sanitize_text_field( (string) $request['title'] ) );
		}
		if ( $request->has_param( 'description' ) ) {
			$coupon->set_description( sanitize_textarea_field( (string) $request['description'] ) );
		}
		if ( $request->has_param( 'individual_use' ) ) {
			$coupon->set_individual_use( (bool) $request['individual_use'] );
		}
		return true;
	}

	/**
	 * Parse an incoming date.
	 *
	 * @param mixed $value ISO 8601 string or null.
	 * @return int|null|false Timestamp, null to clear, false when invalid.
	 */
	private function parse_date( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$timestamp = rest_parse_date( (string) $value );
		return $timestamp ? $timestamp : false;
	}

	/**
	 * Timestamp of a stored date.
	 *
	 * @param mixed $value Timestamp, date object or empty.
	 * @return int 0 when unset.
	 */
	private function timestamp_of( $value ) {
		if ( is_object( $value ) && method_exists( $value, 'getTimestamp' ) ) {
			return (int) $value->getTimestamp();
		}
		return $value ? (int) $value : 0;
	}

	/**
	 * Publish or draft the coupon when the request asks for it.
	 *
	 * @param int             $id      Coupon ID.
	 * @param WP_REST_Request $request Request.
	 * @return void
	 */
	private function apply_status( $id, $request ) {
		if ( $request->has_param( 'status' ) && get_post_status( $id ) !== $request['status'] ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => $request['status'],
				)
			);
		}
	}

	/**
	 * Format a stored date for the API (site time, ISO 8601 without offset).
	 *
	 * @param mixed $value Stored date.
	 * @return string|null
	 */
	private function format_date( $value ) {
		$timestamp = $this->timestamp_of( $value );
		return $timestamp ? wp_date( 'Y-m-d\TH:i:s', $timestamp ) : null;
	}

	/**
	 * Prepare a coupon for the response.
	 *
	 * @param WP_Post         $item    Coupon post.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$coupon   = new Coupon( (int) $item->ID );
		$limit    = (int) $coupon->get_usage_limit( 'edit' );
		$each     = (int) $coupon->get_usage_limit_per_user( 'edit' );
		$scope    = $coupon->get_course_id_type( 'edit' );
		$data     = array(
			'id'                   => (int) $item->ID,
			'code'                 => (string) $coupon->get_code( 'edit' ),
			'title'                => (string) $coupon->get_title( 'edit' ),
			'description'          => (string) $coupon->get_description( 'edit' ),
			'status'               => $item->post_status,
			'discount_type'        => in_array( $coupon->get_discount_type( 'edit' ), self::DISCOUNT_TYPES, true ) ? $coupon->get_discount_type( 'edit' ) : 'flat-rate',
			'amount'               => number_format( (float) $coupon->get_amount( 'edit' ), 'percent' === $coupon->get_discount_type( 'edit' ) ? 2 : ohmylms_get_price_decimals(), '.', '' ),
			'date_start'           => $this->format_date( $coupon->get_date_start( 'edit' ) ),
			'date_expires'         => $this->format_date( $coupon->get_date_expires( 'edit' ) ),
			'usage_count'          => (int) $coupon->get_usage_count( 'edit' ),
			'usage_limit'          => $limit ? $limit : null,
			'usage_limit_per_user' => $each ? $each : null,
			'individual_use'       => in_array( $coupon->get_individual_use( 'edit' ), array( true, 'yes', 1, '1' ), true ),
			'course_id_type'       => 'selected_course' === $scope ? 'selected_course' : 'all',
			'course_ids'           => array_values( array_map( 'absint', (array) $coupon->get_course_ids( 'edit' ) ) ),
			'date_created'         => wp_date( 'Y-m-d\TH:i:s', strtotime( $item->post_date_gmt . ' UTC' ) ),
			'currency'             => get_ohmylms_currency(),
		);
		$data     = $this->filter_response_by_context( $data, ! empty( $request['context'] ) ? $request['context'] : 'view' );
		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $item->ID ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
			)
		);
		return $response;
	}

	/**
	 * Collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = parent::get_collection_params();
		unset( $params['context'] );
		$params['context']  = $this->get_context_param( array( 'default' => 'view' ) );
		$params['page']     = array_merge( $params['page'], array( 'minimum' => 1 ) );
		$params['per_page'] = array_merge( $params['per_page'], array( 'default' => 20 ) );
		$params['search']   = array(
			'description'       => __( 'Limit results to coupons whose code, title or description contains this text.', 'ohmylms' ),
			'type'              => 'string',
			'maxLength'         => 100,
			'sanitize_callback' => 'sanitize_text_field',
		);
		$params['status']   = array(
			'description' => __( 'Limit results to one status.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'any',
			'enum'        => array( 'any', 'publish', 'draft' ),
		);
		$params['orderby']  = array(
			'description' => __( 'Sort the collection by this field.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'date',
			'enum'        => array_keys( self::ORDERBY ),
		);
		$params['order']    = array(
			'description' => __( 'Sort direction.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);
		return $params;
	}

	/**
	 * Coupon schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$date = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => array( 'string', 'null' ),
				'format'      => 'date-time',
				'context'     => array( 'view', 'edit' ),
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'coupon',
			'type'       => 'object',
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier for the coupon.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'code'                 => array(
					'description' => __( 'Coupon code. Unique, at most 50 characters.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 50,
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'title'                => array(
					'description' => __( 'Internal label.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 200,
					'context'     => array( 'view', 'edit' ),
				),
				'description'          => array(
					'description' => __( 'Internal description (plain text).', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 1000,
					'context'     => array( 'view', 'edit' ),
				),
				'status'               => array(
					'description' => __( 'publish = usable at checkout, draft = disabled.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'publish', 'draft' ),
					'context'     => array( 'view', 'edit' ),
				),
				'discount_type'        => array(
					'description' => __( 'percent = percentage of the price, flat-rate = fixed amount per cart item.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => self::DISCOUNT_TYPES,
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'amount'               => array(
					'description' => __( 'Discount: a percentage (0-100] or a money amount (> 0), as a decimal string.', 'ohmylms' ),
					'type'        => array( 'string', 'number' ),
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'date_start'           => $date( __( 'First moment the coupon works, in site time.', 'ohmylms' ) ),
				'date_expires'         => $date( __( 'Last moment the coupon works, in site time.', 'ohmylms' ) ),
				'usage_count'          => array(
					'description' => __( 'Times the coupon has been redeemed.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'usage_limit'          => array(
					'description' => __( 'Total redemptions allowed; null is unlimited.', 'ohmylms' ),
					'type'        => array( 'integer', 'null' ),
					'minimum'     => 0,
					'context'     => array( 'view', 'edit' ),
				),
				'usage_limit_per_user' => array(
					'description' => __( 'Redemptions allowed per customer; null is unlimited.', 'ohmylms' ),
					'type'        => array( 'integer', 'null' ),
					'minimum'     => 0,
					'context'     => array( 'view', 'edit' ),
				),
				'individual_use'       => array(
					'description' => __( 'When true, the coupon cannot be combined with other coupons.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => array( 'view', 'edit' ),
				),
				'course_id_type'       => array(
					'description' => __( 'all = every course, selected_course = only course_ids.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'all', 'selected_course' ),
					'context'     => array( 'view', 'edit' ),
				),
				'course_ids'           => array(
					'description' => __( 'Courses the coupon applies to when course_id_type is selected_course.', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'maxItems'    => 200,
					'context'     => array( 'view', 'edit' ),
				),
				'currency'             => array(
					'description' => __( 'ISO 4217 currency of flat-rate amounts.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'date_created'         => array(
					'description' => __( 'When the coupon was created, in site time.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
