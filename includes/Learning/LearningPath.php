<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin learning path implementation.
 */
final class LearningPath {
	/**
	 * Available for Learning path.
	 *
	 * @param array $item Item.
	 * @param mixed $student Student.
	 * @param mixed $course Course.
	 */
	public static function available( array $item, $student, $course ) {
		if ( empty( $item['available'] ) ) {
			return false; }
		if ( 'practice' === $item['type'] ) {
			return true; }
		return ! apply_filters( 'ohmylms_is_lesson_locked', false, $item['content_id'], $course, $student )
			&& ! apply_filters( 'ohmylms_is_lesson_sequentially_locked', false, $item['content_id'], $course, $student );
	}

	/**
	 * Next for Learning path.
	 *
	 * @param array $state State.
	 */
	public static function next( array $state ) {
		if ( $state['blocked'] ) {
			return null; }
		$program = $state['program'];
		foreach ( $program['items'] as $item ) {
			if ( ! $item['available'] ) {
				continue; }
			if ( 'practice' === $item['type'] ) {
				foreach ( $program['outcomes'] as $outcome ) {
					if ( $outcome['term_id'] === $item['content_id'] && ! $outcome['met'] ) {
						return array(
							'name'   => $outcome['name'],
							'url'    => $outcome['practice_url'],
							'reason' => __( 'Skill target remaining', 'ohmylms' ),
						); }
				}
			} elseif ( $item['required'] && ! $item['complete'] && self::available( $item, $state['student_id'], $program['course_id'] ) ) {
				return array(
					'name'   => $item['name'],
					'url'    => $item['url'],
					'reason' => __( 'Required activity remaining', 'ohmylms' ),
				);
			}
		}
		foreach ( $program['outcomes'] as $outcome ) {
			if ( $outcome['required'] && ! $outcome['met'] ) {
				return array(
					'name'   => $outcome['name'],
					'url'    => $outcome['practice_url'],
					'reason' => __( 'Skill target remaining', 'ohmylms' ),
				); }
		}
		return null;
	}
}
