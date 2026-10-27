<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer classmap path; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\Commerce;

/**
 * Plugin post types implementation.
 */
class PostTypes {

	/**
	 * Register all order-related post types.
	 *
	 * @since 1.0.0
	 */
	public function register_post_types() {

		$this->register_post_type(
			'ohmylms-order',
			array(
				'singular_name' => _x( 'Order', 'Post Type Singular Name', 'ohmylms' ),
				'menu_name'     => _x( 'Orders', 'Admin menu name', 'ohmylms' ),
			),
			array(
				'show_in_nav_menus' => false,
				'query_var'         => false,
				'has_archive'       => false,
				'show_ui'           => true,
				'public'            => false,
				'show_in_menu'      => false,
			)
		);

		$this->register_post_type(
			'ohmylms_order_refund',
			array(
				'name'          => _x( 'Refunds', 'Post Type General Name', 'ohmylms' ),
				'singular_name' => _x( 'Refund', 'Post Type Singular Name', 'ohmylms' ),
			),
			array(
				'show_in_nav_menus' => false,
				'query_var'         => false,
				'has_archive'       => false,
				'show_ui'           => true,
				'public'            => false,
				'show_in_menu'      => false,
			)
		);

		$this->register_post_type(
			'ohmylms_coupons',
			array(
				'name'          => __( 'Coupons', 'ohmylms' ),
				'singular_name' => __( 'Coupon', 'ohmylms' ),
				'menu_name'     => _x( 'Coupons', 'Admin menu name', 'ohmylms' ),
			),
			array(
				'public'              => false,
				'map_meta_cap'        => true,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'show_in_admin_bar'   => false,
				'supports'            => array( 'title' ),
			)
		);

		$this->register_post_type(
			'ohmylms-subscription',
			array(
				'name'              => __( 'Subscriptions', 'ohmylms' ),
				'singular_name'     => __( 'Subscription', 'ohmylms' ),
				'menu_name'         => _x( 'Subscriptions', 'Admin menu name', 'ohmylms' ),
				'edit_item'         => __( 'Edit Subscription', 'ohmylms' ),
				'view_item'         => __( 'View Subscription', 'ohmylms' ),
				'search_items'      => __( 'Search Subscriptions', 'ohmylms' ),
				'not_found'         => __( 'No Subscriptions found', 'ohmylms' ),
				'parent_item_colon' => __( 'Parent Order:', 'ohmylms' ),
			),
			array(
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'show_in_rest'        => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'hierarchical'        => true,
				'supports'            => array( 'title', 'editor', 'custom-fields' ),
			)
		);

		/**
		 * Action hook after creating order post types
		 */
		do_action( 'rex_after_registering_order_post_types' );
		do_action( 'ohmylms_after_registering_subscription_post_type' );
	}


	/**
	 * Register a custom post type.
	 *
	 * @param string $post_type Post type name.
	 * @param array  $custom_labels Optional. Custom labels for the post type.
	 * @param array  $custom_args Optional. Custom arguments for the post type.
	 *
	 * @since 1.0.0
	 */
	private function register_post_type( $post_type, $custom_labels = array(), $custom_args = array() ) {
		$singular       = $custom_labels['singular_name'] ?? $post_type;
		$plural         = $custom_labels['name'] ?? ( $custom_labels['menu_name'] ?? $singular );
		$default_labels = array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $singular,
			'name_admin_bar'     => $singular,
			'add_new_item'       => sprintf( /* translators: %s: singular post type label. */ __( 'Add New %s', 'ohmylms' ), $singular ),
			'new_item'           => sprintf( /* translators: %s: singular post type label. */ __( 'New %s', 'ohmylms' ), $singular ),
			'edit_item'          => sprintf( /* translators: %s: singular post type label. */ __( 'Edit %s', 'ohmylms' ), $singular ),
			'view_item'          => sprintf( /* translators: %s: singular post type label. */ __( 'View %s', 'ohmylms' ), $singular ),
			'all_items'          => sprintf( /* translators: %s: plural post type label. */ __( 'All %s', 'ohmylms' ), $plural ),
			'search_items'       => sprintf( /* translators: %s: plural post type label. */ __( 'Search %s', 'ohmylms' ), $plural ),
			'not_found'          => sprintf( /* translators: %s: plural post type label. */ __( 'No %s found.', 'ohmylms' ), $plural ),
			'not_found_in_trash' => sprintf( /* translators: %s: plural post type label. */ __( 'No %s found in Trash.', 'ohmylms' ), $plural ),
		);

		$labels = wp_parse_args( $custom_labels, $default_labels );

		$default_args = array(
			'label'              => $singular,
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => $post_type ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
		);

		$args = wp_parse_args( $custom_args, $default_args );

		register_post_type( $post_type, $args );
	}


	/**
	 * Register post status for Post types.
	 */
	public function register_post_status() {
		$order_statuses = array(
			'ohmylms-pending'    => array(
				'label'                     => _x( 'Pending payment', 'Order status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Pending payment <span class="count">(%s)</span>', 'Pending payment <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-processing' => array(
				'label'                     => _x( 'Processing payment', 'Order status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Processing payment <span class="count">(%s)</span>', 'Processing payment <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-on-hold'    => array(
				'label'                     => _x( 'On Hold payment', 'Order status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'On Hold payment <span class="count">(%s)</span>', 'On Hold payment <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-failed'     => array(
				'label'                     => _x( 'Failed payment', 'Order status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Failed payment <span class="count">(%s)</span>', 'Failed payment <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-completed'  => array(
				'label'                     => _x( 'Completed payment', 'Order status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Completed payment <span class="count">(%s)</span>', 'Completed payment <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-cancelled'  => array(
				'label'                     => _x( 'Cancelled payment', 'Order status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Cancelled payment <span class="count">(%s)</span>', 'Cancelled payment <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-refunded'   => array(
				'label'                     => _x( 'Refunded payment', 'Order status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Refunded payment <span class="count">(%s)</span>', 'Refunded payment <span class="count">(%s)</span>', 'ohmylms' ),
			),
		);

		foreach ( $order_statuses as $order_status => $values ) {
			register_post_status( $order_status, $values );
		}

		// Subscription Statuses.
		$subscription_statuses = array(
			'ohmylms-pending'        => array(
				'label'                     => _x( 'Pending', 'Subscription status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-active'         => array(
				'label'                     => _x( 'Active', 'Subscription status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-on-hold'        => array(
				'label'                     => _x( 'On Hold', 'Subscription status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'On Hold <span class="count">(%s)</span>', 'On Hold <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-pending-cancel' => array(
				'label'                     => _x( 'Pending Cancellation', 'Subscription status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-cancelled'      => array(
				'label'                     => _x( 'Cancelled', 'Subscription status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'ohmylms' ),
			),
			'ohmylms-expired'        => array(
				'label'                     => _x( 'Expired', 'Subscription status', 'ohmylms' ),
				'public'                    => false,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'ohmylms' ),
			),
		);

		foreach ( $subscription_statuses as $sub_status => $values ) {
			register_post_status( $sub_status, $values );
		}
	}
}
