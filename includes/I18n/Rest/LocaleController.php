<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Learner interface language REST controller.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\I18n\Rest;

use OhMyLMS\I18n\InterfaceLanguage;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `GET|POST ohmylms/v1/learner/locale`: the interface language of the current learner or visitor
 * ({@see InterfaceLanguage}). POST stores `locale` in the signed-in user's `locale` meta and in the
 * `ohmylms_locale` cookie (visitors: the cookie only). Signed-in callers send the `wp_rest` nonce.
 */
class LocaleController extends WP_REST_Controller {

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
	protected $rest_base = 'learner/locale';

	/**
	 * Register the routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => '__return_true',
					'callback'            => array( $this, 'get_item' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'callback'            => array( $this, 'update_item' ),
					'args'                => array(
						'locale' => array(
							'description' => __( 'Interface locale.', 'ohmylms' ),
							'type'        => 'string',
							'enum'        => array_keys( InterfaceLanguage::locales() ),
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Anyone may choose; a signed-in user (cookie authentication) must send the `wp_rest` nonce, so
	 * another site cannot change their profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		if ( ! is_user_logged_in() || null !== rest_get_authenticated_app_password() ) {
			return true;
		}
		$nonce = $request->get_header( 'X-WP-Nonce' );
		$nonce = $nonce ? $nonce : $request->get_param( '_wpnonce' );
		if ( is_string( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return true;
		}
		return new WP_Error( 'ohmylms_locale_invalid_nonce', __( 'Your session has expired. Please reload the page and try again.', 'ohmylms' ), array( 'status' => 403 ) );
	}

	/**
	 * The current locale, where it comes from, and the choices.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		$current = InterfaceLanguage::current();
		return rest_ensure_response( $this->prepare( $current['locale'], $current['source'] ) );
	}

	/**
	 * Store the choice.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$locale = (string) $request['locale'];
		$source = InterfaceLanguage::save( $locale, get_current_user_id() );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$response = rest_ensure_response( $this->prepare( $locale, $source ) );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Response body.
	 *
	 * @param string $locale Locale.
	 * @param string $source Source.
	 * @return array
	 */
	private function prepare( $locale, $source ) {
		$locales = array();
		foreach ( InterfaceLanguage::locales() as $code => $name ) {
			$locales[] = array(
				'locale' => $code,
				'tag'    => str_replace( '_', '-', $code ),
				'name'   => $name,
			);
		}
		return array(
			'locale'  => $locale,
			'source'  => $source,
			'locales' => $locales,
		);
	}

	/**
	 * Item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}
		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'ohmylms-learner-locale',
			'type'       => 'object',
			'properties' => array(
				'locale'  => array(
					'description' => __( 'Interface locale.', 'ohmylms' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'source'  => array(
					'description' => __( 'Where the locale comes from.', 'ohmylms' ),
					'type'        => 'string',
					'enum'        => array( 'user', 'cookie', 'browser', 'default' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'locales' => array(
					'description' => __( 'Languages learners can choose.', 'ohmylms' ),
					'type'        => 'array',
					'context'     => array( 'view' ),
					'readonly'    => true,
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'locale' => array( 'type' => 'string' ),
							'tag'    => array( 'type' => 'string' ),
							'name'   => array( 'type' => 'string' ),
						),
					),
				),
			),
		);
		return $this->add_additional_fields_schema( $this->schema );
	}
}
