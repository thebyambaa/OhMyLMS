<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * RegisterSettings class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * RegisterSettings class.
 *
 * Handles the registration of settings.
 *
 * @since 1.0.0
 */
class RegisterSettings {

	/**
	 * The settings object.
	 *
	 * @var Settings
	 *
	 * @since 1.0.0
	 */
	protected $obj;

	/**
	 * Constructor.
	 *
	 * @param Settings $obj The settings object.
	 *
	 * @since 1.0.0
	 */
	public function __construct( $obj ) {
		if ( ! is_object( $obj ) ) {
			return;
		}

		$this->obj = $obj;

		add_filter( 'ohmylms_settings_groups', array( $this, 'register_settings_groups' ) );
		add_filter( 'ohmylms_settings-' . $this->obj->get_id(), array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings groups.
	 *
	 * @param array $groups The existing groups.
	 * @return array The modified groups.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_groups( $groups ) {
		$groups[] = array(
			'id'    => $this->obj->get_id(),
			'label' => $this->obj->get_label(),
		);
		return $groups;
	}

	/**
	 * Register settings.
	 *
	 * @return array The filtered settings.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		$settings          = $this->obj->get_settings();
		$filtered_settings = array();
		foreach ( $settings as $setting ) {
			if ( ! isset( $setting['id'] ) ) {
				continue;
			}
			$new_setting = $this->register_setting( $setting );
			if ( $new_setting ) {
				$filtered_settings[] = $new_setting;
			}
		}
		return $filtered_settings;
	}


	/**
	 * Register a single setting.
	 *
	 * @param array $setting The setting array.
	 * @return array|false The new setting array or false if invalid.
	 *
	 * @since 1.0.0
	 */
	protected function register_setting( $setting ) {
		if ( ! isset( $setting['id'] ) ) {
			return false;
		}
		$new_setting = array(
			'id'      => $setting['id'],
			'type'    => $setting['type'],
			'default' => $setting['default'] ?? '',
			'value'   => isset( $setting['value'] ) ? $setting['value'] : '',
		);
		if ( isset( $setting['meta_data'] ) ) {
			$new_setting['meta_data'] = isset( $setting['meta_data'] ) ? $setting['meta_data'] : '';
		}
		if ( isset( $setting['type'] ) && 'select' === $setting['type'] ) {
			$new_setting['options'] = isset( $setting['options'] ) ? $setting['options'] : '';
		}

		return $new_setting;
	}
}
