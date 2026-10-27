<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Hook for Quiz
 *
 * @package    OhMyLMSPro
 * @subpackage OhMyLMSPro/includes
 */
namespace OhMyLMS\Hooks;

/**
 * Plugin quiz hook implementation.
 */
class QuizHook {

	/**
	 * Register hooks for Quiz hook.
	 */
	public function register_hooks() {
		add_filter( 'ohmylms_quiz_type', array( $this, 'pro_quiz_type' ), 10 );
		add_filter( 'ohmylms_supported_question_types', array( $this, 'pro_question_types' ), 10 );
	}

	/**
	 * Pro quiz type for Quiz hook.
	 *
	 * @param mixed $types Types.
	 */
	public function pro_quiz_type( $types ) {
		$pro_types = array(
			array(
				'type' => 'fill-in-the-blank',
				'name' => 'Long Text',
			),
			array(
				'type' => 'statement',
				'name' => 'Statement',
			),
		);
		$types     = array_merge( $types, $pro_types );
		return $types;
	}

	/**
	 * Pro question types for Quiz hook.
	 *
	 * @param mixed $types Types.
	 */
	public function pro_question_types( $types ) {
		array_push( $types, 'fill-in-the-blank', 'statement' );
		return $types;
	}
}
