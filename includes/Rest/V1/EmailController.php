<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * SettingsController class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Admin\Settings\AdminSettings;

/**
 * SettingsController class.
 *
 * Handles REST API endpoints for settings.
 *
 * @since 1.0.0
 */
class EmailController extends RestController {

	/**
	 * The base route for settings endpoints.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $base = 'email-settings/(?P<email_id>[\w-]+)';

	/**
	 * Items.
	 *
	 * @var mixed
	 */
	protected $items = 'all-emails';


	/**
	 * Register the routes for the settings endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				'args'   => array(
					'email_id' => array(
						'description' => __( 'Email setting ID.', 'ohmylms' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->items,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}


	/**
	 * Get items (settings) for a specific group.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error The response or error object.
	 *
	 * @since 1.0.0
	 */
	public function get_items( $request ) {
		$email_ids = array(
			'CreatorsEmail\\NewOrder'            => 'creator_new_order',
			'CreatorsEmail\\CancelledOrder'      => 'creator_cancelled_order',
			'CreatorsEmail\\AssignmentSubmitted' => 'instructor_assignment_submitted',
			'CreatorsEmail\\QuizSubmitted'       => 'instructor_quiz_submitted',
			'StudentsEmail\\NewOrder'            => 'student_new_order',
			'StudentsEmail\\ConfirmEnrollment'   => 'student_confirm_enrollment',
			'StudentsEmail\\CompleteCourse'      => 'student_course_completed',
			'StudentsEmail\\CancelEnrollment'    => 'student_cancel_enrollment',
			'StudentsEmail\\AssignmentGraded'    => 'student_assignment_graded',
			'StudentsEmail\\QuizGraded'          => 'student_quiz_graded',
		);

		$search_term = $request->get_param( 'search' );

		$settings = array();
		foreach ( $email_ids as $key => $email_id ) {
			$email = 'OhMyLMS\\Emails\\' . $key;
			if ( class_exists( $email ) ) {
				$settings[ $email_id ]          = $this->get_setting( $email_id, ( new $email() )->default_settings() );
				$settings[ $email_id ]['basic'] = ( new $email() )->basic_settings();
			}
		}
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		if ( ! $search_term ) {
			return rest_ensure_response( $settings );
		}
		$results = array();
		foreach ( $settings as $key => $entry ) {
			if ( isset( $entry['basic']['title'] ) && stripos( $entry['basic']['title'], $search_term ) !== false ) {
				$results[ $key ] = $entry;
			}
		}
		$settings = $results;

		return rest_ensure_response( $settings );
	}


	/**
	 * Get items (settings) for a specific group.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error The response or error object.
	 *
	 * @since 1.0.0
	 */
	public function update_items( $request ) {
		$get_data = $request->get_json_params();
		$email_id = $request['email_id'];
		if ( ! is_array( $get_data ) ) {
			return new \WP_Error( 'rest_setting_setting_invalid', __( 'Invalid setting.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		$this->update_setting( $email_id, $get_data );

		$settings = get_option( 'create_lms_email_' . $email_id );

		return rest_ensure_response( $settings );
	}

	/**
	 * Get a single item (setting) for a specific group.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error The response or error object.
	 *
	 * @since 1.0.0
	 */
	public function get_item( $request ) {
		$email_ids = array(
			'CreatorsEmail\\NewOrder'            => 'creator_new_order',
			'CreatorsEmail\\CancelledOrder'      => 'creator_cancelled_order',
			'CreatorsEmail\\AssignmentSubmitted' => 'instructor_assignment_submitted',
			'CreatorsEmail\\QuizSubmitted'       => 'instructor_quiz_submitted',
			'StudentsEmail\\NewOrder'            => 'student_new_order',
			'StudentsEmail\\ConfirmEnrollment'   => 'student_confirm_enrollment',
			'StudentsEmail\\CompleteCourse'      => 'student_course_completed',
			'StudentsEmail\\CancelEnrollment'    => 'student_cancel_enrollment',
			'StudentsEmail\\AssignmentGraded'    => 'student_assignment_graded',
			'StudentsEmail\\QuizGraded'          => 'student_quiz_graded',
		);
		$key       = array_search( $request['email_id'], $email_ids );
		$email     = 'OhMyLMS\\Emails\\' . $key;
		$setting   = $this->get_setting( $request['email_id'], ( new $email() )->default_settings() );
		if ( is_wp_error( $setting ) ) {
			return $setting;
		}

		$response = $this->prepare_item_for_response( $setting, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Get a single setting for a specific group.
	 *
	 * @param mixed $email_id Email identifier.
	 * @param array $default Default.
	 * @return array|\WP_Error The setting array or error object.
	 *
	 * @since 1.0.0
	 */
	public function get_setting( $email_id, $default = array() ) {
		if ( empty( $email_id ) ) {
			return new \WP_Error( 'rest_setting_setting_invalid', __( 'Invalid email ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return get_option( 'create_lms_email_' . $email_id, $default );
	}

	/**
	 * Get a single setting for a specific group.
	 *
	 * @param mixed $email_id Email identifier.
	 * @param mixed $data Input data.
	 * @return bool The setting array or error object.
	 *
	 * @since 1.0.0
	 */
	public function update_setting( $email_id, $data ) {
		if ( empty( $email_id ) ) {
			return new \WP_Error( 'rest_setting_setting_invalid', __( 'Invalid email ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		return update_option( 'create_lms_email_' . $email_id, $data );
	}



	/**
	 * Prepare a single item for response.
	 *
	 * @param array            $item The item array.
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The response object.
	 *
	 * @since 1.0.0
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data     = $this->add_additional_fields_to_object( $item, $request );
		$response = rest_ensure_response( $data );
		return $response;
	}


	/**
	 * Check permissions for getting items.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if the current user has permission, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Check permissions for updating items.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return bool True if the current user has permission, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public function update_items_permissions_check( $request ) {
		return current_user_can( 'edit_posts' );
	}
}
