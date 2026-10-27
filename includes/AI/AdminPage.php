<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Settings → AI tutor: connect a provider, set limits, test the connection.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\AI;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Settings → AI tutor: connect a provider, set limits, test the connection.
 *
 * The screen is a tab of the React Settings page. The API key is sent once to the server and
 * sealed; it is never returned, only its last four characters.
 */
final class AdminPage {
	/** The old WordPress submenu slug; it now forwards to the Settings tab. */
	const SLUG = 'ohmylms-ai';

	/** Where the tab lives in the admin app. */
	const TAB = 'ai-tutor-settings';

	/** Register shared AI tools routes and the legacy bookmark. */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'legacy_menu' ), 99 );
	}

	/** URL of the Settings tab. */
	public static function url() {
		return admin_url( 'admin.php?page=ohmylms#/settings/' . self::TAB );
	}

	/** Keep old bookmarks working: a hidden page that forwards to the tab. */
	public static function legacy_menu() {
		$hook = add_submenu_page( null, __( 'AI tutor', 'ohmylms' ), __( 'AI tutor', 'ohmylms' ), 'manage_options', self::SLUG, '__return_null' );
		if ( $hook ) {
			add_action(
				'load-' . $hook,
				static function () {
					wp_safe_redirect( self::url() );
					exit;
				}
			);
		}
	}

	/** Restrict provider credentials and tool policies to administrators. */
	public static function can() {
		return current_user_can( 'manage_options' );
	}

	/** Register settings, model discovery and connection tests. */
	public static function routes() {
		register_rest_route(
			'ohmylms/v1',
			'/ai/models',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'can' ),
				'callback'            => array( Models::class, 'listing' ),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/ai',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => array( __CLASS__, 'can' ),
					'callback'            => array( __CLASS__, 'read' ),
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'can' ),
					'callback'            => array( __CLASS__, 'save' ),
				),
			)
		);
		register_rest_route(
			'ohmylms/v1',
			'/ai/test',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'can' ),
				'callback'            => array( __CLASS__, 'test' ),
			)
		);
	}

	/** Everything the settings tab shows. Never contains the key. */
	public static function snapshot() {
		$style = get_option( 'ohmylms_practice_style', 'standard' );
		return array(
			'settings'       => Settings::summary(),
			'stats'          => Settings::stats(),
			'practice_style' => 'lesson' === $style ? 'lesson' : 'standard',
		);
	}

	/** Return settings without the saved key. */
	public static function read() {
		return rest_ensure_response( self::snapshot() );
	}

	/**
	 * Save independent tool policies and the shared connection.
	 *
	 * @param WP_REST_Request $request Administrator JSON input.
	 * @return \WP_REST_Response Updated safe snapshot.
	 */
	public static function save( WP_REST_Request $request ) {
		$input = $request->get_json_params();
		$input = is_array( $input ) ? $input : array();
		Settings::update( $input, ! empty( $input['clear_key'] ) );
		if ( isset( $input['practice_style'] ) ) {
			update_option( 'ohmylms_practice_style', 'lesson' === $input['practice_style'] ? 'lesson' : 'standard', false );
		}
		return rest_ensure_response( self::snapshot() );
	}

	/** Test saved credentials without enabling either tool. */
	public static function test() {
		if ( ! Settings::summary()['has_key'] ) {
			return new WP_Error( 'ai_no_key', __( 'Save an API key first.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		$started = microtime( true );
		$reply   = Client::complete(
			array(
				'system'     => 'You are a connection test. Reply with the single word OK.',
				'user'       => 'ping',
				'max_tokens' => 60,
			)
		);
		if ( is_wp_error( $reply ) ) {
			return rest_ensure_response(
				array(
					'ok'      => false,
					'message' => $reply->get_error_message(),
				)
			);
		}
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => sprintf(
					/* translators: 1: seconds, 2: reply text */
					__( 'Connected in %1$s s. The model replied: %2$s', 'ohmylms' ),
					number_format_i18n( microtime( true ) - $started, 1 ),
					Feedback::plain( $reply['text'], 60 )
				),
			)
		);
	}
}
