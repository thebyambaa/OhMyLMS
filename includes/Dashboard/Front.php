<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The front-end dashboard page: one route, a minimal template, and the React app.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes /dashboard and decides which nav a signed-in person may open.
 */
final class Front {

	const OPTION    = 'ohmylms_front_dashboard_page';
	const SLUG      = 'dashboard';
	const SHORTCODE = 'ohmylms_front_dashboard';

	/** Register the page, template and assets. */
	public static function init() {
		// After Shortcodes::init, which owns the learner [ohmylms_dashboard] tag.
		add_action( 'init', array( __CLASS__, 'register_shortcode' ), 11 );
		add_action( 'admin_init', array( __CLASS__, 'install_page' ) );
		add_action( 'template_redirect', array( __CLASS__, 'guard' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 100 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'hide_admin_bar' ) );
	}

	/** Register the front dashboard shortcode without replacing the learner dashboard. */
	public static function register_shortcode() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Send guests to the login screen before any template output.
	 */
	public static function guard() {
		if ( self::is_page() && ! is_user_logged_in() ) {
			auth_redirect();
		}
	}

	/**
	 * The dashboard page has its own user menu, so the WordPress bar stays off.
	 *
	 * @param bool $show Whether the bar would show.
	 * @return bool
	 */
	public static function hide_admin_bar( $show ) {
		return self::is_page() ? false : $show;
	}

	/**
	 * Whether the current request is the dashboard page.
	 *
	 * @return bool
	 */
	public static function is_page() {
		$page = (int) get_option( self::OPTION, 0 );
		return $page && is_page( $page );
	}

	/**
	 * Public URL of the dashboard, optionally opened on one section.
	 *
	 * @param string $section Section slug.
	 * @return string
	 */
	public static function url( $section = '' ) {
		$page = (int) get_option( self::OPTION, 0 );
		$base = $page ? get_permalink( $page ) : home_url( '/' . self::SLUG . '/' );
		if ( ! is_string( $base ) || '' === $base ) {
			$base = home_url( '/' );
		}
		return $section ? add_query_arg( 'section', sanitize_key( $section ), $base ) : $base;
	}

