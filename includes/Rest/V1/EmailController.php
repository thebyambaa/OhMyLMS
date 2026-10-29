<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * EmailController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Emails\EmailManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * EmailController class.
 *
 * REST API for the system emails under `ohmylms/v1/emails`. See docs/api/emails.md for the contract.
 *
 * Authentication is WordPress core's: cookie authentication requires the `X-WP-Nonce` header
 * (verified by rest_cookie_check_errors()); application passwords also work. Every route requires
 * the CAPABILITY below. Validation and storage live in {@see EmailManager}.
 *
 * @since 1.0.0
 */
class EmailController extends RestController {

	/**
	 * Capability required by every email route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'ohmylms/v1';

	/**
	 * The base route for email endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'emails';

	/**
	 * Register the routes for the email endpoints.
	 *
	 * The `settings` route is registered before the item route so the literal path wins over the
	 * `(?P<id>...)` pattern.
	 */
	public function register_routes() {
		$base = '/' . $this->rest_base;
		$item = $base . '/(?P<id>[a-z][a-z0-9_]*)';
		$id   = array(
			'description' => __( 'Unique identifier for the email.', 'ohmylms' ),
			'type'        => 'string',
			'pattern'     => '^[a-z][a-z0-9_]*$',
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
			$base . '/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => rest_get_endpoint_args_for_schema( $this->get_settings_schema(), WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_settings_schema' ),
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/preview',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'preview_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$item . '/test',
			array(
				'args' => array( 'id' => $id ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'test_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
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
	 * Check if the current user can list emails or read the settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_email_cannot_view', __( 'Sorry, you are not allowed to view emails.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can read one email (also used by preview).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_email_cannot_view', __( 'Sorry, you are not allowed to view emails.', 'ohmylms' ) );
	}

	/**
	 * Check if the current user can edit emails (also used by settings update and test sending).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return $this->require_capability( 'ohmylms_email_cannot_edit', __( 'Sorry, you are not allowed to edit emails.', 'ohmylms' ) );
	}

	/**
	 * Build the not-found error.
	 *
	 * @return WP_Error
	 */
	private function not_found_error() {
		return new WP_Error( 'ohmylms_email_not_found', __( 'Email not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * Names of the editable fields present in a request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string[]        $fields  Field names to look for.
	 * @return array
	 */
	private function request_fields( $request, array $fields ) {
		$input = array();
		foreach ( $fields as $field ) {
			if ( $request->has_param( $field ) ) {
				$input[ $field ] = $request->get_param( $field );
			}
		}
		return $input;
	}

	/**
	 * Editable field names of an email.
	 *
	 * @return string[]
	 */
	private function editable_fields() {
		return array( 'enabled', 'subject', 'heading', 'additional_content', 'button_text', 'button_link', 'footer_text', 'course_suggestion_text', 'recipient_email' );
	}

	/**
	 * Get emails.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$items  = EmailManager::all();
		$search = trim( (string) $request['search'] );
		$status = (string) $request['status'];
		$type   = (string) $request['recipient_type'];

		$items = array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $search, $status, $type ) {
					if ( 'enabled' === $status && ! $item['enabled'] ) {
						return false;
					}
					if ( 'disabled' === $status && $item['enabled'] ) {
						return false;
					}
					if ( in_array( $type, array( 'student', 'creator' ), true ) && $type !== $item['recipient_type'] ) {
						return false;
					}
					if ( '' !== $search ) {
						$haystack = $item['title'] . ' ' . $item['description'] . ' ' . $item['subject'];
						return false !== mb_stripos( $haystack, $search );
					}
					return true;
				}
			)
		);

		$page        = max( 1, (int) $request['page'] );
		$per_page    = min( 100, max( 1, (int) $request['per_page'] ) );
		$total       = count( $items );
		$total_pages = (int) ceil( $total / $per_page );

		if ( $page > $total_pages && $total > 0 ) {
			return new WP_Error( 'ohmylms_email_invalid_page', __( 'The page number requested is larger than the number of pages available.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$data = array();
		foreach ( array_slice( $items, ( $page - 1 ) * $per_page, $per_page ) as $item ) {
			$data[] = $this->prepare_response_for_collection( $this->prepare_item_for_response( $item, $request ) );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		return $response;
	}

	/**
	 * Get one email.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		$item = EmailManager::get( (string) $request['id'] );
		if ( null === $item ) {
			return $this->not_found_error();
		}
		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Update one email. Only the fields sent are changed.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$email_id = (string) $request['id'];
		if ( ! EmailManager::exists( $email_id ) ) {
			return $this->not_found_error();
		}

		$input = $this->request_fields( $request, $this->editable_fields() );
		if ( ! $input ) {
			return new WP_Error( 'ohmylms_email_empty_update', __( 'Send at least one field to change.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$patch = EmailManager::sanitize_patch( $email_id, $input );
		if ( is_wp_error( $patch ) ) {
			return $patch;
		}

		$item = EmailManager::save( $email_id, $patch );
		if ( null === $item ) {
			return new WP_Error( 'ohmylms_email_save_failed', __( 'The email could not be saved.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires after an email's settings are saved through the REST API.
		 *
		 * @param string $email_id Email id.
		 * @param array  $item     The saved email as returned by the API.
		 */
		do_action( 'ohmylms_email_updated', $email_id, $item );

		return $this->prepare_item_for_response( $item, $request );
	}

	/**
	 * Render an email with its real template and sample data. Unsaved fields in the body are applied
	 * over the stored email, so the editor can preview a draft.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function preview_item( $request ) {
		$email_id = (string) $request['id'];
		if ( ! EmailManager::exists( $email_id ) ) {
			return $this->not_found_error();
		}

		$result = EmailManager::preview( $email_id, $this->request_fields( $request, $this->editable_fields() ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Send a test email, with sample data, to the current administrator only.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_item( $request ) {
		$email_id = (string) $request['id'];
		if ( ! EmailManager::exists( $email_id ) ) {
			return $this->not_found_error();
		}

		$result = EmailManager::send_test( $email_id, wp_get_current_user(), $this->request_fields( $request, $this->editable_fields() ) );
		if ( is_wp_error( $result ) ) {
			if ( 'ohmylms_email_test_rate_limited' === $result->get_error_code() ) {
				$data     = $result->get_error_data();
				$response = rest_convert_error_to_response( $result );
				$response->header( 'Retry-After', (string) ( isset( $data['retry_after'] ) ? (int) $data['retry_after'] : EmailManager::TEST_INTERVAL ) );
				return $response;
			}
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * Get the global email settings (sender, branding, colors).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_settings( $request ) {
		return rest_ensure_response( EmailManager::get_global() );
	}

	/**
	 * Update the global email settings. Only the fields sent are changed.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( $request ) {
		$fields = array_keys( $this->get_settings_schema()['properties'] );
		$input  = $this->request_fields( $request, $fields );
		if ( ! $input ) {
			return new WP_Error( 'ohmylms_email_empty_update', __( 'Send at least one field to change.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$options = EmailManager::sanitize_global( $input );
		if ( is_wp_error( $options ) ) {
			return $options;
		}

		return rest_ensure_response( EmailManager::save_global( $options ) );
	}

	/**
	 * Prepare one email for the response.
	 *
	 * @param array           $item    Email from {@see EmailManager::get()}.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $item, $request ) {
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->filter_response_by_context( $item, $context );
		$data    = $this->add_additional_fields_to_object( $data, $request );

		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( $this->namespace . '/' . $this->rest_base . '/' . $item['id'] ) ),
				'collection' => array( 'href' => rest_url( $this->namespace . '/' . $this->rest_base ) ),
			)
		);
		return $response;
	}

	/**
	 * Collection arguments.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		return array(
			'context'        => $this->get_context_param( array( 'default' => 'view' ) ),
			'page'           => array(
				'description' => __( 'Current page of the collection.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 1,
				'minimum'     => 1,
			),
			'per_page'       => array(
				'description' => __( 'Maximum number of items to return.', 'ohmylms' ),
				'type'        => 'integer',
				'default'     => 20,
				'minimum'     => 1,
				'maximum'     => 100,
			),
			'search'         => array(
				'description' => __( 'Limit results to emails whose title, description or subject contains this text.', 'ohmylms' ),
				'type'        => 'string',
				'maxLength'   => 100,
			),
			'status'         => array(
				'description' => __( 'Limit results by whether the email is switched on.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'all',
				'enum'        => array( 'all', 'enabled', 'disabled' ),
			),
			'recipient_type' => array(
				'description' => __( 'Limit results by who receives the email.', 'ohmylms' ),
				'type'        => 'string',
				'default'     => 'all',
				'enum'        => array( 'all', 'student', 'creator' ),
			),
		);
	}

	/**
	 * Email schema (JSON Schema draft 4, as WordPress uses).
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$contexts     = array( 'view', 'edit' );
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-email',
			'type'       => 'object',
			'properties' => array(
				'id'                     => array(
					'description' => __( 'Unique identifier of the email.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $contexts,
					'readonly'    => true,
				),
				'title'                  => array(
					'description' => __( 'Name of the email.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $contexts,
					'readonly'    => true,
				),
				'description'            => array(
					'description' => __( 'When the email is sent.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => $contexts,
					'readonly'    => true,
				),
				'recipient_type'         => array(
					'description' => __( 'Who receives the email.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'student', 'creator' ),
					'context'     => $contexts,
					'readonly'    => true,
				),
				'enabled'                => array(
					'description' => __( 'Whether the email is sent.', 'ohmylms' ),
					'type'        => 'boolean',
					'context'     => $contexts,
				),
				'subject'                => array(
					'description' => __( 'Subject line. Plain text; merge tags allowed.', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => EmailManager::MAX_SUBJECT * 4,
					'context'     => $contexts,
				),
				'heading'                => array(
					'description' => __( 'Heading at the top of the message. Plain text; merge tags allowed.', 'ohmylms' ),
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => EmailManager::MAX_HEADING * 4,
					'context'     => $contexts,
				),
				'additional_content'     => array(
					'description' => __( 'Message body as HTML limited to the email allow-list; merge tags allowed.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => EmailManager::MAX_HTML * 4,
					'context'     => $contexts,
				),
				'button_text'            => array(
					'description' => __( 'Text of the call-to-action button. Empty hides the button where the template allows it.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => EmailManager::MAX_BUTTON * 4,
					'context'     => $contexts,
				),
				'button_link'            => array(
					'description' => __( 'Address of the button: empty, or a full http or https URL. Emails that compute their own destination ignore it.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => EmailManager::MAX_LINK * 2,
					'context'     => $contexts,
				),
				'footer_text'            => array(
					'description' => __( 'Closing text as HTML limited to the email allow-list; merge tags allowed.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => EmailManager::MAX_FOOTER * 4,
					'context'     => $contexts,
				),
				'course_suggestion_text' => array(
					'description' => __( 'Text suggesting further courses. Null for emails that do not have it.', 'ohmylms' ),
					'type'        => array( 'string', 'null' ),
					'maxLength'   => EmailManager::MAX_FOOTER * 4,
					'context'     => $contexts,
				),
				'recipient_email'        => array(
					'description' => __( 'Addresses of staff emails. Empty uses the default (the site administrator or the course author). Student emails always go to the student.', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'maxItems'    => EmailManager::MAX_RECIPIENTS * 2,
					'context'     => $contexts,
				),
				'tags'                   => array(
					'description' => __( 'Merge tags this email supports.', 'ohmylms' ),
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'tag'         => array( 'type' => 'string' ),
							'label'       => array( 'type' => 'string' ),
							'description' => array( 'type' => 'string' ),
						),
					),
					'context'     => $contexts,
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Schema of the global email settings.
	 *
	 * @return array
	 */
	public function get_settings_schema() {
		$contexts = array( 'view', 'edit' );
		$color    = static function ( $description ) use ( $contexts ) {
			return array(
				'description' => $description,
				'type'        => 'string',
				'pattern'     => '^#[0-9A-Fa-f]{6}$',
				'context'     => $contexts,
			);
		};

		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-email-settings',
			'type'       => 'object',
			'properties' => array(
				'branding_image'        => array(
					'description' => __( 'Logo shown at the top of every email: empty, or a full http or https image URL.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => EmailManager::MAX_LINK * 2,
					'context'     => $contexts,
				),
				'base_color'            => $color( __( 'Accent color of headings, buttons and the frame (#RRGGBB).', 'ohmylms' ) ),
				'background_color'      => $color( __( 'Page background color (#RRGGBB).', 'ohmylms' ) ),
				'body_background_color' => $color( __( 'Message background color (#RRGGBB).', 'ohmylms' ) ),
				'body_text_color'       => $color( __( 'Message text color (#RRGGBB).', 'ohmylms' ) ),
				'button_position'       => array(
					'description' => __( 'Alignment of the call-to-action button.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'left', 'center', 'right' ),
					'context'     => $contexts,
				),
				'sender_name'           => array(
					'description' => __( 'Name in the From header. No line breaks, quotes or angle brackets.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 400,
					'context'     => $contexts,
				),
				'sender_email'          => array(
					'description' => __( 'Address in the From header. The From header is only set when name and address are both present.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 254,
					'context'     => $contexts,
				),
				'footer_text'           => array(
					'description' => __( 'Extra text under every email as HTML limited to the email allow-list.', 'ohmylms' ),
					'type'        => 'string',
					'maxLength'   => 4000,
					'context'     => $contexts,
				),
			),
		);
	}
}
