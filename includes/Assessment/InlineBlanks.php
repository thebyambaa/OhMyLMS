<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Brace answers live in the authored title; public parts contain lengths only.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

/** Brace answers live in the authored title; public parts contain lengths only. */
final class InlineBlanks {
	/**
	 * Matches for Inline blanks.
	 *
	 * @param mixed $actual Actual.
	 * @param mixed $expected Expected.
	 * @param mixed $question Question.
	 */
	public static function matches( $actual, $expected, $question ) {
		$settings = method_exists( $question, 'get_settings' ) ? $question->get_settings() : array();
		if ( ! in_array( $settings['case_sensitive'] ?? true, array( false, 0, '0' ), true ) ) {
			return (string) $actual === (string) $expected;
		}
		// Unicode case matching works without requiring the mbstring extension.
		return preg_match( '/\A' . preg_quote( (string) $expected, '/' ) . '\z/iu', (string) $actual ) === 1;
	}
	/**
	 * Complete for Inline blanks.
	 *
	 * @param mixed $question Question.
	 * @param array $answer Answer.
	 */
	public static function complete( $question, array $answer ) {
		$count = count( self::parse( $question->get_name() )['answers'] );
		return ! $count || ( count( $answer ) === $count && ! array_filter(
			$answer,
			static function ( $value ) {
				return trim( (string) $value ) === '';
			}
		) );
	}
	/**
	 * Parse for Inline blanks.
	 *
	 * @param mixed $title Title.
	 */
	public static function parse( $title ) {
		preg_match_all( '/\{([^{}<>]+)\}/u', (string) $title, $matches, PREG_OFFSET_CAPTURE );
		$parts   = array();
		$answers = array();
		$offset  = 0;
		foreach ( $matches[0] as $i => $match ) {
			$answer = html_entity_decode( $matches[1][ $i ][0], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			if ( trim( $answer ) === '' ) {
				continue; }
			$parts[] = array( 'text' => substr( $title, $offset, $match[1] - $offset ) );
			preg_match_all( '/./us', $answer, $characters );
			$parts[]   = array( 'length' => count( $characters[0] ) );
			$answers[] = $answer;
			$offset    = $match[1] + strlen( $match[0] );
		}
		$parts[] = array( 'text' => substr( $title, $offset ) );
		return array(
			'parts'   => $parts,
			'answers' => $answers,
		);
	}

	/**
	 * Public view for Inline blanks.
	 *
	 * @param array $view View.
	 */
	public static function public_view( array $view ) {
		if ( ( $view['settings']['type'] ?? '' ) !== 'fill-in-the-blank' || isset( $view['inline_blanks'] ) ) {
			return $view; }
		$parsed = self::parse( $view['name'] );
		if ( ! $parsed['answers'] ) {
			return $view; }
		$view['inline_blanks'] = $parsed['parts'];
		if ( ! empty( $view['settings']['question_code'] ) ) {
			$view['description'] = ''; }
		if ( ( $view['settings']['blank_mode'] ?? '' ) === 'drag' ) {
			$bank = $parsed['answers'];
			shuffle( $bank );
			$view['blank_bank'] = array_values( $bank );
		}
		$view['name'] = implode(
			'',
			array_map(
				static function ( $part ) {
					return $part['text'] ?? '_____';
				},
				$parsed['parts']
			)
		);
		return $view;
	}

	/**
	 * Render for Inline blanks.
	 *
	 * @param array $view View.
	 * @param array $attempt Attempt.
	 */
	public static function render( array $view, array $attempt ) {
		$view = self::public_view( $view );
		if ( empty( $view['inline_blanks'] ) ) {
			return wp_kses_post( $view['name'] ); }
		$html  = '';
		$index = 0;
		foreach ( $view['inline_blanks'] as $part ) {
			if ( isset( $part['text'] ) ) {
				$html .= wp_kses_post( $part['text'] );
				continue; }
			$length = max( 1, (int) $part['length'] );
			$name   = 'attempt[' . ( $attempt['id'] ?? 0 ) . '][quiz_question][' . $view['id'] . '][]';
			$html  .= '<input type="text" class="ohmylms-text-input" autocomplete="off" aria-label="' . esc_attr(
				sprintf( /* translators: %d: number. */
					__( 'Blank %d', 'ohmylms' ),
					++$index
				)
			) . '" data-question-id="' . esc_attr( $view['id'] ) . '" name="' . esc_attr( $name ) . '" size="' . $length . '" style="display:inline-block;width:calc(' . $length . 'ch + 1.2em);min-width:0;max-width:100%;box-sizing:border-box;font:inherit;letter-spacing:inherit;padding:0.2em 0.5em;vertical-align:baseline">';
		}
		if ( ( $view['settings']['blank_mode'] ?? '' ) === 'drag' ) {
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'ohmylms-inline-blank-drag', plugins_url( 'assets/js/inline-blank-drag.js', OHMYLMS_FILE ), array(), OHMYLMS_VERSION, true );
				wp_enqueue_style( 'ohmylms-quiz-a11y', plugins_url( 'assets/css/quiz-a11y.css', OHMYLMS_FILE ), array(), OHMYLMS_VERSION );
			}
			$html = preg_replace( '/<input type="text"/', '<input type="text" readonly', $html );
			$bank = '';
			foreach ( $view['blank_bank'] ?? array() as $key => $answer ) {
				$bank .= '<button type="button" draggable="true" class="ohmylms-blank-token" data-blank-token="' . esc_attr( $key ) . '" data-blank-answer="' . esc_attr( $answer ) . '">' . htmlspecialchars( $answer, ENT_QUOTES, 'UTF-8' ) . '</button>';
			}
			$html = '<span class="ohmylms-drag-blanks" data-question-id="' . esc_attr( $view['id'] ) . '">' . $html . '<span class="ohmylms-blank-answer-bank" role="group" aria-label="' . esc_attr( __( 'Answer bank', 'ohmylms' ) ) . '">' . $bank . '</span></span>';
		}
		return $html;
	}
}
