<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 class/file mapping; see docs/CODING-STANDARDS.md.
/**
 * Gamification store: reads, validates and saves sections, levels and badges.
 *
 * @package OhMyLMS
 */

namespace OhMyLMS\Engagement;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Persistence and semantic validation for the gamification configuration.
 *
 * Every reader (`Point`, `Level`, `Badge`, `Reward`, `Leaderboard`, `Rules`) goes through this
 * class, so stored data is always normalised against {@see GamificationRegistry}. Writers get a
 * `WP_Error` with status 422 for rules the REST schema cannot express.
 *
 * @since 1.0.0
 */
final class GamificationStore {

	/**
	 * Option holding the level collection.
	 */
	const LEVELS_OPTION = 'ohmylms_levels';

	/**
	 * Option holding the custom badge collection.
	 */
	const BADGES_OPTION = 'ohmylms_badges';

	/**
	 * Image types a badge may use.
	 *
	 * @return string[]
	 */
	public static function image_mime_types() {
		return array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
	}

	/**
	 * Whether the Gamification add-on is switched on in Add-ons.
	 *
	 * @return bool
	 */
	public static function integration_enabled() {
		$integrations = get_option( 'ohmylms_integrations', array() );

		return is_array( $integrations ) && ! empty( $integrations['gamification']['is_enable'] );
	}

	/**
	 * Whether a section is usable: the add-on is on and the section is switched on.
	 *
	 * @param string $section Section id.
	 * @return bool
	 */
	public static function enabled( $section ) {
		$settings = self::get( $section );

		return self::integration_enabled() && ! empty( $settings['enabled'] );
	}

	/**
	 * Read a section, merged with defaults and normalised.
	 *
	 * @param string $section Section id.
	 * @return array
	 */
	public static function get( $section ) {
		$settings = self::read( $section );

		/**
		 * Filters a gamification section after it has been read.
		 *
		 * The hook name follows the group: ohmylms_point_settings, ohmylms_level_settings,
		 * ohmylms_badge_settings, ohmylms_reward_settings or ohmylms_leaderboard_settings.
		 *
		 * @param array $settings Normalised section.
		 */
		$filter_names = array(
			'points'      => 'ohmylms_point_settings',
			'levels'      => 'ohmylms_level_settings',
			'badges'      => 'ohmylms_badge_settings',
			'rewards'     => 'ohmylms_reward_settings',
			'leaderboard' => 'ohmylms_leaderboard_settings',
		);
		$filtered     = apply_filters( $filter_names[ $section ], $settings );

		return is_array( $filtered ) ? $filtered : $settings;
	}

	/**
	 * Read a section as stored, merged with defaults and normalised (no filters).
	 *
	 * @param string $section Section id.
	 * @return array
	 */
	private static function read( $section ) {
		$defaults = GamificationRegistry::defaults( $section );
		$stored   = get_option( GamificationRegistry::option( $section ), array() );
		$stored   = is_array( $stored ) ? $stored : array();
		$settings = $defaults;

		foreach ( $defaults as $key => $default ) {
			if ( 'rules' === $key || ! array_key_exists( $key, $stored ) ) {
				continue;
			}
			$settings[ $key ] = is_bool( $default ) ? (bool) $stored[ $key ] : self::coerce_like( $default, $stored[ $key ] );
		}
		if ( 'points' === $section ) {
			$settings['rules'] = self::merge_rules( $defaults['rules'], isset( $stored['rules'] ) ? $stored['rules'] : array() );
		}
		if ( 'leaderboard' === $section ) {
			if ( ! isset( GamificationRegistry::leaderboard_modes()[ $settings['mode'] ] ) ) {
				$settings['mode'] = $defaults['mode'];
			}
			$settings['threshold'] = max( 0, min( 100, $settings['threshold'] ) );
			$settings['limit']     = max( 1, min( 100, $settings['limit'] ) );
		}

		return $settings;
	}

	/**
	 * Cast a stored scalar to the type of its default.
	 *
	 * @param mixed $default Default value.
	 * @param mixed $value   Stored value.
	 * @return mixed
	 */
	private static function coerce_like( $default, $value ) {
		if ( is_int( $default ) ) {
			return is_numeric( $value ) ? (int) $value : $default;
		}

		return is_string( $default ) && is_string( $value ) ? $value : $default;
	}

