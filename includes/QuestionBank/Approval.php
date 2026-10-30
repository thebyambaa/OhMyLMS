<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Approving a question version for shared use and practice.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

use OhMyLMS\Assessment\ErrorException;
use OhMyLMS\Assessment\Schema;
use OhMyLMS\Utility\Transaction;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Approving a question version for shared use and practice.
 *
 * Used by `POST /question-bank/{id}/approve` and `POST /questions/{id}/approve`. Callers check
 * {@see AccessPolicy::can_approve_question()} first.
 */
final class Approval {
	/**
	 * Approve a version of a question (default: the current version, captured when needed).
	 *
	 * @param int   $question_id Question ID.
	 * @param mixed $version_id  Version to approve; empty for the current version.
	 * @return array|WP_Error The approved version row.
	 */
	public static function approve( $question_id, $version_id = null ) {
		global $wpdb;
		$question_id = (int) $question_id;
		try {
			$version = Transaction::run(
				static function () use ( $question_id, $version_id ) {
					$version = $version_id ? VersionPublisher::version( (int) $version_id ) : VersionPublisher::capture( $question_id );
					// The ErrorExceptions below only carry a WP_Error out of the transaction; they are
					// caught in this method and never output.
					if ( ! $version || (int) $version['question_id'] !== $question_id ) {
						throw new ErrorException( new WP_Error( 'ohmylms_version_missing', __( 'Version not found for this question.', 'ohmylms' ), array( 'status' => 404 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- See above.
					}
					$type         = (string) $version['type'];
					$settings     = json_decode( (string) $version['settings'], true );
					$figure_check = \OhMyLMS\ExamImport\FigureContent::publishable( $settings ? $settings : array() );
					if ( is_wp_error( $figure_check ) ) {
						throw new ErrorException( $figure_check ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- See above.
					}
					if ( ! VersionPublisher::supports_snapshots( $type ) ) {
						throw new ErrorException( new WP_Error( 'quiz_type_unversioned', __( 'This question type cannot be approved for versioned use.', 'ohmylms' ), array( 'status' => 409 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- See above.
					}
					return $version;
				}
			);
		} catch ( ErrorException $error ) {
			return $error->error;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Question bank identity table; no WordPress API or cache.
		$wpdb->update(
			Schema::table( 'qb_questions' ),
			array(
				'approved_version_id' => (int) $version['id'],
				'status'              => 'approved',
				'updated_at'          => current_time( 'mysql', true ),
			),
			array( 'question_id' => $question_id )
		);
		do_action( 'ohmylms_question_version_approved', $question_id, (int) $version['id'], get_current_user_id() );

		return $version;
	}
}
