<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Editable state of one shared skill and the revision-checked write of the skill editor.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

use OhMyLMS\Utility\Lock;

defined( 'ABSPATH' ) || exit;

/**
 * The revision is a hash of the editable state. `update()` takes the skill's advisory lock, re-reads the
 * skill, compares the revision and only then writes through the term and skill services. A busy lock
 * answers `ohmylms_skill_busy` (409) and a different revision `ohmylms_skill_stale` (409); nothing is written.
 */
final class SkillEditor {

	/**
	 * Load an existing skill term.
	 *
	 * @param int $id Skill ID.
	 * @return \WP_Term|\WP_Error
	 */
	public static function term( $id ) {
		$term = get_term( $id, Taxonomy::NAME );
		return $term instanceof \WP_Term ? $term : new \WP_Error( 'ohmylms_skill_missing', __( 'Skill not found.', 'ohmylms' ), array( 'status' => 404 ) );
	}

	/**
	 * The stored editable state; the revision is its hash.
	 *
	 * @param \WP_Term $term Skill.
	 * @return array
	 */
	public static function state( \WP_Term $term ) {
		$id        = (int) $term->term_id;
		$objective = get_term_meta( $id, Objectives::META_KEY, true );
		return array(
			'id'              => $id,
			'name'            => Taxonomy::plain( $term->name ),
			'code'            => (string) get_term_meta( $id, '_ohmylms_skill_code', true ),
			'description'     => Taxonomy::plain( $term->description ),
			'parent'          => (int) $term->parent,
			'prerequisites'   => Taxonomy::prerequisites( $id ),
			'objective'       => is_string( $objective ) ? Taxonomy::plain( $objective ) : '',
			'translations'    => (object) Translations::get( $id ),
			'public_practice' => self::public_practice( $id ),
			'archived'        => Provenance::archived( $id ),
		);
	}

	/**
	 * Revision of the editable state.
	 *
	 * @param \WP_Term $term Skill.
	 * @return string
	 */
	public static function revision( \WP_Term $term ) {
		return hash( 'sha256', (string) wp_json_encode( self::state( $term ) ) );
	}

	/**
	 * Whether the skill's practice pool is public.
	 *
	 * @param int $id Skill ID.
	 * @return bool
	 */
	public static function public_practice( $id ) {
		return (bool) get_term_meta( $id, '_ohmylms_public_practice', true );
	}

	/**
	 * Write validated changes under the skill's advisory lock if the revision still matches. `$read`
	 * runs under the same lock and receives the saved skill.
	 *
	 * @param int      $id Skill ID.
	 * @param array    $changes Validated changes.
	 * @param string   $expected_revision Revision the editor loaded.
	 * @param callable $read Builds the answer from the saved term.
	 * @return mixed|\WP_Error What `$read` returns.
	 */
	public static function update( $id, array $changes, $expected_revision, callable $read ) {
		global $wpdb;
		return Lock::with(
			'ohmylms-skill-' . $id . '-' . md5( $wpdb->prefix ),
			5,
			static function () use ( $id, $changes, $expected_revision, $read ) {
				clean_term_cache( $id, Taxonomy::NAME );
				$term = self::term( $id );
				if ( is_wp_error( $term ) ) {
					return $term;
				}
				if ( ! hash_equals( self::revision( $term ), $expected_revision ) ) {
					return new \WP_Error( 'ohmylms_skill_stale', __( 'This skill changed since you opened it. Reload it and apply your edits again.', 'ohmylms' ), array( 'status' => 409 ) );
				}
				$written = self::write( $id, $changes );
				if ( is_wp_error( $written ) ) {
					return $written;
				}
				clean_term_cache( $id, Taxonomy::NAME );
				return $read( get_term( $id, Taxonomy::NAME ) );
			},
			static function () {
				return new \WP_Error( 'ohmylms_skill_busy', __( 'Another author is saving this skill. Try again.', 'ohmylms' ), array( 'status' => 409 ) );
			}
		);
	}

	/**
	 * Apply validated changes through the existing term and skill services.
	 *
	 * @param int   $id Skill ID.
	 * @param array $changes Validated changes.
	 * @return true|\WP_Error
	 */
	private static function write( $id, array $changes ) {
		$args = array_intersect_key( $changes, array_flip( array( 'name', 'description', 'parent' ) ) );
		if ( $args ) {
			$updated = wp_update_term( $id, Taxonomy::NAME, $args );
			if ( is_wp_error( $updated ) ) {
				$updated->add_data( array( 'status' => 400 ) );
				return $updated;
			}
		}
		if ( isset( $changes['code'] ) ) {
			update_term_meta( $id, '_ohmylms_skill_code', $changes['code'] );
		}
		if ( isset( $changes['prerequisites'] ) ) {
			update_term_meta( $id, '_ohmylms_prerequisites', $changes['prerequisites'] );
		}
		if ( isset( $changes['objective'] ) ) {
			if ( '' === $changes['objective'] ) {
				delete_term_meta( $id, Objectives::META_KEY );
			} else {
				$saved = Objectives::save( $id, $changes['objective'] );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
		}
		if ( isset( $changes['translations'] ) ) {
			$saved = Translations::save( $id, $changes['translations'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		if ( isset( $changes['public_practice'] ) ) {
			update_term_meta( $id, '_ohmylms_public_practice', $changes['public_practice'] );
		}
		return true;
	}
}
