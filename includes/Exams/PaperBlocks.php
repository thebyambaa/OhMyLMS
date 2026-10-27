<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Print-only blocks of an exam's printable paper.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

defined( 'ABSPATH' ) || exit;

/**
 * Header, instructions, sections, columns, page breaks, answer space and formula sheets. They lay out
 * the printed paper only: the online exam renders nothing for them, except the contents of a columns
 * section, which stay in place.
 */
final class PaperBlocks {
	const HEADER       = 'ohmylms/paper-header';
	const INSTRUCTIONS = 'ohmylms/paper-instructions';
	const SECTION      = 'ohmylms/paper-section';
	const COLUMNS      = 'ohmylms/paper-columns';
	const PAGE_BREAK   = 'ohmylms/paper-page-break';
	const ANSWER_SPACE = 'ohmylms/paper-answer-space';
	const FORMULA      = 'ohmylms/paper-formula-sheet';

	/** Attribute any block inside a columns section may carry to span every column. */
	const SPAN = 'ohmylmsPaperSpan';

	/**
	 * Register the blocks. The editor script registers their editing side only on exam screens,
	 * so other documents never offer them.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Every paper block name.
	 *
	 * @return string[] Block names.
	 */
	public static function names() {
		return array( self::HEADER, self::INSTRUCTIONS, self::SECTION, self::COLUMNS, self::PAGE_BREAK, self::ANSWER_SPACE, self::FORMULA );
	}

	/** Register server-side so the exam editor offers them; content lives in the block attributes. */
	public static function register() {
		foreach ( self::names() as $name ) {
			if ( \WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				continue;
			}
			register_block_type(
				$name,
				array(
					'api_version'     => 3,
					'render_callback' => self::COLUMNS === $name ? array( __CLASS__, 'render_columns' ) : '__return_empty_string',
				)
			);
		}
	}

	/**
	 * Online, a columns section is just its contents in order.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Rendered inner blocks.
	 * @return string Inner content.
	 */
	public static function render_columns( $attributes, $content ) {
		return $content;
	}
}
