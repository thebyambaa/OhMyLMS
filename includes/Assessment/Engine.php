<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Release switches. Each defaults on and can be turned off with a constant
 * (wp-config.php) or filter. Turning the engine off stops new versioned
 * launches only; attempts already started on it keep their reader/writer.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Assessment;

defined( 'ABSPATH' ) || exit;

/**
 * Release switches. Each defaults on and can be turned off with a constant
 * (wp-config.php) or filter. Turning the engine off stops new versioned
 * launches only; attempts already started on it keep their reader/writer.
 */
final class Engine {
	/** New quiz launches snapshot a published revision and deliver frozen items. */
	public static function versioned() {
		$enabled = defined( 'OHMYLMS_VERSIONED_ENGINE' ) ? (bool) OHMYLMS_VERSIONED_ENGINE : true;
		return (bool) apply_filters( 'ohmylms_versioned_engine_enabled', $enabled );
	}

	/** Skill practice, inline checks and guest practice. */
	public static function practice() {
		$enabled = defined( 'OHMYLMS_PRACTICE_ENABLED' ) ? (bool) OHMYLMS_PRACTICE_ENABLED : true;
		return (bool) apply_filters( 'ohmylms_practice_enabled', $enabled );
	}

	/** Question bank, skills and performance admin screens. */
	public static function bank_ui() {
		$enabled = defined( 'OHMYLMS_QUESTION_BANK_UI' ) ? (bool) OHMYLMS_QUESTION_BANK_UI : true;
		return (bool) apply_filters( 'ohmylms_question_bank_ui_enabled', $enabled );
	}
}
