<?php
/**
 * A cmi5 package that cannot be imported.
 *
 * @package OhMyLMS\Cmi5
 */

namespace OhMyLMS\Cmi5;

defined( 'ABSPATH' ) || exit;

/**
 * Carries every problem found in a package, so an author can fix them in one pass instead of
 * re-uploading to meet the next error. The messages are plain text that can quote names from the
 * uploaded file, so whatever displays them must escape them.
 */
final class InvalidPackage extends \RuntimeException {

	/**
	 * Problems found, in the order they were met.
	 *
	 * @var string[]
	 */
	private $errors;

	/**
	 * Constructor.
	 *
	 * @param string[] $errors Problems found.
	 */
	public function __construct( array $errors ) {
		$this->errors = array_values( $errors );
		parent::__construct( implode( ' ', array_slice( $this->errors, 0, 3 ) ) );
	}

	/**
	 * Every problem found.
	 *
	 * @return string[]
	 */
	public function errors() {
		return $this->errors;
	}
}