	/**
	 * Merge stored point rules into the catalogue, dropping unknown events.
	 *
	 * @param array $defaults Default rules (one per event).
	 * @param mixed $stored   Stored rules.
	 * @return array
	 */
	private static function merge_rules( array $defaults, $stored ) {
		$by_event = array();
		foreach ( (array) $stored as $rule ) {
			if ( is_array( $rule ) && isset( $rule['event'] ) && is_string( $rule['event'] ) ) {
				$by_event[ $rule['event'] ] = $rule;
			}
		}
		$rules = array();
		foreach ( $defaults as $rule ) {
			$saved = isset( $by_event[ $rule['event'] ] ) ? $by_event[ $rule['event'] ] : array();
			if ( isset( $saved['enabled'] ) ) {
				$rule['enabled'] = (bool) $saved['enabled'];
			}
			if ( isset( $saved['points'] ) && is_numeric( $saved['points'] ) ) {
				$rule['points'] = max( 0, min( GamificationRegistry::MAX_POINTS, (int) $saved['points'] ) );
			}
			if ( null !== $rule['threshold'] && isset( $saved['threshold'] ) && is_numeric( $saved['threshold'] ) ) {
				$rule['threshold'] = max( 0, min( 100, (int) $saved['threshold'] ) );
			}
			if ( isset( $saved['email'] ) && is_array( $saved['email'] ) ) {
				$rule['email'] = array(
					'enabled' => ! empty( $saved['email']['enabled'] ),
					'subject' => isset( $saved['email']['subject'] ) && is_string( $saved['email']['subject'] ) ? $saved['email']['subject'] : '',
					'body'    => isset( $saved['email']['body'] ) && is_string( $saved['email']['body'] ) ? $saved['email']['body'] : '',
				);
			}
			$rules[] = $rule;
		}

		return $rules;
	}

	/**
	 * Validate a partial section update against the stored section.
	 *
	 * The REST schema has already checked types and ranges; this applies the rules it cannot
	 * express: unique events, thresholds only where the event has one, and a complete email
	 * when emails are on. Nothing is written.
	 *
	 * @param string $section Section id.
	 * @param array  $input   Validated request body.
	 * @return array|WP_Error The full section to store.
	 */
	public static function prepare( $section, array $input ) {
		$current = self::read( $section );

		if ( 'points' !== $section ) {
			return array_replace( $current, array_intersect_key( $input, $current ) );
		}

		if ( isset( $input['enabled'] ) ) {
			$current['enabled'] = (bool) $input['enabled'];
		}
		if ( ! isset( $input['rules'] ) ) {
			return $current;
		}

		$seen  = array();
		$index = array();
		foreach ( $current['rules'] as $position => $rule ) {
			$index[ $rule['event'] ] = $position;
		}
		foreach ( $input['rules'] as $patch ) {
			$event = $patch['event'];
			if ( isset( $seen[ $event ] ) ) {
				return self::error( 'ohmylms_gamification_duplicate_event', __( 'Each event can appear only once.', 'ohmylms' ), array( 'event' => $event ) );
			}
			$seen[ $event ] = true;
			$rule           = $current['rules'][ $index[ $event ] ];
			$events         = GamificationRegistry::events();
			if ( array_key_exists( 'threshold', $patch ) ) {
				if ( null !== $patch['threshold'] && null === $events[ $event ]['threshold'] ) {
					return self::error( 'ohmylms_gamification_threshold_unsupported', __( 'This event does not have a threshold.', 'ohmylms' ), array( 'event' => $event ) );
				}
				if ( null !== $events[ $event ]['threshold'] ) {
					$rule['threshold'] = null === $patch['threshold'] ? $events[ $event ]['threshold'] : (int) $patch['threshold'];
				}
			}
			foreach ( array( 'enabled' => 'bool', 'points' => 'int' ) as $key => $type ) {
				if ( isset( $patch[ $key ] ) ) {
					$rule[ $key ] = 'bool' === $type ? (bool) $patch[ $key ] : (int) $patch[ $key ];
				}
			}
			if ( isset( $patch['email'] ) ) {
				$rule['email'] = array_replace( $rule['email'], self::clean_email( $patch['email'] ) );
			}
			if ( $rule['email']['enabled'] && ( '' === trim( $rule['email']['subject'] ) || '' === trim( wp_strip_all_tags( $rule['email']['body'] ) ) ) ) {
				return self::error( 'ohmylms_gamification_email_incomplete', __( 'An email needs a subject and a message.', 'ohmylms' ), array( 'event' => $event ) );
			}
			$current['rules'][ $index[ $event ] ] = $rule;
		}

		return $current;
	}

	/**
	 * Sanitise the email part of a point rule.
	 *
	 * @param array $email Email fields from the request.
	 * @return array
	 */
	private static function clean_email( array $email ) {
		$clean = array();
		if ( isset( $email['enabled'] ) ) {
			$clean['enabled'] = (bool) $email['enabled'];
		}
		if ( isset( $email['subject'] ) ) {
			$clean['subject'] = sanitize_text_field( $email['subject'] );
		}
		if ( isset( $email['body'] ) ) {
			$clean['body'] = wp_kses_post( $email['body'] );
		}

		return $clean;
	}

