<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Integrations controller.
 *
 * @package     OhMyLMS
 * @author      OhMyLMS
 * @copyright   Copyright (c) 2024, CreatorLMS
 * @license     GPL2+
 * @since       1.0.0
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * OhMyLMS Integrations settings controller class.
 *
 * @since 1.0.0
 */
class IntegrationsController extends RestController {

	/**
	 * is_valid/required_plan for an integration. Every bundled integration is available.
	 *
	 * @param string $key Integration key.
	 * @return array{is_valid: bool, required_plan: string|null}
	 */
	private static function get_integration_validity( $key ) {
		return array(
			'is_valid'      => true,
			'required_plan' => null,
		);
	}

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
	protected $rest_base = 'integrations';

	/**
	 * Register the routes for the objects.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_items' ),
					'permission_callback' => array( $this, 'update_items_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'ohmylms' ),
						'type'        => 'string',
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Retrieves all integrations.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$integrations = get_option( 'ohmylms_integrations', array() );
		unset( $integrations['ai_model'], $integrations['question_bank'] );
		if ( is_array( $integrations ) ) {
			foreach ( $integrations as $key => $value ) {
				$validity             = self::get_integration_validity( $key );
				$integrations[ $key ] = array(
					'is_enable'     => isset( $value['is_enable'] ) ? absint( $value['is_enable'] ) : 0,
					'class'         => isset( $value['class'] ) ? sanitize_text_field( $value['class'] ) : '',
					'is_valid'      => $validity['is_valid'],
					'required_plan' => $validity['required_plan'],
				);
			}
		}

		$is_community_active = defined( 'OHMYLMS_COMMUNITY_VERSION' );

		if ( $is_community_active ) {
			$integrations['community']['is_enable'] = 1;
		} else {
			$integrations['community']['is_enable'] = 0;
		}
		return new WP_REST_Response( $integrations, 200 );
	}

	/**
	 * Checks if a given request has access to get integrations.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_view', __( 'Sorry, you are not allowed to view these integrations.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Updates integration settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_items( $request ) {
		$integrations = $request->get_json_params();
		// Managed elsewhere (or core): never saved or returned from this endpoint.
		$skipped_keys         = array( 'ai_model', 'question_bank', 'skills' );
		$current_integrations = get_option( 'ohmylms_integrations', array() );
		foreach ( $skipped_keys as $skipped_key ) {
			unset( $current_integrations[ $skipped_key ] );
		}
		do_action( 'ohmylms_integrations_before_update', $integrations, $current_integrations );
		$sanitized_integrations = array();
		$previous_integrations  = get_option( 'ohmylms_integrations', array() );
		$need_reload            = false;

		foreach ( $integrations as $key => $value ) {
			if ( in_array( $key, $skipped_keys, true ) ) {
				continue;
			}
			$sanitized_key = sanitize_text_field( $key );
			$is_enable     = isset( $value['is_enable'] ) ? absint( $value['is_enable'] ) : 0;
			$validity      = self::get_integration_validity( $key );
			$is_valid      = $validity['is_valid'];
			$required_plan = $validity['required_plan'];
			$class         = isset( $value['class'] ) ? sanitize_text_field( $value['class'] ) : '';

			// Check if gamification or zoom settings changed.
			if ( 'gamification' === $sanitized_key || 'zoom' === $sanitized_key || 'community' === $sanitized_key || 'webhooks' === $sanitized_key || 'googlemeet' === $sanitized_key || 'question_bank' === $sanitized_key || 'mcp' === $sanitized_key ) {
				$previous_enabled = isset( $previous_integrations[ $sanitized_key ]['is_enable'] ) ? absint( $previous_integrations[ $sanitized_key ]['is_enable'] ) : 0;
				if ( $previous_enabled !== $is_enable ) {
					$need_reload = true;
				}
			}

			$was_enabled      = isset( $current_integrations[ $sanitized_key ]['is_enable'] ) ? absint( $current_integrations[ $sanitized_key ]['is_enable'] ) : 0;
			$is_being_enabled = ( 1 === $is_enable && 0 === $was_enabled );

			$current_integrations[ $sanitized_key ] = array(
				'is_enable' => $is_enable,
			);

			do_action( 'ohmylms_integration_' . $sanitized_key . '_updated', $is_enable );

			if ( $is_being_enabled ) {
				do_action( 'ohmylms_integration_' . $sanitized_key . '_before_enable', $sanitized_key );
			}

			update_option( 'ohmylms_integrations', $current_integrations );

			if ( $is_being_enabled ) {
				do_action( 'ohmylms_integration_' . $sanitized_key . '_after_enable', $sanitized_key );

				// Trigger addon activation tracking.
				do_action( 'ohmylms_addon_activated', $sanitized_key );
			}
			if ( 'content_protection' === $sanitized_key ) {
				update_option( 'ohmylms_content_protection', $is_enable ? 'yes' : 'no' );
			}

			$integrations[ $sanitized_key ]['is_enable']     = $is_enable;
			$integrations[ $sanitized_key ]['class']         = $class;
			$integrations[ $sanitized_key ]['is_valid']      = $is_valid;
			$integrations[ $sanitized_key ]['required_plan'] = $required_plan;
		}

		foreach ( $skipped_keys as $skipped_key ) {
			unset( $integrations[ $skipped_key ] );
		}

		update_option( 'ohmylms_integrations', $integrations );

		do_action( 'ohmylms_integrations_after_update', $current_integrations );
		do_action( 'ohmylms_integrations_' . $sanitized_key . '_after_update', $current_integrations );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'integrations' => $integrations,
				'need_reload'  => $need_reload,
				'message'      => __( 'Integrations updated successfully.', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Checks if a given request has access to update integrations.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function update_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_update', __( 'Sorry, you are not allowed to update these integrations.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Deletes a single integration.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$integration_id = $request['id'];
		$integrations   = get_option( 'ohmylms_integrations', array() );

		if ( ! isset( $integrations[ $integration_id ] ) ) {
			return new WP_Error( 'ohmylms_rest_integration_not_found', __( 'Integration not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		unset( $integrations[ $integration_id ] );
		update_option( 'ohmylms_integrations', $integrations );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Integration deleted successfully.', 'ohmylms' ),
			),
			200
		);
	}

	/**
	 * Checks if a given request has access to delete an integration.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function delete_item_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'ohmylms_rest_cannot_delete', __( 'Sorry, you are not allowed to delete this integration.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}
}
