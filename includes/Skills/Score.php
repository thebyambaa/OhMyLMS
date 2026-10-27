<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Stores and reads the 0-100 skill mastery score (see ScoreModel for the rules).
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Skills;

use OhMyLMS\Assessment\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and reads the 0-100 skill mastery score (see ScoreModel for the rules).
 *
 * The score is derived: it is recomputed from the learner's non-superseded primary-skill
 * evidence whenever that changes (Mastery::recompute), so regrades and rule changes are
 * applied exactly, and learners who practised before the feature was switched on get their
 * score the first time it is read. It sits beside the evidence-based level (developing,
 * proficient, mastered) and never replaces it.
 */
final class Score {
	const OPTION = 'ohmylms_score_settings';

	/**
	 * Defaults for Score.
	 */
	public static function defaults() {
		return array(
			'enable'           => false,
			'bands'            => array_map(
				static function ( $band ) {
					return array(
						'gain' => $band['gain'],
						'loss' => $band['loss'],
					);
				},
				ScoreModel::defaults()['bands']
			),
			'hint_gain'        => ScoreModel::defaults()['hint_gain'],
			'unlock'           => ScoreModel::defaults()['unlock'],
			'decay_grace_days' => ScoreModel::defaults()['decay_grace_days'],
			'decay_per_week'   => ScoreModel::defaults()['decay_per_week'],
		);
	}

	/**
	 * Get for Score.
	 */
	public static function get() {
		$settings = self::validate( array_replace( self::defaults(), (array) get_option( self::OPTION, array() ) ) );
		return is_wp_error( $settings ) ? self::defaults() : $settings;
	}

	/**
	 * Enabled for Score.
	 */
	public static function enabled() {
		return ! empty( self::get()['enable'] );
	}

	/** The model's configuration with the administrator's tuning applied. */
	public static function config() {
		$settings = self::get();
		return ScoreModel::config(
			array(
				'bands'            => $settings['bands'],
				'hint_gain'        => $settings['hint_gain'],
				'unlock'           => $settings['unlock'],
				'decay_grace_days' => $settings['decay_grace_days'],
				'decay_per_week'   => $settings['decay_per_week'],
			)
		);
	}

	/**
	 * Validate for Score.
	 *
	 * @return array|\WP_Error
	 * @param mixed $settings Configuration settings.
	 */
	public static function validate( $settings ) {
		$fail = static function () {
			return new \WP_Error( 'score_settings', __( 'Invalid mastery score settings.', 'ohmylms' ), array( 'status' => 400 ) );
		};
		if ( ! is_array( $settings ) ) {
			return $fail(); }
		$settings = array_replace( self::defaults(), $settings );
		if ( ! in_array( $settings['enable'], array( true, false, 0, 1, '0', '1' ), true ) ) {
			return $fail(); }
		$settings['enable'] = in_array( $settings['enable'], array( true, 1, '1' ), true );
		if ( ! is_array( $settings['bands'] ) || count( $settings['bands'] ) !== 4 ) {
			return $fail(); }
		$bands = array();
		foreach ( array_values( $settings['bands'] ) as $band ) {
			if ( ! is_array( $band ) || ! isset( $band['gain'], $band['loss'] ) || ! is_numeric( $band['gain'] ) || ! is_numeric( $band['loss'] )
				|| $band['gain'] < 0 || $band['gain'] > 50 || $band['loss'] < 0 || $band['loss'] > 50 ) {
				return $fail(); }
			$bands[] = array(
				'gain' => (float) $band['gain'],
				'loss' => (float) $band['loss'],
			);
		}
		$settings['bands'] = $bands;
		foreach ( array(
			'hint_gain'      => array( 0, 1 ),
			'decay_per_week' => array( 0, 20 ),
		) as $key => $range ) {
			if ( ! is_numeric( $settings[ $key ] ) || $settings[ $key ] < $range[0] || $settings[ $key ] > $range[1] ) {
				return $fail(); }
			$settings[ $key ] = (float) $settings[ $key ];
		}
		foreach ( array(
			'unlock'           => array( 50, 100 ),
			'decay_grace_days' => array( 0, 365 ),
		) as $key => $range ) {
			if ( filter_var( $settings[ $key ], FILTER_VALIDATE_INT ) === false || $settings[ $key ] < $range[0] || $settings[ $key ] > $range[1] ) {
				return $fail(); }
			$settings[ $key ] = (int) $settings[ $key ];
		}
		return array_intersect_key( $settings, self::defaults() );
	}

