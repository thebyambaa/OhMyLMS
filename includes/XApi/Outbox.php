<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Statement outbox.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The outbox is plugin-owned queue state. Workers must always read current rows, so these queries are deliberately uncached, and WordPress has no API for custom tables.

/**
 * Durable queue between OhMyLMS events and the LRS.
 *
 * A row records that something happened. The statement is built later, by the processor, so
 * queuing costs one small insert and never slows the learner's request. The statement ID is
 * derived from the event, and the table has a unique key on it, so an event is queued once
 * no matter how many times its hook fires.
 */
final class Outbox {

	const MAX_TRIES    = 10;
	const HOLD_SECONDS = 900;
	const FAILED_DAYS  = 90;

	/**
	 * Queue an event. Never throws: xAPI must not be able to break grading or enrolment.
	 *
	 * @param string   $kind       Statement kind.
	 * @param int      $student_id Learner.
	 * @param array    $ref        What the materializer needs to rebuild the event.
	 * @param string   $key        Stable event key; the statement ID is derived from it.
	 * @param int|null $occurred   Unix time of the event; defaults to now.
	 * @return bool True when a new row was queued, false when disabled, duplicate or failed.
	 */
	public static function enqueue( $kind, $student_id, array $ref, $key, $occurred = null ) {
		global $wpdb;
		try {
			if ( ! Settings::enabled() || ! Settings::kind_enabled( $kind ) || $student_id < 1 ) {
				return false;
			}
			$now  = current_time( 'mysql', true );
			$rows = $wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO %i (statement_uuid, student_id, kind, ref, occurred_at, status, tries, next_attempt_at, created_at) VALUES (%s, %d, %s, %s, %s, 'pending', 0, %s, %s)",
					Schema::table( 'outbox' ),
					Uuid::v5( Settings::site_id(), $key ),
					(int) $student_id,
					$kind,
					wp_json_encode( $ref ),
					gmdate( 'Y-m-d H:i:s', $occurred ? (int) $occurred : time() ),
					$now,
					$now
				)
			);

