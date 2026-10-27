<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Bulk question import for past papers and any other exam or subject.
 *
 * Rows describe questions (JSON or CSV). A dry run validates everything and reports what would
 * happen; a real run writes through the same transactional writer the editor uses, so every
 * question follows the normal permission, validation, versioning and skill-mapping rules.
 * Re-running the same rows is safe: each question carries an import key built from its exam,
 * paper, year, section, number and language, so unchanged rows are skipped and changed rows
 * update the existing question instead of creating a duplicate.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\QuestionBank;

use OhMyLMS\Assessment\Structured;
use OhMyLMS\Extensions\Registry;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/** Parses, validates and writes question rows. */
final class BulkImport {

	const MAX_ROWS     = 200;
	const KEY_META     = '_ohmylms_import_key';
	const HASH_META    = '_ohmylms_import_hash';
	const PAPER_META   = '_ohmylms_import_paper';
	const DIFFICULTIES = array( 'easy', 'standard', 'challenge' );

	/** Column name (lowercase, underscores) to canonical field. */
	const ALIASES = array(
		'exam'            => 'exam',
		'paper'           => 'paper',
		'year'            => 'year',
		'section'         => 'section',
		'number'          => 'number',
		'question_number' => 'number',
		'no'              => 'number',
		'q'               => 'number',
		'language'        => 'language',
		'lang'            => 'language',
		'type'            => 'type',
		'title'           => 'title',
		'question'        => 'question',
		'body'            => 'question',
		'stem'            => 'question',
		'answers'         => 'answers',
		'choices'         => 'answers',
		'correct'         => 'correct',
		'answer'          => 'correct',
		'key'             => 'correct',
		'explanation'     => 'explanation',
		'solution'        => 'explanation',
		'skills'          => 'skills',
		'skill'           => 'skills',
		'skill_codes'     => 'skills',
		'family'          => 'family',
		'family_id'       => 'family',
		'difficulty'      => 'difficulty',
		'marks'           => 'marks',
		'score'           => 'marks',
		'points'          => 'marks',
		'tolerance'       => 'tolerance',
		'tolerance_type'  => 'tolerance_type',
		'unit'            => 'unit',
		'image'           => 'image',
		'settings'        => 'settings',
		'parts'           => 'parts',
		'options_raw'     => 'options_raw',
		'confidence'      => 'confidence',
		'ai_confidence'   => 'confidence',
		'notes'           => 'notes',
		'ai_notes'        => 'notes',
		'rationale'       => 'notes',
	);

