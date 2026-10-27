<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Sections post type to connect with course.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\PostTypes;

/**
 * Sections post type to connect with course
 *
 * @since 1.0.0
 */
class SectionPostType {

	/**
	 * Initialize Section post type.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_section_cpt' ) );
	}

	/**
	 * Register section cpt
	 *
	 * @since 1.0.0
	 */
	public function register_section_cpt() {
		$labels = array(
			'name'                  => _x( 'Sections', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Section', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Sections', 'ohmylms' ),
			'name_admin_bar'        => __( 'Section', 'ohmylms' ),
			'archives'              => __( 'Section Archives', 'ohmylms' ),
			'attributes'            => __( 'Section Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Section:', 'ohmylms' ),
			'all_items'             => __( 'All Sections', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Section', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Section', 'ohmylms' ),
			'edit_item'             => __( 'Edit Section', 'ohmylms' ),
			'update_item'           => __( 'Update Section', 'ohmylms' ),
			'view_item'             => __( 'View Section', 'ohmylms' ),
			'view_items'            => __( 'View Section', 'ohmylms' ),
			'search_items'          => __( 'Search Section', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in Section', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Section', 'ohmylms' ),
			'items_list'            => __( 'Sections list', 'ohmylms' ),
			'items_list_navigation' => __( 'Sections list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter sections list', 'ohmylms' ),
		);
		$args   = array(
			'label'               => __( 'Section', 'ohmylms' ),
			'description'         => __( 'Sections to maintain lessons', 'ohmylms' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'show_in_rest'        => true,
		);
		// register_post_type('ohmylms-section', $args);.
	}
}
