<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class Session.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Data;

use OhMyLMS\CPTData\PostTypeData;
use OhMyLMS\DataStores\DataStores;

defined( 'ABSPATH' ) || exit;

/**
 * Class Session
 *
 * Represents a Session in the OhMyLMS system.
 */
class Session extends PostTypeData {

	/**
	 * Name of the store
	 *
	 * @var string
	 */
	protected string $data_store_name = 'session';

	/**
	 * Object type
	 *
	 * @var string
	 */
	public string $object_type = 'session';

	/**
	 * Session data array
	 *
	 * @var array
	 */
	protected array $data = array(
		'name'          => '',
		'description'   => '',
		'status'        => '',
		'date_created'  => null,
		'date_modified' => null,
		'type'          => '',
	);

	/**
	 * Session constructor.
	 *
	 * @param string $session Session.
	 * @throws \Exception When the operation cannot be completed.*/
	public function __construct( $session = '' ) {
		if ( is_numeric( $session ) && $session > 0 ) {
			$this->set_id( $session );
		} elseif ( $session instanceof self ) {
			$this->set_id( absint( $session->get_id() ) );
		} elseif ( ! empty( $session->ID ) ) {
			$this->set_id( absint( $session->ID ) );
		}

		// load the data store.
		$this->data_store = DataStores::load( $this->data_store_name );

		if ( $this->get_id() > 0 ) {
			$this->data_store->read( $this );
		}
	}

	/**
	 * Get the lesson type.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function get_type(): string {
		return $this->get_prop( 'type' ) ?? '';
	}

	/**
	 * Set the lesson type.
	 *
	 * @return void
	 * @since 1.0.0
	 * @param mixed $type Type.
	 */
	public function set_type( $type ) {
		$this->set_prop( 'type', $type );
	}
}
