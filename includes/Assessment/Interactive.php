<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Interactive "recognize and produce" question types.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

use OhMyLMS\Assessment\Cas\Expression;

defined( 'ABSPATH' ) || exit;

/**
 * Interactive "recognize and produce" question types.
 *
 * Every type keeps its answer key in settings keys that never reach a learner;
 * public_view() returns the only data a renderer may use. Grading is whole-question
 * (right or wrong) with per-item results returned for feedback, unless a type is
 * explicitly marked partial.
 *
 * dropdown-blanks  text "The slope is {1}, so the line {2}"; slots = [[id, choices[], answer]]
 * categorize       buckets = [[id, label]]; items = [[id, text, image_url]]; key = item id => bucket id
 * multi-blank      layout inline|table; text / columns / rows with {id} markers; blanks = id => spec
 * build-expression correct = tiles in order; distractors = extra tiles; alternatives = other valid orders
 */
final class Interactive {
	const TYPES = array( ...WordPuzzle::TYPES, 'text-entry', 'dropdown-blanks', 'categorize', 'multi-blank', 'build-expression', 'expression', ...Visual::TYPES );

	/** Marker such as {1} or {b2} inside a prompt or table cell. */
	const MARKER = '/\{([a-z0-9_-]{1,20})\}/i';

	/**
	 * Is interactive for Interactive.
	 *
	 * @param mixed $type Type.
	 */
	public static function is_interactive( $type ) {
		return in_array( $type, self::TYPES, true );
	}

	/** Marker IDs in the order they appear.
	 *
	 * @param mixed $text Text.
	 */
	public static function markers( $text ) {
		$text = preg_replace( '~' . MathLive::MARKER . '~', '', (string) $text );
		$text = preg_replace( '/\{\{[^{}]{1,240}\}\}/', '', $text );
		preg_match_all( self::MARKER, (string) $text, $found );
		return array_values( array_unique( $found[1] ) );
	}

	/** Blank IDs of a multi-blank question, from its inline text or table cells.
	 *
	 * @param array $settings Configuration settings.
	 */
	public static function blank_ids( array $settings ) {
		if ( ( $settings['layout'] ?? 'inline' ) === 'table' ) {
			$ids = array();
			foreach ( (array) ( $settings['rows'] ?? array() ) as $row ) {
				foreach ( (array) $row as $cell ) {
					$ids = array_merge( $ids, self::markers( is_scalar( $cell ) ? $cell : '' ) );
				}
			}
			return array_values( array_unique( $ids ) );
		}
		return self::markers( $settings['text'] ?? '' );
	}

	/** Same list, same seed, same order: stable across reloads, different across learners.
	 *
	 * @param array $list List.
	 * @param mixed $seed Seed.
	 */
	public static function shuffled( array $list, $seed ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.listFound -- Preserve the public named-argument contract; see docs/CODING-STANDARDS.md.
		$keyed = array();
		foreach ( array_values( $list ) as $index => $item ) {
			$keyed[] = array( hash( 'sha256', $seed . '|' . $index ), $item );
		}
		usort(
			$keyed,
			static function ( $a, $b ) {
				return strcmp( $a[0], $b[0] );
			}
		);
		return array_column( $keyed, 1 );
	}

	/** The tile bank of a build-expression question, unshuffled.
	 *
	 * @param array $settings Configuration settings.
	 */
	public static function tiles( array $settings ) {
		return array_merge(
			array_map( 'strval', array_values( (array) ( $settings['correct'] ?? array() ) ) ),
			array_map( 'strval', array_values( (array) ( $settings['distractors'] ?? array() ) ) )
		);
	}

