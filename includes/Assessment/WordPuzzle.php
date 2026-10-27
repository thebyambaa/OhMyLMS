<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the plugin PSR-4 class mapping; see docs/WORDWALL-ACTIVITIES.md.
/**
 * Frozen crossword and wordsearch definitions and server grading.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/** Layouts are authored once, validated and frozen with the question version. */
final class WordPuzzle {
	const TYPES      = array( 'crossword', 'wordsearch' );
	const DIRECTIONS = array(
		'across'        => array( 0, 1 ),
		'down'          => array( 1, 0 ),
		'diagonal-down' => array( 1, 1 ),
		'diagonal-up'   => array( -1, 1 ),
		'left'          => array( 0, -1 ),
		'up'            => array( -1, 0 ),
		'up-left'       => array( -1, -1 ),
		'down-left'     => array( 1, -1 ),
	);

	/**
	 * Split uppercase letters into written characters.
	 *
	 * @param string $text Word.
	 * @return array Characters.
	 */
	public static function letters( $text ) {
		preg_match_all( '/\X/u', mb_strtoupper( trim( $text ), 'UTF-8' ), $letters );
		return $letters[0];
	}

	/**
	 * Stable cell keys along a word.
	 *
	 * @param array $entry Private entry.
	 * @return array Cell IDs.
	 */
	public static function cells( array $entry ) {
		$step = self::DIRECTIONS[ $entry['direction'] ] ?? array( 0, 1 );
		$out  = array();
		foreach ( self::letters( $entry['answer'] ) as $index => $letter ) {
			$out[] = 'r' . ( $entry['row'] + $step[0] * $index ) . 'c' . ( $entry['col'] + $step[1] * $index );
		}
		return $out;
	}

	/**
	 * A path and its reverse identify the same found word.
	 *
	 * @param string $path Two endpoints.
	 * @return string Canonical endpoint key.
	 */
	public static function path_key( $path ) {
		$ends = explode( ':', $path );
		sort( $ends, SORT_STRING );
		return implode( ':', $ends );
	}

