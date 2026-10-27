<?php
/**
 * Minimal WordPress error stand-in for isolated assessment checks.
 *
 * @package OhMyLMS\Tests
 */

/** Stand-in preserving the public WordPress error class name. */
class WP_Error {
	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $message;
	/**
	 * Error identifier.
	 *
	 * @var string
	 */
	public $code;

	/**
	 * Store the error without loading WordPress.
	 *
	 * @param string $code Error identifier.
	 * @param string $message Error message.
	 */
	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}
}
