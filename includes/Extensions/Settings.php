<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Schema-validated, namespaced extension settings for LMS content.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Extensions;

/** Schema-validated, namespaced extension settings for LMS content. */
final class Settings {
	/**
	 * Definitions.
	 *
	 * @var mixed
	 */
	private static $definitions = array();
	/**
	 * Contexts.
	 *
	 * @var mixed
	 */
	private static $contexts = array( 'course', 'lesson', 'quiz', 'question', 'membership', 'assignment', 'certificate', 'session' );

	/**
	 * Register for Settings.
	 *
	 * @param mixed $id Id.
	 * @param array $definition Definition.
	 * @throws \InvalidArgumentException When the operation cannot be completed.
	 */
	public static function register( $id, array $definition ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9_-]*$/D', $id ) || isset( self::$definitions[ $id ] ) ) {
			throw new \InvalidArgumentException( 'Invalid or duplicate settings ID.' );
		}
		if ( empty( $definition['contexts'] ) || array_diff( $definition['contexts'], self::$contexts ) || ( $definition['schema']['type'] ?? '' ) !== 'object' ) {
			throw new \InvalidArgumentException( 'Settings require supported contexts and an object schema.' );
		}
		if ( isset( $definition['sanitize'] ) && ! is_callable( $definition['sanitize'] ) ) {
			throw new \InvalidArgumentException( 'Settings sanitize must be callable.' );
		}
		$definition['schema']['additionalProperties'] = false;
		self::$definitions[ $id ]                     = $definition;
	}
	/**
	 * Manifest for Settings: the public definitions, without server callbacks.
	 */
	public static function manifest() {
		return array_map(
			static function ( $definition ) {
				unset( $definition['sanitize'] );
				return $definition;
			},
			self::$definitions
		);
	}
	/**
	 * Definitions that apply to one content context, without server callbacks.
	 *
	 * @param string $context Content context, for example `membership`.
	 * @return array Definitions keyed by settings ID.
	 */
	public static function for_context( $context ) {
		return array_filter(
			self::manifest(),
			static function ( $definition ) use ( $context ) {
				return in_array( $context, (array) $definition['contexts'], true );
			}
		);
	}
	/**
	 * Init for Settings.
	 */
	public static function init() {
		add_filter(
			'rest_dispatch_request',
			static function ( $response, $request, $route, $handler ) {
				return self::before_content( $response, $handler, $request );
			},
			10,
			4
		);
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_content' ), 10, 3 );
		add_action(
			'rest_api_init',
			static function () {
				register_rest_route(
					'ohmylms/v1',
					'/extension-settings/(?P<type>[a-z]+)/(?P<id>\d+)',
					array(
						array(
							'methods'             => 'GET',
							'callback'            => array( __CLASS__, 'read' ),
							'permission_callback' => array( __CLASS__, 'permission' ),
						),
						array(
							'methods'             => 'PUT',
							'callback'            => array( __CLASS__, 'save' ),
							'permission_callback' => array( __CLASS__, 'permission' ),
						),
					)
				);
			}
		);
	}
	/**
	 * Permission for Settings.
	 *
	 * @param mixed $request REST request.
	 */
	public static function permission( $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! in_array( $request['type'], self::$contexts, true ) || ! $post || $post->post_type !== 'ohmylms-' . $request['type'] ) {
			return new \WP_Error( 'ohmylms_invalid_content', 'Invalid LMS content.', array( 'status' => 404 ) );
		}
		return current_user_can( 'edit_post', $post->ID ) ? true : new \WP_Error( 'ohmylms_forbidden', 'You cannot edit this content.', array( 'status' => rest_authorization_required_code() ) );
	}
	/**
	 * Read for Settings.
	 *
	 * @param mixed $request REST request.
	 */
	public static function read( $request ) {
		$saved  = (array) get_post_meta( (int) $request['id'], '_ohmylms_extension_settings', true );
		$result = array();
		foreach ( self::$definitions as $id => $definition ) {
			if ( in_array( $request['type'], $definition['contexts'], true ) && isset( $saved[ $id ] ) ) {
				$result[ $id ] = $saved[ $id ]; }
		}
		return rest_ensure_response( array( 'settings' => (object) $result ) );
	}
	/**
	 * Save for Settings.
	 *
	 * @param mixed $request REST request.
	 */
	public static function save( $request ) {
		$incoming = $request->get_param( 'settings' );
		$saved    = self::prepare( $request['type'], $incoming, (array) get_post_meta( (int) $request['id'], '_ohmylms_extension_settings', true ) );
		if ( is_wp_error( $saved ) ) {
			return $saved; }
		update_post_meta( (int) $request['id'], '_ohmylms_extension_settings', wp_slash( $saved ) );
		return self::read( $request );
	}
	/**
	 * Prepare for Settings.
	 *
	 * @param mixed $type Type.
	 * @param mixed $incoming Incoming.
	 * @param array $saved Saved.
	 */
	public static function prepare( $type, $incoming, $saved = array() ) {
		if ( ! is_array( $incoming ) ) {
			return new \WP_Error( 'ohmylms_invalid_settings', 'settings must be an object.', array( 'status' => 400 ) ); }
		foreach ( $incoming as $id => $value ) {
			$definition = self::$definitions[ $id ] ?? null;
			if ( ! $definition || ! in_array( $type, $definition['contexts'], true ) ) {
				return new \WP_Error( 'ohmylms_unknown_settings', 'Unregistered extension settings.', array( 'status' => 400 ) );
			}
			$valid = rest_validate_value_from_schema( $value, $definition['schema'], $id );
			if ( is_wp_error( $valid ) ) {
				$valid->add_data( array( 'status' => 400 ) );
				return $valid; }
			$clean = rest_sanitize_value_from_schema( $value, $definition['schema'], $id );
			if ( is_wp_error( $clean ) ) {
				$clean->add_data( array( 'status' => 400 ) );
				return $clean; }
			if ( isset( $definition['sanitize'] ) ) {
				// The callback's result must still match the schema it was registered with.
				$clean = call_user_func( $definition['sanitize'], $clean, $type, $id );
				$valid = is_wp_error( $clean ) ? $clean : rest_validate_value_from_schema( $clean, $definition['schema'], $id );
				if ( is_wp_error( $valid ) ) {
					$valid->add_data( array( 'status' => 400 ) );
					return $valid; }
			}
			$saved[ $id ] = $clean;
		}
		return $saved;
	}
	/**
	 * Content type for Settings.
	 *
	 * @param mixed $request REST request.
	 */
	private static function content_type( $request ) {
		if ( ! preg_match( '#^/(?:ohmylms|ohmylms)/v1/(courses|lessons|quiz|question|membership|assignment|certificates)(?:/\d+)?/?$#', $request->get_route(), $match ) ) {
			return null; }
		return array(
			'courses'      => 'course',
			'lessons'      => 'lesson',
			'certificates' => 'certificate',
		)[ $match[1] ] ?? $match[1];
	}
	/**
	 * Before content for Settings.
	 *
	 * @param mixed $response Response data.
	 * @param mixed $handler Handler.
	 * @param mixed $request REST request.
	 */
	public static function before_content( $response, $handler, $request ) {
		if ( null !== $response || ! in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) || ! $request->has_param( 'extension_settings' ) ) {
			return $response; }
		$type = self::content_type( $request );
		if ( ! $type ) {
			return $response; }
		$prepared = self::prepare( $type, $request['extension_settings'] );
		return is_wp_error( $prepared ) ? $prepared : $response;
	}
	/**
	 * After content for Settings.
	 *
	 * @param mixed $response Response data.
	 * @param mixed $handler Handler.
	 * @param mixed $request REST request.
	 */
	public static function after_content( $response, $handler, $request ) {
		$type = self::content_type( $request );
		if ( ! $type || is_wp_error( $response ) || $request->get_method() === 'DELETE' ) {
			return $response; }
		$response = rest_ensure_response( $response );
		if ( $response->get_status() >= 400 ) {
			return $response; }
		$data = $response->get_data();
		$id   = is_array( $data ) ? (int) ( $data['id'] ?? 0 ) : 0;
		// Extension settings are author-only; never attach them to public responses.
		if ( ! $id || get_post_type( $id ) !== 'ohmylms-' . $type || ! current_user_can( 'edit_post', $id ) ) {
			return $response; }
		$saved = (array) get_post_meta( $id, '_ohmylms_extension_settings', true );
		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH' ), true ) && $request->has_param( 'extension_settings' ) ) {
			$saved = self::prepare( $type, $request['extension_settings'], $saved );
			if ( is_wp_error( $saved ) ) {
				return $saved; }
			update_post_meta( $id, '_ohmylms_extension_settings', wp_slash( $saved ) );
		}
		$data['extension_settings'] = (object) array_intersect_key( $saved, self::$definitions );
		$response->set_data( $data );
		return $response;
	}
}
