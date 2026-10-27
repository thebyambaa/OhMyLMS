<?php
/**
 * Minimal host error stub for standalone schema tests only.
 *
 * @package OhMyLMS
 */

/** Host class name is required by the production schema's error contract. */
class WP_Error {
	/**
	 * Error code.
	 *
	 * @var string
	 */
	public $code;
	/**
	 * Error message.
	 *
	 * @var string
	 */
	public $message;
	/**
	 * Error data.
	 *
	 * @var mixed
	 */
	public $data;

	/**
	 * Construct an error stub.
	 *
	 * @param string $code Error code.
	 * @param string $unused_message Unused message.
	 * @param mixed  $unused_data Unused data.
	 */
	public function __construct( $code = '', $unused_message = '', $unused_data = null ) {
		$this->code    = $code;
		$this->message = $unused_message;
		$this->data    = $unused_data;
	}
}
