<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Profile Block
 *
 * Gutenberg block for OhMyLMS student profile functionality
 *
 * @package OhMyLMS\Blocks
 * @since 1.2.5
 */

namespace OhMyLMS\Blocks;

use OhMyLMS\Shortcodes\ShortCodeProfile;

defined( 'ABSPATH' ) || exit;

/**
 * ProfileGutenbergBlock class
 */
class ProfileGutenbergBlock {

	/**
	 * Block name
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'ohmylms/profile';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_block();
	}

	/**
	 * Register the block
	 *
	 * @return void
	 */
	private function register_block() {
		register_block_type(
			self::BLOCK_NAME,
			array(
				'attributes'      => $this->get_block_attributes(),
				'render_callback' => array( $this, 'render_block' ),
				'editor_script'   => 'ohmylms-blocks-editor',
				'editor_style'    => 'ohmylms-blocks-editor',
				'style'           => 'ohmylms-blocks-frontend',
			)
		);
	}

	/**
	 * Get block attributes
	 *
	 * @return array
	 */
	private function get_block_attributes() {
		return array(
			'align'                         => array(
				'type'    => 'string',
				'default' => 'full',
			),
			// Header visibility and configuration.
			'showHeader'                    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'myProfileUrl'                  => array(
				'type'    => 'string',
				'default' => '',
			),
			'myCoursesUrl'                  => array(
				'type'    => 'string',
				'default' => '',
			),
			// Header styling.
			'headerBgColor'                 => array(
				'type'    => 'string',
				'default' => '#000D2C',
			),
			// User menu styling.
			'userMenuColor'                 => array(
				'type'    => 'string',
				'default' => '#000000',
			),
			'userMenuBgColor'               => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'userMenuFontSize'              => array(
				'type'    => 'number',
				'default' => 14,
			),
			'userMenuFontWeight'            => array(
				'type'    => 'number',
				'default' => 400,
			),
			// User menu hover styling.
			'userMenuHoverColor'            => array(
				'type'    => 'string',
				'default' => '#000000',
			),
			'userMenuHoverBgColor'          => array(
				'type'    => 'string',
				'default' => '#F5F5F5',
			),
			// User menu icon styling.
			'userMenuIconColor'             => array(
				'type'    => 'string',
				'default' => '#000000',
			),
			'userMenuIconHoverColor'        => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
			// Section background.
			'sectionBgColor'                => array(
				'type'    => 'string',
				'default' => '#F9FAFD',
			),
			// Profile wrapper styling.
			'wrapperBorder'                 => array(
				'type'    => 'string',
				'default' => '#EBECEF',
			),
			'wrapperBgColor'                => array(
				'type'    => 'string',
				'default' => '#F8F8F8',
			),
			'wrapperShadow'                 => array(
				'type'    => 'string',
				'default' => '0px 2px 8px 0px #ECECEC',
			),
			// Sidebar items styling.
			'sidebarItemColor'              => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'sidebarItemFontSize'           => array(
				'type'    => 'number',
				'default' => 14,
			),
			'sidebarItemFontWeight'         => array(
				'type'    => 'number',
				'default' => 400,
			),
			'sidebarItemBgColor'            => array(
				'type'    => 'string',
				'default' => 'transparent',
			),
			'sidebarItemActiveColor'        => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
			'sidebarItemActiveFontSize'     => array(
				'type'    => 'number',
				'default' => 14,
			),
			'sidebarItemActiveFontWeight'   => array(
				'type'    => 'number',
				'default' => 400,
			),
			'sidebarItemActiveBgColor'      => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			// Sidebar content styling.
			'sidebarContentBgColor'         => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'sidebarContentShadow'          => array(
				'type'    => 'string',
				'default' => '0px 1px 2px 0px #DBDDE1',
			),
			'sidebarContentPadding'         => array(
				'type'    => 'number',
				'default' => 40,
			),
			'sidebarContentTitleColor'      => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'sidebarContentTitleFontSize'   => array(
				'type'    => 'number',
				'default' => 24,
			),
			'sidebarContentTitleFontWeight' => array(
				'type'    => 'number',
				'default' => 600,
			),
			// Profile info styling.
			'profileNameColor'              => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'profileNameFontSize'           => array(
				'type'    => 'number',
				'default' => 20,
			),
			'profileNameFontWeight'         => array(
				'type'    => 'number',
				'default' => 700,
			),
			'profileBioColor'               => array(
				'type'    => 'string',
				'default' => '#52525B',
			),
			'profileBioFontSize'            => array(
				'type'    => 'number',
				'default' => 15,
			),
			'profileBioFontWeight'          => array(
				'type'    => 'number',
				'default' => 400,
			),
			// Profile edit button styling.
			'profileEditColor'              => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'profileEditBgColor'            => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'profileEditBorder'             => array(
				'type'    => 'string',
				'default' => '#EBEBEF',
			),
			'profileEditFontSize'           => array(
				'type'    => 'number',
				'default' => 14,
			),
			'profileEditFontWeight'         => array(
				'type'    => 'number',
				'default' => 500,
			),
			'profileEditHoverColor'         => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'profileEditHoverBgColor'       => array(
				'type'    => 'string',
				'default' => '#f6f6f6',
			),
			// Basic info section styling.
			'basicInfoBgColor'              => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'basicInfoShadow'               => array(
				'type'    => 'string',
				'default' => '0px 1px 4px 0px #D3D6DD',
			),
			'basicInfoPadding'              => array(
				'type'    => 'number',
				'default' => 21,
			),
			'basicInfoTitleColor'           => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'basicInfoTitleFontSize'        => array(
				'type'    => 'number',
				'default' => 18,
			),
			'basicInfoTitleFontWeight'      => array(
				'type'    => 'number',
				'default' => 600,
			),
			// Input styling.
			'inputBgColor'                  => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'inputBorder'                   => array(
				'type'    => 'string',
				'default' => '#c8d2e980',
			),
			'inputTextColor'                => array(
				'type'    => 'string',
				'default' => '#52525B',
			),
			'inputPlaceholderColor'         => array(
				'type'    => 'string',
				'default' => '#7A8B9A',
			),
			'inputFontSize'                 => array(
				'type'    => 'number',
				'default' => 14,
			),
			'inputPadding'                  => array(
				'type'    => 'number',
				'default' => 13,
			),
			'inputBorderRadius'             => array(
				'type'    => 'number',
				'default' => 8,
			),
			// Label styling.
			'labelColor'                    => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'labelFontSize'                 => array(
				'type'    => 'number',
				'default' => 14,
			),
			'labelFontWeight'               => array(
				'type'    => 'number',
				'default' => 500,
			),
			// Button styling.
			'buttonBgColor'                 => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
			'buttonTextColor'               => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'buttonFontSize'                => array(
				'type'    => 'number',
				'default' => 15,
			),
			'buttonFontWeight'              => array(
				'type'    => 'number',
				'default' => 500,
			),
			'buttonBorderRadius'            => array(
				'type'    => 'number',
				'default' => 8,
			),
			'buttonHoverBgColor'            => array(
				'type'    => 'string',
				'default' => 'transparent',
			),
			'buttonHoverTextColor'          => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
			'buttonHoverBorder'             => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
		);
	}

