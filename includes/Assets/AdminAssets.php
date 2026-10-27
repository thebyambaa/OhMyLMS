<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Assets;

use OhMyLMS\Abstracts\Assets;
use OhMyLMS\Membership\MembershipHelper;

use function OhMyLMS\Commerce\ecommerce;

/**
 * Plugin admin assets implementation.
 */
class AdminAssets extends Assets {

	/**
	 * Init for Admin assets.
	 */
	public function init() {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_print_scripts', array( $this, 'localize_printed_scripts' ) );
	}

	/**
	 * Get scripts for Admin assets.
	 */
	public function get_scripts() {
		$suffix = '';

		$scripts = array(
			'ohmylms-structure-client' => array(
				'src'       => self::get_asset_url( 'assets/dist/admin/course-structure.js' ),
				'version'   => OHMYLMS_VERSION . '.' . filemtime( OHMYLMS_DIR . '/assets/dist/admin/course-structure.js' ),
				'deps'      => array( 'wp-api-fetch' ),
				'in_footer' => true,
				'screens'   => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-vendor'           => array(
				'src'       => self::get_asset_url( 'assets/dist/vendors/vendors' . $suffix . '.js' ),
				'version'   => OHMYLMS_VERSION,
				'deps'      => array( 'react', 'react-dom' ),
				'in_footer' => true,
				'screens'   => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-chartjs'          => array(
				'src'       => self::get_asset_url( 'assets/dist/vendors/chartjs' . $suffix . '.js' ),
				'version'   => OHMYLMS_VERSION,
				'deps'      => array( 'ohmylms-vendor' ),
				'in_footer' => true,
				'screens'   => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-editor'           => array(
				'src'       => self::get_asset_url( 'assets/dist/vendors/editor' . $suffix . '.js' ),
				'version'   => OHMYLMS_VERSION,
				'deps'      => array( 'ohmylms-vendor' ),
				'in_footer' => true,
				'screens'   => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-emotion'          => array(
				'src'       => self::get_asset_url( 'assets/dist/vendors/emotion' . $suffix . '.js' ),
				'version'   => OHMYLMS_VERSION,
				'deps'      => array( 'ohmylms-vendor' ),
				'in_footer' => true,
				'screens'   => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms'                  => array(
				'src'       => self::get_asset_url( 'assets/dist/admin/ohmylms' . $suffix . '.js' ),
				'deps'      => array( 'wp-element', 'wp-i18n', 'wp-components', 'wp-api-fetch', 'ohmylms-structure-client', 'ohmylms-vendor', 'ohmylms-chartjs', 'ohmylms-editor', 'ohmylms-emotion' ),
				'version'   => OHMYLMS_VERSION . '.' . filemtime( OHMYLMS_DIR . '/assets/dist/admin/ohmylms' . $suffix . '.js' ),
				'in_footer' => true,
				'screens'   => array( 'toplevel_page_ohmylms' ),
			),
		);

		return is_array( $scripts ) ? array_filter( $scripts ) : array();
	}

	/**
	 * Get styles for Admin assets.
	 */
	public function get_styles() {
		$suffix = '';
		$styles = array(
			'ohmylms-skill-studio'        => array(
				'src'     => self::get_asset_url( 'assets/css/skill-studio.css' ),
				'deps'    => array( 'wp-components' ),
				'version' => OHMYLMS_VERSION . '.' . filemtime( OHMYLMS_DIR . '/assets/css/skill-studio.css' ),
				'media'   => 'all',
				'has_rtl' => false,
				'screens' => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-syllabus-comparison' => array(
				'src'     => self::get_asset_url( 'assets/css/syllabus-comparison.css' ),
				'deps'    => '',
				'version' => OHMYLMS_VERSION,
				'media'   => 'all',
				'has_rtl' => false,
				'screens' => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-main'                => array(
				'src'     => self::get_asset_url( 'assets/dist/css/admin/ohmylms.css' ),
				'deps'    => '',
				'version' => OHMYLMS_VERSION,
				'media'   => 'all',
				'has_rtl' => false,
				'screens' => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-rtl'                 => array(
				'src'     => self::get_asset_url( 'assets/dist/css/admin/ohmylms-rtl.css' ),
				'deps'    => '',
				'version' => OHMYLMS_VERSION,
				'media'   => 'all',
				'has_rtl' => false,
				'screens' => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-tailwind'            => array(
				'src'     => self::get_asset_url( 'assets/css/tailwind.css' ),
				'deps'    => '',
				'version' => OHMYLMS_VERSION,
				'media'   => 'all',
				'has_rtl' => false,
				'screens' => array( 'toplevel_page_ohmylms' ),
			),
			'ohmylms-global'              => array(
				'src'     => self::get_asset_url( 'assets/css/global.css' ),
				'deps'    => '',
				'version' => OHMYLMS_VERSION,
				'media'   => 'all',
				'has_rtl' => false,
				'screens' => array( 'all' ),
			),
		);
		return is_array( $styles ) ? array_filter( $styles ) : array();
	}

