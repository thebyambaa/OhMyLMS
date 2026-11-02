<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping.
/**
 * Safe document decoration for continuous student papers.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Quiz;

use OhMyLMS\Learning\NativeQuizEditor;
use OhMyLMS\Exams\PaperBlocks;
use OhMyLMS\Exams\PaperModel;
use OhMyLMS\Exams\PaperRenderer;
use OhMyLMS\Exams\PaperSettings;
use OhMyLMS\Exams\PrintPaper;

defined( 'ABSPATH' ) || exit;

/** Freeze instructions and headings alongside the questions they introduce. */
final class PaperPresentation {
	/**
	 * Collect presentation content without question answers or executable blocks.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array HTML keyed by the following question ID, with a trailing entry.
	 */
	public static function content( $quiz_id ) {
		$post = get_post( $quiz_id );
		if ( ! $post ) {
			return array();
		}
		$result  = array();
		$pending = '';
		self::walk( parse_blocks( NativeQuizEditor::editor_content( $post ) ), $result, $pending );
		$result['end'] = $pending;
		return $result;
	}

	/**
	 * The paper document the editor's Paper view shows, for the student's continuous paper, with
	 * its styles and the `ohmylms/paper` module. Questions carry no answer keys.
	 *
	 * @param int        $quiz_id  Quiz ID.
	 * @param array      $settings Quiz settings in effect for this delivery.
	 * @param array|null $items Optional learner-safe items for a single question preview.
	 * @return array Paper data: title, settings, items and timeLimit.
	 */
	public static function student( $quiz_id, array $settings, $items = null ) {
		wp_enqueue_style( PrintPaper::STYLE, plugins_url( 'assets/css/paper.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/paper.css' ) );
		\OhMyLMS\Extensions\Interactivity::enqueue( 'ohmylms/paper' );
		if ( null === $items ) {
			$attempt  = ohmylms_get_quiz( $quiz_id )->get_quiz_attempt( get_current_user_id() );
			$delivery = $attempt ? \OhMyLMS\Assessment\AttemptItems::delivery( $attempt['id'] ) : array();
			$items    = PrintPaper::items( $quiz_id, false );
			if ( $delivery ) {
				$by_id  = array_column( $delivery, null, 'id' );
				$seen   = array();
				$freeze = static function ( array $entries ) use ( &$freeze, $by_id, &$seen ) {
					$result = array();
					foreach ( $entries as $entry ) {
						if ( 'question' === ( $entry['kind'] ?? '' ) ) {
							$id = $entry['question']['id'];
							if ( ! isset( $by_id[ $id ] ) ) {
								continue;
							}
							$entry['question'] = $by_id[ $id ] + array( 'public' => true );
							$seen[ $id ]       = true;
						} elseif ( isset( $entry['items'] ) ) {
							$entry['items'] = $freeze( $entry['items'] );
						}
						$result[] = $entry;
					}
					return $result;
				};
				$items  = $freeze( $items );
				// A later edit may remove a live question; the existing attempt still owns it.
				foreach ( $delivery as $question ) {
					if ( ! isset( $seen[ $question['id'] ] ) ) {
						$items[] = array(
							'kind'     => 'question',
							'question' => $question + array( 'public' => true ),
						);
					}
				}
			}
		}
		wp_interactivity_state(
			'ohmylms/paper',
			array(
				/* translators: %s: question number. */
				'flaggedText'   => __( 'Question %s flagged for review.', 'ohmylms' ),
				/* translators: %s: question number. */
				'unflaggedText' => __( 'Question %s no longer flagged.', 'ohmylms' ),
				/* translators: %d: flagged questions. */
				'confirmFlags'  => __( '%d questions are flagged for review. Submit anyway?', 'ohmylms' ),
				/* translators: %d: minutes left. */
				'minutesLeft'   => __( '%d minutes left.', 'ohmylms' ),
				'oneMinuteLeft' => __( 'One minute left.', 'ohmylms' ),
				'answeredText'  => __( 'answered', 'ohmylms' ),
				'blankText'     => __( 'not answered', 'ohmylms' ),
				'flagText'      => __( 'flagged', 'ohmylms' ),
			)
		);
		return array(
			'title'     => get_the_title( $quiz_id ),
			'settings'  => PaperSettings::sanitize( $settings['paper'] ?? array() ),
			'items'     => null === $items ? PrintPaper::items( $quiz_id, false ) : $items,
			'timeLimit' => array(
				'value' => (float) ( $settings['time_limit']['value'] ?? 0 ),
				'type'  => sanitize_key( (string) ( $settings['time_limit']['type'] ?? 'minutes' ) ),
			),
		);
	}

