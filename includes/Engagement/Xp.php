<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * XP: a learner's effort ledger. Never spent, never graded, never a mastery measure.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Practice\Sessions;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Skills\Score;

defined( 'ABSPATH' ) || exit;

/**
 * XP: a learner's effort ledger. Never spent, never graded, never a mastery measure.
 *
 * Every award has a key that is unique per learner ("practice:<session uuid>"), so replays,
 * retries and concurrent requests cannot pay twice. Dates are the learner's own calendar days
 * (the streak timezone), so the daily goal and the week agree with the streak.
 */
final class Xp {
	/* ---------- Time ---------- */

	/**
	 * Now for Xp.
	 */
	public static function now() {
		return strtotime( Streak::now() . ' UTC' );
	}

	/** The learner's calendar: their saved streak timezone, else their own, else the site's.
	 *
	 * @param mixed $student Student.
	 */
	public static function zone( $student ) {
		global $wpdb;
		if ( StreakSettings::enabled() && StreakSchema::ready() ) {
			$saved = $wpdb->get_var( $wpdb->prepare( 'SELECT timezone FROM %i WHERE user_id=%d', StreakSchema::table( 'state' ), (int) $student ) );
			if ( Streak::timezone( $saved ) ) {
				return new \DateTimeZone( $saved ); }
		}
		return new \DateTimeZone( Streak::default_timezone( (int) $student ) );
	}

	/**
	 * Date of for Xp.
	 *
	 * @param mixed         $timestamp Timestamp.
	 * @param \DateTimeZone $zone Zone.
	 */
	public static function date_of( $timestamp, \DateTimeZone $zone ) {
		return ( new \DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $zone )->format( 'Y-m-d' );
	}

	/**
	 * Today for Xp.
	 *
	 * @param mixed $student Student.
	 */
	public static function today( $student ) {
		return self::date_of( self::now(), self::zone( $student ) );
	}

	/* ---------- Ledger ---------- */

	/**
	 * Enabled for Xp.
	 */
	public static function enabled() {
		return XpSettings::enabled() && XpSchema::ready();
	}

