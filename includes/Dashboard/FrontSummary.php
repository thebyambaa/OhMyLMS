<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * One cached summary for the front-end dashboard home.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Schools\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Indexed counts for the viewer's schools, cached for five minutes and dropped when school data is written.
 */
final class FrontSummary {

	const VERSION = 'ohmylms_fdash_version';

	/**
	 * Days in a named range, and the previous window of the same length.
	 *
	 * @param string $range 7, 30 or term.
	 * @param int    $now   Unix time.
	 * @return array{days:int,from:string,to:string,prev_from:string,prev_to:string}
	 */
	public static function window( $range, $now ) {
		$days = '30' === $range ? 30 : ( 'term' === $range ? 90 : 7 );
		$to   = (int) $now;
		$from = $to - $days * DAY_IN_SECONDS;
		return array(
			'days'      => $days,
			'from'      => gmdate( 'Y-m-d H:i:s', $from ),
			'to'        => gmdate( 'Y-m-d H:i:s', $to ),
			'prev_from' => gmdate( 'Y-m-d H:i:s', $from - $days * DAY_IN_SECONDS ),
			'prev_to'   => gmdate( 'Y-m-d H:i:s', $from ),
		);
	}

	/**
	 * Percent change against the previous window. Null when the previous value is zero.
	 *
	 * @param float $current  Current value.
	 * @param float $previous Previous value.
	 * @return float|null
	 */
	public static function delta( $current, $previous ) {
		if ( $previous <= 0 ) {
			return null;
		}
		return round( 100 * ( $current - $previous ) / $previous, 1 );
	}

	/** Drop cached summaries. Called when classes, invitations or imports change. */
	public static function bust() {
		update_option( self::VERSION, (string) microtime( true ), false );
	}

	/**
	 * Summary for one viewer.
	 *
	 * @param array  $scope  Front::scope().
	 * @param int    $school School ID, or 0 for every school in the scope.
	 * @param string $range  7, 30 or term.
	 * @param string $level  schools, classes or students.
	 * @param int    $class  Class ID when the drill is students.
	 * @return array
	 */
	public static function get( array $scope, $school, $range, $level = 'schools', $class = 0 ) {
		$school  = self::school_in_scope( $scope, $school );
		$range   = in_array( $range, array( '7', '30', 'term' ), true ) ? $range : '7';
		$level   = in_array( $level, array( 'schools', 'classes', 'students' ), true ) ? $level : 'schools';
		$class   = absint( $class );
		$version = (string) get_option( self::VERSION, '0' );
		$key     = 'ohmylms_fdash_' . md5( $version . '|' . $scope['user_id'] . '|' . $school . '|' . $range . '|' . $level . '|' . $class );
		$cached  = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$data = self::build( $scope, $school, self::window( $range, time() ), $level, $class );
		set_transient( $key, $data, 5 * MINUTE_IN_SECONDS );
		return $data;
	}

	/**
	 * Keep a requested school inside the viewer's scope. 0 means all of them.
	 *
	 * @param array $scope  Scope.
	 * @param int   $school Requested school.
	 * @return int
	 */
	public static function school_in_scope( array $scope, $school ) {
		$school = absint( $school );
		if ( ! $school ) {
			return 0;
		}
		return in_array( $school, $scope['school_ids'], true ) ? $school : 0;
	}

	/**
	 * Uncached summary.
	 *
	 * @param array  $scope  Scope.
	 * @param int    $school School filter.
	 * @param array  $window Window.
	 * @param string $level  schools, classes or students.
	 * @param int    $class  Class ID when the drill is students.
	 * @return array
	 */
	private static function build( array $scope, $school, array $window, $level, $class ) {
		$ids     = $school ? array( $school ) : $scope['school_ids'];
		$current = self::counts( $scope, $ids, $window['from'], $window['to'] );
		$prev    = self::counts( $scope, $ids, $window['prev_from'], $window['prev_to'] );
		$drill   = self::drill( $scope, $ids, $window, $level, $class );
		return array(
			'range'   => $window['days'],
			'school'  => $school,
			'kpis'    => self::kpis( $current, $prev ),
			'alerts'  => self::alerts( $scope, $ids ),
			'pending' => self::pending( $scope, $ids ),
			'drill'   => $drill,
			'heatmap' => self::heatmap( $scope, $ids ),
		);
	}