	/**
	 * Printed number, statement and section of each question on the paper.
	 *
	 * @param array $paper Paper data from student().
	 * @return array[] Entries by question ID: question (printed view), label and section title.
	 */
	public static function entries( array $paper ) {
		$entries = array();
		$section = '';
		$visit   = static function ( array $items ) use ( &$visit, &$entries, &$section ) {
			foreach ( $items as $item ) {
				$kind = $item['kind'] ?? '';
				if ( 'question' === $kind ) {
					$id = (int) ( $item['question']['id'] ?? 0 );
					if ( ! isset( $entries[ $id ] ) ) {
						$entries[ $id ] = array(
							'question' => (array) $item['question'],
							'label'    => isset( $item['preview_label'] ) ? (string) $item['preview_label'] : (string) ( $item['label'] ?? '' ),
							'section'  => $section,
						);
					}
				} elseif ( 'paper' === $kind || 'layout' === $kind ) {
					if ( PaperBlocks::SECTION === ( $item['name'] ?? '' ) ) {
						$section = trim( wp_strip_all_tags( (string) ( $item['attrs']['title'] ?? '' ) ) );
					}
					$visit( (array) ( $item['items'] ?? array() ) );
				}
			}
		};
		$visit( PaperModel::annotate( $paper['items'], $paper['settings'] )['items'] );
		return $entries;
	}

	/**
	 * The navigator's outline: the player's questions in paper order with their printed numbers.
	 *
	 * @param array $entries   Entries from entries().
	 * @param array $questions The player's questions, in the order they render.
	 * @return array[] Question ID, player question number, printed label and section.
	 */
	public static function outline( array $entries, array $questions ) {
		$outline = array();
		foreach ( array_values( $questions ) as $index => $question ) {
			$entry = $entries[ (int) $question['id'] ] ?? null;
			if ( 'statement' === ( $question['settings']['type'] ?? '' ) || ( $entry && '' === $entry['label'] ) ) {
				continue;
			}
			$outline[] = array(
				'id'      => (int) $question['id'],
				'number'  => $index + 1,
				'label'   => $entry ? $entry['label'] : (string) ( $index + 1 ),
				'section' => $entry ? $entry['section'] : (string) ( $question['section'] ?? '' ),
			);
		}
		return $outline;
	}

