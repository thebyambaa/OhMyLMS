<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Abstracts;

defined( 'ABSPATH' ) || exit();

/**
 * Plugin cache engine implementation.
 */
abstract class CacheEngine {

	/**
	 * Cache group.
	 *
	 * @var mixed
	 */
	public $cache_group = 'ohmylms';

	/**
	 * Key.
	 *
	 * @var mixed
	 */
	public $key;

	/**
	 * Set for Cache engine.
	 */
	public function set() {}

	/**
	 * Get for Cache engine.
	 */
	public function get() {}

	/**
	 * Clear for Cache engine.
	 */
	public function clear() {}
}
