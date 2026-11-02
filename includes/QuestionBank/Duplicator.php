<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Duplicating a question as a new question in the same family.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Utility\Transaction;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Duplicating a question as a new question in the same family.
 *
 * Used by `POST /questions/{id}/duplicate` (without a quiz). Callers check
 * {@see AccessPolicy::can_use_question()} on the source first.
 */
final class Duplicator {
	/**
	 * Duplicate a question (new UUID, same family). With a quiz, the copy replaces the original in
	 * that quiz — the way to edit a shared, read-only question.
	 *
	 * @param int $source_id Question to copy.
	 * @param int $quiz_id   Quiz whose reference to the source the copy replaces; 0 for none.
	 * @return array|WP_Error DraftWriter result of the copy (`id` is the new question).
	 */
	public static function duplicate( $source_id, $quiz_id = 0 ) {
		global $wpdb;
		$source_id = (int) $source_id;
		$quiz_id   = (int) $quiz_id;
		if ( $quiz_id && ! AccessPolicy::can_edit_quiz( $quiz_id ) ) {
			return AccessPolicy::denied();
		}
		if ( ! AccessPolicy::can_author() ) {
			return AccessPolicy::denied();
		}
		$source     = ohmylms_get_question( $source_id );
		$attributes = BankAttributes::get( $source_id );
		$options    = array_map(
			static function ( $option ) {
				return array(
					'answer'        => $option['answer'],
					'is_correct'    => $option['is_correct'],
					'order_number'  => $option['order_number'],
					'thumbnail_id'  => $option['thumbnail_id'] ?? 0,
					'matching_data' => $option['matching_data'] ?? array(),
				);
			},
			$source->get_questions()
		);
		$family     = $attributes['family_id'] ? $attributes['family_id'] : ( 'q-' . substr( $attributes['uuid'] ?? wp_generate_uuid4(), 0, 8 ) );
		$bank       = array(
			'family_id'  => $family,
			'difficulty' => $attributes['difficulty'] ?? 'standard',
			'source'     => $attributes['source'] ?? '',
			'secure'     => (int) ( $attributes['secure'] ?? 0 ),
		);
		if ( ! empty( $attributes['bank_id'] ) && Banks::can( (int) $attributes['bank_id'], 'edit' ) ) {
			$bank['bank_id'] = (int) $attributes['bank_id'];
		}
		$payload = array(
			/* translators: %s: name of the question being copied. */
			'name'         => sprintf( __( '%s (copy)', 'ohmylms' ), $source->get_name() ),
			'description'  => $source->get_description(),
			'settings'     => $source->get_settings(),
			'thumbnail_id' => $source->get_thumbnail_id(),
			'video_id'     => $source->get_video_id(),
			'questions'    => $options,
			'skills'       => SkillMap::current( $source_id ),
			'bank'         => $bank,
		);
		$order   = null;
		if ( $quiz_id ) {
			$order = $wpdb->get_var( $wpdb->prepare( 'SELECT order_number FROM %i WHERE quiz_id=%d AND question_id=%d', $wpdb->prefix . 'ohmylms_quiz_questions_relationship', $quiz_id, $source_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Quiz/question relationship table; no WordPress API or cache.
			if ( null !== $order ) {
				$payload['order_number'] = (int) $order;
			}
		}
		try {
			$saved = Transaction::run(
				static function () use ( $payload, $quiz_id, $source_id, $order ) {
					// The ErrorExceptions below only carry a WP_Error out of the transaction; they are
					// caught in this method and never output.
					$saved = DraftWriter::save( $payload, $quiz_id );
					if ( is_wp_error( $saved ) ) {
						throw new ErrorException( $saved ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- See above.
					}
					if ( $quiz_id && null !== $order ) {
						$removed = Usage::remove_from_quiz( $quiz_id, $source_id );
						if ( is_wp_error( $removed ) ) {
							throw new ErrorException( $removed ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- See above.
						}
					}
					return $saved;
				}
			);
		} catch ( ErrorException $error ) {
			return $error->error;
		}
		// The family links the copy to its source; the original stays unchanged.
		if ( empty( $attributes['family_id'] ) ) {
			BankAttributes::write( $source_id, array( 'family_id' => $family ) );
		}

		return $saved;
	}
}
