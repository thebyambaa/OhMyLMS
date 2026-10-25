<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * The print page of an exam's printable paper: the student paper and its answer key.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Exams;

use OhMyLMS\Assessment\QuestionSnapshot;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Assessment\Template;
use OhMyLMS\Learning\NativeQuizEditor;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\VersionPublisher;

defined( 'ABSPATH' ) || exit;

/**
 * An editor-only page that lays the exam out with the paper settings for the browser to print.
 * The student paper is built from each question's learner view, so answers never reach it.
 */
final class PrintPaper {
	const QUERY  = 'ohmylms_print';
	const SCRIPT = 'ohmylms-paper-print';
	const STYLE  = 'ohmylms-paper';

	/** Serve the print page before the theme renders anything. */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'render' ), 1 );
	}

	/** What the print page can print: the paper, its key, an answer sheet and a marked sheet. */
	const VARIANTS = array( 'paper', 'key', 'sheet', 'sheet-key' );

	/** Paper versions; version A is the paper as laid out in the editor. */
	const VERSIONS = array( 'A', 'B', 'C', 'D' );

	/**
	 * Link to the print page of an exam.
	 *
	 * @param int    $id      Quiz ID.
	 * @param string $variant One of VARIANTS; anything else prints the student paper.
	 * @param string $version Paper version letter.
	 * @return string Print URL.
	 */
	public static function url( $id, $variant = 'paper', $version = 'A' ) {
		$args = array(
			self::QUERY => (int) $id,
			'variant'   => in_array( $variant, self::VARIANTS, true ) ? $variant : 'paper',
		);
		if ( in_array( $version, self::VERSIONS, true ) && 'A' !== $version ) {
			$args['version'] = $version;
		}
		$args['_wpnonce'] = wp_create_nonce( self::QUERY . '_' . (int) $id );
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Jumble seed of a question in a paper version; questionSeed() in paperModel.mjs is identical.
	 *
	 * @param int    $question_id Question ID.
	 * @param string $version     Version letter.
	 * @return string Seed.
	 */
	public static function seed( $question_id, $version = 'A' ) {
		return 'A' === $version ? (string) $question_id : $question_id . ':' . $version;
	}

	/**
	 * Printed items in document order: core blocks as rendered HTML and questions as data.
	 *
	 * @param int    $id      Quiz ID.
	 * @param bool   $answers Whether to include answer keys the current user may see.
	 * @param string $version Paper version letter.
	 * @return array[] Items for the print bundle.
	 */
	public static function items( $id, $answers, $version = 'A' ) {
		$questions = array();
		foreach ( ohmylms_get_quiz( $id )->get_questions() as $placement ) {
			$questions[ (int) $placement['id'] ] = $placement;
		}
		$blocks = parse_blocks( NativeQuizEditor::editor_content( get_post( $id ) ) );
		return self::walk( $blocks, $id, $answers, $questions, $version );
	}

	/**
	 * Printed items of a list of blocks. Paper blocks keep their children; other containers
	 * holding questions print their contents in order.
	 *
	 * @param array  $blocks    Parsed blocks.
	 * @param int    $id        Quiz ID.
	 * @param bool   $answers   Whether to include answer keys.
	 * @param array  $questions Quiz placements by question ID.
	 * @param string $version   Paper version letter.
	 * @return array[] Items.
	 */
	private static function walk( array $blocks, $id, $answers, array $questions, $version ) {
		$items = array();
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';
			$span = ! empty( $block['attrs'][ PaperBlocks::SPAN ] );
			$item = null;
			if ( NativeQuizEditor::is_question( $block ) ) {
				$question_id = (int) ( $block['attrs']['questionId'] ?? 0 );
				$question    = isset( $questions[ $question_id ] ) ? self::question( $id, $questions[ $question_id ], $answers, $version ) : null;
				if ( $question ) {
					$item = array(
						'kind'     => 'question',
						'question' => $question,
					);
				}
			} elseif ( in_array( $name, PaperBlocks::names(), true ) ) {
				$item = array(
					'kind'  => 'paper',
					'name'  => $name,
					'attrs' => self::paper_attributes( $block['attrs'] ?? array() ),
					'items' => self::walk( $block['innerBlocks'] ?? array(), $id, $answers, $questions, $version ),
				);
			} elseif ( 'core/columns' === $name && self::has_question( $block['innerBlocks'] ?? array() ) ) {
				// Columns keep their layout on the paper, as the editor's Paper view shows them.
				$columns = array();
				foreach ( $block['innerBlocks'] as $column ) {
					$width     = (string) ( $column['attrs']['width'] ?? '' );
					$columns[] = array(
						'kind'  => 'layout',
						'name'  => 'core/column',
						'attrs' => array( 'width' => preg_match( '/^\d+(\.\d+)?(%|px|em|rem)$/', $width ) ? $width : '' ),
						'items' => self::walk( $column['innerBlocks'] ?? array(), $id, $answers, $questions, $version ),
					);
				}
				$item = array(
					'kind'  => 'layout',
					'name'  => 'core/columns',
					'attrs' => array(),
					'items' => $columns,
				);
			} elseif ( '' !== $name && self::has_question( $block['innerBlocks'] ?? array() ) ) {
				$items = array_merge( $items, self::walk( $block['innerBlocks'], $id, $answers, $questions, $version ) );
			} elseif ( '' !== $name ) {
				$html = trim( wp_kses_post( render_block( $block ) ) );
				if ( '' !== $html ) {
					$item = array(
						'kind' => 'html',
						'html' => $html,
					);
				}
			}
			if ( $item ) {
				$items[] = $item + array( 'span' => $span );
			}
		}
		return $items;
	}

	/**
	 * Paper block attributes for the page: text keeps bold, italic and line breaks only.
	 * Block comment attributes are not filtered when a document is saved, so they are here.
	 *
	 * @param mixed $value Attribute value.
	 * @return mixed Safe value; null for unsupported types.
	 */
	public static function paper_attributes( $value ) {
		if ( is_array( $value ) ) {
			$result = array();
			foreach ( $value as $key => $item ) {
				$key = is_int( $key ) ? $key : preg_replace( '/[^A-Za-z0-9_]/', '', (string) $key );
				if ( '' !== $key ) {
					$result[ $key ] = self::paper_attributes( $item );
				}
			}
			return $result;
		}
		if ( is_string( $value ) ) {
			return wp_kses(
				$value,
				array(
					'strong' => array(),
					'em'     => array(),
					'b'      => array(),
					'i'      => array(),
					'br'     => array(),
				)
			);
		}
		return is_bool( $value ) || is_int( $value ) || is_float( $value ) ? $value : null;
	}

	/**
	 * Whether any nested block is a question placement.
	 *
	 * @param array $blocks Parsed blocks.
	 * @return bool Contains a question.
	 */
	private static function has_question( array $blocks ) {
		foreach ( $blocks as $block ) {
			if ( NativeQuizEditor::is_question( $block ) || self::has_question( $block['innerBlocks'] ?? array() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * One question as the exam delivers it: the pinned version, else the current content.
	 * Both variants use the same seed, so a randomized question has the same numbers in the
	 * paper and its key.
	 *
	 * @param int    $quiz_id   Quiz ID.
	 * @param array  $placement Quiz placement.
	 * @param bool   $answers   Whether to include the answer key.
	 * @param string $version   Paper version letter.
	 * @return array|null Question data in the editor's shape, or null when unavailable.
	 */
	private static function question( $quiz_id, array $placement, $answers, $version = 'A' ) {
		$question_id = (int) $placement['id'];
		$content     = ! empty( $placement['pinned_version_id'] ) && Schema::ready() ? VersionPublisher::version( $placement['pinned_version_id'] ) : null;
		if ( $content && (int) $content['question_id'] !== $question_id ) {
			$content = null;
		}
		if ( ! $content ) {
			$content = VersionPublisher::content( $question_id );
		}
		if ( ! $content ) {
			return null;
		}
		$snapshot = QuestionSnapshot::from_row(
			$content + array(
				'question_id'   => $question_id,
				'id'            => 0,
				'version_no'    => 0,
				'question_uuid' => '',
			)
		)->instantiate( Template::seed_from( 'print:' . $quiz_id . ':' . $question_id ) );
		$options  = $snapshot->get_questions();
		usort(
			$options,
			static function ( $a, $b ) {
				return (int) ( $a['order_number'] ?? 0 ) <=> (int) ( $b['order_number'] ?? 0 );
			}
		);
		if ( $answers && AccessPolicy::can_use_question( $question_id ) ) {
			$private_settings = $snapshot->get_settings();
			foreach ( $private_settings['parts'] ?? array() as $index => $part ) {
				$private_settings['parts'][ $index ]['prompt'] = wp_kses_post( $part['prompt'] ?? '' ) . \OhMyLMS\ExamImport\FigureContent::html( $snapshot, 'print:' . $quiz_id, $part['id'] );
			}
			return array(
				'figure_part_html' => true,
				'id'          => $question_id,
				'name'        => $snapshot->get_name(),
				'description' => $snapshot->get_description() . \OhMyLMS\ExamImport\FigureContent::html( $snapshot, 'print:' . $quiz_id ),
				'settings'    => $private_settings,
				'questions'   => $options,
			);
		}
		$view     = $snapshot->student_view( array_column( $options, 'id' ), array(), array(), array(), 'print:' . $quiz_id );
		$settings = $view['settings'];
		$type     = $snapshot->get_type();
		// Option text and image only: no pairings, and no saved order where the order is the answer.
		$printed = static function ( $option ) {
			return array(
				'answer'    => (string) ( $option['answer'] ?? '' ),
				'image_url' => (string) ( $option['image_url'] ?? '' ),
			);
		};
		$rows    = array_map( $printed, $view['questions'] );
		if ( 'reorder' === $type ) {
			$texts = array_map( 'strval', array_column( $options, 'answer' ) );
			$rows  = array();
			foreach ( self::jumble( $texts, self::seed( $question_id, $version ), true ) as $index ) {
				$rows[] = $printed( $options[ $index ] );
			}
		} elseif ( 'matching' === $type ) {
			$labels = array();
			foreach ( $options as $option ) {
				$labels[] = (string) ( $option['matching_data']['label'] ?? '' );
			}
			$settings['match_right'] = array();
			foreach ( self::jumble( $labels, self::seed( $question_id, $version ), true ) as $index ) {
				$settings['match_right'][] = $labels[ $index ];
			}
			$rows = array_map( $printed, $options );
		}
		// Only what the paper renderer reads; the player's blank bank and blank layout are left out.
		return array(
			'id'          => $question_id,
			'public'      => true,
			'name'        => (string) $view['name'],
			'description' => (string) $view['description'],
			'settings'    => $settings,
			'questions'   => $rows,
		);
	}

	/**
	 * FNV-1a hash of a string's UTF-8 bytes; fnv1a() in paperTypes.mjs is identical.
	 *
	 * @param string $text Text.
	 * @return int Unsigned 32-bit hash.
	 */
	public static function hash( $text ) {
		$hash = 0x811c9dc5;
		foreach ( unpack( 'C*', (string) $text ) as $byte ) {
			$hash ^= $byte;
			$hash  = ( $hash * 0x01000193 ) & 0xFFFFFFFF;
		}
		return $hash;
	}

	/**
	 * The printed order of a list, as jumble() in paperTypes.mjs computes it in the editor.
	 *
	 * @param string[] $texts          Item texts, in answer order when $avoid_identity is set.
	 * @param string   $seed           Question seed.
	 * @param bool     $avoid_identity Rotate an order that would match the input.
	 * @return int[] Input indexes in printed order.
	 */
	public static function jumble( array $texts, $seed, $avoid_identity = false ) {
		$texts  = array_values( $texts );
		$hashes = array();
		foreach ( $texts as $index => $text ) {
			$hashes[ $index ] = self::hash( $seed . '|' . $text );
		}
		$order = array_keys( $texts );
		usort(
			$order,
			static function ( $a, $b ) use ( $hashes ) {
				$by_hash = $hashes[ $a ] <=> $hashes[ $b ];
				return 0 !== $by_hash ? $by_hash : $a <=> $b;
			}
		);
		if ( $avoid_identity && count( $order ) > 1 && array_keys( $order ) === $order ) {
			$order[] = array_shift( $order );
		}
		return $order;
	}

	/**
	 * CSS for the printed page box: size, margins and the page number footer.
	 *
	 * @param array $paper Sanitized paper settings.
	 * @return string CSS.
	 */
	public static function page_css( array $paper ) {
		$size   = 'letter' === $paper['size'] ? 'letter' : 'A4';
		$footer = '';
		if ( $paper['page_numbers'] ) {
			/* translators: 1: page number, 2: page count. */
			$format  = __( 'Page %1$s of %2$s', 'ohmylms' );
			$content = array();
			foreach ( preg_split( '/(%[12]\$s)/', $format, -1, PREG_SPLIT_DELIM_CAPTURE ) as $part ) {
				if ( '%1$s' === $part ) {
					$content[] = 'counter(page)';
				} elseif ( '%2$s' === $part ) {
					$content[] = 'counter(pages)';
				} elseif ( '' !== $part ) {
					// Escape quotes and line breaks for the CSS string, and "<" so it cannot close the style element.
					$content[] = '"' . str_replace( '<', '\\3c ', addcslashes( $part, "\\\"\n\r" ) ) . '"';
				}
			}
			$footer = sprintf( '@bottom-center{content:%s;font:9pt Arial,Helvetica,sans-serif;color:#50575e}', implode( ' ', $content ) );
		}
		return sprintf(
			'@page{size:%1$s;margin:%2$dmm;%3$s}.ohmylms-print-page{--ohmylms-paper-font-size:%4$dpt;--ohmylms-paper-margin:%2$dmm;--ohmylms-paper-width:%5$s}',
			$size,
			(int) $paper['margin'],
			$footer,
			(int) $paper['font_size'],
			'letter' === $paper['size'] ? '215.9mm' : '210mm'
		);
	}

	/** Render the print page and stop, when requested. */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presence check; the nonce is verified below.
		if ( ! isset( $_GET[ self::QUERY ] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified with the quiz-specific nonce below.
		$id    = absint( $_GET[ self::QUERY ] );
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( get_post_type( $id ) !== OHMYLMS_QUIZ_CPT || ! AccessPolicy::can_edit_quiz( $id ) || ! wp_verify_nonce( $nonce, self::QUERY . '_' . $id ) ) {
			wp_die( esc_html__( 'You cannot print this exam.', 'ohmylms' ), '', array( 'response' => 403 ) );
		}
		$variant = isset( $_GET['variant'] ) ? sanitize_key( wp_unslash( $_GET['variant'] ) ) : 'paper';
		$variant = in_array( $variant, self::VARIANTS, true ) ? $variant : 'paper';
		$key     = in_array( $variant, array( 'key', 'sheet-key' ), true );
		$quiz    = ohmylms_get_quiz( $id );
		$paper   = PaperSettings::sanitize( $quiz->get_settings()['paper'] ?? array() );
		$version = isset( $_GET['version'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['version'] ) ) ) : 'A';
		$index   = array_search( $version, self::VERSIONS, true );
		$version = false !== $index && $index < $paper['versions'] ? $version : 'A';
		$data    = array(
			'title'     => $quiz->get_name(),
			'variant'   => $variant,
			'version'   => $version,
			'settings'  => $paper,
			'items'     => self::items( $id, $key, $version ),
			'timeLimit' => array(
				'value' => (float) ( $quiz->get_settings()['time_limit']['value'] ?? 0 ),
				'type'  => sanitize_key( (string) ( $quiz->get_settings()['time_limit']['type'] ?? 'minutes' ) ),
			),
			'autoPrint' => isset( $_GET['print'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified above.
		);
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		show_admin_bar( false );

		$asset = require OHMYLMS_DIR . '/assets/dist/admin/paper-print.asset.php';
		wp_register_style( self::STYLE, plugins_url( 'assets/css/paper.css', OHMYLMS_FILE ), array(), (string) filemtime( OHMYLMS_DIR . '/assets/css/paper.css' ) );
		$dependencies = $asset['dependencies'];
		if ( \OhMyLMS\Assessment\MathLive::enabled() ) {
			// This runs before wp_enqueue_scripts, where the math scripts are normally registered.
			\OhMyLMS\Assessment\MathLive::configure();
			// The loader script provides the math configuration that loads the runtime on demand.
			$dependencies[] = 'ohmylms-mathlive-loader';
			wp_enqueue_style( 'ohmylms-mathlive', plugins_url( 'assets/css/mathlive.css', OHMYLMS_FILE ), array(), '0.111.1' );
		}
		wp_register_script( self::SCRIPT, plugins_url( 'assets/dist/admin/paper-print.js', OHMYLMS_FILE ), $dependencies, $asset['version'], true );
		wp_set_script_translations( self::SCRIPT, 'ohmylms' );
		$labels = array(
			'paper'     => '%s',
			/* translators: %s: exam title. */
			'key'       => __( 'Answer key: %s', 'ohmylms' ),
			/* translators: %s: exam title. */
			'sheet'     => __( 'Answer sheet: %s', 'ohmylms' ),
			/* translators: %s: exam title. */
			'sheet-key' => __( 'Marking sheet: %s', 'ohmylms' ),
		);
		$title  = sprintf( $labels[ $variant ], $quiz->get_name() );
		if ( $paper['versions'] > 1 ) {
			/* translators: 1: page title, 2: version letter. */
			$title = sprintf( __( '%1$s (version %2$s)', 'ohmylms' ), $title, $version );
		}
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $title ); ?></title>
		<?php
		wp_print_styles( array( self::STYLE, 'ohmylms-mathlive', 'ohmylms-figures' ) );
		// The CSS is built only from sanitized enumerations, integers and an escaped CSS string.
		printf( '<style id="ohmylms-print-page">%s</style>', self::page_css( $paper ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- See above.
		?>
</head>
<body class="ohmylms-print-page is-<?php echo esc_attr( $data['variant'] ); ?><?php echo 'serif' === $paper['font'] ? ' is-serif' : ''; ?>">
<div class="ohmylms-print-toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Print', 'ohmylms' ); ?>">
	<a class="ohmylms-print-back" href="<?php echo esc_url( (string) get_edit_post_link( $id, 'raw' ) ); ?>"><?php esc_html_e( '← Back to the exam', 'ohmylms' ); ?></a>
	<span class="ohmylms-print-toolbar-title"><?php echo esc_html( $title ); ?></span>
	<span class="ohmylms-print-status" role="status"><?php esc_html_e( 'Preparing the paper…', 'ohmylms' ); ?></span>
	<button type="button" class="ohmylms-print-button" disabled><?php esc_html_e( 'Print', 'ohmylms' ); ?></button>
</div>
<main id="ohmylms-print-root" class="ohmylms-print-sheet"></main>
<script type="application/json" id="ohmylms-print-data"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ); ?></script>
		<?php
		wp_print_scripts( array( self::SCRIPT, 'ohmylms-figures' ) );
		?>
</body>
</html>
		<?php
		exit;
	}
}
