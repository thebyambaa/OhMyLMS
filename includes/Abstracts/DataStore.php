<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class DataStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Abstracts;

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
	 * @param mixed $data Input data.
	 * @param mixed $meta_key Meta key.
	 * @param mixed $meta_value Meta value.
	 * @return bool
	 */
	protected function update_or_delete_post_meta( $data, $meta_key, $meta_value ) {
		if ( in_array( $meta_value, array( array(), '' ), true ) && ! in_array( $meta_key, $this->must_exist_meta_keys, true ) ) {
			$updated = delete_post_meta( $data->get_id(), $meta_key );
		} else {
			$updated = update_post_meta( $data->get_id(), $meta_key, $meta_value );
		}
		return (bool) $updated;
	}

	/**
	 * Update post meta data for the given chapter.
	 *
	 * @param \OhMyLMS\Commerce\Data\Chapter $chapter The chapter object to update.
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$chapter ) {
		$meta_key_to_props = array();

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $chapter->{
				"get_$prop"
			}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			$this->update_or_delete_post_meta( $chapter, $meta_key, $value );
		}
	}

	/**
	 * String to timestamp for Data store.
	 *
	 * @param mixed $time_string Time string.
	 */
	protected function string_to_timestamp( $time_string ) {
		return '0000-00-00 00:00:00' !== $time_string ? ohmylms_string_to_timestamp( $time_string ) : null;
	}


	/**
	 * Generate a unique slug for a given post type.
	 *
	 * @param string   $slug The desired slug.
	 * @param string   $post_type The post type to check uniqueness against.
	 * @param int|null $exclude_id Optional. A post ID to exclude from the check (useful for updates).
	 * @return string Unique slug.
	 */
	public function generate_unique_slug( $slug, $post_type, $exclude_id = null ) {
		$post_id = absint( $exclude_id );
		$parent  = $post_id ? (int) get_post_field( 'post_parent', $post_id ) : 0;
		// LMS URLs are reserved even while their content is still a draft.
		return wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'publish', $post_type, $parent );
	}
}
