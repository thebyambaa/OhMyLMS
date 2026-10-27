<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Turns a learner's evidence on one skill into the stored skill state.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Performance\Policies\EvidencePolicy;
use OhMyLMS\Skills\Mastery;
use OhMyLMS\Skills\Score;
use OhMyLMS\Skills\ScoreModel;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reads and writes the plugin's own derived tables; table names come from Schema::table() and every value is prepared.
/**
 * The one place a skill state is decided. The evidence ledger (`skill_evidence`) is the only input;
 * the active policy decides the level, and the 0-100 mastery score, accuracy, review timing and
 * counts are computed from the same rows. The result is one row per learner and skill in
 * `student_skill_state`, and a short log of what each recent activity did to the level in
 * `skill_level_events`. Both are derived, so a policy or rule change is a rebuild, not a migration.
 */
final class Engine {

	/** How many of the newest activities get a level-change record on each recompute. */
	const RECENT_ACTIVITIES = 3;

	/**
	 * A learner's countable evidence on one skill, oldest first.
	 *
	 * @param int $student_id Learner.
	 * @param int $term_id    Skill.
	 * @return array[]
	 */
	public static function rows( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, grade_event_id, awarded, available, independent, first_try, difficulty, family_id, question_id, source_type, source_id, evidence_at FROM ' . Schema::table( 'skill_evidence' ) . " WHERE student_id=%d AND term_id=%d AND role='primary' AND superseded=0 ORDER BY evidence_at ASC, id ASC",
				(int) $student_id,
				(int) $term_id
			),
			ARRAY_A
		);
	}

	/**
	 * The state evidence rows earn. Pure apart from reading settings.
	 *
	 * @param array[]     $rows   Evidence rows, oldest first.
	 * @param int         $now    Timestamp.
	 * @param Policy|null $policy Policy to use; the site's by default.
	 * @return array Stored columns: level, policy, review_due, review_due_at, evidence_count, independent_correct, families, accuracy, last_evidence_at, mastery, peak, scored, correct, struggling, last_scored_at.
	 */
	public static function evaluate( array $rows, $now, ?Policy $policy = null ) {
		$policy  = $policy ? $policy : Settings::policy();
		$rules   = Mastery::rules();
		$config  = Score::config();
		$run     = ScoreModel::run( $rows, $config );
		$context = array(
			'rules'  => $rules,
			'bands'  => $config['bands'],
			'score'  => $run['score'],
			'scored' => $run['scored'],
			'now'    => (int) $now,
		);
		$state   = EvidencePolicy::evaluate( $rows, $rules, $now );
		$level   = $policy->level( $rows, $context );
		// The review clock follows the level the policy decided, whichever policy that is.
		$last          = $state['last_evidence_at'] ? strtotime( $state['last_evidence_at'] . ' UTC' ) : null;
		$review_due_at = Levels::is_proficient( $level ) && $last ? $last + (int) $rules['review_after_days'] * DAY_IN_SECONDS : null;
		$last_scored   = null;
		foreach ( $run['trace'] as $i => $step ) {
			if ( $step['counted'] ) {
				$last_scored = $rows[ $i ]['evidence_at'];
			}
		}
		return array(
			'level'               => $level,
			'policy'              => $policy->id(),
			'review_due'          => null !== $review_due_at && $now >= $review_due_at ? 1 : 0,
			'review_due_at'       => $review_due_at ? gmdate( 'Y-m-d H:i:s', $review_due_at ) : null,
			'evidence_count'      => $state['evidence_count'],
			'independent_correct' => $state['independent_correct'],
			'families'            => $state['families'],
			'accuracy'            => $state['accuracy'],
			'last_evidence_at'    => $state['last_evidence_at'],
			'mastery'             => $run['score'],
			'peak'                => $run['peak'],
			'scored'              => $run['scored'],
			'correct'             => $run['correct'],
			'struggling'          => $run['struggling'] ? 1 : 0,
			'last_scored_at'      => $last_scored,
		);
	}

	/**
	 * What each of the newest activities did to the level, oldest first.
	 *
	 * @param array[] $rows Evidence rows, oldest first.
	 * @param int     $now  Timestamp.
	 * @return array[] Each: source_type, source_id, before, after, detail (right, wrong, total).
	 */
	public static function transitions( array $rows, $now ) {
		$activities = Policies\StepsPolicy::activities( $rows );
		$recent     = array_slice( $activities, -self::RECENT_ACTIVITIES );
		$keys       = array();
		foreach ( $rows as $index => $row ) {
			$keys[ ( $row['source_type'] ?? '' ) . ':' . (int) ( $row['source_id'] ?? 0 ) ] = $index;
		}
		$result = array();
		foreach ( $recent as $activity ) {
			$last     = $keys[ $activity['type'] . ':' . $activity['id'] ] ?? count( $rows ) - 1;
			$before   = self::level_of( array_slice( $rows, 0, self::before_index( $rows, $activity ) ), $now );
			$after    = self::level_of( array_slice( $rows, 0, $last + 1 ), $now );
			$result[] = array(
				'source_type' => $activity['type'],
				'source_id'   => $activity['id'],
				'before'      => $before,
				'after'       => $after,
				'detail'      => array(
					'right' => $activity['right'],
					'wrong' => $activity['wrong'],
					'total' => $activity['total'],
				),
			);
		}
		return $result;
	}

	/**
	 * Recompute and store one learner's state for a skill, and log what the newest activities did.
	 *
	 * @param int $student_id Learner.
	 * @param int $term_id    Skill.
	 * @return array The stored state.
	 */
	public static function recompute( $student_id, $term_id ) {
		global $wpdb;
		$student_id = (int) $student_id;
		$term_id    = (int) $term_id;
		$now        = time();
		$rows       = self::rows( $student_id, $term_id );
		$state      = self::evaluate( $rows, $now );
		$table      = Schema::table( 'student_skill_state' );
		$stored     = $wpdb->get_row( $wpdb->prepare( "SELECT id, milestones FROM $table WHERE student_id=%d AND term_id=%d", $student_id, $term_id ), ARRAY_A );
		$reached    = array_keys(
			array_filter(
				ScoreModel::MEDALS,
				static function ( $name, $at ) use ( $state ) {
					return $state['peak'] >= $at - 1e-9;
				},
				ARRAY_FILTER_USE_BOTH
			)
		);
		$known      = $stored && '' !== (string) $stored['milestones'] ? array_map( 'intval', explode( ',', $stored['milestones'] ) ) : array();
		$record     = $state + array(
			'student_id' => $student_id,
			'term_id'    => $term_id,
			'milestones' => implode( ',', array_unique( array_merge( $known, $reached ) ) ),
			'updated_at' => current_time( 'mysql', true ),
		);
		if ( $stored ) {
			$wpdb->update( $table, $record, array( 'id' => (int) $stored['id'] ) );
		} else {
			$wpdb->insert( $table, $record );
		}
		self::log( $student_id, $term_id, self::transitions( $rows, $now ) );
		do_action( 'ohmylms_skill_state_updated', $student_id, $term_id, $record );
		if ( Score::enabled() ) {
			// A medal is announced once, the first time that score is reached, even if it later slips.
			foreach ( array_diff( $reached, $known ) as $at ) {
				do_action( 'ohmylms_skill_score_milestone', $student_id, $term_id, (int) $at, ScoreModel::MEDALS[ $at ] );
			}
			do_action( 'ohmylms_skill_score_updated', $student_id, $term_id, $state['mastery'], ScoreModel::run( $rows, Score::config() ) );
		}
		return $record;
	}

	/**
	 * Level the active policy gives some evidence.
	 *
	 * @param array[] $rows Evidence rows, oldest first.
	 * @param int     $now  Timestamp.
	 * @return string
	 */
	private static function level_of( array $rows, $now ) {
		return self::evaluate( $rows, $now )['level'];
	}

	/**
	 * Index just before an activity's first row, for the level it started from.
	 *
	 * @param array[] $rows     Evidence rows.
	 * @param array   $activity One of StepsPolicy::activities().
	 * @return int
	 */
	private static function before_index( array $rows, array $activity ) {
		foreach ( $rows as $index => $row ) {
			if ( ( $row['source_type'] ?? '' ) === $activity['type'] && (int) ( $row['source_id'] ?? 0 ) === $activity['id'] ) {
				return $index;
			}
		}
		return count( $rows );
	}

	/**
	 * Store level-change records; an activity already logged is updated in place.
	 *
	 * @param int     $student_id  Learner.
	 * @param int     $term_id     Skill.
	 * @param array[] $transitions From transitions().
	 */
	private static function log( $student_id, $term_id, array $transitions ) {
		global $wpdb;
		$table = Schema::table( 'skill_level_events' );
		foreach ( $transitions as $change ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $table (student_id, term_id, source_type, source_id, level_before, level_after, detail, created_at) VALUES (%d, %d, %s, %d, %s, %s, %s, %s)
					ON DUPLICATE KEY UPDATE level_before=VALUES(level_before), level_after=VALUES(level_after), detail=VALUES(detail)",
					$student_id,
					$term_id,
					$change['source_type'],
					$change['source_id'],
					$change['before'],
					$change['after'],
					wp_json_encode( $change['detail'] ),
					current_time( 'mysql', true )
				)
			);
		}
	}

	/**
	 * What one activity did to the learner's skills, for the summary screen.
	 *
	 * @param int    $student_id  Learner.
	 * @param string $source_type practice, quiz or question-player.
	 * @param int    $source_id   Session or attempt ID.
	 * @return array[] Each: skill (name), before, after, change (up, down or same).
	 */
	public static function changes( $student_id, $source_type, $source_id ) {
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT term_id, level_before, level_after FROM ' . Schema::table( 'skill_level_events' ) . ' WHERE student_id=%d AND source_type=%s AND source_id=%d ORDER BY id', (int) $student_id, (string) $source_type, (int) $source_id ), ARRAY_A );
		$result = array();
		foreach ( $rows as $row ) {
			$term     = get_term( (int) $row['term_id'] );
			$delta    = Levels::rank( $row['level_after'] ) <=> Levels::rank( $row['level_before'] );
			$result[] = array(
				'skill'  => $term && ! is_wp_error( $term ) ? $term->name : '',
				'before' => (string) $row['level_before'],
				'after'  => (string) $row['level_after'],
				'change' => $delta > 0 ? 'up' : ( $delta < 0 ? 'down' : 'same' ),
			);
		}
		return $result;
	}

	/**
	 * Recompute stored states after a policy change, a slice at a time.
	 *
	 * @param int $after Last state row ID handled.
	 * @param int $limit Rows to handle.
	 * @return int The last row ID handled, or 0 when there is nothing left.
	 */
	public static function rebuild_states( $after = 0, $limit = 100 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, student_id, term_id FROM ' . Schema::table( 'student_skill_state' ) . ' WHERE id>%d ORDER BY id LIMIT %d', (int) $after, max( 1, (int) $limit ) ), ARRAY_A );
		foreach ( $rows as $row ) {
			self::recompute( (int) $row['student_id'], (int) $row['term_id'] );
		}
		return $rows ? (int) end( $rows )['id'] : 0;
	}

	/**
	 * Rebuild the evidence of some learners from their grade events, a slice at a time. Used when the
	 * set of activities that count changes.
	 *
	 * @param int $after Last learner ID handled.
	 * @param int $limit Learners to handle.
	 * @return int The last learner ID handled, or 0 when there is nothing left.
	 */
	public static function rebuild_evidence( $after = 0, $limit = 20 ) {
		global $wpdb;
		$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT student_id FROM ' . Schema::table( 'grade_events' ) . ' WHERE student_id>%d ORDER BY student_id LIMIT %d', (int) $after, max( 1, (int) $limit ) ) ) );
		foreach ( $ids as $id ) {
			\OhMyLMS\Skills\Evidence::rebuild( $id );
		}
		return $ids ? (int) end( $ids ) : 0;
	}

	/**
	 * Queue a background rebuild.
	 *
	 * @param bool $evidence Also rebuild the evidence, not only the states.
	 */
	public static function schedule_rebuild( $evidence = false ) {
			wp_schedule_single_event( time() + 5, 'ohmylms_performance_rebuild', array( $evidence ? 'evidence' : 'states', 0 ) );
	}

	/**
	 * One step of the background rebuild; queues the next until every row is handled.
	 *
	 * @param string $mode  states or evidence.
	 * @param int    $after Cursor from the previous step.
	 */
	public static function rebuild_step( $mode, $after ) {
			$next = 'evidence' === $mode ? self::rebuild_evidence( (int) $after ) : self::rebuild_states( (int) $after );
		if ( $next ) {
			wp_schedule_single_event( time() + 5, 'ohmylms_performance_rebuild', array( $mode, $next ) );
		}
	}
}
