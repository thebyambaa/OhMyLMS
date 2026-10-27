<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Integrations\Cohorts\Includes;

/**
 * Plugin hooks implementation.
 */
class Hooks {

	/**
	 * Initialize Hooks.
	 */
	public function __construct() {
		add_filter( 'ohmylms_integrations', array( $this, 'add_chort' ) );
		add_filter( 'ohmylms_show_sessions_menu', array( $this, 'register_session_menu' ) );
		add_filter( 'ohmylms_should_show_cohort', array( $this, 'is_cohort_enabled' ) );
	}


	/**
	 * Is cohort enabled for Hooks.
	 *
	 * @param mixed $should_show Should show.
	 */
	public function is_cohort_enabled( $should_show ) {
		$integrations = get_option( 'ohmylms_integrations' );
		return is_array( $integrations ) && isset( $integrations['cohort']['is_enable'] ) && $integrations['cohort']['is_enable'];
	}


	/**
	 * Add Cohorts integration to the list of available integrations.
	 *
	 * @param array $integrations List of existing integrations.
	 * @return array Updated list of integrations with Cohorts added.
	 * @since 1.0.0
	 */
	public function add_chort( $integrations ) {
		$integrations['cohort'] = array(
			'label'       => __( 'Cohorts', 'ohmylms' ),
			'icon'        => OHMYLMS_PRO_URL . '/includes/Integrations/Cohorts/Assets/Images/cohort-icon.svg',
			'description' => __( 'Enable collaborative and structured learning by grouping students into cohorts for a shared course experience.', 'ohmylms' ),
			'categories'  => array( 'course-enhancements' ),
			'hasSettings' => false,
			'class'       => 'OhMyLMS\Integrations\Cohorts',
			'is_valid'    => true,
		);
		return $integrations;
	}


	/**
	 * Check if zoom is enable
	 *
	 * @since 1.0.0
	 * @param mixed $should_show Should show.
	 */
	public function register_session_menu( $should_show ) {
		$integrations = get_option( 'ohmylms_integrations' );
		$zoom_enabled = isset( $integrations['zoom']['is_enable'] ) && $integrations['zoom']['is_enable'];
		$meet_enabled = isset( $integrations['google_meet']['is_enable'] ) && $integrations['google_meet']['is_enable'];
		if ( $zoom_enabled || $meet_enabled ) {
			return true;
		}
		return $should_show;
	}
}
