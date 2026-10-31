<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The printed form of an exam paper: numbering, marks, versions and the model of each question.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\Assessment\Interactive;
use OhMyLMS\Assessment\WordPuzzle;

defined( 'ABSPATH' ) || exit;

/**
 * Pure functions shared by the print page and the learner's Paper View. They mirror paperModel.mjs,
 * paperTypes.mjs and paperVisual.mjs, which the editor's paper canvas uses, so a paper looks the same
 * in the editor, on screen and on paper. tests/fixtures/paper-models.json keeps both equal.
 */
final class PaperModel {
	/** Question types with a printed form. */
	const TYPES = array( 'single-choice', 'multiple-choice', 'true-false', 'short-text', 'text-entry', 'long-text', 'numerical', 'structured', 'statement', 'matching', 'reorder', 'fill-in-the-blank', 'dropdown-blanks', 'multi-blank', 'categorize', 'build-expression', 'expression', 'crossword', 'wordsearch', 'number-line', 'shade-model', 'count-blocks', 'set-clock', 'make-amount', 'fill-level', 'build-chart', 'grid-build' );

	/** Visual question types printed as a static figure. */
	const VISUAL_TYPES = array( 'number-line', 'shade-model', 'count-blocks', 'set-clock', 'make-amount', 'fill-level', 'build-chart', 'grid-build' );

	/** Paper version letters; version A is the paper as laid out in the editor. */
	const VERSIONS = array( 'A', 'B', 'C', 'D' );

	/** Answer space styles. */
	const SPACE_STYLES = array( 'lines', 'grid', 'blank' );

	const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const MARKER  = '/\{([a-z0-9_-]{1,20})\}/i';
	const PLACES  = array(
		'thousands' => 1000,
		'hundreds'  => 100,
		'tens'      => 10,
		'ones'      => 1,
	);

