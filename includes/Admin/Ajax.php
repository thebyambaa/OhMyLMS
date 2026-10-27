<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Admin;

/**
 * Plugin ajax implementation.
 */
class Ajax {

	/**
	 * Initialize Ajax.
	 */
	public function __construct() {
		add_action( 'wp_ajax_install_sample_data', array( $this, 'install_sample_data' ) );
		add_action( 'wp_ajax_delete_sample_data', array( $this, 'delete_sample_data' ) );
		add_action( 'wp_ajax_ohmylms_delete_promotional_banner', array( $this, 'delete_promotional_banner' ) );
		add_action( 'wp_ajax_ohmylms_delete_transient_cache', array( $this, 'delete_transient_cache' ) );
		add_action( 'wp_ajax_ohmylms_track_page_view', array( $this, 'track_page_view' ) );
	}


	/**
	 * Install sample data for Ajax.
	 */
	public function install_sample_data() {
		check_ajax_referer( 'admin_tools', 'nonce' );

		// create sample course here.
	}

	/**
	 * Delete sample data for Ajax.
	 */
	public function delete_sample_data() {
		check_ajax_referer( 'admin_tools', 'nonce' );

		// create sample course here.
	}

	/**
	 * Delete promotional banner
	 *
	 * @return void
	 */
	public function delete_promotional_banner() {
		check_ajax_referer( 'ohmylms_promotional_banner_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access', 'ohmylms' ) ) );
		}

		$occasion = isset( $_POST['occasion'] ) ? sanitize_text_field( wp_unslash( $_POST['occasion'] ) ) : '';

		if ( empty( $occasion ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid occasion', 'ohmylms' ) ) );
		}

		update_option( '_is_show_' . $occasion . '_banner_ohmylms', 'no' );

		wp_send_json_success( array( 'message' => __( 'Banner dismissed successfully', 'ohmylms' ) ) );
	}

	/**
	 * Delete OhMyLMS transient cache
	 *
	 * @return void
	 */
	public function delete_transient_cache() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'ohmylms_delete_cache_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed', 'ohmylms' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access', 'ohmylms' ) ) );
		}

		global $wpdb;

		// Delete all OhMyLMS related transients.
		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ohmylms_' ) . '%',
				$wpdb->esc_like( '_site_transient_update_plugins' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ohmylms_' ) . '%',
				$wpdb->esc_like( '_site_transient_ohmylms_' ) . '%'
			)
		);

		// Also delete transients with 'ohmylms' and 'ohmylms' prefix.
		$count += $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_ohmylms_' ) . '%',
				$wpdb->esc_like( '_transient_ohmylms_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ohmylms_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ohmylms_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_ohmylms_' ) . '%',
				$wpdb->esc_like( '_site_transient_ohmylms_' ) . '%',
				$wpdb->esc_like( '_site_transient_update_plugins' ) . '%',
				$wpdb->esc_like( '_site_transient_ohmylms_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_ohmylms_' ) . '%'
			)
		);

		// For multisite, also delete from sitemeta.
		if ( is_multisite() ) {
			$count += $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s",
					$wpdb->esc_like( '_site_transient_ohmylms_' ) . '%',
					$wpdb->esc_like( '_site_transient_ohmylms_' ) . '%',
					$wpdb->esc_like( '_site_transient_ohmylms_' ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_ohmylms_' ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_ohmylms_' ) . '%',
					$wpdb->esc_like( '_site_transient_timeout_ohmylms_' ) . '%',
					$wpdb->esc_like( '_site_transient_update_plugins' ) . '%',
					$wpdb->esc_like( '_site_transient_update_ohmylms_' ) . '%',
					$wpdb->esc_like( '_site_transient_update_ohmylms_' ) . '%'
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of cache items deleted */
					_n( '%d cache item deleted successfully.', '%d cache items deleted successfully.', $count, 'ohmylms' ),
					$count
				),
			)
		);
	}

	/**
	 * Clear known cache keys through core so object caches and hooks are respected.
	 * SQL only discovers legacy keys: WordPress has no prefix enumeration API.
	 *
	 * @return int Number of transient values deleted (not database rows).
	 */
	public static function clear_transient_cache() {
		global $wpdb;
		$keys = array( 'ohmylms_count_comments', 'ohmylms_installing', 'mollie_methods_cache_test', 'mollie_methods_cache_live', 'mollie_methods_cache_frontend_test', 'mollie_methods_cache_frontend_live' );
		foreach ( array( '_transient_', '_transient_timeout_' ) as $prefix ) {
			foreach ( array( 'ohmylms_', 'mollie_methods_cache_' ) as $name ) {
				$rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $prefix . $name ) . '%' ) );
				foreach ( $rows as $row ) {
					$keys[] = substr( $row, strlen( $prefix ) );
				}
			}
		}
		$count = 0;
		foreach ( array_unique( $keys ) as $key ) {
			$count += (int) delete_transient( $key );
		}
		$site_keys = array( 'update_plugins' );
		foreach ( array( '_site_transient_', '_site_transient_timeout_' ) as $prefix ) {
			foreach ( array( 'ohmylms_', 'update_ohmylms_' ) as $name ) {
				$pattern = $wpdb->esc_like( $prefix . $name ) . '%';
				$rows    = is_multisite()
					? $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key LIKE %s", get_current_network_id(), $pattern ) )
					: $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) );
				foreach ( $rows as $row ) {
					$site_keys[] = substr( $row, strlen( $prefix ) );
				}
			}
		}
		foreach ( array_unique( $site_keys ) as $key ) {
			$count += (int) delete_site_transient( $key );
		}
		return $count;
	}

	/**
	 * Track page view via AJAX for React Router navigation.
	 *
	 * @since 1.1.10
	 * @return void
	 */
	public function track_page_view() {
		wp_send_json_success( array( 'message' => 'Tracking disabled' ) );
	}
}
