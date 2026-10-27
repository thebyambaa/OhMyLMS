<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Layout settings of an exam's printable paper, stored with the quiz settings.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

defined( 'ABSPATH' ) || exit;

/** Validate the `paper` key of `_quiz_settings`. Mirrors paperSettings() in paperModel.mjs. */
final class PaperSettings {
	const DEFAULTS = array(
		'size'           => 'a4',
		'margin'         => 20,
		'font_size'      => 11,
		'font'           => 'sans',
		'numbering'      => 'number',
		'show_marks'     => true,
		'lines_per_mark' => 2,
		'page_numbers'   => true,
		'default_view'   => false,
		'versions'       => 1,
	);

	/**
	 * Keep only known paper fields, each within its allowed values.
	 *
	 * @param mixed $value Submitted paper settings.
	 * @return array Complete paper settings.
	 */
	public static function sanitize( $value ) {
		$value  = is_array( $value ) || is_object( $value ) ? (array) $value : array();
		$choice = static function ( $key, array $allowed ) use ( $value ) {
			$item = isset( $value[ $key ] ) ? (string) $value[ $key ] : '';
			return in_array( $item, $allowed, true ) ? $item : self::DEFAULTS[ $key ];
		};
		$number = static function ( $key, $min, $max ) use ( $value ) {
			if ( ! isset( $value[ $key ] ) || ! is_numeric( $value[ $key ] ) ) {
				return self::DEFAULTS[ $key ];
			}
			return (int) min( $max, max( $min, round( (float) $value[ $key ] ) ) );
		};
		$flag   = static function ( $key ) use ( $value ) {
			if ( ! array_key_exists( $key, $value ) ) {
				return self::DEFAULTS[ $key ];
			}
			return ! in_array( $value[ $key ], array( false, 0, '0', '', null ), true );
		};
		return array(
			'size'           => $choice( 'size', array( 'a4', 'letter' ) ),
			'margin'         => $number( 'margin', 10, 30 ),
			'font_size'      => $number( 'font_size', 9, 14 ),
			'font'           => $choice( 'font', array( 'sans', 'serif' ) ),
			'numbering'      => $choice( 'numbering', array( 'number', 'q' ) ),
			'show_marks'     => $flag( 'show_marks' ),
			'lines_per_mark' => $number( 'lines_per_mark', 1, 4 ),
			'page_numbers'   => $flag( 'page_numbers' ),
			'default_view'   => $flag( 'default_view' ),
			'versions'       => $number( 'versions', 1, 4 ),
		);
	}
}
