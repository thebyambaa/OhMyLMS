<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Display numbers for the units (chapters) of a course.
 *
 * A unit shows as "UNIT 3" or "UNIT 2A" on the hub. An author may store a short label on the
 * chapter; without one the unit is numbered by its position in the course, as before. The label
 * only changes what is printed: anchors and ordering stay positional.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Curriculum;

defined( 'ABSPATH' ) || exit;

/** Rules and storage for the optional unit label kept on a chapter post. */
final class UnitNumber {

	const META       = '_ohmylms_unit_number';
	const MAX_LENGTH = 10;

	/**
	 * Clean a submitted label.
	 *
	 * @param mixed $raw Submitted value.
	 * @return string|null The label ('' when cleared), or null when it is not allowed.
	 */
	public static function normalize( $raw ) {
		if ( null === $raw ) {
			return '';
		}
		if ( ! is_scalar( $raw ) ) {
			return null;
		}
		$label = trim( (string) $raw );
		if ( '' === $label ) {
			return '';
		}
		return preg_match( '/^[A-Za-z0-9.\-]{1,' . self::MAX_LENGTH . '}$/', $label ) ? $label : null;
	}

	/**
	 * The label printed for a unit.
	 *
	 * @param string $stored   Stored label, or '' for automatic.
	 * @param int    $position Zero-based position of the unit in the course.
	 * @return string
	 */
	public static function label( $stored, $position ) {
		$stored = (string) $stored;
		return '' !== $stored ? $stored : (string) ( (int) $position + 1 );
	}

	/**
	 * The stored label of a chapter, or '' when it is numbered automatically.
	 *
	 * @param int $chapter_id Chapter post ID.
	 * @return string
	 */
	public static function get( $chapter_id ) {
		$unit = \OhMyLMS\Learning\UnitSnapshots::public_unit( $chapter_id );
		if ( $unit ) {
			return $unit['number'];
		}
		$stored = self::normalize( get_post_meta( (int) $chapter_id, self::META, true ) );
		return null === $stored ? '' : $stored;
	}

	/**
	 * Store a chapter's label; an empty label returns it to automatic numbering.
	 *
	 * @param int   $chapter_id Chapter post ID.
	 * @param mixed $raw        Submitted label.
	 * @return string|null The stored label, or null when the label is not allowed.
	 */
	public static function set( $chapter_id, $raw ) {
		$label = self::normalize( $raw );
		if ( null === $label ) {
			return null;
		}
		if ( '' === $label ) {
			delete_post_meta( (int) $chapter_id, self::META );
		} else {
			update_post_meta( (int) $chapter_id, self::META, $label );
		}
		return $label;
	}
}
