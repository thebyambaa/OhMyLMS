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
	 * Get the point settings.
	 *
	 * @return array
	 */
	public static function get_rules() {
		$settings = get_option( 'ohmylms_level_settings', array() );
		return apply_filters( 'ohmylms_level_settings', $settings );
	}

	/**
	 * Get all levels.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function get_levels() {
		return get_option( 'ohmylms_levels', array() );
	}

	/**
	 * Maybe enable the point system.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function maybe_enable() {
		return Rules::enabled( 'level' );
	}

	/**
	 * Maybe met rules for a specific event.
	 * Returns the levels that should be assigned to the user, if any.
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

		$levels = self::get_levels();
		if ( empty( $levels ) || ! is_array( $levels ) ) {
			return false;
		}

		// Sort levels by progression.
		$sorted_levels = self::sort_levels_by_progression( $levels );

		// Get current level data.
		$current_level_data = self::get_current_level_of_a_user( $user_id );
		$current_level_slug = $current_level_data ? $current_level_data['level_id'] : null;

		$levels_to_assign = array();

		// If user has no level, find ALL levels they qualify for in progression order.
		if ( ! $current_level_slug ) {
			foreach ( $sorted_levels as $level ) {
				if ( self::user_qualifies_for_level( $user_id, $level ) && isset( $level['slug'] ) ) {
					$levels_to_assign[] = $level['slug'];
				}
			}
			return empty( $levels_to_assign ) ? false : $levels_to_assign;
		}

		// User has a current level, find ALL levels in progression they qualify for after current.
		$found_current = false;
		foreach ( $sorted_levels as $level ) {
			if ( isset( $level['slug'] ) && $level['slug'] === $current_level_slug ) {
				$found_current = true;
				continue; // Skip current level.
			}

			// Only check levels after the current one.
			if ( $found_current ) {
				if ( self::user_qualifies_for_level( $user_id, $level ) && isset( $level['slug'] ) ) {
					$levels_to_assign[] = $level['slug'];
				} else {
					// If user doesn't qualify for this next level, they won't qualify for higher ones.
					break;
				}
			}
		}

		return empty( $levels_to_assign ) ? false : $levels_to_assign;
	}

	/**
	 * Helper to compare values with a sign
	 *
	 * @param mixed $value Value to process.
	 * @param mixed $compare_sign Comparesign.
	 * @param mixed $compare_data Comparedata.
	 */
	private static function compare_with_sign( $value, $compare_sign, $compare_data ) {
		return Rules::compare( $value, $compare_sign, $compare_data );
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
	 * Get current level of a user (highest level achieved).
	 * * @param string $slug
	 *
	 * @return int
	 * @since 1.0.0
	 * @param mixed $user_id User identifier.
	 */
	public static function get_current_level_of_a_user( $user_id = null ) {
		global $wpdb;

		if ( ! self::maybe_enable() ) {
			return false; // Level system is not enabled.
		}

		$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		if ( ! $user_id ) {
			return false; // No user logged in.
		}

		$table_name = $wpdb->prefix . 'ohmylms_user_achievement';

		// Get all user's level achievements.
		$level_achievements = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT level_id, date_created FROM %i
             WHERE user_id = %d 
             AND type = \'level\'
             AND status = \'active\'
             ORDER BY date_created ASC',
				$table_name,
				$user_id
			)
		);

		if ( empty( $level_achievements ) ) {
			return false; // User doesn't have any levels.
		}

		// Get all levels to find the level details and determine highest level.
		$levels = get_option( 'ohmylms_levels', array() );
		if ( empty( $levels ) || ! is_array( $levels ) ) {
			return false;
		}

		// Sort levels by progression to find the highest level.
		$sorted_levels    = self::sort_levels_by_progression( $levels );
		$user_level_slugs = array_map( 'strval', array_column( $level_achievements, 'level_id' ) );

		// Find the highest level the user has achieved.
		$highest_level      = null;
		$highest_level_date = null;

		// Go through sorted levels in reverse order (highest to lowest).
		for ( $i = count( $sorted_levels ) - 1; $i >= 0; $i-- ) {
			$level = $sorted_levels[ $i ];
			if ( isset( $level['slug'] ) && in_array( (string) $level['slug'], $user_level_slugs, true ) ) {
				// Find the date this level was achieved.
				foreach ( $level_achievements as $achievement ) {
					if ( $achievement->level_id === $level['slug'] ) {
						$highest_level      = $level;
						$highest_level_date = $achievement->date_created;
						break 2; // Break both loops.
					}
				}
			}
		}

		if ( ! $highest_level ) {
			return false; // No valid level found.
		}

		return array(
			'level_id'         => $highest_level['slug'],
			'level_name'       => isset( $highest_level['name'] ) ? $highest_level['name'] : '',
			'level_color'      => isset( $highest_level['color'] ) ? $highest_level['color'] : '',
			'level_text_color' => isset( $highest_level['textColor'] ) ? $highest_level['textColor'] : '',
			'date_achieved'    => $highest_level_date,
		);
	}


	/**
	 * Get next level for a user with points and rules information.
	 *
	 * @param int $user_id User identifier.
	 * @return array|false
	 * @since 1.0.0
	 */
	public static function get_next_level_of_a_user( $user_id = null ) {
		global $wpdb;

		if ( ! self::maybe_enable() ) {
			return false; // Level system is not enabled.
		}

		if ( ! $user_id ) {
			$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
		}

		if ( ! $user_id ) {
			return false; // No user logged in.
		}

		// Get current level data.
		$current_level_data = self::get_current_level_of_a_user( $user_id );

		// Get all levels and sort them by minimum points requirement.
		$levels = get_option( 'ohmylms_levels', array() );

		if ( empty( $levels ) || ! is_array( $levels ) ) {
			return false; // No levels configured.
		}

		// Sort levels by minimum points requirement for proper progression.
		$sorted_levels = self::sort_levels_by_progression( $levels );

		$current_points = Point::get_total_points( $user_id );

		// If no current level, find the first level the user should qualify for.
		if ( ! $current_level_data ) {
			foreach ( $sorted_levels as $level ) {
				if ( self::user_qualifies_for_level( $user_id, $level ) ) {
					continue; // User already qualifies for this level.
				}

				// This is the next level they should work towards.
				return self::format_next_level_data( $level, $user_id, $current_points );
			}
			return false; // User doesn't qualify for any level or all levels achieved.
		}

		// Find current level in sorted array and get the next one.
		$current_level_found = false;
		foreach ( $sorted_levels as $index => $level ) {
			if ( $current_level_found ) {
				// This is the next level after current.
				return self::format_next_level_data( $level, $user_id, $current_points );
			}

			if ( isset( $level['slug'] ) && $level['slug'] === $current_level_data['level_id'] ) {
				$current_level_found = true;
			}
		}

		return false; // No next level available (user is at highest level).
	}

	/**
	 * Sort levels by progression based on minimum points requirement.
	 *
	 * @param array $levels Levels.
	 * @return array
	 * @since 1.0.0
	 */
	private static function sort_levels_by_progression( $levels ) {
		usort(
			$levels,
			function ( $a, $b ) {
				$min_points_a = self::get_minimum_points_for_level( $a );
				$min_points_b = self::get_minimum_points_for_level( $b );
				return $min_points_a - $min_points_b;
			}
		);

		return $levels;
	}

	/**
	 * Get minimum points requirement for a level.
	 *
	 * @param array $level Level.
	 * @return int
	 * @since 1.0.0
	 */
	private static function get_minimum_points_for_level( $level ) {
		if ( ! isset( $level['rules'] ) || ! is_array( $level['rules'] ) ) {
			return 0;
		}

		$min_points = 0;
		foreach ( $level['rules'] as $rule ) {
			if ( isset( $rule['dataValue'], $rule['compareData'], $rule['compareSign'] ) && 'points' === $rule['dataValue'] ) {
				// For >= or > operators, this is a minimum requirement.
				if ( in_array( $rule['compareSign'], array( '>=', '>' ), true ) ) {
					$required_points = intval( $rule['compareData'] );
					if ( '>' === $rule['compareSign'] ) {
						++$required_points; // For strict greater than.
					}
					$min_points = max( $min_points, $required_points );
				}
			}
		}

		return $min_points;
	}

	/**
	 * Check if user qualifies for a specific level.
	 *
	 * @param int   $user_id User identifier.
	 * @param array $level Level.
	 * @return bool
	 * @since 1.0.0
	 */
	private static function user_qualifies_for_level( $user_id, $level ) {
		return Rules::met( $level['rules'] ?? array(), $user_id );
	}

	/**
	 * Format next level data for return.
	 *
	 * @param array $next_level Next level.
	 * @param int   $user_id User identifier.
	 * @param int   $current_points Current points.
	 * @return array
	 * @since 1.0.0
	 */
	private static function format_next_level_data( $next_level, $user_id, $current_points ) {
		$points_needed = 0;
		$rules_summary = array();

		if ( Rules::valid( $next_level['rules'] ?? array() ) ) {
			foreach ( $next_level['rules'] as $rule ) {
				if ( isset( $rule['dataValue'], $rule['compareData'], $rule['compareSign'] ) ) {
					$rule_summary = array(
						'type'     => $rule['dataValue'],
						'operator' => $rule['compareSign'],
						'required' => $rule['compareData'],
						'current'  => 0,
						'met'      => false,
					);

					// Get current value based on rule type.
					switch ( $rule['dataValue'] ) {
						case 'points':
							$rule_summary['current'] = $current_points;
							// Calculate points needed for >= or > operators.
							if ( in_array( $rule['compareSign'], array( '>=', '>' ), true ) ) {
								$required_points = intval( $rule['compareData'] );
								if ( '>' === $rule['compareSign'] ) {
									++$required_points;
								}
								$points_needed = max( $points_needed, $required_points - $current_points );
							}
							break;

						case 'completed_courses':
							$student = new \OhMyLMS\Data\Student( $user_id );
							if ( $student ) {
								$completed_courses       = $student->get_completed_course_count();
								$rule_summary['current'] = $completed_courses;
							}
							break;

						case 'completed_lesson':
							$rule_summary['current'] = Rules::value( 'completed_lesson', $user_id );
							break;
					}

					// Check if rule is met.
					$rule_summary['met'] = self::compare_with_sign(
						$rule_summary['current'],
						$rule['compareSign'],
						$rule['compareData']
					);

					$rules_summary[] = $rule_summary;
				}
			}
		}

		return array(
			'level_id'            => $next_level['slug'],
			'level_data'          => $next_level,
			'points_needed'       => max( 0, $points_needed ),
			'current_points'      => $current_points,
			'rules'               => $rules_summary,
			'progress_percentage' => self::rule_progress( $rules_summary ),
		);
	}

	/**
	 * Rule progress for Level.
	 *
	 * @param mixed $rules Rules.
	 */
	private static function rule_progress( $rules ) {
		if ( ! $rules ) {
			return 0; }
		$progress = array();
		foreach ( $rules as $rule ) {
			$required   = (float) $rule['required'] + ( '>' === $rule['operator'] ? 1 : 0 );
			$progress[] = $rule['met'] ? 100 : ( in_array( $rule['operator'], array( '>', '>=', '=', '==' ), true ) && $required > 0 ? min( 99, 100 * $rule['current'] / $required ) : 0 );
		}
		return min( $progress );
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
			$levels = self::get_levels();
			foreach ( $results as $result ) {
				foreach ( $levels as $level ) {
					if ( (string) $result->level_id === (string) $level['slug'] ) {
						$formatted_levels[] = $level;
					}
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
