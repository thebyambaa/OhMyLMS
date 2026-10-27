<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Item-level grade history. Each grade or regrade is a new event; the previous
 * current event for the same item is marked superseded. Events get an evidence-outbox
 * row unless their surface explicitly opts out, so participating skill evidence is updated
 * exactly once per grade even if a request dies after commit.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Item-level grade history. Each grade or regrade is a new event; the previous
 * current event for the same item is marked superseded. Every event also gets
 * an evidence-outbox row in the same transaction, so skill evidence is updated
 * exactly once per grade even if a request dies after commit.
 */
final class GradeEvents {
	/**
	 * Record.
	 *
	 * @param array $event source_type, source_id, item_id, student_id, question_id, version_id,
	 *                     awarded, max_marks, fraction, correct, grader, reviewer_id, reason.
	 *                     Optional record_evidence defaults to true; false saves a grade without skill effects.
	 * @return int event ID
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function record( array $event ) {
		global $wpdb;
		$table    = Schema::table( 'grade_events' );
		$part     = (string) ( $event['part_id'] ?? '' );
		$previous = (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE source_type=%s AND source_id=%d AND item_id=%d AND part_id=%s AND superseded_by=0 ORDER BY id DESC LIMIT 1', $table, $event['source_type'], $event['source_id'], $event['item_id'], $part )
		);
		$row      = array(
			'source_type' => (string) $event['source_type'],
			'source_id'   => (int) $event['source_id'],
			'item_id'     => (int) $event['item_id'],
			'part_id'     => $part,
			'student_id'  => (int) ( $event['student_id'] ?? 0 ),
			'question_id' => (int) $event['question_id'],
			'version_id'  => (int) $event['version_id'],
			'awarded'     => round( (float) $event['awarded'], 4 ),
			'max_marks'   => round( (float) $event['max_marks'], 4 ),
			'fraction'    => round( (float) $event['fraction'], 6 ),
			'correct'     => ! empty( $event['correct'] ) ? 1 : 0,
			'grader'      => (string) ( $event['grader'] ?? 'auto' ),
			'reviewer_id' => (int) ( $event['reviewer_id'] ?? 0 ),
			'reason'      => (string) ( $event['reason'] ?? '' ),
			'supersedes'  => $previous,
			'created_at'  => current_time( 'mysql', true ),
		);
		if ( ! $wpdb->insert( $table, $row ) ) {
			throw new \RuntimeException( 'Grade event write failed' ); }
		$id = (int) $wpdb->insert_id;
		if ( $previous && $wpdb->update( $table, array( 'superseded_by' => $id ), array( 'id' => $previous ) ) === false ) {
			throw new \RuntimeException( 'Grade event supersede failed' ); }
		if ( ( ! array_key_exists( 'record_evidence', $event ) || $event['record_evidence'] ) && ! $wpdb->insert(
			Schema::table( 'evidence_outbox' ),
			array(
				'grade_event_id' => $id,
				'status'         => 'pending',
				'created_at'     => current_time( 'mysql', true ),
			)
		) ) {
			throw new \RuntimeException( 'Evidence outbox write failed' );
		}
		/**
		 * Fires inside the grading transaction, after the grade event and any participating evidence
		 * outbox row are written. Listeners must be cheap and must not throw: an exception here would roll
		 * back the grade.
		 *
		 * @param int   $id  Grade event ID.
		 * @param array $row The stored event.
		 */
		do_action( 'ohmylms_grade_event_recorded', $id, $row );
		return $id;
	}

	/** Current (non-superseded) event for an item, or null.
	 *
	 * @param mixed $source_type Source type.
	 * @param mixed $source_id Source identifier.
	 * @param mixed $item_id Item identifier.
	 */
	public static function current( $source_type, $source_id, $item_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE source_type=%s AND source_id=%d AND item_id=%d AND superseded_by=0 ORDER BY id DESC LIMIT 1', Schema::table( 'grade_events' ), $source_type, $source_id, $item_id ),
			ARRAY_A
		) ?: null;
	}

	/** Full history for an item, oldest first.
	 *
	 * @param mixed $source_type Source type.
	 * @param mixed $source_id Source identifier.
	 * @param mixed $item_id Item identifier.
	 */
	public static function history( $source_type, $source_id, $item_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE source_type=%s AND source_id=%d AND item_id=%d ORDER BY id', Schema::table( 'grade_events' ), $source_type, $source_id, $item_id ),
			ARRAY_A
		);
	}

	/**
	 * Get for Grade events.
	 *
	 * @param mixed $id Id.
	 */
	public static function get( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', Schema::table( 'grade_events' ), (int) $id ), ARRAY_A ) ?: null;
	}
}