	/**
	 * Learner-safe settings: no answers, accepted values, tolerances or bucket assignments.
	 *
	 * @param mixed  $type Type.
	 * @param array  $settings Configuration settings.
	 * @param string $seed Delivery seed that fixes the order of shuffled lists.
	 */
	public static function public_view( $type, array $settings, $seed = '' ) {
		if ( in_array( $type, WordPuzzle::TYPES, true ) ) {
			return WordPuzzle::public_view( $type, $settings );
		}
		if ( Visual::handles( $type ) ) {
			return Visual::public_view( $type, $settings ); }
		$view = array();
		switch ( $type ) {
			case 'text-entry':
				return array( 'max_length' => 2000 );
			case 'dropdown-blanks':
				$view['text']  = (string) ( $settings['text'] ?? '' );
				$view['slots'] = array();
				foreach ( (array) ( $settings['slots'] ?? array() ) as $slot ) {
					$id              = preg_replace( '/[^a-z0-9_-]/i', '', (string) ( $slot['id'] ?? '' ) );
					$view['slots'][] = array(
						'id'       => $id,
						'choices'  => self::shuffled( array_values( array_map( 'strval', (array) ( $slot['choices'] ?? array() ) ) ), $seed . '|' . $id ),
						'multiple' => ! empty( $slot['multiple'] ),
					);
				}
				break;
			case 'categorize':
				$view['buckets'] = array_map(
					static function ( $bucket ) {
						return array(
							'id'    => (string) ( $bucket['id'] ?? '' ),
							'label' => (string) ( $bucket['label'] ?? '' ),
						);
					},
					array_values( (array) ( $settings['buckets'] ?? array() ) )
				);
				$view['items']   = self::shuffled(
					array_map(
						static function ( $item ) {
							return array(
								'id'        => (string) ( $item['id'] ?? '' ),
								'text'      => (string) ( $item['text'] ?? '' ),
								'image_url' => (string) ( $item['image_url'] ?? '' ),
							);
						},
						array_values( (array) ( $settings['items'] ?? array() ) )
					),
					$seed
				);
				break;
			case 'multi-blank':
				$layout         = ( $settings['layout'] ?? 'inline' ) === 'table' ? 'table' : 'inline';
				$view['layout'] = $layout;
				if ( 'table' === $layout ) {
					$view['columns'] = array_map( 'strval', array_values( (array) ( $settings['columns'] ?? array() ) ) );
					$view['rows']    = array_map(
						static function ( $row ) {
							return array_map( 'strval', array_values( (array) $row ) );
						},
						array_values( (array) ( $settings['rows'] ?? array() ) )
					);
				} else {
					$view['text'] = (string) ( $settings['text'] ?? '' );
				}
				$view['fields'] = array();
				foreach ( self::blank_ids( $settings ) as $id ) {
					$spec                  = (array) ( $settings['blanks'][ $id ] ?? array() );
					$view['fields'][ $id ] = array(
						'kind' => in_array( $spec['kind'] ?? '', array( 'numerical', 'expression' ), true ) ? $spec['kind'] : 'text',
						'unit' => (string) ( $spec['unit'] ?? '' ),
						'form' => self::form( $spec['form'] ?? 'any' ),
					);
				}
				break;
			case 'build-expression':
				$view['tiles'] = self::shuffled( self::tiles( $settings ), $seed );
				$view['form']  = self::form( $settings['form'] ?? 'any' );
				break;
			case 'expression':
				$view['form'] = self::form( $settings['form'] ?? 'any' );
				break;
		}
		return $view;
	}

	/** Readable correct answer, revealed only after a response has been graded.
	 *
	 * @param mixed $type Type.
	 * @param array $settings Configuration settings.
	 */
	public static function expected( $type, array $settings ) {
		if ( in_array( $type, WordPuzzle::TYPES, true ) ) {
			return array_map(
				static function ( $entry ) {
					return $entry['clue'] . ' = ' . $entry['answer'];
				},
				$settings['entries']
			);
		}
		if ( Visual::handles( $type ) ) {
			return Visual::expected( $type, $settings ); }
		switch ( $type ) {
			case 'text-entry':
				return array_values( (array) ( $settings['accepted'] ?? array() ) );
			case 'dropdown-blanks':
				return array_map(
					static function ( $slot ) {
						return '{' . ( $slot['id'] ?? '' ) . '} = ' . implode( ', ', self::dropdown_answers( $slot ) );
					},
					array_values( (array) ( $settings['slots'] ?? array() ) )
				);
			case 'categorize':
				$labels = array_column( (array) ( $settings['buckets'] ?? array() ), 'label', 'id' );
				$key    = (array) ( $settings['key'] ?? array() );
				return array_map(
					static function ( $item ) use ( $labels, $key ) {
						return ( $item['text'] ?? '' ) . ' → ' . ( $labels[ $key[ $item['id'] ?? '' ] ?? '' ] ?? '' );
					},
					array_values( (array) ( $settings['items'] ?? array() ) )
				);
			case 'multi-blank':
				$out = array();
				foreach ( self::blank_ids( $settings ) as $id ) {
					$spec  = (array) ( $settings['blanks'][ $id ] ?? array() );
					$kind  = $spec['kind'] ?? 'text';
					$value = 'numerical' === $kind ? ( $spec['answers'][0] ?? $spec['answer'] ?? '' ) : ( 'expression' === $kind ? ( $spec['answer'] ?? '' ) : ( $spec['accepted'][0] ?? '' ) );
					$out[] = '{' . $id . '} = ' . $value;
				}
				return $out;
			case 'build-expression':
				return array( implode( ' ', array_map( 'strval', (array) ( $settings['correct'] ?? array() ) ) ) );
			case 'expression':
				return array( (string) ( $settings['answer'] ?? '' ) );
		}
		return array();
	}

