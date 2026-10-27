<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The one way to create shared skills, and to retire skills without losing anything.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Curriculum\AiAnalysis;
use OhMyLMS\Curriculum\AiAnalysisModel;
use OhMyLMS\Curriculum\Comparison;

defined( 'ABSPATH' ) || exit;

/**
 * Creating a shared skill first looks for one of the same subject whose name means the same
 * ("Solve linear equations" and "solving linear equation"), and reuses it, so sources that propose
 * skills cannot fill the library with near copies. Every skill records its origin (see Provenance).
 *
 * A skill used by a course outline, learner evidence or question mappings is archived instead of
 * deleted: it stays readable everywhere that uses it, and is no longer offered for new use.
 */
final class SkillWriter {

	/**
	 * An existing shared skill of a subject with the same meaning of name, or 0.
	 *
	 * @param string $name    Skill name.
	 * @param string $subject Subject.
	 * @return int Term ID.
	 */
	public static function find_shared( $name, $subject ) {
		$key = AiAnalysisModel::name_key( (string) $name );
		if ( '' === $key ) {
			return 0;
		}
		foreach ( AiAnalysis::catalog( (string) $subject ) as $id => $skill ) {
			if ( AiAnalysisModel::name_key( $skill['name'] ) === $key ) {
				return (int) $id;
			}
		}
		return 0;
	}

	/**
	 * Create a shared skill, or reuse an existing one with the same meaning.
	 *
	 * @param array $fields name, description, topic, subject, domain, grade_from, grade_to.
	 * @param array $origin Provenance: source, ref, review, provider, model, prompt, confidence.
	 * @return array|\WP_Error id, reused, skill (the shared skill).
	 */
	public static function shared( array $fields, array $origin ) {
		$existing = self::find_shared( (string) ( $fields['name'] ?? '' ), (string) ( $fields['subject'] ?? '' ) );
		if ( $existing ) {
			return array(
				'id'     => $existing,
				'reused' => true,
				'skill'  => Comparison::shared_skill( $existing ),
			);
		}
		$saved = Provenance::with(
			$origin,
			static function () use ( $fields ) {
				return Comparison::save_shared(
					0,
					array_intersect_key( $fields, array_flip( array( 'name', 'description', 'topic', 'subject', 'domain', 'grade_from', 'grade_to', 'roadmap_id' ) ) )
				);
			}
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		/**
		 * Fires after a shared skill is created through the skill writer.
		 *
		 * @param int   $id     Skill term ID.
		 * @param array $origin Provenance it was created with.
		 */
		do_action( 'ohmylms_skill_created', (int) $saved['id'], $origin );
		return array(
			'id'     => (int) $saved['id'],
			'reused' => false,
			'skill'  => $saved,
		);
	}

	/**
	 * Archive a skill: keep it for every course, question and learner record that uses it.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array|\WP_Error id, archived, usage.
	 */
	public static function archive( $term_id ) {
		$term_id = (int) $term_id;
		if ( ! term_exists( $term_id, Taxonomy::NAME ) ) {
			return new \WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		update_term_meta( $term_id, Provenance::STATUS_META, 'archived' );
		/**
		 * Fires after a skill is archived instead of deleted.
		 *
		 * @param int $term_id Skill term ID.
		 */
		do_action( 'ohmylms_skill_archived', $term_id );
		return array(
			'id'       => $term_id,
			'archived' => true,
			'usage'    => SkillUsage::of( $term_id ),
		);
	}

	/**
	 * Return an archived skill to normal use.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array|\WP_Error id, archived.
	 */
	public static function restore( $term_id ) {
		$term_id = (int) $term_id;
		if ( ! term_exists( $term_id, Taxonomy::NAME ) ) {
			return new \WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		delete_term_meta( $term_id, Provenance::STATUS_META );
		do_action( 'ohmylms_skill_restored', $term_id );
		return array(
			'id'       => $term_id,
			'archived' => false,
		);
	}
}
