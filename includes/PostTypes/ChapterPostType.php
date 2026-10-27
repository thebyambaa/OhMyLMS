<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Chapter post type to connect with topics.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\PostTypes;

/**
 * Chapter post type to connect with topics
 *
 * @since 1.0.0
 */
class ChapterPostType {

	/**
	 * Post type initialization
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_lesson_cpt' ) );
	}

	/**
	 * Register section cpt
	 *
	 * @since 1.0.0
	 */
	public function register_lesson_cpt(): void {
		$labels = array(
			'name'                  => _x( 'Chapters', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Chapter', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Chapters', 'ohmylms' ),
			'name_admin_bar'        => __( 'Chapter', 'ohmylms' ),
			'archives'              => __( 'Chapter Archives', 'ohmylms' ),
			'attributes'            => __( 'Chapter Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Chapter:', 'ohmylms' ),
			'all_items'             => __( 'All Chapters', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Chapter', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Chapter', 'ohmylms' ),
			'edit_item'             => __( 'Edit Chapter', 'ohmylms' ),
			'update_item'           => __( 'Update Chapter', 'ohmylms' ),
			'view_item'             => __( 'View Chapter', 'ohmylms' ),
			'view_items'            => __( 'View Chapter', 'ohmylms' ),
			'search_items'          => __( 'Search Chapter', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in Chapter', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Chapter', 'ohmylms' ),
			'items_list'            => __( 'Chapters list', 'ohmylms' ),
			'items_list_navigation' => __( 'Chapters list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter Chapters list', 'ohmylms' ),
		);

		$args = array(
			'label'               => __( 'Chapter', 'ohmylms' ),
			'description'         => __( 'Chapters to maintain lessons', 'ohmylms' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'taxonomies'          => array( 'category', 'post_tag' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
		);
		register_post_type( 'ohmylms-chapter', $args );
	}
}
