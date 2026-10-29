<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Read-only stand-in for orders, courses and students in email previews.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Emails;

/**
 * Answers the getters the email templates call (`get_name()`, `get_total()` and so on) from a
 * fixed array, so an email can be rendered with the real template without touching real records.
 */
class SampleRecord {

	/**
	 * Values keyed by getter name or property name.
	 *
	 * @var array<string,mixed>
	 */
	private $values;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $values Getter name (for example `get_name`) or property name => value.
	 */
	public function __construct( array $values ) {
		$this->values = $values;
	}

	/**
	 * Answer any getter; an unknown one returns an empty string, like a record without that value.
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Ignored.
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		return array_key_exists( $name, $this->values ) ? $this->values[ $name ] : '';
	}

	/**
	 * Read a property such as `display_name`.
	 *
	 * @param string $name Property name.
	 * @return mixed
	 */
	public function __get( $name ) {
		return array_key_exists( $name, $this->values ) ? $this->values[ $name ] : '';
	}

	/**
	 * Whether a property is set (templates test properties with `isset`).
	 *
	 * @param string $name Property name.
	 * @return bool
	 */
	public function __isset( $name ) {
		return array_key_exists( $name, $this->values );
	}
}
