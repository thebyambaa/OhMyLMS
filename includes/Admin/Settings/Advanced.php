<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Advanced settings class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * Advanced settings class.
 *
 * Handles advanced settings for the OhMyLMS.
 *
 * @since 1.0.0
 */
class Advanced extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'advanced';

	/**
	 * Constructor.
	 *
	 * Initializes the advanced settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'advanced';
		$this->label = __( 'Advanced', 'ohmylms' );
	}

	/**
	 * Get the advanced settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {
		$settings = array(
			array(
				'id'      => 'ohmylms_use_custom_video_player',
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Use Custom Video Player', 'ohmylms' ),
				'desc'    => __( 'Enable custom video player with enhanced controls and branding removal for YouTube, Vimeo, and self-hosted videos. When disabled, the standard platform embed will be used instead.', 'ohmylms' ),
			),
		);

		return $settings;
	}
}
