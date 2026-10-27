<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\DataStore;

use OhMyLMS\Commerce\Abstracts\Data;
use OhMyLMS\Commerce\Abstracts\DataStore;

/**
 * Plugin order item store implementation.
 */
class OrderItemStore extends DataStore {

	/**
	 * Create new record
	 *
	 * @param mixed $item Item.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function create( &$item ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'ohmylms_order_items',
			array(
				'order_item_name' => $item->get_name(),
				'order_item_type' => $item->get_type(),
				'order_id'        => $item->get_order_id(),
			)
		);
		$item->set_id( $wpdb->insert_id );
		$this->save_item_data( $item );
	}

	/**
	 * Read new record
	 *
	 * @param mixed $item Item.
	 * @return mixed
	 * @since 1.0.0
	 * @throws \Exception When the operation cannot be completed.
	 */
	public function read( &$item ) {
		global $wpdb;

		// Get from cache if available.
		$data = wp_cache_get( 'ecom-item-' . $item->get_id(), 'order-items' );

		if ( false === $data ) {
			$data = $wpdb->get_row( $wpdb->prepare( 'SELECT order_id, order_item_name FROM %i WHERE order_item_id = %d LIMIT 1;', $wpdb->prefix . 'ohmylms_order_items', $item->get_id() ) );
			wp_cache_set( 'ecom-item-' . $item->get_id(), $data, 'order-items' );
		}

		if ( ! $data ) {
			throw new \Exception( __( 'Invalid order item.', 'ohmylms' ) );
		}

		$item->set_props(
			array(
				'order_id' => $data->order_id,
				'name'     => $data->order_item_name,
			)
		);
	}

	/**
	 * Update new record
	 *
	 * @param mixed $data Data.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function update( &$data ) {
		// TODO: Implement update() method.
	}

	/**
	 * Delete new record
	 *
	 * @param mixed $data Input data.
	 * @param array $args Operation arguments.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function delete( &$data, $args = array() ) {
		// TODO: Implement delete() method.
	}


	/**
	 * Get the properties to update for the given object.
	 *
	 * @param object $object The object to get properties for.
	 * @param array  $meta_key_to_props Mapping of meta keys to properties.
	 * @param string $meta_type The type of metadata. Default is 'post'.
	 * @return array The properties to update.
	 *
	 * @since 1.0.0
	 */
	protected function get_props_to_update( $object, $meta_key_to_props, $meta_type = 'post' ) {
		$props_to_update = array();
		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$props_to_update[ $meta_key ] = $prop;
		}

		return $props_to_update;
	}



	/**
	 * Update custom item metadata.
	 *
	 * @param int    $item_id The ID of the item.
	 * @param string $meta_key The metadata key.
	 * @param mixed  $meta_value The metadata value.
	 * .
	 * @since 1.0.0
	 */
	function update_metadata( $item_id, $meta_key, $meta_value ) {
		global $wpdb;
		$table_name_meta = $wpdb->prefix . 'ohmylms_order_itemmeta';

		// Check if the meta key exists for the custom item.
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SELECT meta_id FROM %i WHERE order_item_id = %d AND meta_key = %s', $table_name_meta, $item_id, $meta_key )
		);

		if ( $exists ) {
			// Update the existing meta value.
			$wpdb->update(
				$table_name_meta,
				array( 'meta_value' => maybe_serialize( $meta_value ) ),
				array(
					'order_item_id' => $item_id,
					'meta_key'      => $meta_key,
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
		} else {
			// Insert new meta value.
			$wpdb->insert(
				$table_name_meta,
				array(
					'order_item_id' => $item_id,
					'meta_key'      => $meta_key,
					'meta_value'    => maybe_serialize( $meta_value ),
				),
				array( '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Retrieve custom item metadata.
	 *
	 * @param int    $item_id The ID of the custom item.
	 * @param string $meta_key The metadata key.
	 * @param bool   $single Whether to return a single value.
	 * @return mixed The metadata value.
	 *
	 * @since 1.0.0
	 */
	function get_metadata( $item_id, $meta_key, $single = true ) {
		global $wpdb;
		$table_name_meta = $wpdb->prefix . 'ohmylms_order_itemmeta';

		$meta_value = $wpdb->get_var(
			$wpdb->prepare( 'SELECT meta_value FROM %i WHERE order_item_id = %d AND meta_key = %s', $table_name_meta, $item_id, $meta_key )
		);

		return $single ? maybe_unserialize( $meta_value ) : array_map( 'maybe_unserialize', (array) $meta_value );
	}
}
