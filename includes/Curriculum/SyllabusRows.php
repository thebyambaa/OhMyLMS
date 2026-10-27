<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Cleans and checks the rows of a syllabus import before anything is planned or written. A row is
 * one line of the administrator's CSV after the browser mapped its columns:.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Curriculum;

/**
 * Cleans and checks the rows of a syllabus import before anything is planned or written. A row is
 * one line of the administrator's CSV after the browser mapped its columns:
 *
 *   content, content_code   a topic or chapter (several levels may be written "Paper 1 > Number")
 *   group, group_code       a skill group (a section, a skill family)
 *   skill, skill_code       one skill (a learning objective)
 *   description             notes or examples for the skill
 *
 * Pure PHP so the rules can be unit tested without WordPress. A row with a problem is reported with
 * its line number and the whole import then changes nothing.
 */
final class SyllabusRows {
	const MAX_ROWS           = 5000;
	const MAX_DEPTH          = 5;
	const NAME_LIMIT         = 190;
	const CONTENT_CODE_LIMIT = 60;
	const GROUP_CODE_LIMIT   = 60;
	const SKILL_CODE_LIMIT   = 40;
	const DESCRIPTION_LIMIT  = 2000;

	/** Text as valid UTF-8: stray bytes become "?" instead of making later pattern matching fail.
	 *
	 * @param mixed $value Value to process.
	 */
	private static function utf8( $value ) {
		$text = is_scalar( $value ) ? (string) $value : '';
		return function_exists( 'mb_convert_encoding' ) ? (string) mb_convert_encoding( $text, 'UTF-8', 'UTF-8' ) : $text;
	}