	/** Does this settings array still hold an answer key?
	 *
	 * @param mixed $type Type.
	 * @param array $settings Configuration settings.
	 */
	private static function has_key( $type, array $settings ) {
		if ( in_array( $type, WordPuzzle::TYPES, true ) ) {
			return isset( $settings['entries'] );
		}
		if ( Visual::handles( $type ) ) {
			return Visual::has_key( $type, $settings ); }
		switch ( $type ) {
			case 'text-entry':
				return isset( $settings['accepted'] );
			case 'dropdown-blanks':
				foreach ( (array) ( $settings['slots'] ?? array() ) as $slot ) {
					if ( isset( $slot['answer'] ) || isset( $slot['answers'] ) ) {
						return true; }
				}
				return false;
			case 'categorize':
				return isset( $settings['key'] );
			case 'multi-blank':
				return isset( $settings['blanks'] );
			case 'build-expression':
				return isset( $settings['correct'] );
			case 'expression':
				return isset( $settings['answer'] );
		}
		return false;
	}

	/**
	 * Settings a template may output. Student views are already safe; raw settings
	 * (legacy rendering, previews) are reduced here so a template can never leak a key.
	 *
	 * @param mixed $type Type.
	 * @param array $question Question.
	 */
	public static function learner_settings( $type, array $question ) {
		$settings = (array) ( $question['settings'] ?? array() );
		if ( in_array( $type, WordPuzzle::TYPES, true ) && isset( $settings['entries'] ) ) {
			return WordPuzzle::public_view( $type, $settings );
		}
		if ( self::has_key( $type, $settings ) ) {
			$private = array(
				'text-entry'       => array( 'accepted', 'case_sensitive', 'collapse_whitespace', 'ignore_punctuation' ),
				'dropdown-blanks'  => array( 'dropdown_grading_version', 'partial_credit' ),
				'categorize'       => array( 'key' ),
				'multi-blank'      => array( 'blanks' ),
				'build-expression' => array( 'correct', 'distractors', 'alternatives' ),
				'expression'       => array( 'answer', 'alternatives' ),
			);
			$kept    = array_diff_key( $settings, array_flip( $private[ $type ] ?? Visual::private_keys( $type ) ) );
			return array_merge( $kept, self::public_view( $type, $settings, (string) ( $question['id'] ?? '' ) ) );
		}
		return $settings;
	}

	/** Form field name for a keyed answer, or for the ordered list when $key is null.
	 *
	 * @param array $attempt Attempt.
	 * @param array $question Question.
	 * @param mixed $key Lookup key.
	 */
	public static function field_name( array $attempt, array $question, $key = null ) {
		return 'attempt[' . (int) $attempt['id'] . '][quiz_question][' . (int) $question['id'] . ']'
			. ( null === $key ? '[]' : '[' . preg_replace( '/[^a-z0-9_-]/i', '', (string) $key ) . ']' );
	}

	/** Field-name prefix for widgets that add their own hidden inputs: attempt[a][quiz_question][q].
	 *
	 * @param array $attempt Attempt.
	 * @param array $question Question.
	 */
	public static function field_prefix( array $attempt, array $question ) {
		return 'attempt[' . (int) $attempt['id'] . '][quiz_question][' . (int) $question['id'] . ']';
	}

	/**
	 * Register the one bundled JSXGraph (script `ohmylms-jsxgraph`, global `JXG`) and its style.
	 *
	 * The graphing question and the Function Graph block both draw with it; nothing else carries a copy.
	 */
	public static function register_jsxgraph() {
		wp_register_script( 'ohmylms-jsxgraph', plugins_url( 'assets/dist/jsxgraph/jsxgraphcore.js', OHMYLMS_FILE ), array(), '1.14.1', true );
		wp_register_style( 'ohmylms-jsxgraph', plugins_url( 'assets/dist/jsxgraph/jsxgraph.css', OHMYLMS_FILE ), array(), '1.14.1' );
	}

