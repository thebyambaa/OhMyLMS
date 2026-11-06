<?php // phpcs:ignore WordPress.Files.FileName -- Composer PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Maps studio question labels onto the registered question type ids.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

defined( 'ABSPATH' ) || exit;

/**
 * Studio aliases such as single_choice become the stored type single-choice.
 */
final class QuestionAliases {

	/**
	 * Registered type id, or an empty string when the alias is unknown.
	 *
	 * @param string $name Alias or stored type.
	 * @return string
	 */
	public static function canonical( $name ) {
		$key = strtolower( str_replace( '-', '_', (string) $name ) );
		$map = array(
			'single_choice'   => 'single-choice',
			'multiple_choice' => 'multiple-choice',
			'numerical'       => 'numerical',
			'short_answer'    => 'short-text',
			'true_false'      => 'true-false',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : '';
	}
}
