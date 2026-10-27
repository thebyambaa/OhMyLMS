<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hook for Lesson
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMSPro/includes
 */
namespace OhMyLMS\Hooks;

/**
 * Plugin lesson hook implementation.
 */
class LessonHook {

	/**
	 * Register hooks for Lesson hook.
	 */
	public function register_hooks() {
		add_filter( 'ohmylms_get_content_object', array( $this, 'get_content_object' ), 10, 3 );
	}

	/**
	 * Get content object for lesson
	 *
	 * @param mixed  $default Default value if content type is not assignment or session.
	 * @param string $content_type Content type.
	 * @param int    $lesson_id Lesson ID.
	 * @return mixed Content object if content type is assignment or session, otherwise default value.
	 * @since 1.0.0
	 */
	public function get_content_object( $default, $content_type, $lesson_id ) {
		if ( 'assignment' === $content_type ) {
			return ohmylms_get_assignment( $lesson_id );
		}
		if ( 'session' === $content_type ) {
			return ohmylms_get_session( $lesson_id );
		}
		return $default;
	}
}
