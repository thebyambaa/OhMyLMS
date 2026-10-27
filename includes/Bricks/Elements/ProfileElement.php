<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * OhMyLMS Profile Element for Bricks Builder
 *
 * Styling is handled entirely by ShortCodeProfile output styles,
 * generated via shortcode attrs passed from Bricks controls.
 *
 * @package OhMyLMS\Bricks\Elements
 * @since 1.0.0
 */

namespace OhMyLMS\Bricks\Elements;

use OhMyLMS\Shortcodes\ShortCodeProfile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ProfileElement class
 */
class ProfileElement extends \Bricks\Element {

	/**
	 * Category.
	 *
	 * @var mixed
	 */
	public $category = 'ohmylms';
	/**
	 * Name.
	 *
	 * @var mixed
	 */
	public $name = 'ohmylms-profile';
	/**
	 * Icon.
	 *
	 * @var mixed
	 */
	public $icon = 'ti-user';
	/**
	 * Keywords.
	 *
	 * @var mixed
	 */
	public $keywords = array( 'profile', 'student', 'creator', 'lms' );
	/**
	 * Scripts.
	 *
	 * @var mixed
	 */
	public $scripts = array( 'ohmylms-frontend' );
	/**
	 * Styles.
	 *
	 * @var mixed
	 */
	public $styles = array( 'ohmylms-frontend' );

	/**
	 * Get label for Profile element.
	 */
	public function get_label() {
		return esc_html__( 'OhMyLMS Profile', 'ohmylms' );
	}

	/**
	 * Set controls for Profile element.
	 */
	public function set_controls() {
		$this->controls       = array();
		$this->control_groups = array();
		$this->set_content_controls();
		$this->set_style_controls();
	}

	/**
	 * Set content controls for Profile element.
	 */
	private function set_content_controls() {
		$this->controls['show_header'] = array(
			'tab'     => 'content',
			'label'   => esc_html__( 'Show Header', 'ohmylms' ),
			'type'    => 'checkbox',
			'default' => true,
		);
	}