	/**
	 * Enqueue for Interactive.
	 */
	public static function enqueue() {
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return; }
		self::register_assets();
		MathLive::enqueue_loader();
		wp_enqueue_script( 'ohmylms-interactive' );
		wp_enqueue_script( 'ohmylms-interactive-visual' );
		wp_enqueue_script( 'ohmylms-puzzle-loader', plugins_url( 'assets/js/puzzle-loader.js', OHMYLMS_FILE ), array( 'ohmylms-interactive', 'wp-i18n' ), max( filemtime( OHMYLMS_DIR . '/assets/js/puzzle-loader.js' ), filemtime( OHMYLMS_DIR . '/assets/interactivity/puzzles.mjs' ) ), true );
		wp_enqueue_style( 'ohmylms-puzzles', plugins_url( 'assets/css/puzzles.css', OHMYLMS_FILE ), array(), filemtime( OHMYLMS_DIR . '/assets/css/puzzles.css' ) );
		self::register_jsxgraph();
		wp_enqueue_style( 'ohmylms-jsxgraph' );
		wp_enqueue_script( 'ohmylms-extended-questions', plugins_url( 'assets/interactivity/extended-questions.js', OHMYLMS_FILE ), array( 'ohmylms-interactive', 'ohmylms-jsxgraph', 'wp-i18n' ), max( filemtime( OHMYLMS_DIR . '/assets/interactivity/extended-questions.js' ), filemtime( OHMYLMS_DIR . '/assets/interactivity/diagram-labels.mjs' ) ), true );
		wp_localize_script( 'ohmylms-extended-questions', 'ohmylmsResponseMedia', ResponseMedia::script_data() );
		wp_enqueue_style( 'ohmylms-extended-questions', plugins_url( 'assets/css/extended-questions.css', OHMYLMS_FILE ), array( 'ohmylms-interactive' ), filemtime( OHMYLMS_DIR . '/assets/css/extended-questions.css' ) );
		wp_enqueue_style( 'ohmylms-diagram-labels', plugins_url( 'assets/css/diagram-labels.css', OHMYLMS_FILE ), array( 'ohmylms-extended-questions' ), filemtime( OHMYLMS_DIR . '/assets/css/diagram-labels.css' ) );
		wp_enqueue_style( 'ohmylms-interactive' );
	}

	/** Registered once so the practice runner can depend on the controls. */
	public static function register_assets() {
		if ( ! wp_script_is( 'ohmylms-interactive', 'registered' ) ) {
			wp_register_script( 'ohmylms-interactive', plugins_url( 'assets/js/interactive-controls.js', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/js/interactive-controls.js' ), true ); }
		if ( ! wp_script_is( 'ohmylms-interactive-visual', 'registered' ) ) {
			wp_register_script( 'ohmylms-interactive-visual', plugins_url( 'assets/js/interactive-visual.js', OHMYLMS_FILE ), array( 'ohmylms-interactive' ), OHMYLMS_VERSION, true ); }
		if ( ! wp_style_is( 'ohmylms-interactive', 'registered' ) ) {
			wp_register_style( 'ohmylms-interactive', plugins_url( 'assets/css/interactive.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/interactive.css' ) ); }
	}

	/**
	 * Split text at {marker}s: calls $field($id) for each marker, escapes the rest.
	 *
	 * @param mixed    $text Text.
	 * @param callable $field Receives the marker ID and echoes a control.
	 */
	public static function render_marked( $text, callable $field ) {
		$parts = preg_split( '~(' . MathLive::MARKER . '|\{[a-z0-9_-]{1,20}\})~i', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $index => $part ) {
			if ( $index % 2 && '{' === substr( $part, 0, 1 ) ) {
				call_user_func( $field, substr( $part, 1, -1 ) );
			} else {
				echo esc_html( $part );
			}
		}
	}

	/**
	 * Grade.
	 *
	 * @param mixed       $type Type.
	 * @param array|mixed $answer Keyed by slot / item / blank ID, or an ordered list of tiles.
	 * @param array       $settings Configuration settings.
	 * @return array{correct:bool,fraction:float,manual:bool}
	 */
	public static function grade( $type, $answer, array $settings ) {
		$answer = is_array( $answer ) ? $answer : array();
		if ( in_array( $type, WordPuzzle::TYPES, true ) ) {
			return WordPuzzle::grade( $type, $answer, $settings );
		}
		if ( Visual::handles( $type ) ) {
			return Visual::grade( $type, $answer, $settings ); }
		switch ( $type ) {
			case 'text-entry':
				return TextEntry::grade( $answer, $settings );
			case 'dropdown-blanks':
				return self::grade_dropdown( $answer, $settings );
			case 'categorize':
				return self::grade_categorize( $answer, $settings );
			case 'multi-blank':
				return self::grade_multi_blank( $answer, $settings );
			case 'build-expression':
				return self::grade_tiles( $answer, $settings );
			case 'expression':
				return self::grade_expression( $answer, $settings );
		}
		return self::result( array( false ), false );
	}

	/**
	 * Validate bounded scalar or selection-list responses for dropdowns.
	 *
	 * @param mixed $answer Learner response.
	 * @return bool Whether the response shape is supported.
	 */
	public static function validate_dropdown_response( $answer ) {
		if ( ! is_array( $answer ) || count( $answer ) > 200 ) {
			return false;
		}
		foreach ( $answer as $input ) {
			$values = is_array( $input ) ? $input : array( $input );
			if ( count( $values ) > 200 ) {
				return false;
			}
			foreach ( $values as $value ) {
				if ( ! is_scalar( $value ) || strlen( (string) $value ) > 2000 ) {
					return false;
				}
			}
		}
		return true;
	}

	/** A form name the engine knows; anything else means no requirement.
	 *
	 * @param mixed $form Form.
	 */
	private static function form( $form ) {
		return in_array( $form, Expression::FORMS, true ) ? $form : 'any';
	}

	/** The one typed value of a single-field answer, posted as a list or as a keyed object.
	 *
	 * @param array $answer Answer.
	 */
	private static function typed( array $answer ) {
		foreach ( $answer as $value ) {
			if ( is_scalar( $value ) ) {
				return (string) $value; }
		}
		return '';
	}

	/**
	 * Reject unsupported learner notation without inspecting or disclosing the answer key.
	 *
	 * @param string $type Question type.
	 * @param mixed  $answer Learner answer.
	 * @param array  $settings Frozen settings.
	 * @return bool Whether nonempty expression fields use supported notation.
	 */
	public static function supported_input( $type, $answer, array $settings ) {
		$answer = (array) $answer;
		$fields = array();
		if ( 'expression' === $type ) {
			$fields[] = self::typed( (array) $answer );
		} elseif ( 'multi-blank' === $type ) {
			foreach ( (array) ( $settings['blanks'] ?? array() ) as $id => $spec ) {
				if ( 'expression' === ( $spec['kind'] ?? '' ) ) {
					$fields[] = $answer[ $id ] ?? '';
				}
			}
		}
		foreach ( $fields as $field ) {
			if ( ! is_scalar( $field ) || ( '' !== trim( (string) $field ) && null === Expression::parse( $field ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Grade expression for Interactive.
	 *
	 * @param array $answer Answer.
	 * @param array $settings Configuration settings.
	 */
	private static function grade_expression( array $answer, array $settings ) {
		$typed = self::typed( $answer );
		$keys  = array_merge( array( (string) ( $settings['answer'] ?? '' ) ), array_map( 'strval', (array) ( $settings['alternatives'] ?? array() ) ) );
		$form  = self::form( $settings['form'] ?? 'any' );
		$ok    = false;
		$why   = 'different';
		foreach ( array_filter( $keys, 'strlen' ) as $key ) {
			$check = Expression::check( $typed, $key, $form, $settings );
			$ok    = $ok || $check['correct'];
			// "form" is the most helpful reason when the value was right but the shape was not.
			$why = $check['correct'] ? 'equivalent' : ( 'equivalent' === $why || 'different' === $check['reason'] ? $why : $check['reason'] );
		}
		$result           = self::result( array( $ok ), false );
		$result['reason'] = $ok ? 'equivalent' : $why;
		return $result;
	}

	/**
	 * Result for Interactive.
	 *
	 * @param bool[] $items per-item correctness.
	 * @param mixed  $partial Partial.
	 */
	private static function result( array $items, $partial ) {
		$total = count( $items );
		$hits  = count( array_filter( $items ) );
		$all   = $total > 0 && $hits === $total;
		return array(
			'correct'  => $all,
			'fraction' => $all ? 1.0 : ( $partial && $total > 0 ? $hits / $total : 0.0 ),
			'manual'   => false,
			'items'    => $items,
		);
	}

	/**
	 * Same text for Interactive.
	 *
	 * @param mixed $a A.
	 * @param mixed $b B.
	 */
	private static function same_text( $a, $b ) {
		return mb_strtolower( trim( (string) $a ) ) === mb_strtolower( trim( (string) $b ) );
	}

	/**
	 * Grade dropdown for Interactive.
	 *
	 * @param array $answer Answer.
	 * @param array $settings Configuration settings.
	 */
	private static function grade_dropdown( array $answer, array $settings ) {
		if ( 2 === ( $settings['dropdown_grading_version'] ?? null ) ) {
			return self::grade_weighted_dropdown( $answer, $settings );
		}
		$items = array();
		foreach ( (array) ( $settings['slots'] ?? array() ) as $slot ) {
			$id    = (string) ( $slot['id'] ?? '' );
			$input = $answer[ $id ] ?? '';
			// The chosen text must be one of the slot's choices and the key.
			$items[ $id ] = is_scalar( $input ) && '' !== (string) $input
				&& in_array( (string) $input, array_map( 'strval', (array) ( $slot['choices'] ?? array() ) ), true )
				&& self::same_text( $input, $slot['answer'] ?? null );
		}
		return self::result( $items, ! empty( $settings['partial_credit'] ) );
	}

	/**
	 * Read legacy or multiple correct choices from private settings.
	 *
	 * @param array $slot Dropdown settings.
	 * @return array Correct choices.
	 */
	private static function dropdown_answers( array $slot ) {
		return isset( $slot['answers'] ) ? (array) $slot['answers'] : array( $slot['answer'] ?? '' );
	}

	/**
	 * Grade bounded multiple selections with frozen per-dropdown points.
	 *
	 * @param array $answer Learner selections.
	 * @param array $settings Frozen teacher settings.
	 * @return array Grade fraction and dropdown correctness.
	 */
	private static function grade_weighted_dropdown( array $answer, array $settings ) {
		$total  = 0.0;
		$earned = 0.0;
		$items  = array();
		foreach ( (array) ( $settings['slots'] ?? array() ) as $slot ) {
			$id       = (string) $slot['id'];
			$points   = max( 0.0, (float) ( $slot['points'] ?? 0 ) );
			$total   += $points;
			$input    = $answer[ $id ] ?? array();
			$selected = is_scalar( $input ) ? array( (string) $input ) : $input;
			$expected = self::dropdown_answers( $slot );
			$fraction = 0.0;
			if ( is_array( $selected ) && count( $selected ) <= 200 && ! array_filter(
				$selected,
				static function ( $choice ) {
					return ! is_string( $choice ); }
			) ) {
				$selected = array_unique( $selected );
				$valid    = ! array_diff( $selected, (array) $slot['choices'] ) && ( ! empty( $slot['multiple'] ) || count( $selected ) <= 1 );
				$correct  = array_filter(
					$selected,
					static function ( $choice ) use ( $expected ) {
						foreach ( $expected as $accepted ) {
							if ( self::same_text( $choice, $accepted ) ) {
								return true;
							}
						}
						return false;
					}
				);
				if ( $valid && $correct && count( $correct ) === count( $selected ) ) {
					$fraction = in_array( $slot['grading'] ?? 'equal', array( 'any', 'no-wrong' ), true ) ? 1.0 : count( $correct ) / max( 1, count( $expected ) );
				}
			}
			$earned      += $points * $fraction;
			$items[ $id ] = 1.0 <= $fraction;
		}
		return array(
			'correct'  => ! empty( $items ) && ! in_array( false, $items, true ),
			'fraction' => $total > 0 ? min( 1.0, $earned / $total ) : 0.0,
			'manual'   => false,
			'items'    => $items,
		);
	}

	/**
	 * Grade categorize for Interactive.
	 *
	 * @param array $answer Answer.
	 * @param array $settings Configuration settings.
	 */
	private static function grade_categorize( array $answer, array $settings ) {
		$items = array();
		$key   = (array) ( $settings['key'] ?? array() );
		foreach ( (array) ( $settings['items'] ?? array() ) as $item ) {
			$id           = (string) ( $item['id'] ?? '' );
			$items[ $id ] = isset( $answer[ $id ], $key[ $id ] ) && is_scalar( $answer[ $id ] ) && (string) $answer[ $id ] === (string) $key[ $id ];
		}
		return self::result( $items, ! empty( $settings['partial_credit'] ) );
	}

	/**
	 * Grade multi blank for Interactive.
	 *
	 * @param array $answer Answer.
	 * @param array $settings Configuration settings.
	 */
	private static function grade_multi_blank( array $answer, array $settings ) {
		$items = array();
		foreach ( self::blank_ids( $settings ) as $id ) {
			$spec    = (array) ( $settings['blanks'][ $id ] ?? array() );
			$input   = $answer[ $id ] ?? '';
			$present = is_scalar( $input ) && trim( (string) $input ) !== '';
			if ( ! $present ) {
				$items[ $id ] = false;
			} elseif ( ( $spec['kind'] ?? 'text' ) === 'numerical' ) {
				$items[ $id ] = NumericAnswer::grade( $input, $spec )['fraction'] >= 1.0;
			} elseif ( ( $spec['kind'] ?? 'text' ) === 'expression' ) {
				$items[ $id ] = Expression::check( (string) $input, (string) ( $spec['answer'] ?? '' ), self::form( $spec['form'] ?? 'any' ), $spec )['correct'];
			} else {
				$accepted     = array_map( array( Structured::class, 'normalize_text' ), (array) ( $spec['accepted'] ?? array() ) );
				$items[ $id ] = in_array( Structured::normalize_text( $input ), $accepted, true );
			}
		}
		return self::result( $items, ! empty( $settings['partial_credit'] ) );
	}

	/**
	 * Grade tiles for Interactive.
	 *
	 * @param array $answer Answer.
	 * @param array $settings Configuration settings.
	 */
	private static function grade_tiles( array $answer, array $settings ) {
		$given = array_map( 'strval', array_values( array_filter( $answer, 'is_scalar' ) ) );
		$valid = array_merge(
			array( (array) ( $settings['correct'] ?? array() ) ),
			(array) ( $settings['alternatives'] ?? array() )
		);
		$ok    = false;
		foreach ( $valid as $sequence ) {
			$ok = $ok || ( $given && array_map( 'strval', array_values( (array) $sequence ) ) === $given );
		}
		// Different tiles can still say the same thing (2 + 3 x versus 3 x + 2): ask the engine.
		if ( ! $ok && $given && ! empty( $settings['equivalence'] ) ) {
			$form = self::form( $settings['form'] ?? 'any' );
			foreach ( $valid as $sequence ) {
				$ok = $ok || Expression::check( implode( '', $given ), implode( '', array_map( 'strval', (array) $sequence ) ), $form, $settings )['correct'];
			}
		}
		return self::result( array( $ok ), false );
	}

	/** Authoring validation: true or a message.
	 *
	 * @param mixed $type Type.
	 * @param array $settings Configuration settings.
	 */
	public static function validate_settings( $type, array $settings ) {
		if ( in_array( $type, WordPuzzle::TYPES, true ) ) {
			return WordPuzzle::validate_settings( $type, $settings );
		}
		if ( Visual::handles( $type ) ) {
			return Visual::validate_settings( $type, $settings ); }
		switch ( $type ) {
			case 'text-entry':
				return TextEntry::validate_settings( $settings );
			case 'dropdown-blanks':
				if ( isset( $settings['dropdown_grading_version'] ) && 2 !== $settings['dropdown_grading_version'] ) {
					return __( 'Unsupported dropdown grading version.', 'ohmylms' );
				}
				$slots = (array) ( $settings['slots'] ?? array() );
				$ids   = self::markers( $settings['text'] ?? '' );
				if ( ! $slots || ! $ids ) {
					return __( 'Write the sentence with at least one {1} marker and add its choices.', 'ohmylms' ); }
				$seen = array();
				foreach ( $slots as $slot ) {
					$id      = (string) ( $slot['id'] ?? '' );
					$choices = array_map( 'strval', (array) ( $slot['choices'] ?? array() ) );
					if ( ! preg_match( '/^[a-z0-9_-]{1,20}$/i', $id ) || isset( $seen[ $id ] ) || ! in_array( $id, $ids, true ) ) {
						return __( 'Each dropdown needs a unique ID that appears in the sentence as {ID}.', 'ohmylms' ); }
					$seen[ $id ] = true;
					if ( count( $choices ) < 2 || count( array_unique( array_map( 'mb_strtolower', $choices ) ) ) !== count( $choices ) || in_array( '', $choices, true ) ) {
						return __( 'Each dropdown needs at least two different, non-empty choices.', 'ohmylms' ); }
					$answers = self::dropdown_answers( $slot );
					if ( ! $answers || count( $answers ) > 200 || array_filter(
						$answers,
						static function ( $choice ) {
							return ! is_string( $choice ); }
					) || count( $answers ) !== count( array_unique( $answers ) ) || array_diff( $answers, $choices ) ) {
						return __( 'Each dropdown answer must be one of its choices.', 'ohmylms' ); }
					if ( 2 === ( $settings['dropdown_grading_version'] ?? null ) && ( ! isset( $slot['points'] ) || ! is_numeric( $slot['points'] ) || ! is_finite( (float) $slot['points'] ) || $slot['points'] < 0 || ! in_array( $slot['grading'] ?? '', array( 'equal', 'any', 'no-wrong' ), true ) || ( count( $answers ) > 1 && empty( $slot['multiple'] ) ) ) ) {
						return __( 'Set non-negative dropdown points and a supported grading mode. Multiple correct answers require multiple selections.', 'ohmylms' );
					}
				}
				if ( 2 === ( $settings['dropdown_grading_version'] ?? null ) ) {
					$total = array_sum( array_column( $slots, 'points' ) );
					if ( ! is_finite( (float) $total ) || ! is_numeric( $settings['score']['value'] ?? null ) || ! is_finite( (float) $settings['score']['value'] ) || abs( $total - (float) $settings['score']['value'] ) > 0.000001 ) {
						return __( 'Question points must equal the sum of its dropdown points.', 'ohmylms' );
					}
				}
				return count( $seen ) === count( $ids ) ? true : __( 'Every {marker} in the sentence needs a dropdown.', 'ohmylms' );
			case 'categorize':
				$buckets = array_column( (array) ( $settings['buckets'] ?? array() ), 'id' );
				$items   = (array) ( $settings['items'] ?? array() );
				$key     = (array) ( $settings['key'] ?? array() );
				if ( count( $buckets ) < 2 || count( array_unique( $buckets ) ) !== count( $buckets ) || count( array_filter( array_column( (array) $settings['buckets'], 'label' ), 'strlen' ) ) !== count( $buckets ) ) {
					return __( 'Add at least two labelled groups with unique IDs.', 'ohmylms' ); }
				if ( ! $items || count( array_unique( array_column( $items, 'id' ) ) ) !== count( $items ) ) {
					return __( 'Add items with unique IDs.', 'ohmylms' ); }
				foreach ( $items as $item ) {
					$id = (string) ( $item['id'] ?? '' );
					if ( trim( (string) ( $item['text'] ?? '' ) ) === '' && empty( $item['image_url'] ) ) {
						return __( 'Every item needs text or an image.', 'ohmylms' ); }
					if ( ! isset( $key[ $id ] ) || ! in_array( (string) $key[ $id ], array_map( 'strval', $buckets ), true ) ) {
						return __( 'Every item must belong to one of the groups.', 'ohmylms' ); }
				}
				return true;
			case 'multi-blank':
				$ids = self::blank_ids( $settings );
				if ( ! $ids ) {
					return __( 'Add at least one {blank} marker.', 'ohmylms' ); }
				if ( ( $settings['layout'] ?? 'inline' ) === 'table' && ! array_filter( (array) ( $settings['rows'] ?? array() ) ) ) {
					return __( 'A table needs at least one row.', 'ohmylms' ); }
				foreach ( $ids as $id ) {
					$spec = (array) ( $settings['blanks'][ $id ] ?? array() );
					if ( ( $spec['kind'] ?? 'text' ) === 'numerical' ) {
						$expected = $spec['answers'] ?? array( $spec['answer'] ?? null );
						foreach ( (array) $expected as $value ) {
							if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) ) {
								return __( 'Numerical blanks need a finite expected answer.', 'ohmylms' ); }
						}
					} elseif ( ( $spec['kind'] ?? 'text' ) === 'expression' ) {
						if ( Expression::parse( $spec['answer'] ?? '' ) === null ) {
							return __( 'Expression blanks need an answer written as a valid math expression.', 'ohmylms' ); }
						if ( ! in_array( $spec['form'] ?? 'any', Expression::FORMS, true ) ) {
							return __( 'Unknown answer form.', 'ohmylms' ); }
					} elseif ( ! array_filter( (array) ( $spec['accepted'] ?? array() ), 'strlen' ) ) {
						return __( 'Text blanks need at least one accepted answer.', 'ohmylms' ); }
				}
				return true;
			case 'build-expression':
				if ( isset( $settings['tile_mode'] ) && ( ! in_array( $settings['tile_mode'], array( 'math', 'words', 'letters' ), true ) || count( (array) ( $settings['correct'] ?? array() ) ) > 128 || ( 'math' !== $settings['tile_mode'] && ! empty( $settings['equivalence'] ) ) ) ) {
					return __( 'Choose a supported tile mode with at most 128 answer tiles. Language tiles use exact sequence grading.', 'ohmylms' );
				}
				$correct = array_map( 'strval', (array) ( $settings['correct'] ?? array() ) );
				if ( count( $correct ) < 2 || in_array( '', $correct, true ) ) {
					return __( 'Enter the correct tiles in order (at least two).', 'ohmylms' ); }
				$bank = self::tiles( $settings );
				if ( in_array( '', $bank, true ) ) {
					return __( 'Tiles cannot be empty.', 'ohmylms' ); }
				foreach ( (array) ( $settings['alternatives'] ?? array() ) as $sequence ) {
					$extra = array_count_values( array_map( 'strval', (array) $sequence ) );
					$have  = array_count_values( $bank );
					foreach ( $extra as $tile => $count ) {
						if ( ( $have[ $tile ] ?? 0 ) < $count ) {
							return __( 'Alternative orders can only use tiles from the bank.', 'ohmylms' ); }
					}
				}
				if ( ! in_array( $settings['form'] ?? 'any', Expression::FORMS, true ) ) {
					return __( 'Unknown answer form.', 'ohmylms' ); }
				if ( ! empty( $settings['equivalence'] ) && Expression::parse( implode( '', $correct ) ) === null ) {
					return __( 'With "accept equivalent expressions" the correct tiles must form a valid math expression.', 'ohmylms' ); }
				return true;
			case 'expression':
				if ( Expression::parse( $settings['answer'] ?? '' ) === null ) {
					return __( 'Enter the correct answer as a valid math expression, for example 2(x+3).', 'ohmylms' ); }
				if ( ! in_array( $settings['form'] ?? 'any', Expression::FORMS, true ) ) {
					return __( 'Unknown answer form.', 'ohmylms' ); }
				foreach ( (array) ( $settings['alternatives'] ?? array() ) as $alternative ) {
					if ( Expression::parse( $alternative ) === null ) {
						return __( 'Every other accepted answer must be a valid math expression.', 'ohmylms' ); }
				}
				return true;
		}
		return true;
	}
}
