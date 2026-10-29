<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Shared frontend styles, loaded once and only for the required page groups.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Design;

defined( 'ABSPATH' ) || exit;

/** Page-specific dependencies for the public ohmylms-frontend stylesheet handle. */
final class FrontendStyles {
	/**
	 * Read the authored build metadata.
	 *
	 * @return array Module metadata keyed by group.
	 */
	public static function modules() {
		$path = OHMYLMS_DIR . '/assets/css/frontend/modules.php';
		return is_file( $path ) ? require $path : array();
	}

	/**
	 * Resolve the groups needed by the current WordPress request.
	 *
	 * @return string[] Group names.
	 */
	public static function groups() {
		$groups = array( 'core' );
		$checks = array(
			'catalog' => array( 'is_ohmylms_archive', 'ohmylms_is_courses_page', 'ohmylms_is_course_list_shortcode', 'ohmylms_is_course_taxonomy', 'ohmylms_is_course_category' ),
			'course' => array( 'ohmylms_is_single_course_page' ),
			'lesson' => array( 'ohmylms_is_content_page' ),
			'checkout' => array( 'is_ohmylms_checkout', 'is_ohmylms_buy_now' ),
			'thankyou' => array( 'is_ohmylms_order_received_page' ),
			'account' => array( 'is_ohmylms_dashboard', 'is_ohmylms_profile', 'is_ohmylms_profile_shortcode', 'is_ohmylms_my_courses_shortcode' ),
			'membership' => array( 'ohmylms_is_membership_page', 'ohmylms_is_membership_plan_shortcode' ),
		);
		foreach ( $checks as $group => $functions ) {
			foreach ( $functions as $function ) {
				if ( function_exists( $function ) && $function() ) {
					$groups[] = $group;
					break;
				}
			}
		}
		/** Integrations can request their layout groups without loading the full compatibility bundle. */
		$groups = apply_filters( 'ohmylms_frontend_style_groups', $groups );
		if ( is_rtl() ) {
			$groups[] = 'rtl';
		}
		return array_values( array_unique( $groups ) );
	}

	/**
	 * Register the shared components and requested modules, retaining the public stylesheet handle.
	 *
	 * @param string[]|null $groups Explicit groups for editors/integrations, or current page groups.
	 * @return void
	 */
	public static function register( $groups = null ) {
		Ui::register();
		$modules = self::modules();
		$deps = array( Ui::STYLE_HANDLE );
		if ( wp_style_is( 'ohmylms-quiz-a11y', 'registered' ) ) {
			$deps[] = 'ohmylms-quiz-a11y';
		}
		foreach ( $groups ?? self::groups() as $name ) {
			if ( ! isset( $modules[ $name ] ) ) {
				continue;
			}
			$module = $modules[ $name ];
			$handle = 'ohmylms-frontend-' . $name;
			wp_register_style( $handle, plugins_url( $module['path'], OHMYLMS_FILE ), array( Ui::STYLE_HANDLE ), $module['version'] );
			$deps[] = $handle;
		}
		wp_deregister_style( 'ohmylms-frontend' );
		wp_register_style( 'ohmylms-frontend', false, $deps, OHMYLMS_VERSION );
	}
}
