<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Pseudonymous guest practice and the one-time claim that attaches it to an account.
 *
 * @package OhMyLMS
 */
namespace OhMyLMS\Practice;

use OhMyLMS\Assessment\Schema;
use OhMyLMS\Skills\Evidence;
use OhMyLMS\Utility\Transaction;

defined( 'ABSPATH' ) || exit;

/**
 * Pseudonymous guest practice and the one-time claim that attaches it to an account.
 *
 * The browser keeps a random credential; the server stores only its hash. Results are
 * graded and stored server-side, so a claim attaches server records only (never
 * client-reported scores). A credential can be claimed once, by one account; repeating
 * the claim from that account is harmless, any other account is refused.
 */
final class Guests {
	const TTL_DAYS = 30;
	const HEADER   = 'x-ohmylms-guest';

	/**
	 * Create for Guests.
	 *
	 * @return array{token:string,id:int,expires_at:string} */
	public static function create() {
		global $wpdb;
		$token   = wp_generate_password( 43, false, false );
		$expires = gmdate( 'Y-m-d H:i:s', time() + self::TTL_DAYS * DAY_IN_SECONDS );
		$wpdb->insert(
			Schema::table( 'guest_sessions' ),
			array(
				'token_hash' => hash( 'sha256', $token ),
				'created_at' => current_time( 'mysql', true ),
				'expires_at' => $expires,
			)
		);
		return array(
			'token'      => $token,
			'id'         => (int) $wpdb->insert_id,
			'expires_at' => $expires,
		);
	}

	/** Active, unclaimed guest session for a credential, or null.
	 *
	 * @param mixed $token Token.
	 */
	public static function resolve( $token ) {
		global $wpdb;
		if ( ! is_string( $token ) || strlen( $token ) < 20 ) {
			return null; }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE token_hash=%s', Schema::table( 'guest_sessions' ), hash( 'sha256', $token ) ), ARRAY_A );
		if ( ! $row || (int) $row['claimed_by'] || strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
			return null; }
		return $row;
	}

	/** Guest owner from the request header (REST).
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function from_request( \WP_REST_Request $request ) {
		$token = $request->get_header( self::HEADER );
		return $token ? self::resolve( $token ) : null;
	}

	/** Attach a guest's server-side results to the current account, once.
	 *
	 * @param mixed $token Token.
	 * @param mixed $user_id User identifier.
	 */
	public static function claim( $token, $user_id ) {
		global $wpdb;
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return new \WP_Error( 'ohmylms_login_required', __( 'Please log in first.', 'ohmylms' ), array( 'status' => 401 ) ); }
		if ( ! is_string( $token ) || '' === $token ) {
			return new \WP_Error( 'ohmylms_guest_invalid', __( 'Nothing to save from this device.', 'ohmylms' ), array( 'status' => 400 ) ); }
		$table = Schema::table( 'guest_sessions' );
		try {
			return Transaction::run(
				static function () use ( $wpdb, $table, $token, $user_id ) {
					$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE token_hash=%s FOR UPDATE', $table, hash( 'sha256', $token ) ), ARRAY_A );
					if ( ! $row ) {
						throw new \OhMyLMS\Assessment\ErrorException( new \WP_Error( 'ohmylms_guest_invalid', __( 'Nothing to save from this device.', 'ohmylms' ), array( 'status' => 404 ) ) ); }
					if ( (int) $row['claimed_by'] === $user_id ) {
						return array(
							'claimed'  => 0,
							'already'  => true,
							'sessions' => 0,
						); }
					if ( (int) $row['claimed_by'] ) {
						throw new \OhMyLMS\Assessment\ErrorException( new \WP_Error( 'ohmylms_guest_claimed', __( 'These results were already saved to another account.', 'ohmylms' ), array( 'status' => 409 ) ) ); }
					if ( strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
						throw new \OhMyLMS\Assessment\ErrorException( new \WP_Error( 'ohmylms_guest_expired', __( 'These practice results have expired.', 'ohmylms' ), array( 'status' => 410 ) ) ); }
					if ( $wpdb->query( $wpdb->prepare( 'UPDATE %i SET claimed_by=%d, claimed_at=%s WHERE id=%d AND claimed_by=0', $table, $user_id, current_time( 'mysql', true ), (int) $row['id'] ) ) !== 1 ) {
						throw new \RuntimeException( 'Claim race' );
					}
					$sessions = Schema::table( 'practice_sessions' );
					$ids      = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE guest_id=%d AND student_id=0', $sessions, (int) $row['id'] ) ) );
					if ( ! $ids ) {
						return array(
							'claimed'  => 0,
							'already'  => false,
							'sessions' => 0,
						); }
					$list = implode( ',', $ids );
					$wpdb->query( $wpdb->prepare( "UPDATE $sessions SET student_id=%d, claimed_at=%s WHERE id IN ($list)", $user_id, current_time( 'mysql', true ) ) );
					$events    = Schema::table( 'grade_events' );
					$event_ids = array_map( 'intval', $wpdb->get_col( "SELECT id FROM $events WHERE source_type IN ('practice','inline','placement') AND student_id=0 AND source_id IN ($list)" ) );
					if ( $event_ids ) {
						$wpdb->query( $wpdb->prepare( "UPDATE $events SET student_id=%d WHERE id IN (" . implode( ',', $event_ids ) . ')', $user_id ) );
						Evidence::release_waiting( $event_ids );
					}
					return array(
						'claimed'  => count( $event_ids ),
						'already'  => false,
						'sessions' => count( $ids ),
					);
				}
			);
		} catch ( \OhMyLMS\Assessment\ErrorException $error ) {
			return $error->error;
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'ohmylms_guest_claimed', __( 'These results were already saved.', 'ohmylms' ), array( 'status' => 409 ) );
		} finally {
			Evidence::soon();
		}
	}

	/** Daily: remove expired, unclaimed guest data. */
	public static function cleanup() {
		global $wpdb;
		$guests   = Schema::table( 'guest_sessions' );
		$sessions = Schema::table( 'practice_sessions' );
		$expired  = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE claimed_by=0 AND expires_at<%s LIMIT 500', $guests, gmdate( 'Y-m-d H:i:s' ) ) ) );
		foreach ( $expired as $guest ) {
			foreach ( array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE guest_id=%d AND student_id=0', $sessions, $guest ) ) ) as $session ) {
				$events = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE source_type IN (\'practice\',\'inline\') AND source_id=%d AND student_id=0', Schema::table( 'grade_events' ), $session ) ) );
				if ( $events ) {
					$list = implode( ',', $events );
					$wpdb->query( 'DELETE FROM ' . Schema::table( 'evidence_outbox' ) . " WHERE grade_event_id IN ($list)" );
					$wpdb->query( 'DELETE FROM ' . Schema::table( 'grade_events' ) . " WHERE id IN ($list)" );
				}
				$wpdb->delete( Schema::table( 'practice_items' ), array( 'session_id' => $session ) );
				$wpdb->delete( $sessions, array( 'id' => $session ) );
			}
			$wpdb->delete( $guests, array( 'id' => $guest ) );
		}
		return count( $expired );
	}
}
