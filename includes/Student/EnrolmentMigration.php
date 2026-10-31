<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * One-time, batched and reversible fill of `ohmylms_enrolments` from `ohmylms_user_enrollment`.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Student;

use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Background migration of legacy enrolment rows into `ohmylms_enrolments` (see docs/api/enrolments.md).
 *
 * Up: WP-Cron batches walk the legacy table by ID; each batch locks its rows, projects them through
 * {@see EnrolmentMirror::write()} (rows it creates get `migrated = 1`), stores the cursor and schedules the
 * next batch. Re-running is safe: rows are keyed by `legacy_id`. The last pass sweeps legacy rows still
 * without a projection, removes projections whose legacy row is gone, then marks the state `complete`;
 * only then do readers use the new table ({@see self::complete()}).
 *
 * Down: {@see self::revert()} turns reading off at once, then batches delete only `migrated = 1` rows.
 */
final class EnrolmentMigration {

	/**
	 * State option.
	 */
	const OPTION = 'ohmylms_enrolment_migration';

	/**
	 * Cron hook of one up batch.
	 */
	const UP_HOOK = 'ohmylms_migrate_enrolments_batch';

	/**
	 * Cron hook of one down batch.
	 */
	const DOWN_HOOK = 'ohmylms_revert_enrolments_batch';

	/**
	 * Database update key that schedules the migration.
	 */
	const DB_VERSION = '1.2.21';

	/**
	 * Legacy rows per up batch.
	 */
	const BATCH = 200;

	/**
	 * Rows deleted per down batch.
	 */
	const DOWN_BATCH = 500;

	/**
	 * Seconds a legacy row may stay without a projection before a sweep is scheduled.
	 */
	const LAG_GRACE = 60;

	/**
	 * Register the cron handlers.
	 */
	public static function register() {
		add_action( self::UP_HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( self::DOWN_HOOK, array( __CLASS__, 'revert_batch' ) );
	}

	/**
	 * Stored state with defaults. `status` is `none`, `running`, `complete`, `stale`, `reverting` or `reverted`.
	 *
	 * @return array
	 */
	public static function state() {
		$state = get_option( self::OPTION, array() );
		return array_merge(
			array(
				'status'      => 'none',
				'cursor'      => 0,
				'phase'       => 'walk',
				'created'     => 0,
				'updated'     => 0,
				'unchanged'   => 0,
				'duplicates'  => 0,
				'orphans'     => 0,
				'removed'     => 0,
				'batches'     => 0,
				'error'       => '',
				'started_at'  => '',
				'finished_at' => '',
			),
			is_array( $state ) ? $state : array()
		);
	}

	/**
	 * Save the state.
	 *
	 * @param array $state State.
	 */
	private static function save( array $state ) {
		update_option( self::OPTION, $state, true );
	}

	/**
	 * Whether readers may use the new table: the migration finished, the table exists for this prefix, and
	 * no legacy row newer than the newest projection exists (a writer outside the service inserted it, or
	 * its projection is still being written). A lag older than {@see self::LAG_GRACE} seconds starts a sweep.
	 *
	 * @return bool
	 */
	public static function complete() {
		$complete = 'complete' === self::state()['status'] && EnrolmentSchema::exists() && ! self::lagging();
		/**
		 * Filters whether enrolment readers use `ohmylms_enrolments` instead of `ohmylms_user_enrollment`.
		 *
		 * @param bool $complete Whether the migration finished.
		 */
		return (bool) apply_filters( 'ohmylms_enrolments_read_new', $complete );
	}

	/**
	 * Whether the migration finished and the table exists (no lag check): writers then also create
	 * projections that are missing.
	 *
	 * @return bool
	 */
	public static function finished() {
		return 'complete' === self::state()['status'] && EnrolmentSchema::exists();
	}

	/**
	 * Whether the newest legacy row has no projection yet. Remembers the first sighting and schedules a
	 * sweep when the same row is still missing after the grace period.
	 *
	 * @return bool
	 */
	private static function lagging() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Two index-only MAX() lookups; must be fresh.
		$newest = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT IF((SELECT COALESCE(MAX(id),0) FROM %i) > (SELECT COALESCE(MAX(legacy_id),0) FROM %i), (SELECT MAX(id) FROM %i), 0)', EnrolmentMirror::legacy_table(), EnrolmentSchema::table(), EnrolmentMirror::legacy_table() ) );
		if ( ! $newest ) {
			return false;
		}
		$seen = get_transient( 'ohmylms_enrolments_lag' );
		if ( ! is_array( $seen ) || (int) $seen['id'] !== $newest ) {
			set_transient(
				'ohmylms_enrolments_lag',
				array(
					'id' => $newest,
					'at' => time(),
				),
				HOUR_IN_SECONDS
			);
		} elseif ( time() - (int) $seen['at'] >= self::LAG_GRACE ) {
			delete_transient( 'ohmylms_enrolments_lag' );
			self::mark_stale( 'sweep' );
		}
		return true;
	}

