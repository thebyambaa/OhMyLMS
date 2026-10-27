<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hook for Course
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMSPro/includes
 */
namespace OhMyLMS\Hooks;

/**
 * Plugin course hook implementation.
 */
class CourseHook {

	/**
	 * Register hooks for Course hook.
	 */
	public function register_hooks() {
		add_filter( 'ohmylms_rest_get_course_data', array( $this, 'get_course_data' ), 10, 2 );
	}

	/**
	 * Get course data.
	 *
	 *
	 * @param mixed $data Input data.
	 * @param mixed $course Course.
	 * @return mixed
	 */
	public function get_course_data( $data, $course ) {
		$data['has_community'] = $course->get_has_community();
		return $data;
	}
}
