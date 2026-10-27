<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Lesson post type to connect with topics.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\PostTypes;

/**
 * Lesson post type to connect with topics
 *
 * @since 1.0.0
 */
class AssignmentPostType {

	/**
	 * Post type initialization
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_assignment_cpt' ) );
	}

	/**
	 * Register section cpt
	 *
	 * @since 1.0.0
	 */
	public function register_assignment_cpt(): void {
		$labels = array(
			'name'                  => _x( 'Assignment', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Assignment', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Assignment', 'ohmylms' ),
			'name_admin_bar'        => __( 'Assignment', 'ohmylms' ),
			'archives'              => __( 'Assignment Archives', 'ohmylms' ),
			'attributes'            => __( 'Assignment Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Assignment:', 'ohmylms' ),
			'all_items'             => __( 'All Assignment', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Assignment', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Assignment', 'ohmylms' ),
			'edit_item'             => __( 'Edit Assignment', 'ohmylms' ),
			'update_item'           => __( 'Update Assignment', 'ohmylms' ),
			'view_item'             => __( 'View Assignment', 'ohmylms' ),
			'view_items'            => __( 'View Assignment', 'ohmylms' ),
			'search_items'          => __( 'Search Assignment', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in Assignment', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Assignment', 'ohmylms' ),
			'items_list'            => __( 'Assignment list', 'ohmylms' ),
			'items_list_navigation' => __( 'Assignment list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter Assignment list', 'ohmylms' ),
		);

		$args = array(
			'label'               => __( 'Assignment', 'ohmylms' ),
			'description'         => __( 'Assignment to maintain Assignment', 'ohmylms' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'menu_position'       => 5,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
		);
		register_post_type( 'ohmylms-assignment', $args );
	}
}
