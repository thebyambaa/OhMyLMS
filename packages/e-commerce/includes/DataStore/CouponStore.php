<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce\DataStore;

use OhMyLMS\Abstracts\Data;
use OhMyLMS\Abstracts\DataStore;

/**
 * Plugin coupon store implementation.
 */
class CouponStore extends DataStore {

	/**
	 * Create a new coupon.
	 *
	 * @param \OhMyLMS\Commerce\Data\Coupon $coupon The coupon object.
	 */
	public function create( &$coupon ) {
		if ( ! $coupon->get_date_created( 'edit' ) ) {
			$coupon->set_date_created( time() );
		}

		$coupon_id = wp_insert_post(
			array(
				'post_type'     => 'ohmylms_coupon',
				'post_status'   => 'publish',
				'post_author'   => get_current_user_id(),
				'post_title'    => $coupon->get_code( 'edit' ),
				'post_content'  => $coupon->get_description( 'edit' ),
				'post_excerpt'  => $coupon->get_description( 'edit' ),
				'post_date'     => gmdate( 'Y-m-d H:i:s', $coupon->get_date_created()->getOffsetTimestamp() ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $coupon->get_date_created()->getTimestamp() ),
			),
			true
		);

		if ( $coupon_id ) {
			$coupon->set_id( $coupon_id );
			$this->update_post_meta( $coupon );
			do_action( 'ohmylms_new_coupon', $coupon_id, $coupon );
		}
	}

	/**
	 * Read new record
	 *
	 * @param mixed $coupon Coupon.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function read( &$coupon ) {
		$coupon_id   = $coupon->get_id();
		$post_object = get_post( $coupon->get_id() );
		if ( ! $post_object || 'ohmylms_coupon' !== $post_object->post_type ) {
			\OhMyLMS\Commerce\ecommerce()->session->set( 'applied_coupons', array() );
			return false;
		}

		$coupon->set_props(
			array(
				'title'                  => get_post_meta( $coupon_id, 'title', true ),
				'code'                   => $post_object->post_title,
				'description'            => $post_object->post_excerpt,
				'status'                 => $post_object->post_status,
				'date_created'           => $this->string_to_timestamp( $post_object->post_date_gmt ),
				'date_modified'          => $this->string_to_timestamp( $post_object->post_modified_gmt ),
				'date_expires'           => get_post_meta( $coupon_id, 'date_expires', true ),
				'date_start'             => get_post_meta( $coupon_id, 'date_start', true ),
				'discount_type'          => get_post_meta( $coupon_id, 'discount_type', true ),
				'amount'                 => get_post_meta( $coupon_id, 'amount', true ),
				'usage_count'            => get_post_meta( $coupon_id, 'usage_count', true ),
				'individual_use'         => 'yes' === get_post_meta( $coupon_id, 'individual_use', true ),
				'course_id_type'         => get_post_meta( $coupon_id, 'course_id_type', true ),
				'course_ids'             => array_filter( (array) explode( ',', get_post_meta( $coupon_id, 'course_ids', true ) ) ),
				'excluded_course_ids'    => array_filter( (array) explode( ',', get_post_meta( $coupon_id, 'exclude_course_ids', true ) ) ),
				'usage_limit'            => get_post_meta( $coupon_id, 'usage_limit', true ),
				'usage_limit_per_user'   => get_post_meta( $coupon_id, 'usage_limit_per_user', true ),
				'limit_usage_to_x_items' => 0 < get_post_meta( $coupon_id, 'limit_usage_to_x_items', true ) ? get_post_meta( $coupon_id, 'limit_usage_to_x_items', true ) : null,
				'exclude_sale_items'     => 'yes' === get_post_meta( $coupon_id, 'exclude_sale_items', true ),
				'minimum_amount'         => get_post_meta( $coupon_id, 'minimum_amount', true ),
				'maximum_amount'         => get_post_meta( $coupon_id, 'maximum_amount', true ),
				'email_restrictions'     => array_filter( (array) get_post_meta( $coupon_id, 'customer_email', true ) ),
				'used_by'                => array_filter( (array) get_post_meta( $coupon_id, '_used_by' ) ),
			)
		);
	}

	/**
	 * Update new record
	 *
	 * @param mixed $coupon Coupon.
	 * @return mixed
	 * @since 1.0.0
	 */
	public function update( &$coupon ) {
		$post_data = array(
			'post_content' => $coupon->get_description( 'edit' ),
			'post_excerpt' => $coupon->get_description( 'edit' ),
			'post_title'   => $coupon->get_code( 'edit' ),
		);
		if ( $coupon->get_date_created( 'edit' ) ) {
			$post_data['post_date_gmt'] = $coupon->get_date_created() ? gmdate( 'Y-m-d H:i:s', $coupon->get_date_created( 'edit' )->getTimestamp() ) : current_time( 'mysql' );
		}

		$post_data['post_modified']     = current_time( 'mysql' );
		$post_data['post_modified_gmt'] = current_time( 'mysql', 1 );
		wp_update_post( array_merge( array( 'ID' => $coupon->get_id() ), $post_data ) );

		if ( $coupon->get_id() ) {
			$this->update_post_meta( $coupon );
			do_action( 'ohmylms_update_coupon', $coupon->get_id(), $coupon );
		}
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
	 * Get coupon IDs by code.
	 *
	 * @param string $code The coupon code.
	 * @return array The list of coupon IDs.
	 *
	 * @since 1.0.0
	 */
	public function get_ids_by_code( $code ) {
		return get_posts(
			array(
				'post_type'      => 'ohmylms_coupon',
				'post_status'    => 'publish',
				'title'          => ohmylms_ecommerce_format_coupon_code( $code ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
	}
	/**
	 * Update post meta data for the given coupon.
	 *
	 * @param \OhMyLMS\Commerce\Data\Coupon $coupon The coupon object to update.
	 * @param bool                          $force Whether to force the update.
	 * .
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$coupon, $force = false ) {
		$meta_key_to_props = array(
			'title'                => 'title',
			'discount_type'        => 'discount_type',
			'amount'               => 'amount',
			'individual_use'       => 'individual_use',
			'course_id_type'       => 'course_id_type',
			'course_ids'           => 'course_ids',
			'excluded_course_ids'  => 'excluded_course_ids',
			'usage_limit'          => 'usage_limit',
			'usage_limit_per_user' => 'usage_limit_per_user',
			'usage_count'          => 'usage_count',
			'date_expires'         => 'date_expires',
			'date_start'           => 'date_start',
			'exclude_sale_items'   => 'exclude_sale_items',
		);

		$props_to_update = $meta_key_to_props;
		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $coupon->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			switch ( $prop ) {
				case 'course_ids':
				case 'excluded_course_ids':
					$value = is_array( $value ) ? $value : array();
					$value = implode( ',', array_filter( array_map( 'intval', $value ) ) );
					break;
				case 'date_expires':
				case 'date_start':
					$value = $value ? $value->getTimestamp() : null;
					break;
			}

			$updated = $this->update_or_delete_post_meta( $coupon, $meta_key, $value );
		}
	}
}