	/**
	 * Store a prepared section.
	 *
	 * @param string $section  Section id.
	 * @param array  $settings Prepared section.
	 * @return bool False when the database write failed.
	 */
	public static function save( $section, array $settings ) {
		if ( 'points' === $section ) {
			foreach ( $settings['rules'] as $position => $rule ) {
				unset( $rule['label'], $rule['description'], $rule['supports_threshold'] );
				$settings['rules'][ $position ] = $rule;
			}
		}
		$option = GamificationRegistry::option( $section );
		if ( get_option( $option, null ) === $settings ) {
			return true;
		}

		return (bool) update_option( $option, $settings );
	}

	/**
	 * Section prepared for a response (display fields added).
	 *
	 * @param string $section Section id.
	 * @return array
	 */
	public static function for_response( $section ) {
		$settings = self::get( $section );
		if ( 'points' === $section ) {
			$settings['rules'] = array_map( array( GamificationRegistry::class, 'describe_rule' ), $settings['rules'] );
		}

		return $settings;
	}

	/**
	 * Build a WP_Error with a 422 status.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param array  $data    Extra data.
	 * @return WP_Error
	 */
	public static function error( $code, $message, array $data = array() ) {
		return new WP_Error( $code, $message, array_merge( array( 'status' => 422 ), $data ) );
	}

	/* ------------------------------------------------------------------ Levels */

	/**
	 * All levels, lowest threshold first.
	 *
	 * @return array[]
	 */
	public static function levels() {
		$stored = get_option( self::LEVELS_OPTION, array() );
		$levels = array();
		foreach ( is_array( $stored ) ? $stored : array() as $level ) {
			$level = self::normalize_level( $level );
			if ( $level ) {
				$levels[ $level['slug'] ] = $level;
			}
		}
		$levels = array_values( $levels );
		usort(
			$levels,
			static function ( $a, $b ) {
				return $a['points'] <=> $b['points'];
			}
		);

		return $levels;
	}

	/**
	 * Normalise one stored level; null when it is unusable.
	 *
	 * @param mixed $level Stored level.
	 * @return array|null
	 */
	private static function normalize_level( $level ) {
		if ( ! is_array( $level ) || empty( $level['slug'] ) || ! is_string( $level['slug'] ) || empty( $level['name'] ) || ! isset( $level['points'] ) || ! is_numeric( $level['points'] ) ) {
			return null;
		}

		return array(
			'slug'        => $level['slug'],
			'name'        => (string) $level['name'],
			'description' => isset( $level['description'] ) ? (string) $level['description'] : '',
			'color'       => self::hex( isset( $level['color'] ) ? $level['color'] : '', '#6e42d3' ),
			'text_color'  => self::hex( isset( $level['text_color'] ) ? $level['text_color'] : '', '#ffffff' ),
			'points'      => max( 0, (int) $level['points'] ),
		);
	}

