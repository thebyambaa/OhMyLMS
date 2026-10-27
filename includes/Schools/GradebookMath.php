<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Schools;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin gradebook math implementation.
 */
final class GradebookMath {
	/** Ungraded work is excluded; a real zero remains a grade.
	 *
	 * @param array $cells Cells.
	 */
	public static function total( array $cells ) {
		$score  = 0;
		$max    = 0;
		$graded = 0;
		foreach ( $cells as $cell ) {
			if ( null === $cell['score'] || $cell['max'] <= 0 ) {
				continue; }
			$score += $cell['score'];
			$max   += $cell['max'];
			++$graded;
		}
		return array(
			'score'   => $score,
			'max'     => $max,
			'graded'  => $graded,
			'percent' => $max > 0 ? round( 100 * $score / $max, 2 ) : null,
		);
	}
	/**
	 * Average for Gradebook math.
	 *
	 * @param array $values Values.
	 */
	public static function average( array $values ) {
		$values = array_values(
			array_filter(
				$values,
				function ( $v ) {
					return null !== $v;
				}
			)
		);
		return $values ? round( array_sum( $values ) / count( $values ), 2 ) : null;
	}
	/**
	 * Validate score for Gradebook math.
	 *
	 * @param mixed $score Score.
	 * @param mixed $max Max.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function validate_score( $score, $max ) {
		if ( ! is_numeric( $score ) || ! is_finite( (float) $score ) || $max <= 0 || $score < 0 || $score > $max ) {
			throw new \RuntimeException( 'Enter a score between zero and the maximum points.', 400 );
		}
		return round( (float) $score, 4 );
	}
}
