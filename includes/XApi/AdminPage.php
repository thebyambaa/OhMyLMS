<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Settings screen for xAPI.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy hidden xAPI screen. The primary UI lives under OhMyLMS > Settings > xAPI.
 *
 * Every action is an admin-post handler that checks the capability and a nonce, then redirects
 * back with a short notice code (never a message taken from the request).
 */
final class AdminPage {

	const SLUG       = 'ohmylms-xapi';
	const CAPABILITY = 'manage_options';

	/**
	 * Register the screen and its handlers.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 30 );
		add_action( 'admin_post_ohmylms_xapi_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_ohmylms_xapi_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_ohmylms_xapi_retry', array( __CLASS__, 'handle_retry' ) );
		add_action( 'admin_post_ohmylms_xapi_send', array( __CLASS__, 'handle_send' ) );
	}

	/**
	 * Retain the legacy settings URL without adding a separate submenu entry.
	 */
	public static function menu() {
		add_submenu_page(
			defined( 'OHMYLMS_SLUG' ) ? OHMYLMS_SLUG : 'ohmylms',
			__( 'xAPI', 'ohmylms' ),
			__( 'xAPI', 'ohmylms' ),
			self::CAPABILITY,
			self::SLUG,
			array( __CLASS__, 'render' )
		);
		remove_submenu_page( defined( 'OHMYLMS_SLUG' ) ? OHMYLMS_SLUG : 'ohmylms', self::SLUG );
	}

	/**
	 * Screen URL, optionally with a notice code.
	 *
	 * @param string $notice Notice code.
	 * @return string
	 */
	private static function url( $notice = '' ) {
		$url = admin_url( 'admin.php?page=ohmylms#/settings/xapi-settings' );

		return '' === $notice ? $url : add_query_arg( 'xapi_notice', $notice, $url );
	}

	/**
	 * Common guard for the handlers.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage xAPI settings.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Save settings.
	 */
	public static function handle_save() {
		self::guard( 'ohmylms_xapi_save' );
		Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
		Processor::schedule();
		wp_safe_redirect( self::url( 'saved' ) );
		exit;
	}

	/**
	 * Test the saved connection.
	 */
	public static function handle_test() {
		self::guard( 'ohmylms_xapi_test' );
		$result = LrsClient::test();
		set_transient( 'ohmylms_xapi_test_' . get_current_user_id(), $result, MINUTE_IN_SECONDS );
		wp_safe_redirect( self::url( $result['ok'] ? 'test_ok' : 'test_failed' ) );
		exit;
	}

	/**
	 * Requeue failed statements.
	 */
	public static function handle_retry() {
		self::guard( 'ohmylms_xapi_retry' );
		Outbox::requeue_failed();
		wp_safe_redirect( self::url( 'requeued' ) );
		exit;
	}

