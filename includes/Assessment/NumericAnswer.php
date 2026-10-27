<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Parsing and tolerance comparison for numerical answers.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Parsing and tolerance comparison for numerical answers.
 *
 * Accepted input: integers and decimals ("3", "-2.50", ".5"), a decimal comma when no
 * point is present ("2,5"), scientific notation ("1.2e3"), simple fractions ("3/4",
 * "-1/2") and mixed numbers ("1 1/2") when allowed. Thousands separators, units and
 * expressions are rejected rather than guessed. Only finite values are ever accepted.
 */
final class NumericAnswer {
	/**
	 * Parse for Numeric answer.
	 *
	 * @return float|null null for blank or invalid input
	 * @param mixed $input Input.
	 * @param bool  $allow_fractions Allow fractions.
	 */
	public static function parse( $input, $allow_fractions = true ) {
		if ( is_array( $input ) ) {
			$input = reset( $input ); }
		if ( ! is_scalar( $input ) ) {
			return null; }
		$text = trim( str_replace( array( "\u{2212}", "\u{00A0}" ), array( '-', ' ' ), (string) $input ) );
		if ( '' === $text || strlen( $text ) > 64 ) {
			return null; }
		if ( strpos( $text, ',' ) !== false && strpos( $text, '.' ) === false && substr_count( $text, ',' ) === 1 ) {
			$text = str_replace( ',', '.', $text ); }
		if ( preg_match( '/^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d{1,3})?$/', $text ) ) {
			$value = (float) $text;
			return is_finite( $value ) ? $value : null;
		}
		if ( $allow_fractions && preg_match( '/^([+-]?)(?:(\d+)\s+)?(\d+)\s*\/\s*(\d+)$/', $text, $match ) ) {
			$denominator = (float) $match[4];
			if ( 0.0 === $denominator ) {
				return null; }
			$value = (float) ( '' !== $match[2] ? $match[2] : 0 ) + (float) $match[3] / $denominator;
			$value = '-' === $match[1] ? -$value : $value;
			return is_finite( $value ) ? $value : null;
		}
		return null;
	}

	/**
	 * Is $value within tolerance of $expected?
	 *
	 * @param mixed  $value Value to process.
	 * @param mixed  $expected Expected.
	 * @param mixed  $tolerance Tolerance.
	 * @param string $type 'absolute' (|v - e| <= t) or 'relative' (|v - e| <= t * |e|).
	 */
	public static function matches( $value, $expected, $tolerance = 0.0, $type = 'absolute' ) {
		if ( null === $value || ! is_numeric( $expected ) || ! is_finite( (float) $expected ) ) {
			return false; }
		$tolerance  = max( 0.0, (float) $tolerance );
		$difference = abs( (float) $value - (float) $expected );
		$limit      = 'relative' === $type ? $tolerance * abs( (float) $expected ) : $tolerance;
		// Absorb binary floating-point noise (e.g. 0.1 + 0.2) without widening real tolerance.
		$epsilon = 1e-9 * max( 1.0, abs( (float) $expected ) );
		return $difference <= $limit + $epsilon;
	}

	/** Grade against settings: answer (or answers[]), tolerance, tolerance_type, allow_fractions.
	 *
	 * @param mixed $input Input.
	 * @param array $settings Configuration settings.
	 */
	public static function grade( $input, array $settings ) {
		$value    = self::parse( $input, ! isset( $settings['allow_fractions'] ) || ! empty( $settings['allow_fractions'] ) );
		$accepted = isset( $settings['answers'] ) && is_array( $settings['answers'] ) ? $settings['answers'] : array( $settings['answer'] ?? null );
		foreach ( $accepted as $expected ) {
			if ( self::matches( $value, $expected, $settings['tolerance'] ?? 0, ( $settings['tolerance_type'] ?? 'absolute' ) === 'relative' ? 'relative' : 'absolute' ) ) {
				return array(
					'correct'  => true,
					'fraction' => 1.0,
					'manual'   => false,
					'valid'    => true,
				);
			}
		}
		return array(
			'correct'  => false,
			'fraction' => 0.0,
			'manual'   => false,
			'valid'    => null !== $value,
		);
	}
}
