<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The one way to read where learners stand on skills.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\Recommendations;
use OhMyLMS\Skills\Score;
use OhMyLMS\Skills\ScoreModel;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only views of `student_skill_state`, shared by the learner dashboard, the teacher and school
 * reports, course outlines, bundles, MCP tools and the AI notes. Nothing here writes; Engine does.
 */
final class Snapshot {

	/**
	 * Where each learner stands on each skill: one query however many learners there are.
	 *
	 * @param int[] $student_ids Learner IDs.
	 * @param int[] $term_ids    Skill term IDs.
	 * @return array<int,array<int,array{key:string,label:string,score:?float,policy:string}>> Learner => skill => entry; skills never practised are Not started.
	 */
	public static function students( array $student_ids, array $term_ids ) {
		global $wpdb;
		$terms    = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		$students = array_values( array_unique( array_map( 'intval', $student_ids ) ) );
		$blank    = array();
		foreach ( $terms as $term ) {
			$blank[ $term ] = self::entry( 'not-started', null, 'none' );
		}
		$result = array();
		foreach ( $students as $student ) {
			$result[ $student ] = $blank;
		}
		$students = array_values( array_filter( $students ) );
		if ( ! $students || ! $terms || ! Schema::ready() ) {
			return $result;
		}
		$people  = implode( ',', array_fill( 0, count( $students ), '%d' ) );
		$holders = implode( ',', array_fill( 0, count( $terms ), '%d' ) );
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT student_id, term_id, level, policy, mastery, peak, scored, last_scored_at FROM %i WHERE student_id IN ($people) AND term_id IN ($holders)", array_merge( array( Schema::table( 'student_skill_state' ) ), $students, $terms ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fresh read of the learners' own states.
		foreach ( $rows as $row ) {
			$result[ (int) $row['student_id'] ][ (int) $row['term_id'] ] = self::entry( (string) $row['level'], Score::enabled() && Settings::get()['show_score'] && (int) $row['scored'] > 0 ? Score::describe( $row )['score'] : null, (string) $row['policy'] );
		}
		return $result;
	}

	/**
	 * Where one learner stands on each skill.
	 *
	 * @param int   $student_id Learner ID.
	 * @param int[] $term_ids   Skill term IDs.
	 * @return array<int,array{key:string,label:string,score:?float,policy:string}> Skill => entry.
	 */
	public static function student( $student_id, array $term_ids ) {
		$student_id = (int) $student_id;
		return self::students( array( $student_id ), $term_ids )[ $student_id ];
	}

	/**
	 * Skills a learner has any recorded state for.
	 *
	 * @param int $student_id Learner ID.
	 * @return int[]
	 */
	public static function started_terms( $student_id ) {
		global $wpdb;
		if ( ! (int) $student_id || ! Schema::ready() ) {
			return array();
		}
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT term_id FROM %i WHERE student_id=%d', Schema::table( 'student_skill_state' ), (int) $student_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fresh read of the learner's own skills.
	}

	/**
	 * How many of a learner's started skills sit at each level.
	 *
	 * @param int $student_id Learner ID.
	 * @return array{attempted:int,familiar:int,proficient:int,mastered:int,total:int}
	 */
	public static function counts( $student_id ) {
		$counts = array(
			'attempted'  => 0,
			'familiar'   => 0,
			'proficient' => 0,
			'mastered'   => 0,
			'total'      => 0,
		);
		if ( ! (int) $student_id ) {
			return $counts;
		}
		foreach ( self::student( (int) $student_id, self::started_terms( (int) $student_id ) ) as $entry ) {
			if ( isset( $counts[ $entry['key'] ] ) ) {
				++$counts[ $entry['key'] ];
				++$counts['total'];
			}
		}
		return $counts;
	}

	/**
	 * Everything a dashboard or report shows about a learner's skills, sorted by skill name.
	 *
	 * @param int $student_id Learner ID.
	 * @return array[] Each skill's details (see Taxonomy::describe) plus level, level_label, review_due, evidence_count, independent_correct, families, recent_accuracy, last_evidence_at, trend and, with the score switched on, mastery.
	 */
	public static function skills( $student_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return array();
		}
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE student_id=%d', Schema::table( 'student_skill_state' ), (int) $student_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fresh read of the learner's own states.
		$scores = Score::summary( (int) $student_id );
		$result = array();
		foreach ( $rows as $row ) {
			$skill = Taxonomy::describe( (int) $row['term_id'] );
			if ( ! $skill ) {
				continue;
			}
			$result[] = $skill + ( isset( $scores[ (int) $row['term_id'] ] ) ? array( 'mastery' => $scores[ (int) $row['term_id'] ] ) : array() ) + array(
				'level'               => $row['level'],
				'level_label'         => Levels::label( $row['level'] ),
				'policy'              => $row['policy'],
				'review_due'          => (bool) $row['review_due'],
				'evidence_count'      => (int) $row['evidence_count'],
				'independent_correct' => (int) $row['independent_correct'],
				'families'            => (int) $row['families'],
				'recent_accuracy'     => (float) $row['accuracy'],
				'last_evidence_at'    => $row['last_evidence_at'],
				'struggling'          => (bool) $row['struggling'],
			);
		}
		usort(
			$result,
			static function ( $left, $right ) {
				return strcmp( $left['name'], $right['name'] );
			}
		);
		return $result;
	}

	/**
	 * Next steps for a learner (help, review, ready), from the same states.
	 *
	 * @param int $student_id Learner ID.
	 * @param int $limit      How many to return.
	 * @return array[]
	 */
	public static function next_steps( $student_id, $limit = 6 ) {
		return Recommendations::for_student( (int) $student_id, $limit );
	}

	/**
	 * Guidance for a learner: strength by area, next steps, reviews covered by other work and
	 * readiness for each syllabus they have started (see Guidance).
	 *
	 * @param int    $student_id Learner ID.
	 * @param int    $limit      Most next steps.
	 * @param string $subject    One subject, or empty for all.
	 * @return array
	 */
	public static function guidance( $student_id, $limit = 6, $subject = '' ) {
		return Guidance::for_student( (int) $student_id, (int) $limit, (string) $subject );
	}

	/**
	 * Mastery score band names, for display beside a level.
	 *
	 * @param float $mastery Score.
	 * @return string learning, building, proficient or challenge.
	 */
	public static function band( $mastery ) {
		return ScoreModel::band_name( (float) $mastery, Score::config()['bands'] );
	}

	/**
	 * One snapshot entry.
	 *
	 * @param string     $key    Level.
	 * @param float|null $score  Mastery score to show, when it is switched on.
	 * @param string     $policy Policy that decided the level, or none.
	 * @return array{key:string,label:string,score:?float,policy:string}
	 */
	private static function entry( $key, $score, $policy ) {
		return array(
			'key'    => $key,
			'label'  => Levels::label( $key ),
			'score'  => $score,
			'policy' => $policy,
		);
	}
}
