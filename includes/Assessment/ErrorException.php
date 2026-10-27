<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Carries a WP_Error out of a transaction closure so the transaction rolls back.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/** Carries a WP_Error out of a transaction closure so the transaction rolls back. */
final class ErrorException extends \RuntimeException {
	/**
	 * Error.
	 *
	 * @var mixed
	 */
	public $error;

	/**
	 * Initialize Error exception.
	 *
	 * @param \WP_Error $error Error.
	 */
	public function __construct( \WP_Error $error ) {
		parent::__construct( $error->get_error_message() );
		$this->error = $error;
	}

	/**
	 * Raise for Error exception.
	 *
	 * @param mixed $value Value to process.
	 * @throws self When the operation cannot be completed.
	 */
	public static function raise( $value ) {
		if ( is_wp_error( $value ) ) {
			throw new self( $value ); }
		return $value;
	}
}
