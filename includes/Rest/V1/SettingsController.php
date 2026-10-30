<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * SettingsController class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Rest\V1;

use OhMyLMS\Abstracts\RestController;
use OhMyLMS\Settings\SettingsRegistry;
use OhMyLMS\Settings\SettingsService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST API for the core settings under `ohmylms/v1/settings`.
 *
 * Groups, fields, schema and defaults come from {@see SettingsRegistry}; validation and storage
 * from {@see SettingsService}. Every route requires CAPABILITY. Cookie authentication is verified
 * by WordPress core (`X-WP-Nonce`). See docs/api/settings.md for the contract.
 *
 * @since 1.0.0
 */
class SettingsController extends RestController {

	/**
	 * Capability required by every settings route.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Maximum rows of the page picker.
	 */
	const PAGE_PICKER_LIMIT = 20;

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
		$base = '/' . $this->rest_base;

		register_rest_route(
			$this->namespace,
			$base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		foreach ( SettingsRegistry::group_ids() as $group ) {
			register_rest_route(
				$this->namespace,
				$base . '/' . $group,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => function ( $request ) use ( $group ) {
							return $this->get_group( $group, $request );
						},
						'permission_callback' => array( $this, 'get_items_permissions_check' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => function ( $request ) use ( $group ) {
							return $this->update_group( $group, $request );
						},
						'permission_callback' => array( $this, 'update_item_permissions_check' ),
					),
					'schema' => static function () use ( $group ) {
						return SettingsRegistry::schema( $group );
					},
				)
			);
		}

