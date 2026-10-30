<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MembershipController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Data\Membership;
use OhMyLMS\Membership\CourseSelection;
use OhMyLMS\Membership\FreeSale;
use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * MembershipController class.
 *
 * REST API for membership plans under `ohmylms/v1/memberships`. See docs/api/memberships.md for the
 * contract. Billing, renewals and gateways are outside this controller: it only edits the plan.
 *
 * Authentication is WordPress core's: cookie authentication requires the `X-WP-Nonce` header
 * (verified by rest_cookie_check_errors()); application passwords also work. Every route requires
 * the CAPABILITY below.
 *
 * @since 1.0.0
 */
class MembershipController extends RestController {

	/**
	 * Capability required by every membership route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Maximum individually selected (or excluded) courses per plan.
	 */
	const MAX_COURSES = 500;

	/**
	 * Maximum curriculum items per plan.
	 */
	const MAX_CURRICULUM_ITEMS = 200;

	/**
	 * Largest accepted price.
	 */
	const MAX_PRICE = 99999999.99;

	/**
	 * Membership statuses that count as having members.
	 */
	const ACTIVE_MEMBER_STATUSES = array( 'enrolled', 'pending' );

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for membership endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'memberships';

	/**
	 * Enrolled member counts for the plans of the current collection page, keyed by plan ID.
	 *
	 * @var array<int,int>
	 */
	private $member_counts = array();

	/**
	 * Register the routes for the membership endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[\d]+)';
		$id   = array(
			'description' => __( 'Unique identifier for the membership plan.', 'ohmylms' ),
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
			$item . '/duplicate',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'duplicate_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/members',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_members' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_members_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/options',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_options' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/courses',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_courses' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_course_collection_params(),
				),
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

		return true;
	}

	/**
	 * Check if the current user can list memberships (also options and the course search).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_membership_cannot_view', __( 'Sorry, you are not allowed to view membership plans.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can read a single membership (also its members).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_membership_cannot_view', __( 'Sorry, you are not allowed to view membership plans.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can create (or duplicate) a membership.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_membership_cannot_create', __( 'Sorry, you are not allowed to create membership plans.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can update a membership.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_membership_cannot_edit', __( 'Sorry, you are not allowed to edit membership plans.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can delete a membership.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_membership_cannot_delete', __( 'Sorry, you are not allowed to delete membership plans.', 'ohmylms' ) );
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found_error() {
		return new WP_Error( 'ohmylms_membership_not_found', __( 'Membership plan not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Load a plan or return a 404 error.
	 *
	 * @param int $id Membership post ID.
	 * @return Membership|WP_Error
	 */
	private function get_membership( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || OHMYLMS_MEMBERSHIP_CPT !== $post->post_type || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
			return $this->not_found_error();
		}