	/**
	 * Get script data for Admin assets.
	 *
	 * @param mixed $handle Handle.
	 */
	public function get_script_data( $handle ) {
		$admin_email = get_option( 'admin_email' );
		$admin_user  = get_user_by( 'email', $admin_email );
		$admin_name  = $admin_user ? $admin_user->display_name : '';

		switch ( $handle ) {
			case 'ohmylms':
				$localized_data = array(
					'ajax_url'                     => admin_url( 'admin-ajax.php' ),
					'api_url'                      => get_rest_url(),
					'nonce'                        => wp_create_nonce( 'wp_rest' ),
					'setup_wizard_nonce'           => wp_create_nonce( 'ohmylms_setup_wizard' ),
					'delete_cache_nonce'           => wp_create_nonce( 'ohmylms_delete_cache_nonce' ),
					'should_track'                 => false,
					'track_page_view_nonce'        => wp_create_nonce( 'ohmylms_track_page_view' ),
					'currency'                     => get_ohmylms_currency_symbol( get_ohmylms_currency() ),
					'gtm_offset'                   => get_option( 'gmt_offset' ),
					'timezone_string'              => get_option( 'timezone_string' ),
					'currency_symbol'              => get_ohmylms_currency_symbol(),
					'currency_position'            => get_option( 'ohmylms_currency_pos', 'left' ),
					'decimal_separator'            => ohmylms_get_price_decimal_separator(),
					'currency_format_trim_zeros'   => ohmylms_get_price_thousand_separator(),
					'currency_format_num_decimals' => ohmylms_get_price_decimals(),
					'price_format'                 => ohmylms_get_price_format(),
					'ohmylms_home_page'            => admin_url( 'admin.php?page=ohmylms' ),
					'plugin_assets'                => plugin_dir_url( __FILE__ ),
					'admin_name'                   => $admin_name,
					'admin_email'                  => $admin_email,
					'is_tutor_lms_active'          => defined( 'TUTOR_VERSION' ),
					'is_learndash_lms_active'      => defined( 'LEARNDASH_VERSION' ),
					'is_learnpress_active'         => defined( 'LEARNPRESS_VERSION' ),
					'is_masterstudy_active'        => defined( 'STM_LMS_VERSION' ) || defined( 'STM_LMS_FILE' ) || defined( 'MASTERSTUDY_LMS_VERSION' ) || class_exists( 'STM_LMS' ) || post_type_exists( 'stm-courses' ),
					'is_wpfusion_active'           => defined( 'WP_FUSION_VERSION' ),
					'is_mailmint_active'           => defined( 'MRM_VERSION' ),
					'is_uiexpress_active'          => defined( 'uixpress_plugin_version' ),
					'timezone'                     => $this->default_timezone(),
					'date_format'                  => get_option( 'date_format' ),
					'time_format'                  => get_option( 'time_format' ),
					'payment_gateways'             => apply_filters(
						'ohmylms_payment_gateways',
						ecommerce()->gateways()->get_payment_gateway_settings()
					),
					'integrations'                 => apply_filters( 'ohmylms_integrations', array() ),
					'is_cohort_enabled'            => apply_filters( 'ohmylms_should_show_cohort', false ),
					'is_gamification_enabled'      => apply_filters( 'ohmylms_show_gamification_menu', false ),
					'is_webhook_enabled'           => apply_filters( 'ohmylms_should_enable_webhooks', false ),
					'is_funnel_enabled'            => apply_filters( 'ohmylms_should_show_funnel', false ),
					'is_communities_enabled'       => apply_filters( 'ohmylms_is_communities_enabled', false ),
					'is_community_addon_active'    => defined( 'OHMYLMS_COMMUNITY_VERSION' ),
					'community_addon_data'         => $this->get_community_addon_data(),
					'has_pro_plugin'               => ohmylms_is_pro(),
					'setup_wizard_certificate_id'  => get_option( 'ohmylms_setup_wizard_certificate_id', 0 ),
				);
				break;
			case 'ohmylms-settings':
				$localized_data = array(
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'search_pages_nonce' => wp_create_nonce( 'search-pages' ),
				);
				break;
			case 'ohmylms-membership':
				$localized_data = array(
					'ajax_url'             => admin_url( 'admin-ajax.php' ),
					'nonce'                => wp_create_nonce( 'ohmylms-membership' ),
					'courses'              => MembershipHelper::get_courses_for_membership_plans(),
					'subscription_options' => MembershipHelper::subscription_options(),
				);
				break;
			case 'ohmylms-course':
				$localized_data = array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ohmylms-course' ),
				);
				break;
			default:
				$localized_data = false;
		}

