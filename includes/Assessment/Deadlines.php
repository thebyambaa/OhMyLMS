<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * UTC deadlines for versioned attempts.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * UTC deadlines for versioned attempts.
 *
 * The deadline is fixed when the attempt starts (time limit plus any extra time).
 * A small grace period absorbs network latency for a submission sent before the
 * deadline; responses received after deadline + grace are not accepted as new
 * answers — only autosaved responses received in time count.
 */
final class Deadlines {
	const DEFAULT_GRACE = 15;

	/**
	 * Deadline ts for Deadlines.
	 *
	 * @param array $context Context.
	 */
	public static function deadline_ts( array $context ) {
		return empty( $context['deadline_at'] ) ? null : strtotime( $context['deadline_at'] . ' UTC' );
	}

	/** Seconds left before the deadline (null when untimed).
	 *
	 * @param array $context Context.
	 * @param mixed $now Now.
	 */
	public static function remaining( array $context, $now = null ) {
		$deadline = self::deadline_ts( $context );
		return null === $deadline ? null : max( 0, $deadline - ( $now ?? time() ) );
	}

	/** Past the deadline itself (the learner should stop answering).
	 *
	 * @param array $context Context.
	 * @param mixed $now Now.
	 */
	public static function expired( array $context, $now = null ) {
		$deadline = self::deadline_ts( $context );
		return null !== $deadline && ( $now ?? time() ) >= $deadline;
	}

	/**
	 * Finalize versioned attempts whose deadline (plus grace) has passed and that nobody
	 * submitted, e.g. because the browser was closed. Runs from WP-Cron.
	 *
	 * @return int number of attempts finalized
	 * @param int $limit Limit.
	 */
	public static function finalize_due( $limit = 50 ) {
		global $wpdb;
		$context = Schema::table( 'attempt_context' );
		$ids     = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT c.attempt_id FROM %i c JOIN %i a ON a.id=c.attempt_id
             WHERE c.finalized_at IS NULL AND c.deadline_at IS NOT NULL AND a.status=\'in-progress\'
             AND DATE_ADD(c.deadline_at, INTERVAL c.grace_seconds SECOND) < %s ORDER BY c.deadline_at LIMIT %d',
				$context,
				$wpdb->prefix . 'ohmylms_quiz_attempts',
				gmdate( 'Y-m-d H:i:s' ),
				max( 1, (int) $limit )
			)
		);
		$done    = 0;
		foreach ( $ids as $attempt_id ) {
			$result = \OhMyLMS\Quiz\Submission::finalize_expired( (int) $attempt_id );
			if ( ! is_wp_error( $result ) ) {
				++$done; }
		}
		return $done;
	}

	/**
	 * Schedules for Deadlines.
	 *
	 * @param mixed $schedules Schedules.
	 */
	public static function schedules( $schedules ) {
		$schedules['ohmylms_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every five minutes', 'ohmylms' ),
		);
		return $schedules;
	}

	/**
	 * Schedule for Deadlines.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'ohmylms_finalize_attempts' ) ) {
			wp_schedule_event( time() + 300, 'ohmylms_five_minutes', 'ohmylms_finalize_attempts' ); }
	}

	/** Past deadline + grace: new responses can no longer be accepted.
	 *
	 * @param array $context Context.
	 * @param mixed $now Now.
	 */
	public static function closed( array $context, $now = null ) {
		$deadline = self::deadline_ts( $context );
		return null !== $deadline && ( $now ?? time() ) > $deadline + (int) $context['grace_seconds'];
	}
}
