<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Contract for anything that proposes skills.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/**
 * A skill source proposes skills; it never creates them. Proposals become suggestions in the review
 * queue, and only accepting one creates (or reuses) a skill, with this source recorded as its origin.
 * Register a source with the `ohmylms_skill_sources` filter.
 */
interface SkillSource {

	/**
	 * Stable source id: lower-case letters, digits and hyphens. Recorded on every skill it produces.
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Name shown to authors.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Whether the source can run on this site now (for example, an AI connection is configured).
	 *
	 * @return bool
	 */
	public function available();

	/**
	 * Propose skills for a request.
	 *
	 * @param array $request course_id, unit_id, group_id (lesson UUID), subject, and source-specific fields.
	 * @return array|\WP_Error proposals (each: name, description, topic, domain, grade_from, grade_to, confidence,
	 *                         reason, existing (skill term ID to reuse, or 0)), and meta (provider, model, prompt).
	 */
	public function propose( array $request );
}
