<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Signal that an outbox row has nothing to report.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by the materializer when a queued event no longer produces a statement, for example
 * because the learner or the content was deleted. The row is marked skipped, not retried.
 */
final class SkipStatement extends \RuntimeException {
}