		try {
			return new Membership( $post );
		} catch ( \Exception $e ) {
			return $this->not_found_error();
		}
	}

	/**
	 * Get memberships.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$orderby  = array(
			'id'         => 'ID',
			'name'       => 'title',
			'status'     => 'post_status',
			'created_at' => 'date',
			'updated_at' => 'modified',
		);
		$statuses = 'all' === $request['status'] ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : array( (string) $request['status'] );

		$args = array(
			'post_type'              => OHMYLMS_MEMBERSHIP_CPT,
			'post_status'            => $statuses,
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => $orderby[ $request['orderby'] ],
			'order'                  => strtoupper( (string) $request['order'] ),
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);
		if ( '' !== (string) $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}
		if ( ! empty( $request['include'] ) ) {
			$args['post__in'] = array_map( 'absint', (array) $request['include'] );
		}

		/**
		 * Filters the WP_Query arguments used by GET /memberships.
		 *
		 * @param array            $args    Query arguments.
		 * @param WP_REST_Request  $request The request.
		 */
		$args = apply_filters( 'ohmylms_rest_ohmylms_membership_query', $args, $request );

		$query = new WP_Query( $args );
		$total = $this->found_posts( $query, $args );
		$pages = (int) ceil( $total / $per_page );

		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_membership_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$this->member_counts = $this->count_members( wp_list_pluck( $query->posts, 'ID' ) );
		$this->prime_course_posts( $query->posts );

		$data = array();
		foreach ( $query->posts as $post ) {
			$membership = $this->get_membership( $post->ID );
			if ( is_wp_error( $membership ) ) {
				continue;
			}
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $membership, $request ) );
		}
		$this->member_counts = array();

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		$base = add_query_arg( urlencode_deep( $request->get_query_params() ), rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $page > 1 ) {
			$response->link_header( 'prev', add_query_arg( 'page', $page - 1, $base ) );
		}
		if ( $page < $pages ) {
			$response->link_header( 'next', add_query_arg( 'page', $page + 1, $base ) );
		}

		return $response;
	}

	/**
	 * Get a specific membership plan.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$membership = $this->get_membership( $request['id'] );
		if ( is_wp_error( $membership ) ) {
			return $membership;
		}

		return $this->prepare_item_for_response( $membership, $request );
	}

	/**
	 * Create a membership plan.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'ohmylms_membership_exists', __( 'Cannot create an existing membership plan.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$membership = new Membership();
		$valid      = $this->apply_request( $membership, $request );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$saved = $this->save( $membership );
		if ( ! $saved ) {
			return new WP_Error( 'ohmylms_membership_create_failed', __( 'The membership plan could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$post = get_post( $membership->get_id() );

		/**
		 * Fires after a membership plan is inserted through the REST API.
		 *
		 * @param \WP_Post        $post    The membership post.
		 * @param WP_REST_Request $request The request.
		 */
		do_action( 'ohmylms_rest_insert_membership', $post, $request );

		/**
		 * Fires after a membership plan is created through the REST API.
		 *
		 * @param int             $membership_id Membership ID.
		 * @param WP_REST_Request $request       The request.
		 */
		do_action( 'ohmylms_membership_created', $membership->get_id(), $request );

		$this->after_courses_saved( $membership );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $this->get_membership( $membership->get_id() ), $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $membership->get_id() ) ) );

		return $response;
	}

	/**
	 * Update a membership plan. PUT, PATCH and POST all behave as a partial update.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$membership = $this->get_membership( $request['id'] );
		if ( is_wp_error( $membership ) ) {
			return $membership;
		}

		$valid = $this->apply_request( $membership, $request );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->save( $membership ) ) {
			return new WP_Error( 'ohmylms_membership_update_failed', __( 'The membership plan could not be updated.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$this->after_courses_saved( $membership );

		$request->set_param( 'context', 'edit' );

		return $this->prepare_item_for_response( $this->get_membership( $membership->get_id() ), $request );
	}

	/**
	 * Delete a membership plan. Refused while it has enrolled or pending members.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$membership = $this->get_membership( $request['id'] );
		if ( is_wp_error( $membership ) ) {
			return $membership;
		}

		$members = $this->count_active_members( $membership->get_id() );
		if ( $members > 0 ) {
			return new WP_Error(
				'ohmylms_membership_has_members',
				__( 'This plan has active members. Cancel their memberships or set the plan to draft instead of deleting it.', 'ohmylms' ),
				array(
					'status'  => 409,
					'members' => $members,
				)
			);
		}

		$request->set_param( 'context', 'edit' );
		$previous = $this->prepare_item_for_response( $membership, $request )->get_data();
		$id       = $membership->get_id();

		$membership->delete();
		if ( get_post( $id ) ) {
			return new WP_Error( 'ohmylms_membership_delete_failed', __( 'The membership plan could not be deleted.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after a membership plan is deleted through the REST API.
		 *
		 * @param int $membership_id Membership ID.
		 */
		do_action( 'ohmylms_rest_delete_membership', $id );

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'previous' => $previous,
			)
		);
	}

	/**
	 * Duplicate a plan as a draft with the same price, access rules and courses, and no members.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function duplicate_item( $request ) {
		$source = $this->get_membership( $request['id'] );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$copy = new Membership();
		$copy->set_name(
			mb_substr(
				/* translators: %s: membership plan name. */
				sprintf( __( '%s (copy)', 'ohmylms' ), $source->get_name( 'edit' ) ),
				0,
				200
			)
		);
		$copy->set_description( $source->get_description( 'edit' ) );
		$copy->set_status( 'draft' );
		$copy->set_regular_price( $source->get_regular_price( 'edit' ) );
		$copy->set_sale_price( $source->get_sale_price( 'edit' ) );
		$from = FreeSale::date( $source->get_sale_price_dates_from( 'edit' ) );
		$to   = FreeSale::date( $source->get_sale_price_dates_to( 'edit' ) );
		$copy->set_sale_price_dates_from( $from ? $from : null );
		$copy->set_sale_price_dates_to( $to ? $to : null );
		$copy->set_sign_up_fee( $source->get_sign_up_fee() );
		$copy->set_subscription_period( $source->get_subscription_period() );
		$copy->set_subscription_period_interval( $source->get_subscription_period_interval() );
		$copy->set_subscription_length( $source->get_subscription_length() );
		$copy->set_all_learning_content( $source->get_all_learning_content( 'edit' ) );
		$copy->set_products( $source->get_products( 'edit' ) );
		$copy->set_excluded_courses( $source->get_excluded_courses( 'edit' ) );
		$copy->set_course_curriculum( $source->get_course_curriculum( 'edit' ) );

		if ( ! $this->save( $copy ) ) {
			return new WP_Error( 'ohmylms_membership_create_failed', __( 'The membership plan could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		$this->after_courses_saved( $copy );

		$request->set_param( 'context', 'edit' );
		$response = $this->prepare_item_for_response( $this->get_membership( $copy->get_id() ), $request );
		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $copy->get_id() ) ) );

		return $response;
	}

	/**
	 * Read-only list of a plan's members (one page).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_members( $request ) {
		global $wpdb;

		$membership = $this->get_membership( $request['id'] );
		if ( is_wp_error( $membership ) ) {
			return $membership;
		}

		$table    = $wpdb->prefix . 'ohmylms_user_membership';
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 100, max( 1, (int) $request['per_page'] ) );
		$status   = (string) $request['status'];
		$where    = 'WHERE m.membership_id = %d';
		$params   = array( $table, $membership->get_id() );
		if ( 'all' !== $status ) {
			$where   .= ' AND m.status = %s';
			$params[] = $status;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i m {$where}", $params ) );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_membership_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.id, m.user_id, m.status, m.order_id, m.start_date, m.end_date, u.display_name, u.user_email
				FROM %i m LEFT JOIN %i u ON u.ID = m.user_id {$where}
				ORDER BY m.id DESC LIMIT %d OFFSET %d",
				array_merge( array( $table, $wpdb->users ), array_slice( $params, 1 ), array( $per_page, ( $page - 1 ) * $per_page ) )
			)
		);
		// phpcs:enable

		$to_date = static function ( $value ) {
			$value = (string) $value;
			return ( '' === $value || 0 === strpos( $value, '0000-00-00' ) ) ? null : str_replace( ' ', 'T', $value ) . 'Z';
		};
		$data    = array();
		foreach ( (array) $rows as $row ) {
			$data[] = array(
				'id'           => (int) $row->id,
				'user_id'      => (int) $row->user_id,
				'display_name' => (string) $row->display_name,
				'email'        => (string) $row->user_email,
				'status'       => (string) $row->status,
				'order_id'     => (int) $row->order_id,
				'start_date'   => $to_date( $row->start_date ),
				'end_date'     => $to_date( $row->end_date ),
			);
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * Choices and limits for the editor: statuses, billing periods and the site's currency settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_options( $request ) {
		$code = (string) get_ohmylms_currency();

		return rest_ensure_response(
			array(
				'statuses'        => array(
					array(
						'value' => 'draft',
						'label' => __( 'Draft', 'ohmylms' ),
					),
					array(
						'value' => 'publish',
						'label' => __( 'Published', 'ohmylms' ),
					),
				),
				'billing_periods' => array(
					array(
						'value' => 'one_time',
						'label' => __( 'One time', 'ohmylms' ),
					),
					array(
						'value' => 'year',
						'label' => __( 'Every year', 'ohmylms' ),
					),
					array(
						'value' => 'month',
						'label' => __( 'Every month', 'ohmylms' ),
					),
					array(
						'value' => 'week',
						'label' => __( 'Every week', 'ohmylms' ),
					),
					array(
						'value' => 'day',
						'label' => __( 'Every day', 'ohmylms' ),
					),
				),
				'currency'        => array(
					'code'     => $code,
					'symbol'   => html_entity_decode( (string) get_ohmylms_currency_symbol( $code ), ENT_QUOTES, 'UTF-8' ),
					'position' => (string) get_ohmylms_currency_position(),
					'decimals' => (int) ohmylms_get_price_decimals(),
				),
				'limits'          => array(
					'max_courses'          => self::MAX_COURSES,
					'max_curriculum_items' => self::MAX_CURRICULUM_ITEMS,
					'max_price'            => self::MAX_PRICE,
					'max_name'             => 200,
				),
			)
		);
	}

	/**
	 * Search courses that can be added to a plan (bounded, one page).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_courses( $request ) {
		$page     = max( 1, (int) $request['page'] );
		$per_page = min( 50, max( 1, (int) $request['per_page'] ) );
		$args     = array(
			'post_type'              => 'ohmylms-course',
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( '' !== (string) $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}
		if ( ! empty( $request['include'] ) ) {
			$args['post__in'] = array_map( 'absint', (array) $request['include'] );
		}

		$query = new WP_Query( $args );
		$total = $this->found_posts( $query, $args );
		$pages = (int) ceil( $total / $per_page );
		if ( $page > $pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_membership_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$response = rest_ensure_response( array_map( array( $this, 'course_ref' ), $query->posts ) );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $pages );

		return $response;
	}

	/**
	 * Total matches of a query. WordPress reports zero for a page past the end, so the total is
	 * counted again without paging in that case.
	 *
	 * @param WP_Query $query Executed query.
	 * @param array    $args  Its arguments.
	 * @return int
	 */
	private function found_posts( WP_Query $query, array $args ) {
		if ( $query->found_posts > 0 || (int) $args['paged'] < 2 ) {
			return (int) $query->found_posts;
		}

		$count = new WP_Query(
			array_merge(
				$args,
				array(
					'paged'          => 1,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			)
		);

		return (int) $count->found_posts;
	}

	/**
	 * Describe a course post.
	 *
	 * @param \WP_Post $post Course post.
	 * @return array
	 */
	private function course_ref( $post ) {
		return array(
			'id'     => (int) $post->ID,
			'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'status' => (string) $post->post_status,
		);
	}

	/**
	 * Count enrolled members for several plans with one query.
	 *
	 * @param int[] $ids Membership IDs.
	 * @return array<int,int>
	 */
	private function count_members( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT membership_id, COUNT(*) AS total FROM %i WHERE status = 'enrolled' AND membership_id IN ({$placeholders}) GROUP BY membership_id", array_merge( array( $wpdb->prefix . 'ohmylms_user_membership' ), $ids ) ) );
		$counts = array_fill_keys( $ids, 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ (int) $row->membership_id ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Count members that block deletion.
	 *
	 * @param int $id Membership ID.
	 * @return int
	 */
	private function count_active_members( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE membership_id = %d AND status IN (%s, %s)', $wpdb->prefix . 'ohmylms_user_membership', $id, self::ACTIVE_MEMBER_STATUSES[0], self::ACTIVE_MEMBER_STATUSES[1] ) );
	}

	/**
	 * Warm the post cache for the explicitly selected courses of a page of plans, so counting them
	 * does not query once per course.
	 *
	 * @param \WP_Post[] $posts Membership posts.
	 * @return void
	 */
	private function prime_course_posts( array $posts ) {
		$ids = array();
		foreach ( $posts as $post ) {
			$ids = array_merge( $ids, $this->course_ids_of( new Membership( $post ) ) );
		}
		$ids = array_values( array_unique( $ids ) );
		if ( $ids ) {
			_prime_post_caches( $ids, false, false );
		}
	}

	/**
	 * Explicitly selected course IDs of a plan.
	 *
	 * @param Membership $membership Plan.
	 * @return int[]
	 */
	private function course_ids_of( Membership $membership ) {
		$products = $membership->get_products( 'edit' );
		$ids      = is_array( $products ) ? array_map( 'absint', array_column( array_filter( $products, 'is_array' ), 'id' ) ) : array();

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Number of published courses the plan currently grants.
	 *
	 * @param Membership $membership Plan.
	 * @return int
	 */
	private function resolved_course_count( Membership $membership ) {
		if ( $membership->get_all_learning_content( 'edit' ) ) {
			static $published = null;
			if ( null === $published ) {
				$published = (int) wp_count_posts( 'ohmylms-course' )->publish;
			}
			$excluded = array_map( 'absint', (array) $membership->get_excluded_courses( 'edit' ) );
			$hidden   = $excluded ? count(
				get_posts(
					array(
						'post_type'      => 'ohmylms-course',
						'post_status'    => 'publish',
						'post__in'       => $excluded,
						'fields'         => 'ids',
						'posts_per_page' => count( $excluded ),
					)
				)
			) : 0;

			return max( 0, $published - $hidden );
		}

		return count( $membership->get_products() );
	}

	/**
	 * Course references (id, title, status) for IDs, in the given order, with one query.
	 *
	 * @param int[] $ids Course IDs.
	 * @return array[]
	 */
	private function course_refs( array $ids ) {
		if ( ! $ids ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => 'ohmylms-course',
				'post_status'    => 'any',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => count( $ids ),
			)
		);

		return array_map( array( $this, 'course_ref' ), $posts );
	}

	/**
	 * Apply the request to a plan after validating every field. Nothing is written until the whole
	 * request is valid, so a rejected request leaves the plan unchanged.
	 *
	 * @param Membership      $membership Plan to change (new or loaded).
	 * @param WP_REST_Request $request    Request.
	 * @return true|WP_Error
	 */
	private function apply_request( Membership $membership, WP_REST_Request $request ) {
		$decimals = (int) ohmylms_get_price_decimals();

		// Name and description.
		$name = null;
		if ( $request->has_param( 'name' ) ) {
			$name = trim( sanitize_text_field( (string) $request['name'] ) );
			if ( '' === $name ) {
				return new WP_Error( 'ohmylms_membership_invalid_name', __( 'Enter a name for the plan.', 'ohmylms' ), array( 'status' => 422 ) );
			}
		}

		// Pricing, merged with the stored values so a partial update is validated as a whole.
		$regular = $request->has_param( 'regular_price' ) ? $request['regular_price'] : $membership->get_regular_price( 'edit' );
		$sale    = $request->has_param( 'sale_price' ) ? $request['sale_price'] : $membership->get_sale_price( 'edit' );
		$from    = $request->has_param( 'sale_start_date' ) ? $request['sale_start_date'] : FreeSale::date( $membership->get_sale_price_dates_from( 'edit' ) );
		$to      = $request->has_param( 'sale_end_date' ) ? $request['sale_end_date'] : FreeSale::date( $membership->get_sale_price_dates_to( 'edit' ) );
		$sale    = ( null === $sale || '' === (string) $sale ) ? null : $sale;
		$from    = ( null === $from || '' === (string) $from ) ? null : (string) $from;
		$to      = ( null === $to || '' === (string) $to ) ? null : (string) $to;

		$prices = array(
			'regular_price' => $regular,
			'sale_price'    => $sale,
		);
		if ( $request->has_param( 'sign_up_fee' ) && null !== $request['sign_up_fee'] && '' !== $request['sign_up_fee'] ) {
			$prices['sign_up_fee'] = $request['sign_up_fee'];
		}
		foreach ( $prices as $field => $value ) {
			if ( null === $value ) {
				continue;
			}
			if ( ! is_numeric( $value ) || (float) $value < 0 || (float) $value > self::MAX_PRICE || strlen( (string) ( explode( '.', (string) $value )[1] ?? '' ) ) > $decimals ) {
				return new WP_Error(
					'ohmylms_membership_invalid_price',
					/* translators: %d: number of decimal places. */
					sprintf( __( 'Prices must be zero or greater with at most %d decimal places.', 'ohmylms' ), $decimals ),
					array(
						'status' => 422,
						'field'  => $field,
					)
				);
			}
		}

		if ( null !== $sale && (float) $sale > (float) $regular ) {
			return new WP_Error( 'ohmylms_membership_sale_price_too_high', __( 'The sale price cannot be higher than the regular price.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		foreach ( array( $from, $to ) as $date ) {
			if ( null !== $date && FreeSale::date( $date ) !== $date ) {
				return new WP_Error( 'ohmylms_membership_invalid_sale_dates', __( 'Sale dates must be real calendar dates (YYYY-MM-DD).', 'ohmylms' ), array( 'status' => 422 ) );
			}
		}
		if ( null !== $from && null !== $to && $from > $to ) {
			return new WP_Error( 'ohmylms_membership_invalid_sale_dates', __( 'The sale end date must be on or after its start date.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		if ( null === $sale && ( null !== $from || null !== $to ) ) {
			return new WP_Error( 'ohmylms_membership_sale_dates_without_price', __( 'Sale dates need a sale price.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		if ( null !== $sale && 0.0 === (float) $sale && null === $to ) {
			return new WP_Error( 'ohmylms_membership_free_needs_end', __( 'A free promotion (sale price 0) needs an end date.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		if ( null !== $sale && (float) $sale > 0 && ( ( null === $from ) !== ( null === $to ) ) ) {
			return new WP_Error( 'ohmylms_membership_sale_dates_incomplete', __( 'A discounted sale needs both a start and an end date, or neither.', 'ohmylms' ), array( 'status' => 422 ) );
		}

		// Courses and rules.
		$course_ids   = $request->has_param( 'course_ids' ) ? $this->unique_ids( $request['course_ids'] ) : null;
		$excluded_ids = $request->has_param( 'excluded_course_ids' ) ? $this->unique_ids( $request['excluded_course_ids'] ) : null;
		$curriculum   = $request->has_param( 'curriculum_item_ids' ) ? $this->unique_ids( $request['curriculum_item_ids'] ) : null;

		$missing = $this->missing_courses( array_merge( (array) $course_ids, (array) $excluded_ids ) );
		if ( $missing ) {
			return new WP_Error(
				'ohmylms_membership_invalid_courses',
				__( 'Some selected courses no longer exist.', 'ohmylms' ),
				array(
					'status'      => 422,
					'invalid_ids' => $missing,
				)
			);
		}
		$overlap = array_values( array_intersect( null === $course_ids ? $this->course_ids_of( $membership ) : $course_ids, null === $excluded_ids ? array_map( 'absint', (array) $membership->get_excluded_courses( 'edit' ) ) : $excluded_ids ) );
		if ( $overlap ) {
			return new WP_Error(
				'ohmylms_membership_course_conflict',
				__( 'A course cannot be both included and excluded.', 'ohmylms' ),
				array(
					'status'      => 422,
					'invalid_ids' => $overlap,
				)
			);
		}
		if ( $curriculum ) {
			$unknown = array_values( array_diff( $curriculum, array_keys( \OhMyLMS\Curriculum\Items::parents() ) ) );
			if ( $unknown ) {
				return new WP_Error(
					'ohmylms_membership_invalid_curriculum',
					__( 'Some selected curriculum items no longer exist.', 'ohmylms' ),
					array(
						'status'      => 422,
						'invalid_ids' => $unknown,
					)
				);
			}
		}

		// Everything is valid: write to the object.
		if ( null !== $name ) {
			$membership->set_name( $name );
		}
		if ( $request->has_param( 'description' ) ) {
			$membership->set_description( wp_kses_post( (string) $request['description'] ) );
		}
		if ( $request->has_param( 'status' ) ) {
			$membership->set_status( (string) $request['status'] );
		}
		$membership->set_regular_price( number_format( (float) $regular, $decimals, '.', '' ) );
		$membership->set_sale_price( null === $sale ? '' : number_format( (float) $sale, $decimals, '.', '' ) );
		$membership->set_sale_price_dates_from( $from );
		$membership->set_sale_price_dates_to( $to );
		if ( $request->has_param( 'sign_up_fee' ) ) {
			$fee = $request['sign_up_fee'];
			$membership->set_sign_up_fee( ( null === $fee || '' === $fee ) ? '' : number_format( (float) $fee, $decimals, '.', '' ) );
		}
		if ( $request->has_param( 'billing_period' ) ) {
			$membership->set_subscription_period( (string) $request['billing_period'] );
		}
		if ( $request->has_param( 'billing_interval' ) ) {
			$membership->set_subscription_period_interval( (int) $request['billing_interval'] );
		}
		if ( $request->has_param( 'all_learning_content' ) ) {
			$membership->set_all_learning_content( (bool) $request['all_learning_content'] );
		}
		if ( null !== $course_ids ) {
			$membership->set_products(
				array_map(
					static function ( $id ) {
						return array( 'id' => $id );
					},
					$course_ids
				)
			);
		}
		if ( null !== $excluded_ids ) {
			$membership->set_excluded_courses( $excluded_ids );
		}
		if ( null !== $curriculum ) {
			$membership->set_course_curriculum( $curriculum );
		}

		return true;
	}

	/**
	 * Save a plan.
	 *
	 * @param Membership $membership Plan.
	 * @return bool Whether the plan was stored.
	 */
	private function save( Membership $membership ) {
		try {
			$membership->save();
		} catch ( \Exception $e ) {
			return false;
		}

		return $membership->get_id() > 0 && null !== get_post( $membership->get_id() );
	}

	/**
	 * Tell the enrolment sync about the (possibly empty) course list, so removed courses lose this
	 * plan's access.
	 *
	 * @param Membership $membership Saved plan.
	 * @return void
	 */
	private function after_courses_saved( Membership $membership ) {
		/**
		 * Fires after a plan's courses and rules were saved through the REST API (also when empty).
		 *
		 * @param array $products      Courses the plan currently grants.
		 * @param int   $membership_id Membership ID.
		 */
		do_action( 'ohmylms_rest_after_adding_products_on_membership', $membership->get_products(), $membership->get_id() );
	}

	/**
	 * De-duplicate and sanitise a list of IDs.
	 *
	 * @param mixed $values IDs.
	 * @return int[]
	 */
	private function unique_ids( $values ) {
		return CourseSelection::ids( $values );
	}

	/**
	 * IDs that are not course posts.
	 *
	 * @param int[] $ids Course IDs.
	 * @return int[]
	 */
	private function missing_courses( array $ids ) {
		$ids = array_values( array_unique( $ids ) );
		if ( ! $ids ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'      => 'ohmylms-course',
				'post_status'    => 'any',
				'post__in'       => $ids,
				'fields'         => 'ids',
				'posts_per_page' => count( $ids ),
			)
		);

		return array_values( array_diff( $ids, array_map( 'intval', $found ) ) );
	}

	/**
	 * Format a stored price as a fixed-decimal string, or null when empty.
	 *
	 * @param mixed $value Stored price.
	 * @return string|null
	 */
	private function price_string( $value ) {
		if ( null === $value || '' === (string) $value || ! is_numeric( $value ) ) {
			return null;
		}

		return number_format( (float) $value, (int) ohmylms_get_price_decimals(), '.', '' );
	}

	/**
	 * Prepare a membership for the response.
	 *
	 * @param Membership      $item    Plan.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$membership = $item;
		$id         = $membership->get_id();
		$post       = get_post( $id );
		$edit       = $this->course_ids_of( $membership );
		$excluded   = array_values( array_filter( array_map( 'absint', (array) $membership->get_excluded_courses( 'edit' ) ) ) );
		$from       = FreeSale::date( $membership->get_sale_price_dates_from( 'edit' ) );
		$to         = FreeSale::date( $membership->get_sale_price_dates_to( 'edit' ) );
		$status     = (string) $membership->get_status( 'edit' );
		$regular    = $this->price_string( $membership->get_regular_price( 'edit' ) );
		$sale       = $this->price_string( $membership->get_sale_price( 'edit' ) );

		if ( FreeSale::active( $membership ) ) {
			$current = $this->price_string( 0 );
		} elseif ( $membership->is_on_sale( 'edit' ) && $membership->validate_on_sale() ) {
			$current = $sale;
		} else {
			$current = $regular;
		}

		$data = array(
			'id'                   => $id,
			'name'                 => html_entity_decode( (string) $membership->get_name( 'edit' ), ENT_QUOTES, 'UTF-8' ),
			'slug'                 => (string) $membership->get_slug( 'edit' ),
			'status'               => 'publish' === $status ? 'publish' : 'draft',
			'description'          => (string) $membership->get_description( 'edit' ),
			'permalink'            => (string) get_permalink( $id ),
			'regular_price'        => null === $regular ? $this->price_string( 0 ) : $regular,
			'sale_price'           => $sale,
			'sale_start_date'      => $from ? $from : null,
			'sale_end_date'        => $to ? $to : null,
			'price'                => null === $current ? $this->price_string( 0 ) : $current,
			'sign_up_fee'          => $this->price_string( $membership->get_sign_up_fee() ),
			'billing_period'       => $this->billing_period( $membership ),
			'billing_interval'     => max( 1, (int) $membership->get_subscription_period_interval() ),
			'currency'             => (string) get_ohmylms_currency(),
			'all_learning_content' => (bool) $membership->get_all_learning_content( 'edit' ),
			'course_ids'           => $edit,
			'courses'              => $this->course_refs( $edit ),
			'excluded_course_ids'  => $excluded,
			'excluded_courses'     => $this->course_refs( $excluded ),
			'curriculum_item_ids'  => array_values( array_filter( array_map( 'absint', (array) $membership->get_course_curriculum( 'edit' ) ) ) ),
			'course_count'         => $this->resolved_course_count( $membership ),
			'member_count'         => isset( $this->member_counts[ $id ] ) ? $this->member_counts[ $id ] : (int) $membership->count_membership_members(),
			'created_at'           => $post ? mysql_to_rfc3339( $post->post_date ) : '',
			'updated_at'           => $post ? mysql_to_rfc3339( $post->post_modified ) : '',
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
				'members'    => array( 'href' => rest_url( sprintf( '%s/%s/%d/members', $this->namespace, $this->rest_base, $id ) ) ),
			)
		);

		/**
		 * Filters the membership response.
		 *
		 * @param WP_REST_Response $response Response.
		 * @param \WP_Post|null    $post     Membership post.
		 * @param WP_REST_Request  $request  Request.
		 */
		return apply_filters( 'ohmylms_rest_prepare_membership', $response, $post, $request );
	}

	/**
	 * Stored billing period, limited to the supported values.
	 *
	 * @param Membership $membership Plan.
	 * @return string
	 */
	private function billing_period( Membership $membership ) {
		$period = (string) $membership->get_subscription_period();

		return in_array( $period, array( 'one_time', 'year', 'month', 'week', 'day' ), true ) ? $period : 'one_time';
	}

	/**
	 * Get the membership schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$price = static function ( $description, $extra = array() ) {
			return array_merge(
				array(
					'description' => $description,
					'type'        => 'string',
					'pattern'     => '^\d{1,8}(\.\d{1,4})?$',
					'context'     => array( 'view', 'edit' ),
				),
				$extra
			);
		};
		$date  = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => array( 'string', 'null' ),
				'pattern'     => '^\d{4}-\d{2}-\d{2}$',
				'context'     => array( 'view', 'edit' ),
			);
		};
		$ids   = static function ( $description, $max ) {
			return array(
				'description' => $description,
				'type'        => 'array',
				'items'       => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'maxItems'    => $max,
				'uniqueItems' => true,
				'default'     => array(),
				'context'     => array( 'edit' ),
			);
		};
		$refs  = static function ( $description ) {
			return array(
				'description' => $description,
				'type'        => 'array',
				'context'     => array( 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'title'  => array( 'type' => 'string' ),
						'status' => array( 'type' => 'string' ),
					),
				),
			);
		};

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'membership',
			'type'       => 'object',
			'properties' => array(
				'id'                   => array(
					'description' => __( 'Unique identifier for the plan.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'                 => array(
					'description' => __( 'Plan name.', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 200,
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
				),
				'slug'                 => array(
					'description' => __( 'Plan slug. Never changes when the name changes.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'status'               => array(
					'description' => __( 'Draft plans are not sold; published plans are.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'draft', 'publish' ),
					'default'     => 'draft',
					'context'     => array( 'view', 'edit' ),
				),
				'description'          => array(
					'description' => __( 'Plan description (post-safe HTML).', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 50000,
					'default'     => '',
					'context'     => array( 'edit' ),
				),
				'permalink'            => array(
					'description' => __( 'Public plan URL.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'regular_price'        => $price(
					__( 'Regular price as a decimal string, 0 or greater, at most 99999999.99.', 'ohmylms' ),
					array( 'default' => '0' )
				),
				'sale_price'           => $price(
					__( 'Sale price (at most the regular price), or null for none. 0 is a free promotion that needs an end date.', 'ohmylms' ),
					array( 'type' => array( 'string', 'null' ) )
				),
				'sale_start_date'      => $date( __( 'First day of the sale (site timezone), or null.', 'ohmylms' ) ),
				'sale_end_date'        => $date( __( 'Last day of the sale (site timezone), or null.', 'ohmylms' ) ),
				'price'                => array(
					'description' => __( 'The price that applies today.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'sign_up_fee'          => $price(
					__( 'One-off sign-up fee, or null for none.', 'ohmylms' ),
					array( 'type' => array( 'string', 'null' ) )
				),
				'billing_period'       => array(
					'description' => __( 'How often the price is charged.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'one_time', 'year', 'month', 'week', 'day' ),
					'default'     => 'one_time',
					'context'     => array( 'view', 'edit' ),
				),
				'billing_interval'     => array(
					'description' => __( 'Number of billing periods between charges.', 'ohmylms' ),
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 365,
					'default'     => 1,
					'context'     => array( 'view', 'edit' ),
				),
				'currency'             => array(
					'description' => __( 'Site currency code (a site setting, not stored on the plan).', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'all_learning_content' => array(
					'description' => __( 'Include every published course, now and in the future.', 'ohmylms' ),
					'type'        => 'boolean',
					'default'     => false,
					'context'     => array( 'view', 'edit' ),
				),
				'course_ids'           => $ids( __( 'Individually included courses (replaced as a whole).', 'ohmylms' ), self::MAX_COURSES ),
				'courses'              => $refs( __( 'Included courses with titles.', 'ohmylms' ) ),
				'excluded_course_ids'  => $ids( __( 'Courses never included, even by the other rules.', 'ohmylms' ), self::MAX_COURSES ),
				'excluded_courses'     => $refs( __( 'Excluded courses with titles.', 'ohmylms' ) ),
				'curriculum_item_ids'  => $ids( __( 'Curriculum items; everything beneath them is included.', 'ohmylms' ), self::MAX_CURRICULUM_ITEMS ),
				'course_count'         => array(
					'description' => __( 'Published courses the plan grants today.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'member_count'         => array(
					'description' => __( 'Enrolled members.', 'ohmylms' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'created_at'           => array(
					'description' => __( 'Creation time, in the site timezone.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'updated_at'           => array(
					'description' => __( 'Last modification time, in the site timezone.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Arguments for the update route: every writable field, optional, without defaults (schema
	 * defaults would otherwise reset fields in a partial update).
	 *
	 * @return array
	 */
	private function get_update_args() {
		$args = $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE );
		foreach ( $args as $name => $arg ) {
			unset( $args[ $name ]['default'], $args[ $name ]['required'] );
		}

		return $args;
	}

	/**
	 * Get collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default']   = 20;
		$params['per_page']['maximum']   = 100;
		$params['search']['description'] = __( 'Limit results to plans whose name or description matches the string.', 'ohmylms' );

		$params['status']  = array(
			'description' => __( 'Limit result set to plans with a status.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'publish', 'draft' ),
		);
		$params['include'] = array(
			'description' => __( 'Limit result set to specific plan IDs.', 'ohmylms' ),
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
			'maxItems'    => 100,
			'default'     => array(),
		);
		$params['orderby'] = array(
			'description' => __( 'Sort collection by attribute.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'created_at',
			'enum'        => array( 'id', 'name', 'status', 'created_at', 'updated_at' ),
		);
		$params['order']   = array(
			'description' => __( 'Order sort attribute ascending or descending.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);

		return $params;
	}

	/**
	 * Collection parameters for the course search.
	 *
	 * @return array
	 */
	private function get_course_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page', 'search' ) ) );

		$params['per_page']['default']   = 10;
		$params['per_page']['maximum']   = 50;
		$params['search']['description'] = __( 'Limit results to courses whose title or content matches the string.', 'ohmylms' );
		$params['include']               = array(
			'description' => __( 'Limit result set to specific course IDs.', 'ohmylms' ),
			'type'        => 'array',
			'items'       => array( 'type' => 'integer' ),
			'maxItems'    => 50,
			'default'     => array(),
		);

		return $params;
	}

	/**
	 * Collection parameters for the members route.
	 *
	 * @return array
	 */
	private function get_members_collection_params() {
		$params = array_intersect_key( parent::get_collection_params(), array_flip( array( 'context', 'page', 'per_page' ) ) );

		$params['per_page']['default'] = 20;
		$params['per_page']['maximum'] = 100;
		$params['status']              = array(
			'description' => __( 'Limit result set to members with a status.', 'ohmylms' ),
			'type'        => 'string',
			'default'     => 'all',
			'enum'        => array( 'all', 'enrolled', 'pending', 'cancelled', 'expired' ),
		);

		return $params;
	}
}
