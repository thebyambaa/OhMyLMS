<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hooks of issued certificates.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Certificates;

defined( 'ABSPATH' ) || exit;

/** Issues certificates on course completion and answers the download and verification addresses. */
final class Bootstrap {
	/**
	 * Register the hooks.
	 */
	public static function init() {
		// Before the completion e-mail (priority 10), so it can link to the certificate.
		add_action( 'ohmylms_course_completed', array( Issuer::class, 'on_course_completed' ), 5, 2 );
		add_action( 'template_redirect', array( Download::class, 'route' ), 1 );
	}
}
