<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * API PluginInstallerController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API PluginInstallerController class.
 *
 * @since 1.0.0
 */
class PluginInstallerController extends RestController {


	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $base = 'plugin-installer';

	/**
	 * Register routes for plugin installation operations
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/install',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'install_plugin' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'slug' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => 'The slug of the plugin to install',
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && ! empty( $param );
							},
						),
					),
				),
			)
		);

		// Activate plugin endpoint.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/activate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'activate_plugin' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'slug' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => 'The slug of the plugin to activate',
							'validate_callback' => function ( $param ) {
								return is_string( $param ) && ! empty( $param );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Install WordPress plugin from repository
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function install_plugin( $request ) {
		// require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		// require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		// require_once ABSPATH . 'wp-admin/includes/plugin-install.php';.

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/file.php'; // Add this line to include filesystem functions.

		$plugin_slug = sanitize_text_field( $request->get_param( 'slug' ) );
		$installed   = false;

		// Check if plugin is already installed.
		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin_path => $plugin ) {
			if ( strpos( $plugin_path, $plugin_slug . '/' ) === 0 ) {
				$installed = true;
				break;
			}
		}

		if ( $installed ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => sprintf( /* translators: %s: text value. */
					__( 'Plugin %s is already installed.' ), $plugin_slug ),
				)
			);
		}

		// Get plugin info from WordPress.org.
		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin_slug,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			return new WP_Error(
				'plugin_api_error',
				sprintf( /* translators: %s: text value. */
				__( 'Unable to fetch plugin information for %s from WordPress.org.' ), $plugin_slug ),
				array( 'status' => 500 )
			);
		}

		// Initialize WP_Filesystem before plugin installation.
		WP_Filesystem();

		$upgrader = new \Plugin_Upgrader( new \Automatic_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'plugin_install_error',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => sprintf( /* translators: %s: text value. */
				__( 'Plugin %s installed successfully.' ), $plugin_slug ),
			)
		);
	}

	/**
	 * Activate an installed WordPress plugin
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function activate_plugin( $request ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_slug     = sanitize_text_field( $request->get_param( 'slug' ) );
		$plugin_basename = '';

		// Find the plugin basename.
		$all_plugins = get_plugins();
		foreach ( $all_plugins as $plugin_path => $plugin ) {
			if ( strpos( $plugin_path, $plugin_slug . '/' ) === 0 ) {
				$plugin_basename = $plugin_path;
				break;
			}
		}

		if ( empty( $plugin_basename ) ) {
			return new WP_Error(
				'plugin_not_found',
				sprintf( /* translators: %s: text value. */
				__( 'Plugin %s is not installed.' ), $plugin_slug ),
				array( 'status' => 404 )
			);
		}

		if ( is_plugin_active( $plugin_basename ) ) {
			return new WP_Error(
				'plugin_already_active',
				sprintf( /* translators: %s: text value. */
				__( 'Plugin %s is already active.' ), $plugin_slug ),
				array( 'status' => 400 )
			);
		}
		if ( 'mail-mint/mail-mint.php' === $plugin_basename ) {
			// Get plugin version.
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_file = WP_PLUGIN_DIR . '/mail-mint/mail-mint.php';
			if ( file_exists( $plugin_file ) ) {
				$plugin_data       = get_plugin_data( $plugin_file );
				$mail_mint_version = isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '';
				if ( $mail_mint_version ) {
					update_option( 'mail_mint_version', $mail_mint_version, false );
				}
			}
		}
		$result = activate_plugin( $plugin_basename );

		if ( is_wp_error( $result ) ) {
			return new WP_Error(
				'plugin_activation_error',
				$result->get_error_message(),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => sprintf( /* translators: %s: text value. */
				__( 'Plugin %s activated successfully.' ), $plugin_slug ),
			)
		);
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}
}
