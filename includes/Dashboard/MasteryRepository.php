<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping (OhMyLMS\ => includes/); see docs/CODING-STANDARDS.md.
/**
 * Read model of the mastery heatmap and the review-set recommendations.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Dashboard;

use OhMyLMS\Assessment\Schema as AssessmentSchema;
use OhMyLMS\Curriculum\Comparison;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only aggregates over `student_skill_state` (one row per learner and skill, derived from the skill
 * evidence by `Performance\Engine`) for the rows of a scope: its classes, or its courses when it has no
 * classes. A learner counts as assessed on a skill when the row has evidence (`evidence_count > 0`);
 * their percentage is the stored 0-100 `mastery` score. Every list is bounded.
 */
final class MasteryRepository {

	/** Most rows (classes or courses) a scope shows. */
	const MAX_ROWS = 20;

	/** Most learners read per row. */
	const MAX_ROW_LEARNERS = 1000;

	/** Most skill columns. */
	const MAX_SKILLS = 40;

	/**
	 * Skill state table.
	 *
	 * @return string
	 */
	private static function table() {
		return AssessmentSchema::table( 'student_skill_state' );
	}

	/**
	 * Run a prepared query returning rows.
	 *
	 * @param string $sql  SQL with placeholders.
	 * @param array  $args Values.
	 * @return array[]
	 * @throws \RuntimeException On a database error.
	 */
	private static function query( $sql, array $args ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Identifiers use %i, values use placeholders, id lists come from Scope::in_condition().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Dashboard query failed.' );
		}
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Rows of a scope: a class scope is its class; a school its active classes; `all` every active class.
	 * A scope without classes uses its courses with the most enrolled learners instead.
	 *
	 * @param Scope $scope Scope.
	 * @return array{type:string,total:int,rows:array[]} Each row: key (`class:9`, `course:5`), type, id, label,
	 *               count (learners in the row) and learners (sorted ids, at most MAX_ROW_LEARNERS).
	 */
	public static function rows( Scope $scope ) {
		if ( Scope::CLASSROOM === $scope->type() ) {
			$classes = array(
				array(
					'id'   => $scope->id(),
					'name' => $scope->label(),
				),
			);
		} else {
			$classes = Directory::classes( $scope->is_all() ? null : array( $scope->id() ), $scope->is_all() );
		}
		if ( $classes ) {
			$rows = array();
			foreach ( array_slice( $classes, 0, self::MAX_ROWS ) as $class ) {
				$learners = Directory::class_learners( (int) $class['id'] );
				$rows[]   = array(
					'key'      => 'class:' . (int) $class['id'],
					'type'     => 'class',
					'id'       => (int) $class['id'],
					'label'    => (string) $class['name'],
					'count'    => count( $learners ),
					'learners' => array_slice( $learners, 0, self::MAX_ROW_LEARNERS ),
				);
			}
			return array(
				'type'  => 'class',
				'total' => count( $classes ),
				'rows'  => $rows,
			);
		}
		$rows    = array();
		$courses = Enrolments::learners_by_course( $scope, self::MAX_ROWS * 2, self::MAX_ROW_LEARNERS );
		$exists  = Directory::existing_courses( array_keys( $courses ) );
		foreach ( $courses as $course_id => $course ) {
			if ( ! in_array( (int) $course_id, $exists, true ) || count( $rows ) >= self::MAX_ROWS ) {
				continue;
			}
			$rows[] = array(
				'key'      => 'course:' . (int) $course_id,
				'type'     => 'course',
				'id'       => (int) $course_id,
				'label'    => html_entity_decode( get_the_title( (int) $course_id ), ENT_QUOTES, 'UTF-8' ),
				'count'    => $course['count'],
				'learners' => $course['learners'],
			);
		}
		return array(
			'type'  => 'course',
			'total' => count( $rows ),
			'rows'  => $rows,
		);
	}

