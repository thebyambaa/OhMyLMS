<?php // phpcs:ignore WordPress.Files.FileName -- Preserve the established Composer class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Level system Helper class.
 *
 * This class handles the level system settings and functionality.
 *
 * @since 1.0.0
 * @package OhMyLMSPro
 */
namespace OhMyLMS\Engagement;

use OhMyLMS\Engagement\Achievements;

/**
 * Plugin level implementation.
 */
class Level {

	/**
	 * Get the level settings.
	 *
	 * @return array
	 */
	public static function get_rules() {
		return GamificationStore::get( 'levels' );
	}

	/**
	 * Get all levels, lowest threshold first.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_levels() {
		return GamificationStore::levels();
	}

	/**
	 * Whether levels are usable.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function maybe_enable() {
		return Rules::enabled( 'level' );
	}

	/**
	 * Slugs of the levels a learner has reached and not yet been given, lowest first.
	 *
	 * @return array|false
	 * @since 1.0.0
	 * @param mixed $user_id User identifier.
	 */
	public static function maybe_met_rules( $user_id = null ) {
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( ! $user_id || ! self::maybe_enable() ) {
			return false;
		}
		$points   = Point::get_total_points( $user_id );
		$awarded  = array_column( self::get_all_levels_of_a_user( $user_id ), 'slug' );
		$to_award = array();
		foreach ( self::get_levels() as $level ) {
			if ( $level['points'] <= $points && ! in_array( $level['slug'], $awarded, true ) ) {
				$to_award[] = $level['slug'];
			}
		}
		return $to_award ? $to_award : false;
	}

	/**
	 * Add points to a user.
	 *
	 * @param int    $user_id User identifier.
	 * @param mixed  $type Type.
	 * @param mixed  $level_id Level identifier.
	 * @param string $reason Reason.
	 * @param mixed  $course_id Course identifier.
	 * @param mixed  $membership_id Membership identifier.
	 * @param mixed  $content_id Content identifier.
	 * @return bool
	 * @since 1.0.0
	 */
	public static function add_level( $user_id, $type, $level_id, $reason = '', $course_id = null, $membership_id = null, $content_id = null ) {
		if ( ! $user_id || ! get_userdata( $user_id ) || ! is_string( $level_id ) || '' === $level_id || strlen( $level_id ) > 45 ) {
			return false; }
		if ( ! self::maybe_enable() ) {
			return false; // level system is not enabled.
		}

		$data     = array(
			'user_id'       => $user_id,
			'type'          => $type,
			'level_id'      => $level_id,
			'reason'        => $reason,
			'course_id'     => $course_id,
			'membership_id' => $membership_id,
			'content_id'    => $content_id,
			'date_created'  => current_time( 'mysql' ),
		);
		$response = Achievements::insert_achievement( $data );
		if ( $response ) {
			set_transient( 'level_added_for_user_' . $user_id, true, 60 );
			do_action( 'ohmylms_after_level_added', $user_id, $level_id, $type );
		}

		return $response;
	}

	/**
	 * Get the highest level a learner has been given.
	 *
	 * @return array|false
	 * @since 1.0.0
	 * @param mixed $user_id User identifier.
	 */
	public static function get_current_level_of_a_user( $user_id = null ) {
		if ( ! self::maybe_enable() ) {
			return false;
		}
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( ! $user_id ) {
			return false;
		}
		global $wpdb;
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT level_id, MIN(date_created) AS date_created FROM %i WHERE user_id = %d AND type = 'level' AND status = 'active' GROUP BY level_id", $wpdb->prefix . 'ohmylms_user_achievement', $user_id )
		);
		$dates = array();
		foreach ( (array) $rows as $row ) {
			$dates[ (string) $row->level_id ] = $row->date_created;
		}
		$highest = null;
		foreach ( self::get_levels() as $level ) {
			if ( isset( $dates[ $level['slug'] ] ) ) {
				$highest = $level;
			}
		}
		if ( ! $highest ) {
			return false;
		}
		return array(
			'level_id'         => $highest['slug'],
			'level_name'       => $highest['name'],
			'level_color'      => $highest['color'],
			'level_text_color' => $highest['text_color'],
			'date_achieved'    => $dates[ $highest['slug'] ],
		);
	}

	/**
	 * Get the next level to work towards, with a progress summary.
	 *
	 * @param int $user_id User identifier.
	 * @return array|false
	 * @since 1.0.0
	 */
	public static function get_next_level_of_a_user( $user_id = null ) {
		if ( ! self::maybe_enable() ) {
			return false;
		}
		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( ! $user_id ) {
			return false;
		}
		$points = Point::get_total_points( $user_id );
		foreach ( self::get_levels() as $level ) {
			if ( $level['points'] > $points ) {
				return self::format_next_level_data( $level, $points );
			}
		}
		return false;
	}

	/**
	 * Format next level data for templates.
	 *
	 * @param array $next_level     Next level.
	 * @param int   $current_points Current points.
	 * @return array
	 * @since 1.0.0
	 */
	private static function format_next_level_data( $next_level, $current_points ) {
		$required = (int) $next_level['points'];
		return array(
			'level_id'            => $next_level['slug'],
			'level_data'          => $next_level,
			'points_needed'       => max( 0, $required - $current_points ),
			'current_points'      => $current_points,
			'rules'               => array(
				array(
					'type'     => 'points',
					'operator' => '>=',
					'required' => $required,
					'current'  => $current_points,
					'met'      => $current_points >= $required,
				),
			),
			'progress_percentage' => $required > 0 ? min( 99, (int) floor( 100 * $current_points / $required ) ) : 100,
		);
	}

	/**
	 * Get all the levels of a user
	 *
	 * @param int $user_id User identifier.
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_all_levels_of_a_user( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ohmylms_user_achievement';

		$query = $wpdb->prepare(
			'
            SELECT level_id FROM %i
            WHERE user_id = %d
            AND level_id IS NOT NULL
            AND type = %s
            AND status = %s
            ORDER BY date_created DESC
            ',
			$table_name,
			$user_id,
			'level',
			'active'
		);

		$results = $wpdb->get_results( $query );

		$formatted_levels = array();
		if ( is_array( $results ) && ! empty( $results ) ) {
			$levels = array();
			foreach ( self::get_levels() as $level ) {
				$levels[ $level['slug'] ] = $level;
			}
			foreach ( $results as $result ) {
				if ( isset( $levels[ (string) $result->level_id ] ) ) {
					$formatted_levels[] = $levels[ (string) $result->level_id ];
				}
			}
		}
		return $formatted_levels;
	}

	/**
	 * Get the total points for a user.
	 *
	 * @param int $user_id User identifier.
	 * @return int
	 * @since 1.0.0
	 */
	public static function get_total_points( $user_id ) {
		global $wpdb;
		$table_name   = $wpdb->prefix . 'ohmylms_user_achievement';
		$total_points = $wpdb->get_var(
			$wpdb->prepare( 'SELECT SUM(points) FROM %i WHERE user_id = %d AND status = \'active\'', $table_name, $user_id )
		);
		return intval( $total_points );
	}
}
