<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hook for Settings
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMSPro/includes
 */
namespace OhMyLMS\Hooks;

/**
 * Plugin settings hook implementation.
 */
class SettingsHook {

	/**
	 * Register hooks for Settings hook.
	 */
	public function register_hooks() {
		add_filter( 'ohmylms_default_pages', array( $this, 'add_membership_default_page' ) );
	}

	/**
	 * Add membership default page for Settings hook.
	 *
	 * @param mixed $pages Pages.
	 */
	public function add_membership_default_page( $pages ) {
		$pages['membership'] = array(
			'name'    => _x( 'ohmylms-all-membership', 'Page slug', 'ohmylms' ),
			'title'   => _x( 'All Membership', 'Page title', 'ohmylms' ),
			'content' => '<!-- wp:shortcode -->[ohmylms_membership_plan]<!-- /wp:shortcode -->',
		);

		return $pages;
	}
}