	/**
	 * Render the block
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content Block content.
	 * @return string
	 */
	public function render_block( $attributes, $content = '' ) {
		// Validate and sanitize attributes.
		$attributes = $this->validate_attributes( $attributes );

		// Convert block attributes to shortcode attributes.
		$shortcode_attrs = $this->convert_attributes_to_shortcode_attrs( $attributes );

		// Check if we're in the editor context (ServerSideRender).
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;

		// Start output buffering.
		ob_start();

		// Enable preview mode for Gutenberg editor to show profile even when not logged in.
		if ( $is_editor ) {
			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
		}

		// Add editor-specific styling for proper profile rendering.
		if ( $is_editor ) {
			?>
			<style>
				.wp-block-ohmylms-profile .ohmylms {
					max-width: 100% !important;
					background-color: #F9FAFD !important;
					width: 100% !important;
				}
				.wp-block-ohmylms-profile .ohmylms-dashboard {
					padding: 30px !important;
					background-color: #fff !important;
					border-radius: 12px !important;
					box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
				}
				.wp-block-ohmylms-profile .ohmylms-student-profile {
					display: flex !important;
					gap: 30px !important;
				}
				.wp-block-ohmylms-profile .ohmylms-student-profile-sidebar {
					min-width: 250px !important;
				}
				.wp-block-ohmylms-profile .ohmylms-student-profile-sidebar-content {
					flex: 1 !important;
				}
				@media (max-width: 768px) {
					.wp-block-ohmylms-profile .ohmylms-dashboard {
						padding: 20px !important;
					}
					.wp-block-ohmylms-profile .ohmylms-student-profile {
						flex-direction: column !important;
					}
				}
			</style>
			<?php
		}

		// Build wrapper attributes for frontend only.
		$wrapper_attributes = array();

		// Add alignment class for frontend (not in editor).
		if ( ! $is_editor && ! empty( $attributes['align'] ) ) {
			$wrapper_attributes['class'] = 'align' . $attributes['align'];
		}

		// Output block wrapper with alignment class only on frontend.
		if ( ! $is_editor && ! empty( $wrapper_attributes ) ) {
			// Convert wrapper attributes to string.
			$wrapper_attrs_string = '';
			foreach ( $wrapper_attributes as $key => $value ) {
				$wrapper_attrs_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
			}
			echo '<div' . $wrapper_attrs_string . '>';
		}

		// Add proper wrapper classes for consistency with frontend.
		$wrapper_classes = array( 'ohmylms' );
		if ( $is_editor ) {
			$wrapper_classes[] = 'ohmylms-page';
		}

		echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

		// Add preview notice in editor mode.
		if ( $is_editor ) {
			echo '<div class="ohmylms-gutenberg-edit-mode">';
			echo '<small>' . esc_html__( 'Gutenberg Preview Mode: This is how the student profile will appear to logged-in users.', 'ohmylms' ) . '</small>';
			echo '</div>';
		}

		// Output the profile.
		ShortCodeProfile::output( $shortcode_attrs );

		echo '</div>'; // Close ohmylms wrapper.

		// Close alignment wrapper only if it was opened (frontend only).
		if ( ! $is_editor && ! empty( $wrapper_attributes ) ) {
			echo '</div>'; // Close alignment wrapper.
		}

		// Remove preview mode filter if it was set.
		if ( $is_editor ) {
			remove_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
		}

		return ob_get_clean();
	}

