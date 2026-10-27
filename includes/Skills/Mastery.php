<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Evidence thresholds and review timing; the skill state itself is decided by Performance\Engine.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Skills;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Performance\Engine;
use OhMyLMS\Performance\Levels;
use OhMyLMS\Performance\Policies\EvidencePolicy;

defined( 'ABSPATH' ) || exit;

/**
 * Evidence thresholds (option "ohmylms_mastery_rules") and the review-due refresh. The level a
 * learner holds on a skill is decided by the site's policy in Performance\Engine, and the five
 * level names live in Performance\Levels.
 */
final class Mastery {
	const OPTION = 'ohmylms_mastery_rules';
	const LEVELS = Levels::SCALE;

	/**
	 * The evidence thresholds with the administrator's changes.
	 *
	 * @return array
	 */
	public static function rules() {
		$saved = get_option( self::OPTION );
		return array_merge( EvidencePolicy::DEFAULTS, is_array( $saved ) ? array_intersect_key( $saved, EvidencePolicy::DEFAULTS ) : array() );
	}

	/**
	 * Recompute and store the state for one learner and skill.
	 *
	 * @param int $student_id Learner.
	 * @param int $term_id    Skill.
	 * @return array The stored state.
	 */
	public static function recompute( $student_id, $term_id ) {
		return Engine::recompute( (int) $student_id, (int) $term_id );
	}

	/**
	 * Level and review timing the evidence rules alone give some rows. Pure, for tests and previews.
	 *
	 * @param array $rows  Evidence rows, oldest first.
	 * @param array $rules Thresholds.
	 * @param int   $now   Timestamp.
	 * @return array
	 */
	public static function evaluate( array $rows, array $rules, $now ) {
		return EvidencePolicy::evaluate( $rows, $rules, $now );
	}

	/**
	 * Refresh stale review flags (time passing alone can make a review due).
	 *
	 * @param int $limit Rows to update at most.
	 * @return int Rows updated.
	 */
	public static function refresh_reviews( $limit = 500 ) {
		global $wpdb;
		$table = Schema::table( 'student_skill_state' );
		return (int) $wpdb->query( $wpdb->prepare( 'UPDATE %i SET review_due=1 WHERE review_due=0 AND review_due_at IS NOT NULL AND review_due_at<=%s LIMIT %d', $table, gmdate( 'Y-m-d H:i:s' ), $limit ) );
	}

	/**
	 * Learner-facing name for a level.
	 *
	 * @param string $level Level.
	 * @return string
	 */
	public static function label( $level ) {
		return Levels::label( $level );
	}
}
