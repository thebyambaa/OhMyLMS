<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Verbs, activity types and extension IRIs used in statements.
 *
 * @package OhMyLMS\XApi
 */

namespace OhMyLMS\XApi;

defined( 'ABSPATH' ) || exit;

/**
 * The fixed xAPI vocabulary OhMyLMS emits.
 *
 * Verbs and activity types come from the ADL vocabulary so that any LRS or report understands
 * them. Anything specific to OhMyLMS lives under EXTENSION_BASE and is only ever additive.
 */
final class Vocabulary {

	const VERB_BASE      = 'http://adlnet.gov/expapi/verbs/';
	const ACTIVITY_BASE  = 'http://adlnet.gov/expapi/activities/';
	const EXTENSION_BASE = 'https://ohmylms.com/xapi/extensions/';

	/**
	 * Verb key => display name. IRI is VERB_BASE plus the key.
	 *
	 * @return array<string,string>
	 */
	public static function verbs() {
		return array(
			'registered' => 'registered',
			'completed'  => 'completed',
			'passed'     => 'passed',
			'failed'     => 'failed',
			'answered'   => 'answered',
			'mastered'   => 'mastered',
		);
	}

	/**
	 * Verb object for a statement.
	 *
	 * @param string $key Verb key.
	 * @return array
	 * @throws \InvalidArgumentException When the verb is not part of the vocabulary.
	 */
	public static function verb( $key ) {
		$verbs = self::verbs();
		if ( ! isset( $verbs[ $key ] ) ) {
			throw new \InvalidArgumentException( 'Unknown verb ' . esc_html( $key ) );
		}

		return array(
			'id'      => self::VERB_BASE . $key,
			'display' => array( 'en-US' => $verbs[ $key ] ),
		);
	}

	/**
	 * Activity type IRI for an OhMyLMS content kind.
	 *
	 * @param string $kind course|lesson|assessment|interaction|skill.
	 * @return string
	 * @throws \InvalidArgumentException When the kind is not part of the vocabulary.
	 */
	public static function activity_type( $kind ) {
		$types = array(
			'course'      => 'course',
			'lesson'      => 'lesson',
			'assessment'  => 'assessment',
			'interaction' => 'cmi.interaction',
			'skill'       => 'objective',
		);
		if ( ! isset( $types[ $kind ] ) ) {
			throw new \InvalidArgumentException( 'Unknown activity kind ' . esc_html( $kind ) );
		}

		return self::ACTIVITY_BASE . $types[ $kind ];
	}

	/**
	 * Extension IRI.
	 *
	 * @param string $name Extension name.
	 * @return string
	 */
	public static function extension( $name ) {
		return self::EXTENSION_BASE . $name;
	}

	/**
	 * Map an OhMyLMS question type to an xAPI interactionType. Unknown types are "other".
	 *
	 * @param string $question_type Question type slug.
	 * @return string
	 */
	public static function interaction_type( $question_type ) {
		$map = array(
			'single-choice'     => 'choice',
			'multiple-choice'   => 'choice',
			'true-false'        => 'true-false',
			'short-text'        => 'fill-in',
			'fill-in-the-blank' => 'fill-in',
			'long-text'         => 'long-fill-in',
			'matching'          => 'matching',
			'reorder'           => 'sequencing',
			'numerical'         => 'numeric',
		);

		return isset( $map[ $question_type ] ) ? $map[ $question_type ] : 'other';
	}
}