	/**
	 * Validate and store a level (create when `$slug` is empty).
	 *
	 * @param array  $input Validated request fields.
	 * @param string $slug  Existing level slug, or an empty string to create.
	 * @return array|WP_Error The stored level.
	 */
	public static function save_level( array $input, $slug = '' ) {
		$levels   = self::levels();
		$existing = null;
		foreach ( $levels as $level ) {
			if ( $level['slug'] === $slug ) {
				$existing = $level;
			}
		}
		if ( '' !== $slug && ! $existing ) {
			return new WP_Error( 'ohmylms_level_not_found', __( 'Level not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! $existing && count( $levels ) >= GamificationRegistry::MAX_LEVELS ) {
			return self::error( 'ohmylms_level_limit', __( 'You have reached the maximum number of levels.', 'ohmylms' ) );
		}

		$level = $existing ? $existing : array(
			'slug'        => '',
			'name'        => '',
			'description' => '',
			'color'       => '#6e42d3',
			'text_color'  => '#ffffff',
			'points'      => null,
		);
		foreach ( array( 'name', 'description', 'color', 'text_color' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$level[ $key ] = 'description' === $key ? sanitize_textarea_field( $input[ $key ] ) : sanitize_text_field( $input[ $key ] );
			}
		}
		if ( isset( $input['points'] ) ) {
			$level['points'] = (int) $input['points'];
		}
		if ( '' === trim( $level['name'] ) ) {
			return self::error( 'ohmylms_level_invalid_name', __( 'A level needs a name.', 'ohmylms' ) );
		}
		if ( null === $level['points'] ) {
			return self::error( 'ohmylms_level_invalid_points', __( 'A level needs a points threshold.', 'ohmylms' ) );
		}
		foreach ( $levels as $other ) {
			if ( $other['slug'] !== $level['slug'] && $other['points'] === $level['points'] ) {
				return self::error(
					'ohmylms_level_points_taken',
					__( 'Level thresholds must be strictly increasing. Another level already starts at this number of points.', 'ohmylms' ),
					array( 'conflicting_slug' => $other['slug'] )
				);
			}
		}
		if ( ! $existing ) {
			$level['slug'] = self::unique_slug( GamificationRegistry::slugify( $level['name'] ), array_column( $levels, 'slug' ) );
		}

		$next = array();
		foreach ( $levels as $other ) {
			if ( $other['slug'] !== $level['slug'] ) {
				$next[] = $other;
			}
		}
		$next[] = $level;
		usort(
			$next,
			static function ( $a, $b ) {
				return $a['points'] <=> $b['points'];
			}
		);
		update_option( self::LEVELS_OPTION, $next );

		return $level;
	}

	/**
	 * Delete a level.
	 *
	 * @param string $slug Level slug.
	 * @return array|WP_Error The deleted level.
	 */
	public static function delete_level( $slug ) {
		$remaining = array();
		$deleted   = null;
		foreach ( self::levels() as $level ) {
			if ( $level['slug'] === $slug ) {
				$deleted = $level;
			} else {
				$remaining[] = $level;
			}
		}
		if ( ! $deleted ) {
			return new WP_Error( 'ohmylms_level_not_found', __( 'Level not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		update_option( self::LEVELS_OPTION, $remaining );

		return $deleted;
	}

	/* ------------------------------------------------------------------ Badges */

	/**
	 * Custom badges (not the system streak badges).
	 *
	 * @return array[]
	 */
	public static function badges() {
		$stored = get_option( self::BADGES_OPTION, array() );
		$badges = array();
		foreach ( is_array( $stored ) ? $stored : array() as $badge ) {
			$badge = self::normalize_badge( $badge );
			if ( $badge ) {
				$badges[ $badge['slug'] ] = $badge;
			}
		}

		return array_values( $badges );
	}

	/**
	 * Normalise one stored badge; null when it is unusable.
	 *
	 * @param mixed $badge Stored badge.
	 * @return array|null
	 */
	private static function normalize_badge( $badge ) {
		if ( ! is_array( $badge ) || empty( $badge['slug'] ) || ! is_string( $badge['slug'] ) || empty( $badge['name'] ) ) {
			return null;
		}
		$criteria = array();
		foreach ( isset( $badge['criteria'] ) && is_array( $badge['criteria'] ) ? $badge['criteria'] : array() as $criterion ) {
			if ( Rules::valid( array( $criterion ) ) ) {
				$criteria[] = array(
					'metric'   => $criterion['metric'],
					'operator' => $criterion['operator'],
					'value'    => (int) $criterion['value'],
				);
			}
		}

		return array(
			'slug'         => $badge['slug'],
			'name'         => (string) $badge['name'],
			'description'  => isset( $badge['description'] ) ? (string) $badge['description'] : '',
			'image_id'     => isset( $badge['image_id'] ) ? absint( $badge['image_id'] ) : 0,
			'color'        => self::hex( isset( $badge['color'] ) ? $badge['color'] : '', '#6e42d3' ),
			'award_source' => isset( $badge['award_source'] ) && 'streak' === $badge['award_source'] ? 'streak' : 'criteria',
			'criteria'     => $criteria,
		);
	}

	/**
	 * Validate and store a badge (create when `$slug` is empty).
	 *
	 * @param array  $input Validated request fields.
	 * @param string $slug  Existing badge slug, or an empty string to create.
	 * @return array|WP_Error The stored badge.
	 */
	public static function save_badge( array $input, $slug = '' ) {
		$badges   = self::badges();
		$existing = null;
		foreach ( $badges as $badge ) {
			if ( $badge['slug'] === $slug ) {
				$existing = $badge;
			}
		}
		if ( '' !== $slug && ! $existing ) {
			return new WP_Error( 'ohmylms_badge_not_found', __( 'Badge not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		if ( ! $existing && count( $badges ) >= GamificationRegistry::MAX_BADGES ) {
			return self::error( 'ohmylms_badge_limit', __( 'You have reached the maximum number of badges.', 'ohmylms' ) );
		}

		$badge = $existing ? $existing : array(
			'slug'         => '',
			'name'         => '',
			'description'  => '',
			'image_id'     => 0,
			'color'        => '#6e42d3',
			'award_source' => 'criteria',
			'criteria'     => array(),
		);
		foreach ( array( 'name', 'description', 'color' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$badge[ $key ] = 'description' === $key ? sanitize_textarea_field( $input[ $key ] ) : sanitize_text_field( $input[ $key ] );
			}
		}
		if ( isset( $input['award_source'] ) ) {
			$badge['award_source'] = $input['award_source'];
		}
		if ( isset( $input['criteria'] ) ) {
			$badge['criteria'] = array_map(
				static function ( $criterion ) {
					return array(
						'metric'   => $criterion['metric'],
						'operator' => $criterion['operator'],
						'value'    => (int) $criterion['value'],
					);
				},
				$input['criteria']
			);
		}
		if ( array_key_exists( 'image_id', $input ) ) {
			$badge['image_id'] = absint( $input['image_id'] );
		}

		if ( '' === trim( $badge['name'] ) ) {
			return self::error( 'ohmylms_badge_invalid_name', __( 'A badge needs a name.', 'ohmylms' ) );
		}
		if ( 'streak' === $badge['award_source'] && $badge['criteria'] ) {
			return self::error( 'ohmylms_badge_criteria_not_allowed', __( 'A badge awarded by learning streaks cannot have point or progress criteria.', 'ohmylms' ) );
		}
		if ( 'criteria' === $badge['award_source'] && ( ! $badge['criteria'] || ! Rules::valid( $badge['criteria'] ) ) ) {
			return self::error( 'ohmylms_badge_invalid_criteria', __( 'Define at least one valid badge condition.', 'ohmylms' ) );
		}
		if ( $badge['image_id'] && ! self::is_usable_image( $badge['image_id'] ) ) {
			return self::error( 'ohmylms_badge_invalid_image', __( 'Choose a JPEG, PNG, GIF or WebP image from the media library.', 'ohmylms' ), array( 'image_id' => $badge['image_id'] ) );
		}
		if ( ! $existing ) {
			$taken         = array_merge( array_column( $badges, 'slug' ), array_column( StreakSettings::badges(), 'slug' ) );
			$badge['slug'] = self::unique_slug( GamificationRegistry::slugify( $badge['name'] ), $taken );
		}

		$next = array();
		foreach ( $badges as $other ) {
			$next[] = $other['slug'] === $badge['slug'] ? $badge : $other;
		}
		if ( ! $existing ) {
			$next[] = $badge;
		}
		update_option( self::BADGES_OPTION, $next );

		return $badge;
	}

	/**
	 * Delete a badge. Awards already given stay in the log but are no longer shown.
	 *
	 * @param string $slug Badge slug.
	 * @return array|WP_Error The deleted badge.
	 */
	public static function delete_badge( $slug ) {
		$remaining = array();
		$deleted   = null;
		foreach ( self::badges() as $badge ) {
			if ( $badge['slug'] === $slug ) {
				$deleted = $badge;
			} else {
				$remaining[] = $badge;
			}
		}
		if ( ! $deleted ) {
			return new WP_Error( 'ohmylms_badge_not_found', __( 'Badge not found.', 'ohmylms' ), array( 'status' => 404 ) );
		}
		update_option( self::BADGES_OPTION, $remaining );

		return $deleted;
	}

	/**
	 * Whether an attachment is an allowed raster image.
	 *
	 * @param int $attachment_id Attachment id.
	 * @return bool
	 */
	public static function is_usable_image( $attachment_id ) {
		$post = get_post( $attachment_id );

		return $post && 'attachment' === $post->post_type && in_array( get_post_mime_type( $post ), self::image_mime_types(), true );
	}

	/* ----------------------------------------------------------------- Helpers */

	/**
	 * Return a three- or six-digit hex colour, or a fallback.
	 *
	 * @param mixed  $value    Candidate.
	 * @param string $fallback Fallback colour.
	 * @return string
	 */
	private static function hex( $value, $fallback ) {
		$color = sanitize_hex_color( is_string( $value ) ? $value : '' );

		return $color ? $color : $fallback;
	}

	/**
	 * Make a slug unique among others by appending -2, -3 and so on.
	 *
	 * @param string   $base  Base slug.
	 * @param string[] $taken Slugs in use.
	 * @return string
	 */
	private static function unique_slug( $base, array $taken ) {
		$base      = '' === $base ? 'item' : $base;
		$candidate = $base;
		$number    = 2;
		while ( in_array( $candidate, $taken, true ) ) {
			$candidate = $base . '-' . $number;
			++$number;
		}

		return $candidate;
	}
}
