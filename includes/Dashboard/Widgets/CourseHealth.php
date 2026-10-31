<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "Course health": completion, rating, median progress and drop-off per course of the scope.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Access;
use OhMyLMS\Dashboard\CourseHealthReport;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * `data: { courses[], total, limit }` (docs/api/dashboard.md, "Course health"); each course
 * `{ id, title, enrolled, completed, completion, rating: { average, count } | null, items,
 * median_progress, drop_off: { content_id, title, type, position, lost, percent } | null, report }`.
 * Figures cover the scope's learners now (not the period). `report` (the course report route) is null
 * for users who cannot open it.
 */
final class CourseHealth extends Widget {

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return 'course-health';
	}

	/**
	 * Courses of a scope.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period (unused: health is the state now).
	 * @return array
	 */
	public function compute( Scope $scope, Period $period ) {
		return CourseHealthReport::build( $scope );
	}

	/**
	 * Add the course report route for users who may open it.
	 *
	 * @param array  $data    Cached data.
	 * @param Scope  $scope   Scope.
	 * @param Period $period  Period.
	 * @param int    $user_id User.
	 * @return array
	 */
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		$reports = user_can( (int) $user_id, Access::SITE_CAPABILITY );
		$courses = array();
		foreach ( (array) ( $data['courses'] ?? array() ) as $course ) {
			$course['report'] = $reports ? '/reports/courses/' . (int) $course['id'] : null;
			$courses[]        = $course;
		}
		return array(
			'courses' => $courses,
			'total'   => (int) ( $data['total'] ?? count( $courses ) ),
			'limit'   => (int) ( $data['limit'] ?? CourseHealthReport::limit() ),
		);
	}
}
