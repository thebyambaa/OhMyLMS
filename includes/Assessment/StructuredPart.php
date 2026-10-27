<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer class/file mapping.
/**
 * Reuse leaf question contracts inside a structured part.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\Extensions\Authoring;
use OhMyLMS\Extensions\QuestionTypes;
use OhMyLMS\Extensions\Registry;

defined( 'ABSPATH' ) || exit;

/** Nested questions are frozen inline with their parent, never read from a live draft. */
final class StructuredPart {

	const TYPES = array( 'single-choice', 'multiple-choice', 'true-false', 'fill-in-the-blank', 'matching', 'reorder', 'dropdown-blanks', 'categorize', 'multi-blank', 'build-expression', 'expression', 'number-line', 'shade-model', 'count-blocks', 'set-clock', 'make-amount', 'fill-level', 'build-chart', 'grid-build', 'graphing', 'hot-text', 'match-table-grid', 'labeling', 'hotspot', 'draw', 'audio-response', 'video-response' );

	/**
	 * Build the same immutable object used for standalone questions.
	 *
	 * @param array $part Private part.
	 * @return QuestionSnapshot Child snapshot.
	 */
	public static function snapshot( array $part ) {
		$child    = (array) ( $part['question'] ?? array() );
		$settings = (array) ( $child['settings'] ?? array() );
		return new QuestionSnapshot(
			array(
				'id'            => 0,
				'version_no'    => 1,
				'question_id'   => 0,
				'question_uuid' => $part['id'],
				'type'          => $settings['type'] ?? '',
				'title'         => $part['prompt'] ?? '',
				'body'          => '',
				'settings'      => $settings,
				'options'       => (array) ( $child['questions'] ?? array() ),
				'parts'         => array(),
				'extension'     => array(),
				'media'         => array(),
			)
		);
	}

