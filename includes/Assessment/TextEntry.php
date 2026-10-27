<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the plugin PSR-4 class mapping; see docs/WORDWALL-ACTIVITIES.md.
/**
 * Explicit automatically marked text answers.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/** Keeps accepted answers private without changing manual short-answer grading. */
final class TextEntry {

	/**
	 * Apply only the author's explicit comparison rules.
	 *
	 * @param string $text Response or accepted answer.
	 * @param array  $settings Comparison settings.
	 * @return string Normalized text.
	 */
	public static function normalize( $text, array $settings ) {
		$text = trim( $text );
		if ( ! empty( $settings['ignore_punctuation'] ) ) {
			$text = preg_replace( '/\p{P}+/u', '', $text );
		}
		if ( ! empty( $settings['collapse_whitespace'] ) ) {
			$text = preg_replace( '/\s+/u', ' ', $text );
		}
		return empty( $settings['case_sensitive'] ) ? mb_strtolower( $text, 'UTF-8' ) : $text;
	}

	/**
	 * Validate a bounded list of explicit answers.
	 *
	 * @param array $settings Private definition.
	 * @return bool|string Validity or author-facing error.
	 */
	public static function validate_settings( array $settings ) {
		$answers = $settings['accepted'] ?? null;
		if ( ! is_array( $answers ) || ! $answers || count( $answers ) > 50 ) {
			return __( 'Enter between one and fifty accepted answers.', 'ohmylms' );
		}
		foreach ( $answers as $answer ) {
			if ( ! is_string( $answer ) || mb_strlen( $answer ) > 2000 || '' === self::normalize( $answer, $settings ) ) {
				return __( 'Accepted answers must contain text and be at most 2000 characters.', 'ohmylms' );
			}
		}
		foreach ( array( 'case_sensitive', 'collapse_whitespace', 'ignore_punctuation' ) as $key ) {
			if ( isset( $settings[ $key ] ) && ! is_bool( $settings[ $key ] ) ) {
				return __( 'Text comparison settings must be enabled or disabled.', 'ohmylms' );
			}
		}
		return true;
	}

	/**
	 * Compare one response; extra values cannot be used to guess every answer.
	 *
	 * @param array $answer Submitted text list.
	 * @param array $settings Private frozen definition.
	 * @return array Grade.
	 */
	public static function grade( array $answer, array $settings ) {
		$correct = false;
		if ( 1 === count( $answer ) && is_scalar( reset( $answer ) ) && mb_strlen( (string) reset( $answer ) ) <= 2000 ) {
			$given = self::normalize( (string) reset( $answer ), $settings );
			foreach ( (array) ( $settings['accepted'] ?? array() ) as $expected ) {
				$correct = $correct || ( '' !== $given && self::normalize( $expected, $settings ) === $given );
			}
		}
		return array(
			'correct'  => $correct,
			'fraction' => $correct ? 1 : 0,
			'manual'   => false,
		);
	}
}
