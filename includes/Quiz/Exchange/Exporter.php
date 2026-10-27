<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Writes a quiz and its questions to the OhMyLMS quiz file.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Quiz\Exchange;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Extensions\Settings;
use OhMyLMS\QuestionBank\AccessPolicy;
use OhMyLMS\QuestionBank\BankAttributes;
use OhMyLMS\QuestionBank\SkillMap;
use OhMyLMS\Quiz\QuizSkills;
use OhMyLMS\Skills\Taxonomy;

defined( 'ABSPATH' ) || exit;

/**
 * Export: the editable (draft) content of one quiz, with nothing about courses or attempts.
 */
final class Exporter {

	/**
	 * Export one quiz.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array|\WP_Error filename, mime, content, summary, warnings.
	 */
	public static function export( $quiz_id ) {
		$quiz_id = (int) $quiz_id;
		if ( get_post_type( $quiz_id ) !== OHMYLMS_QUIZ_CPT ) {
			return new \WP_Error( 'ohmylms_rest_invalid_quiz_id', __( 'Invalid ID.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		// The file contains every answer key, so only people who may edit the quiz can have it.
		if ( ! AccessPolicy::can_edit_quiz( $quiz_id ) ) {
			return AccessPolicy::denied( __( 'You are not allowed to export this quiz.', 'ohmylms' ) );
		}
		$document = self::document( $quiz_id );
		$warnings = array();
		foreach ( $document['questions'] as $index => $question ) {
			foreach ( QuestionCheck::problems( $question ) as $message ) {
				$warnings[] = Document::problem(
					$index + 1,
					/* translators: %s: what is wrong */
					sprintf( __( 'This question is unfinished (%s). The file will be refused on import until it is fixed.', 'ohmylms' ), $message )
				);
			}
		}
		$base = trim( sanitize_file_name( $document['quiz']['name'] ), '.-_ ' );
		return array(
			'format'   => 'ohmylms',
			'filename' => ( '' !== $base ? $base : 'quiz' ) . '.ohmylms.json',
			'mime'     => 'application/json',
			'content'  => Document::render( $document ),
			'summary'  => Document::summary( $document ),
			'warnings' => $warnings,
		);
	}

	/**
	 * The quiz as a normalized document.
	 *
	 * @param int $quiz_id Quiz ID.
	 * @return array
	 */
	public static function document( $quiz_id ) {
		$quiz      = ohmylms_get_quiz( (int) $quiz_id );
		$uploads   = wp_get_upload_dir();
		$drip      = $quiz->get_drip_settings();
		$questions = array();
		foreach ( $quiz->get_questions() as $row ) {
			$questions[] = self::question( $row );
		}
		return array(
			'source'    => array(
				'site'        => home_url(),
				'uploads_url' => rtrim( (string) ( $uploads['baseurl'] ?? '' ), '/' ),
				'exported'    => gmdate( 'c' ),
				'generator'   => 'OhMyLMS ' . ( defined( 'OHMYLMS_VERSION' ) ? OHMYLMS_VERSION : '' ),
			),
			'quiz'      => array(
				'name'               => (string) $quiz->get_name(),
				'description'        => (string) $quiz->get_description(),
				'settings'           => (array) $quiz->get_settings(),
				'drip_settings'      => is_array( $drip ) ? $drip : array(),
				'extension_settings' => self::extension_settings( $quiz->get_id(), 'quiz' ),
				'skills'             => self::refs( QuizSkills::get( $quiz->get_id() ) ),
			),
			'questions' => $questions,
		);
	}

	/**
	 * One question row (as the quiz data store returns it) in file shape.
	 *
	 * @param array $row Question row with its option rows under `questions`.
	 * @return array
	 */
	private static function question( array $row ) {
		$id       = (int) $row['id'];
		$settings = is_array( $row['settings'] ?? null ) ? $row['settings'] : array();
		$options  = array();
		foreach ( (array) ( $row['questions'] ?? array() ) as $option ) {
			$entry = array(
				'answer'     => (string) ( $option['answer'] ?? '' ),
				'is_correct' => ! empty( $option['is_correct'] ) && '0' !== (string) $option['is_correct'],
				'image_url'  => (string) ( $option['image_url'] ?? '' ),
			);
			$data  = $option['matching_data'] ?? null;
			// Reorder and matching options always carry this object, even when it is empty.
			if ( is_array( $data ) && $data ) {
				$entry['matching_data'] = array(
					'label'     => (string) ( $data['label'] ?? '' ),
					'image_url' => (string) ( $data['image_url'] ?? '' ),
				);
			}
			$options[] = $entry;
		}
		$settings['type'] = (string) ( $settings['type'] ?? '' );
		return array(
			'name'               => (string) ( $row['name'] ?? '' ),
			'description'        => (string) ( $row['description'] ?? '' ),
			'settings'           => $settings,
			'options'            => $options,
			// The original file, not the resized "large" copy the editor shows.
			'image_url'          => ! empty( $row['thumbnail_id'] ) ? (string) wp_get_attachment_url( (int) $row['thumbnail_id'] ) : '',
			'video_url'          => (string) ( $row['video_src'] ?? '' ),
			'skills'             => self::skill_map( $id ),
			'bank'               => self::bank( $id ),
			'extension_settings' => self::extension_settings( $id, 'question' ),
		);
	}

	/**
	 * Settings that installed extensions keep on a quiz or question, by extension ID.
	 *
	 * @param int    $post_id Quiz or question ID.
	 * @param string $context `quiz` or `question`.
	 * @return array
	 */
	private static function extension_settings( $post_id, $context ) {
		$saved = get_post_meta( (int) $post_id, '_ohmylms_extension_settings', true );
		if ( ! is_array( $saved ) ) {
			return array();
		}
		$settings = array();
		foreach ( Settings::manifest() as $id => $definition ) {
			if ( isset( $saved[ $id ] ) && in_array( $context, $definition['contexts'], true ) ) {
				$settings[ $id ] = $saved[ $id ];
			}
		}
		return $settings;
	}

	/**
	 * Part-level skill mapping with portable references.
	 *
	 * @param int $question_id Question ID.
	 * @return array
	 */
	private static function skill_map( $question_id ) {
		$map = array();
		foreach ( SkillMap::current( $question_id ) as $part => $roles ) {
			$primary    = $roles['primary'] ? self::refs( array( $roles['primary'] ) ) : array();
			$supporting = self::refs( $roles['supporting'] );
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
	 * Bank attributes worth keeping (defaults are left out).
	 *
	 * @param int $question_id Question ID.
	 * @return array
	 */
	private static function bank( $question_id ) {
		if ( ! Schema::ready() ) {
			return array();
		}
		$attributes = BankAttributes::get( $question_id );
		$bank       = array();
		if ( ! empty( $attributes['difficulty'] ) && 'standard' !== $attributes['difficulty'] ) {
			$bank['difficulty'] = $attributes['difficulty'];
		}
		if ( ! empty( $attributes['source'] ) ) {
			$bank['source'] = $attributes['source'];
		}
		if ( ! empty( $attributes['family_id'] ) ) {
			$bank['family_id'] = $attributes['family_id'];
		}
		if ( ! empty( $attributes['secure'] ) ) {
			$bank['secure'] = true;
		}
		return $bank;
	}

	/**
	 * Skill term IDs as portable references: the uuid travels between sites, the slug is the fallback.
	 *
	 * @param int[] $term_ids Skill term IDs.
	 * @return array[]
	 */
	private static function refs( array $term_ids ) {
		$refs = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( (int) $term_id, Taxonomy::NAME );
			if ( $term instanceof \WP_Term ) {
				$refs[] = array(
					'uuid' => Taxonomy::uuid( $term->term_id ),
					'slug' => $term->slug,
					'name' => $term->name,
				);
			}
		}
		return $refs;
	}
}
