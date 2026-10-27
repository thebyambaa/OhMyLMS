<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the Composer PSR-4 class/file mapping.
/**
 * Standalone Gutenberg question authoring integration.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Learning;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\QuestionBank\Banks;

defined( 'ABSPATH' ) || exit;

/** Connect native screens to existing bank permissions and reference-only blocks. */
final class NativeQuestionEditor {
	/** Register native editor boundaries. */
	public static function init() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'bank_capabilities' ), 10, 4 );
		add_filter( 'block_editor_settings_all', array( __CLASS__, 'editor_settings' ), 10, 2 );
	}

	/**
	 * Let core open questions granted through a bank, without granting deletion or approval.
	 *
	 * @param string[] $caps Required capabilities.
	 * @param string   $cap Requested capability.
	 * @param int      $user_id User ID.
	 * @param array    $args Capability arguments.
	 * @return string[] Mapped capabilities.
	 */
	public static function bank_capabilities( $caps, $cap, $user_id, $args ) {
		$id = isset( $args[0] ) && is_numeric( $args[0] ) ? (int) $args[0] : 0;
		if ( in_array( $cap, array( 'edit_post', 'read_post' ), true ) && 'ohmylms-question' === get_post_type( $id ) && Schema::ready() ) {
			$bank = Banks::of_question( $id );
			if ( $bank && Banks::can( $bank, 'edit', $user_id ) ) {
				return array( 'exist' );
			}
		}
		return $caps;
	}

	/**
	 * A standalone question contains one fixed reference, edited using the shared controls.
	 *
	 * @param array  $settings Editor settings.
	 * @param object $context Editor context.
	 * @return array Settings.
	 */
	public static function editor_settings( $settings, $context ) {
		if ( isset( $context->post ) && 'ohmylms-question' === $context->post->post_type ) {
			$settings['template']           = array(
				array(
					NativeQuizEditor::QUESTION_BLOCK,
					array(
						'questionId' => (int) $context->post->ID,
						'delivery'   => 'bank',
						'lock'       => array(
							'move'   => true,
							'remove' => true,
						),
					),
				),
			);
			$settings['templateLock']       = 'all';
			$settings['allowedBlockTypes']  = array( NativeQuizEditor::QUESTION_BLOCK );
			$settings['canLockBlocks']      = false;
			$settings['codeEditingEnabled'] = false;
		}
		return $settings;
	}
}
