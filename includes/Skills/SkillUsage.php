<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * What still depends on a skill.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/** Counts the course programs, learner evidence and question mappings that use a skill. */
final class SkillUsage {

	/**
	 * Everything that uses a skill.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array published (program versions), drafts (course IDs whose draft outline uses it), evidence, questions.
	 */
	public static function of( $term_id ) {
		global $wpdb;
		$term_id = (int) $term_id;
		$result  = array(
			'published' => 0,
			'drafts'    => array(),
			'evidence'  => 0,
			'questions' => 0,
		);
		if ( \OhMyLMS\Learning\Schema::ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live usage check before archiving or deleting.
			$result['published'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE term_id=%d', \OhMyLMS\Learning\Schema::table( 'outcomes' ), $term_id ) );
		}
		// Draft outlines are serialized arrays in post meta; an outcome stores its skill as "term_id";i:<id>;.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Exact meta key with a bounded pattern; live usage check.
		$result['drafts'] = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT post_id FROM %i WHERE meta_key=%s AND meta_value LIKE %s', $wpdb->postmeta, \OhMyLMS\Learning\CourseProgram::DRAFT, '%' . $wpdb->esc_like( '"term_id";i:' . $term_id . ';' ) . '%' ) ) );
		if ( \OhMyLMS\Assessment\Schema::ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live usage check before archiving or deleting.
			$result['evidence'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE term_id=%d', \OhMyLMS\Assessment\Schema::table( 'skill_evidence' ), $term_id ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Live usage check before archiving or deleting.
			$result['questions'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE term_id=%d', \OhMyLMS\Assessment\Schema::table( 'qb_version_skills' ), $term_id ) );
		}
		return $result;
	}

	/**
	 * Whether a course outline (draft or published) uses the skill. Such a skill is never deleted.
	 *
	 * @param array $usage Result of of().
	 * @return bool
	 */
	public static function in_courses( array $usage ) {
		return $usage['published'] > 0 || ! empty( $usage['drafts'] );
	}

	/**
	 * Whether anything uses the skill.
	 *
	 * @param array $usage Result of of().
	 * @return bool
	 */
	public static function used( array $usage ) {
		return self::in_courses( $usage ) || $usage['evidence'] > 0 || $usage['questions'] > 0;
	}
}