	/**
	 * One line of text: control characters and runs of whitespace removed. Angle brackets are kept
	 * (objectives such as "a < 0" are maths, not markup); WordPress sanitizes the text when it is saved.
	 *
	 * @param mixed $value Value to process.
	 */
	public static function line( $value ) {
		$text = self::utf8( $value );
		$text = preg_replace( '/^\xEF\xBB\xBF/', '', $text );
		$text = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	/** Several lines of text: control characters removed, line breaks kept.
	 *
	 * @param mixed $value Value to process.
	 */
	public static function notes( $value ) {
		$text = self::utf8( $value );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', ' ', (string) $text );
		$text = preg_replace( "/[ \t]+/u", ' ', (string) $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Length for Syllabus rows.
	 *
	 * @param mixed $text Text.
	 */
	private static function length( $text ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
	}

	/**
	 * Path for Syllabus rows.
	 * "Paper 1 > 1 Number" => ['Paper 1', '1 Number'].
	 *
	 * @param mixed $value Value to process.
	 */
	public static function path( $value ) {
		$parts = preg_split( '/\s*[>›»]\s*/u', self::line( $value ) );
		return array_values(
			array_filter(
				array_map( 'trim', $parts ? $parts : array() ),
				static function ( $part ) {
					return '' !== $part;
				}
			)
		);
	}

	/**
	 * Normalize.
	 *
	 * @param array[] $rows Rows as sent by the browser; `line` is the row's line in the file.
	 * @return array{rows: array[], errors: array[], warnings: array[], skipped: int}
	 */
	public static function normalize( array $rows ) {
		$clean    = array();
		$errors   = array();
		$warnings = array();
		$skipped  = 0;
		if ( count( $rows ) > self::MAX_ROWS ) {
			$errors[] = array(
				'line'    => 0,
				'message' => sprintf( /* translators: %d: number. */
				__( 'An import can have at most %d rows. Split the file and import it in parts.', 'ohmylms' ), self::MAX_ROWS ),
			);
			return array(
				'rows'     => array(),
				'errors'   => $errors,
				'warnings' => $warnings,
				'skipped'  => 0,
			);
		}
		$index = 0;
		foreach ( $rows as $row ) {
			++$index;
			$row          = is_array( $row ) ? $row : array();
			$line         = isset( $row['line'] ) ? (int) $row['line'] : $index;
			$path         = self::path( $row['content'] ?? '' );
			$content_code = self::line( $row['content_code'] ?? '' );
			$group        = self::line( $row['group'] ?? '' );
			$group_code   = self::line( $row['group_code'] ?? '' );
			$skill        = self::line( $row['skill'] ?? '' );
			$skill_code   = self::line( $row['skill_code'] ?? '' );
			$description  = self::notes( $row['description'] ?? '' );
			$category     = self::line( $row['category'] ?? '' );

			// A code with no name is allowed for contents and groups (the code is then the name).
			if ( ! $path && '' !== $content_code ) {
				$path         = array( $content_code );
				$content_code = ''; }
			if ( '' === $group && '' !== $group_code ) {
				$group = $group_code; }

			if ( ! $path && '' === $group && '' === $skill && '' === $skill_code ) {
				if ( '' !== $description ) {
					$warnings[] = array(
						'line'    => $line,
						'message' => __( 'Notes without a skill were ignored.', 'ohmylms' ),
					); }
				++$skipped;
				continue;
			}
			$problems = array();
			if ( self::length( $category ) > 60 ) {
				$problems[] = __( 'A skill category can have at most 60 characters.', 'ohmylms' ); }
			if ( '' === $skill && '' !== $skill_code ) {
				$problems[] = sprintf( /* translators: %s: text value. */
				__( 'Skill code “%s” has no skill name.', 'ohmylms' ), $skill_code ); }
			if ( count( $path ) > self::MAX_DEPTH ) {
				$problems[] = sprintf( /* translators: %d: number. */
				__( 'A content path can have at most %d levels.', 'ohmylms' ), self::MAX_DEPTH ); }
			foreach ( $path as $name ) {
				if ( self::length( $name ) > self::NAME_LIMIT ) {
					$problems[] = sprintf( /* translators: %d: number. */
					__( 'A content name can have at most %d characters.', 'ohmylms' ), self::NAME_LIMIT );
					break; }
			}
			if ( self::length( $group ) > self::NAME_LIMIT ) {
				$problems[] = sprintf( /* translators: %d: number. */
				__( 'A skill group name can have at most %d characters.', 'ohmylms' ), self::NAME_LIMIT ); }
			if ( self::length( $skill ) > self::NAME_LIMIT ) {
				$problems[] = sprintf( /* translators: %d: number. */
				__( 'A skill name can have at most %d characters.', 'ohmylms' ), self::NAME_LIMIT ); }
			if ( self::length( $content_code ) > self::CONTENT_CODE_LIMIT ) {
				$problems[] = sprintf( /* translators: %d: number. */
				__( 'A content code can have at most %d characters.', 'ohmylms' ), self::CONTENT_CODE_LIMIT ); }
			if ( self::length( $group_code ) > self::GROUP_CODE_LIMIT ) {
				$problems[] = sprintf( /* translators: %d: number. */
				__( 'A skill group code can have at most %d characters.', 'ohmylms' ), self::GROUP_CODE_LIMIT ); }
			if ( self::length( $skill_code ) > self::SKILL_CODE_LIMIT ) {
				$problems[] = sprintf( /* translators: %d: number. */
				__( 'A skill code can have at most %d characters.', 'ohmylms' ), self::SKILL_CODE_LIMIT ); }
			if ( self::length( $description ) > self::DESCRIPTION_LIMIT ) {
				$problems[] = sprintf( /* translators: %d: number. */
				__( 'Notes can have at most %d characters.', 'ohmylms' ), self::DESCRIPTION_LIMIT ); }
			if ( $problems ) {
				foreach ( $problems as $message ) {
					$errors[] = array(
						'line'    => $line,
						'message' => $message,
					); }
				continue;
			}
			if ( '' === $skill && '' !== $description ) {
				$warnings[]  = array(
					'line'    => $line,
					'message' => __( 'Notes without a skill were ignored.', 'ohmylms' ),
				);
				$description = '';
			}
			$clean[] = array(
				'line'         => $line,
				'content_path' => $path,
				'content_code' => $content_code,
				'group'        => $group,
				'group_code'   => $group_code,
				'skill'        => $skill,
				'skill_code'   => $skill_code,
				'description'  => $description,
				'category'     => $category,
			);
		}
		return array(
			'rows'     => $clean,
			'errors'   => $errors,
			'warnings' => $warnings,
			'skipped'  => $skipped,
		);
	}
}
