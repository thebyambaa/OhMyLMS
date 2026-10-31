<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Admin;

use OhMyLMS\Admin\Pages\AdminSettings;

/**
 * Plugin menu implementation.
 */
class Menu {

	/**
	 * Admin constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'init_menu' ) );
		add_action( 'admin_menu', array( $this, 'menu_highlight' ) );
		add_action( 'admin_menu', array( $this, 'map_legacy_submenu' ), 9999 );
		add_action( 'admin_head', array( AdminNavigation::class, 'hide_dashboard_entry' ), 9999 );
		add_action( 'wp_loaded', array( $this, 'save_settings' ) );
		add_action( 'admin_footer', array( $this, 'open_link_with_new_page' ) );
		add_action( 'admin_bar_menu', array( $this, 'lms_admin_menu_option' ), 100 );
	}


	/**
	 * Register the OhMyLMS menu: the top-level entry and one submenu per page the user can open (Dashboard,
	 * Content Hub, Account Hub, Commerce, Engagement, Settings; see AdminNavigation), with the
	 * pending-orders badge on the top-level entry and Commerce.
	 *
	 * @since 1.0.0
	 */
	public function init_menu() {
		$pending_count = (int) apply_filters( 'ohmylms_pending_orders_count', $this->count_unchecked_orders() );
		$badge_html    = $pending_count > 0 ? ' <span class="update-plugins count-' . $pending_count . '"><span class="plugin-count">' . number_format_i18n( $pending_count ) . '</span></span>' : '';
		AdminNavigation::register_menu( $this->get_menu_icon(), $badge_html );

		/**
		 * Fires after the OhMyLMS pages are registered; add-ons add their own submenu entries here.
		 */
		do_action( 'ohmylms_after_settings_menu_item' );
	}

	/**
	 * Count orders nobody has opened yet.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	private function count_unchecked_orders() {
		if ( ! current_user_can( AdminNavigation::CAPABILITY ) ) {
			return 0;
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'ohmylms-order',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One count per admin request, as before.
					array(
						'key'     => '_is_open',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Point every OhMyLMS submenu entry that still opens a hash route of the legacy page
	 * (`admin.php?page=ohmylms#/…`) or of the retired app page (`admin.php?page=ohmylms-app#/…`), as
	 * add-ons added them, at its new address, and drop the legacy ones no screen replaces. Entries for
	 * other pages (including the Dashboard itself) and external links are kept.
	 */
	public function map_legacy_submenu() {
		global $submenu;
		if ( empty( $submenu[ OHMYLMS_SLUG ] ) ) {
			return;
		}
		$pattern = '#(?:^|/)admin\.php\?page=(' . preg_quote( OHMYLMS_SLUG, '#' ) . '|' . preg_quote( AppPage::SLUG, '#' ) . ')(?=[&\#]|$)[^\#]*(?:\#(.*))?$#';
		foreach ( $submenu[ OHMYLMS_SLUG ] as $index => $item ) {
			$url = (string) ( $item[2] ?? '' );
			if ( ! preg_match( $pattern, $url, $found ) ) {
				continue;
			}
			$route = '' === ( $found[2] ?? '' ) ? '/' : '/' . ltrim( $found[2], '/' );
			if ( AppPage::SLUG === $found[1] ) {
				$submenu[ OHMYLMS_SLUG ][ $index ][2] = LegacyRoutes::app_url( $route ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				continue;
			}
			$mapped = LegacyRoutes::resolve( $route );
			if ( null === $mapped ) {
				unset( $submenu[ OHMYLMS_SLUG ][ $index ] );
			} else {
				$submenu[ OHMYLMS_SLUG ][ $index ][2] = LegacyRoutes::app_url( $mapped ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}
	}

	/**
	 * Open link with new page for Menu.
	 */
	public function open_link_with_new_page() {
		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('.ohmylms-open-new-tab').parent().attr('target','_blank');
			});
		</script>
		<?php
	}


	/**
	 * Menu highlight for Menu.
	 */
	public function menu_highlight() {
		global $parent_file, $submenu_file, $post_type, $current_screen;

		switch ( $post_type ) {
			case 'ohmylms-course':
			case 'ohmylms-order':
			case 'ohmylms-coupon':
				$parent_file = 'ohmylms'; // WPCS: override ok.
				break;
		}
	}


	/**
	 * Render tools page
	 *
	 * @since 1.0.0
	 */
	public function render_tools_page(): void {
		require_once OHMYLMS_INCLUDES . '/Admin/views/tools.php';
	}


	/**
	 * Render settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page(): void {
		global $ohmylms_current_tab, $ohmylms_current_section;
		$ohmylms_current_tab = empty( $_GET['tab'] ) ? 'general' : sanitize_title( wp_unslash( $_GET['tab'] ) ); // WPCS: input var okay, CSRF ok.
		$tabs                = apply_filters( 'ohmylms_settings_tabs_array', array() );
		require_once OHMYLMS_INCLUDES . '/Admin/views/settings.php';
	}


	/**
	 * Save settings data
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function save_settings(): void {
		global $ohmylms_current_tab, $ohmylms_current_section, $ohmylms_current_page;
		if ( ohmylms_is_admin_page() ) {
			$ohmylms_current_section = empty( $_GET['section'] ) ? '' : sanitize_title( wp_unslash( $_GET['section'] ) ); // WPCS: input var okay, CSRF ok.
			$ohmylms_current_tab     = empty( $_GET['tab'] ) ? 'general' : sanitize_title( wp_unslash( $_GET['tab'] ) ); // WPCS: input var okay, CSRF ok.
			$ohmylms_current_page    = empty( $_GET['page'] ) ? 'ohmylms-settings' : sanitize_title( wp_unslash( $_GET['page'] ) ); // WPCS: input var okay, CSRF ok.

			if ( ! empty( $_POST['save'] ) ) {
				AdminSettings::save();
			}
		}
	}

	/**
	 * Add folded menu class
	 *
	 * @param string $classes Classes.
	 *
	 * @return string
	 */
	public function add_folded_menu_class( $classes ) {
		return $classes . ' folded';
	}

	/**
	 * Add custom menu option to the admin bar.
	 *
	 * @param mixed $wp_admin_bar Wp admin bar.
	 */
	public function lms_admin_menu_option( $wp_admin_bar ) {
		$archive_page_id  = get_option( 'ohmylms_course_page_id', 0 );
		$archive_page_url = home_url();
		if ( $archive_page_id ) {
			$archive_page_url = get_permalink( $archive_page_id );
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'visit-ohmylms-courses',
				'title'  => 'Visit Courses',
				'href'   => $archive_page_url, // Your custom page URL.
				'parent' => 'site-name', // Add under Visit Site.
				'meta'   => array(
					'title'  => __( 'Visit Courses', 'ohmylms' ), // Tooltip.
					'target' => '_blank', // Open in new tab.
					'class'  => 'visit-ohmylms-courses-class',
				),
			)
		);

		if ( ! ohmylms_is_pro() ) {
			return;
		}
		// For memberships.
		$membership_page_id = get_option( 'ohmylms_membership_page_id', 0 );
		if ( ! $membership_page_id ) {
			return;
		}
		$membership_page_url = home_url();
		if ( $membership_page_id ) {
			$membership_page_url = get_permalink( $membership_page_id );
		}
		$wp_admin_bar->add_node(
			array(
				'id'     => 'visit-ohmylms-memberships',
				'title'  => 'Visit Memberships',
				'href'   => $membership_page_url, // Your custom page URL.
				'parent' => 'site-name', // Add under Visit Site.
				'meta'   => array(
					'title'  => __( 'Visit Memberships', 'ohmylms' ), // Tooltip.
					'target' => '_blank', // Open in new tab.
					'class'  => 'visit-ohmylms-memberships-class',
				),
			)
		);
	}

