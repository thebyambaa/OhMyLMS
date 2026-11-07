<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Who may use the staff screens of the hub and how they are mounted.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Schools\Access;
use OhMyLMS\Schools\Schema;
use OhMyLMS\Student\StudioWorkspace;

defined( 'ABSPATH' ) || exit;

/**
 * Decides which staff sections a signed-in person may open and mounts the React screens inside the hub.
 */
final class Front {

	/** Register the admin-bar link. */
	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );
	}

	/**
	 * Load the staff app for the hub's Studio workspace. The hub draws the navigation; the app draws one section.
	 *
	 * @param string $section Section slug of the app.
	 */
	public static function enqueue( $section ) {
		if ( ! is_user_logged_in() ) {
			return;
		}
		\OhMyLMS\Design\Ui::enqueue();
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
		wp_enqueue_script( 'ohmylms-front-dashboard', plugins_url( $directory . '/admin-app-dashboard.js', OHMYLMS_FILE ), $deps, $ver, true );
		$css = OHMYLMS_DIR . '/' . $directory . '/admin-app-dashboard.css';
		if ( is_file( $css ) ) {
			wp_enqueue_style( 'ohmylms-front-dashboard', plugins_url( $directory . '/admin-app-dashboard.css', OHMYLMS_FILE ), array(), $ver );
			// The hub already prints the --oml-* tokens, light and dark, on its root; do not redeclare the light set here.
		}
		wp_add_inline_script( 'ohmylms-front-dashboard', 'window.ohmylmsDashboard=' . wp_json_encode( self::boot( $section ) ) . ';', 'before' );
	}

	/**
	 * Admin-bar link for people who have staff sections.
	 *
	 * @param \WP_Admin_Bar $bar Bar.
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_user_logged_in() || ! StudioWorkspace::available() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'ohmylms-front-dashboard',
				'title' => __( 'OhMyLMS studio', 'ohmylms' ),
				'href'  => StudioWorkspace::url(),
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
	 * @param string $section Section the hub asked for.
	 * @return array
	 */
	private static function boot( $section ) {
		$scope = self::scope();
		return array(
			'rest'      => esc_url_raw( rest_url( 'ohmylms/v1/front-dashboard' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'adminUrl'  => $scope['owner'] ? admin_url() : '',
			'user'      => array(
				'id'   => $scope['user_id'],
				'name' => $scope['name'],
			),
			'owner'     => $scope['owner'],
			'canManage' => (bool) $scope['school_admin'],
			'schools'   => $scope['schools'],
			'nav'       => $scope['nav'],
			'section'   => sanitize_key( $section ),
			'links'     => array(
				'gradebook' => admin_url( 'admin.php?page=ohmylms-content&tab=gradebook' ),
				'courses'   => admin_url( 'admin.php?page=ohmylms-content&tab=courses' ),
				'lessons'   => admin_url( 'admin.php?page=ohmylms-bank&tab=lessons' ),
				'quizzes'   => admin_url( 'admin.php?page=ohmylms-bank&tab=quizzes' ),
				'skills'    => admin_url( 'admin.php?page=ohmylms-curriculum&tab=tools' ),
				'commerce'  => admin_url( 'admin.php?page=ohmylms-commerce' ),
				'reports'   => admin_url( 'admin.php?page=ohmylms-bank&tab=quizzes&section=reports' ),
			),
		);
	}
}