		return apply_filters( 'ohmylms_get_admin_script_data', $localized_data, $handle );
	}


	/**
	 * Get the default timezone.
	 *
	 * @return array The default timezone.
	 * @since 1.0.0
	 */
	public function default_timezone() {
		$date          = new \DateTime( 'now', wp_timezone() );
		$offsetSeconds = $date->getOffset();

		$timezone = sprintf(
			'%+03d:%02d',
			floor( $offsetSeconds / 3600 ),
			abs( ( $offsetSeconds % 3600 ) / 60 )
		);

		$timezone_string = get_option( 'timezone_string' );
		$timezone_type   = $timezone_string ? 1 : 2; // 1 = named, 2 = offset (you can customize the meaning)

		$result = array(
			'timezone_string' => $timezone,
			'timezone_type'   => $timezone_type,
		);
		return $result;
	}

	/**
	 * Load scripts for Admin assets.
	 */
	public function load_scripts() {
		$this->register_scripts();
		$this->register_styles();

		$screen    = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		wp_tinymce_inline_scripts();
		wp_enqueue_editor();
		wp_enqueue_media();
		wp_enqueue_script( 'wp-element' );
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script( 'wp-data' );
		wp_enqueue_style( 'wp-interface' );
		wp_enqueue_style( 'wp-block-editor' );
		foreach ( $this->get_scripts() as $handle => $script ) {
			if ( ! $this->should_enqueue( $script, $screen_id ) ) {
				continue;
			}
			wp_enqueue_script( $handle );
			$loaded_app = true;
		}
		// The question editor previews interactive questions with the learners' own widgets.
		if ( ! empty( $loaded_app ) ) {
			\OhMyLMS\Assessment\Interactive::enqueue();
		}
		wp_set_script_translations(
			'ohmylms',
			'ohmylms',
			plugin_dir_path( OHMYLMS_FILE ) . 'languages'
		);

		foreach ( $this->get_styles() as $handle => $style ) {
			if ( ! $this->should_enqueue( $style, $screen_id ) ) {
				continue;
			}

			// Skip RTL styles if not in RTL mode.
			if ( 'ohmylms-rtl' === $handle && ! is_rtl() ) {
				continue;
			}

			wp_enqueue_style( $handle );
		}
	}

	/**
	 * Get community addon data for Admin assets.
	 */
	public function get_community_addon_data() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins   = get_plugins();
		$slug      = '';
		$installed = false;

		foreach ( $plugins as $path => $plugin ) {
			if ( 'OhMyLMS - Community Addon' === $plugin['Name'] || 'ohmylms-community' === $plugin['TextDomain'] ) {
				$slug      = $path;
				$installed = true;
				break;
			}
		}

		return array(
			'is_installed' => $installed,
			'slug'         => $slug,
		);
	}
}


( new AdminAssets() )->init();