	/**
	 * Set style controls for Profile element.
	 */
	private function set_style_controls() {
		$this->control_groups['header_style']              = array(
			'title'    => esc_html__( 'Header Style', 'ohmylms' ),
			'tab'      => 'style',
			'required' => array( array( 'show_header', '=', true ) ),
		);
		$this->control_groups['user_menu_style']           = array(
			'title' => esc_html__( 'User Menu Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['section_style']             = array(
			'title' => esc_html__( 'Section Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['wrapper_style']             = array(
			'title' => esc_html__( 'Profile Wrapper Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['sidebar_items_style']       = array(
			'title' => esc_html__( 'Sidebar Items Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['sidebar_content_style']     = array(
			'title' => esc_html__( 'Sidebar Content Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['profile_info_style']        = array(
			'title' => esc_html__( 'Profile Info Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['profile_edit_button_style'] = array(
			'title' => esc_html__( 'Profile Edit Button Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['basic_info_style']          = array(
			'title' => esc_html__( 'Basic Info Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['input_fields_style']        = array(
			'title' => esc_html__( 'Input Fields Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['labels_style']              = array(
			'title' => esc_html__( 'Labels Style', 'ohmylms' ),
			'tab'   => 'style',
		);
		$this->control_groups['save_button_style']         = array(
			'title' => esc_html__( 'Save Button Style', 'ohmylms' ),
			'tab'   => 'style',
		);

		$this->controls['header_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'header_style',
			'label'   => esc_html__( 'Header Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000D2C' ),
		);

		$this->controls['user_menu_color']            = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000000' ),
		);
		$this->controls['user_menu_bg_color']         = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['user_menu_font_size']        = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['user_menu_font_weight']      = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 400,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['user_menu_hover_color']      = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000000' ),
		);
		$this->controls['user_menu_hover_bg_color']   = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#F5F5F5' ),
		);
		$this->controls['user_menu_icon_color']       = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Icon Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#000000' ),
		);
		$this->controls['user_menu_icon_hover_color'] = array(
			'tab'     => 'style',
			'group'   => 'user_menu_style',
			'label'   => esc_html__( 'Icon Hover Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);

		$this->controls['section_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'section_style',
			'label'   => esc_html__( 'Section Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#F9FAFD' ),
		);

		$this->controls['wrapper_border']   = array(
			'tab'     => 'style',
			'group'   => 'wrapper_style',
			'label'   => esc_html__( 'Border Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#EBECEF' ),
		);
		$this->controls['wrapper_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'wrapper_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#F8F8F8' ),
		);
		$this->controls['wrapper_shadow']   = array(
			'tab'     => 'style',
			'group'   => 'wrapper_style',
			'label'   => esc_html__( 'Box Shadow', 'ohmylms' ),
			'type'    => 'text',
			'default' => '0px 2px 8px 0px #ECECEC',
		);

		$this->controls['sidebar_item_color']              = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Item Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['sidebar_item_font_size']          = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Item Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['sidebar_item_font_weight']        = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Item Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 400,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['sidebar_item_bg_color']           = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Item Background Color', 'ohmylms' ),
			'type'    => 'text',
			'default' => 'transparent',
		);
		$this->controls['sidebar_item_active_color']       = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Active Item Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);
		$this->controls['sidebar_item_active_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Active Item Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['sidebar_item_active_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Active Item Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 400,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['sidebar_item_active_bg_color']    = array(
			'tab'     => 'style',
			'group'   => 'sidebar_items_style',
			'label'   => esc_html__( 'Active Item Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);

		$this->controls['sidebar_content_bg_color']          = array(
			'tab'     => 'style',
			'group'   => 'sidebar_content_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['sidebar_content_shadow']            = array(
			'tab'     => 'style',
			'group'   => 'sidebar_content_style',
			'label'   => esc_html__( 'Box Shadow', 'ohmylms' ),
			'type'    => 'text',
			'default' => '0px 1px 2px 0px #DBDDE1',
		);
		$this->controls['sidebar_content_padding']           = array(
			'tab'     => 'style',
			'group'   => 'sidebar_content_style',
			'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 40,
			'min'     => 0,
			'max'     => 200,
		);
		$this->controls['sidebar_content_title_color']       = array(
			'tab'     => 'style',
			'group'   => 'sidebar_content_style',
			'label'   => esc_html__( 'Title Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['sidebar_content_title_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'sidebar_content_style',
			'label'   => esc_html__( 'Title Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 24,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['sidebar_content_title_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'sidebar_content_style',
			'label'   => esc_html__( 'Title Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 600,
			'min'     => 100,
			'max'     => 900,
		);

		$this->controls['profile_name_color']       = array(
			'tab'     => 'style',
			'group'   => 'profile_info_style',
			'label'   => esc_html__( 'Name Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['profile_name_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'profile_info_style',
			'label'   => esc_html__( 'Name Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 20,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['profile_name_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'profile_info_style',
			'label'   => esc_html__( 'Name Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 700,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['profile_bio_color']        = array(
			'tab'     => 'style',
			'group'   => 'profile_info_style',
			'label'   => esc_html__( 'Bio Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#52525B' ),
		);
		$this->controls['profile_bio_font_size']    = array(
			'tab'     => 'style',
			'group'   => 'profile_info_style',
			'label'   => esc_html__( 'Bio Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 15,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['profile_bio_font_weight']  = array(
			'tab'     => 'style',
			'group'   => 'profile_info_style',
			'label'   => esc_html__( 'Bio Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 400,
			'min'     => 100,
			'max'     => 900,
		);

		$this->controls['profile_edit_color']          = array(
			'tab'     => 'style',
			'group'   => 'profile_edit_button_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['profile_edit_bg_color']       = array(
			'tab'     => 'style',
			'group'   => 'profile_edit_button_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['profile_edit_border']         = array(
			'tab'     => 'style',
			'group'   => 'profile_edit_button_style',
			'label'   => esc_html__( 'Border Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#EBEBEF' ),
		);
		$this->controls['profile_edit_font_size']      = array(
			'tab'     => 'style',
			'group'   => 'profile_edit_button_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['profile_edit_font_weight']    = array(
			'tab'     => 'style',
			'group'   => 'profile_edit_button_style',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 500,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['profile_edit_hover_color']    = array(
			'tab'     => 'style',
			'group'   => 'profile_edit_button_style',
			'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['profile_edit_hover_bg_color'] = array(
			'tab'     => 'style',
			'group'   => 'profile_edit_button_style',
			'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#f6f6f6' ),
		);

		$this->controls['basic_info_bg_color']          = array(
			'tab'     => 'style',
			'group'   => 'basic_info_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['basic_info_shadow']            = array(
			'tab'     => 'style',
			'group'   => 'basic_info_style',
			'label'   => esc_html__( 'Box Shadow', 'ohmylms' ),
			'type'    => 'text',
			'default' => '0px 1px 4px 0px #D3D6DD',
		);
		$this->controls['basic_info_padding']           = array(
			'tab'     => 'style',
			'group'   => 'basic_info_style',
			'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 21,
			'min'     => 0,
			'max'     => 200,
		);
		$this->controls['basic_info_title_color']       = array(
			'tab'     => 'style',
			'group'   => 'basic_info_style',
			'label'   => esc_html__( 'Title Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['basic_info_title_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'basic_info_style',
			'label'   => esc_html__( 'Title Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 18,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['basic_info_title_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'basic_info_style',
			'label'   => esc_html__( 'Title Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 600,
			'min'     => 100,
			'max'     => 900,
		);

		$this->controls['input_bg_color']          = array(
			'tab'     => 'style',
			'group'   => 'input_fields_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['input_border']            = array(
			'tab'     => 'style',
			'group'   => 'input_fields_style',
			'label'   => esc_html__( 'Border Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#c8d2e980' ),
		);
		$this->controls['input_text_color']        = array(
			'tab'     => 'style',
			'group'   => 'input_fields_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#52525B' ),
		);
		$this->controls['input_placeholder_color'] = array(
			'tab'     => 'style',
			'group'   => 'input_fields_style',
			'label'   => esc_html__( 'Placeholder Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#7A8B9A' ),
		);
		$this->controls['input_font_size']         = array(
			'tab'     => 'style',
			'group'   => 'input_fields_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['input_padding']           = array(
			'tab'     => 'style',
			'group'   => 'input_fields_style',
			'label'   => esc_html__( 'Padding (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 13,
			'min'     => 0,
			'max'     => 200,
		);
		$this->controls['input_border_radius']     = array(
			'tab'     => 'style',
			'group'   => 'input_fields_style',
			'label'   => esc_html__( 'Border Radius (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 8,
			'min'     => 0,
			'max'     => 200,
		);

		$this->controls['label_color']       = array(
			'tab'     => 'style',
			'group'   => 'labels_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#1E1E1E' ),
		);
		$this->controls['label_font_size']   = array(
			'tab'     => 'style',
			'group'   => 'labels_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 14,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['label_font_weight'] = array(
			'tab'     => 'style',
			'group'   => 'labels_style',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 500,
			'min'     => 100,
			'max'     => 900,
		);

		$this->controls['button_bg_color']         = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);
		$this->controls['button_text_color']       = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#FFFFFF' ),
		);
		$this->controls['button_font_size']        = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Font Size (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 15,
			'min'     => 1,
			'max'     => 200,
		);
		$this->controls['button_font_weight']      = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Font Weight', 'ohmylms' ),
			'type'    => 'number',
			'default' => 500,
			'min'     => 100,
			'max'     => 900,
		);
		$this->controls['button_border_radius']    = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Border Radius (px)', 'ohmylms' ),
			'type'    => 'number',
			'default' => 8,
			'min'     => 0,
			'max'     => 200,
		);
		$this->controls['button_hover_bg_color']   = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Hover Background Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'rgb' => 'transparent' ),
		);
		$this->controls['button_hover_text_color'] = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Hover Text Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);
		$this->controls['button_hover_border']     = array(
			'tab'     => 'style',
			'group'   => 'save_button_style',
			'label'   => esc_html__( 'Hover Border Color', 'ohmylms' ),
			'type'    => 'color',
			'default' => array( 'hex' => '#4361EE' ),
		);
	}

	/**
	 * Is bricks edit mode for Profile element.
	 */
	private function is_bricks_edit_mode() {
		if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
			return true;
		}
		if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
			return true;
		}
		if ( function_exists( 'bricks_is_rest_call' ) && bricks_is_rest_call() ) {
			return true;
		}
		return false;
	}

	/**
	 * Extract color for Profile element.
	 *
	 * @param mixed $color Color.
	 */
	private function extract_color( $color ) {
		if ( is_array( $color ) ) {
			if ( ! empty( $color['hex'] ) ) {
				return $color['hex'];
			}
			if ( ! empty( $color['rgb'] ) ) {
				return $color['rgb'];
			}
			return '';
		}
		return is_string( $color ) ? $color : '';
	}

	/**
	 * Build shortcode attrs for Profile element.
	 *
	 * @param mixed $settings Configuration settings.
	 */
	private function build_shortcode_attrs( $settings ) {
		$attrs = array(
			'show_header' => ( isset( $settings['show_header'] ) && true === $settings['show_header'] ) ? 'yes' : 'no',
		);

		$color_keys = array(
			'header_bg_color',
			'user_menu_color',
			'user_menu_bg_color',
			'user_menu_hover_color',
			'user_menu_hover_bg_color',
			'user_menu_icon_color',
			'user_menu_icon_hover_color',
			'section_bg_color',
			'wrapper_border',
			'wrapper_bg_color',
			'sidebar_item_color',
			'sidebar_item_active_color',
			'sidebar_item_active_bg_color',
			'sidebar_content_bg_color',
			'sidebar_content_title_color',
			'profile_name_color',
			'profile_bio_color',
			'profile_edit_color',
			'profile_edit_bg_color',
			'profile_edit_border',
			'profile_edit_hover_color',
			'profile_edit_hover_bg_color',
			'basic_info_bg_color',
			'basic_info_title_color',
			'input_bg_color',
			'input_border',
			'input_text_color',
			'input_placeholder_color',
			'label_color',
			'button_bg_color',
			'button_text_color',
			'button_hover_text_color',
		);

		foreach ( $color_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$value = $this->extract_color( $settings[ $key ] );
				if ( '' !== $value ) {
					$attrs[ $key ] = $value;
				}
			}
		}

		$text_keys = array(
			'wrapper_shadow',
			'sidebar_item_bg_color',
			'sidebar_content_shadow',
			'basic_info_shadow',
			'button_hover_bg_color',
			'button_hover_border',
		);

		foreach ( $text_keys as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$attrs[ $key ] = $settings[ $key ];
			}
		}

		$number_keys = array(
			'user_menu_font_size',
			'user_menu_font_weight',
			'sidebar_item_font_size',
			'sidebar_item_font_weight',
			'sidebar_item_active_font_size',
			'sidebar_item_active_font_weight',
			'sidebar_content_padding',
			'sidebar_content_title_font_size',
			'sidebar_content_title_font_weight',
			'profile_name_font_size',
			'profile_name_font_weight',
			'profile_bio_font_size',
			'profile_bio_font_weight',
			'profile_edit_font_size',
			'profile_edit_font_weight',
			'basic_info_padding',
			'basic_info_title_font_size',
			'basic_info_title_font_weight',
			'input_font_size',
			'input_padding',
			'input_border_radius',
			'label_font_size',
			'label_font_weight',
			'button_font_size',
			'button_font_weight',
			'button_border_radius',
		);

		foreach ( $number_keys as $key ) {
			if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
				$attrs[ $key ] = $settings[ $key ];
			}
		}

		// Ensure updated color controls always have Gutenberg-equivalent defaults.
		if ( empty( $attrs['input_border'] ) ) {
			$attrs['input_border'] = '#c8d2e980';
		}
		if ( empty( $attrs['button_hover_bg_color'] ) ) {
			$attrs['button_hover_bg_color'] = 'transparent';
		}
		if ( empty( $attrs['button_hover_border'] ) ) {
			$attrs['button_hover_border'] = '#4361EE';
		}

		return $attrs;
	}

	/**
	 * Render for Profile element.
	 */
	public function render() {
		$settings        = $this->settings;
		$is_edit_mode    = $this->is_bricks_edit_mode();
		$shortcode_attrs = $this->build_shortcode_attrs( $settings );

		if ( $is_edit_mode ) {
			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
			add_filter( 'ohmylms_bricks_preview_mode', '__return_true' );
		}

		echo '<div class="ohmylms-page ohmylms">';

		try {
			ob_start();
			ShortCodeProfile::output( $shortcode_attrs );
			$output = ob_get_clean();

			if ( '' !== $output ) {
				echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( $is_edit_mode ) {
				echo '<div class="ohmylms-bricks-preview-notice">';
				echo '<p>' . esc_html__( 'OhMyLMS Profile — preview requires a logged-in student account.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
		} catch ( \Throwable $e ) {
			if ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			if ( $is_edit_mode ) {
				echo '<div class="ohmylms-bricks-preview-notice">';
				echo '<p>' . esc_html__( 'OhMyLMS Profile — render error.', 'ohmylms' ) . '</p>';
				echo '</div>';
			}
		} finally {
			if ( $is_edit_mode ) {
				remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
				remove_filter( 'ohmylms_bricks_preview_mode', '__return_true' );
			}
		}

		echo '</div>';
	}
}
