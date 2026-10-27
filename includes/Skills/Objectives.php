<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Complete learning objectives, independent of legacy short skill titles.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/** Preserves existing skill identity and legacy definitions. */
final class Objectives {
	const META_KEY = '_ohmylms_skill_objective';

	/**
	 * Read the full objective, falling back to a legacy definition or title.
	 *
	 * @param \WP_Term $term Skill term.
	 * @return string
	 */
	public static function get( $term ) {
		$text = get_term_meta( $term->term_id, self::META_KEY, true );
		if ( ! is_string( $text ) || '' === $text ) {
			$text = '' !== $term->description ? $term->description : $term->name;
		}
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Validate the same bounded plain-text contract as existing skill notes.
	 *
	 * @param mixed $text Proposed objective.
	 * @return string|\WP_Error
	 */
	public static function normalize( $text ) {
		if ( ! is_string( $text ) || mb_strlen( $text ) > 2000 || '' === trim( sanitize_textarea_field( $text ) ) ) {
			return new \WP_Error( 'ohmylms_objective_invalid', __( 'Enter a learning objective of up to 2000 characters.', 'ohmylms' ), array( 'status' => 400 ) );
		}
		return sanitize_textarea_field( $text );
	}

	/**
	 * Store a validated objective without overwriting the title or legacy notes.
	 *
	 * @param int    $term_id Shared skill ID.
	 * @param string $text Validated objective.
	 * @return bool|\WP_Error
	 */
	public static function save( $term_id, $text ) {
		if ( get_term_meta( $term_id, self::META_KEY, true ) === $text ) {
			return true;
		}
		$result = update_term_meta( $term_id, self::META_KEY, wp_slash( $text ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return false === $result ? new \WP_Error( 'ohmylms_objective_save', __( 'Could not save the learning objective.', 'ohmylms' ), array( 'status' => 500 ) ) : true;
	}
}
