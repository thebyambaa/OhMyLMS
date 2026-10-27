<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Abstract class HookHandler.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Abstracts;

/**
 * Abstract class HookHandler
 *
 * This class provides a template for registering hooks in the OhMyLMS plugin.
 */
abstract class HookHandler {

	/**
	 * Register hooks.
	 *
	 * This method should be implemented by subclasses to register their specific hooks.
	 */
	abstract public function register_hooks();
}
