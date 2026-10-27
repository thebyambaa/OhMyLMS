<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * General settings class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * General settings class.
 *
 * Handles general settings for the OhMyLMS.
 *
 * @since 1.0.0
 */
class EmailSettings extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'email';

	/**
	 * Constructor.
	 *
	 * Initializes the general settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'email';
		$this->label = __( 'Email', 'ohmylms' );
	}

	/**
	 * Get the general settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {
		$settings = array(
			array(
				'id'      => 'ohmylms_email_branding_image',
				'type'    => 'image',
				'default' => '',
			),
			array(
				'id'      => 'ohmylms_email_base_color',
				'type'    => 'color',
				'default' => '#6E42D3',
			),
			array(
				'id'      => 'ohmylms_email_background_color',
				'type'    => 'color',
				'default' => '#F4F5F7',
			),
			array(
				'id'      => 'ohmylms_email_body_background_color',
				'type'    => 'color',
				'default' => '#FFFFFF',
			),
			array(
				'id'      => 'ohmylms_email_body_text_color',
				'type'    => 'color',
				'default' => '#1F2328',
			),
			array(
				'id'      => 'ohmylms_email_button_possition',
				'type'    => 'text',
				'default' => 'left',
			),
			array(
				'id'      => 'ohmylms_email_sender_email_address',
				'type'    => 'text',
				'default' => '',
			),
			array(
				'id'      => 'ohmylms_email_sender_name',
				'type'    => 'text',
				'default' => '',
			),
			array(
				'id'      => 'ohmylms_email_footer_text',
				'type'    => 'text',
				'default' => '',
			),
			array(
				'id'      => 'ohmylms_notification_color',
				'type'    => 'color',
				'default' => '#6E42D3',
			),
		);

		return $settings;
	}
}