	/**
	 * Convert block attributes to shortcode attributes
	 *
	 * @param array $attributes Block attributes.
	 * @return array Shortcode attributes.
	 */
	private function convert_attributes_to_shortcode_attrs( $attributes ) {
		$shortcode_attrs = array();

		// Map block attributes to shortcode attributes.
		$attribute_map = array(
			'showHeader'                    => 'show_header',
			'headerBgColor'                 => 'header_bg_color',
			'userMenuColor'                 => 'user_menu_color',
			'userMenuBgColor'               => 'user_menu_bg_color',
			'userMenuFontSize'              => 'user_menu_font_size',
			'userMenuFontWeight'            => 'user_menu_font_weight',
			'userMenuHoverColor'            => 'user_menu_hover_color',
			'userMenuHoverBgColor'          => 'user_menu_hover_bg_color',
			'userMenuIconColor'             => 'user_menu_icon_color',
			'userMenuIconHoverColor'        => 'user_menu_icon_hover_color',
			'sectionBgColor'                => 'section_bg_color',
			'wrapperBorder'                 => 'wrapper_border',
			'wrapperBgColor'                => 'wrapper_bg_color',
			'wrapperShadow'                 => 'wrapper_shadow',
			'sidebarItemColor'              => 'sidebar_item_color',
			'sidebarItemFontSize'           => 'sidebar_item_font_size',
			'sidebarItemFontWeight'         => 'sidebar_item_font_weight',
			'sidebarItemBgColor'            => 'sidebar_item_bg_color',
			'sidebarItemActiveColor'        => 'sidebar_item_active_color',
			'sidebarItemActiveFontSize'     => 'sidebar_item_active_font_size',
			'sidebarItemActiveFontWeight'   => 'sidebar_item_active_font_weight',
			'sidebarItemActiveBgColor'      => 'sidebar_item_active_bg_color',
			'sidebarContentBgColor'         => 'sidebar_content_bg_color',
			'sidebarContentShadow'          => 'sidebar_content_shadow',
			'sidebarContentPadding'         => 'sidebar_content_padding',
			'sidebarContentTitleColor'      => 'sidebar_content_title_color',
			'sidebarContentTitleFontSize'   => 'sidebar_content_title_font_size',
			'sidebarContentTitleFontWeight' => 'sidebar_content_title_font_weight',
			'profileNameColor'              => 'profile_name_color',
			'profileNameFontSize'           => 'profile_name_font_size',
			'profileNameFontWeight'         => 'profile_name_font_weight',
			'profileBioColor'               => 'profile_bio_color',
			'profileBioFontSize'            => 'profile_bio_font_size',
			'profileBioFontWeight'          => 'profile_bio_font_weight',
			'profileEditColor'              => 'profile_edit_color',
			'profileEditBgColor'            => 'profile_edit_bg_color',
			'profileEditBorder'             => 'profile_edit_border',
			'profileEditFontSize'           => 'profile_edit_font_size',
			'profileEditFontWeight'         => 'profile_edit_font_weight',
			'profileEditHoverColor'         => 'profile_edit_hover_color',
			'profileEditHoverBgColor'       => 'profile_edit_hover_bg_color',
			'basicInfoBgColor'              => 'basic_info_bg_color',
			'basicInfoShadow'               => 'basic_info_shadow',
			'basicInfoPadding'              => 'basic_info_padding',
			'basicInfoTitleColor'           => 'basic_info_title_color',
			'basicInfoTitleFontSize'        => 'basic_info_title_font_size',
			'basicInfoTitleFontWeight'      => 'basic_info_title_font_weight',
			'inputBgColor'                  => 'input_bg_color',
			'inputBorder'                   => 'input_border',
			'inputTextColor'                => 'input_text_color',
			'inputPlaceholderColor'         => 'input_placeholder_color',
			'inputFontSize'                 => 'input_font_size',
			'inputPadding'                  => 'input_padding',
			'inputBorderRadius'             => 'input_border_radius',
			'labelColor'                    => 'label_color',
			'labelFontSize'                 => 'label_font_size',
			'labelFontWeight'               => 'label_font_weight',
			'buttonBgColor'                 => 'button_bg_color',
			'buttonTextColor'               => 'button_text_color',
			'buttonFontSize'                => 'button_font_size',
			'buttonFontWeight'              => 'button_font_weight',
			'buttonBorderRadius'            => 'button_border_radius',
			'buttonHoverBgColor'            => 'button_hover_bg_color',
			'buttonHoverTextColor'          => 'button_hover_text_color',
			'buttonHoverBorder'             => 'button_hover_border',
		);

		foreach ( $attribute_map as $block_attr => $shortcode_attr ) {
			if ( isset( $attributes[ $block_attr ] ) ) {
				$value = $attributes[ $block_attr ];

				// Convert boolean to yes/no for shortcode.
				if ( is_bool( $value ) ) {
					$value = $value ? 'yes' : 'no';
				}

				$shortcode_attrs[ $shortcode_attr ] = $value;
			}
		}

		return $shortcode_attrs;
	}

	/**
	 * Validate and sanitize block attributes
	 *
	 * @param array $attributes Raw attributes.
	 * @return array Validated attributes
	 */
	private function validate_attributes( $attributes ) {
		// Ensure attributes is an array.
		if ( ! is_array( $attributes ) ) {
			$attributes = array();
		}

		$validated          = array();
		$default_attributes = $this->get_block_attributes();

		foreach ( $default_attributes as $key => $config ) {
			if ( isset( $attributes[ $key ] ) && null !== $attributes[ $key ] ) {
				$value = $attributes[ $key ];

				// Validate based on type.
				switch ( $config['type'] ) {
					case 'string':
						$validated[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $config['default'];
						break;
					case 'number':
						$validated[ $key ] = is_numeric( $value ) ? absint( $value ) : $config['default'];
						break;
					case 'boolean':
						$validated[ $key ] = is_bool( $value ) ? $value : $config['default'];
						break;
					default:
						$validated[ $key ] = $config['default'];
				}
			} else {
				$validated[ $key ] = $config['default'];
			}
		}

		return $validated;
	}
}