	/**
	 * One row of a scope by key, or null when the scope does not show it.
	 *
	 * @param Scope  $scope Scope.
	 * @param string $key   `class:<id>` or `course:<id>`.
	 * @return array|null
	 */
	public static function row( Scope $scope, $key ) {
		foreach ( self::rows( $scope )['rows'] as $row ) {
			if ( $row['key'] === $key ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * The skills most of the learners have evidence on, most learners first.
	 *
	 * @param int[] $learners Learners.
	 * @param int   $limit    Most skills.
	 * @return array[] Each: id, name, code, subject ('' when none), assessed (learners with evidence).
	 */
	public static function top_skills( array $learners, $limit = self::MAX_SKILLS ) {
		if ( ! $learners || ! AssessmentSchema::ready() ) {
			return array();
		}
		$args   = array( self::table() );
		$in     = Scope::in_condition( 'student_id', $learners, $args );
		$args[] = min( self::MAX_SKILLS, max( 1, (int) $limit ) ) * 2;
		$rows   = self::query( "SELECT term_id, COUNT(*) AS n FROM %i WHERE evidence_count > 0 AND $in GROUP BY term_id ORDER BY n DESC, term_id ASC LIMIT %d", $args );
		$terms  = self::terms( array_map( 'intval', wp_list_pluck( $rows, 'term_id' ) ) );
		$skills = array();
		foreach ( $rows as $row ) {
			$term = $terms[ (int) $row['term_id'] ] ?? null;
			if ( ! $term || count( $skills ) >= $limit ) {
				continue;
			}
			$skills[] = $term + array( 'assessed' => (int) $row['n'] );
		}
		return $skills;
	}

	/**
	 * Name, code and subject of skills that exist.
	 *
	 * @param int[] $ids Skill ids.
	 * @return array<int,array{id:int,name:string,code:string,subject:string}>
	 */
	public static function terms( array $ids ) {
		if ( ! $ids ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::NAME,
				'include'    => $ids,
				'hide_empty' => false,
			)
		);
		$found = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$found[ (int) $term->term_id ] = array(
				'id'      => (int) $term->term_id,
				'name'    => Taxonomy::plain( $term->name ),
				'code'    => (string) get_term_meta( $term->term_id, '_ohmylms_skill_code', true ),
				'subject' => sanitize_text_field( (string) get_term_meta( $term->term_id, Comparison::SUBJECT_META, true ) ),
			);
		}
		return $found;
	}

	/**
	 * Per skill, how many of the learners have evidence, the sum of their percentages and how many are
	 * below a percentage.
	 *
	 * @param int[] $learners Learners.
	 * @param int[] $skills   Skills.
	 * @param int   $below    Percentage (rounded) below which a learner counts as struggling.
	 * @return array<int,array{assessed:int,sum:float,below:int}> Skill id => figures; skills nobody has evidence on are absent.
	 */
	public static function skill_figures( array $learners, array $skills, $below ) {
		if ( ! $learners || ! $skills || ! AssessmentSchema::ready() ) {
			return array();
		}
		$args    = array( (int) $below, self::table() );
		$learner = Scope::in_condition( 'student_id', $learners, $args );
		$skill   = Scope::in_condition( 'term_id', $skills, $args );
		$rows    = self::query(
			"SELECT term_id, COUNT(*) AS assessed, SUM( LEAST( 100, GREATEST( 0, mastery ) ) ) AS total, SUM( ROUND( mastery ) < %d ) AS below
			FROM %i WHERE evidence_count > 0 AND $learner AND $skill GROUP BY term_id",
			$args
		);
		$figures = array();
		foreach ( $rows as $row ) {
			$figures[ (int) $row['term_id'] ] = array(
				'assessed' => (int) $row['assessed'],
				'sum'      => (float) $row['total'],
				'below'    => (int) $row['below'],
			);
		}
		return $figures;
	}

	/**
	 * Skills on which at least some of the learners are below a percentage, most such learners first.
	 *
	 * @param int[] $learners Learners.
	 * @param int   $below    Percentage (rounded) below which a learner counts as struggling.
	 * @param int   $min      Fewest struggling learners.
	 * @param int   $limit    Most skills.
	 * @return array<int,int> Skill id => struggling learners.
	 */
	public static function struggling_skills( array $learners, $below, $min, $limit ) {
		if ( ! $learners || ! AssessmentSchema::ready() ) {
			return array();
		}
		$args   = array( self::table(), (int) $below );
		$in     = Scope::in_condition( 'student_id', $learners, $args );
		$args[] = max( 1, (int) $min );
		$args[] = max( 1, (int) $limit );
		$rows   = self::query(
			"SELECT term_id, COUNT(*) AS n FROM %i WHERE evidence_count > 0 AND ROUND( mastery ) < %d AND $in
			GROUP BY term_id HAVING n >= %d ORDER BY n DESC, term_id ASC LIMIT %d",
			$args
		);
		$skills = array();
		foreach ( $rows as $row ) {
			$skills[ (int) $row['term_id'] ] = (int) $row['n'];
		}
		return $skills;
	}

	/**
	 * Learners with evidence on a skill who are below a percentage, lowest first.
	 *
	 * @param int[] $learners Learners.
	 * @param int   $skill    Skill.
	 * @param int   $below    Percentage (rounded) below which a learner counts as struggling.
	 * @param int   $limit    Most learners.
	 * @return array{total:int,assessed:int,learners:array[]} Learners: id, percent.
	 */
	public static function struggling_learners( array $learners, $skill, $below, $limit ) {
		$empty = array(
			'total'    => 0,
			'assessed' => 0,
			'learners' => array(),
		);
		if ( ! $learners || ! AssessmentSchema::ready() ) {
			return $empty;
		}
		$args    = array( (int) $below, self::table(), (int) $skill );
		$in      = Scope::in_condition( 'student_id', $learners, $args );
		$summary = self::query( "SELECT COUNT(*) AS assessed, SUM( ROUND( mastery ) < %d ) AS below FROM %i WHERE evidence_count > 0 AND term_id = %d AND $in", $args );
		$args    = array( self::table(), (int) $skill, (int) $below );
		$in      = Scope::in_condition( 'student_id', $learners, $args );
		$args[]  = max( 1, (int) $limit );
		$rows    = self::query( "SELECT student_id, mastery FROM %i WHERE evidence_count > 0 AND term_id = %d AND ROUND( mastery ) < %d AND $in ORDER BY mastery ASC, student_id ASC LIMIT %d", $args );
		return array(
			'total'    => (int) ( $summary[0]['below'] ?? 0 ),
			'assessed' => (int) ( $summary[0]['assessed'] ?? 0 ),
			'learners' => array_map(
				static function ( $row ) {
					return array(
						'id'      => (int) $row['student_id'],
						'percent' => (int) round( min( 100, max( 0, (float) $row['mastery'] ) ) ),
					);
				},
				$rows
			),
		);
	}
}
