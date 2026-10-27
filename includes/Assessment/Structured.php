<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Structured (multi-part) questions: one shared stem/diagram with separately scored parts.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Structured (multi-part) questions: one shared stem/diagram with separately scored parts.
 *
 * Settings.parts = [[
 *   'id' => 'a', 'label' => '(a)', 'prompt' => '...', 'marks' => 2,
 *   'kind' => 'numerical' | 'text' | 'written' | 'question',
 *   'answer' / 'answers' / 'tolerance' / 'tolerance_type' / 'unit'   (numerical)
 *   'accepted' => ['x=3', ...]                       (text; case and spaces ignored)
 *   'rubric' => 'teacher-only marking notes'
 * ], ...]
 * Written parts are marked by a teacher. Part IDs are stable, so skills and evidence
 * can be attributed per part. The parent always stays together in a delivery.
 */
final class Structured {
	const KINDS = array( 'numerical', 'text', 'written', 'question' );

	/**
	 * Normalize stable part identities and marks.
	 *
	 * @param array $settings Private parent settings.
	 * @return array Normalized parts.
	 */
	public static function parts( array $settings ) {
		$parts = array();
		foreach ( (array) ( $settings['parts'] ?? array() ) as $index => $part ) {
			if ( ! is_array( $part ) ) {
				continue; }
			$id      = preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $part['id'] ?? '' ) );
			$id      = $id ? $id : 'p' . ( $index + 1 );
			$parts[] = array_merge(
				$part,
				array(
					'id'    => $id,
					'label' => (string) ( $part['label'] ?? '(' . chr( 97 + $index ) . ')' ),
					'kind'  => in_array( $part['kind'] ?? '', self::KINDS, true ) ? $part['kind'] : 'written',
					'marks' => max( 0, (float) ( $part['marks'] ?? 1 ) ),
				)
			);
		}
		return $parts;
	}

	/**
	 * Part weights for evidence.
	 *
	 * @param array $settings Private settings.
	 * @return array Part ID to share of marks.
	 */
	public static function weights( array $settings ) {
		$parts   = self::parts( $settings );
		$total   = array_sum( array_column( $parts, 'marks' ) );
		$weights = array();
		foreach ( $parts as $part ) {
			$weights[ $part['id'] ] = $total > 0 ? $part['marks'] / $total : 1 / max( 1, count( $parts ) ); }
		return $weights;
	}

	/**
	 * Build learner-safe parts.
	 *
	 * @param array  $settings Private settings.
	 * @param string $identity Parent question/version identity.
	 * @param string $scope Delivery token scope.
	 * @return array Public parts.
	 */
	public static function public_parts( array $settings, $identity = '', $scope = '' ) {
		return array_map(
			static function ( $part ) use ( $identity, $scope ) {
				$public           = array_intersect_key( $part, array_flip( array( 'id', 'label', 'prompt', 'kind', 'marks', 'unit' ) ) );
				$public['prompt'] = wp_kses_post( $public['prompt'] ?? '' );
				if ( 'question' === $part['kind'] ) {
					$public['question'] = StructuredPart::view( $part, StructuredPart::scope( $scope, $identity, $part ) );
					if ( 'fill-in-the-blank' === $public['question']['settings']['type'] && ! empty( $public['question']['inline_blanks'] ) ) {
						$public['prompt'] = '';
					}
				}
				return $public;
			},
			self::parts( $settings )
		);
	}

	/**
	 * Grade each part against its frozen settings.
	 *
	 * @param mixed  $answer Submitted parts.
	 * @param object $question Frozen parent question.
	 * @return array|\WP_Error Grade or invalid leaf response.
	 */
	public static function grade( $answer, $question ) {
		$settings = $question->get_settings();
		$parts    = self::parts( $settings );
		$answer   = is_array( $answer ) ? $answer : array();
		$total    = array_sum( array_column( $parts, 'marks' ) );
		$earned   = 0.0;
		$manual   = false;
		$results  = array();
		$all      = true;
		foreach ( $parts as $part ) {
			$input   = $answer[ $part['id'] ] ?? '';
			$present = is_scalar( $input ) && trim( (string) $input ) !== '';
			if ( 'written' === $part['kind'] ) {
				$results[ $part['id'] ] = $present ? null : 0.0;
				$manual                 = $manual || $present;
				$all                    = false;
				continue;
			}
			if ( 'question' === $part['kind'] ) {
				$grade = Grader::grade( StructuredPart::snapshot( $part ), is_array( $input ) ? $input : array() );
				if ( is_wp_error( $grade ) ) {
					return $grade;
				}
				if ( $grade['pending'] ) {
					$manual                 = true;
					$all                    = false;
					$results[ $part['id'] ] = null;
					continue;
				}
				$fraction = $grade['fraction'];
			} elseif ( 'numerical' === $part['kind'] ) {
				$fraction = NumericAnswer::grade( $input, $part )['fraction'];
			} else {
				$accepted = array_map(
					static function ( $value ) {
						return self::normalize_text( $value );
					},
					(array) ( $part['accepted'] ?? array() )
				);
				$fraction = $present && in_array( self::normalize_text( $input ), $accepted, true ) ? 1.0 : 0.0;
			}
			$results[ $part['id'] ] = $fraction;
			$earned                += $part['marks'] * $fraction;
			$all                    = $all && $fraction >= 1.0;
		}
		return array(
			'correct'  => ! $manual && $all,
			'fraction' => $total > 0 ? $earned / $total : 0.0,
			'manual'   => $manual,
			'parts'    => $results,
		);
	}

	/**
	 * Validate bounded leaf responses before grading.
	 *
	 * @param mixed  $answer Submitted parts.
	 * @param object $question Frozen parent.
	 * @return bool Whether the response has a valid shape.
	 */
	public static function validate_response( $answer, $question ) {
		if ( ! is_array( $answer ) || count( $answer ) > 50 ) {
			return false;
		}
		$parts = array_column( self::parts( $question->get_settings() ), null, 'id' );
		foreach ( $answer as $id => $input ) {
			if ( ! isset( $parts[ $id ] ) ) {
				return false;
			}
			$part = $parts[ $id ];
			if ( 'question' === $part['kind'] ) {
				$child      = StructuredPart::snapshot( $part );
				$definition = \OhMyLMS\Extensions\Registry::get( 'question', $child->get_type() );
				if ( ! in_array( $child->get_type(), StructuredPart::TYPES, true ) || ! is_array( $input ) || ! is_callable( $definition['validate'] ?? null ) || ! call_user_func( $definition['validate'], $input, $child ) ) {
					return false;
				}
			} elseif ( ! is_scalar( $input ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a part needs teacher review.
	 *
	 * @param array $settings Private settings.
	 * @return bool Whether any part is manual.
	 */
	public static function has_manual( array $settings ) {
		foreach ( self::parts( $settings ) as $part ) {
			if ( 'written' === $part['kind'] || ( 'question' === $part['kind'] && Grader::manual_type( StructuredPart::snapshot( $part )->get_type() ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Map child options without changing numerical or text answers.
	 *
	 * @param object $question Frozen parent.
	 * @param mixed  $answer Response.
	 * @param string $scope Delivery token scope.
	 * @param bool   $decode Whether to decode tokens.
	 * @return mixed Mapped response.
	 */
	public static function map_response( $question, $answer, $scope, $decode ) {
		if ( ! is_array( $answer ) ) {
			return $answer;
		}
		foreach ( self::parts( $question->get_settings() ) as $part ) {
			if ( 'question' === $part['kind'] && isset( $answer[ $part['id'] ] ) ) {
				$identity              = $question->get_id() . '|' . $question->get_version_id();
				$answer[ $part['id'] ] = StructuredPart::map_response( $part, $answer[ $part['id'] ], StructuredPart::scope( $scope, $identity, $part ), $decode );
			}
		}
		return $answer;
	}

	/**
	 * Normalize case and whitespace in exact text answers.
	 *
	 * @param mixed $value Answer.
	 * @return string Normalized answer.
	 */
	public static function normalize_text( $value ) {
		return preg_replace( '/\s+/u', '', mb_strtolower( trim( (string) $value ) ) );
	}

	/**
	 * Validate private part settings.
	 *
	 * @param array $settings Private settings.
	 * @return true|string Validation result.
	 */
	public static function validate_settings( array $settings ) {
		$parts = (array) ( $settings['parts'] ?? array() );
		if ( ! $parts || count( $parts ) > 50 ) {
			return __( 'A structured question needs at least one part.', 'ohmylms' ); }
		$ids = array();
		foreach ( $parts as $part ) {
			if ( ! is_array( $part ) ) {
				return __( 'Each part must be an object.', 'ohmylms' ); }
			$id = (string) ( $part['id'] ?? '' );
			if ( ! preg_match( '/^[a-z0-9_-]{1,40}$/i', $id ) || isset( $ids[ $id ] ) ) {
				return __( 'Each part needs a unique ID (letters, numbers, - or _).', 'ohmylms' ); }
			$ids[ $id ] = true;
			if ( ! in_array( $part['kind'] ?? '', self::KINDS, true ) ) {
				return __( 'Choose an answer type for each part.', 'ohmylms' ); }
			if ( isset( $part['marks'] ) && ( ! is_numeric( $part['marks'] ) || (float) $part['marks'] < 0 ) ) {
				return __( 'Part marks must be zero or more.', 'ohmylms' ); }
			if ( 'question' === $part['kind'] ) {
				$valid = StructuredPart::validate( $part );
				if ( true !== $valid ) {
					return $valid;
				}
			}
			if ( 'numerical' === $part['kind'] ) {
				$expected = $part['answers'] ?? array( $part['answer'] ?? null );
				foreach ( (array) $expected as $value ) {
					if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) ) {
						return __( 'Numerical parts need a finite expected answer.', 'ohmylms' ); }
				}
				if ( isset( $part['tolerance'] ) && ( ! is_numeric( $part['tolerance'] ) || (float) $part['tolerance'] < 0 ) ) {
					return __( 'Tolerance must be zero or more.', 'ohmylms' ); }
			}
			if ( 'text' === $part['kind'] && ! array_filter( (array) ( $part['accepted'] ?? array() ), 'strlen' ) ) {
				return __( 'Text parts need at least one accepted answer.', 'ohmylms' ); }
		}
		return true;
	}
}
