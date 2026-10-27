<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Lesson post type registration.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\PostTypes;

/**
 * Lesson post type to connect with topics
 *
 * @since 1.0.0
 */
class LessonPostType {

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
	public function register_lesson_cpt() {
		$labels = array(
			'name'                  => _x( 'Lessons', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Lesson', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Lessons', 'ohmylms' ),
			'name_admin_bar'        => __( 'Lesson', 'ohmylms' ),
			'archives'              => __( 'Lesson Archives', 'ohmylms' ),
			'attributes'            => __( 'Lesson Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Lesson:', 'ohmylms' ),
			'all_items'             => __( 'All Lessons', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Lesson', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Lesson', 'ohmylms' ),
			'edit_item'             => __( 'Edit Lesson', 'ohmylms' ),
			'update_item'           => __( 'Update Lesson', 'ohmylms' ),
			'view_item'             => __( 'View Lesson', 'ohmylms' ),
			'view_items'            => __( 'View Lesson', 'ohmylms' ),
			'search_items'          => __( 'Search Lesson', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in Lesson', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Lesson', 'ohmylms' ),
			'items_list'            => __( 'Lessons list', 'ohmylms' ),
			'items_list_navigation' => __( 'Lessons list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter Lessons list', 'ohmylms' ),
		);

		$args = array(
			'label'                 => __( 'Lesson', 'ohmylms' ),
			'description'           => __( 'Lessons to maintain lessons', 'ohmylms' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'author' ),
			'taxonomies'            => array( 'category', 'post_tag' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_rest'          => true,
			'rest_base'             => 'ohmylms-lessons',
			'rest_controller_class' => \OhMyLMS\Learning\NativeLessonController::class,
			'show_in_menu'          => false,
			'menu_position'         => 5,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => false,
			'publicly_queryable'    => true,
			'capability_type'       => 'post',
		);
		register_post_type( 'ohmylms-lesson', $args );
	}
}
