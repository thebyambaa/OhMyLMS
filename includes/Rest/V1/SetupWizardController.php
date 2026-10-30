<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Setup wizard REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Curriculum\Placement;
use OhMyLMS\SetupWizard\SetupService;
use OhMyLMS\SetupWizard\SetupSteps;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API for the first-run setup wizard under `ohmylms/v1/setup`.
 *
 * Logic lives in {@see SetupService}; this class owns routes, permissions and schema. Cookie
 * authentication is verified by WordPress core (`X-WP-Nonce`). See docs/api/setup.md.
 *
 * @since 1.0.0
 */
class SetupWizardController extends RestController {

	/**
	 * Capability required by every setup route.
	 */
	const CAPABILITY = 'manage_options';

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
	protected $rest_base = 'setup';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		$base  = '/' . $this->rest_base;
		$steps = implode( '|', SetupSteps::ids() );
		$step  = array(
			'description' => __( 'Setup step id.', 'ohmylms' ),
			'type'        => 'string',
			'enum'        => SetupSteps::ids(),
		);

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/steps/(?P<step>' . $steps . ')',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_step' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array( 'step' => $step ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/steps/(?P<step>' . $steps . ')/skip',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'skip_step' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array( 'step' => $step ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/complete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'complete' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/dismiss',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'dismiss' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => array(
						'step' => array(
							'description' => __( 'The step the administrator was on.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => SetupSteps::ids(),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/reset',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reset' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check that the user may read the wizard state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_setup_cannot_view', __( 'Sorry, you are not allowed to view the setup.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check that the user may run the wizard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_setup_cannot_manage', __( 'Sorry, you are not allowed to run the setup.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Current wizard state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		return rest_ensure_response( SetupService::state() );
	}

	/**
	 * Save one step and mark it done.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_step( $request ) {
		$body = $this->read_body( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$result = SetupService::save_step( (string) $request['step'], $body );

		return $this->step_response( $result );
	}

	/**
	 * Skip one step.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function skip_step( $request ) {
		return $this->step_response( SetupService::skip_step( (string) $request['step'] ) );
	}

	/**
	 * Complete the wizard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function complete( $request ) {
		return $this->state_response( SetupService::complete() );
	}

	/**
	 * Leave the wizard without completing it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss( $request ) {
		$step = $request->get_param( 'step' );

		return $this->state_response( SetupService::dismiss( is_string( $step ) ? $step : '' ) );
	}

	/**
	 * Reset the wizard progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset( $request ) {
		return $this->state_response( SetupService::reset() );
	}

	/**
	 * The wizard's category names become top-level curriculum items (course categories were replaced by
	 * the curriculum). Names that already exist are left alone. Kept for the placement integration test.
	 *
	 * @param array $request Array with an optional `category` list of names.
	 * @return void
	 */
	private function save_terms( $request ) {
		if ( isset( $request['category'] ) && is_array( $request['category'] ) ) {
			Placement::ensure_items( array_map( 'sanitize_text_field', array_map( 'strval', $request['category'] ) ) );
		}
	}

	/**
	 * Decode the JSON body (an empty body is an empty object).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|null|WP_Error Decoded object, null for no body.
	 */
	private function read_body( $request ) {
		if ( '' === trim( (string) $request->get_body() ) ) {
			return null;
		}
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'ohmylms_setup_invalid_body', __( 'Send a JSON object.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		return $body;
	}

	/**
	 * Response of a step action: the new state and the step result.
	 *
	 * @param array|WP_Error $result Step result or error.
	 * @return WP_REST_Response|WP_Error
	 */
	private function step_response( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'state'  => SetupService::state(),
				'result' => $result,
			)
		);
	}

	/**
	 * Response of a wizard-level action: the new state.
	 *
	 * @param true|WP_Error $outcome Outcome of the service call.
	 * @return WP_REST_Response|WP_Error
	 */
	private function state_response( $outcome ) {
		if ( is_wp_error( $outcome ) ) {
			return $outcome;
		}

		return rest_ensure_response( array( 'state' => SetupService::state() ) );
	}

	/**
	 * JSON schema of the wizard state.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$nullable_string = array(
			'type'     => array( 'string', 'null' ),
			'readonly' => true,
		);
		$this->schema    = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-setup',
			'type'       => 'object',
			'properties' => array(
				'version'       => array(
					'type'     => 'integer',
					'readonly' => true,
				),
				'status'        => array(
					'type'     => 'string',
					'enum'     => array( 'not_started', 'in_progress', 'completed', 'dismissed' ),
					'readonly' => true,
				),
				'current_step'  => array(
					'type'     => array( 'string', 'null' ),
					'enum'     => array_merge( SetupSteps::ids(), array( null ) ),
					'readonly' => true,
				),
				'steps'         => array(
					'type'     => 'array',
					'readonly' => true,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'id'        => array(
								'type' => 'string',
								'enum' => SetupSteps::ids(),
							),
							'status'    => array(
								'type' => 'string',
								'enum' => array( 'pending', 'done', 'skipped' ),
							),
							'skippable' => array( 'type' => 'boolean' ),
							'available' => array( 'type' => 'boolean' ),
						),
					),
				),
				'answers'       => array(
					'type'       => 'object',
					'readonly'   => true,
					'properties' => array(
						'experience' => array(
							'type' => array( 'string', 'null' ),
							'enum' => array_merge( SetupSteps::EXPERIENCES, array( null ) ),
						),
						'niche'      => array(
							'type' => array( 'string', 'null' ),
							'enum' => array_merge( SetupSteps::NICHES, array( null ) ),
						),
						'topics'     => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'preferences'   => array(
					'type'       => 'object',
					'readonly'   => true,
					'properties' => array(
						'currency'           => array( 'type' => 'string' ),
						'currency_pos'       => array( 'type' => 'string' ),
						'price_num_decimals' => array( 'type' => 'integer' ),
					),
				),
				'starter_pages' => array(
					'type'     => 'array',
					'readonly' => true,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'key'     => array( 'type' => 'string' ),
							'title'   => array( 'type' => 'string' ),
							'slug'    => array( 'type' => 'string' ),
							'page_id' => array( 'type' => 'integer' ),
							'status'  => array(
								'type' => 'string',
								'enum' => array( 'ready', 'adoptable', 'missing' ),
							),
						),
					),
				),
				'started_at'    => $nullable_string,
				'completed_at'  => $nullable_string,
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
