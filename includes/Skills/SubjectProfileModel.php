<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 namespace/class filename required by the existing Composer autoloader.
/**
 * Pure rules for subject profiles: keys, validation and level labels.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Skills;

defined( 'ABSPATH' ) || exit;

/**
 * A subject profile describes how skills of one subject are organised: its domains, its ordered
 * levels (school grades, CEFR levels, exam bands …) and the context AI analysis is given. Nothing
 * here touches WordPress, so the rules can be checked on their own.
 */
final class SubjectProfileModel {

	const MAX_DOMAINS = 60;
	const MAX_LEVELS  = 30;
	const MAX_LABEL   = 100;
	const MAX_LEVEL   = 60;
	const MAX_TEXT    = 2000;
	const AI_FIELDS   = array( 'role', 'context', 'level_hints' );

	/**
	 * The profile key of a subject name: lower case, words joined by hyphens. Any script is kept,
	 * so "Mathematics" is "mathematics" and "Математик" is "математик".
	 *
	 * @param mixed $subject Subject name or key.
	 * @return string Key, or empty.
	 */
	public static function key( $subject ) {
		$key = mb_strtolower( trim( (string) $subject ), 'UTF-8' );
		$key = trim( (string) preg_replace( '/[^\p{L}\p{N}]+/u', '-', $key ), '-' );
		return mb_substr( $key, 0, 40, 'UTF-8' );
	}

	/**
	 * Check and normalise a profile.
	 *
	 * @param array $input label, language, domains (id, label), levels (labels in order), ai (role, context, level_hints).
	 * @return array|string The normalised profile, or an error code: label, language, domains, domain, levels, level, ai.
	 */
	public static function normalize( array $input ) {
		$label = trim( (string) ( $input['label'] ?? '' ) );
		if ( '' === $label || mb_strlen( $label, 'UTF-8' ) > self::MAX_LABEL || '' === self::key( $label ) ) {
			return 'label';
		}
		$language = trim( (string) ( $input['language'] ?? '' ) );
		if ( '' !== $language && ! preg_match( '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})?$/', $language ) ) {
			return 'language';
		}
		$domains = $input['domains'] ?? array();
		if ( ! is_array( $domains ) || count( $domains ) > self::MAX_DOMAINS ) {
			return 'domains';
		}
		$clean = array();
		foreach ( $domains as $domain ) {
			$id   = is_array( $domain ) ? (string) ( $domain['id'] ?? '' ) : '';
			$name = is_array( $domain ) ? trim( (string) ( $domain['label'] ?? '' ) ) : '';
			if ( ! preg_match( '/^[a-z][a-z0-9-]{0,39}$/', $id ) || isset( $clean[ $id ] ) || '' === $name || mb_strlen( $name, 'UTF-8' ) > self::MAX_LABEL ) {
				return 'domain';
			}
			$clean[ $id ] = $name;
		}
		$levels = $input['levels'] ?? array();
		if ( ! is_array( $levels ) || ! $levels || count( $levels ) > self::MAX_LEVELS ) {
			return 'levels';
		}
		$ordered = array();
		foreach ( array_values( $levels ) as $level ) {
			$name = trim( (string) ( is_array( $level ) ? ( $level['label'] ?? '' ) : $level ) );
			if ( '' === $name || mb_strlen( $name, 'UTF-8' ) > self::MAX_LEVEL ) {
				return 'level';
			}
			$ordered[] = $name;
		}
		$ai = $input['ai'] ?? array();
		if ( ! is_array( $ai ) ) {
			return 'ai';
		}
		$context = array();
		foreach ( self::AI_FIELDS as $field ) {
			$text = trim( (string) ( $ai[ $field ] ?? '' ) );
			if ( mb_strlen( $text, 'UTF-8' ) > self::MAX_TEXT ) {
				return 'ai';
			}
			$context[ $field ] = $text;
		}
		$domain_rows = array();
		foreach ( $clean as $id => $name ) {
			$domain_rows[] = array(
				'id'    => $id,
				'label' => $name,
			);
		}
		return array(
			'label'    => $label,
			'language' => $language,
			'domains'  => $domain_rows,
			'levels'   => $ordered,
			'ai'       => $context,
		);
	}

	/**
	 * Domains of a profile keyed by id.
	 *
	 * @param array $profile Normalised profile.
	 * @return array<string,string>
	 */
	public static function domain_map( array $profile ) {
		return array_column( $profile['domains'] ?? array(), 'label', 'id' );
	}

	/**
	 * Levels of a profile as ordinals 1 … n with their labels. Stored skill bands use these ordinals.
	 *
	 * @param array $profile Normalised profile.
	 * @return array[] value, label.
	 */
	public static function levels( array $profile ) {
		$result = array();
		foreach ( array_values( $profile['levels'] ?? array() ) as $index => $label ) {
			$result[] = array(
				'value' => $index + 1,
				'label' => $label,
			);
		}
		return $result;
	}

	/**
	 * Label of a level ordinal. Values above the scale read as its last level.
	 *
	 * @param array $profile Normalised profile.
	 * @param int   $value   Level ordinal.
	 * @return string Label, or empty when not set.
	 */
	public static function level_label( array $profile, $value ) {
		$value  = (int) $value;
		$levels = array_values( $profile['levels'] ?? array() );
		if ( $value <= 0 || ! $levels ) {
			return '';
		}
		return $levels[ min( $value, count( $levels ) ) - 1 ];
	}
}
