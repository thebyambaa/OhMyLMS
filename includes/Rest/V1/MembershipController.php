<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Controller for handling membership REST API endpoints.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Membership;
use OhMyLMS\DataException;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_Query;

/**
 * Controller for handling membership REST API endpoints.
 *
 * This class extends the RESTController abstract class and defines REST API routes
 * for membership-related CRUD operations and many more.
 *
 * @since 1.0.0
 */
class MembershipController extends RestController {

	/**
	 * The base route for membership base endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'membership';

	/**
	 * Check membership permission for Membership controller.
	 */
	public function check_membership_permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Permission check for reading a single membership.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * .
	 * @return true|\WP_Error
	 */
	public function check_membership_read_permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to manage this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return $this->check_object_permission( $request, 'read', 'ohmylms-membership' );
	}

	/**
	 * Permission check for editing a single membership.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * .
	 * @return true|\WP_Error
	 */
	public function check_membership_edit_permission( $request ) {
		return $this->check_object_permission( $request, 'edit', 'ohmylms-membership' );
	}

	/**
	 * Permission check for deleting a single membership.
	 *
	 * @since 1.2.20
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * .
	 * @return true|\WP_Error
	 */
	public function check_membership_delete_permission( $request ) {
		return $this->check_object_permission( $request, 'delete', 'ohmylms-membership' );
	}


	/**
	 * Registers REST API routes for membership operations.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/membership/course-preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'course_preview' ),
				'permission_callback' => array( $this, 'check_membership_permission' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_membership_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_membership_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
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
					'permission_callback' => array( $this, 'check_membership_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the membership.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'check_membership_read_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'check_membership_edit_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_membership_delete_permission' ),
				),
			)
		);
	}

	/**
	 * Check if a given request has access to read an item.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 *
	 * @since 1.0.0
	 */
	public function get_item_permissions_check( $request ) {
		$post = get_post( (int) $request['id'] );

		if ( $post && ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_view', __( 'Sorry, you cannot view this resource.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}


	/**
	 * Get collection of memberships.
	 *
	 * This method handles the retrieval of memberships based on the provided request parameters.
	 * It supports various filters and pagination options to customize the query.
	 *
	 * @param \WP_REST_Request $request The REST request object containing query parameters.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the memberships data or an error.
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
			's'                   => isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '',
			'post_type'           => OHMYLMS_MEMBERSHIP_CPT,
			'post_status'         => isset( $request['post_status'] ) ? sanitize_text_field( $request['post_status'] ) : 'any',
			'meta_query'          => isset( $request['meta_key'] ) && isset( $request['meta_value'] ) ? array(
				array(
					'key'     => sanitize_text_field( $request['meta_key'] ),
					'value'   => sanitize_text_field( $request['meta_value'] ),
					'compare' => 'LIKE',
				),
			) : array(),
		);

		if ( 'any' === $args['post_status'] || ! in_array( $args['post_status'], array( 'draft', 'publish', 'future' ) ) ) {
			$args['post_status'] = array( 'draft', 'publish', 'future' );
		}

		$args['date_query'] = array();

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

		$args       = apply_filters( 'ohmylms_rest_ohmylms_membership_query', $args, $request );
		$query_args = $this->prepare_items_query( $args, $request );

		$posts_query  = new WP_Query();
		$query_result = $posts_query->query( $query_args );

		$posts = array();

		foreach ( $query_result as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				continue;
			}
			$data    = $this->prepare_item_for_response( $post, $request );
			$posts[] = $this->prepare_response_for_collection( $data );
		}

		$page        = (int) $query_args['paged'];
		$total_posts = $posts_query->found_posts;

		if ( 1 > $total_posts && 1 < $page ) {
			unset( $query_args['paged'] );
			$count_query = new WP_Query();
			$count_query->query( $query_args );
			$total_posts = $count_query->found_posts;
		}

		$max_pages = ceil( $total_posts / (int) $query_args['posts_per_page'] );

		$response = rest_ensure_response( $posts );
		$response->header( 'X-WP-Total', (int) $total_posts );
		$response->header( 'X-WP-TotalPages', (int) $max_pages );

		$request_params = $request->get_query_params();
		if ( ! empty( $request_params['filter'] ) ) {
			unset( $request_params['filter']['posts_per_page'] );
			unset( $request_params['filter']['paged'] );
		}
		$base = add_query_arg( $request_params, rest_url( sprintf( '/%s/%s', $this->namespace, $this->rest_base ) ) );

		if ( 1 < $page ) {
			$prev_page = $page - 1;
			if ( $prev_page > $max_pages ) {
				$prev_page = $max_pages;
			}
			$prev_link = add_query_arg( 'page', $prev_page, $base );
			$response->link_header( 'prev', $prev_link );
		}
		if ( $max_pages > $page ) {
			$next_page = $page + 1;
			$next_link = add_query_arg( 'page', $next_page, $base );
			$response->link_header( 'next', $next_link );
		}
		return $response;
	}


	/**
	 * Create a single membership
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			// Translators: %s is replaced with error name.
			return new WP_Error( 'ohmylms_rest_membership_exists', sprintf( __( 'Cannot create existing %s.', 'ohmylms' ), 'membership' ), array( 'status' => 400 ) );
		}

		try {
			$membership_id = $this->save_membership( $request );
			$post          = get_post( $membership_id );
			/**
			 * Fires after a membership is inserted via the REST API.
			 *
			 * @param \WP_Post         $post    The post object for the membership.
			 * @param \WP_REST_Request $request The request object.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_rest_insert_membership', $post, $request );

			// Trigger membership created event for tracking.
			do_action( 'ohmylms_membership_created', $membership_id, $request );

			$this->update_additional_fields_for_object( $post, $request );
			$this->update_post_meta_fields( $post, $request );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );
			$response = rest_ensure_response( $response );
			$response->set_status( 201 );
			return $response;
		} catch ( DataException $e ) {
			return new WP_Error( 400, $e->getMessage(), array( 'status' => $e->getCode() ) );
		}
	}


	/**
	 * Retrieves a single membership by ID.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the membership ID.
	 * @return WP_Error|\WP_HTTP_Response|\WP_REST_Response The response object containing the membership data or an error.
	 *
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$id   = (int) $request['id'];
		$post = get_post( $id );
		if ( empty( $id ) || empty( $post->ID ) || OHMYLMS_MEMBERSHIP_CPT !== $post->post_type ) {
			return new WP_Error( 'ohmylms_rest_invalid_membership_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$data     = $this->prepare_item_for_response( $post, $request );
		$response = rest_ensure_response( $data );

		$response->link_header( 'alternate', get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;
	}


	/**
	 * Delete bulk memberships.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response The response object indicating success or failure.
	 *
	 * @since 1.0.0
	 */
	public function trash_bulk( $request ) {
		$membership_ids = $request->get_param( 'membership_ids' );
		if ( is_array( $membership_ids ) ) {
			$membership_ids = $this->filter_allowed_post_ids( $membership_ids, 'delete', 'ohmylms-membership' );

			if ( is_wp_error( $membership_ids ) ) {
				return $membership_ids;
			}

			foreach ( $membership_ids as $membership_id ) {
				wp_trash_post( $membership_id );
				do_action( 'ohmylms_rest_delete_membership', $membership_id );
			}
			return new \WP_REST_Response( array( 'message' => 'Deleted Successfully' ), 200 );
		}
		return new \WP_REST_Response( array( 'message' => 'Failed to trash the membership.' ), 500 );
	}


	/**
	 * Updates a single membership.
	 *
	 * @param \WP_REST_Request $request The REST request object containing the membership ID and data.
	 * @return WP_Error|\WP_REST_Response|\WP_HTTP_Response The response object containing the updated membership data or an error.
	 *
	 * @since 1.0.0
	 */
	public function update_item( $request ) {
		$post_id = (int) $request['id'];
		if ( empty( $post_id ) || get_post_type( $post_id ) !== OHMYLMS_MEMBERSHIP_CPT ) {
			return new WP_Error( 'ohmylms_rest_membership_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		try {
			$membership_id = $this->save_membership( $request );
			$post          = get_post( $membership_id );

			$this->update_additional_fields_for_object( $post, $request );
			$this->update_post_meta_fields( $post, $request );

			$request->set_param( 'context', 'edit' );
			$response = $this->prepare_item_for_response( $post, $request );

			return rest_ensure_response( $response );

		} catch ( DataException $e ) {
			return new WP_Error( $e->getErrorCode(), $e->getMessage(), $e->getErrorData() );
		}
	}


	/**
	 * Delete membership
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_Error|\WP_HTTP_Response|WP_REST_Response
	 * @since 1.0.0
	 */
	public function delete_item( $request ) {
		// Get membership id.
		$membership_id = isset( $request['id'] ) ? (int) $request['id'] : 0;

		// Check the membership id exist or not.
		if ( ! $membership_id ) {
			return new WP_Error( 'ohmylms_rest_membership_empty_id', __( 'ID is required.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Get existing membership by membership id.
		$membership = ohmylms_get_membership( $membership_id );

		// Check the membership exist or not.
		if ( ! ( $membership instanceof Membership ) ) {
			return new WP_Error( 'ohmylms_rest_membership_invalid_id', __( 'ID is invalid.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Delete the membership.
		$membership->delete();

		/**
		 * Executes the 'ohmylms_rest_delete_membership' action hook.
		 * This hook is triggered when a membership is being deleted via the REST API.
		 *
		 * @param string $membership_id Membership ID.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_rest_delete_membership', $membership_id );

		$response = array(
			'status'  => 'success',
			'message' => __( 'Membership deleted successfully', 'ohmylms' ),
		);
		return rest_ensure_response( $response );
	}

	/**
	 * Prepares the query arguments for fetching items.
	 *
	 * This function filters and constructs the query arguments based on the allowed query variables.
	 * It ensures that only valid query variables are included in the final query arguments.
	 *
	 * @param array                $prepared_args The prepared arguments for the query.
	 * @param WP_REST_Request|null $request The REST request object.
	 * .
	 * @return array The filtered and prepared query arguments.
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
				$query_args[ $var ] = apply_filters( "woocommerce_rest_query_var-{$var}", $prepared_args[ $var ] );
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
	 * Get the allowed query variables for the REST API.
	 *
	 * This method retrieves the list of query variables that are allowed to be used
	 * in REST API requests for memberships. It merges the public and private query variables
	 * and applies filters to allow customization.
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

		$post_type_obj = get_post_type_object( OHMYLMS_MEMBERSHIP_CPT );
		if ( current_user_can( $post_type_obj->cap->edit_posts ) ) {
			$valid_vars = array_merge( $valid_vars, $wp->private_query_vars );
		}
		$rest_valid = array(
			'date_query',
			'ignore_sticky_posts',
			'offset',
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
		 * that can be used in REST API requests for memberships.
		 *
		 * @param array $valid_vars The array of valid query variables.
		 */
		$valid_vars = apply_filters( 'ohmylms_rest_query_vars', $valid_vars );

		return $valid_vars;
	}


	/**
	 * Saves a membership to the database.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function save_membership( $request ) {
		$membership = $this->prepare_item_for_database( $request );
		$this->validate_sale_schedule( $request, $membership );
		return $membership->save();
	}

	/**
	 * Validate calendar sale fields before creating or changing a plan.
	 *
	 * @param WP_REST_Request $request Submitted fields.
	 * @param Membership $membership Existing membership values.
	 * @throws DataException When the date range or free-access end is invalid.
	 */
	private function validate_sale_schedule( $request, $membership ) {
		$sale = isset( $request['sale_price'] ) ? $request['sale_price'] : $membership->get_sale_price( 'edit' );
		$dates = array();
		foreach ( array( 'from', 'to' ) as $side ) {
			$field = 'sale_price_dates_' . $side;
			$value = isset( $request[ $field ] ) ? $request[ $field ] : $membership->{'get_' . $field}();
			$dates[ $side ] = \OhMyLMS\Membership\FreeSale::date( $value );
			if ( ! empty( $value ) && ! $dates[ $side ] ) {
				throw new DataException( 'membership_sale_date', __( 'Choose a valid sale date from the calendar.', 'ohmylms' ), 400 );
			}
		}
		if ( $dates['from'] && $dates['to'] && $dates['from'] > $dates['to'] ) {
			throw new DataException( 'membership_sale_date', __( 'The sale end date must be on or after its start date.', 'ohmylms' ), 400 );
		}
		if ( '' !== (string) $sale && ( ! is_numeric( $sale ) || 0 > (float) $sale ) ) {
			throw new DataException( 'membership_sale_price', __( 'Sale price must be zero or greater.', 'ohmylms' ), 400 );
		}
		if ( '' !== (string) $sale && is_numeric( $sale ) && 0.0 === (float) $sale && ! $dates['to'] ) {
			throw new DataException( 'membership_free_until', __( 'Choose a Free until date for a zero-price sale.', 'ohmylms' ), 400 );
		}
	}


	/**
	 * Update post meta fields for a membership.
	 *
	 * This method updates the meta fields for a given membership post based on the provided request data.
	 *
	 * @param \WP_Post         $post The post object representing the membership.
	 * @param \WP_REST_Request $request The REST request object containing the meta data.
	 * @return bool True on success, false on failure.
	 *
	 * @throws DataException When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function update_post_meta_fields( $post, $request ) {
		$membership = ohmylms_get_membership( $post );
		// Save membership meta fields.
		$membership = $this->set_membership_meta( $membership, $request );

		// Save the membership data.
		$membership->save();
		$products = $membership->get_products();
		// Notify on empty selections too, so removed courses lose this plan's access.
		do_action( 'ohmylms_rest_after_adding_products_on_membership', $products, $membership->get_id() );
		return true;
	}


	/**
	 * Set product meta data for a membership.
	 *
	 * @param Membership      $membership The membership object.
	 * @param WP_REST_Request $request The REST request object containing the meta data.
	 * @return Membership The updated membership object.
	 *
	 * @since 1.0.0
	 * @throws DataException When the operation cannot be completed.
	 */
	protected function set_membership_meta( $membership, $request ) {
		$error = $this->validate_course_selection( $request, $membership );
		if ( is_wp_error( $error ) ) {
			throw new DataException( $error->get_error_code(), $error->get_error_message(), 400 );
		}
		foreach ( array( 'course_categories', 'course_tags', 'course_curriculum', 'course_bundles', 'excluded_courses' ) as $field ) {
			if ( isset( $request[ $field ] ) ) {
				$membership->{'set_' . $field}( $request[ $field ] );
			}
		}
		if ( isset( $request['regular_price'] ) ) {
			$membership->set_regular_price( $request['regular_price'] );
		}
		if ( isset( $request['sale_price'] ) ) {
			$membership->set_sale_price( $request['sale_price'] );
		}
		if ( isset( $request['sale_price_dates_from'] ) ) {
			$membership->set_sale_price_dates_from( \OhMyLMS\Membership\FreeSale::date( $request['sale_price_dates_from'] ) ?: null );
		}
		if ( isset( $request['sale_price_dates_to'] ) ) {
			$membership->set_sale_price_dates_to( \OhMyLMS\Membership\FreeSale::date( $request['sale_price_dates_to'] ) ?: null );
		}
		if ( isset( $request['subscription_length'] ) ) {
			$membership->set_subscription_length( $request['subscription_length'] );
		}
		if ( isset( $request['subscription_period'] ) ) {
			$membership->set_subscription_period( $request['subscription_period'] );
		}
		if ( isset( $request['subscription_period_interval'] ) ) {
			$membership->set_subscription_period_interval( $request['subscription_period_interval'] );
		}
		if ( isset( $request['products'] ) ) {
			$membership->set_products( $request['products'] );
		}
		if ( isset( $request['sign_up_fee'] ) ) {
			$membership->set_sign_up_fee( $request['sign_up_fee'] );
		}
		return $membership;
	}


	/**
	 * Set the cover image for a membership.
	 *
	 * @param Membership $membership The membership object.
	 * @param int        $attachment_id The attachment ID of the image.
	 * @return Membership The updated membership object.
	 * @throws DataException If the attachment ID is not a valid image.
	 *
	 * @since 1.0.0
	 */
	protected function set_membership_cover_image( $membership, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			// Translators: %s is replaced with error name.
			throw new DataException( 'ohmylms_membership_invalid_image_id', sprintf( __( '#%s is an invalid image ID.', 'ohmylms' ), $attachment_id ), 400 );
		}

		$membership->set_thumbnail_id( $attachment_id );

		return $membership;
	}

	/**
	 * Get membership data.
	 *
	 * @param Membership $membership Membership.
	 * @return array
	 *
	 * @since 1.0.0
	 */
	protected function get_membership_data( $membership ) {
		$data = array(
			'id'                           => $membership->get_id(),
			'name'                         => $membership->get_name(),
			'slug'                         => $membership->get_slug(),
			'status'                       => $membership->get_status(),
			'description'                  => $membership->get_description(),
			'price'                        => $membership->get_price(),
			'regular_price'                => $membership->get_regular_price(),
			'sale_price'                   => $membership->get_sale_price(),
			'sale_price_dates_from'        => $membership->get_sale_price_dates_from(),
			'sale_price_dates_to'          => $membership->get_sale_price_dates_to(),
			'date_created'                 => $membership->get_date_created(),
			'date_modified'                => $membership->get_date_modified(),
			'membership_url'               => get_permalink( $membership->get_id() ),
			'sign_up_fee'                  => $membership->get_sign_up_fee(),
			'free_trial'                   => $membership->get_free_trial(),
			'stop_renew'                   => $membership->get_stop_renew(),
			'subscription_length'          => $membership->get_subscription_length(),
			'subscription_period'          => $membership->get_subscription_period(),
			'subscription_period_interval' => $membership->get_subscription_period_interval(),
			'products'                     => $membership->get_products( 'edit' ),
			'course_categories'            => $membership->get_course_categories(),
			'course_tags'                  => $membership->get_course_tags(),
			'course_curriculum'            => $membership->get_course_curriculum(),
			'course_bundles'               => $membership->get_course_bundles(),
			'legacy_rules'                 => $this->legacy_rules( $membership ),
			'excluded_courses'             => $membership->get_excluded_courses(),
			'courses'                      => count( $membership->get_products() ),
			'members'                      => $membership->count_membership_members(),
			'currency'                     => html_entity_decode( get_ohmylms_currency_symbol( get_ohmylms_currency() ) ),
			'currency_pos'                 => get_ohmylms_currency_position(),

		);
		return $data;
	}

	/**
	 * Course rules. Curriculum items and course bundles are the rules to choose from. Legacy category and
	 * tag rules, saved before those were replaced, can only be kept or removed, never extended: members keep
	 * the access they were granted until an administrator removes the rule, yet nothing new is organized by
	 * categories or tags.
	 *
	 * @param mixed $request REST request.
	 * @param mixed $membership Membership.
	 */
	private function validate_course_selection( $request, $membership = null ) {
		$rules = array(
			'course_categories' => 'course_category',
			'course_tags'       => 'course_tag',
			'course_curriculum' => 'curriculum',
			'course_bundles'    => 'bundles',
			'excluded_courses'  => null,
		);
		foreach ( $rules as $field => $kind ) {
			if ( ! isset( $request[ $field ] ) ) {
				continue;
			}
			if ( ! is_array( $request[ $field ] ) ) {
				return new WP_Error( 'membership_selection_invalid', __( 'Course selections must be lists.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			foreach ( $request[ $field ] as $id ) {
				if ( ! is_numeric( $id ) || (int) $id <= 0 || (string) (int) $id !== (string) $id ) {
					return new WP_Error( 'membership_selection_invalid', __( 'A selected course rule or exclusion no longer exists.', 'ohmylms' ), array( 'status' => 400 ) );
				}
				$id = (int) $id;
				if ( 'curriculum' === $kind ) {
					$exists = \OhMyLMS\Curriculum\Items::get( $id ) !== null;
				} elseif ( 'bundles' === $kind ) {
					$exists = \OhMyLMS\Bundles\Bundles::get( $id ) !== null;
				} elseif ( $kind ) {
					$exists = (bool) term_exists( $id, $kind );
				} else {
					$exists = get_post_type( $id ) === 'ohmylms-course';
				}
				if ( ! $exists ) {
					return new WP_Error( 'membership_selection_invalid', __( 'A selected course rule or exclusion no longer exists.', 'ohmylms' ), array( 'status' => 400 ) );
				}
			}
			if ( $membership && in_array( $kind, array( 'course_category', 'course_tag' ), true ) ) {
				$saved = 'course_category' === $kind ? $membership->get_course_categories( 'edit' ) : $membership->get_course_tags( 'edit' );
				if ( array_diff( array_map( 'intval', $request[ $field ] ), array_map( 'intval', (array) $saved ) ) ) {
					return new WP_Error( 'membership_legacy_rules', __( 'Category and tag rules were replaced by curriculum items and course bundles. Existing rules can only be removed.', 'ohmylms' ), array( 'status' => 400 ) );
				}
			}
		}
		if ( isset( $request['products'] ) ) {
			if ( ! is_array( $request['products'] ) ) {
				return new WP_Error( 'membership_selection_invalid', __( 'Courses must be a list.', 'ohmylms' ), array( 'status' => 400 ) );
			}
			foreach ( $request['products'] as $product ) {
				if ( ! is_array( $product ) || empty( $product['id'] ) || ! is_numeric( $product['id'] ) || (string) (int) $product['id'] !== (string) $product['id'] || get_post_type( (int) $product['id'] ) !== 'ohmylms-course' ) {
					return new WP_Error( 'membership_selection_invalid', __( 'A selected course no longer exists.', 'ohmylms' ), array( 'status' => 400 ) );
				}
			}
		}
		return true;
	}

	/** Legacy category and tag rules still saved on a plan, with their names, so they can be shown and removed.
	 *
	 * @param mixed $membership Membership.
	 */
	private function legacy_rules( $membership ) {
		$named = static function ( $ids, $taxonomy ) {
			$rows = array();
			foreach ( (array) $ids as $id ) {
				$term   = taxonomy_exists( $taxonomy ) ? get_term( (int) $id, $taxonomy ) : null;
				$rows[] = array(
					'id'   => (int) $id,
					'name' => $term && ! is_wp_error( $term ) ? $term->name : '#' . (int) $id,
				);
			}
			return $rows;
		};
		return array(
			'categories' => $named( $membership->get_course_categories(), 'course_category' ),
			'tags'       => $named( $membership->get_course_tags(), 'course_tag' ),
		);
	}

	/**
	 * Course preview for Membership controller.
	 *
	 * @param mixed $request REST request.
	 */
	public function course_preview( $request ) {
		$valid = $this->validate_course_selection( $request );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$curriculum   = $request['course_curriculum'] ?? array();
		$bundles      = $request['course_bundles'] ?? array();
		$courses      = \OhMyLMS\Membership\CourseSelection::resolve( $request['products'] ?? array(), $request['course_categories'] ?? array(), $request['course_tags'] ?? array(), $request['excluded_courses'] ?? array(), $curriculum, $bundles );
		$direct       = array_column( $request['products'] ?? array(), 'id' );
		$categories   = $request['course_categories'] ?? array();
		$category_ids = $categories;
		foreach ( $categories as $category ) {
			$children = get_term_children( $category, 'course_category' );
			if ( ! is_wp_error( $children ) ) {
				$category_ids = array_merge( $category_ids, $children );
			}
		}
		// Curriculum rules include everything beneath a selected item; a bundle includes its courses and the courses under its curriculum members.
		$item_scope = array();
		if ( $curriculum ) {
			$parents    = \OhMyLMS\Curriculum\Items::parents();
			$item_scope = array_map( 'intval', $curriculum );
			foreach ( $item_scope as $item ) {
				$item_scope = array_merge( $item_scope, \OhMyLMS\Curriculum\Tree::descendants( $parents, $item ) );
			}
		}
		$bundle_courses = array();
		foreach ( $bundles as $bundle_id ) {
			$bundle = \OhMyLMS\Bundles\Bundles::get( (int) $bundle_id );
			if ( $bundle ) {
				$bundle_courses[] = array(
					'title'   => $bundle['title'],
					'courses' => \OhMyLMS\Curriculum\Placement::course_ids_for_bundles( array( (int) $bundle_id ) ),
				);
			}
		}
		foreach ( $courses as &$course ) {
			$course['reasons'] = array();
			if ( in_array( $course['id'], $direct ) ) {
				$course['reasons'][] = __( 'Individual course', 'ohmylms' );
			}
			foreach ( array(
				'course_category' => $category_ids,
				'course_tag'      => $request['course_tags'] ?? array(),
			) as $taxonomy => $ids ) {
				$terms = wp_get_post_terms( $course['id'], $taxonomy );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					if ( in_array( $term->term_id, $ids ) ) {
						$course['reasons'][] = $term->name;
					}
				}
			}
			if ( $item_scope ) {
				foreach ( \OhMyLMS\Curriculum\Placement::items( $course['id'] ) as $item ) {
					if ( in_array( $item['id'], $item_scope, true ) ) {
										$course['reasons'][] = $item['name'];
					}
				}
			}
			foreach ( $bundle_courses as $bundle ) {
				if ( in_array( (int) $course['id'], $bundle['courses'], true ) ) {
					$course['reasons'][] = $bundle['title'];
				}
			}
		}
		unset( $course );
		return rest_ensure_response(
			array(
				'courses' => $courses,
				'total'   => count( $courses ),
			)
		);
	}

	/**
	 * Prepare links for the request.
	 *
	 * @param mixed $membership Membership.
	 * @param mixed $request REST request.
	 * @return array[]
	 *
	 * @since 1.0.0
	 */
	protected function prepare_links( $membership, $request ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->base, $membership->get_id() ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->base ) ),
			),
		);

		return $links;
	}


	/**
	 * Prepare a single membership for create or update.
	 *
	 * @param mixed $request REST request.
	 * @return bool|Membership|object|WP_Error
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	protected function prepare_item_for_database( $request ) {
		$id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;

		if ( isset( $request['id'] ) ) {
			$membership = ohmylms_get_membership( $id );
		} else {
			$membership = new Membership();
		}

		if ( isset( $request['name'] ) ) {
			$membership->set_name( wp_filter_post_kses( $request['name'] ) );
		}

		if ( isset( $request['description'] ) ) {
			$membership->set_description( wp_filter_post_kses( $request['description'] ) );
		}

		if ( isset( $request['status'] ) ) {
			$membership->set_status( get_post_status_object( $request['status'] ) ? $request['status'] : 'draft' );
		}
		return $membership;
	}


	/**
	 * Prepare a single membership for response.
	 *
	 * @param \WP_Post         $post The post object.
	 * @param \WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $post, $request ) {

		$membership = ohmylms_get_membership( $post );
		$data       = $this->get_membership_data( $membership );
		$response   = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $membership, $request ) );

		/**
		 * Filters the response for the membership in the REST API.
		 *
		 * This filter allows developers to modify the membership response data before it is returned by the REST API.
		 *
		 * @param array $response The response data for the membership.
		 * @param \WP_Post $post The WP_Post object representing the membership.
		 * @param \WP_REST_Request $request The request object containing information about the API request.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'ohmylms_rest_prepare_membership', $response, $post, $request );
	}
}
