<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Abstract Settings class.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Abstracts;

/**
 * Abstract Settings class.
 *
 * Provides a base for settings classes.
 *
 * @since 1.0.0
 */
abstract class Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id;

	/**
	 * The settings label.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $label;

	/**
	 * Get the settings ID.
	 *
	 * @return string The settings ID.
	 *
	 * @since 1.0.0
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the settings label.
	 *
	 * @return string The settings label.
	 * @since 1.0.0
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Get the settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	abstract public function get_settings();
}
