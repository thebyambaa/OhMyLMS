<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Badge system Helper class.
 *
 * This class handles the Badge system settings and functionality.
 *
 * @since 1.0.0
 * @package OhMyLMSPro
 */
namespace OhMyLMS\Engagement;

use OhMyLMS\Engagement\Achievements;

/**
 * Plugin badge implementation.
 */
class Badge {


	/**
	 * Get badges: the custom badges and, optionally, the system streak badges.
	 *
	 * Each badge carries `image`, the image URL resolved from `image_id`, for templates.
	 *
	 * @param mixed $include_streak Include streak badges; null follows the streak setting.
	 * @return array[]
	 */
	public static function get_badges( $include_streak = null ) {
		$include_streak = null === $include_streak ? StreakSettings::enabled() : $include_streak;
		$badges         = array();
		foreach ( GamificationStore::badges() as $badge ) {
			$url            = $badge['image_id'] ? wp_get_attachment_image_url( $badge['image_id'], 'thumbnail' ) : '';
			$badge['image'] = $url ? $url : '';
			$badges[]       = $badge;
		}
		return array_merge( $badges, $include_streak ? StreakSettings::badges() : array() );
	}

	/**
	 * Get the badge settings.
	 *
	 * @return array
	 */
	public static function get_rules() {
		return GamificationStore::get( 'badges' );
	}

	/**
	 * Whether badges are usable.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function maybe_enable() {
		return Rules::enabled( 'badge' );
	}

	/**
	 * Slugs of the badges a learner qualifies for by criteria.
	 *
	 * @return array
	 * @since 1.0.0
	 * @param mixed $user_id User identifier.
	 */
	public static function maybe_met_rules( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( ! self::maybe_enable() || ! $user_id ) {
			return array();
		}
		$earned = array();
		$cache  = array();
		foreach ( GamificationStore::badges() as $badge ) {
			if ( 'criteria' === $badge['award_source'] && Rules::met( $badge['criteria'], $user_id, $cache ) ) {
				$earned[] = $badge['slug'];
			}
		}
		return $earned;
	}

	/**
	 * Add badge to a user.
	 *
	 * @param int    $user_id User identifier.
	 * @param mixed  $type Type.
	 * @param mixed  $badge_id Badge identifier.
	 * @param string $reason Reason.
	 * @param mixed  $course_id Course identifier.
	 * @param mixed  $membership_id Membership identifier.
	 * @param mixed  $content_id Content identifier.
	 * @param bool   $streak Streak.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function add_badge( $user_id, $type, $badge_id, $reason = '', $course_id = null, $membership_id = null, $content_id = null, $streak = false ) {
		if ( ! $user_id || ! get_userdata( $user_id ) || ! is_string( $badge_id ) || '' === $badge_id || strlen( $badge_id ) > 45 ) {
			return false; }
		if ( ! self::maybe_enable() && ! ( $streak && StreakSettings::enabled() ) ) {
			return false; // Point system is not enabled.
		}

		$data = array(
			'user_id'       => $user_id,
			'type'          => $type,
			'badge_id'      => $badge_id,
			'reason'        => $reason,
			'course_id'     => $course_id,
			'membership_id' => $membership_id,
			'content_id'    => $content_id,
			'date_created'  => current_time( 'mysql' ),
		);

		$response = Achievements::insert_achievement( $data );
		if ( $response ) {
			set_transient( 'badge_added_for_user_' . $user_id, true, 60 );
			do_action( 'ohmylms_after_badge_added', $user_id, $badge_id, $type );
		}
		return $response;
	}


	/**
	 * Get all the badges of a user.
	 *
	 * @param int $user_id The user ID to get badges for.
	 * @return array Array of badge objects or empty array if no badges found.
	 * @since 1.0.0
	 */
	public static function get_all_badges_of_a_user( $user_id ) {
		// Validate user ID.
		if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
			return array();
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'ohmylms_user_achievement';

		// Query to get unique badge IDs for the user.
		$query = $wpdb->prepare(
			'
            SELECT DISTINCT badge_id 
            FROM %i
            WHERE user_id = %d
                AND badge_id IS NOT NULL
                AND type = %s
                AND status = %s
            ORDER BY badge_id ASC
            ',
			$table_name,
			$user_id,
			'badge',
			'active'
		);

		$badge_ids = $wpdb->get_col( $query );

		// Return empty array if no badges found.
		if ( empty( $badge_ids ) || ! is_array( $badge_ids ) ) {
			return array();
		}

		// Get all available badges once.
		$all_badges = self::get_badges( true );
		if ( empty( $all_badges ) || ! is_array( $all_badges ) ) {
			return array();
		}

		// Create a lookup map for O(1) access instead of O(n) nested loops.
		$badge_lookup = array();
		foreach ( $all_badges as $badge ) {
			if ( isset( $badge['slug'] ) && ! empty( $badge['slug'] ) ) {
				$badge_lookup[ $badge['slug'] ] = $badge;
			}
		}

		// Build result array using the lookup map.
		$user_badges = array();
		foreach ( $badge_ids as $badge_id ) {
			if ( isset( $badge_lookup[ $badge_id ] ) ) {
				$user_badges[] = $badge_lookup[ $badge_id ];
			}
		}

		return $user_badges;
	}
}
