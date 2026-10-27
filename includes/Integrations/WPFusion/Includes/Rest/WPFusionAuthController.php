<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * WPFusionAuthController class.
 *
 * Handles WP Fusion authentication, connection, and disconnection
 *
 * @package ohmylms-pro
 * @since 1.0.0
 */

namespace OhMyLMS\Integrations\WPFusion\Includes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use OhMyLMS\Integrations\WPFusion\Includes\Api\WPFusionApiClient;

/**
 * Class WPFusionAuthController
 *
 * @package OhMyLMS\Integrations\WPFusion\Includes\Rest
 * @since 1.0.0
 */
class WPFusionAuthController {
	/**
	 * The single instance of the class.
	 *
	 * @var WPFusionAuthController
	 * @since 1.0.0
	 */
	protected static $instance = null;

	/**
	 * Path
	 *
	 * @var string
	 */
	protected $base = 'wpfusion/auth';

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
	 * @return WPFusionAuthController
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
		// Connect to WP Fusion.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/connect',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'connect' ),
					'permission_callback' => array( $this, 'admin_permission' ),
					'args'                => $this->get_connect_args(),
				),
			)
		);

		// Get connection status.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);

		// Disconnect from WP Fusion.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/disconnect',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'disconnect' ),
					'permission_callback' => array( $this, 'admin_permission' ),
				),
			)
		);

		// Get available tags.
		\register_rest_route(
			$this->namespace,
			'/' . $this->base . '/tags',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_tags' ),
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
	 * Connect to WP Fusion
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function connect( WP_REST_Request $request ) {
		$api_url = \sanitize_text_field( $request->get_param( 'api_url' ) );
		$api_key = \sanitize_text_field( $request->get_param( 'api_key' ) );

		// Validate the API credentials.
		$api_client        = new WPFusionApiClient( $api_url, $api_key );
		$validation_result = $api_client->validate_credentials();

		if ( ! $validation_result['success'] ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $validation_result['message'],
				),
				400
			);
		}

		// Save the credentials.
		$credentials = array(
			'api_url'      => $api_url,
			'api_key'      => $api_key,
			'connected_at' => current_time( 'mysql' ),
		);

		\update_option( 'ohmylms_wpfusion_credentials', $credentials );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Connected to WP Fusion successfully', 'ohmylms' ),
				'data'    => array(
					'connected' => true,
					'api_url'   => $api_url,
				),
			),
			200
		);
	}

	/**
	 * Get connection status
	 *
	 * Follows WPFunnels pattern: just checks if WP Fusion is installed and can connect
	 * No authentication/credentials needed - WP Fusion handles its own CRM auth
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_status( $request ) {
		try {
			// Check if WP Fusion integration is enabled in OhMyLMS settings.
			$integration_settings = get_option( 'ohmylms_integrations', array() );
			$is_enabled           = isset( $integration_settings['wpfusion']['is_enable'] ) &&
							1 === (int) $integration_settings['wpfusion']['is_enable'];

			if ( ! $is_enabled ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'data'    => array(
							'connected' => false,
							'installed' => defined( 'WP_FUSION_VERSION' ),
							'enabled'   => false,
							'message'   => __( 'WP Fusion integration is disabled. Please enable it from the Integrations page.', 'ohmylms' ),
						),
					),
					200
				);
			}

			// Check if WP Fusion class exists.
			if ( defined( 'WP_FUSION_VERSION' ) === false ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'data'    => array(
							'connected' => false,
							'installed' => false,
							'enabled'   => true,
							'message'   => __( 'WP Fusion plugin is not installed or activated.', 'ohmylms' ),
						),
					),
					200
				);
			}

			// Check if WP Fusion CRM is connected (WPFunnels pattern).
			if ( ! wp_fusion()->crm || ! wp_fusion()->crm->connect( null, false ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'data'    => array(
							'connected' => false,
							'installed' => true,
							'enabled'   => true,
							'message'   => __( 'WP Fusion is installed but not connected to a CRM. Please configure WP Fusion first.', 'ohmylms' ),
						),
					),
					200
				);
			}

			// Get CRM name.
			$crmName = wp_fusion()->crm->name ?? 'Unknown';

			return new \WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'connected' => true,
						'installed' => true,
						'enabled'   => true,
						'crm'       => $crmName,
						'message'   => sprintf( /* translators: %s: text value. */
						__( 'Connected to %s via WP Fusion', 'ohmylms' ), $crmName ),
					),
				),
				200
			);

		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'data'    => array(
						'connected' => false,
						'installed' => false,
						'message'   => $e->getMessage(),
					),
				),
				500
			);
		}
	}

	/**
	 * Disconnect from WP Fusion
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function disconnect( WP_REST_Request $request ) {
		\delete_option( 'ohmylms_wpfusion_credentials' );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Disconnected from WP Fusion successfully', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Get available tags from WP Fusion
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_tags( $request ) {
		// Check if WP Fusion is available.
		if ( defined( 'WP_FUSION_VERSION' ) === false ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'WP Fusion plugin is not installed or activated.', 'ohmylms' ),
				),
				400
			);
		}

		if ( ! wp_fusion()->crm ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'WP Fusion is not connected to a CRM.', 'ohmylms' ),
				),
				400
			);
		}

		// Get tags directly from WP Fusion.
		$available_tags = wp_fusion()->settings->get( 'available_tags', array() );

		if ( empty( $available_tags ) ) {
			// Try to sync tags if none are cached.
			if ( method_exists( wp_fusion()->crm, 'sync_tags' ) ) {
				wp_fusion()->crm->sync_tags();
				$available_tags = wp_fusion()->settings->get( 'available_tags', array() );
			}
		}

		// Format tags for frontend.
		$formatted_tags = array();
		foreach ( $available_tags as $tag_id => $tag_name ) {
			$formatted_tags[] = array(
				'id'   => $tag_id,
				'name' => $tag_name,
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $formatted_tags,
			),
			200
		);
	}

	/**
	 * Get connect args
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_connect_args() {
		return array(
			'api_url' => array(
				'description' => __( 'WP Fusion API Base URL', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
			'api_key' => array(
				'description' => __( 'WP Fusion API Key', 'ohmylms' ),
				'type'        => 'string',
				'required'    => true,
			),
		);
	}
}
