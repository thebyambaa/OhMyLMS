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
class Permalink extends Settings {

	/**
	 * The settings ID.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	protected $id = 'permalink';

	/**
	 * Constructor.
	 *
	 * Initializes the general settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->id    = 'permalink';
		$this->label = __( 'Permalink Settings', 'ohmylms' );
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
				'id'      => 'ohmylms_permalink',
				'type'    => 'text',
				'default' => array(
					'course_base'     => _x( 'ohmylms-courses', 'slug', 'ohmylms' ),
					'lesson_base'     => _x( 'course-lessons', 'slug', 'ohmylms' ),
					'category_base'   => _x( 'course-categories', 'slug', 'ohmylms' ),
					'tag_base'        => _x( 'course-tags', 'slug', 'ohmylms' ),
					'membership_base' => _x( 'members', 'slug', 'ohmylms' ),
					'quiz_base'       => _x( 'course-quizzes', 'slug', 'ohmylms' ),
				),
				'value'   => '',
			),

		);

		return $settings;
	}
}
