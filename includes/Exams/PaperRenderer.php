<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Server-rendered exam paper: the print page and the learner's Paper View.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

defined( 'ABSPATH' ) || exit;

/**
 * Renders print items (PrintPaper::items()) as the paper markup that assets/css/paper.css styles. The
 * markup matches what the editor's paper canvas shows (PaperQuestion.jsx, PaperTypedViews.jsx,
 * PaperVisualViews.jsx and PaperBlockViews.jsx), so the printed paper is what the author laid out.
 */
final class PaperRenderer {
	/** Answer key colour on figures, as in the editor. */
	const KEY = '#b32d2e';

	/**
	 * The paper document: core blocks, paper blocks and questions in document order.
	 *
	 * @param array $items   Print items.
	 * @param array $context {
	 *     Rendering options.
	 *
	 *     @type string        $title      Exam title for the header.
	 *     @type array         $time_limit Quiz time limit (value, type).
	 *     @type array         $settings   Sanitized paper settings.
	 *     @type string        $version    Paper version letter.
	 *     @type bool          $answers    Whether to print the answer key.
	 *     @type bool          $continuous Leave out page breaks (the paper on screen has no pages).
	 *     @type callable|null $slot       Receives a question item and its label; returns the HTML that
	 *                                     takes the question's place, or null to leave it out.
	 * }
	 * @return string HTML.
	 */
	public static function document( array $items, array $context ) {
		$context  += array(
			'title'      => '',
			'time_limit' => array(),
			'version'    => 'A',
			'answers'    => false,
			'continuous' => false,
			'slot'       => null,
		);
		$annotated = PaperModel::annotate( $items, $context['settings'] );
		$context  += array( 'total' => $annotated['total'] );
		return self::items( $annotated['items'], $context );
	}

	/**
	 * Printed items, each in a block wrapper that keeps it on one page and may span columns.
	 *
	 * @param array $items   Annotated print items.
	 * @param array $context Rendering options.
	 * @return string HTML.
	 */
	private static function items( array $items, array $context ) {
		$html = '';
		foreach ( $items as $item ) {
			$inner = self::item( $item, $context );
			if ( null === $inner ) {
				continue;
			}
			$html .= '<div class="ohmylms-print-block' . ( ! empty( $item['span'] ) ? ' is-ohmylms-paper-span' : '' ) . '">' . $inner . '</div>';
		}
		return $html;
	}

	/**
	 * Saved text of a paper block with its defaults: bold, italic and line breaks only.
	 *
	 * @param array  $values    Attributes with defaults.
	 * @param string $key       Attribute.
	 * @param string $tag       Element.
	 * @param string $class_name Class.
	 * @return string HTML, empty when the text is empty.
	 */
	private static function text( array $values, $key, $tag, $class_name ) {
		$value = isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? (string) $values[ $key ] : '';
		if ( '' === $value ) {
			return '';
		}
		$value = wp_kses(
			$value,
			array(
				'strong' => array(),
				'em'     => array(),
				'b'      => array(),
				'i'      => array(),
				'br'     => array(),
			)
		);
		return sprintf( '<%1$s class="%2$s">%3$s</%1$s>', $tag, esc_attr( $class_name ), $value );
	}

	/**
	 * Default text of paper blocks; documents do not store unchanged defaults.
	 *
	 * @param string $name Block name.
	 * @return array Defaults.
	 */
	private static function defaults( $name ) {
		if ( PaperBlocks::INSTRUCTIONS === $name ) {
			return array( 'heading' => __( 'Instructions', 'ohmylms' ) );
		}
		if ( PaperBlocks::FORMULA === $name ) {
			return array( 'heading' => __( 'Formula sheet', 'ohmylms' ) );
		}
		return array();
	}

	/**
	 * One printed item, or null to leave its block out.
	 *
	 * @param array $item    Print item.
	 * @param array $context Rendering options.
	 * @return string|null HTML.
	 */
	private static function item( array $item, array $context ) {
		$kind = $item['kind'] ?? '';
		if ( 'html' === $kind ) {
			return wp_kses_post( (string) ( $item['html'] ?? '' ) );
		}
		if ( 'layout' === $kind ) {
			// Gutenberg columns holding questions: side by side on the sheet.
			$html = '<div class="ohmylms-print-columns">';
			foreach ( (array) ( $item['items'] ?? array() ) as $column ) {
				$width = (string) ( $column['attrs']['width'] ?? '' );
				$style = preg_match( '/^\d+(\.\d+)?(%|px|em|rem)$/', $width ) ? ' style="' . esc_attr( 'flex:0 0 ' . $width ) . '"' : '';
				$html .= '<div class="ohmylms-print-column"' . $style . '>' . self::items( (array) ( $column['items'] ?? array() ), $context ) . '</div>';
			}
			return $html . '</div>';
		}
		if ( 'question' === $kind ) {
			$question = (array) ( $item['question'] ?? array() );
			$label    = isset( $item['preview_label'] ) ? (string) $item['preview_label'] : (string) ( $item['label'] ?? '' );
			if ( $context['slot'] ) {
				return call_user_func( $context['slot'], $item + array( 'label' => $label ), $label );
			}
			return self::question( $question, $label, $context['settings'], $context['answers'], $context['version'] );
		}
		$attrs    = (array) ( $item['attrs'] ?? array() );
		$values   = array_merge( self::defaults( $item['name'] ?? '' ), $attrs );
		$children = self::items( (array) ( $item['items'] ?? array() ), $context );
		switch ( $item['name'] ?? '' ) {
			case PaperBlocks::HEADER:
				return self::header( $attrs, $values, $context['title'], PaperModel::minutes( $context['time_limit'] ), $context['total'], $context['settings']['versions'] > 1 ? $context['version'] : '' );
			case PaperBlocks::INSTRUCTIONS:
				return '<div class="ohmylms-paper-instructions">' . self::text( $values, 'heading', 'p', 'ohmylms-paper-instructions-heading' ) . $children . '</div>';
			case PaperBlocks::SECTION:
				$total = (float) ( $item['total'] ?? 0 );
				$html  = '<div class="ohmylms-paper-section">' . self::text( $values, 'title', 'p', 'ohmylms-paper-section-title' );
				if ( false !== ( $attrs['showTotal'] ?? null ) && $total > 0 ) {
					/* translators: %d: marks in the section. */
					$html .= '<span class="ohmylms-paper-section-total">' . esc_html( sprintf( _n( '[%d mark]', '[%d marks]', (int) $total, 'ohmylms' ), $total ) ) . '</span>';
				}
				return $html . '</div>';
			case PaperBlocks::COLUMNS:
				$layout = PaperModel::columns( $attrs );
				return sprintf(
					'<div class="%1$s" style="--ohmylms-paper-columns:%2$d"><div class="ohmylms-paper-columns-body">%3$s</div></div>',
					esc_attr( 'ohmylms-paper-columns is-' . $layout['flow'] . ( $layout['rule'] ? ' has-rule' : '' ) ),
					$layout['columns'],
					$children
				);
			case PaperBlocks::PAGE_BREAK:
				// A continuous paper on screen has no pages to break.
				return $context['continuous'] ? '' : '<div class="ohmylms-paper-page-break"><span>' . esc_html__( 'Page break', 'ohmylms' ) . '</span></div>';
			case PaperBlocks::ANSWER_SPACE:
				$space = PaperModel::answer_space( $attrs );
				return sprintf( '<div class="%1$s" style="--ohmylms-paper-lines:%2$d" aria-hidden="true"></div>', esc_attr( 'ohmylms-paper-answer-space is-' . $space['style'] ), $space['lines'] );
			case PaperBlocks::FORMULA:
				return '<div class="ohmylms-paper-formula-sheet' . ( false !== ( $attrs['newPage'] ?? null ) ? ' is-new-page' : '' ) . '">' . self::text( $values, 'heading', 'p', 'ohmylms-paper-formula-heading' ) . $children . '</div>';
		}
		return '';
	}

