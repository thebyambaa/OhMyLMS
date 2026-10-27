<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.

namespace OhMyLMS\PostTypes;

use OhMyLMS\Abstracts\PostType;
use OhMyLMS\Membership\MembershipHelper;

defined( 'ABSPATH' ) || exit();

/**
 * Plugin membership post type implementation.
 */
class MembershipPostType extends PostType {

	/**
	 * Instance.
	 *
	 * @var null
	 */
	protected static $_instance = null;

	/**
	 * Initialize post type
	 *
	 * @return self|null
	 * @since 1.0.0
	 */
	public static function instance() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}



	/**
	 * Initialize Membership post type.
	 */
	public function __construct() {
		$this->post_type = 'ohmylms-membership';
		parent::__construct();

		// add_action( 'add_meta_boxes', [$this, 'membership_add_plan_meta_box'] );.
	}


	/**
	 * Get arguments of CPT - ohmylms-membership
	 *
	 * @return array|void
	 * @since 1.0.0
	 */
	public function get_args() {

		$labels = array(
			'name'                  => _x( 'Membership', 'Post Type General Name', 'ohmylms' ),
			'singular_name'         => _x( 'Membership', 'Post Type Singular Name', 'ohmylms' ),
			'menu_name'             => __( 'Memberships', 'ohmylms' ),
			'name_admin_bar'        => __( 'Membership', 'ohmylms' ),
			'archives'              => __( 'Membership Archives', 'ohmylms' ),
			'attributes'            => __( 'Membership Attributes', 'ohmylms' ),
			'parent_item_colon'     => __( 'Parent Membership:', 'ohmylms' ),
			'all_items'             => __( 'All Memberships', 'ohmylms' ),
			'add_new_item'          => __( 'Add New Membership', 'ohmylms' ),
			'add_new'               => __( 'Add New', 'ohmylms' ),
			'new_item'              => __( 'New Membership', 'ohmylms' ),
			'edit_item'             => __( 'Edit Membership', 'ohmylms' ),
			'update_item'           => __( 'Update Membership', 'ohmylms' ),
			'view_item'             => __( 'View Membership', 'ohmylms' ),
			'view_items'            => __( 'View Membership', 'ohmylms' ),
			'search_items'          => __( 'Search Membership', 'ohmylms' ),
			'not_found'             => __( 'Not found', 'ohmylms' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'ohmylms' ),
			'featured_image'        => __( 'Featured Image', 'ohmylms' ),
			'set_featured_image'    => __( 'Set featured image', 'ohmylms' ),
			'remove_featured_image' => __( 'Remove featured image', 'ohmylms' ),
			'use_featured_image'    => __( 'Use as featured image', 'ohmylms' ),
			'insert_into_item'      => __( 'Insert in Membership', 'ohmylms' ),
			'uploaded_to_this_item' => __( 'Uploaded to this Membership', 'ohmylms' ),
			'items_list'            => __( 'Memberships list', 'ohmylms' ),
			'items_list_navigation' => __( 'Memberships list navigation', 'ohmylms' ),
			'filter_items_list'     => __( 'Filter Memberships list', 'ohmylms' ),
		);
		// Get permalink structure of OhMyLMS courses.
		$permalinks = ohmylms_get_permalink_structure();

		// Get course archive page ID and set archive page.
		$membership_page_id = ohmylms_get_page_id( 'membership' );
		$has_archive        = $membership_page_id && get_post( $membership_page_id ) ? urldecode( get_page_uri( $membership_page_id ) ) : 'membership';

		// CPT supports.
		$supports = array( 'title', 'editor', 'excerpt' );

		$this->args = array(
			'labels'             => $labels,
			'public'             => false,
			'query_var'          => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'has_archive'        => $has_archive,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'show_in_admin_bar'  => false,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'show_in_menu'       => false,
			'supports'           => $supports,
			'hierarchical'       => false,
			'rewrite'            => $permalinks['membership_base'] ? array(
				'slug'       => '/' . $permalinks['membership_base'],
				'with_front' => false,
				'feeds'      => true,
			) : false,
		);

		return $this->args;
	}

	/**
	 * Membership plan meta box initialization
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function membership_add_plan_meta_box() {
		add_meta_box(
			'membership_meta_box',
			__( 'Membership Details', 'ohmylms' ),
			array( $this, 'membership_meta_box_callback' ),
			'ohmylms-membership',
			'normal',
			'high'
		);
	}

	/**
	 * Membership details metabox callback
	 *
	 * @param mixed $post Post.
	 * @return void
	 * @since 1.0.0
	 */
	public function membership_meta_box_callback( $post ) {

		wp_nonce_field( 'membership_save_meta_box_data', 'membership_meta_box_nonce' );

		// == implement plan details == //
		$membership_plans = get_post_meta( $post->ID, 'ohmylms_membership_plans', true );
		$membership_plans = is_array( $membership_plans ) ? $membership_plans : array();

		$courses = get_posts(
			array(
				'post_type'   => 'ohmylms-course',
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);

		$subscription_options = MembershipHelper::subscription_options();

		?>
		<h2><?php esc_html_e( 'Membership Plans:', 'ohmylms' ); ?></h2>
		<div id="membership_plans_container">
			<?php foreach ( $membership_plans as $index => $plan ) : ?>
				<div class="membership_plan">
					<input type="hidden" id="membership_plan_id_<?php echo esc_attr( $index ); ?>" name="membership_plan_id[]" value="<?php echo esc_attr( $plan['plan_id'] ); ?>" />
					<p>
						<label for="membership_plan_title_<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Plan Title', 'ohmylms' ); ?></label>
						<input type="text" id="membership_plan_title_<?php echo esc_attr( $index ); ?>" name="membership_plan_title[]" value="<?php echo esc_attr( $plan['title'] ); ?>" />
					</p>
					<p>
						<label for="membership_plan_price_<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Plan Price', 'ohmylms' ); ?></label>
						<input type="number" id="membership_plan_price_<?php echo esc_attr( $index ); ?>" name="membership_plan_price[]" value="<?php echo esc_attr( $plan['price'] ); ?>" step="0.01" />
					</p>
					<p>
						<label for="subscription_type_<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Subscription Type', 'ohmylms' ); ?></label>
						<select id="subscription_type_<?php echo esc_attr( $index ); ?>" name="subscription_type[<?php echo esc_attr( $index ); ?>]" class="subscription_type">
							<?php
							foreach ( $subscription_options as $subscription_key => $subscription ) {
								?>
									<option value="<?php echo esc_attr( $subscription_key ); ?>" <?php selected( $plan['subscription'], $subscription_key ); ?>><?php echo esc_html( $subscription ); ?></option>
									<?php
							}
							?>
						</select>
					</p>
					<p>
						<label for="connected_courses_<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Connected Courses', 'ohmylms' ); ?></label>
						<select id="connected_courses_<?php echo esc_attr( $index ); ?>" name="connected_courses[<?php echo esc_attr( $index ); ?>][]" multiple="multiple" class="connected_courses" style="width: 100%;">
							<?php
							$connected_courses = isset( $plan['courses'] ) ? $plan['courses'] : array();
							foreach ( $courses as $course ) {
								echo '<option value="' . esc_attr( $course->ID ) . '"' . ( in_array( $course->ID, $connected_courses ) ? ' selected="selected"' : '' ) . '>' . esc_html( $course->post_title ) . '</option>';
							}
							?>
						</select>
					</p>
					<button type="button" class="button button-secondary remove_membership_plan"><?php esc_html_e( 'Remove Plan', 'ohmylms' ); ?></button>
					<hr>
				</div>
			<?php endforeach; ?>
		</div>
		<p>
			<button type="button" id="add_membership_plan" class="button"><?php esc_html_e( 'Add Another Plan', 'ohmylms' ); ?></button>
		</p>
		<div class="ohmylms-loader" ></div>
		<p>
			<button type="button" id="membership_save_button" class="button button-primary"><?php esc_html_e( 'Save Membership Details', 'ohmylms' ); ?></button>
		</p>
		<div class="ohmylms-notice"></div>

		<style>
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


MembershipPostType::instance();
