<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\PostTypes;

use OhMyLMS\Abstracts\PostType;

defined( 'ABSPATH' ) || exit();

/**
 * Plugin course post type implementation.
 */
class CoursePostType extends PostType {

	/**
	 * Instance.
	 *
	 * @var null
	 */
	protected static $_instance = null;


	/**
	 * Instance.
	 *
	 * @return CoursePostType|null
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}



	/**
	 * Initialize Course post type.
	 */
	public function __construct() {
		$this->post_type = 'ohmylms-course';
		parent::__construct();

		add_action( 'init', array( $this, 'register_taxonomy' ) );
	}

	/**
	 * Get arguments of CPT - ohmylms-course
	 *
	 * @return array|void
	 * @since 1.0.0
	 */
	public function get_args() {

		$labels = array(
			'name'                  => _x( 'Courses', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Course', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Courses', 'ohmylms' ),
			'name_admin_bar'        => __( 'Course', 'ohmylms' ),
			'archives'              => __( 'Course Archives', 'ohmylms' ),
			'attributes'            => __( 'Course Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Course:', 'ohmylms' ),
			'all_items'             => __( 'All Courses', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Course', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Course', 'ohmylms' ),
			'edit_item'             => __( 'Edit Course', 'ohmylms' ),
			'update_item'           => __( 'Update Course', 'ohmylms' ),
			'view_item'             => __( 'View Course', 'ohmylms' ),
			'view_items'            => __( 'View Courses', 'ohmylms' ),
			'search_items'          => __( 'Search Course', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in course', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Course', 'ohmylms' ),
			'items_list'            => __( 'Courses list', 'ohmylms' ),
			'items_list_navigation' => __( 'Courses list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter Courses list', 'ohmylms' ),
		);

		$permalinks     = ohmylms_get_permalink_structure();
		$course_page_id = ohmylms_get_page_id( 'course' );
		$has_archive    = $course_page_id && get_post( $course_page_id ) ? urldecode( get_page_uri( $course_page_id ) ) : 'course';
		$supports       = array( 'title', 'editor', 'thumbnail', 'revisions', 'comments', 'excerpt' );

		$this->args = array(
			'labels'             => $labels,
			'public'             => false,
			'query_var'          => true,
			'publicly_queryable' => true,
			'show_ui'            => false,
			'has_archive'        => $has_archive,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'show_in_admin_bar'  => true,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => true,
			'show_in_menu'       => false,
			'taxonomies'         => array( 'course_category', 'course_tag' ),
			'supports'           => $supports,
			'hierarchical'       => false,
			'rewrite'            => $permalinks['course_base'] ? array(
				'slug'       => $permalinks['course_base'],
				'with_front' => false,
			) : false,
		);

		return $this->args;
	}


