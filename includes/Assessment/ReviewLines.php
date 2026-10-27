<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Readable answer-review lines for question types whose answers are stored by ID.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Assessment;

use OhMyLMS\Extensions\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Turns stored answers and answer keys into text a learner can read on the result screen.
 *
 * Types that store IDs (tokens, rows, slots, targets) would otherwise show codes such as
 * "t1 = 1" and no correct answer. A registered type can supply its own lines with the
 * optional definition callbacks review_given( $given, $settings ) and
 * review_expected( $settings ), each returning a list of strings. Both callbacks read only
 * the settings they are given, so they also work for frozen versions.
 */
final class ReviewLines {
	/**
	 * The learner's answer as readable lines.
	 *
	 * @param string $type     Question type.
	 * @param array  $given    Stored answer.
	 * @param array  $settings Frozen question settings.
	 * @return string[]|null Null when the caller should use its generic lines.
	 */
	public static function given( $type, array $given, array $settings ) {
		$definition = Registry::get( 'question', $type );
		if ( is_callable( $definition['review_given'] ?? null ) ) {
			return self::strings( (array) call_user_func( $definition['review_given'], $given, $settings ) );
		}
		switch ( $type ) {
			case 'crossword':
				return self::crossword_lines( $settings, $given );
			case 'wordsearch':
				return self::wordsearch_lines( $settings, $given );
			case 'categorize':
				$labels = self::labels( $settings['buckets'] ?? array(), 'label' );
				$lines  = array();
				foreach ( (array) ( $settings['items'] ?? array() ) as $item ) {
					$bucket = $given[ $item['id'] ?? '' ] ?? '';
					if ( is_scalar( $bucket ) && '' !== (string) $bucket ) {
						$lines[] = ( $item['text'] ?? '' ) . ' → ' . ( $labels[ (string) $bucket ] ?? $bucket );
					}
				}
				return $lines;
			case 'dropdown-blanks':
				return self::blank_lines( array_column( (array) ( $settings['slots'] ?? array() ), 'id' ), $given );
			case 'multi-blank':
				return self::blank_lines( Interactive::blank_ids( $settings ), $given );
			case 'hot-text':
				$chosen = array_keys(
					array_filter(
						$given,
						static function ( $value ) {
							return '1' === (string) $value;
						}
					)
				);
				return self::token_texts( $settings, $chosen );
			case 'match-table-grid':
				return self::grid_lines( $settings, $given, true );
			case 'labeling':
				$lines = array();
				foreach ( array_values( (array) ( $settings['targets'] ?? array() ) ) as $index => $target ) {
					$value = $given[ $target['id'] ?? '' ] ?? '';
					if ( is_scalar( $value ) && '' !== (string) $value ) {
						/* translators: %d: label number. */
						$lines[] = sprintf( __( 'Label %d', 'ohmylms' ), $index + 1 ) . ': ' . $value;
					}
				}
				return $lines;
			case 'hotspot':
				if ( is_numeric( $given['x'] ?? null ) && is_numeric( $given['y'] ?? null ) ) {
					return array( self::point( $given['x'], $given['y'], '%' ) );
				}
				return array();
			case 'audio-response':
			case 'video-response':
				$media = (string) ( $given['media'] ?? '' );
				if ( '' === $media ) {
					return array();
				}
				// A recording is a stored file; an embedded data link would be unreadable text.
				return array( 0 === strpos( $media, 'data:' ) ? __( 'Recording submitted', 'ohmylms' ) : $media );
			case 'gameshow':
			case 'timed-true-false':
			case 'whack-a-mole':
			case 'flying-fruit':
				$chosen = array_column( (array) ( $settings['choices'] ?? array() ), 'text', 'id' )[ $given['choice'] ?? '' ] ?? '';
				return '' === $chosen ? array() : array( $chosen );
			case 'hangman':
				$guesses = trim( (string) ( $given['guesses'] ?? '' ) );
				return '' === $guesses ? array() : array( $guesses );
			case 'interactive-video':
				$lines = array();
				foreach ( (array) ( $settings['checkpoints'] ?? array() ) as $checkpoint ) {
					$value = $given[ $checkpoint['id'] ?? '' ] ?? '';
					if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
						$lines[] = ( $checkpoint['prompt'] ?? '' ) . ' → ' . trim( (string) $value );
					}
				}
				return $lines;
			case 'graphing':
				$points = json_decode( (string) ( $given['points'] ?? '[]' ), true );
				return is_array( $points ) && $points ? array( self::points( $points ) ) : array();
		}
		return null;
	}

	/**
	 * The correct answer as readable lines.
	 *
	 * @param string $type     Question type.
	 * @param array  $settings Frozen question settings.
	 * @return string[]|null Null when the caller should use its generic lines.
	 */
	public static function expected( $type, array $settings ) {
		$definition = Registry::get( 'question', $type );
		if ( is_callable( $definition['review_expected'] ?? null ) ) {
			return self::strings( (array) call_user_func( $definition['review_expected'], $settings ) );
		}
		switch ( $type ) {
			case 'crossword':
			case 'wordsearch':
				$lines = array();
				foreach ( (array) ( $settings['entries'] ?? array() ) as $entry ) {
					$word    = implode( '', WordPuzzle::letters( $entry['answer'] ) );
					$lines[] = 'crossword' === $type || ! empty( $settings['with_clues'] ) ? $entry['clue'] . ': ' . $word : $word;
				}
				return $lines;
			case 'categorize':
				return self::strings( Interactive::expected( $type, $settings ) );
			case 'dropdown-blanks':
			case 'multi-blank':
				$ids = 'multi-blank' === $type ? Interactive::blank_ids( $settings ) : array_column( (array) ( $settings['slots'] ?? array() ), 'id' );
				return self::blank_lines( $ids, self::unmarked( Interactive::expected( $type, $settings ) ) );
			case 'hot-text':
				return self::token_texts( $settings, (array) ( $settings['correct'] ?? array() ) );
			case 'match-table-grid':
				return self::grid_lines( $settings, (array) ( $settings['key'] ?? array() ), false );
			case 'labeling':
				$lines = array();
				foreach ( array_values( (array) ( $settings['targets'] ?? array() ) ) as $index => $target ) {
					/* translators: %d: label number. */
					$lines[] = sprintf( __( 'Label %d', 'ohmylms' ), $index + 1 ) . ': ' . ( $target['answer'] ?? '' );
				}
				return $lines;
			case 'hotspot':
				$lines = array();
				foreach ( array_values( (array) ( $settings['zones'] ?? array() ) ) as $index => $zone ) {
					/* translators: 1: area number, 2: position. */
					$lines[] = sprintf( __( 'Area %1$d: around %2$s', 'ohmylms' ), $index + 1, self::point( $zone['x'] ?? 0, $zone['y'] ?? 0, '%' ) );
				}
				return $lines;
			case 'gameshow':
			case 'timed-true-false':
			case 'whack-a-mole':
			case 'flying-fruit':
				$correct = array_column( (array) ( $settings['choices'] ?? array() ), 'text', 'id' )[ $settings['correct'] ?? '' ] ?? '';
				return '' === $correct ? array() : array( $correct );
			case 'hangman':
				return array_map( 'strval', array_values( (array) ( $settings['accepted'] ?? array() ) ) );
			case 'interactive-video':
				$lines = array();
				foreach ( (array) ( $settings['checkpoints'] ?? array() ) as $checkpoint ) {
					$lines[] = ( $checkpoint['prompt'] ?? '' ) . ' → ' . ( $checkpoint['answer'] ?? '' );
				}
				return $lines;
			case 'graphing':
				$points = (array) ( $settings['points'] ?? array() );
				if ( 'line' === ( $settings['mode'] ?? '' ) && 2 === count( $points ) ) {
					/* translators: 1: first point, 2: second point. */
					return array( sprintf( __( 'A line through %1$s and %2$s', 'ohmylms' ), self::point( $points[0]['x'] ?? 0, $points[0]['y'] ?? 0 ), self::point( $points[1]['x'] ?? 0, $points[1]['y'] ?? 0 ) ) );
				}
				return $points ? array( self::points( $points ) ) : array();
		}
		return null;
	}

	/**
	 * Reconstruct each crossword word from the learner's cells, keeping gaps visible.
	 *
	 * @param array $settings Frozen layout and clues.
	 * @param array $given    Cell answers.
	 * @return string[] Readable clue and answer pairs.
	 */
	private static function crossword_lines( array $settings, array $given ) {
		if ( ! array_filter( $given, 'is_scalar' ) ) {
			return array();
		}
		$lines = array();
		foreach ( (array) ( $settings['entries'] ?? array() ) as $entry ) {
			$word   = '';
			$filled = false;
			foreach ( WordPuzzle::cells( $entry ) as $cell ) {
				$value  = $given[ $cell ] ?? '';
				$value  = is_scalar( $value ) ? WordPuzzle::letters( (string) $value ) : array();
				$letter = $value[0] ?? '';
				$filled = $filled || '' !== $letter;
				$word  .= '' !== $letter ? $letter : '_';
			}
			$lines[] = $entry['clue'] . ': ' . ( $filled ? $word : __( 'Not answered', 'ohmylms' ) );
		}
		return $lines;
	}

	/**
	 * Show found words instead of internal wordsearch path coordinates.
	 *
	 * @param array $settings Frozen grid and entries.
	 * @param array $given    Selected endpoint pairs.
	 * @return string[] Words, with clues when enabled.
	 */
	private static function wordsearch_lines( array $settings, array $given ) {
		$targets = array();
		foreach ( (array) ( $settings['entries'] ?? array() ) as $entry ) {
			$cells = WordPuzzle::cells( $entry );
			$targets[ WordPuzzle::path_key( reset( $cells ) . ':' . end( $cells ) ) ] = $entry;
		}
		$lines = array();
		$seen  = array();
		$size  = (int) ( $settings['size'] ?? 0 );
		foreach ( $given as $path ) {
			if ( ! is_scalar( $path ) || '' === (string) $path ) {
				continue;
			}
			if ( ! preg_match( '/\Ar(\d{1,2})c(\d{1,2}):r(\d{1,2})c(\d{1,2})\z/', (string) $path, $match ) ) {
				$lines[] = __( 'Invalid selection', 'ohmylms' );
				continue;
			}
			$key = WordPuzzle::path_key( (string) $path );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( isset( $targets[ $key ] ) ) {
				$entry   = $targets[ $key ];
				$word    = implode( '', WordPuzzle::letters( $entry['answer'] ) );
				$lines[] = ! empty( $settings['with_clues'] ) ? $entry['clue'] . ': ' . $word : $word;
				continue;
			}
			list( , $r1, $c1, $r2, $c2 ) = array_map( 'intval', $match );
			$length                      = max( abs( $r2 - $r1 ), abs( $c2 - $c1 ) );
			if ( min( $r1, $c1, $r2, $c2 ) < 0 || max( $r1, $c1, $r2, $c2 ) >= $size || $length < 1 || ( $r1 !== $r2 && $c1 !== $c2 && abs( $r2 - $r1 ) !== abs( $c2 - $c1 ) ) ) {
				$lines[] = __( 'Invalid selection', 'ohmylms' );
				continue;
			}
			$word = '';
			for ( $index = 0; $index <= $length; ++$index ) {
				$row   = $r1 + ( $r2 <=> $r1 ) * $index;
				$col   = $c1 + ( $c2 <=> $c1 ) * $index;
				$word .= $settings['grid'][ $row ][ $col ] ?? '';
			}
			/* translators: %s: letters selected in the wordsearch. */
			$lines[] = sprintf( __( '%s (not a target word)', 'ohmylms' ), $word );
		}
		return $lines;
	}

	/**
	 * "Blank 1: value" lines, in blank order, for answers keyed by blank ID.
	 *
	 * @param string[] $ids   Blank IDs in display order.
	 * @param array    $given Answers keyed by blank ID.
	 * @return string[]
	 */
	private static function blank_lines( array $ids, array $given ) {
		$lines = array();
		foreach ( array_values( $ids ) as $index => $id ) {
			$value = $given[ $id ] ?? '';
			$value = is_array( $value ) ? implode( ', ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ), 'strlen' ) ) : trim( (string) $value );
			if ( '' !== $value ) {
				/* translators: %d: blank number. */
				$lines[] = sprintf( __( 'Blank %d', 'ohmylms' ), $index + 1 ) . ': ' . $value;
			}
		}
		return $lines;
	}

	/**
	 * Turn "{id} = value" lines from Interactive::expected() into a map keyed by ID.
	 *
	 * @param string[] $lines Lines.
	 * @return array
	 */
	private static function unmarked( array $lines ) {
		$map = array();
		foreach ( $lines as $line ) {
			if ( preg_match( '/^\{([^}]*)\} = (.*)$/s', (string) $line, $match ) ) {
				$map[ $match[1] ] = $match[2];
			}
		}
		return $map;
	}

	/**
	 * Texts of the tokens with the given IDs, in reading order.
	 *
	 * @param array    $settings Settings with a tokens list.
	 * @param string[] $ids      Token IDs.
	 * @return string[]
	 */
	private static function token_texts( array $settings, array $ids ) {
		$lines = array();
		foreach ( (array) ( $settings['tokens'] ?? array() ) as $token ) {
			if ( in_array( (string) ( $token['id'] ?? '' ), array_map( 'strval', $ids ), true ) ) {
				$lines[] = (string) ( $token['text'] ?? '' );
			}
		}
		return $lines;
	}

	/**
	 * "Row → column" lines for a match grid; $answers is the learner's or the key.
	 *
	 * @param array $settings Settings with rows and columns.
	 * @param array $answers  Row ID to column ID (or a list/JSON list when multiple).
	 * @param bool  $decode   Whether multiple answers arrive as JSON strings.
	 * @return string[]
	 */
	private static function grid_lines( array $settings, array $answers, $decode ) {
		$columns = self::labels( $settings['columns'] ?? array(), 'label' );
		$lines   = array();
		foreach ( (array) ( $settings['rows'] ?? array() ) as $row ) {
			$value = $answers[ $row['id'] ?? '' ] ?? null;
			if ( $decode && is_string( $value ) && ! empty( $settings['multiple'] ) ) {
				$value = json_decode( $value, true );
			}
			$ids = array_filter( (array) $value, 'is_scalar' );
			if ( ! $ids ) {
				continue;
			}
			$names   = array_map(
				static function ( $id ) use ( $columns ) {
					return $columns[ (string) $id ] ?? (string) $id;
				},
				$ids
			);
			$lines[] = ( $row['label'] ?? '' ) . ' → ' . implode( ', ', $names );
		}
		return $lines;
	}

	/**
	 * Labels keyed by ID.
	 *
	 * @param mixed  $list  List of rows with an id.
	 * @param string $field Label field.
	 * @return array
	 */
	private static function labels( $list, $field ) {
		$map = array();
		foreach ( (array) $list as $entry ) {
			if ( is_array( $entry ) && isset( $entry['id'] ) ) {
				$map[ (string) $entry['id'] ] = (string) ( $entry[ $field ] ?? '' );
			}
		}
		return $map;
	}

	/**
	 * Format a coordinate pair.
	 *
	 * @param mixed  $x      X.
	 * @param mixed  $y      Y.
	 * @param string $suffix Unit suffix.
	 * @return string
	 */
	private static function point( $x, $y, $suffix = '' ) {
		return '(' . round( (float) $x, 2 ) . $suffix . ', ' . round( (float) $y, 2 ) . $suffix . ')';
	}

	/**
	 * Format a list of points.
	 *
	 * @param array $points Points with x and y.
	 * @return string
	 */
	private static function points( array $points ) {
		return implode(
			', ',
			array_map(
				function ( $point ) {
					return is_array( $point ) ? self::point( $point['x'] ?? 0, $point['y'] ?? 0 ) : '';
				},
				$points
			)
		);
	}

	/**
	 * Keep non-empty string lines.
	 *
	 * @param array $lines Lines.
	 * @return string[]
	 */
	private static function strings( array $lines ) {
		return array_values( array_filter( array_map( 'strval', array_filter( $lines, 'is_scalar' ) ), 'strlen' ) );
	}
}
