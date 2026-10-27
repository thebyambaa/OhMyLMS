<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Creates a draft quiz and its questions from a quiz file.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz\Exchange;

use OhMyLMS\Data\Quiz;
use OhMyLMS\Extensions\Settings;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\DraftWriter;
use OhMyLMS\QuestionBank\VersionPublisher;
use OhMyLMS\Quiz\CsvImport;
use OhMyLMS\Quiz\PlayerTemplates;
use OhMyLMS\Quiz\QuizSkills;
use OhMyLMS\Skills\Taxonomy;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Import: all or nothing, always a new draft.
 *
 * Every question goes through the same writer and validators as the question editor
 * (`DraftWriter`), so an imported question is exactly as functional as one built by hand:
 * it is versioned, validated by its type, and randomized templates are proven to work.
 * If any question is bad, nothing is created and every problem is listed.
 */
final class Importer {

	/**
	 * Skill references already looked up in this import.
	 *
	 * @var int[]
	 */
	private static $skill_cache = array();

	/**
	 * Import a file, or check it without importing (dry run).
	 *
	 * @param string $content File text (an OhMyLMS quiz file, or the older CSV layout).
	 * @param array  $args    name, format (auto|ohmylms|csv), dry_run, copy_media.
	 * @return array|\WP_Error id (unless dry run), name, format, dry_run, summary, warnings.
	 */
	public static function run( $content, array $args = array() ) {
		$args   = array_merge(
			array(
				'name'       => '',
				'format'     => 'auto',
				'dry_run'    => false,
				'copy_media' => true,
			),
			$args
		);
		$format = 'auto' === $args['format'] ? ( Document::looks_like( $content ) ? 'ohmylms' : 'csv' ) : $args['format'];
		if ( ! AccessPolicy::can_author() ) {
			return AccessPolicy::denied( __( 'You are not allowed to create quizzes.', 'ohmylms' ) );
		}
		if ( 'csv' === $format ) {
			return self::run_csv( (string) $content, $args );
		}
		if ( 'ohmylms' !== $format ) {
			return new \WP_Error( 'ohmylms_quiz_import_format', __( 'Unknown file format.', 'ohmylms' ), array( 'status' => 400 ) );
		}

		$parsed = Document::parse( $content );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		$document = $parsed['document'];
		$warnings = $parsed['warnings'];
		$missing  = array();

		self::$skill_cache = array();
		$problems          = array();
		$payloads          = array();
		$extensions        = array();
		foreach ( $document['questions'] as $index => $question ) {
			$number = $index + 1;
			foreach ( QuestionCheck::problems( $question ) as $message ) {
				$problems[] = Document::problem( $number, $message );
			}
			if ( $question['extension_settings'] ) {
				$prepared = Settings::prepare( 'question', $question['extension_settings'] );
				if ( is_wp_error( $prepared ) ) {
					/* translators: %s: reason */
					$problems[] = Document::problem( $number, sprintf( __( 'Extension settings cannot be imported: %s Enable the extension that owns them first.', 'ohmylms' ), $prepared->get_error_message() ) );
				} else {
					$extensions[ $number ] = $prepared;
				}
			}
			$payloads[] = self::payload( $question, $number, $missing, null );
		}
		$quiz_extensions = array();
		if ( $document['quiz']['extension_settings'] ) {
			$prepared = Settings::prepare( 'quiz', $document['quiz']['extension_settings'] );
			if ( is_wp_error( $prepared ) ) {
				/* translators: %s: reason */
				$problems[] = Document::problem( 0, sprintf( __( 'Quiz extension settings cannot be imported: %s Enable the extension that owns them first.', 'ohmylms' ), $prepared->get_error_message() ) );
			} else {
				$quiz_extensions = $prepared;
			}
		}
		// The editor's own writer rejects what the types reject (unknown type, bad settings, bad skills).
		$prepared = DraftWriter::prepare_many( $payloads, 0 );
		if ( is_wp_error( $prepared ) ) {
			foreach ( (array) ( $prepared->get_error_data()['errors'] ?? array() ) as $error ) {
				$problems[] = Document::problem( (int) $error['index'] + 1, (string) $error['message'] );
			}
		}
		$quiz_settings = self::quiz_settings( $document['quiz']['settings'], $warnings );
		if ( $problems ) {
			usort(
				$problems,
				static function ( $a, $b ) {
					return $a['question'] <=> $b['question'];
				}
			);
			return Document::failure( self::unique_problems( $problems ) );
		}

		$name     = sanitize_text_field( '' !== trim( (string) $args['name'] ) ? $args['name'] : $document['quiz']['name'] );
		$name     = '' !== $name ? $name : __( 'Imported quiz', 'ohmylms' );
		$unique   = self::unique_name( $name );
		$quiz_ids = self::skill_ids( $document['quiz']['skills'], $missing );
		$summary  = Document::summary( $document );
		$result   = array(
			'name'    => $unique,
			'renamed' => $unique !== $name,
			'format'  => 'ohmylms',
			'dry_run' => (bool) $args['dry_run'],
			'summary' => $summary,
		);
		if ( $missing ) {
			/* translators: %s: skill names */
			$warnings[] = Document::problem( 0, sprintf( __( 'These skills do not exist on this site and were not linked: %s', 'ohmylms' ), implode( ', ', array_keys( $missing ) ) ) );
		}
		if ( $args['dry_run'] ) {
			if ( $summary['media'] ) {
				/* translators: %d: number of files */
				$warnings[] = Document::problem( 0, sprintf( _n( '%d image or video will be copied into the media library.', '%d images and videos will be copied into the media library.', $summary['media'], 'ohmylms' ), $summary['media'] ) );
			}
			$result['warnings'] = self::flatten( $warnings );
			return $result;
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
		$media    = new Media( $document['source']['uploads_url'], (bool) $args['copy_media'] );
		$payloads = array();
		foreach ( $document['questions'] as $index => $question ) {
			$payloads[] = self::payload( $question, $index + 1, $missing, $media );
		}
		$description = $media->rewrite( $document['quiz']['description'], 0 );
		$failure     = null;
		// Extension settings are part of a question's frozen version, so they are saved right after
		// each new question is written and the version is captured again with them.
		$apply_extensions = static function ( $question_id, $created, $plan ) use ( $extensions ) {
			$number = (int) ( $plan['order_number'] ?? 0 );
			if ( $created && isset( $extensions[ $number ] ) ) {
				update_post_meta( $question_id, '_ohmylms_extension_settings', wp_slash( $extensions[ $number ] ) );
				VersionPublisher::capture( $question_id );
			}
		};
		add_action( 'ohmylms_question_draft_saved', $apply_extensions, 10, 3 );
		try {
			$quiz_id = Transaction::run(
				static function () use ( $unique, $description, $quiz_settings, $document, $quiz_ids, $quiz_extensions, $payloads, &$failure ) {
					$quiz = new Quiz();
					$quiz->set_name( $unique );
					$quiz->set_description( current_user_can( 'unfiltered_html' ) ? $description : wp_kses_post( $description ) );
					$quiz->set_status( 'draft' );
					$quiz->set_settings( $quiz_settings );
					if ( $document['quiz']['drip_settings'] ) {
						$quiz->set_drip_settings( $document['quiz']['drip_settings'] );
					}
					$id = (int) $quiz->save();
					if ( ! $id ) {
						throw new \RuntimeException( 'Quiz was not created' );
					}
					// The data store inserts quizzes as published; an import is always a draft.
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => 'draft',
						)
					);
					if ( $quiz_extensions ) {
						update_post_meta( $id, '_ohmylms_extension_settings', wp_slash( $quiz_extensions ) );
					}
					$skills = QuizSkills::set( $id, $quiz_ids );
					if ( is_wp_error( $skills ) ) {
						$failure = $skills;
						throw new \RuntimeException( 'Skills' );
					}
					$saved = DraftWriter::save_many( $payloads, $id );
					if ( is_wp_error( $saved ) ) {
						$failure = $saved;
						throw new \RuntimeException( 'Questions' );
					}
					return $id;
				}
			);
		} catch ( \Throwable $error ) {
			remove_action( 'ohmylms_question_draft_saved', $apply_extensions, 10 );
			$media->discard();
			return $failure ? $failure : new \WP_Error( 'ohmylms_quiz_import_failed', __( 'The quiz could not be imported. Nothing was changed.', 'ohmylms' ), array( 'status' => 500 ) );
		}
		remove_action( 'ohmylms_question_draft_saved', $apply_extensions, 10 );

