<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Removes skill links and learner skill data that point to something deleted.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\SkillMap;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps skill connections pointing at things that exist.
 *
 * When a question, quiz, skill or user is deleted, its skill rows go with it: question and quiz
 * connections, and a learner's evidence, levels and level history. Frozen question versions are
 * history that old attempts report from, so they are left to QuestionBank\Usage. A one-time sweep
 * removes rows left behind before these hooks existed.
 */
final class Cleanup {

	/** Option recording which sweep has run. */
	const SWEPT = 'ohmylms_skill_cleanup_version';

	/** Current sweep; raise it to run the sweep again after adding a rule. */
	const VERSION = 1;

	/** Learner tables keyed by student and skill. */
	const LEARNER_TABLES = array( 'skill_evidence', 'student_skill_state', 'skill_level_events' );

	/** Register the hooks. */
	public static function init() {
		add_action( 'before_delete_post', array( __CLASS__, 'post_deleted' ) );
		// Core fires `delete_term` after the term is gone from the database.
		add_action( 'delete_term', array( __CLASS__, 'term_deleted' ), 10, 3 );
		add_action( 'deleted_user', array( __CLASS__, 'user_deleted' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_sweep' ) );
	}

	/**
	 * A deleted question or quiz leaves no skill connections.
	 *
	 * @param int $post_id Post being deleted.
	 */
	public static function post_deleted( $post_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return;
		}
		$type = get_post_type( $post_id );
		if ( OHMYLMS_QUESTION_CPT === $type ) {
			$wpdb->delete( Schema::table( 'question_skills' ), array( 'question_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing the deleted question's rows.
		} elseif ( OHMYLMS_QUIZ_CPT === $type ) {
			$wpdb->delete( Schema::table( 'quiz_skills' ), array( 'quiz_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing the deleted quiz's rows.
		}
	}

	/**
	 * A deleted skill leaves no question or quiz connections and no learner levels.
	 *
	 * @param int    $term_id  Deleted term.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function term_deleted( $term_id, $tt_id, $taxonomy ) {
		if ( Taxonomy::NAME !== $taxonomy || ! Schema::ready() ) {
			return;
		}
		self::forget_terms( array( (int) $term_id ) );
	}

	/**
	 * A deleted user leaves no skill evidence, levels or level history.
	 *
	 * @param int $user_id Deleted user.
	 */
	public static function user_deleted( $user_id ) {
		global $wpdb;
		if ( ! Schema::ready() ) {
			return;
		}
		foreach ( self::LEARNER_TABLES as $table ) {
			$wpdb->delete( Schema::table( $table ), array( 'student_id' => (int) $user_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removing the deleted user's rows.
		}
	}

	/** Run the sweep once per version, from an administrator's request. */
	public static function maybe_sweep() {
		if ( ! Schema::ready() || (int) get_option( self::SWEPT, 0 ) >= self::VERSION || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::sweep();
		update_option( self::SWEPT, self::VERSION, false );
	}

	/**
	 * Remove every skill row that points to a deleted question, quiz, skill or user.
	 *
	 * @return array<string,int> Rows removed by kind.
	 */
	public static function sweep() {
		global $wpdb;
		$removed = array();
		if ( ! Schema::ready() ) {
			return $removed;
		}
		$questions = Schema::table( 'question_skills' );
		$quizzes   = Schema::table( 'quiz_skills' );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-time maintenance over the plugin's own tables.
		$removed['question_skills'] = (int) $wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s LEFT JOIN %i p ON p.ID=s.question_id WHERE p.ID IS NULL', $questions, $wpdb->posts ) );
		$removed['quiz_skills']     = (int) $wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s LEFT JOIN %i p ON p.ID=s.quiz_id WHERE p.ID IS NULL', $quizzes, $wpdb->posts ) );
		foreach ( self::LEARNER_TABLES as $table ) {
			$removed[ $table ] = (int) $wpdb->query( $wpdb->prepare( 'DELETE s FROM %i s LEFT JOIN %i u ON u.ID=s.student_id WHERE u.ID IS NULL', Schema::table( $table ), $wpdb->users ) );
		}
		// Skills that no longer exist, wherever they are still named.
		$missing = array();
		foreach ( array( $questions, $quizzes, Schema::table( 'skill_evidence' ), Schema::table( 'student_skill_state' ), Schema::table( 'skill_level_events' ) ) as $table ) {
			$missing = array_merge( $missing, $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT s.term_id FROM %i s LEFT JOIN %i t ON t.term_id=s.term_id AND t.taxonomy=%s WHERE t.term_id IS NULL', $table, $wpdb->term_taxonomy, Taxonomy::NAME ) ) );
		}
		// phpcs:enable
		$removed['missing_skills'] = count( array_unique( array_map( 'intval', $missing ) ) );
		if ( $missing ) {
			self::forget_terms( array_unique( array_map( 'intval', $missing ) ) );
		}
		$removed['question_maps'] = self::clean_maps();
		return $removed;
	}

	/**
	 * Remove deleted skills from question and quiz connections and from learner levels.
	 *
	 * @param int[] $term_ids Deleted skills.
	 */
	private static function forget_terms( array $term_ids ) {
		global $wpdb;
		$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
		if ( ! $term_ids ) {
			return;
		}
		$holders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholders are generated for each ID.
		$affected = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT question_id FROM %i WHERE term_id IN ($holders)", array_merge( array( Schema::table( 'question_skills' ) ), $term_ids ) ) );
		foreach ( array_merge( array( 'question_skills', 'quiz_skills' ), self::LEARNER_TABLES ) as $table ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE term_id IN ($holders)", array_merge( array( Schema::table( $table ) ), $term_ids ) ) );
		}
		// phpcs:enable
		foreach ( $affected as $question_id ) {
			self::clean_map( (int) $question_id );
		}
	}

	/**
	 * Drop deleted skills from every question's saved skill map, so a later save cannot bring them back.
	 *
	 * @return int Questions whose map changed.
	 */
	private static function clean_maps() {
		global $wpdb;
		$changed = 0;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-time maintenance over one meta key.
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT m.post_id FROM %i m JOIN %i p ON p.ID=m.post_id WHERE m.meta_key=%s', $wpdb->postmeta, $wpdb->posts, SkillMap::META ) ) as $question_id ) {
			$changed += self::clean_map( (int) $question_id ) ? 1 : 0;
		}
		return $changed;
	}

	/**
	 * Drop deleted skills from one question's saved map.
	 *
	 * @param int $question_id Question.
	 * @return bool Whether the map changed.
	 */
	private static function clean_map( $question_id ) {
		if ( ! get_post( $question_id ) ) {
			return false;
		}
		$map   = SkillMap::current( $question_id );
		$clean = array();
		foreach ( $map as $part => $roles ) {
			$clean[ $part ] = array(
				'primary'    => $roles['primary'] && term_exists( $roles['primary'], Taxonomy::NAME ) ? $roles['primary'] : 0,
				'supporting' => array_values(
					array_filter(
						$roles['supporting'],
						static function ( $term_id ) {
							return (bool) term_exists( $term_id, Taxonomy::NAME );
						}
					)
				),
			);
		}
		$clean = SkillMap::normalize( $clean );
		if ( $clean === $map ) {
			return false;
		}
		SkillMap::save( $question_id, $clean );
		return true;
	}
}
