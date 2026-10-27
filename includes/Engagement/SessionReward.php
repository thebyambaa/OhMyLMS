<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * What a learner gets to see when a practice session ends: XP and the daily goal, the change
 * in the skill's mastery score, and the streak. Read-only: it reports what the ledgers hold.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Engagement;

use OhMyLMS\Skills\Score;

defined( 'ABSPATH' ) || exit;

/**
 * What a learner gets to see when a practice session ends: XP and the daily goal, the change
 * in the skill's mastery score, and the streak. Read-only: it reports what the ledgers hold.
 */
final class SessionReward {
	/**
	 * For session for Session reward.
	 *
	 * @return array|null null when nothing is switched on or the session has no owner
	 * @param array $session Session.
	 */
	public static function for_session( array $session ) {
		$student = (int) ( $session['student_id'] ?? 0 );
		if ( ! $student || ( $session['mode'] ?? '' ) !== 'skill' ) {
			return null; }
		$reward = array();
		if ( Xp::enabled() ) {
			$earned       = Xp::for_session( $student, $session['uuid'] ) ?: array(
				'xp'     => 0,
				'parts'  => array(),
				'capped' => false,
			);
			$summary      = Xp::summary( $student );
			$reward['xp'] = $earned + array(
				'total'    => $summary['total'],
				'today'    => $summary['today'],
				'goal'     => $summary['goal'],
				'goal_met' => $summary['goal_met'],
				'week'     => $summary['week'],
			);
		}
		if ( Score::enabled() ) {
			$term            = get_term( (int) $session['term_id'], \OhMyLMS\Skills\Taxonomy::NAME );
			$reward['score'] = Score::for_session( $student, (int) $session['term_id'], (int) $session['id'] ) + array(
				'skill'  => $term && ! is_wp_error( $term ) ? $term->name : '',
				'unlock' => (int) Score::config()['unlock'],
			);
		}
		if ( StreakSettings::enabled() && StreakSchema::ready() ) {
			$streak = Streak::snapshot( $student, 7 );
			if ( $streak && ! is_wp_error( $streak ) ) {
				$reward['streak'] = array(
					'current'        => (int) $streak['current_streak'],
					'longest'        => (int) $streak['longest_streak'],
					'today_complete' => ! empty( $streak['today_complete'] ),
				); }
		}
		return $reward ? $reward : null;
	}
}
