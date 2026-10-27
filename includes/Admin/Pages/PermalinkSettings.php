<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Admin\Pages;

use OhMyLMS\Abstracts\SettingsPage;

defined( 'ABSPATH' ) || exit;


/**
 * Plugin permalink settings implementation.
 */
class PermalinkSettings extends SettingsPage {
	/**
	 * Initialize Permalink settings.
	 */
	public function __construct() {
		$this->id    = 'permalink';
		$this->label = __( 'Permalink', 'ohmylms' );

		parent::__construct();
	}


	/**
	 * Get settings for default section for Permalink settings.
	 */
	public function get_settings_for_default_section() {
		$settings = array(
			array(
				'title' => __( 'Permalink setup', 'ohmylms' ),
				'type'  => 'title',
				'id'    => 'permalink_settings',
			),
			array(
				'title'   => esc_html__( 'Course', 'ohmylms' ),
				'type'    => 'permalink',
				'default' => '',
				'id'      => 'course_base',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'permalink_settings',
			),
		);
		return apply_filters( 'ohmylms_permalink_settings', $settings );
	}
}
