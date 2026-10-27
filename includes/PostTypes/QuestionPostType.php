<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Question post type to connect with topics.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\PostTypes;

/**
 * Question post type to connect with topics
 *
 * @since 1.0.0
 */
class QuestionPostType {

	/**
	 * Post type initialization
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_question_cpt' ) );
	}

	/**
	 * Register section cpt
	 *
	 * @since 1.0.0
	 */
	public function register_question_cpt(): void {
		$labels = array(
			'name'                  => _x( 'Questions', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Question', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Questions', 'ohmylms' ),
			'name_admin_bar'        => __( 'Question', 'ohmylms' ),
			'archives'              => __( 'Question Archives', 'ohmylms' ),
			'attributes'            => __( 'Question Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Question:', 'ohmylms' ),
			'all_items'             => __( 'All Questions', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Question', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Question', 'ohmylms' ),
			'edit_item'             => __( 'Edit Question', 'ohmylms' ),
			'update_item'           => __( 'Update Question', 'ohmylms' ),
			'view_item'             => __( 'View Question', 'ohmylms' ),
			'view_items'            => __( 'View Question', 'ohmylms' ),
			'search_items'          => __( 'Search Question', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in Question', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Question', 'ohmylms' ),
			'items_list'            => __( 'Questions list', 'ohmylms' ),
			'items_list_navigation' => __( 'Questions list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter Questions list', 'ohmylms' ),
		);

		$args = array(
			'label'                 => __( 'Question', 'ohmylms' ),
			'description'           => __( 'Questions to maintain Questions', 'ohmylms' ),
			'labels'                => $labels,
			'supports'              => array( 'editor', 'thumbnail' ),
			'taxonomies'            => array( 'category', 'post_tag' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_rest'          => true,
			'rest_base'             => 'ohmylms-questions',
			'rest_controller_class' => \OhMyLMS\Learning\NativeQuestionController::class,
			'show_in_menu'          => false,
			'menu_position'         => 5,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'capability_type'       => 'post',
		);
		register_post_type( 'ohmylms-question', $args );
	}
}
