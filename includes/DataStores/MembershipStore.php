<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Class CourseStore.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\DataStores;

use OhMyLMS\Abstracts\DataStore;
use OhMyLMS\Data\Membership;

defined( 'ABSPATH' ) || exit;

/**
 * Class CourseStore
 *
 * @package OhMyLMS\DataStores
 * @since 1.0.0
 */
class MembershipStore extends DataStore {

	/**
	 * Create membership
	 *
	 * @param Membership $membership Membership.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function create( &$membership ) {

		if ( ! $membership->get_date_created( 'edit' ) ) {
			$membership->set_date_created( time() );
		}

		$id = wp_insert_post(
			apply_filters(
				'ohmylms_new_membership_data',
				array(
					'post_type'    => OHMYLMS_MEMBERSHIP_CPT,
					'post_author'  => get_current_user_id(),
					'post_status'  => $membership->get_status() ? $membership->get_status() : 'publish',
					'post_title'   => $membership->get_name() ? $membership->get_name() : __( 'Untitled', 'ohmylms' ),
					'post_content' => $membership->get_description(),
					'post_name'    => $membership->get_slug( 'edit' ),
				)
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			$membership->set_id( $id );
			flush_rewrite_rules();
			$this->update_post_meta( $membership );

			/**
			 * Fires after a new membership is created.
			 *
			 * This action hook allows developers to perform additional actions after a membership is created.
			 *
			 * @param int   $id     The ID of the newly created membership.
			 * @param array $membership The membership data array, containing information about the created membership.
			 *
			 * @since 1.0.0
			 */
			do_action( 'ohmylms_after_creating_new_membership', $id, $membership );
		}
	}


	/**
	 * Read data
	 *
	 * @param Membership $membership Membership.
	 * @return mixed|void
	 * @throws \Exception When the operation cannot be completed.
	 * @since 1.0.0
	 */
	public function read( &$membership ) {
		$post_object = get_post( $membership->get_id() );
		if ( ! $membership->get_id() || ! $post_object || OHMYLMS_MEMBERSHIP_CPT !== $post_object->post_type ) {
			return;
			// throw new \Exception( __( 'Invalid membership.', 'ohmylms' ) );.
		}

		$membership->set_props(
			array(
				'name'               => $post_object->post_title,
				'slug'               => $post_object->post_name,
				'status'             => $post_object->post_status,
				'post_date'          => $post_object->post_date,
				'date_created'       => $post_object->post_date_gmt,
				'date_modified'      => $post_object->post_modified_gmt,
				'description'        => $post_object->post_content,
				'password_protected' => $post_object->post_password ? $post_object->post_password : '',
				'thumbnail_id'       => get_post_thumbnail_id( $membership->get_id() ),
			)
		);

		$this->read_membership_data( $membership );
	}


	/**
	 * Update membership data
	 *
	 * @param Membership $membership The membership object to update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function update( &$membership ) {
		$post_data = array(
			'post_content' => $membership->get_description( 'edit' ),
			'post_excerpt' => $membership->get_short_description( 'edit' ),
			'post_title'   => $membership->get_name( 'edit' ),
			'post_status'  => $membership->get_status( 'edit' ) ? $membership->get_status( 'edit' ) : 'publish',
			'post_name'    => sanitize_title( $membership->get_name() ),
			'post_type'    => OHMYLMS_MEMBERSHIP_CPT,
		);
		wp_update_post( array_merge( array( 'ID' => $membership->get_id() ), $post_data ) );

		$this->update_post_meta( $membership );

		/**
		 * Action hook to perform additional actions after a chapter is updated.
		 *
		 * @param int    $chapter_id The ID of the updated chapter.
		 * @param Membership $chapter    The chapter object.
		 *
		 * @since 1.0.0
		 */
		do_action( 'ohmylms_update_membership', $membership->get_id(), $membership );
	}


	/**
	 * Update post meta for the membership.
	 *
	 * @param Membership $membership The membership object.
	 * @param bool       $force Whether to force the update.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function update_post_meta( &$membership, $force = false ) {
		$meta_key_to_props = array(
			'_price'                        => 'price',
			'_regular_price'                => 'regular_price',
			'_sale_price'                   => 'sale_price',
			'_sale_price_dates_from'        => 'sale_price_dates_from',
			'_sale_price_dates_to'          => 'sale_price_dates_to',
			'_sign_up_fee'                  => 'sign_up_fee',
			'_free_trial'                   => 'free_trial',
			'_stop_renew'                   => 'stop_renew',
			'_subscription_length'          => 'subscription_length',
			'_subscription_period'          => 'subscription_period',
			'_subscription_period_interval' => 'subscription_period_interval',
			'_products'                     => 'products',
			'_course_categories'            => 'course_categories',
			'_course_tags'                  => 'course_tags',
			'_course_curriculum'            => 'course_curriculum',
			'_course_bundles'               => 'course_bundles',
			'_excluded_courses'             => 'excluded_courses',
		);

		$props_to_update = $meta_key_to_props;

		foreach ( $props_to_update as $meta_key => $prop ) {
			$value = $membership->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			switch ( $prop ) {
				case 'sale_price_dates_from':
				case 'sale_price_dates_to':
					$value = $value ? $value->getTimestamp() : '';
					break;
			}

			$membership_price_props = array( '_regular_price', '_sale_price' );
			if ( in_array( $meta_key, $membership_price_props ) ) {
				$value = ohmylms_format_decimal( $value );

				if ( $membership->is_on_sale( 'edit' ) ) {
					update_post_meta( $membership->get_id(), '_price', $membership->get_sale_price( 'edit' ) );
					$membership->set_price( $membership->get_sale_price( 'edit' ) );
				} else {
					update_post_meta( $membership->get_id(), '_price', $membership->get_regular_price( 'edit' ) );
					$membership->set_price( $membership->get_regular_price( 'edit' ) );
				}
			}

			$this->update_or_delete_post_meta( $membership, $meta_key, $value );
		}
	}


	/**
	 * Delete the membership
	 *
	 * @param mixed $membership Membership.
	 * @param array $args Operation arguments.
	 * @return mixed|void
	 * @since 1.0.0
	 */
	public function delete( &$membership, $args = array() ) {
		if ( $membership ) {
			$membership_id = $membership->get_id();
			if ( $membership_id ) {
				wp_delete_post( $membership_id, true );
				/**
				 * Triggered after deleting a membership.
				 *
				 * This action hook allows developers to perform additional actions after a membership is deleted.
				 *
				 * @since 1.0.0
				 */
				do_action( 'ohmylms_after_deleting_a_membership' );
			}
		}
	}


	/**
	 * Helper function that reads membership data
	 *
	 * @param membership $membership Membership.
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function read_membership_data( &$membership ) {
		$id               = $membership->get_id();
		$post_meta_values = get_post_meta( $id );

		$meta_key_to_props = array(
			'_price'                        => 'price',
			'_regular_price'                => 'regular_price',
			'_sale_price'                   => 'sale_price',
			'_sale_price_dates_from'        => 'sale_price_dates_from',
			'_sale_price_dates_to'          => 'sale_price_dates_to',
			'_sign_up_fee'                  => 'sign_up_fee',
			'_free_trial'                   => 'free_trial',
			'_stop_renew'                   => 'stop_renew',
			'_subscription_length'          => 'subscription_length',
			'_subscription_period'          => 'subscription_period',
			'_subscription_period_interval' => 'subscription_period_interval',
			'_products'                     => 'products',
			'_course_categories'            => 'course_categories',
			'_course_tags'                  => 'course_tags',
			'_course_curriculum'            => 'course_curriculum',
			'_course_bundles'               => 'course_bundles',
			'_excluded_courses'             => 'excluded_courses',
		);

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$meta_value         = isset( $post_meta_values[ $meta_key ][0] ) ? $post_meta_values[ $meta_key ][0] : null;
			$set_props[ $prop ] = maybe_unserialize( $meta_value );
		}
		$membership->set_props( $set_props );
	}


	/**
	 * Check membership is already purchased or not
	 *
	 * @return bool
	 * @param mixed $membership Membership.
	 */
	public function is_already_purchased( &$membership ) {
		global $wpdb;

		// Get the current user ID.
		$user_id = get_current_user_id();

		// Prepare the query.
		$table_name = $wpdb->prefix . 'ohmylms_user_membership';
		$query      = $wpdb->prepare( 'SELECT membership_id FROM %i WHERE user_id = %d AND `status` = %s AND membership_id = %d', $table_name, $user_id, 'enrolled', $membership->get_id() );
		// Execute the query and return the result.
		$result = $wpdb->get_row( $query, ARRAY_A );
		return ! empty( $result ) ? true : false;
	}

	/**
	 * Check how many memberships are already purchased
	 *
	 * @return int
	 * @param mixed $membership Membership.
	 */
	public function count_membership_members( &$membership ) {
		global $wpdb;

		// Table name.
		$table_name = $wpdb->prefix . 'ohmylms_user_membership';

		// Prepare the query to count rows.
		$query = $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE `status` = %s AND membership_id = %d', $table_name, 'enrolled', $membership->get_id() );

		// Execute the query and return the count.
		$count = $wpdb->get_var( $query );
		return (int) $count;
	}


	/**
	 * Update membership status
	 *
	 * @return bool
	 * @param mixed $membership Membership.
	 * @param mixed $new_status New status.
	 */
	public function update_membership_status( &$membership, $new_status ) {
		global $wpdb;

		// Table where the membership statuses are stored.
		$table_name = $wpdb->prefix . 'ohmylms_user_membership';

		// Get the current user ID.
		$user_id = get_current_user_id();

		// Validate the input parameters.
		if ( empty( $membership ) || empty( $new_status ) ) {
			return false;
		}

		// Update the membership status in the database.
		$result = $wpdb->update(
			$table_name,
			array( 'status' => $new_status ), // Data to update.
			array(
				'user_id'       => $user_id,
				'membership_id' => $membership->get_id(),
			), // Where conditions.
			array( '%s' ), // Format for the updated data.
			array( '%d', '%d', '%s' ) // Format for the where conditions.
		);

		// Check if the update was successful.
		if ( false !== $result ) {
			// Log the status change or trigger an action hook.
			do_action( 'ohmylms_membership_status_updated', $membership->get_id(), $new_status, $user_id );

			return true;
		}

		return false;
	}


	/**
	 * Cancel enrollment for Membership store.
	 *
	 * @param mixed $membership Membership.
	 * @param mixed $student_id Student identifier.
	 * @param mixed $order_id Order identifier.
	 */
	public function cancel_enrollment( &$membership, $student_id, $order_id ) {
		global $wpdb;
		$user_id          = $student_id;
		$enrollment_table = $wpdb->prefix . 'ohmylms_user_enrollment';
		$membership_table = $wpdb->prefix . 'ohmylms_user_membership';
		$products         = $membership->get_products();

		if ( ! empty( $products ) ) {
			foreach ( $products as $product ) {
				$wpdb->update(
					$enrollment_table,
					array(
						'status' => 'cancelled',
					),
					array(
						'user_id'  => $user_id,
						'order_id' => $order_id,
					)
				);
			}
		}

		// Remove the user from the membership.
		$wpdb->update(
			$membership_table,
			array(
				'status' => 'cancelled',
			),
			array(
				'user_id'  => $user_id,
				'order_id' => $order_id,
			)
		);
		\OhMyLMS\Membership\CourseSelection::sync( $membership->get_id() );
	}


	/**
	 * Get billing period for Membership store.
	 *
	 * @param mixed $membership Membership.
	 */
	public function get_billing_period( &$membership ) {
		$membership_id       = $membership->get_id();
		$subscription_length = get_post_meta( $membership_id, '_subscription_period', true );

		return $subscription_length;
	}


	/**
	 * Get billing interval for Membership store.
	 *
	 * @param mixed $membership Membership.
	 */
	public function get_billing_interval( &$membership ) {
		$membership_id       = $membership->get_id();
		$subscription_length = get_post_meta( $membership_id, '_subscription_length', true );

		if ( empty( $subscription_length ) ) {
			return false;
		}

		$subscription_data = maybe_unserialize( $subscription_length );

		if ( ! is_array( $subscription_data ) || ! isset( $subscription_data['duration'] ) || ! isset( $subscription_data['period'] ) ) {
			return false;
		}

		if ( 'every' === $subscription_data['period'] ) {
			return 1;
		}
		return $subscription_data['duration'];
	}
}
