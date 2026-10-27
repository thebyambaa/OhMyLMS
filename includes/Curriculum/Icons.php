<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The editor and API share one supported icon catalogue. Empty means use the default for the node kind.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

defined( 'ABSPATH' ) || exit;

/** The editor and API share one supported icon catalogue. Empty means use the default for the node kind. */
final class Icons {
	/**
	 * Clean for Icons.
	 *
	 * @param mixed $value Value to process.
	 */
	public static function clean( $value ) {
		$options = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/assets/src/features/curriculum/icons.json' ), true );
		if ( ! is_string( $value ) || ( '' !== $value && ! in_array( $value, array_column( $options ? $options : array(), 'id' ), true ) ) ) {
			return Access::error( 'ohmylms_curriculum_icon_invalid', __( 'Choose an icon from the icon picker.', 'ohmylms' ) );
		}
		return $value;
	}
}
