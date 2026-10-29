<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Integrations controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Integrations\IntegrationSettings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API for the Add-ons (integrations) screen under `ohmylms/v1/integrations`.
 *
 * The catalogue comes from the `ohmylms_integrations` filter; the on/off state lives in the
 * `ohmylms_integrations` option (`{ id: { is_enable: 0|1 } }`), which other modules read. See
 * docs/api/integrations.md for the contract.
 *
 * Authentication is WordPress core's: cookie authentication requires the `X-WP-Nonce` header
 * (verified by rest_cookie_check_errors()); application passwords also work. Every route requires
 * the CAPABILITY below.
 *
 * @since 1.0.0
 */
class IntegrationsController extends RestController {

	/**
	 * Capability required by every integrations route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Option that stores the integration switches.
	 */
	const OPTION = 'ohmylms_integrations';

	/**
	 * Integrations whose switch changes the admin menu, so the screen should reload after a change.
	 */
	const RELOAD_IDS = array( 'gamification', 'zoom', 'community', 'webhooks', 'googlemeet', 'mcp' );

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
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[a-z0-9_-]+)';
		$id   = array(
			'description' => __( 'Identifier of the integration.', 'ohmylms' ),
			'type'        => 'string',
			'pattern'     => '^[a-z0-9_-]+$',
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
					'args'                => array(
						'enabled' => array(
							'description'       => __( 'Whether the integration is switched on.', 'ohmylms' ),
							'type'              => 'boolean',
							'required'          => true,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'rest_sanitize_request_arg',
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/settings',
			array(
				'args'   => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_settings_schema' ),
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
	 * Check if the current user can list integrations.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_integration_cannot_view', __( 'Sorry, you are not allowed to view integrations.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can read one integration or its settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_integration_cannot_view', __( 'Sorry, you are not allowed to view integrations.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can switch an integration or change its settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_integration_cannot_edit', __( 'Sorry, you are not allowed to change integrations.', 'ohmylms' ) );
	}

	/**
	 * Read the stored switches as an array.
	 *
	 * @return array
	 */
	private function get_stored() {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Catalogue of known integrations, keyed by id, in registration order.
	 *
	 * @return array<string, array>
	 */
	private function get_catalogue() {
		$catalogue = apply_filters( 'ohmylms_integrations', array() );
		$clean     = array();
		if ( ! is_array( $catalogue ) ) {
			return $clean;
		}
		foreach ( $catalogue as $id => $definition ) {
			if ( is_string( $id ) && preg_match( '/^[a-z0-9_-]+$/', $id ) && is_array( $definition ) ) {
				$clean[ $id ] = $definition;
			}
		}

		return $clean;
	}

	/**
	 * Find one integration definition or return a 404 error.
	 *
	 * @param string $id Integration id.
	 * @return array|WP_Error
	 */
	private function get_definition( $id ) {
		$catalogue = $this->get_catalogue();
		if ( ! isset( $catalogue[ $id ] ) ) {
			return new WP_Error( 'ohmylms_integration_not_found', __( 'Integration not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		return $catalogue[ $id ];
	}

	/**
	 * Whether an integration is switched on (its own manifest wins over the stored switch).
	 *
	 * @param string $id         Integration id.
	 * @param array  $definition Catalogue definition.
	 * @return bool
	 */
	private function is_enabled( $id, array $definition ) {
		if ( isset( $definition['is_enable'] ) ) {
			return 1 === (int) $definition['is_enable'];
		}
		$stored = $this->get_stored();

		return isset( $stored[ $id ]['is_enable'] ) && 1 === (int) $stored[ $id ]['is_enable'];
	}

	/**
	 * Reason an integration cannot be switched on, or null when it can.
	 *
	 * @param string $id         Integration id.
	 * @param array  $definition Catalogue definition.
	 * @return string|null
	 */
	private function get_unavailable_reason( $id, array $definition ) {
		$reason = null;
		if ( isset( $definition['is_valid'] ) && ! $definition['is_valid'] ) {
			$reason = __( 'This integration is not available with your current plan.', 'ohmylms' );
		} elseif ( 'wpfusion' === $id && ! defined( 'WP_FUSION_VERSION' ) ) {
			$reason = __( 'Install and activate the WP Fusion Lite plugin to use this integration.', 'ohmylms' );
		}

		/**
		 * Filters why an integration cannot be switched on.
		 *
		 * @param string|null $reason Translated reason, or null when the integration can be enabled.
		 * @param string      $id     Integration id.
		 */
		$reason = apply_filters( 'ohmylms_integration_unavailable_reason', $reason, $id );

		return is_string( $reason ) && '' !== $reason ? $reason : null;
	}

	/**
	 * Whether a catalogue entry matches the collection filters.
	 *
	 * @param array           $entry   Prepared entry.
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	private function matches_filters( array $entry, $request ) {
		$status = (string) $request['status'];
		if ( ( 'enabled' === $status && ! $entry['enabled'] ) || ( 'disabled' === $status && $entry['enabled'] ) ) {
			return false;
		}
		$category = (string) $request['category'];
		if ( '' !== $category && ! in_array( $category, $entry['categories'], true ) ) {
			return false;
		}
		$search = trim( (string) $request['search'] );
		if ( '' !== $search && false === mb_stripos( $entry['label'] . ' ' . $entry['description'], $search ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Build the internal entry for one catalogue definition.
	 *
	 * @param string $id         Integration id.
	 * @param array  $definition Catalogue definition.
	 * @return array
	 */
	private function build_entry( $id, array $definition ) {
		$reason = $this->get_unavailable_reason( $id, $definition );

		return array(
			'id'                 => $id,
			'label'              => isset( $definition['label'] ) ? wp_strip_all_tags( (string) $definition['label'] ) : $id,
			'description'        => isset( $definition['description'] ) ? wp_strip_all_tags( (string) $definition['description'] ) : '',
			'icon'               => isset( $definition['icon'] ) ? esc_url_raw( (string) $definition['icon'] ) : '',
			'categories'         => isset( $definition['categories'] ) ? array_values( array_map( 'sanitize_key', (array) $definition['categories'] ) ) : array(),
			'dependency'         => ! empty( $definition['dependency'] ) ? wp_strip_all_tags( (string) $definition['dependency'] ) : null,
			'required_plan'      => ! empty( $definition['required_plan'] ) ? sanitize_text_field( (string) $definition['required_plan'] ) : null,
			'enabled'            => $this->is_enabled( $id, $definition ),
			'available'          => null === $reason,
			'unavailable_reason' => $reason,
			'configurable'       => IntegrationSettings::supports( $id ),
			'requires_reload'    => false,
		);
	}

	/**
	 * Get integrations.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( $this->get_catalogue() as $id => $definition ) {
			$entry = $this->build_entry( $id, $definition );
			if ( $this->matches_filters( $entry, $request ) ) {
				$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $entry, $request ) );
			}
		}

		$response = rest_ensure_response( $data );
		// The catalogue is small and fixed, so it is never paginated.
		$response->header( 'X-WP-Total', (string) count( $data ) );
		$response->header( 'X-WP-TotalPages', '1' );

		return $response;
	}

	/**
	 * Get one integration.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$id         = (string) $request['id'];
		$definition = $this->get_definition( $id );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}

		return $this->prepare_item_for_response( $this->build_entry( $id, $definition ), $request );
	}

	/**
	 * Switch an integration on or off.
	 *
	 * Fires, in order and only when the state changes: `ohmylms_integrations_before_update`,
	 * `ohmylms_integration_{id}_updated`, `ohmylms_integration_{id}_before_enable` (enabling),
	 * the option write, `ohmylms_integration_{id}_after_enable` and `ohmylms_addon_activated`
	 * (enabling), `ohmylms_integrations_after_update` and `ohmylms_integrations_{id}_after_update`.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id         = (string) $request['id'];
		$definition = $this->get_definition( $id );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}

		$entry   = $this->build_entry( $id, $definition );
		$enabled = (bool) $request['enabled'];

		if ( $enabled === $entry['enabled'] ) {
			return $this->prepare_item_for_response( $entry, $request );
		}

		if ( $enabled && ! $entry['available'] ) {
			return new WP_Error(
				'ohmylms_integration_unavailable',
				(string) $entry['unavailable_reason'],
				array( 'status' => 422 )
			);
		}

		$is_enable = $enabled ? 1 : 0;
		$current   = $this->get_stored();
		$changes   = array(
			$id => array( 'is_enable' => $is_enable ),
		);

		do_action( 'ohmylms_integrations_before_update', $changes, $current );
		do_action( 'ohmylms_integration_' . $id . '_updated', $is_enable );
		if ( $enabled ) {
			do_action( 'ohmylms_integration_' . $id . '_before_enable', $id );
		}

		$next        = $current;
		$next[ $id ] = array_merge( isset( $current[ $id ] ) && is_array( $current[ $id ] ) ? $current[ $id ] : array(), $changes[ $id ] );
		update_option( self::OPTION, $next );

		$saved = $this->get_stored();
		if ( ! isset( $saved[ $id ]['is_enable'] ) || (int) $saved[ $id ]['is_enable'] !== $is_enable ) {
			return new WP_Error( 'ohmylms_integration_update_failed', __( 'The integration could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		if ( $enabled ) {
			do_action( 'ohmylms_integration_' . $id . '_after_enable', $id );
			do_action( 'ohmylms_addon_activated', $id );
		}
		if ( 'content_protection' === $id ) {
			update_option( 'ohmylms_content_protection', $enabled ? 'yes' : 'no' );
		}

		do_action( 'ohmylms_integrations_after_update', $saved );
		do_action( 'ohmylms_integrations_' . $id . '_after_update', $saved );

		$entry                    = $this->build_entry( $id, $this->get_definition( $id ) );
		$entry['enabled']         = $enabled;
		$entry['requires_reload'] = in_array( $id, self::RELOAD_IDS, true );

		return $this->prepare_item_for_response( $entry, $request );
	}

	/**
	 * Resolve the integration of a settings request.
	 *
	 * @param string $id Integration id.
	 * @return true|WP_Error
	 */
	private function check_settings_target( $id ) {
		$definition = $this->get_definition( $id );
		if ( is_wp_error( $definition ) ) {
			return $definition;
		}
		if ( ! IntegrationSettings::supports( $id ) ) {
			return new WP_Error( 'ohmylms_integration_no_settings', __( 'This integration has no settings here.', 'ohmylms' ), array( 'status' => 404 ) );
		}

		return true;
	}

	/**
	 * Get an integration's settings (secrets are never returned).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_settings( $request ) {
		$id     = (string) $request['id'];
		$target = $this->check_settings_target( $id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		return rest_ensure_response( IntegrationSettings::describe( $id ) );
	}

	/**
	 * Update an integration's settings.
	 *
	 * The body is a JSON object of field names. Omitted fields are kept; a secret is replaced by a
	 * non-empty string or cleared with null (optional secrets only).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		$id     = (string) $request['id'];
		$target = $this->check_settings_target( $id );
		if ( is_wp_error( $target ) ) {
			return $target;
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) || ( $body && array_values( $body ) === $body ) ) {
			return new WP_Error( 'ohmylms_integration_invalid_body', __( 'Send a JSON object of settings.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$changed = IntegrationSettings::update( $id, $body );
		if ( is_wp_error( $changed ) ) {
			return $changed;
		}

		if ( $changed ) {
			/**
			 * Fires after an integration's credentials changed.
			 *
			 * @param string   $id      Integration id.
			 * @param string[] $changed Names of the fields that changed (never their values).
			 */
			do_action( 'ohmylms_integration_settings_updated', $id, $changed );
		}

		return rest_ensure_response( IntegrationSettings::describe( $id ) );
	}

	/**
	 * Prepare an integration entry for the response.
	 *
	 * @param array           $item    Entry from {@see self::build_entry()}.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$data = array(
			'id'                 => $item['id'],
			'label'              => $item['label'],
			'description'        => $item['description'],
			'icon'               => $item['icon'],
			'categories'         => $item['categories'],
			'dependency'         => $item['dependency'],
			'required_plan'      => $item['required_plan'],
			'enabled'            => $item['enabled'],
			'available'          => $item['available'],
			'unavailable_reason' => $item['unavailable_reason'],
			'configurable'       => $item['configurable'],
			'requires_reload'    => $item['requires_reload'],
		);

		$context  = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data     = $this->filter_response_by_context( $data, $context );
		$response = rest_ensure_response( $data );

		$self = rest_url( $this->namespace . '/' . $this->rest_base . '/' . $item['id'] );
		$response->add_link( 'self', $self );
		$response->add_link( 'collection', rest_url( $this->namespace . '/' . $this->rest_base ) );
		if ( $item['configurable'] ) {
			$response->add_link( 'settings', $self . '/settings' );
		}

		return $response;
	}

	/**
	 * Collection parameters.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
			'search'   => array(
				'description'       => __( 'Limit results to integrations whose name or description contains this text.', 'ohmylms' ),
				'type'              => 'string',
				'default'           => '',
				'maxLength'         => 100,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'category' => array(
				'description'       => __( 'Limit results to one category slug.', 'ohmylms' ),
				'type'              => 'string',
				'default'           => '',
				'pattern'           => '^[a-z0-9_-]*$',
				'maxLength'         => 64,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_key',
			),
			'status'   => array(
				'description'       => __( 'Limit results by switch state.', 'ohmylms' ),
				'type'              => 'string',
				'default'           => 'all',
				'enum'              => array( 'all', 'enabled', 'disabled' ),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			),
		);
	}

	/**
	 * Integration schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$context = array( 'view', 'edit' );

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'integration',
			'type'       => 'object',
			'properties' => array(
				'id'                 => array(
					'description' => __( 'Identifier of the integration.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $context,
					'readonly'    => true,
				),
				'label'              => array(
					'description' => __( 'Display name.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $context,
					'readonly'    => true,
				),
				'description'        => array(
					'description' => __( 'What the integration does.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $context,
					'readonly'    => true,
				),
				'icon'               => array(
					'description' => __( 'Icon URL.', 'ohmylms' ),
					'type'        => 'string',
					'format'      => 'uri',
					'context'     => $context,
					'readonly'    => true,
				),
				'categories'         => array(
					'description' => __( 'Category slugs.', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'context'     => $context,
					'readonly'    => true,
				),
				'dependency'         => array(
					'description' => __( 'Informational note about what the integration works with.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => $context,
					'readonly'    => true,
				),
				'required_plan'      => array(
					'description' => __( 'Plan that unlocks the integration, when it is restricted.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => $context,
					'readonly'    => true,
				),
				'enabled'            => array(
					'description' => __( 'Whether the integration is switched on.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $context,
					'required'    => true,
				),
				'available'          => array(
					'description' => __( 'Whether the integration can be switched on.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $context,
					'readonly'    => true,
				),
				'unavailable_reason' => array(
					'description' => __( 'Why the integration cannot be switched on.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'context'     => $context,
					'readonly'    => true,
				),
				'configurable'       => array(
					'description' => __( 'Whether credentials are managed at /integrations/{id}/settings.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $context,
					'readonly'    => true,
				),
				'requires_reload'    => array(
					'description' => __( 'True in an update response when the admin menu changes, so the page should reload.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $context,
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Settings schema (JSON Schema draft 4).
	 *
	 * @return array
	 */
	public function get_settings_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'integration-settings',
			'type'       => 'object',
			'properties' => array(
				'integration' => array(
					'type'     => 'string',
					'readonly' => true,
				),
				'configured'  => array(
					'description' => __( 'True when every required field has a value.', 'ohmylms' ),
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'fields'      => array(
					'description' => __( 'Editable fields. Text fields carry `value`; secret fields carry `configured` and `hint`, never the secret.', 'ohmylms' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array( 'type' => 'object' ),
				),
				'readonly'    => array(
					'description' => __( 'Values to copy into the provider’s console.', 'ohmylms' ),
					'type'        => 'array',
					'readonly'    => true,
					'items'       => array( 'type' => 'object' ),
				),
			),
		);
	}
}