	/**
	 * Answer fields for a printed choice question, so the learner answers by the printed letters.
	 * Each printed option takes the player's field with the same option text; when any option has
	 * no match, the player's own option list stays instead.
	 *
	 * @param array $printed  Printed question (the paper's learner view).
	 * @param array $question The player's question.
	 * @param array $attempt  Attempt.
	 * @param array $settings Sanitized paper settings.
	 * @return string[]|null Field HTML by printed option index, or null to keep the player's options.
	 */
	public static function choice_fields( array $printed, array $question, array $attempt, array $settings ) {
		$type = $question['settings']['type'] ?? '';
		if ( ! in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false' ), true ) ) {
			return null;
		}
		$model = PaperModel::question( $printed, $settings );
		if ( 'choice' !== $model['layout'] ) {
			return null;
		}
		$normalize = static function ( $text ) {
			return trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) ) ) );
		};
		$pool      = array();
		foreach ( (array) ( $question['questions'] ?? array() ) as $option ) {
			$pool[] = array( $normalize( $option['answer'] ?? '' ), $option );
		}
		$fields = array();
		foreach ( $model['options'] as $index => $option ) {
			$text  = $normalize( $option['text'] );
			$match = null;
			foreach ( $pool as $at => $candidate ) {
				if ( $candidate[0] === $text ) {
					$match = $candidate[1];
					unset( $pool[ $at ] );
					break;
				}
			}
			if ( ! $match ) {
				return null;
			}
			$question_id      = $match['question_id'] ?? $question['id'];
			$fields[ $index ] = sprintf(
				'<input type="%1$s" class="ohmylms-paper-choice-input" data-question-id="%2$s" name="%3$s" value="%4$s">',
				'multiple-choice' === $type ? 'checkbox' : 'radio',
				esc_attr( $question_id ),
				esc_attr( 'attempt[' . $attempt['id'] . '][quiz_question][' . $question_id . '][]' ),
				esc_attr( $match['id'] ?? '' )
			);
		}
		return $pool ? null : $fields;
	}

	/**
	 * The printed head of a player question box, with its flag button.
	 *
	 * @param array|null $entry     The question's paper entry, or null for a question the paper does not place.
	 * @param array      $question  The player's question.
	 * @param array      $attempt   Attempt.
	 * @param array      $settings  Sanitized paper settings.
	 * @param string     $statement The player's statement box, used when it holds answer fields.
	 * @param int        $number    The player's question number.
	 * @return array{html: string, classes: string[], statement: bool, choices: bool} The head, box
	 * classes, and whether the head took the statement box or the choice fields.
	 */
	public static function box_head( $entry, array $question, array $attempt, array $settings, $statement, $number ) {
		$result = array(
			'html'      => '',
			'classes'   => array(),
			'statement' => false,
			'choices'   => false,
		);
		$label  = $entry ? $entry['label'] : (string) $number;
		if ( $entry ) {
			$fields = self::choice_fields( $entry['question'], $question, $attempt, $settings );
			$args   = array( 'head_only' => null === $fields );
			if ( $fields ) {
				$args['choice_input'] = static function ( $option, $index ) use ( $fields ) {
					return $fields[ $index ];
				};
				$result['choices']    = true;
				$result['classes'][]  = 'has-paper-choices';
			} elseif ( preg_match( '/<(input|select|textarea)\b/i', (string) preg_replace( '/<input[^>]+type=["\']?hidden[^>]*>/i', '', $statement ) ) ) {
				// Blanks typed into the statement need the player's statement, fields and all.
				$args['prompt']      = $statement;
				$result['statement'] = true;
				$result['classes'][] = 'has-player-statement';
			}
			$result['classes'][] = 'is-paper-question';
			$result['html']      = PaperRenderer::question( $entry['question'], $label, $settings, false, 'A', $args );
		}
		if ( '' !== $label && 'statement' !== ( $question['settings']['type'] ?? '' ) ) {
			$result['html'] .= sprintf(
				'<button type="button" class="ohmylms-paper-flag" data-wp-interactive="ohmylms/paper" data-wp-context="%1$s" data-wp-on--click="actions.toggleFlag" data-wp-bind--aria-pressed="state.flagPressed" data-wp-class--is-flagged="state.isFlagged" aria-pressed="false"><span aria-hidden="true">⚑</span> %2$s</button>',
				esc_attr(
					wp_json_encode(
						array(
							'questionId' => (int) $question['id'],
							'label'      => $label,
						)
					)
				),
				/* translators: %s: question number. */
				esc_html( sprintf( __( 'Flag question %s for review', 'ohmylms' ), $label ) )
			);
		}
		return $result;
	}

	/**
	 * The continuous paper sheet with the player's question boxes in their printed places. Boxes the
	 * paper does not place come last, as the player shows them.
	 *
	 * @param array    $paper Paper data from student().
	 * @param string[] $boxes Question box HTML by question ID, in the player's order.
	 * @return string HTML.
	 */
	public static function sheet( array $paper, array $boxes ) {
		$settings = $paper['settings'];
		$html     = PaperRenderer::document(
			$paper['items'],
			array(
				'title'      => $paper['title'],
				'time_limit' => $paper['timeLimit'],
				'settings'   => $settings,
				'continuous' => true,
				'slot'       => static function ( $item ) use ( &$boxes ) {
					$id = (int) ( $item['question']['id'] ?? 0 );
					if ( ! isset( $boxes[ $id ] ) ) {
						// Not in this attempt: leave no empty place on the sheet.
						return null;
					}
					$box = $boxes[ $id ];
					unset( $boxes[ $id ] );
					return '<div class="ohmylms-paper-slot" data-question-id="' . esc_attr( $id ) . '">' . $box . '</div>';
				},
			)
		);
		foreach ( $boxes as $box ) {
			$html .= '<div class="ohmylms-print-block">' . $box . '</div>';
		}
		if ( PaperRenderer::has_math( $html ) ) {
			\OhMyLMS\Assessment\MathLive::enqueue_loader();
		}
		return sprintf(
			'<div class="ohmylms-print-page ohmylms-paper-student%1$s" style="--ohmylms-paper-font-size:%2$dpt;--ohmylms-paper-margin:%3$dmm;--ohmylms-paper-width:%4$s"><div class="ohmylms-print-sheet" data-ohmylms-latex>%5$s</div></div>',
			'serif' === $settings['font'] ? ' is-serif' : '',
			$settings['font_size'],
			$settings['margin'],
			'letter' === $settings['size'] ? '215.9mm' : '210mm',
			$html
		);
	}

	/**
	 * Question IDs in the order the paper prints them.
	 *
	 * @param array $items Paper items.
	 * @return int[] Question IDs.
	 */
	public static function order( array $items ) {
		$ids = array();
		foreach ( $items as $item ) {
			if ( 'question' === ( $item['kind'] ?? '' ) ) {
				$ids[] = (int) $item['question']['id'];
			} elseif ( ! empty( $item['items'] ) ) {
				$ids = array_merge( $ids, self::order( $item['items'] ) );
			}
		}
		return $ids;
	}

	/**
	 * Walk static document content in order; retain question renderers separately.
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param array  $result Decorations by question ID.
	 * @param string $pending HTML before the next question.
	 */
	private static function walk( array $blocks, array &$result, &$pending ) {
		foreach ( $blocks as $block ) {
			$name  = $block['blockName'] ?? '';
			$attrs = $block['attrs'] ?? array();
			if ( NativeQuizEditor::is_question( $block ) ) {
				$result[ (int) ( $attrs['questionId'] ?? 0 ) ] = $pending;
				$pending                                       = '';
				continue;
			}
			if ( PaperBlocks::HEADER === $name ) {
				$pending .= '<div class="ohmylms-paper-introduction">' . wp_kses_post( $attrs['school'] ?? '' ) . '<p>' . wp_kses_post( $attrs['subtitle'] ?? '' ) . '</p></div>';
			} elseif ( PaperBlocks::SECTION === $name ) {
				$pending .= '<h2 class="ohmylms-quiz-section-title">' . wp_kses_post( $attrs['title'] ?? '' ) . '</h2>';
			} elseif ( PaperBlocks::INSTRUCTIONS === $name ) {
				$pending .= '<h2>' . wp_kses_post( $attrs['heading'] ?? __( 'Instructions', 'ohmylms' ) ) . '</h2>';
			} elseif ( empty( $block['innerBlocks'] ) && in_array( $name, array( 'core/paragraph', 'core/heading', 'core/image', 'core/list-item', 'core/table', 'core/html' ), true ) ) {
				$pending .= wp_kses_post( $block['innerHTML'] ?? '' );
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				if ( 'core/list' === $name ) {
					$pending .= '<ul>';
				}
				self::walk( $block['innerBlocks'], $result, $pending );
				if ( 'core/list' === $name ) {
					$pending .= '</ul>';
				}
			}
		}
	}
}
