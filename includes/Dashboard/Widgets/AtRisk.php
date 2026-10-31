<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * "At-risk learners": who is flagged by the at-risk rules, and why.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard\Widgets;

use OhMyLMS\Dashboard\Cache;
use OhMyLMS\Dashboard\Period;
use OhMyLMS\Dashboard\Scope;
use OhMyLMS\Dashboard\Widget;

defined( 'ABSPATH' ) || exit;

/**
 * `GET /dashboard/widgets/at-risk`: the learners of the scope that {@see AtRiskRules} flags, most urgent
 * first, with their reasons and level. Minimal personal data: id, display name and initials. The cached list
 * is shared by everyone who may see the scope; reason texts (in the reader's language), the student report
 * link (administrators only) and the reader's own nudges are added per user in {@see AtRisk::present()}.
 */
final class AtRisk extends Widget {

	const ID = 'at-risk';

	/**
	 * Most flagged learners kept in the cached list (and so nudgeable from it).
	 */
	const MAX_CACHED = 500;

	/**
	 * Most learners returned to the client.
	 */
	const MAX_SHOWN = 50;

	/**
	 * Capability of the student report screen.
	 */
	const REPORT_CAPABILITY = 'manage_options';

	/**
	 * Widget id.
	 *
	 * @return string
	 */
	public function id() {
		return self::ID;
	}

	/**
	 * Flagged learners of a scope and period.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array{learners:array[],counts:array{high:int,watch:int}}
	 */
	public function compute( Scope $scope, Period $period ) {
		$rules   = AtRiskSettings::rules();
		$now     = time();
		$flagged = array();
		foreach ( AtRiskSignals::collect( $scope, $period, $rules, $now ) as $id => $signals ) {
			$result = AtRiskRules::evaluate( $signals, $rules );
			if ( null !== $result ) {
				$flagged[ $id ] = $result + array( 'weight' => AtRiskRules::weight( $result, $rules ) );
			}
		}
		$names   = AtRiskSignals::names( array_keys( $flagged ) );
		$flagged = array_intersect_key( $flagged, $names );
		$counts  = array(
			'high'  => 0,
			'watch' => 0,
		);
		foreach ( $flagged as $result ) {
			++$counts[ $result['level'] ];
		}
		uksort(
			$flagged,
			static function ( $a, $b ) use ( $flagged, $names ) {
				return $flagged[ $b ]['weight'] <=> $flagged[ $a ]['weight'] ?: strcasecmp( $names[ $a ], $names[ $b ] ) ?: $a <=> $b;
			}
		);
		$learners = array();
		foreach ( array_slice( $flagged, 0, self::MAX_CACHED, true ) as $id => $result ) {
			$learners[] = array(
				'id'       => (int) $id,
				'name'     => $names[ $id ],
				'initials' => self::initials( $names[ $id ] ),
				'level'    => $result['level'],
				'reasons'  => $result['reasons'],
			);
		}
		return array(
			'learners' => $learners,
			'counts'   => $counts,
		);
	}

	/**
	 * Per user: reason texts, the report link the user may open and the user's own nudges.
	 *
	 * @param array  $data    Cached data.
	 * @param Scope  $scope   Scope.
	 * @param Period $period  Period.
	 * @param int    $user_id User.
	 * @return array
	 */
	public function present( array $data, Scope $scope, Period $period, $user_id ) {
		$settings = AtRiskSettings::read();
		$rules    = AtRiskSettings::rules( $settings );
		$learners = array_slice( (array) ( $data['learners'] ?? array() ), 0, self::MAX_SHOWN );
		$reports  = user_can( (int) $user_id, self::REPORT_CAPABILITY );
		$last     = AtRiskNudges::last_sent( (int) $user_id, array_map( 'intval', wp_list_pluck( $learners, 'id' ) ) );
		$shown    = array();
		foreach ( $learners as $learner ) {
			$reasons = array();
			foreach ( (array) $learner['reasons'] as $reason ) {
				$reasons[] = $reason + array( 'text' => AtRiskRules::text( $reason, $rules ) );
			}
			$sent    = $last[ (int) $learner['id'] ] ?? null;
			$shown[] = array(
				'id'           => (int) $learner['id'],
				'name'         => (string) $learner['name'],
				'initials'     => (string) $learner['initials'],
				'level'        => (string) $learner['level'],
				'reasons'      => $reasons,
				'summary'      => AtRiskRules::summary( $reasons, $rules ),
				'report'       => $reports ? '/students/' . (int) $learner['id'] . '/report' : null,
				'nudged_at'    => null === $sent ? null : gmdate( 'Y-m-d\TH:i:s\Z', $sent ),
				'nudged_label' => null === $sent ? '' : AtRiskNudges::date_label( $sent ),
				'can_nudge'    => null === $sent || $sent + AtRiskNudges::INTERVAL <= time(),
			);
		}
		$counts = (array) ( $data['counts'] ?? array() );
		return array(
			'learners' => $shown,
			'counts'   => array(
				'high'  => (int) ( $counts['high'] ?? 0 ),
				'watch' => (int) ( $counts['watch'] ?? 0 ),
			),
			'total'    => count( (array) ( $data['learners'] ?? array() ) ),
			'rules'    => array(
				'inactive' => $rules['inactive']['enabled'],
				'mastery'  => $rules['mastery']['enabled'],
				'overdue'  => $rules['overdue']['enabled'],
			),
			'nudge'    => array(
				'cap'            => (int) $settings['nudge_daily_cap'],
				'remaining'      => AtRiskNudges::remaining_today( (int) $user_id, (int) $settings['nudge_daily_cap'] ),
				'interval_hours' => (int) ( AtRiskNudges::INTERVAL / HOUR_IN_SECONDS ),
			),
		);
	}

	/**
	 * Up to two initials of a display name.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	public static function initials( $name ) {
		$words    = preg_split( '/[\s\-_.]+/u', trim( (string) $name ), -1, PREG_SPLIT_NO_EMPTY );
		$initials = '';
		foreach ( array_slice( (array) $words, 0, 2 ) as $word ) {
			$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
		}
		return '' === $initials ? '?' : $initials;
	}

	/**
	 * The widget's cached data for a scope and period, computed and stored when missing. Reads the same
	 * entry as the widget route without going through {@see Cache::remember()}, so it can run inside another
	 * widget's computation without changing that request's hit/miss report.
	 *
	 * @param Scope  $scope  Scope.
	 * @param Period $period Period.
	 * @return array Compute output.
	 */
	public static function cached( Scope $scope, Period $period ) {
		$widget = new self();
		$ttl    = Cache::ttl( self::ID );
		$key    = Cache::key( self::ID, $scope, $period );
		$entry  = $ttl > 0 ? get_transient( $key ) : false;
		if ( ! is_array( $entry ) ) {
			$entry = array(
				'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'data'         => $widget->compute( $scope, $period ),
			);
			if ( $ttl > 0 ) {
				set_transient( $key, $entry, $ttl );
			}
		}
		return (array) ( $entry['data'] ?? array() );
	}
}
