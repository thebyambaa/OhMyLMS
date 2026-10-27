<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * The OhMyLMS quiz file: one JSON document holding a quiz and all of its questions.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz\Exchange;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, checks and writes the `ohmylms.quiz` file format.
 *
 * The file is lossless on purpose. Question `settings` are copied untouched, so every question
 * type, every setting and the randomized-variable `template` of a question survive a round trip
 * without this class knowing anything about them. Only the shape of the file is checked here;
 * what a question type accepts is decided by the same validators the question editor uses.
 *
 *     {
 *       "format": "ohmylms.quiz", "version": 1,
 *       "source": { "site": "...", "uploads_url": "...", "exported": "..." },
 *       "quiz": { "name", "description", "settings", "drip_settings", "skills": [ref] },
 *       "questions": [ {
 *         "name", "description", "settings": { "type", "score", "template", ... },
 *         "options": [ { "answer", "is_correct", "image_url", "matching_data": { "label", "image_url" } } ],
 *         "image_url", "video_url", "skills": { part: { "primary": ref, "supporting": [ref] } },
 *         "bank": { "difficulty", "source", "family_id", "secure" }
 *       } ]
 *     }
 *
 * A skill `ref` is `{ "uuid", "slug", "name" }`. Problems are `{ question, message }` where
 * `question` is the 1-based question number, or 0 for the file as a whole.
 */
final class Document {

	const FORMAT        = 'ohmylms.quiz';
	const VERSION       = 1;
	const MAX_BYTES     = 10485760;
	const MAX_QUESTIONS = 1000;
	const MAX_OPTIONS   = 200;

	/** Fields a pasted REST response carries that a file never needs; ignored without a warning. */
	const IGNORED = array( 'id', 'uuid', 'version', 'approved_version', 'pinned_version_id', 'readonly', 'usage_count', 'usage', 'skill_map', 'modified', 'quiz_id', 'order_number', 'thumbnail_id', 'video_id', 'image_src', 'video_src', 'base_modified', 'temp', 'status', 'slug', 'date_created', 'date_modified' );

