<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Skills a quiz as a whole is connected to.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Quiz-to-skill connections, stored in the `quiz_skills` table.
 */
final class QuizSkills {

	/**
	 * Skill IDs connected to a quiz, in the order they were chosen.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return int[]
	 */
	public static function get( $quiz_id ) {
		global $wpdb;
		$table = Schema::table( 'quiz_skills' );
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT term_id FROM %i WHERE quiz_id=%d ORDER BY position, term_id', $table, (int) $quiz_id ) ) );
	}

	/**
	 * Replace the skills connected to a quiz.
	 *
	 * @param int   $quiz_id  Quiz ID.
	 * @param array $skill_ids Skill (term) IDs.
	 * @return int[]|\WP_Error The saved IDs.
	 */
	public static function set( $quiz_id, array $skill_ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $skill_ids ) ) ) );
		foreach ( $ids as $term_id ) {
			if ( ! term_exists( $term_id, Taxonomy::NAME ) ) {
				return new \WP_Error( 'ohmylms_skill_missing', __( 'A chosen skill does not exist.', 'ohmylms' ), array( 'status' => 400 ) );
			}
		}
		$table = Schema::table( 'quiz_skills' );
		$wpdb->delete( $table, array( 'quiz_id' => (int) $quiz_id ), array( '%d' ) );
		foreach ( $ids as $position => $term_id ) {
			$wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (quiz_id, term_id, position) VALUES (%d, %d, %d)', $table, (int) $quiz_id, $term_id, $position ) );
		}
		return $ids;
	}
}
