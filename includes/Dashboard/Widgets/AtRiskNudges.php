<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Nudges: the encouragement email to a flagged learner, its rate limits and its log.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Emails\StudentsEmail\AtRiskNudge;
use OhMyLMS\Schools\Schema;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A nudge sends the fixed `student_at_risk_nudge` email (editable under Emails) to a learner the at-risk
 * widget flags in the sender's scope, optionally with a copy to each active guardian (Account Hub
 * `guardian_links`; setting `nudge_guardians`, off by default).
 *
 * Limits, per sender: one nudge per learner per 72 hours, and at most `nudge_daily_cap` (default 20) in any
 * 24 hours. Both are checked and recorded under a per-sender database lock, so parallel requests cannot pass
 * them. Every attempt is logged in `{prefix}ohmylms_at_risk_nudges` (sender, learner, scope, reasons,
 * guardian copies, status, UTC time); only `sent` rows count against the limits.
 */
final class AtRiskNudges {

	/**
	 * Seconds between two nudges of the same learner by the same sender.
	 */
	const INTERVAL = 72 * HOUR_IN_SECONDS;

	/**
	 * Window of the daily cap, in seconds.
	 */
	const DAY = DAY_IN_SECONDS;

	const DB_VERSION        = '1';
	const DB_VERSION_OPTION = 'ohmylms_at_risk_nudges_db';

	/**
	 * Run a prepared query returning rows.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return array[]
	 */
	private static function rows( $sql, array $args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders, id lists come from Scope::in_condition().
		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	}

	/**
	 * Log table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'ohmylms_at_risk_nudges';
	}

	/**
	 * Create or upgrade the log table when its version changed.
	 *
	 * @return bool Whether the table is usable.
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
		$table = self::table();
		dbDelta(
			"CREATE TABLE $table (
 id bigint unsigned NOT NULL AUTO_INCREMENT,
 sender_id bigint unsigned NOT NULL,
 learner_id bigint unsigned NOT NULL,
 scope varchar(40) NOT NULL DEFAULT '',
 reasons text NULL,
 guardians smallint unsigned NOT NULL DEFAULT 0,
 status varchar(10) NOT NULL DEFAULT 'sent',
 sent_at datetime NOT NULL,
 PRIMARY KEY  (id),
 KEY sender_learner (sender_id,learner_id,sent_at),
 KEY sender_time (sender_id,sent_at),
 KEY learner (learner_id)
) " . $wpdb->get_charset_collate() . ';'
		);
		$ready = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $ready ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
		}
		return $ready;
	}

	/**
	 * Remove the log rows of a deleted user (as learner or sender).
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public static function forget_user( $user_id ) {
		if ( self::DB_VERSION !== get_option( self::DB_VERSION_OPTION ) ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE learner_id = %d OR sender_id = %d', self::table(), (int) $user_id, (int) $user_id ) );
	}

	/**
	 * Unix time of the latest sent nudge by a sender to each of some learners.
	 *
	 * @param int   $sender_id   Sender.
	 * @param int[] $learner_ids Learners.
	 * @return array<int,int>
	 */
	public static function last_sent( $sender_id, array $learner_ids ) {
		if ( ! $sender_id || ! $learner_ids || ! self::install() ) {
			return array();
		}
		$args = array( self::table(), (int) $sender_id );
		$in   = Scope::in_condition( 'learner_id', array_map( 'intval', $learner_ids ), $args );
		$last = array();
		foreach ( self::rows( "SELECT learner_id, MAX( sent_at ) AS at FROM %i WHERE sender_id = %d AND status = 'sent' AND $in GROUP BY learner_id", $args ) as $row ) {
			$last[ (int) $row['learner_id'] ] = (int) strtotime( $row['at'] . ' UTC' );
		}
		return $last;
	}

