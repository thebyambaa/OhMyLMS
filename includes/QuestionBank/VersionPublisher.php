<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Assigns persistent UUIDs and captures immutable question versions.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

use OhMyLMS\Assessment\QuestionSnapshot;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Extensions\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Assigns persistent UUIDs and captures immutable question versions.
 *
 * A version freezes everything needed to show and grade the question later:
 * text, settings, options with correctness, media references, extension data
 * and part IDs. Identical content reuses the latest version (content hash).
 */
final class VersionPublisher {
	const SCHEMA_VERSION = 1;

	/** Identity/bank row for a question, created on first use.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param bool  $create Create.
	 */
	public static function identity( $question_id, $create = true ) {
		global $wpdb;
		$question_id = (int) $question_id;
		$table       = Schema::table( 'qb_questions' );
		$row         = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE question_id=%d', $table, $question_id ), ARRAY_A );
		if ( $row || ! $create || get_post_type( $question_id ) !== OHMYLMS_QUESTION_CPT ) {
			return $row ? $row : null; }
		$settings = (array) get_post_meta( $question_id, '_question_settings', true );
		$wpdb->query(
			$wpdb->prepare( 'INSERT IGNORE INTO %i (question_id, uuid, status, type, author_id, family_id, updated_at) VALUES (%d, %s, %s, %s, %d, %s, %s)', $table, $question_id, wp_generate_uuid4(), get_post_status( $question_id ) === Usage::ARCHIVED ? 'archived' : 'draft', (string) ( $settings['type'] ?? '' ), (int) get_post_field( 'post_author', $question_id ), '', current_time( 'mysql', true ) )
		);
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE question_id=%d', $table, $question_id ), ARRAY_A );
	}

	/**
	 * Uuid for Version publisher.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function uuid( $question_id ) {
		$identity = self::identity( $question_id );
		return $identity ? $identity['uuid'] : '';
	}

	/**
	 * Question by uuid for Version publisher.
	 *
	 * @param mixed $uuid Uuid.
	 */
	public static function question_by_uuid( $uuid ) {
		global $wpdb;
		$table = Schema::table( 'qb_questions' );
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT question_id FROM %i WHERE uuid=%s', $table, (string) $uuid ) );
	}

	/** Canonical, complete content of the current editable question.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function content( $question_id ) {
		$question = ohmylms_get_question( (int) $question_id );
		if ( ! $question ) {
			return null; }
		$settings = $question->get_settings();
		$options  = array();
		foreach ( $question->get_questions() as $option ) {
			$options[] = array(
				'id'            => (int) $option['id'],
				'answer'        => (string) $option['answer'],
				'order_number'  => (int) $option['order_number'],
				'is_correct'    => (int) ! empty( $option['is_correct'] ),
				'thumbnail_id'  => (int) ( $option['thumbnail_id'] ?? 0 ),
				'image_url'     => (string) ( $option['image_url'] ?? '' ),
				'matching_data' => is_array( $option['matching_data'] ?? null ) ? $option['matching_data'] : array(),
			);
		}
		$type       = (string) ( $settings['type'] ?? '' );
		$definition = Registry::get( 'question', $type );
		$parts      = array(
			array(
				'id'       => 'p1',
				'fraction' => 1,
			),
		);
		if ( in_array( $type, array( 'structured', 'passage' ), true ) ) {
			// Part weights follow part marks, so evidence is attributed per part.
			$parts = array();
			foreach ( \OhMyLMS\Assessment\Structured::weights( $settings ) as $part_id => $weight ) {
				$parts[] = array(
					'id'       => $part_id,
					'fraction' => round( $weight, 6 ),
				); }
		}
		$extension = get_post_meta( (int) $question_id, '_ohmylms_extension_settings', true );
		return array(
			'type'           => $type,
			'grader_version' => (string) ( $definition['version'] ?? '1' ),
			'title'          => (string) $question->get_name(),
			'body'           => (string) $question->get_description(),
			'settings'       => $settings,
			'options'        => $options,
			'media'          => array(
				'thumbnail_id' => (int) $question->get_thumbnail_id(),
				'image_id'     => (int) $question->get_image_id(),
				'image_url'    => (string) $question->get_image_url(),
				'video_id'     => (int) $question->get_video_id(),
				'video_url'    => (string) $question->get_video_url(),
			),
			'extension'      => is_array( $extension ) ? $extension : array(),
			'parts'          => $parts,
			// Mapping changes create a new version so evidence always cites the mapping it used.
			'skills'         => SkillMap::current( (int) $question_id ),
		);
	}

	/**
	 * Hash for Version publisher.
	 *
	 * @param array $content Content.
	 */
	public static function hash( array $content ) {
		return hash( 'sha256', wp_json_encode( $content ) );
	}

