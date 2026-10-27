<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Pooling of skill evidence for a shared skill and the curriculum-specific skills explicitly
 * mapped to it as equivalent.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Bundles;

use OhMyLMS\Skills\Mastery;

defined( 'ABSPATH' ) || exit;

/**
 * Pooling of skill evidence for a shared skill and the curriculum-specific skills explicitly
 * mapped to it as equivalent.
 *
 * This is a read-only calculation over existing evidence rows: nothing is written, no skill
 * state is changed and course completion is never touched, so shared evidence cannot mark
 * another course complete. The same answer is counted once however many mapped skills
 * reference it, and repeated answers to one question never count as independent first tries.
 */
final class Combined {
	/**
	 * De-duplicate and order evidence rows. Each row needs grade_event_id, part_id, question_id,
	 * evidence_at and the fields Mastery::evaluate reads. Rows for one graded part (an event and
	 * part) collapse to one; a question answered again later is kept but no longer a first try.
	 *
	 * @param array[] $rows Rows.
	 * @return array[]
	 */
	public static function merge( array $rows ) {
		usort(
			$rows,
			static function ( $left, $right ) {
				return strcmp( (string) $left['evidence_at'], (string) $right['evidence_at'] ) ?: ( (int) ( $left['id'] ?? 0 ) <=> (int) ( $right['id'] ?? 0 ) ) ?: ( (int) $left['grade_event_id'] <=> (int) $right['grade_event_id'] );
			}
		);
		$seen_parts     = array();
		$seen_questions = array();
		$merged         = array();
		foreach ( $rows as $row ) {
			$part_key = (int) $row['grade_event_id'] . '|' . (string) $row['part_id'];
			if ( isset( $seen_parts[ $part_key ] ) ) {
				continue; }
			$seen_parts[ $part_key ]         = true;
			$question_key                    = (int) $row['question_id'] . '|' . (string) $row['part_id'];
			$row['first_try']                = ! empty( $row['first_try'] ) && ! isset( $seen_questions[ $question_key ] ) ? 1 : 0;
			$seen_questions[ $question_key ] = true;
			$merged[]                        = $row;
		}
		return $merged;
	}

	/** Evaluate pooled evidence with the site's own mastery rules.
	 *
	 * @param array $rows Rows.
	 * @param array $rules Rules.
	 * @param mixed $now Now.
	 */
	public static function evaluate( array $rows, array $rules, $now ) {
		return Mastery::evaluate( self::merge( $rows ), $rules, $now );
	}

	/**
	 * The pooled state under the site's level policy (what a learner's own skill state would be).
	 *
	 * @param array[] $rows Pooled evidence rows.
	 * @param int     $now Timestamp.
	 * @return array
	 */
	public static function state( array $rows, $now ) {
		return \OhMyLMS\Performance\Engine::evaluate( self::merge( $rows ), $now );
	}
}