	/**
	 * Sent nudges of a sender in the last 24 hours, oldest first (Unix times).
	 *
	 * @param int $sender_id Sender.
	 * @param int $now       Unix time.
	 * @return int[]
	 */
	private static function sent_today( $sender_id, $now ) {
		if ( ! self::install() ) {
			return array();
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$times = $wpdb->get_col( $wpdb->prepare( "SELECT sent_at FROM %i WHERE sender_id = %d AND status = 'sent' AND sent_at > %s ORDER BY sent_at ASC", self::table(), (int) $sender_id, gmdate( 'Y-m-d H:i:s', $now - self::DAY ) ) );
		return array_map(
			static function ( $at ) {
				return (int) strtotime( $at . ' UTC' );
			},
			(array) $times
		);
	}

	/**
	 * Nudges a sender may still send in the current 24-hour window.
	 *
	 * @param int $sender_id Sender.
	 * @param int $cap       Daily cap.
	 * @return int
	 */
	public static function remaining_today( $sender_id, $cap ) {
		return max( 0, (int) $cap - count( self::sent_today( (int) $sender_id, time() ) ) );
	}

	/**
	 * Site-formatted date of a Unix time.
	 *
	 * @param int $ts Unix time.
	 * @return string
	 */
	public static function date_label( $ts ) {
		return (string) wp_date( (string) get_option( 'date_format' ), (int) $ts );
	}

	/**
	 * Send a nudge.
	 *
	 * @param int    $sender_id  Sender (the current user; must be able to use the scope).
	 * @param Scope  $scope      Scope resolved for the sender.
	 * @param Period $period     Period the sender is looking at.
	 * @param int    $learner_id Learner.
	 * @return array|WP_Error
	 */
	public static function send( $sender_id, Scope $scope, Period $period, $learner_id ) {
		$learner_id = (int) $learner_id;
		$in_scope   = $scope->learner_ids();
		if ( null !== $in_scope && ! in_array( $learner_id, $in_scope, true ) ) {
			return new WP_Error( 'ohmylms_at_risk_out_of_scope', __( 'You can only nudge learners of your own schools and classes.', 'ohmylms' ), array( 'status' => 403 ) );
		}
		$flagged = null;
		foreach ( (array) ( AtRisk::cached( $scope, $period )['learners'] ?? array() ) as $learner ) {
			if ( (int) $learner['id'] === $learner_id ) {
				$flagged = $learner;
				break;
			}
		}
		if ( null === $flagged ) {
			return new WP_Error( 'ohmylms_at_risk_not_flagged', __( 'This learner is not flagged as at risk in this school or class.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		$user = get_userdata( $learner_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return new WP_Error( 'ohmylms_at_risk_no_address', __( 'This learner has no valid email address.', 'ohmylms' ), array( 'status' => 422 ) );
		}
		$email = new AtRiskNudge();
		if ( ! $email->enabled() ) {
			return new WP_Error( 'ohmylms_at_risk_nudge_disabled', __( 'The nudge email is switched off under Emails.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		if ( ! self::install() ) {
			return new WP_Error( 'ohmylms_at_risk_nudge_failed', __( 'The nudge could not be recorded.', 'ohmylms' ), array( 'status' => 500 ) );
		}

		global $wpdb;
		$lock = 'ohmylms_nudge_' . (int) $sender_id;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 5 )', $lock ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return new WP_Error( 'ohmylms_at_risk_nudge_busy', __( 'Another nudge is being sent. Try again in a moment.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		try {
			$settings = AtRiskSettings::read();
			$now      = time();
			$last     = self::last_sent( $sender_id, array( $learner_id ) )[ $learner_id ] ?? null;
			if ( null !== $last && $last + self::INTERVAL > $now ) {
				return new WP_Error(
					'ohmylms_at_risk_nudge_too_soon',
					/* translators: %s: date. */
					sprintf( __( 'You already nudged this learner on %s. You can nudge them again after 72 hours.', 'ohmylms' ), self::date_label( $last ) ),
					array(
						'status'       => 429,
						'retry_after'  => $last + self::INTERVAL - $now,
						'nudged_at'    => gmdate( 'Y-m-d\TH:i:s\Z', $last ),
						'nudged_label' => self::date_label( $last ),
					)
				);
			}
			$today = self::sent_today( $sender_id, $now );
			$cap   = (int) $settings['nudge_daily_cap'];
			if ( count( $today ) >= $cap ) {
				return new WP_Error(
					'ohmylms_at_risk_nudge_daily_cap',
					/* translators: %s: number of nudges. */
					sprintf( _n( 'You have sent %s nudge in the last 24 hours, the most allowed. Try again later.', 'You have sent %s nudges in the last 24 hours, the most allowed. Try again later.', $cap, 'ohmylms' ), number_format_i18n( $cap ) ),
					array(
						'status'      => 429,
						'retry_after' => max( 1, $today[ count( $today ) - $cap ] + self::DAY - $now ),
					)
				);
			}

			$rules     = AtRiskSettings::rules( $settings );
			$reasons   = (array) $flagged['reasons'];
			$guardians = $settings['nudge_guardians'] ? self::guardian_addresses( $learner_id, $scope ) : array();
			$sender    = get_userdata( (int) $sender_id );
			$result    = $email->send( $user, $sender ? $sender->display_name : '', AtRiskRules::summary( $reasons, $rules ), $guardians );
			$status    = $result['sent'] ? 'sent' : 'failed';
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::table(),
				array(
					'sender_id'  => (int) $sender_id,
					'learner_id' => $learner_id,
					'scope'      => $scope->key(),
					'reasons'    => (string) wp_json_encode(
						array_map(
							static function ( $reason ) {
								return array(
									'rule'   => (string) $reason['rule'],
									'value'  => (int) $reason['value'],
									'severe' => (bool) $reason['severe'],
								);
							},
							$reasons
						)
					),
					'guardians'  => (int) $result['guardians'],
					'status'     => $status,
					'sent_at'    => gmdate( 'Y-m-d H:i:s', $now ),
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
			);
			if ( ! $result['sent'] ) {
				return new WP_Error( 'ohmylms_at_risk_nudge_failed', __( 'WordPress could not send the email. Check the site mail configuration.', 'ohmylms' ), array( 'status' => 500 ) );
			}

			/**
			 * Fires after a nudge was sent.
			 *
			 * @param int      $learner_id Learner.
			 * @param int      $sender_id  Sender.
			 * @param string   $scope      Scope key.
			 * @param array[]  $reasons    Reasons (`rule`, `value`, `severe`).
			 */
			do_action( 'ohmylms_at_risk_nudge_sent', $learner_id, (int) $sender_id, $scope->key(), $reasons );

			return array(
				'learner'      => $learner_id,
				'scope'        => $scope->key(),
				'nudged_at'    => gmdate( 'Y-m-d\TH:i:s\Z', $now ),
				'nudged_label' => self::date_label( $now ),
				'next_at'      => gmdate( 'Y-m-d\TH:i:s\Z', $now + self::INTERVAL ),
				'remaining'    => max( 0, $cap - count( $today ) - 1 ),
				'guardians'    => (int) $result['guardians'],
			);
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Addresses of the learner's active guardians: in the scope's school, or in any school for the site-wide
	 * scope and independent classes.
	 *
	 * @param int   $learner_id Learner.
	 * @param Scope $scope      Scope.
	 * @return string[]
	 */
	public static function guardian_addresses( $learner_id, Scope $scope ) {
		global $wpdb;
		$args   = array( Schema::table( 'guardian_links' ), $wpdb->users, (int) $learner_id );
		$school = '';
		if ( $scope->school_id() > 0 ) {
			$school = ' AND g.school_id = %d';
			$args[] = $scope->school_id();
		}
		$emails = wp_list_pluck( self::rows( "SELECT DISTINCT u.user_email FROM %i g INNER JOIN %i u ON u.ID = g.guardian_user_id WHERE g.student_user_id = %d AND g.status = 'active'$school", $args ), 'user_email' );
		return array_values( array_filter( array_map( 'sanitize_email', $emails ), 'is_email' ) );
	}

	/**
	 * Recent log rows, newest first (site administrators).
	 *
	 * @param int $learner_id Only this learner (0: everyone).
	 * @param int $limit      Most rows.
	 * @return array[]
	 */
	public static function log( $learner_id = 0, $limit = 100 ) {
		if ( ! self::install() ) {
			return array();
		}
		$args  = array( self::table() );
		$where = '';
		if ( $learner_id > 0 ) {
			$where  = ' WHERE learner_id = %d';
			$args[] = (int) $learner_id;
		}
		$args[] = max( 1, min( 100, (int) $limit ) );
		$rows   = self::rows( "SELECT id, sender_id, learner_id, scope, reasons, guardians, status, sent_at FROM %i$where ORDER BY sent_at DESC, id DESC LIMIT %d", $args );
		return array_map(
			static function ( $row ) {
				$reasons = json_decode( (string) $row['reasons'], true );
				return array(
					'id'        => (int) $row['id'],
					'sender'    => (int) $row['sender_id'],
					'learner'   => (int) $row['learner_id'],
					'scope'     => (string) $row['scope'],
					'reasons'   => is_array( $reasons ) ? $reasons : array(),
					'guardians' => (int) $row['guardians'],
					'status'    => (string) $row['status'],
					'sent_at'   => gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $row['sent_at'] . ' UTC' ) ),
				);
			},
			$rows
		);
	}
}