		register_rest_route(
			$this->namespace,
			$base . '/pages',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pages' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'search'   => array(
							'description'       => __( 'Limit results to pages whose title or content contains this text.', 'ohmylms' ),
							'type'              => 'string',
							'default'           => '',
							'maxLength'         => 100,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'per_page' => array(
							'description'       => __( 'Maximum number of pages to return.', 'ohmylms' ),
							'type'              => 'integer',
							'default'           => self::PAGE_PICKER_LIMIT,
							'minimum'           => 1,
							'maximum'           => self::PAGE_PICKER_LIMIT,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'include'  => array(
							'description'       => __( 'Return exactly these published pages (to label saved choices).', 'ohmylms' ),
							'type'              => 'array',
							'items'             => array( 'type' => 'integer' ),
							'maxItems'          => self::PAGE_PICKER_LIMIT,
							'default'           => array(),
							'sanitize_callback' => 'wp_parse_id_list',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/currencies',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_currencies' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/countries',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_countries' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/countries/(?P<code>[A-Z]{2})/states',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_states' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'code' => array(
							'description' => __( 'ISO 3166-1 alpha-2 country code.', 'ohmylms' ),
							'type'        => 'string',
							'pattern'     => '^[A-Z]{2}$',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/general/restore-pages',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_pages' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$base . '/advanced/clear-cache',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'clear_cache' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/integration-status/(?P<name>[\w,-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_integration_status' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'name' => array(
							'description' => __( 'Integration name(s), comma-separated.', 'ohmylms' ),
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Check that the user may read settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_settings_cannot_view', __( 'Sorry, you are not allowed to view settings.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * Check that the user may change settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error( 'ohmylms_settings_cannot_edit', __( 'Sorry, you are not allowed to change settings.', 'ohmylms' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}

	/**
	 * List the groups.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( SettingsRegistry::groups() as $id => $group ) {
			$data[] = array(
				'id'          => $id,
				'label'       => $group['label'],
				'description' => $group['description'],
				'fields'      => array_keys( $group['fields'] ),
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Build the response of one group.
	 *
	 * @param string $group   Group id.
	 * @param array  $changed Names of the fields that changed.
	 * @return WP_REST_Response
	 */
	private function group_response( $group, array $changed ) {
		$response = rest_ensure_response(
			array(
				'group'   => $group,
				'values'  => SettingsService::read( $group ),
				'changed' => $changed,
			)
		);
		$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $group ) );

		return $response;
	}

	/**
	 * Read one group.
	 *
	 * @param string          $group   Group id.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	private function get_group( $group, $request ) {
		return $this->group_response( $group, array() );
	}

	/**
	 * Update some fields of a group; the others are untouched.
	 *
	 * @param string          $group   Group id.
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function update_group( $group, $request ) {
		$values = SettingsService::validate( $group, $request->get_json_params() );
		if ( is_wp_error( $values ) ) {
			return $values;
		}
		$changed = SettingsService::write( $group, $values );
		if ( is_wp_error( $changed ) ) {
			return $changed;
		}

		return $this->group_response( $group, $changed );
	}

	/**
	 * Search published pages for the page pickers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_pages( $request ) {
		$include = array_filter( array_map( 'absint', (array) $request['include'] ) );
		$args    = array(
			'post_type'              => 'page',
			'post_status'            => 'publish',
			'posts_per_page'         => $include ? count( $include ) : (int) $request['per_page'],
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( $include ) {
			$args['post__in'] = $include;
			$args['orderby']  = 'post__in';
		} elseif ( '' !== (string) $request['search'] ) {
			$args['s'] = (string) $request['search'];
		}

		$pages = array();
		foreach ( get_posts( $args ) as $post ) {
			$pages[] = array(
				'id'    => (int) $post->ID,
				'title' => html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
				'slug'  => $post->post_name,
			);
		}

		return rest_ensure_response( $pages );
	}

	/**
	 * List supported currencies.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_currencies( $request ) {
		$currencies = array();
		foreach ( get_ohmylms_currencies() as $code => $name ) {
			$currencies[] = array(
				'code'   => (string) $code,
				'name'   => html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' ),
				'symbol' => html_entity_decode( wp_strip_all_tags( (string) get_ohmylms_currency_symbol( $code ) ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		return rest_ensure_response( $currencies );
	}

	/**
	 * List countries.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_countries( $request ) {
		$countries = array();
		foreach ( SettingsService::countries() as $code => $name ) {
			$countries[] = array(
				'code' => (string) $code,
				'name' => html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		return rest_ensure_response( $countries );
	}

	/**
	 * List the states of one country.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_states( $request ) {
		$code      = (string) $request['code'];
		$countries = SettingsService::countries();
		if ( ! isset( $countries[ $code ] ) ) {
			return new WP_Error( 'ohmylms_settings_country_not_found', __( 'Country not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		$all    = SettingsService::states();
		$states = array();
		foreach ( isset( $all[ $code ] ) ? $all[ $code ] : array() as $state => $name ) {
			$states[] = array(
				'code' => (string) $state,
				'name' => html_entity_decode( wp_strip_all_tags( (string) $name ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		return rest_ensure_response( $states );
	}

	/**
	 * Re-create the default LMS pages that are missing and point the settings at them.
	 *
	 * Filter `ohmylms_default_pages` still extends the list.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_pages( $request ) {
		$default_pages = apply_filters(
			'ohmylms_default_pages',
			array(
				'course'            => array(
					'name'    => 'ohmylms-all-courses',
					'title'   => _x( 'All Course', 'Page title', 'ohmylms' ),
					'content' => '',
				),
				'checkout'          => array(
					'name'     => 'ohmylms-checkout',
					'title'    => _x( 'OhMy Checkout', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_checkout]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-checkout',
				),
				'student_dashboard' => array(
					'name'     => 'my-dashboard',
					'title'    => _x( 'My Dashboard', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_dashboard]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-dashboard',
				),
				'student_profile'   => array(
					'name'     => 'my-profile',
					'title'    => _x( 'My Profile', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_profile]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-profile',
				),
				'student_courses'   => array(
					'name'     => 'my-courses',
					'title'    => _x( 'My Courses', 'Page title', 'ohmylms' ),
					'content'  => '<!-- wp:shortcode -->[ohmylms_my_courses]<!-- /wp:shortcode -->',
					'template' => 'ohmylms-my-courses',
				),
			)
		);

		$restored = array();
		foreach ( $default_pages as $key => $page ) {
			if ( ! is_string( $key ) || ! preg_match( '/^[a-z_]+$/', $key ) || empty( $page['title'] ) || empty( $page['name'] ) ) {
				continue;
			}
			$option  = 'ohmylms_' . $key . '_page_id';
			$page_id = absint( get_option( $option, 0 ) );
			$post    = $page_id ? get_post( $page_id ) : null;
			if ( $post && 'page' === $post->post_type && 'publish' === $post->post_status ) {
				continue;
			}

			$new_page_id = wp_insert_post(
				array(
					'post_title'     => $page['title'],
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_content'   => isset( $page['content'] ) ? $page['content'] : '',
					'post_author'    => get_current_user_id(),
					'comment_status' => 'closed',
					'post_name'      => $page['name'],
				),
				true
			);
			if ( is_wp_error( $new_page_id ) || ! $new_page_id ) {
				return new WP_Error( 'ohmylms_settings_restore_failed', __( 'A default page could not be created.', 'ohmylms' ), array( 'status' => 500 ) );
			}
			if ( ! empty( $page['template'] ) ) {
				update_post_meta( $new_page_id, '_wp_page_template', sanitize_text_field( $page['template'] ) );
			}
			update_option( $option, (int) $new_page_id );
			$restored[] = array(
				'key'     => $key,
				'page_id' => (int) $new_page_id,
				'title'   => $page['title'],
			);
		}

		return rest_ensure_response(
			array(
				'restored' => $restored,
				'values'   => SettingsService::read( 'general' ),
			)
		);
	}

	/**
	 * Delete OhMyLMS transients (cache only; settings and content are untouched).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function clear_cache( $request ) {
		global $wpdb;

		$patterns = array(
			$wpdb->esc_like( '_transient_ohmylms_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_ohmylms_' ) . '%',
			$wpdb->esc_like( '_site_transient_ohmylms_' ) . '%',
			$wpdb->esc_like( '_site_transient_timeout_ohmylms_' ) . '%',
		);
		$deleted  = (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Transient cleanup has no API for prefixes.
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$patterns[0],
				$patterns[1],
				$patterns[2],
				$patterns[3]
			)
		);
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		return rest_ensure_response( array( 'deleted' => $deleted ) );
	}

	/**
	 * Report whether integrations are switched on (used by other admin screens).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_integration_status( $request ) {
		$names    = array_filter( array_map( 'sanitize_key', explode( ',', (string) $request['name'] ) ) );
		$stored   = get_option( 'ohmylms_integrations', array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$is_pro   = apply_filters( 'ohmylms_is_pro', false );
		$statuses = array();
		foreach ( $names as $name ) {
			$statuses[ $name ] = ! empty( $stored[ $name ]['is_enable'] ) && 1 === (int) $stored[ $name ]['is_enable'] && $is_pro;
		}
		if ( 1 === count( $names ) ) {
			return rest_ensure_response( array( 'is_enabled' => reset( $statuses ) ) );
		}

		return rest_ensure_response( $statuses );
	}

	/**
	 * Index item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings-group',
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'string' ),
				'label'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
				'fields'      => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
		);
	}
}
