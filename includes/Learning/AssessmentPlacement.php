<?php // phpcs:ignore WordPress.Files.FileName -- Required Composer PSR-4 class/file mapping.
/**
 * Structural locations for unit tests and course challenges.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Assessment\AssessmentSettings;
use OhMyLMS\Assessment\Schema as AssessmentSchema;

defined( 'ABSPATH' ) || exit;

/** Assessment definitions remain shared; only their course placement is validated here. */
final class AssessmentPlacement {

	/**
	 * Validate assessment locations without modifying their versioned definitions.
	 *
	 * @param array $items Normalized program items.
	 * @return string|null Error message, or null.
	 */
	public static function validate( array $items ) {
		if ( ! AssessmentSchema::ready() ) {
			return null;
		}
		$tests     = array();
		$last      = array();
		$challenge = null;
		foreach ( $items as $position => $item ) {
			$chapter          = (int) $item['chapter_id'];
			$last[ $chapter ] = $position;
			if ( 'quiz' !== $item['type'] ) {
				continue;
			}
			$style = AssessmentSettings::get( $item['content_id'] )['style'];
			if ( 'unit-test' === $style ) {
				if ( ! $chapter || ! empty( $item['group_id'] ) || isset( $tests[ $chapter ] ) ) {
					return __( 'Place one unit test at the end of its unit, outside lesson groups.', 'ohmylms' );
				}
				$tests[ $chapter ] = $position;
			}
			if ( 'course-challenge' === $style ) {
				if ( $chapter || ! empty( $item['group_id'] ) || null !== $challenge ) {
					return __( 'Place one course challenge at course level, outside units and lesson groups.', 'ohmylms' );
				}
				$challenge = $position;
			}
		}
		foreach ( $tests as $chapter => $position ) {
			if ( $last[ $chapter ] !== $position ) {
				return __( 'The unit test must be the last activity in its unit.', 'ohmylms' );
			}
		}
		if ( null !== $challenge && count( $items ) - 1 !== $challenge ) {
			return __( 'The course challenge must follow all other course activities.', 'ohmylms' );
		}
		return null;
	}
}
