<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
namespace OhMyLMS\Extensions;

/**
 * Plugin question types implementation.
 */
final class QuestionTypes {
	/**
	 * Register defaults for Question types.
	 */
	public static function register_defaults() {
		$templates = array(
			'multiple-choice'   => 'multiple-question',
			'single-choice'     => 'single-choice',
			'true-false'        => 'true-false',
			'short-text'        => 'short-text',
			'long-text'         => 'long-text',
			'fill-in-the-blank' => 'fill-in-the-blank',
			'statement'         => 'statement',
			'reorder'           => 'reorder',
			'matching'          => 'matching',
		);
		foreach ( $templates as $type => $template ) {
			Registry::register(
				'question',
				$type,
				array(
					'manual'          => in_array( $type, array( 'short-text', 'long-text' ), true ),
					// Graders read only the question object they receive, so frozen versions grade correctly.
					'snapshot'        => true,
					// Answers reference options; versioned deliveries send opaque option tokens.
					'answers'         => in_array( $type, array( 'multiple-choice', 'single-choice', 'true-false', 'reorder', 'matching' ), true ) ? 'options' : 'text',
					'public_settings' => array(
						'fill-in-the-blank' => array( 'blank_mode' ),
						'matching'          => array( 'match_layout' ),
					)[ $type ] ?? array(),
					'label'           => ucwords( str_replace( '-', ' ', $type ) ),
					'editor'          => array(
						'format'   => 'ohmylms-options',
						'settings' => array( 'required', 'score', 'randomize' ),
					),
					'render'          => static function ( $question, $attempt ) use ( $template ) {
						ohmylms_get_template( 'single-lesson/quiz-loop/' . $template . '.php', compact( 'question', 'attempt' ) ); },
					'validate'        => static function ( $answer ) {
						return is_array( $answer ) && count( $answer ) <= 1000 && ! array_filter(
							$answer,
							static function ( $v ) {
								return ! is_scalar( $v );
							}
						); },
					'grade'           => static function ( $answer, $question ) use ( $type ) {
						return self::grade_builtin( $type, $answer, $question ); },
				)
			);
		}
	}
	/** Mathematics types: tolerance-based numerical answers and structured multi-part questions. */
	public static function register_math() {
		Registry::register(
			'question',
			'numerical',
			array(
				'label'           => __( 'Numerical', 'ohmylms' ),
				'manual'          => false,
				'snapshot'        => true,
				'answers'         => 'text',
				'public_settings' => array( 'unit' ),
				'editor'          => array(
					'format' => 'ohmylms-settings',
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'answer'          => array( 'type' => 'number' ),
							'answers'         => array(
								'type'  => 'array',
								'items' => array( 'type' => 'number' ),
							),
							'tolerance'       => array(
								'type'    => 'number',
								'minimum' => 0,
							),
							'tolerance_type'  => array(
								'type' => 'string',
								'enum' => array( 'absolute', 'relative' ),
							),
							'unit'            => array(
								'type'      => 'string',
								'maxLength' => 40,
							),
							'allow_fractions' => array( 'type' => 'boolean' ),
						),
						'anyOf'      => array( array( 'required' => array( 'answer' ) ), array( 'required' => array( 'answers' ) ) ),
					),
				),
				'render'          => static function ( $question, $attempt ) {
					ohmylms_get_template( 'single-lesson/quiz-loop/numerical.php', compact( 'question', 'attempt' ) ); },
				// Any text is a valid submission; invalid numbers are graded as incorrect, not rejected.
				'validate'        => static function ( $answer ) {
					return is_array( $answer ) && count( $answer ) <= 1 && ! array_filter(
						$answer,
						static function ( $v ) {
							return ! is_scalar( $v );
						}
					); },
				'grade'           => static function ( $answer, $question ) {
					$result = \OhMyLMS\Assessment\NumericAnswer::grade( $answer, $question->get_settings() );
					unset( $result['valid'] );
					return $result; },
			)
		);
		Registry::register(
			'question',
			'structured',
			array(
				'label'             => __( 'Structured (multi-part)', 'ohmylms' ),
				'manual'            => false,
				'snapshot'          => true,
				'answers'           => 'text',
				'public_settings'   => array( 'parts' ),
				'editor'            => array( 'format' => 'ohmylms-settings' ),
				'validate_settings' => array( \OhMyLMS\Assessment\Structured::class, 'validate_settings' ),
				'render'            => static function ( $question, $attempt ) {
					ohmylms_get_template( 'single-lesson/quiz-loop/structured.php', compact( 'question', 'attempt' ) ); },
				'validate'          => array( \OhMyLMS\Assessment\Structured::class, 'validate_response' ),
				'grade'             => array( \OhMyLMS\Assessment\Structured::class, 'grade' ),
			)
		);
	}
	/**
	 * Interactive recognize/produce types (dropdowns, sorting, multi-blank, tile expressions).
	 * Their answer keys live in settings that student_view() never exposes.
	 */
	public static function register_interactive() {
		$labels = array(
			'crossword'        => __( 'Crossword', 'ohmylms' ),
			'wordsearch'       => __( 'Wordsearch', 'ohmylms' ),
			'text-entry'       => __( 'Type the answer (automatic)', 'ohmylms' ),
			'dropdown-blanks'  => __( 'Dropdown in a sentence', 'ohmylms' ),
			'categorize'       => __( 'Sort into groups', 'ohmylms' ),
			'multi-blank'      => __( 'Multi-blank / table', 'ohmylms' ),
			'build-expression' => __( 'Build from tiles', 'ohmylms' ),
			'expression'       => __( 'Math expression', 'ohmylms' ),
			'number-line'      => __( 'Number line', 'ohmylms' ),
			'shade-model'      => __( 'Shade a model', 'ohmylms' ),
			'count-blocks'     => __( 'Count with blocks', 'ohmylms' ),
			'set-clock'        => __( 'Set the clock', 'ohmylms' ),
			'make-amount'      => __( 'Make an amount', 'ohmylms' ),
			'fill-level'       => __( 'Fill to a level', 'ohmylms' ),
			'build-chart'      => __( 'Build a chart', 'ohmylms' ),
			'grid-build'       => __( 'Build on a grid', 'ohmylms' ),
		);
		foreach ( \OhMyLMS\Assessment\Interactive::TYPES as $type ) {
			Registry::register(
				'question',
				$type,
				array(
					'label'             => $labels[ $type ],
					'manual'            => false,
					'snapshot'          => true,
					'answers'           => 'text',
					'public_settings'   => array(),
					'public_view'       => static function ( array $settings, $seed = '' ) use ( $type ) {
						return \OhMyLMS\Assessment\Interactive::public_view( $type, $settings, $seed ); },
					'editor'            => array( 'format' => 'ohmylms-settings' ),
					'validate_settings' => static function ( array $settings ) use ( $type ) {
						return \OhMyLMS\Assessment\Interactive::validate_settings( $type, $settings ); },
					'render'            => static function ( $question, $attempt ) use ( $type ) {
						ohmylms_get_template( 'single-lesson/quiz-loop/' . ( in_array( $type, \OhMyLMS\Assessment\WordPuzzle::TYPES, true ) ? 'puzzle' : ( \OhMyLMS\Assessment\Visual::handles( $type ) ? 'visual' : $type ) ) . '.php', compact( 'question', 'attempt' ) ); },
					// Keyed by slot / item / blank ID, or an ordered list of tiles; values are scalars.
					'validate'          => static function ( $answer ) use ( $type ) {
						if ( 'dropdown-blanks' === $type ) {
							return \OhMyLMS\Assessment\Interactive::validate_dropdown_response( $answer );
						}
						return is_array( $answer ) && count( $answer ) <= 200 && ! array_filter(
							$answer,
							static function ( $v ) {
								return ! is_scalar( $v );
							}
						); },
					'grade'             => static function ( $answer, $question ) use ( $type ) {
						$result = \OhMyLMS\Assessment\Interactive::grade( $type, $answer, $question->get_settings() );
						return $result; },
				)
			);
		}
	}
	/**
	 * Render for Question types.
	 *
	 * @param array $question Question.
	 * @param array $attempt Attempt.
	 */
	public static function render( array $question, array $attempt ) {
		$definition = Registry::get( 'question', $question['settings']['type'] ?? '' );
		if ( $definition ) {
			call_user_func( $definition['render'], $question, $attempt );
		}
	}
	/**
	 * Grade builtin for Question types.
	 *
	 * @param mixed $type Type.
	 * @param array $answer Answer.
	 * @param mixed $question Question.
	 */
	public static function grade_builtin( $type, array $answer, $question ) {
		if ( 'fill-in-the-blank' === $type && method_exists( $question, 'get_name' ) ) {
			$parsed = \OhMyLMS\Assessment\InlineBlanks::parse( $question->get_name() );
			if ( $parsed['answers'] ) {
				$settings       = method_exists( $question, 'get_settings' ) ? $question->get_settings() : array();
				$partial_credit = $settings['partial_credit'] ?? true;
				$values         = array_values( $answer );
				$parts          = array();
				$hits           = 0;
				foreach ( $parsed['answers'] as $i => $expected ) {
					$correct = isset( $values[ $i ] ) && \OhMyLMS\Assessment\InlineBlanks::matches( $values[ $i ], $expected, $question );
					$hits   += $correct ? 1 : 0;
					$parts[] = array(
						'correct'  => $correct,
						'fraction' => $correct ? 1 : 0,
					);
				}
				if ( count( $values ) > count( $parts ) ) {
					$hits = 0; }
				return array(
					'correct'  => $hits === count( $parts ),
					'fraction' => false === $partial_credit ? ( $hits === count( $parts ) ? 1 : 0 ) : $hits / count( $parts ),
					'manual'   => false,
					'blanks'   => $parts,
				);
			}
		}
		if ( in_array( $type, array( 'short-text', 'long-text' ), true ) ) {
			return array(
				'correct'  => false,
				'fraction' => 0,
				'manual'   => true,
			);
		}
		$options = $question->get_questions();
		$answer  = array_map( 'strval', $answer );
		$correct = false;
		if ( 'matching' === $type ) {
			$correct = count( $options ) > 0 && count( $answer ) === count( $options );
			foreach ( $options as $option ) {
				$correct = $correct && isset( $answer[ $option['id'] ] ) && $answer[ $option['id'] ] === (string) $option['id'];
			}
		} elseif ( 'reorder' === $type ) {
			$correct = count( $options ) > 0 && array_values( $answer ) === array_map( 'strval', array_column( $options, 'id' ) );
		} else {
			// Correctness comes from the options of the object being graded (a frozen version
			// in the versioned engine), never from the current answer tables.
			$expected = array_values(
				array_filter(
					$options,
					static function ( $option ) {
						return ! empty( $option['is_correct'] );
					}
				)
			);
			$field    = in_array( $type, array( 'statement', 'fill-in-the-blank' ), true ) ? 'answer' : 'id';
			$expected = array_map( 'strval', array_column( $expected, $field ) );
			$answer   = array_values( $answer );
			if ( 'multiple-choice' === $type ) {
				sort( $expected );
				sort( $answer ); }
			$correct = count( $expected ) > 0 && $answer === $expected;
			if ( 'multiple-choice' === $type && true === ( $question->get_settings()['partial_credit'] ?? false ) ) {
				$expected = array_values( array_unique( $expected ) );
				$answer   = array_values( array_unique( $answer ) );
				sort( $expected );
				sort( $answer );
				$hits   = count( array_intersect( $answer, $expected ) );
				$misses = count( array_diff( $answer, $expected ) );
				return array(
					'correct'  => 0 < count( $expected ) && $answer === $expected,
					'fraction' => 0 < count( $expected ) ? max( 0.0, ( $hits - $misses ) / count( $expected ) ) : 0.0,
					'manual'   => false,
				);
			}
			if ( 'fill-in-the-blank' === $type && method_exists( $question, 'get_settings' ) && true === ( $question->get_settings()['partial_credit'] ?? false ) && 0 < count( $expected ) ) {
				$hits = 0;
				foreach ( $expected as $index => $value ) {
					if ( isset( $answer[ $index ] ) && \OhMyLMS\Assessment\InlineBlanks::matches( $answer[ $index ], $value, $question ) ) {
						++$hits;
					}
				}
				if ( count( $answer ) > count( $expected ) ) {
					$hits = 0;
				}
				return array(
					'correct'  => $hits === count( $expected ),
					'fraction' => $hits / count( $expected ),
					'manual'   => false,
				);
			}
			if ( 'fill-in-the-blank' === $type && count( $expected ) > 0 && count( $answer ) === count( $expected ) ) {
				$correct = true;
				foreach ( $expected as $i => $value ) {
					$correct = $correct && \OhMyLMS\Assessment\InlineBlanks::matches( $answer[ $i ], $value, $question );
				}
			}
		}
		return array(
			'correct'  => $correct,
			'fraction' => $correct ? 1 : 0,
			'manual'   => false,
		);
	}
}
