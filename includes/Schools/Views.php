<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Platform school management and compatibility links for retired frontend views.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/** Keep legacy URLs and blocks usable without rendering a second frontend app. */
final class Views {
	/**
	 * Read scalar routing values; all resource IDs are authorized by their destination.
	 *
	 * @param string $key Query key.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private static function query_value( $key, $fallback = '' ) {
		return isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ) : $fallback; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only; download callers separately verify the nonce and resource access.
	}

	/** Canonical hub entry, retaining the old endpoint when no hub is configured. */
	public static function portal_url() {
		$hub = \OhMyLMS\Student\Hub::base_url();
		return $hub ? $hub : add_query_arg( 'ohmylms_portal', '1', home_url( '/' ) ); }
	/** Link existing dashboard hooks to the learner hub. */
	public static function dashboard_link() {
		if ( ! is_user_logged_in() ) {
			return; }
		echo '<p class="ohmylms-school-shortcut"><a href="' . esc_url( self::workspace_url( 'student-assignments' ) ) . '">' . esc_html__( 'Open school assignments and family learning', 'ohmylms' ) . '</a></p>';
	}
	/** Register the school styles the hub workspaces use. */
	public static function assets() {
		wp_register_style( 'ohmylms-schools', plugins_url( 'assets/schools/schools.css', OHMYLMS_FILE ), array(), filemtime( OHMYLMS_DIR . '/assets/schools/schools.css' ) );
	}
	/** Keep published legacy blocks and shortcodes pointing to the hub. */
	public static function blocks() {
		wp_register_script( 'ohmylms-school-blocks', plugins_url( 'assets/schools/blocks.js', OHMYLMS_FILE ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n' ), filemtime( OHMYLMS_DIR . '/assets/schools/blocks.js' ), true );
		foreach ( array( 'student-registration', 'teacher-registration', 'parent-registration', 'sign-in', 'school-dashboard', 'teacher-dashboard', 'parent-dashboard', 'student-assignments' ) as $kind ) {
			// Keep legacy tags registered; published blocks now link to the hub.
			if ( 'student-assignments' !== $kind ) {
				register_block_type(
					'ohmylms/' . $kind,
					array(
						'api_version'     => 2,
						'editor_script'   => 'ohmylms-school-blocks',
						'render_callback' => function () use ( $kind ) {
							return self::render( $kind );
						},
					)
				);
			}
			add_shortcode(
				'ohmylms_' . str_replace( '-', '_', $kind ),
				function () use ( $kind ) {
					return self::render( $kind );
				}
			);
		}
		add_action( 'template_redirect', array( self::class, 'portal' ) );
	}
	/**
	 * Admin address that replaces an old `admin.php?page=ohmylms-schools` address.
	 *
	 * @param array $query Scalar query values: school, class, tab.
	 * @return string
	 */
	public static function management_target( array $query ) {
		if ( ! empty( $query['school'] ) ) {
			return \OhMyLMS\Admin\LegacyRoutes::app_url( '/account-hub/schools/' . absint( $query['school'] ) );
		}
		if ( ! empty( $query['class'] ) ) {
			return \OhMyLMS\Admin\LegacyRoutes::app_url( '/account-hub/classes/' . absint( $query['class'] ) );
		}
		$tab = sanitize_key( (string) ( $query['tab'] ?? 'schools' ) );
		if ( 'students' === $tab ) {
			return \OhMyLMS\Admin\LegacyRoutes::app_url( '/students' );
		}
		if ( ! in_array( $tab, array( 'teachers', 'parents', 'users', 'classes', 'schools' ), true ) ) {
			$tab = 'schools';
		}
		return \OhMyLMS\Admin\LegacyRoutes::app_url( '/account-hub?tab=' . $tab );
	}
	/** Forward the retired school management page to the Account Hub, or school staff to their workspace. */
	public static function redirect_management() {
		if ( 'ohmylms-schools' !== self::query_value( 'page' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			// Non-administrators never use the WordPress dashboard for school work.
			$workspace = \OhMyLMS\Student\SchoolWorkspace::available() ? \OhMyLMS\Student\SchoolWorkspace::url() : \OhMyLMS\Student\Hub::base_url();
			wp_safe_redirect( $workspace ? $workspace : admin_url() );
			exit;
		}
		wp_safe_redirect(
			self::management_target(
				array(
					'school' => self::query_value( 'school' ),
					'class'  => self::query_value( 'class' ),
					'tab'    => self::query_value( 'tab', 'schools' ),
				)
			)
		);
		exit;
	}
	/** Keep the retired page registered so its old address reaches the redirect above. */
	public static function menu() {
		add_submenu_page(
			'',
			__( 'Account Hub', 'ohmylms' ),
			__( 'Account Hub', 'ohmylms' ),
			'manage_options',
			'ohmylms-schools',
			static function () {
				echo '<div class="wrap"><p><a href="' . esc_url( self::management_target( array() ) ) . '">' . esc_html__( 'Open the Account Hub', 'ohmylms' ) . '</a></p></div>';
			}
		);
	}
	/**
	 * Compatibility link to the native workspace for retired blocks and shortcodes.
	 *
	 * @param string $kind Requested legacy view.
	 * @return string
	 */
	public static function render( $kind ) {
		$target = self::workspace_url( $kind );
		return '<p class="ohmylms-school-shortcut"><a href="' . esc_url( $target ) . '">' . esc_html__( 'Continue to your learning hub', 'ohmylms' ) . '</a></p>';
	}
	/**
	 * Resolve a legacy embedded view to its native hub workspace.
	 *
	 * @param string $kind Legacy view identifier.
	 * @return string
	 */
	public static function workspace_url( $kind ) {
		$hub = \OhMyLMS\Student\Hub::base_url();
		if ( ! $hub ) {
			return wp_login_url( home_url( '/' ) );
		}
		if ( in_array( $kind, array( 'parent-registration', 'parent-dashboard' ), true ) ) {
			return \OhMyLMS\Student\ParentWorkspace::url();
		}
		if ( in_array( $kind, array( 'teacher-registration', 'teacher-dashboard' ), true ) ) {
			return \OhMyLMS\Student\TeacherWorkspace::url();
		}
		if ( 'school-dashboard' === $kind ) {
			return \OhMyLMS\Student\SchoolWorkspace::url();
		}
		return 'student-assignments' === $kind ? \OhMyLMS\Student\Hub::url( 'classes' ) : $hub;
	}

	/** Serve authorized submissions and redirect retired portal requests. */
	public static function portal() {
		if ( isset( $_GET['ohmylms_school_file'] ) ) {
			try {
				Access::require_access( is_user_logged_in() && wp_verify_nonce( self::query_value( '_wpnonce' ), 'ohmylms_school_file' ) );
				[, $attempt] = Service::submission_scope( absint( self::query_value( 'learning_id' ) ), absint( self::query_value( 'ohmylms_school_file' ) ) );
				$files       = maybe_unserialize( $attempt['files'] );
				$file        = is_array( $files ) && ! empty( $files['file'] ) ? realpath( $files['file'] ) : false;
				$uploads     = wp_upload_dir();
				$root        = realpath( $uploads['basedir'] );
				Access::require_access( $file && $root && strpos( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( $root ) ) ) === 0 && is_file( $file ) );
				nocache_headers();
				header( 'Content-Type: application/octet-stream' );
				header( 'X-Content-Type-Options: nosniff' );
				header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $file ) ) . '"' );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Stream a verified authorized upload without loading it into memory.
				readfile( $file );
				exit;
			} catch ( \Throwable $error ) {
				wp_die( esc_html__( 'You cannot download this submission.', 'ohmylms' ), '', array( 'response' => 403 ) ); }
		}
		if ( ! isset( $_GET['ohmylms_portal'] ) ) {
			return; }
		if ( 'GET' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			wp_die( esc_html__( 'Please open the learning hub to continue.', 'ohmylms' ), '', array( 'response' => 405 ) );
		}
		// Compatibility endpoint only: rendering the standalone portal is retired.
		if ( ! \OhMyLMS\Student\Hub::base_url() ) {
			wp_die( esc_html__( 'The learning hub is unavailable. Please contact the site administrator.', 'ohmylms' ), '', array( 'response' => 503 ) );
		}
		$view   = isset( $_GET['view'] ) && is_string( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'school-dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only legacy navigation.
		$target = self::workspace_url( $view );
		if ( isset( $_GET['class'] ) && is_scalar( $_GET['class'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The workspace reauthorizes the selected class.
			$target = add_query_arg( 'hub_class', absint( $_GET['class'] ), $target ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		}
		wp_safe_redirect( $target );
		exit;
	}
}