	/**
	 * Schools, then classes, then students.
	 *
	 * @param array  $scope  Scope.
	 * @param int[]  $ids    Schools.
	 * @param array  $window Window.
	 * @param string $level  Drill level.
	 * @param int    $class  Class ID when the level is students.
	 * @return array
	 */
	private static function drill( array $scope, array $ids, array $window, $level, $class ) {
		if ( 'students' === $level && $class && self::class_allowed( $scope, $class ) ) {
			return array(
				'level'   => 'students',
				'class'   => $class,
				'columns' => array( __( 'Student', 'ohmylms' ), __( 'Skills proficient', 'ohmylms' ) ),
				'rows'    => self::student_rows( $class ),
			);
		}
		if ( 'classes' === $level ) {
			return array(
				'level'   => 'classes',
				'columns' => array( __( 'Class', 'ohmylms' ), __( 'Grade', 'ohmylms' ), __( 'Students', 'ohmylms' ) ),
				'rows'    => self::class_rows( $scope, $ids ),
			);
		}
		return array(
			'level'   => 'schools',
			'columns' => array( __( 'School', 'ohmylms' ), __( 'Students', 'ohmylms' ), __( 'Skills proficient this period', 'ohmylms' ) ),
			'rows'    => self::schools_rows( $scope, $ids, $window ),
		);
	}

