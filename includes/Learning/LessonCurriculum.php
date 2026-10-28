<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Lesson discovery through existing curriculum references.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Learning;

use OhMyLMS\Curriculum\Items;
use OhMyLMS\Curriculum\CourseObjectives;
use OhMyLMS\QuestionBank\Organisation;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Curriculum filtering does not change lesson ownership or grant access. */
final class LessonCurriculum {

	/**
	 * Candidate lessons for a curriculum branch, before the normal permission/status query.
	 *
	 * @param int $scope Selected curriculum item.
	 * @return int[]|\WP_Error Lesson IDs or unavailable scope error.
	 */
	public static function ids( $scope ) {
		$references = Organisation::resolve( 'curriculum', $scope );
		if ( is_wp_error( $references ) ) {
			return $references;
		}
		$branch  = Items::with_descendants( $scope );
		$courses = $references['courses'];
		foreach ( $branch as $id ) {
			$item = Items::get( $id );
			if ( ! empty( $item['course_id'] ) ) {
				$courses[] = (int) $item['course_id'];
			}
		}
		$matched = $references['skills'] ? get_objects_in_term( $references['skills'], Taxonomy::NAME ) : array();
		if ( is_wp_error( $matched ) ) {
			return $matched;
		}
		$lessons          = get_posts(
			array(
				'post_type'      => OHMYLMS_LESSON_CPT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$sources          = array();
		$activity_lessons = array();
		foreach ( Catalog::used_in( $lessons ) as $lesson => $used ) {
			foreach ( $used as $course ) {
				if ( ! isset( $sources[ $course ] ) ) {
					$sources[ $course ]          = CourseObjectives::source( $course );
					$activity_lessons[ $course ] = array();
					$draft                       = get_post_meta( $course, CourseProgram::DRAFT, true );
					$current                     = CourseProgram::current( $course );
					foreach ( array_merge( $draft['items'] ?? array(), $current['items'] ?? array() ) as $activity ) {
						if ( 'lesson' === ( $activity['type'] ?? '' ) && array_intersect( $references['skills'], $activity['skill_ids'] ?? array() ) ) {
							$activity_lessons[ $course ][] = (int) $activity['content_id'];
						}
					}
				}
				if ( in_array( $course, $courses, true ) || in_array( $sources[ $course ], $branch, true ) || in_array( $lesson, $activity_lessons[ $course ], true ) ) {
					$matched[] = $lesson;
					break;
				}
			}
		}
		return array_values( array_unique( array_map( 'intval', $matched ) ) );
	}
}