	/**
	 * Taxonomy added for course
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function register_taxonomy() {
		$permalinks = ohmylms_get_permalink_structure();

		// register category.
		$labels = array(
			'name'                       => __( 'Course Categories', 'ohmylms' ),
			'singular_name'              => __( 'Category', 'ohmylms' ),
			'menu_name'                  => __( 'Course Category', 'ohmylms' ),
			'all_items'                  => __( 'All Categories', 'ohmylms' ),
			'edit_item'                  => __( 'Edit Category', 'ohmylms' ),
			'view_item'                  => __( 'View Category', 'ohmylms' ),
			'update_item'                => __( 'Update Category', 'ohmylms' ),
			'add_new_item'               => __( 'Add A New Course Category', 'ohmylms' ),
			'new_item_name'              => __( 'New Category Name', 'ohmylms' ),
			'parent_item'                => __( 'Parent Category', 'ohmylms' ),
			'parent_item_colon'          => __( 'Parent Category:', 'ohmylms' ),
			'search_items'               => __( 'Search Categories', 'ohmylms' ),
			'popular_items'              => __( 'Popular Categories', 'ohmylms' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'ohmylms' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'ohmylms' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'ohmylms' ),
			'not_found'                  => __( 'No categories found', 'ohmylms' ),
			'back_to_items'              => __( 'Back to categories', 'ohmylms' ),
		);

		$args = array(
			'hierarchical' => true,
			'label'        => __( 'Categories', 'ohmylms' ),
			'labels'       => $labels,
			'show_in_rest' => true,
			'show_ui'      => false,
			'query_var'    => true,
			'rewrite'      => array(
				'slug'         => 'course-category',
				'with_front'   => false,
				'hierarchical' => true,
			),
		);
		register_taxonomy(
			'course_category',
			array( $this->post_type ),
			$args
		);

		// register tags.
		$labels = array(
			'name'                       => __( 'Course Tags', 'ohmylms' ),
			'singular_name'              => __( 'Tag', 'ohmylms' ),
			'menu_name'                  => __( 'Course Tag', 'ohmylms' ),
			'all_items'                  => __( 'All Tags', 'ohmylms' ),
			'edit_item'                  => __( 'Edit Tag', 'ohmylms' ),
			'view_item'                  => __( 'View Tag', 'ohmylms' ),
			'update_item'                => __( 'Update Tag', 'ohmylms' ),
			'add_new_item'               => __( 'Add A New Course Tag', 'ohmylms' ),
			'new_item_name'              => __( 'New Tag Name', 'ohmylms' ),
			'parent_item'                => __( 'Parent Tag', 'ohmylms' ),
			'parent_item_colon'          => __( 'Parent Tag:', 'ohmylms' ),
			'search_items'               => __( 'Search Tags', 'ohmylms' ),
			'popular_items'              => __( 'Popular Tags', 'ohmylms' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'ohmylms' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'ohmylms' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'ohmylms' ),
			'not_found'                  => __( 'No tags found', 'ohmylms' ),
			'back_to_items'              => __( 'Back to tags', 'ohmylms' ),
		);

		$args = array(
			'hierarchical' => false,
			'label'        => __( 'Tags', 'ohmylms' ),
			'labels'       => $labels,
			'show_in_rest' => true,
			'show_ui'      => false,
			'query_var'    => true,
			'rewrite'      => array(
				'slug'         => 'course-tag',
				'with_front'   => false,
				'hierarchical' => true,
			),
		);

		register_taxonomy(
			'course_tag',
			array( $this->post_type ),
			$args
		);
	}

	/**
	 * Course details contents
	 *
	 * @param mixed $post Post.
	 * @return void
	 * @since 1.0.0
	 */
	public function course_details_meta_box_callback( $post ) {

		$course_duration      = get_post_meta( $post->ID, 'ohmylms_course_duration', true );
		$course_price         = get_post_meta( $post->ID, 'ohmylms_course_price', true );
		$sale_price           = get_post_meta( $post->ID, 'ohmylms_course_sale_price', true );
		$max_students_allowed = get_post_meta( $post->ID, 'ohmylms_course_max_student_allowed', true );
		$max_retake_allowed   = get_post_meta( $post->ID, 'ohmylms_course_max_retake_allowed', true );
		$passing_grade        = get_post_meta( $post->ID, 'ohmylms_course_passing_grade', true );

		// Nonce field for security.
		wp_nonce_field( 'ohmylms_course_nonce', 'nonce' );
		?>
		<div class="ohmylms-course-meta-box">
			<h3><?php esc_html_e( 'General Settings', 'ohmylms' ); ?></h3>
			<ul class="course-meta-list">
				<li>
					<label for="ohmylms_course_duration"><?php esc_html_e( 'Duration:', 'ohmylms' ); ?></label>
					<input type="text" id="ohmylms_course_duration" name="ohmylms_course_duration" value="<?php echo esc_attr( $course_duration ); ?>" />
				</li>
				<li>
					<label for="ohmylms_course_price"><?php esc_html_e( 'Course Price:', 'ohmylms' ); ?></label>
					<input type="text" id="ohmylms_course_price" name="ohmylms_course_price" value="<?php echo esc_attr( $course_price ); ?>" />
				</li>
				<li>
					<label for="ohmylms_course_sale_price"><?php esc_html_e( 'Sale Price:', 'ohmylms' ); ?></label>
					<input type="text" id="ohmylms_course_sale_price" name="ohmylms_course_sale_price" value="<?php echo esc_attr( $sale_price ); ?>" />
				</li>
				<li>
					<label for="ohmylms_course_max_student_allowed"><?php esc_html_e( 'Max Students Allowed:', 'ohmylms' ); ?></label>
					<input type="text" id="ohmylms_course_max_student_allowed" name="ohmylms_course_max_student_allowed" value="<?php echo esc_attr( $max_students_allowed ); ?>" />
				</li>
				<li>
					<label for="ohmylms_course_max_retake_allowed"><?php esc_html_e( 'Max Retake Allowed:', 'ohmylms' ); ?></label>
					<input type="text" id="ohmylms_course_max_retake_allowed" name="ohmylms_course_max_retake_allowed" value="<?php echo esc_attr( $max_retake_allowed ); ?>" />
				</li>
				<li>
					<label for="ohmylms_course_passing_grade"><?php esc_html_e( 'Passing Grade (%):', 'ohmylms' ); ?></label>
					<input type="text" id="ohmylms_course_passing_grade" name="ohmylms_course_passing_grade" value="<?php echo esc_attr( $passing_grade ); ?>" />
				</li>
			</ul>
		</div>

		<div class="ohmylms-loader" ></div>
		<button id="save-course-data" class="button button-primary"><?php esc_html_e( 'Save Course Data', 'ohmylms' ); ?></button>
		<div class="ohmylms-notice"></div>

		<style>
			.ohmylms-course-meta-box {
				margin-bottom: 20px;
			}

			.course-meta-list {
				list-style: none;
				padding: 0;
			}

			.course-meta-list li {
				margin-bottom: 10px;
			}

			.course-meta-list label {
				display: inline-block;
				width: 150px;
				font-weight: bold;
			}

			.course-meta-list input[type="text"] {
				width: 300px;
				padding: 5px;
				border: 1px solid #ccc;
			}

			.ohmylms-loader {
				display: none;
				text-align: center;
				margin-top: 10px;
			}

			.ohmylms-loader .spinner {
				display: inline-block;
				float: left;
				width: 20px;
				height: 20px;
				vertical-align: middle;
				border: 3px solid rgba(0, 0, 0, 0.1);
				border-left-color: #0073aa;
				border-radius: 50%;
				animation: spin 0.8s linear infinite;
			}

			@keyframes spin {
				to {
					transform: rotate(360deg);
				}
			}

			.ohmylms-notice {
				display: none;
				margin-top: 10px;
			}

			.ohmylms-notice .notice {
				padding: 10px;
				border-radius: 3px;
			}

			.notice-success {
				background-color: #d4edda;
				border-color: #c3e6cb;
				color: #155724;
			}

			.notice-error {
				background-color: #f8d7da;
				border-color: #f5c6cb;
				color: #721c24;
			}
		</style>
		<?php
	}
}


CoursePostType::instance();
