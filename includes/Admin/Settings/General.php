<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * General settings class.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Admin\Settings;

use OhMyLMS\Abstracts\Settings;

/**
 * General settings class.
 *
 * Handles general settings for the OhMyLMS.
 *
 * @since 1.0.0
 */
class General extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'general';

	/**
	 * Constructor.
	 *
	 * Initializes the general settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'general';
		$this->label = __( 'General', 'ohmylms' );
	}

	/**
	 * Get the general settings.
	 *
	 * @return array The settings array.
	 *
	 * @since 1.0.0
	 */
	public function get_settings() {

		$settings = array(
			array(
				'id'    => 'ohmylms_navigation_links_section',
				'type'  => 'title',
				'title' => __( 'Navigation Links', 'ohmylms' ),
				'desc'  => __( 'Configure navigation links for student dashboard, profile, and courses pages. These links will be used globally across all blocks.', 'ohmylms' ),
			),
			array(
				'id'        => 'ohmylms_nav_my_profile_url',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' => array(),
				),
				'meta_data' => array(
					'label'       => __( 'My Profile Page', 'ohmylms' ),
					'description' => __( 'Select the page for My Profile navigation link. Leave empty to use default.', 'ohmylms' ),
				),
			),
			array(
				'id'        => 'ohmylms_nav_my_courses_url',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' => array(),
				),
				'meta_data' => array(
					'label'       => __( 'My Courses Page', 'ohmylms' ),
					'description' => __( 'Select the page for My Courses navigation link. Leave empty to use default.', 'ohmylms' ),
				),
			),
			array(
				'id'   => 'ohmylms_navigation_links_section',
				'type' => 'sectionend',
			),
			array(
				'id'        => 'ohmylms_course_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
							ohmylms_get_page_id( 'myaccount' ),
						),
				),
				'meta_data' => $this->get_page_title( 'course' ),
			),
			array(
				'id'        => 'ohmylms_profile_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'profile' ),
			),
			array(
				'id'        => 'ohmylms_student_dashboard_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => ohmylms_get_page_id( 'student_dashboard' ),
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'student_dashboard' ),
			),
			array(
				'id'        => 'ohmylms_student_courses_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => ohmylms_get_page_id( 'student_courses' ),
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'student_courses' ),
			),
			array(
				'id'        => 'ohmylms_student_profile_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => ohmylms_get_page_id( 'student_profile' ),
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'student_profile' ),
			),
			array(
				'id'        => 'ohmylms_checkout_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'course' ),
							ohmylms_get_page_id( 'myaccount' ),
						),
				),
				'meta_data' => $this->get_page_title( 'checkout' ),
			),
			array(
				'id'        => 'ohmylms_thank_you_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'course' ),
							ohmylms_get_page_id( 'myaccount' ),
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'thank_you' ),
			),
			array(
				'id'        => 'ohmylms_terms_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'terms' ),
			),
			array(
				'id'        => 'ohmylms_privacy_policy_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'privacy_policy' ),
			),
			array(
				'id'        => 'ohmylms_registration_page_id',
				'type'      => 'single_select_page_with_search',
				'default'   => '',
				'args'      => array(
					'exclude' =>
						array(
							ohmylms_get_page_id( 'checkout' ),
						),
				),
				'meta_data' => $this->get_page_title( 'registration' ),
			),
		);

		return $settings;
	}


	/**
	 * Get page title safely.
	 *
	 * @param string $page_type The page type.
	 * @return string The page title or empty string.
	 */
	private function get_page_title( $page_type ) {
		$page_id = ohmylms_get_page_id( $page_type );
		if ( ! $page_id ) {
			return '';
		}
		$page = get_post( $page_id );
		return $page && 'page' === $page->post_type && 'publish' === $page->post_status ? $page->post_title : '';
	}
}