	/**
	 * Whether this viewer may drill into the class.
	 *
	 * @param array $scope Scope.
	 * @param int   $class Class ID.
	 * @return bool
	 */
	private static function class_allowed( array $scope, $class ) {
		global $wpdb;
		if ( ! $scope['schema'] ) {
			return false;
		}
		$school = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT school_id FROM %i WHERE id=%d AND status=%s', Schema::table( 'classes' ), $class, 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $school || ! in_array( $school, $scope['school_ids'], true ) ) {
			return false;
		}
		if ( $scope['owner'] || $scope['school_admin'] ) {
			return true;
		}
		return is_array( $scope['class_ids'] ) && in_array( (int) $class, $scope['class_ids'], true );
	}

	/**
	 * Class rows for the drill table.
	 *
	 * @param array $scope Scope.
	 * @param int[] $ids   Schools.
	 * @return array
	 */
	private static function class_rows( array $scope, array $ids ) {
		global $wpdb;
		if ( ! $scope['schema'] || ! $ids ) {
			return array();
		}
		$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql  = "SELECT c.id,c.name,c.grade,(SELECT COUNT(*) FROM %i m WHERE m.class_id=c.id AND m.role='student' AND m.status='active') AS students FROM %i c WHERE c.status='active' AND c.school_id IN ($in)";
		$args = array_merge( array( Schema::table( 'class_memberships' ), Schema::table( 'classes' ) ), $ids );
		if ( is_array( $scope['class_ids'] ) ) {
			if ( ! $scope['class_ids'] ) {
				return array();
			}
			$sql .= ' AND c.id IN (' . implode( ',', array_fill( 0, count( $scope['class_ids'] ), '%d' ) ) . ')';
			$args = array_merge( $args, $scope['class_ids'] );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY c.name LIMIT 50', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'    => (int) $row['id'],
				'name'  => (string) $row['name'],
				'cells' => array( (string) $row['grade'], (string) (int) $row['students'] ),
			);
		}
		return $out;
	}

	/**
	 * Student rows for one class.
	 *
	 * @param int $class Class ID.
	 * @return array
	 */
	private static function student_rows( $class ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT u.ID AS id,u.display_name FROM %i m JOIN {$wpdb->users} u ON u.ID=m.user_id WHERE m.class_id=%d AND m.role='student' AND m.status='active' ORDER BY u.display_name LIMIT 100", Schema::table( 'class_memberships' ), $class ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$skills = '—';
			if ( class_exists( '\OhMyLMS\Assessment\Schema' ) && \OhMyLMS\Assessment\Schema::ready() ) {
				$skills = (string) (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE student_id=%d AND level IN ('proficient','mastered')", \OhMyLMS\Assessment\Schema::table( 'student_skill_state' ), $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			}
			$out[] = array(
				'id'    => (int) $row['id'],
				'name'  => (string) $row['display_name'],
				'cells' => array( $skills ),
			);
		}
		return $out;
	}

	/**
	 * KPI cards. A missing source is `available: false` with a note.
	 *
	 * @param array $current Current counts.
	 * @param array $prev    Previous counts.
	 * @return array
	 */
	private static function kpis( array $current, array $prev ) {
		$cards = array();
		$cards[] = self::ratio_card( 'activated', __( 'Activated students', 'ohmylms' ), $current['students'], $current['activated'], $prev['students'], $prev['activated'], __( 'Students who have signed in at least once.', 'ohmylms' ) );
		$cards[] = self::count_card( 'active', __( 'Weekly active learners', 'ohmylms' ), $current['active'], $prev['active'], __( 'Learners with a finished quiz or practice session in this period.', 'ohmylms' ) );
		if ( $current['goal_available'] ) {
			$cards[] = self::ratio_card( 'goal', __( 'Meeting weekly goal', 'ohmylms' ), $current['students'], $current['goal_met'], $prev['students'], $prev['goal_met'], __( 'Share of students at or above the site default XP goal this period.', 'ohmylms' ) );
		} else {
			$cards[] = self::empty_card( 'goal', __( 'Meeting weekly goal', 'ohmylms' ), __( 'Weekly XP goals are turned off, so this is not measured.', 'ohmylms' ) );
		}
		if ( $current['skills_available'] ) {
			$cards[] = self::count_card( 'skills', __( 'Skills levelled up to proficient', 'ohmylms' ), $current['skills'], $prev['skills'], __( 'Skill records that reached proficient or mastered in this period.', 'ohmylms' ) );
		} else {
			$cards[] = self::empty_card( 'skills', __( 'Skills levelled up to proficient', 'ohmylms' ), __( 'Skill mastery is not available yet.', 'ohmylms' ) );
		}
		$cards[] = self::ratio_card( 'teachers', __( 'Teachers active', 'ohmylms' ), $current['teachers'], $current['teachers_active'], $prev['teachers'], $prev['teachers_active'], __( 'Teachers who have signed in, out of teachers in these schools.', 'ohmylms' ) );
		return $cards;
	}

	/**
	 * A count KPI with a period-over-period delta.
	 *
	 * @param string $id    Card id.
	 * @param string $label Label.
	 * @param int    $now   Current count.
	 * @param int    $then  Previous count.
	 * @param string $note  Note.
	 * @return array
	 */
	private static function count_card( $id, $label, $now, $then, $note ) {
		return array(
			'id'        => $id,
			'label'     => $label,
			'available' => true,
			'value'     => number_format_i18n( (int) $now ),
			'delta'     => self::delta( $now, $then ),
			'note'      => $note,
		);
	}

	/**
	 * A percentage KPI, or a fraction for the teacher card.
	 *
	 * @param string $id    Card id.
	 * @param string $label Label.
	 * @param int    $total Denominator now.
	 * @param int    $part  Numerator now.
	 * @param int    $ptot  Denominator before.
	 * @param int    $ppart Numerator before.
	 * @param string $note  Note.
	 * @return array
	 */
	private static function ratio_card( $id, $label, $total, $part, $ptot, $ppart, $note ) {
		$now  = $total > 0 ? round( 100 * $part / $total, 1 ) : 0;
		$then = $ptot > 0 ? round( 100 * $ppart / $ptot, 1 ) : 0;
		$value = 'teachers' === $id ? ( (int) $part . '/' . (int) $total ) : ( $now . '%' );
		return array(
			'id'        => $id,
			'label'     => $label,
			'available' => true,
			'value'     => $value,
			'delta'     => self::delta( $now, $then ),
			'note'      => $total ? $note : __( 'No one is in this group yet.', 'ohmylms' ),
		);
	}

	/**
	 * An honest empty KPI when the metric has no source.
	 *
	 * @param string $id    Card id.
	 * @param string $label Label.
	 * @param string $note  Why it is empty.
	 * @return array
	 */
	private static function empty_card( $id, $label, $note ) {
		return array(
			'id'        => $id,
			'label'     => $label,
			'available' => false,
			'value'     => '',
			'delta'     => null,
			'note'      => $note,
		);
	}

	/**
	 * Counts for one window.
	 *
	 * @param array  $scope Scope.
	 * @param int[]  $ids   School ids.
	 * @param string $from  Inclusive start.
	 * @param string $to    Exclusive end.
	 * @return array
	 */
	private static function counts( array $scope, array $ids, $from, $to ) {
		global $wpdb;
		$out = array(
			'students'         => 0,
			'activated'        => 0,
			'active'           => 0,
			'teachers'         => 0,
			'teachers_active'  => 0,
			'skills'           => 0,
			'skills_available' => false,
			'goal_met'         => 0,
			'goal_available'   => false,
		);
		if ( ! $scope['schema'] || ! $ids ) {
			return $out;
		}
		$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$args = array_merge( array( Schema::table( 'school_memberships' ) ), $ids );
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(role='student') AS students, SUM(role='teacher') AS teachers FROM %i WHERE status='active' AND school_id IN ($in)", $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The IN list is only %d placeholders.
		$out['students'] = (int) ( $row['students'] ?? 0 );
		$out['teachers'] = (int) ( $row['teachers'] ?? 0 );
		$meta            = $wpdb->usermeta;
		$out['activated'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT m.user_id) FROM %i m JOIN {$meta} um ON um.user_id=m.user_id AND um.meta_key='session_tokens' AND um.meta_value<>'' WHERE m.status='active' AND m.role='student' AND m.school_id IN ($in)", array_merge( array( Schema::table( 'school_memberships' ) ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Membership index plus usermeta.
		$out['teachers_active'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT m.user_id) FROM %i m JOIN {$meta} um ON um.user_id=m.user_id AND um.meta_key='session_tokens' AND um.meta_value<>'' WHERE m.status='active' AND m.role='teacher' AND m.school_id IN ($in)", array_merge( array( Schema::table( 'school_memberships' ) ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$quiz = $wpdb->prefix . 'ohmylms_quiz_attempts';
		$out['active'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT a.student_id) FROM %i a JOIN %i m ON m.user_id=a.student_id AND m.role='student' AND m.status='active' AND m.school_id IN ($in) WHERE a.status<>'in-progress' AND a.end_date>=%s AND a.end_date<%s", array_merge( array( $quiz, Schema::table( 'school_memberships' ) ), $ids, array( $from, $to ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( class_exists( '\OhMyLMS\Assessment\Schema' ) && \OhMyLMS\Assessment\Schema::ready() ) {
			$practice = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.student_id) FROM %i p JOIN %i m ON m.user_id=p.student_id AND m.role='student' AND m.status='active' AND m.school_id IN ($in) WHERE p.status='complete' AND p.completed_at>=%s AND p.completed_at<%s", array_merge( array( \OhMyLMS\Assessment\Schema::table( 'practice_sessions' ), Schema::table( 'school_memberships' ) ), $ids, array( $from, $to ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$out['active'] = max( $out['active'], $practice );
		}
		if ( class_exists( '\OhMyLMS\Assessment\Schema' ) && \OhMyLMS\Assessment\Schema::ready() ) {
			$out['skills_available'] = true;
			$out['skills'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i s JOIN %i m ON m.user_id=s.student_id AND m.role='student' AND m.status='active' AND m.school_id IN ($in) WHERE s.level IN ('proficient','mastered') AND s.last_evidence_at>=%s AND s.last_evidence_at<%s", array_merge( array( \OhMyLMS\Assessment\Schema::table( 'student_skill_state' ), Schema::table( 'school_memberships' ) ), $ids, array( $from, $to ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		if ( class_exists( '\OhMyLMS\Engagement\Xp' ) && \OhMyLMS\Engagement\Xp::enabled() ) {
			$goal = (int) \OhMyLMS\Engagement\XpSettings::get()['default_goal'];
			$out['goal_available'] = true;
			$out['goal_met'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM (SELECT e.student_id FROM %i e JOIN %i m ON m.user_id=e.student_id AND m.role='student' AND m.status='active' AND m.school_id IN ($in) WHERE e.local_date>=%s AND e.local_date<%s GROUP BY e.student_id HAVING SUM(e.xp)>=%d) t", array_merge( array( \OhMyLMS\Engagement\XpSchema::table( 'xp_events' ), Schema::table( 'school_memberships' ) ), $ids, array( substr( $from, 0, 10 ), substr( $to, 0, 10 ), max( 1, $goal ) ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		return $out;
	}

	/**
	 * Alerts that have a real row behind them.
	 *
	 * @param array $scope Scope.
	 * @param int[] $ids   Schools.
	 * @return array
	 */
	private static function alerts( array $scope, array $ids ) {
		global $wpdb;
		$alerts = array();
		if ( ! $scope['schema'] || ! $ids ) {
			return $alerts;
		}
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$old   = gmdate( 'Y-m-d H:i:s', time() - 2 * DAY_IN_SECONDS );
		$stale = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s AND created_at<%s AND school_id IN ($in)", array_merge( array( Schema::table( 'school_invitations' ), \OhMyLMS\Schools\Service::now(), $old ), $ids ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $stale ) {
			$alerts[] = array(
				'id'     => 'invites',
				'tone'   => 'danger',
				'text'   => sprintf( /* translators: %d: invitation count. */ _n( '%d invitation has been pending for more than 48 hours.', '%d invitations have been pending for more than 48 hours.', $stale, 'ohmylms' ), $stale ),
				'action' => 'people',
				'label'  => __( 'Review', 'ohmylms' ),
			);
		}
		return $alerts;
	}

	/**
	 * Newest pending invitations.
	 *
	 * @param array $scope Scope.
	 * @param int[] $ids   Schools.
	 * @return array
	 */
	public static function pending( array $scope, array $ids ) {
		global $wpdb;
		if ( ! $scope['schema'] || ! $ids ) {
			return array();
		}
		$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,role,email,class_id,created_at,expires_at FROM %i WHERE consumed_at IS NULL AND revoked_at IS NULL AND expires_at>%s AND school_id IN ($in) ORDER BY id DESC LIMIT 12", array_merge( array( Schema::table( 'school_invitations' ), \OhMyLMS\Schools\Service::now() ), $ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'      => (int) $row['id'],
				'role'    => (string) $row['role'],
				'email'   => (string) $row['email'],
				'created' => (string) $row['created_at'],
			);
		}
		return $out;
	}

	/**
	 * One row per school.
	 *
	 * @param array $scope  Scope.
	 * @param int[] $ids    Schools.
	 * @param array $window Window.
	 * @return array
	 */
	private static function schools_rows( array $scope, array $ids, array $window ) {
		global $wpdb;
		$rows = array();
		if ( ! $scope['schema'] || ! $ids ) {
			return $rows;
		}
		$in       = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$students = array();
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT school_id, COUNT(*) AS total FROM %i WHERE status='active' AND role='student' AND school_id IN ($in) GROUP BY school_id", array_merge( array( Schema::table( 'school_memberships' ) ), $ids ) ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$students[ (int) $row['school_id'] ] = (int) $row['total'];
		}
		$skills = array();
		if ( class_exists( '\OhMyLMS\Assessment\Schema' ) && \OhMyLMS\Assessment\Schema::ready() ) {
			foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT m.school_id, COUNT(*) AS total FROM %i s JOIN %i m ON m.user_id=s.student_id AND m.role='student' AND m.status='active' WHERE s.level IN ('proficient','mastered') AND s.last_evidence_at>=%s AND s.last_evidence_at<%s AND m.school_id IN ($in) GROUP BY m.school_id", array_merge( array( \OhMyLMS\Assessment\Schema::table( 'student_skill_state' ), Schema::table( 'school_memberships' ), $window['from'], $window['to'] ), $ids ) ), ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$skills[ (int) $row['school_id'] ] = (int) $row['total'];
			}
		}
		foreach ( $scope['schools'] as $school ) {
			if ( ! in_array( $school['id'], $ids, true ) ) {
				continue;
			}
			$rows[] = array(
				'id'    => $school['id'],
				'name'  => $school['name'],
				'cells' => array(
					(string) ( $students[ $school['id'] ] ?? 0 ),
					isset( $skills[ $school['id'] ] ) ? (string) $skills[ $school['id'] ] : '—',
				),
			);
		}
		return $rows;
	}

	/**
	 * Skill-level share by class. Empty when mastery has no rows.
	 *
	 * @param array $scope Scope.
	 * @param int[] $ids   Schools.
	 * @return array{available:bool,note:string,columns:array,rows:array}
	 */
	private static function heatmap( array $scope, array $ids ) {
		global $wpdb;
		$empty = array(
			'available' => false,
			'note'      => __( 'A unit-by-class heatmap needs courses linked to classes. Skill levels by class appear after learners practise.', 'ohmylms' ),
			'columns'   => array(),
			'rows'      => array(),
		);
		if ( ! $scope['schema'] || ! $ids || ! class_exists( '\OhMyLMS\Assessment\Schema' ) || ! \OhMyLMS\Assessment\Schema::ready() ) {
			return $empty;
		}
		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$class_sql = "SELECT c.id,c.name FROM %i c WHERE c.status='active' AND c.school_id IN ($in)";
		$args = array_merge( array( Schema::table( 'classes' ) ), $ids );
		if ( is_array( $scope['class_ids'] ) ) {
			if ( ! $scope['class_ids'] ) {
				return $empty;
			}
			$class_sql .= ' AND c.id IN (' . implode( ',', array_fill( 0, count( $scope['class_ids'] ), '%d' ) ) . ')';
			$args = array_merge( $args, $scope['class_ids'] );
		}
		$classes = $wpdb->get_results( $wpdb->prepare( $class_sql . ' ORDER BY c.name LIMIT 8', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- The IN lists are placeholders.
		if ( ! $classes ) {
			return $empty;
		}
		$class_ids = array_map( 'intval', array_column( $classes, 'id' ) );
		$cin       = implode( ',', array_fill( 0, count( $class_ids ), '%d' ) );
		$grouped   = $wpdb->get_results( $wpdb->prepare( "SELECT m.class_id, s.level, COUNT(*) AS total FROM %i s JOIN %i m ON m.user_id=s.student_id AND m.role='student' AND m.status='active' WHERE m.class_id IN ($cin) GROUP BY m.class_id, s.level", array_merge( array( \OhMyLMS\Assessment\Schema::table( 'student_skill_state' ), Schema::table( 'class_memberships' ) ), $class_ids ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$by        = array();
		foreach ( (array) $grouped as $row ) {
			$by[ (int) $row['class_id'] ][ (string) $row['level'] ] = (int) $row['total'];
		}
		$levels = array( 'attempted', 'familiar', 'proficient', 'mastered' );
		$rows   = array();
		$any    = false;
		foreach ( $levels as $level ) {
			$cells = array();
			foreach ( $classes as $class ) {
				$bag   = $by[ (int) $class['id'] ] ?? array();
				$total = array_sum( $bag );
				$part  = $bag[ $level ] ?? 0;
				$pct   = $total ? (int) round( 100 * $part / $total ) : 0;
				if ( $total ) {
					$any = true;
				}
				$cells[] = $pct;
			}
			$rows[] = array(
				'label' => $level,
				'cells' => $cells,
			);
		}
		if ( ! $any ) {
			return $empty;
		}
		return array(
			'available' => true,
			'note'      => __( 'Share of recorded skill levels in each class. A unit-by-class grid needs each class linked to a course.', 'ohmylms' ),
			'columns'   => array_map(
				static function ( $class ) {
					return (string) $class['name'];
				},
				$classes
			),
			'rows'      => $rows,
		);
	}
}