	/**
	 * Translated label of a candidate field.
	 *
	 * @param string $field Field key.
	 * @return string Label.
	 */
	private static function candidate_label( $field ) {
		return array(
			'name'   => __( 'Name', 'ohmylms' ),
			'class'  => __( 'Class', 'ohmylms' ),
			'number' => __( 'Candidate number', 'ohmylms' ),
			'date'   => __( 'Date', 'ohmylms' ),
		)[ $field ] ?? '';
	}

	/**
	 * Exam header: school, title, details, time and marks, and boxes for the candidate.
	 *
	 * @param array  $attrs   Block attributes.
	 * @param array  $values  Text values.
	 * @param string $title   Exam title.
	 * @param int    $minutes Time allowed, 0 for none.
	 * @param float  $total   Total marks.
	 * @param string $version Version letter, empty for a paper with one version.
	 * @return string HTML.
	 */
	public static function header( array $attrs, array $values, $title, $minutes, $total, $version = '' ) {
		$fields  = array_values( array_intersect( isset( $attrs['fields'] ) && is_array( $attrs['fields'] ) ? $attrs['fields'] : array( 'name', 'class', 'number' ), array( 'name', 'class', 'number', 'date' ) ) );
		$details = array();
		if ( '' !== $version ) {
			/* translators: %s: paper version letter. */
			$details[] = sprintf( __( 'Version %s', 'ohmylms' ), $version );
		}
		if ( false !== ( $attrs['showTime'] ?? null ) && $minutes > 0 ) {
			/* translators: %d: minutes allowed. */
			$details[] = sprintf( _n( 'Time: %d minute', 'Time: %d minutes', $minutes, 'ohmylms' ), $minutes );
		}
		if ( false !== ( $attrs['showMarks'] ?? null ) && $total > 0 ) {
			/* translators: %d: total marks. */
			$details[] = sprintf( _n( 'Total: %d mark', 'Total: %d marks', (int) $total, 'ohmylms' ), $total );
		}
		$html  = '<div class="ohmylms-paper-header"><div class="ohmylms-paper-header-main">';
		$html .= self::text( $values, 'school', 'p', 'ohmylms-paper-header-school' );
		$html .= '<p class="ohmylms-paper-header-title">' . esc_html( $title ) . '</p>';
		$html .= self::text( $values, 'subtitle', 'p', 'ohmylms-paper-header-subtitle' );
		if ( $details ) {
			$html .= '<p class="ohmylms-paper-header-details">' . esc_html( implode( ' · ', $details ) ) . '</p>';
		}
		$html .= '</div>';
		if ( $fields ) {
			$html .= '<div class="ohmylms-paper-header-fields">';
			foreach ( $fields as $field ) {
				$html .= '<div class="' . esc_attr( 'ohmylms-paper-header-field is-' . $field ) . '"><span>' . esc_html( self::candidate_label( $field ) ) . '</span>';
				$html .= 'number' === $field ? '<span class="ohmylms-paper-boxes" aria-hidden="true">' . str_repeat( '<span></span>', 6 ) . '</span>' : '<span class="ohmylms-paper-field-line" aria-hidden="true"></span>';
				$html .= '</div>';
			}
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * Bracketed marks, right-aligned as on exam papers.
	 *
	 * @param float $marks Marks.
	 * @param bool  $total Whether this is a question total.
	 * @return string HTML.
	 */
	private static function marks( $marks, $total = false ) {
		if ( ! $marks ) {
			return '';
		}
		/* translators: %d: total marks for the question. */
		$text = $total ? sprintf( __( '[Total: %d]', 'ohmylms' ), $marks ) : '[' . PaperModel::number_text( $marks ) . ']';
		return '<span class="ohmylms-print-marks">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Answer key entries.
	 *
	 * @param string[] $answers Accepted answers.
	 * @param string   $rubric  Marking notes.
	 * @return string HTML.
	 */
	private static function key( array $answers, $rubric = '' ) {
		if ( ! $answers && '' === $rubric ) {
			return '';
		}
		$html = '<div class="ohmylms-print-key">';
		if ( $answers ) {
			/* translators: %s: accepted answers separated by commas. */
			$html .= '<p>' . esc_html( sprintf( __( 'Answer: %s', 'ohmylms' ), implode( ', ', $answers ) ) ) . '</p>';
		}
		if ( '' !== $rubric ) {
			$html .= '<p>' . esc_html( $rubric ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * Answer key lines.
	 *
	 * @param array|null $lines Key lines.
	 * @return string HTML.
	 */
	private static function key_lines( $lines ) {
		if ( empty( $lines ) ) {
			return '';
		}
		$html = '<div class="ohmylms-print-key">';
		foreach ( $lines as $line ) {
			$html .= '<p>' . esc_html( $line ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * Space to write an answer: an answer line or ruled lines.
	 *
	 * @param array $item Question or part model.
	 * @return string HTML.
	 */
	private static function answer_space( array $item ) {
		if ( 'answer-line' === ( $item['layout'] ?? '' ) ) {
			$unit = (string) ( $item['unit'] ?? '' );
			return '<div class="ohmylms-print-answer-line"><span class="ohmylms-print-line"></span>' . ( '' !== $unit ? '<span class="ohmylms-print-unit">' . esc_html( $unit ) . '</span>' : '' ) . '</div>';
		}
		return '<div class="ohmylms-print-lines" style="' . esc_attr( '--ohmylms-paper-lines:' . PaperModel::number_text( $item['lines'] ?? 4 ) ) . '" aria-hidden="true"></div>';
	}

	/**
	 * Option text with an optional image.
	 *
	 * @param array $item Item with text and image.
	 * @return string HTML.
	 */
	private static function item_content( array $item ) {
		$image = (string) ( $item['image'] ?? '' );
		return '<span class="ohmylms-print-option">' . wp_kses_post( (string) ( $item['text'] ?? '' ) ) . ( '' !== $image ? '<img src="' . esc_url( $image ) . '" alt="">' : '' ) . '</span>';
	}

	/**
	 * The printed form of one question.
	 *
	 * @param array  $question Question: settings, options and the public flag of a learner view.
	 * @param string $label    Printed number, empty for none.
	 * @param array  $settings Sanitized paper settings.
	 * @param bool   $answers  Whether to print the answer key.
	 * @param string $version  Paper version letter.
	 * @param array  $args {
	 *     Learner Paper View options.
	 *
	 *     @type bool          $head_only    Print only the number, statement and marks.
	 *     @type string|null   $prompt       Statement HTML to print instead of the question's own.
	 *     @type callable|null $choice_input Receives a choice model; returns the answer field that
	 *                                       makes its printed letter selectable.
	 * }
	 * @return string HTML.
	 */
	public static function question( array $question, $label, array $settings, $answers = false, $version = 'A', array $args = array() ) {
		$args  += array(
			'head_only'    => false,
			'prompt'       => null,
			'choice_input' => null,
		);
		$paper  = PaperModel::question( $question, $settings, $answers, PaperModel::prompt( $question ), $version );
		$layout = $paper['layout'];
		$marks  = ! empty( $settings['show_marks'] );
		$html   = '<div class="' . esc_attr( 'ohmylms-print-question is-' . $layout ) . '" data-ohmylms-latex>';
		$html  .= '<div class="ohmylms-print-head">';
		if ( 'text' !== $layout && '' !== (string) $label ) {
			$html .= '<span class="ohmylms-print-number">' . esc_html( $label ) . '</span>';
		}
		$html .= '<div class="ohmylms-print-prompt">' . ( null !== $args['prompt'] ? $args['prompt'] : wp_kses_post( (string) $paper['prompt'] ) ) . '</div>';
		if ( $marks && 'parts' !== $layout ) {
			$html .= self::marks( $paper['marks'] );
		}
		$html .= '</div>';
		if ( ! $args['head_only'] ) {
			$html .= self::body( $paper, $question, $marks, $answers, $args['choice_input'] );
		}
		/**
		 * Filters the printed form of a question on the print page and in the learner's Paper View.
		 *
		 * A question type without a built-in printed form prints its statement and blank lines; an
		 * add-on can print its own form here. Never include answers unless `$answers` is true.
		 *
		 * @param string $html     Printed question HTML.
		 * @param array  $question Question: the learner view, or the full question for an answer key.
		 * @param array  $paper    Paper model of the question.
		 * @param bool   $answers  Whether this is an answer key.
		 */
		return (string) apply_filters( 'ohmylms_paper_question_html', $html . '</div>', $question, $paper, $answers );
	}

	/**
	 * Body of a printed question below its statement.
	 *
	 * @param array         $paper    Paper model.
	 * @param array         $question Question.
	 * @param bool          $marks    Whether marks print.
	 * @param bool          $answers  Whether this is an answer key.
	 * @param callable|null $input    Choice answer field, for the learner's Paper View.
	 * @return string HTML.
	 */
	private static function body( array $paper, array $question, $marks, $answers, $input ) {
		switch ( $paper['layout'] ) {
			case 'choice':
				$html = '<ol class="' . esc_attr( 'ohmylms-print-options' . ( $paper['multiple'] ? ' is-multiple' : '' ) ) . '">';
				foreach ( $paper['options'] as $index => $option ) {
					$field   = $input ? (string) call_user_func( $input, $option, $index ) : '';
					$classes = trim( ( ! empty( $option['correct'] ) ? 'is-correct' : '' ) . ( '' !== $field ? ' ohmylms-paper-choice' : '' ) );
					$body    = '<span class="ohmylms-print-letter">' . esc_html( $option['letter'] ) . '</span>' . self::item_content( $option );
					$html   .= '' !== $classes ? '<li class="' . esc_attr( $classes ) . '">' : '<li>';
					// A label makes the whole printed option select its answer field.
					$html .= ( '' !== $field ? '<label class="ohmylms-paper-choice-label">' . $field . $body . '</label>' : $body ) . '</li>';
				}
				return $html . '</ol>';
			case 'answer-line':
			case 'lines':
				return self::answer_space( $paper ) . ( $answers ? self::key( (array) ( $paper['answers'] ?? array() ) ) : '' );
			case 'parts':
				$html = '';
				foreach ( $paper['parts'] as $part ) {
					$prompt = ! empty( $question['public'] ) || ! empty( $question['figure_part_html'] ) ? wp_kses_post( $part['prompt'] ) : esc_html( $part['prompt'] );
					$html  .= '<div class="ohmylms-print-part"><div class="ohmylms-print-head"><span class="ohmylms-print-part-label">' . ( '' !== $part['label'] ? esc_html( '(' . $part['label'] . ')' ) : '' ) . '</span>';
					$html  .= '<div class="ohmylms-print-prompt">' . $prompt . '</div>' . ( $marks ? self::marks( $part['marks'] ) : '' ) . '</div>';
					$html  .= self::answer_space( $part ) . ( $answers ? self::key( (array) ( $part['answers'] ?? array() ), (string) ( $part['rubric'] ?? '' ) ) : '' ) . '</div>';
				}
				if ( $marks && count( $paper['parts'] ) > 1 ) {
					$html .= '<div class="ohmylms-print-total">' . self::marks( $paper['marks'], true ) . '</div>';
				}
				return $html;
			case 'unsupported':
				return self::answer_space(
					array(
						'layout' => 'lines',
						'lines'  => 4,
					)
				);
		}
		return self::typed( $paper );
	}

	/**
	 * Bodies of matching, ordering, blank, sorting, tile, puzzle and visual questions.
	 *
	 * @param array $paper Paper model.
	 * @return string HTML.
	 */
	private static function typed( array $paper ) {
		$key = self::key_lines( $paper['key'] ?? null );
		switch ( $paper['layout'] ) {
			case 'matching':
				$left  = '';
				$right = '';
				$boxes = '';
				foreach ( $paper['left'] as $item ) {
					$left  .= '<li><span class="ohmylms-print-letter">' . esc_html( (string) $item['number'] ) . '</span>' . self::item_content( $item ) . '</li>';
					$boxes .= '<span>' . esc_html( (string) $item['number'] ) . '<span class="ohmylms-print-box"></span></span>';
				}
				foreach ( $paper['right'] as $item ) {
					$right .= '<li><span class="ohmylms-print-letter">' . esc_html( $item['letter'] ) . '</span>' . self::item_content( $item ) . '</li>';
				}
				return '<div class="ohmylms-print-matching"><ol>' . $left . '</ol><ol>' . $right . '</ol></div><p class="ohmylms-print-boxes">' . $boxes . '</p>' . $key;
			case 'reorder':
				$html = '<ol class="ohmylms-print-options is-ordering">';
				foreach ( $paper['items'] as $item ) {
					$html .= '<li><span class="ohmylms-print-box"></span><span class="ohmylms-print-letter">' . esc_html( $item['letter'] ) . '</span>' . self::item_content( $item ) . '</li>';
				}
				return $html . '</ol><p class="ohmylms-print-instruction">' . esc_html__( 'Number the boxes to show the correct order.', 'ohmylms' ) . '</p>' . $key;
			case 'blanks':
				return '';
			case 'visual':
				return self::visual( $paper['figure'] );
			case 'dropdown':
				$html = '<ol class="ohmylms-print-slots">';
				foreach ( $paper['slots'] as $slot ) {
					$html .= '<li><span class="ohmylms-print-letter">(' . esc_html( (string) $slot['number'] ) . ')</span>';
					foreach ( $slot['choices'] as $choice ) {
						$filled = in_array( $choice, (array) ( $slot['answers'] ?? array() ), true );
						$html  .= '<span class="ohmylms-print-choice"><span class="ohmylms-print-bubble' . ( $filled ? ' is-filled' : '' ) . '" aria-hidden="true"></span>' . esc_html( $choice ) . '</span>';
					}
					$html .= '</li>';
				}
				return $html . '</ol>' . $key;
			case 'multi-blank':
				$html = '';
				if ( $paper['table'] ) {
					$html .= '<table class="ohmylms-print-table">';
					if ( $paper['table']['columns'] ) {
						$html .= '<thead><tr>';
						foreach ( $paper['table']['columns'] as $column ) {
							$html .= '<th>' . esc_html( $column ) . '</th>';
						}
						$html .= '</tr></thead>';
					}
					$html .= '<tbody>';
					foreach ( $paper['table']['rows'] as $row ) {
						$html .= '<tr>';
						foreach ( $row as $parts ) {
							$html .= '<td>';
							foreach ( $parts as $part ) {
								if ( isset( $part['id'] ) ) {
									$unit  = (string) ( $paper['units'][ $part['id'] ] ?? '' );
									$html .= '<span class="ohmylms-print-blank">(' . esc_html( $part['id'] ) . ')</span>' . ( '' !== $unit ? ' ' . esc_html( $unit ) : '' );
								} else {
									$html .= esc_html( $part['text'] );
								}
							}
							$html .= '</td>';
						}
						$html .= '</tr>';
					}
					$html .= '</tbody></table>';
				}
				return $html . $key;
			case 'categorize':
				$html = '<ol class="ohmylms-print-items">';
				foreach ( $paper['items'] as $item ) {
					$html .= '<li><span class="ohmylms-print-letter">' . esc_html( $item['letter'] ) . '</span>' . self::item_content( $item ) . '</li>';
				}
				$head = '';
				$body = '';
				foreach ( $paper['groups'] as $group ) {
					$head .= '<th>' . esc_html( $group['label'] ) . '</th>';
					$body .= '<td></td>';
				}
				return $html . '</ol><table class="ohmylms-print-table is-groups"><thead><tr>' . $head . '</tr></thead><tbody><tr>' . $body . '</tr></tbody></table>' . $key;
			case 'tiles':
				$tiles = '';
				foreach ( $paper['tiles'] as $tile ) {
					$tiles .= '<span>' . esc_html( $tile ) . '</span>';
				}
				return '<p class="ohmylms-print-tiles">' . $tiles . '</p><div class="ohmylms-print-answer-line"><span class="ohmylms-print-line"></span></div>' . $key;
			case 'crossword':
				$cells = array_flip( $paper['cells'] );
				$grid  = '';
				for ( $row = 0; $row < $paper['size']; $row++ ) {
					$grid .= '<tr>';
					for ( $col = 0; $col < $paper['size']; $col++ ) {
						$cell = 'r' . $row . 'c' . $col;
						if ( ! isset( $cells[ $cell ] ) ) {
							$grid .= '<td class="is-empty"></td>';
							continue;
						}
						$number = $paper['numbers'][ $cell ] ?? '';
						$grid  .= '<td>' . ( $number ? '<span class="ohmylms-print-cell-number">' . esc_html( (string) $number ) . '</span>' : '' ) . esc_html( (string) ( $paper['letters'][ $cell ] ?? '' ) ) . '</td>';
					}
					$grid .= '</tr>';
				}
				$clues = '';
				foreach ( array(
					array( __( 'Across', 'ohmylms' ), $paper['across'] ),
					array( __( 'Down', 'ohmylms' ), $paper['down'] ),
				) as $group ) {
					if ( ! $group[1] ) {
						continue;
					}
					$clues .= '<div><p class="ohmylms-print-clues-heading">' . esc_html( $group[0] ) . '</p><ol>';
					foreach ( $group[1] as $clue ) {
						$clues .= '<li><strong>' . esc_html( (string) $clue['number'] ) . '</strong> ' . esc_html( $clue['text'] ) . ' (' . esc_html( (string) $clue['length'] ) . ')</li>';
					}
					$clues .= '</ol></div>';
				}
				return '<div class="ohmylms-print-puzzle"><table class="ohmylms-print-grid is-crossword" aria-hidden="true"><tbody>' . $grid . '</tbody></table><div class="ohmylms-print-clues">' . $clues . '</div></div>';
			case 'wordsearch':
				$marked = array_flip( $paper['marked'] );
				$grid   = '';
				foreach ( $paper['grid'] as $row_index => $row ) {
					$grid .= '<tr>';
					foreach ( $row as $col => $letter ) {
						$grid .= '<td' . ( isset( $marked[ 'r' . $row_index . 'c' . $col ] ) ? ' class="is-marked"' : '' ) . '>' . esc_html( $letter ) . '</td>';
					}
					$grid .= '</tr>';
				}
				$words = '';
				foreach ( $paper['words'] as $word ) {
					$words .= '<li>' . esc_html( $word ) . '</li>';
				}
				return '<div class="ohmylms-print-puzzle"><table class="ohmylms-print-grid is-wordsearch" aria-hidden="true"><tbody>' . $grid . '</tbody></table><ul class="ohmylms-print-words">' . $words . '</ul></div>';
		}
		return '';
	}

	/**
	 * A coordinate for SVG markup.
	 *
	 * @param float $value Number.
	 * @return string Text.
	 */
	private static function n( $value ) {
		return PaperModel::number_text( round( (float) $value, 3 ) );
	}

	/**
	 * A scale label: up to four decimals, without trailing zeros.
	 *
	 * @param float $value Number.
	 * @return string Text.
	 */
	private static function label( $value ) {
		return PaperModel::number_text( round( (float) $value, 4 ) );
	}

	/**
	 * The printed figure of a visual question, with its key when the model has one.
	 *
	 * @param array|null $figure Figure model.
	 * @return string HTML.
	 */
	private static function visual( $figure ) {
		if ( ! $figure ) {
			return '';
		}
		$drawings = array(
			'number-line'  => 'number_line',
			'fill-level'   => 'jug',
			'shade-model'  => 'shade_model',
			'set-clock'    => 'clock',
			'count-blocks' => 'place_value',
			'make-amount'  => 'money',
			'build-chart'  => 'chart',
			'grid-build'   => 'grid',
		);
		$html     = '<div class="ohmylms-print-visual">';
		if ( isset( $drawings[ $figure['figure'] ] ) ) {
			$html .= call_user_func( array( __CLASS__, $drawings[ $figure['figure'] ] ), $figure );
		}
		if ( 'make-amount' === $figure['figure'] ) {
			$html .= '<div class="ohmylms-print-answer-line">' . esc_html__( 'Total:', 'ohmylms' ) . '<span class="ohmylms-print-line"></span><span class="ohmylms-print-unit">' . esc_html( $figure['symbol'] ) . '</span></div>';
		}
		return $html . self::figure_key( $figure ) . '</div>';
	}

	/**
	 * Whether a tick is labelled.
	 *
	 * @param array $ticks Ticks.
	 * @param int   $index Tick index.
	 * @return bool Labelled.
	 */
	private static function labelled( array $ticks, $index ) {
		return 0 === $index % $ticks['labelEvery'] || count( $ticks['values'] ) - 1 === $index;
	}

	/**
	 * Whether a figure has an answer target to draw.
	 *
	 * @param array $figure Figure model.
	 * @return bool Has a target.
	 */
	private static function has_target( array $figure ) {
		return isset( $figure['target'] ) && is_finite( (float) $figure['target'] );
	}

	/**
	 * Horizontal number line with ticks and labels.
	 *
	 * @param array $figure Figure model.
	 * @return string SVG.
	 */
	private static function number_line( array $figure ) {
		$span = $figure['max'] - $figure['min'];
		$span = $span ? $span : 1;
		$x    = static function ( $value ) use ( $figure, $span ) {
			return self::n( 12 + ( ( $value - $figure['min'] ) / $span ) * 276 );
		};
		$svg  = '<svg class="ohmylms-print-figure is-number-line" viewBox="0 0 300 46" aria-hidden="true"><line x1="4" y1="24" x2="296" y2="24" stroke="currentColor"></line><path d="M4 24l6-4v8zM296 24l-6-4v8z" fill="currentColor"></path>';
		foreach ( $figure['ticks']['values'] as $index => $value ) {
			$labelled = self::labelled( $figure['ticks'], $index );
			$svg     .= '<g><line x1="' . $x( $value ) . '" y1="' . ( $labelled ? 18 : 20 ) . '" x2="' . $x( $value ) . '" y2="' . ( $labelled ? 30 : 28 ) . '" stroke="currentColor"></line>';
			if ( $labelled ) {
				$svg .= '<text x="' . $x( $value ) . '" y="42" text-anchor="middle" font-size="8" fill="currentColor">' . esc_html( self::label( $value ) ) . '</text>';
			}
			$svg .= '</g>';
		}
		if ( self::has_target( $figure ) ) {
			$svg .= '<g fill="' . self::KEY . '"><circle cx="' . $x( $figure['target'] ) . '" cy="24" r="3.5"></circle><text x="' . $x( $figure['target'] ) . '" y="11" text-anchor="middle" font-size="8">' . esc_html( self::label( $figure['target'] ) ) . '</text></g>';
		}
		return $svg . '</svg>';
	}

	/**
	 * Measuring jug with a scale, filled to the target in the answer key.
	 *
	 * @param array $figure Figure model.
	 * @return string SVG.
	 */
	private static function jug( array $figure ) {
		$span = $figure['max'] - $figure['min'];
		$span = $span ? $span : 1;
		$y    = static function ( $value ) use ( $figure, $span ) {
			return 196 - ( ( $value - $figure['min'] ) / $span ) * 170;
		};
		$svg  = '<svg class="ohmylms-print-figure is-jug" viewBox="0 0 130 206" aria-hidden="true">';
		if ( self::has_target( $figure ) ) {
			$top  = $y( $figure['target'] );
			$svg .= '<rect x="56" y="' . self::n( $top ) . '" width="60" height="' . self::n( 196 - $top ) . '" fill="#f5c4c4" stroke="' . self::KEY . '"></rect>';
		}
		$svg .= '<path d="M56 14v182h60v-182" fill="none" stroke="currentColor" stroke-width="1.5"></path>';
		if ( '' !== $figure['unit'] ) {
			$svg .= '<text x="86" y="9" text-anchor="middle" font-size="8" fill="currentColor">' . esc_html( $figure['unit'] ) . '</text>';
		}
		foreach ( $figure['ticks']['values'] as $index => $value ) {
			$labelled = self::labelled( $figure['ticks'], $index );
			$svg     .= '<g><line x1="56" y1="' . self::n( $y( $value ) ) . '" x2="' . ( $labelled ? 68 : 63 ) . '" y2="' . self::n( $y( $value ) ) . '" stroke="currentColor"></line>';
			if ( $labelled ) {
				$svg .= '<text x="51" y="' . self::n( $y( $value ) + 3 ) . '" text-anchor="end" font-size="8" fill="currentColor">' . esc_html( self::label( $value ) ) . '</text>';
			}
			$svg .= '</g>';
		}
		return $svg . '</svg>';
	}

	/**
	 * Bar, grid or circle divided into equal parts; the key shades the answer.
	 *
	 * @param array $figure Figure model.
	 * @return string SVG.
	 */
	private static function shade_model( array $figure ) {
		$parts  = $figure['parts'];
		$shaded = $figure['shaded'] ?? 0;
		$fill   = static function ( $index ) use ( $shaded ) {
			return $index < $shaded ? '#f5c4c4' : 'none';
		};
		if ( 'circle' === $figure['shape'] ) {
			$svg   = '<svg class="ohmylms-print-figure is-circle" viewBox="0 0 120 120" aria-hidden="true">';
			$point = static function ( $angle ) {
				return self::n( 60 + 55 * cos( $angle ) ) . ' ' . self::n( 60 + 55 * sin( $angle ) );
			};
			for ( $index = 0; $index < $parts; $index++ ) {
				if ( 1 === $parts ) {
					$svg .= '<circle cx="60" cy="60" r="55" fill="' . $fill( $index ) . '" stroke="currentColor"></circle>';
					continue;
				}
				$start = ( $index / $parts ) * 2 * M_PI - M_PI / 2;
				$end   = ( ( $index + 1 ) / $parts ) * 2 * M_PI - M_PI / 2;
				$svg  .= '<path d="M60 60L' . $point( $start ) . 'A55 55 0 ' . ( $end - $start > M_PI ? 1 : 0 ) . ' 1 ' . $point( $end ) . 'Z" fill="' . $fill( $index ) . '" stroke="currentColor"></path>';
			}
			return $svg . '</svg>';
		}
		$cols   = 'bar' === $figure['shape'] ? $parts : $figure['cols'];
		$rows   = (int) ceil( $parts / $cols );
		$cell   = 'bar' === $figure['shape'] ? 280 / $parts : 20;
		$height = 'bar' === $figure['shape'] ? 36 : 20;
		$style  = 'grid' === $figure['shape'] ? ' style="' . esc_attr( 'width:' . ( $cols * 9 ) . 'mm' ) . '"' : '';
		$svg    = '<svg class="' . esc_attr( 'ohmylms-print-figure is-' . $figure['shape'] ) . '" viewBox="0 0 ' . self::n( $cols * $cell + 2 ) . ' ' . self::n( $rows * $height + 2 ) . '"' . $style . ' aria-hidden="true">';
		for ( $index = 0; $index < $parts; $index++ ) {
			$svg .= '<rect x="' . self::n( 1 + ( $index % $cols ) * $cell ) . '" y="' . self::n( 1 + floor( $index / $cols ) * $height ) . '" width="' . self::n( $cell ) . '" height="' . $height . '" fill="' . $fill( $index ) . '" stroke="currentColor"></rect>';
		}
		return $svg . '</svg>';
	}

	/**
	 * Clock face without hands; the key draws them. A digital box is added when the question shows one.
	 *
	 * @param array $figure Figure model.
	 * @return string HTML.
	 */
	private static function clock( array $figure ) {
		$has_time = isset( $figure['hour'] );
		$svg      = '<div class="ohmylms-print-clock"><svg class="ohmylms-print-figure is-clock" viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="56" fill="none" stroke="currentColor" stroke-width="1.5"></circle>';
		for ( $minute = 0; $minute < 60; $minute++ ) {
			$radians = deg2rad( $minute * 6 - 90 );
			$inner   = $minute % 5 ? 52 : 48;
			$svg    .= '<line x1="' . self::n( 60 + $inner * cos( $radians ) ) . '" y1="' . self::n( 60 + $inner * sin( $radians ) ) . '" x2="' . self::n( 60 + 56 * cos( $radians ) ) . '" y2="' . self::n( 60 + 56 * sin( $radians ) ) . '" stroke="currentColor" stroke-width="' . ( $minute % 5 ? '0.5' : '1.2' ) . '"></line>';
		}
		for ( $index = 0; $index < 12; $index++ ) {
			$radians = deg2rad( ( $index + 1 ) * 30 - 90 );
			$svg    .= '<text x="' . self::n( 60 + 40 * cos( $radians ) ) . '" y="' . self::n( 60 + 40 * sin( $radians ) + 3.5 ) . '" text-anchor="middle" font-size="10" fill="currentColor">' . ( $index + 1 ) . '</text>';
		}
		if ( $has_time ) {
			$hand = static function ( $angle, $length ) {
				$radians = deg2rad( $angle - 90 );
				return 'x2="' . self::n( 60 + $length * cos( $radians ) ) . '" y2="' . self::n( 60 + $length * sin( $radians ) ) . '"';
			};
			$svg .= '<g stroke="' . self::KEY . '" stroke-linecap="round"><line x1="60" y1="60" ' . $hand( ( ( $figure['hour'] % 12 ) + $figure['minute'] / 60 ) * 30, 26 ) . ' stroke-width="3"></line><line x1="60" y1="60" ' . $hand( $figure['minute'] * 6, 42 ) . ' stroke-width="1.8"></line></g>';
		}
		$svg .= '<circle cx="60" cy="60" r="2.5" fill="currentColor"></circle></svg>';
		if ( $figure['digital'] ) {
			$svg .= '<p class="ohmylms-print-digital"><span class="ohmylms-print-box">' . ( $has_time ? esc_html( (string) $figure['hour'] ) : '' ) . '</span>:<span class="ohmylms-print-box">' . ( $has_time ? esc_html( str_pad( (string) $figure['minute'], 2, '0', STR_PAD_LEFT ) ) : '' ) . '</span></p>';
		}
		return $svg . '</div>';
	}

	/**
	 * Place value table to write how many blocks of each place.
	 *
	 * @param array $figure Figure model.
	 * @return string HTML.
	 */
	private static function place_value( array $figure ) {
		$names = array(
			'thousands' => __( 'Thousands', 'ohmylms' ),
			'hundreds'  => __( 'Hundreds', 'ohmylms' ),
			'tens'      => __( 'Tens', 'ohmylms' ),
			'ones'      => __( 'Ones', 'ohmylms' ),
		);
		$head  = '';
		$body  = '';
		foreach ( $figure['places'] as $place ) {
			$head .= '<th>' . esc_html( $names[ $place ] ) . '</th>';
			$body .= '<td>' . ( isset( $figure['counts'][ $place ] ) ? esc_html( (string) $figure['counts'][ $place ] ) : '' ) . '</td>';
		}
		return '<table class="ohmylms-print-table is-places"><thead><tr>' . $head . '</tr></thead><tbody><tr>' . $body . '</tr></tbody></table>';
	}

	/**
	 * Notes and coins with a box for how many of each.
	 *
	 * @param array $figure Figure model.
	 * @return string HTML.
	 */
	private static function money( array $figure ) {
		$html = '<div class="ohmylms-print-money">';
		foreach ( $figure['denominations'] as $value ) {
			$html .= '<span><span class="ohmylms-print-note-value">' . esc_html( $value . ' ' . $figure['symbol'] ) . '</span>×<span class="ohmylms-print-box">' . ( isset( $figure['counts'][ $value ] ) ? esc_html( (string) $figure['counts'][ $value ] ) : '' ) . '</span></span>';
		}
		return $html . '</div>';
	}

	/**
	 * Bar chart axes with categories; the key draws the bars.
	 *
	 * @param array $figure Figure model.
	 * @return string HTML.
	 */
	private static function chart( array $figure ) {
		$top    = 12;
		$bottom = 150;
		$left   = 36;
		$right  = 292;
		$y      = static function ( $value ) use ( $figure, $top, $bottom ) {
			return $bottom - ( $value / $figure['max'] ) * ( $bottom - $top );
		};
		$width  = ( $right - $left ) / max( 1, count( $figure['categories'] ) );
		$html   = '';
		if ( isset( $figure['table'] ) ) {
			$html .= '<table class="ohmylms-print-table is-data"><tbody>';
			foreach ( $figure['table'] as $row ) {
				$html .= '<tr><th>' . esc_html( $row['label'] ) . '</th><td>' . esc_html( self::label( $row['value'] ) ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}
		$html .= '<svg class="ohmylms-print-figure is-chart" viewBox="0 0 300 172" aria-hidden="true">';
		foreach ( $figure['ticks']['values'] as $index => $value ) {
			$html .= '<g><line x1="' . $left . '" y1="' . self::n( $y( $value ) ) . '" x2="' . $right . '" y2="' . self::n( $y( $value ) ) . '" stroke="#c3c4c7" stroke-width="0.5"></line>';
			if ( self::labelled( $figure['ticks'], $index ) ) {
				$html .= '<text x="' . ( $left - 4 ) . '" y="' . self::n( $y( $value ) + 3 ) . '" text-anchor="end" font-size="8" fill="currentColor">' . esc_html( self::label( $value ) ) . '</text>';
			}
			$html .= '</g>';
		}
		if ( '' !== $figure['unit'] ) {
			$html .= '<text x="' . ( $left - 4 ) . '" y="6" text-anchor="end" font-size="8" fill="currentColor">' . esc_html( $figure['unit'] ) . '</text>';
		}
		foreach ( $figure['categories'] as $index => $category ) {
			$html .= '<g>';
			if ( isset( $figure['values'] ) ) {
				$top_y = $y( min( $figure['max'], $figure['values'][ $category['id'] ] ?? 0 ) );
				$html .= '<rect x="' . self::n( $left + $index * $width + $width * 0.2 ) . '" y="' . self::n( $top_y ) . '" width="' . self::n( $width * 0.6 ) . '" height="' . self::n( $bottom - $top_y ) . '" fill="#f5c4c4" stroke="' . self::KEY . '"></rect>';
			}
			$html .= '<text x="' . self::n( $left + $index * $width + $width / 2 ) . '" y="' . ( $bottom + 14 ) . '" text-anchor="middle" font-size="8" fill="currentColor">' . esc_html( $category['label'] ) . '</text></g>';
		}
		return $html . '<path d="M' . $left . ' ' . $top . 'V' . $bottom . 'H' . $right . '" fill="none" stroke="currentColor"></path></svg>';
	}

	/**
	 * Squared grid to draw on.
	 *
	 * @param array $figure Figure model.
	 * @return string SVG.
	 */
	private static function grid( array $figure ) {
		$rows = $figure['rows'];
		$cols = $figure['cols'];
		$svg  = '<svg class="ohmylms-print-figure is-grid-build" viewBox="0 0 ' . ( $cols * 10 + 2 ) . ' ' . ( $rows * 10 + 2 ) . '" style="' . esc_attr( 'width:' . ( $cols * 7 ) . 'mm' ) . '" aria-hidden="true">';
		for ( $row = 0; $row <= $rows; $row++ ) {
			$svg .= '<line x1="1" y1="' . ( 1 + $row * 10 ) . '" x2="' . ( 1 + $cols * 10 ) . '" y2="' . ( 1 + $row * 10 ) . '" stroke="#8c8f94" stroke-width="0.5"></line>';
		}
		for ( $col = 0; $col <= $cols; $col++ ) {
			$svg .= '<line x1="' . ( 1 + $col * 10 ) . '" y1="1" x2="' . ( 1 + $col * 10 ) . '" y2="' . ( 1 + $rows * 10 ) . '" stroke="#8c8f94" stroke-width="0.5"></line>';
		}
		return $svg . '</svg>';
	}

	/**
	 * Answer key lines of a figure.
	 *
	 * @param array $figure Figure model.
	 * @return string HTML.
	 */
	private static function figure_key( array $figure ) {
		$lines = (array) ( $figure['key'] ?? array() );
		if ( isset( $figure['constraints'] ) ) {
			$c     = $figure['constraints'];
			$lines = array();
			if ( null !== $c['area'] ) {
				/* translators: %d: area in grid squares. */
				$lines[] = sprintf( _n( 'Area: %d square', 'Area: %d squares', (int) $c['area'], 'ohmylms' ), $c['area'] );
			}
			if ( null !== $c['perimeter'] ) {
				/* translators: %d: perimeter in grid units. */
				$lines[] = sprintf( _n( 'Perimeter: %d unit', 'Perimeter: %d units', (int) $c['perimeter'], 'ohmylms' ), $c['perimeter'] );
			}
			if ( $c['rectangle'] ) {
				$lines[] = __( 'The shape is a rectangle.', 'ohmylms' );
			}
			if ( $c['connected'] ) {
				$lines[] = __( 'The squares form one connected shape.', 'ohmylms' );
			}
		}
		if ( ! $lines ) {
			return '';
		}
		if ( 1 === count( $lines ) ) {
			/* translators: %s: correct answer. */
			return '<div class="ohmylms-print-key"><p>' . esc_html( sprintf( __( 'Answer: %s', 'ohmylms' ), $lines[0] ) ) . '</p></div>';
		}
		$html = '<div class="ohmylms-print-key"><p>' . esc_html__( 'Answer:', 'ohmylms' ) . '</p>';
		foreach ( $lines as $line ) {
			$html .= '<p>' . esc_html( $line ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * Answer sheet for the choice questions of a paper version: a row of lettered circles per
	 * question, numbered as the paper prints them. The marking sheet fills the correct circles.
	 *
	 * @param array $items   Print items in the version's order.
	 * @param array $context Rendering options (title, settings, version, answers).
	 * @return string HTML.
	 */
	public static function answer_sheet( array $items, array $context ) {
		$settings  = $context['settings'];
		$answers   = ! empty( $context['answers'] );
		$version   = $context['version'] ?? 'A';
		$annotated = PaperModel::annotate( $items, $settings );
		$rows      = array();
		$elsewhere = array();
		foreach ( $annotated['questions'] as $item ) {
			$label = (string) ( $item['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$paper = PaperModel::question( (array) $item['question'], $settings, $answers, '', $version );
			if ( 'choice' === $paper['layout'] ) {
				$rows[] = array( $label, $paper );
			} else {
				$elsewhere[] = $label;
			}
		}
		$html  = '<div class="ohmylms-answer-sheet">';
		$html .= self::header(
			array(
				'fields'    => array( 'name', 'number' ),
				'showTime'  => false,
				'showMarks' => false,
			),
			array( 'subtitle' => $answers ? __( 'Marking sheet', 'ohmylms' ) : __( 'Answer sheet', 'ohmylms' ) ),
			(string) ( $context['title'] ?? '' ),
			0,
			0,
			$settings['versions'] > 1 ? $version : ''
		);
		if ( ! $answers && $rows ) {
			$html .= '<p class="ohmylms-print-instruction">' . esc_html__( 'Fill in the circle for your answer. Where a question asks you to select all that apply, fill in every circle you choose.', 'ohmylms' ) . '</p>';
		}
		if ( $rows ) {
			$html .= '<ol class="ohmylms-answer-sheet-rows">';
			foreach ( $rows as list( $label, $paper ) ) {
				$html .= '<li' . ( $paper['multiple'] ? ' class="is-multiple"' : '' ) . '><span class="ohmylms-print-number">' . esc_html( $label ) . '</span>';
				foreach ( $paper['options'] as $option ) {
					$html .= '<span class="ohmylms-answer-sheet-bubble' . ( ! empty( $option['correct'] ) ? ' is-filled' : '' ) . '">' . esc_html( $option['letter'] ) . '</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ol>';
		} else {
			$html .= '<p>' . esc_html__( 'This exam has no multiple choice or true or false questions for an answer sheet.', 'ohmylms' ) . '</p>';
		}
		if ( $rows && $elsewhere ) {
			/* translators: %s: question numbers. */
			$html .= '<p class="ohmylms-print-instruction">' . esc_html( sprintf( _n( 'Answer question %s on the question paper.', 'Answer questions %s on the question paper.', count( $elsewhere ), 'ohmylms' ), implode( ', ', $elsewhere ) ) ) . '</p>';
		}
		return $html . '</div>';
	}

	/**
	 * Whether rendered paper content needs the math runtime: equation markers or LaTeX delimiters.
	 *
	 * @param string $html Rendered paper.
	 * @return bool Has math.
	 */
	public static function has_math( $html ) {
		return false !== strpos( $html, '[[ohmylms-math:latex:' ) || (bool) preg_match( '/\$\S|\\\\[\[(]/', wp_strip_all_tags( $html ) );
	}
}
