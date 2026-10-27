<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * A rule for turning a learner's evidence on one skill into a level.
 *
 * @package OhMyLMS\Performance
 */

namespace OhMyLMS\Performance;

defined( 'ABSPATH' ) || exit;

/**
 * Policies are pure: the same evidence rows and context always give the same level, so switching
 * policy is a rebuild and never a data migration. Register more with the
 * `ohmylms_performance_policies` filter (see Policies::all()).
 */
interface Policy {

	/**
	 * Stable identifier stored with the state, e.g. "evidence".
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Name for the settings screen.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * One sentence saying how the policy decides, for the settings screen.
	 *
	 * @return string
	 */
	public function description();

	/**
	 * The level the evidence earns.
	 *
	 * @param array[] $rows    Primary-skill, non-superseded evidence, oldest first. Each has awarded,
	 *                         available, independent, first_try, family_id, question_id, difficulty,
	 *                         source_type, source_id and evidence_at (UTC).
	 * @param array   $context rules (the evidence thresholds), bands (the score bands), score and
	 *                         scored (the mastery score of these rows), and now (a timestamp).
	 * @return string One of Levels::SCALE.
	 */
	public function level( array $rows, array $context );
}
