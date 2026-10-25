<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping.
/**
 * Safe document decoration for continuous student papers.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Quiz;

use OhMyLMS\Learning\NativeQuizEditor;
use OhMyLMS\Exams\PaperBlocks;
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
	 * its script and styles. Questions carry no answer keys.
	 *
	 * @param int        $quiz_id  Quiz ID.
	 * @param array      $settings Quiz settings in effect for this delivery.
	 * @param array|null $items Optional learner-safe items for a single question preview.
	 * @return array|null Paper data, or null when the paper script is not built.
	 */
	public static function student( $quiz_id, array $settings, $items = null ) {
		$asset = OHMYLMS_DIR . '/assets/dist/admin/paper-student.asset.php';
		if ( ! file_exists( $asset ) ) {
			return null;
		}
		$asset = require $asset;
		wp_enqueue_style( PrintPaper::STYLE, plugins_url( 'assets/css/paper.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/paper.css' ) );
		\OhMyLMS\Assessment\MathLive::enqueue_loader();
		wp_enqueue_script( 'ohmylms-paper-student', plugins_url( 'assets/dist/admin/paper-student.js', OHMYLMS_FILE ), $asset['dependencies'], $asset['version'], true );
		if ( null === $items ) {
			$attempt  = ohmylms_get_quiz( $quiz_id )->get_quiz_attempt( get_current_user_id() );
			$delivery = $attempt ? \OhMyLMS\Assessment\AttemptItems::delivery( $attempt['id'] ) : array();
			$items    = PrintPaper::items( $quiz_id, false );
			if ( $delivery ) {
				$by_id  = array_column( $delivery, null, 'id' );
				$freeze = static function ( array $entries ) use ( &$freeze, $by_id ) {
					$result = array();
					foreach ( $entries as $entry ) {
						if ( 'question' === ( $entry['kind'] ?? '' ) ) {
							$id = $entry['question']['id'];
							if ( ! isset( $by_id[ $id ] ) ) {
								continue;
							}
							$entry['question'] = $by_id[ $id ] + array( 'public' => true );
						} elseif ( isset( $entry['items'] ) ) {
							$entry['items'] = $freeze( $entry['items'] );
						}
						$result[] = $entry;
					}
					return $result;
				};
				$items  = $freeze( $items );
			}
		}
		wp_set_script_translations( 'ohmylms-paper-student', 'ohmylms' );
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
