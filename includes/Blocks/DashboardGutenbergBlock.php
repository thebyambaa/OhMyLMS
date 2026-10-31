<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Dashboard Block
 *
 * Gutenberg block for OhMyLMS student dashboard functionality
 *
 * @package OhMyLMS\Blocks
 * @since 1.0.0
 */

namespace OhMyLMS\Blocks;

use OhMyLMS\Shortcodes\ShortCodeDashboard;
use function OhMyLMS\Commerce\ecommerce;

defined( 'ABSPATH' ) || exit;

/**
 * DashboardGutenbergBlock class
 */
class DashboardGutenbergBlock {

	/**
	 * Block name
	 *
	 * @var string
	 */
	const BLOCK_NAME = 'ohmylms/dashboard';

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
			'align'                      => array(
				'type'    => 'string',
				'default' => 'full',
			),
			// Header visibility and configuration.
			'showHeader'                 => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'myProfileUrl'               => array(
				'type'    => 'string',
				'default' => '',
			),
			'myCoursesUrl'               => array(
				'type'    => 'string',
				'default' => '',
			),
			// Header styling.
			'headerBgColor'              => array(
				'type'    => 'string',
				'default' => '#000D2C',
			),
			// User menu styling.
			'userMenuColor'              => array(
				'type'    => 'string',
				'default' => '#000000',
			),
			'userMenuBgColor'            => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'userMenuFontSize'           => array(
				'type'    => 'number',
				'default' => 14,
			),
			'userMenuFontWeight'         => array(
				'type'    => 'number',
				'default' => 400,
			),
			// User menu hover styling.
			'userMenuHoverColor'         => array(
				'type'    => 'string',
				'default' => '#000000',
			),
			'userMenuHoverBgColor'       => array(
				'type'    => 'string',
				'default' => '#F5F5F5',
			),
			// User menu icon styling.
			'userMenuIconColor'          => array(
				'type'    => 'string',
				'default' => '#000000',
			),
			'userMenuIconHoverColor'     => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
			// Dashboard background.
			'dashboardBgColor'           => array(
				'type'    => 'string',
				'default' => '#F9FAFD',
			),
			// Course card button styling.
			'courseButtonTextColor'      => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'courseButtonBgColor'        => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
			'courseButtonFontSize'       => array(
				'type'    => 'number',
				'default' => 15,
			),
			'courseButtonFontWeight'     => array(
				'type'    => 'number',
				'default' => 500,
			),
			'courseButtonBorderRadius'   => array(
				'type'    => 'number',
				'default' => 10,
			),
			'courseButtonHoverTextColor' => array(
				'type'    => 'string',
				'default' => '#4361EE',
			),
			'courseButtonHoverBgColor'   => array(
				'type'    => 'string',
				'default' => 'transparent',
			),
			// Card styling.
			'cardBgColor'                => array(
				'type'    => 'string',
				'default' => '#FFFFFF',
			),
			'cardTextColor'              => array(
				'type'    => 'string',
				'default' => '#52525B',
			),
			'cardTextFontSize'           => array(
				'type'    => 'number',
				'default' => 14,
			),
			'cardTextFontWeight'         => array(
				'type'    => 'number',
				'default' => 400,
			),
			'cardNumberColor'            => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'cardNumberFontSize'         => array(
				'type'    => 'number',
				'default' => 30,
			),
			'cardNumberFontWeight'       => array(
				'type'    => 'number',
				'default' => 700,
			),
			// Title styling.
			'titleColor'                 => array(
				'type'    => 'string',
				'default' => '#1E1E1E',
			),
			'titleFontSize'              => array(
				'type'    => 'number',
				'default' => 22,
			),
			'titleFontWeight'            => array(
				'type'    => 'number',
				'default' => 700,
			),
		);
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
			'showHeader'                 => 'show_header',
			'headerBgColor'              => 'header_bg_color',
			'userMenuColor'              => 'user_menu_color',
			'userMenuBgColor'            => 'user_menu_bg_color',
			'userMenuFontSize'           => 'user_menu_font_size',
			'userMenuFontWeight'         => 'user_menu_font_weight',
			'userMenuHoverColor'         => 'user_menu_hover_color',
			'userMenuHoverBgColor'       => 'user_menu_hover_bg_color',
			'userMenuIconColor'          => 'user_menu_icon_color',
			'userMenuIconHoverColor'     => 'user_menu_icon_hover_color',
			'dashboardBgColor'           => 'dashboard_bg_color',
			'courseButtonTextColor'      => 'course_button_text_color',
			'courseButtonBgColor'        => 'course_button_bg_color',
			'courseButtonFontSize'       => 'course_button_font_size',
			'courseButtonFontWeight'     => 'course_button_font_weight',
			'courseButtonBorderRadius'   => 'course_button_border_radius',
			'courseButtonHoverTextColor' => 'course_button_hover_text_color',
			'courseButtonHoverBgColor'   => 'course_button_hover_bg_color',
			'cardBgColor'                => 'card_bg_color',
			'cardTextColor'              => 'card_text_color',
			'cardTextFontSize'           => 'card_text_font_size',
			'cardTextFontWeight'         => 'card_text_font_weight',
			'cardNumberColor'            => 'card_number_color',
			'cardNumberFontSize'         => 'card_number_font_size',
			'cardNumberFontWeight'       => 'card_number_font_weight',
			'titleColor'                 => 'title_color',
			'titleFontSize'              => 'title_font_size',
			'titleFontWeight'            => 'title_font_weight',
		);

		foreach ( $attribute_map as $block_attr => $shortcode_attr ) {
			if ( isset( $attributes[ $block_attr ] ) ) {
				$shortcode_attrs[ $shortcode_attr ] = $attributes[ $block_attr ];
			}
		}

		return $shortcode_attrs;
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

		// Enable preview mode for Gutenberg editor to show dashboard even when not logged in.
		if ( $is_editor ) {
			add_filter( 'ohmylms_gutenberg_preview_mode', '__return_true' );
		}

		// Add editor-specific styling for proper dashboard rendering.
		if ( $is_editor ) {
			?>
			<style>
				.wp-block-ohmylms-dashboard .ohmylms {
					max-width: 100% !important;
					background-color: #F9FAFD !important;
					width: 100% !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard {
					padding: 30px !important;
					background-color: #fff !important;
					border-radius: 12px !important;
					box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1) !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-header {
					margin-bottom: 30px !important;
					padding-bottom: 20px !important;
					border-bottom: 1px solid #eee !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-title {
					color: var(--ohmylms-heading-color, #1e1e1e) !important;
					font-size: 24px !important;
					font-weight: 600 !important;
					line-height: 1.3 !important;
					margin: 0 0 10px !important;
					letter-spacing: 0 !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-subtitle {
					color: #666 !important;
					font-size: 16px !important;
					font-weight: 400 !important;
					line-height: 1.5 !important;
					margin: 0 !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-stats {
					display: grid !important;
					grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
					gap: 20px !important;
					margin-bottom: 30px !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-stat-card {
					background-color: #f8f9fa !important;
					padding: 20px !important;
					border-radius: 8px !important;
					text-align: center !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-stat-value {
					font-size: 28px !important;
					font-weight: 700 !important;
					color: #6E42D3 !important;
					margin-bottom: 5px !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-stat-label {
					font-size: 14px !important;
					color: #666 !important;
					font-weight: 500 !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-actions {
					display: flex !important;
					gap: 15px !important;
					flex-wrap: wrap !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-button {
					background-color: #6E42D3 !important;
					color: #fff !important;
					padding: 12px 24px !important;
					border-radius: 6px !important;
					text-decoration: none !important;
					font-weight: 600 !important;
					font-size: 14px !important;
					border: none !important;
					cursor: pointer !important;
					transition: all 0.3s ease !important;
				}
				.wp-block-ohmylms-dashboard .ohmylms-dashboard-button:hover {
					background-color: #5a32c2 !important;
				}
				@media (max-width: 768px) {
					.wp-block-ohmylms-dashboard .ohmylms-dashboard {
						padding: 20px !important;
					}
					.wp-block-ohmylms-dashboard .ohmylms-dashboard-stats {
						grid-template-columns: 1fr !important;
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
			$wrapper_classes[] = 'ohmylms-dashboard';
		}

		echo '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

		// Add preview notice in editor mode.
		if ( $is_editor ) {
			echo '<div class="ohmylms-gutenberg-edit-mode">';
			echo '<small>' . esc_html__( 'Gutenberg Preview Mode: This is how the student dashboard will appear to logged-in users.', 'ohmylms' ) . '</small>';
			echo '</div>';
		}

		// Output the dashboard.
		ShortCodeDashboard::output( $shortcode_attrs );

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
	 * Validate and sanitize block attributes
	 *
	 * @param array $attributes Raw attributes.
	 * @return array Validated attributes
	 */
	private function validate_attributes( $attributes ) {
		$validated          = array();
		$default_attributes = $this->get_block_attributes();

		foreach ( $default_attributes as $key => $config ) {
			if ( isset( $attributes[ $key ] ) ) {
				$value = $attributes[ $key ];

				// Validate based on type.
				switch ( $config['type'] ) {
					case 'boolean':
						$validated[ $key ] = (bool) $value;
						break;
					case 'string':
						$validated[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $config['default'];
						break;
					case 'number':
						$validated[ $key ] = is_numeric( $value ) ? (float) $value : $config['default'];
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