	/**
	 * Does this text look like an OhMyLMS quiz file rather than CSV?
	 *
	 * @param string $text File contents.
	 * @return bool
	 */
	public static function looks_like( $text ) {
		$text = ltrim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $text ) );
		return '' !== $text && ( '{' === $text[0] || '[' === $text[0] );
	}

	/**
	 * Parse and normalize a quiz file.
	 *
	 * @param string $text File contents.
	 * @return array|\WP_Error `{ document, warnings }`, or an error listing every problem found.
	 */
	public static function parse( $text ) {
		$text = (string) $text;
		if ( strlen( $text ) > self::MAX_BYTES ) {
			return self::failure( array( self::problem( 0, __( 'The file is larger than 10 MB.', 'ohmylms' ) ) ) );
		}
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		if ( '' === trim( $text ) ) {
			return self::failure( array( self::problem( 0, __( 'The file is empty.', 'ohmylms' ) ) ) );
		}
		$raw = json_decode( $text, true, 128 );
		if ( null === $raw && JSON_ERROR_NONE !== json_last_error() ) {
			/* translators: %s: JSON parser message */
			return self::failure( array( self::problem( 0, sprintf( __( 'The file is not valid JSON (%s). Export a quiz to see the expected layout.', 'ohmylms' ), json_last_error_msg() ) ) ) );
		}
		if ( ! is_array( $raw ) ) {
			return self::failure( array( self::problem( 0, __( 'The file must contain a JSON object.', 'ohmylms' ) ) ) );
		}
		// A bare list of questions is accepted too.
		if ( self::is_list( $raw ) ) {
			$raw = array( 'questions' => $raw );
		}

		$warnings = array();
		$problems = array();

		if ( isset( $raw['format'] ) && self::FORMAT !== $raw['format'] ) {
			$problems[] = self::problem( 0, __( 'This is not an OhMyLMS quiz file ("format" must be "ohmylms.quiz").', 'ohmylms' ) );
		}
		$version = $raw['version'] ?? self::VERSION;
		if ( ! is_int( $version ) || $version < 1 ) {
			$problems[] = self::problem( 0, __( '"version" must be a whole number of 1 or more.', 'ohmylms' ) );
		} elseif ( $version > self::VERSION ) {
			$problems[] = self::problem( 0, __( 'This file was made by a newer version of OhMyLMS. Update the plugin to import it.', 'ohmylms' ) );
		}

		$quiz = self::quiz( $raw['quiz'] ?? array(), $warnings, $problems );

		$list = $raw['questions'] ?? null;
		if ( ! is_array( $list ) || ! self::is_list( $list ) ) {
			$problems[] = self::problem( 0, __( '"questions" must be a list of questions.', 'ohmylms' ) );
			$list       = array();
		} elseif ( ! $list ) {
			$problems[] = self::problem( 0, __( 'The file has no questions.', 'ohmylms' ) );
		} elseif ( count( $list ) > self::MAX_QUESTIONS ) {
			/* translators: %d: question limit */
			$problems[] = self::problem( 0, sprintf( __( 'A file can hold at most %d questions.', 'ohmylms' ), self::MAX_QUESTIONS ) );
			$list       = array();
		}
		$questions = array();
		foreach ( $list as $index => $item ) {
			$questions[] = self::question( $item, $index + 1, $warnings, $problems );
		}

		if ( $problems ) {
			return self::failure( $problems );
		}
		$source = is_array( $raw['source'] ?? null ) ? $raw['source'] : array();
		return array(
			'document' => array(
				'source'    => array(
					'site'        => is_string( $source['site'] ?? null ) ? $source['site'] : '',
					'uploads_url' => is_string( $source['uploads_url'] ?? null ) ? rtrim( $source['uploads_url'], '/' ) : '',
				),
				'quiz'      => $quiz,
				'questions' => $questions,
			),
			'warnings' => $warnings,
		);
	}

	/**
	 * Quiz-level fields.
	 *
	 * @param mixed $raw      Raw `quiz` value.
	 * @param array $warnings Collects warnings.
	 * @param array $problems Collects problems.
	 * @return array
	 */
	private static function quiz( $raw, array &$warnings, array &$problems ) {
		if ( ! is_array( $raw ) ) {
			$problems[] = self::problem( 0, __( '"quiz" must be an object.', 'ohmylms' ) );
			$raw        = array();
		}
		$quiz = array(
			'name'               => self::text( $raw, 'name', 0, $problems ),
			'description'        => self::text( $raw, 'description', 0, $problems ),
			'settings'           => self::map( $raw, 'settings', 0, $problems ),
			'drip_settings'      => self::map( $raw, 'drip_settings', 0, $problems ),
			'extension_settings' => self::map( $raw, 'extension_settings', 0, $problems ),
			'skills'             => self::refs( $raw['skills'] ?? array(), 0, $problems ),
		);
		foreach ( array_keys( $raw ) as $key ) {
			if ( ! array_key_exists( $key, $quiz ) && ! in_array( $key, self::IGNORED, true ) ) {
				/* translators: %s: field name */
				$warnings[] = self::problem( 0, sprintf( __( 'The quiz field "%s" is not part of the format and was ignored.', 'ohmylms' ), $key ) );
			}
		}
		return $quiz;
	}

	/**
	 * One question, normalized. Problems are recorded, never thrown, so one pass reports them all.
	 *
	 * @param mixed $raw      Raw question.
	 * @param int   $number   1-based question number.
	 * @param array $warnings Collects warnings.
	 * @param array $problems Collects problems.
	 * @return array
	 */
	private static function question( $raw, $number, array &$warnings, array &$problems ) {
		if ( ! is_array( $raw ) || self::is_list( $raw ) ) {
			$problems[] = self::problem( $number, __( 'A question must be an object.', 'ohmylms' ) );
			return array(
				'name'               => '',
				'description'        => '',
				'settings'           => array( 'type' => '' ),
				'options'            => array(),
				'image_url'          => '',
				'video_url'          => '',
				'skills'             => array(),
				'bank'               => array(),
				'extension_settings' => array(),
			);
		}
		$settings = self::map( $raw, 'settings', $number, $problems );
		// `type` at the top of a question is shorthand for settings.type.
		if ( ( ! isset( $settings['type'] ) || '' === $settings['type'] ) && isset( $raw['type'] ) ) {
			$settings['type'] = $raw['type'];
		}
		$type = $settings['type'] ?? '';
		if ( ! is_string( $type ) || ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/i', $type ) ) {
			$problems[] = self::problem( $number, __( 'The question has no valid type (settings.type).', 'ohmylms' ) );
			$type       = '';
		}
		$settings['type'] = $type;
		if ( ! isset( $settings['score'] ) ) {
			$settings['score'] = array(
				'enabled' => true,
				'value'   => 1,
			);
		}

		$options_raw = $raw['options'] ?? ( $raw['questions'] ?? array() );
		$options     = array();
		if ( ! is_array( $options_raw ) || ! self::is_list( $options_raw ) ) {
			$problems[]  = self::problem( $number, __( '"options" must be a list.', 'ohmylms' ) );
			$options_raw = array();
		} elseif ( count( $options_raw ) > self::MAX_OPTIONS ) {
			/* translators: %d: option limit */
			$problems[]  = self::problem( $number, sprintf( __( 'A question can have at most %d options.', 'ohmylms' ), self::MAX_OPTIONS ) );
			$options_raw = array();
		}
		foreach ( $options_raw as $option ) {
			$options[] = self::option( $option, $number, $problems );
		}

		$question = array(
			'name'               => self::text( $raw, 'name', $number, $problems ),
			'description'        => self::text( $raw, 'description', $number, $problems ),
			'settings'           => $settings,
			'options'            => $options,
			'image_url'          => self::url( $raw, 'image_url', $number, $problems ),
			'video_url'          => self::url( $raw, 'video_url', $number, $problems ),
			'skills'             => self::skill_map( $raw['skills'] ?? array(), $number, $problems ),
			'bank'               => self::bank( $raw['bank'] ?? array(), $number, $problems ),
			'extension_settings' => self::map( $raw, 'extension_settings', $number, $problems ),
		);
		$known    = array_merge( array_keys( $question ), array( 'type', 'questions' ), self::IGNORED );
		foreach ( array_keys( $raw ) as $key ) {
			if ( ! in_array( $key, $known, true ) ) {
				/* translators: %s: field name */
				$warnings[] = self::problem( $number, sprintf( __( 'The field "%s" is not part of the format and was ignored. Hints, explanations and the score belong inside "settings".', 'ohmylms' ), $key ) );
			}
		}
		return $question;
	}

	/**
	 * One answer option.
	 *
	 * @param mixed $raw      Raw option.
	 * @param int   $number   Question number.
	 * @param array $problems Collects problems.
	 * @return array
	 */
	private static function option( $raw, $number, array &$problems ) {
		if ( ! is_array( $raw ) || self::is_list( $raw ) ) {
			$problems[] = self::problem( $number, __( 'Each option must be an object.', 'ohmylms' ) );
			$raw        = array();
		}
		$answer = $raw['answer'] ?? '';
		if ( is_int( $answer ) || is_float( $answer ) ) {
			$answer = (string) $answer;
		} elseif ( null === $answer ) {
			$answer = '';
		} elseif ( ! is_string( $answer ) ) {
			$problems[] = self::problem( $number, __( 'An option "answer" must be text.', 'ohmylms' ) );
			$answer     = '';
		}
		$option = array(
			'answer'     => $answer,
			'is_correct' => filter_var( $raw['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN ),
			'image_url'  => self::url( $raw, 'image_url', $number, $problems ),
		);
		$data   = $raw['matching_data'] ?? array();
		if ( is_array( $data ) && ! self::is_list( $data ) ) {
			$label = $data['label'] ?? '';
			$label = is_int( $label ) || is_float( $label ) ? (string) $label : $label;
			if ( ! is_string( $label ) ) {
				$problems[] = self::problem( $number, __( 'A matching "label" must be text.', 'ohmylms' ) );
				$label      = '';
			}
			$option['matching_data'] = array(
				'label'     => $label,
				'image_url' => self::url( $data, 'image_url', $number, $problems ),
			);
		}
		return $option;
	}

	/**
	 * Per-part skill map: part id => primary ref, supporting refs.
	 *
	 * @param mixed $raw      Raw map.
	 * @param int   $number   Question number.
	 * @param array $problems Collects problems.
	 * @return array
	 */
	private static function skill_map( $raw, $number, array &$problems ) {
		if ( ! is_array( $raw ) || ( $raw && self::is_list( $raw ) ) ) {
			$problems[] = self::problem( $number, __( '"skills" must be an object keyed by part (for example "p1").', 'ohmylms' ) );
			return array();
		}
		$map = array();
		foreach ( $raw as $part => $roles ) {
			$part = (string) $part;
			if ( ! preg_match( '/^[a-z0-9_-]{1,40}$/i', $part ) || ! is_array( $roles ) ) {
				$problems[] = self::problem( $number, __( 'Skill mapping parts need a short ID and an object with "primary" and "supporting".', 'ohmylms' ) );
				continue;
			}
			$primary    = self::refs( isset( $roles['primary'] ) ? array( $roles['primary'] ) : array(), $number, $problems );
			$supporting = self::refs( $roles['supporting'] ?? array(), $number, $problems );
			if ( $primary || $supporting ) {
				$map[ $part ] = array(
					'primary'    => $primary ? $primary[0] : null,
					'supporting' => $supporting,
				);
			}
		}
		return $map;
	}

	/**
	 * A list of skill references.
	 *
	 * @param mixed $raw      Raw list.
	 * @param int   $number   Question number (0 for the quiz).
	 * @param array $problems Collects problems.
	 * @return array[] Each: uuid, slug, name.
	 */
	private static function refs( $raw, $number, array &$problems ) {
		if ( ! is_array( $raw ) || ! self::is_list( $raw ) ) {
			$problems[] = self::problem( $number, __( 'Skills must be a list.', 'ohmylms' ) );
			return array();
		}
		$refs = array();
		foreach ( $raw as $item ) {
			if ( is_string( $item ) && '' !== trim( $item ) ) {
				$item = array( 'slug' => $item );
			}
			if ( ! is_array( $item ) ) {
				$problems[] = self::problem( $number, __( 'A skill must be an object with "uuid", "slug" and "name", or a slug.', 'ohmylms' ) );
				continue;
			}
			$ref = array(
				'uuid' => is_string( $item['uuid'] ?? null ) ? $item['uuid'] : '',
				'slug' => is_string( $item['slug'] ?? null ) ? $item['slug'] : '',
				'name' => is_string( $item['name'] ?? null ) ? $item['name'] : '',
			);
			if ( '' === $ref['uuid'] && '' === $ref['slug'] ) {
				$problems[] = self::problem( $number, __( 'A skill needs a "uuid" or a "slug".', 'ohmylms' ) );
				continue;
			}
			$refs[] = $ref;
		}
		return $refs;
	}

	/**
	 * Indexed bank attributes.
	 *
	 * @param mixed $raw      Raw bank object.
	 * @param int   $number   Question number.
	 * @param array $problems Collects problems.
	 * @return array
	 */
	private static function bank( $raw, $number, array &$problems ) {
		if ( ! is_array( $raw ) || ( $raw && self::is_list( $raw ) ) ) {
			$problems[] = self::problem( $number, __( '"bank" must be an object.', 'ohmylms' ) );
			return array();
		}
		return array_intersect_key( $raw, array_flip( array( 'difficulty', 'source', 'family_id', 'secure' ) ) );
	}

	/**
	 * A text field that may be absent.
	 *
	 * @param array  $raw      Source object.
	 * @param string $key      Field.
	 * @param int    $number   Question number.
	 * @param array  $problems Collects problems.
	 * @return string
	 */
	private static function text( array $raw, $key, $number, array &$problems ) {
		$value = $raw[ $key ] ?? '';
		if ( null === $value ) {
			return '';
		}
		if ( ! is_string( $value ) ) {
			/* translators: %s: field name */
			$problems[] = self::problem( $number, sprintf( __( '"%s" must be text.', 'ohmylms' ), $key ) );
			return '';
		}
		return $value;
	}

	/**
	 * An object field that may be absent.
	 *
	 * @param array  $raw      Source object.
	 * @param string $key      Field.
	 * @param int    $number   Question number.
	 * @param array  $problems Collects problems.
	 * @return array
	 */
	private static function map( array $raw, $key, $number, array &$problems ) {
		$value = $raw[ $key ] ?? array();
		if ( null === $value || '' === $value ) {
			return array();
		}
		if ( ! is_array( $value ) || ( $value && self::is_list( $value ) ) ) {
			/* translators: %s: field name */
			$problems[] = self::problem( $number, sprintf( __( '"%s" must be an object.', 'ohmylms' ), $key ) );
			return array();
		}
		return $value;
	}

	/**
	 * An http(s) address that may be absent.
	 *
	 * @param array  $raw      Source object.
	 * @param string $key      Field.
	 * @param int    $number   Question number.
	 * @param array  $problems Collects problems.
	 * @return string
	 */
	private static function url( array $raw, $key, $number, array &$problems ) {
		$value = $raw[ $key ] ?? '';
		if ( null === $value || '' === $value ) {
			return '';
		}
		if ( ! is_string( $value ) || strlen( $value ) > 2048 || ! preg_match( '#^https?://[^\s<>"]+$#i', $value ) ) {
			/* translators: %s: field name */
			$problems[] = self::problem( $number, sprintf( __( '"%s" must be a web address starting with http:// or https://.', 'ohmylms' ), $key ) );
			return '';
		}
		return $value;
	}

	/**
	 * What a document contains, for the preview and the result message.
	 *
	 * @param array $document Normalized document.
	 * @return array questions, points, types (type => count), randomized, media.
	 */
	public static function summary( array $document ) {
		$types      = array();
		$points     = 0.0;
		$randomized = 0;
		$media      = 0;
		foreach ( $document['questions'] as $question ) {
			$type           = $question['settings']['type'];
			$types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
			$score          = $question['settings']['score'] ?? array();
			if ( is_array( $score ) && ! empty( $score['enabled'] ) && is_numeric( $score['value'] ?? null ) ) {
				$points += (float) $score['value'];
			}
			if ( ! empty( $question['settings']['template']['variables'] ) ) {
				++$randomized;
			}
			$media += ( '' !== $question['image_url'] ? 1 : 0 ) + ( '' !== $question['video_url'] ? 1 : 0 );
			foreach ( $question['options'] as $option ) {
				$media += ( '' !== $option['image_url'] ? 1 : 0 ) + ( '' !== ( $option['matching_data']['image_url'] ?? '' ) ? 1 : 0 );
			}
		}
		arsort( $types );
		return array(
			'questions'  => count( $document['questions'] ),
			'points'     => round( $points, 6 ),
			'types'      => $types,
			'randomized' => $randomized,
			'media'      => $media,
		);
	}

	/**
	 * Serialize a document to the file text.
	 *
	 * @param array $document Document with `source`, `quiz` and `questions`.
	 * @return string
	 */
	public static function render( array $document ) {
		$questions = array();
		foreach ( $document['questions'] as $question ) {
			$out = array(
				'name'        => $question['name'],
				'description' => $question['description'],
				'settings'    => $question['settings'] ? $question['settings'] : new \stdClass(),
				'options'     => array(),
			);
			foreach ( $question['options'] as $option ) {
				$row = array(
					'answer'     => $option['answer'],
					'is_correct' => (bool) $option['is_correct'],
				);
				if ( '' !== $option['image_url'] ) {
					$row['image_url'] = $option['image_url'];
				}
				if ( isset( $option['matching_data'] ) ) {
					$row['matching_data'] = $option['matching_data'];
				}
				$out['options'][] = $row;
			}
			foreach ( array( 'image_url', 'video_url' ) as $key ) {
				if ( '' !== $question[ $key ] ) {
					$out[ $key ] = $question[ $key ];
				}
			}
			if ( $question['skills'] ) {
				$out['skills'] = $question['skills'];
			}
			if ( $question['bank'] ) {
				$out['bank'] = $question['bank'];
			}
			if ( $question['extension_settings'] ) {
				$out['extension_settings'] = $question['extension_settings'];
			}
			$questions[] = $out;
		}
		$quiz = array(
			'name'        => $document['quiz']['name'],
			'description' => $document['quiz']['description'],
			'settings'    => $document['quiz']['settings'] ? $document['quiz']['settings'] : new \stdClass(),
		);
		if ( $document['quiz']['drip_settings'] ) {
			$quiz['drip_settings'] = $document['quiz']['drip_settings'];
		}
		if ( $document['quiz']['extension_settings'] ) {
			$quiz['extension_settings'] = $document['quiz']['extension_settings'];
		}
		if ( $document['quiz']['skills'] ) {
			$quiz['skills'] = $document['quiz']['skills'];
		}
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;
		return (string) wp_json_encode(
			array(
				'format'    => self::FORMAT,
				'version'   => self::VERSION,
				'source'    => $document['source'],
				'quiz'      => $quiz,
				'questions' => $questions,
			),
			$flags
		) . "\n";
	}

	/**
	 * A problem or warning.
	 *
	 * @param int    $question Question number, or 0 for the file.
	 * @param string $message  Message.
	 * @return array
	 */
	public static function problem( $question, $message ) {
		return array(
			'question' => (int) $question,
			'message'  => (string) $message,
		);
	}

	/**
	 * Display text for a problem: "Question 3: ...".
	 *
	 * @param array $problem Problem.
	 * @return string
	 */
	public static function describe( array $problem ) {
		/* translators: 1: question number, 2: message */
		return $problem['question'] ? sprintf( __( 'Question %1$d: %2$s', 'ohmylms' ), $problem['question'], $problem['message'] ) : $problem['message'];
	}

	/**
	 * An error that lists every problem.
	 *
	 * @param array $problems Problems.
	 * @return \WP_Error
	 */
	public static function failure( array $problems ) {
		$count = count( $problems );
		return new \WP_Error(
			'ohmylms_quiz_import_invalid',
			sprintf(
				/* translators: 1: number of problems, 2: first problem */
				_n( '%1$d problem found, nothing was imported: %2$s', '%1$d problems found, nothing was imported. First: %2$s', $count, 'ohmylms' ),
				$count,
				self::describe( $problems[0] )
			),
			array(
				'status' => 400,
				'errors' => array_values( $problems ),
			)
		);
	}

	/**
	 * Is this array a sequential list (or empty)?
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	public static function is_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 ) || array() === $value;
	}
}
