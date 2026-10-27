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
class Design extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'design';

	/**
	 * Constructor.
	 *
	 * Initializes the general settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'design';
		$this->label = __( 'Design', 'ohmylms' );
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
				'id'      => 'ohmylms_courses_per_page',
				'type'    => 'number',
				'default' => '10',
				'value'   => '10',
			),
			array(
				'id'      => 'ohmylms_archive_page_layout',
				'type'    => 'text',
				'default' => 'grid',
				'value'   => 'grid',
			),
			array(
				'id'      => 'ohmylms_archive_page_layout_style',
				'type'    => 'text',
				'default' => 'grid-style1',
				'value'   => 'grid-style1',
			),
			array(
				'id'      => 'ohmylms_archive_page_filter_is_enabled',
				'type'    => 'text',
				'default' => 'no',
				'value'   => 'no',
			),
			array(
				'id'      => 'ohmylms_archive_page_filters',
				'type'    => 'array',
				'default' => array( 'keyword', 'category', 'tag', 'difficulty_level', 'price_type' ),
				'value'   => array( 'keyword', 'category', 'tag', 'difficulty_level', 'price_type' ),
			),
			array(
				'id'      => 'ohmylms_archive_page_sorting_is_enabled',
				'type'    => 'text',
				'default' => 'no',
				'value'   => 'no',
			),
			array(
				'id'      => 'ohmylms_archive_page_search_is_enabled',
				'type'    => 'text',
				'default' => 'no',
				'value'   => 'no',
			),
			array(
				'id'      => 'ohmylms_archive_page_category_is_enabled',
				'type'    => 'text',
				'default' => 'no',
				'value'   => 'no',
			),
			array(
				'id'      => 'ohmylms_archive_page_row',
				'type'    => 'array',
				'default' => array(
					array(
						'row_display_criteria' => 'all',
						'row_heading'          => 'Untitled',
					),
				),
				'value'   => array(
					array(
						'row_display_criteria' => 'all',
						'row_heading'          => 'Untitled',
					),
				),
			),
			array(
				'id'      => 'ohmylms_single_course_page_features',
				'type'    => 'array',
				'default' => array( 'level', 'review', 'students', 'available_seat', 'duration', 'total_lesson', 'author', 'category', 'tag', 'level_with_enroll', 'review_with_enroll', 'students_with_enroll', 'available_seat_with_enroll', 'progress_bar_with_enroll', 'certificate_with_enroll', 'leaderboard_with_enroll', 'duration_with_enroll', 'total_lesson_with_enroll', 'author_with_enroll', 'category_with_enroll', 'tag_with_enroll' ),
				'value'   => array( 'level', 'review', 'students', 'available_seat', 'duration', 'total_lesson', 'author', 'category', 'tag', 'level_with_enroll', 'review_with_enroll', 'students_with_enroll', 'available_seat_with_enroll', 'progress_bar_with_enroll', 'certificate_with_enroll', 'leaderboard_with_enroll', 'duration_with_enroll', 'total_lesson_with_enroll', 'author_with_enroll', 'category_with_enroll', 'tag_with_enroll' ),
			),
			array(
				'id'      => 'ohmylms_single_course_page_layout',
				'type'    => 'text',
				'default' => 'layout_2',
				'value'   => 'layout_2',
			),
			array(
				'id'      => 'ohmylms_columns_per_row',
				'type'    => 'number',
				'default' => '4',
				'value'   => '4',
			),
			array(
				'id'      => 'ohmylms_container_width',
				'type'    => 'text',
				'default' => '1920px',
				'value'   => '1920px',
			),
			array(
				'id'      => 'ohmylms_debug_mode',
				'type'    => 'switch',
				'default' => 'off',
				'value'   => 'off',
			),
			array(
				'id'      => 'ohmylms_color_preset',
				'type'    => 'text',
				'default' => 'royal-purple',
				'value'   => 'royal-purple',
			),
			array(
				'id'      => 'ohmylms_primary_color_scheme',
				'type'    => 'switch',
				'default' => '#6E42D3',
				'value'   => '#6E42D3',
			),
			array(
				'id'      => 'ohmylms_primary_hover_color_scheme',
				'type'    => 'switch',
				'default' => '#1447BC',
				'value'   => '#1447BC',
			),
			array(
				'id'      => 'ohmylms_heading_color_scheme',
				'type'    => 'switch',
				'default' => '#000D25',
				'value'   => '#000D25',
			),
			array(
				'id'      => 'ohmylms_body_text_color_scheme',
				'type'    => 'switch',
				'default' => '#52525B',
				'value'   => '#52525B',
			),
			array(
				'id'      => 'ohmylms_body_progress_color_scheme',
				'type'    => 'switch',
				'default' => '#35BD4C',
				'value'   => '#35BD4C',
			),
			// Fonts and admin colors (see OhMyLMS\Design\Tokens).
			array(
				'id'      => 'ohmylms_font_family',
				'type'    => 'text',
				'default' => 'inherit',
				'value'   => 'inherit',
			),
			array(
				'id'      => 'ohmylms_admin_primary_color',
				'type'    => 'color',
				'default' => '#6E42D3',
				'value'   => '#6E42D3',
			),
			array(
				'id'      => 'ohmylms_admin_heading_color',
				'type'    => 'color',
				'default' => '#000D25',
				'value'   => '#000D25',
			),
			array(
				'id'      => 'ohmylms_admin_muted_color',
				'type'    => 'color',
				'default' => '#7A8B9A',
				'value'   => '#7A8B9A',
			),
			array(
				'id'      => 'ohmylms_admin_font_family',
				'type'    => 'text',
				'default' => 'system',
				'value'   => 'system',
			),
			// Checkout page layout type.
			array(
				'id'      => 'ohmylms_checkout_page_layout_type',
				'type'    => 'text',
				'default' => 'canvas',
				'value'   => 'canvas',
			),
			array(
				'id'      => 'ohmylms_leaderboard_settings',
				'type'    => 'array',
				'default' => array(),
				'value'   => array(),
			),
			array(
				'id'      => 'ohmylms_video_player_logo',
				'type'    => 'image',
				'default' => '',
			),
			array(
				'id'      => 'ohmylms_video_player_logo_bg_color',
				'type'    => 'color',
				'default' => '#6E42D3',
			),

		);

		foreach ( \OhMyLMS\Design\Tokens::QUIZ_COLORS as $option => $default ) {
			$settings[] = array(
				'id'      => $option,
				'type'    => 'color',
				'default' => $default,
				'value'   => $default,
			);
		}
		return $settings;
	}
}