	/** XP earned on one calendar day, or over a span of days when $to is given.
	 *
	 * @param mixed $student Student.
	 * @param mixed $from From.
	 * @param mixed $to To.
	 */
	public static function earned( $student, $from, $to = null ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(xp),0) FROM %i WHERE student_id=%d AND local_date BETWEEN %s AND %s', XpSchema::table( 'xp_events' ), (int) $student, $from, $to ? $to : $from ) );
	}

	/**
	 * Total for Xp.
	 *
	 * @param mixed $student Student.
	 */
	public static function total( $student ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(xp),0) FROM %i WHERE student_id=%d', XpSchema::table( 'xp_events' ), (int) $student ) );
	}

	/**
	 * Record XP once. Returns the stored award, or the earlier one when the key was already used.
	 *
	 * @return array{xp:int,duplicate:bool,key:string}|null null when XP is off
	 * @param mixed  $student Student.
	 * @param mixed  $key Lookup key.
	 * @param mixed  $kind Kind.
	 * @param mixed  $xp Xp.
	 * @param array  $meta Meta.
	 * @param int    $term_id Term identifier.
	 * @param string $source_type Source type.
	 * @param int    $source_id Source identifier.
	 */
	public static function award( $student, $key, $kind, $xp, array $meta = array(), $term_id = 0, $source_type = '', $source_id = 0 ) {
		global $wpdb;
		if ( ! self::enabled() || (int) $student < 1 ) {
			return null; }
		$now   = self::now();
		$zone  = self::zone( $student );
		$table = XpSchema::table( 'xp_events' );
		$wpdb->query(
			$wpdb->prepare( 'INSERT IGNORE INTO %i (student_id, event_key, kind, xp, term_id, source_type, source_id, meta, local_date, created_at) VALUES (%d, %s, %s, %d, %d, %s, %d, %s, %s, %s)', $table, (int) $student, substr( (string) $key, 0, 100 ), substr( (string) $kind, 0, 20 ), (int) $xp, (int) $term_id, (string) $source_type, (int) $source_id, wp_json_encode( $meta ), self::date_of( $now, $zone ), gmdate( 'Y-m-d H:i:s', $now ) )
		);
		$new    = 1 === (int) $wpdb->rows_affected;
		$stored = $new ? (int) $xp : (int) $wpdb->get_var( $wpdb->prepare( 'SELECT xp FROM %i WHERE student_id=%d AND event_key=%s', $table, (int) $student, (string) $key ) );
		if ( $new && 'goal' !== $kind ) {
			do_action( 'ohmylms_xp_awarded', (int) $student, $stored, $kind, $meta );
			self::check_goal( $student ); }
		return array(
			'xp'        => $stored,
			'duplicate' => ! $new,
			'key'       => (string) $key,
		);
	}

	/** The first time each day the goal is met: one marker row, an optional bonus, one action.
	 *
	 * @param mixed $student Student.
	 */
	private static function check_goal( $student ) {
		$today = self::today( $student );
		$goal  = self::goal( $student );
		if ( self::earned( $student, $today ) < $goal ) {
			return; }
		$award = self::award( $student, 'goal:' . $today, 'goal', (int) XpSettings::get()['goal_bonus'], array( 'goal' => $goal ) );
		if ( $award && ! $award['duplicate'] ) {
			do_action( 'ohmylms_xp_goal_met', (int) $student, $today, $goal ); }
	}

	/* ---------- Daily goal ---------- */

	/**
	 * Goal for Xp.
	 *
	 * @param mixed $student Student.
	 */
	public static function goal( $student ) {
		$settings = XpSettings::get();
		$chosen   = (int) get_user_meta( (int) $student, 'ohmylms_xp_goal', true );
		return in_array( $chosen, $settings['goals'], true ) ? $chosen : $settings['default_goal'];
	}

	/**
	 * Set goal for Xp.
	 *
	 * @return int|\WP_Error
	 * @param mixed $student Student.
	 * @param mixed $goal Goal.
	 */
	public static function set_goal( $student, $goal ) {
		if ( ! in_array( (int) $goal, XpSettings::get()['goals'], true ) ) {
			return new \WP_Error( 'xp_goal', __( 'Choose one of the available daily goals.', 'ohmylms' ), array( 'status' => 400 ) ); }
		update_user_meta( (int) $student, 'ohmylms_xp_goal', (int) $goal );
		return (int) $goal;
	}

	/** Totals and the last week, for the dashboard and the API.
	 *
	 * @param mixed $student Student.
	 * @param int   $history_days History days.
	 */
	public static function summary( $student, $history_days = 7 ) {
		global $wpdb;
		$today                   = self::today( $student );
		$goal                    = self::goal( $student );
		list( $monday, $sunday ) = XpRules::week( $today );
		$span                    = max( 7, min( 90, (int) $history_days ) );
		$from                    = ( new \DateTimeImmutable( $today ) )->modify( '-' . ( $span - 1 ) . ' days' )->format( 'Y-m-d' );
		$by_day                  = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT local_date, SUM(xp) AS xp FROM %i WHERE student_id=%d AND local_date BETWEEN %s AND %s GROUP BY local_date', XpSchema::table( 'xp_events' ), (int) $student, $from, $today ), ARRAY_A ) as $row ) {
			$by_day[ $row['local_date'] ] = (int) $row['xp']; }
		$days = array();
		for ( $i = 0; $i < $span; $i++ ) {
			$date   = ( new \DateTimeImmutable( $from ) )->modify( "+$i days" )->format( 'Y-m-d' );
			$days[] = array(
				'date' => $date,
				'xp'   => $by_day[ $date ] ?? 0,
			);
		}
		$earned = $by_day[ $today ] ?? 0;
		return array(
			'enabled'   => true,
			'total'     => self::total( $student ),
			'today'     => $earned,
			'goal'      => $goal,
			'goals'     => XpSettings::get()['goals'],
			'goal_met'  => $earned >= $goal,
			'week'      => self::earned( $student, $monday, $sunday ),
			'week_from' => $monday,
			'date'      => $today,
			'days'      => $days,
		);
	}

	/* ---------- Practice lessons ---------- */

	/**
	 * Pay a finished practice session: 10 for the lesson, +5 for no mistakes, +2 for each right
	 * answer given while the skill's mastery score was 90 or more. Hooked to
	 * ohmylms_practice_completed, so lesson and standard styles are both covered, once.
	 *
	 * @return array|null the award (with its breakdown) or null when nothing was paid
	 * @param mixed $uuid Uuid.
	 */
	public static function award_practice( $uuid ) {
		global $wpdb;
		if ( ! self::enabled() || ! Schema::ready() ) {
			return null; }
		$session = Sessions::get( $uuid );
		if ( ! $session || 'complete' !== $session['status'] || ! (int) $session['student_id'] || 'skill' !== $session['mode'] ) {
			return null; }
		$student = (int) $session['student_id'];
		// The evidence for the last answers is written asynchronously; settle it first so the
		// challenge zone is judged on the score each answer was actually made at.
		Evidence::process( 100 );
		$items     = Sessions::items( $session['id'] );
		$lesson    = ( $session['policy']['style'] ?? '' ) === 'lesson';
		$planned   = array_values(
			array_filter(
				$items,
				static function ( $item ) use ( $lesson ) {
					return null !== $item['answered_at'] && ( ! $lesson || empty( $item['display']['replay_of'] ) );
				}
			)
		);
		$answered  = array_filter(
			$planned,
			static function ( $item ) {
				return StreakCalendar::meaningful( $item['response'] );
			}
		);
		$right     = array_filter(
			$planned,
			static function ( $item ) {
				return 1 === (int) $item['correct'];
			}
		);
		$challenge = 0;
		if ( Score::enabled() ) {
			$trace   = Score::trace( $student, (int) $session['term_id'] );
			$events  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, item_id FROM %i WHERE source_type=\'practice\' AND source_id=%d AND superseded_by=0', Schema::table( 'grade_events' ), (int) $session['id'] ), ARRAY_A );
			$by_item = array();
			foreach ( $events as $event ) {
				$by_item[ (int) $event['item_id'] ] = (int) $event['id']; }
			foreach ( $right as $item ) {
				$step = $trace[ $by_item[ (int) $item['id'] ] ?? 0 ] ?? null;
				if ( $step && ! empty( $step['counted'] ) && $step['before'] >= 90 ) {
					++$challenge; }
			}
		}
		$settings = XpSettings::get();
		$result   = XpRules::practice(
			array(
				'answered'        => count( $answered ),
				'planned_total'   => count( $planned ),
				'planned_right'   => count( $right ),
				'challenge_right' => $challenge,
			),
			$settings
		);
		if ( ! $result['counted'] ) {
			return null; }
		$capped = XpRules::cap( $result['total'], self::earned( $student, self::today( $student ) ), $settings['daily_cap'] );
		$award  = self::award(
			$student,
			'practice:' . $session['uuid'],
			'practice',
			$capped['xp'],
			array(
				'parts'   => $result['parts'],
				'capped'  => $capped['capped'],
				'right'   => count( $right ),
				'planned' => count( $planned ),
				'style'   => $lesson ? 'lesson' : 'standard',
			),
			(int) $session['term_id'],
			'practice',
			(int) $session['id']
		);
		return $award ? $award + array(
			'parts'  => $result['parts'],
			'capped' => $capped['capped'],
		) : null;
	}

	/** What a finished session earned, as stored (for the summary the learner sees).
	 *
	 * @param mixed $student Student.
	 * @param mixed $uuid Uuid.
	 */
	public static function for_session( $student, $uuid ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT xp, meta FROM %i WHERE student_id=%d AND event_key=%s', XpSchema::table( 'xp_events' ), (int) $student, 'practice:' . $uuid ), ARRAY_A );
		if ( ! $row ) {
			return null; }
		$meta = json_decode( (string) $row['meta'], true ) ?: array();
		return array(
			'xp'     => (int) $row['xp'],
			'parts'  => (array) ( $meta['parts'] ?? array() ),
			'capped' => ! empty( $meta['capped'] ),
		);
	}
}
