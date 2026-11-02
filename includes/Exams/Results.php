<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Exam results release: the per-exam policy, per-attempt releases, learner views and their log.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\ExamImport\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * An exam shows results immediately, from a date, or when its author releases them. This class is the
 * only authority for exam attempts: `AssessmentSettings::feedback_visible()` asks it through the
 * `ohmylms_attempt_feedback_visible` filter, so every template and endpoint that checks feedback
 * visibility (exam player, lesson quiz table, answer review, Quiz Player block, mark scheme, learner
 * history) follows it. The quiz-wide `released` flag of the backing quiz is ignored for exams.
 *
 * Releases live in `{prefix}ohmylms_exam_results` (one row per attempt) and every release, undo, policy
 * change, notification and first learner view is logged in `{prefix}ohmylms_exam_results_log`. A manual
 * release can be undone until the learner has seen the results; once seen, results stay visible whatever
 * the policy becomes. Writes run under the exam lock; the view claim and the undo are single conditional
 * updates, so exactly one of them wins a race.
 */
final class Results {

	const POLICY_META       = '_ohmylms_exam_results_policy';
	const MODES             = array( 'immediate', 'scheduled', 'manual' );
	const DB_VERSION        = '1';
	const DB_VERSION_OPTION = 'ohmylms_exam_results_db';
	const HOOK              = 'ohmylms_exam_results_scheduled_release';
	const MAX_BATCH         = 200;

	/**
	 * Exam of each backing quiz looked up in this request.
	 *
	 * @var array<int,int>
	 */
	private static $exams = array();

