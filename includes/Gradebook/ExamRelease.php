<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Exam results release in the gradebooks.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Gradebook;

use OhMyLMS\Exams\Results;

defined( 'ABSPATH' ) || exit;

/**
 * A graded exam attempt whose results are held ({@see Results::held()}: manual policy and not released, or
 * a release date still ahead) has no score in any gradebook: the course grid and every view built on it,
 * the class gradebook (`Schools\Gradebook`) and the school/teacher matrices (`Student\SchoolGradebook`).
 * Its status becomes {@see ExamRelease::HELD}, which every gradebook treats like work waiting for grading:
 * shown as pending, left out of totals and averages. The rule does not depend on the viewer, so cached grids
 * stay user-independent; the exam's author sees held scores in the exam's own Results panel.
 */
final class ExamRelease {

	/**
	 * Status given to a held attempt row.
	 */
	const HELD = 'held';

	/**
	 * Attempt statuses that carry a score.
	 */
	const GRADED = array( 'completed', 'passed', 'failed' );

	/**
	 * Mark the held exam attempts among quiz attempt rows.
	 *
	 * @param array[] $rows Quiz attempt rows with `id`, `content_id` (quiz) and `status`.
	 * @return array[] The rows; held ones have status {@see ExamRelease::HELD}.
	 */
	public static function hold( array $rows ) {
		$candidates = array();
		foreach ( $rows as $row ) {
			if ( in_array( (string) $row['status'], self::GRADED, true ) && Results::exam_of_quiz( (int) $row['content_id'] ) ) {
				$candidates[] = (int) $row['id'];
			}
		}
		if ( ! $candidates ) {
			return $rows;
		}
		$held = array_flip( Results::held( $candidates ) );
		foreach ( $rows as &$row ) {
			if ( isset( $held[ (int) $row['id'] ] ) ) {
				$row['status'] = self::HELD;
			}
		}
		unset( $row );
		return $rows;
	}
}