	/**
	 * Capture the current content as a version unless it equals the latest one.
	 *
	 * @return array|null Decoded version row.
	 * @param mixed $question_id Question identifier.
	 * @param bool  $migration Migration.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function capture( $question_id, $migration = false ) {
		global $wpdb;
		$question_id = (int) $question_id;
		$content     = self::content( $question_id );
		$identity    = self::identity( $question_id );
		if ( ! $content || ! $identity ) {
			return null; }
		$hash     = self::hash( $content );
		$versions = Schema::table( 'qb_question_versions' );
		$latest   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE question_id=%d ORDER BY version_no DESC LIMIT 1', $versions, $question_id ), ARRAY_A );
		if ( $latest && $latest['content_hash'] === $hash && ! self::media_replaced( $latest, $content ) ) {
			self::sync_identity( $question_id, (int) $latest['id'], (int) $latest['version_no'], $content['type'] );
			return $latest;
		}
		$version_no = $latest ? (int) $latest['version_no'] + 1 : 1;
		$saved      = $wpdb->insert(
			$versions,
			array(
				'question_id'           => $question_id,
				'question_uuid'         => $identity['uuid'],
				'version_no'            => $version_no,
				'content_hash'          => $hash,
				'type'                  => $content['type'],
				'schema_version'        => self::SCHEMA_VERSION,
				'grader_version'        => $content['grader_version'],
				'title'                 => $content['title'],
				'body'                  => $content['body'],
				'settings'              => wp_json_encode( $content['settings'] ),
				'options'               => wp_json_encode( $content['options'] ),
				// Frozen media copies are not part of the content hash; a replaced file is detected separately.
				'media'                 => wp_json_encode( $content['media'] + array( 'frozen' => (object) MediaFreezer::freeze( $content ) ) ),
				'extension'             => wp_json_encode( $content['extension'] ),
				'parts'                 => wp_json_encode( $content['parts'] ),
				'is_migration_snapshot' => $migration ? 1 : 0,
				'created_by'            => get_current_user_id(),
				'created_at'            => current_time( 'mysql', true ),
			)
		);
		if ( ! $saved ) {
			// A concurrent capture may have written the same version number.
			$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE question_id=%d AND content_hash=%s ORDER BY version_no DESC LIMIT 1', $versions, $question_id, $hash ), ARRAY_A );
			if ( $existing ) {
				return $existing; }
			throw new \RuntimeException( 'Question version write failed' );
		}
		$version_id = (int) $wpdb->insert_id;
		self::sync_identity( $question_id, $version_id, $version_no, $content['type'] );
		SkillMap::freeze( $question_id, $version_id );
		do_action( 'ohmylms_question_version_created', $question_id, $version_id, $version_no );
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $versions, $version_id ), ARRAY_A );
	}

	/** A media file the latest version froze was replaced in place.
	 *
	 * @param array $latest Latest.
	 * @param array $content Content.
	 */
	private static function media_replaced( array $latest, array $content ) {
		$media = json_decode( (string) $latest['media'], true );
		return is_array( $media ) && ! empty( $media['frozen'] ) && MediaFreezer::changed( (array) $media['frozen'], $content );
	}

	/**
	 * Sync identity for Version publisher.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param mixed $version_id Version identifier.
	 * @param mixed $version_no Version no.
	 * @param mixed $type Type.
	 */
	private static function sync_identity( $question_id, $version_id, $version_no, $type ) {
		global $wpdb;
		$wpdb->update(
			Schema::table( 'qb_questions' ),
			array(
				'current_version_id' => $version_id,
				'latest_version_no'  => $version_no,
				'type'               => $type,
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( 'question_id' => $question_id )
		);
	}

	/**
	 * Version for Version publisher.
	 *
	 * @param mixed $version_id Version identifier.
	 */
	public static function version( $version_id ) {
		global $wpdb;
		static $cache = array();
		$version_id   = (int) $version_id;
		if ( ! isset( $cache[ $version_id ] ) ) {
			$table                = Schema::table( 'qb_question_versions' );
			$cache[ $version_id ] = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', $table, $version_id ), ARRAY_A );
		}
		return $cache[ $version_id ];
	}

	/**
	 * Snapshot for Version publisher.
	 *
	 * @param mixed $version_id Version identifier.
	 */
	public static function snapshot( $version_id ) {
		return QuestionSnapshot::from_row( self::version( $version_id ) );
	}

	/** All versions of a question, newest first (without large columns).
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function history( $question_id ) {
		global $wpdb;
		$table = Schema::table( 'qb_question_versions' );
		return $wpdb->get_results( $wpdb->prepare( 'SELECT id, version_no, content_hash, type, title, is_migration_snapshot, created_by, created_at FROM %i WHERE question_id=%d ORDER BY version_no DESC', $table, (int) $question_id ), ARRAY_A );
	}

	/** Can this question type grade a frozen version? Unknown or legacy-only types cannot.
	 *
	 * @param mixed $type Type.
	 */
	public static function supports_snapshots( $type ) {
		$definition = Registry::get( 'question', (string) $type );
		return $definition && ! empty( $definition['snapshot'] );
	}
}
