<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Activity IRIs and definitions.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * Turns OhMyLMS content into xAPI activities.
 *
 * Activity IRIs are built from the site address and a stable ID, never from permalinks, which
 * change when a slug is edited. Questions use their permanent UUID rather than a post ID.
 */
final class Activities {

	/**
	 * IRI of an activity.
	 *
	 * @param string $base Site base URL.
	 * @param string $path Path segments, for example "course/12".
	 * @return string
	 */
	public static function iri( $base, $path ) {
		return trailingslashit( $base ) . 'xapi/activities/' . ltrim( $path, '/' );
	}

	/**
	 * Language tag for names and descriptions.
	 *
	 * @return string
	 */
	public static function language() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();

		return str_replace( '_', '-', $locale ? $locale : 'en_US' );
	}

	/**
	 * Course activity.
	 *
	 * @param int $course_id Course ID.
	 * @return array|null Null when the course no longer exists.
	 */
	public static function course( $course_id ) {
		return self::post_activity( (int) $course_id, 'course', 'course' );
	}

	/**
	 * Lesson-like content: lessons, quizzes and other course content.
	 *
	 * @param int $content_id Post ID.
	 * @return array|null
	 */
	public static function content( $content_id ) {
		$type = get_post_type( (int) $content_id );
		if ( ! $type ) {
			return null;
		}
		if ( OHMYLMS_QUIZ_CPT === $type ) {
			return self::post_activity( (int) $content_id, 'quiz', 'assessment' );
		}

		return self::post_activity( (int) $content_id, 'lesson', 'lesson' );
	}

	/**
	 * Skill activity.
	 *
	 * @param int $term_id Skill term ID.
	 * @return array|null
	 */
	public static function skill( $term_id ) {
		$skill = \OhMyLMS\Skills\Taxonomy::describe( (int) $term_id );
		if ( ! $skill ) {
			return null;
		}
		$definition = array(
			'type' => Vocabulary::activity_type( 'skill' ),
			'name' => array( self::language() => $skill['name'] ),
		);
		if ( '' !== (string) $skill['description'] ) {
			$definition['description'] = array( self::language() => self::plain( $skill['description'] ) );
		}

		return array(
			'objectType' => 'Activity',
			'id'         => self::iri( home_url(), 'skill/' . $skill['uuid'] ),
			'definition' => $definition,
		);
	}

	/**
	 * Question (or one part of a question) as an interaction activity.
	 *
	 * @param array  $version Question version row: question_uuid, type, title, body.
	 * @param string $part_id Part ID, or an empty string for the whole question.
	 * @return array
	 */
	public static function question( array $version, $part_id = '' ) {
		$path = 'question/' . $version['question_uuid'] . ( '' !== $part_id ? '/part/' . rawurlencode( $part_id ) : '' );
		$name = self::plain( $version['title'] );
		$body = self::plain( $version['body'] );
		if ( '' === $name ) {
			$name = self::excerpt( $body, 80 );
		}
		$definition = array(
			'type'            => Vocabulary::activity_type( 'interaction' ),
			'interactionType' => Vocabulary::interaction_type( $version['type'] ),
		);
		if ( '' !== $name ) {
			$definition['name'] = array( self::language() => $name . ( '' !== $part_id ? ' (' . $part_id . ')' : '' ) );
		}
		if ( '' !== $body ) {
			$definition['description'] = array( self::language() => self::excerpt( $body, 500 ) );
		}

		return array(
			'objectType' => 'Activity',
			'id'         => self::iri( home_url(), $path ),
			'definition' => $definition,
		);
	}

	/**
	 * Activity for a post, named after its title.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $segment IRI path segment.
	 * @param string $kind    Vocabulary kind.
	 * @return array|null
	 */
	private static function post_activity( $post_id, $segment, $kind ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}
		$title = self::plain( get_the_title( $post ) );

		return array(
			'objectType' => 'Activity',
			'id'         => self::iri( home_url(), $segment . '/' . $post_id ),
			'definition' => array(
				'type' => Vocabulary::activity_type( $kind ),
				'name' => array( self::language() => '' !== $title ? $title : ucfirst( $segment ) . ' #' . $post_id ),
			),
		);
	}

	/**
	 * Plain text from rich content: tags removed, entities decoded, whitespace collapsed.
	 *
	 * @param string $html Content.
	 * @return string
	 */
	public static function plain( $html ) {
		$text = wp_strip_all_tags( (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$collapsed = preg_replace( '/\s+/u', ' ', $text );

		return trim( null === $collapsed ? $text : $collapsed );
	}

	/**
	 * Shorten text to a number of characters, adding an ellipsis when cut.
	 *
	 * @param string $text   Plain text.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function excerpt( $text, $length ) {
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text, 'UTF-8' ) > $length ) {
			return rtrim( mb_substr( $text, 0, $length - 1, 'UTF-8' ) ) . "\u{2026}";
		}

		return $text;
	}
}