	/**
	 * A number as JavaScript's Number() reads it.
	 *
	 * @param mixed $value    Value.
	 * @param float $fallback Value for anything that is not a finite number.
	 * @return float Number.
	 */
	public static function number( $value, $fallback = 0.0 ) {
		if ( is_bool( $value ) ) {
			return $value ? 1.0 : 0.0;
		}
		if ( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( trim( $value ) ) ) ) {
			$number = (float) $value;
			return is_finite( $number ) ? $number : $fallback;
		}
		return $fallback;
	}

	/**
	 * A value as JavaScript's String() prints it, for the scalars questions hold.
	 *
	 * @param mixed $value Value.
	 * @return string Text.
	 */
	public static function text( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_float( $value ) || is_int( $value ) ) {
			return self::number_text( $value );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * A number without a trailing ".0", as JavaScript prints it.
	 *
	 * @param float|int $value Number.
	 * @return string Text.
	 */
	public static function number_text( $value ) {
		$value = (float) $value;
		if ( floor( $value ) === $value && abs( $value ) < 1e15 ) {
			return (string) (int) $value;
		}
		return (string) $value;
	}

	/**
	 * Letter of a list position: A to Z, then AA, AB and so on.
	 *
	 * @param int $index Zero-based position.
	 * @return string Letter.
	 */
	public static function letter( $index ) {
		if ( $index < 26 ) {
			return self::LETTERS[ $index ];
		}
		return ( self::LETTERS[ (int) floor( $index / 26 ) - 1 ] ?? '' ) . self::LETTERS[ $index % 26 ];
	}

	/**
	 * Marks a question is worth on paper.
	 *
	 * @param array $question Question with settings.
	 * @return float Marks, 0 when ungraded.
	 */
	public static function marks( array $question ) {
		$settings = (array) ( $question['settings'] ?? array() );
		$type     = $settings['type'] ?? '';
		if ( 'structured' === $type ) {
			$total = 0.0;
			foreach ( (array) ( $settings['parts'] ?? array() ) as $part ) {
				$total += self::number( $part['marks'] ?? 0 );
			}
			return $total;
		}
		if ( 'statement' === $type || false === ( $settings['score']['enabled'] ?? null ) ) {
			return 0.0;
		}
		return self::number( $settings['score']['value'] ?? 0 );
	}

	/**
	 * Printed labels for question types in document order; statements are not numbered.
	 *
	 * @param string[] $types    Question types.
	 * @param array    $settings Sanitized paper settings.
	 * @return string[] Labels, empty for statements.
	 */
	public static function labels( array $types, array $settings ) {
		$index  = 0;
		$labels = array();
		foreach ( $types as $type ) {
			$labels[] = 'statement' === $type ? '' : ( 'q' === ( $settings['numbering'] ?? '' ) ? 'Q' : '' ) . ( ++$index );
		}
		return $labels;
	}

	/**
	 * Minutes allowed, from the quiz time limit.
	 *
	 * @param array $limit Time limit: value and type (minutes, hours or days).
	 * @return int Minutes, 0 for no limit.
	 */
	public static function minutes( $limit ) {
		$limit  = (array) $limit;
		$value  = max( 0, self::number( $limit['value'] ?? 0 ) );
		$factor = array(
			'hours' => 60,
			'days'  => 1440,
		)[ $limit['type'] ?? '' ] ?? 1;
		return (int) round( $value * $factor );
	}

	/**
	 * Normalize answer space attributes.
	 *
	 * @param array $attrs Block attributes.
	 * @return array{style: string, lines: int} Answer space.
	 */
	public static function answer_space( array $attrs ) {
		$lines = self::number( $attrs['lines'] ?? 0 );
		return array(
			'style' => in_array( $attrs['style'] ?? '', self::SPACE_STYLES, true ) ? $attrs['style'] : 'lines',
			'lines' => (int) round( min( 30, max( 1, $lines ? $lines : 6 ) ) ),
		);
	}

	/**
	 * Normalize columns section attributes.
	 *
	 * @param array $attrs Block attributes.
	 * @return array{columns: int, flow: string, rule: bool} Columns section.
	 */
	public static function columns( array $attrs ) {
		$columns = self::number( $attrs['columns'] ?? 0 );
		return array(
			'columns' => (int) round( min( 3, max( 1, $columns ? $columns : 2 ) ) ),
			'flow'    => 'grid' === ( $attrs['flow'] ?? '' ) ? 'grid' : 'flow',
			'rule'    => ! empty( $attrs['rule'] ),
		);
	}

	/**
	 * A version letter the paper offers, else A.
	 *
	 * @param mixed $value    Requested version.
	 * @param array $settings Sanitized paper settings.
	 * @return string Version letter.
	 */
	public static function version( $value, array $settings ) {
		$index = array_search( strtoupper( (string) $value ), self::VERSIONS, true );
		return false !== $index && $index > 0 && $index < (int) $settings['versions'] ? self::VERSIONS[ $index ] : 'A';
	}

	/**
	 * Print items in a paper version. Versions B to D shuffle each run of consecutive questions in
	 * place; headers, sections, statements, wide items and other blocks stay where the author put them.
	 *
	 * @param array  $items   Print items.
	 * @param string $version Version letter.
	 * @return array Items in printed order.
	 */
	public static function version_items( array $items, $version ) {
		if ( 'A' === $version ) {
			return $items;
		}
		$run   = 0;
		$visit = static function ( array $list ) use ( &$visit, &$run, $version ) {
			$out   = array();
			$group = array();
			$flush = static function () use ( &$out, &$group, &$run, $version ) {
				if ( count( $group ) > 1 ) {
					$ids = array();
					foreach ( $group as $item ) {
						$ids[] = self::text( $item['question']['id'] ?? '' );
					}
					foreach ( PrintPaper::jumble( $ids, $version . ':' . ( $run++ ), true ) as $index ) {
						$out[] = $group[ $index ];
					}
				} else {
					$out = array_merge( $out, $group );
				}
				$group = array();
			};
			foreach ( $list as $item ) {
				if ( 'question' === ( $item['kind'] ?? '' ) && 'statement' !== ( $item['question']['settings']['type'] ?? '' ) && empty( $item['span'] ) ) {
					$group[] = $item;
					continue;
				}
				$flush();
				if ( in_array( $item['kind'] ?? '', array( 'paper', 'layout' ), true ) && ! empty( $item['items'] ) ) {
					$item['items'] = $visit( $item['items'] );
				}
				$out[] = $item;
			}
			$flush();
			return $out;
		};
		return $visit( $items );
	}

	/**
	 * Number the questions and total the sections, in document order across nested blocks. Each
	 * question item gains `label` and each section `total`.
	 *
	 * @param array $items    Print items.
	 * @param array $settings Sanitized paper settings.
	 * @return array{items: array, total: float, questions: array} Annotated items, the paper's
	 * total marks and the question items in printed order.
	 */
	public static function annotate( array $items, array $settings ) {
		$types   = array();
		$entries = array();
		$collect = static function ( array $list ) use ( &$collect, &$types, &$entries ) {
			foreach ( $list as $item ) {
				$kind = $item['kind'] ?? '';
				if ( 'question' === $kind ) {
					$types[]   = (string) ( $item['question']['settings']['type'] ?? '' );
					$entries[] = array(
						'kind'  => 'question',
						'marks' => self::marks( (array) $item['question'] ),
					);
				} elseif ( 'layout' === $kind || 'paper' === $kind ) {
					if ( 'paper' === $kind && PaperBlocks::SECTION === ( $item['name'] ?? '' ) ) {
						$entries[] = array( 'kind' => 'section' );
					}
					$collect( (array) ( $item['items'] ?? array() ) );
				}
			}
		};
		$collect( $items );
		$labels  = self::labels( $types, $settings );
		$totals  = array();
		$current = null;
		$total   = 0.0;
		foreach ( $entries as $entry ) {
			if ( 'section' === $entry['kind'] ) {
				$current            = count( $totals );
				$totals[ $current ] = 0.0;
			} else {
				$total += $entry['marks'];
				if ( null !== $current ) {
					$totals[ $current ] += $entry['marks'];
				}
			}
		}
		$question  = 0;
		$section   = 0;
		$questions = array();
		$apply     = static function ( array $list ) use ( &$apply, &$question, &$section, &$questions, $labels, $totals ) {
			foreach ( $list as $index => $item ) {
				$kind = $item['kind'] ?? '';
				if ( 'question' === $kind ) {
					$item['label'] = $labels[ $question++ ];
					$questions[]   = $item;
				} elseif ( 'layout' === $kind || 'paper' === $kind ) {
					if ( 'paper' === $kind && PaperBlocks::SECTION === ( $item['name'] ?? '' ) ) {
						$item['total'] = $totals[ $section++ ] ?? 0.0;
					}
					$item['items'] = $apply( (array) ( $item['items'] ?? array() ) );
				}
				$list[ $index ] = $item;
			}
			return $list;
		};
		$items = $apply( $items );
		return array(
			'items'     => $items,
			'total'     => $total,
			'questions' => $questions,
		);
	}

	/**
	 * Statement HTML of a question as the editor's prompt field holds it.
	 *
	 * @param array $question Question: name, description and settings.
	 * @return string Statement HTML.
	 */
	public static function prompt( array $question ) {
		$settings = (array) ( $question['settings'] ?? array() );
		$type     = $settings['type'] ?? ( $question['type'] ?? '' );
		$escape   = static function ( $text ) {
			return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), (string) $text );
		};
		$name     = (string) ( $question['name'] ?? '' );
		$named    = '' !== $name && 'Untitled' !== $name ? '<p>' . $escape( $name ) . '</p>' : '';
		$key      = 'passage' === $type ? 'passage' : ( 'dropdown-blanks' === $type || ( 'multi-blank' === $type && 'table' !== ( $settings['layout'] ?? '' ) ) ? 'text' : '' );
		if ( '' !== $key ) {
			$statement    = self::text( $settings[ $key ] ?? '' );
			$introduction = ! empty( $settings['question_code'] ) ? (string) ( $question['description'] ?? '' ) : $named . ( $question['description'] ?? '' );
			return $introduction . ( '' !== $statement ? '<p>' . str_replace( "\n", '<br>', $escape( $statement ) ) . '</p>' : '' );
		}
		if ( ! empty( $settings['question_code'] ) ) {
			return (string) ( $question['description'] ?? '' );
		}
		return $named . ( $question['description'] ?? '' );
	}

	/**
	 * Options in their saved order.
	 *
	 * @param array $question Question.
	 * @return array Options.
	 */
	private static function rows( array $question ) {
		$rows = array_values( (array) ( $question['questions'] ?? ( $question['options'] ?? array() ) ) );
		usort(
			$rows,
			static function ( $a, $b ) {
				return self::number( $a['order_number'] ?? 0 ) <=> self::number( $b['order_number'] ?? 0 );
			}
		);
		return $rows;
	}

	/**
	 * Non-empty answers from several values, each a value or a list.
	 *
	 * @param array $values Values.
	 * @return string[] Answers.
	 */
	private static function accepted( array $values ) {
		$out = array();
		foreach ( $values as $value ) {
			foreach ( is_array( $value ) ? $value : array( $value ) as $answer ) {
				$answer = trim( self::text( $answer ) );
				if ( '' !== $answer ) {
					$out[] = $answer;
				}
			}
		}
		return $out;
	}

	/**
	 * The printed form of one structured part.
	 *
	 * @param array $part     Saved part.
	 * @param array $settings Sanitized paper settings.
	 * @param bool  $answers  Whether to include the answer key.
	 * @return array Part model.
	 */
	private static function part( array $part, array $settings, $answers ) {
		$marks   = self::number( $part['marks'] ?? 0 );
		$kind    = $part['kind'] ?? '';
		$written = ! in_array( $kind, array( 'numerical', 'text' ), true );
		$model   = array(
			'id'     => self::text( $part['id'] ?? '' ),
			// Imported papers store labels with brackets, such as "(a)"; the paper adds its own.
			'label'  => (string) preg_replace( '/^\((.*)\)$/s', '$1', trim( self::text( $part['label'] ?? '' ) ) ),
			'prompt' => self::text( $part['prompt'] ?? '' ),
			'marks'  => $marks,
			'layout' => $written ? 'lines' : 'answer-line',
			'lines'  => $written ? max( 2, $marks * (int) $settings['lines_per_mark'] ) : 0,
			'unit'   => 'numerical' === $kind ? self::text( $part['unit'] ?? '' ) : '',
		);
		if ( $answers ) {
			$model['answers'] = self::accepted( array( $part['answer'] ?? array(), $part['answers'] ?? array(), $part['accepted'] ?? array() ) );
			$model['rubric']  = self::text( $part['rubric'] ?? '' );
		}
		return $model;
	}

	/**
	 * The printed form of a question. Answer fields are present only when $answers is true, so a
	 * student paper never carries the answer key, even hidden.
	 *
	 * @param array  $question Question: id, settings, options and the public flag of a learner view.
	 * @param array  $settings Sanitized paper settings.
	 * @param bool   $answers  Whether to include the answer key.
	 * @param string $prompt   Statement HTML.
	 * @param string $version  Paper version letter; B to D shuffle choice options.
	 * @return array Paper question model.
	 */
	public static function question( array $question, array $settings, $answers = false, $prompt = '', $version = 'A' ) {
		$type  = (string) ( $question['settings']['type'] ?? '' );
		$model = array(
			'type'   => $type,
			// A fill-in-the-blank statement holds its answers in braces: print blanks instead.
			'prompt' => 'fill-in-the-blank' === $type && ! $answers ? (string) preg_replace( '/\{[^{}<>]+\}/', '__________', $prompt ) : $prompt,
			'marks'  => self::marks( $question ),
			'layout' => 'unsupported',
		);
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return $model;
		}
		$seed  = PrintPaper::seed( self::text( $question['id'] ?? '' ), $version );
		$typed = self::typed( $question, $model, $answers, $seed );
		if ( $typed ) {
			return $typed;
		}
		$rows = self::rows( $question );
		$own  = (array) ( $question['settings'] ?? array() );
		if ( in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false' ), true ) ) {
			if ( 'A' !== $version && 'true-false' !== $type ) {
				// Other versions reorder the options; true or false keeps its order.
				$texts    = array_map(
					static function ( $row ) {
						return self::text( $row['answer'] ?? '' );
					},
					$rows
				);
				$shuffled = array();
				foreach ( PrintPaper::jumble( $texts, $seed, true ) as $index ) {
					$shuffled[] = $rows[ $index ];
				}
				$rows = $shuffled;
			}
			$model['layout']   = 'choice';
			$model['multiple'] = 'multiple-choice' === $type;
			$model['options']  = array();
			foreach ( $rows as $index => $row ) {
				$option = array(
					'letter' => $index < 26 ? self::LETTERS[ $index ] : (string) ( $index + 1 ),
					'text'   => self::text( $row['answer'] ?? '' ),
					'image'  => self::text( $row['image_url'] ?? '' ),
				);
				if ( $answers ) {
					$option['correct'] = ! in_array( $row['is_correct'] ?? null, array( false, 0, '0', '' ), true );
				}
				$model['options'][] = $option;
			}
		} elseif ( 'long-text' === $type ) {
			$model['layout'] = 'lines';
			$model['lines']  = max( 3, $model['marks'] * (int) $settings['lines_per_mark'] );
		} elseif ( 'numerical' === $type ) {
			$model['layout'] = 'answer-line';
			$model['unit']   = self::text( $own['unit'] ?? '' );
			if ( $answers ) {
				$tolerance        = self::number( $own['tolerance'] ?? 0 );
				$model['answers'] = array_map(
					static function ( $answer ) use ( $tolerance ) {
						return $tolerance ? $answer . ' ± ' . self::number_text( $tolerance ) : $answer;
					},
					self::accepted( array( $own['answer'] ?? array() ) )
				);
			}
		} elseif ( in_array( $type, array( 'short-text', 'text-entry' ), true ) ) {
			$model['layout'] = 'answer-line';
			if ( $answers ) {
				// Type-the-answer questions keep accepted answers in their settings.
				$model['answers'] = self::accepted( array( array_column( $rows, 'answer' ), $own['answers'] ?? array(), $own['accepted'] ?? array() ) );
			}
		} elseif ( 'structured' === $type ) {
			$model['layout'] = 'parts';
			$model['parts']  = array();
			foreach ( (array) ( $own['parts'] ?? array() ) as $part ) {
				$model['parts'][] = self::part( (array) $part, $settings, $answers );
			}
		} elseif ( 'statement' === $type ) {
			$model['layout'] = 'text';
		}
		return $model;
	}

	/**
	 * The learner view of an interactive question's settings, without per-delivery shuffles.
	 *
	 * @param string $type     Question type.
	 * @param array  $settings Full or already public settings.
	 * @param bool   $is_public Whether the settings are already the learner view.
	 * @return array Learner view.
	 */
	public static function learner_settings( $type, array $settings, $is_public ) {
		if ( $is_public ) {
			return $settings;
		}
		if ( in_array( $type, WordPuzzle::TYPES, true ) ) {
			return isset( $settings['entries'], $settings['size'] ) && is_array( $settings['entries'] ) ? array_merge( $settings, WordPuzzle::public_view( $type, $settings ) ) : $settings;
		}
		if ( 'build-expression' === $type ) {
			$settings['tiles'] = array_map( array( __CLASS__, 'text' ), array_merge( array_values( (array) ( $settings['correct'] ?? array() ) ), array_values( (array) ( $settings['distractors'] ?? array() ) ) ) );
			return $settings;
		}
		if ( 'multi-blank' === $type ) {
			$fields = array();
			foreach ( Interactive::blank_ids( $settings ) as $id ) {
				$spec          = (array) ( $settings['blanks'][ $id ] ?? array() );
				$fields[ $id ] = array(
					'kind' => in_array( $spec['kind'] ?? '', array( 'numerical', 'expression' ), true ) ? $spec['kind'] : 'text',
					'unit' => self::text( $spec['unit'] ?? '' ),
				);
			}
			$settings['fields'] = $fields;
		}
		return $settings;
	}

	/**
	 * Split text at blank markers such as {a} or {1}.
	 *
	 * @param string $text Text with markers.
	 * @return array Parts: `text` or `id`.
	 */
	public static function split_markers( $text ) {
		$parts = array();
		foreach ( preg_split( self::MARKER, (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE ) as $index => $part ) {
			if ( 1 === $index % 2 ) {
				$parts[] = array( 'id' => $part );
			} elseif ( '' !== $part ) {
				$parts[] = array( 'text' => $part );
			}
		}
		return $parts;
	}

	/**
	 * Replace blank markers in statement HTML with numbered answer blanks.
	 *
	 * @param string   $html  Statement HTML.
	 * @param callable $label Printed label of a blank ID.
	 * @param array    $units Unit printed after each blank.
	 * @return string Statement HTML with blanks.
	 */
	private static function blank_markers( $html, callable $label, array $units = array() ) {
		$escape = static function ( $text ) {
			return str_replace( array( '&', '<', '>', '"' ), array( '&amp;', '&lt;', '&gt;', '&quot;' ), (string) $text );
		};
		return (string) preg_replace_callback(
			self::MARKER,
			static function ( $match ) use ( $label, $units, $escape ) {
				$unit = $units[ $match[1] ] ?? '';
				return '<span class="ohmylms-print-blank">(' . $escape( $label( $match[1] ) ) . ')</span>' . ( '' !== $unit ? ' ' . $escape( $unit ) : '' );
			},
			(string) $html
		);
	}

	/**
	 * Readable answer of one multi-blank field.
	 *
	 * @param array $spec Private blank settings.
	 * @return string Answer.
	 */
	private static function blank_answer( array $spec ) {
		if ( 'numerical' === ( $spec['kind'] ?? '' ) ) {
			return self::text( $spec['answers'][0] ?? ( $spec['answer'] ?? '' ) );
		}
		if ( 'expression' === ( $spec['kind'] ?? '' ) ) {
			return self::text( $spec['answer'] ?? '' );
		}
		return self::text( $spec['accepted'][0] ?? '' );
	}

	/**
	 * Texts of a list, in order.
	 *
	 * @param array  $list Items.
	 * @param string $key  Field holding the text, or empty for the items themselves.
	 * @return string[] Texts.
	 */
	private static function texts( array $list, $key = '' ) {
		$out = array();
		foreach ( $list as $item ) {
			$out[] = self::text( '' === $key ? $item : ( $item[ $key ] ?? '' ) );
		}
		return $out;
	}

	/**
	 * Printed form of matching, ordering, blank, sorting, tile, expression, word puzzle and visual
	 * questions, built from the learner view of the question.
	 *
	 * @param array  $question Question: settings, options and public flag.
	 * @param array  $model    Base paper model (type, prompt, marks).
	 * @param bool   $show     Whether to include the answer key.
	 * @param string $seed     Jumble seed of the question in this paper version.
	 * @return array|null Paper model, or null for other types.
	 */
	private static function typed( array $question, array $model, $show, $seed ) {
		$type      = $model['type'];
		$is_public = ! empty( $question['public'] );
		$settings  = self::learner_settings( $type, (array) ( $question['settings'] ?? array() ), $is_public );
		$rows      = self::rows( $question );
		$answers   = $show && ! $is_public;
		switch ( $type ) {
			case 'matching':
				$left = array();
				foreach ( $rows as $index => $row ) {
					$left[] = array(
						'number' => $index + 1,
						'text'   => self::text( $row['answer'] ?? '' ),
						'image'  => self::text( $row['image_url'] ?? '' ),
					);
				}
				// Labels in pair order; the student print page receives them already jumbled.
				if ( $is_public ) {
					$labels = self::texts( (array) ( $settings['match_right'] ?? array() ) );
					$order  = array_keys( $labels );
				} else {
					$labels = array();
					foreach ( $rows as $row ) {
						$labels[] = self::text( $row['matching_data']['label'] ?? '' );
					}
					$order = PrintPaper::jumble( $labels, $seed, true );
				}
				$model['layout'] = 'matching';
				$model['left']   = $left;
				$model['right']  = array();
				foreach ( $order as $index => $from ) {
					$model['right'][] = array(
						'letter' => self::letter( $index ),
						'text'   => $labels[ $from ],
					);
				}
				if ( $answers ) {
					$model['key'] = array();
					foreach ( $left as $index => $item ) {
						$model['key'][] = $item['number'] . ' → ' . self::position_letter( $order, $index );
					}
				}
				return $model;
			case 'reorder':
				$texts = self::texts( $rows, 'answer' );
				$order = $is_public ? array_keys( $texts ) : PrintPaper::jumble( $texts, $seed, true );
				$model['layout'] = 'reorder';
				$model['items']  = array();
				foreach ( $order as $index => $from ) {
					$model['items'][] = array(
						'letter' => self::letter( $index ),
						'text'   => $texts[ $from ],
						'image'  => self::text( $rows[ $from ]['image_url'] ?? '' ),
					);
				}
				if ( $answers ) {
					$letters = array();
					foreach ( array_keys( $texts ) as $index ) {
						$letters[] = self::position_letter( $order, $index );
					}
					$model['key'] = array( implode( ', ', $letters ) );
				}
				return $model;
			case 'fill-in-the-blank':
				preg_match_all( '/\{([^{}<>]+)\}/', (string) $model['prompt'], $found );
				$model['layout'] = 'blanks';
				if ( $answers ) {
					$model['prompt'] = (string) preg_replace( '/\{([^{}<>]+)\}/', '<span class="ohmylms-print-blank is-answered">$1</span>', (string) $model['prompt'] );
					$model['key']    = $found[1];
				}
				return $model;
			case 'dropdown-blanks':
				$slots   = array();
				$numbers = array();
				foreach ( array_values( (array) ( $settings['slots'] ?? array() ) ) as $index => $slot ) {
					$slot     = (array) $slot;
					$id       = self::text( $slot['id'] ?? '' );
					$choices  = self::texts( (array) ( $slot['choices'] ?? array() ) );
					$printed  = array();
					foreach ( PrintPaper::jumble( $choices, $seed . '|' . $id ) as $from ) {
						$printed[] = $choices[ $from ];
					}
					$entry = array(
						'id'       => $id,
						'number'   => $index + 1,
						'multiple' => ! empty( $slot['multiple'] ),
						'choices'  => $printed,
					);
					if ( $answers ) {
						$entry['answers'] = self::texts( is_array( $slot['answers'] ?? null ) ? $slot['answers'] : ( ! empty( $slot['answer'] ) ? array( $slot['answer'] ) : array() ) );
					}
					$slots[]                = $entry;
					$numbers[ $entry['id'] ] = (string) $entry['number'];
				}
				$model['layout'] = 'dropdown';
				$model['prompt'] = self::blank_markers(
					$model['prompt'],
					static function ( $id ) use ( $numbers ) {
						return $numbers[ $id ] ?? $id;
					}
				);
				$model['slots']  = $slots;
				if ( $answers ) {
					$model['key'] = array();
					foreach ( $slots as $slot ) {
						$model['key'][] = '(' . $slot['number'] . ') ' . implode( ', ', $slot['answers'] );
					}
				}
				return $model;
			case 'multi-blank':
				$fields = (array) ( $settings['fields'] ?? array() );
				$units  = array();
				foreach ( $fields as $id => $field ) {
					$units[ (string) $id ] = self::text( $field['unit'] ?? '' );
				}
				$model['layout'] = 'multi-blank';
				$model['table']  = null;
				if ( 'table' === ( $settings['layout'] ?? '' ) ) {
					$table = array(
						'columns' => self::texts( (array) ( $settings['columns'] ?? array() ) ),
						'rows'    => array(),
					);
					foreach ( (array) ( $settings['rows'] ?? array() ) as $row ) {
						$cells = array();
						foreach ( (array) $row as $cell ) {
							$cells[] = self::split_markers( self::text( $cell ) );
						}
						$table['rows'][] = $cells;
					}
					$model['table'] = $table;
				}
				$model['units'] = $units;
				if ( 'table' !== ( $settings['layout'] ?? '' ) ) {
					$model['prompt'] = self::blank_markers(
						$model['prompt'],
						static function ( $id ) {
							return $id;
						},
						$units
					);
				}
				if ( $answers ) {
					$model['key'] = array();
					foreach ( array_keys( $fields ) as $id ) {
						$model['key'][] = '(' . $id . ') ' . self::blank_answer( (array) ( $settings['blanks'][ $id ] ?? array() ) );
					}
				}
				return $model;
			case 'categorize':
				$items   = array_values( (array) ( $settings['items'] ?? array() ) );
				$printed = array();
				foreach ( PrintPaper::jumble( self::texts( $items, 'text' ), $seed ) as $index => $from ) {
					$printed[] = array(
						'id'     => self::text( $items[ $from ]['id'] ?? '' ),
						'letter' => self::letter( $index ),
						'text'   => self::text( $items[ $from ]['text'] ?? '' ),
						'image'  => self::text( $items[ $from ]['image_url'] ?? '' ),
					);
				}
				$groups = array();
				foreach ( (array) ( $settings['buckets'] ?? array() ) as $bucket ) {
					$groups[] = array(
						'id'    => self::text( $bucket['id'] ?? '' ),
						'label' => self::text( $bucket['label'] ?? '' ),
					);
				}
				$model['layout'] = 'categorize';
				$model['items']  = $printed;
				$model['groups'] = $groups;
				if ( $answers ) {
					$model['key'] = array();
					foreach ( $groups as $group ) {
						$letters = array();
						foreach ( $printed as $item ) {
							if ( ( $settings['key'][ $item['id'] ] ?? null ) === $group['id'] ) {
								$letters[] = $item['letter'];
							}
						}
						$model['key'][] = $group['label'] . ': ' . ( $letters ? implode( ', ', $letters ) : '—' );
					}
				}
				return $model;
			case 'build-expression':
				$tiles           = self::texts( (array) ( $settings['tiles'] ?? array() ) );
				$model['layout'] = 'tiles';
				$model['tiles']  = array();
				foreach ( PrintPaper::jumble( $tiles, $seed ) as $from ) {
					$model['tiles'][] = $tiles[ $from ];
				}
				if ( $answers ) {
					$model['key'] = array( implode( ' ', self::texts( (array) ( $settings['correct'] ?? array() ) ) ) );
				}
				return $model;
			case 'expression':
				$model['layout'] = 'answer-line';
				if ( $answers ) {
					$model['answers'] = array( '$' . self::text( $settings['answer'] ?? '' ) . '$' );
				}
				return $model;
			case 'crossword':
				$numbers = array();
				foreach ( (array) ( $settings['clues'] ?? array() ) as $clue ) {
					$numbers[ 'r' . self::text( $clue['row'] ?? '' ) . 'c' . self::text( $clue['col'] ?? '' ) ] = $clue['number'] ?? '';
				}
				$letters = array();
				if ( $answers ) {
					foreach ( (array) ( $settings['entries'] ?? array() ) as $entry ) {
						$word = WordPuzzle::letters( self::text( $entry['answer'] ?? '' ) );
						foreach ( WordPuzzle::cells( (array) $entry ) as $index => $cell ) {
							$letters[ $cell ] = $word[ $index ];
						}
					}
				}
				$clues = static function ( $direction ) use ( $settings ) {
					$list = array_values(
						array_filter(
							(array) ( $settings['clues'] ?? array() ),
							static function ( $clue ) use ( $direction ) {
								return ( $clue['direction'] ?? '' ) === $direction;
							}
						)
					);
					usort(
						$list,
						static function ( $a, $b ) {
							return self::number( $a['number'] ?? 0 ) <=> self::number( $b['number'] ?? 0 );
						}
					);
					return array_map(
						static function ( $clue ) {
							return array(
								'number' => $clue['number'] ?? '',
								'text'   => self::text( $clue['clue'] ?? '' ),
								'length' => $clue['length'] ?? '',
							);
						},
						$list
					);
				};
				$model['layout']  = 'crossword';
				$model['size']    = (int) self::number( $settings['size'] ?? 0 );
				$model['cells']   = array_values( array_unique( self::texts( (array) ( $settings['cells'] ?? array() ) ) ) );
				$model['numbers'] = $numbers;
				$model['letters'] = $letters;
				$model['across']  = $clues( 'across' );
				$model['down']    = $clues( 'down' );
				return $model;
			case 'wordsearch':
				$marked = array();
				if ( $answers ) {
					foreach ( (array) ( $settings['entries'] ?? array() ) as $entry ) {
						$marked = array_merge( $marked, WordPuzzle::cells( (array) $entry ) );
					}
				}
				$model['layout'] = 'wordsearch';
				$model['grid']   = array();
				foreach ( (array) ( $settings['grid'] ?? array() ) as $row ) {
					$model['grid'][] = self::texts( (array) $row );
				}
				$model['marked'] = array_values( array_unique( $marked ) );
				$model['words']  = self::texts( (array) ( $settings['clues'] ?? array() ), 'clue' );
				return $model;
			default:
				if ( in_array( $type, self::VISUAL_TYPES, true ) ) {
					$model['layout'] = 'visual';
					$model['figure'] = self::figure( $type, $settings, $answers );
					return $model;
				}
				return null;
		}
	}

	/**
	 * Letter printed for the item that came from a position.
	 *
	 * @param int[] $order Printed order of input positions.
	 * @param int   $index Input position.
	 * @return string Letter.
	 */
	private static function position_letter( array $order, $index ) {
		$at = array_search( $index, $order, true );
		return false === $at ? '' : self::letter( $at );
	}

	/**
	 * Values at each tick of a scale, at most `limit` ticks, with every nth labelled.
	 *
	 * @param float $min   Start.
	 * @param float $max   End.
	 * @param float $step  Tick spacing.
	 * @param int   $limit Most ticks to draw.
	 * @return array{values: float[], labelEvery: int} Ticks.
	 */
	public static function scale_ticks( $min, $max, $step, $limit = 101 ) {
		$low     = min( $min, $max );
		$high    = max( $min, $max );
		$spacing = max( $step, ( $high - $low ) / ( $limit - 1 ), 1e-6 );
		// Keep the spacing a whole multiple of the step, so ticks stay on step values.
		$spacing = $step * ceil( $spacing / $step - 1e-9 );
		$values  = array();
		for ( $value = $low; $value <= $high + $spacing / 1e6; $value += $spacing ) {
			$values[] = round( $value, 6 );
		}
		return array(
			'values'     => $values,
			'labelEvery' => max( 1, (int) ceil( count( $values ) / 11 ) ),
		);
	}

	/**
	 * A whole number within bounds.
	 *
	 * @param mixed $value    Value.
	 * @param float $fallback Value when not a number.
	 * @param float $min      Lowest.
	 * @param float $max      Highest.
	 * @return int Number.
	 */
	private static function whole( $value, $fallback, $min, $max ) {
		$number = self::number( $value, (float) $fallback );
		return (int) max( $min, min( $max, $number < 0 ? ceil( $number ) : floor( $number ) ) );
	}

	/**
	 * A setting as a number, treating an empty string as missing.
	 *
	 * @param mixed $value    Value.
	 * @param float $fallback Fallback.
	 * @return float Number.
	 */
	private static function num( $value, $fallback = 0.0 ) {
		return '' === $value || null === $value ? $fallback : self::number( $value, $fallback );
	}

	/**
	 * The printed figure of a visual question. Reads only the learner view's fields; the key adds
	 * the private target.
	 *
	 * @param string $type    Question type.
	 * @param array  $s       Settings: full (key) or the learner view.
	 * @param bool   $answers Whether to add the answer key.
	 * @return array|null Figure model.
	 */
	public static function figure( $type, array $s, $answers = false ) {
		switch ( $type ) {
			case 'number-line':
			case 'fill-level':
				$jug    = 'fill-level' === $type;
				$step   = max( 1e-6, self::num( $s['step'] ?? null, $jug ? 50 : 1 ) );
				$figure = array(
					'figure' => $type,
					'min'    => self::num( $s['min'] ?? null, 0 ),
					'max'    => self::num( $s['max'] ?? null, $jug ? 1000 : 10 ),
					'step'   => $step,
					'unit'   => self::text( $s['unit'] ?? ( $jug ? 'ml' : '' ) ),
				);
				$figure['ticks'] = self::scale_ticks( $figure['min'], $figure['max'], $step );
				if ( $answers ) {
					$figure['target'] = self::num( $s['target'] ?? null, NAN );
					$figure['key']    = array( self::text( $s['target'] ?? '' ) . ( '' !== $figure['unit'] ? ' ' . $figure['unit'] : '' ) );
				}
				return $figure;
			case 'shade-model':
				$parts  = self::whole( $s['parts'] ?? null, 8, 1, 100 );
				$figure = array(
					'figure' => $type,
					'shape'  => in_array( $s['shape'] ?? '', array( 'bar', 'grid', 'circle' ), true ) ? $s['shape'] : 'bar',
					'parts'  => $parts,
					'cols'   => self::whole( $s['cols'] ?? null, min( $parts, 10 ), 1, $parts ),
				);
				if ( $answers ) {
					$figure['shaded'] = self::whole( $s['answer'] ?? null, 0, 0, $parts );
					$figure['key']    = array( $figure['shaded'] . ' / ' . $parts );
				}
				return $figure;
			case 'count-blocks':
				$wanted = isset( $s['places'] ) ? (array) $s['places'] : array( 'hundreds', 'tens', 'ones' );
				$chosen = array_values( array_intersect( array_keys( self::PLACES ), $wanted ) );
				$figure = array(
					'figure' => $type,
					'places' => $chosen ? $chosen : array( 'hundreds', 'tens', 'ones' ),
				);
				if ( $answers ) {
					$rest   = max( 0, (int) self::num( $s['target'] ?? null ) );
					$counts = array();
					foreach ( $figure['places'] as $place ) {
						$counts[ $place ] = intdiv( $rest, self::PLACES[ $place ] );
						$rest            -= $counts[ $place ] * self::PLACES[ $place ];
					}
					$figure['counts'] = $counts;
					$figure['key']    = array( self::text( $s['target'] ?? '' ) );
				}
				return $figure;
			case 'set-clock':
				$figure = array(
					'figure'  => $type,
					'digital' => ! empty( $s['show_digital'] ),
				);
				if ( $answers ) {
					$figure['hour']   = self::whole( $s['hour'] ?? null, 12, 0, 23 );
					$figure['minute'] = self::whole( $s['minute'] ?? null, 0, 0, 59 );
					$figure['key']    = array( $figure['hour'] . ':' . str_pad( (string) $figure['minute'], 2, '0', STR_PAD_LEFT ) );
				}
				return $figure;
			case 'make-amount':
				$denominations = array();
				foreach ( (array) ( $s['denominations'] ?? array() ) as $value ) {
					$value = (int) self::num( $value );
					if ( $value > 0 && ! in_array( $value, $denominations, true ) ) {
						$denominations[] = $value;
					}
				}
				rsort( $denominations );
				$figure = array(
					'figure'        => $type,
					'denominations' => $denominations,
					'symbol'        => self::text( $s['symbol'] ?? '₮' ),
				);
				if ( $answers ) {
					$rest   = (int) self::num( $s['target'] ?? null );
					$counts = array();
					foreach ( $denominations as $value ) {
						$count = intdiv( $rest, $value );
						if ( $count > 0 ) {
							$counts[ $value ] = $count;
							$rest            -= $count * $value;
						}
					}
					$figure['counts'] = 0 === $rest ? $counts : array();
					$figure['key']    = array( self::text( $s['target'] ?? '' ) . ' ' . $figure['symbol'] );
				}
				return $figure;
			case 'build-chart':
				$categories = array();
				foreach ( (array) ( $s['categories'] ?? array() ) as $category ) {
					$categories[] = array(
						'id'    => (string) preg_replace( '/[^a-z0-9_-]/i', '', self::text( $category['id'] ?? '' ) ),
						'label' => self::text( $category['label'] ?? '' ),
					);
				}
				$figure          = array(
					'figure'     => $type,
					'categories' => $categories,
					'max'        => max( 1e-6, self::num( $s['max'] ?? null, 10 ) ),
					'step'       => max( 1e-6, self::num( $s['step'] ?? null, 1 ) ),
					'unit'       => self::text( $s['unit'] ?? '' ),
				);
				$figure['ticks'] = self::scale_ticks( 0, $figure['max'], $figure['step'], 21 );
				// The author may show the data the learner turns into bars; the learner view has it too.
				if ( isset( $s['table'] ) && is_array( $s['table'] ) ) {
					$figure['table'] = array();
					foreach ( $s['table'] as $row ) {
						$figure['table'][] = array(
							'label' => self::text( $row['label'] ?? '' ),
							'value' => self::num( $row['value'] ?? null ),
						);
					}
				} elseif ( ! empty( $s['show_table'] ) ) {
					$figure['table'] = array();
					foreach ( $categories as $category ) {
						$figure['table'][] = array(
							'label' => $category['label'],
							'value' => self::num( $s['values'][ $category['id'] ] ?? null ),
						);
					}
				}
				if ( $answers ) {
					$figure['values'] = array();
					$figure['key']    = array();
					foreach ( $categories as $category ) {
						$figure['values'][ $category['id'] ] = self::num( $s['values'][ $category['id'] ] ?? null );
						$figure['key'][]                     = $category['label'] . ' = ' . self::number_text( $figure['values'][ $category['id'] ] );
					}
				}
				return $figure;
			case 'grid-build':
				$figure = array(
					'figure'   => $type,
					'rows'     => self::whole( $s['rows'] ?? null, 6, 1, 20 ),
					'cols'     => self::whole( $s['cols'] ?? null, 6, 1, 20 ),
					'measures' => ! empty( $s['show_measures'] ),
				);
				if ( $answers ) {
					// The shape is the candidate's; the key states what it must satisfy.
					$c                     = (array) ( $s['constraints'] ?? array() );
					$figure['constraints'] = array(
						'area'      => $c['area'] ?? null,
						'perimeter' => $c['perimeter'] ?? null,
						'rectangle' => ! empty( $c['rectangle'] ),
						'connected' => ! empty( $c['connected'] ),
					);
				}
				return $figure;
		}
		return null;
	}
}
