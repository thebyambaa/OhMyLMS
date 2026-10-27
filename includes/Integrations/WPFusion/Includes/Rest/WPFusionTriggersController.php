<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WPFusionTriggersController class.
 *
 * Handles CRUD operations for WP Fusion triggers
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\WPFusion\Includes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use OhMyLMS\Integrations\WPFusion\Includes\WPFusionMigration;

/**
 * Class WPFusionTriggersController
 *
 * @package OhMyLMS\Integrations\WPFusion\Includes\Rest
 * @since 1.0.0
 */
class WPFusionTriggersController {
	/**
	 * The single instance of the class.
	 *
	 * @var WPFusionTriggersController
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Path
	 *
	 * @var string
	 */
	protected $base = 'wpfusion/triggers';

	/**
	 * REST API namespace
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Get instance
	 *
	 * @since 1.0.0
	 *
	 * @return WPFusionTriggersController
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register routes
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		// Get all triggers.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_triggers' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_trigger' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => $this->get_trigger_args(),
				),
			)
		);

		// Get, update, delete single trigger.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_trigger' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_trigger' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => $this->get_trigger_args(),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_trigger' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);

		// Get available events.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/events',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_events' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);

		// Get available actions.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/actions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_actions' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);
	}

	/**
	 * Check if the user has admin permission.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function admin_permission() {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Get all triggers
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_triggers( WP_REST_Request $request ) {
		global $wpdb;

		$table_name = WPFusionMigration::get_table_name();

		// Filter by wpfusion CRM type since this is the WP Fusion controller.
		$triggers = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE crm_type = %s ORDER BY created_at DESC', $table_name, 'wpfusion' ),
			ARRAY_A
		);

		// Decode JSON fields.
		foreach ( $triggers as &$trigger ) {
			if ( isset( $trigger['action_data'] ) ) {
				$trigger['action_data'] = json_decode( $trigger['action_data'], true );
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $triggers,
			),
			200
		);
	}

	/**
	 * Get single trigger
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_trigger( WP_REST_Request $request ) {
		global $wpdb;

		$id         = intval( $request->get_param( 'id' ) );
		$table_name = WPFusionMigration::get_table_name();

		$trigger = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $id ),
			ARRAY_A
		);

		if ( ! $trigger ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Trigger not found', 'ohmylms' ),
				),
				404
			);
		}

		// Decode JSON field.
		if ( isset( $trigger['action_data'] ) ) {
			$trigger['action_data'] = json_decode( $trigger['action_data'], true );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $trigger,
			),
			200
		);
	}

	/**
	 * Create trigger
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function create_trigger( WP_REST_Request $request ) {
		global $wpdb;

		$name          = \sanitize_text_field( $request->get_param( 'name' ) );
		$crm_type      = \sanitize_text_field( $request->get_param( 'crm_type' ) );
		$trigger_event = \sanitize_text_field( $request->get_param( 'trigger_event' ) );
		$content_type  = \sanitize_text_field( $request->get_param( 'content_type' ) );
		$content_id    = absint( $request->get_param( 'content_id' ) );
		$action_type   = \sanitize_text_field( $request->get_param( 'action_type' ) );
		$action_data   = $request->get_param( 'action_data' );
		$status        = \sanitize_text_field( $request->get_param( 'status' ) );

		// Default crm_type to wpfusion if not provided.
		if ( empty( $crm_type ) ) {
			$crm_type = 'wpfusion';
		}

		if ( empty( $status ) ) {
			$status = 'active';
		}

		$table_name = WPFusionMigration::get_table_name();

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'name'          => $name,
				'crm_type'      => $crm_type,
				'trigger_event' => $trigger_event,
				'content_type'  => $content_type,
				'content_id'    => $content_id,
				'action_type'   => $action_type,
				'action_data'   => wp_json_encode( $action_data ),
				'status'        => $status,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to create trigger', 'ohmylms' ),
				),
				500
			);
		}

		$trigger_id = $wpdb->insert_id;

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Trigger created successfully', 'ohmylms' ),
				'data'    => array( 'id' => $trigger_id ),
			),
			201
		);
	}

	/**
	 * Update trigger
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function update_trigger( WP_REST_Request $request ) {
		global $wpdb;

		$id            = intval( $request->get_param( 'id' ) );
		$name          = \sanitize_text_field( $request->get_param( 'name' ) );
		$crm_type      = \sanitize_text_field( $request->get_param( 'crm_type' ) );
		$trigger_event = \sanitize_text_field( $request->get_param( 'trigger_event' ) );
		$content_type  = \sanitize_text_field( $request->get_param( 'content_type' ) );
		$content_id    = absint( $request->get_param( 'content_id' ) );
		$action_type   = \sanitize_text_field( $request->get_param( 'action_type' ) );
		$action_data   = $request->get_param( 'action_data' );
		$status        = \sanitize_text_field( $request->get_param( 'status' ) );

		// Default crm_type to wpfusion if not provided.
		if ( empty( $crm_type ) ) {
			$crm_type = 'wpfusion';
		}

		$table_name = WPFusionMigration::get_table_name();

		$updated = $wpdb->update(
			$table_name,
			array(
				'name'          => $name,
				'crm_type'      => $crm_type,
				'trigger_event' => $trigger_event,
				'content_type'  => $content_type,
				'content_id'    => $content_id,
				'action_type'   => $action_type,
				'action_data'   => wp_json_encode( $action_data ),
				'status'        => $status,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to update trigger', 'ohmylms' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Trigger updated successfully', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Delete trigger
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_trigger( WP_REST_Request $request ) {
		global $wpdb;

		$id         = intval( $request->get_param( 'id' ) );
		$table_name = WPFusionMigration::get_table_name();

		$deleted = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( ! $deleted ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to delete trigger', 'ohmylms' ),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Trigger deleted successfully', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Get available OhMyLMS events
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_events( WP_REST_Request $request ) {
		$events = array(
			array(
				'value' => 'ohmylms_course_completed',
				'label' => __( 'Course Completed', 'ohmylms' ),
			),
			array(
				'value' => 'ohmylms_lesson_completed',
				'label' => __( 'Lesson Completed', 'ohmylms' ),
			),
			array(
				'value' => 'ohmylms_manual_student_enrollment',
				'label' => __( 'Student Enrolled', 'ohmylms' ),
			),
			array(
				'value' => 'ohmylms_quiz_submission',
				'label' => __( 'Quiz Submitted', 'ohmylms' ),
			),
			array(
				'value' => 'ohmylms_after_assignment_submitted',
				'label' => __( 'Assignment Submitted', 'ohmylms' ),
			),
			array(
				'value' => 'ohmylms_quiz_result',
				'label' => __( 'Quiz Result', 'ohmylms' ),
			),
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $events,
			),
			200
		);
	}

	/**
	 * Get available WP Fusion actions
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_actions( WP_REST_Request $request ) {
		$actions = array(
			array(
				'value' => 'add_tag',
				'label' => __( 'Add Tag', 'ohmylms' ),
			),
			array(
				'value' => 'remove_tag',
				'label' => __( 'Remove Tag', 'ohmylms' ),
			),
			array(
				'value' => 'update_fields',
				'label' => __( 'Update Contact Fields', 'ohmylms' ),
			),
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $actions,
			),
			200
		);
	}

	/**
	 * Get trigger args
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_trigger_args() {
		return array(
			'name'          => array(
				'description' => __( 'Trigger Name', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
			'trigger_event' => array(
				'description' => __( 'OhMyLMS Event', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
			'action_type'   => array(
				'description' => __( 'WP Fusion Action Type', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
			'action_data'   => array(
				'description' => __( 'Action Data (tags, fields, etc.)', 'ohmylms' ),
				'type'        => 'object',
				'required'    => true,
			),
			'status'        => array(
				'description' => __( 'Trigger Status', 'ohmylms' ),
				'type'        => 'string',
				'required'    => false,
			),
		);
	}
}