	/**
	 * Read rows from JSON or CSV text.
	 *
	 * @param string|array $input Text, or rows that are already decoded.
	 * @param string       $format json, csv or auto.
	 * @return array|\WP_Error List of raw rows, each an associative array.
	 */
	public static function parse( $input, $format = 'auto' ) {
		if ( is_array( $input ) ) {
			return self::rows_from_array( $input );
		}
		$text = trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $input ) );
		if ( '' === $text ) {
			return self::error( 'ohmylms_import_empty', __( 'Nothing to import. Paste rows or choose a file.', 'ohmylms' ) );
		}
		if ( 'auto' === $format ) {
			$format = in_array( $text[0], array( '[', '{' ), true ) ? 'json' : 'csv';
		}
		if ( 'json' === $format ) {
			$decoded = json_decode( $text, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				/* translators: %s: JSON error message. */
				return self::error( 'ohmylms_import_json', sprintf( __( 'The JSON could not be read: %s', 'ohmylms' ), json_last_error_msg() ) );
			}
			return self::rows_from_array( $decoded );
		}
		return self::parse_csv( $text );
	}

	/**
	 * Accept a list of rows or an object holding them under "rows".
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array|\WP_Error
	 */
	private static function rows_from_array( $data ) {
		if ( is_array( $data ) && isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
			$data = $data['rows'];
		}
		if ( ! is_array( $data ) || ! $data || array_keys( $data ) !== range( 0, count( $data ) - 1 ) ) {
			return self::error( 'ohmylms_import_shape', __( 'Provide a list of question rows.', 'ohmylms' ) );
		}
		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				return self::error( 'ohmylms_import_shape', __( 'Every row must be an object with named fields.', 'ohmylms' ) );
			}
		}
		return $data;
	}

	/**
	 * Read CSV with a header row; quoted cells may span lines.
	 *
	 * @param string $text CSV text.
	 * @return array|\WP_Error
	 */
	public static function parse_csv( $text ) {
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream.
		fwrite( $handle, $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory stream.
		rewind( $handle );
		$header = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
			return self::error( 'ohmylms_import_empty', __( 'The CSV has no header row.', 'ohmylms' ) );
		}
		$header = array_map(
			static function ( $name ) {
				return trim( strtolower( preg_replace( '/[\s-]+/', '_', (string) $name ) ), "_ \t" );
			},
			$header
		);
		$rows   = array();
		while ( true ) {
			$cells = fgetcsv( $handle, 0, ',', '"', '' );
			if ( false === $cells ) {
				break;
			}
			if ( array( null ) === $cells || '' === trim( implode( '', $cells ) ) ) {
				continue;
			}
			$row = array();
			foreach ( $header as $i => $name ) {
				if ( '' !== $name && isset( $cells[ $i ] ) && '' !== $cells[ $i ] ) {
					$row[ $name ] = $cells[ $i ];
				}
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream.
		return $rows ? $rows : self::error( 'ohmylms_import_empty', __( 'The CSV has a header but no rows.', 'ohmylms' ) );
	}

	/**
	 * Canonical field names, trimmed values and list fields split.
	 *
	 * @param array $raw One raw row.
	 * @return array
	 */
	public static function normalize_row( array $raw ) {
		$row = array(
			'exam'           => '',
			'paper'          => '',
			'year'           => '',
			'section'        => '',
			'number'         => '',
			'language'       => 'en',
			'type'           => 'single-choice',
			'title'          => '',
			'question'       => '',
			'answers'        => array(),
			'correct'        => array(),
			'explanation'    => '',
			'skills'         => array(),
			'family'         => '',
			'difficulty'     => 'standard',
			'marks'          => '',
			'tolerance'      => '',
			'tolerance_type' => 'absolute',
			'unit'           => '',
			'image'          => '',
			'settings'       => array(),
			'parts'          => null,
			'options_raw'    => null,
			'confidence'     => '',
			'notes'          => '',
		);
		foreach ( $raw as $name => $value ) {
			$field = self::ALIASES[ trim( strtolower( preg_replace( '/[\s-]+/', '_', (string) $name ) ) ) ] ?? null;
			if ( null === $field ) {
				continue;
			}
			if ( in_array( $field, array( 'answers', 'correct' ), true ) ) {
				$value         = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : $value;
				$row[ $field ] = self::list_of( $value, '|' );
			} elseif ( 'skills' === $field ) {
				$row[ $field ] = self::list_of( $value, ';,|' );
			} elseif ( in_array( $field, array( 'settings', 'options_raw', 'parts' ), true ) ) {
				$row[ $field ] = self::json_value( $value );
				if ( 'parts' === $field && null === $row[ $field ] ) {
					$row[ $field ] = array();
				}
			} elseif ( is_scalar( $value ) ) {
				$row[ $field ] = trim( (string) $value );
			}
		}
		$row['type']       = '' === $row['type'] ? 'single-choice' : strtolower( $row['type'] );
		$row['language']   = '' === $row['language'] ? 'en' : strtolower( $row['language'] );
		$row['difficulty'] = '' === $row['difficulty'] ? 'standard' : strtolower( $row['difficulty'] );
		if ( '' === $row['tolerance_type'] ) {
			$row['tolerance_type'] = 'absolute';
		}
		return $row;
	}

	/**
	 * Split a cell into trimmed non-empty items, or keep a list as it is.
	 *
	 * @param mixed  $value Cell or list.
	 * @param string $delimiters Characters that separate items.
	 * @return string[]
	 */
	private static function list_of( $value, $delimiters ) {
		$items = is_array( $value ) ? $value : preg_split( '/[' . preg_quote( $delimiters, '/' ) . ']/', (string) $value );
		$items = array_map(
			static function ( $item ) {
				return is_scalar( $item ) ? trim( (string) $item ) : '';
			},
			(array) $items
		);
		return array_values( array_filter( $items, 'strlen' ) );
	}

	/**
	 * Decode an object or list given as JSON text, or keep it when already decoded.
	 *
	 * @param mixed $value Cell or structure.
	 * @return array|null
	 */
	private static function json_value( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		$decoded = json_decode( (string) $value, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Stable identity of a question across uploads: exam, paper, year, section, number, language.
	 *
	 * @param array $row Normalized row.
	 * @return string Empty when the row cannot be identified.
	 */
	public static function key( array $row ) {
		if ( '' === $row['number'] || ( '' === $row['exam'] && '' === $row['paper'] ) ) {
			return '';
		}
		$parts = array( $row['exam'], $row['paper'], $row['year'], $row['section'], $row['number'], $row['language'] );
		$key   = strtolower( trim( preg_replace( '/\s+/', ' ', implode( '|', $parts ) ) ) );
		return strlen( $key ) <= 190 ? $key : '';
	}

	/**
	 * Check one normalized row without touching the database.
	 *
	 * @param array    $row Normalized row.
	 * @param callable $type_known Returns true when a question type is registered.
	 * @return array{errors:string[],warnings:string[]}
	 */
	public static function validate_row( array $row, callable $type_known ) {
		$errors   = array();
		$warnings = array();
		if ( '' === self::key( $row ) ) {
			$errors[] = __( 'Each row needs a question number and an exam or paper name (they identify the question when you upload again).', 'ohmylms' );
		}
		if ( ! $type_known( $row['type'] ) ) {
			/* translators: %s: question type. */
			$errors[] = sprintf( __( 'Question type "%s" is not available.', 'ohmylms' ), $row['type'] );
		}
		// Types described by their own settings can carry all their content there, so the stem may be empty.
		if ( '' === $row['question'] && in_array( $row['type'], array( 'single-choice', 'multiple-choice', 'true-false', 'numerical' ), true ) ) {
			$errors[] = __( 'The question text is empty.', 'ohmylms' );
		}
		if ( ! in_array( $row['difficulty'], self::DIFFICULTIES, true ) ) {
			$errors[] = __( 'Difficulty must be easy, standard or challenge.', 'ohmylms' );
		}
		if ( 'structured' !== $row['type'] && ! $row['skills'] ) {
			$errors[] = __( 'Add at least one skill code. Questions without skills cannot count toward progress.', 'ohmylms' );
		}
		if ( '' !== $row['marks'] && ( ! is_numeric( $row['marks'] ) || (float) $row['marks'] < 0 || ! is_finite( (float) $row['marks'] ) ) ) {
			$errors[] = __( 'Marks must be a number of zero or more.', 'ohmylms' );
		}
		if ( '' !== $row['confidence'] && ( ! is_numeric( $row['confidence'] ) || (float) $row['confidence'] < 0 || (float) $row['confidence'] > 1 ) ) {
			$errors[] = __( 'Confidence must be a number from 0 to 1.', 'ohmylms' );
		}
		if ( '' !== $row['year'] && ! preg_match( '/^\d{4}$/', $row['year'] ) ) {
			$warnings[] = __( 'Year is not four digits; it will be kept as written.', 'ohmylms' );
		}
		if ( 'structured' !== $row['type'] && null !== $row['parts'] ) {
			$errors[] = __( 'The parts field is only supported for structured questions.', 'ohmylms' );
		}
		$built = self::build_answers( $row );
		foreach ( $built['errors'] as $message ) {
			$errors[] = $message;
		}
		if ( 'structured' === $row['type'] && is_array( $built['settings']['parts'] ?? null ) && in_array( 'written', array_column( $built['settings']['parts'], 'kind' ), true ) ) {
			$warnings[] = __( 'Written parts require teacher marking; rubric notes do not automatically award method marks.', 'ohmylms' );
		}
		if ( in_array( $row['type'], array( 'short-text', 'long-text' ), true ) ) {
			$warnings[] = __( 'Short and long text answers are marked by hand, so this question cannot be used in practice or move SmartScore.', 'ohmylms' );
		}
		return array(
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}

	/**
	 * Answer options and type settings for a row.
	 *
	 * @param array $row Normalized row.
	 * @return array{options:?array,settings:array,errors:string[]}
	 */
	public static function build_answers( array $row ) {
		$errors   = array();
		$settings = array();
		$options  = null;
		$answers  = $row['answers'];
		switch ( $row['type'] ) {
			case 'structured':
				return self::build_structured( $row );
			case 'single-choice':
			case 'multiple-choice':
				if ( is_array( $row['options_raw'] ) && $row['options_raw'] ) {
					// Full option rows (images, odd characters) take the place of the answers and correct columns.
					$options = self::clean_options( $row['options_raw'], $errors );
					$right   = count( array_filter( array_column( $options, 'is_correct' ) ) );
					if ( count( $options ) < 2 || count( $options ) > 12 ) {
						$errors[] = __( 'Provide between 2 and 12 answers.', 'ohmylms' );
					} elseif ( ! $right ) {
						$errors[] = __( 'Mark the correct answer.', 'ohmylms' );
					} elseif ( 'single-choice' === $row['type'] && $right > 1 ) {
						$errors[] = __( 'A single-choice question has exactly one correct answer.', 'ohmylms' );
					}
					break;
				}
				if ( count( $answers ) < 2 || count( $answers ) > 12 ) {
					$errors[] = __( 'Provide between 2 and 12 answers.', 'ohmylms' );
					break;
				}
				$chosen = array();
				foreach ( $row['correct'] as $token ) {
					$index = self::answer_index( $token, $answers );
					if ( null === $index ) {
						/* translators: %s: the correct-answer value from the row. */
						$errors[] = sprintf( __( 'The correct answer "%s" matches none of the answers.', 'ohmylms' ), $token );
						continue;
					}
					$chosen[ $index ] = true;
				}
				if ( ! $row['correct'] ) {
					$errors[] = __( 'Mark the correct answer.', 'ohmylms' );
				} elseif ( 'single-choice' === $row['type'] && count( $chosen ) > 1 ) {
					$errors[] = __( 'A single-choice question has exactly one correct answer.', 'ohmylms' );
				}
				$options = self::options( $answers, array_keys( $chosen ) );
				break;
			case 'true-false':
				if ( is_array( $row['options_raw'] ) && $row['options_raw'] ) {
					$options = self::clean_options( $row['options_raw'], $errors );
					if ( 2 !== count( $options ) || 1 !== count( array_filter( array_column( $options, 'is_correct' ) ) ) ) {
						$errors[] = __( 'A true/false question has exactly two answers, one of them correct.', 'ohmylms' );
					}
					break;
				}
				$answers = $answers ? $answers : array( __( 'True', 'ohmylms' ), __( 'False', 'ohmylms' ) );
				$token   = strtolower( $row['correct'][0] ?? '' );
				if ( in_array( $token, array( 't', 'true', 'yes', '1', 'a' ), true ) ) {
					$index = 0;
				} elseif ( in_array( $token, array( 'f', 'false', 'no', '0', 'b' ), true ) ) {
					$index = 1;
				} else {
					$index    = null;
					$errors[] = __( 'For true/false, the correct value must be true or false.', 'ohmylms' );
				}
				if ( 2 !== count( $answers ) ) {
					$errors[] = __( 'A true/false question has exactly two answers.', 'ohmylms' );
				}
				$options = self::options( $answers, null === $index ? array() : array( $index ) );
				break;
			case 'numerical':
				$value = $row['correct'][0] ?? '';
				if ( '' === $value || ! is_numeric( str_replace( ',', '.', $value ) ) ) {
					$errors[] = __( 'A numerical question needs a number as its correct answer.', 'ohmylms' );
					break;
				}
				if ( '' !== $row['tolerance'] && ( ! is_numeric( $row['tolerance'] ) || (float) $row['tolerance'] < 0 ) ) {
					$errors[] = __( 'Tolerance must be a number of zero or more.', 'ohmylms' );
				}
				if ( ! in_array( $row['tolerance_type'], array( 'absolute', 'relative' ), true ) ) {
					$errors[] = __( 'Tolerance type must be absolute or relative.', 'ohmylms' );
				}
				$settings = array(
					'answer'         => (float) str_replace( ',', '.', $value ),
					'tolerance'      => '' === $row['tolerance'] ? 0 : (float) $row['tolerance'],
					'tolerance_type' => $row['tolerance_type'],
				);
				if ( '' !== $row['unit'] ) {
					$settings['unit'] = mb_substr( $row['unit'], 0, 40 );
				}
				$options = array();
				break;
			case 'fill-in-the-blank':
				if ( ! preg_match( '/\{[^{}]+\}/', $row['question'] ) ) {
					$errors[] = __( 'Put each answer in braces inside the text, for example "The capital is {Paris}."', 'ohmylms' );
				}
				$options = array();
				break;
			default:
				// Any other registered type is described by its own settings and options.
				if ( ! is_array( $row['settings'] ) || ! $row['settings'] ) {
					/* translators: %s: question type. */
					$errors[] = sprintf( __( 'Type "%s" needs a "settings" object (and "options_raw" if it uses answer options).', 'ohmylms' ), $row['type'] );
				}
				$settings = is_array( $row['settings'] ) ? $row['settings'] : array();
				$options  = is_array( $row['options_raw'] ) ? $row['options_raw'] : array();
		}
		if ( in_array( $row['type'], array( 'single-choice', 'multiple-choice', 'true-false', 'numerical', 'fill-in-the-blank' ), true ) && is_array( $row['settings'] ) ) {
			// Settings beyond what the columns express (hints, partial credit, randomization) are kept; the columns win.
			$settings += array_diff_key( $row['settings'], array_flip( array( 'type', 'score', 'explanation' ) ) );
		}
		return array(
			'options'  => $options,
			'settings' => $settings,
			'errors'   => $errors,
		);
	}

	/**
	 * Option rows given as JSON, reduced to the fields the writer accepts.
	 *
	 * @param array    $raw Option rows from the options_raw field.
	 * @param string[] $errors Problems found, added to.
	 * @return array[]
	 */
	private static function clean_options( array $raw, array &$errors ) {
		$options = array();
		foreach ( array_values( $raw ) as $index => $option ) {
			if ( ! is_array( $option ) || ! is_scalar( $option['answer'] ?? '' ) ) {
				$errors[] = __( 'Each option must be an object with answer text.', 'ohmylms' );
				return array();
			}
			$clean = array(
				'answer'       => (string) ( $option['answer'] ?? '' ),
				'is_correct'   => ! empty( $option['is_correct'] ) && 'false' !== $option['is_correct'],
				'order_number' => $index + 1,
			);
			foreach ( array( 'thumbnail_id', 'image_url', 'matching_data' ) as $field ) {
				if ( isset( $option[ $field ] ) && '' !== $option[ $field ] && array() !== $option[ $field ] ) {
					$clean[ $field ] = $option[ $field ];
				}
			}
			$options[] = $clean;
		}
		return $options;
	}

	/**
	 * Build multipart settings without storing import-only skill tokens in the answer key.
	 *
	 * @param array $row Normalized import row.
	 * @return array
	 */
	private static function build_structured( array $row ) {
		$settings = is_array( $row['settings'] ) ? $row['settings'] : array();
		$errors   = array();
		if ( null !== $row['parts'] && isset( $settings['parts'] ) && $row['parts'] !== $settings['parts'] ) {
			$errors[] = __( 'Provide parts either in parts or settings.parts, not two different definitions.', 'ohmylms' );
		}
		$parts = null !== $row['parts'] ? $row['parts'] : ( $settings['parts'] ?? array() );
		if ( ! is_array( $parts ) || ! $parts || array_keys( $parts ) !== range( 0, count( $parts ) - 1 ) || count( $parts ) > 50 ) {
			return array(
				'options'  => array(),
				'settings' => $settings,
				'errors'   => array( __( 'Provide a list of 1 to 50 structured parts.', 'ohmylms' ) ),
			);
		}
		foreach ( $parts as $part ) {
			if ( ! is_array( $part ) ) {
				$errors[] = __( 'Each part must be an object.', 'ohmylms' );
				break;
			}
			foreach ( array( 'id', 'label', 'prompt', 'rubric', 'unit' ) as $field ) {
				if ( isset( $part[ $field ] ) && ! is_scalar( $part[ $field ] ) ) {
					$errors[] = __( 'Part IDs, labels, prompts, rubrics and units must be text.', 'ohmylms' );
				}
			}
			if ( isset( $part['accepted'] ) && ( ! is_array( $part['accepted'] ) || array_filter(
				$part['accepted'],
				static function ( $value ) {
					return ! is_string( $value ); }
			) ) ) {
				$errors[] = __( 'Accepted text answers must be a list of strings.', 'ohmylms' );
			}
			if ( isset( $part['answers'] ) && ( ! is_array( $part['answers'] ) || ! $part['answers'] ) ) {
				$errors[] = __( 'Numerical answer alternatives must be a non-empty list.', 'ohmylms' );
			}
			if ( isset( $part['tolerance'] ) && ( ! is_numeric( $part['tolerance'] ) || ! is_finite( (float) $part['tolerance'] ) || (float) $part['tolerance'] < 0 ) ) {
				$errors[] = __( 'Tolerance must be a finite number of zero or more.', 'ohmylms' );
			}
			if ( isset( $part['tolerance_type'] ) && ! in_array( $part['tolerance_type'], array( 'absolute', 'relative' ), true ) ) {
				$errors[] = __( 'Tolerance type must be absolute or relative.', 'ohmylms' );
			}
		}
		if ( $errors ) {
			return array(
				'options'  => array(),
				'settings' => $settings,
				'errors'   => $errors,
			);
		}
		$settings['parts'] = $parts;
		$valid             = Structured::validate_settings( $settings );
		if ( true !== $valid ) {
			$errors[] = $valid;
		}
		$total = 0.0;
		foreach ( $parts as $index => $part ) {
			if ( ! is_array( $part ) ) {
				continue;
			}
			$marks = $part['marks'] ?? 1;
			if ( ! is_numeric( $marks ) || ! is_finite( (float) $marks ) || (float) $marks < 0 ) {
				$errors[] = __( 'Part marks must be finite numbers of zero or more.', 'ohmylms' );
			} else {
				$total += (float) $marks;
			}
			if ( ! self::part_skill_tokens( $part, $row['skills'] ) ) {
				/* translators: %s: structured part label or ID. */
				$errors[] = sprintf( __( 'Add at least one skill for part "%s", or provide row skills as a fallback.', 'ohmylms' ), (string) ( $part['id'] ?? $index ) );
			}
			unset( $settings['parts'][ $index ]['skills'] );
		}
		if ( ! is_finite( $total ) || ( '' !== $row['marks'] && abs( (float) $row['marks'] - $total ) > 0.00001 ) ) {
			$errors[] = __( 'Question marks must equal the sum of its part marks.', 'ohmylms' );
		}
		$settings['score'] = array(
			'enabled' => true,
			'value'   => $total,
		);
		$settings['type']  = 'structured';
		return array(
			'options'  => array(),
			'settings' => $settings,
			'errors'   => $errors,
		);
	}

	/**
	 * Read a part's explicit skills or inherit the row mapping when omitted.
	 *
	 * @param array $part Structured part.
	 * @param array $fallback Row skill tokens.
	 * @return array
	 */
	private static function part_skill_tokens( array $part, array $fallback ) {
		return array_key_exists( 'skills', $part ) ? self::list_of( $part['skills'], ';,|' ) : $fallback;
	}

	/**
	 * Read raw parts from either supported import format.
	 *
	 * @param array $row Normalized row.
	 * @return array
	 */
	private static function import_parts( array $row ) {
		$parts = $row['parts'] ?? ( $row['settings']['parts'] ?? array() );
		return is_array( $parts ) ? $parts : array();
	}

	/**
	 * Collect only the skill tokens actually used by this row for batch lookup.
	 *
	 * @param array $row Normalized row.
	 * @return array
	 */
	public static function skill_tokens( array $row ) {
		if ( 'structured' !== $row['type'] ) {
			return $row['skills'];
		}
		$tokens = array();
		foreach ( self::import_parts( $row ) as $part ) {
			if ( is_array( $part ) ) {
				$tokens = array_merge( $tokens, self::part_skill_tokens( $part, $row['skills'] ) );
			}
		}
		return array_values( array_unique( $tokens ) );
	}

	/**
	 * Resolve imported skill tokens into a per-part writer mapping.
	 *
	 * @param array $row Normalized row.
	 * @param array $resolved Token to ID (or ambiguous IDs) lookup.
	 * @param array $errors Receives unknown or ambiguous skill errors.
	 * @return array
	 */
	public static function skill_map( array $row, array $resolved, array &$errors ) {
		$tokens = array( 'p1' => $row['skills'] );
		if ( 'structured' === $row['type'] ) {
			$tokens = array();
			foreach ( self::import_parts( $row ) as $part ) {
				if ( is_array( $part ) && isset( $part['id'] ) && is_scalar( $part['id'] ) ) {
					$tokens[ (string) $part['id'] ] = self::part_skill_tokens( $part, $row['skills'] );
				}
			}
		}
		$map = array();
		foreach ( $tokens as $part_id => $part_tokens ) {
			$terms = array();
			foreach ( $part_tokens as $token ) {
				$found = $resolved[ $token ] ?? null;
				if ( is_array( $found ) ) {
					/* translators: %s: skill code. */
					$errors[] = sprintf( __( 'Skill "%s" matches more than one skill. Use its UUID instead.', 'ohmylms' ), $token );
				} elseif ( ! $found ) {
					/* translators: %s: skill code. */
					$errors[] = sprintf( __( 'Skill "%s" was not found.', 'ohmylms' ), $token );
				} else {
					$terms[] = (int) $found;
				}
			}
			$terms           = array_values( array_unique( $terms ) );
			$map[ $part_id ] = array(
				'primary'    => $terms[0] ?? 0,
				'supporting' => array_slice( $terms, 1 ),
			);
		}
		return $map;
	}

	/**
	 * Position of the answer a token refers to: a letter, a 1-based number or the answer text.
	 *
	 * @param string   $token Value from the correct column.
	 * @param string[] $answers Answer texts.
	 * @return int|null Zero-based index.
	 */
	private static function answer_index( $token, array $answers ) {
		$count = count( $answers );
		if ( 1 === strlen( $token ) && ctype_alpha( $token ) ) {
			$index = ord( strtoupper( $token ) ) - 65;
			if ( $index < $count ) {
				return $index;
			}
		}
		if ( ctype_digit( $token ) && (int) $token >= 1 && (int) $token <= $count ) {
			return (int) $token - 1;
		}
		foreach ( $answers as $index => $answer ) {
			if ( 0 === strcasecmp( trim( $answer ), trim( $token ) ) ) {
				return $index;
			}
		}
		return null;
	}

	/**
	 * Option rows in the writer's format.
	 *
	 * @param string[] $answers Answer texts.
	 * @param int[]    $correct Zero-based indexes that are correct.
	 * @return array[]
	 */
	private static function options( array $answers, array $correct ) {
		$options = array();
		foreach ( array_values( $answers ) as $index => $answer ) {
			$options[] = array(
				'answer'     => $answer,
				'is_correct' => in_array( $index, $correct, true ),
			);
		}
		return $options;
	}

	/**
	 * Short human-readable source for the bank's source field.
	 *
	 * @param array $row Normalized row.
	 * @return string
	 */
	public static function source( array $row ) {
		$parts = array_filter( array( $row['exam'], $row['paper'], $row['year'], $row['section'], 'Q' . $row['number'] ), 'strlen' );
		$text  = implode( ' · ', $parts );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 100 ) : substr( $text, 0, 100 );
	}

	/**
	 * Writer payload for a valid row.
	 *
	 * @param array      $row Normalized row.
	 * @param array      $skills Resolved skill term IDs, primary first.
	 * @param int        $bank_id Target bank, 0 for none.
	 * @param int        $image_id Attachment ID for a question image, 0 for none.
	 * @param array|null $part_skills Resolved per-part mapping, or inherit the supplied skills.
	 * @return array
	 */
	public static function payload( array $row, array $skills, $bank_id, $image_id, $part_skills = null ) {
		$built    = self::build_answers( $row );
		$settings = $built['settings'] + array( 'type' => $row['type'] );
		if ( '' !== $row['explanation'] ) {
			$settings['explanation'] = $row['explanation'];
		}
		if ( 'structured' !== $row['type'] && '' !== $row['marks'] ) {
			$settings['score'] = (float) $row['marks'];
		}
		$title = '' !== $row['title'] ? $row['title'] : trim( implode( ' ', array_filter( array( $row['exam'], $row['paper'], $row['year'] ), 'strlen' ) ) . ' · Q' . $row['number'] );
		$bank  = array(
			'difficulty' => $row['difficulty'],
			'source'     => self::source( $row ),
		);
		if ( '' !== $row['family'] ) {
			$bank['family_id'] = $row['family'];
		}
		if ( $bank_id ) {
			$bank['bank_id'] = (int) $bank_id;
		}
		if ( null === $part_skills ) {
			$mapping     = array(
				'primary'    => (int) ( $skills[0] ?? 0 ),
				'supporting' => array_map( 'intval', array_slice( $skills, 1 ) ),
			);
			$part_skills = array( 'p1' => $mapping );
			if ( 'structured' === $row['type'] ) {
				$part_skills = array();
				foreach ( Structured::parts( $settings ) as $part ) {
					$part_skills[ $part['id'] ] = $mapping;
				}
			}
		}
		$payload = array(
			'name'     => 'fill-in-the-blank' === $row['type'] ? $row['question'] : $title,
			'status'   => 'publish',
			'settings' => $settings,
			'skills'   => $part_skills,
			'bank'     => $bank,
		);
		if ( 'fill-in-the-blank' !== $row['type'] ) {
			$payload['description'] = $row['question'];
		}
		if ( $built['options'] ) {
			$payload['questions'] = $built['options'];
		}
		if ( $image_id ) {
			$payload['thumbnail_id'] = (int) $image_id;
		}
		return $payload;
	}

	/**
	 * Fingerprint of what would be written, so identical re-uploads are skipped.
	 *
	 * @param array $payload Writer payload.
	 * @return string
	 */
	public static function fingerprint( array $payload ) {
		return sha1( wp_json_encode( $payload ) );
	}

	/**
	 * Check and optionally write a batch of rows.
	 *
	 * Options: dry_run (default true), bank_id, approve, update_existing (default true),
	 * skip_invalid (default false: any invalid row stops the whole batch).
	 *
	 * @param array[] $raw_rows Raw rows from parse().
	 * @param array   $options Import options.
	 * @return array|\WP_Error Report with per-row results.
	 */
	public static function run( array $raw_rows, array $options = array() ) {
		$options = array_merge(
			array(
				'dry_run'         => true,
				'bank_id'         => 0,
				'approve'         => false,
				'update_existing' => true,
				'skip_invalid'    => false,
				'review'          => false,
				// Skills given to every row that names none, e.g. the skill an import was started from.
				'default_skills'  => '',
			),
			$options
		);
		if ( ! AccessPolicy::can_author() ) {
			return AccessPolicy::denied();
		}
		if ( count( $raw_rows ) > self::MAX_ROWS ) {
			/* translators: %d: maximum rows per request. */
			return self::error( 'ohmylms_import_limit', sprintf( __( 'Import at most %d rows at a time.', 'ohmylms' ), self::MAX_ROWS ) );
		}
		$bank_id = (int) $options['bank_id'];
		if ( $bank_id && ! Banks::can( $bank_id, 'edit' ) ) {
			return AccessPolicy::denied( __( 'You cannot add questions to that bank.', 'ohmylms' ) );
		}
		$rows     = array_map( array( __CLASS__, 'normalize_row' ), $raw_rows );
		$defaults = self::list_of( $options['default_skills'], ';,|' );
		if ( $defaults ) {
			foreach ( $rows as &$default_row ) {
				if ( ! $default_row['skills'] ) {
					$default_row['skills'] = $defaults;
				}
			}
			unset( $default_row );
		}
		$type_known = static function ( $type ) {
			return (bool) Registry::get( 'question', $type );
		};
		$skill_ids  = self::resolve_skills( array_merge( array(), ...array_map( array( __CLASS__, 'skill_tokens' ), $rows ) ) );
		$keys       = array_values( array_filter( array_map( array( __CLASS__, 'key' ), $rows ) ) );
		$existing   = self::existing( $keys );
		$report     = array();
		$items      = array();
		$seen       = array();
		foreach ( $rows as $index => $row ) {
			$line               = $index + 1;
			$result             = array(
				'row'      => $line,
				'key'      => self::key( $row ),
				'status'   => 'error',
				'id'       => 0,
				'errors'   => array(),
				'warnings' => array(),
			);
			$check              = self::validate_row( $row, $type_known );
			$result['errors']   = $check['errors'];
			$result['warnings'] = $check['warnings'];
			$part_skills        = self::skill_map( $row, $skill_ids, $result['errors'] );
			if ( $result['key'] && isset( $seen[ $result['key'] ] ) ) {
				/* translators: %d: earlier row number. */
				$result['errors'][] = sprintf( __( 'The same question appears again; row %d already uses this exam, paper, year, section, number and language.', 'ohmylms' ), $seen[ $result['key'] ] );
			} elseif ( $result['key'] ) {
				$seen[ $result['key'] ] = $line;
			}
			$image_id = self::image_id( $row['image'], $result );
			if ( ! $result['errors'] ) {
				$payload = self::payload( $row, array(), $bank_id, $image_id, $part_skills );
				$hash    = self::fingerprint( $payload );
				$match   = $existing[ $result['key'] ] ?? null;
				if ( $match ) {
					$result['id'] = $match['id'];
					if ( $match['hash'] === $hash ) {
						$result['status'] = 'unchanged';
					} elseif ( $options['update_existing'] ) {
						$result['status'] = 'update';
						$payload['id']    = $match['id'];
						$items[ $index ]  = array(
							'payload' => $payload,
							'hash'    => $hash,
							'row'     => $row,
						);
					} else {
						$result['status']     = 'unchanged';
						$result['warnings'][] = __( 'Exists and differs, but updates are switched off.', 'ohmylms' );
					}
				} else {
					$result['status'] = 'create';
					$items[ $index ]  = array(
						'payload' => $payload,
						'hash'    => $hash,
						'row'     => $row,
					);
				}
			}
			$report[ $index ] = $result;
		}
		$invalid = count(
			array_filter(
				$report,
				static function ( $result ) {
					return 'error' === $result['status'];
				}
			)
		);
		// The writer is the authority on what it will accept; ask it before reporting or writing.
		if ( $items ) {
			$plans = DraftWriter::prepare_many( array_column( $items, 'payload' ), 0 );
			if ( is_wp_error( $plans ) ) {
				$problems = (array) ( $plans->get_error_data()['errors'] ?? array() );
				if ( ! $problems ) {
					return $plans;
				}
				$positions = array_keys( $items );
				foreach ( $problems as $problem ) {
					$at = $positions[ $problem['index'] ] ?? null;
					if ( null !== $at ) {
						$report[ $at ]['status']   = 'error';
						$report[ $at ]['errors'][] = $problem['message'];
						unset( $items[ $at ] );
						++$invalid;
					}
				}
			}
		}
		$summary = array(
			'dry_run'   => (bool) $options['dry_run'],
			'committed' => false,
			'approved'  => 0,
			'counts'    => array(),
			'rows'      => array_values( $report ),
		);
		if ( ! $options['dry_run'] && ( ! $invalid || $options['skip_invalid'] ) && $items ) {
			$saved = DraftWriter::save_many( array_column( $items, 'payload' ), 0 );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			$ids = array();
			foreach ( array_keys( $items ) as $position => $index ) {
				$id                     = (int) $saved[ $position ]['id'];
				$ids[]                  = $id;
				$report[ $index ]['id'] = $id;
				update_post_meta( $id, self::KEY_META, $report[ $index ]['key'] );
				update_post_meta( $id, self::HASH_META, $items[ $index ]['hash'] );
				update_post_meta( $id, self::PAPER_META, self::paper_fields( $items[ $index ]['row'] ) );
				if ( $options['review'] ) {
					ReviewQueue::flag(
						$id,
						array(
							'by'         => 'ai',
							'source'     => 'import',
							'confidence' => $items[ $index ]['row']['confidence'],
							'notes'      => $items[ $index ]['row']['notes'],
						)
					);
				}
			}
			// The writer leaves an edited question as a draft post; imported questions are published.
			foreach ( $ids as $id ) {
				if ( 'draft' === get_post_status( $id ) ) {
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => 'publish',
						)
					);
				}
			}
			$summary['committed'] = true;
			// AI-classified questions are never approved here; a person approves them from the review list.
			if ( $options['approve'] && ! $options['review'] ) {
				$unapproved = array();
				foreach ( $report as $index => $result ) {
					if ( 'unchanged' === $result['status'] && $result['id'] && empty( BankAttributes::get( $result['id'] )['approved_version_id'] ) ) {
						$unapproved[] = $result['id'];
					}
				}
				foreach ( array_merge( $ids, $unapproved ) as $id ) {
					$approved = ReviewQueue::approve( $id );
					if ( true === $approved ) {
						++$summary['approved'];
					} else {
						foreach ( $report as $index => $result ) {
							if ( $result['id'] === $id ) {
								$report[ $index ]['warnings'][] = $approved;
							}
						}
					}
				}
			}
			$summary['rows'] = array_values( $report );
		} elseif ( ! $options['dry_run'] && $invalid && ! $options['skip_invalid'] ) {
			$summary['stopped'] = __( 'Nothing was imported because some rows have errors. Fix them, or choose to skip invalid rows.', 'ohmylms' );
		}
		foreach ( array( 'create', 'update', 'unchanged', 'error' ) as $status ) {
			$summary['counts'][ $status ] = count(
				array_filter(
					$report,
					static function ( $result ) use ( $status ) {
						return $status === $result['status'];
					}
				)
			);
		}
		return $summary;
	}

	/**
	 * Paper fields kept on each question, so papers can be rebuilt and filtered later.
	 *
	 * @param array $row Normalized row.
	 * @return array
	 */
	public static function paper_fields( array $row ) {
		return array(
			'exam'     => $row['exam'],
			'paper'    => $row['paper'],
			'year'     => $row['year'],
			'section'  => $row['section'],
			'number'   => $row['number'],
			'language' => $row['language'],
		);
	}

	/**
	 * Skill term IDs by the code, UUID or "#id" a row used. Ambiguous codes map to an array.
	 *
	 * @param string[] $tokens Skill tokens.
	 * @return array<string,int|int[]>
	 */
	private static function resolve_skills( array $tokens ) {
		global $wpdb;
		$tokens = array_values( array_unique( array_filter( $tokens, 'strlen' ) ) );
		$found  = array();
		$lookup = array();
		foreach ( $tokens as $token ) {
			if ( preg_match( '/^#(\d+)$/', $token, $match ) ) {
				$term = get_term( (int) $match[1], Taxonomy::NAME );
				if ( $term && ! is_wp_error( $term ) ) {
					$found[ $token ] = (int) $term->term_id;
				}
			} else {
				$lookup[] = $token;
			}
		}
		if ( $lookup ) {
			$holders = implode( ',', array_fill( 0, count( $lookup ), '%s' ) );
			$query   = $wpdb->prepare( "SELECT m.meta_value AS token, m.term_id FROM {$wpdb->termmeta} m JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=m.term_id AND tt.taxonomy=%s WHERE m.meta_key IN ('_ohmylms_skill_code','_ohmylms_skill_uuid') AND m.meta_value IN ($holders)", array_merge( array( Taxonomy::NAME ), $lookup ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are generated from the token count.
			$matches = array();
			foreach ( $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only batch lookup, prepared above.
				$matches[ $row['token'] ][ (int) $row['term_id'] ] = true;
			}
			foreach ( $matches as $token => $terms ) {
				$found[ $token ] = 1 === count( $terms ) ? (int) array_key_first( $terms ) : array_keys( $terms );
			}
		}
		return $found;
	}

	/**
	 * Existing imported questions by key, with their last fingerprint.
	 *
	 * @param string[] $keys Import keys.
	 * @return array<string,array{id:int,hash:string}>
	 */
	private static function existing( array $keys ) {
		global $wpdb;
		$keys = array_values( array_unique( $keys ) );
		if ( ! $keys ) {
			return array();
		}
		$holders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$query   = $wpdb->prepare( "SELECT m.post_id, m.meta_value AS import_key FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE m.meta_key=%s AND p.post_type=%s AND p.post_status<>'trash' AND m.meta_value IN ($holders)", array_merge( array( self::KEY_META, OHMYLMS_QUESTION_CPT ), $keys ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholders are generated from the key count.
		$result  = array();
		foreach ( $wpdb->get_results( $query, ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Read-only batch lookup, prepared above.
			$id                           = (int) $row['post_id'];
			$result[ $row['import_key'] ] = array(
				'id'   => $id,
				'hash' => (string) get_post_meta( $id, self::HASH_META, true ),
			);
		}
		return $result;
	}

	/**
	 * Media library attachment for an image column: an attachment ID or a media library URL.
	 *
	 * @param string $value Cell value.
	 * @param array  $result Row result, to which an error is added when it cannot be found.
	 * @return int
	 */
	private static function image_id( $value, array &$result ) {
		if ( '' === $value ) {
			return 0;
		}
		$id = ctype_digit( $value ) ? (int) $value : (int) attachment_url_to_postid( $value );
		if ( ! $id || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
			/* translators: %s: the image value from the row. */
			$result['errors'][] = sprintf( __( 'Image "%s" is not an image in the media library. Upload it first and use its ID or URL.', 'ohmylms' ), $value );
			return 0;
		}
		return $id;
	}

	/**
	 * Build a WP_Error with a status.
	 *
	 * @param string $code Error code.
	 * @param string $message Message.
	 * @return \WP_Error
	 */
	private static function error( $code, $message ) {
		return new \WP_Error( $code, $message, array( 'status' => 400 ) );
	}
}
