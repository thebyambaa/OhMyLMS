<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Achievements Helper class.
 *
 * This class handles the achievements system settings and functionality.
 *
 * @since 1.0.0
 * @package OhMyLMSPro
 */
namespace OhMyLMS\Engagement;

/**
 * Plugin achievements implementation.
 */
class Achievements {
	/** Serialize an award or spend for a learner, including the balance check.
	 *
	 * @param mixed    $user_id User identifier.
	 * @param callable $work Work.
	 */
	public static function locked( $user_id, callable $work ) {
		global $wpdb;
		$key = 'ohmylms-award-' . md5( $wpdb->prefix . ':' . (int) $user_id );
		if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $key ) ) !== 1 ) {
			return false; }
		try {
			return $work(); } finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) ); }
	}

	/**
	 * Insert a new achievement.
	 *
	 * @param array $data Input data.
	 * @return int|false
	 * @since 1.0.0
	 */
	public static function insert_achievement( $data ) {
		if ( ! is_array( $data ) || empty( $data['user_id'] ) || empty( $data['type'] ) ) {
			return false; }
		return self::locked(
			$data['user_id'],
			static function () use ( $data ) {
				return self::persist( $data );
			}
		);
	}

	/**
	 * Persist for Achievements.
	 *
	 * @param mixed $data Input data.
	 */
	private static function persist( $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_achievement';
		if ( ! is_array( $data ) || empty( $data ) ) {
			return false;
		}

		if ( ! isset( $data['user_id'] ) ) {
			return false; // User ID is required.
		}
		if ( isset( $data['id'] ) ) {
			// Update existing achievement.
			$where = array( 'id' => $data['id'] );
			return $wpdb->update( $table_name, $data, $where );
		}
		if ( self::achievement_exists( $data['user_id'], $data['type'], $data['course_id'] ?? null, $data['membership_id'] ?? null, $data['content_id'] ?? null, $data['badge_id'] ?? null, $data['level_id'] ?? null, $data['reason'] ?? null ) ) {
			return false; // Achievement already exists for this user and event.
		}

		$data = wp_parse_args(
			$data,
			array(
				'status' => 'active',
			)
		);

		return $wpdb->insert( $table_name, $data );
	}

	/**
	 * Check if an achievement exists for a user.
	 *
	 * @param int      $user_id User identifier.
	 * @param string   $type Type.
	 * @param int|null $course_id Course identifier.
	 * @param int|null $membership_id Membership identifier.
	 * @param int|null $content_id Content identifier.
	 * @param string   $badge_id Badge identifier.
	 * @param string   $level_id Level identifier.
	 * @param string   $reason Reason.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function achievement_exists( $user_id, $type, $course_id = null, $membership_id = null, $content_id = null, $badge_id = '', $level_id = '', $reason = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'ohmylms_user_achievement';

		// Base conditions that always apply.
		$where_clauses = array( 'user_id = %d', 'type = %s', 'status = %s' );
		$params        = array( $user_id, $type, 'active' );

		// Type-specific logic.
		switch ( $type ) {
			case 'badge':
				if ( ! empty( $badge_id ) ) {
					$where_clauses[] = 'badge_id = %s';
					$params[]        = $badge_id;
				} else {
					return false;
				}
				break;

			case 'level':
				if ( ! empty( $level_id ) ) {
					$where_clauses[] = 'level_id = %s';
					$params[]        = $level_id;
				} else {
					return false;
				}
				break;

			case 'point':
				// For points, we need to be more specific to avoid duplicate point awards
				// but not so specific that we miss legitimate duplicates.

				// Always include reason if provided.
				if ( ! empty( $reason ) ) {
					$where_clauses[] = 'reason = %s';
					$params[]        = $reason;
				}

				// Add contextual IDs based on what's provided.
				if ( $course_id ) {
					$where_clauses[] = 'course_id = %d';
					$params[]        = $course_id;
				}

				if ( $membership_id ) {
					$where_clauses[] = 'membership_id = %d';
					$params[]        = $membership_id;
				}

				if ( $content_id ) {
					$where_clauses[] = 'content_id = %d';
					$params[]        = $content_id;
				}
				break;

			default:
				return false;
		}

		$where_sql = implode( ' AND ', $where_clauses );
		$sql       = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
		$query     = $wpdb->prepare( $sql, ...$params );
		$count     = $wpdb->get_var( $query );

		return $count > 0;
	}

	/**
	 * Give a badge by hand. Idempotent: a learner who already holds it is left unchanged.
	 *
	 * The reason column records who awarded it (`manual:<admin id>`).
	 *
	 * @param int    $user_id  Learner.
	 * @param string $slug     Badge slug.
	 * @param int    $admin_id Administrator who awards the badge.
	 * @return bool|null True when awarded now, false when already held, null when it could not be saved.
	 */
	public static function award_badge_manually( $user_id, $slug, $admin_id ) {
		global $wpdb;
		$result = self::locked(
			$user_id,
			static function () use ( $wpdb, $user_id, $slug, $admin_id ) {
				if ( self::achievement_exists( $user_id, 'badge', null, null, null, $slug ) ) {
					return 'held';
				}
				$inserted = $wpdb->insert(
					$wpdb->prefix . 'ohmylms_user_achievement',
					array(
						'user_id'      => $user_id,
						'type'         => 'badge',
						'badge_id'     => $slug,
						'reason'       => 'manual:' . (int) $admin_id,
						'status'       => 'active',
						'date_created' => current_time( 'mysql' ),
					)
				);
				return $inserted ? 'awarded' : 'failed';
			}
		);
		if ( 'awarded' === $result ) {
			do_action( 'ohmylms_after_badge_added', $user_id, $slug, 'badge' );
			return true;
		}
		return 'held' === $result ? false : null;
	}

	/**
	 * Take a badge back. Idempotent: revoking a badge that is not held changes nothing.
	 *
	 * The row stays for the record: its status becomes `revoked` and the reason notes who revoked it and when.
	 *
	 * @param int    $user_id  Learner.
	 * @param string $slug     Badge slug.
	 * @param int    $admin_id Administrator who revokes the badge.
	 * @return int Number of awards revoked.
	 */
	public static function revoke_badge( $user_id, $slug, $admin_id ) {
		global $wpdb;
		$count = self::locked(
			$user_id,
			static function () use ( $wpdb, $user_id, $slug, $admin_id ) {
				$note = sprintf( ' | revoked:%d:%s', (int) $admin_id, gmdate( 'Y-m-d\TH:i:s\Z' ) );
				return $wpdb->query(
					$wpdb->prepare(
						"UPDATE %i SET status = 'revoked', reason = CONCAT(reason, %s) WHERE user_id = %d AND type = 'badge' AND badge_id = %s AND status = 'active'",
						$wpdb->prefix . 'ohmylms_user_achievement',
						$note,
						$user_id,
						$slug
					)
				);
			}
		);
		return (int) $count;
	}

	/**
	 * Add or remove points by hand, once per reference.
	 *
	 * The reference is the idempotency key: repeating a request with the same reference returns the
	 * first result without a second row. The reason records `manual:<reference>:<admin id>:<note>`.
	 * A deduction cannot take the balance below zero.
	 *
	 * @param int    $user_id   Learner.
	 * @param int    $points    Signed whole number, not zero.
	 * @param string $reference Idempotency key.
	 * @param string $note      Why.
	 * @param int    $admin_id  Administrator who adjusts.
	 * @return array|\WP_Error `created` (bool) and the new `balance`.
	 */
	public static function adjust_points( $user_id, $points, $reference, $note, $admin_id ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'ohmylms_user_achievement';
		$result = self::locked(
			$user_id,
			static function () use ( $wpdb, $table, $user_id, $points, $reference, $note, $admin_id ) {
				$prefix = 'manual:' . $reference . ':';
				$found  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE user_id = %d AND type = 'point' AND reason LIKE %s", $table, $user_id, $wpdb->esc_like( $prefix ) . '%' ) );
				if ( (int) $found > 0 ) {
					return array( 'created' => false );
				}
				if ( $points < 0 && Point::get_total_points( $user_id ) + $points < 0 ) {
					return new \WP_Error( 'ohmylms_points_insufficient', __( 'The learner does not have enough points for this deduction.', 'ohmylms' ), array( 'status' => 422 ) );
				}
				$inserted = $wpdb->insert(
					$table,
					array(
						'user_id'      => $user_id,
						'type'         => 'point',
						'points'       => $points,
						'reason'       => $prefix . (int) $admin_id . ':' . $note,
						'status'       => 'active',
						'date_created' => current_time( 'mysql' ),
					)
				);
				return $inserted ? array( 'created' => true ) : new \WP_Error( 'ohmylms_points_adjust_failed', __( 'Could not save the adjustment.', 'ohmylms' ), array( 'status' => 500 ) );
			}
		);
		if ( false === $result ) {
			return new \WP_Error( 'ohmylms_points_busy', __( 'Another update is in progress. Try again.', 'ohmylms' ), array( 'status' => 409 ) );
		}
		if ( is_array( $result ) && $result['created'] ) {
			if ( $points > 0 ) {
				do_action( 'ohmylms_after_point_added', $user_id, $points, 'point' );
			} else {
				do_action( 'ohmylms_after_point_deduct', $user_id, -$points, 'point' );
			}
		}
		return is_array( $result ) ? array_merge( $result, array( 'balance' => Point::get_total_points( $user_id ) ) ) : $result;
	}
}