	/**
	 * Validate the selected leaf type and its private answer key.
	 *
	 * @param array $part Private part.
	 * @return true|string Validation result.
	 */
	public static function validate( array $part ) {
		if ( ! is_array( $part['question'] ?? null ) || ! is_array( $part['question']['settings'] ?? null ) || ! is_string( $part['question']['settings']['type'] ?? null ) || ! is_array( $part['question']['questions'] ?? array() ) || array_filter(
			$part['question']['questions'] ?? array(),
			static function ( $option ) {
				return ! is_array( $option ); }
		) ) {
			return __( 'The part question must have valid settings and options.', 'ohmylms' );
		}
		$type = $part['question']['settings']['type'];
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return __( 'Choose a supported question type for this part.', 'ohmylms' );
		}
		$child  = self::snapshot( $part );
		$result = Authoring::check( Registry::get( 'question', $type ), $child->get_settings() );
		if ( true !== $result ) {
			return $result;
		}
		$options = $child->get_questions();
		if ( in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false', 'matching', 'reorder', 'fill-in-the-blank' ), true ) ) {
			$minimum = 'fill-in-the-blank' === $type ? 1 : 2;
			if ( 'fill-in-the-blank' === $type && InlineBlanks::parse( $child->get_name() )['answers'] ) {
				return true;
			}
			$ids = array();
			foreach ( $options as $option ) {
				if ( ! is_array( $option ) || ! is_scalar( $option['id'] ?? null ) || ! ctype_digit( (string) $option['id'] ) || (int) $option['id'] <= 0 || isset( $ids[ (int) $option['id'] ] ) || ! is_string( $option['answer'] ?? null ) || '' === trim( $option['answer'] ) ) {
					return __( 'Each part option needs a unique numeric ID and answer text.', 'ohmylms' );
				}
				$ids[ (int) $option['id'] ] = true;
				if ( 'matching' === $type && ( ! is_string( $option['matching_data']['label'] ?? null ) || '' === trim( $option['matching_data']['label'] ) ) ) {
					return __( 'Each matching item needs its matching answer.', 'ohmylms' );
				}
			}
			if ( count( $options ) < $minimum || count( $options ) > 200 ) {
				return __( 'Add the answer options for this part (up to 200).', 'ohmylms' );
			}
			if ( in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false' ), true ) ) {
				$count = count( $child->get_correct_options() );
				if ( 0 === $count || ( 'multiple-choice' !== $type && 1 !== $count ) ) {
					return __( 'Select the correct answer for this part.', 'ohmylms' );
				}
			}
		}
		return true;
	}

	/**
	 * Scope options to the delivery, parent version, and stable part ID.
	 *
	 * @param string $scope Delivery scope.
	 * @param string $identity Parent identity.
	 * @param array  $part Private part.
	 * @return string Token scope.
	 */
	public static function scope( $scope, $identity, array $part ) {
		return $scope . ':structured:' . $identity . ':' . $part['id'];
	}

	/**
	 * Render a learner-safe child with independent opaque option/definition tokens.
	 *
	 * @param array  $part Private part.
	 * @param string $scope Scoped delivery.
	 * @return array Public child view.
	 */
	public static function view( array $part, $scope ) {
		$child = self::snapshot( $part );
		$ids   = array_column( $child->get_questions(), 'id' );
		$order = $ids;
		if ( in_array( $child->get_type(), array( 'matching', 'reorder' ), true ) || ! empty( $child->get_settings()['randomize'] ) ) {
			usort(
				$order,
				static function ( $left, $right ) use ( $scope ) {
					return strcmp( AttemptItems::token( $scope, $left ), AttemptItems::token( $scope, $right ) );
				}
			);
		}
		$definitions = $ids;
		usort(
			$definitions,
			static function ( $left, $right ) use ( $scope ) {
				return strcmp( AttemptItems::token( $scope, $left, 'd' ), AttemptItems::token( $scope, $right, 'd' ) );
			}
		);
		$view = $child->student_view( $order, AttemptItems::tokens( $scope, $ids ), array( 'definitions' => $definitions ), AttemptItems::tokens( $scope, $ids, 'd' ) );
		// Inline blank statements must expose placeholders, never their embedded answer keys.
		return $view;
	}

	/**
	 * Convert between public option tokens and the stored response.
	 *
	 * @param array  $part Private part.
	 * @param mixed  $answer Part response.
	 * @param string $scope Scoped delivery.
	 * @param bool   $decode Decode submitted tokens.
	 * @return mixed Mapped response.
	 */
	public static function map_response( array $part, $answer, $scope, $decode ) {
		$child      = self::snapshot( $part );
		$definition = Registry::get( 'question', $child->get_type() );
		if ( 'options' !== ( $definition['answers'] ?? '' ) || ! is_array( $answer ) ) {
			return $answer;
		}
		$ids    = array_column( $child->get_questions(), 'id' );
		$values = AttemptItems::tokens( $scope, $ids );
		$keys   = AttemptItems::tokens( $scope, $ids, 'd' );
		if ( $decode ) {
			$values = array_flip( $values );
			$keys   = array_flip( $keys );
		}
		$result = array();
		foreach ( $answer as $key => $value ) {
			if ( 'matching' === $child->get_type() ) {
				$key = $keys[ $key ] ?? 'invalid:' . $key;
			}
			$result[ $key ] = is_scalar( $value ) ? ( $values[ $value ] ?? ( '' === (string) $value ? '' : 'invalid:' . $value ) ) : $value;
		}
		return $result;
	}

	/**
	 * Render through the existing question templates with a nested field prefix.
	 *
	 * @param array  $view Public child.
	 * @param array  $attempt Attempt.
	 * @param string $prefix Parent part input name.
	 * @param string $field_id Unique DOM prefix.
	 * @param int    $parent_id Parent question ID.
	 * @return string Trusted template HTML.
	 */
	public static function render( array $view, array $attempt, $prefix, $field_id, $parent_id ) {
		ob_start();
		if ( ! empty( $view['inline_blanks'] ) ) {
			echo InlineBlanks::render( $view, $attempt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted learner-safe renderer escapes values.
		} else {
			QuestionTypes::render( $view, $attempt );
		}
		$html      = ob_get_clean();
		$processor = new \WP_HTML_Tag_Processor( $html );
		$original  = 'attempt[' . (int) $attempt['id'] . '][quiz_question][0]';
		while ( $processor->next_tag() ) {
			foreach ( array( 'name', 'data-name-prefix' ) as $attribute ) {
				$value = $processor->get_attribute( $attribute );
				if ( is_string( $value ) && str_starts_with( $value, $original ) ) {
					$processor->set_attribute( $attribute, $prefix . substr( $value, strlen( $original ) ) );
				}
			}
			foreach ( array( 'id', 'for' ) as $attribute ) {
				$value = $processor->get_attribute( $attribute );
				if ( is_string( $value ) ) {
					$processor->set_attribute( $attribute, $field_id . '-' . $value );
				}
			}
			if ( null !== $processor->get_attribute( 'data-question-id' ) ) {
				$processor->set_attribute( 'data-question-id', (string) $parent_id );
			}
		}
		return $processor->get_updated_html();
	}
}