	/**
	 * Reject invalid bounds, intersections, disconnected crosswords and bad grids.
	 *
	 * @param string $type Puzzle type.
	 * @param array  $settings Private settings.
	 * @return bool|string Validity or actionable error.
	 */
	public static function validate_settings( $type, array $settings ) {
		$size    = $settings['size'] ?? 0;
		$entries = $settings['entries'] ?? array();
		if ( 1 !== ( $settings['puzzle_version'] ?? null ) || ! is_int( $size ) || $size < 4 || $size > 12 || ! is_array( $entries ) || ! array_is_list( $entries ) || ! $entries || count( $entries ) > 12 ) {
			return __( 'Use a grid from 4 to 12 and between 1 and 12 words. Generate a layout before saving.', 'ohmylms' );
		}
		foreach ( array( 'partial_credit', 'with_clues', 'allow_diagonal', 'allow_backward' ) as $flag ) {
			if ( isset( $settings[ $flag ] ) && ! is_bool( $settings[ $flag ] ) ) {
				return __( 'Puzzle options must be enabled or disabled.', 'ohmylms' );
			}
		}
		$ids    = array();
		$words  = array();
		$map    = array();
		$owners = array();
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || ! is_string( $entry['id'] ?? null ) || ! preg_match( '/\Aw[a-z0-9_-]{1,19}\z/', $entry['id'] ) || isset( $ids[ $entry['id'] ] ) || ! is_string( $entry['answer'] ?? null ) || mb_strlen( $entry['answer'] ) > 100 || ! is_string( $entry['clue'] ?? null ) || mb_strlen( $entry['clue'] ) > 500 ) {
				return __( 'Give each item a unique ID, a word and a short clue.', 'ohmylms' );
			}
			$letters = self::letters( $entry['answer'] );
			$word    = implode( '', $letters );
			if ( count( $letters ) < 2 || count( $letters ) > $size || in_array( $word, $words, true ) || ( 'wordsearch' === $type && in_array( implode( '', array_reverse( $letters ) ), $words, true ) ) || array_filter(
				$letters,
				static function ( $letter ) {
					return ! preg_match( '/\A\p{L}\p{M}*\z/u', $letter ); }
			) ) {
				return __( 'Use distinct words with 2 to 12 letters, without spaces or punctuation, that fit the grid.', 'ohmylms' );
			}
			if ( ( 'crossword' === $type || ! empty( $settings['with_clues'] ) ) && '' === trim( $entry['clue'] ) ) {
				return __( 'Enter a clue for each word.', 'ohmylms' );
			}
			$direction = $entry['direction'] ?? '';
			if ( ! is_string( $direction ) || ! isset( self::DIRECTIONS[ $direction ] ) || ( 'crossword' === $type && ! in_array( $direction, array( 'across', 'down' ), true ) ) ) {
				return __( 'Choose a valid word direction.', 'ohmylms' );
			}
			$step = self::DIRECTIONS[ $direction ];
			if ( 'wordsearch' === $type && ( ( empty( $settings['allow_diagonal'] ) && $step[0] && $step[1] ) || ( empty( $settings['allow_backward'] ) && in_array( $direction, array( 'left', 'up', 'up-left', 'down-left' ), true ) ) ) ) {
				return __( 'This layout uses a disabled direction. Generate it again.', 'ohmylms' );
			}
			if ( ! is_int( $entry['row'] ?? null ) || ! is_int( $entry['col'] ?? null ) ) {
				return __( 'Generate a layout with whole-number cell positions.', 'ohmylms' );
			}
			foreach ( $letters as $index => $letter ) {
				$row = $entry['row'] + $step[0] * $index;
				$col = $entry['col'] + $step[1] * $index;
				$key = 'r' . $row . 'c' . $col;
				if ( min( $row, $col ) < 0 || max( $row, $col ) >= $size || ( isset( $map[ $key ] ) && $map[ $key ] !== $letter ) ) {
					return __( 'Words conflict or leave the grid. Generate a new layout or adjust their positions.', 'ohmylms' );
				}
				if ( 'crossword' === $type && isset( $owners[ $key ] ) ) {
					foreach ( $owners[ $key ] as $other ) {
						if ( $ids[ $other ]['direction'] === $direction ) {
							return __( 'Crossword words can cross but cannot overlap in the same direction.', 'ohmylms' );
						}
					}
				}
				$map[ $key ]      = $letter;
				$owners[ $key ][] = $entry['id'];
			}
			$ids[ $entry['id'] ] = $entry;
			$words[]             = $word;
		}
		if ( 'crossword' === $type ) {
			$connected   = array( $entries[0]['id'] );
			$entry_count = count( $entries );
			for ( $pass = 0; $pass < $entry_count; ++$pass ) {
				foreach ( $owners as $cell_owners ) {
					if ( array_intersect( $connected, $cell_owners ) ) {
						$connected = array_values( array_unique( array_merge( $connected, $cell_owners ) ) );
					}
				}
			}
			if ( count( $connected ) !== count( $entries ) ) {
				return __( 'All crossword words must connect. Try words with shared letters or a larger grid.', 'ohmylms' );
			}
			foreach ( $entries as $entry ) {
				$step   = self::DIRECTIONS[ $entry['direction'] ];
				$length = count( self::letters( $entry['answer'] ) );
				for ( $index = -1; $index <= $length; ++$index ) {
					$row = $entry['row'] + $step[0] * $index;
					$col = $entry['col'] + $step[1] * $index;
					$key = 'r' . $row . 'c' . $col;
					if ( -1 === $index || $length === $index ) {
						if ( isset( $map[ $key ] ) ) {
							return __( 'Leave an empty square before and after each crossword word.', 'ohmylms' );
						}
					} elseif ( 1 === count( $owners[ $key ] ) ) {
						foreach ( array( -1, 1 ) as $side ) {
							$neighbor = 'r' . ( $row + $step[1] * $side ) . 'c' . ( $col + $step[0] * $side );
							if ( isset( $map[ $neighbor ] ) ) {
								return __( 'Crossword words must cross, with a space between neighboring words.', 'ohmylms' );
							}
						}
					}
				}
			}
		} else {
			$grid = $settings['grid'] ?? array();
			if ( ! is_array( $grid ) || ! array_is_list( $grid ) || count( $grid ) !== $size ) {
				return __( 'Generate the wordsearch grid before saving.', 'ohmylms' );
			}
			foreach ( $grid as $row => $cells ) {
				if ( ! is_int( $row ) || ! is_array( $cells ) || ! array_is_list( $cells ) || count( $cells ) !== $size ) {
					return __( 'The wordsearch grid has an invalid row.', 'ohmylms' );
				}
				foreach ( $cells as $col => $letter ) {
					if ( ! is_int( $col ) || ! is_string( $letter ) || mb_strlen( $letter ) > 16 || 1 !== count( self::letters( $letter ) ) || ! preg_match( '/\A\p{L}\p{M}*\z/u', $letter ) || ( isset( $map[ 'r' . $row . 'c' . $col ] ) && mb_strtoupper( $letter, 'UTF-8' ) !== $map[ 'r' . $row . 'c' . $col ] ) ) {
						return __( 'Grid letters must match the placed words. Generate the grid again.', 'ohmylms' );
					}
				}
			}
		}
		return true;
	}

	/**
	 * Learner projection excludes answers and wordsearch placements.
	 *
	 * @param string $type Puzzle type.
	 * @param array  $settings Private settings.
	 * @return array Safe configuration.
	 */
	public static function public_view( $type, array $settings ) {
		$size   = $settings['size'];
		$starts = array();
		foreach ( $settings['entries'] as $entry ) {
			$starts[] = $entry['row'] * $size + $entry['col'];
		}
		$starts = array_values( array_unique( $starts ) );
		sort( $starts, SORT_NUMERIC );
		$clues = array();
		$cells = array();
		foreach ( $settings['entries'] as $index => $entry ) {
			$clue = array(
				'id'     => $entry['id'],
				'clue'   => 'wordsearch' === $type && empty( $settings['with_clues'] ) ? implode( '', self::letters( $entry['answer'] ) ) : $entry['clue'],
				'length' => count( self::letters( $entry['answer'] ) ),
			);
			if ( 'crossword' === $type ) {
				$clue += array(
					'row'       => $entry['row'],
					'col'       => $entry['col'],
					'direction' => $entry['direction'],
					'number'    => array_search( $entry['row'] * $size + $entry['col'], $starts, true ) + 1,
				);
				$cells = array_merge( $cells, self::cells( $entry ) );
			}
			$clues[] = $clue;
		}
		$view = array(
			'size'           => $size,
			'puzzle_version' => 1,
			'clues'          => $clues,
		);
		if ( 'wordsearch' === $type ) {
			$view['grid'] = $settings['grid'];
		} else {
			$view['cells'] = array_values( array_unique( $cells ) );
		}
		return $view;
	}

	/**
	 * Grade words using server definitions, never browser claims.
	 *
	 * @param string $type Puzzle type.
	 * @param array  $answer Keyed cells or endpoint paths.
	 * @param array  $settings Private frozen settings.
	 * @return array Grade with per-word feedback.
	 */
	public static function grade( $type, array $answer, array $settings ) {
		$parts       = array();
		$hits        = 0;
		$invalid     = count( $answer ) > ( 'crossword' === $type ? 144 : count( $settings['entries'] ) );
		$valid_cells = array();
		foreach ( $settings['entries'] as $entry ) {
			$cells       = self::cells( $entry );
			$valid_cells = array_merge( $valid_cells, $cells );
		}
		$submitted     = array();
		$matched_words = array();
		foreach ( $answer as $key => $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 100 ) {
				$invalid = true;
				continue;
			}
			if ( 'crossword' === $type ) {
				$invalid = $invalid || ! in_array( $key, $valid_cells, true ) || count( self::letters( $value ) ) > 1;
			} else {
				if ( '' === trim( $value ) ) {
					continue;
				}
				$path        = self::path_key( $value );
				$invalid     = $invalid || ! preg_match( '/\Ar\d{1,2}c\d{1,2}:r\d{1,2}c\d{1,2}\z/', $value ) || in_array( $path, $submitted, true );
				$submitted[] = $path;
				if ( preg_match( '/\Ar(\d{1,2})c(\d{1,2}):r(\d{1,2})c(\d{1,2})\z/', $path, $ends ) ) {
					$r1       = (int) $ends[1];
					$c1       = (int) $ends[2];
					$r2       = (int) $ends[3];
					$c2       = (int) $ends[4];
					$distance = max( abs( $r2 - $r1 ), abs( $c2 - $c1 ) );
					$straight = $r1 === $r2 || $c1 === $c2 || ( ! empty( $settings['allow_diagonal'] ) && abs( $r2 - $r1 ) === abs( $c2 - $c1 ) );
					if ( $distance > 0 && $straight && max( $r1, $c1, $r2, $c2 ) < $settings['size'] ) {
						$given = array();
						for ( $i = 0; $i <= $distance; ++$i ) {
							$given[] = mb_strtoupper( $settings['grid'][ $r1 + ( $r2 <=> $r1 ) * $i ][ $c1 + ( $c2 <=> $c1 ) * $i ], 'UTF-8' );
						}
						foreach ( $settings['entries'] as $entry ) {
							$expected = self::letters( $entry['answer'] );
							if ( $expected === $given || array_reverse( $expected ) === $given ) {
								$matched_words[ $path ] = $entry['id'];
							}
						}
					}
				}
			}
		}
		foreach ( $settings['entries'] as $entry ) {
			if ( 'crossword' === $type ) {
				$given = '';
				foreach ( self::cells( $entry ) as $cell ) {
					$given .= is_string( $answer[ $cell ] ?? null ) ? implode( '', self::letters( $answer[ $cell ] ) ) : '';
				}
				$correct = ! $invalid && implode( '', self::letters( $entry['answer'] ) ) === $given;
			} else {
				$correct = ! $invalid && in_array( $entry['id'], $matched_words, true );
			}
			$hits                 += $correct ? 1 : 0;
			$parts[ $entry['id'] ] = array(
				'correct'  => $correct,
				'fraction' => $correct ? 1 : 0,
			);
		}
		$wrong    = 'wordsearch' === $type ? count( $submitted ) - count( array_unique( $matched_words ) ) : 0;
		$fraction = max( 0, $hits - $wrong ) / count( $settings['entries'] );
		$correct  = ! $invalid && 0 === $wrong && count( $settings['entries'] ) === $hits;
		return array(
			'correct'  => $correct,
			'fraction' => false === ( $settings['partial_credit'] ?? true ) ? ( $correct ? 1 : 0 ) : $fraction,
			'manual'   => false,
			'parts'    => $parts,
		);
	}
}
