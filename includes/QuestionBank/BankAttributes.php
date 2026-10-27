<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Indexed bank attributes of a question: bank, difficulty band, source,
 * family (near-identical variants) and the secure (exam-only) flag.
 * Stored as columns on qb_questions so filtering never scans JSON.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\QuestionBank;

use OhMyLMS\Assessment\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Indexed bank attributes of a question: bank, difficulty band, source,
 * family (near-identical variants) and the secure (exam-only) flag.
 * Stored as columns on qb_questions so filtering never scans JSON.
 */
final class BankAttributes {
	const DIFFICULTIES = array( 'easy', 'standard', 'challenge' );

	/**
	 * Prepare for Bank attributes.
	 *
	 * @param mixed $bank Bank.
	 * @param mixed $question_id Question identifier.
	 */
	public static function prepare( $bank, $question_id ) {
		if ( ! is_array( $bank ) ) {
			return new \WP_Error( 'ohmylms_bank_invalid', __( 'Bank attributes must be an object.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$clean = array();
		if ( isset( $bank['difficulty'] ) ) {
			if ( ! in_array( $bank['difficulty'], self::DIFFICULTIES, true ) ) {
				return new \WP_Error( 'ohmylms_bank_invalid', __( 'Difficulty must be easy, standard or challenge.', 'ohmylms' ), array( 'status' => 400 ) ); }
			$clean['difficulty'] = $bank['difficulty'];
		}
		if ( isset( $bank['source'] ) ) {
			$clean['source'] = mb_substr( sanitize_text_field( (string) $bank['source'] ), 0, 100 ); }
		if ( isset( $bank['family_id'] ) ) {
			$clean['family_id'] = mb_substr( sanitize_title( (string) $bank['family_id'] ), 0, 64 ); }
		if ( isset( $bank['secure'] ) ) {
			$clean['secure'] = ! empty( $bank['secure'] ) && 'false' !== $bank['secure'] ? 1 : 0; }
		if ( isset( $bank['bank_id'] ) ) {
			$bank_id = (int) $bank['bank_id'];
			if ( $bank_id && ! Banks::can( $bank_id, 'edit' ) ) {
				return AccessPolicy::denied( __( 'You cannot add questions to that bank.', 'ohmylms' ) ); }
			$clean['bank_id'] = $bank_id;
		}
		return $clean;
	}

	/**
	 * Write for Bank attributes.
	 *
	 * @param mixed $question_id Question identifier.
	 * @param array $attributes Attributes.
	 * @throws \RuntimeException When the operation cannot be completed.
	 */
	public static function write( $question_id, array $attributes ) {
		global $wpdb;
		if ( ! $attributes ) {
			return; }
		$attributes['updated_at'] = current_time( 'mysql', true );
		if ( $wpdb->update( Schema::table( 'qb_questions' ), $attributes, array( 'question_id' => (int) $question_id ) ) === false ) {
			throw new \RuntimeException( 'Bank attribute write failed' );
		}
	}

	/**
	 * Get for Bank attributes.
	 *
	 * @param mixed $question_id Question identifier.
	 */
	public static function get( $question_id ) {
		$identity = VersionPublisher::identity( $question_id, false );
		if ( ! $identity ) {
			return array(
				'bank_id'    => 0,
				'difficulty' => 'standard',
				'source'     => '',
				'family_id'  => '',
				'secure'     => 0,
				'status'     => 'draft',
			); }
		return array_intersect_key( $identity, array_flip( array( 'uuid', 'bank_id', 'difficulty', 'source', 'family_id', 'secure', 'status', 'current_version_id', 'approved_version_id', 'latest_version_no' ) ) );
	}
}