	/**
	 * Gets the SVG icon for menu
	 *
	 * @desc Gets the SVG icon for menu
	 * @return string
	 * @since 1.0.0
	 */
	private function get_menu_icon() {
		return 'data:image/svg+xml;base64,' . base64_encode(        //phpcs:ignore
			'<?xml version="1.0" encoding="utf-8"?>
			<!-- Generator: Adobe Illustrator 27.1.1, SVG Export Plug-In . SVG Version: 6.00 Build 0)  -->
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="19" viewBox="0 0 20 19" fill="none">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M2.60227 12.4573C3.83527 13.8718 5.84269 14.733 8.06157 14.733C10.2204 14.733 12.1786 13.9263 13.4052 12.5868L15.9512 14.9181C13.9694 17.0823 11.0279 18.1851 8.06157 18.1851C5.00001 18.1851 1.98405 17.0017 0 14.7256L2.60227 12.4573Z" fill="#A8AAAD"/>
				<path fill-rule="evenodd" clip-rule="evenodd" d="M5.62282 11.9229C6.66317 12.309 7.77643 12.4158 8.9626 12.2432C10.1117 12.076 11.1105 11.6665 11.985 11.0169C12.1178 10.9182 12.2472 10.8171 12.3726 10.7132L12.3765 10.7149L12.4322 10.6634C12.6072 10.5157 12.774 10.3623 12.9311 10.2022L20 3.66677L14.4622 3.80363L10.8692 7.09554L10.8587 7.09031C10.8558 7.09806 10.8528 7.10579 10.8497 7.11349L9.94072 7.94627C9.8413 8.01486 9.74868 8.08035 9.67184 8.14275C9.3187 8.42126 8.9086 8.59449 8.44155 8.66245C7.72243 8.76709 7.09112 8.6167 6.54761 8.21128C6.00411 7.80585 5.68003 7.24358 5.57539 6.52447C5.47075 5.80535 5.62114 5.17404 6.02657 4.63054C6.43091 4.07961 6.99264 3.75183 7.71176 3.64719C8.17881 3.57923 8.62181 3.63211 9.04075 3.80583C9.45862 3.97214 9.79939 4.23295 10.0631 4.58824L12.8482 2.01958C12.1776 1.20871 11.4744 0.760382 10.451 0.386931C9.42766 0.0134798 8.34142 -0.0896406 7.19232 0.0775697C6.00616 0.250174 4.97003 0.673484 4.08394 1.3475C3.20419 2.01302 2.56114 2.84851 2.1548 3.85395C1.74846 4.85939 1.62835 5.93296 1.79448 7.07464C1.96062 8.21633 2.3817 9.21114 3.05774 10.0591C3.73377 10.907 4.5888 11.5283 5.62282 11.9229Z" fill="#A8AAAD"/>
			</svg>'
		);
	}
}