		$warnings = array_merge( $warnings, $media->warnings() );
		if ( $media->copied() ) {
			$result['media_copied'] = $media->copied();
		}
		$result['id']       = $quiz_id;
		$result['warnings'] = self::flatten( $warnings );
		/**
		 * Fires after a quiz file was imported as a new draft quiz.
		 *
		 * @param int   $quiz_id  New quiz ID.
		 * @param array $document The normalized file contents.
		 */
		do_action( 'ohmylms_quiz_imported', $quiz_id, $document );
		return $result;
	}

	/**
	 * The older CSV layout, kept working with the same result shape.
	 *
	 * @param string $csv  CSV text.
	 * @param array  $args Import arguments.
	 * @return array|\WP_Error
	 */
	private static function run_csv( $csv, array $args ) {
		$items = CsvImport::parse( $csv );
		if ( is_wp_error( $items ) ) {
			return $items;
		}
		$types  = array();
		$points = 0.0;
		foreach ( $items as $item ) {
			$type           = $item['settings']['type'];
			$types[ $type ] = ( $types[ $type ] ?? 0 ) + 1;
			$points        += (float) $item['settings']['score']['value'];
		}
		$name   = sanitize_text_field( (string) $args['name'] );
		$result = array(
			'name'     => '' !== $name ? $name : __( 'Imported quiz', 'ohmylms' ),
			'format'   => 'csv',
			'dry_run'  => (bool) $args['dry_run'],
			'summary'  => array(
				'questions'  => count( $items ),
				'points'     => $points,
				'types'      => $types,
				'randomized' => 0,
				'media'      => 0,
			),
			'warnings' => array(),
		);
		if ( $args['dry_run'] ) {
			return $result;
		}
		$quiz_id = CsvImport::import( $result['name'], $csv );
		if ( is_wp_error( $quiz_id ) ) {
			return $quiz_id;
		}
		$result['id'] = $quiz_id;
		return $result;
	}

	/**
	 * One question in the shape the editor's writer takes.
	 *
	 * @param array      $question Normalized question.
	 * @param int        $number   1-based question number.
	 * @param array      $missing  Collects skills this site does not have.
	 * @param Media|null $media    Copies media when given; null for validation only.
	 * @return array
	 */
	private static function payload( array $question, $number, array &$missing, $media ) {
		$text    = static function ( $value ) use ( $media, $number ) {
			return $media ? $media->rewrite( $value, $number ) : $value;
		};
		$options = array();
		foreach ( $question['options'] as $position => $option ) {
			$row = array(
				'answer'       => $text( $option['answer'] ),
				'is_correct'   => $option['is_correct'] ? 1 : 0,
				'order_number' => $position + 1,
			);
			if ( $media && '' !== $option['image_url'] ) {
				$id = $media->attachment( $option['image_url'], $number, __( 'an option image', 'ohmylms' ) );
				if ( $id ) {
					$row['thumbnail_id'] = $id;
					$row['image_url']    = (string) wp_get_attachment_url( $id );
				}
			}
			if ( isset( $option['matching_data'] ) ) {
				$data = array(
					'label'     => $text( $option['matching_data']['label'] ),
					'image_id'  => '',
					'image_url' => $option['matching_data']['image_url'],
				);
				if ( $media && '' !== $data['image_url'] ) {
					$id = $media->attachment( $data['image_url'], $number, __( 'a matching image', 'ohmylms' ) );
					if ( $id ) {
						$data['image_id']  = $id;
						$data['image_url'] = (string) wp_get_attachment_url( $id );
					}
				}
				$row['matching_data'] = $data;
			}
			$options[] = $row;
		}
		$payload = array(
			'name'         => '' !== trim( $question['name'] ) ? $question['name'] : sprintf( /* translators: %d: question number */ __( 'Question %d', 'ohmylms' ), $number ),
			'description'  => $text( $question['description'] ),
			'status'       => 'publish',
			'settings'     => $text( $question['settings'] ),
			'questions'    => $options,
			'order_number' => $number,
		);
		if ( $media ) {
			$image = $media->attachment( $question['image_url'], $number, __( 'the question image', 'ohmylms' ) );
			$video = $media->attachment( $question['video_url'], $number, __( 'the question video', 'ohmylms' ) );
			if ( $image ) {
				$payload['thumbnail_id'] = $image;
			}
			if ( $video ) {
				$payload['video_id'] = $video;
			}
		}
		$map = array();
		foreach ( $question['skills'] as $part => $roles ) {
			$primary    = $roles['primary'] ? self::skill_id( $roles['primary'], $missing ) : 0;
			$supporting = array();
			foreach ( $roles['supporting'] as $ref ) {
				$id = self::skill_id( $ref, $missing );
				if ( $id ) {
					$supporting[] = $id;
				}
			}
			if ( $primary || $supporting ) {
				$map[ $part ] = array(
					'primary'    => $primary,
					'supporting' => $supporting,
				);
			}
		}
		if ( $map ) {
			$payload['skills'] = $map;
		}
		if ( $question['bank'] ) {
			$payload['bank'] = $question['bank'];
		}
		return $payload;
	}

	/**
	 * Quiz settings: kept as written, except a player template this site does not have.
	 *
	 * @param array $settings Settings from the file.
	 * @param array $warnings Collects warnings.
	 * @return array
	 */
	private static function quiz_settings( array $settings, array &$warnings ) {
		if ( isset( $settings['player_template'] ) && PlayerTemplates::resolve( $settings['player_template'] ) !== $settings['player_template'] ) {
			/* translators: %s: template name */
			$warnings[] = Document::problem( 0, sprintf( __( 'The player design "%s" is not installed here; the default design is used.', 'ohmylms' ), is_scalar( $settings['player_template'] ) ? $settings['player_template'] : '' ) );
			unset( $settings['player_template'] );
		}
		return $settings;
	}

	/**
	 * Skill term IDs for references, matching the uuid first and the slug second.
	 *
	 * @param array[] $refs    References.
	 * @param array   $missing Collects skills this site does not have.
	 * @return int[]
	 */
	private static function skill_ids( array $refs, array &$missing ) {
		$ids = array();
		foreach ( $refs as $ref ) {
			$id = self::skill_id( $ref, $missing );
			if ( $id ) {
				$ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * One skill reference.
	 *
	 * @param array $ref     uuid, slug, name.
	 * @param array $missing Collects skills this site does not have.
	 * @return int Term ID, or 0.
	 */
	private static function skill_id( array $ref, array &$missing ) {
		$key = $ref['uuid'] . '|' . $ref['slug'];
		if ( ! isset( self::$skill_cache[ $key ] ) ) {
			$id = '' !== $ref['uuid'] ? (int) Taxonomy::by_uuid( $ref['uuid'] ) : 0;
			if ( ! $id && '' !== $ref['slug'] ) {
				$term = get_term_by( 'slug', $ref['slug'], Taxonomy::NAME );
				$id   = $term instanceof \WP_Term ? (int) $term->term_id : 0;
			}
			self::$skill_cache[ $key ] = $id;
		}
		if ( ! self::$skill_cache[ $key ] ) {
			$label             = '' !== $ref['name'] ? $ref['name'] : ( '' !== $ref['slug'] ? $ref['slug'] : $ref['uuid'] );
			$missing[ $label ] = true;
		}
		return self::$skill_cache[ $key ];
	}

	/**
	 * A quiz name no other quiz uses, so a re-import is easy to tell from the original.
	 *
	 * @param string $name Wanted name.
	 * @return string
	 */
	private static function unique_name( $name ) {
		$existing = get_posts(
			array(
				'post_type'        => OHMYLMS_QUIZ_CPT,
				'title'            => $name,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		if ( ! $existing ) {
			return $name;
		}
		/* translators: 1: quiz name, 2: date and time */
		return sprintf( __( '%1$s (imported %2$s)', 'ohmylms' ), $name, wp_date( 'Y-m-d H:i' ) );
	}

	/**
	 * Drop repeats of the same problem on the same question.
	 *
	 * @param array[] $problems Problems.
	 * @return array[]
	 */
	private static function unique_problems( array $problems ) {
		$seen = array();
		$list = array();
		foreach ( $problems as $problem ) {
			$key = $problem['question'] . '|' . $problem['message'];
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$list[]       = $problem;
			}
		}
		return $list;
	}

	/**
	 * Warnings as display text.
	 *
	 * @param array[] $warnings Warnings.
	 * @return string[]
	 */
	private static function flatten( array $warnings ) {
		return array_values( array_unique( array_map( array( Document::class, 'describe' ), $warnings ) ) );
	}
}
