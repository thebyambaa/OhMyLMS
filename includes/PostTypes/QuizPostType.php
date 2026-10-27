<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Quiz post type to connect with topics.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\PostTypes;

/**
 * Quiz post type to connect with topics
 *
 * @since 1.0.0
 */
class QuizPostType {

	/**
	 * Post type initialization
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_quiz_cpt' ) );
	}

	/**
	 * Register section cpt
	 *
	 * @since 1.0.0
	 */
	public function register_quiz_cpt(): void {
		$labels = array(
			'name'                  => _x( 'Quiz', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Quiz', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Quiz', 'ohmylms' ),
			'name_admin_bar'        => __( 'Quiz', 'ohmylms' ),
			'archives'              => __( 'Quiz Archives', 'ohmylms' ),
			'attributes'            => __( 'Quiz Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Quiz:', 'ohmylms' ),
			'all_items'             => __( 'All Quiz', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Quiz', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Quiz', 'ohmylms' ),
			'edit_item'             => __( 'Edit Quiz', 'ohmylms' ),
			'update_item'           => __( 'Update Quiz', 'ohmylms' ),
			'view_item'             => __( 'View Quiz', 'ohmylms' ),
			'view_items'            => __( 'View Quiz', 'ohmylms' ),
			'search_items'          => __( 'Search Quiz', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in Quiz', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Quiz', 'ohmylms' ),
			'items_list'            => __( 'Quiz list', 'ohmylms' ),
			'items_list_navigation' => __( 'Quiz list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter Quiz list', 'ohmylms' ),
		);

		$args = array(
			'label'                 => __( 'Quiz', 'ohmylms' ),
			'description'           => __( 'Quiz to maintain quiz and questions', 'ohmylms' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'author' ),
			'taxonomies'            => array( 'category', 'post_tag' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_rest'          => true,
			'rest_base'             => 'ohmylms-quizzes',
			'rest_controller_class' => \OhMyLMS\Learning\NativeQuizController::class,
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
		register_post_type( 'ohmylms-quiz', $args );
	}
}