	/** Register the visibility authority, the scheduled release and the graded-email guard. */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'install' ), 5 );
		add_filter( 'ohmylms_attempt_feedback_visible', array( __CLASS__, 'feedback_filter' ), 10, 2 );
		add_filter( 'ohmylms_quiz_graded_email', array( __CLASS__, 'graded_email_filter' ), 10, 3 );
		add_action( self::HOOK, array( __CLASS__, 'run_schedule' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'forget' ) );
	}

	/**
	 * Release table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_exam_results';
	}

	/**
	 * Log table.
	 *
	 * @return string
	 */
	public static function log_table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_exam_results_log';
	}

	/**
	 * Create or upgrade both tables when their version changed.
	 *
	 * @return bool Whether the tables are usable.
	 */
	public static function install() {
		static $ready = null;
		if ( true === $ready ) {
			return true;
		}
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) ) {
			$ready = true;
			return true;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
 attempt_id bigint unsigned NOT NULL,
 exam_id bigint unsigned NOT NULL,
 student_id bigint unsigned NOT NULL DEFAULT 0,
 released_at datetime NULL,
 released_by bigint unsigned NOT NULL DEFAULT 0,
 source varchar(12) NOT NULL DEFAULT '',
 viewed_at datetime NULL,
 notified_at datetime NULL,
 PRIMARY KEY  (attempt_id),
 KEY exam (exam_id)
) ENGINE=InnoDB $collate;"
		);
		dbDelta(
			'CREATE TABLE ' . self::log_table() . " (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 exam_id bigint unsigned NOT NULL,
 attempt_id bigint unsigned NOT NULL DEFAULT 0,
 actor_id bigint unsigned NOT NULL DEFAULT 0,
 action varchar(20) NOT NULL DEFAULT '',
 detail varchar(190) NOT NULL DEFAULT '',
 created_at datetime NOT NULL,
 PRIMARY KEY  (id),
 KEY exam_time (exam_id,id)
) ENGINE=InnoDB $collate;"
		);
		$ready = true;
		foreach ( array( self::table(), self::log_table() ) as $table ) {
			$ready = $ready && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		if ( $ready ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		}
		return $ready;
	}

	// Policy.

	/**
	 * The exam's release policy. Exams saved before policies existed derive it from the backing quiz.
	 *
	 * @param int $exam_id Exam ID.
	 * @return array{mode:string,release_at:string} `release_at` is UTC `Y-m-d H:i:s` or empty.
	 */
	public static function policy( $exam_id ) {
		$stored = get_post_meta( (int) $exam_id, self::POLICY_META, true );
		if ( is_array( $stored ) && in_array( $stored['mode'] ?? '', self::MODES, true ) ) {
			return array(
				'mode'       => $stored['mode'],
				'release_at' => 'scheduled' === $stored['mode'] ? (string) ( $stored['release_at'] ?? '' ) : '',
			);
		}
		$data     = Editor::data( $exam_id );
		$settings = $data ? \OhMyLMS\Assessment\AssessmentSettings::get( (int) $data['quiz_id'] ) : array();
		if ( ! empty( $settings['released'] ) || 'immediate' === ( $settings['feedback_release'] ?? 'manual' ) ) {
			return array(
				'mode'       => 'immediate',
				'release_at' => '',
			);
		}
		if ( 'after_close' === ( $settings['feedback_release'] ?? '' ) && ! empty( $settings['available_until'] ) ) {
			return array(
				'mode'       => 'scheduled',
				'release_at' => (string) $settings['available_until'],
			);
		}
		return array(
			'mode'       => 'manual',
			'release_at' => '',
		);
	}

	/**
	 * Change the policy. Attempts a learner has already seen stay visible.
	 *
	 * @param int    $exam_id    Exam ID.
	 * @param string $mode       immediate|scheduled|manual.
	 * @param string $release_at RFC 3339 time, required for `scheduled`.
	 * @return array|\WP_Error The policy.
	 */
	public static function save_policy( $exam_id, $mode, $release_at = '' ) {
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return self::error( 'invalid_policy', __( 'Choose when results are shown: immediately, on a date, or when you release them.', 'ohmylms' ), 422 );
		}
		$at = '';
		if ( 'scheduled' === $mode ) {
			$time = is_string( $release_at ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})$/D', $release_at ) ? strtotime( $release_at ) : false;
			if ( ! $time || $time < strtotime( '2000-01-01 UTC' ) || $time > time() + 10 * YEAR_IN_SECONDS ) {
				return self::error( 'invalid_policy', __( 'Enter the release date and time (UTC), within the next ten years.', 'ohmylms' ), 422 );
			}
			$at = gmdate( 'Y-m-d H:i:s', $time );
		}
		return Jobs::locked(
			$exam_id,
			static function () use ( $exam_id, $mode, $at ) {
				$before = self::policy( $exam_id );
				$policy = array(
					'mode'       => $mode,
					'release_at' => $at,
				);
				if ( false === update_post_meta( $exam_id, self::POLICY_META, $policy ) && get_post_meta( $exam_id, self::POLICY_META, true ) !== $policy ) {
					return self::error( 'storage', __( 'The results policy could not be saved.', 'ohmylms' ), 500 );
				}
				wp_clear_scheduled_hook( self::HOOK, array( (int) $exam_id ) );
				if ( 'scheduled' === $mode ) {
					wp_schedule_single_event( max( time(), strtotime( $at . ' UTC' ) ), self::HOOK, array( (int) $exam_id ) );
				}
				if ( $before !== $policy ) {
					self::log( $exam_id, 0, 'policy', $mode . ( $at ? ' ' . $at : '' ) );
					self::changed( $exam_id );
				}
				return $policy;
			}
		);
	}

	// Visibility.

	/**
	 * Exam ID backing a quiz, or 0.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int
	 */
	public static function exam_of_quiz( $quiz_id ) {
		$quiz_id = (int) $quiz_id;
		if ( isset( self::$exams[ $quiz_id ] ) ) {
			return self::$exams[ $quiz_id ];
		}
		$exam_id = (int) get_post_meta( $quiz_id, '_ohmylms_exam_id', true );
		if ( ! $exam_id || Library::TYPE !== get_post_type( $exam_id ) ) {
			return 0;
		}
		self::$exams[ $quiz_id ] = $exam_id;
		return $exam_id;
	}

	/**
	 * One attempt row, uncached.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array|null
	 */
	private static function attempt( $attempt_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, quiz_id, student_id, status FROM %i WHERE id=%d', $wpdb->prefix . 'ohmylms_quiz_attempts', (int) $attempt_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live release decision.
		return $row ? $row : null;
	}

	/**
	 * Release row of an attempt, uncached.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array|null
	 */
	private static function record( $attempt_id ) {
		global $wpdb;
		if ( ! self::install() ) {
			return null;
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE attempt_id=%d', self::table(), (int) $attempt_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live release decision.
		return $row ? $row : null;
	}

	/**
	 * Release state of an attempt.
	 *
	 * @param array      $policy  Exam policy.
	 * @param array      $attempt Attempt row (status).
	 * @param array|null $record  Release row.
	 * @param int|null   $now     Current time.
	 * @return string in_progress|awaiting_marking|pending|released|viewed
	 */
	public static function state( array $policy, array $attempt, $record, $now = null ) {
		$now = null === $now ? time() : $now;
		if ( 'in-progress' === ( $attempt['status'] ?? '' ) ) {
			return 'in_progress';
		}
		if ( ! empty( $record['viewed_at'] ) ) {
			return 'viewed';
		}
		if ( ! empty( $record['released_at'] ) || 'immediate' === $policy['mode'] ) {
			return 'released';
		}
		if ( 'in-review' === ( $attempt['status'] ?? '' ) ) {
			return 'awaiting_marking';
		}
		if ( 'scheduled' === $policy['mode'] && $policy['release_at'] && $now >= strtotime( $policy['release_at'] . ' UTC' ) ) {
			return 'released';
		}
		return 'pending';
	}

	/**
	 * Whether the learner may see the marks, feedback and mark scheme of an exam attempt now.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return bool|null Null when the attempt does not belong to an exam.
	 */
	public static function visible( $attempt_id ) {
		$attempt = self::attempt( $attempt_id );
		$exam_id = $attempt ? self::exam_of_quiz( $attempt['quiz_id'] ) : 0;
		if ( ! $exam_id ) {
			return null;
		}
		return in_array( self::state( self::policy( $exam_id ), $attempt, self::record( $attempt_id ) ), array( 'released', 'viewed' ), true );
	}

	/**
	 * The attempts among the given ones whose exam results learners may not see yet, decided as
	 * {@see self::visible()} does, in one query per 500 attempts. Attempts outside exams are never held.
	 *
	 * @param int[] $attempt_ids Attempt IDs.
	 * @return int[] Held attempt IDs.
	 */
	public static function held( array $attempt_ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', $attempt_ids ) ) ) );
		if ( ! $ids ) {
			return array();
		}
		$installed = self::install();
		$policies  = array();
		$held      = array();
		foreach ( array_chunk( $ids, 500 ) as $chunk ) {
			$in = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			if ( $installed ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Only %d placeholders are interpolated; live release decision.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT a.id, a.quiz_id, a.status, r.released_at, r.viewed_at FROM %i a LEFT JOIN %i r ON r.attempt_id = a.id WHERE a.id IN ($in)", array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts', self::table() ), $chunk ) ), ARRAY_A );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Only %d placeholders are interpolated; live release decision.
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, quiz_id, status, NULL AS released_at, NULL AS viewed_at FROM %i WHERE id IN ($in)", array_merge( array( $wpdb->prefix . 'ohmylms_quiz_attempts' ), $chunk ) ), ARRAY_A );
			}
			foreach ( (array) $rows as $row ) {
				$exam_id = self::exam_of_quiz( (int) $row['quiz_id'] );
				if ( ! $exam_id ) {
					continue;
				}
				if ( ! isset( $policies[ $exam_id ] ) ) {
					$policies[ $exam_id ] = self::policy( $exam_id );
				}
				$state = self::state( $policies[ $exam_id ], $row, $row['released_at'] || $row['viewed_at'] ? $row : null );
				if ( ! in_array( $state, array( 'released', 'viewed' ), true ) ) {
					$held[] = (int) $row['id'];
				}
			}
		}
		return $held;
	}

	/**
	 * Announce that which attempts of an exam learners may see has changed.
	 *
	 * @param int $exam_id Exam ID.
	 * @return void
	 */
	private static function changed( $exam_id ) {
		/**
		 * Fires after a release, undo, policy change or scheduled release changed which results of an exam
		 * learners may see. Cached views of exam scores (the gradebook) are recomputed.
		 *
		 * @param int $exam_id Exam ID.
		 */
		do_action( 'ohmylms_exam_results_changed', (int) $exam_id );
	}

	/**
	 * Release state of an exam attempt for the learner's pending notice.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return array{state:string,mode:string,release_at:string}|null Null outside exams.
	 */
	public static function learner_state( $attempt_id ) {
		$attempt = self::attempt( $attempt_id );
		$exam_id = $attempt ? self::exam_of_quiz( $attempt['quiz_id'] ) : 0;
		if ( ! $exam_id ) {
			return null;
		}
		$policy = self::policy( $exam_id );
		return array(
			'state'      => self::state( $policy, $attempt, self::record( $attempt_id ) ),
			'mode'       => $policy['mode'],
			'release_at' => $policy['release_at'],
		);
	}

	/**
	 * `ohmylms_attempt_feedback_visible`: decide for exam attempts, leave other quizzes alone.
	 *
	 * @param bool|null $visible    Decision of an earlier filter, or null.
	 * @param int       $attempt_id Attempt ID.
	 * @return bool|null
	 */
	public static function feedback_filter( $visible, $attempt_id ) {
		$decision = self::visible( (int) $attempt_id );
		return null === $decision ? $visible : $decision;
	}

	/**
	 * `ohmylms_quiz_graded_email`: no graded email (it carries the score) for exams whose results are
	 * held; the release notification follows instead.
	 *
	 * @param bool $send       Whether to send.
	 * @param int  $quiz_id    Quiz ID.
	 * @param int  $student_id Learner.
	 * @return bool
	 */
	public static function graded_email_filter( $send, $quiz_id, $student_id ) {
		$exam_id = self::exam_of_quiz( (int) $quiz_id );
		if ( ! $send || ! $exam_id ) {
			return $send;
		}
		global $wpdb;
		$latest = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE quiz_id=%d AND student_id=%d ORDER BY id DESC LIMIT 1', $wpdb->prefix . 'ohmylms_quiz_attempts', (int) $quiz_id, (int) $student_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live release decision.
		return $latest && true === self::visible( $latest );
	}

	/**
	 * Record that the learner is being shown released results, then confirm they are still released.
	 * Call before rendering marks; render them only when this returns true. The claim and an undo are
	 * conditional updates of the same row, so a release undone first is never shown.
	 *
	 * @param int $attempt_id Attempt ID.
	 * @return bool Whether the results may be shown.
	 */
	public static function claim_view( $attempt_id ) {
		global $wpdb;
		$attempt = self::attempt( $attempt_id );
		$exam_id = $attempt ? self::exam_of_quiz( $attempt['quiz_id'] ) : 0;
		if ( ! $exam_id || ! self::install() ) {
			return false;
		}
		$record = self::record( $attempt_id );
		$state  = self::state( self::policy( $exam_id ), $attempt, $record );
		if ( 'viewed' === $state ) {
			return true;
		}
		if ( 'released' !== $state ) {
			return false;
		}
		$now = current_time( 'mysql', true );
		if ( ! empty( $record['released_at'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Conditional claim that races with an undo.
			$claimed = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET viewed_at=%s WHERE attempt_id=%d AND viewed_at IS NULL AND released_at IS NOT NULL', self::table(), $now, (int) $attempt_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Released by policy: record the first view.
			$claimed = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (attempt_id, exam_id, student_id, viewed_at) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE viewed_at = IF(viewed_at IS NULL, VALUES(viewed_at), viewed_at)', self::table(), (int) $attempt_id, $exam_id, (int) $attempt['student_id'], $now ) );
		}
		if ( $claimed ) {
			self::log( $exam_id, (int) $attempt_id, 'view', '', (int) $attempt['student_id'] );
		}
		$after = self::record( $attempt_id );
		return ! empty( $after['viewed_at'] );
	}

	// Author actions.

	/**
	 * Release attempts now, optionally emailing each learner once.
	 *
	 * @param int        $exam_id Exam ID.
	 * @param int[]|null $ids     Attempts, or null for every pending attempt (up to MAX_BATCH).
	 * @param bool       $notify  Email learners whose results become visible.
	 * @return array|\WP_Error {released: int[], skipped: {id, reason}[], notified: int, remaining: int}
	 */
	public static function release( $exam_id, $ids, $notify ) {
		if ( ! self::install() ) {
			return self::error( 'storage', __( 'The results tables are unavailable.', 'ohmylms' ), 503 );
		}
		$outcome = Jobs::locked(
			$exam_id,
			static function () use ( $exam_id, $ids ) {
				global $wpdb;
				$data   = Editor::data( $exam_id );
				$policy = self::policy( $exam_id );
				$rows   = self::attempt_rows( (int) $data['quiz_id'], null === $ids ? null : array_map( 'intval', $ids ) );
				$result = array(
					'released' => array(),
					'skipped'  => array(),
				);
				$found  = array();
				foreach ( $rows as $row ) {
					$found[ (int) $row['id'] ] = true;
					$state                     = self::state( $policy, $row, $row['released_at'] || $row['viewed_at'] ? $row : null );
					if ( 'pending' !== $state ) {
						if ( null !== $ids ) {
							$result['skipped'][] = array(
								'id'     => (int) $row['id'],
								'reason' => in_array( $state, array( 'released', 'viewed' ), true ) ? 'already_visible' : $state,
							);
						}
						continue;
					}
					if ( count( $result['released'] ) >= self::MAX_BATCH ) {
						continue;
					}
					$now = current_time( 'mysql', true );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One release row per attempt.
					$saved = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (attempt_id, exam_id, student_id, released_at, released_by, source) VALUES (%d, %d, %d, %s, %d, %s) ON DUPLICATE KEY UPDATE released_at=VALUES(released_at), released_by=VALUES(released_by), source=VALUES(source)', self::table(), (int) $row['id'], (int) $exam_id, (int) $row['student_id'], $now, get_current_user_id(), 'manual' ) );
					if ( false === $saved ) {
						$result['skipped'][] = array(
							'id'     => (int) $row['id'],
							'reason' => 'storage',
						);
						continue;
					}
					$result['released'][] = (int) $row['id'];
					self::log( $exam_id, (int) $row['id'], 'release', 'manual' );
				}
				foreach ( null === $ids ? array() : array_diff( array_map( 'intval', $ids ), array_keys( $found ) ) as $missing ) {
					$result['skipped'][] = array(
						'id'     => (int) $missing,
						'reason' => 'not_found',
					);
				}
				return $result;
			}
		);
		if ( is_wp_error( $outcome ) ) {
			return $outcome;
		}
		if ( $outcome['released'] ) {
			self::changed( $exam_id );
		}
		$outcome['notified']  = $notify ? self::notify( $exam_id, $outcome['released'] ) : 0;
		$outcome['remaining'] = self::counts( $exam_id )['pending'];
		return $outcome;
	}

	/**
	 * Undo manual releases the learner has not seen yet.
	 *
	 * @param int   $exam_id Exam ID.
	 * @param int[] $ids     Attempts.
	 * @return array|\WP_Error {revoked: int[], skipped: {id, reason}[]}
	 */
	public static function revoke( $exam_id, array $ids ) {
		if ( ! self::install() ) {
			return self::error( 'storage', __( 'The results tables are unavailable.', 'ohmylms' ), 503 );
		}
		$outcome = Jobs::locked(
			$exam_id,
			static function () use ( $exam_id, $ids ) {
				global $wpdb;
				$data   = Editor::data( $exam_id );
				$policy = self::policy( $exam_id );
				$rows   = array();
				foreach ( self::attempt_rows( (int) $data['quiz_id'], array_map( 'intval', $ids ) ) as $row ) {
					$rows[ (int) $row['id'] ] = $row;
				}
				$result = array(
					'revoked' => array(),
					'skipped' => array(),
				);
				foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
					$row = $rows[ $id ] ?? null;
					if ( ! $row ) {
						$reason = 'not_found';
					} elseif ( $row['viewed_at'] ) {
						$reason = 'viewed';
					} elseif ( ! $row['released_at'] ) {
						$reason = 'released' === self::state( $policy, $row, null ) ? 'policy' : 'not_released';
					} else {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Conditional undo that races with a learner view.
						$reason = $wpdb->query( $wpdb->prepare( "UPDATE %i SET released_at=NULL, released_by=0, source='' WHERE attempt_id=%d AND viewed_at IS NULL AND released_at IS NOT NULL", self::table(), $id ) ) ? '' : 'viewed';
					}
					if ( '' === $reason ) {
						$result['revoked'][] = $id;
						self::log( $exam_id, $id, 'revoke', '' );
					} else {
						$result['skipped'][] = array(
							'id'     => $id,
							'reason' => $reason,
						);
					}
				}
				return $result;
			}
		);
		if ( ! is_wp_error( $outcome ) && $outcome['revoked'] ) {
			self::changed( $exam_id );
		}
		return $outcome;
	}

	/**
	 * Scheduled release: record and notify every attempt the date released. Runs in batches.
	 *
	 * @param int $exam_id Exam ID.
	 * @return void
	 */
	public static function run_schedule( $exam_id ) {
		$exam_id = (int) $exam_id;
		$policy  = Editor::data( $exam_id ) ? self::policy( $exam_id ) : null;
		if ( ! $policy || 'scheduled' !== $policy['mode'] || time() < strtotime( $policy['release_at'] . ' UTC' ) || ! self::install() ) {
			return;
		}
		global $wpdb;
		$data   = Editor::data( $exam_id );
		$rows   = self::attempt_rows( (int) $data['quiz_id'], null );
		$marked = array();
		foreach ( $rows as $row ) {
			if ( 'released' !== self::state( $policy, $row, $row['released_at'] || $row['viewed_at'] ? $row : null ) || $row['released_at'] || $row['notified_at'] || count( $marked ) >= self::MAX_BATCH ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One release row per attempt.
			$wpdb->query( $wpdb->prepare( 'INSERT INTO %i (attempt_id, exam_id, student_id, released_at, source) VALUES (%d, %d, %d, %s, %s) ON DUPLICATE KEY UPDATE released_at=IFNULL(released_at, VALUES(released_at)), source=IF(source=\'\', VALUES(source), source)', self::table(), (int) $row['id'], $exam_id, (int) $row['student_id'], current_time( 'mysql', true ), 'schedule' ) );
			self::log( $exam_id, (int) $row['id'], 'release', 'schedule' );
			$marked[] = (int) $row['id'];
		}
		if ( $marked ) {
			self::changed( $exam_id );
		}
		self::notify( $exam_id, $marked );
		if ( count( $marked ) >= self::MAX_BATCH ) {
			wp_schedule_single_event( time() + 60, self::HOOK, array( $exam_id ) );
		}
	}

	/**
	 * Email each learner of the given attempts once, through the plugin's email settings.
	 *
	 * @param int   $exam_id Exam ID.
	 * @param int[] $ids     Attempts whose results became visible.
	 * @return int Emails sent.
	 */
	private static function notify( $exam_id, array $ids ) {
		global $wpdb;
		$sent = 0;
		if ( ! $ids || ! ResultsEmail::enabled() ) {
			return 0;
		}
		foreach ( $ids as $id ) {
			$record = self::record( $id );
			if ( ! $record || $record['notified_at'] ) {
				continue;
			}
			$learner = get_userdata( (int) $record['student_id'] );
			if ( ! $learner || ! ResultsEmail::send( $learner, (int) $exam_id ) ) {
				self::log( $exam_id, (int) $id, 'notify_failed', '' );
				continue;
			}
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET notified_at=%s WHERE attempt_id=%d', self::table(), current_time( 'mysql', true ), (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remember the email was sent.
			self::log( $exam_id, (int) $id, 'notify', '' );
			++$sent;
		}
		return $sent;
	}

	// Reads.

	/**
	 * Attempts of the backing quiz with their release rows, newest first.
	 *
	 * @param int        $quiz_id  Quiz ID.
	 * @param int[]|null $ids      Only these attempts, or all.
	 * @param int        $limit    Rows (0 for no limit).
	 * @param int        $offset   Offset.
	 * @return array[]
	 */
	private static function attempt_rows( $quiz_id, $ids, $limit = 0, $offset = 0 ) {
		global $wpdb;
		if ( is_array( $ids ) && ! $ids ) {
			return array();
		}
		$args  = array( $wpdb->prefix . 'ohmylms_quiz_attempts', $wpdb->users, self::table(), (int) $quiz_id );
		$where = '';
		if ( is_array( $ids ) ) {
			$where = ' AND a.id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')';
			$args  = array_merge( $args, $ids );
		}
		$page = '';
		if ( $limit ) {
			$page   = ' LIMIT %d OFFSET %d';
			$args[] = (int) $limit;
			$args[] = (int) $offset;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- Placeholders only, values passed as one array; live release list.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT a.id, a.student_id, a.status, a.total, a.start_date, a.end_date, u.display_name, r.released_at, r.released_by, r.source, r.viewed_at, r.notified_at FROM %i a LEFT JOIN %i u ON u.ID = a.student_id LEFT JOIN %i r ON r.attempt_id = a.id WHERE a.quiz_id = %d' . $where . ' ORDER BY a.id DESC' . $page, $args ), ARRAY_A );
	}

	/**
	 * Attempt counts by release state.
	 *
	 * @param int $exam_id Exam ID.
	 * @return array<string,int>
	 */
	public static function counts( $exam_id ) {
		$data   = Editor::data( $exam_id );
		$policy = self::policy( $exam_id );
		$counts = array_fill_keys( array( 'in_progress', 'awaiting_marking', 'pending', 'released', 'viewed' ), 0 );
		foreach ( self::attempt_rows( (int) $data['quiz_id'], null ) as $row ) {
			++$counts[ self::state( $policy, $row, $row['released_at'] || $row['viewed_at'] ? $row : null ) ];
		}
		$counts['total'] = array_sum( $counts );
		return $counts;
	}

	/**
	 * Summary for exam lists: the policy mode, attempts waiting for release, and whether every
	 * submitted attempt can see its results.
	 *
	 * @param int $exam_id Exam ID.
	 * @return array{mode:string,pending:int,released:bool}
	 */
	public static function summary( $exam_id ) {
		$policy = self::policy( $exam_id );
		$counts = self::counts( $exam_id );
		return array(
			'mode'     => $policy['mode'],
			'pending'  => $counts['pending'] + $counts['awaiting_marking'],
			'released' => 'immediate' === $policy['mode'] || ( $counts['released'] + $counts['viewed'] > 0 && 0 === $counts['pending'] + $counts['awaiting_marking'] ),
		);
	}

	/**
	 * Author view: policy, counts and one page of attempts.
	 *
	 * @param int $exam_id  Exam ID.
	 * @param int $page     Page from 1.
	 * @param int $per_page Rows.
	 * @return array
	 */
	public static function view( $exam_id, $page = 1, $per_page = 20 ) {
		$data   = Editor::data( $exam_id );
		$quiz   = ohmylms_get_quiz( (int) $data['quiz_id'] );
		$policy = self::policy( $exam_id );
		$counts = self::counts( $exam_id );
		$items  = array();
		foreach ( self::attempt_rows( (int) $data['quiz_id'], null, $per_page, ( $page - 1 ) * $per_page ) as $row ) {
			$record  = $row['released_at'] || $row['viewed_at'] ? $row : null;
			$state   = self::state( $policy, $row, $record );
			$items[] = array(
				'attempt_id'   => (int) $row['id'],
				'learner'      => array(
					'id'   => (int) $row['student_id'],
					'name' => (string) ( $row['display_name'] ?? '' ),
				),
				'status'       => (string) $row['status'],
				'submitted_at' => 'in-progress' === $row['status'] ? '' : self::time( $row['end_date'] ),
				'score'        => 'in-progress' === $row['status'] ? null : (float) $row['total'],
				'max'          => $quiz ? (float) \OhMyLMS\Assessment\AttemptReport::basis( (int) $row['id'], $quiz )['max'] : 0.0,
				'state'        => $state,
				'released_at'  => self::time( (string) $row['released_at'] ),
				'release_by'   => (string) $row['source'],
				'viewed_at'    => self::time( (string) $row['viewed_at'] ),
				'notified_at'  => self::time( (string) $row['notified_at'] ),
				'can_release'  => 'pending' === $state,
				'can_revoke'   => ! empty( $row['released_at'] ) && empty( $row['viewed_at'] ),
			);
		}
		return array(
			'policy' => array(
				'mode'       => $policy['mode'],
				'release_at' => self::time( $policy['release_at'] ),
			),
			'counts' => $counts,
			'items'  => $items,
		);
	}

	/**
	 * Newest log entries of an exam.
	 *
	 * @param int $exam_id  Exam ID.
	 * @param int $page     Page from 1.
	 * @param int $per_page Rows.
	 * @return array{items: array[], total: int}
	 */
	public static function history( $exam_id, $page = 1, $per_page = 50 ) {
		global $wpdb;
		if ( ! self::install() ) {
			return array(
				'items' => array(),
				'total' => 0,
			);
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live audit log.
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE exam_id=%d', self::log_table(), (int) $exam_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live audit log.
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT l.*, u.display_name FROM %i l LEFT JOIN %i u ON u.ID = l.actor_id WHERE l.exam_id=%d ORDER BY l.id DESC LIMIT %d OFFSET %d', self::log_table(), $wpdb->users, (int) $exam_id, (int) $per_page, ( (int) $page - 1 ) * (int) $per_page ), ARRAY_A );
		$items = array();
		foreach ( $rows as $row ) {
			$items[] = array(
				'id'         => (int) $row['id'],
				'attempt_id' => (int) $row['attempt_id'],
				'action'     => (string) $row['action'],
				'detail'     => (string) $row['detail'],
				'actor'      => array(
					'id'   => (int) $row['actor_id'],
					'name' => (string) ( $row['display_name'] ?? '' ),
				),
				'at'         => self::time( (string) $row['created_at'] ),
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Append a log entry.
	 *
	 * @param int    $exam_id    Exam ID.
	 * @param int    $attempt_id Attempt, or 0 for the exam.
	 * @param string $action     policy|release|revoke|notify|notify_failed|view.
	 * @param string $detail     Short detail.
	 * @param int    $actor      Acting user; the current user by default.
	 * @return void
	 */
	private static function log( $exam_id, $attempt_id, $action, $detail, $actor = null ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Append-only audit log.
		$wpdb->insert(
			self::log_table(),
			array(
				'exam_id'    => (int) $exam_id,
				'attempt_id' => (int) $attempt_id,
				'actor_id'   => null === $actor ? get_current_user_id() : (int) $actor,
				'action'     => $action,
				'detail'     => mb_substr( (string) $detail, 0, 190 ),
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Remove an exam's release rows and log (when the exam is deleted).
	 *
	 * @param int $exam_id Exam ID.
	 * @return void
	 */
	public static function forget( $exam_id ) {
		global $wpdb;
		if ( Library::TYPE !== get_post_type( $exam_id ) || ! self::install() ) {
			return;
		}
		$wpdb->delete( self::table(), array( 'exam_id' => (int) $exam_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( self::log_table(), array( 'exam_id' => (int) $exam_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_clear_scheduled_hook( self::HOOK, array( (int) $exam_id ) );
	}

	/**
	 * RFC 3339 UTC time, or an empty string.
	 *
	 * @param string $mysql UTC MySQL date time.
	 * @return string
	 */
	private static function time( $mysql ) {
		$mysql = (string) $mysql;
		return '' === $mysql || '0000-00-00 00:00:00' === $mysql ? '' : gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $mysql . ' UTC' ) );
	}

	/**
	 * Domain error.
	 *
	 * @param string $code    Code suffix.
	 * @param string $message Message.
	 * @param int    $status  HTTP status.
	 * @return \WP_Error
	 */
	private static function error( $code, $message, $status ) {
		return new \WP_Error( 'ohmylms_exam_results_' . $code, $message, array( 'status' => $status ) );
	}
}
