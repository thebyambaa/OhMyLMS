<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * LMS operations registered in WordPress's native Abilities API.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\MCP;

defined( 'ABSPATH' ) || exit;

/** LMS operations registered in WordPress's native Abilities API. */
final class Abilities {
	/**
	 * Init for Abilities.
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
		add_filter( 'mcp_adapter_tool_name', array( __CLASS__, 'tool_name' ), 10, 2 );
	}

	/**
	 * Category for Abilities.
	 */
	public static function category() {
		wp_register_ability_category(
			'ohmylms',
			array(
				'label'       => 'OhMyLMS',
				'description' => 'Administrator LMS content operations.',
			)
		);
	}

	/**
	 * Name for Abilities.
	 *
	 * @param mixed $tool Tool.
	 */
	public static function name( $tool ) {
		return 'ohmylms/' . str_replace( '_', '-', $tool );
	}

	/**
	 * Names for Abilities.
	 */
	public static function names() {
		return array_map( array( __CLASS__, 'name' ), array_keys( Server::tools() ) );
	}

	/**
	 * Tool name for Abilities.
	 *
	 * @param mixed $name Name.
	 * @param mixed $ability Ability.
	 */
	public static function tool_name( $name, $ability ) {
		// Preserve existing client allowlists while using valid, namespaced ability IDs.
		foreach ( Server::tools() as $tool => $definition ) {
			if ( $ability->get_name() === self::name( $tool ) ) {
				return $tool;
			}
		}
		return $name;
	}

	/**
	 * Register for Abilities.
	 */
	public static function register() {
		foreach ( Server::tools() as $name => $tool ) {
			wp_register_ability(
				self::name( $name ),
				array(
					'label'               => ucwords( str_replace( '_', ' ', $name ) ),
					'description'         => $tool['description'],
					'category'            => 'ohmylms',
					'input_schema'        => array(
						'type'                 => 'object',
						'properties'           => $tool['properties'],
						'required'             => $tool['required'],
						'additionalProperties' => false,
					),
					'output_schema'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'data'        => array( 'type' => array( 'array', 'object' ) ),
							'total'       => array( 'type' => 'integer' ),
							'total_pages' => array( 'type' => 'integer' ),
						),
						'required'             => array( 'data' ),
						'additionalProperties' => false,
					),
					'permission_callback' => static function () {
						return Server::enabled() && Server::can_manage(); },
					'execute_callback'    => static function ( $input ) use ( $name, $tool ) {
						return self::execute( $name, $tool, $input );
					},
					'meta'                => array(
						'show_in_rest' => true,
						// Exposed only on the explicitly configured LMS server, not the adapter's default discovery server.
						'mcp'          => array( 'public' => false ),
						'annotations'  => array(
							'readonly'    => $tool['readonly'] ?? true,
							'destructive' => $tool['destructive'] ?? false,
							'idempotent'  => $tool['idempotent'] ?? true,
						),
					),
				)
			);
		}
	}

	/**
	 * Execute for Abilities.
	 *
	 * @param mixed $name Name.
	 * @param mixed $tool Tool.
	 * @param mixed $input Input.
	 */
	private static function execute( $name, $tool, $input ) {
		$args = (array) $input;
		foreach ( $args as $key => $value ) {
			if ( ! isset( $tool['properties'][ $key ] ) || ( 'integer' === $tool['properties'][ $key ]['type'] && ! is_int( $value ) )
				|| ( 'string' === $tool['properties'][ $key ]['type'] && ! is_string( $value ) ) ) {
				return new \WP_Error( 'ohmylms_ability_invalid_input', 'Invalid argument type.' );
			}
		}
		$method  = $tool['method'] ?? 'GET';
		$route   = str_replace( array( '{id}', '{student_id}' ), array( (string) ( $args['id'] ?? '' ), (string) ( $args['student_id'] ?? '' ) ), $tool['route'] );
		$request = new \WP_REST_Request( $method, '/ohmylms/v1' . $route );
		if ( 'GET' !== $method ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $args ) );
		}
		if ( $tool['list'] ) {
			$request->set_query_params(
				array_merge(
					array(
						'page'     => 1,
						'per_page' => 10,
					),
					$args
				)
			);
		}
		try {
			// Internal REST dispatch retains all existing LMS permission and validation callbacks.
			$reply = rest_do_request( $request );
			if ( $reply->get_status() >= 400 ) {
				$payload = $reply->get_data();
				$result  = new \WP_Error( 'ohmylms_ability_failed', $payload['message'] ?? 'The LMS operation failed.', array( 'status' => $reply->get_status() ) );
			} else {
				$result  = array( 'data' => $reply->get_data() );
				$headers = $reply->get_headers();
				if ( isset( $headers['X-WP-Total'] ) ) {
					$result['total'] = (int) $headers['X-WP-Total'];
				}
				if ( isset( $headers['X-WP-TotalPages'] ) ) {
					$result['total_pages'] = (int) $headers['X-WP-TotalPages'];
				}
				$encoded = wp_json_encode( $result );
				if ( false === $encoded || strlen( $encoded ) > 262144 ) {
					$result = new \WP_Error( 'ohmylms_ability_result_too_large', 'Result is too large. Reduce per_page or retrieve an individual item.' );
				}
			}
		} catch ( \Throwable $exception ) {
			$result = new \WP_Error( 'ohmylms_ability_failed', 'The LMS operation failed.' );
		}
		do_action( 'ohmylms_mcp_tool_called', get_current_user_id(), $name, is_wp_error( $result ) );
		return $result;
	}
}