	/**
	 * Start (or restart after a revert or a failed projection) and schedule the first batch.
	 *
	 * @return array State.
	 */
	public static function start() {
		$state = self::state();
		if ( in_array( $state['status'], array( 'running', 'complete' ), true ) ) {
			self::schedule( self::UP_HOOK );
			return $state;
		}
		if ( ! EnrolmentSchema::exists() && ! EnrolmentSchema::install() ) {
			$state['error'] = 'The enrolments table could not be created.';
			self::save( $state );
			return $state;
		}
		wp_clear_scheduled_hook( self::DOWN_HOOK );
		$state = array_merge(
			self::state(),
			array(
				'status'      => 'running',
				'cursor'      => 0,
				'phase'       => 'walk',
				'created'     => 0,
				'updated'     => 0,
				'unchanged'   => 0,
				'duplicates'  => 0,
				'orphans'     => 0,
				'batches'     => 0,
				'error'       => '',
				'started_at'  => current_time( 'mysql', true ),
				'finished_at' => '',
			)
		);
		self::save( $state );
		self::schedule( self::UP_HOOK );
		return $state;
	}

	/**
	 * Stop reading the new table and repair it: after a failed projection write the whole legacy table is
	 * walked again (rows are updated in place); for rows a writer outside the service inserted, only the
	 * sweep of rows without a projection runs.
	 *
	 * @param string $phase `walk` or `sweep`.
	 */
	public static function mark_stale( $phase = 'walk' ) {
		$state = self::state();
		if ( 'complete' !== $state['status'] ) {
			return;
		}
		$state['status'] = 'stale';
		$state['cursor'] = 0;
		$state['phase']  = 'sweep' === $phase ? 'sweep' : 'walk';
		self::save( $state );
		self::schedule( self::UP_HOOK );
	}