			return 1 === (int) $rows;
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	/**
	 * Rows ready to send, oldest first.
	 *
	 * @param int $limit Maximum rows.
	 * @return array[]
	 */
	public static function due( $limit ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status='pending' AND next_attempt_at <= %s ORDER BY id LIMIT %d",
				Schema::table( 'outbox' ),
				current_time( 'mysql', true ),
				max( 1, (int) $limit )
			),
			ARRAY_A
		);
	}

	/**
	 * Keep the built statement so retries send exactly what was built the first time.
	 *
	 * @param int    $id   Row ID.
	 * @param string $json Statement JSON.
	 */
	public static function store_statement( $id, $json ) {
		global $wpdb;
		$wpdb->update( Schema::table( 'outbox' ), array( 'statement' => $json ), array( 'id' => (int) $id ) );
	}

	/**
	 * Mark rows delivered.
	 *
	 * @param int[] $ids Row IDs.
	 */
	public static function mark_sent( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $placeholders is only "%d" repeated once per validated integer ID, and the arguments are the table, the time and those same IDs.
			$wpdb->prepare( "UPDATE %i SET status='sent', sent_at=%s, last_error=NULL WHERE id IN ($placeholders)", array_merge( array( Schema::table( 'outbox' ), current_time( 'mysql', true ) ), $ids ) )
		);
	}

	/**
	 * Mark a row as having nothing to send.
	 *
	 * @param int    $id     Row ID.
	 * @param string $reason Why.
	 */
	public static function skip( $id, $reason ) {
		global $wpdb;
		$wpdb->update(
			Schema::table( 'outbox' ),
			array(
				'status'     => 'skipped',
				'last_error' => substr( $reason, 0, 500 ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Mark a row permanently undeliverable.
	 *
	 * @param int    $id    Row ID.
	 * @param string $error Why.
	 */
	public static function fail( $id, $error ) {
		global $wpdb;
		$wpdb->update(
			Schema::table( 'outbox' ),
			array(
				'status'     => 'failed',
				'last_error' => substr( $error, 0, 500 ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Schedule another attempt with backoff, or fail the row after MAX_TRIES.
	 *
	 * @param array  $row   Outbox row.
	 * @param string $error Why this attempt failed.
	 */
	public static function retry( array $row, $error ) {
		global $wpdb;
		$tries = (int) $row['tries'] + 1;
		$wpdb->update(
			Schema::table( 'outbox' ),
			array(
				'tries'           => $tries,
				'status'          => $tries >= self::MAX_TRIES ? 'failed' : 'pending',
				'next_attempt_at' => gmdate( 'Y-m-d H:i:s', time() + self::backoff_seconds( $tries ) ),
				'last_error'      => substr( $error, 0, 500 ),
			),
			array( 'id' => (int) $row['id'] )
		);
	}

	/**
	 * Pause everything that is due, without using up attempts. Used when the problem is the
	 * connection (bad credentials, wrong URL) rather than any one statement.
	 *
	 * @param string $error   Why.
	 * @param int    $seconds Pause length.
	 */
	public static function hold_all( $error, $seconds = self::HOLD_SECONDS ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET next_attempt_at=%s, last_error=%s WHERE status='pending' AND next_attempt_at <= %s",
				Schema::table( 'outbox' ),
				gmdate( 'Y-m-d H:i:s', time() + (int) $seconds ),
				substr( $error, 0, 500 ),
				current_time( 'mysql', true )
			)
		);
	}

	/**
	 * Seconds to wait after the given number of failed attempts: 1, 2, 4 ... minutes, capped at an hour.
	 *
	 * @param int $tries Failed attempts so far (at least 1).
	 * @return int
	 */
	public static function backoff_seconds( $tries ) {
		return (int) min( 3600, 60 * ( 1 << min( max( 1, (int) $tries ) - 1, 10 ) ) );
	}

	/**
	 * Row counts by status.
	 *
	 * @return array<string,int>
	 */
	public static function counts() {
		global $wpdb;
		$counts = array(
			'pending' => 0,
			'sent'    => 0,
			'failed'  => 0,
			'skipped' => 0,
		);
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS total FROM %i GROUP BY status', Schema::table( 'outbox' ) ), ARRAY_A );
		foreach ( $rows as $row ) {
			$counts[ $row['status'] ] = (int) $row['total'];
		}

		return $counts;
	}

	/**
	 * Most recent failed rows, for the admin screen.
	 *
	 * @param int $limit Maximum rows.
	 * @return array[]
	 */
	public static function recent_failed( $limit = 10 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT id, kind, student_id, tries, last_error, created_at FROM %i WHERE status='failed' ORDER BY id DESC LIMIT %d", Schema::table( 'outbox' ), max( 1, (int) $limit ) ),
			ARRAY_A
		);
	}

	/**
	 * Put failed rows back in the queue with a fresh set of attempts.
	 *
	 * @return int Rows requeued.
	 */
	public static function requeue_failed() {
		global $wpdb;
		$rows = $wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status='pending', tries=0, next_attempt_at=%s WHERE status='failed'",
				Schema::table( 'outbox' ),
				current_time( 'mysql', true )
			)
		);

		return (int) $rows;
	}

	/**
	 * Delete delivered and skipped rows past the retention period, and long-dead failures.
	 *
	 * @param int $days Retention for delivered rows.
	 * @return int Rows deleted.
	 */
	public static function prune( $days ) {
		global $wpdb;
		$table   = Schema::table( 'outbox' );
		$expired = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE status IN ('sent','skipped') AND COALESCE(sent_at, created_at) < %s LIMIT 5000",
				$table,
				gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS )
			)
		);
		$dead    = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE status='failed' AND created_at < %s LIMIT 5000",
				$table,
				gmdate( 'Y-m-d H:i:s', time() - self::FAILED_DAYS * DAY_IN_SECONDS )
			)
		);

		return (int) $expired + (int) $dead;
	}
}
