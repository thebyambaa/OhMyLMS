<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Completeness checks for imported questions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz\Exchange;

use OhMyLMS\Assessment\InlineBlanks;
use OhMyLMS\Assessment\Template;
use OhMyLMS\Extensions\Authoring;
use OhMyLMS\Extensions\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * The server validates each type's settings, but the question editor is what stops an author
 * from saving a single-choice question with no correct answer. An import has no editor, so these
 * rules run here: a file is only accepted when every question is complete.
 */
final class QuestionCheck {

	/**
	 * Why a question is not usable, if it is not.
	 *
	 * @param array $question Normalized question (see Document).
	 * @return string[] Messages; empty when the question is complete.
	 */
	public static function problems( array $question ) {
		$settings = $question['settings'];
		$type     = (string) $settings['type'];
		$options  = $question['options'];
		$problems = array();

		$score = $settings['score'] ?? array();
		if ( ! is_array( $score ) || ( isset( $score['value'] ) && ( ! is_numeric( $score['value'] ) || $score['value'] < 0 ) ) ) {
			$problems[] = __( 'The score must be a number of 0 or more (settings.score.value).', 'ohmylms' );
		}

		$prompt = trim( wp_strip_all_tags( $question['name'] . ' ' . $question['description'] ) );
		if ( '' === $prompt && in_array( $type, array( 'single-choice', 'multiple-choice', 'true-false', 'statement', 'short-text', 'long-text', 'matching', 'reorder', 'numerical', 'fill-in-the-blank' ), true ) ) {
			$problems[] = __( 'The question text is empty.', 'ohmylms' );
		}

		$filled  = 0;
		$correct = 0;
		foreach ( $options as $option ) {
			if ( '' !== trim( $option['answer'] ) || '' !== $option['image_url'] ) {
				++$filled;
			}
			$correct += $option['is_correct'] ? 1 : 0;
		}

		switch ( $type ) {
			case 'single-choice':
			case 'multiple-choice':
			case 'true-false':
				if ( count( $options ) < 2 || $filled < count( $options ) ) {
					$problems[] = __( 'It needs at least two options and every option needs text or an image.', 'ohmylms' );
				}
				if ( 'true-false' === $type && 2 !== count( $options ) ) {
					$problems[] = __( 'A true / false question has exactly two options.', 'ohmylms' );
				}
				if ( 'multiple-choice' === $type ? $correct < 1 : 1 !== $correct ) {
					$problems[] = 'multiple-choice' === $type
						? __( 'Mark at least one option as correct.', 'ohmylms' )
						: __( 'Mark exactly one option as correct.', 'ohmylms' );
				}
				break;
			case 'statement':
				$ok = false;
				foreach ( $options as $option ) {
					$ok = $ok || ( $option['is_correct'] && '' !== trim( $option['answer'] ) );
				}
				if ( ! $ok ) {
					$problems[] = __( 'It needs at least one correct answer text.', 'ohmylms' );
				}
				break;
			case 'fill-in-the-blank':
				$blanks = InlineBlanks::parse( $question['name'] )['answers'];
				if ( ! $blanks ) {
					// Older questions keep the expected text in a correct option instead of {braces}.
					$ok = false;
					foreach ( $options as $option ) {
						$ok = $ok || ( $option['is_correct'] && '' !== trim( $option['answer'] ) );
					}
					if ( ! $ok ) {
						$problems[] = __( 'Write the answers in braces in the question name, for example "The capital of France is {Paris}."', 'ohmylms' );
					}
				}
				break;
			case 'matching':
				if ( count( $options ) < 2 ) {
					$problems[] = __( 'It needs at least two pairs.', 'ohmylms' );
				}
				foreach ( $options as $position => $option ) {
					$label = $option['matching_data']['label'] ?? '';
					$image = $option['matching_data']['image_url'] ?? '';
					if ( ( '' === trim( $option['answer'] ) && '' === $option['image_url'] ) || ( '' === trim( $label ) && '' === $image ) ) {
						/* translators: %d: pair number */
						$problems[] = sprintf( __( 'Pair %d needs an item and a matching definition.', 'ohmylms' ), $position + 1 );
					}
				}
				break;
			case 'reorder':
				if ( count( $options ) < 2 || $filled < count( $options ) ) {
					$problems[] = __( 'It needs at least two items and every item needs text or an image.', 'ohmylms' );
				}
				break;
		}

		return array_merge( $problems, self::template_problems( $question ) );
	}

	/**
	 * Prove a randomized template works with the question's real text and options.
	 *
	 * The writer validates the template against the settings alone; this builds example
	 * questions from the full question, so a placeholder that no variable defines is caught.
	 *
	 * @param array $question Normalized question.
	 * @return string[]
	 */
	private static function template_problems( array $question ) {
		$settings = $question['settings'];
		if ( ! Template::has( $settings ) ) {
			return array();
		}
		$definition = Registry::get( 'question', (string) $settings['type'] );
		if ( ! $definition ) {
			return array();
		}
		$options = array();
		foreach ( $question['options'] as $option ) {
			$row = array(
				'answer'     => $option['answer'],
				'is_correct' => $option['is_correct'] ? 1 : 0,
			);
			if ( isset( $option['matching_data'] ) ) {
				$row['matching_data'] = $option['matching_data'];
			}
			$options[] = $row;
		}
		$result = Template::validate(
			$settings,
			$question['name'],
			$question['description'],
			$options,
			static function ( $example ) use ( $definition ) {
				return Authoring::check( $definition, $example );
			}
		);
		/* translators: %s: reason */
		return true === $result ? array() : array( sprintf( __( 'Randomized numbers: %s', 'ohmylms' ), $result ) );
	}
}
