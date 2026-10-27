<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Add-ons registration and lifecycle for the bundled xAPI emitter.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/** Keeps the Add-ons switch and the existing connection settings in sync. */
final class Integration {

	/**
	 * Prevent option hooks from reflecting the same update back into its source.
	 *
	 * @var bool
	 */
	private static $synchronizing = false;

	/** Register the card and observe both settings surfaces. */
	public static function init() {
		add_filter( 'ohmylms_integrations', array( __CLASS__, 'manifest' ) );
		add_action( 'init', array( __CLASS__, 'migrate' ), 7 );
		add_action( 'update_option_ohmylms_integrations', array( __CLASS__, 'integrations_updated' ), 10, 2 );
		add_action( 'add_option_ohmylms_integrations', array( __CLASS__, 'integrations_added' ), 10, 2 );
		add_action( 'delete_option_ohmylms_integrations', array( __CLASS__, 'integrations_deleted' ) );
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'settings_updated' ), 10, 2 );
		add_action( 'add_option_' . Settings::OPTION, array( __CLASS__, 'settings_added' ), 10, 2 );
		add_action( 'delete_option_' . Settings::OPTION, array( __CLASS__, 'settings_deleted' ) );
		add_action( 'ohmylms_plugin_deactivated', array( __CLASS__, 'deactivate' ) );
	}

	/**
	 * Add the connection card without exposing LRS credentials.
	 *
	 * @param array $integrations Existing integration definitions.
	 * @return array
	 */
	public static function manifest( $integrations ) {
		$settings             = Settings::all();
		$integrations['xapi'] = array(
			'label'       => __( 'xAPI', 'ohmylms' ),
			'description' => __( 'Send enrolments, completions, quiz results, answers and skill mastery to a Learning Record Store.', 'ohmylms' ),
			'icon'        => plugins_url( 'assets/images/xapi-icon.svg', OHMYLMS_FILE ),
			'categories'  => array( 'connections' ),
			'hasSettings' => true,
			'class'       => '',
			'is_valid'    => true,
			'is_enable'   => empty( $settings['enabled'] ) ? 0 : 1,
		);

		return $integrations;
	}

	/** Adopt existing emitter settings without enabling a new installation. */
	public static function migrate() {
		$integrations = get_option( 'ohmylms_integrations', array() );
		if ( ! is_array( $integrations ) || ! isset( $integrations['xapi'] ) ) {
			self::settings_updated( array(), Settings::all() );
		}
	}

	/**
	 * Apply an Add-ons toggle, preserving connection and privacy settings.
	 *
	 * @param mixed $previous Previous integration switches.
	 * @param mixed $current Current integration switches.
	 */
	public static function integrations_updated( $previous, $current ) {
		if ( self::$synchronizing || ( ! isset( $previous['xapi'] ) && ! isset( $current['xapi'] ) ) ) {
			return;
		}
		$settings = get_option( Settings::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$enabled  = isset( $current['xapi']['is_enable'] ) && 1 === (int) $current['xapi']['is_enable'] ? 1 : 0;
		if ( (int) ( $settings['enabled'] ?? 0 ) !== $enabled ) {
			$settings['enabled'] = $enabled;
			self::$synchronizing = true;
			try {
				update_option( Settings::OPTION, $settings, false );
			} finally {
				self::$synchronizing = false;
			}
		}
		Processor::schedule();
	}

	/**
	 * Handle first creation of the Add-ons option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value Saved switches.
	 */
	public static function integrations_added( $option, $value ) {
		self::integrations_updated( array(), $value );
	}

	/** Disable delivery when the Add-ons option is removed. */
	public static function integrations_deleted() {
		self::integrations_updated( array( 'xapi' => array() ), array() );
	}

	/**
	 * Reflect connection-screen changes in the Add-ons switch.
	 *
	 * @param mixed $previous Previous emitter settings.
	 * @param mixed $current Current emitter settings.
	 */
	public static function settings_updated( $previous, $current ) {
		if ( self::$synchronizing ) {
			return;
		}
		$integrations = get_option( 'ohmylms_integrations', array() );
		$integrations = is_array( $integrations ) ? $integrations : array();
		$enabled      = empty( $current['enabled'] ) ? 0 : 1;
		if ( ! isset( $integrations['xapi']['is_enable'] ) || (int) $integrations['xapi']['is_enable'] !== $enabled ) {
			$integrations['xapi'] = array( 'is_enable' => $enabled );
			self::$synchronizing  = true;
			try {
				update_option( 'ohmylms_integrations', $integrations );
			} finally {
				self::$synchronizing = false;
			}
		}
		Processor::schedule();
	}

	/**
	 * Handle first creation of the emitter settings.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value Saved settings.
	 */
	public static function settings_added( $option, $value ) {
		self::settings_updated( array(), $value );
	}

	/** Disable the card when the connection settings are removed. */
	public static function settings_deleted() {
		self::settings_updated( array(), array() );
	}

	/** Stop background jobs on plugin deactivation, retaining settings and the outbox. */
	public static function deactivate() {
		wp_clear_scheduled_hook( Processor::HOOK );
		wp_clear_scheduled_hook( Processor::PRUNE_HOOK );
	}
}
