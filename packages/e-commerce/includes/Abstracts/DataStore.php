<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.
/**
 * Class DataStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Commerce\Abstracts;

defined( 'ABSPATH' ) || exit;

/**
 * Class DataStore
 *
 * @package OhMyLMS\Abstracts
 * @since 1.0.0
 */
abstract class DataStore {

	/**
	 * Must exist meta keys.
	 *
	 * @var mixed
	 */
	protected $must_exist_meta_keys = array();

	/**
	 * Create new record
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 * @since 1.0.0
	 */
	abstract public function create( &$data );

	/**
	 * Read new record
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 * @since 1.0.0
	 */
	abstract public function read( &$data );


	/**
	 * Update new record
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 * @since 1.0.0
	 */
	abstract public function update( &$data );


	/**
	 * Delete new record
	 *
	 * @param mixed $data Input data.
	 * @param array $args Operation arguments.
	 * @return mixed
	 * @since 1.0.0
	 */
	abstract public function delete( &$data, $args = array() );


	/**
	 * Update or delete post meta.
	 *
	 * @param mixed $object Object.
	 * @param mixed $meta_key Meta key.
	 * @param mixed $meta_value Meta value.
	 * @return bool
	 */
	protected function update_or_delete_post_meta( $object, $meta_key, $meta_value ) {
		if ( in_array( $meta_value, array( array(), '' ), true ) && ! in_array( $meta_key, $this->must_exist_meta_keys, true ) ) {
			$updated = delete_post_meta( $object->get_id(), $meta_key );
		} else {
			$updated = update_post_meta( $object->get_id(), $meta_key, $meta_value );
		}

		return (bool) $updated;
	}


	/**
	 * String to timestamp for Data store.
	 *
	 * @param mixed $time_string Time string.
	 */
	protected function string_to_timestamp( $time_string ) {
		return '0000-00-00 00:00:00' !== $time_string ? ohmylms_string_to_timestamp( $time_string ) : null;
	}
}