	/**
	 * Schedule a batch unless one is pending.
	 *
	 * @param string $hook  Cron hook.
	 * @param int    $delay Seconds from now.
	 */
	private static function schedule( $hook, $delay = 10 ) {
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( time() + $delay, $hook );
		}
	}

	/**
	 * Re-arm a lost cron event (cleared by a cron plugin, a restart before it fired, a broken object cache)
	 * once the database update has run: start the migration if it never started, resume it if it stopped,
	 * finish a revert that stopped. A completed or reverted migration is left alone.
	 */
	public static function watchdog() {
		$db_version = get_option( 'ohmylms_db_version', null );
		if ( is_null( $db_version ) || version_compare( $db_version, self::DB_VERSION, '<' ) ) {
			return;
		}
		$status = self::state()['status'];
		if ( 'none' === $status ) {
			self::start();
		} elseif ( in_array( $status, array( 'running', 'stale' ), true ) ) {
			self::schedule( self::UP_HOOK );
		} elseif ( 'reverting' === $status ) {
			self::schedule( self::DOWN_HOOK );
		}
	}

	/**
	 * Run one up batch (the cron handler). Holds a named lock so two runners never overlap.
	 *
	 * @param int|null $size Legacy rows per batch.
	 * @return array State.
	 */
	public static function run_batch( $size = null ) {
		global $wpdb;

		$state = self::state();
		if ( ! in_array( $state['status'], array( 'running', 'stale' ), true ) ) {
			return $state;
		}
		$lock = 'ohmylms_enrolments_' . md5( $wpdb->prefix );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Advisory lock.
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return $state;
		}
		try {
			$state = self::step( $state, max( 1, (int) ( $size ? $size : self::BATCH ) ) );
			self::save( $state );
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Advisory lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		if ( in_array( $state['status'], array( 'running', 'stale' ), true ) ) {
			self::schedule( self::UP_HOOK, $state['error'] ? 60 : 1 );
		}
		return $state;
	}

	/**
	 * Run up batches now until the migration completes or the limit is reached (tests and WP-CLI).
	 *
	 * @param int      $max_batches Batch limit.
	 * @param int|null $size        Rows per batch.
	 * @return array State.
	 */
	public static function run( $max_batches = PHP_INT_MAX, $size = null ) {
		$state = self::state();
		$runs  = 0;
		while ( $runs < $max_batches && in_array( $state['status'], array( 'running', 'stale' ), true ) ) {
			$state = self::run_batch( $size );
			++$runs;
			if ( $state['error'] ) {
				break;
			}
		}
		return $state;
	}

	/**
	 * One batch: walk the next legacy rows by ID, then sweep rows still missing, then finish.
	 *
	 * @param array $state State.
	 * @param int   $size  Rows per batch.
	 * @return array State.
	 */
	private static function step( array $state, $size ) {
		global $wpdb;

		$legacy = EnrolmentMirror::legacy_table();
		$table  = EnrolmentSchema::table();
		if ( ! EnrolmentSchema::exists() ) {
			$state['error'] = 'The enrolments table is missing.';
			return $state;
		}
		$state['error'] = '';
		++$state['batches'];
		$next = $state;

		try {
			$done = Transaction::run(
				static function () use ( $wpdb, $legacy, $table, &$next, $size ) {
					if ( 'walk' === $next['phase'] ) {
						// Locking the batch makes a concurrent legacy write wait until its projection exists.
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Migration batch.
						$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d FOR UPDATE', $legacy, (int) $next['cursor'], $size ), ARRAY_A );
					} else {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Migration sweep.
						$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT l.* FROM %i l LEFT JOIN %i m ON m.legacy_id = l.id WHERE m.id IS NULL ORDER BY l.id ASC LIMIT %d FOR UPDATE', $legacy, $table, $size ), ARRAY_A );
					}
					foreach ( $rows as $row ) {
						$outcome = EnrolmentMirror::write( $row, null, true, true );
						if ( 'failed' === $outcome ) {
							// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Stored in the migration state, never printed.
							throw new \RuntimeException( sprintf( 'Legacy enrolment %d could not be projected: %s', (int) $row['id'], $wpdb->last_error ) );
						}
						if ( false !== strpos( $outcome, '+duplicate' ) ) {
							++$next['duplicates'];
						}
						$key = strtok( $outcome, '+' );
						if ( isset( $next[ $key ] ) ) {
							++$next[ $key ];
						}
						if ( 'walk' === $next['phase'] ) {
							$next['cursor'] = (int) $row['id'];
						}
					}
					return count( $rows ) < $size;
				}
			);
		} catch ( \Throwable $error ) {
			$state['error'] = $error->getMessage();
			return $state;
		}
		$state = $next;

		if ( ! $done ) {
			return $state;
		}
		if ( 'walk' === $state['phase'] ) {
			$state['phase'] = 'sweep';
			return $state;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Projections whose legacy row was deleted by a writer outside the service.
		$state['orphans']    += (int) $wpdb->query( $wpdb->prepare( 'DELETE m FROM %i m LEFT JOIN %i l ON l.id = m.legacy_id WHERE m.legacy_id IS NOT NULL AND l.id IS NULL', $table, $legacy ) );
		$state['status']      = 'complete';
		$state['finished_at'] = current_time( 'mysql', true );
		return $state;
	}

	/**
	 * Start the down step: readers fall back to legacy storage at once, then batches remove the rows the
	 * migration created. Rows written by the services (`migrated = 0`) stay.
	 *
	 * @return array State.
	 */
	public static function revert() {
		wp_clear_scheduled_hook( self::UP_HOOK );
		$state            = self::state();
		$state['status']  = 'reverting';
		$state['removed'] = 0;
		$state['error']   = '';
		self::save( $state );
		self::schedule( self::DOWN_HOOK );
		return $state;
	}

	/**
	 * Run one down batch (the cron handler).
	 *
	 * @param int|null $size Rows per batch.
	 * @return array State.
	 */
	public static function revert_batch( $size = null ) {
		global $wpdb;

		$state = self::state();
		if ( 'reverting' !== $state['status'] ) {
			return $state;
		}
		$size = max( 1, (int) ( $size ? $size : self::DOWN_BATCH ) );
		if ( EnrolmentSchema::exists() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes only rows this migration created.
			$removed = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE migrated = 1 ORDER BY id LIMIT %d', EnrolmentSchema::table(), $size ) );
			if ( false === $removed ) {
				$state['error'] = $wpdb->last_error;
				self::save( $state );
				self::schedule( self::DOWN_HOOK, 60 );
				return $state;
			}
		} else {
			$removed = 0;
		}
		$state['removed'] += (int) $removed;
		if ( $removed < $size ) {
			$state['status']      = 'reverted';
			$state['cursor']      = 0;
			$state['phase']       = 'walk';
			$state['finished_at'] = current_time( 'mysql', true );
		} else {
			self::schedule( self::DOWN_HOOK, 1 );
		}
		self::save( $state );
		return $state;
	}

	/**
	 * Run down batches now until the revert finishes (tests and WP-CLI).
	 *
	 * @param int|null $size Rows per batch.
	 * @return array State.
	 */
	public static function run_down( $size = null ) {
		$state = self::state();
		while ( 'reverting' === $state['status'] && '' === $state['error'] ) {
			$state = self::revert_batch( $size );
		}
		return $state;
	}

	/**
	 * Compare legacy storage with the new table: totals, rows without a projection, projections without a
	 * legacy row, status mismatches, and counts by new status and by source.
	 *
	 * @return array
	 */
	public static function compare() {
		global $wpdb;

		$legacy = EnrolmentMirror::legacy_table();
		$table  = EnrolmentSchema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Verification report over plugin tables.
		$report = array(
			'legacy'     => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $legacy ) ),
			'legacy_by'  => array(),
			'enrolments' => 0,
			'migrated'   => 0,
			'missing'    => 0,
			'orphans'    => 0,
			'mismatched' => 0,
			'by_status'  => array(),
			'by_source'  => array(),
		);
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, progress, COUNT(*) AS n FROM %i GROUP BY status, progress', $legacy ), ARRAY_A ) as $row ) {
			$key                         = EnrolmentMirror::status( $row['status'], $row['progress'] );
			$report['legacy_by'][ $key ] = ( $report['legacy_by'][ $key ] ?? 0 ) + (int) $row['n'];
		}
		ksort( $report['legacy_by'] );
		if ( ! EnrolmentSchema::exists() ) {
			return $report;
		}
		$report['enrolments'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		$report['migrated']   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE migrated = 1', $table ) );
		$report['missing']    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i l LEFT JOIN %i m ON m.legacy_id = l.id WHERE m.id IS NULL', $legacy, $table ) );
		$report['orphans']    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i m LEFT JOIN %i l ON l.id = m.legacy_id WHERE l.id IS NULL', $table, $legacy ) );
		$pairs                = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT l.status AS legacy_status, l.progress, m.status FROM %i l JOIN %i m ON m.legacy_id = l.id', $legacy, $table ), ARRAY_A );
		foreach ( $pairs as $pair ) {
			if ( EnrolmentMirror::status( $pair['legacy_status'], $pair['progress'] ) !== $pair['status'] ) {
				++$report['mismatched'];
			}
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS n FROM %i GROUP BY status ORDER BY status', $table ), ARRAY_A ) as $row ) {
			$report['by_status'][ $row['status'] ] = (int) $row['n'];
		}
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT source, source_type, COUNT(*) AS n FROM %i GROUP BY source, source_type ORDER BY source, source_type', $table ), ARRAY_A ) as $row ) {
			$report['by_source'][ $row['source'] . ( '' !== $row['source_type'] ? '/' . $row['source_type'] : '' ) ] = (int) $row['n'];
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		return $report;
	}
}
