<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * MailMintAutomationController class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_Error;
use WP_REST_Response;
use WP_HTTP_Response;

/**
 * MailMintAutomationController class.
 *
 * Handles REST API endpoints for MailMint automation settings.
 *
 * @package OhMyLMS\Rest\V1
 * @since 1.0.0
 */
class MailMintAutomationController extends RestController {

	/**
	 * The base route for automation endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'automation';

	/**
	 * Checks if the user has the required permission to manage automations.
	 *
	 * @return bool True if the user can edit posts, otherwise false.
	 * @param mixed $request REST request.
	 */
	public function check_automation_permission( $request ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'ohmylms_rest_forbidden', __( 'Sorry, you are not allowed to manage automations.', 'ohmylms' ), array( 'status' => \rest_authorization_required_code() ) );
		}

		/*
		 * Every automation route operates on the LMS content identified by
		 * post_id, so authorisation is scoped to that piece of content rather
		 * than to the generic edit_posts capability.
		 */
		return $this->check_object_permission(
			$request,
			'edit',
			array(
				OHMYLMS_COURSE_CPT,
				OHMYLMS_LESSON_CPT,
				OHMYLMS_ASSIGNMENT_CPT,
				OHMYLMS_QUIZ_CPT,
				OHMYLMS_MEMBERSHIP_CPT,
				OHMYLMS_CHAPTER_CPT,
			),
			'post_id'
		);
	}

	/**
	 * Registers the routes for the MailMint automation endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/content/(?P<post_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'check_automation_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'check_automation_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/content/(?P<post_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_automation' ),
					'permission_callback' => array( $this, 'check_automation_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/content/(?P<post_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'duplicate_automation' ),
					'permission_callback' => array( $this, 'check_automation_permission' ),
					'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the automation.', 'ohmylms' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_status' ),
					'permission_callback' => array( $this, 'check_automation_permission' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'check_automation_permission' ),
				),
			)
		);
	}


	/**
	 * Retrieves all automation IDs associated with a given post.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Error|WP_REST_Response|WP_HTTP_Response
	 */
	public function get_items( $request ) {
		$post_id = $request->get_param( 'post_id' );

		if ( empty( $post_id ) ) {
			return new \WP_Error( 'invalid_post_id', __( 'Invalid or missing post ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$page     = isset( $request['page'] ) ? absint( $request['page'] ) : 1;
		$per_page = isset( $request['per_page'] ) ? absint( $request['per_page'] ) : 25;
		$offset   = ( $page - 1 ) * $per_page;

		$status = isset( $request['status'] ) ? strtolower( $request['status'] ) : 'all';

		$order_by = isset( $request['orderby'] ) ? strtolower( $request['orderby'] ) : 'created_at';
		if ( 'date_created_desc' == $order_by ) {
			$order_by   = 'created_at';
			$order_type = 'desc';
		} elseif ( 'date_created_asc' == $order_by ) {
			$order_by   = 'created_at';
			$order_type = 'asc';
		} elseif ( 'name_asc' === $order_by ) {
			$order_by   = 'name';
			$order_type = 'asc';
		} elseif ( 'name_desc' === $order_by ) {
			$order_by   = 'name';
			$order_type = 'desc';
		} else {
			$order_type = 'desc';
		}

		// Automation Search keyword.
		$search = isset( $request['search'] ) ? sanitize_text_field( $request['search'] ) : '';

		// Get all automation IDs stored in post meta.
		$automation_ids = get_post_meta( $post_id, '_mm_automation_id', true );

		// Ensure it's an array.
		if ( ! is_array( $automation_ids ) ) {
			return rest_ensure_response( array() );
		}

		$automation_instance = new \OhMyLMS\Integrations\MailMint( $request->get_param( 'post_id' ) );
		$automation_data     = $automation_instance->get_all( $automation_ids, $order_by, $order_type, $offset, $per_page, $search, $status );

		return rest_ensure_response( $automation_data );
	}


	/**
	 * Creates a new automation in MailMint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Error|WP_REST_Response|WP_HTTP_Response
	 */
	public function create_item( $request ) {
		$automation_instance = new \OhMyLMS\Integrations\MailMint( $request->get_param( 'post_id' ) );

		if ( ! $automation_instance ) {
			return new \WP_Error( 'mailmint_not_active', __( 'MailMint is not active.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$post_id                 = $request->get_param( 'post_id' );
		$data                    = $request->get_json_params();
		$data['post_id']         = $post_id;
		$automation_id           = $automation_instance->create_or_update_automation( $data );
		$data                    = \MintMail\App\Internal\Automation\AutomationModel::get_single( $automation_id );
		$updated_automation_data = array();
		if ( isset( $data['data'][0] ) ) {
			$updated_automation_data = $data['data'][0];
		}

		$response = array(
			'automation_id' => $automation_id,
			'data'          => $updated_automation_data,
			'status'        => 'success',
		);
		return rest_ensure_response( $response );
	}


	/**
	 * Deletes an automation in MailMint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Error|WP_REST_Response|WP_HTTP_Response
	 */
	public function delete_item( $request ) {
		$automation_instance = new \OhMyLMS\Integrations\MailMint( $request->get_param( 'post_id' ) );
		if ( ! $automation_instance ) {
			return new \WP_Error( 'mailmint_not_active', __( 'MailMint is not active.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$automation_id = $request->get_param( 'id' );
		$automation_instance->delete_automation( $automation_id );
		return rest_ensure_response( $automation_id );
	}


	/**
	 * Deletes automations in MailMint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Error|WP_REST_Response|WP_HTTP_Response
	 */
	public function delete_automation( $request ) {
		$automation_instance = new \OhMyLMS\Integrations\MailMint( $request->get_param( 'post_id' ) );
		if ( ! $automation_instance ) {
			return new \WP_Error( 'mailmint_not_active', __( 'MailMint is not active.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$automation_ids = $request->get_param( 'ids' );
		if ( is_array( $automation_ids ) && ! empty( $automation_ids ) ) {
			foreach ( $automation_ids as $automation_id ) {
				$automation_instance->delete_automation( $automation_id );
			}
		}

		$response = array(
			'status' => 'success',
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Updates the status of an automation in MailMint.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_Error|WP_REST_Response|WP_HTTP_Response
	 */
	public function update_status( $request ) {
		// Get the automation ID and new status from the request.
		$automation_id = $request->get_param( 'id' );
		$new_status    = $request->get_param( 'status' );

		// Create a new MailMint instance for the post ID.
		$automation_instance = new \OhMyLMS\Integrations\MailMint( $request->get_param( 'post_id' ) );

		if ( ! $automation_instance ) {
			return new \WP_Error( 'mailmint_not_active', __( 'MailMint is not active.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Update the automation status.
		$automation_instance->update_automation_status( $automation_id, $new_status );

		// Return the updated status.
		return rest_ensure_response( $new_status );
	}


	/**
	 * Duplicate an automation.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 */
	public function duplicate_automation( $request ) {

		// Get the automation ID and new status from the request.
		$automation_id = $request->get_param( 'id' );
		$post_id       = $request->get_param( 'post_id' );
		if ( empty( $post_id ) ) {
			return new \WP_Error( 'invalid_post_id', __( 'Invalid or missing post ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		if ( empty( $automation_id ) ) {
			return new \WP_Error( 'invalid_automation_id', __( 'Invalid or missing automation ID.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		// Create a new MailMint instance for the post ID.
		$automation_instance = new \OhMyLMS\Integrations\MailMint( $request->get_param( 'post_id' ) );

		if ( ! $automation_instance ) {
			return new \WP_Error( 'mailmint_not_active', __( 'MailMint is not active.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$new_automation_id = $automation_instance->duplicate_automation( $automation_id );

		$response = array(
			'automation_id' => $new_automation_id,
			'status'        => 'success',
		);

		return rest_ensure_response( $response );
	}
}