	/** Create the page once. A page this class already created is retagged if it still uses the learner shortcode. */
	public static function install_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$content = '[' . self::SHORTCODE . ']';
		$page_id = (int) get_option( self::OPTION, 0 );
		if ( $page_id ) {
			$page = get_post( $page_id );
			if ( $page instanceof \WP_Post && '[ohmylms_dashboard]' === $page->post_content ) {
				wp_update_post(
					array(
						'ID'           => $page_id,
						'post_content' => $content,
					)
				);
			}
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Dashboard', 'ohmylms' ),
				'post_name'    => self::SLUG,
				'post_content' => $content,
			),
			true
		);
		if ( ! is_wp_error( $page_id ) ) {
			update_option( self::OPTION, (int) $page_id, false );
		}
	}

	/**
	 * Minimal document. The theme template is not used.
	 *
	 * @param string $template Current template.
	 * @return string
	 */
	public static function template( $template ) {
		if ( ! self::is_page() ) {
			return $template;
		}
		$own = OHMYLMS_DIR . '/templates/dashboard/page.php';
		return is_file( $own ) ? $own : $template;
	}

	/**
	 * Mount point. Visitors who cannot use the dashboard are sent to sign in or told so.
	 *
	 * @return string
	 */
	public static function shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p><a href="' . esc_url( wp_login_url( self::url() ) ) . '">' . esc_html__( 'Sign in to open the dashboard.', 'ohmylms' ) . '</a></p>';
		}
		$scope = self::scope();
		if ( ! $scope['nav'] ) {
			return '<p>' . esc_html__( 'This dashboard is for site owners, school staff and content authors.', 'ohmylms' ) . '</p>';
		}
		return '<div id="ohmylms-front-dashboard" class="oml-dashboard"><p class="oml-dash-note">' . esc_html__( 'Loading…', 'ohmylms' ) . '</p></div>';
	}

	/** Enqueue the app only on this page, and drop theme styles. */
	public static function assets() {
		if ( ! self::is_page() || ! is_user_logged_in() ) {
			return;
		}
		self::dequeue_theme();
		$directory = defined( 'OHMYLMS_SOURCE_ASSETS' ) && OHMYLMS_SOURCE_ASSETS ? 'build/sdk' : 'assets/dist/admin';
		$asset     = OHMYLMS_DIR . '/' . $directory . '/admin-app-dashboard.asset.php';
		$script    = OHMYLMS_DIR . '/' . $directory . '/admin-app-dashboard.js';
		if ( ! is_file( $asset ) || ! is_file( $script ) ) {
			return;
		}
		$meta = require $asset;
		if ( ! is_array( $meta ) ) {
			return;
		}
		$deps = isset( $meta['dependencies'] ) && is_array( $meta['dependencies'] ) ? $meta['dependencies'] : array();
		$ver  = isset( $meta['version'] ) ? (string) $meta['version'] : OHMYLMS_VERSION;
		$url  = plugins_url( $directory . '/admin-app-dashboard.js', OHMYLMS_FILE );
		wp_enqueue_script( 'ohmylms-front-dashboard', $url, $deps, $ver, true );
		$css = OHMYLMS_DIR . '/' . $directory . '/admin-app-dashboard.css';
		if ( is_file( $css ) ) {
			wp_enqueue_style( 'ohmylms-front-dashboard', plugins_url( $directory . '/admin-app-dashboard.css', OHMYLMS_FILE ), array(), $ver );
			wp_add_inline_style( 'ohmylms-front-dashboard', '.oml-dashboard{' . \OhMyLMS\Design\Tokens::frontend_oml_vars() . '}' );
		}
		wp_add_inline_script( 'ohmylms-front-dashboard', 'window.ohmylmsDashboard=' . wp_json_encode( self::boot() ) . ';', 'before' );
	}

	/**
	 * Admin-bar link for people who may open the dashboard.
	 *
	 * @param \WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_user_logged_in() || ! self::scope()['nav'] ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'ohmylms-front-dashboard',
				'title' => __( 'OhMyLMS dashboard', 'ohmylms' ),
				'href'  => self::url(),
			)
		);
	}

	/**
	 * Who is looking, which schools and classes they may see, and which nav items to draw.
	 *
	 * @return array
	 */
	public static function scope() {
		$user    = wp_get_current_user();
		$owner   = user_can( $user, 'manage_options' );
		$editor  = user_can( $user, 'edit_posts' );
		$schema  = class_exists( Schema::class ) && Schema::VERSION === get_option( 'ohmylms_school_schema' );
		$schools = array();
		$classes = array();
		$admin   = $owner;
		if ( $schema && $user->ID ) {
			global $wpdb;
			if ( $owner ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,name,'owner' AS role FROM %i WHERE status='active' ORDER BY name LIMIT 100", Schema::table( 'schools' ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Dashboard scope, cached with the summary.
			} else {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT s.id,s.name,m.role FROM %i m JOIN %i s ON s.id=m.school_id AND s.status='active' WHERE m.user_id=%d AND m.status='active' AND m.role IN ('school_admin','teacher') ORDER BY s.name", Schema::table( 'school_memberships' ), Schema::table( 'schools' ), $user->ID ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The viewer's own memberships.
			}
			foreach ( (array) $rows as $row ) {
				$id = (int) $row['id'];
				if ( isset( $schools[ $id ] ) && 'school_admin' === $schools[ $id ]['role'] ) {
					continue;
				}
				$schools[ $id ] = array(
					'id'   => $id,
					'name' => (string) $row['name'],
					'role' => (string) $row['role'],
				);
				if ( 'school_admin' === $row['role'] ) {
					$admin = true;
				}
			}
			if ( ! $owner ) {
				$classes = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT c.id FROM %i c JOIN %i m ON m.class_id=c.id AND m.user_id=%d AND m.role='teacher' AND m.status='active' WHERE c.status='active'", Schema::table( 'classes' ), Schema::table( 'class_memberships' ), $user->ID ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The viewer's own classes.
			}
		}
		$teacher = $owner || $admin || $classes || ( $schema && Access::teacher() );
		$grade   = user_can( $user, 'ohmylms_view_gradebook' );
		$nav     = array();
		if ( $owner || $admin || $teacher || $editor ) {
			$nav[] = 'home';
		}
		if ( $owner ) {
			$nav[] = 'schools';
		}
		if ( $owner || $admin || $classes ) {
			$nav[] = 'classes';
		}
		if ( $owner || $admin ) {
			$nav[] = 'people';
		}
		if ( $owner || $editor || $teacher ) {
			$nav[] = 'content';
		}
		if ( $grade ) {
			$nav[] = 'gradebook';
		}
		if ( $owner || $admin ) {
			$nav[] = 'reports';
		}
		if ( $owner ) {
			$nav[] = 'commerce';
			$nav[] = 'settings';
		} elseif ( $admin ) {
			$nav[] = 'settings';
		}
		return array(
			'user_id'      => (int) $user->ID,
			'name'         => $user->display_name,
			'owner'        => $owner,
			'editor'       => $editor,
			'school_admin' => $admin,
			'teacher'      => (bool) $teacher,
			'schools'      => array_values( $schools ),
			'school_ids'   => array_map( 'intval', array_keys( $schools ) ),
			'class_ids'    => $owner || $admin ? null : array_values( array_unique( $classes ) ),
			'nav'          => $nav,
			'schema'       => $schema,
		);
	}

	/**
	 * Whether a section is in this viewer's nav.
	 *
	 * @param array  $scope   Result of scope().
	 * @param string $section Section slug.
	 * @return bool
	 */
	public static function allows( array $scope, $section ) {
		return in_array( $section, $scope['nav'], true );
	}

	/**
	 * Bootstrap passed to the React app.
	 *
	 * @return array
	 */
	private static function boot() {
		$scope = self::scope();
		return array(
			'rest'        => esc_url_raw( rest_url( 'ohmylms/v1/front-dashboard' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'adminUrl'    => $scope['owner'] ? admin_url() : '',
			'user'        => array(
				'id'   => $scope['user_id'],
				'name' => $scope['name'],
			),
			'owner'       => $scope['owner'],
			'canManage'   => (bool) $scope['school_admin'],
			'schools'     => $scope['schools'],
			'nav'         => $scope['nav'],
			'links'       => array(
				'gradebook' => admin_url( 'admin.php?page=ohmylms-content&tab=gradebook' ),
				'courses'   => admin_url( 'admin.php?page=ohmylms-content&tab=courses' ),
				'lessons'   => admin_url( 'admin.php?page=ohmylms-content&tab=lessons' ),
				'quizzes'   => admin_url( 'admin.php?page=ohmylms-content&tab=quizzes' ),
				'skills'    => admin_url( 'admin.php?page=ohmylms-content&tab=skills' ),
				'commerce'  => admin_url( 'admin.php?page=ohmylms-commerce' ),
				'reports'   => admin_url( 'admin.php?page=ohmylms-content&tab=quizzes&section=reports' ),
			),
		);
	}

	/** Remove theme and block-library styles so the dashboard is not waiting on them. */
	private static function dequeue_theme() {
		global $wp_styles;
		if ( ! $wp_styles instanceof \WP_Styles ) {
			return;
		}
		foreach ( (array) $wp_styles->queue as $handle ) {
			$keep = 0 === strpos( (string) $handle, 'ohmylms' ) || 0 === strpos( (string) $handle, 'wp-' );
			if ( ! $keep || in_array( $handle, array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ), true ) ) {
				if ( 0 !== strpos( (string) $handle, 'ohmylms' ) ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	}
}
