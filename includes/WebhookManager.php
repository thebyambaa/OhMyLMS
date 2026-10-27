<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class WebhookManager.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS;

use OhMyLMS\Webhooks\WebhookSender;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebhookManager
 *
 * Handles webhook execution and event triggers
 *
 * @package OhMyLMS
 * @since 1.0.0
 */
class WebhookManager {

	/**
	 * WebhookSender instance
	 *
	 * @var WebhookSender
	 * @since 1.0.0
	 */
	private $webhook_sender;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->webhook_sender = new WebhookSender();
		$this->webhook_sender->init();
	}
}
