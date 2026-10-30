<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * DesignSettingsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Settings\DesignSettings;
use WP_REST_Request;
use WP_REST_Server;

/**
 * `ohmylms/v1/settings/course-archive` and `/settings/quiz-design`: the design settings groups, with
 * the same permission, response and error contract as the registry groups of SettingsController.
 * See docs/api/settings.md ("Design groups").
 *
 * @since 1.0.0
 */
class DesignSettingsController extends RestController {

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
	protected $rest_base = 'settings';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$settings = new SettingsController();
		foreach ( DesignSettings::GROUPS as $group ) {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/' . $group,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => function () use ( $group ) {
							return $this->respond( $group, array() );
						},
						'permission_callback' => array( $settings, 'get_items_permissions_check' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => function ( WP_REST_Request $request ) use ( $group ) {
							$values = DesignSettings::validate( $group, $request->get_json_params() );
							if ( is_wp_error( $values ) ) {
								return $values;
							}
							$changed = DesignSettings::write( $group, $values );
							return is_wp_error( $changed ) ? $changed : $this->respond( $group, $changed );
						},
						'permission_callback' => array( $settings, 'update_item_permissions_check' ),
					),
					'schema' => static function () use ( $group ) {
						return DesignSettings::schema( $group );
					},
				)
			);
		}
	}

	/**
	 * Group response, shaped like the registry groups.
	 *
	 * @param string   $group   Group id.
	 * @param string[] $changed Changed fields.
	 * @return \WP_REST_Response
	 */
	private function respond( $group, array $changed ) {
		return rest_ensure_response(
			array(
				'group'   => $group,
				'values'  => DesignSettings::read( $group ),
				'changed' => $changed,
			)
		);
	}
}