	/**
	 * Send what is queued now instead of waiting for the next cron run.
	 */
	public static function handle_send() {
		self::guard( 'ohmylms_xapi_send' );
		Processor::process( Processor::BATCH, 20 );
		wp_safe_redirect( self::url( 'sent' ) );
		exit;
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to manage xAPI settings.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		$settings = Settings::all();
		$action   = admin_url( 'admin-post.php' );

		echo '<div class="wrap"><h1>' . esc_html__( 'xAPI', 'ohmylms' ) . '</h1>';
		self::notices();
		echo '<p>' . esc_html__( 'Send learning activity to a Learning Record Store (LRS) as xAPI statements. Statements are queued and sent in the background, so a slow or offline LRS never slows learners down.', 'ohmylms' ) . '</p>';

		echo '<form method="post" action="' . esc_url( $action ) . '">';
		echo '<input type="hidden" name="action" value="ohmylms_xapi_save">';
		wp_nonce_field( 'ohmylms_xapi_save' );
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__( 'Send statements', 'ohmylms' ) . '</th><td><label><input type="checkbox" name="enabled" value="1" ' . checked( ! empty( $settings['enabled'] ), true, false ) . '> ' . esc_html__( 'Enabled', 'ohmylms' ) . '</label></td></tr>';

		self::text_row( 'endpoint', __( 'LRS endpoint', 'ohmylms' ), $settings['endpoint'], 'url', __( 'For example https://lrs.example.com/xapi', 'ohmylms' ) );
		self::text_row( 'key', __( 'Key (username)', 'ohmylms' ), $settings['key'], 'text', '' );
		self::text_row( 'secret', __( 'Secret (password)', 'ohmylms' ), '', 'password', Settings::locked( 'secret' ) ? __( 'Set in wp-config.php.', 'ohmylms' ) : ( '' !== $settings['secret'] ? __( 'A secret is saved. Leave blank to keep it.', 'ohmylms' ) : '' ) );

		echo '<tr><th scope="row"><label for="ohmylms-xapi-actor">' . esc_html__( 'Learner identity', 'ohmylms' ) . '</label></th><td><select id="ohmylms-xapi-actor" name="actor">';
		$modes = array(
			'pseudonym' => __( 'Pseudonym (recommended): a stable code, no name or email', 'ohmylms' ),
			'user_id'   => __( 'WordPress user ID', 'ohmylms' ),
			'mbox'      => __( 'Email address', 'ohmylms' ),
		);
		foreach ( $modes as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $settings['actor'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">' . esc_html__( 'Email addresses send personal data to the LRS. Learners without an email address are skipped in that mode.', 'ohmylms' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Report', 'ohmylms' ) . '</th><td><fieldset>';
		foreach ( Settings::groups() as $group => $label ) {
			echo '<label><input type="checkbox" name="groups[' . esc_attr( $group ) . ']" value="1" ' . checked( ! empty( $settings['groups'][ $group ] ), true, false ) . '> ' . esc_html( $label ) . '</label><br>';
		}
		echo '</fieldset></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Answers', 'ohmylms' ) . '</th><td><label><input type="checkbox" name="responses" value="1" ' . checked( ! empty( $settings['responses'] ), true, false ) . '> ' . esc_html__( 'Include what the learner answered', 'ohmylms' ) . '</label></td></tr>';

		echo '<tr><th scope="row"><label for="ohmylms-xapi-retention">' . esc_html__( 'Keep sent records', 'ohmylms' ) . '</label></th><td><input type="number" id="ohmylms-xapi-retention" name="retention_days" min="1" max="365" value="' . esc_attr( (string) $settings['retention_days'] ) . '" class="small-text"> ' . esc_html__( 'days', 'ohmylms' ) . '</td></tr>';
		echo '</table>';
		submit_button( __( 'Save settings', 'ohmylms' ) );
		echo '</form>';

		self::status( $action );
		echo '</div>';
	}

	/**
	 * Render one text input row.
	 *
	 * @param string $name        Field name.
	 * @param string $label       Label.
	 * @param string $value       Current value.
	 * @param string $type        Input type.
	 * @param string $description Help text.
	 */
	private static function text_row( $name, $label, $value, $type, $description ) {
		$locked = Settings::locked( $name );
		echo '<tr><th scope="row"><label for="ohmylms-xapi-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="' . esc_attr( $type ) . '" id="ohmylms-xapi-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off" ' . disabled( $locked, true, false ) . '>';
		if ( $locked ) {
			echo '<p class="description">' . esc_html__( 'Set in wp-config.php.', 'ohmylms' ) . '</p>';
		} elseif ( '' !== $description ) {
			echo '<p class="description">' . esc_html( $description ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Delivery status and actions.
	 *
	 * @param string $action admin-post URL.
	 */
	private static function status( $action ) {
		if ( ! Schema::ready() ) {
			return;
		}
		$counts = Outbox::counts();
		$health = Processor::health();

		echo '<h2>' . esc_html__( 'Delivery', 'ohmylms' ) . '</h2>';
		echo '<table class="widefat striped" style="max-width:32rem"><tbody>';
		foreach ( array(
			'pending' => __( 'Waiting to send', 'ohmylms' ),
			'sent'    => __( 'Sent', 'ohmylms' ),
			'failed'  => __( 'Failed', 'ohmylms' ),
			'skipped' => __( 'Skipped (nothing to report)', 'ohmylms' ),
		) as $status => $label ) {
			echo '<tr><td>' . esc_html( $label ) . '</td><td>' . esc_html( number_format_i18n( $counts[ $status ] ) ) . '</td></tr>';
		}
		echo '<tr><td>' . esc_html__( 'Last successful send', 'ohmylms' ) . '</td><td>' . esc_html( ! empty( $health['last_success'] ) ? wp_date( 'Y-m-d H:i:s', (int) $health['last_success'] ) : __( 'Never', 'ohmylms' ) ) . '</td></tr>';
		if ( ! empty( $health['last_error'] ) ) {
			echo '<tr><td>' . esc_html__( 'Last problem', 'ohmylms' ) . '</td><td>' . esc_html( $health['last_error'] ) . ' (' . esc_html( wp_date( 'Y-m-d H:i:s', (int) $health['last_error_at'] ) ) . ')</td></tr>';
		}
		echo '</tbody></table>';

		echo '<div style="display:flex;gap:.5rem;flex-wrap:wrap">';
		foreach ( array(
			'test'  => __( 'Test connection', 'ohmylms' ),
			'send'  => __( 'Send queued now', 'ohmylms' ),
			'retry' => __( 'Retry failed', 'ohmylms' ),
		) as $name => $label ) {
			echo '<form method="post" action="' . esc_url( $action ) . '">';
			echo '<input type="hidden" name="action" value="ohmylms_xapi_' . esc_attr( $name ) . '">';
			wp_nonce_field( 'ohmylms_xapi_' . $name );
			submit_button( $label, 'secondary', 'submit', false );
			echo '</form>';
		}
		echo '</div><p class="description">' . esc_html__( 'Test connection uses the saved settings, so save first.', 'ohmylms' ) . '</p>';

		$failed = Outbox::recent_failed( 10 );
		if ( $failed ) {
			echo '<h3>' . esc_html__( 'Recent failures', 'ohmylms' ) . '</h3><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Event', 'ohmylms' ) . '</th><th>' . esc_html__( 'Attempts', 'ohmylms' ) . '</th><th>' . esc_html__( 'Problem', 'ohmylms' ) . '</th></tr></thead><tbody>';
			foreach ( $failed as $row ) {
				echo '<tr><td>' . esc_html( $row['kind'] ) . '</td><td>' . esc_html( (string) $row['tries'] ) . '</td><td>' . esc_html( (string) $row['last_error'] ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Show the notice for the code in the query string.
	 */
	private static function notices() {
		$code = isset( $_GET['xapi_notice'] ) ? sanitize_key( wp_unslash( $_GET['xapi_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( '' === $code ) {
			return;
		}
		$messages = array(
			'saved'    => array( 'success', __( 'Settings saved.', 'ohmylms' ) ),
			'requeued' => array( 'success', __( 'Failed statements were queued again.', 'ohmylms' ) ),
			'sent'     => array( 'success', __( 'Queued statements were processed.', 'ohmylms' ) ),
		);
		if ( in_array( $code, array( 'test_ok', 'test_failed' ), true ) ) {
			$key    = 'ohmylms_xapi_test_' . get_current_user_id();
			$result = get_transient( $key );
			delete_transient( $key );
			if ( is_array( $result ) ) {
				$messages[ $code ] = array( 'test_ok' === $code ? 'success' : 'error', (string) $result['message'] );
			}
		}
		if ( isset( $messages[ $code ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $messages[ $code ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $code ][1] ) . '</p></div>';
		}
	}
}