	/** Primary-skill evidence of one learner and skill, oldest first.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $term_id Term identifier.
	 */
	public static function rows( $student_id, $term_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, grade_event_id, awarded, available, independent, first_try, difficulty, evidence_at, source_type, source_id, question_id FROM %i WHERE student_id=%d AND term_id=%d AND role=\'primary\' AND superseded=0 ORDER BY evidence_at ASC, id ASC', Schema::table( 'skill_evidence' ), (int) $student_id, (int) $term_id ),
			ARRAY_A
		);
	}

	/** The score story of a skill, row by row; keyed by grade event for lookups.
	 *
	 * @param mixed $student_id Student identifier.
	 * @param mixed $term_id Term identifier.
	 */
	public static function trace( $student_id, $term_id ) {
		$run   = ScoreModel::run( self::rows( $student_id, $term_id ), self::config() );
		$by_id = array();
		foreach ( $run['trace'] as $step ) {
			if ( $step['grade_event_id'] ) {
				$by_id[ (int) $step['grade_event_id'] ] = $step; }
		}
		return $by_id;
	}

	/**
	 * Score before and after one practice session, from the same evidence, for the lesson summary.
	 *
	 * @return array{before:float,after:float,delta:float,medal:string,reached:int[]}
	 * @param mixed $student_id Student identifier.
	 * @param mixed $term_id Term identifier.
	 * @param mixed $session_id Session identifier.
	 */
	public static function for_session( $student_id, $term_id, $session_id ) {
		$rows    = self::rows( $student_id, $term_id );
		$prior   = array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $session_id ) {
					return ! ( 'practice' === $row['source_type'] && (int) $row['source_id'] === (int) $session_id );
				}
			)
		);
		$config  = self::config();
		$before  = ScoreModel::run( $prior, $config );
		$after   = ScoreModel::run( $rows, $config );
		$reached = array();
		foreach ( ScoreModel::MEDALS as $at => $name ) {
			if ( $after['peak'] >= $at - 1e-9 && $before['peak'] < $at - 1e-9 ) {
				$reached[] = $at; }
		}
		return array(
			'before'  => $before['score'],
			'after'   => $after['score'],
			'delta'   => round( $after['score'] - $before['score'], 2 ),
			'medal'   => $after['medal'],
			'reached' => $reached,
		);
	}

	/** One skill's presentable score, or null if the skill has not been scored.
	 *
	 * @param array $row Row.
	 * @param mixed $now Now.
	 */
	public static function describe( array $row, $now = null ) {
		$config    = self::config();
		$now       = $now ? $now : time();
		$effective = ScoreModel::effective( (float) $row['mastery'], $row['last_scored_at'] ? strtotime( $row['last_scored_at'] . ' UTC' ) : null, $now, $config );
		$score     = $effective['score'];
		return array(
			'score'      => $score,
			'stored'     => (float) $row['mastery'],
			'band'       => ScoreModel::band_name( $score, $config['bands'] ),
			'medal'      => ScoreModel::medal( (float) $row['peak'] ),
			'unlocked'   => $score >= (int) $config['unlock'] - 1e-9,
			'review_due' => $effective['review_due'],
			'struggling' => (bool) $row['struggling'],
			'scored'     => (int) $row['scored'],
		);
	}

	/**
	 * Every scored skill of a learner by term ID. A skill with evidence but no stored score
	 * (practised before the feature was switched on) is computed here, once.
	 *
	 * @param mixed $student_id Student identifier.
	 */
	public static function summary( $student_id ) {
		return self::summary_many( array( $student_id ) )[ (int) $student_id ] ?? array();
	}

	/**
	 * Every scored skill of several learners: one query however many learners there are.
	 *
	 * @param int[] $student_ids Learner IDs.
	 * @return array<int,array<int,array>> Learner => term => described score; every learner given is present.
	 */
	public static function summary_many( array $student_ids ) {
		global $wpdb;
		$students = array_values( array_unique( array_filter( array_map( 'intval', $student_ids ) ) ) );
		$result   = array_fill_keys( $students, array() );
		if ( ! $students || ! self::enabled() ) {
			return $result; }
		$holders = implode( ',', array_fill( 0, count( $students ), '%d' ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT student_id, term_id, mastery, peak, scored, struggling, last_scored_at FROM %i WHERE student_id IN ($holders) AND scored>0", array_merge( array( Schema::table( 'student_skill_state' ) ), $students ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fresh read of the learners' own states.
		foreach ( $rows as $row ) {
			$result[ (int) $row['student_id'] ][ (int) $row['term_id'] ] = self::describe( $row ); }
		return $result;
	}
}
