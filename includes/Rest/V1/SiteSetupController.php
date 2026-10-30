<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Subject browsing setup: the All subjects page and the header Explore item.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Student\SiteSetup;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Administrator-only routes under `ohmylms/v1/site-setup`; every change is only previewed unless
 * `confirm` is true, and repeating a confirmed call changes nothing (see {@see SiteSetup}).
 *
 * Permissions come from {@see SiteSetup::can_read()} and {@see SiteSetup::can_write()}:
 * `manage_options` plus `edit_theme_options` (and `publish_pages` to write). They answer 401 for
 * anonymous callers and 403 otherwise. See docs/api/setup.md.
 *
 * @since 1.0.0
 */
class SiteSetupController extends RestController {

	/**
	 * Longest page title.
	 */
	const TITLE_MAX = 100;

	/**
	 * Longest menu label.
	 */
	const LABEL_MAX = 40;

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'site-setup';

	/**
	 * Register the setup routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base    = '/' . $this->rest_base;
		$confirm = array(
			'description' => __( 'Apply the change. Without it the response only describes the change.', 'ohmylms' ),
			'type'        => 'boolean',
			'default'     => false,
		);

		register_rest_route(
			$this->namespace,
			$base . '/navigation-menus',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => array( SiteSetup::class, 'can_read' ),
					'callback'            => array( $this, 'menus' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/subjects-page',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( SiteSetup::class, 'can_write' ),
					'callback'            => array( $this, 'subjects_page' ),
					'args'                => array(
						'title'   => array(
							'description'       => __( 'Title of the page when one is created.', 'ohmylms' ),
							'type'              => 'string',
							'default'           => '',
							'maxLength'         => self::TITLE_MAX,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'display' => array(
							'description'       => __( 'Layout of the Browse Subjects block.', 'ohmylms' ),
							'type'              => 'string',
							'enum'              => array( 'panel', 'dropdown' ),
							'default'           => 'panel',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'confirm' => $confirm + array( 'validate_callback' => 'rest_validate_request_arg' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/explore-menu',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( SiteSetup::class, 'can_write' ),
					'callback'            => array( $this, 'explore_menu' ),
					'args'                => array(
						'menu_id' => array(
							'description'       => __( 'ID of the navigation menu.', 'ohmylms' ),
							'type'              => 'integer',
							'minimum'           => 1,
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
						),
						'label'   => array(
							'description'       => __( 'Label of the Explore item.', 'ohmylms' ),
							'type'              => 'string',
							'default'           => '',
							'maxLength'         => self::LABEL_MAX,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'confirm' => $confirm + array( 'validate_callback' => 'rest_validate_request_arg' ),
					),
				),
			)
		);
	}

	/**
	 * Navigation menus.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function menus( $request ) {
		return rest_ensure_response( SiteSetup::menus() );
	}

	/**
	 * Find or create the All subjects page.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function subjects_page( $request ) {
		return rest_ensure_response( SiteSetup::subjects_page( (string) $request['title'], (string) $request['display'], rest_sanitize_boolean( $request['confirm'] ) ) );
	}

	/**
	 * Add the Explore item to a navigation menu.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function explore_menu( $request ) {
		return rest_ensure_response( SiteSetup::explore_menu( (int) $request['menu_id'], (string) $request['label'], rest_sanitize_boolean( $request['confirm'] ) ) );
	}
}
