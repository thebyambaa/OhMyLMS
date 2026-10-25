<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Subject browsing setup: the All subjects page and the header Explore item.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Student\SiteSetup;

defined( 'ABSPATH' ) || exit;

/** Administrator-only routes; every change is previewed unless `confirm` is true. */
class SiteSetupController extends RestController {

	/** Register the setup routes. */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/site-setup/navigation-menus',
			array(
				'methods'             => 'GET',
				'permission_callback' => array( SiteSetup::class, 'can_read' ),
				'callback'            => array( $this, 'menus' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/site-setup/subjects-page',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( SiteSetup::class, 'can_write' ),
				'callback'            => array( $this, 'subjects_page' ),
				'args'                => array(
					'title'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ) {
							return strlen( (string) $value ) <= 100;
						},
					),
					'display' => array(
						'type'    => 'string',
						'enum'    => array( 'panel', 'dropdown' ),
						'default' => 'panel',
					),
					'confirm' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/site-setup/explore-menu',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( SiteSetup::class, 'can_write' ),
				'callback'            => array( $this, 'explore_menu' ),
				'args'                => array(
					'menu_id' => array(
						'type'     => 'integer',
						'minimum'  => 1,
						'required' => true,
					),
					'label'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static function ( $value ) {
							return strlen( (string) $value ) <= 40;
						},
					),
					'confirm' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Navigation menus.
	 *
	 * @return array
	 */
	public function menus() {
		return SiteSetup::menus();
	}

	/**
	 * Find or create the All subjects page.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function subjects_page( $request ) {
		return SiteSetup::subjects_page( (string) $request['title'], (string) $request['display'], rest_sanitize_boolean( $request['confirm'] ) );
	}

	/**
	 * Add the Explore item to a navigation menu.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public function explore_menu( $request ) {
		return SiteSetup::explore_menu( (int) $request['menu_id'], (string) $request['label'], rest_sanitize_boolean( $request['confirm'] ) );
	}
}
